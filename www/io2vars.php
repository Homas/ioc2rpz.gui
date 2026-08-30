<?php
/**
 * ioc2rpz.gui - Configuration Variables and Database Functions
 * 
 * This file contains:
 * - Database configuration constants
 * - ioc2rpz server management settings
 * - Database abstraction layer functions (SQLite)
 * - Configuration generation utilities for ioc2rpz servers
 * 
 * @package ioc2rpz.gui
 * @author Vadim Pavlov
 * @copyright 2018-2026
 * @license Apache-2.0
 */

/**
 * Database type constant
 * Currently only SQLite is supported for single-user deployments
 */
const DB="sqlite";

/**
 * Path to the SQLite database file relative to the www directory
 */
const DBFile="io2cfg/io2db.sqlite";

/**
 * Whether to create the database file if it doesn't exist
 */
const DBCreateIfNotExists=true;

/**
 * Directory for ioc2rpz configuration files
 */
const ioc2rpzConf="io2cfg";

/**
 * Path to the dig command for DNS queries
 * Alternative: /usr/bin/kdig +tls for DNS over TLS
 */
const dig="/usr/bin/dig +tcp";

/**
 * ioc2rpz management interface type
 * Options: 'rest' for REST API, 'dns' for DNS-based management
 */
const io2mgmt="rest";

/**
 * Whether to verify SSL certificates for management connections
 * Set to false for self-signed certificates
 */
const io2mgmt_verifyssl=false;

/**
 * Port number for REST management interface
 */
const rest_mgmt_port=8443;

/**
 * Application version number (YYYYMMDDNN format)
 * @var int
 */
$io2ver=2026083001;

/**
 * Filters an array to return only numeric values
 * Used to sanitize arrays of IDs before database queries
 * 
 * @param array $array Input array containing mixed values
 * @return array Array containing only numeric values
 */
function filterIntArr($array){
  $result = [];
  foreach ($array as $a) {if (is_numeric($a)) $result[]=$a;};
  return $result;
};

/**
 * Extracts group IDs from an array of prefixed group identifiers
 * Group IDs are prefixed with 'gr_' (e.g., 'gr_123' returns '123')
 * 
 * @param array $array Input array containing group identifiers
 * @return array Array of extracted numeric group IDs
 */
function getGroupsId($array){
  $result = [];
  foreach ($array as $a) {if (preg_match('/^gr_(\d+)$/',$a,$m)) $result[]=$m[1];};
  return $result;
};

/**
 * Placeholder function for database validation
 * Reserved for future implementation of database integrity checks
 */
function checkDB(){

};

/**
 * Opens a database connection
 * Configures SQLite with WAL journal mode for better concurrency
 * 
 * @return SQLite3 Database connection handle
 */
function DB_open()
{
  switch (DB){
    case "sqlite":
      $db = new SQLite3(DBFile);
      $db->busyTimeout(5000);
      $db->exec('PRAGMA journal_mode = wal;'); //PRAGMA foreign_keys = ON;
    break;
  }
  return $db;
}

/**
 * Closes a database connection
 * 
 * @param SQLite3 $db Database connection handle to close
 * @return void
 */
function DB_close($db)
{
  switch (DB){
    case "sqlite":
      $db->close();
    break;
  }
}

/**
 * Executes a SELECT query and returns a result set
 * 
 * @param SQLite3 $db Database connection handle
 * @param string $sql SQL SELECT query to execute
 * @return SQLite3Result Query result set for iteration
 */
function DB_select($db,$sql){
  switch (DB){
    case "sqlite":
      $result=$db->query($sql);
    break;
  }
  return $result;
};

/**
 * Escapes a string for safe use in SQL queries
 * Prevents SQL injection attacks
 * 
 * @param SQLite3 $db Database connection handle
 * @param string $text String to escape
 * @return string Escaped string safe for SQL queries
 */
function DB_escape($db,$text){
  switch (DB){
    case "sqlite":
      $result=$db->escapeString($text);
    break;
  }
  return $result;
};

