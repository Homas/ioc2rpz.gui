# ioc2rpz.gui - Project Documentation

## Overview

ioc2rpz.gui is a web-based management interface for [ioc2rpz](https://github.com/Homas/ioc2rpz), a DNS server that transforms threat indicators into Response Policy Zone (RPZ) feeds. The application is built with Vue 3, Bootstrap-Vue-Next, PHP backend, and SQLite database.

---

## Project Structure

```
ioc2rpz.gui/
├── www/                    # Web application root
│   ├── css/               # Stylesheets
│   ├── dist/              # Vite production build output
│   ├── io2cfg/            # SQLite database storage (denied over HTTP, see Security Features)
│   ├── js/                # JavaScript source files
│   ├── src/               # Vue 3 entry points
│   ├── webfonts/          # Font Awesome fonts
│   ├── index.php          # Main application entry
│   ├── io2auth.php        # Authentication handler
│   ├── io2data.php        # REST API endpoints
│   ├── io2fun.php         # PHP utility functions
│   ├── io2vars.php        # Configuration variables
│   ├── rpidns.php         # RpiDNS management UI
│   ├── rpidns_config.php  # RpiDNS config generator
│   ├── utils.php          # Utilities page
│   └── vite-helpers.php   # Vite asset loading helpers
├── scripts/               # Server-side scripts
│   ├── init_db.php        # Database initialization
│   ├── publish_cfg.php    # Configuration publisher
│   └── upgrade_db.php     # Database migration
│   └── run_ioc2rpz.gui.sh # Container entrypoint / provisioning
├── tests/                 # Test suites
│   ├── php/               # PHPUnit tests (backend, config writer, migrations)
│   ├── fixtures/          # Shared fixture generators
│   └── *.test.js          # Vitest tests (frontend, config reader/writer)
├── .github/workflows/     # CI (see below)
├── cfg/                   # Configuration files
├── pkg/                   # Third-party packages. pkg/openresty-*.deb is fetched at
│                          # install time by io2install_rpidns.php - do not remove
├── phpunit.xml.dist       # PHPUnit configuration
├── vitest.config.js       # Vitest configuration
├── composer.json          # PHP dependencies (PHP >= 8.1)
├── vite.config.js         # Vite build configuration
└── package.json           # Node.js dependencies
```

### Tests and CI

| Command | Runs |
|---------|------|
| `composer install` | PHP dev dependencies (PHPUnit, Eris) |
| `./vendor/bin/phpunit` | Backend suite |
| `npm test` | Frontend suite (`vitest run`) |
| `npm run build` | Production Vite build |
| `docker build -t ioc2rpz-gui .` | Container image |

`.github/workflows/ci.yml` runs on pushes and pull requests against `master` and `dev`,
in four jobs:

| Job | Covers |
|-----|--------|
| `php` | `composer validate`, PHPUnit, and a lint sweep over every PHP file |
| `js` | Vitest plus the production Vite build |
| `container` | Builds the image, then smoke tests a running container |
| `hygiene` | Shell syntax, committed backups/databases, version consistency |

The PHP job runs on 8.1 (the floor in composer.json) and 8.4. It fails on any deprecation
reported by `php -l`, which is what catches the `"${var}"` string interpolation that PHP 9
removes.

The `container` job is gated on `php` and `js` so a commit with failing tests does not
spend minutes on an image build. It builds with Buildx using the GitHub Actions cache and
loads the image locally — it does **not** push. Publishing needs registry credentials, so
it is left as a deliberate decision: add a login step and set
`push: ${{ github.event_name == 'push' }}` with your tags.

The smoke test asserts four things against the running container, each of which is a
regression guard for something that actually broke:

1. The login page returns 200 over HTTPS, so the entrypoint provisioned Apache and the
   `httpd -t` check passed
2. `/io2cfg/`, `io2db.sqlite`, and the `-wal` / `-shm` sidecars all return **403**. This
   can regress from an Apache config change alone, which neither the PHP nor the JS suite
   would notice
3. A fresh database contains the complete sample configuration. The `init_db.php`
   positional inserts silently stopped matching the schema when the rate limit columns
   were added, and a new install came up with no server and no feed
4. `genConfig()` produces `srv`, `key`, `source`, `whitelist` and `rpz` records against
   that real sample data, exercising the config writer end to end rather than through
   fixtures

Two notes for anyone extending the smoke test. The PHP binary inside the container is
`php83`, not `php`. And Apache logs `AH01630 client denied by server configuration` at
`authz_core:error` level every time the deny rule does its job, so the log assertion
filters that pattern — otherwise it would pass or fail depending on step order.

---

## Configuration Settings

### Database Configuration (io2vars.php)

| Constant | Default | Description |
|----------|---------|-------------|
| `DB` | `"sqlite"` | Database type |
| `DBFile` | `"io2cfg/io2db.sqlite"` | Database file path, relative to `www/`. Note this places it inside `DocumentRoot`; `scripts/run_ioc2rpz.gui.sh` appends an Apache `Require all denied` block for that directory. Moving it out of the web root is tracked in TODO.md |
| `DBCreateIfNotExists` | `true` | Auto-create database |
| `dig` | `"/usr/bin/dig +tcp"` | DNS dig command path |
| `io2mgmt` | `"rest"` | Management interface type (rest/dns) |
| `io2mgmt_verifyssl` | `false` | SSL verification for management |
| `rest_mgmt_port` | `8443` | REST management port |

### Security Settings (io2auth.php)

| Constant | Value | Description |
|----------|-------|-------------|
| `MAX_LOGIN_ATTEMPTS` | `5` | Max failed logins before lockout |
| `LOCKOUT_DURATION` | `900` | Lockout duration in seconds (15 min) |

---

## PHP Functions Reference

### io2fun.php - Core Functions

| Function | Parameters | Description |
|----------|------------|-------------|
| `getRequest()` | - | Parses HTTP request (GET/POST/PUT/DELETE) |
| `getProto()` | - | Returns current protocol (http/https) |
| `startSession()` | - | Starts the session with hardened cookie attributes (HttpOnly, SameSite=Strict, Secure over TLS). Use instead of `session_start()` |
| `secHeaders()` | - | Sets security headers (CSP, XSS, HSTS over TLS) |
| `diffAssociations($old, $fkColumn, $new)` | array, string, array | Resolves stored association rows against a requested target set; returns `delete` (association rowids), `insert` and `keep` (target rowids) |
| `diffValueAssociations($old, $valueColumn, $wanted)` | array, string, iterable | As above for tables storing a literal value (notify, management IPs); returns `delete` (rowids) and `insert` (values) |
| `generateCsrfToken()` | - | Generates 64-char CSRF token |
| `validateCsrfToken($token)` | string | Validates CSRF token against session |
| `getCsrfToken()` | - | Gets/creates session CSRF token |
| `validatePassword($password)` | string | Validates password strength |
| `validateRpzJson($rpz)` | array | Validates RpiDNS RPZ configuration |
| `uuid()` | - | Generates UUID v4 |
| `generate_install_script($db, $uuid)` | db, string | Generates RpiDNS install script |
| `genConfig($db, $USERID, $SrvId)` | db, int, int | Generates ioc2rpz server config |
| `erlEscape($str)` | string | Escapes string for Erlang config |
| `erlAction($str)` | string | Converts action to Erlang format |

### io2vars.php - Database Functions

| Function | Parameters | Description |
|----------|------------|-------------|
| `DB_open()` | - | Opens database connection |
| `DB_close($db)` | db | Closes database connection |
| `DB_select($db, $sql)` | db, string | Executes SELECT query |
| `DB_selectArray($db, $sql)` | db, string | Returns SELECT as array |
| `DB_fetchArray($result)` | result | Fetches row as associative array |
| `DB_execute($db, $sql)` | db, string | Executes INSERT/UPDATE/DELETE |
| `DB_escape($db, $text)` | db, string | Escapes string for SQL |
| `DB_boolval($val)` | mixed | Converts to database boolean |
| `filterIntArr($array)` | array | Filters array to integers only |
| `getGroupsId($array)` | array | Extracts group IDs from array |

### vite-helpers.php - Asset Loading

| Function | Parameters | Description |
|----------|------------|-------------|
| `vite_is_dev_mode()` | - | Checks if in development mode |
| `vite_dev_server_url()` | - | Returns Vite dev server URL |
| `vite_get_manifest()` | - | Reads Vite manifest.json |
| `vite_get_entry_file($entry)` | string | Gets hashed filename for entry |
| `vite_get_entry_css($entry)` | string | Gets CSS files for entry |
| `vite_get_entry_imports($entry)` | string | Gets imported chunks |
| `vite_script_tag($entry)` | string | Generates script tag |
| `vite_css_tags($entry)` | string | Generates CSS link tags |
| `vite_tags($entry)` | string | Generates all asset tags |

---

## JavaScript Functions Reference

### io2.js - Helper Functions (Standalone)

| Function | Parameters | Description |
|----------|------------|-------------|
| `sleep(ms)` | number | Promise-based delay |
| `downloadAsPlainText(fileName, Data)` | string, string | Downloads data as text file |
| `copyToClipboardID(id)` | string | Copies element content to clipboard |
| `getLocationOrigin()` | - | Returns window.location.origin |
| `escapeHtml(str)` | string | Escapes HTML special characters |

### Validation Functions

| Function | Parameters | Description |
|----------|------------|-------------|
| `checkHostIPNet(V)` | string | Validates host/IP/network |
| `checkHostIP(V)` | string | Validates host or IP |
| `checkIP(IP)` | string | Validates IPv4 or IPv6 |
| `checkIPv4(IP)` | string | Validates IPv4 address |
| `checkIPv4Net(IP)` | string | Validates IPv4 with CIDR |
| `checkIPv6(IP)` | string | Validates IPv6 address |
| `checkHostName(HN)` | string | Validates FQDN hostname |
| `checkHostNameNum(HN)` | string | Validates hostname with numbers |
| `checkHostNameOnly(HN)` | string | Validates hostname without TLD |
| `checkSourceURL(HN)` | string | Validates source URL format |

### Vue-Instance Dependent Functions

| Function | Parameters | Description |
|----------|------------|-------------|
| `update_window_size(obj)` | Vue instance | Updates responsive layout |
| `toggleUpdates(srv, obj, state)` | -, Vue, bool | Toggles publish updates state |
| `splitRpiDNSList(obj)` | Vue instance | Splits RpiDNS list for dashboard |
| `ImportIOC2RPZ(vm, txt)` | Vue, string | Imports ioc2rpz configuration |

### eventBus.js - Event Bus Functions

| Function | Parameters | Description |
|----------|------------|-------------|
| `showModal(modalId)` | string | Shows modal by ID |
| `hideModal(modalId)` | string | Hides modal by ID |
| `refreshTable(tableId)` | string | Refreshes table by ID |
| `onShowModal(handler)` | function | Subscribes to modal show events |
| `onHideModal(handler)` | function | Subscribes to modal hide events |
| `onRefreshTable(handler)` | function | Subscribes to table refresh events |

---

## Vue App Methods (appConfig.methods)

### Table Management

| Method | Description |
|--------|-------------|
| `tableProvider(ctx, apiUrl)` | Async data provider for b-table |
| `createTableProvider(apiUrl)` | Creates provider function for specific API |
| `refreshTbl(table)` | Refreshes specified table |
| `onFiltered(filteredItems)` | Handles table filter events |
| `get_tables(obj)` | Legacy table data fetcher |

### Record Management

| Method | Description |
|--------|-------------|
| `mgmtRec(action, table, row, target)` | Handles add/edit/info/clone/delete actions |
| `requestDelete(table, row)` | Shows delete confirmation modal |
| `tblDeleteRecord(table, rowid)` | Deletes record from table |
| `importRec(action, table, row, target)` | Shows import modal |

### CRUD Operations

| Method | Description |
|--------|-------------|
| `tblMgmtTKeyRecord(ev, table)` | Creates/updates TSIG key |
| `tblMgmtTKeyGRecord(ev, table)` | Creates/updates key group |
| `tblMgmtSrcRecord(ev, table)` | Creates/updates source/whitelist |
| `tblMgmtSrvRecord(ev, table)` | Creates/updates server |
| `tblMgmtRPZRecord(ev, table)` | Creates/updates RPZ |
| `manageUsers(ev)` | Creates/updates user |

### Validation Methods

| Method | Parameters | Description |
|--------|------------|-------------|
| `validateName(vrbl)` | string | Validates alphanumeric name |
| `validateNameAT(vrbl)` | string | Validates name with @ and / |
| `validateUName(vrbl)` | string | Validates username |
| `validateB64(vrbl)` | string | Validates Base64 string |
| `validateInt(vrbl)` | string | Validates integer |
| `validateURL(vrbl)` | string | Validates URL |
| `validateIXFRURL(vrbl)` | string | Validates IXFR URL with keywords |
| `validateREGEX(vrbl)` | string | Validates regex pattern |
| `validateIP(vrbl)` | string | Validates IP address |
| `validateIPList(vrbl)` | string | Validates comma-separated IPs |
| `validateHostname(vrbl)` | string | Validates hostname |
| `validateHostnameNum(vrbl)` | string | Validates hostname with numbers |
| `validateHostnameOnly(vrbl)` | string | Validates hostname only |
| `validateHostnameIP(vrbl)` | string | Validates hostname or IP |
| `validateHostnameIPNet(vrbl)` | string | Validates hostname/IP/network |
| `validateEmail(vrbl)` | string | Validates email address |
| `validatePass(pass1)` | string | Validates password strength |
| `validatePassMatch(pass1, pass2)` | string, string | Validates passwords match |
| `validateCustomAction(CustomActions)` | string | Validates RPZ custom actions |

### Formatter Methods

| Method | Parameters | Description |
|--------|------------|-------------|
| `formatName(val, e)` | string, event | Formats alphanumeric name |
| `formatB64(val, e)` | string, event | Formats Base64 string |
| `formatInt(val, e)` | string, event | Formats integer |
| `formatURL(val, e)` | string, event | Formats URL |
| `formatURLAT(val, e)` | string, event | Formats URL with @ |
| `formatSourceURL(val, e)` | string, event | Formats source URL |
| `formatLocFile(val, e)` | string, event | Formats local file path |
| `formatIXFRURL(val, e)` | string, event | Formats IXFR URL |
| `formatIP(val, e)` | string, event | Formats IP address |
| `formatIPList(val, e)` | string, event | Formats IP list |
| `formatHostname(val, e)` | string, event | Formats hostname |
| `formatHostnameIP(val, e)` | string, event | Formats hostname/IP |
| `formatHostnameIPNet(val, e)` | string, event | Formats hostname/IP/network |
| `formatEmail(val, e)` | string, event | Formats email |

### RpiDNS Methods

| Method | Description |
|--------|-------------|
| `refreshRpiDNS()` | Refreshes RpiDNS list from API |
| `rpidns_add(id)` | Opens add RpiDNS modal |
| `rpidns_edit(id)` | Opens edit RpiDNS modal |
| `rpidns_delete(rpidns_id)` | Deletes RpiDNS with confirmation |
| `add_rpidns(event)` | Saves new/edited RpiDNS |
| `clear_rpidns_modal()` | Resets RpiDNS modal fields |
| `addRpiDNSFeedActionComp(type)` | Returns feed action options |

### Utility Methods

| Method | Description |
|--------|-------------|
| `get_lists(table, variable)` | Fetches dropdown options |
| `genRandom(type)` | Generates random key name or TSIG key |
| `copyToClipboard(ref)` | Copies ref content to clipboard |
| `showInfo(msg, time)` | Shows info message for duration |
| `changeTab(tab)` | Handles tab navigation |
| `signOut()` | Signs out user |
| `pushUpdatestoSRV(SrvId)` | Publishes config to server |

### Export Methods

| Method | Description |
|--------|-------------|
| `exportShowModal(format)` | Opens export modal |
| `rpzExportToggleAll(checked)` | Toggles all RPZs for export |
| `exportDNSConfig()` | Exports DNS config (Bind/PowerDNS/Infoblox) |
| `ImportConfig(ev)` | Imports config from file |
| `ImportConfigLine(ev)` | Imports config from text |
| `checkImpFile(e)` | Handles import file drop |

---

## REST API Endpoints (io2data.php)

### Servers

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/io2data.php/servers` | List all servers |
| POST | `/io2data.php/servers` | Create server |
| PUT | `/io2data.php/servers` | Update server |
| DELETE | `/io2data.php/servers` | Delete server(s) |

### TSIG Keys

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/io2data.php/tkeys` | List all TSIG keys |
| POST | `/io2data.php/tkeys` | Create TSIG key |
| PUT | `/io2data.php/tkeys` | Update TSIG key |
| DELETE | `/io2data.php/tkeys` | Delete TSIG key(s) |
| GET | `/io2data.php/tkeys_mgmt` | List management keys |

### Key Groups

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/io2data.php/tkeys_groups` | List key groups |
| POST | `/io2data.php/tkeys_groups` | Create key group |
| PUT | `/io2data.php/tkeys_groups` | Update key group |
| DELETE | `/io2data.php/tkeys_groups` | Delete key group(s) |
| GET | `/io2data.php/tkeys_groups_list` | List for dropdown |

### Sources

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/io2data.php/sources` | List all sources |
| POST | `/io2data.php/sources` | Create source |
| PUT | `/io2data.php/sources` | Update source |
| DELETE | `/io2data.php/sources` | Delete source(s) |

### Whitelists (Allowlists)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/io2data.php/whitelists` | List all whitelists |
| POST | `/io2data.php/whitelists` | Create whitelist |
| PUT | `/io2data.php/whitelists` | Update whitelist |
| DELETE | `/io2data.php/whitelists` | Delete whitelist(s) |

### RPZs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/io2data.php/rpzs` | List all RPZs |
| POST | `/io2data.php/rpzs` | Create RPZ |
| PUT | `/io2data.php/rpzs` | Update RPZ |
| DELETE | `/io2data.php/rpzs` | Delete RPZ(s) |
| GET | `/io2data.php/rpz_servers` | List servers for dropdown |
| GET | `/io2data.php/rpz_tkeys` | List keys for dropdown |
| GET | `/io2data.php/rpz_sources` | List sources for dropdown |
| GET | `/io2data.php/rpz_whitelists` | List whitelists for dropdown |
| GET | `/io2data.php/rpz_lists` | List enabled RPZs |

### RpiDNS

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/io2data.php/rpidns` | List all RpiDNS configs |
| POST | `/io2data.php/rpidns` | Create RpiDNS config |
| PUT | `/io2data.php/rpidns` | Update RpiDNS config |
| DELETE | `/io2data.php/rpidns` | Delete RpiDNS config |

### Users

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/io2data.php/users` | List all users (admin only) |
| POST | `/io2data.php/users` | Create user |
| PUT | `/io2data.php/users` | Update user |
| DELETE | `/io2data.php/users` | Delete user(s) |

### Configuration

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/io2data.php/servercfg?rowid=X` | Download server config |
| POST | `/io2data.php/publish_upd` | Publish configuration |

---

## Database Schema

### Tables

| Table | Description |
|-------|-------------|
| `users` | User accounts and authentication |
| `tkeys` | TSIG keys for DNS authentication |
| `tkeys_groups` | TSIG key groups |
| `tkeys_tsig_groups` | Key-to-group associations |
| `servers` | ioc2rpz server configurations |
| `servers_tsig` | Server-to-key associations |
| `servers_tsig_groups` | Server-to-key-group associations |
| `mgmt_ips` | Management IP addresses |
| `whitelists` | Allowlist sources |
| `sources` | IOC feed sources |
| `rpzs` | Response Policy Zones |
| `rpzs_servers` | RPZ-to-server associations |
| `rpzs_tkeys` | RPZ-to-key associations |
| `rpzs_tkeys_groups` | RPZ-to-key-group associations |
| `rpzs_whitelists` | RPZ-to-whitelist associations |
| `rpzs_sources` | RPZ-to-source associations |
| `rpzs_notify` | RPZ notify IP addresses |
| `rpidns` | RpiDNS configurations |

---

## Vue App Data Properties

### UI State

| Property | Type | Description |
|----------|------|-------------|
| `toggleMenu` | number | Menu toggle state |
| `cfgTab` | number | Current configuration tab |
| `windowInnerWidth` | number | Window width for responsive |
| `logs_height` | number | Table height |
| `logs_pp` | number | Logs per page |
| `infoWindow` | boolean | Info mode (read-only) |
| `publishUpdates` | boolean | Pending config changes |
| `modalVisibility` | object | Modal visibility states |

### Form Fields - TSIG Keys

| Property | Type | Description |
|----------|------|-------------|
| `ftKeyId` | number | Key ID (-1 for new) |
| `ftKeyName` | string | Key name |
| `ftKey` | string | TSIG key value |
| `ftKeyAlg` | string | Algorithm (md5/sha256/sha512) |
| `ftKeyMGMT` | number | Is management key |
| `ftTKeysGroups` | array | Selected key groups |
| `ftTKeysAllGroups` | array | All available groups |

### Form Fields - Key Groups

| Property | Type | Description |
|----------|------|-------------|
| `ftKeyGId` | number | Group ID (-1 for new) |
| `ftKeyGName` | string | Group name |

### Form Fields - Sources/Whitelists

| Property | Type | Description |
|----------|------|-------------|
| `ftSrcId` | number | Source ID (-1 for new) |
| `ftSrcName` | string | Source name |
| `ftSrcURL` | string | Source URL |
| `ftSrcURLIXFR` | string | IXFR URL |
| `ftSrcREGEX` | string | Extraction regex |
| `ftSrcType` | string | Type (sources/whitelists) |
| `ftSrcMaxIOC` | string | Max IOC count |
| `ftSrcHotCacheAXFR` | string | AXFR cache time |
| `ftSrcHotCacheIXFR` | string | IXFR cache time |
| `ftSrcIoCType` | string | IOC type (mixed/fqdn/ip) |
| `ftSrcKeepInCache` | number | Keep in cache flag |

### Form Fields - Servers

| Property | Type | Description |
|----------|------|-------------|
| `ftSrvId` | number | Server ID (-1 for new) |
| `ftSrvName` | string | Server name |
| `ftSrvPubIP` | string | Public IP/FQDN |
| `ftSrvIP` | string | Management IP/FQDN |
| `ftSrvNS` | string | Name server |
| `ftSrvEmail` | string | Admin email |
| `ftSrvMGMT` | number | Management enabled |
| `ftSrvMGMTIP` | string | Management IPs |
| `ftSrvTKeys` | array | Selected TSIG keys |
| `ftSrvDisabled` | number | Disabled flag |
| `ftSrvSType` | number | Storage type (0=local, 1=scp) |
| `ftSrvURL` | string | Config file path |
| `ftCertFile` | string | SSL certificate file |
| `ftKeyFile` | string | SSL key file |
| `ftCACertFile` | string | CA certificate file |
| `ftCustomConfig` | string | Custom config text |

### Form Fields - RPZs

| Property | Type | Description |
|----------|------|-------------|
| `ftRPZId` | number | RPZ ID (-1 for new) |
| `ftRPZName` | string | RPZ zone name |
| `ftRPZSrvs` | array | Selected servers |
| `ftRPZTKeys` | array | Selected TSIG keys |
| `ftRPZSrc` | array | Selected sources |
| `ftRPZWL` | array | Selected whitelists |
| `ftRPZNotify` | string | Notify IPs |
| `ftRPZSOA_Refresh` | string | SOA refresh |
| `ftRPZSOA_UpdRetry` | string | SOA update retry |
| `ftRPZSOA_Exp` | string | SOA expiration |
| `ftRPZSOA_NXTTL` | string | SOA NX TTL |
| `ftRPZCache` | number | Cache enabled |
| `ftRPZWildcard` | number | Wildcards enabled |
| `ftRPZAction` | string | Response action |
| `ftRPZActionCustom` | string | Custom action config |
| `ftRPZIOCType` | string | IOC type |
| `ftRPZAXFR` | string | AXFR update time |
| `ftRPZIXFR` | string | IXFR update time |
| `ftRPZDisabled` | number | Disabled flag |

### Form Fields - Users

| Property | Type | Description |
|----------|------|-------------|
| `ftUId` | number | User ID (0 for new) |
| `ftUName` | string | Current username |
| `ftUNameProf` | string | Username for form |
| `ftUPwd` | string | Password |
| `ftUpwdConf` | string | Password confirmation |
| `ftUPerm` | number | Permission level |

### Form Fields - RpiDNS

| Property | Type | Description |
|----------|------|-------------|
| `addRpiDNSid` | number | RpiDNS ID (0 for new) |
| `addRpiDNSName` | string | RpiDNS name |
| `addRpiDNSComment` | string | Commentary |
| `addRpiDNSModel` | object | Hardware model |
| `addRpiDNSServer` | object | DNS server type |
| `addRpiDNSCheckConf` | boolean | Check config updates |
| `addRpiDNSRedirect` | string | Redirect type |
| `addRpiDNSRedirectURL` | string | Custom redirect URL |
| `addRpiDNSLogs` | string | Logging type |
| `addRpiDNSLogsURL` | string | Log destination |
| `addRpiDNSType` | string | DNS type (primary/secondary) |
| `addRpiDNSTypeIPNet` | string | Primary IP or subnet |
| `ftRpiDNSRPZ` | array | Selected RPZ feeds |
| `ftRpiDNSRPZAction` | object | RPZ actions map |

---

## Security Features

1. **CSRF Protection**: All state-changing requests require CSRF token
2. **Password Hashing**: bcrypt with automatic MD5 migration
3. **Account Lockout**: 5 failed attempts = 15 minute lockout
4. **Session Security**: Session regeneration on login. Cookie attributes are set in
   `startSession()` (io2fun.php) rather than relying on php.ini, so every deployment
   gets `HttpOnly`, `SameSite=Strict`, and `Secure` when served over TLS. Always call
   `startSession()` instead of `session_start()`
5. **Content Security Policy**: Comprehensive CSP headers
6. **HSTS**: Emitted by `secHeaders()` over TLS, so bare-metal installs get it and not
   only the container's Apache configuration
7. **XSS Prevention**: HTML escaping via `escapeHtml()` function
8. **Database not served over HTTP**: The SQLite database lives under `www/io2cfg`, i.e.
   inside `DocumentRoot`. `scripts/run_ioc2rpz.gui.sh` appends a `Require all denied`
   block for that directory, covering the `-wal` and `-shm` sidecars as well as the
   database itself. The block is applied idempotently and outside the `config-done`
   marker guard so existing containers receive it on restart. File permissions cannot
   substitute for this: mod_php needs read/write on the database and runs as the same
   user that serves it. **Operators not using the bundled Apache configuration must add
   an equivalent rule.** Relocating the database outside the web root is tracked in
   TODO.md

### SQL construction

There is no prepared-statement layer. Values are interpolated into SQL after being put
through one of:

| Helper | Use for | Output |
|--------|---------|--------|
| `DB_escape($db, $text)` | text values | escaped string, **must be wrapped in `'` quotes by the caller** |
| `DB_boolval($val)` | boolean flags | `0` or `1` |
| `DB_intOrNull($val)` | nullable integers | `NULL` or a decimal integer |
| `intval($val)` | required integers | decimal integer |

`DB_escape()` wraps `SQLite3::escapeString()`, which only doubles single quotes. It
therefore provides **no protection at all** in an unquoted context: a payload containing
no quotes escapes the statement, and `DB_execute()` runs through `SQLite3::exec()`, which
accepts multiple statements. Any numeric or boolean column must use `DB_boolval()`,
`DB_intOrNull()` or `intval()` — never a bare `DB_escape()`. This was a live SQL
injection in `POST servers` before 2.3.0.

---

## Source Attribution

Source attribution lets operators track which feed sources contributed to a matched
indicator. It is controlled by a server-wide default and a per-feed override, and
the results are surfaced in the IOC lookup view.

### Source attribution (global default)

The server editor exposes a **Source attribution (global default)** control that
sets the server-wide attribution default. It accepts exactly one of the following
values:

| Value | Meaning |
|-------|---------|
| `off` | Attribution disabled by default (default when unset). |
| `auto` | Attribution enabled automatically where applicable. |
| `on` | Attribution enabled by default. |

The selected value is persisted as the server's `track_default` setting. When a
server has no stored value, it is treated as `off`.

### Track sources (per-feed)

The RPZ (feed) editor exposes a **Track sources** control that overrides the server
default for an individual feed. It accepts exactly one of the following values:

| Value | Meaning |
|-------|---------|
| `Inherit` | Use the server's global default (default when unset). |
| `auto` | Enable attribution automatically for this feed. |
| `true` | Force attribution on for this feed. |
| `false` | Force attribution off for this feed. |

The selected value is persisted as the feed's `track_sources` setting. When a feed
has no stored value, it is treated as `Inherit`.

### Effective tracking resolution

When a feed's **Track sources** control is set to `Inherit`, the editor resolves the
effective tracking state (Effective_Tracking) and displays it as a read-only
inherited-state indication. The indication is rendered as the literal text
`Inherited: ` immediately followed by the resolved value, and it updates live as the
control changes (no save required). Resolution follows this precedence, and the
result is always one of `off`, `auto`, or `on`:

1. If the feed's **Track sources** value is one of `auto`, `true`, or `false`, that
   value is used directly, mapping `true` → `on` and `false` → `off` (`auto` stays
   `auto`).
2. Otherwise (the feed is set to `Inherit`), the server's global default is used when
   it is one of `off`, `auto`, or `on`.
3. Otherwise, it falls back to the built-in default `off`.

### Cache requirement and zone rebuild

Attribution is produced only for feeds whose **cache** setting is `true`. The RPZ
editor always shows a hint that attribution requires `cache = true`. When tracking is
being enabled (the control resolves to `auto` or `on`):

- The editor shows a hint that enabling tracking on a cached feed triggers a one-time
  full zone rebuild (AXFR_Rebuild).
- If the feed's `cache` setting is not `true`, the editor shows a notice that
  attribution will not be produced until `cache = true`.

When tracking resolves to `off` (or the control is `false`), neither the rebuild hint
nor the attribution-unavailable notice is shown.

### IOC lookup view

The IOC lookup view looks up a single indicator (1–2048 characters) against a
selected server. Both the indicator and the server are required; the view blocks
submission of an empty indicator or a missing server before contacting the backend.

Results list each matching feed's name, type, and contributing sources, rendered as
badges (one badge per source, preserving order and duplicates). Special cases:

- When a feed's `sources` value is an empty array or `null`, the view shows an
  **attribution unavailable** indication instead of badges.
- When the lookup returns no matching feeds, the view shows a **not found in any
  feed** message.

---

## DNS Rate Limits

ioc2rpz can limit how many DNS requests a single source may make within a time window.
The limits are configured server-wide and, optionally, overridden per feed. Every
option is individually optional, and **an empty field means inherit** — the GUI never
writes a value to represent "inherit".

### Options

| Option | Levels | Unit / range | Built-in default when unset |
|---|---|---|---|
| Rate limit window | server, feed | seconds, integer > 0 | 60 |
| Max requests per window | server, feed | integer >= 0 | 6 |
| Max unknown requests per window | **server only** | integer >= 0 | 1 |

**Max requests per window** limits the granular per-`{IP, zone, query type}` bucket: a
provisioned zone queried with `SOA`, `AXFR`, or `IXFR`, plus recognized management
requests.

**Max unknown requests per window** limits the aggregate per-`{IP}` bucket: an unknown
zone, an unsupported query type, or the wrong class. It exists only at the server level
because a request counted there never resolved to a zone, so there is no feed
configuration to read it from. The feed editor does not offer it, and the backend
rejects it on a feed.

Setting a maximum to **0 refuses every request in that bucket**. This is a valid
setting, not a way to disable the limit, so the editors show a warning when a maximum
is set to 0. Leave the field empty to inherit instead.

### Precedence

Each option resolves **independently**, with the precedence:

**feed → server → built-in default**

So a feed may set *Max requests per window* alone and still inherit *Rate limit window*
from the server, and a server may set nothing at all.

While a field is empty, the editor shows the resolved value and where it came from, for
example `Inherited: 60 (server)` or `Inherited: 6 (built-in default)`. The indication
updates live as the form changes; no save is required.

#### Feeds published to several servers

A feed can be assigned to more than one server, and **each server generates its own
configuration file**. An option the feed does not set is therefore inherited from each
server separately and can resolve to a different value per server. When that happens the
editor reports the breakdown instead of a single value:

```
Inherited: varies by server - ns1: 30, ns2: 120
```

When the selected servers all resolve to the same value it is shown as one value, as
usual. An option set **on the feed** overrides every server, so it is never ambiguous.

### Server editor

The server editor exposes all three fields under **DNS rate limits**. They apply to
every zone on that server that does not override them.

Sources listed in the server's **ACL / management stations IPs** are **exempt** from DNS
rate limiting, so a monitoring station in the ACL is never limited. The editor notes
this next to the fields.

### Feed editor

The RPZ (feed) editor exposes **Rate limit window** and **Max requests per window**
under **DNS rate limits**, overriding the server values for that feed only.

### Validation

A value that is present must be a whole number in range:

| Field | Range |
|-------|-------|
| Rate limit window | 1 to 86400 seconds (24 hours) |
| Max requests per window | 0 or more, no upper bound |
| Max unknown requests per window | 0 or more, no upper bound |

Both the frontend and the backend reject anything else, and an invalid value moves focus
to the offending field so it can be found and corrected.

Rejecting an invalid value matters because the ioc2rpz server only *logs and ignores* an
invalid rate limit and then falls back to the next level, so a bad value written by the
GUI would silently do nothing at all rather than fail visibly.

The window is capped because it is stored with every entry in the server's rate limit
table and decides when that entry is swept: an absurd window (a fumbled digit) would keep
entries alive for as long as it lasts, which is a slow, configuration-driven memory leak.
A stored window above the cap is omitted from the generated configuration, so the record
inherits rather than emitting a value the server would have to carry. The maximums are
deliberately uncapped — a large threshold only makes the limiter permissive and retains
nothing.

### Storage and generated configuration

The values are persisted in nullable columns — `servers.rl_window`,
`servers.rl_max_requests`, `servers.rl_max_unknown_requests`, `rpzs.rl_window`,
`rpzs.rl_max_requests` — where **NULL means inherit**. NULL is kept distinct from a
number so "inherit" never collapses into "explicitly set to the current default".

Set options are emitted as an optional trailing `{rate_limit, Options}` element on the
`srv` and `rpz` tuples, after the optional TrackSources element:

```erlang
{srv,{NS_Name, Email, MgmtKeys, ACL,
      {rate_limit,[{window,60},{max_requests,6},{max_unknown_requests,1}]}}}.

{rpz,{ZoneName, SOA_Refresh, SOA_Retry, SOA_Expire, SOA_NXDomain_TTL, Cache, Wildcards,
      Action, Keys, IOC_Type, AXFR_Time, IXFR_Time, Sources, NotifyList, Whitelists,
      {rate_limit,[{window,60},{max_requests,20}]}}}.
```

Only the options that are set are emitted, so a feed overriding one option produces
just that option:

```erlang
{rpz,{..., Whitelists, {rate_limit,[{max_requests,20}]}}}.
```

When no rate limit is set, no element is emitted at all and the tuple is byte-identical
to the pre-feature output. The element is order-independent with the TrackSources
element: the writer emits TrackSources first, and the importer accepts either order, or
either element alone.

### Zone name canonicalisation

The ioc2rpz server canonicalises zone names to lower case (RFC 4343). Feed names are
therefore normalized to lower case as they are typed and on import, so the GUI and the
server always agree on a zone's identity.

---

## Build & Development

### Development Mode
```bash
npm install
npm run dev
```

### Production Build
```bash
npm run build
```

### Environment Variables
- `VITE_DEV_MODE=true` - Enable Vite dev server
- `VITE_DEV_HOST=localhost` - Dev server host
- `VITE_DEV_PORT=5173` - Dev server port

---

*Documentation generated for ioc2rpz.gui v2022121101*
