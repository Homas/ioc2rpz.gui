<?php
/**
 * Property-based tests for the rate limit emission helpers in www/io2vars.php:
 * erlSrvRateLimit(), erlRpzRateLimit(), and the shared erlRateLimitValue().
 *
 * Property: for ANY stored value - absent, empty, null, 0, negative, non-numeric,
 * or hostile - the writer emits either nothing at all or a well-formed
 * `,{rate_limit,[{opt,N},...]}` element containing only in-range integers. It never
 * emits an option that was not set, never emits an out-of-range value, never emits
 * max_unknown_requests on an rpz record, and can never be induced to emit anything
 * that would change the structure of the surrounding tuple.
 *
 * The last point matters because the tuple is assembled by string concatenation: if a
 * stored value could leak arbitrary text into the element, it could inject extra
 * tuple fields or unbalance the braces of the generated configuration.
 *
 * These helpers are the pure emission points wired into genConfig(), so testing them
 * directly exercises the whole sanitization contract without needing a live database.
 *
 * @package ioc2rpz.gui
 */

declare(strict_types=1);

use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

final class RateLimitWriterTest extends TestCase
{
    use TestTrait;

    /**
     * Candidate stored values.
     *
     * Deliberately mixes: legitimate integers and their string forms; the 0 edge (legal
     * for the maximums, out of range for window); negatives; the absent shapes (null,
     * ""); numeric-looking strings that are NOT integers; and injection attempts aimed
     * at the generated Erlang tuple.
     */
    private function valueGenerator()
    {
        return Generator\oneOf(
            Generator\elements([0, 1, 6, 60, 3600, 86400]),
            Generator\elements(['0', '1', '60', ' 60 ', '060']),
            Generator\elements([-1, -60, '-1']),
            Generator\elements([null, '', false]),
            Generator\elements(['abc', '6.5', '1e3', '0x10', 'true', 'null', 'NULL', '6,6']),
            Generator\elements([
                // Injection attempts: extra tuple fields, unbalanced braces, comments.
                '60}]},{extra,1',
                '60},{max_requests,999',
                '1]}]}.',
                "60\n{rpz,{\"evil\"",
                '60%comment',
                '"60"',
                '60;',
            ]),
            Generator\constant([]),
            Generator\string()
        );
    }

    /** Option names legal at each level. */
    private const SRV_OPTIONS = ['window', 'max_requests', 'max_unknown_requests'];
    private const RPZ_OPTIONS = ['window', 'max_requests'];

    /**
     * The only shapes an emission may take: empty, or a rate_limit element whose option
     * list contains nothing but `{name,integer}` pairs from the allowed set.
     */
    private function assertWellFormedEmission(string $out, array $allowedOptions): array
    {
        if ($out === '') {
            return [];
        }

        $this->assertMatchesRegularExpression(
            '/^,\{rate_limit,\[\{[a-z_]+,-?[0-9]+\}(?:,\{[a-z_]+,-?[0-9]+\})*\]\}$/',
            $out,
            'emission must be a well-formed rate_limit element'
        );

        // Balanced braces/brackets - the element can never unbalance the tuple.
        $this->assertSame(
            substr_count($out, '{'),
            substr_count($out, '}'),
            'braces must balance'
        );
        $this->assertSame(
            substr_count($out, '['),
            substr_count($out, ']'),
            'brackets must balance'
        );

        // No character that could terminate or extend the surrounding tuple.
        foreach (['"', '%', "\n", "\r", ';', '.'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $out,
                'emission must not contain ' . var_export($forbidden, true)
            );
        }

        preg_match_all('/\{([a-z_]+),(-?[0-9]+)\}/', $out, $m, PREG_SET_ORDER);
        $parsed = [];
        foreach ($m as $pair) {
            $name = $pair[1];
            $this->assertContains($name, $allowedOptions, "option $name is not legal at this level");
            $this->assertArrayNotHasKey($name, $parsed, "option $name emitted twice");
            $parsed[$name] = (int) $pair[2];
        }

        return $parsed;
    }

    /** Whether a stored value is one the writer must emit, given the option minimum. */
    private function expectedEmitted($value, int $min): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        if (!is_scalar($value)) {
            return null;
        }
        // Surrounding whitespace is tolerated, matching DB_intOrNull() and
        // validateRateLimitFields(), so " 60 " from a form field is a valid 60.
        if (!preg_match('/^\s*-?[0-9]+\s*$/', (string) $value)) {
            return null;
        }
        $int = (int) $value;

