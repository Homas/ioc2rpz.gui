import { describe, it, expect } from 'vitest';
import {
  countErlTupleFields,
  splitErlTupleFields,
  parseErlRateLimit,
  classifyErlTrailingElements,
} from 'io2';

// Unit tests for the top-level field counter used by ImportIOC2RPZ() to report
// an accurate field count when a srv/rpz tuple matches neither accepted arity.
describe('countErlTupleFields', () => {
  it('counts a legacy 4-field srv tuple body', () => {
    // {srv,{Server, Email, [MKeys], [ACL]}}
    const body = '"ns.example.com","admin.example.com",[key1,key2],[1.2.3.4]';
    expect(countErlTupleFields(body)).toBe(4);
  });

  it('counts an attribution 5-field srv tuple body', () => {
    const body = '"ns.example.com","admin.example.com",[key1],[1.2.3.4],on';
    expect(countErlTupleFields(body)).toBe(5);
  });

  it('ignores commas nested in lists and tuples', () => {
    const body = '"a","b",[k1,k2,k3],[acl1,acl2],{nested,x,y}';
    expect(countErlTupleFields(body)).toBe(5);
  });

  it('ignores commas inside quoted strings', () => {
    const body = '"a,b,c","d,e",[k1,k2]';
    expect(countErlTupleFields(body)).toBe(3);
  });

  it('reports a wrong arity (6 fields) for a malformed srv tuple', () => {
    const body = '"ns","email",[k1],[acl],on,extra';
    expect(countErlTupleFields(body)).toBe(6);
  });

  it('returns 0 for an empty or whitespace body', () => {
    expect(countErlTupleFields('')).toBe(0);
    expect(countErlTupleFields('   ')).toBe(0);
    expect(countErlTupleFields(null)).toBe(0);
    expect(countErlTupleFields(undefined)).toBe(0);
  });
});

// Field counting with the rate limit element present. The whole reason the reader
// tokenizes the tail instead of capturing it positionally is that a
// {rate_limit,[...]} tuple contains the commas, braces and brackets a positional
// group has to exclude - these pin that the counter sees it as ONE field.
describe('countErlTupleFields with a rate_limit element', () => {
  it('counts a rate_limit tuple as a single top-level field (5-field srv)', () => {
    const body = '"ns","email",[k1],[acl],{rate_limit,[{window,60},{max_requests,6}]}';
    expect(countErlTupleFields(body)).toBe(5);
  });

  it('counts TrackSources + rate_limit as two trailing fields (6-field srv)', () => {
    const body = '"ns","email",[k1],[acl],on,{rate_limit,[{window,60}]}';
    expect(countErlTupleFields(body)).toBe(6);
  });

  it('counts the same 6 fields with the trailing elements in the reverse order', () => {
    const body = '"ns","email",[k1],[acl],{rate_limit,[{window,60}]},on';
    expect(countErlTupleFields(body)).toBe(6);
  });

  it('counts an empty rate_limit option list as one field', () => {
    expect(countErlTupleFields('"ns","email",[k1],[acl],{rate_limit,[]}')).toBe(5);
  });
});

describe('splitErlTupleFields', () => {
  it('splits a legacy 4-field srv body', () => {
    expect(splitErlTupleFields('"ns.example.com","admin.example.com",[key1,key2],[1.2.3.4]'))
      .toEqual(['"ns.example.com"', '"admin.example.com"', '[key1,key2]', '[1.2.3.4]']);
  });

  it('keeps a rate_limit tuple intact as one field', () => {
    expect(splitErlTupleFields('"ns","email",[k1],[acl],{rate_limit,[{window,60},{max_requests,6}]}'))
      .toEqual(['"ns"', '"email"', '[k1]', '[acl]', '{rate_limit,[{window,60},{max_requests,6}]}']);
  });

  it('preserves the order of both trailing elements, either way round', () => {
    expect(splitErlTupleFields('"ns","email",[k1],[acl],on,{rate_limit,[{window,60}]}').slice(4))
      .toEqual(['on', '{rate_limit,[{window,60}]}']);
    expect(splitErlTupleFields('"ns","email",[k1],[acl],{rate_limit,[{window,60}]},on').slice(4))
      .toEqual(['{rate_limit,[{window,60}]}', 'on']);
  });

  it('ignores commas inside quoted strings', () => {
    expect(splitErlTupleFields('"a,b,c","d,e",[k1,k2]'))
      .toEqual(['"a,b,c"', '"d,e"', '[k1,k2]']);
  });

  it('ignores commas nested in lists and tuples at any depth', () => {
    expect(splitErlTupleFields('[a,[b,c],{d,[e,f]}],x'))
      .toEqual(['[a,[b,c],{d,[e,f]}]', 'x']);
  });

  it('trims surrounding whitespace from each field', () => {
    expect(splitErlTupleFields(' "a" , "b" , [c] ')).toEqual(['"a"', '"b"', '[c]']);
  });

  it('returns an empty array for an empty or whitespace-only body, matching countErlTupleFields', () => {
    for (const body of ['', '   ', null, undefined]) {
      expect(splitErlTupleFields(body)).toEqual([]);
      expect(countErlTupleFields(body)).toBe(0);
    }
  });

  it('agrees with countErlTupleFields on the field count', () => {
    for (const body of [
      '"ns","email",[k1],[acl]',
      '"ns","email",[k1],[acl],on',
      '"ns","email",[k1],[acl],{rate_limit,[{window,60}]}',
      '"ns","email",[k1],[acl],on,{rate_limit,[{window,60},{max_requests,0}]}',
      '"a,b","c",[x,y],{nested,[1,2]}',
    ]) {
      expect(splitErlTupleFields(body).length).toBe(countErlTupleFields(body));
    }
  });
});

