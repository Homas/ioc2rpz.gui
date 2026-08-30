import { describe, it, expect } from 'vitest';
import { appConfig } from 'io2';

// Unit tests for the Server_Editor "Source attribution (global default)" control.
//
// These exercise the reactive data and the mgmtRec() binding logic added in task
// 10.1: the exact option set (Req 1.1), the default `off` on a fresh form
// (Req 1.2), and the binding of persisted/absent values when a record is
// reopened (Req 1.5, Req 10.5).

// Build a mock component context for invoking appConfig.methods.mgmtRec directly.
// mgmtRec reads and writes everything through `this.$root` and calls
// `this.$root.get_lists(...)`, so $root points back at the same data bag and
// get_lists is stubbed to avoid network side effects. showModal is a harmless
// mitt emit, so the method can run to completion.
function makeContext() {
  const ctx = {
    // seed only the field under test with its reactive default; mgmtRec assigns
    // the rest of the fields it touches
    ftSrvTrackDefault: appConfig.data.ftSrvTrackDefault,
    get_lists: () => {},
  };
  ctx.$root = ctx;
  return ctx;
}

// A fully-populated server row.item as returned by `GET servers` (select rowid,*),
// optionally overriding track_default.
function makeServerRow(overrides = {}) {
  return {
    item: {
      rowid: 7,
      name: 'srv1',
      ip: '10.0.0.1',
      pub_ip: '203.0.113.1',
      ns: 'ns.example.com',
      email: 'admin@example.com',
      mgmt: 1,
      stype: 0,
      URL: '',
      certfile: '',
      keyfile: '',
      cacertfile: '',
      custom_config: '',
      disabled: 0,
      mgmt_ips: [{ mgmt_ip: '10.0.0.1' }],
      tkeys: [{ rowid: 3 }],
      ...overrides,
    },
  };
}

describe('Server_Editor source-attribution control data', () => {
  it('offers exactly the off/auto/on option set (Req 1.1)', () => {
    expect(appConfig.data.Srv_TrackDefault_Options).toEqual([
      { value: 'off', text: 'off' },
      { value: 'auto', text: 'auto' },
      { value: 'on', text: 'on' },
    ]);
  });

  it('defaults ftSrvTrackDefault to `off` on a fresh form (Req 1.2)', () => {
    expect(appConfig.data.ftSrvTrackDefault).toBe('off');
  });
});

describe('Server_Editor mgmtRec() track_default binding', () => {
  it('resets the control to `off` when adding a new server (Req 1.2)', () => {
    const ctx = makeContext();
    // pre-dirty the field to prove the add branch resets it
    ctx.ftSrvTrackDefault = 'on';
    appConfig.methods.mgmtRec.call(ctx, 'add', 'servers', {}, null);
    expect(ctx.ftSrvTrackDefault).toBe('off');
  });

  it('binds the persisted track_default value on edit (Req 1.5)', () => {
    const ctx = makeContext();
    appConfig.methods.mgmtRec.call(
      ctx,
      'edit',
      'servers',
      makeServerRow({ track_default: 'auto' }),
      null
    );
    expect(ctx.ftSrvTrackDefault).toBe('auto');
  });

  it('binds `on` when the persisted value is `on` (Req 1.5)', () => {
    const ctx = makeContext();
    appConfig.methods.mgmtRec.call(
      ctx,
      'edit',
      'servers',
      makeServerRow({ track_default: 'on' }),
      null
    );
    expect(ctx.ftSrvTrackDefault).toBe('on');
  });

  it('falls back to `off` when the record has no persisted track_default (Req 10.5)', () => {
    const ctx = makeContext();
    // record with the attribute entirely absent
    const row = makeServerRow();
    delete row.item.track_default;
    appConfig.methods.mgmtRec.call(ctx, 'edit', 'servers', row, null);
    expect(ctx.ftSrvTrackDefault).toBe('off');
  });

  it('falls back to `off` when track_default is an empty string (Req 10.5)', () => {
    const ctx = makeContext();
    appConfig.methods.mgmtRec.call(
      ctx,
      'edit',
      'servers',
      makeServerRow({ track_default: '' }),
      null
    );
    expect(ctx.ftSrvTrackDefault).toBe('off');
  });
});

