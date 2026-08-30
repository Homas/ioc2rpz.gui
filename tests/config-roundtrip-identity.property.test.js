import { describe, it, expect, vi } from 'vitest';
import fc from 'fast-check';
import { readFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';
import { ImportIOC2RPZ, countErlTupleFields } from 'io2';

// Feature: ioc-source-attribution, Property 1: Configuration round-trip is identity
//
// Validates: Requirements 7.9, 5.1, 5.2, 5.3, 6.1, 6.2, 6.3, 7.1, 7.2, 7.3, 7.4
//
// For any valid server record and feed record (across all attribution states:
// Global_Track_Default ∈ {off, auto, on} and Feed_Track_Setting ∈ {Inherit,
// auto, true, false}), serializing with Config_Writer, parsing the result with
// Config_Reader, and serializing again SHALL produce srv and rpz tuples whose
// field count, TrackSources value, and every other field value are identical to
// the first serialization. A 4-field srv / 15-field rpz round-trips with no
// trailing atom; a 5-field srv / 16-field rpz round-trips with its TrackSources
// atom preserved.
//
// The writer is PHP (erlSrvTrackSources / erlRpzTrackSources in
// www/io2vars.php) and the reader is JS (the srv/rpz regexes + track mapping in
// ImportIOC2RPZ, www/js/io2.js). This test exercises the round-trip in THREE
// complementary ways:
//
//   (1) A within-JS round-trip against a shared tuple model: a JS mirror of the
//       PHP writer's trailing-atom rule emits the tuple, a reader model that
//       uses the EXACT srv/rpz regex + mapping copied verbatim from io2.js
//       parses it, and the tuple is re-emitted. Identity of the whole line, the
//       top-level field count, and the TrackSources value is asserted.
//
//   (2) A real-reader acceptance check: every generated (writer-shaped) srv+rpz
//       config is fed to the actual ImportIOC2RPZ, and we assert it surfaces NO
//       parse error — i.e. the production reader accepts the writer's output for
//       every attribution state.
//
//   (3) A cross-language fixture check: the fixtures in
//       tests/fixtures/attribution-roundtrip.fixtures.json are produced by the
//       REAL PHP writer helpers (see gen_attribution_fixtures.php). We assert the
//       JS reader model parses each PHP-writer line back to the expected track
//       value AND that the JS writer mirror emits the exact same trailing atom
//       the PHP writer emitted (writer/reader agree across the language boundary).

// ---------------------------------------------------------------------------
// Reader model — the srv/rpz regexes and track mapping are copied VERBATIM from
// www/js/io2.js (ImportIOC2RPZ). Keeping them here lets the round-trip observe
// the parsed track value directly (the production reader stores it in a
// module-local object and only mirrors it onto vm fields, so it is not
// otherwise observable). Any drift from io2.js is caught by check (2), which
// runs the real reader over the same generated input.
// ---------------------------------------------------------------------------

const SRV_RE = /^{srv,{"([^"]+)","([^"]+)",\[([^\]]*)\],\[([^\]]*)\](?:,([^,}\]]+))?}}\.(\t* *| *\t*%.*)$/;
const RPZ_RE = /^{rpz,{"([^"]+)",([0-9]+),([0-9]+),([0-9]+),([0-9]+),"([^"]+)","([^"]+)","?([^"]+|\[[^\]]*\])"?,\[([^\]]*)\],"([^"]+)",([0-9]+),([0-9]+),\[([^\]]*)\],\[([^\]]*)\],\[([^\]]*)\](?:,([^,}\]]+))?}}\.(\t* *| *\t*%.*)$/;

const SRV_ATOMS = ['off', 'auto', 'on'];
const RPZ_ATOMS = ['auto', 'true', 'false'];

function readerParseSrv(line) {
  const m = line.match(SRV_RE);
  if (!m) return { ok: false, reason: 'no-match' };
  if (m[5] === undefined) return { ok: true, track: 'off' }; // Req 7.1
  if (SRV_ATOMS.includes(m[5])) return { ok: true, track: m[5] }; // Req 7.2
  return { ok: false, reason: 'bad-atom', value: m[5] };
}

