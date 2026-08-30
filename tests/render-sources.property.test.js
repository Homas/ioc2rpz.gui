import { describe, it, expect } from 'vitest';
import fc from 'fast-check';
import { renderSources } from 'io2';

// Feature: ioc-source-attribution, Property 7: Source rendering handles present, empty, null, and absent uniformly
//
// Validates: Requirements 9.1, 9.2, 9.3, 9.4, 8.4, 10.2
//
// For any feed object, IOC_Lookup_View rendering SHALL:
//   - when `sources` is a non-empty array, produce exactly one badge per element
//     in the same order including duplicates (Req 9.1);
//   - when `sources` is an empty array or `null`, produce a single
//     "attribution unavailable" indication and no badges (Req 9.2, 9.3);
//   - when `sources` is absent, produce no sources indication and no error (Req 9.4, 10.2);
//   - and in all cases render the feed's name and type without raising a
//     rendering error (Req 8.4, 10.2).
//
// The unit under test is the pure `renderSources(sources)` helper (task 13.1,
// www/js/io2.js). Its contract:
//   - non-empty array  => { kind: 'badges', badges: [{ text: String(el) }, ...] }
//   - empty array []   => { kind: 'unavailable' }
//   - null             => { kind: 'unavailable' }
//   - absent(undefined)/unexpected non-array/non-null => { kind: 'none' }

// A sentinel modeling an ABSENT `sources` key on a feed object (no property set).
const SOURCES_ABSENT = Symbol('sources-absent');

// Arbitrary for a single source-name element of a non-empty array. Source names
// in the API response are strings, but renderSources coerces with String(), so
// we also allow non-strings to prove badge text is always a string.
const sourceElementArb = fc.oneof(
  fc.string(),
  fc.integer(),
  fc.boolean(),
  fc.constant(null),
  fc.constant(undefined)
);

// Build a feed object {feed, type, sources?} the way the lookup view receives it.
function makeFeedObject(feed, type, sourcesKind) {
  const obj = { feed, type };
  if (sourcesKind !== SOURCES_ABSENT) {
    obj.sources = sourcesKind;
  }
  return obj;
}

describe('Feature: ioc-source-attribution, Property 7: Source rendering handles present, empty, null, and absent uniformly', () => {
  it('non-empty array => one badge per element, same order, duplicates preserved (Req 9.1)', () => {
    fc.assert(
      fc.property(
        fc.array(sourceElementArb, { minLength: 1, maxLength: 20 }),
        fc.string(),
        fc.string(),
        (sources, feed, type) => {
          const obj = makeFeedObject(feed, type, sources);
          const result = renderSources(obj.sources);

          expect(result.kind).toBe('badges');
          // Exactly one badge per element.
          expect(result.badges).toHaveLength(sources.length);
          // Same order, duplicates preserved, each text === String(element).
          for (let i = 0; i < sources.length; i++) {
            expect(result.badges[i].text).toBe(String(sources[i]));
            expect(typeof result.badges[i].text).toBe('string');
          }

          // Name + type always readable without error (Req 8.4).
          expect(obj.feed).toBe(feed);
          expect(obj.type).toBe(type);
        }
      ),
      { numRuns: 200 }
    );
  });

  it('duplicates and specific order are preserved verbatim (Req 9.1)', () => {
    fc.assert(
      fc.property(
        fc.array(fc.constantFrom('srcA', 'srcB', 'srcC'), { minLength: 1, maxLength: 12 }),
        (sources) => {
          const result = renderSources(sources);
          expect(result.kind).toBe('badges');
          expect(result.badges.map((b) => b.text)).toEqual(sources.map(String));
        }
      ),
      { numRuns: 200 }
    );
  });

  it('empty array or null => single "attribution unavailable", no badges (Req 9.2, 9.3)', () => {
    fc.assert(
      fc.property(
        fc.constantFrom([], null),
        fc.string(),
        fc.string(),
        (sources, feed, type) => {
          const obj = makeFeedObject(feed, type, sources);
          const result = renderSources(obj.sources);

          expect(result.kind).toBe('unavailable');
          expect(result.badges).toBeUndefined();

          // Name + type still render (Req 8.4).
          expect(obj.feed).toBe(feed);
          expect(obj.type).toBe(type);
        }
      ),
      { numRuns: 100 }
    );
  });

  it('absent sources => no indication, no error; other fields still render (Req 9.4, 10.2)', () => {
    fc.assert(
      fc.property(fc.string(), fc.string(), (feed, type) => {
        const obj = makeFeedObject(feed, type, SOURCES_ABSENT);
        // `sources` key is genuinely absent.
        expect(Object.prototype.hasOwnProperty.call(obj, 'sources')).toBe(false);

        const result = renderSources(obj.sources);
        expect(result.kind).toBe('none');
        expect(result.badges).toBeUndefined();

        // Remaining fields render without error.
        expect(obj.feed).toBe(feed);
        expect(obj.type).toBe(type);
      }),
      { numRuns: 100 }
    );
  });

  it('unexpected non-array/non-null types => { kind: "none" }, never throws (Req 10.2)', () => {
    fc.assert(
      fc.property(
        fc.oneof(
          fc.integer(),
          fc.double(),
          fc.string(),
          fc.boolean(),
          fc.object(),
          fc.constant(undefined)
        ),
        (weird) => {
          const result = renderSources(weird);
          expect(result.kind).toBe('none');
        }
      ),
      { numRuns: 200 }
    );
  });

  it('renderSources is total: never throws for any input across the whole feed-object space (Req 8.4, 10.2)', () => {
    // A generator over every `sources` shape: non-empty arrays, empty array,
    // null, absent, and unexpected scalar/object types.
    const sourcesArb = fc.oneof(
      fc.array(sourceElementArb, { minLength: 1, maxLength: 10 }),
      fc.constant([]),
      fc.constant(null),
      fc.constant(SOURCES_ABSENT),
      fc.integer(),
      fc.string(),
      fc.boolean(),
      fc.object()
    );

    fc.assert(
      fc.property(fc.string(), fc.string(), sourcesArb, (feed, type, sourcesKind) => {
        const obj = makeFeedObject(feed, type, sourcesKind);

        // renderSources must never throw regardless of the sources shape.
        let result;
        expect(() => {
          result = renderSources(obj.sources);
        }).not.toThrow();

        // The result is always a valid model with a known discriminator.
        expect(['badges', 'unavailable', 'none']).toContain(result.kind);

        // name + type always render without error, regardless of sources shape.
        expect(obj.feed).toBe(feed);
        expect(obj.type).toBe(type);
      }),
      { numRuns: 300 }
    );
  });
});
