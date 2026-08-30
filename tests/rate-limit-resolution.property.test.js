import { describe, it, expect } from 'vitest';
import fc from 'fast-check';
import { appConfig, resolveRateLimit, rateLimitHint, RL_DEFAULTS, RL_OPTIONS } from 'io2';

// Feature: dns-rate-limits, Property: the resolution chain is zone ?? server ?? default
//
// Each rate limit option resolves INDEPENDENTLY with the precedence
//   RPZ zone -> server -> compile-time macro default
// so a feed may set max_requests alone and still inherit window from the server, and
// a server may set nothing at all. "Absent" (null / undefined / empty string) means
// inherit and must never mask the level below it.
//
// This mirrors the style of tests/effective-tracking.property.test.js: an independent
// reference resolver derived from the rule, checked against the production resolver
// over the full cross-product of zone value x server value x unset.

// Sentinels modelling the three shapes an "absent" value arrives in from the DB/form.
const ABSENT = [null, undefined, ''];

// Values exercised per level: absent in all three shapes, the zero edge (a legal
// value for the maximums meaning "refuse everything", and OUT of range for window),
// ordinary values, a negative (always out of range), and non-numeric junk.
const VALUES = [...ABSENT, 0, 1, 6, 60, 3600, -1, 'abc', '12x', '6', '0'];

const OPTIONS = ['window', 'max_requests', 'max_unknown_requests'];

// Reference resolver derived straight from the rule, independent of the impl.
function expectedResolve(zone, srv, option) {
  const min = RL_OPTIONS[option].min;
  const usable = (raw) => {
    if (raw === null || raw === undefined || raw === '') return null;
    if (typeof raw === 'string' && !/^\s*-?[0-9]+\s*$/.test(raw)) return null;
    const n = typeof raw === 'number' ? raw : parseInt(raw, 10);
    if (!Number.isInteger(n) || n < min) return null;
    return n;
  };
  const z = usable(zone);
  if (z !== null) return { value: z, from: 'zone' };
  const s = usable(srv);
  if (s !== null) return { value: s, from: 'server' };
  return { value: RL_DEFAULTS[option], from: 'default' };
}

describe('Feature: dns-rate-limits, Property: option resolution is zone ?? server ?? macro default', () => {
  it('resolves every (zone, server, option) triple with the documented precedence', () => {
    fc.assert(
      fc.property(
        fc.constantFrom(...VALUES),
        fc.constantFrom(...VALUES),
        fc.constantFrom(...OPTIONS),
        (zone, srv, option) => {
          const got = resolveRateLimit(zone, srv, option);
          const want = expectedResolve(zone, srv, option);

          expect(got.value).toBe(want.value);
          expect(got.from).toBe(want.from);

          // The resolved value is always a valid integer at or above the minimum.
          expect(Number.isInteger(got.value)).toBe(true);
          expect(got.value).toBeGreaterThanOrEqual(RL_OPTIONS[option].min);

          // Origin is always one of the three levels.
          expect(['zone', 'server', 'default']).toContain(got.from);
        }
      ),
      { numRuns: 500 }
    );
  });

  it('exhaustively covers the full cross-product deterministically', () => {
    for (const zone of VALUES) {
      for (const srv of VALUES) {
        for (const option of OPTIONS) {
          const got = resolveRateLimit(zone, srv, option);
          expect(got).toEqual(expectedResolve(zone, srv, option));
        }
      }
    }
  });

  it('an absent zone value never masks the server value', () => {
    for (const absent of ABSENT) {
      expect(resolveRateLimit(absent, 30, 'window')).toEqual({ value: 30, from: 'server' });
      expect(resolveRateLimit(absent, 0, 'max_requests')).toEqual({ value: 0, from: 'server' });
    }
  });

  it('absent at both levels falls back to the macro default, per option', () => {
    for (const option of OPTIONS) {
      expect(resolveRateLimit('', '', option)).toEqual({
        value: RL_DEFAULTS[option],
        from: 'default',
      });
    }
    // The documented macro defaults.
    expect(RL_DEFAULTS.window).toBe(60);
    expect(RL_DEFAULTS.max_requests).toBe(6);
    expect(RL_DEFAULTS.max_unknown_requests).toBe(1);
  });

  it('an explicit 0 wins over the level below it and is never treated as absent', () => {
    // 0 is a legal maximum meaning "refuse every request in that bucket". A `||`-style
    // falsy check would wrongly treat it as inherit; this pins that it does not.
    expect(resolveRateLimit(0, 20, 'max_requests')).toEqual({ value: 0, from: 'zone' });
    expect(resolveRateLimit('0', 20, 'max_requests')).toEqual({ value: 0, from: 'zone' });
    expect(resolveRateLimit('', 0, 'max_requests')).toEqual({ value: 0, from: 'server' });
    expect(resolveRateLimit(0, 20, 'max_unknown_requests')).toEqual({ value: 0, from: 'zone' });
  });

  it('rejects 0 for window (out of range) and falls through to the next level', () => {
    // window must be > 0, so a stored 0 is not usable and must not win.
    expect(resolveRateLimit(0, 30, 'window')).toEqual({ value: 30, from: 'server' });
    expect(resolveRateLimit(0, '', 'window')).toEqual({ value: 60, from: 'default' });
  });

  it('options resolve independently: one overridden, the other inherited', () => {
    // Feed sets max_requests only; window still comes from the server.
    const win = resolveRateLimit('', 30, 'window');
    const max = resolveRateLimit(20, 6, 'max_requests');
    expect(win).toEqual({ value: 30, from: 'server' });
    expect(max).toEqual({ value: 20, from: 'zone' });

    // Feed sets window only; max_requests falls all the way to the macro default.
    expect(resolveRateLimit(15, '', 'window')).toEqual({ value: 15, from: 'zone' });
    expect(resolveRateLimit('', '', 'max_requests')).toEqual({ value: 6, from: 'default' });
  });
});