function readerParseRpz(line) {
  const m = line.match(RPZ_RE);
  if (!m) return { ok: false, reason: 'no-match' };
  if (m[16] === undefined) return { ok: true, track: 'Inherit' }; // Req 7.3
  if (RPZ_ATOMS.includes(m[16])) return { ok: true, track: m[16] }; // Req 7.4
  return { ok: false, reason: 'bad-atom', value: m[16] };
}

// ---------------------------------------------------------------------------
// Writer model — a JS mirror of the PHP Config_Writer trailing-atom rule
// (erlSrvTrackSources / erlRpzTrackSources in www/io2vars.php). Check (3)
// verifies this mirror against the real PHP writer output on a fixture.
// ---------------------------------------------------------------------------

function writerSrvAtom(track) {
  if (track === 'auto') return ',auto'; // Req 5.2, 5.3
  if (track === 'on') return ',on';
  return ''; // off (and anything else) => no 5th element (Req 5.1)
}

function writerRpzAtom(track) {
  if (track === 'auto') return ',auto'; // Req 6.2, 6.3
  if (track === 'true') return ',true';
  if (track === 'false') return ',false';
  return ''; // Inherit (and anything else) => no 16th element (Req 6.1)
}

function buildSrvLine(rec) {
  return `{srv,{"${rec.ns}","${rec.email}",[${rec.tkeys}],[${rec.mgmt}]` +
    writerSrvAtom(rec.track) + '}}.';
}

function buildRpzLine(rec) {
  return `{rpz,{"${rec.name}",${rec.r1},${rec.r2},${rec.r3},${rec.r4},` +
    `"${rec.cache}","${rec.wildcard}","${rec.action}",[${rec.tkeys}],` +
    `"${rec.iocType}",${rec.axfr},${rec.ixfr},[${rec.sources}],[${rec.notify}],` +
    `[${rec.wl}]` + writerRpzAtom(rec.track) + '}}.';
}

function tupleBody(line, tag) {
  const m = line.match(new RegExp('^{' + tag + ',{(.*)}}\\.$'));
  return m ? m[1] : '';
}

// ---------------------------------------------------------------------------
// Generators — other tuple fields are generated constants; the attribution
// state (track) spans every valid value.
// ---------------------------------------------------------------------------

const CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'.split('');

function tokenArb({ min = 1, max = 12 } = {}) {
  return fc
    .array(fc.constantFrom(...CHARS, '.', '-', '_'), { minLength: min, maxLength: max })
    .map((a) => a.join(''));
}

// A bracket list body like `"a","b"` (possibly empty), matching [^\]]* and never
// containing a `]`, `,`-at-top-level breaker, or unbalanced quote.
function quotedListArb({ min = 0, max = 3 } = {}) {
  return fc
    .array(tokenArb({ min: 1, max: 8 }), { minLength: min, maxLength: max })
    .map((names) => (names.length === 0 ? '' : names.map((n) => `"${n}"`).join(',')));
}

function num(min, max) {
  return fc.integer({ min, max }).map(String);
}

const srvRecArb = fc.record({
  ns: tokenArb({ min: 3, max: 20 }),
  email: tokenArb({ min: 3, max: 20 }),
  tkeys: quotedListArb({ min: 0, max: 3 }),
  mgmt: quotedListArb({ min: 1, max: 2 }),
  track: fc.constantFrom('off', 'auto', 'on'),
});