describe('parseErlRateLimit', () => {
  it('parses each option independently and omits the ones not present', () => {
    const r = parseErlRateLimit('{rate_limit,[{max_requests,20}]}', 'rpz');
    expect(r.ok).toBe(true);
    // window absent => the key is simply not there, never a default value.
    expect(r.opts).toEqual({ max_requests: 20 });
  });

  it('parses all three server-level options', () => {
    const r = parseErlRateLimit(
      '{rate_limit,[{window,60},{max_requests,6},{max_unknown_requests,1}]}', 'srv');
    expect(r.ok).toBe(true);
    expect(r.opts).toEqual({ window: 60, max_requests: 6, max_unknown_requests: 1 });
  });

  it('accepts 0 for the maximums (refuse every request in that bucket)', () => {
    expect(parseErlRateLimit('{rate_limit,[{max_requests,0}]}', 'rpz').opts)
      .toEqual({ max_requests: 0 });
    expect(parseErlRateLimit('{rate_limit,[{max_unknown_requests,0}]}', 'srv').opts)
      .toEqual({ max_unknown_requests: 0 });
  });

  it('accepts an empty option list as nothing set', () => {
    const r = parseErlRateLimit('{rate_limit,[]}', 'srv');
    expect(r.ok).toBe(true);
    expect(r.opts).toEqual({});
  });

  it('rejects window <= 0 and negative maximums', () => {
    expect(parseErlRateLimit('{rate_limit,[{window,0}]}', 'srv').ok).toBe(false);
    expect(parseErlRateLimit('{rate_limit,[{window,-1}]}', 'srv').ok).toBe(false);
    expect(parseErlRateLimit('{rate_limit,[{max_requests,-1}]}', 'srv').ok).toBe(false);
  });

  it('rejects max_unknown_requests at the rpz level but accepts it at the srv level', () => {
    const rpz = parseErlRateLimit('{rate_limit,[{max_unknown_requests,1}]}', 'rpz');
    expect(rpz.ok).toBe(false);
    expect(rpz.error).toContain('max_unknown_requests');
    expect(parseErlRateLimit('{rate_limit,[{max_unknown_requests,1}]}', 'srv').ok).toBe(true);
  });

  it('rejects unknown options, duplicates, and malformed structures', () => {
    expect(parseErlRateLimit('{rate_limit,[{max_bursts,5}]}', 'srv').ok).toBe(false);
    expect(parseErlRateLimit('{rate_limit,[{window,60},{window,30}]}', 'srv').ok).toBe(false);
    expect(parseErlRateLimit('{rate_limit,[{window,abc}]}', 'srv').ok).toBe(false);
    expect(parseErlRateLimit('{rate_limit,[{window}]}', 'srv').ok).toBe(false);
    expect(parseErlRateLimit('{rate_limit,{window,60}}', 'srv').ok).toBe(false);
    expect(parseErlRateLimit('on', 'srv').ok).toBe(false);
  });
});

describe('classifyErlTrailingElements', () => {
  it('classifies both trailing elements regardless of order', () => {
    const forward = classifyErlTrailingElements(['on', '{rate_limit,[{window,60}]}'], 'srv');
    const reversed = classifyErlTrailingElements(['{rate_limit,[{window,60}]}', 'on'], 'srv');
    expect(forward.ok).toBe(true);
    expect(reversed.ok).toBe(true);
    expect(forward.track).toBe('on');
    expect(reversed.track).toBe('on');
    expect(forward.rateLimit).toEqual({ window: 60 });
    expect(reversed.rateLimit).toEqual({ window: 60 });
  });

  it('reports null for an absent element rather than a default', () => {
    const none = classifyErlTrailingElements([], 'srv');
    expect(none).toEqual({ ok: true, track: null, rateLimit: null });

    const trackOnly = classifyErlTrailingElements(['auto'], 'srv');
    expect(trackOnly.track).toBe('auto');
    expect(trackOnly.rateLimit).toBe(null);

    const rlOnly = classifyErlTrailingElements(['{rate_limit,[{window,60}]}'], 'rpz');
    expect(rlOnly.track).toBe(null);
    expect(rlOnly.rateLimit).toEqual({ window: 60 });
  });

  it('validates the TrackSources atom against the level vocabulary', () => {
    expect(classifyErlTrailingElements(['on'], 'srv').ok).toBe(true);
    expect(classifyErlTrailingElements(['on'], 'rpz').ok).toBe(false); // rpz: auto/true/false
    expect(classifyErlTrailingElements(['true'], 'rpz').ok).toBe(true);
    expect(classifyErlTrailingElements(['true'], 'srv').ok).toBe(false); // srv: off/auto/on
    expect(classifyErlTrailingElements(['junk'], 'srv').ok).toBe(false);
  });

  it('rejects a repeated element of either kind', () => {
    expect(classifyErlTrailingElements(['on', 'auto'], 'srv').ok).toBe(false);
    expect(classifyErlTrailingElements(
      ['{rate_limit,[{window,60}]}', '{rate_limit,[{max_requests,6}]}'], 'srv').ok).toBe(false);
  });
});
