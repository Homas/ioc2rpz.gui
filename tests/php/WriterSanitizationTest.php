<?php
/**
 * Property-based test for the Config_Writer emission helpers in
 * www/io2vars.php (task 6.1): erlSrvTrackSources() and erlRpzTrackSources().
 *
 * Feature: ioc-source-attribution, Property 2: Writer sanitizes absent or
 * invalid stored track values
 *
 * Property (design.md, Property 2):
 *   For any stored `track_default` value that is NOT one of {auto, on}
 *   (including `off`, empty, null, or arbitrary junk), the writer SHALL emit a
 *   4-field srv tuple with no trailing element; and for any stored
 *   `track_sources` value that is NOT one of {auto, true, false} (including
 *   `Inherit`, empty, null, or arbitrary junk), the writer SHALL emit a
 *   15-field rpz tuple with no trailing element and SHALL never emit an invalid
 *   TrackSources atom.
 *
 * These two helpers are the pure emission points wired into genConfig() (task
 * 6.2); testing them directly avoids needing a live database, while still
 * exercising exactly the sanitization contract the property describes.
 *
 * Validates: Requirements 5.4, 6.4, 10.3, 10.4
 *
 * @package ioc2rpz.gui
 */

declare(strict_types=1);

use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

final class WriterSanitizationTest extends TestCase
{
    use TestTrait;

    /** The only trailing atoms the srv helper may ever emit (or ""). */
    private const SRV_EMISSIONS = ['', ',auto', ',on'];

    /** The only trailing atoms the rpz helper may ever emit (or ""). */
    private const RPZ_EMISSIONS = ['', ',auto', ',true', ',false'];

    /** Stored values that MUST produce a trailing srv atom. */
    private const SRV_EMITTING = ['auto', 'on'];

    /** Stored values that MUST produce a trailing rpz atom. */
    private const RPZ_EMITTING = ['auto', 'true', 'false'];

    /**
     * Candidate stored track values.
     *
     * Deliberately mixes:
     *   - values valid in one vocabulary (auto/on/true/false), so each helper
     *     sees both its own emitting set AND the other helper's atoms (which are
     *     non-emitting for it, e.g. `on` for rpz, `true` for srv);
     *   - the documented non-emitting sentinels `off` and `Inherit`;
     *   - the empty string and null (absent);
     *   - case-permuted look-alikes (`Auto`, `ON`, `True`) — the key edge, since
     *     matching is exact/case-sensitive;
     *   - whitespace-padded look-alikes;
     *   - arbitrary junk strings.
     */
    private function trackValueGenerator()
    {
        return Generator\oneOf(
            Generator\elements(['auto', 'on', 'true', 'false']),
            Generator\elements([
                'off', 'on', 'Inherit', 'inherit', '',
                'Auto', 'AUTO', 'On', 'ON', 'Off', 'OFF',
                'True', 'TRUE', 'False', 'FALSE',
                ' auto', 'auto ', 'on ', ' on', 'auto\n',
                'junk', 'n/a', '0', '1', 'yes', 'no', 'null', 'undefined',
            ]),
            Generator\constant(null),
            Generator\string()
        );
    }

    /**
     * Feature: ioc-source-attribution, Property 2: Writer sanitizes absent or
     * invalid stored track values (srv / Global_Track_Default).
     *
     * Validates: Requirements 5.4, 10.3
     */
    public function testSrvWriterSanitizesTrackDefault(): void
    {
        $this->limitTo(100)
            ->forAll($this->trackValueGenerator())
            ->then(function ($value): void {
                $out = erlSrvTrackSources($value);

                // Never an invalid atom: the emission is always exactly "" or one
                // of the two legal bare atoms.
                $this->assertTrue(
                    in_array($out, self::SRV_EMISSIONS, true),
                    'srv writer emitted an unexpected atom: ' . var_export($out, true)
                );

                $emitting = is_string($value) && in_array($value, self::SRV_EMITTING, true);

                if ($value === 'auto') {
                    $this->assertSame(',auto', $out);
                } elseif ($value === 'on') {
                    $this->assertSame(',on', $out);
                } else {
                    // Any non-emitting value (off, Inherit, "", null, case-permuted,
                    // junk) sanitizes to no trailing element.
                    $this->assertSame('', $out, 'non-emitting value should produce no trailing srv element');
                }

                // A legacy srv tuple has 4 top-level fields; the optional
                // TrackSources element makes it 5. Assert the arity that results
                // from appending the emission to a representative srv body.
                $body   = '"srv-name","admin@example.com",["mkey1"],["192.0.2.0/24"]' . $out;
                $fields = $this->topLevelFieldCount($body);
                $this->assertSame(
                    $emitting ? 5 : 4,
                    $fields,
                    'non-emitting srv value must yield a 4-field tuple'
                );
            });
    }