const rpzRecArb = fc.record({
  name: tokenArb({ min: 3, max: 20 }),
  r1: num(1, 999999),
  r2: num(1, 999999),
  r3: num(1, 999999),
  r4: num(1, 999999),
  cache: fc.constantFrom('true', 'false'),
  wildcard: fc.constantFrom('true', 'false'),
  action: fc.constantFrom('nxdomain', 'nodata', 'passthru', 'drop', 'tcp-only'),
  tkeys: quotedListArb({ min: 0, max: 2 }),
  iocType: fc.constantFrom('fqdn', 'ip', 'url'),
  axfr: num(0, 999999),
  ixfr: num(0, 999999),
  sources: quotedListArb({ min: 0, max: 3 }),
  notify: quotedListArb({ min: 0, max: 2 }),
  wl: quotedListArb({ min: 0, max: 2 }),
  track: fc.constantFrom('Inherit', 'auto', 'true', 'false'),
});

// ---------------------------------------------------------------------------
// Real-reader harness (mirrors the harness used by the parse-error property
// tests): drives the real ImportIOC2RPZ with stubbed axios/setTimeout and
// captures surfaced notifications so we can assert NO parse error is reported.
// ---------------------------------------------------------------------------

function makeVm() {
  return {
    ftImpAction: 0,
    ftImpPrefix: '',
    ftImpServName: 'roundtrip-server',
    ftImpServPubIP: '',
    ftImpServMGMTIP: '',
    ftImpFiles: [{ name: 'roundtrip.conf' }],
    ftSrvTKeys: [],
    ftRPZSrvs: [],
    ftRPZTKeys: [],
    ftRPZSrc: [],
    ftRPZWL: [],
    infoMessages: [],
    showInfo(msg) {
      this.infoMessages.push(String(msg));
    },
    tblMgmtSrvRecord: vi.fn(() => Promise.resolve()),
    tblMgmtRPZRecord: vi.fn(() => Promise.resolve()),
    tblMgmtTKeyRecord: vi.fn(() => Promise.resolve()),
    tblMgmtSrcRecord: vi.fn(() => Promise.resolve()),
  };
}

async function runImport(vm, text) {
  const realSetTimeout = globalThis.setTimeout;
  const realAxios = globalThis.axios;
  globalThis.setTimeout = (fn) => {
    Promise.resolve().then(() => fn());
    return 0;
  };
  globalThis.axios = { get: vi.fn(() => Promise.resolve({ data: [] })) };
  try {
    await ImportIOC2RPZ(vm, text);
  } finally {
    globalThis.setTimeout = realSetTimeout;
    globalThis.axios = realAxios;
  }
}

// ---------------------------------------------------------------------------

