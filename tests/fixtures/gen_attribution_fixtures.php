<?php
/**
 * Generates the cross-language round-trip fixture for Property 1
 * (Configuration round-trip is identity).
 *
 * It calls the REAL PHP Config_Writer emission helpers in www/io2vars.php
 * (erlSrvTrackSources / erlRpzTrackSources) for every valid attribution state
 * and records the trailing atom each one emits, plus the full srv/rpz tuple
 * line the writer would produce. The JS round-trip test
 * (tests/config-roundtrip-identity.property.test.js) reads this JSON so that it
 * is exercising the ACTUAL PHP writer output, not a hand-copied approximation
 * (the "cross-language fixture check that the JS parser accepts the PHP writer's
 * output" required by task 8.1).
 *
 * Re-generate with (error display suppressed so only JSON reaches stdout):
 *   php -d display_errors=0 -d error_reporting=0 \
 *     tests/fixtures/gen_attribution_fixtures.php \
 *     > tests/fixtures/attribution-roundtrip.fixtures.json
 *
 * @package ioc2rpz.gui
 */

declare(strict_types=1);

require_once __DIR__ . '/../../www/io2vars.php';

// The full srv tuple the writer would emit for a representative server, with the
// trailing TrackSources element supplied by the real helper.
function buildSrvLine(string $track): string
{
    return '{srv,{"ns.example.com","admin.example.com",["mkey1"],["192.0.2.10"]'
        . erlSrvTrackSources($track) . '}}.';
}

// The full rpz tuple the writer would emit for a representative feed, with the
// trailing TrackSources element supplied by the real helper. The 15 legacy
// fields mirror the genConfig() rpz shape (ending with the whitelist list).
function buildRpzLine(string $track): string
{
    return '{rpz,{"feed.example",3600,60,86400,30,"true","true","nxdomain",[],'
        . '"fqdn",900,300,["src1","src2"],[],["wl1"]'
        . erlRpzTrackSources($track) . '}}.';
}

$srv = [];
foreach (['off', 'auto', 'on'] as $track) {
    $srv[] = [
        'track'         => $track,
        'atom'          => erlSrvTrackSources($track), // "", ",auto" or ",on"
        'line'          => buildSrvLine($track),
        'expectedParse' => $track, // JS reader must parse the line back to this
    ];
}

$rpz = [];
foreach (['Inherit', 'auto', 'true', 'false'] as $track) {
    $rpz[] = [
        'track'         => $track,
        'atom'          => erlRpzTrackSources($track), // "", ",auto", ",true", ",false"
        'line'          => buildRpzLine($track),
        'expectedParse' => $track,
    ];
}

echo json_encode(
    ['srv' => $srv, 'rpz' => $rpz],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . "\n";
