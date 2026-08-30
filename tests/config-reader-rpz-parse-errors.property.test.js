import { describe, it, expect, vi } from 'vitest';
import fc from 'fast-check';
import { ImportIOC2RPZ } from 'io2';

// Feature: ioc-source-attribution, Property 4: rpz parse errors are reported and suppress the record
//
// Validates: Requirements 7.6, 7.8
//
// For any rpz tuple whose trailing atom is present but not one of {auto, true,
// false}, or whose field count is neither 15 nor 16, Config_Reader
// (ImportIOC2RPZ in www/js/io2.js) SHALL report a parse error that identifies
// the offending value (or the field count) and the rpz tuple it appears in, and
// SHALL NOT create or update a feed record from that tuple.
//
// ImportIOC2RPZ is async: it reads existing records via a global `axios` and uses
// sleep()/setTimeout between phases. The tests drive it with a mock `vm` (to
// capture surfaced messages and detect record creation) and stub global.axios to
// return empty datasets. The real production behavior is untouched; only the
// module-scope globals it reads (axios, setTimeout) are stubbed for the duration
// of each call so the test runs fast. This mirrors the harness used by the srv
// parse-error property test (Property 3).

// -- generators -------------------------------------------------------------

const ATOM_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789'.split('');
const VALID_RPZ_ATOMS = ['auto', 'true', 'false'];

// A bare Erlang atom fragment restricted to a charset that contains none of the
// regex-significant characters (",", "}", "]", '"'), so it is always captured by
// the reader's optional 16th-atom group and never breaks the surrounding tuple.
function atomArb({ min = 1, max = 10 } = {}) {
  return fc
    .array(fc.constantFrom(...ATOM_CHARS), { minLength: min, maxLength: max })
    .map((a) => a.join(''));
}

// A token safe to embed inside a quoted string field ("...").
function nameArb() {
  return fc
    .array(fc.constantFrom(...ATOM_CHARS, '.'), { minLength: 1, maxLength: 15 })
    .map((a) => a.join(''));
}

// Build a structurally valid 16-field rpz tuple string with an explicit trailing
// atom. Every field is generated so the rpz regex in ImportIOC2RPZ matches:
//   {rpz,{ Name, refresh, update, exp, nxttl, "cache", "wildcard", action,
//          [tkeys], "ioc_type", axfr, ixfr, [sources], [notify], [whitelist],
//          TrackSources }}.
function rpzTuple({ name, cache, wildcard, action, iocType, track }) {
  return (
    `{rpz,{"${name}",` +
    `3600,60,86400,30,` +
    `"${cache}","${wildcard}","${action}",` +
    `[],"${iocType}",` +
    `900,300,` +
    `[],[],[],` +
    `${track}}}.`
  );
}

// Case (a): a structurally valid 16-field rpz tuple whose trailing atom is NOT a
// member of {auto, true, false}. Expected: parse error naming the offending
// value.
const badAtomCase = fc
  .record({
    name: nameArb(),
    cache: fc.constantFrom('true', 'false'),
    wildcard: fc.constantFrom('true', 'false'),
    action: fc.constantFrom('nxdomain', 'nodata', 'passthru', 'drop'),
    iocType: fc.constantFrom('fqdn', 'ip', 'url'),
    bad: atomArb({ min: 1, max: 8 }).filter((s) => !VALID_RPZ_ATOMS.includes(s)),
  })
  .map(({ name, cache, wildcard, action, iocType, bad }) => ({
    kind: 'atom',
    bad,
    line: rpzTuple({ name, cache, wildcard, action, iocType, track: bad }),
  }));

// Case (b): an rpz tuple whose top-level field count is neither 15 nor 16. Built
// from N quoted-string fields (no brackets, no integers), so it can never match
// the 15-/16-field rpz regex and its counted arity is exactly N. Expected: a
// parse error naming the field count.
const badArityCase = fc
  .integer({ min: 1, max: 20 })
  .filter((n) => n !== 15 && n !== 16)
  .chain((n) =>
    fc
      .array(atomArb({ min: 1, max: 6 }), { minLength: n, maxLength: n })
      .map((vals) => ({
        kind: 'arity',
        count: n,
        line: `{rpz,{${vals.map((v) => `"${v}"`).join(',')}}}.`,
      }))
  );

const rpzParseErrorCase = fc.oneof(badAtomCase, badArityCase);

// -- mock vm ----------------------------------------------------------------

function makeVm() {
  return {
    ftImpAction: 0,
    ftImpPrefix: '',
    ftImpServName: 'test-server',
    ftImpServPubIP: '',
    ftImpServMGMTIP: '',
    ftImpFiles: [{ name: 'test.conf' }],
    // capture surfaced notifications
    infoMessages: [],
    showInfo(msg) {
      this.infoMessages.push(String(msg));
    },
    // record-creation detectors (must NOT be called for a suppressed rpz tuple)
    tblMgmtSrvRecord: vi.fn(() => Promise.resolve()),
    tblMgmtRPZRecord: vi.fn(() => Promise.resolve()),
    tblMgmtTKeyRecord: vi.fn(() => Promise.resolve()),
    tblMgmtSrcRecord: vi.fn(() => Promise.resolve()),
  };
}

// Run ImportIOC2RPZ against a mock vm with stubbed globals, fast (setTimeout is
// collapsed to a microtask only while the call is in flight, then restored).
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

// -- property ---------------------------------------------------------------

