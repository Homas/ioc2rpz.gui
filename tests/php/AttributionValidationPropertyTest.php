<?php
/**
 * Property-based test for attribution settings validation (Task 3.3).
 *
 * Feature: ioc-source-attribution, Property 5: Attribution settings validation
 * accepts exactly the allowed members
 *
 * Validates: Requirements 1.3, 1.4, 2.3, 2.4
 *
 * Property (design.md):
 *   For any submitted `tSrvTrackDefault` value, `validateServerFields` SHALL
 *   report valid if and only if the value is absent/empty or an exact,
 *   case-sensitive member of {off, auto, on}, and on rejection SHALL name the
 *   offending value; and for any submitted `tRPZTrackSources` value,
 *   `validateRpzFields` SHALL report valid if and only if the value is
 *   absent/empty or an exact, case-sensitive member of
 *   {Inherit, auto, true, false}, and on rejection SHALL name the value.
 *
 * Both validators check OTHER fields too, so each generated value is placed in
 * an otherwise-valid $data array (a valid server name / RPZ zone name with every
 * other field absent) and ONLY the attribution field is varied. This isolates
 * the attribution rule as the sole possible cause of rejection.
 *
 * Generators deliberately include case-permuted valid words (e.g. `Off`, `AUTO`,
 * `True`, `INHERIT`) as a key edge, alongside exact valid members, random junk,
 * and empty strings; the absent-key case is covered by dedicated example tests.
 *
 * @package ioc2rpz.gui
 */

declare(strict_types=1);

use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

final class AttributionValidationPropertyTest extends TestCase
{
    use TestTrait;

    /** Minimum property iterations mandated by the design (>= 100). */
    private const ITERATIONS = 100;

    /** Exact, case-sensitive valid members for the server-wide default. */
    private const SRV_VALID = ['off', 'auto', 'on'];

    /** Exact, case-sensitive valid members for the per-feed setting. */
    private const RPZ_VALID = ['Inherit', 'auto', 'true', 'false'];

    /**
     * Case-permuted variants of the server-vocabulary words. None of these is a
     * case-sensitive member of SRV_VALID, so every one must be rejected.
     */
    private const SRV_CASE_PERMUTED = [
        'Off', 'OFF', 'oFf', 'ofF',
        'Auto', 'AUTO', 'aUto', 'AUto',
        'On', 'ON', 'oN',
    ];

    /**
     * Case-permuted variants of the feed-vocabulary words. None is a
     * case-sensitive member of RPZ_VALID, so every one must be rejected.
     * (`Inherit` itself is valid, so its permutations exclude the exact form.)
     */
    private const RPZ_CASE_PERMUTED = [
        'inherit', 'INHERIT', 'InHeRiT', 'Inherit ',
        'Auto', 'AUTO',
        'True', 'TRUE', 'tRue',
        'False', 'FALSE', 'fAlse',
    ];

    /** Arbitrary junk that is never a valid member of either vocabulary. */
    private const JUNK = [
        'yes', 'no', 'enabled', 'disabled', '1', '0', 'null', 'none',
        'off auto', ' on', 'on ', 'aut', 'offf', 'inherit-mode', '{off}',
        'trueee', 'fals', 'AutoOn', 'off,auto', "off\n",
    ];

    // ------------------------------------------------------------------
    // Server-wide default: validateServerFields / tSrvTrackDefault
    // ------------------------------------------------------------------

    /**
     * Feature: ioc-source-attribution, Property 5: Attribution settings
     * validation accepts exactly the allowed members (server default).
     *
     * Validates: Requirements 1.3, 1.4
     */
    public function testServerTrackDefaultValidationAcceptsExactlyAllowedMembers(): void
    {
        $this->limitTo(self::ITERATIONS);
        $this->forAll($this->trackValueGenerator(self::SRV_CASE_PERMUTED))
            ->withMaxSize(50)
            ->then(function (string $value): void {
                // Otherwise-valid server payload; only the attribution field varies.
                $data   = ['tSrvName' => 'test-server', 'tSrvTrackDefault' => $value];
                $result = validateServerFields($data);

                $expectedValid = ($value === '' || in_array($value, self::SRV_VALID, true));

                $this->assertSame(
                    $expectedValid,
                    $result['valid'],
                    "tSrvTrackDefault=" . var_export($value, true)
                        . " should be " . ($expectedValid ? 'accepted' : 'rejected')
                );

                if (!$expectedValid) {
                    // Req 1.4: rejection must name the offending value.
                    $this->assertNotNull($result['error'], 'Rejection must carry an error message');
                    $this->assertStringContainsString(
                        $value,
                        (string) $result['error'],
                        'Rejection error must name the offending value'
                    );
                }
            });
    }

    // ------------------------------------------------------------------
    // Per-feed setting: validateRpzFields / tRPZTrackSources
    // ------------------------------------------------------------------

