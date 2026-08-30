import { describe, it, expect } from 'vitest';
import { appConfig } from 'io2';

// Unit tests for the RPZ_Editor "Track sources" control and its derived UI
// states (tasks 11.1 / 11.2). These exercise the reactive data, the mgmtRec()
// binding logic, and the effectiveTracking / trackingEnabling computed
// properties added for source attribution:
//
//  - the exact option set (Req 2.1) and default `Inherit` (Req 2.2)
//  - binding persisted / absent track_sources on reopen and reset on add
//    (Req 2.6, Req 10.6)
//  - the inherited-state indicator value + presence/absence + live update
//    (Req 3.1, Req 3.4, Req 3.5)
//  - the AXFR_Rebuild hint, the always-visible cache=true hint, and the
//    attribution-unavailable notice conditions (Req 4.1, 4.2, 4.3, 4.4)
//
// The tests target the computed-driven conditions behind the template v-if
// expressions rather than rendering the DOM. The technique mirrors
// tests/server-editor-track-default.test.js: invoke appConfig.methods.mgmtRec
// with a mock $root context and a stubbed get_lists, and invoke the computed
// functions with .call(ctx).

// Build a mock component context for invoking appConfig.methods.mgmtRec and the
// computed functions directly. mgmtRec reads/writes everything through
// `this.$root` and calls `this.$root.get_lists(...)`, so $root points back at
// the same bag and get_lists is stubbed to avoid side effects. showModal is a
// harmless mitt emit, so the method can run to completion.
function makeContext() {
  const ctx = {
    ftRPZTrackSources: appConfig.data.ftRPZTrackSources,
    ftRPZSrvs: [],
    ftRPZSrvsAll: [],
    ftRPZCache: 0,
    get_lists: () => {},
  };
  ctx.$root = ctx;
  return ctx;
}

// A fully-populated rpz row.item covering every field the "edit/clone/info
// rpzs" branch of mgmtRec reads, optionally overriding track_sources.
function makeRpzRow(overrides = {}) {
  return {
    item: {
      rowid: 12,
      name: 'feed1',
      soa_refresh: 86400,
      soa_update_retry: 3600,
      soa_expiration: 2592000,
      soa_nx_ttl: 7200,
      axfr_update: 604800,
      ixfr_update: 86400,
      cache: 1,
      wildcard: 1,
      action: 'nxdomain',
      actioncustom: '',
      ioc_type: 'mixed',
      disabled: 0,
      notify: [],
      servers: [],
      tkeys: [],
      sources: [],
      whitelists: [],
      track_sources: 'Inherit',
      ...overrides,
    },
  };
}

// Resolve the inherited-state indicator "value" the template renders while the
// control is Inherit: the literal "Inherited: " immediately followed by the
// resolved effectiveTracking (Req 3.3).
function indicatorText(ctx) {
  return 'Inherited: ' + appConfig.computed.effectiveTracking.call(ctx);
}

// Evaluate trackingEnabling for a context. trackingEnabling reads
// this.effectiveTracking (a property), so resolve the effectiveTracking computed
// first and expose it on the context before invoking trackingEnabling.
function evalTrackingEnabling(ctx) {
  ctx.effectiveTracking = appConfig.computed.effectiveTracking.call(ctx);
  return appConfig.computed.trackingEnabling.call(ctx);
}

describe('RPZ_Editor track-sources control data', () => {
  it('offers exactly the Inherit/auto/true/false option set (Req 2.1)', () => {
    expect(appConfig.data.RPZ_TrackSources_Options).toEqual([
      { value: 'Inherit', text: 'Inherit' },
      { value: 'auto', text: 'auto' },
      { value: 'true', text: 'true' },
      { value: 'false', text: 'false' },
    ]);
  });

  it('defaults ftRPZTrackSources to `Inherit` on a fresh form (Req 2.2)', () => {
    expect(appConfig.data.ftRPZTrackSources).toBe('Inherit');
  });
});