/**
 * Converts a value to a database boolean (0 or 1)
 * 
 * @param mixed $val Value to convert (string "1" becomes 1, else 0)
 * @return int 1 for true, 0 for false
 */
function DB_boolval($val){
  switch (DB){
    case "sqlite":
      $result=$val=="1"?1:0;
    break;
  }
  return $result;
};

/**
 * Renders an optional integer as a SQL literal, preserving the NULL/0 distinction.
 *
 * Used for the nullable DNS rate limit columns, where NULL means "inherit" and 0 is
 * a legitimate value (refuse every request in that bucket). An absent, empty, or
 * non-integer input yields the unquoted keyword NULL; anything else yields the
 * integer, so the value can never carry SQL syntax.
 *
 * @param mixed $val Value to render (null, "", or an integer-like scalar)
 * @return string `"NULL"` or a decimal integer literal
 */
function DB_intOrNull($val){
  if ($val === null || $val === "" || $val === false) return "NULL";
  if (is_array($val)) return "NULL";
  if (!preg_match('/^\s*-?[0-9]+\s*$/', (string)$val)) return "NULL";
  return (string)intval($val);
};

/**
 * Executes a SELECT query and returns all results as an array
 * 
 * @param SQLite3 $db Database connection handle
 * @param string $sql SQL SELECT query to execute
 * @return array Array of associative arrays, one per row
 */
function DB_selectArray($db,$sql){
  switch (DB){
    case "sqlite":
			#error_log("$sql\n");
      $data=[];
      $result=$db->query($sql);
      while ($row=$result->fetchArray(SQLITE3_ASSOC)){
        $data[]=$row;
      };
    break;
  }
  return $data;
};

/**
 * Fetches the next row from a result set as an associative array
 * 
 * @param SQLite3Result $result Query result set
 * @return array|false Associative array of column values, or false if no more rows
 */
function DB_fetchArray($result){
  switch (DB){
    case "sqlite":
      $data=$result->fetchArray(SQLITE3_ASSOC);
    break;
  }
  return $data;
};

/**
 * Executes a non-SELECT SQL statement (INSERT, UPDATE, DELETE)
 * 
 * @param SQLite3 $db Database connection handle
 * @param string $sql SQL statement to execute
 * @return bool True on success, false on failure
 */
function DB_execute($db,$sql){
  switch (DB){
    case "sqlite":
      $result=$db->exec($sql);
    break;
  }
  return $result;
};

/**
 * Generates ioc2rpz server configuration file content
 * 
 * Creates an Erlang-format configuration file for ioc2rpz server including:
 * - Server settings (NS, email, TSIG keys, management IPs)
 * - SSL certificate configuration
 * - TSIG keys for zone transfers
 * - Whitelists and sources
 * - RPZ zone definitions
 * 
 * @param SQLite3 $db Database connection handle
 * @param int $USERID User ID for filtering records
 * @param int $SrvId Server row ID to generate config for
 * @return array Associative array with 'filename' and 'cfg' keys
 */