// ===========================================================================
// Server_Editor DNS rate limit controls.
//
// An empty input means inherit and renders the resolved value as a hint; an explicit
// value overrides it; one option can be set while the other inherits. The critical
// edge is 0, which is a legitimate value ("refuse every request in that bucket") and
// must never collapse to "inherit".
// ===========================================================================

import { rlToInput, RL_DEFAULTS } from 'io2';

// A context for the server editor's rate limit fields, as Vue would hold them.
function makeRLContext(overrides = {}) {
  const ctx = {
    ftSrvRLWindow: appConfig.data.ftSrvRLWindow,
    ftSrvRLMaxRequests: appConfig.data.ftSrvRLMaxRequests,
    ftSrvRLMaxUnknownRequests: appConfig.data.ftSrvRLMaxUnknownRequests,
    get_lists: () => {},
    ...overrides,
  };
  ctx.$root = ctx;
  return ctx;
}

// Resolve the hint text the template renders while an input is empty.
function windowHint(ctx) {
  ctx.effectiveSrvRateLimit = appConfig.computed.effectiveSrvRateLimit.call(ctx);
  return appConfig.computed.effectiveSrvRLWindowHint.call(ctx);
}
function maxRequestsHint(ctx) {
  ctx.effectiveSrvRateLimit = appConfig.computed.effectiveSrvRateLimit.call(ctx);
  return appConfig.computed.effectiveSrvRLMaxRequestsHint.call(ctx);
}
function maxUnknownHint(ctx) {
  ctx.effectiveSrvRateLimit = appConfig.computed.effectiveSrvRateLimit.call(ctx);
  return appConfig.computed.effectiveSrvRLMaxUnknownRequestsHint.call(ctx);
}

describe('Server_Editor rate limit control data', () => {
  it('defaults every rate limit field to empty (inherit) on a fresh form', () => {
    expect(appConfig.data.ftSrvRLWindow).toBe('');
    expect(appConfig.data.ftSrvRLMaxRequests).toBe('');
    expect(appConfig.data.ftSrvRLMaxUnknownRequests).toBe('');
  });
});

describe('Server_Editor mgmtRec() rate limit binding', () => {
  it('resets all three fields to empty when adding a new server', () => {
    const ctx = makeRLContext({
      ftSrvRLWindow: '30', ftSrvRLMaxRequests: '9', ftSrvRLMaxUnknownRequests: '2',
    });
    appConfig.methods.mgmtRec.call(ctx, 'add', 'servers', {}, null);
    expect(ctx.ftSrvRLWindow).toBe('');
    expect(ctx.ftSrvRLMaxRequests).toBe('');
    expect(ctx.ftSrvRLMaxUnknownRequests).toBe('');
  });

  it('binds persisted values on edit', () => {
    const ctx = makeRLContext();
    appConfig.methods.mgmtRec.call(ctx, 'edit', 'servers', makeServerRow({
      rl_window: 30, rl_max_requests: 12, rl_max_unknown_requests: 4,
    }), null);
    expect(ctx.ftSrvRLWindow).toBe('30');
    expect(ctx.ftSrvRLMaxRequests).toBe('12');
    expect(ctx.ftSrvRLMaxUnknownRequests).toBe('4');
  });

  it('binds a NULL stored limit to empty (inherit)', () => {
    const ctx = makeRLContext();
    appConfig.methods.mgmtRec.call(ctx, 'edit', 'servers', makeServerRow({
      rl_window: null, rl_max_requests: null, rl_max_unknown_requests: null,
    }), null);
    expect(ctx.ftSrvRLWindow).toBe('');
    expect(ctx.ftSrvRLMaxRequests).toBe('');
    expect(ctx.ftSrvRLMaxUnknownRequests).toBe('');
  });

  it('binds empty when the columns are absent entirely (pre-upgrade record)', () => {
    const ctx = makeRLContext();
    const row = makeServerRow();
    // a record from before the schema upgrade has no rl_* keys at all
    appConfig.methods.mgmtRec.call(ctx, 'edit', 'servers', row, null);
    expect(ctx.ftSrvRLWindow).toBe('');
    expect(ctx.ftSrvRLMaxRequests).toBe('');
    expect(ctx.ftSrvRLMaxUnknownRequests).toBe('');
  });

  it('binds a stored 0 to "0", not to empty', () => {
    // The NULL/0 distinction is the whole reason the columns are nullable: a `|| ''`
    // fallback here would silently turn "refuse everything" into "inherit".
    const ctx = makeRLContext();
    appConfig.methods.mgmtRec.call(ctx, 'edit', 'servers', makeServerRow({
      rl_max_requests: 0, rl_max_unknown_requests: 0,
    }), null);
    expect(ctx.ftSrvRLMaxRequests).toBe('0');
    expect(ctx.ftSrvRLMaxUnknownRequests).toBe('0');
  });

  it('rlToInput preserves 0 and maps every absent shape to empty', () => {
    expect(rlToInput(0)).toBe('0');
    expect(rlToInput('0')).toBe('0');
    expect(rlToInput(60)).toBe('60');
    for (const absent of [null, undefined, '']) {
      expect(rlToInput(absent)).toBe('');
    }
  });
});