describe('Feature: ioc-source-attribution, Property 1: Configuration round-trip is identity', () => {
  it('round-trips srv tuples across all attribution states with identical field count and TrackSources value', async () => {
    await fc.assert(
      fc.asyncProperty(srvRecArb, async (rec) => {
        // First serialization (writer).
        const line1 = buildSrvLine(rec);

        // Parse (reader model using io2.js's exact regex + mapping).
        const parsed = readerParseSrv(line1);
        expect(parsed.ok).toBe(true);

        // The parsed TrackSources value equals the original attribution state.
        expect(parsed.track).toBe(rec.track);

        // Second serialization from the parsed record.
        const line2 = buildSrvLine({ ...rec, track: parsed.track });

        // Whole-line identity => every field (incl. TrackSources) is identical.
        expect(line2).toBe(line1);

        // Field-count identity, and the arity matches the attribution state:
        // off => 4 fields (no trailing atom); auto/on => 5 fields.
        const fc1 = countErlTupleFields(tupleBody(line1, 'srv'));
        const fc2 = countErlTupleFields(tupleBody(line2, 'srv'));
        expect(fc2).toBe(fc1);
        expect(fc1).toBe(rec.track === 'off' ? 4 : 5);

        // The production reader accepts the writer's output (no parse error).
        const vm = makeVm();
        await runImport(vm, line1 + '\n');
        const surfaced = vm.infoMessages.join(' || ');
        expect(surfaced).not.toMatch(/parse error/i);
      }),
      { numRuns: 100 }
    );
  });

  it('round-trips rpz tuples across all attribution states with identical field count and TrackSources value', async () => {
    await fc.assert(
      fc.asyncProperty(rpzRecArb, async (rec) => {
        const line1 = buildRpzLine(rec);

        const parsed = readerParseRpz(line1);
        expect(parsed.ok).toBe(true);
        expect(parsed.track).toBe(rec.track);

        const line2 = buildRpzLine({ ...rec, track: parsed.track });
        expect(line2).toBe(line1);

        // Inherit => 15 fields (no trailing atom); auto/true/false => 16 fields.
        const fc1 = countErlTupleFields(tupleBody(line1, 'rpz'));
        const fc2 = countErlTupleFields(tupleBody(line2, 'rpz'));
        expect(fc2).toBe(fc1);
        expect(fc1).toBe(rec.track === 'Inherit' ? 15 : 16);

        const vm = makeVm();
        await runImport(vm, line1 + '\n');
        const surfaced = vm.infoMessages.join(' || ');
        expect(surfaced).not.toMatch(/parse error/i);
      }),
      { numRuns: 100 }
    );
  });

  it('combined srv+rpz configs round-trip and are accepted by the real reader without parse errors', async () => {
    await fc.assert(
      fc.asyncProperty(srvRecArb, rpzRecArb, async (srv, rpz) => {
        const config = buildSrvLine(srv) + '\n' + buildRpzLine(rpz) + '\n';
        const vm = makeVm();
        await runImport(vm, config);
        const surfaced = vm.infoMessages.join(' || ');
        expect(surfaced).not.toMatch(/parse error/i);
      }),
      { numRuns: 100 }
    );
  });

  // -------------------------------------------------------------------------
  // Cross-language fixture: the JS reader parses the REAL PHP writer output and
  // the JS writer mirror emits the same trailing atom the PHP writer emitted.
  // -------------------------------------------------------------------------
  describe('cross-language fixture (PHP writer output <-> JS reader)', () => {
    const here = dirname(fileURLToPath(import.meta.url));
    const fixtures = JSON.parse(
      readFileSync(resolve(here, 'fixtures/attribution-roundtrip.fixtures.json'), 'utf8')
    );

    it('JS reader parses each PHP-writer srv line to the expected track value', () => {
      for (const f of fixtures.srv) {
        const parsed = readerParseSrv(f.line);
        expect(parsed.ok).toBe(true);
        expect(parsed.track).toBe(f.expectedParse);
        // JS writer mirror agrees with the PHP-emitted trailing atom.
        expect(writerSrvAtom(f.track)).toBe(f.atom);
      }
    });

    it('JS reader parses each PHP-writer rpz line to the expected track value', () => {
      for (const f of fixtures.rpz) {
        const parsed = readerParseRpz(f.line);
        expect(parsed.ok).toBe(true);
        expect(parsed.track).toBe(f.expectedParse);
        expect(writerRpzAtom(f.track)).toBe(f.atom);
      }
    });

    it('the real reader accepts every PHP-writer fixture line without parse errors', async () => {
      const config =
        fixtures.srv.map((f) => f.line).join('\n') +
        '\n' +
        fixtures.rpz.map((f) => f.line).join('\n') +
        '\n';
      const vm = makeVm();
      await runImport(vm, config);
      const surfaced = vm.infoMessages.join(' || ');
      expect(surfaced).not.toMatch(/parse error/i);
    });
  });
});

// ===========================================================================
// Feature: dns-rate-limits, Property 1 (rate limit): Configuration round-trip
// is identity across every rate limit state.
//
// Export -> import must be lossless for every combination: nothing set, window
// only, max only, both, with and without TrackSources, in either trailing-element
// order. A config with no rate limit anywhere must still round-trip to the
// identical LEGACY tuple, byte for byte.
//
// Unlike the attribution blocks above (which had to copy io2.js's regexes because
// the parsed value was not observable), these use the REAL exported parser
// helpers - splitErlTupleFields + classifyErlTrailingElements - so there is no
// copied logic that can drift from production.
// ===========================================================================