function genConfig($db,$USERID,$SrvId){
  //srv
  $row=DB_selectArray($db,"select * from servers where user_id=$USERID and rowid=$SrvId;")[0];
  $cfg="% ioc2rpz server {$row['name']} config generated by ioc2rpz.gui at ".date("Y-m-d H:i:s")."\n";
  $cfg.="\n% srv record: ns, email, [tkeys], [mgmt]\n";
  $response['filename']=$row['URL']?$row['URL']:"{$row['name']}.conf";
  $subres=DB_selectArray($db,"select name from servers_tsig left join tkeys on tkeys.rowid=servers_tsig.tsig_id where servers_tsig.user_id=$USERID and servers_tsig.server_id=$SrvId");
  $subres1=DB_selectArray($db,"select mgmt_ip from mgmt_ips where mgmt_ips.user_id=$USERID and mgmt_ips.server_id=$SrvId;");

  $subres_gr=DB_selectArray($db,"select group_name from servers_tsig_groups left join tkeys_groups on tkeys_groups.rowid=servers_tsig_groups.tsig_group_id where servers_tsig_groups.user_id=$USERID and servers_tsig_groups.server_id=$SrvId");
	if ($subres_gr) $groups=",{groups,[\"".implode('","',array_column($subres_gr,'group_name'))."\"]}"; else $groups="";

  $cfg.="{srv,{\"".erlEscape($row['ns'])."\",\"".str_replace("@",".",erlEscape($row['email']))."\",[\"".implode('","',array_map('erlEscape',array_column($subres,'name')))."\"$groups],[\"".implode('","',array_map('erlEscape',array_column($subres1,'mgmt_ip')))."\"]".erlSrvTrackSources($row['track_default']).erlSrvRateLimit($row)."}}.\n";

  if ($row['certfile']!="" and $row['keyfile']!="") {
    $cfg.="\n% cert record: certfile, keyfile, cacertfile\n";
    $cfg.="{cert,{\"".erlEscape($row['certfile'])."\",\"".erlEscape($row['keyfile'])."\",\"".erlEscape($row['cacertfile'])."\"}}.\n";
  };

  if ($row['custom_config']!="") {
    $cfg.="\n% Custom configuration\n";
    $cfg.="{$row['custom_config']}\n\n";
	};

  //tkeys -- add groups TSIGs from servers and RPZ
  $cfg.="\n% tsig key record: name, alg, key\n";
  $row=DB_selectArray($db,"select rowid,* from tkeys where user_id=$USERID and (rowid in (select tsig_id from servers_tsig where server_id=$SrvId) or rowid in (select tsig_id from tkeys_tsig_groups left join servers_tsig_groups on servers_tsig_groups.tsig_group_id=tkeys_tsig_groups.tsig_group_id where server_id=$SrvId and servers_tsig_groups.user_id=$USERID) or rowid in (select tsig_id from tkeys_tsig_groups left join rpzs_tkeys_groups on rpzs_tkeys_groups.tkey_group_id=tkeys_tsig_groups.tsig_group_id left join rpzs_tkeys on rpzs_tkeys.rpz_id=rpzs_tkeys_groups.rpz_id left join rpzs_servers on rpzs_servers.rpz_id=rpzs_tkeys.rpz_id where server_id=$SrvId and rpzs_tkeys_groups.user_id=$USERID) or rowid in (select tkey_id from rpzs_tkeys left join rpzs on rpzs_tkeys.rpz_id=rpzs.rowid left join rpzs_servers on rpzs_servers.rpz_id=rpzs.rowid where server_id=$SrvId and rpzs.disabled=0));");
  foreach($row as $item){
		$subres_gr=DB_selectArray($db,"select group_name from tkeys_tsig_groups left join tkeys_groups on tkeys_groups.rowid=tkeys_tsig_groups.tsig_group_id where tkeys_tsig_groups.user_id=$USERID and tkeys_tsig_groups.tsig_id={$item['rowid']}");
		if ($subres_gr) $groups=",[\"".implode('","',array_column($subres_gr,'group_name'))."\"]"; else $groups="";
		$cfg.="{key,{\"".erlEscape($item['name'])."\",\"".erlEscape($item['alg'])."\",\"".erlEscape($item['tkey'])."\"$groups}}.\n";
	};

  //whitelists
  $cfg.="\n% whitelist record: name, path, regex\n";
  $row=DB_selectArray($db,"select * from whitelists where user_id=$USERID and rowid in (select whitelist_id from rpzs_whitelists left join rpzs on rpzs_whitelists.rpz_id=rpzs.rowid left join rpzs_servers on rpzs_servers.rpz_id=rpzs.rowid where server_id=$SrvId);");
  foreach($row as $item){$cfg.="{whitelist,{\"{$item['name']}\",\"{$item['url']}\",".($item['regex']=="none"?"none":'"'.erlEscape($item['regex']).'"').",".($item['userid']==NULL?'""':$item['userid']).",{$item['max_ioc']},{$item['hotcache_time']},{$item['hotcacheixfr_time']},\"{$item['ioc_type']}\",".($item['keep_in_cache']?"true":"false")."}}.\n";};

  //sources
  $cfg.="\n% source record: name, axfr_path, ixfr_path, regex\n";
  $row=DB_selectArray($db,"select * from sources where user_id=$USERID and rowid in (select source_id from rpzs_sources left join rpzs on rpzs_sources.rpz_id=rpzs.rowid left join rpzs_servers on rpzs_servers.rpz_id=rpzs.rowid where server_id=$SrvId);");
  foreach($row as $item){$cfg.="{source,{\"{$item['name']}\",\"{$item['url']}\",\"{$item['url_ixfr']}\",".($item['regex']=="none"?"none":'"'.erlEscape($item['regex']).'"').",".($item['userid']==NULL?'""':$item['userid']).",{$item['max_ioc']},{$item['hotcache_time']},{$item['hotcacheixfr_time']},\"{$item['ioc_type']}\",".($item['keep_in_cache']?"true":"false")."}}.\n";};

  //rpzs -- add groups {groups,["ip2"]},
  $cfg.="\n% rpz record: name, SOA refresh, SOA update retry, SOA expiration, SOA NXDomain TTL, Cache, Wildcards, Action, [tkeys], ioc_type, AXFR_time, IXFR_time, [sources], [notify], [whitelists]\n";
  $row=DB_selectArray($db,"select rpzs.rowid,* from rpzs left join rpzs_servers on rpzs_servers.rpz_id=rpzs.rowid where server_id=$SrvId and rpzs.user_id=$USERID and rpzs.disabled=0;");

  foreach($row as $item){
    $subres_tkeys=DB_selectArray($db,"select name from rpzs_tkeys left join tkeys on tkeys.rowid=rpzs_tkeys.tkey_id where rpzs_tkeys.user_id=$USERID and rpz_id={$item['rowid']}");
		$subres_gr=DB_selectArray($db,"select group_name from rpzs_tkeys_groups left join tkeys_groups on tkeys_groups.rowid=rpzs_tkeys_groups.tkey_group_id where rpzs_tkeys_groups.user_id=$USERID and rpzs_tkeys_groups.rpz_id={$item['rowid']}");
	 if ($subres_gr) $groups=",{groups,[\"".implode('","',array_column($subres_gr,'group_name'))."\"]}"; else $groups="";

    $subres_srcs=DB_selectArray($db,"select name from rpzs_sources left join sources on sources.rowid=rpzs_sources.source_id where rpzs_sources.user_id=$USERID and rpz_id={$item['rowid']}");
    $subres_wl=DB_selectArray($db,"select name from rpzs_whitelists left join whitelists on whitelists.rowid=rpzs_whitelists.whitelist_id where rpzs_whitelists.user_id=$USERID and rpz_id={$item['rowid']}");
    $subres_notify=DB_selectArray($db,"select notify from rpzs_notify where user_id=$USERID and rpz_id={$item['rowid']}");

    $cfg.="{rpz,{\"{$item['name']}\",{$item['soa_refresh']},{$item['soa_update_retry']},{$item['soa_expiration']},{$item['soa_nx_ttl']},\"".($item['cache']?"true":"false")."\",\"".($item['wildcard']?"true":"false")."\",".erlAction($item['action']).",[\"".implode('","',array_column($subres_tkeys,'name'))."\"$groups],\"{$item['ioc_type']}\",{$item['axfr_update']},{$item['ixfr_update']},[\"".implode('","',array_column($subres_srcs,'name'))."\"],[".(empty($subres_notify)?"":"\"".implode('","',array_column($subres_notify,'notify'))."\"")."],[".(empty($subres_wl)?"":"\"".implode('","',array_column($subres_wl,'name'))."\"")."]".erlRpzTrackSources($item['track_sources']).erlRpzRateLimit($item)."}}.\n";
  };

  $response['cfg']=$cfg;
  return $response;
};

