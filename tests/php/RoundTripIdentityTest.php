<?php
/**
 * Property-based test for the configuration round-trip identity, PHP side.
 *
 * Feature: ioc-source-attribution, Property 1: Configuration round-trip is
 * identity
 *
 * Property (design.md, Property 1):
 *   For any valid server record and feed record (Global_Track_Default ∈
 *   {off, auto, on}, Feed_Track_Setting ∈ {Inherit, auto, true, false}),
 *   serializing with Config_Writer, parsing with Config_Reader, and serializing
 *   again produces srv/rpz tuples whose field count, TrackSources value, and
 *   every other field are identical. A 4-field srv / 15-field rpz round-trips
 *   with no trailing atom; a 5-field srv / 16-field rpz round-trips with its
 *   TrackSources atom preserved.
 *
 * Validates: Requirements 7.9, 5.1, 5.2, 5.3, 6.1, 6.2, 6.3, 7.1, 7.2, 7.3, 7.4
 *
 * The writer under test is the REAL PHP Config_Writer emission helper
 * (erlSrvTrackSources / erlRpzTrackSources in www/io2vars.php, loaded by the
 * bootstrap). The reader is JS (www/js/io2.js); its trailing-atom mapping is
 * mirrored here as the shared tuple model so the round-trip can be driven
 * entirely within PHP (the JS counterpart lives in
 * tests/config-roundtrip-identity.property.test.js, which additionally consumes
 * the fixtures this project emits from these same PHP helpers). This test
 * asserts the round-trip is an identity for every valid attribution state:
 *
 *   emit (real PHP writer) -> parse (reader model) -> emit again == emit.
 *
 * @package ioc2rpz.gui
 */

declare(strict_types=1);

use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

final class RoundTripIdentityTest extends TestCase
{
    use TestTrait;

    /** Reader model (mirror of io2.js srv mapping): 5th atom absent => off. */
    private function readerParseSrv(?string $atom): string
    {
        if ($atom === null || $atom === '') {
            return 'off'; // Req 7.1
        }
        if (in_array($atom, ['off', 'auto', 'on'], true)) {
            return $atom; // Req 7.2
        }
        return "\0invalid"; // would be a parse error; never reached for valid states
    }

    /** Reader model (mirror of io2.js rpz mapping): 16th atom absent => Inherit. */
    private function readerParseRpz(?string $atom): string
    {
        if ($atom === null || $atom === '') {
            return 'Inherit'; // Req 7.3
        }
        if (in_array($atom, ['auto', 'true', 'false'], true)) {
            return $atom; // Req 7.4
        }
        return "\0invalid";
    }

    /** Extracts the bare trailing atom (without the leading comma) from an emission. */
    private function atomOf(string $emission): ?string
    {
        if ($emission === '') {
            return null;
        }
        // Emission is ",<atom>"; strip the leading comma.
        return substr($emission, 1);
    }

    /** Builds a representative srv tuple line with the given trailing emission. */
    private function buildSrvLine(string $emission): string
    {
        return '{srv,{"ns.example.com","admin.example.com",["mkey1"],["192.0.2.10"]'
            . $emission . '}}.';
    }

    /** Builds a representative 15-field rpz tuple body with the given trailing emission. */
    private function buildRpzLine(string $emission): string
    {
        return '{rpz,{"feed.example",3600,60,86400,30,"true","true","nxdomain",[],'
            . '"fqdn",900,300,["src1","src2"],[],["wl1"]' . $emission . '}}.';
    }

    /**
     * Counts top-level (bracket-depth 0) comma-separated fields inside an Erlang
     * tuple body, ignoring commas nested in "..." strings, [...] lists, or
     * {...}/(...) groups.
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

    private function srvBody(string $line): string
    {
        preg_match('/^\{srv,\{(.*)\}\}\.$/', $line, $m);
        return $m[1] ?? '';
    }

    private function rpzBody(string $line): string
    {
        preg_match('/^\{rpz,\{(.*)\}\}\.$/', $line, $m);
        return $m[1] ?? '';
    }

    /**
     * Feature: ioc-source-attribution, Property 1: Configuration round-trip is
     * identity (srv / Global_Track_Default).
     *
     * Validates: Requirements 7.9, 5.1, 5.2, 5.3, 7.1, 7.2
     */
    public function testSrvRoundTripIsIdentity(): void
    {
        $this->limitTo(100)
            ->forAll(Generator\elements(['off', 'auto', 'on']))
            ->then(function (string $track): void {
                // First serialization with the real writer.
                $emission1 = erlSrvTrackSources($track);
                $line1     = $this->buildSrvLine($emission1);

                // Parse with the reader model.
                $parsed = $this->readerParseSrv($this->atomOf($emission1));
                $this->assertSame($track, $parsed, 'srv track value must survive the round-trip');

                // Second serialization from the parsed value.
                $emission2 = erlSrvTrackSources($parsed);
                $line2     = $this->buildSrvLine($emission2);

                // Whole-line identity => every field incl. TrackSources is identical.
                $this->assertSame($line1, $line2);

                // Field-count identity + arity matches the attribution state.
                $fc1 = $this->topLevelFieldCount($this->srvBody($line1));
                $fc2 = $this->topLevelFieldCount($this->srvBody($line2));
                $this->assertSame($fc1, $fc2);
                $this->assertSame($track === 'off' ? 4 : 5, $fc1);
            });
    }

    /**
     * Feature: ioc-source-attribution, Property 1: Configuration round-trip is
     * identity (rpz / Feed_Track_Setting).
     *
     * Validates: Requirements 7.9, 6.1, 6.2, 6.3, 7.3, 7.4
     */
    public function testRpzRoundTripIsIdentity(): void
    {
        $this->limitTo(100)
            ->forAll(Generator\elements(['Inherit', 'auto', 'true', 'false']))
            ->then(function (string $track): void {
                $emission1 = erlRpzTrackSources($track);
                $line1     = $this->buildRpzLine($emission1);

                $parsed = $this->readerParseRpz($this->atomOf($emission1));
                $this->assertSame($track, $parsed, 'rpz track value must survive the round-trip');

                $emission2 = erlRpzTrackSources($parsed);
                $line2     = $this->buildRpzLine($emission2);

                $this->assertSame($line1, $line2);

                $fc1 = $this->topLevelFieldCount($this->rpzBody($line1));
                $fc2 = $this->topLevelFieldCount($this->rpzBody($line2));
                $this->assertSame($fc1, $fc2);
                $this->assertSame($track === 'Inherit' ? 15 : 16, $fc1);
            });
    }
}