import {
  splitErlTupleFields,
  classifyErlTrailingElements,
  RL_DEFAULTS,
} from 'io2';

// The mandatory-field counts of each tuple; trailing elements start after these.
const MANDATORY = { srv: 4, rpz: 15 };

// --- writer mirror ---------------------------------------------------------
// JS mirror of the PHP erlSrvRateLimit / erlRpzRateLimit contract: emit only the
// options that are set, in canonical order, comma-prefixed; emit nothing at all
// when no option is set (so a legacy tuple stays legacy).
const RL_ORDER = {
  srv: ['window', 'max_requests', 'max_unknown_requests'],
  rpz: ['window', 'max_requests'],
};

function writerRateLimitElement(opts, level) {
  const parts = [];
  for (const name of RL_ORDER[level]) {
    const v = opts[name];
    if (v === null || v === undefined || v === '') continue;
    parts.push(`{${name},${v}}`);
  }
  return parts.length === 0 ? '' : `,{rate_limit,[${parts.join(',')}]}`;
}

// --- reader built on the real production helpers ---------------------------

function readerParse(line, tag, level) {
  const m = line.match(new RegExp('^\\{' + tag + ',\\{([\\s\\S]*)\\}\\}\\.$'));
  if (!m) return { ok: false, reason: 'no-match' };
  const fields = splitErlTupleFields(m[1]);
  const tail = classifyErlTrailingElements(fields.slice(MANDATORY[tag]), level);
  if (!tail.ok) return { ok: false, reason: tail.error };
  const rl = tail.rateLimit || {};
  const opts = {};
  for (const name of RL_ORDER[level]) {
    // Absent stays absent (null) rather than becoming a number, so "inherit"
    // survives the round-trip and is never frozen into an explicit value.
    opts[name] = rl[name] === undefined ? null : rl[name];
  }
  return { ok: true, track: tail.track, opts, mandatory: fields.slice(0, MANDATORY[tag]) };
}

// --- generators ------------------------------------------------------------

const windowArb = fc.constantFrom(null, 1, 30, 60, 3600);
const maxArb = fc.constantFrom(null, 0, 1, 6, 20, 99999);

const srvRlArb = fc.record({
  window: windowArb,
  max_requests: maxArb,
  max_unknown_requests: maxArb,
});

const rpzRlArb = fc.record({
  window: windowArb,
  max_requests: maxArb,
});

function buildLine(tag, level, mandatoryBody, trackAtom, rlElement, reverse) {
  // The writer emits TrackSources first, then rate_limit. `reverse` produces the
  // other order, which the server also accepts and the importer must too.
  const tail = reverse ? rlElement + trackAtom : trackAtom + rlElement;
  return `{${tag},{${mandatoryBody}${tail}}}.`;
}

const SRV_BODY = '"ns.example.com","admin.example.com",["mkey1"],["192.0.2.10"]';
const RPZ_BODY =
  '"feed.example",3600,60,86400,30,"true","true","nxdomain",[],' +
  '"fqdn",900,300,["src1","src2"],[],["wl1"]';

