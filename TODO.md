# 2026-01-20
- [ ] Replace notracking with own sample feeds and allow lists on github
- [ ] New RPZ zone
- [ ] RpiDNS - migrate to containers only
- [ ] remove enforcement for 6 chars for usernames and RpiDNS
- [ ] edit users doesn't work

# 2020-05-28
- [ ] Security
  - [x] Enforce HSTS - emitted by `secHeaders()` over TLS as of 2.3.0, so it no longer
        depends on the container's Apache configuration
- [ ] Server's current status and history
- [ ] Update RPZ management window
- [ ] IPv4 and IPv6 DNS IPs
- [ ] Force RPZ, Source refresh
- [ ] Users management
  - [ ] Change password hashing
  - [ ] User's profile + require for the current user to change the password via the profile
  - [ ] Add indexes on names (users, rpz, sources, whitelists) to enforce uniqueness
  - [ ] Check if a user name is unique
  - [ ] If password was not set - just change permissions
- [ ] Publish
  - [ ] do it per server
  - [ ] Change server management keys - safe config and immediately publish update - store old key data
  - [ ] Updating a management TSIG (assigned tot a server) - publish config immediately - store old key data.
  - [ ] Whitelist/source create doesn't push the config update but edit/delete does even is the WL is not used in RPZ. Should be validated if used in servers configs
- [0] On tables refresh if a session was expired - redirect to the login page <--- Mostly done. Need to check how to handle "await"

# Security - needs review before implementation

## Move the SQLite database out of the web root

Follow-up to the 2.3.0 containment fix. `scripts/run_ioc2rpz.gui.sh` now appends a
`Require all denied` block for `www/io2cfg`, which closes the exposure for the Apache
deployment. It does not survive a switch to another web server - note that
`io2install_rpidns.php` generates an openresty config, where that block means nothing -
and bare-metal installs depend on the operator copying it into their own vhost.

The durable fix is to keep the database outside `DocumentRoot` entirely, e.g.
`/opt/ioc2rpz.gui/db/io2db.sqlite`.

- [ ] Untangle `DBFile` first. This is worth doing on its own merit, independently of the
      move. It is currently a relative path resolved three incompatible ways:
    - `new SQLite3(DBFile)` (io2vars.php) relies on the CWD being `www/`, which only
      happens to hold under mod_php
    - `scripts/init_db.php` and `scripts/upgrade_db.php` build `IO2PATH."/www/".DBFile`
    - `scripts/publish_cfg.php` declares its *own* `const DBFile` with an absolute path
      before requiring io2vars.php. PHP then warns on the duplicate `const` and keeps the
      first, so cron works by accident rather than by design.
      Collapse all four to one absolute constant, overridable by environment variable so
      bare-metal installs and `tests/` can point elsewhere.
- [ ] Decide the migration sequencing. `/opt/ioc2rpz.gui/www/io2cfg` is a declared
      `VOLUME` in the Dockerfile and the documented bind-mount target in README.md and
      install_void.txt, so existing deployments have host directories mounted there.
      A new build that reads a different path finds nothing, `run_ioc2rpz.gui.sh` falls
      through to `init_db.php`, and the operator gets a fresh database with the sample
      server while their real data sits unreferenced in the mount. Looks exactly like
      data loss.
      Preferred option: document the new `/opt/ioc2rpz.gui/db` mount, keep accepting the
      old one for one release, migrate on startup when both are present (moving
      `io2db.sqlite` together with `-wal` and `-shm`), and hard-fail with an explicit
      message when only the old mount exists - otherwise the migrated database lands on
      the container's writable layer and dies on `docker rm`.
- [ ] Migration step must run before the `init_db.php` check at the top of
      `run_ioc2rpz.gui.sh`, which is currently the first thing to touch the database.
- [ ] Files to update once the path moves: `Dockerfile` (mkdir + `VOLUME`),
      `scripts/run_ioc2rpz.gui.sh`, `scripts/init_db.php`, `scripts/upgrade_db.php`,
      `scripts/publish_cfg.php`, `.gitignore`, `README.md`, `install_void.txt`,
      `DOCUMENTATION.md`.
- [ ] Verify afterwards that `publish_cfg.php` still finds the database from cron. It runs
      with a different CWD than mod_php and is the call site most likely to break.

## Erlang configuration injection via generated ioc2rpz.conf

Reviewed and confirmed, not yet fixed - the remediation changes what gets written to DNS
server configs, so it needs a decision on how strict to be before touching it.

`erlEscape()` (io2vars.php) is an intentional pass-through: stored regexes, URLs and keys
have to reach the generated config verbatim, and re-escaping corrupts them. That puts the
entire burden on the input validators, and they have gaps.

- [ ] Several fields bypass `erlEscape()` altogether. In `genConfig()` the whitelist and
      source rows interpolate `name`, `url`, `url_ixfr` and `ioc_type` raw; only `regex`
      goes through the pass-through. Same for the RPZ `name` and `ioc_type`. A `"` or a
      newline in any of them terminates the quoted Erlang string and injects arbitrary
      terms into the config of the DNS node.
- [ ] Validator gaps behind that:
    - `validateSourceUrl()` only anchors a scheme prefix (`^(https?://|ftp://|file:|shell:)`)
      and length-checks. Everything after the prefix is unconstrained.
    - `tSrcREGEX` is length-checked only.
    - `group_name`, RPZ `notify` and whitelist `url` are not charset-restricted.
- [ ] `custom_config` is emitted raw by design, which is arbitrary Erlang on the DNS node.
      Decide whether that stays a deliberate power-user escape hatch (and if so, gate it
      on an admin-only permission) or gets a grammar.
- [ ] Related and arguably the bigger half: `shell:` source URLs mean the GUI can configure
      command execution on the DNS server *by design*. Combined with the absence of any
      `perm` check on the `servers`, `rpzs`, `sources` and `tkeys` endpoints - only
      `users` gates on `perm == 1` - any authenticated account can do this. Consistent RBAC
      across the data endpoints should probably land before or with the escaping work.
- [ ] Decide the approach: charset-restrict every field that reaches the config writer, or
      give `erlEscape()` a real implementation plus a migration for values already stored
      in the escaped-by-hand form. The second is more correct and riskier.

# Bugs

# TODO
- [ ] Container. Session expiration
- [ ] Config import. Pub_IP & local management IP & Email & Management.


----- old cut before Def Con 2018 -----
- [ ] Constraints enforcements on SQLite (requires redo the DB, keys etc) (if there is a named index, php doesn't see rowid.....)
- [ ] Source/whitelist check availability/rechability
- [ ] Server side. Intelligent publishing an updated server configuration
- [ ] Monitoring/dashboards
- [ ] MySQL or PostgreSQL support
- [ ] S3 support
- [ ] Utils
    - [ ] Import configuration. Srv and RPZs uniqueness + add SRV params
    - [ ] Import/Backup ioc2rpz.gui config