        return $int < $min ? null : $int;
    }

    /**
     * erlSrvRateLimit(): every option independently emitted or omitted, always
     * well-formed, never injectable.
     */
    public function testSrvWriterEmitsOnlyWellFormedInRangeOptions(): void
    {
        $this->limitTo(200)
            ->forAll($this->valueGenerator(), $this->valueGenerator(), $this->valueGenerator())
            ->then(function ($window, $maxReq, $maxUnknown): void {
                $out = erlSrvRateLimit([
                    'rl_window'               => $window,
                    'rl_max_requests'         => $maxReq,
                    'rl_max_unknown_requests' => $maxUnknown,
                ]);

                $parsed = $this->assertWellFormedEmission($out, self::SRV_OPTIONS);

                // window: min 1 (a zero-length window is meaningless).
                $expWindow = $this->expectedEmitted($window, 1);
                // maximums: min 0 (0 = refuse every request in that bucket).
                $expMax = $this->expectedEmitted($maxReq, 0);
                $expUnknown = $this->expectedEmitted($maxUnknown, 0);

                $this->assertSame($expWindow, $parsed['window'] ?? null, 'window emission');
                $this->assertSame($expMax, $parsed['max_requests'] ?? null, 'max_requests emission');
                $this->assertSame($expUnknown, $parsed['max_unknown_requests'] ?? null, 'max_unknown emission');

                // Nothing set => nothing emitted, so the tuple stays legacy.
                if ($expWindow === null && $expMax === null && $expUnknown === null) {
                    $this->assertSame('', $out, 'no set option must produce no element');
                }

                // Canonical option order, so the output is deterministic and round-trips.
                if (count($parsed) > 1) {
                    $order = array_keys($parsed);
                    $expectedOrder = array_values(array_intersect(self::SRV_OPTIONS, $order));
                    $this->assertSame($expectedOrder, $order, 'options must be in canonical order');
                }

                // Arity: appending to a 4-field srv body yields 4 or 5 fields, never more.
                $body = '"ns","admin",["mkey1"],["192.0.2.0/24"]' . $out;
                $this->assertSame($out === '' ? 4 : 5, $this->topLevelFieldCount($body));
            });
    }

    /**
     * erlRpzRateLimit(): same contract, and max_unknown_requests is never emitted
     * because it has no meaning at the zone level.
     */
    public function testRpzWriterEmitsOnlyWellFormedInRangeOptionsAndNeverMaxUnknown(): void
    {
        $this->limitTo(200)
            ->forAll($this->valueGenerator(), $this->valueGenerator(), $this->valueGenerator())
            ->then(function ($window, $maxReq, $maxUnknown): void {
                // A stray max_unknown_requests on the record must be ignored entirely.
                $out = erlRpzRateLimit([
                    'rl_window'               => $window,
                    'rl_max_requests'         => $maxReq,
                    'rl_max_unknown_requests' => $maxUnknown,
                ]);

                $parsed = $this->assertWellFormedEmission($out, self::RPZ_OPTIONS);

                $this->assertArrayNotHasKey(
                    'max_unknown_requests',
                    $parsed,
                    'max_unknown_requests is server-level only and must never be emitted on an rpz'
                );
                $this->assertStringNotContainsString('max_unknown_requests', $out);

                $expWindow = $this->expectedEmitted($window, 1);
                $expMax    = $this->expectedEmitted($maxReq, 0);

                $this->assertSame($expWindow, $parsed['window'] ?? null);
                $this->assertSame($expMax, $parsed['max_requests'] ?? null);

                if ($expWindow === null && $expMax === null) {
                    $this->assertSame('', $out, 'no set option must produce no element');
                }

                // Arity: appending to a 15-field rpz body yields 15 or 16 fields.
                $body = $this->rpzBody($out);
                $this->assertSame($out === '' ? 15 : 16, $this->topLevelFieldCount($body));
            });
    }

    /** erlRateLimitValue() is the single normalization point; pin its range rules. */
    public function testRateLimitValueRangeRules(): void
    {
        // window (min 1): 0 and negatives are out of range.
        $this->assertNull(erlRateLimitValue(0, 1));
        $this->assertNull(erlRateLimitValue(-1, 1));
        $this->assertSame(1, erlRateLimitValue(1, 1));
        $this->assertSame(60, erlRateLimitValue('60', 1));

        // maximums (min 0): 0 is legal, negatives are not.
        $this->assertSame(0, erlRateLimitValue(0, 0));
        $this->assertSame(0, erlRateLimitValue('0', 0));
        $this->assertNull(erlRateLimitValue(-1, 0));

        // An upper bound, when the option has one: the cap itself is in range, above it
        // is not, and no maximum means unbounded.
        $this->assertSame(RL_WINDOW_MAX, erlRateLimitValue(RL_WINDOW_MAX, 1, RL_WINDOW_MAX));
        $this->assertNull(erlRateLimitValue(RL_WINDOW_MAX + 1, 1, RL_WINDOW_MAX));
        $this->assertNull(erlRateLimitValue('999999999999', 1, RL_WINDOW_MAX));
        $this->assertSame(999999, erlRateLimitValue(999999, 0));

        // Absent shapes.
        foreach ([null, '', false] as $absent) {
            $this->assertNull(erlRateLimitValue($absent, 0));
        }

        // Non-integers never degrade into a partial number.
        foreach (['abc', '6.5', '1e3', '60}]},{extra,1', '"60"', [], new stdClass()] as $bad) {
            $this->assertNull(erlRateLimitValue($bad, 0), 'non-integer must normalize to null');
        }
    }

    /**
     * A stored window above the cap is never written to a configuration file: the option
     * is omitted so the record inherits, rather than emitting a value that would keep
     * every rate limit entry alive for as long as it lasts.
     */
    public function testOverCapWindowIsOmittedAndTheRestStillEmitted(): void
    {
        $over = RL_WINDOW_MAX + 1;

        $this->assertSame(
            ',{rate_limit,[{max_requests,10}]}',
            erlSrvRateLimit(['rl_window' => $over, 'rl_max_requests' => 10])
        );
        $this->assertSame(
            ',{rate_limit,[{max_requests,10}]}',
            erlRpzRateLimit(['rl_window' => $over, 'rl_max_requests' => 10])
        );

        // Nothing else set: no element at all, so the legacy tuple is unchanged.
        $this->assertSame('', erlSrvRateLimit(['rl_window' => $over]));
        $this->assertSame('', erlRpzRateLimit(['rl_window' => $over]));

        // The cap itself is emitted.
        $this->assertSame(
            ',{rate_limit,[{window,' . RL_WINDOW_MAX . '}]}',
            erlRpzRateLimit(['rl_window' => RL_WINDOW_MAX])
        );

        // The maximums stay uncapped.
        $this->assertSame(
            ',{rate_limit,[{max_requests,1000000}]}',
            erlRpzRateLimit(['rl_max_requests' => 1000000])
        );
    }

    /** A record missing the rl_* keys entirely (pre-upgrade row) emits nothing. */
    public function testAbsentColumnsEmitNothing(): void
    {
        $this->assertSame('', erlSrvRateLimit([]));
        $this->assertSame('', erlRpzRateLimit([]));
        $this->assertSame('', erlSrvRateLimit(['name' => 'srv']));
        $this->assertSame('', erlRpzRateLimit(['name' => 'feed']));
        // Defensive: a non-array (e.g. a failed query) must not raise or emit.
        $this->assertSame('', erlSrvRateLimit(null));
        $this->assertSame('', erlRpzRateLimit('nope'));
    }

    /** The documented macro defaults are never emitted just because they are defaults. */
    public function testDefaultsAreNotEmittedWhenNothingIsStored(): void
    {
        // Emitting the default would convert "inherit" into "explicitly set" and freeze
        // the setting against a future change to the server's compile-time default.
        $this->assertSame('', erlSrvRateLimit([
            'rl_window' => null, 'rl_max_requests' => null, 'rl_max_unknown_requests' => null,
        ]));
        $this->assertSame('', erlRpzRateLimit(['rl_window' => null, 'rl_max_requests' => null]));

        // But a value that HAPPENS to equal the default is still emitted, because the
        // operator set it explicitly.
        $this->assertSame(
            ',{rate_limit,[{window,' . RL_DEFAULT_WINDOW . '}]}',
            erlSrvRateLimit(['rl_window' => RL_DEFAULT_WINDOW])
        );
        $this->assertSame(
            ',{rate_limit,[{max_requests,' . RL_DEFAULT_MAX_REQUESTS . '}]}',
            erlRpzRateLimit(['rl_max_requests' => RL_DEFAULT_MAX_REQUESTS])
        );
    }

    /** Exact expected emissions for the documented examples. */
    public function testCanonicalEmissions(): void
    {
        $this->assertSame(
            ',{rate_limit,[{window,60},{max_requests,6},{max_unknown_requests,1}]}',
            erlSrvRateLimit([
                'rl_window' => 60, 'rl_max_requests' => 6, 'rl_max_unknown_requests' => 1,
            ])
        );
        $this->assertSame(
            ',{rate_limit,[{window,60},{max_requests,20}]}',
            erlRpzRateLimit(['rl_window' => 60, 'rl_max_requests' => 20])
        );
        // One option set, the other inherited.
        $this->assertSame(
            ',{rate_limit,[{max_requests,20}]}',
            erlRpzRateLimit(['rl_window' => null, 'rl_max_requests' => 20])
        );
        // The 0 edge is emitted, not dropped.
        $this->assertSame(
            ',{rate_limit,[{max_requests,0}]}',
            erlRpzRateLimit(['rl_max_requests' => 0])
        );
    }

    /** Builds a representative 15-field rpz body and appends the emission. */
    private function rpzBody(string $trailing): string
    {
        $fields = [
            '"feed.example"', '3600', '60', '86400', '30',
            '"true"', '"true"', '"nxdomain"', '[]',
            '"fqdn"', '900', '300', '["src1","src2"]', '[]', '["wl1"]',
        ];

        return implode(',', $fields) . $trailing;
    }

    /**
     * Counts top-level (depth 0) comma-separated fields in an Erlang tuple body,
     * ignoring commas nested in strings, lists, or tuples.
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
