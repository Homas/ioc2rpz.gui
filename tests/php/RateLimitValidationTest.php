<?php
/**
 * Tests for the server-side rate limit validators in www/io2fun.php:
 * validateRateLimitFields(), and its use inside validateServerFields() /
 * validateRpzFields().
 *
 * The GUI must reject an invalid rate limit rather than write it, because the server
 * only LOGS and ignores an invalid value and then falls back to the next level - so a
 * bad value written by the GUI would silently do nothing at all.
 *
 * Rules under test:
 *   - absent/empty is valid and means inherit
 *   - a present value must be a plain integer
 *   - window must be > 0
 *   - the maximums must be >= 0, and 0 is legal ("refuse every request in that bucket")
 *   - max_unknown_requests is rejected on a feed (server-level only)
 *
 * Each varied field is placed in an otherwise-valid payload so the rate limit is the
 * only possible cause of rejection (the technique used by
 * AttributionValidationPropertyTest).
 *
 * @package ioc2rpz.gui
 */

declare(strict_types=1);

use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RateLimitValidationTest extends TestCase
{
    use TestTrait;

    /** An otherwise-valid server payload. */
    private function serverPayload(array $overrides = []): array
    {
        return array_merge([
            'tSrvName'          => 'srv1',
            'tSrvIP'            => '10.0.0.1',
            'tSrvPubIP'         => '203.0.113.1',
            'tSrvNS'            => 'ns.example.com',
            'tSrvEmail'         => 'admin@example.com',
            'tSrvURL'           => 'ioc2rpz.conf',
            'tSrvMGMTIP'        => '["10.0.0.1"]',
            'tCertFile'         => '',
            'tKeyFile'          => '',
            'tCACertFile'       => '',
            'tCustomConfig'     => '',
            'tSrvTrackDefault'  => 'off',
        ], $overrides);
    }

    /** An otherwise-valid feed payload. */
    private function rpzPayload(array $overrides = []): array
    {
        return array_merge([
            'tRPZName'          => 'feed.example',
            'tRPZNotify'        => '[]',
            'tRPZIOCType'       => 'mixed',
            'tRPZTrackSources'  => 'Inherit',
        ], $overrides);
    }

    /** Values that must be ACCEPTED for a `window` field (min 1). */
    public static function validWindowProvider(): array
    {
        return [
            'absent'      => [null],
            'empty'       => [''],
            'whitespace'  => ['   '],
            'one'         => ['1'],
            'sixty'       => ['60'],
            'large'       => ['86400'],
            'padded'      => [' 60 '],
        ];
    }

    /** Values that must be REJECTED for a `window` field. */
    public static function invalidWindowProvider(): array
    {
        return [
            'zero'        => ['0'],
            'negative'    => ['-1'],
            'decimal'     => ['6.5'],
            'exponent'    => ['1e3'],
            'alpha'       => ['abc'],
            'mixed'       => ['60abc'],
            'injection'   => ['60}]},{extra,1'],
            'list'        => [['60']],
            // Above the cap: the window is stored with every rate limit entry and
            // decides when it is swept, so an absurd value is a slow memory leak.
            'over cap'    => [(string) (RL_WINDOW_MAX + 1)],
            'huge'        => ['999999999999'],
        ];
    }

    /** Values that must be ACCEPTED for a maximum field (min 0). */
    public static function validMaxProvider(): array
    {
        return [
            'absent' => [null],
            'empty'  => [''],
            'zero'   => ['0'],
            'one'    => ['1'],
            'six'    => ['6'],
            'large'  => ['99999'],
        ];
    }

    /** Values that must be REJECTED for a maximum field. */
    public static function invalidMaxProvider(): array
    {
        return [
            'negative'  => ['-1'],
            'decimal'   => ['0.5'],
            'alpha'     => ['many'],
            'injection' => ['0},{window,0'],
            'list'      => [[0]],
        ];
    }

    // -- server level -------------------------------------------------------

    #[DataProvider('validWindowProvider')]
    public function testServerAcceptsValidWindow($value): void
    {
        $data = $this->serverPayload();
        if ($value !== null) {
            $data['tSrvRLWindow'] = $value;
        }
        $result = validateServerFields($data);
        $this->assertTrue($result['valid'], 'window ' . var_export($value, true) . ' should be valid');
    }

    #[DataProvider('invalidWindowProvider')]
    public function testServerRejectsInvalidWindow($value): void
    {
        $result = validateServerFields($this->serverPayload(['tSrvRLWindow' => $value]));
        $this->assertFalse($result['valid'], 'window ' . var_export($value, true) . ' should be rejected');
        $this->assertStringContainsString('rate limit window', $result['error']);
    }

    #[DataProvider('validMaxProvider')]
    public function testServerAcceptsValidMaximums($value): void
    {
        foreach (['tSrvRLMaxRequests', 'tSrvRLMaxUnknownRequests'] as $field) {
            $data = $this->serverPayload();
            if ($value !== null) {
                $data[$field] = $value;
            }
            $this->assertTrue(
                validateServerFields($data)['valid'],
                "$field " . var_export($value, true) . ' should be valid'
            );
        }
    }

    #[DataProvider('invalidMaxProvider')]
    public function testServerRejectsInvalidMaximums($value): void
    {
        foreach (['tSrvRLMaxRequests', 'tSrvRLMaxUnknownRequests'] as $field) {
            $result = validateServerFields($this->serverPayload([$field => $value]));
            $this->assertFalse($result['valid'], "$field " . var_export($value, true) . ' should be rejected');
            $this->assertStringContainsString('rate limit max', $result['error']);
        }
    }

    /** All three options set together on a server is valid. */
    public function testServerAcceptsAllThreeOptions(): void
    {
        $result = validateServerFields($this->serverPayload([
            'tSrvRLWindow'             => '60',
            'tSrvRLMaxRequests'        => '6',
            'tSrvRLMaxUnknownRequests' => '1',
        ]));
        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    /** A server with no rate limit fields at all is valid (unchanged legacy payload). */
    public function testServerWithNoRateLimitFieldsIsValid(): void
    {
        $result = validateServerFields($this->serverPayload());
        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    // -- feed level ---------------------------------------------------------

    #[DataProvider('validWindowProvider')]
    public function testRpzAcceptsValidWindow($value): void
    {
        $data = $this->rpzPayload();
        if ($value !== null) {
            $data['tRPZRLWindow'] = $value;
        }
        $this->assertTrue(validateRpzFields($data)['valid']);
    }

    #[DataProvider('invalidWindowProvider')]
    public function testRpzRejectsInvalidWindow($value): void
    {
        $result = validateRpzFields($this->rpzPayload(['tRPZRLWindow' => $value]));
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('rate limit window', $result['error']);
    }

    #[DataProvider('validMaxProvider')]
    public function testRpzAcceptsValidMaxRequests($value): void
    {
        $data = $this->rpzPayload();
        if ($value !== null) {
            $data['tRPZRLMaxRequests'] = $value;
        }
        $this->assertTrue(validateRpzFields($data)['valid']);
    }

    #[DataProvider('invalidMaxProvider')]
    public function testRpzRejectsInvalidMaxRequests($value): void
    {
        $result = validateRpzFields($this->rpzPayload(['tRPZRLMaxRequests' => $value]));
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('rate limit max', $result['error']);
    }

    /**
     * max_unknown_requests is server-level only: a request counted in that bucket never
     * resolved to a zone, so there is no feed config to read it from. The server logs
     * and ignores it on an rpz, so the GUI rejects it rather than persisting a setting
     * that could never take effect.
     */
    public function testRpzRejectsMaxUnknownRequests(): void
    {
        foreach (['0', '1', '5'] as $value) {
            $result = validateRpzFields($this->rpzPayload(['tRPZRLMaxUnknownRequests' => $value]));
            $this->assertFalse($result['valid'], "max_unknown_requests=$value must be rejected on a feed");
            $this->assertStringContainsString('max_unknown_requests', $result['error']);
            $this->assertStringContainsString('server-level', $result['error']);
        }
    }

    /** An empty max_unknown_requests on a feed is harmless (the field simply is not set). */
    public function testRpzAcceptsEmptyMaxUnknownRequests(): void
    {
        foreach (['', '   '] as $value) {
            $this->assertTrue(
                validateRpzFields($this->rpzPayload(['tRPZRLMaxUnknownRequests' => $value]))['valid'],
                'an empty server-only field must not fail a feed'
            );
        }
    }

    /** One option set while the other inherits is valid at both levels. */
    public function testOneOptionSetWhileTheOtherInheritsIsValid(): void
    {
        $this->assertTrue(validateRpzFields($this->rpzPayload([
            'tRPZRLWindow' => '', 'tRPZRLMaxRequests' => '20',
        ]))['valid']);
        $this->assertTrue(validateRpzFields($this->rpzPayload([
            'tRPZRLWindow' => '30', 'tRPZRLMaxRequests' => '',
        ]))['valid']);
        $this->assertTrue(validateServerFields($this->serverPayload([
            'tSrvRLWindow' => '', 'tSrvRLMaxRequests' => '6', 'tSrvRLMaxUnknownRequests' => '',
        ]))['valid']);
    }

    // -- property -----------------------------------------------------------

    /**
     * Property: for any value, validateRateLimitFields() accepts it exactly when it is
     * absent/empty or an integer within the option's range (at or above the minimum,
     * and at or below the maximum when the option has one), and the rejection message
     * always names the field.
     */
    public function testValidatorMatchesTheRangeRuleForAnyValue(): void
    {
        $this->limitTo(300)
            ->forAll(
                Generator\oneOf(
                    Generator\elements([0, 1, 6, 60, 3600, -1, -60, RL_WINDOW_MAX, RL_WINDOW_MAX + 1, 60000]),
                    Generator\elements(['0', '1', '60', '', '   ', ' 60 ', '-1', '86400', '86401']),
                    Generator\elements(['abc', '6.5', '1e3', '60abc', '60}]},{x,1', 'null']),
                    Generator\constant(null),
                    Generator\string()
                ),
                Generator\elements([0, 1]),
                Generator\elements([null, RL_WINDOW_MAX])
            )
            ->then(function ($value, int $min, ?int $max): void {
                $spec = ['label' => 'test field', 'min' => $min];
                if ($max !== null) {
                    $spec['max'] = $max;
                }
                $data = $value === null ? [] : ['f' => $value];
                $result = validateRateLimitFields($data, ['f' => $spec]);

                // Reference rule, derived from the spec independently of the impl.
                $trimmed = is_array($value) ? null : trim((string) ($value ?? ''));
                if ($value === null || (is_string($value) && $trimmed === '') || $trimmed === '') {
                    $expectValid = true;
                } elseif (is_array($value)) {
                    $expectValid = false;
                } elseif (!preg_match('/^-?[0-9]+$/', $trimmed)) {
                    $expectValid = false;
                } else {
                    $int = (int) $trimmed;
                    $expectValid = $int >= $min && ($max === null || $int <= $max);
                }

                $this->assertSame(
                    $expectValid,
                    $result['valid'],
                    'value ' . var_export($value, true) . " with min=$min max=" . var_export($max, true)
                );

                if ($expectValid) {
                    $this->assertNull($result['error']);
                } else {
                    $this->assertNotNull($result['error']);
                    $this->assertStringContainsString('test field', $result['error']);
                }
            });
    }

    /** Fail-fast: the first invalid field is the one reported. */
    public function testReportsTheFirstInvalidFieldOnly(): void
    {
        $result = validateRateLimitFields(
            ['a' => '-1', 'b' => 'abc'],
            ['a' => ['label' => 'field a', 'min' => 0], 'b' => ['label' => 'field b', 'min' => 0]]
        );
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('field a', $result['error']);
        $this->assertStringNotContainsString('field b', $result['error']);
    }

    /** An empty field set is trivially valid. */
    public function testEmptyFieldSetIsValid(): void
    {
        $result = validateRateLimitFields([], []);
        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }
}