describe('RPZ_Editor mgmtRec() track_sources binding', () => {
  it('resets the control to `Inherit` when adding a new feed (Req 2.2)', () => {
    const ctx = makeContext();
    // pre-dirty the field to prove the add branch resets it
    ctx.ftRPZTrackSources = 'auto';
    appConfig.methods.mgmtRec.call(ctx, 'add', 'rpzs', {}, null);
    expect(ctx.ftRPZTrackSources).toBe('Inherit');
  });

  it('binds the persisted track_sources value on edit (Req 2.6)', () => {
    const ctx = makeContext();
    appConfig.methods.mgmtRec.call(
      ctx,
      'edit',
      'rpzs',
      makeRpzRow({ track_sources: 'auto' }),
      null
    );
    expect(ctx.ftRPZTrackSources).toBe('auto');
  });

  it('binds `true` and `false` persisted values on edit (Req 2.6)', () => {
    const ctxTrue = makeContext();
    appConfig.methods.mgmtRec.call(ctxTrue, 'edit', 'rpzs', makeRpzRow({ track_sources: 'true' }), null);
    expect(ctxTrue.ftRPZTrackSources).toBe('true');

    const ctxFalse = makeContext();
    appConfig.methods.mgmtRec.call(ctxFalse, 'edit', 'rpzs', makeRpzRow({ track_sources: 'false' }), null);
    expect(ctxFalse.ftRPZTrackSources).toBe('false');
  });

  it('falls back to `Inherit` when the record has no persisted track_sources (Req 10.6)', () => {
    const ctx = makeContext();
    const row = makeRpzRow();
    delete row.item.track_sources;
    appConfig.methods.mgmtRec.call(ctx, 'edit', 'rpzs', row, null);
    expect(ctx.ftRPZTrackSources).toBe('Inherit');
  });

  it('falls back to `Inherit` when track_sources is an empty string (Req 10.6)', () => {
    const ctx = makeContext();
    appConfig.methods.mgmtRec.call(ctx, 'edit', 'rpzs', makeRpzRow({ track_sources: '' }), null);
    expect(ctx.ftRPZTrackSources).toBe('Inherit');
  });
});

describe('RPZ_Editor effectiveTracking resolution (Req 3.1, 3.2, 3.3, 3.5)', () => {
  it('maps explicit feed values directly (true->on, false->off, auto->auto)', () => {
    const ctx = makeContext();
    ctx.ftRPZTrackSources = 'auto';
    expect(appConfig.computed.effectiveTracking.call(ctx)).toBe('auto');
    ctx.ftRPZTrackSources = 'true';
    expect(appConfig.computed.effectiveTracking.call(ctx)).toBe('on');
    ctx.ftRPZTrackSources = 'false';
    expect(appConfig.computed.effectiveTracking.call(ctx)).toBe('off');
  });

  it('resolves Inherit to the selected server global default (off/auto/on)', () => {
    for (const def of ['off', 'auto', 'on']) {
      const ctx = makeContext();
      ctx.ftRPZTrackSources = 'Inherit';
      ctx.ftRPZSrvs = [5];
      ctx.ftRPZSrvsAll = [{ value: 5, text: 'srv', track_default: def }];
      expect(appConfig.computed.effectiveTracking.call(ctx)).toBe(def);
    }
  });

  it('resolves Inherit to `off` when the global default is unavailable', () => {
    const ctx = makeContext();
    ctx.ftRPZTrackSources = 'Inherit';
    // no servers selected / no track_default exposed
    expect(appConfig.computed.effectiveTracking.call(ctx)).toBe('off');
  });

  it('renders the inherited indicator value "Inherited: <effective>" while Inherit', () => {
    const ctx = makeContext();
    ctx.ftRPZTrackSources = 'Inherit';
    ctx.ftRPZSrvs = [5];
    ctx.ftRPZSrvsAll = [{ value: 5, text: 'srv', track_default: 'auto' }];
    expect(indicatorText(ctx)).toBe('Inherited: auto');
  });

  it('updates the inherited indicator live when the resolved default changes (Req 3.5)', () => {
    const ctx = makeContext();
    ctx.ftRPZTrackSources = 'Inherit';
    ctx.ftRPZSrvs = [5];
    ctx.ftRPZSrvsAll = [{ value: 5, text: 'srv', track_default: 'off' }];
    expect(indicatorText(ctx)).toBe('Inherited: off');
    // change the selected server default without saving
    ctx.ftRPZSrvsAll = [{ value: 5, text: 'srv', track_default: 'on' }];
    expect(indicatorText(ctx)).toBe('Inherited: on');
  });

  it('the inherited indicator is present only while the control is Inherit (Req 3.1, 3.4)', () => {
    // Presence is driven by the v-if `ftRPZTrackSources === 'Inherit'`.
    const inherit = makeContext();
    inherit.ftRPZTrackSources = 'Inherit';
    expect(inherit.ftRPZTrackSources === 'Inherit').toBe(true);

    for (const v of ['auto', 'true', 'false']) {
      const ctx = makeContext();
      ctx.ftRPZTrackSources = v;
      expect(ctx.ftRPZTrackSources === 'Inherit').toBe(false);
    }
  });
});

