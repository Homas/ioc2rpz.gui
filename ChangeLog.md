#ioc2rpz.gui change log
## 2.2.0.0 2026-08-25
- Added DNS rate limit management (ioc2rpz 1.4.0.4). The server editor exposes a rate limit window (seconds), max requests per window, and max unknown requests per window; the feed editor exposes the window and max requests, overriding the server values per feed. Each option resolves independently with the precedence feed -> server -> built-in default, an empty field means inherit and shows the resolved value and its origin, and a maximum of 0 (refuse every request in that bucket) is called out in the UI. Set options are serialized as the optional trailing `{rate_limit, Options}` element on the srv and rpz tuples and parsed back on import, in either order with the TrackSources element.
- Invalid rate limits are rejected by the frontend and the backend instead of being written. The ioc2rpz server only logs and ignores an invalid value and falls back to the next level, so a bad value would otherwise silently do nothing.
- DB schema version bumped from 3 to 4, adding nullable columns servers.rl_window, servers.rl_max_requests, servers.rl_max_unknown_requests, rpzs.rl_window and rpzs.rl_max_requests. NULL means inherit, so existing databases migrate with every feed and server inheriting as before, and existing configurations are unaffected: a record with no rate limit still serializes to the byte-identical legacy tuple, and legacy configs still import unchanged.
- Feed names are normalized to lower case on input and on import, matching the server's RFC 4343 zone name canonicalisation.
- The server editor notes that sources in the management ACL are exempt from DNS rate limiting.

## 2.1.1.0 2026-07-07
- Security: the IOC lookup endpoint now scopes the target server and its management TSIG credentials to the authenticated user (user_id). A session can no longer resolve another user's server or use its management key by supplying an arbitrary server rowid.
- Fixed IOC lookup handling and result rendering (enhanced matched-indicator grouping, empty/not-found handling).
- Fixed server management IP validation so operator-entered ACL entries are no longer incorrectly rejected.
- Fixed server configuration file name validation (validated as a file name/location instead of an http/https URL).
- Fixed feeds not being returned in the IOC lookup response.

## 2.1.0.0 2026-07-04
- Added source attribution management. Feeds now have a per-feed "Track sources" option and there is a server-wide "Source attribution (global default)" control. The optional TrackSources atoms are serialized in the generated configuration, and a new IOC lookup view displays attribution for matched indicators.

## 2.0.1.0 2026-07-03
- Fixed backend source/IXFR URL validation that rejected valid sources. The strict http/https-only check introduced in the pre-release now accepts ftp/file/shell source URLs and IXFR meta keywords ([:AXFR:], [:FTimestamp:], [:ToTimestamp:]), matching the frontend.
- Fixed publish/reconfigure ("publish_upd") failing with an "Undefined array key SrvId" warning. Query-string parameters (e.g. ?SrvId=1) are now merged into the request alongside the JSON body, and the handler rejects requests with a missing SrvId.
- Fixed generated server configuration being rejected by ioc2rpz with "syntax error before: '.'". The srv record was terminated with a literal "\n" (backslash-n) instead of a real newline, mashing it together with following records. Now emits a proper newline like all other records.
- Fixed doubled escape characters in generated config (e.g. regex "\." became "\\."). erlEscape() was changed in the pre-release to escape backslashes/quotes, but stored values are already in the exact form ioc2rpz expects and must be written verbatim. Reverted erlEscape() to a pass-through.

## 2.0.0.0 2026-01-23
- Migration to vue3 with vite

## 1.0.1.0 2020-09-08
- Max IoCs, hot cache time management.

## 1.0.0.0 2020-07-07
- UX/UI was deeply updated
- TSIG Key Group Management
- User Management
- RpiDNS. DB file should be updated. Check init_db.php  comment on 2020-06-08

## 0.9.4.0 2019-06-15
- Key groups support for servers and RPZs. Groups should be directly added to the DB.

## 2018-09-23
- Bugs in the startup script (run_ioc2rpz.gui.sh):
	- php.ini inline edit;
	- check if the apache2 and php configs were already modified;

Not released yet.
