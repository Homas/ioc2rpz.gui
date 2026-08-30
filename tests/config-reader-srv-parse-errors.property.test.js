import { describe, it, expect, vi } from 'vitest';
import fc from 'fast-check';
import { ImportIOC2RPZ } from 'io2';

// Feature: ioc-source-attribution, Property 3: srv parse errors are reported and suppress the record
//
// Validates: Requirements 7.5, 7.7
//
// For any srv tuple whose trailing atom is present but not one of {off, auto, on},
// or whose field count is neither 4 nor 5, Config_Reader (ImportIOC2RPZ in
// www/js/io2.js) SHALL report a parse error that identifies the offending value
// (or the field count) and the srv tuple it appears in, and SHALL NOT create or
// update a server record from that tuple.
//
// ImportIOC2RPZ is async: it reads existing records via a global `axios` and uses
// sleep()/setTimeout between phases. The tests drive it with a mock `vm` (to
// capture surfaced messages and detect record creation) and stub global.axios to
// return empty datasets. The real production behavior is untouched; only the
// module-scope globals it reads (axios, setTimeout) are stubbed for the duration
// of each call so the test runs fast.

// -- generators -------------------------------------------------------------

const ATOM_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789'.split('');
const VALID_SRV_ATOMS = ['off', 'auto', 'on'];

// A bare Erlang atom fragment restricted to a charset that contains none of the
// regex-significant characters (",", "}", "]", '"'), so it is always captured by
// the reader's optional 5th-atom group and never breaks the surrounding tuple.
function atomArb({ min = 1, max = 10 } = {}) {
  return fc
    .array(fc.constantFrom(...ATOM_CHARS), { minLength: min, maxLength: max })
    .map((a) => a.join(''));
}

// A token safe to embed inside a quoted string field ("...") or a list ([...]).
function nameArb() {
  return fc
    .array(fc.constantFrom(...ATOM_CHARS, '.'), { minLength: 1, maxLength: 15 })
    .map((a) => a.join(''));
}

// Case (a): a structurally valid 5-field srv tuple whose trailing atom is NOT a
// member of {off, auto, on}. Expected: parse error naming the offending value.
const badAtomCase = fc
  .record({
    ns: nameArb(),
    email: nameArb(),
    mkeys: atomArb({ min: 0, max: 8 }),
    acl: nameArb(),
    bad: atomArb({ min: 1, max: 8 }).filter((s) => !VALID_SRV_ATOMS.includes(s)),
  })
  .map(({ ns, email, mkeys, acl, bad }) => ({
    kind: 'atom',
    bad,
    line: `{srv,{"${ns}","${email}",[${mkeys}],[${acl}],${bad}}}.`,
  }));

// Case (b): an srv tuple whose top-level field count is neither 4 nor 5. Built
// from N quoted-string fields (no brackets), so it can never match the 4-/5-field
// srv regex and its counted arity is exactly N. Expected: parse error naming the
// field count.
const badArityCase = fc
  .integer({ min: 1, max: 9 })
  .filter((n) => n !== 4 && n !== 5)
  .chain((n) =>
    fc
      .array(atomArb({ min: 1, max: 6 }), { minLength: n, maxLength: n })
      .map((vals) => ({
        kind: 'arity',
        count: n,
        line: `{srv,{${vals.map((v) => `"${v}"`).join(',')}}}.`,
      }))
  );

const srvParseErrorCase = fc.oneof(badAtomCase, badArityCase);

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
    // record-creation detectors (must NOT be called for a suppressed srv tuple)
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

describe('Feature: ioc-source-attribution, Property 3: srv parse errors are reported and suppress the record', () => {
  it('reports a parse error naming the offending value/field count and the tuple, and creates no server record', async () => {
    await fc.assert(
      fc.asyncProperty(srvParseErrorCase, async (tc) => {
        const vm = makeVm();
        await runImport(vm, tc.line + '\n');

        const surfaced = vm.infoMessages.join(' || ');

        // A parse error was surfaced through the existing notification channel.
        expect(surfaced).toMatch(/parse error/i);

        // The error identifies the srv tuple it appears in.
        expect(surfaced).toContain(tc.line);

        // The error identifies the offending value (bad atom) or the field count.
        if (tc.kind === 'atom') {
          expect(surfaced).toContain(`"${tc.bad}"`);
        } else {
          expect(surfaced).toContain(`found ${tc.count}`);
        }

        // No server record is created or updated from a bad srv tuple.
        expect(vm.tblMgmtSrvRecord).not.toHaveBeenCalled();
      }),
      { numRuns: 100 }
    );
  });
});

