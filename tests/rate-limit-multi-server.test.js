// ===========================================================================
// Rate limit inheritance across SEVERAL servers, the window upper bound, and the
// focus-on-error behaviour of the two save handlers.
//
// A feed can be published to more than one server, and each server generates its own
// configuration file, so an option the feed does not set is inherited from each server
// separately and can land on a different value per server. The editor has to say so
// instead of showing one server's value as the effective limit.
// ===========================================================================

import { describe, it, expect } from 'vitest';
import {
  appConfig, resolveRateLimitAcrossServers, rateLimitHint,
  RL_OPTIONS, RL_DEFAULTS, RL_WINDOW_MAX, parseErlRateLimit, resolveRateLimit,
} from 'io2';

// A feed-editor context with any number of selected servers.
function ctxWith(servers, { zoneWindow = '', zoneMax = '' } = {}) {
  const ctx = {
    ftRPZRLWindow: zoneWindow,
    ftRPZRLMaxRequests: zoneMax,
    ftRPZSrvs: servers.map((s) => s.value),
    ftRPZSrvsAll: servers,
  };
  ctx.rpzServerRateLimits = appConfig.computed.rpzServerRateLimits.call(ctx);
  ctx.effectiveRPZRateLimit = appConfig.computed.effectiveRPZRateLimit.call(ctx);
  return ctx;
}
const srv = (value, text, rl_window, rl_max_requests) => ({ value, text, rl_window, rl_max_requests });

describe('rpzServerRateLimits collects every selected server', () => {
  it('returns one entry per selected server, in selection order', () => {
    const ctx = ctxWith([srv(1, 'ns1', 30, 10), srv(2, 'ns2', null, 5)]);
    expect(ctx.rpzServerRateLimits).toEqual([
      { name: 'ns1', window: 30, max_requests: 10 },
      { name: 'ns2', window: '', max_requests: 5 },
    ]);
  });

  it('skips a selected id that is not in the loaded list', () => {
    const ctx = ctxWith([srv(1, 'ns1', 30, 10)]);
    ctx.ftRPZSrvs = [1, 99];
    expect(appConfig.computed.rpzServerRateLimits.call(ctx)).toHaveLength(1);
  });

  it('is empty when no server is selected or the list has not loaded', () => {
    expect(appConfig.computed.rpzServerRateLimits.call({ ftRPZSrvs: [], ftRPZSrvsAll: [] })).toEqual([]);
    expect(appConfig.computed.rpzServerRateLimits.call({ ftRPZSrvs: [1], ftRPZSrvsAll: null })).toEqual([]);
  });
});