describe('Feature: ioc-source-attribution, Property 4: rpz parse errors are reported and suppress the record', () => {
  it('reports a parse error naming the offending value/field count and the tuple, and creates no feed record', async () => {
    await fc.assert(
      fc.asyncProperty(rpzParseErrorCase, async (tc) => {
        const vm = makeVm();
        await runImport(vm, tc.line + '\n');

        const surfaced = vm.infoMessages.join(' || ');

        // A parse error was surfaced through the existing notification channel.
        expect(surfaced).toMatch(/parse error/i);

        // The error identifies the rpz tuple it appears in.
        expect(surfaced).toContain(tc.line);

        // The error identifies the offending value (bad atom) or the field count.
        if (tc.kind === 'atom') {
          expect(surfaced).toContain(`"${tc.bad}"`);
        } else {
          expect(surfaced).toContain(`found ${tc.count}`);
        }

        // No feed record is created or updated from a bad rpz tuple.
        expect(vm.tblMgmtRPZRecord).not.toHaveBeenCalled();
      }),
      { numRuns: 100 }
    );
  });
});

// ===========================================================================
// Feature: dns-rate-limits: malformed, out-of-range, or misplaced rate_limit
// options are reported and suppress the rpz record.
//
// Includes the level rule: max_unknown_requests limits the aggregate {IP} bucket
// (unknown zone, unsupported qtype, wrong class). A request counted there never
// resolved to a zone, so there is no zone config to read it from - it is
// server-level only, and the server logs and ignores it on an rpz record.
// ===========================================================================

const RPZ_BODY =
  '"feed.example",3600,60,86400,30,"true","true","nxdomain",[],' +
  '"fqdn",900,300,["src1","src2"],[],["wl1"]';

function rpzLineWith(tail) {
  return `{rpz,{${RPZ_BODY}${tail}}}.`;
}

const badRpzRateLimitCase = fc.oneof(
  fc.constantFrom(0, -1, -60).map((n) => ({
    line: rpzLineWith(`,{rate_limit,[{window,${n}}]}`),
    needle: String(n),
    why: 'window below minimum',
  })),
  fc.constantFrom(-1, -99).map((n) => ({
    line: rpzLineWith(`,{rate_limit,[{max_requests,${n}}]}`),
    needle: String(n),
    why: 'max_requests below minimum',
  })),
  fc.constantFrom('max_bursts', 'windows', 'rate').map((name) => ({
    line: rpzLineWith(`,{rate_limit,[{${name},10}]}`),
    needle: `"${name}"`,
    why: 'unknown option',
  })),
  fc.constantFrom('abc', '6.5', 'true').map((v) => ({
    line: rpzLineWith(`,{rate_limit,[{max_requests,${v}}]}`),
    needle: 'rate_limit',
    why: 'non-integer value',
  })),
  fc.constant({
    line: rpzLineWith(',{rate_limit,[{max_requests,6},{max_requests,7}]}'),
    needle: '"max_requests"',
    why: 'duplicate option',
  }),
  fc.constantFrom(
    ',{rate_limit,[{window}]}',
    ',{rate_limit,{window,60}}',
    ',{rate_limit,[window,60]}'
  ).map((tail) => ({
    line: rpzLineWith(tail),
    needle: 'rate_limit',
    why: 'malformed element',
  }))
);

describe('Feature: dns-rate-limits: invalid rpz rate_limit is reported and suppresses the record', () => {
  it('reports a parse error identifying the offending value and creates no feed record', async () => {
    await fc.assert(
      fc.asyncProperty(badRpzRateLimitCase, async (tc) => {
        const vm = makeVm();
        await runImport(vm, tc.line + '\n');
        const surfaced = vm.infoMessages.join(' || ');

        expect(surfaced, tc.why).toMatch(/parse error/i);
        expect(surfaced).toContain(tc.line);
        expect(surfaced).toContain(tc.needle);
        expect(vm.tblMgmtRPZRecord).not.toHaveBeenCalled();
      }),
      { numRuns: 100 }
    );
  });

  it('rejects the server-only max_unknown_requests on a feed, naming the option and the level', async () => {
    const vm = makeVm();
    const line = rpzLineWith(',{rate_limit,[{max_unknown_requests,1}]}');
    await runImport(vm, line + '\n');
    const surfaced = vm.infoMessages.join(' || ');

    expect(surfaced).toMatch(/parse error/i);
    expect(surfaced).toContain('"max_unknown_requests"');
    expect(surfaced).toContain('rpz level');
    expect(surfaced).toContain(line);
    expect(vm.tblMgmtRPZRecord).not.toHaveBeenCalled();
  });

  it('accepts a valid rpz rate_limit in either trailing-element order, or alone', async () => {
    for (const tail of [
      ',true,{rate_limit,[{window,60},{max_requests,20}]}',
      ',{rate_limit,[{window,60},{max_requests,20}]},true',
      ',{rate_limit,[{max_requests,0}]}',
      ',{rate_limit,[{window,30}]}',
      ',{rate_limit,[]}',
      ',false',
      '',
    ]) {
      const vm = makeVm();
      await runImport(vm, rpzLineWith(tail) + '\n');
      expect(vm.infoMessages.join(' || '), tail).not.toMatch(/parse error/i);
    }
  });

  it('reports the widened arity range when an rpz tuple has too many fields', async () => {
    const vm = makeVm();
    const line = `{rpz,{${RPZ_BODY},true,{rate_limit,[{window,60}]},extra}}.`;
    await runImport(vm, line + '\n');
    const surfaced = vm.infoMessages.join(' || ');
    expect(surfaced).toMatch(/parse error/i);
    expect(surfaced).toContain('expected 15 to 17 fields');
    expect(surfaced).toContain('found 18');
    expect(vm.tblMgmtRPZRecord).not.toHaveBeenCalled();
  });
});