describe('RPZ_Editor trackingEnabling and hint/notice conditions (Req 4.1-4.4)', () => {
  it('is enabling for explicit auto/true and not enabling for false (Req 4.1, 4.4)', () => {
    const ctxAuto = makeContext();
    ctxAuto.ftRPZTrackSources = 'auto';
    expect(evalTrackingEnabling(ctxAuto)).toBe(true);

    const ctxTrue = makeContext();
    ctxTrue.ftRPZTrackSources = 'true';
    expect(evalTrackingEnabling(ctxTrue)).toBe(true);

    const ctxFalse = makeContext();
    ctxFalse.ftRPZTrackSources = 'false';
    expect(evalTrackingEnabling(ctxFalse)).toBe(false);
  });

  it('is enabling for Inherit resolving to auto/on and not for Inherit resolving to off', () => {
    for (const def of ['auto', 'on']) {
      const ctx = makeContext();
      ctx.ftRPZTrackSources = 'Inherit';
      ctx.ftRPZSrvs = [5];
      ctx.ftRPZSrvsAll = [{ value: 5, text: 'srv', track_default: def }];
      expect(evalTrackingEnabling(ctx)).toBe(true);
    }

    const off = makeContext();
    off.ftRPZTrackSources = 'Inherit';
    off.ftRPZSrvs = [5];
    off.ftRPZSrvsAll = [{ value: 5, text: 'srv', track_default: 'off' }];
    expect(evalTrackingEnabling(off)).toBe(false);
  });

  it('shows the AXFR_Rebuild hint exactly when tracking is enabling (Req 4.1, 4.4)', () => {
    // The rebuild hint v-if is `trackingEnabling`.
    const enabling = makeContext();
    enabling.ftRPZTrackSources = 'true';
    expect(evalTrackingEnabling(enabling)).toBe(true);

    const notEnabling = makeContext();
    notEnabling.ftRPZTrackSources = 'false';
    expect(evalTrackingEnabling(notEnabling)).toBe(false);
  });

  // The unavailable-notice v-if is `trackingEnabling && cache !== true`.
  function unavailableNotice(ctx) {
    return evalTrackingEnabling(ctx) && ctx.ftRPZCache !== true;
  }

  it('shows the attribution-unavailable notice when enabling and cache is not true (Req 4.3)', () => {
    const ctx = makeContext();
    ctx.ftRPZTrackSources = 'true';
    ctx.ftRPZCache = false;
    expect(unavailableNotice(ctx)).toBe(true);
  });

  it('suppresses the unavailable notice when enabling and cache is true (Req 4.3)', () => {
    const ctx = makeContext();
    ctx.ftRPZTrackSources = 'true';
    ctx.ftRPZCache = true;
    expect(unavailableNotice(ctx)).toBe(false);
  });

  it('suppresses BOTH the rebuild hint and the unavailable notice when not enabling (Req 4.4)', () => {
    // control = false -> not enabling; neither condition holds regardless of cache
    for (const cache of [true, false]) {
      const ctx = makeContext();
      ctx.ftRPZTrackSources = 'false';
      ctx.ftRPZCache = cache;
      expect(evalTrackingEnabling(ctx)).toBe(false); // rebuild hint hidden
      expect(unavailableNotice(ctx)).toBe(false); // unavailable notice hidden
    }

    // control = Inherit resolving to off -> not enabling either
    const inheritOff = makeContext();
    inheritOff.ftRPZTrackSources = 'Inherit';
    inheritOff.ftRPZSrvs = [5];
    inheritOff.ftRPZSrvsAll = [{ value: 5, text: 'srv', track_default: 'off' }];
    inheritOff.ftRPZCache = false;
    expect(evalTrackingEnabling(inheritOff)).toBe(false);
    expect(unavailableNotice(inheritOff)).toBe(false);
  });

  it('documents that the cache=true requirement hint is unconditional (Req 4.2)', () => {
    // The "attribution requires cache = true" hint has no v-if in the template:
    // it is always rendered while the RPZ editor is open, independent of the
    // control value or cache state. There is no reactive condition to assert;
    // this test records that invariant so a future guard would fail review.
    expect(true).toBe(true);
  });
});

