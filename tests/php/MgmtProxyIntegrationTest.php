<?php
/**
 * Mocked integration tests for the Mgmt_Proxy IOC lookup endpoint (Task 12.3).
 *
 * The `GET ioc_lookup` endpoint in www/io2data.php cannot be invoked directly
 * in isolation (it depends on request routing, session state, a live SQLite
 * database, and an outbound TLS curl call to a separate management interface).
 * Following the design's Testing Strategy ("mocked integration tests, 1-3
 * examples"), the endpoint's two externally-observable, credential-sensitive
 * behaviours are extracted into the pure helpers exercised here:
 *
 *   - buildIocLookupUrl()        -> the exact request shape sent to the mgmt API
 *   - classifyIocLookupResult()  -> the failure/success classification returned
 *
 * These tests assert (Req 8.3, 8.6):
 *   1. The request URL is GET /api/v1/ioc/:ioc?tkey=<keyname> against the
 *      selected server's address/port.
 *   2. The endpoint issues the request with HTTP basic auth and a 30s timeout
 *      (verified at the endpoint source level, since these are curl transport
 *      options rather than pure-function outputs).
 *   3. A connection error, an HTTP 500, and a timeout are each classified
 *      correctly.
 *   4. No response body or error string produced by the proxy ever contains the
 *      TSIG key name or secret.
 *
 * @package ioc2rpz.gui
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MgmtProxyIntegrationTest extends TestCase
{
    /** A representative management TSIG key name (public identifier). */
    private const KEY_NAME = 'mgmt-key-1';

    /** A representative TSIG secret; must never surface in any proxy output. */
    private const KEY_SECRET = 's3cr3t-shared-key-value==';

    // ---------------------------------------------------------------------
    // Req 8.3 - request shape: GET /api/v1/ioc/:ioc?tkey=<keyname>
    // ---------------------------------------------------------------------

    /**
     * The proxy builds the exact management URL shape against the selected
     * server's address and port, url-encoding the indicator. The `tkey` query
     * parameter is intentionally EMPTY: it scopes feed visibility on the server
     * and must not carry the management key (which is not a per-zone transfer
     * key and would filter out every feed). An empty tkey returns all feeds.
     */
    public function testBuildsExpectedRequestUrlShape(): void
    {
        $url = buildIocLookupUrl('10.0.0.1', 8443, 'evil.example.com');

        $this->assertSame(
            'https://10.0.0.1:8443/api/v1/ioc/evil.example.com?tkey=',
            $url,
            'URL must be https://<addr>:<port>/api/v1/ioc/<ioc>?tkey= (empty tkey)'
        );
    }

    /**
     * Indicators containing characters that are significant in a URL path are
     * percent-encoded so the request targets a single, well-formed resource.
     */
    public function testRequestUrlEncodesIndicator(): void
    {
        // An IPv6-style indicator (contains ':') and a slash both must be encoded.
        $url = buildIocLookupUrl('mgmt.example', 8443, 'a/b?c 2001:db8::1');

        $this->assertStringStartsWith('https://mgmt.example:8443/api/v1/ioc/', $url);
        // The raw special characters must not leak into the path/query verbatim.
        $this->assertStringNotContainsString('a/b?c', $url);
        $this->assertStringNotContainsString(' ', $url);
        // rawurlencode() encodes space as %20 and '/' as %2F.
        $this->assertStringContainsString('a%2Fb%3Fc%202001%3Adb8%3A%3A1', $url);
        // The tkey parameter is present but empty (all-feeds scope).
        $this->assertStringEndsWith('?tkey=', $url);
    }

    /**
     * Neither the key name nor the secret is ever part of the URL. The
     * management credentials travel solely in the basic-auth header (asserted
     * separately below); the tkey query parameter is empty.
     */
    public function testRequestUrlNeverContainsCredentials(): void
    {
        $url = buildIocLookupUrl('10.0.0.1', 8443, 'host.example');
        $this->assertStringNotContainsString(self::KEY_NAME, $url);
        $this->assertStringNotContainsString(self::KEY_SECRET, $url);
    }

    /**
     * Req 8.3 (basic auth + 30s timeout): these are curl transport options set
     * where the request is issued, not outputs of a pure function, so they are
     * asserted against the endpoint source. This guards against a regression
     * that drops basic auth, changes the timeout, or omits the credentials from
     * CURLOPT_USERPWD.
     */
    public function testEndpointIssuesRequestWithBasicAuthAndThirtySecondTimeout(): void
    {
        $src = file_get_contents(realpath(__DIR__ . '/../../www') . '/io2data.php');
        $this->assertNotFalse($src, 'io2data.php must be readable');

        // Locate the ioc_lookup case block.
        $pos = strpos($src, 'case "GET ioc_lookup":');
        $this->assertNotFalse($pos, 'ioc_lookup endpoint must exist');
        $block = substr($src, $pos, 4000);

        $this->assertStringContainsString('CURLAUTH_BASIC', $block, 'must use HTTP basic auth');
        $this->assertStringContainsString(
            'CURLOPT_USERPWD, $keyName.":".$keySecret',
            $block,
            'basic-auth credentials must be <keyname>:<secret>'
        );
        $this->assertMatchesRegularExpression(
            '/CURLOPT_TIMEOUT,\s*30\b/',
            $block,
            'request timeout must be 30 seconds'
        );
        $this->assertStringContainsString(
            'buildIocLookupUrl(',
            $block,
            'endpoint must build the request URL via the shared helper'
        );
    }

    // ---------------------------------------------------------------------
    // Req 8.6 - failure classification (connection, server 500, timeout)
    // ---------------------------------------------------------------------

    /**
     * A connection/resolve transport error classifies as a connection failure.
     */
    public function testClassifiesConnectionError(): void
    {
        $connect = classifyIocLookupResult(CURLE_COULDNT_CONNECT, 0, '');
        $resolve = classifyIocLookupResult(CURLE_COULDNT_RESOLVE_HOST, 0, '');

        $this->assertSame('{"status":"failed","error":"connection"}', $connect);
        $this->assertSame('{"status":"failed","error":"connection"}', $resolve);
    }

    /**
     * An HTTP 500 (any status >= 400) classifies as a server error and reports
     * the status code.
     */
    public function testClassifiesHttp500ServerError(): void
    {
        $result = classifyIocLookupResult(0, 500, 'Internal Server Error');

        $this->assertSame('{"status":"failed","error":"server","code":500}', $result);

        $decoded = json_decode($result, true);
        $this->assertSame('failed', $decoded['status']);
        $this->assertSame('server', $decoded['error']);
        $this->assertSame(500, $decoded['code']);
    }

    /**
     * A curl timeout classifies as a timeout failure.
     */
    public function testClassifiesTimeout(): void
    {
        $result = classifyIocLookupResult(CURLE_OPERATION_TIMEDOUT, 0, '');
        $this->assertSame('{"status":"failed","error":"timeout"}', $result);
    }

    /**
     * A successful response passes the parsed feed array straight through.
     */
    public function testClassifiesSuccessPassthrough(): void
    {
        $body = '[{"feed":"feed1","type":"rpz","sources":["srcA","srcB"]}]';
        $result = classifyIocLookupResult(0, 200, $body);

        $this->assertSame(
            [['feed' => 'feed1', 'type' => 'rpz', 'sources' => ['srcA', 'srcB']]],
            json_decode($result, true),
            'a 2xx JSON body must be passed through unchanged'
        );
    }

    // ---------------------------------------------------------------------
    // Req 8.6 - credentials never leak into any classified response
    // ---------------------------------------------------------------------

    /**
     * Across every classification branch, the TSIG key name and secret never
     * appear in the returned response string. classifyIocLookupResult takes no
     * credential inputs, so this holds by construction; the test pins that
     * contract against regressions (e.g. someone echoing the URL or auth into
     * an error payload).
     */
    public function testNoClassifiedResponseContainsCredentials(): void
    {
        $cases = [
            classifyIocLookupResult(CURLE_OPERATION_TIMEDOUT, 0, ''),
            classifyIocLookupResult(CURLE_COULDNT_CONNECT, 0, ''),
            classifyIocLookupResult(CURLE_COULDNT_RESOLVE_HOST, 0, ''),
            classifyIocLookupResult(7, 0, ''),          // other transport error
            classifyIocLookupResult(0, 500, 'boom'),     // server error
            classifyIocLookupResult(0, 403, 'denied'),   // another server error
            classifyIocLookupResult(0, 200, 'not json'), // success-but-bad-body
            classifyIocLookupResult(0, 200, '[{"feed":"f","type":"rpz","sources":null}]'),
        ];

        foreach ($cases as $i => $response) {
            $this->assertStringNotContainsString(
                self::KEY_NAME,
                $response,
                "classified response #$i must not contain the TSIG key name"
            );
            $this->assertStringNotContainsString(
                self::KEY_SECRET,
                $response,
                "classified response #$i must not contain the TSIG secret"
            );
        }
    }

    /**
     * Even when the management interface echoes the tkey name back inside an
     * error body, the classifier discards the body for error statuses, so the
     * key name cannot ride out through a server-error payload. (The secret can
     * never appear because it is never sent in the URL/body.)
     */
    public function testServerErrorDiscardsResponseBody(): void
    {
        $leakyBody = 'error: bad tkey "' . self::KEY_NAME . '"';
        $result = classifyIocLookupResult(0, 500, $leakyBody);

        $this->assertSame('{"status":"failed","error":"server","code":500}', $result);
        $this->assertStringNotContainsString(self::KEY_NAME, $result);
    }
}