describe('rateLimitHint names both the value and where it came from', () => {
  it('distinguishes a server-inherited value from the built-in default', () => {
    expect(rateLimitHint({ value: 30, from: 'server' })).toBe('Inherited: 30 (server)');
    expect(rateLimitHint({ value: 60, from: 'default' })).toBe('Inherited: 60 (built-in default)');
    expect(rateLimitHint({ value: 20, from: 'zone' })).toBe('Inherited: 20 (this feed)');
  });

  it('renders the resolved value for every (zone, server) pair as a stable string', () => {
    fc.assert(
      fc.property(
        fc.constantFrom(...VALUES),
        fc.constantFrom(...VALUES),
        fc.constantFrom(...OPTIONS),
        (zone, srv, option) => {
          const hint = rateLimitHint(resolveRateLimit(zone, srv, option));
          expect(hint).toMatch(/^Inherited: [0-9]+ \((server|this feed|built-in default)\)$/);
        }
      ),
      { numRuns: 200 }
    );
  });
});

// The editors expose the resolution through computed properties, so those are checked
// against the same rule with mock component contexts (as Vue would invoke them).
describe('editor computed properties resolve through the same chain', () => {
  function rpzCtx({ zoneWindow = '', zoneMax = '', srvWindow = null, srvMax = null, withServer = true } = {}) {
    const ctx = {
      ftRPZRLWindow: zoneWindow,
      ftRPZRLMaxRequests: zoneMax,
      ftRPZSrvs: withServer ? [5] : [],
      ftRPZSrvsAll: withServer
        ? [{ value: 5, text: 'srv', rl_window: srvWindow, rl_max_requests: srvMax }]
        : [],
    };
    ctx.rpzServerRateLimits = appConfig.computed.rpzServerRateLimits.call(ctx);
    return ctx;
  }

  function rpzResolved(opts) {
    const ctx = rpzCtx(opts);
    return appConfig.computed.effectiveRPZRateLimit.call(ctx);
  }

  it('feed value wins over the server value', () => {
    const r = rpzResolved({ zoneWindow: '15', srvWindow: 30 });
    expect(r.window).toEqual({ value: 15, from: 'zone' });
  });

  it('empty feed value inherits the server value', () => {
    const r = rpzResolved({ zoneWindow: '', srvWindow: 30 });
    expect(r.window).toEqual({ value: 30, from: 'server' });
  });

  it('empty at both levels inherits the built-in default', () => {
    const r = rpzResolved({ zoneWindow: '', srvWindow: null });
    expect(r.window).toEqual({ value: 60, from: 'default' });
    expect(r.max_requests).toEqual({ value: 6, from: 'default' });
  });

  it('one option overridden while the other inherits', () => {
    const r = rpzResolved({ zoneMax: '20', srvWindow: 30, srvMax: 6 });
    expect(r.max_requests).toEqual({ value: 20, from: 'zone' });
    expect(r.window).toEqual({ value: 30, from: 'server' });
  });

  it('no server selected resolves to the built-in defaults', () => {
    const r = rpzResolved({ withServer: false });
    expect(r.window).toEqual({ value: 60, from: 'default' });
    expect(r.max_requests).toEqual({ value: 6, from: 'default' });
  });

  it('an explicit feed 0 for max_requests resolves to 0, not to the inherited value', () => {
    const r = rpzResolved({ zoneMax: '0', srvMax: 20 });
    expect(r.max_requests).toEqual({ value: 0, from: 'zone' });
  });

  it('the server editor resolves its own values against the built-in defaults', () => {
    const ctx = { ftSrvRLWindow: '', ftSrvRLMaxRequests: '30', ftSrvRLMaxUnknownRequests: '0' };
    const r = appConfig.computed.effectiveSrvRateLimit.call(ctx);
    // The server is the second link, so there is no zone level: empty inherits the default.
    expect(r.window).toEqual({ value: 60, from: 'default' });
    expect(r.max_requests).toEqual({ value: 30, from: 'server' });
    expect(r.max_unknown_requests).toEqual({ value: 0, from: 'server' });
  });
});