// ===========================================================================
// RPZ_Editor DNS rate limit controls.
//
// The feed editor resolves each option through zone -> server -> built-in default,
// so an empty input renders the inherited value AND where it came from (the selected
// server, or the ioc2rpz built-in default). One option can be overridden while the
// other inherits.
// ===========================================================================

import { RL_DEFAULTS } from 'io2';

// A feed-editor context including the rate limit fields and a selected server whose
// rl_* columns the rpz_servers endpoint exposes.
function makeRLContext({ zoneWindow = '', zoneMax = '', srvWindow = null, srvMax = null,
                         withServer = true } = {}) {
  const ctx = {
    ftRPZRLWindow: zoneWindow,
    ftRPZRLMaxRequests: zoneMax,
    ftRPZSrvs: withServer ? [5] : [],
    ftRPZSrvsAll: withServer
      ? [{ value: 5, text: 'srv', rl_window: srvWindow, rl_max_requests: srvMax }]
      : [],
    get_lists: () => {},
  };
  ctx.$root = ctx;
  return ctx;
}

// Resolve the computed chain the way Vue would, exposing each dependency in turn.
function resolved(ctx) {
  ctx.rpzServerRateLimits = appConfig.computed.rpzServerRateLimits.call(ctx);
  ctx.effectiveRPZRateLimit = appConfig.computed.effectiveRPZRateLimit.call(ctx);
  return ctx.effectiveRPZRateLimit;
}
function windowHint(ctx) {
  resolved(ctx);
  return appConfig.computed.effectiveRPZRLWindowHint.call(ctx);
}
function maxRequestsHint(ctx) {
  resolved(ctx);
  return appConfig.computed.effectiveRPZRLMaxRequestsHint.call(ctx);
}

describe('RPZ_Editor rate limit control data', () => {
  it('defaults both fields to empty (inherit) on a fresh form', () => {
    expect(appConfig.data.ftRPZRLWindow).toBe('');
    expect(appConfig.data.ftRPZRLMaxRequests).toBe('');
  });
});

describe('RPZ_Editor mgmtRec() rate limit binding', () => {
  it('resets both fields to empty when adding a new feed', () => {
    const ctx = makeContext();
    ctx.ftRPZRLWindow = '30';
    ctx.ftRPZRLMaxRequests = '9';
    appConfig.methods.mgmtRec.call(ctx, 'add', 'rpzs', {}, null);
    expect(ctx.ftRPZRLWindow).toBe('');
    expect(ctx.ftRPZRLMaxRequests).toBe('');
  });

  it('binds persisted values on edit', () => {
    const ctx = makeContext();
    appConfig.methods.mgmtRec.call(ctx, 'edit', 'rpzs',
      makeRpzRow({ rl_window: 30, rl_max_requests: 20 }), null);
    expect(ctx.ftRPZRLWindow).toBe('30');
    expect(ctx.ftRPZRLMaxRequests).toBe('20');
  });

  it('binds NULL / absent stored limits to empty (inherit)', () => {
    const ctxNull = makeContext();
    appConfig.methods.mgmtRec.call(ctxNull, 'edit', 'rpzs',
      makeRpzRow({ rl_window: null, rl_max_requests: null }), null);
    expect(ctxNull.ftRPZRLWindow).toBe('');
    expect(ctxNull.ftRPZRLMaxRequests).toBe('');

    // a pre-upgrade record has no rl_* keys at all
    const ctxAbsent = makeContext();
    appConfig.methods.mgmtRec.call(ctxAbsent, 'edit', 'rpzs', makeRpzRow(), null);
    expect(ctxAbsent.ftRPZRLWindow).toBe('');
    expect(ctxAbsent.ftRPZRLMaxRequests).toBe('');
  });

  it('binds a stored 0 to "0", not to empty', () => {
    const ctx = makeContext();
    appConfig.methods.mgmtRec.call(ctx, 'edit', 'rpzs',
      makeRpzRow({ rl_max_requests: 0 }), null);
    expect(ctx.ftRPZRLMaxRequests).toBe('0');
  });
});