    /**
     * Feature: ioc-source-attribution, Property 5: Attribution settings
     * validation accepts exactly the allowed members (feed setting).
     *
     * Validates: Requirements 2.3, 2.4
     */
    public function testFeedTrackSourcesValidationAcceptsExactlyAllowedMembers(): void
    {
        $this->limitTo(self::ITERATIONS);
        $this->forAll($this->trackValueGenerator(self::RPZ_CASE_PERMUTED))
            ->withMaxSize(50)
            ->then(function (string $value): void {
                // Otherwise-valid RPZ payload; only the attribution field varies.
                $data   = ['tRPZName' => 'feed.example.com', 'tRPZTrackSources' => $value];
                $result = validateRpzFields($data);

                $expectedValid = ($value === '' || in_array($value, self::RPZ_VALID, true));

                $this->assertSame(
                    $expectedValid,
                    $result['valid'],
                    "tRPZTrackSources=" . var_export($value, true)
                        . " should be " . ($expectedValid ? 'accepted' : 'rejected')
                );

                if (!$expectedValid) {
                    // Req 2.4: rejection must name the offending value.
                    $this->assertNotNull($result['error'], 'Rejection must carry an error message');
                    $this->assertStringContainsString(
                        $value,
                        (string) $result['error'],
                        'Rejection error must name the offending value'
                    );
                }
            });
    }

    // ------------------------------------------------------------------
    // Example tests: the absent-key edge (key not present at all).
    // The property generator covers empty-string; these cover true absence.
    // ------------------------------------------------------------------

    /**
     * Req 1.3/1.4 edge: an absent tSrvTrackDefault key is treated as `off`
     * (valid); each exact member is accepted; a case-permuted word is rejected
     * naming the value.
     */
    public function testServerTrackDefaultAbsentAndExactMembers(): void
    {
        // Absent key -> valid (treated as 'off').
        $this->assertTrue(validateServerFields(['tSrvName' => 'test-server'])['valid']);

        // Every exact, case-sensitive member is accepted.
        foreach (self::SRV_VALID as $member) {
            $r = validateServerFields(['tSrvName' => 'test-server', 'tSrvTrackDefault' => $member]);
            $this->assertTrue($r['valid'], "Exact member '$member' must be accepted");
        }

        // A case-permuted valid word is rejected and the error names it.
        $r = validateServerFields(['tSrvName' => 'test-server', 'tSrvTrackDefault' => 'Off']);
        $this->assertFalse($r['valid']);
        $this->assertStringContainsString('Off', (string) $r['error']);
    }

    /**
     * Req 2.3/2.4 edge: an absent tRPZTrackSources key is treated as `Inherit`
     * (valid); each exact member is accepted; a case-permuted word is rejected
     * naming the value.
     */
    public function testFeedTrackSourcesAbsentAndExactMembers(): void
    {
        // Absent key -> valid (treated as 'Inherit').
        $this->assertTrue(validateRpzFields(['tRPZName' => 'feed.example.com'])['valid']);

        // Every exact, case-sensitive member is accepted.
        foreach (self::RPZ_VALID as $member) {
            $r = validateRpzFields(['tRPZName' => 'feed.example.com', 'tRPZTrackSources' => $member]);
            $this->assertTrue($r['valid'], "Exact member '$member' must be accepted");
        }

        // A case-permuted valid word is rejected and the error names it.
        $r = validateRpzFields(['tRPZName' => 'feed.example.com', 'tRPZTrackSources' => 'True']);
        $this->assertFalse($r['valid']);
        $this->assertStringContainsString('True', (string) $r['error']);
    }

    // ------------------------------------------------------------------
    // Generator
    // ------------------------------------------------------------------

    /**
     * Builds a generator over the attribution input space: exact valid members,
     * case-permuted valid words (the key edge for case-sensitivity), curated
     * junk, the empty string, and random junk strings. Random strings are
     * length-bounded and stripped of characters that would collide with a valid
     * member so their expected outcome stays unambiguous.
     *
     * @param string[] $casePermuted Vocabulary-specific case-permuted words.
     * @return Generator
     */
    private function trackValueGenerator(array $casePermuted): Generator
    {
        $curated = array_merge(
            self::SRV_VALID,
            self::RPZ_VALID,
            $casePermuted,
            self::JUNK,
            ['']
        );

        return Generator\oneOf(
            Generator\elements($curated),
            Generator\map(
                static function (string $s): string {
                    // Keep random junk from accidentally equalling a valid member.
                    $trimmed = substr($s, 0, 40);
                    $members = ['off', 'auto', 'on', 'Inherit', 'true', 'false', ''];
                    return in_array($trimmed, $members, true) ? $trimmed . '_x' : $trimmed;
                },
                Generator\string()
            )
        );
    }
}
