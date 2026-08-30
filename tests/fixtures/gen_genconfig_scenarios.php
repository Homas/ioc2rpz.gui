<?php
/**
 * Drives the REAL genConfig() in www/io2vars.php against a throwaway v4-schema SQLite
 * database and emits, as JSON, the srv/rpz lines it generates for each DNS rate limit
 * scenario.
 *
 * tests/genconfig-import-roundtrip.test.js runs this and feeds the output to the real
 * JS importer, so the pair covers the whole export -> import path across the language
 * boundary: not just the emission helpers in isolation, but the tuple assembly in
 * genConfig() (where the rate limit element is concatenated after the TrackSources
 * element) and the quirks of genConfig's real output that a hand-built line would miss,
 * notably the empty `[""]` key/source lists.
 *
 * Run standalone with:
 *   php -d display_errors=0 -d error_reporting=0 tests/fixtures/gen_genconfig_scenarios.php
 *
 * @package ioc2rpz.gui
 */

declare(strict_types=1);

require_once __DIR__ . '/../../www/io2vars.php';

$dbFile = tempnam(sys_get_temp_dir(), 'io2gen_') ?: '';
@unlink($dbFile);
$db = new SQLite3($dbFile);

// v4 schema, mirroring scripts/init_db.php after the rate limit migration.
$db->exec('create table servers (user_id integer, name text, ip text, pub_ip text, ns text, '
    . 'email text, mgmt integer, disabled integer, stype integer, URL text, cfg_updated integer, '
    . 'publish_upd integer, certfile text, keyfile text, cacertfile text, custom_config text, '
    . "track_default text default 'off', rl_window integer default NULL, "
    . 'rl_max_requests integer default NULL, rl_max_unknown_requests integer default NULL);');
$db->exec('create table rpzs (user_id integer, name text, soa_refresh integer, '
    . 'soa_update_retry integer, soa_expiration integer, soa_nx_ttl integer, cache integer, '
    . 'wildcard integer, action text, ioc_type text, axfr_update integer, ixfr_update integer, '
    . "disabled integer, track_sources text default 'Inherit', rl_window integer default NULL, "
    . 'rl_max_requests integer default NULL);');

foreach ([
    'servers_tsig (server_id integer, user_id integer, tsig_id integer)',
    'servers_tsig_groups (server_id integer, user_id integer, tsig_group_id integer)',
    'mgmt_ips (server_id integer, user_id integer, mgmt_ip text)',
    'tkeys (user_id integer, name text, alg text, tkey text)',
    'tkeys_groups (user_id integer, group_name text)',
    'tkeys_tsig_groups (user_id integer, tsig_id integer, tsig_group_id integer)',
    'whitelists (user_id integer, name text, url text, regex text, userid text, max_ioc integer, '
        . 'hotcache_time integer, hotcacheixfr_time integer, ioc_type text, keep_in_cache integer)',
    'sources (user_id integer, name text, url text, url_ixfr text, regex text, userid text, '
        . 'max_ioc integer, hotcache_time integer, hotcacheixfr_time integer, ioc_type text, '
        . 'keep_in_cache integer)',
    'rpzs_servers (rpz_id integer, user_id integer, server_id integer)',
    'rpzs_tkeys (rpz_id integer, user_id integer, tkey_id integer)',
    'rpzs_tkeys_groups (rpz_id integer, user_id integer, tkey_group_id integer)',
    'rpzs_whitelists (rpz_id integer, user_id integer, whitelist_id integer)',
    'rpzs_sources (rpz_id integer, user_id integer, source_id integer)',
    'rpzs_notify (rpz_id integer, user_id integer, notify text)',
] as $table) {
    $db->exec("create table $table;");
}

$USERID = 1;

/**
 * Scenario => [srv rate limits, rpz rate limits, track_default, track_sources].
 * srv limits are [window, max_requests, max_unknown_requests]; rpz are [window, max_requests].
 * null models a NULL column (inherit).
 */
$scenarios = [
    'none'        => [[null, null, null], [null, null], 'off',  'Inherit'],
    'srv_only'    => [[60, 6, 1],         [null, null], 'off',  'Inherit'],
    'rpz_only'    => [[null, null, null], [30, 20],     'off',  'Inherit'],
    'both'        => [[60, 6, 1],         [30, 20],     'off',  'Inherit'],
    'rpz_partial' => [[60, 6, 1],         [null, 20],   'off',  'Inherit'],
    'zero_max'    => [[null, 0, 0],       [null, 0],    'off',  'Inherit'],
    'with_track'  => [[60, 6, 1],         [30, 20],     'auto', 'true'],
    'track_only'  => [[null, null, null], [null, null], 'on',   'false'],
];

$out = [];
foreach ($scenarios as $name => [$srvRl, $rpzRl, $trackDefault, $trackSources]) {
    $db->exec('delete from servers;');
    $db->exec('delete from rpzs;');
    $db->exec('delete from rpzs_servers;');
    $db->exec('delete from mgmt_ips;');

    $w = DB_intOrNull($srvRl[0]);
    $m = DB_intOrNull($srvRl[1]);
    $u = DB_intOrNull($srvRl[2]);
    $db->exec("insert into servers values($USERID,'srv1','10.0.0.1','203.0.113.1',"
        . "'ns.example.com','admin@example.com',1,0,0,'ioc2rpz.conf',0,0,'','','','',"
        . "'$trackDefault',$w,$m,$u);");
    $srvId = (int) $db->lastInsertRowID();
    $db->exec("insert into mgmt_ips values($srvId,$USERID,'192.0.2.10');");

    $rw = DB_intOrNull($rpzRl[0]);
    $rm = DB_intOrNull($rpzRl[1]);
    $db->exec("insert into rpzs values($USERID,'feed.example',3600,60,86400,30,1,1,"
        . "'nxdomain','fqdn',900,300,0,'$trackSources',$rw,$rm);");
    $rpzId = (int) $db->lastInsertRowID();
    $db->exec("insert into rpzs_servers values($rpzId,$USERID,$srvId);");

    $cfg = genConfig($db, $USERID, $srvId)['cfg'];

    $lines = [];
    foreach (explode("\n", $cfg) as $line) {
        if (preg_match('/^\{(srv|rpz),/', $line)) {
            $lines[] = $line;
        }
    }
    $out[$name] = $lines;
}

DB_close($db);
@unlink($dbFile);

echo json_encode($out) . "\n";