describe('Server_Editor rate limit inherited hints', () => {
  it('renders the built-in default as the hint while a field is empty', () => {
    const ctx = makeRLContext();
    expect(windowHint(ctx)).toBe(`Inherited: ${RL_DEFAULTS.window} (built-in default)`);
    expect(maxRequestsHint(ctx)).toBe(`Inherited: ${RL_DEFAULTS.max_requests} (built-in default)`);
    expect(maxUnknownHint(ctx)).toBe(`Inherited: ${RL_DEFAULTS.max_unknown_requests} (built-in default)`);
  });

  it('an explicit value overrides the inherited default in the resolution', () => {
    const ctx = makeRLContext({ ftSrvRLWindow: '15' });
    const resolved = appConfig.computed.effectiveSrvRateLimit.call(ctx);
    expect(resolved.window).toEqual({ value: 15, from: 'server' });
    // and the other options still inherit
    expect(resolved.max_requests).toEqual({ value: RL_DEFAULTS.max_requests, from: 'default' });
  });

  it('one option set while the others inherit', () => {
    const ctx = makeRLContext({ ftSrvRLMaxRequests: '25' });
    const resolved = appConfig.computed.effectiveSrvRateLimit.call(ctx);
    expect(resolved.max_requests).toEqual({ value: 25, from: 'server' });
    expect(resolved.window).toEqual({ value: RL_DEFAULTS.window, from: 'default' });
    expect(resolved.max_unknown_requests)
      .toEqual({ value: RL_DEFAULTS.max_unknown_requests, from: 'default' });
  });

  it('an explicit 0 resolves to 0 and is not reported as inherited', () => {
    const ctx = makeRLContext({ ftSrvRLMaxRequests: '0' });
    const resolved = appConfig.computed.effectiveSrvRateLimit.call(ctx);
    expect(resolved.max_requests).toEqual({ value: 0, from: 'server' });
  });

  it('the hint is only rendered while the input is empty (template v-if)', () => {
    // The hint's v-if is `ftSrvRLWindow === ''`.
    expect(makeRLContext().ftSrvRLWindow === '').toBe(true);
    expect(makeRLContext({ ftSrvRLWindow: '30' }).ftSrvRLWindow === '').toBe(false);
  });
});

describe('Server_Editor validateRateLimit', () => {
  function v(field, value, min) {
    const ctx = { $data: { [field]: value } };
    return appConfig.methods.validateRateLimit.call(ctx, field, min);
  }

  it('treats empty as inherit (null state, no error shown)', () => {
    expect(v('f', '', 1)).toBe(null);
    expect(v('f', null, 1)).toBe(null);
    expect(v('f', undefined, 1)).toBe(null);
  });

  it('accepts an in-range integer', () => {
    expect(v('f', '60', 1)).toBe(true);
    expect(v('f', '1', 1)).toBe(true);
    expect(v('f', '0', 0)).toBe(true);
  });

  it('rejects 0 for window (min 1) but accepts it for the maximums (min 0)', () => {
    expect(v('f', '0', 1)).toBe(false);
    expect(v('f', '0', 0)).toBe(true);
  });

  it('rejects non-integers', () => {
    for (const bad of ['abc', '6.5', '-1', '1e3', ' ']) {
      expect(v('f', bad, 0)).toBe(false);
    }
  });
});
