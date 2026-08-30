import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { appConfig } from 'io2';

// Unit tests for the IOC lookup view method appConfig.methods.iocLookup (task 13.2).
//
// iocLookup reads/writes all its state through `this.$root.ftLookup*` and calls
// the global `axios.get(...)`. We mirror the mock-context technique from
// tests/server-editor-track-default.test.js: build a ctx where ctx.$root = ctx,
// seed the ftLookup* fields, and stub the global axios so no real network call
// happens. global.axios is restored after each test.

// Build a mock component context seeded with the lookup data fields.
function makeContext(overrides = {}) {
  const ctx = {
    ftLookupIoc: '',
    ftLookupServer: '',
    ftLookupServersAll: [],
    ftLookupResults: [],
    ftLookupError: '',
    ftLookupNotFound: false,
    ftLookupSubmitted: false,
    ftLookupInProgress: false,
    // iocLookupErrorMessage is a real method used on the failure branch.
    iocLookupErrorMessage: appConfig.methods.iocLookupErrorMessage,
    // extractLookupFeeds normalizes the proxy response into a flat feed list.
    extractLookupFeeds: appConfig.methods.extractLookupFeeds,
    ...overrides,
  };
  ctx.$root = ctx;
  return ctx;
}

// Let queued microtasks (the axios .then/.catch callbacks) run.
const flushPromises = () => new Promise((resolve) => setTimeout(resolve, 0));

let savedAxios;

beforeEach(() => {
  savedAxios = global.axios;
});

afterEach(() => {
  global.axios = savedAxios;
  vi.restoreAllMocks();
});

describe('iocLookup client-side guard (Req 8.2)', () => {
  it('blocks an empty indicator: sets an error and never calls axios', () => {
    global.axios = { get: vi.fn() };
    const ctx = makeContext({ ftLookupIoc: '', ftLookupServer: '7' });

    appConfig.methods.iocLookup.call(ctx, null);

    expect(ctx.ftLookupError).toBeTruthy();
    expect(ctx.ftLookupError.length).toBeGreaterThan(0);
    expect(global.axios.get).not.toHaveBeenCalled();
    // guard failure must not mark a submission as in progress
    expect(ctx.ftLookupInProgress).toBe(false);
  });

  it('blocks when no server is selected: sets an error and never calls axios', () => {
    global.axios = { get: vi.fn() };
    const ctx = makeContext({ ftLookupIoc: 'evil.example.com', ftLookupServer: '' });

    appConfig.methods.iocLookup.call(ctx, null);

    expect(ctx.ftLookupError).toBeTruthy();
    expect(ctx.ftLookupError.length).toBeGreaterThan(0);
    expect(global.axios.get).not.toHaveBeenCalled();
    expect(ctx.ftLookupInProgress).toBe(false);
  });

  it('blocks an over-2048-character indicator: sets an error and never calls axios', () => {
    global.axios = { get: vi.fn() };
    const ctx = makeContext({
      ftLookupIoc: 'a'.repeat(2049),
      ftLookupServer: '7',
    });

    appConfig.methods.iocLookup.call(ctx, null);

    expect(ctx.ftLookupError).toBeTruthy();
    expect(ctx.ftLookupError.length).toBeGreaterThan(0);
    expect(global.axios.get).not.toHaveBeenCalled();
    expect(ctx.ftLookupInProgress).toBe(false);
  });

  it('calls preventDefault on the event when a guard fails', () => {
    global.axios = { get: vi.fn() };
    const ctx = makeContext({ ftLookupIoc: '', ftLookupServer: '7' });
    const ev = { preventDefault: vi.fn() };

    appConfig.methods.iocLookup.call(ctx, ev);

    expect(ev.preventDefault).toHaveBeenCalled();
    expect(global.axios.get).not.toHaveBeenCalled();
  });
});

describe('iocLookup empty-result "not found" message (Req 8.5)', () => {
  it('sets ftLookupNotFound when the proxy returns an empty array', async () => {
    global.axios = { get: vi.fn().mockResolvedValue({ data: [] }) };
    const ctx = makeContext({ ftLookupIoc: 'clean.example.com', ftLookupServer: '7' });

    appConfig.methods.iocLookup.call(ctx, null);

    // a valid submission should reach the proxy
    expect(global.axios.get).toHaveBeenCalledTimes(1);
    expect(global.axios.get.mock.calls[0][0]).toContain('/io2data.php/ioc_lookup');

    await flushPromises();

    expect(ctx.ftLookupNotFound).toBe(true);
    expect(ctx.ftLookupResults).toEqual([]);
    expect(ctx.ftLookupError).toBe('');
    expect(ctx.ftLookupInProgress).toBe(false);
  });
});

describe('iocLookup positive result (Req 8.4/8.5)', () => {
  it('populates ftLookupResults and leaves ftLookupNotFound false on a non-empty array', async () => {
    const feeds = [{ feed: 'f.example', type: 'rpz', sources: ['a'] }];
    global.axios = { get: vi.fn().mockResolvedValue({ data: feeds }) };
    const ctx = makeContext({ ftLookupIoc: 'evil.example.com', ftLookupServer: '7' });

    appConfig.methods.iocLookup.call(ctx, null);

    await flushPromises();

    // The bare-array shape carries no per-match indicator, so matchedIoc is ''.
    expect(ctx.ftLookupResults).toHaveLength(1);
    expect(ctx.ftLookupResults).toEqual([{ matchedIoc: '', feed: 'f.example', type: 'rpz', sources: ['a'] }]);
    expect(ctx.ftLookupNotFound).toBe(false);
    expect(ctx.ftLookupError).toBe('');
    expect(ctx.ftLookupInProgress).toBe(false);
  });
});

