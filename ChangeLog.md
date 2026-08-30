#ioc2rpz.gui change log
## 2.3.0 2026-08-30

### Security

- **The SQLite database was downloadable over HTTP.** It lives in `www/io2cfg`, inside the
document root, and the rewrite rules only redirect requests whose target does not exist on
disk - so `GET /io2cfg/io2db.sqlite` was served by Apache as a static file, with no
authentication of any kind. That file contains every user's password hash and every TSIG
key secret in cleartext. `scripts/run_ioc2rpz.gui.sh` now appends a `Require all denied`
block for the directory, which also covers the `-wal` and `-shm` sidecars; in WAL mode the
sidecar holds the most recent writes, so denying only the database file would still have
leaked recent changes. The block is applied idempotently and outside the `config-done`
marker guard, so containers provisioned before this release receive it on restart, and the
entrypoint now runs `httpd -t` and refuses to start on a bad configuration rather than
coming up with the database exposed. File permissions cannot substitute for this: mod_php
needs read/write access and runs as the same user that serves the file.
**Operators not using the bundled Apache configuration must add an equivalent rule.**
Anyone who has run an affected deployment on a reachable network should treat the database
contents as disclosed and rotate TSIG keys and passwords. Moving the database out of the
document root entirely is tracked in TODO.md
- **SQL injection in `POST servers`.** The `mgmt` column interpolated
`DB_escape($REQUEST['tSrvMGMT'])` without surrounding quotes. `DB_escape()` wraps
`SQLite3::escapeString()`, which only doubles single quotes, so a payload containing none
escaped the `VALUES` list, and `DB_execute()` runs through `SQLite3::exec()`, which accepts
multiple statements. `validateServerFields()` never inspected that field. Any authenticated
account could therefore execute arbitrary SQL, including granting itself administrator
permission and reading every TSIG secret. The column now uses `DB_boolval()`. Documentation
gained a table of which helper to use for which column type, because the distinction is not
obvious and the unquoted case is silently unsafe
- Session cookies are now hardened in code (`startSession()` in io2fun.php) instead of
depending on a `sed` of php.ini that the container entrypoint performs once, behind a marker
file. Bare-metal installations and already-provisioned containers previously received none
of these settings. `SameSite=Strict` has been added, which is the backstop behind the CSRF
token check - a leaked or omitted token cannot be replayed from another origin. `Secure`
follows the detected protocol, so a deployment terminating TLS upstream can still sign in
- HSTS is now emitted by `secHeaders()`. It was previously set only in the container's
Apache configuration, leaving bare-metal RpiDNS deployments without it

### Fixed

- **Saving a server or a feed silently rebuilt all of its associations.** The diff between
the stored and the submitted set compared a foreign key against a list of associative rows
using `array_search()`, which never matches, so the "already linked" branch was dead and
every TSIG key, source, allowlist, server and notify address was deleted and re-inserted on
every save. The `PUT rpzs` handler compounded this by diffing four different relations
against the TSIG key list rather than their own, and the surrounding
`if ($k = array_search(...))` idiom additionally treated a match at index 0 as "not found".
Replaced with `diffAssociations()` / `diffValueAssociations()`, which compare target row ids
and leave unchanged links alone. Pre-existing duplicate links are now cleaned up rather than
preserved
- **Editing a feed marked the wrong servers for republishing.** The list of servers needing
a new configuration collected the rowid of the `rpzs_servers` association row instead of the
server's own rowid, so `update servers set cfg_updated=1` matched unrelated servers, or none.
It is now the union of the servers serving the feed before and after the change, which also
covers retained servers whose configuration changes when the feed's own attributes do
- **Deleting a feed marked the wrong servers for republishing**, reusing the list of feed ids
as a server rowid filter. The affected servers are now resolved from `rpzs_servers` before the
association rows are removed
- Deleting a server left orphaned rows in `servers_tsig_groups` and `rpzs_servers`
- Creating a server set `cfg_updated` from the management checkbox rather than marking the new
server as needing a publish, so a newly created server could be skipped by the next publish
- **A fresh installation came up with no sample configuration.** `scripts/init_db.php`
inserted sample rows positionally, and when the DNS rate limit columns were added the
`servers` and `rpzs` inserts still supplied the old number of values. SQLite rejected both,
and because each was the first statement of a multi-statement `exec()` whose return value
was discarded, every association that followed - `servers_tsig`, `mgmt_ips`, `rpzs_servers`,
`rpzs_whitelists`, `rpzs_sources`, `rpzs_notify`, `rpzs_tkeys` - was skipped in silence. A
new user got an empty GUI instead of the sample server and feed the README promises, with
only a PHP warning in the container log to show for it. All sample inserts now name their
columns explicitly, so a future column with a default cannot break them, failures are
reported, and `init_db.php` exits non-zero if any of them fail. Found by the new container
smoke test
- A `DELETE` request without a usable `rowid` produced a PHP warning and degraded to
`rowid in ()`, which SQLite accepts and matches nothing - reporting success while deleting
nothing. Such requests are now rejected with HTTP 400. Row id parsing also no longer breaks
on an integer `rowid` in a JSON body, which previously reached `array_filter(null)` and threw
- `io2auth.php` called `exit(1)` before `DB_close($db)`, so the connection was never closed
and the script reported failure to the web server on the normal path
- Removed the `PATCH user_passsword` endpoint: the name was misspelled, the body was empty,
and nothing called it. Self-service password change remains in TODO.md, where it is noted
that it needs to authenticate the current user rather than gate on administrator permission

