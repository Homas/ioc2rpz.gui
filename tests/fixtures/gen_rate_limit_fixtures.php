<?php
/**
 * Generates the cross-language round-trip fixture for the DNS rate limit feature.
 *
 * It calls the REAL PHP Config_Writer emission helpers in www/io2vars.php
 * (erlSrvRateLimit / erlRpzRateLimit, plus erlSrvTrackSources /
 * erlRpzTrackSources) for every combination of rate limit state and attribution
 * state, and records the trailing elements each one emits together with the full
 * srv/rpz tuple line the writer would produce.
 *
 * The JS round-trip test (tests/config-roundtrip-identity.property.test.js) reads
 * this JSON so it exercises the ACTUAL PHP writer output rather than a hand-copied
 * approximation, and so the "no rate limit anywhere serializes to the byte-identical
 * legacy tuple" guarantee is checked against the real writer.
 *
 * Re-generate with (error display suppressed so only JSON reaches stdout):
 *   php -d display_errors=0 -d error_reporting=0 \
 *     tests/fixtures/gen_rate_limit_fixtures.php \
 *     > tests/fixtures/rate-limit-roundtrip.fixtures.json
 *
 * @package ioc2rpz.gui
 */

declare(strict_types=1);

require_once __DIR__ . '/../../www/io2vars.php';

/** The 4 mandatory srv fields, matching the genConfig() srv shape. */
const SRV_MANDATORY = '"ns.example.com","admin.example.com",["mkey1"],["192.0.2.10"]';

/** The 15 mandatory rpz fields, matching the genConfig() rpz shape. */
const RPZ_MANDATORY = '"feed.example",3600,60,86400,30,"true","true","nxdomain",[],'
    . '"fqdn",900,300,["src1","src2"],[],["wl1"]';

/**
 * Values exercised per option: null models "not set" (the column is NULL), 0 exercises
 * the "refuse everything" edge that must not collapse to inherit, and a positive value
 * exercises the ordinary case. `window` skips 0 because 0 is out of range for it.
 */
const WINDOW_VALUES = [null, 1, 60];
const MAX_VALUES     = [null, 0, 20];

$srv = [];
foreach (WINDOW_VALUES as $window) {
    foreach (MAX_VALUES as $maxReq) {
        foreach (MAX_VALUES as $maxUnknown) {
            foreach (['off', 'auto', 'on'] as $track) {
                $row = [
                    'track_default'            => $track,
                    'rl_window'                => $window,
                    'rl_max_requests'          => $maxReq,
                    'rl_max_unknown_requests'  => $maxUnknown,
                ];
                $trackAtom = erlSrvTrackSources($track);
                $rlElement = erlSrvRateLimit($row);
                $srv[] = [
                    'stored'    => $row,
                    'trackAtom' => $trackAtom,
                    'rlElement' => $rlElement,
                    'line'      => '{srv,{' . SRV_MANDATORY . $trackAtom . $rlElement . '}}.',
                    // What the JS reader must parse the line back to. Absent options stay
                    // absent (null), never a number, so "inherit" survives the round-trip.
                    'expected'  => [
                        'track'                => $track,
                        'window'               => $window,
                        'max_requests'         => $maxReq,
                        'max_unknown_requests' => $maxUnknown,
                    ],
                ];
            }
        }
    }
}

$rpz = [];
foreach (WINDOW_VALUES as $window) {
    foreach (MAX_VALUES as $maxReq) {
        foreach (['Inherit', 'auto', 'true', 'false'] as $track) {
            $item = [
                'track_sources'   => $track,
                'rl_window'       => $window,
                'rl_max_requests' => $maxReq,
            ];
            $trackAtom = erlRpzTrackSources($track);
            $rlElement = erlRpzRateLimit($item);
            $rpz[] = [
                'stored'    => $item,
                'trackAtom' => $trackAtom,
                'rlElement' => $rlElement,
                'line'      => '{rpz,{' . RPZ_MANDATORY . $trackAtom . $rlElement . '}}.',
                'expected'  => [
                    'track'        => $track,
                    'window'       => $window,
                    'max_requests' => $maxReq,
                ],
            ];
        }
    }
}

// The exact legacy lines a config with no rate limit and no attribution must produce.
// Byte-identical equality against these pins the backwards-compatibility guarantee.
$legacy = [
    'srv' => '{srv,{' . SRV_MANDATORY . '}}.',
    'rpz' => '{rpz,{' . RPZ_MANDATORY . '}}.',
];

echo json_encode(
    ['srv' => $srv, 'rpz' => $rpz, 'legacy' => $legacy],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . "\n";
