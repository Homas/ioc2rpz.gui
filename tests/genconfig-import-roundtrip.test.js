import { describe, it, expect, vi } from 'vitest';
import { execFileSync } from 'child_process';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';
import {
  ImportIOC2RPZ,
  splitErlTupleFields,
  classifyErlTrailingElements,
} from 'io2';

// End-to-end check across the language boundary: the srv/rpz lines that the REAL PHP
// genConfig() produces, for each rate limit scenario, are fed to the REAL JS importer.
//
// The fixture tests in config-roundtrip-identity exercise the emission helpers in
// isolation. This one goes through genConfig() itself against a real SQLite database,
// so it also covers the tuple assembly (where the rate limit element is concatenated
// after the TrackSources element) and the quirks of genConfig's real output that a
// hand-built line would miss - notably the empty `[""]` key/source lists.
//
// The PHP side runs via `php`; the test is skipped when no PHP binary is available so
// it never fails a JS-only environment.

const here = dirname(fileURLToPath(import.meta.url));
const projectRoot = resolve(here, '..');

function phpAvailable() {
  try {
    execFileSync('php', ['--version'], { stdio: 'ignore' });
    return true;
  } catch {
    return false;
  }
}

// Drives the real PHP genConfig() over a temp v4-schema DB (see
// tests/fixtures/gen_genconfig_scenarios.php) and returns the generated srv/rpz lines
// per scenario.
function generateScenarios() {
  const stdout = execFileSync(
    'php',
    [
      '-d', 'display_errors=0',
      '-d', 'error_reporting=0',
      'tests/fixtures/gen_genconfig_scenarios.php',
    ],
    { cwd: projectRoot, encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 }
  );
  return JSON.parse(stdout);
}

// Parse a generated line with the real production helpers.
function parseLine(line) {
  const tag = line.startsWith('{srv,') ? 'srv' : 'rpz';
  const mandatory = tag === 'srv' ? 4 : 15;
  const body = line.match(new RegExp('^\\{' + tag + ',\\{([\\s\\S]*)\\}\\}\\.$'))[1];
  const fields = splitErlTupleFields(body);
  const tail = classifyErlTrailingElements(fields.slice(mandatory), tag);
  return { tag, fields, tail };
}

function makeVm() {
  return {
    ftImpAction: 0,
    ftImpPrefix: '',
    ftImpServName: 'genconfig-server',
    ftImpServPubIP: '',
    ftImpServMGMTIP: '',
    ftImpFiles: [{ name: 'ioc2rpz.conf' }],
    ftSrvTKeys: [],
    ftRPZSrvs: [],
    ftRPZTKeys: [],
    ftRPZSrc: [],
    ftRPZWL: [],
    infoMessages: [],
    showInfo(msg) { this.infoMessages.push(String(msg)); },
    tblMgmtSrvRecord: vi.fn(() => Promise.resolve()),
    tblMgmtRPZRecord: vi.fn(() => Promise.resolve()),
    tblMgmtTKeyRecord: vi.fn(() => Promise.resolve()),
    tblMgmtSrcRecord: vi.fn(() => Promise.resolve()),
  };
}

async function runImport(vm, text) {
  const realSetTimeout = globalThis.setTimeout;
  const realAxios = globalThis.axios;
  globalThis.setTimeout = (fn) => { Promise.resolve().then(() => fn()); return 0; };
  globalThis.axios = { get: vi.fn(() => Promise.resolve({ data: [] })) };
  try {
    await ImportIOC2RPZ(vm, text);
  } finally {
    globalThis.setTimeout = realSetTimeout;
    globalThis.axios = realAxios;
  }
}

describe.skipIf(!phpAvailable())('real genConfig() output is accepted by the real importer', () => {
  const scenarios = generateScenarios();

  it('generates the expected trailing elements per scenario', () => {
    // (a) nothing set => the exact legacy tuples, no trailing element at all.
    expect(scenarios.none[0]).toBe(
      '{srv,{"ns.example.com","admin.example.com",[""],["192.0.2.10"]}}.');
    expect(scenarios.none[1]).toBe(
      '{rpz,{"feed.example",3600,60,86400,30,"true","true","nxdomain",[""],"fqdn",900,300,[""],[],[]}}.');

    // (b) server-level only.
    expect(scenarios.srv_only[0])
      .toContain(',{rate_limit,[{window,60},{max_requests,6},{max_unknown_requests,1}]}');
    expect(scenarios.srv_only[1]).not.toContain('rate_limit');

    // (c) feed-level only.
    expect(scenarios.rpz_only[0]).not.toContain('rate_limit');
    expect(scenarios.rpz_only[1]).toContain(',{rate_limit,[{window,30},{max_requests,20}]}');

    // (e) the feed overrides one option and inherits the other: only max_requests emitted.
    expect(scenarios.rpz_partial[1]).toContain(',{rate_limit,[{max_requests,20}]}');
    expect(scenarios.rpz_partial[1]).not.toContain('window');

    // The 0 edge is emitted, never dropped.
    expect(scenarios.zero_max[1]).toContain(',{rate_limit,[{max_requests,0}]}');

    // TrackSources is emitted BEFORE rate_limit, as the writer contract states.
    expect(scenarios.with_track[0]).toContain('["192.0.2.10"],auto,{rate_limit,');
    expect(scenarios.with_track[1]).toContain('[],true,{rate_limit,');
  });

  it('parses every generated line back to the exact stored state', () => {
    const expected = {
      none:        { srv: [null, null, null], rpz: [null, null], td: null,   ts: null },
      srv_only:    { srv: [60, 6, 1],         rpz: [null, null], td: null,   ts: null },
      rpz_only:    { srv: [null, null, null], rpz: [30, 20],     td: null,   ts: null },
      both:        { srv: [60, 6, 1],         rpz: [30, 20],     td: null,   ts: null },
      rpz_partial: { srv: [60, 6, 1],         rpz: [null, 20],   td: null,   ts: null },
      zero_max:    { srv: [null, 0, 0],       rpz: [null, 0],    td: null,   ts: null },
      with_track:  { srv: [60, 6, 1],         rpz: [30, 20],     td: 'auto', ts: 'true' },
      track_only:  { srv: [null, null, null], rpz: [null, null], td: 'on',   ts: 'false' },
    };

    for (const [name, exp] of Object.entries(expected)) {
      const [srvLine, rpzLine] = scenarios[name];

      const srv = parseLine(srvLine);
      expect(srv.tail.ok, `${name} srv: ${srv.tail.error}`).toBe(true);
      const srvRl = srv.tail.rateLimit || {};
      expect([
        srvRl.window ?? null, srvRl.max_requests ?? null, srvRl.max_unknown_requests ?? null,
      ], `${name} srv rate limit`).toEqual(exp.srv);
      expect(srv.tail.track, `${name} srv track`).toBe(exp.td);

      const rpz = parseLine(rpzLine);
      expect(rpz.tail.ok, `${name} rpz: ${rpz.tail.error}`).toBe(true);
      const rpzRl = rpz.tail.rateLimit || {};
      expect([rpzRl.window ?? null, rpzRl.max_requests ?? null], `${name} rpz rate limit`)
        .toEqual(exp.rpz);
      expect(rpz.tail.track, `${name} rpz track`).toBe(exp.ts);
    }
  });

  it('the real importer reports no parse error for any generated scenario', async () => {
    for (const [name, lines] of Object.entries(scenarios)) {
      const vm = makeVm();
      await runImport(vm, lines.join('\n') + '\n');
      expect(vm.infoMessages.join(' || '), `scenario ${name}`).not.toMatch(/parse error/i);
    }
  });
});