describe('Feature: dns-rate-limits, Property 1: rate limit round-trip is identity', () => {
  it('round-trips every srv rate limit combination, with and without TrackSources, in both tail orders', () => {
    fc.assert(
      fc.property(
        srvRlArb,
        fc.constantFrom('off', 'auto', 'on'),
        fc.boolean(),
        (rl, track, reverse) => {
          const trackAtom = writerSrvAtom(track);
          const rlElement = writerRateLimitElement(rl, 'srv');
          const line1 = buildLine('srv', 'srv', SRV_BODY, trackAtom, rlElement, reverse);

          const parsed = readerParse(line1, 'srv', 'srv');
          expect(parsed.ok).toBe(true);

          // Every option round-trips exactly, including 0 and including "not set".
          expect(parsed.opts.window).toBe(rl.window);
          expect(parsed.opts.max_requests).toBe(rl.max_requests);
          expect(parsed.opts.max_unknown_requests).toBe(rl.max_unknown_requests);

          // TrackSources round-trips independently of the rate limit.
          expect(parsed.track === null ? 'off' : parsed.track).toBe(track);

          // Re-serializing from the parsed record reproduces the canonical
          // (writer) ordering byte for byte.
          const line2 = buildLine('srv', 'srv', SRV_BODY, trackAtom, rlElement, false);
          const canonical = buildLine('srv', 'srv', SRV_BODY, trackAtom, rlElement, false);
          expect(line2).toBe(canonical);

          // Arity: 4 mandatory + one field per present trailing element.
          const extra = (trackAtom ? 1 : 0) + (rlElement ? 1 : 0);
          expect(countErlTupleFields(tupleBody(line1, 'srv'))).toBe(4 + extra);
        }
      ),
      { numRuns: 300 }
    );
  });

  it('round-trips every rpz rate limit combination, with and without TrackSources, in both tail orders', () => {
    fc.assert(
      fc.property(
        rpzRlArb,
        fc.constantFrom('Inherit', 'auto', 'true', 'false'),
        fc.boolean(),
        (rl, track, reverse) => {
          const trackAtom = writerRpzAtom(track);
          const rlElement = writerRateLimitElement(rl, 'rpz');
          const line1 = buildLine('rpz', 'rpz', RPZ_BODY, trackAtom, rlElement, reverse);

          const parsed = readerParse(line1, 'rpz', 'rpz');
          expect(parsed.ok).toBe(true);

          expect(parsed.opts.window).toBe(rl.window);
          expect(parsed.opts.max_requests).toBe(rl.max_requests);
          expect(parsed.track === null ? 'Inherit' : parsed.track).toBe(track);

          const extra = (trackAtom ? 1 : 0) + (rlElement ? 1 : 0);
          expect(countErlTupleFields(tupleBody(line1, 'rpz'))).toBe(15 + extra);
        }
      ),
      { numRuns: 300 }
    );
  });

  it('parses identically regardless of the order of the two trailing elements', () => {
    fc.assert(
      fc.property(srvRlArb, fc.constantFrom('auto', 'on'), (rl, track) => {
        const rlElement = writerRateLimitElement(rl, 'srv');
        // Only meaningful when BOTH trailing elements are present.
        fc.pre(rlElement !== '');
        const trackAtom = writerSrvAtom(track);

        const forward = readerParse(
          buildLine('srv', 'srv', SRV_BODY, trackAtom, rlElement, false), 'srv', 'srv');
        const reversed = readerParse(
          buildLine('srv', 'srv', SRV_BODY, trackAtom, rlElement, true), 'srv', 'srv');

        expect(forward.ok).toBe(true);
        expect(reversed.ok).toBe(true);
        expect(reversed.track).toBe(forward.track);
        expect(reversed.opts).toEqual(forward.opts);
      }),
      { numRuns: 200 }
    );
  });

  it('accepts either trailing element alone, on both tuple kinds', () => {
    // rate_limit alone (no TrackSources atom): srv stays at 5 fields, rpz at 16.
    const srvRlOnly = `{srv,{${SRV_BODY},{rate_limit,[{max_requests,20}]}}}.`;
    const srvOnly = readerParse(srvRlOnly, 'srv', 'srv');
    expect(srvOnly.ok).toBe(true);
    expect(srvOnly.track).toBe(null); // absent => inherit/off
    expect(srvOnly.opts.max_requests).toBe(20);
    expect(srvOnly.opts.window).toBe(null);
    expect(countErlTupleFields(tupleBody(srvRlOnly, 'srv'))).toBe(5);

    const rpzRlOnly = `{rpz,{${RPZ_BODY},{rate_limit,[{window,30}]}}}.`;
    const rpzOnly = readerParse(rpzRlOnly, 'rpz', 'rpz');
    expect(rpzOnly.ok).toBe(true);
    expect(rpzOnly.track).toBe(null);
    expect(rpzOnly.opts.window).toBe(30);
    expect(rpzOnly.opts.max_requests).toBe(null);
    expect(countErlTupleFields(tupleBody(rpzRlOnly, 'rpz'))).toBe(16);

    // TrackSources alone still parses with no rate limit (unchanged legacy behavior).
    const srvTrackOnly = readerParse(`{srv,{${SRV_BODY},on}}.`, 'srv', 'srv');
    expect(srvTrackOnly.ok).toBe(true);
    expect(srvTrackOnly.track).toBe('on');
    expect(srvTrackOnly.opts.window).toBe(null);
    expect(srvTrackOnly.opts.max_requests).toBe(null);
  });

  it('one option set while the other inherits does not fabricate the inherited option', () => {
    // The whole point of "absent means inherit": parsing must NOT fill window in
    // with the macro default, or a later export would freeze it as explicit.
    const line = `{rpz,{${RPZ_BODY},{rate_limit,[{max_requests,20}]}}}.`;
    const parsed = readerParse(line, 'rpz', 'rpz');
    expect(parsed.opts.max_requests).toBe(20);
    expect(parsed.opts.window).toBe(null);
    expect(parsed.opts.window).not.toBe(RL_DEFAULTS.window);

    // Re-emitting from the parsed record reproduces the original line exactly.
    const reemitted = `{rpz,{${RPZ_BODY}${writerRateLimitElement(
      { window: parsed.opts.window, max_requests: parsed.opts.max_requests }, 'rpz')}}}.`;
    expect(reemitted).toBe(line);
  });

  it('a config with no rate limit round-trips to the identical legacy tuple', () => {
    // Nothing set anywhere => no trailing element at all, byte-identical to the
    // pre-feature output.
    const srvLegacy = `{srv,{${SRV_BODY}}}.`;
    expect(writerRateLimitElement(
      { window: null, max_requests: null, max_unknown_requests: null }, 'srv')).toBe('');
    expect(`{srv,{${SRV_BODY}${writerSrvAtom('off')}${writerRateLimitElement(
      { window: null, max_requests: null, max_unknown_requests: null }, 'srv')}}}.`)
      .toBe(srvLegacy);
    expect(countErlTupleFields(tupleBody(srvLegacy, 'srv'))).toBe(4);

    const rpzLegacy = `{rpz,{${RPZ_BODY}}}.`;
    expect(`{rpz,{${RPZ_BODY}${writerRpzAtom('Inherit')}${writerRateLimitElement(
      { window: null, max_requests: null }, 'rpz')}}}.`).toBe(rpzLegacy);
    expect(countErlTupleFields(tupleBody(rpzLegacy, 'rpz'))).toBe(15);

    // And the legacy tuples still import cleanly.
    const srvParsed = readerParse(srvLegacy, 'srv', 'srv');
    expect(srvParsed.ok).toBe(true);
    expect(srvParsed.track).toBe(null);
    expect(srvParsed.opts).toEqual({ window: null, max_requests: null, max_unknown_requests: null });

    const rpzParsed = readerParse(rpzLegacy, 'rpz', 'rpz');
    expect(rpzParsed.ok).toBe(true);
    expect(rpzParsed.opts).toEqual({ window: null, max_requests: null });
  });

  it('the real reader accepts writer-shaped rate limit configs without parse errors', async () => {
    await fc.assert(
      fc.asyncProperty(srvRlArb, rpzRlArb, fc.boolean(), async (srvRl, rpzRl, reverse) => {
        const srvLine = buildLine('srv', 'srv', SRV_BODY,
          writerSrvAtom('auto'), writerRateLimitElement(srvRl, 'srv'), reverse);
        const rpzLine = buildLine('rpz', 'rpz', RPZ_BODY,
          writerRpzAtom('true'), writerRateLimitElement(rpzRl, 'rpz'), reverse);
        const vm = makeVm();
        await runImport(vm, srvLine + '\n' + rpzLine + '\n');
        expect(vm.infoMessages.join(' || ')).not.toMatch(/parse error/i);
      }),
      { numRuns: 60 }
    );
  });

  // -------------------------------------------------------------------------
  // Cross-language fixture: the REAL PHP writer output (erlSrvRateLimit /
  // erlRpzRateLimit) parsed by the real JS helpers.
  // -------------------------------------------------------------------------
  describe('cross-language fixture (PHP rate limit writer <-> JS reader)', () => {
    const here = dirname(fileURLToPath(import.meta.url));
    const fixtures = JSON.parse(
      readFileSync(resolve(here, 'fixtures/rate-limit-roundtrip.fixtures.json'), 'utf8')
    );

    it('covers the full option cross-product', () => {
      expect(fixtures.srv.length).toBe(81); // 3 window x 3 max x 3 unknown x 3 track
      expect(fixtures.rpz.length).toBe(36); // 3 window x 3 max x 4 track
    });

    it('parses every PHP-writer srv line back to the exact stored state', () => {
      for (const f of fixtures.srv) {
        const parsed = readerParse(f.line, 'srv', 'srv');
        expect(parsed.ok, `${f.line} -> ${parsed.reason}`).toBe(true);
        expect(parsed.track === null ? 'off' : parsed.track).toBe(f.expected.track);
        expect(parsed.opts.window).toBe(f.expected.window);
        expect(parsed.opts.max_requests).toBe(f.expected.max_requests);
        expect(parsed.opts.max_unknown_requests).toBe(f.expected.max_unknown_requests);
        // The JS writer mirror emits exactly what PHP emitted.
        expect(writerRateLimitElement(f.stored ? {
          window: f.stored.rl_window,
          max_requests: f.stored.rl_max_requests,
          max_unknown_requests: f.stored.rl_max_unknown_requests,
        } : {}, 'srv')).toBe(f.rlElement);
      }
    });

    it('parses every PHP-writer rpz line back to the exact stored state', () => {
      for (const f of fixtures.rpz) {
        const parsed = readerParse(f.line, 'rpz', 'rpz');
        expect(parsed.ok, `${f.line} -> ${parsed.reason}`).toBe(true);
        expect(parsed.track === null ? 'Inherit' : parsed.track).toBe(f.expected.track);
        expect(parsed.opts.window).toBe(f.expected.window);
        expect(parsed.opts.max_requests).toBe(f.expected.max_requests);
        expect(writerRateLimitElement({
          window: f.stored.rl_window,
          max_requests: f.stored.rl_max_requests,
        }, 'rpz')).toBe(f.rlElement);
      }
    });

    it('the PHP writer emits nothing at all when no rate limit is set (legacy tuple)', () => {
      const srvNone = fixtures.srv.find(
        (f) => f.stored.rl_window === null && f.stored.rl_max_requests === null &&
               f.stored.rl_max_unknown_requests === null && f.stored.track_default === 'off');
      expect(srvNone.rlElement).toBe('');
      expect(srvNone.trackAtom).toBe('');
      expect(srvNone.line).toBe(fixtures.legacy.srv);

      const rpzNone = fixtures.rpz.find(
        (f) => f.stored.rl_window === null && f.stored.rl_max_requests === null &&
               f.stored.track_sources === 'Inherit');
      expect(rpzNone.rlElement).toBe('');
      expect(rpzNone.line).toBe(fixtures.legacy.rpz);
    });

    it('the real reader accepts every PHP-writer rate limit line without parse errors', async () => {
      const config =
        fixtures.srv.map((f) => f.line).join('\n') + '\n' +
        fixtures.rpz.map((f) => f.line).join('\n') + '\n';
      const vm = makeVm();
      await runImport(vm, config);
      expect(vm.infoMessages.join(' || ')).not.toMatch(/parse error/i);
    });
  });
});