### Changed

- Source and allowlist URLs now accept spaces and `%` for `http`, `https` and `ftp`. Both were
silently deleted as you typed: `https://host/a b.txt` became `https://host/ab.txt`, and the
correctly encoded `https://host/a%20b.txt` became `https://host/a20b.txt` because `%` was not
in the permitted set - which corrupted every percent-escape (`%2F`, `%3D`, `%26`), with no error
shown and no sign of it until the feed failed to fetch. The field now permits the RFC 3986 URI
character set plus a literal space. The double quote, backslash, angle brackets, braces, pipe,
caret, backtick and control characters are still removed; the double quote matters because these
values are written verbatim into a quoted Erlang string in the generated `ioc2rpz.conf`.
`shell:` and `file:` values are still passed through untouched
- The IXFR path field now uses the same character set as the source URL field. The two had
diverged only because one escaped the hyphen and the other did not, leaving
`[^A-Za-z0-9/=:?#.-_&]` to be read as the range `.`-`_`, which silently permitted `[`, `]`,
`;`, `<`, `>`, `@`, `\` and `^`
- Certificate, key and CA path fields now permit exactly what the backend
`validateFilePath()` accepts. They previously accepted characters the server then rejected, so
a valid-looking entry failed on save with "Invalid certificate file path"
- Fixed `/^[:AXFR:]/` in the source and IXFR formatters: written as a character class it
matched a leading `:`, `A`, `X`, `F` or `R` rather than the literal `[:AXFR:]` token, so the
pass-through it was named for never happened
- Replaced all 53 uses of the deprecated `"${var}"` string interpolation in `www/io2vars.php`
and `scripts/publish_cfg.php` with `"{$var}"`. These are deprecated as of PHP 8.2 and removed
in PHP 9; the container already runs a version that warns. Generated configuration output is
byte-for-byte unchanged. The `"${...}"` occurrences elsewhere are literal shell syntax inside
generated installation scripts and are intentionally left alone
- Added `.github/workflows/ci.yml`. Both test suites existed but nothing ran them. The PHP job
covers 8.1 and 8.4 and fails on any deprecation; a hygiene job rejects committed editor backups
and database files and checks that `package.json` and `ChangeLog.md` agree
- The CI PHP matrix is 8.2, 8.3 and 8.4, and `require.php` in composer.json moved from `>=8.1`
to `>=8.2`. 8.1 could never pass: `phpunit/phpunit` 11 and the whole `sebastian/*` tree require
`php >= 8.2`, so the job died at `composer install` before running a test. 8.2 is the declared
floor and what Debian 12 ships, 8.3 is what the container ships, 8.4 provides the deprecation
early warning. `composer.lock` was refreshed with `composer update --lock` so that
`composer validate --strict` still passes; no package versions changed
- The PHPUnit step now checks that a `phpunit.xml.dist` exists before invoking PHPUnit. Without
a configuration file PHPUnit has no test target, prints its usage text and exits 1, which reads
as a broken command line rather than a missing file
- CI also builds the container image on every push and smoke tests it: the login page must
answer 200 over HTTPS, the database and its `-wal`/`-shm` sidecars must return 403, a fresh
database must contain the complete sample configuration, and `genConfig()` must produce every
record type against that data. The 403 assertion matters because it can regress from an Apache
configuration change alone, which no unit test would notice; the sample data assertion is what
found the `init_db.php` bug above. The image is built but not published - pushing needs registry
credentials and is left as an explicit choice
- Version numbering realigned. `package.json`, `ChangeLog.md` and `$io2ver` in io2vars.php had
drifted to 2.2.1, 2.2.0.1 and a 2022 date stamp respectively. The changelog now uses three-part
versions to match `package.json`, and `$io2ver` - the CSS cache-buster - tracks the release date
- License metadata aligned to Apache-2.0, matching the `LICENSE` file, the README badge and the
Dockerfile header. `composer.json` and the `@license` tag in nine PHP files claimed MIT. This
corrects the metadata to match the license the project actually ships; it is not a relicensing
- README dependencies corrected from PHP 7 to PHP 8.1 or newer
- Removed `www/js/io2.js.bak` and `io2.js.bak2`. Both were tracked, dead since the Vue 3
migration, and served from the document root. `.gitignore` now covers editor backups, vendored
archives, and local developer workflow scripts carrying personal paths and internal addresses

## 2.2.0.1 2026-08-26
- The feed editor now reports the inherited rate limit correctly for a feed published to more than one server. Each server generates its own configuration file, so an option the feed does not set is inherited from each server separately and can resolve to a different value per server; the hint previously showed only the first selected server's value as if it were the effective limit. When the servers disagree the hint reads `Inherited: varies by server - ns1: 30, ns2: 120`; when they agree it reads a single value as before. An option set on the feed overrides every server and is never ambiguous
- An invalid rate limit now moves focus to the field that is blocking the save. It already prevented submission, but focus went to an unrelated field (the notify input on the server form, the IXFR input on the feed form), leaving a keyboard or screen reader user with no way to find the offending control
- Added an upper bound of 86400 seconds (24 hours) on the rate limit window, enforced in the browser, in the backend validator, and in the config writer. The window is stored with every entry in the server's rate limit table and decides when that entry is swept, so an absurd value (a fumbled digit) would keep entries alive for as long as it lasts - a slow, config-driven memory leak. A stored value above the cap is omitted from the generated configuration so the record inherits instead. The request maximums stay uncapped: a large threshold only makes the limiter permissive and retains nothing
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
