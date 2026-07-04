#ioc2rpz.gui change log
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