    /**
     * Feature: ioc-source-attribution, Property 2: Writer sanitizes absent or
     * invalid stored track values (rpz / Feed_Track_Setting).
     *
     * Validates: Requirements 6.4, 10.4
     */
    public function testRpzWriterSanitizesTrackSources(): void
    {
        $this->limitTo(100)
            ->forAll($this->trackValueGenerator())
            ->then(function ($value): void {
                $out = erlRpzTrackSources($value);

                // Never an invalid atom.
                $this->assertTrue(
                    in_array($out, self::RPZ_EMISSIONS, true),
                    'rpz writer emitted an unexpected atom: ' . var_export($out, true)
                );

                $emitting = is_string($value) && in_array($value, self::RPZ_EMITTING, true);

                if ($value === 'auto') {
                    $this->assertSame(',auto', $out);
                } elseif ($value === 'true') {
                    $this->assertSame(',true', $out);
                } elseif ($value === 'false') {
                    $this->assertSame(',false', $out);
                } else {
                    // Any non-emitting value (Inherit, on, off, "", null,
                    // case-permuted, junk) sanitizes to no trailing element.
                    $this->assertSame('', $out, 'non-emitting value should produce no trailing rpz element');
                }

                // A legacy rpz tuple has 15 top-level fields; the optional
                // TrackSources element makes it 16.
                $body   = $this->rpzBody($out);
                $fields = $this->topLevelFieldCount($body);
                $this->assertSame(
                    $emitting ? 16 : 15,
                    $fields,
                    'non-emitting rpz value must yield a 15-field tuple'
                );
            });
    }

    /**
     * Builds a representative 15-field rpz tuple body and appends the (already
     * sanitized) trailing emission. The 15 fields mirror the real genConfig()
     * rpz shape closely enough to make the top-level arity assertion meaningful;
     * the last legacy field is the whitelist list, after which TrackSources goes.
     */
    private function rpzBody(string $trailing): string
    {
        $fields = [
            '"feed-name"',      // 1  name
            '"desc"',           // 2  description
            '3600',             // 3  refresh
            '600',              // 4  retry
            '86400',            // 5  expire
            '30',               // 6  minimum ttl
            'true',             // 7  cache
            '"soa"',            // 8  soa
            '"ns"',             // 9  ns
            '["src1","src2"]',  // 10 sources
            '"action"',         // 11 action
            '3',                // 12 nested field
            '"wildcards"',      // 13
            '"ns_ip"',          // 14
            '["wl1","wl2"]',    // 15 whitelist
        ];

        return implode(',', $fields) . $trailing;
    }

    /**
     * Counts top-level (bracket-depth 0) comma-separated fields inside an Erlang
     * tuple body, ignoring commas nested inside "..." strings, [...] lists, or
     * {...}/(...) groups. Used to assert the emitted tuple's arity.
     */
    private function topLevelFieldCount(string $body): int
    {
        $depth = 0;
        $inStr = false;
        $count = 1;
        $len   = strlen($body);

        for ($i = 0; $i < $len; $i++) {
            $c = $body[$i];

            if ($inStr) {
                if ($c === '"') {
                    $inStr = false;
                }
                continue;
            }

            if ($c === '"') {
                $inStr = true;
            } elseif ($c === '[' || $c === '{' || $c === '(') {
                $depth++;
            } elseif ($c === ']' || $c === '}' || $c === ')') {
                $depth--;
            } elseif ($c === ',' && $depth === 0) {
                $count++;
            }
        }

        return $count;
    }
}