describe('inheritance across several servers', () => {
  it('agreeing servers resolve to a single value', () => {
    const r = ctxWith([srv(1, 'ns1', 30, 10), srv(2, 'ns2', 30, 10)]).effectiveRPZRateLimit;
    expect(r.window).toEqual({ value: 30, from: 'server' });
    expect(r.max_requests).toEqual({ value: 10, from: 'server' });
  });

  it('disagreeing servers are reported as varying, with the per-server breakdown', () => {
    const r = ctxWith([srv(1, 'ns1', 30, 10), srv(2, 'ns2', 120, 10)]).effectiveRPZRateLimit;
    expect(r.window.ambiguous).toBe(true);
    expect(r.window.perServer).toEqual([
      { name: 'ns1', value: 30, from: 'server' },
      { name: 'ns2', value: 120, from: 'server' },
    ]);
    // the option they DO agree on is still reported as a single value
    expect(r.max_requests).toEqual({ value: 10, from: 'server' });
  });

  it('one server setting the option and another inheriting the default counts as varying', () => {
    const r = ctxWith([srv(1, 'ns1', 30, null), srv(2, 'ns2', null, null)]).effectiveRPZRateLimit;
    expect(r.window.ambiguous).toBe(true);
    expect(r.window.perServer).toEqual([
      { name: 'ns1', value: 30, from: 'server' },
      { name: 'ns2', value: RL_DEFAULTS.window, from: 'default' },
    ]);
  });

  it('a value set on the feed overrides every server, so it is never ambiguous', () => {
    const r = ctxWith([srv(1, 'ns1', 30, 10), srv(2, 'ns2', 120, 99)], { zoneWindow: '15', zoneMax: '0' }).effectiveRPZRateLimit;
    expect(r.window).toEqual({ value: 15, from: 'zone' });
    expect(r.max_requests).toEqual({ value: 0, from: 'zone' });
  });

  it('servers reaching the same value by different routes report it once, attributed to the server level', () => {
    // ns2 has no value and inherits the built-in default, which happens to equal ns1's
    const r = ctxWith([srv(1, 'ns1', RL_DEFAULTS.window, null), srv(2, 'ns2', null, null)]).effectiveRPZRateLimit;
    expect(r.window).toEqual({ value: RL_DEFAULTS.window, from: 'server' });
  });

  it('a single server behaves exactly as before', () => {
    const r = ctxWith([srv(1, 'ns1', 30, null)]).effectiveRPZRateLimit;
    expect(r.window).toEqual({ value: 30, from: 'server' });
    expect(r.max_requests).toEqual({ value: RL_DEFAULTS.max_requests, from: 'default' });
  });

  it('no servers resolves to the built-in defaults', () => {
    const r = ctxWith([]).effectiveRPZRateLimit;
    expect(r.window).toEqual({ value: RL_DEFAULTS.window, from: 'default' });
  });

  it('resolveRateLimitAcrossServers tolerates a non-array and a malformed entry', () => {
    expect(resolveRateLimitAcrossServers('', null, 'window')).toEqual({ value: RL_DEFAULTS.window, from: 'default' });
    expect(resolveRateLimitAcrossServers('', [null], 'window')).toEqual({ value: RL_DEFAULTS.window, from: 'default' });
  });
});

describe('the hint reports a varying inherited value instead of one server\'s', () => {
  it('renders the per-server breakdown', () => {
    const ctx = ctxWith([srv(1, 'ns1', 30, null), srv(2, 'ns2', 120, null)]);
    expect(appConfig.computed.effectiveRPZRLWindowHint.call(ctx))
      .toBe('Inherited: varies by server - ns1: 30, ns2: 120');
  });

  it('still renders a single value when the servers agree', () => {
    const ctx = ctxWith([srv(1, 'ns1', 30, null), srv(2, 'ns2', 30, null)]);
    expect(appConfig.computed.effectiveRPZRLWindowHint.call(ctx)).toBe('Inherited: 30 (server)');
  });

  it('falls back to a placeholder for a nameless server', () => {
    expect(rateLimitHint({ ambiguous: true, perServer: [{ name: '', value: 30 }] }))
      .toBe('Inherited: varies by server - ?: 30');
  });
});

describe('window upper bound', () => {
  const over = String(RL_WINDOW_MAX + 1);

  it('the spec caps window and leaves the maximums uncapped', () => {
    expect(RL_OPTIONS.window.max).toBe(RL_WINDOW_MAX);
    expect(RL_OPTIONS.max_requests.max).toBeUndefined();
    expect(RL_OPTIONS.max_unknown_requests.max).toBeUndefined();
  });

  it('the form validator rejects a window above the cap and accepts the cap itself', () => {
    const v = (val) => appConfig.methods.validateRateLimit.call(
      { $data: { f: val } }, 'f', 1, RL_WINDOW_MAX);
    expect(v(String(RL_WINDOW_MAX))).toBe(true);
    expect(v(over)).toBe(false);
    expect(v('0')).toBe(false);
    expect(v('')).toBe(null); // inherit
  });

  it('an uncapped maximum accepts an arbitrarily large value', () => {
    expect(appConfig.methods.validateRateLimit.call({ $data: { f: '100000000' } }, 'f', 0)).toBe(true);
  });

  it('the importer rejects a window above the cap', () => {
    const r = parseErlRateLimit('{rate_limit,[{window,' + over + '}]}', 'srv');
    expect(r.ok).toBe(false);
    expect(r.error).toContain('above the maximum');
    expect(parseErlRateLimit('{rate_limit,[{window,' + RL_WINDOW_MAX + '}]}', 'srv').ok).toBe(true);
  });

  it('resolution ignores an out-of-range stored window and falls through', () => {
    expect(resolveRateLimit(over, 30, 'window')).toEqual({ value: 30, from: 'server' });
    expect(resolveRateLimit('', over, 'window')).toEqual({ value: RL_DEFAULTS.window, from: 'default' });
  });
});

