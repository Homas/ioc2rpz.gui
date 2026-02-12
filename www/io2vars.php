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
 * @license MIT
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
$io2ver=2022121101;

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
  $cfg="% ioc2rpz server ${row['name']} config generated by ioc2rpz.gui at ".date("Y-m-d H:i:s")."\n";
  $cfg.="\n% srv record: ns, email, [tkeys], [mgmt]\n";
  $response['filename']=$row['URL']?$row['URL']:"${row['name']}.conf";
  $subres=DB_selectArray($db,"select name from servers_tsig left join tkeys on tkeys.rowid=servers_tsig.tsig_id where servers_tsig.user_id=$USERID and servers_tsig.server_id=$SrvId");
  $subres1=DB_selectArray($db,"select mgmt_ip from mgmt_ips where mgmt_ips.user_id=$USERID and mgmt_ips.server_id=$SrvId;");

  $subres_gr=DB_selectArray($db,"select group_name from servers_tsig_groups left join tkeys_groups on tkeys_groups.rowid=servers_tsig_groups.tsig_group_id where servers_tsig_groups.user_id=$USERID and servers_tsig_groups.server_id=$SrvId");
	if ($subres_gr) $groups=",{groups,[\"".implode('","',array_column($subres_gr,'group_name'))."\"]}"; else $groups="";

  $cfg.="{srv,{\"".erlEscape($row['ns'])."\",\"".str_replace("@",".",erlEscape($row['email']))."\",[\"".implode('","',array_map('erlEscape',array_column($subres,'name')))."\"$groups],[\"".implode('","',array_map('erlEscape',array_column($subres1,'mgmt_ip')))."\"]}}.\\n";

  if ($row['certfile']!="" and $row['keyfile']!="") {
    $cfg.="\n% cert record: certfile, keyfile, cacertfile\n";
    $cfg.="{cert,{\"".erlEscape($row['certfile'])."\",\"".erlEscape($row['keyfile'])."\",\"".erlEscape($row['cacertfile'])."\"}}.\n";
  };

  if ($row['custom_config']!="") {
    $cfg.="\n% Custom configuration\n";
    $cfg.="${row['custom_config']}\n\n";
	};

  //tkeys -- add groups TSIGs from servers and RPZ
  $cfg.="\n% tsig key record: name, alg, key\n";
  $row=DB_selectArray($db,"select rowid,* from tkeys where user_id=$USERID and (rowid in (select tsig_id from servers_tsig where server_id=$SrvId) or rowid in (select tsig_id from tkeys_tsig_groups left join servers_tsig_groups on servers_tsig_groups.tsig_group_id=tkeys_tsig_groups.tsig_group_id where server_id=$SrvId and servers_tsig_groups.user_id=$USERID) or rowid in (select tsig_id from tkeys_tsig_groups left join rpzs_tkeys_groups on rpzs_tkeys_groups.tkey_group_id=tkeys_tsig_groups.tsig_group_id left join rpzs_tkeys on rpzs_tkeys.rpz_id=rpzs_tkeys_groups.rpz_id left join rpzs_servers on rpzs_servers.rpz_id=rpzs_tkeys.rpz_id where server_id=$SrvId and rpzs_tkeys_groups.user_id=$USERID) or rowid in (select tkey_id from rpzs_tkeys left join rpzs on rpzs_tkeys.rpz_id=rpzs.rowid left join rpzs_servers on rpzs_servers.rpz_id=rpzs.rowid where server_id=$SrvId and rpzs.disabled=0));");
  foreach($row as $item){
		$subres_gr=DB_selectArray($db,"select group_name from tkeys_tsig_groups left join tkeys_groups on tkeys_groups.rowid=tkeys_tsig_groups.tsig_group_id where tkeys_tsig_groups.user_id=$USERID and tkeys_tsig_groups.tsig_id=${item['rowid']}");
		if ($subres_gr) $groups=",[\"".implode('","',array_column($subres_gr,'group_name'))."\"]"; else $groups="";
		$cfg.="{key,{\"".erlEscape($item['name'])."\",\"".erlEscape($item['alg'])."\",\"".erlEscape($item['tkey'])."\"$groups}}.\n";
	};

  //whitelists
  $cfg.="\n% whitelist record: name, path, regex\n";
  $row=DB_selectArray($db,"select * from whitelists where user_id=$USERID and rowid in (select whitelist_id from rpzs_whitelists left join rpzs on rpzs_whitelists.rpz_id=rpzs.rowid left join rpzs_servers on rpzs_servers.rpz_id=rpzs.rowid where server_id=$SrvId);");
  foreach($row as $item){$cfg.="{whitelist,{\"${item['name']}\",\"${item['url']}\",".($item['regex']=="none"?"none":'"'.erlEscape($item['regex']).'"').",".($item['userid']==NULL?'""':$item['userid']).",${item['max_ioc']},${item['hotcache_time']},${item['hotcacheixfr_time']},\"${item['ioc_type']}\",".($item['keep_in_cache']?"true":"false")."}}.\n";};

  //sources
  $cfg.="\n% source record: name, axfr_path, ixfr_path, regex\n";
  $row=DB_selectArray($db,"select * from sources where user_id=$USERID and rowid in (select source_id from rpzs_sources left join rpzs on rpzs_sources.rpz_id=rpzs.rowid left join rpzs_servers on rpzs_servers.rpz_id=rpzs.rowid where server_id=$SrvId);");
  foreach($row as $item){$cfg.="{source,{\"${item['name']}\",\"${item['url']}\",\"${item['url_ixfr']}\",".($item['regex']=="none"?"none":'"'.erlEscape($item['regex']).'"').",".($item['userid']==NULL?'""':$item['userid']).",${item['max_ioc']},${item['hotcache_time']},${item['hotcacheixfr_time']},\"${item['ioc_type']}\",".($item['keep_in_cache']?"true":"false")."}}.\n";};

  //rpzs -- add groups {groups,["ip2"]},
  $cfg.="\n% rpz record: name, SOA refresh, SOA update retry, SOA expiration, SOA NXDomain TTL, Cache, Wildcards, Action, [tkeys], ioc_type, AXFR_time, IXFR_time, [sources], [notify], [whitelists]\n";
  $row=DB_selectArray($db,"select rpzs.rowid,* from rpzs left join rpzs_servers on rpzs_servers.rpz_id=rpzs.rowid where server_id=$SrvId and rpzs.user_id=$USERID and rpzs.disabled=0;");

  foreach($row as $item){
    $subres_tkeys=DB_selectArray($db,"select name from rpzs_tkeys left join tkeys on tkeys.rowid=rpzs_tkeys.tkey_id where rpzs_tkeys.user_id=$USERID and rpz_id=${item['rowid']}");
		$subres_gr=DB_selectArray($db,"select group_name from rpzs_tkeys_groups left join tkeys_groups on tkeys_groups.rowid=rpzs_tkeys_groups.tkey_group_id where rpzs_tkeys_groups.user_id=$USERID and rpzs_tkeys_groups.rpz_id=${item['rowid']}");
	 if ($subres_gr) $groups=",{groups,[\"".implode('","',array_column($subres_gr,'group_name'))."\"]}"; else $groups="";

    $subres_srcs=DB_selectArray($db,"select name from rpzs_sources left join sources on sources.rowid=rpzs_sources.source_id where rpzs_sources.user_id=$USERID and rpz_id=${item['rowid']}");
    $subres_wl=DB_selectArray($db,"select name from rpzs_whitelists left join whitelists on whitelists.rowid=rpzs_whitelists.whitelist_id where rpzs_whitelists.user_id=$USERID and rpz_id=${item['rowid']}");
    $subres_notify=DB_selectArray($db,"select notify from rpzs_notify where user_id=$USERID and rpz_id=${item['rowid']}");

    $cfg.="{rpz,{\"${item['name']}\",${item['soa_refresh']},${item['soa_update_retry']},${item['soa_expiration']},${item['soa_nx_ttl']},\"".($item['cache']?"true":"false")."\",\"".($item['wildcard']?"true":"false")."\",".erlAction($item['action']).",[\"".implode('","',array_column($subres_tkeys,'name'))."\"$groups],\"${item['ioc_type']}\",${item['axfr_update']},${item['ixfr_update']},[\"".implode('","',array_column($subres_srcs,'name'))."\"],[".(empty($subres_notify)?"":"\"".implode('","',array_column($subres_notify,'notify'))."\"")."],[".(empty($subres_wl)?"":"\"".implode('","',array_column($subres_wl,'name'))."\"")."]}}.\n";
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
  // Escape backslashes first, then double-quotes for Erlang string literals
  $str = str_replace('\\', '\\\\', $str);
  $str = str_replace('"', '\\"', $str);
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
            if(filter_var($lr[1],FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)) $lstr.="$cmm{\"${lr[0]}\",\"${lr[1]}\"}";$cmm=",";
            break;
          case "local_a":
            if(filter_var($lr[1],FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)) $lstr.="$cmm{\"${lr[0]}\",\"${lr[1]}\"}";$cmm=",";
            break;
          case "redirect_ip":
            if(filter_var($lr[1], FILTER_VALIDATE_IP)) $lstr.="$cmm{\"${lr[0]}\",\"${lr[1]}\"}";$cmm=",";
            break;
          case "local_cname":
            if(filter_var($lr[1], FILTER_VALIDATE_DOMAIN)) $lstr.="$cmm{\"${lr[0]}\",\"${lr[1]}\"}";$cmm=",";
            break;
          case "redirect_domain":
            if(filter_var($lr[1], FILTER_VALIDATE_DOMAIN)) $lstr.="$cmm{\"${lr[0]}\",\"${lr[1]}\"}";$cmm=",";
            break;
          case "local_txt":
            $sanitized = preg_replace('/["\x00-\x1f\\\\]/', '', $lr[1]);
            if (!empty($sanitized)) { $lstr.="$cmm{\"${lr[0]}\",\"$sanitized\"}";$cmm=","; }
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

?>
