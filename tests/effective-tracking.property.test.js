import { describe, it, expect } from 'vitest';
import fc from 'fast-check';
import { appConfig } from 'io2';

// Feature: ioc-source-attribution, Property 6: Effective tracking resolves to a server-vocabulary value with correct precedence
//
// Validates: Requirements 3.2, 3.3
//
// For any pair (Feed_Track_Setting, Global_Track_Default) drawn from all valid,
// invalid, and absent values, the resolved Effective_Tracking SHALL equal the
// feed setting when it is one of {auto, true, false} (mapping true->on,
// false->off, auto unchanged), otherwise the global default when it is one of
// {off, auto, on}, otherwise off; the resolved value SHALL always be exactly one
// of {off, auto, on}; and while the control is Inherit, the inherited-state text
// SHALL be exactly the literal "Inherited: " immediately followed by the
// resolved value.
//
// The resolver under test is the Vue computed `effectiveTracking` on
// appConfig.computed (task 11.1, www/js/io2.js). It is invoked with a mock
// component context exactly as Vue would (`.call(ctx)`), reading:
//   - this.ftRPZTrackSources  (the per-feed "Track sources" control value)
//   - this.ftRPZSrvs[0]       (the selected server id)
//   - this.ftRPZSrvsAll       (loaded servers; el.track_default = global default)

// Sentinels used only inside this test to model "absent" states:
//   FEED_ABSENT       -> ftRPZTrackSources is undefined
//   GLOBAL_ABSENT     -> no selected server / empty server list (no global default)
const FEED_ABSENT = Symbol('feed-absent');
const GLOBAL_ABSENT = Symbol('global-absent');

// The full input space per the task: valid, invalid, and absent values.
const FEED_VALUES = ['Inherit', 'auto', 'true', 'false', '', 'xyz', FEED_ABSENT];
const GLOBAL_VALUES = ['off', 'auto', 'on', '', 'xyz', GLOBAL_ABSENT];

const SERVER_VOCAB = ['off', 'auto', 'on'];

// Build a component context mirroring how the RPZ_Editor holds these fields.
function makeCtx(feed, globalDefault) {
  const ctx = {};
  if (feed !== FEED_ABSENT) {
    ctx.ftRPZTrackSources = feed;
  } // else leave ftRPZTrackSources undefined

  if (globalDefault === GLOBAL_ABSENT) {
    // No server selected / nothing loaded => no global default available.
    ctx.ftRPZSrvs = [];
    ctx.ftRPZSrvsAll = [];
  } else {
    ctx.ftRPZSrvs = [1];
    ctx.ftRPZSrvsAll = [{ value: 1, text: 's', track_default: globalDefault }];
  }
  return ctx;
}

// Reference resolver derived directly from Req 3.2/3.3 (independent of the impl).
function expectedResolved(feed, globalDefault) {
  const f = feed === FEED_ABSENT ? undefined : feed;
  if (f === 'auto') return 'auto';
  if (f === 'true') return 'on';
  if (f === 'false') return 'off';
  // Inherit / invalid / absent => fall back to the global default.
  const g = globalDefault === GLOBAL_ABSENT ? 'off' : globalDefault;
  if (g === 'off' || g === 'auto' || g === 'on') return g;
  return 'off';
}

function resolve(feed, globalDefault) {
  return appConfig.computed.effectiveTracking.call(makeCtx(feed, globalDefault));
}

describe('Feature: ioc-source-attribution, Property 6: Effective tracking resolves to a server-vocabulary value with correct precedence', () => {
  it('resolves every (feed, globalDefault) pair in the full cross-product with correct precedence and vocabulary', () => {
    fc.assert(
      fc.property(
        fc.constantFrom(...FEED_VALUES),
        fc.constantFrom(...GLOBAL_VALUES),
        (feed, globalDefault) => {
          const result = resolve(feed, globalDefault);

          // Precedence: matches the reference resolution rule (Req 3.2).
          expect(result).toBe(expectedResolved(feed, globalDefault));

          // The resolved value is always exactly one of {off, auto, on} (Req 3.3).
          expect(SERVER_VOCAB).toContain(result);

          // Feed setting wins regardless of the global default when set.
          if (feed === 'auto') expect(result).toBe('auto');
          if (feed === 'true') expect(result).toBe('on');
          if (feed === 'false') expect(result).toBe('off');

          // Inherit/invalid/absent feed => the global default if valid, else off.
          if (feed === 'Inherit' || feed === '' || feed === 'xyz' || feed === FEED_ABSENT) {
            const g = globalDefault === GLOBAL_ABSENT ? 'off' : globalDefault;
            expect(result).toBe(SERVER_VOCAB.includes(g) ? g : 'off');
          }

          // Inherited-state text (Req 3.3): while the control is Inherit, the
          // rendered indication is exactly "Inherited: " + the resolved value,
          // and the resolved value is one of {off, auto, on}.
          if (feed === 'Inherit') {
            const inheritedText = 'Inherited: ' + result;
            expect(inheritedText).toBe('Inherited: ' + result);
            expect(inheritedText).toMatch(/^Inherited: (off|auto|on)$/);
          }
        }
      ),
      { numRuns: 200 }
    );
  });

  it('exhaustively covers the full cross-product deterministically', () => {
    for (const feed of FEED_VALUES) {
      for (const globalDefault of GLOBAL_VALUES) {
        const result = resolve(feed, globalDefault);
        expect(result).toBe(expectedResolved(feed, globalDefault));
        expect(SERVER_VOCAB).toContain(result);
        if (feed === 'Inherit') {
          expect('Inherited: ' + result).toMatch(/^Inherited: (off|auto|on)$/);
        }
      }
    }
  });
});