describe('an invalid rate limit moves focus to the field that blocks the save', () => {
  // Records which ref got focused, so the else-if chain can be checked without a DOM.
  function focusCtx(fields) {
    const focused = [];
    const mk = (name) => ({ $el: { focus: () => focused.push(name) } });
    const ctx = {
      $data: fields,
      ...fields,
      editRow: {},
      focused,
      $refs: {},
      // every validator the chains consult before reaching the rate limits passes
      validateName: () => true, validateIP: () => true, validateHostname: () => true,
      validateEmail: () => true, validateIPList: () => true, validateLocFile: () => true,
      validateHostnameNum: () => true, validateCustomAction: () => true,
      validateInt: () => true,
      validateRateLimit: appConfig.methods.validateRateLimit,
    };
    ['formSrvName', 'formSrvPubIP', 'formSrvIP', 'formSrvNS', 'formSrvEmail', 'formCertFile',
     'formKeyFile', 'formCACertFile', 'formSrcNotify', 'formSrvRLWindow', 'formSrvRLMaxRequests',
     'formSrvRLMaxUnknownRequests', 'formRPZName', 'formRPZNotify', 'formRPZActionCustom',
     'formRPZSOA_Refresh', 'formRPZSOA_UpdRetry', 'formRPZSOA_Exp', 'formRPZSOA_NXTTL',
     'formRPZAXFR', 'formRPZIXFR', 'formRPZRLWindow', 'formRPZRLMaxRequests',
    ].forEach((n) => { ctx.$refs[n] = mk(n); });
    return ctx;
  }
  const ev = { preventDefault: () => {} };

  const srvFields = { ftSrvRLWindow: '', ftSrvRLMaxRequests: '', ftSrvRLMaxUnknownRequests: '' };
  const rpzFields = { ftRPZRLWindow: '', ftRPZRLMaxRequests: '', ftRPZAction: 'nxdomain', ftRPZActionCustom: '' };

  it.each([
    ['ftSrvRLWindow', String(RL_WINDOW_MAX + 1), 'formSrvRLWindow'],
    ['ftSrvRLWindow', '0', 'formSrvRLWindow'],
    ['ftSrvRLMaxRequests', '-1', 'formSrvRLMaxRequests'],
    ['ftSrvRLMaxUnknownRequests', 'abc', 'formSrvRLMaxUnknownRequests'],
  ])('server editor: invalid %s focuses %s', (field, value, ref) => {
    const ctx = focusCtx({ ...srvFields, [field]: value });
    appConfig.methods.tblMgmtSrvRecord.call(ctx, ev, 'servers');
    expect(ctx.focused).toEqual([ref]);
  });

  it.each([
    ['ftRPZRLWindow', String(RL_WINDOW_MAX + 1), 'formRPZRLWindow'],
    ['ftRPZRLWindow', '0', 'formRPZRLWindow'],
    ['ftRPZRLMaxRequests', '-5', 'formRPZRLMaxRequests'],
  ])('feed editor: invalid %s focuses %s', (field, value, ref) => {
    const ctx = focusCtx({ ...rpzFields, [field]: value });
    appConfig.methods.tblMgmtRPZRecord.call(ctx, ev, 'rpzs');
    expect(ctx.focused).toEqual([ref]);
  });
});