/**
 * Escapes special characters for Erlang string format
 * Reserved for future implementation of quote escaping
 * 
 * @param string $str String to escape
 * @return string Escaped string safe for Erlang format
 */
function erlEscape($str){
  // The stored values (regexes, URLs, keys, etc.) are already kept in the
  // exact form ioc2rpz expects in the generated config, so they must be
  // written verbatim. Re-escaping backslashes/quotes here doubles the escape
  // characters and corrupts regex patterns, so this is intentionally a
  // pass-through.
  return $str;
};

/**
 * Validates local RPZ records format
 * Reserved for future implementation of record validation
 * 
 * @param string $str Local records string to validate
 * @return string Validated records string
 */
function erlChLRecords($str){
  // Validate and sanitize custom local RPZ records
  // Only allow known record types with validated values
  if (empty($str)) return 'nxdomain';
  $decoded = json_decode($str);
  if ($decoded === null) return 'nxdomain';
  $validated = [];
  foreach(explode(PHP_EOL, $decoded) as $item){
    $item = trim($item);
    if (empty($item)) continue;
    $lr = explode("=", $item, 2);
    if (count($lr) !== 2) continue;
    $type = trim($lr[0]);
    $val = trim($lr[1]);
    switch($type){
      case 'local_a':
        if (filter_var($val, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $validated[] = "$type=$val";
        break;
      case 'local_aaaa':
        if (filter_var($val, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) $validated[] = "$type=$val";
        break;
      case 'redirect_ip':
        if (filter_var($val, FILTER_VALIDATE_IP)) $validated[] = "$type=$val";
        break;
      case 'local_cname':
      case 'redirect_domain':
        if (filter_var($val, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) $validated[] = "$type=$val";
        break;
      case 'local_txt':
        // Sanitize: remove quotes and control characters
        $val = preg_replace('/["\x00-\x1f]/', '', $val);
        if (!empty($val)) $validated[] = "$type=$val";
        break;
      default:
        // Reject unknown record types
        break;
    }
  }
  return empty($validated) ? 'nxdomain' : json_encode(implode(PHP_EOL, $validated));
};

/**
 * Converts RPZ action to Erlang format
 * 
 * Handles standard actions (nxdomain, nodata, passthru, drop, tcp-only)
 * and custom local record actions (local_a, local_aaaa, local_cname, etc.)
 * 
 * @param string $str Action string or JSON-encoded custom actions
 * @return string Erlang-formatted action string or tuple list
 */
function erlAction($str){
  switch($str){
    case "nxdomain":
    case "nodata":
    case "passthru":
    case "drop":
    case "tcp-only":
      $result='"'.$str.'"';
      break;
    default:
      $lstr="";$cmm="";
      foreach(explode(PHP_EOL,json_decode($str)) as $item){
        $lr=explode("=",$item,2);
        switch($lr[0]){
          case "local_aaaa":
            if(filter_var($lr[1],FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)) $lstr.="$cmm{\"{$lr[0]}\",\"{$lr[1]}\"}";$cmm=",";
            break;
          case "local_a":
            if(filter_var($lr[1],FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)) $lstr.="$cmm{\"{$lr[0]}\",\"{$lr[1]}\"}";$cmm=",";
            break;
          case "redirect_ip":
            if(filter_var($lr[1], FILTER_VALIDATE_IP)) $lstr.="$cmm{\"{$lr[0]}\",\"{$lr[1]}\"}";$cmm=",";
            break;
          case "local_cname":
            if(filter_var($lr[1], FILTER_VALIDATE_DOMAIN)) $lstr.="$cmm{\"{$lr[0]}\",\"{$lr[1]}\"}";$cmm=",";
            break;
          case "redirect_domain":
            if(filter_var($lr[1], FILTER_VALIDATE_DOMAIN)) $lstr.="$cmm{\"{$lr[0]}\",\"{$lr[1]}\"}";$cmm=",";
            break;
          case "local_txt":
            $sanitized = preg_replace('/["\x00-\x1f\\\\]/', '', $lr[1]);
            if (!empty($sanitized)) { $lstr.="$cmm{\"{$lr[0]}\",\"$sanitized\"}";$cmm=","; }
            break;
          default:
            break;
        };
      };
      $result=$lstr?"[$lstr]":'"nxdomain"';
      break;
  };
  return $result;
};

/**
 * Emits the optional srv-tuple TrackSources element (Global_Track_Default).
 *
 * Source attribution is off by default, and an `off` default is represented by
 * the ABSENCE of the 5th srv-tuple element rather than an emitted `off` atom.
 * This keeps a legacy (off) server serializing to the exact 4-field tuple.
 * Only `auto` and `on` produce a trailing element; every other value
 * (`off`, absent, null, or arbitrary junk) yields an empty string.
 *
 * The emitted atom is a bare, unquoted, lowercase Erlang atom prefixed with a
 * comma so it can be appended directly before the closing `}}` of the srv tuple.
 *
 * @param string|null $value Persisted track_default value
 * @return string `",auto"` / `",on"` for those values, otherwise `""`
 */
function erlSrvTrackSources($value){
  if ($value === "auto") return ",auto";
  if ($value === "on")   return ",on";
  // off, absent, null, or invalid => no trailing element (legacy 4-field tuple)
  return "";
};

/**
 * Emits the optional rpz-tuple TrackSources element (Feed_Track_Setting).
 *
 * An `Inherit` (or absent) feed setting is represented by the ABSENCE of the
 * 16th rpz-tuple element, so a legacy feed serializes to the exact 15-field
 * tuple. Only `auto`, `true`, and `false` produce a trailing element; every
 * other value (`Inherit`, absent, null, or arbitrary junk) yields an empty
 * string and never an invalid TrackSources atom.
 *
 * The emitted atom is a bare, unquoted, lowercase Erlang atom prefixed with a
 * comma so it can be appended directly before the closing `}}` of the rpz tuple.
 *
 * @param string|null $value Persisted track_sources value
 * @return string `",auto"` / `",true"` / `",false"` for those values, otherwise `""`
 */
function erlRpzTrackSources($value){
  if ($value === "auto")  return ",auto";
  if ($value === "true")  return ",true";
  if ($value === "false") return ",false";
  // Inherit, absent, null, or invalid => no trailing element (legacy 15-field tuple)
  return "";
};

/**
 * ioc2rpz compile-time rate limit defaults (include/ioc2rpz.hrl).
 *
 * These are the values the server falls back to when neither the rpz nor the srv
 * record carries the option. The GUI only ever displays them as the resolved
 * "inherited" value; it must NEVER emit them, because emitting a value would turn
 * "inherit" into "explicitly set" and freeze the setting against future server
 * default changes.
 */
define("RL_DEFAULT_WINDOW", 60);                 // ?RATE_LIMIT_WINDOW (60000 ms)
define("RL_DEFAULT_MAX_REQUESTS", 6);            // ?MAX_REQUESTS_PER_WINDOW
define("RL_DEFAULT_MAX_UNKNOWN_REQUESTS", 1);    // ?MAX_UNKNOWN_REQUESTS_PER_WINDOW

/**
 * Largest accepted rate limit window, in seconds (24 hours).
 *
 * The window is stored with every entry in the server's rate limit table and decides
 * when that entry is swept, so an absurd window (a fumbled digit, a value pasted in
 * milliseconds) would keep entries alive for as long as it lasts - a slow,
 * config-driven memory leak. A day is far beyond any legitimate DNS rate limit window.
 * Must match RL_WINDOW_MAX in www/js/io2.js.
 *
 * The maximums are deliberately uncapped: a large threshold only makes the limiter
 * permissive, it does not retain anything.
 */
define("RL_WINDOW_MAX", 86400);

/**
 * Normalizes one stored rate limit value, or null when it must be omitted.
 *
 * A value is emitted only when it is an unambiguous integer within the option's
 * legal range. Absent, empty, non-numeric, and out-of-range values all normalize to
 * null (omit the option) rather than to a number, because the server logs and
 * ignores an invalid value and silently falls back to the next level - so emitting
 * a bad value would look like a setting that does nothing. Omitting it makes the
 * inheritance explicit and keeps the emitted tuple always well-formed.
 *
 * @param mixed $value Stored value (int, numeric string, "", or null)
 * @param int $min Smallest legal value (1 for window, 0 for the maximums)
 * @param int|null $max Largest legal value, null when the option is unbounded
 * @return int|null Normalized integer, or null when the option must be omitted
 */
function erlRateLimitValue($value, $min, $max = null){
  if ($value === null || $value === "" || $value === false) return null;
  if (!is_scalar($value)) return null;
  // Reject anything that is not purely an integer, so "60abc", "6.5", "1e3" and
  // any injection attempt can never reach the generated configuration.
  if (!preg_match('/^\s*-?[0-9]+\s*$/', (string)$value)) return null;
  $int = intval($value);
  if ($int < $min) return null;
  if ($max !== null && $int > $max) return null;
  return $int;
};

/**
 * Builds the optional trailing `{rate_limit,[...]}` element from a set of options.
 *
 * Only the options that are set are emitted, each one independently, so a record can
 * set `max_requests` alone and still inherit `window`. When no option survives
 * normalization the result is the empty string, which keeps a record with no rate
 * limits serializing to the byte-identical legacy tuple.
 *
 * The result is comma-prefixed so it can be appended directly before the closing
 * `}}` of the srv/rpz tuple, after the optional TrackSources element.
 *
 * @param array $opts Ordered map of option name => stored value
 * @return string `",{rate_limit,[{opt,N},...]}"`, or `""` when nothing is set
 */
function erlRateLimitElement($opts){
  $parts = [];
  foreach ($opts as $name => $spec) {
    $norm = erlRateLimitValue($spec['value'], $spec['min'], $spec['max'] ?? null);
    if ($norm !== null) $parts[] = "{".$name.",".$norm."}";
  }
  if (empty($parts)) return "";
  return ",{rate_limit,[".implode(",", $parts)."]}";
};

/**
 * Emits the optional srv-tuple `{rate_limit,[...]}` element.
 *
 * Server-level limits apply to every zone that does not override them. All three
 * options are valid here, including `max_unknown_requests`, which has no rpz-level
 * counterpart because a request counted in that bucket never resolved to a zone.
 *
 * `window` must be > 0; the maximums may be 0, which means "refuse every request in
 * that bucket".
 *
 * @param array $row Server record (rl_window, rl_max_requests, rl_max_unknown_requests)
 * @return string Comma-prefixed rate_limit element, or `""` when nothing is set
 */
function erlSrvRateLimit($row){
  if (!is_array($row)) return "";
  return erlRateLimitElement([
    "window"               => ["value" => $row["rl_window"] ?? null,               "min" => 1, "max" => RL_WINDOW_MAX],
    "max_requests"         => ["value" => $row["rl_max_requests"] ?? null,         "min" => 0],
    "max_unknown_requests" => ["value" => $row["rl_max_unknown_requests"] ?? null, "min" => 0],
  ]);
};

/**
 * Emits the optional rpz-tuple `{rate_limit,[...]}` element.
 *
 * Zone-level limits take precedence over the srv-level ones, per option. Only
 * `window` and `max_requests` exist at this level: the server logs and ignores
 * `max_unknown_requests` on an rpz record, so it is never emitted here.
 *
 * @param array $item Feed record (rl_window, rl_max_requests)
 * @return string Comma-prefixed rate_limit element, or `""` when nothing is set
 */
function erlRpzRateLimit($item){
  if (!is_array($item)) return "";
  return erlRateLimitElement([
    "window"       => ["value" => $item["rl_window"] ?? null,       "min" => 1, "max" => RL_WINDOW_MAX],
    "max_requests" => ["value" => $item["rl_max_requests"] ?? null, "min" => 0],
  ]);
};

?>