// ===========================================================================
// Feature: dns-rate-limits: malformed or out-of-range rate_limit options are
// reported and suppress the srv record.
//
// The server only LOGS and ignores an invalid rate_limit value and then falls back
// to the next level, so a bad value written by the GUI would silently do nothing.
// The reader therefore rejects it outright: a parse error naming the offending
// value, and no server record created.
// ===========================================================================

const SRV_BODY = '"ns.example.com","admin.example.com",["mkey1"],["192.0.2.10"]';

function srvLineWith(tail) {
  return `{srv,{${SRV_BODY}${tail}}}.`;
}

// Structurally-parseable but semantically invalid rate_limit elements.
const badRateLimitCase = fc.oneof(
  // window must be > 0
  fc.constantFrom(0, -1, -60).map((n) => ({
    line: srvLineWith(`,{rate_limit,[{window,${n}}]}`),
    needle: String(n),
    why: 'window below minimum',
  })),
  // the maximums must be >= 0
  fc.constantFrom(-1, -99).map((n) => ({
    line: srvLineWith(`,{rate_limit,[{max_requests,${n}}]}`),
    needle: String(n),
    why: 'max_requests below minimum',
  })),
  // unknown option name
  fc.constantFrom('max_bursts', 'windows', 'rate', 'max_request').map((name) => ({
    line: srvLineWith(`,{rate_limit,[{${name},10}]}`),
    needle: `"${name}"`,
    why: 'unknown option',
  })),
  // non-integer values
  fc.constantFrom('abc', '6.5', '1e3', 'true').map((v) => ({
    line: srvLineWith(`,{rate_limit,[{window,${v}}]}`),
    needle: 'rate_limit',
    why: 'non-integer value',
  })),
  // duplicated option
  fc.constant({
    line: srvLineWith(',{rate_limit,[{window,60},{window,30}]}'),
    needle: '"window"',
    why: 'duplicate option',
  }),
  // duplicated rate_limit element
  fc.constant({
    line: srvLineWith(',{rate_limit,[{window,60}]},{rate_limit,[{max_requests,6}]}'),
    needle: 'rate_limit',
    why: 'duplicate element',
  }),
  // malformed element structure
  fc.constantFrom(
    ',{rate_limit,[{window}]}',
    ',{rate_limit,[window,60]}',
    ',{rate_limit,{window,60}}',
    ',{rate_limit,[]extra}'
  ).map((tail) => ({
    line: srvLineWith(tail),
    needle: 'rate_limit',
    why: 'malformed element',
  }))
);

describe('Feature: dns-rate-limits: invalid srv rate_limit is reported and suppresses the record', () => {
  it('reports a parse error identifying the offending value and creates no server record', async () => {
    await fc.assert(
      fc.asyncProperty(badRateLimitCase, async (tc) => {
        const vm = makeVm();
        await runImport(vm, tc.line + '\n');
        const surfaced = vm.infoMessages.join(' || ');

        expect(surfaced, tc.why).toMatch(/parse error/i);
        // The error identifies the srv tuple it appears in...
        expect(surfaced).toContain(tc.line);
        // ...and the offending value / option.
        expect(surfaced).toContain(tc.needle);
        // No server record is created from a bad srv tuple.
        expect(vm.tblMgmtSrvRecord).not.toHaveBeenCalled();
      }),
      { numRuns: 100 }
    );
  });

  it('rejects max_unknown_requests only where it is unsupported (valid on srv)', async () => {
    // Server level: max_unknown_requests is valid, so this must parse cleanly.
    const vm = makeVm();
    await runImport(vm, srvLineWith(',{rate_limit,[{max_unknown_requests,3}]}') + '\n');
    expect(vm.infoMessages.join(' || ')).not.toMatch(/parse error/i);
  });

  it('accepts a valid srv rate_limit in either trailing-element order', async () => {
    for (const tail of [
      ',on,{rate_limit,[{window,60},{max_requests,6}]}',
      ',{rate_limit,[{window,60},{max_requests,6}]},on',
      ',{rate_limit,[{max_requests,0}]}',
      ',{rate_limit,[]}',
    ]) {
      const vm = makeVm();
      await runImport(vm, srvLineWith(tail) + '\n');
      expect(vm.infoMessages.join(' || '), tail).not.toMatch(/parse error/i);
    }
  });

  it('reports the widened arity range when a srv tuple has too many fields', async () => {
    const vm = makeVm();
    const line = `{srv,{${SRV_BODY},on,{rate_limit,[{window,60}]},extra}}.`;
    await runImport(vm, line + '\n');
    const surfaced = vm.infoMessages.join(' || ');
    expect(surfaced).toMatch(/parse error/i);
    expect(surfaced).toContain('expected 4 to 6 fields');
    expect(surfaced).toContain('found 7');
    expect(vm.tblMgmtSrvRecord).not.toHaveBeenCalled();
  });
});