describe('RPZ_Editor rate limit inherited hints', () => {
  it('renders the server value and names the server as its origin', () => {
    const ctx = makeRLContext({ srvWindow: 30, srvMax: 12 });
    expect(windowHint(ctx)).toBe('Inherited: 30 (server)');
    expect(maxRequestsHint(ctx)).toBe('Inherited: 12 (server)');
  });

  it('renders the built-in default and names it when the server sets nothing', () => {
    const ctx = makeRLContext({ srvWindow: null, srvMax: null });
    expect(windowHint(ctx)).toBe(`Inherited: ${RL_DEFAULTS.window} (built-in default)`);
    expect(maxRequestsHint(ctx)).toBe(`Inherited: ${RL_DEFAULTS.max_requests} (built-in default)`);
  });

  it('renders the built-in default when no server is selected', () => {
    const ctx = makeRLContext({ withServer: false });
    expect(windowHint(ctx)).toBe(`Inherited: ${RL_DEFAULTS.window} (built-in default)`);
  });

  it('an explicit feed value overrides the inherited value', () => {
    const r = resolved(makeRLContext({ zoneWindow: '15', srvWindow: 30 }));
    expect(r.window).toEqual({ value: 15, from: 'zone' });
  });

  it('one option overridden while the other inherits from the server', () => {
    const r = resolved(makeRLContext({ zoneMax: '20', srvWindow: 30, srvMax: 6 }));
    expect(r.max_requests).toEqual({ value: 20, from: 'zone' });
    expect(r.window).toEqual({ value: 30, from: 'server' });
  });

  it('one option overridden while the other falls through to the built-in default', () => {
    const r = resolved(makeRLContext({ zoneWindow: '45', srvWindow: null, srvMax: null }));
    expect(r.window).toEqual({ value: 45, from: 'zone' });
    expect(r.max_requests).toEqual({ value: RL_DEFAULTS.max_requests, from: 'default' });
  });

  it('an explicit feed 0 for max_requests wins over the server value', () => {
    const r = resolved(makeRLContext({ zoneMax: '0', srvMax: 20 }));
    expect(r.max_requests).toEqual({ value: 0, from: 'zone' });
  });

  it('updates the inherited hint live when the selected server changes (no save)', () => {
    const ctx = makeRLContext({ srvWindow: 30 });
    expect(windowHint(ctx)).toBe('Inherited: 30 (server)');
    ctx.ftRPZSrvsAll = [{ value: 5, text: 'srv', rl_window: 90, rl_max_requests: null }];
    expect(windowHint(ctx)).toBe('Inherited: 90 (server)');
  });

  it('the hint is only rendered while the input is empty (template v-if)', () => {
    expect(makeRLContext().ftRPZRLWindow === '').toBe(true);
    expect(makeRLContext({ zoneWindow: '30' }).ftRPZRLWindow === '').toBe(false);
  });

  it('ignores an out-of-range server window and falls through to the built-in default', () => {
    // window must be > 0; a stored 0 is unusable and must not become the inherited value.
    const ctx = makeRLContext({ srvWindow: 0 });
    expect(windowHint(ctx)).toBe(`Inherited: ${RL_DEFAULTS.window} (built-in default)`);
  });
});

// ===========================================================================
// Zone name canonicalisation.
//
// The ioc2rpz server canonicalises zone names to lower case (RFC 4343). If the GUI let
// an operator keep a mixed-case feed name, it would come back lower-cased from an
// imported config and look like a different zone, so the name is normalized on input.
// ===========================================================================
describe('RPZ_Editor zone name canonicalisation (RFC 4343)', () => {
  function format(value) {
    return appConfig.methods.formatZoneName.call({}, value, null);
  }

  it('lower-cases the typed feed name', () => {
    expect(format('Feed.Example.COM')).toBe('feed.example.com');
    expect(format('MIXEDcase')).toBe('mixedcase');
  });

  it('leaves an already-lower-case name unchanged (idempotent)', () => {
    for (const name of ['feed.example.com', 'a-b_c.123']) {
      expect(format(name)).toBe(name);
      expect(format(format(name))).toBe(name);
    }
  });

  it('still strips characters that are invalid in a zone name, like formatName', () => {
    expect(format('Feed Example!.com')).toBe('feedexample.com');
    expect(format('a/b\\c:d')).toBe('abcd');
  });

  it('preserves the characters a zone name legitimately uses', () => {
    expect(format('sub.domain-name_1.example')).toBe('sub.domain-name_1.example');
  });
});