// The real ioc2rpz management API wraps results as
//   { ioc, tkey, data: [ { ioc, feeds: [...] } ] }
// rather than returning a bare array of feed objects.
describe('iocLookup real ioc2rpz response shape (wrapped { data:[{ioc,feeds}] })', () => {
  it('treats empty nested feeds as "not found in any feed" (Req 8.5)', async () => {
    global.axios = {
      get: vi.fn().mockResolvedValue({
        data: { ioc: 'time.aws.com', tkey: 'tkey_mgmt_1', data: [{ ioc: 'time.aws.com', feeds: [] }] },
      }),
    };
    const ctx = makeContext({ ftLookupIoc: 'time.aws.com', ftLookupServer: '1' });

    appConfig.methods.iocLookup.call(ctx, null);
    await flushPromises();

    expect(ctx.ftLookupNotFound).toBe(true);
    expect(ctx.ftLookupResults).toEqual([]);
    expect(ctx.ftLookupError).toBe('');
  });

  it('flattens and normalizes nested feed objects across data entries (Req 8.4)', async () => {
    global.axios = {
      get: vi.fn().mockResolvedValue({
        data: {
          ioc: 'evil.example.com',
          tkey: 'tkey_mgmt_1',
          data: [
            { ioc: 'evil.example.com', feeds: [{ feed: 'dns-bh.ioc2rpz', type: 'fqdn', sources: ['srcA', 'srcB'] }] },
          ],
        },
      }),
    };
    const ctx = makeContext({ ftLookupIoc: 'evil.example.com', ftLookupServer: '1' });

    appConfig.methods.iocLookup.call(ctx, null);
    await flushPromises();

    expect(ctx.ftLookupNotFound).toBe(false);
    expect(ctx.ftLookupResults).toEqual([
      { matchedIoc: 'evil.example.com', feed: 'dns-bh.ioc2rpz', type: 'fqdn', sources: ['srcA', 'srcB'] },
    ]);
    expect(ctx.ftLookupError).toBe('');
  });

  it('normalizes bare-string feed entries to a feed name with no sources', async () => {
    global.axios = {
      get: vi.fn().mockResolvedValue({
        data: { ioc: 'x.example', data: [{ ioc: 'x.example', feeds: ['dns-bh.ioc2rpz'] }] },
      }),
    };
    const ctx = makeContext({ ftLookupIoc: 'x.example', ftLookupServer: '1' });

    appConfig.methods.iocLookup.call(ctx, null);
    await flushPromises();

    expect(ctx.ftLookupResults).toEqual([{ matchedIoc: 'x.example', feed: 'dns-bh.ioc2rpz', type: '', sources: undefined }]);
    expect(ctx.ftLookupNotFound).toBe(false);
  });

  it('tags each feed with the specific indicator that matched it across multiple data entries', async () => {
    // A single query (www.ss-01.com) matches through the wildcard parent
    // (ss-01.com) and through the exact name, so the same feed legitimately
    // appears under both. Each row must carry the matched indicator so the
    // view can group the repeats rather than showing them as duplicates.
    global.axios = {
      get: vi.fn().mockResolvedValue({
        data: {
          ioc: 'www.ss-01.com',
          tkey: '',
          data: [
            {
              ioc: 'ss-01.com',
              feeds: [
                { feed: 'adultfree.ioc2rpz', wildcard: true, type: 'fqdn', sources: null },
                { feed: 'oisd-full.ioc2rpz', wildcard: true, type: 'mixed', sources: ['oisd_full'] },
              ],
            },
            {
              ioc: 'www.ss-01.com',
              feeds: [
                { feed: 'hblock.ioc2rpz', wildcard: true, type: 'mixed', sources: ['hblock'] },
                { feed: 'adultfree.ioc2rpz', wildcard: true, type: 'fqdn', sources: null },
              ],
            },
          ],
        },
      }),
    };
    const ctx = makeContext({ ftLookupIoc: 'www.ss-01.com', ftLookupServer: '1' });

    appConfig.methods.iocLookup.call(ctx, null);
    await flushPromises();

    expect(ctx.ftLookupNotFound).toBe(false);
    expect(ctx.ftLookupResults).toEqual([
      { matchedIoc: 'ss-01.com', feed: 'adultfree.ioc2rpz', type: 'fqdn', sources: null },
      { matchedIoc: 'ss-01.com', feed: 'oisd-full.ioc2rpz', type: 'mixed', sources: ['oisd_full'] },
      { matchedIoc: 'www.ss-01.com', feed: 'hblock.ioc2rpz', type: 'mixed', sources: ['hblock'] },
      { matchedIoc: 'www.ss-01.com', feed: 'adultfree.ioc2rpz', type: 'fqdn', sources: null },
    ]);
  });
});
