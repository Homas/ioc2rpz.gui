<?php
/**
 * ioc2rpz.gui - REST API Data Handler
 * 
 * This file implements the REST API for managing ioc2rpz configuration:
 * 
 * Endpoints:
 * - GET/POST/PUT/DELETE /servers - ioc2rpz server management
 * - GET/POST/PUT/DELETE /tkeys - TSIG key management
 * - GET/POST/PUT/DELETE /tkeys_groups - TSIG key group management
 * - GET/POST/PUT/DELETE /sources - IOC source management
 * - GET/POST/PUT/DELETE /whitelists - Whitelist management
 * - GET/POST/PUT/DELETE /rpzs - RPZ zone management
 * - GET/POST/PUT/DELETE /users - User management (admin only)
 * - GET/POST/PUT/DELETE /rpidns - RpiDNS device management
 * - GET /servercfg - Generate server configuration file
 * - POST /publish_upd - Publish configuration updates
 * 
 * All state-changing requests (POST, PUT, DELETE, PATCH) require CSRF token validation.
 * 
 * @package ioc2rpz.gui
 * @author Vadim Pavlov
 * @copyright 2018-2026
 * @license Apache-2.0
 */

require_once 'io2auth.php';
require_once 'io2fun.php';

// Set default Content-Type for JSON responses
header('Content-Type: application/json');

$REQUEST=getRequest();

// CSRF validation for state-changing requests (POST, PUT, DELETE, PATCH)
if (in_array($REQUEST['method'], ['POST', 'PUT', 'DELETE', 'PATCH'])) {
    $csrfToken = isset($REQUEST['csrf_token']) ? $REQUEST['csrf_token'] : '';
    if (!validateCsrfToken($csrfToken)) {
        echo '{"status":"failed","reason":"Invalid CSRF token"}';
        exit;
    }
}

// Normalise the requested row id(s) into a comma separated list of integers for use in
// "... where rowid in ($ReqRowId)".
//
// Always initialised: it was previously assigned only inside the `!empty` branch, so a
// request without a rowid left it undefined and every consumer emitted a PHP warning
// before interpolating an empty string.
//
// The input may arrive as a bare id, a JSON array of ids, or - from a JSON request body -
// an actual integer. The previous version passed the raw value to ctype_digit(), which
// interprets an int as a character code, and then handed a possibly-null json_decode()
// result to array_filter(), which is a TypeError on PHP 8. Both paths are handled here,
// and every id is cast with intval() so the result can never carry SQL syntax.
$ReqRowId='';
if (isset($REQUEST['rowid']) && $REQUEST['rowid'] !== '' && $REQUEST['rowid'] !== null) {
  $reqIds = $REQUEST['rowid'];
  if (!is_array($reqIds)) {
    $reqIds = ctype_digit((string)$reqIds) ? [$reqIds] : json_decode((string)$reqIds, true);
  }
  $rowIds = [];
  foreach ((is_array($reqIds) ? $reqIds : []) as $id) {
    if (is_numeric($id)) $rowIds[] = intval($id);
  };
  $ReqRowId = implode(",", $rowIds);
};

// API rate limiting — enforces per-session request throttling
$rateCheck = checkApiRateLimit($REQUEST['method']);
if (!$rateCheck['allowed']) {
    header('Retry-After: ' . $rateCheck['retry_after']);
    http_response_code(429);
    echo '{"status":"failed","reason":"Rate limit exceeded. Try again in '.$rateCheck['retry_after'].' second(s)."}';
    exit;
}

// Every DELETE handler interpolates $ReqRowId (or intval($REQUEST['rowid'])) into a
// where clause. Without a usable id the statement used to degrade into "rowid in ()",
// which SQLite accepts and silently matches nothing - a delete that reports success
// while doing nothing. Reject the request instead of guessing.
if ($REQUEST['method'] === 'DELETE' && $ReqRowId === '') {
    http_response_code(400);
    echo '{"status":"failed","reason":"Missing or invalid rowid"}';
    exit;
}

$db=DB_open();

#var_dump($REQUEST);

switch ($REQUEST['method'].' '.$REQUEST["req"]):
    case "GET servers":
      $rarray=[];
      $result=DB_select($db,"select rowid,* from servers");
      while ($row = DB_fetchArray($result)) {
        unset($row['user_id']);
#        $subres=DB_selectArray($db,"select tkeys.rowid,tkeys.name from servers_tsig left join tkeys on tkeys.rowid=servers_tsig.tsig_id where servers_tsig.user_id=$USERID and servers_tsig.server_id=${row['rowid']};");
        $subres=DB_selectArray($db,"select tkeys.rowid,tkeys.name from servers_tsig left join tkeys on tkeys.rowid=servers_tsig.tsig_id where servers_tsig.server_id={$row['rowid']} union select \"gr_\"||tkeys_groups.rowid, tkeys_groups.group_name||\" (group)\" as name from servers_tsig_groups left join tkeys_groups on tkeys_groups.rowid=servers_tsig_groups.tsig_group_id where  servers_tsig_groups.server_id={$row['rowid']};");
        $row['tkeys']=$subres;
        $subres=DB_selectArray($db,"select mgmt_ips.rowid,mgmt_ips.mgmt_ip from mgmt_ips where mgmt_ips.server_id={$row['rowid']};");
        $row['mgmt_ips']=$subres;
        $rarray[]=$row;
      };
      $response=json_encode($rarray);
      break;

    case "POST servers":
      // Validate server fields
      $v = validateServerFields($REQUEST);
      if (!$v['valid']) { $response='{"status":"failed","reason":"'.addslashes($v['error']).'"}'; break; }
      $tkeys=DB_selectArray($db,"select rowid from tkeys where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tSrvTKeys']))).")");
      $tkeys_groups=DB_selectArray($db,"select rowid from tkeys_groups where rowid in (".implode(",",getGroupsId(json_decode($REQUEST['tSrvTKeys']))).")");
      // Validated tSrvTrackDefault (validateServerFields guarantees off/auto/on); absent/empty defaults to 'off'
      $trackDefault = (isset($REQUEST['tSrvTrackDefault']) && $REQUEST['tSrvTrackDefault'] !== '') ? $REQUEST['tSrvTrackDefault'] : 'off';
      // Validated DNS rate limits (validateServerFields guarantees integer/in-range or empty).
      // Empty/absent persists as NULL, not 0, so "inherit" stays distinct from an explicit value.
      $rlWindow = DB_intOrNull($REQUEST['tSrvRLWindow'] ?? null);
      $rlMaxRequests = DB_intOrNull($REQUEST['tSrvRLMaxRequests'] ?? null);
      $rlMaxUnknownRequests = DB_intOrNull($REQUEST['tSrvRLMaxUnknownRequests'] ?? null);
      // Column order: user_id, name, ip, pub_ip, ns, email, mgmt, disabled, stype, URL,
      // cfg_updated, publish_upd, certfile, keyfile, cacertfile, custom_config,
      // track_default, rl_window, rl_max_requests, rl_max_unknown_requests.
      //
      // `mgmt` is an integer flag and must go through DB_boolval. It previously used
      // DB_escape without surrounding quotes, which left an unquoted, attacker-controlled
      // fragment in the VALUES list: DB_escape only doubles single quotes, so a payload
      // containing none of them escaped the statement entirely. DB_execute runs through
      // SQLite3::exec(), which accepts multiple statements, so that was a full SQL
      // injection reachable by any authenticated user (validateServerFields never
      // inspected tSrvMGMT). DB_boolval collapses the value to 0 or 1 and can never
      // carry syntax.
      //
      // `cfg_updated` is set to 1, not derived from tSrvMGMT as it was before: a new
      // server has no configuration on the DNS node yet, so it must be picked up by the
      // next publish (POST publish_upd selects on cfg_updated=1). PUT servers likewise
      // forces it to 1 on every edit.
      $sql="insert into servers values($USERID,'".DB_escape($db,$REQUEST['tSrvName'])."','".DB_escape($db,$REQUEST['tSrvIP'])."','".DB_escape($db,$REQUEST['tSrvPubIP']).
      "','".DB_escape($db,$REQUEST['tSrvNS'])."','".DB_escape($db,$REQUEST['tSrvEmail'])."',".DB_boolval($REQUEST['tSrvMGMT']).",".DB_boolval($REQUEST['tSrvDisabled']).",".intval($REQUEST['tSrvSType']).",'".DB_escape($db,$REQUEST['tSrvURL'])."',1,0,'".DB_escape($db,$REQUEST['tCertFile'])."','".DB_escape($db,$REQUEST['tKeyFile'])."','".DB_escape($db,$REQUEST['tCACertFile'])."','".DB_escape($db,$REQUEST['tCustomConfig'])."','".DB_escape($db,$trackDefault)."',$rlWindow,$rlMaxRequests,$rlMaxUnknownRequests)"; #certfile, keyfile, cacertfile, custom_config, track_default, rate limits (validated values; NULL = inherit)
      if (DB_execute($db,$sql)) {
        //safest way to get id?
        $srvid=DB_selectArray($db,"select max(rowid) as rowid from servers where name='".DB_escape($db,$REQUEST['tSrvName'])."'")[0]['rowid'];
        $sql='';
        foreach($tkeys as $tkey){
          $sql.="insert into servers_tsig values($srvid,$USERID,{$tkey['rowid']});\n";
        };
        foreach($tkeys_groups as $tkey_group){
          $sql.="insert into servers_tsig_groups values($srvid,$USERID,{$tkey_group['rowid']});\n";
        };
        foreach(json_decode($REQUEST['tSrvMGMTIP']) as $ip){
          //TODO add uniq only
          $sql.="insert into mgmt_ips values($srvid,$USERID,'".DB_escape($db,$ip)."');\n";
        };
        if (DB_execute($db,$sql)) {
          $response='{"status":"ok"}';
        }else $response='{"status":"failed", "reason":"Database operation failed"}';
      }else $response='{"status":"failed", "reason":"Database operation failed"}';
      break;

    case "PUT servers":
      // Validate server fields
      $v = validateServerFields($REQUEST);
      if (!$v['valid']) { $response='{"status":"failed","reason":"'.addslashes($v['error']).'"}'; break; }
      // Validated tSrvTrackDefault (validateServerFields guarantees off/auto/on); absent/empty defaults to 'off'
      $trackDefault = (isset($REQUEST['tSrvTrackDefault']) && $REQUEST['tSrvTrackDefault'] !== '') ? $REQUEST['tSrvTrackDefault'] : 'off';
      // Validated DNS rate limits; empty/absent persists as NULL (inherit), not 0.
      $rlWindow = DB_intOrNull($REQUEST['tSrvRLWindow'] ?? null);
      $rlMaxRequests = DB_intOrNull($REQUEST['tSrvRLMaxRequests'] ?? null);
      $rlMaxUnknownRequests = DB_intOrNull($REQUEST['tSrvRLMaxUnknownRequests'] ?? null);
      $srvid=intval($REQUEST['tSrvId']);
      $tkeys_new=DB_selectArray($db,"select rowid from tkeys where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tSrvTKeys']))).")");
      $tkeys_old=DB_selectArray($db,"select rowid,tsig_id from servers_tsig where server_id=$srvid");
      $tkeys_groups_new=DB_selectArray($db,"select rowid from tkeys_groups where rowid in (".implode(",",getGroupsId(json_decode($REQUEST['tSrvTKeys']))).")");
      $tkeys_groups_old=DB_selectArray($db,"select rowid,tsig_group_id from servers_tsig_groups where server_id=$srvid");
      // Association diffs. These used array_search($fk, $rows) where $rows is a list of
      // associative rows, which never matches, so the "already linked" branch was dead and
      // every association was deleted and re-inserted on each save. The assignment
      // `if ($k=array_search(...))` was additionally false for a match at index 0.
      // diffAssociations resolves both against the target row ids.
      $sql='';
      $tkeysDiff=diffAssociations($tkeys_old,'tsig_id',$tkeys_new);
      foreach($tkeysDiff['delete'] as $rowid){
        $sql.="delete from servers_tsig where rowid=$rowid;\n";
      };
      foreach($tkeysDiff['insert'] as $tsigid){
        $sql.="insert into servers_tsig values($srvid,$USERID,$tsigid);\n";
      };

      $tkeysGroupsDiff=diffAssociations($tkeys_groups_old,'tsig_group_id',$tkeys_groups_new);
      foreach($tkeysGroupsDiff['delete'] as $rowid){
        $sql.="delete from servers_tsig_groups where rowid=$rowid;\n";
      };
      foreach($tkeysGroupsDiff['insert'] as $groupid){
        $sql.="insert into servers_tsig_groups values($srvid,$USERID,$groupid);\n";
      };

      $mgmtip_old=DB_selectArray($db,"select rowid, mgmt_ip from mgmt_ips where server_id=$srvid");
      $mgmtipDiff=diffValueAssociations($mgmtip_old,'mgmt_ip',json_decode($REQUEST['tSrvMGMTIP']));
      foreach($mgmtipDiff['delete'] as $rowid){
        $sql.="delete from mgmt_ips where rowid=$rowid;\n";
      };
      foreach($mgmtipDiff['insert'] as $ip){
        $sql.="insert into mgmt_ips values($srvid,$USERID,'".DB_escape($db,$ip)."');\n";
      };
      $sql.="update servers set name='".DB_escape($db,$REQUEST['tSrvName'])."', ip='".DB_escape($db,$REQUEST['tSrvIP'])."', pub_ip='".DB_escape($db,$REQUEST['tSrvPubIP']).
      "', ns='".DB_escape($db,$REQUEST['tSrvNS'])."', email='".DB_escape($db,$REQUEST['tSrvEmail'])."', mgmt=".DB_boolval($REQUEST['tSrvMGMT']).", disabled=".DB_boolval($REQUEST['tSrvDisabled'])." ,stype=".intval($REQUEST['tSrvSType']).", URL='".DB_escape($db,$REQUEST['tSrvURL'])."', cfg_updated=".DB_boolval(1).", certfile='".DB_escape($db,$REQUEST['tCertFile'])."', keyfile='".DB_escape($db,$REQUEST['tKeyFile'])."', cacertfile='".DB_escape($db,$REQUEST['tCACertFile'])."', custom_config='".DB_escape($db,$REQUEST['tCustomConfig'])."', track_default='".DB_escape($db,$trackDefault)."', rl_window=$rlWindow, rl_max_requests=$rlMaxRequests, rl_max_unknown_requests=$rlMaxUnknownRequests where rowid=$srvid";

      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "reason":"Database operation failed"}';
      break;

    //Select rows from tables
    case "GET sources":
    case "GET whitelists":
    case "GET tkeys_groups":
      $rarray=[];
      $result=DB_select($db,"select rowid,* from {$REQUEST['req']}");
      while ($row = DB_fetchArray($result)) {
        unset($row['user_id']);
        $rarray[]=$row;
      };
      $response=json_encode($rarray);
      break;
    case "POST tkeys_groups":
      // Validate group name
      $v = validateGroupName($REQUEST['tKeyGName'] ?? '');
      if (!$v['valid']) { $response='{"status":"failed","reason":"'.addslashes($v['error']).'"}'; break; }
      $sql="insert into tkeys_groups values($USERID,'".DB_escape($db,$REQUEST['tKeyGName'])."')";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "reason":"Database operation failed"}';
      break;
    case "PUT tkeys_groups":
      // Validate group name
      $v = validateGroupName($REQUEST['tKeyGName'] ?? '');
      if (!$v['valid']) { $response='{"status":"failed","reason":"'.addslashes($v['error']).'"}'; break; }
      $sql="update tkeys_groups set group_name='".DB_escape($db,$REQUEST['tKeyGName'])."' where rowid=".intval($REQUEST['tKeyGId']);
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "reason":"Database operation failed"}';
      break;
    case "GET tkeys":
      $rarray=[];
      $result=DB_select($db,"select rowid,* from {$REQUEST['req']}");
      while ($row = DB_fetchArray($result)) {
        unset($row['user_id']);
        $subres=DB_selectArray($db,"select tkeys_groups.rowid, tkeys_groups.group_name from tkeys_tsig_groups left join tkeys_groups on tkeys_groups.rowid=tkeys_tsig_groups.tsig_group_id where tkeys_tsig_groups.tsig_id={$row['rowid']};");
        $row['tkey_groups']=$subres;
        $rarray[]=$row;
      };
      $response=json_encode($rarray);
      break;

    case "GET tkeys_groups_list":
      $response=json_encode(DB_selectArray($db,"select rowid as value, group_name as text from tkeys_groups"));
      break;
    case "GET tkeys_mgmt":
#      $response=json_encode(DB_selectArray($db,"select rowid as value, name as text from tkeys where user_id=$USERID and mgmt=1;"));
      $response=json_encode(DB_selectArray($db,"select value,text from (select rowid as value, name as text, \"2\" as tbl from tkeys where mgmt=1 union select \"gr_\"||rowid as value, group_name||\" (group)\" as text, \"1\" as tbl from tkeys_groups ) order by tbl, text;"));
      break;

    //add TSIG
    case "POST tkeys":
      // Validate TSIG key fields
      $v = validateTkeyFields($REQUEST);
      if (!$v['valid']) { $response='{"status":"failed","reason":"'.addslashes($v['error']).'"}'; break; }
      $tkeys_groups=DB_selectArray($db,"select rowid from tkeys_groups where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tTKeysGroups']))).")");
      $sql="insert into tkeys values($USERID,'".DB_escape($db,$REQUEST['tKeyName'])."','".DB_escape($db,$REQUEST['tKeyAlg'])."','".DB_escape($db,$REQUEST['tKey'])."',".DB_boolval($REQUEST['tKeyMGMT']).");";
      if (DB_execute($db,$sql)) {
          $tkeyid=DB_selectArray($db,"select max(rowid) as rowid from tkeys where name='".DB_escape($db,$REQUEST['tKeyName'])."'")[0]['rowid'];
          $sql='';
    			foreach($tkeys_groups as $tkey_group){$sql.="insert into tkeys_tsig_groups values($tkeyid,$USERID,{$tkey_group['rowid']});\n";};       // <!--- should be keyid
          DB_execute($db,$sql);
          $response='{"status":"ok"}';
        } else $response='{"status":"failed", "reason":"Database operation failed"}';




			//TODO add tsig_groups
      break;
    //modify TSIG
    case "PUT tkeys":
      // Validate TSIG key fields
      $v = validateTkeyFields($REQUEST);
      if (!$v['valid']) { $response='{"status":"failed","reason":"'.addslashes($v['error']).'"}'; break; }
      $sql_update="update servers set cfg_updated=1 where rowid in (select distinct server_id from rpzs_servers left join rpzs_{$REQUEST['req']} on rpzs_{$REQUEST['req']}.rpz_id=rpzs_servers.rpz_id where  rpzs_{$REQUEST['req']}.".rtrim($REQUEST['req'],"s")."_id=".intval($REQUEST['tKeyId'])." UNION select server_id from servers_tsig where tsig_id=".intval($REQUEST['tKeyId']).");\n";
      $sql="update tkeys set name='".DB_escape($db,$REQUEST['tKeyName'])."', alg='".DB_escape($db,$REQUEST['tKeyAlg'])."', tkey='".DB_escape($db,$REQUEST['tKey'])."', mgmt=".DB_boolval($REQUEST['tKeyMGMT'])." where rowid=".intval($REQUEST['tKeyId']).";\n$sql_update";
      $tkeys_groups_new=DB_selectArray($db,"select rowid from tkeys_groups where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tTKeysGroups']))).")");
      $tkeys_groups_old=DB_selectArray($db,"select rowid,tsig_group_id from tkeys_tsig_groups where tsig_id=".intval($REQUEST['tKeyId']));
      $tkeysGroupsDiff=diffAssociations($tkeys_groups_old,'tsig_group_id',$tkeys_groups_new);
      foreach($tkeysGroupsDiff['delete'] as $rowid){
        $sql.="delete from tkeys_tsig_groups where rowid=$rowid;\n";
      };
      foreach($tkeysGroupsDiff['insert'] as $groupid){
        $sql.="insert into tkeys_tsig_groups values(".intval($REQUEST['tKeyId']).",$USERID,$groupid);\n";
      };

      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "reason":"Database operation failed"}';
			//TODO add tsig_groups
      break;

    //add whitelist
    case "POST whitelists":
      // Validate source/whitelist fields
      $v = validateSourceFields($REQUEST);
      if (!$v['valid']) { $response='{"status":"failed","reason":"'.addslashes($v['error']).'"}'; break; }
      $sql="insert into whitelists values($USERID,'".DB_escape($db,$REQUEST['tSrcName'])."','".DB_escape($db,$REQUEST['tSrcURL'])."','".DB_escape($db,$REQUEST['tSrcREGEX'])."',NULL,".intval($REQUEST['tSrcMaxIOC']).",".intval($REQUEST['tSrcHotCacheAXFR']).",".intval($REQUEST['tSrcHotCacheIXFR']).",'".($REQUEST['tSrcIoCType']=='fqdn'?'fqdn':($REQUEST['tSrcIoCType']=='ip'?'ip':'mixed'))."',".intval($REQUEST['tSrcKeepInCache']).")";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "reason":"Database operation failed"}';
      break;
    //modify whitelist
    case "PUT whitelists":
      // Validate source/whitelist fields
      $v = validateSourceFields($REQUEST);
      if (!$v['valid']) { $response='{"status":"failed","reason":"'.addslashes($v['error']).'"}'; break; }
      $sql_update="update servers set cfg_updated=1 where rowid in (select distinct server_id from rpzs_servers left join rpzs_{$REQUEST['req']} on rpzs_{$REQUEST['req']}.rpz_id=rpzs_servers.rpz_id where rpzs_{$REQUEST['req']}.".rtrim($REQUEST['req'],"s")."_id=".intval($REQUEST['tSrcId']).");\n";
      $sql="update whitelists set name='".DB_escape($db,$REQUEST['tSrcName'])."', url='".DB_escape($db,$REQUEST['tSrcURL'])."', regex='".DB_escape($db,$REQUEST['tSrcREGEX'])."', max_ioc=".intval($REQUEST['tSrcMaxIOC']).", hotcache_time=".intval($REQUEST['tSrcHotCacheAXFR']).", hotcacheixfr_time=".intval($REQUEST['tSrcHotCacheIXFR']).",ioc_type='".($REQUEST['tSrcIoCType']=='fqdn'?'fqdn':($REQUEST['tSrcIoCType']=='ip'?'ip':'mixed'))."',keep_in_cache=".intval($REQUEST['tSrcKeepInCache'])." where rowid=".intval($REQUEST['tSrcId']).";\n$sql_update";

      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "reason":"Database operation failed"}';
      break;

    //add sources
    case "POST sources":
      // Validate source fields
      $v = validateSourceFields($REQUEST);
      if (!$v['valid']) { $response='{"status":"failed","reason":"'.addslashes($v['error']).'"}'; break; }
      $sql="insert into sources values($USERID,'".DB_escape($db,$REQUEST['tSrcName'])."','".DB_escape($db,$REQUEST['tSrcURL'])."','".DB_escape($db,$REQUEST['tSrcURLIXFR'])."','".DB_escape($db,$REQUEST['tSrcREGEX'])."',NULL,".intval($REQUEST['tSrcMaxIOC']).",".intval($REQUEST['tSrcHotCacheAXFR']).",".intval($REQUEST['tSrcHotCacheIXFR']).",'".($REQUEST['tSrcIoCType']=='fqdn'?'fqdn':($REQUEST['tSrcIoCType']=='ip'?'ip':'mixed'))."',".intval($REQUEST['tSrcKeepInCache']).")";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "reason":"Database operation failed"}';
      break;
    //modify sources
    case "PUT sources":
      // Validate source fields
      $v = validateSourceFields($REQUEST);
      if (!$v['valid']) { $response='{"status":"failed","reason":"'.addslashes($v['error']).'"}'; break; }

      $sql_update="update servers set cfg_updated=1 where rowid in (select distinct server_id from rpzs_servers left join rpzs_{$REQUEST['req']} on rpzs_{$REQUEST['req']}.rpz_id=rpzs_servers.rpz_id where  rpzs_{$REQUEST['req']}.".rtrim($REQUEST['req'],"s")."_id=".intval($REQUEST['tSrcId']).");\n";

      $sql="update sources set name='".DB_escape($db,$REQUEST['tSrcName'])."', url='".DB_escape($db,$REQUEST['tSrcURL'])."', url_ixfr='".DB_escape($db,$REQUEST['tSrcURLIXFR'])."', regex='".DB_escape($db,$REQUEST['tSrcREGEX'])."', max_ioc=".intval($REQUEST['tSrcMaxIOC']).", hotcache_time=".intval($REQUEST['tSrcHotCacheAXFR']).", hotcacheixfr_time=".intval($REQUEST['tSrcHotCacheIXFR']).", ioc_type='".($REQUEST['tSrcIoCType']=='fqdn'?'fqdn':($REQUEST['tSrcIoCType']=='ip'?'ip':'mixed'))."',keep_in_cache=".intval($REQUEST['tSrcKeepInCache'])." where rowid=".intval($REQUEST['tSrcId']).";\n$sql_update";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "reason":"Database operation failed"}';
      break;

    //Delete rows
    case "DELETE sources":
    case "DELETE whitelists":
    case "DELETE tkeys":
    case "DELETE tkeys_groups":
      $sql="delete from {$REQUEST['req']} where rowid in ($ReqRowId)";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "reason":"Database operation failed"}';
      break;
    case "DELETE servers":
      $sql="delete from mgmt_ips where server_id in ($ReqRowId);\n";
      $sql.="delete from servers_tsig where server_id in ($ReqRowId);\n";
      // servers_tsig_groups and rpzs_servers were left behind, orphaning rows that keep
      // referencing a server that no longer exists.
      $sql.="delete from servers_tsig_groups where server_id in ($ReqRowId);\n";
      $sql.="delete from rpzs_servers where server_id in ($ReqRowId);\n";
      $sql.="delete from servers where rowid in ($ReqRowId);\n";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "reason":"Database operation failed"}';
      break;

    case "GET rpzs":
#{rpz,{"dns-bh.ioc2rpz",86400,3600,2592000,7200,"true","true","nxdomain",["pub_demokey_1","at_demokey_1","priv_key_1"],"mixed",604800,86400,["dns-bh"],[],["whitelist_1"]}}.
#      $response='[{"name":"dns-bh.ioc2rpz", "servers":["server-1"], "soa_refresh":86400, "soa_update_retry":3600, "soa_expiration":2592000, "soa_nx_ttl":7200, "cache":"true", "wildcard":"true", "action":"nxdomain", "tkeys":["pub_demokey_1","at_demokey_1","priv_key_1"], "ioc_type":"mixed", "axfr_update":604800, "ixfr_update":86400, "sources":["dns-bh","dns-bh1"], "notify":[], "whitelists":["whitelist_1"]}]';

      $result=DB_select($db,"select rowid,* from rpzs".(!empty($REQUEST['rowid'])?"  where rowid in ($ReqRowId)":"").";");
      $rarray=[];
      while ($row = DB_fetchArray($result)) {
        unset($row['user_id']);

        //actioncustom nx/nod/pass/drop/tcp/loc
        if (in_array($row['action'],["nxdomain","nodata","passthru","drop","tcp-only"])) $row['actioncustom']="";else{$row['actioncustom']=$row['action'];$row['action']="local";};

#        $subres=DB_selectArray($db,"select tkeys.rowid,tkeys.name, tkeys.alg, tkeys.tkey from rpzs_tkeys left join tkeys on tkeys.rowid=rpzs_tkeys.tkey_id where tkeys.mgmt=0 and rpzs_tkeys.user_id=$USERID and rpzs_tkeys.rpz_id=${row['rowid']};");
#        $row['tkeys']=$subres;

        $subres=DB_selectArray($db,"select tkeys.rowid,tkeys.name, tkeys.alg, tkeys.tkey from rpzs_tkeys left join tkeys on tkeys.rowid=rpzs_tkeys.tkey_id where tkeys.mgmt=0 and rpzs_tkeys.rpz_id={$row['rowid']} union select \"gr_\"||tkeys_groups.rowid, tkeys_groups.group_name||\" (group)\" as name, \"\" as alg, \"\" as tkey from rpzs_tkeys_groups left join tkeys_groups on tkeys_groups.rowid=rpzs_tkeys_groups.tkey_group_id where rpzs_tkeys_groups.rpz_id={$row['rowid']};");
        $row['tkeys']=$subres;

        $subres=DB_selectArray($db,"select tkeys_groups.rowid,tkeys_groups.group_name from rpzs_tkeys_groups left join tkeys_groups on tkeys_groups.rowid=rpzs_tkeys_groups.tkey_group_id where rpzs_tkeys_groups.rpz_id={$row['rowid']};");
        $row['tkeys_groups']=$subres;

        $subres=DB_selectArray($db,"select servers.rowid,servers.name, pub_ip from rpzs_servers left join servers on servers.rowid=rpzs_servers.server_id where rpzs_servers.rpz_id={$row['rowid']};");
        $row['servers']=$subres;

        $subres=DB_selectArray($db,"select whitelists.rowid,whitelists.name from rpzs_whitelists left join whitelists on whitelists.rowid=rpzs_whitelists.whitelist_id where rpzs_whitelists.rpz_id={$row['rowid']};");
        $row['whitelists']=$subres;

        $subres=DB_selectArray($db,"select sources.rowid,sources.name from rpzs_sources left join sources on sources.rowid=rpzs_sources.source_id where rpzs_sources.rpz_id={$row['rowid']};");
        $row['sources']=$subres;

        $subres=DB_selectArray($db,"select rpzs_notify.rowid,rpzs_notify.notify from rpzs_notify where rpzs_notify.rpz_id={$row['rowid']};");
        $row['notify']=$subres;

        $rarray[]=$row;
      };
      $response=json_encode($rarray);
      break;
    case "POST rpzs":
      //actioncustom nx/nod/pass/drop/tcp/loc
      // Validate RPZ fields
      $v = validateRpzFields($REQUEST);
      if (!$v['valid']) { $response='{"status":"failed","reason":"'.addslashes($v['error']).'"}'; break; }

      // Validated tRPZTrackSources (validateRpzFields guarantees Inherit/auto/true/false); absent/empty defaults to 'Inherit'
      $trackSources = (isset($REQUEST['tRPZTrackSources']) && $REQUEST['tRPZTrackSources'] !== '') ? $REQUEST['tRPZTrackSources'] : 'Inherit';
      // Validated per-feed DNS rate limits (validateRpzFields guarantees integer/in-range
      // or empty). Empty/absent persists as NULL = inherit from the server level.
      $rlWindow = DB_intOrNull($REQUEST['tRPZRLWindow'] ?? null);
      $rlMaxRequests = DB_intOrNull($REQUEST['tRPZRLMaxRequests'] ?? null);
      $tkeys=DB_selectArray($db,"select rowid from tkeys where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZTKeys']))).")");
      $tkeys_groups=DB_selectArray($db,"select rowid from tkeys_groups where rowid in (".implode(",",getGroupsId(json_decode($REQUEST['tRPZTKeys']))).")");
      $servers=DB_selectArray($db,"select rowid from servers where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZSrvs']))).")");
      $sources=DB_selectArray($db,"select rowid from sources where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZSrc']))).")");
      $whlists=DB_selectArray($db,"select rowid from whitelists where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZWL']))).")");

      if (in_array($REQUEST['tRPZAction'],["nxdomain","nodata","passthru","drop","tcp-only"])) $action=$REQUEST['tRPZAction'];else $action=erlChLRecords($REQUEST['tRPZActionCustom']);

      $sql="insert into rpzs values($USERID,'".DB_escape($db,$REQUEST['tRPZName'])."',".intval($REQUEST['tRPZSOA_Refresh']).",".intval($REQUEST['tRPZSOA_UpdRetry']).",".
            intval($REQUEST['tRPZSOA_Exp']).",".intval($REQUEST['tRPZSOA_NXTTL']).",".DB_boolval($REQUEST['tRPZCache']).",".DB_boolval($REQUEST['tRPZWildcard']).",'".
            DB_escape($db,$action)."','".DB_escape($db,$REQUEST['tRPZIOCType'])."',".intval($REQUEST['tRPZAXFR']).",".intval($REQUEST['tRPZIXFR']).",".
            DB_boolval($REQUEST['tRPZDisabled']).",'".DB_escape($db,$trackSources)."',$rlWindow,$rlMaxRequests);"; #trailing track_sources, rate limits (validated values; NULL = inherit)
      if (DB_execute($db,$sql)) {
        //safest way to get id?
        $rpzid=DB_selectArray($db,"select max(rowid) as rowid from rpzs where name='".DB_escape($db,$REQUEST['tRPZName'])."'")[0]['rowid'];
        $sql='';
        foreach($tkeys as $tkey){
          $sql.="insert into rpzs_tkeys values($rpzid,$USERID,{$tkey['rowid']});\n";
        };
        foreach($tkeys_groups as $tkey_group){
          $sql.="insert into rpzs_tkeys_groups values($rpzid,$USERID,{$tkey_group['rowid']});\n";
        };
        foreach($servers as $tkey){
          $sql.="insert into rpzs_servers values($rpzid,$USERID,{$tkey['rowid']});\n update servers set cfg_updated=1 where rowid={$tkey['rowid']};\n";
        };
        foreach($sources as $tkey){
          $sql.="insert into rpzs_sources values($rpzid,$USERID,{$tkey['rowid']});\n";
        };
        foreach($whlists as $tkey){
          $sql.="insert into rpzs_whitelists values($rpzid,$USERID,{$tkey['rowid']});\n";
        };
        foreach(array_unique(json_decode($REQUEST['tRPZNotify'])) as $ip){
          //TODO add uniq only
          $sql.="insert into rpzs_notify values($rpzid,$USERID,'".DB_escape($db,$ip)."');\n";
        };
        if (DB_execute($db,$sql)) {
          $response='{"status":"ok"}';
        }else $response='{"status":"failed", "reason":"Database operation failed"}';
      }else $response='{"status":"failed", "reason":"Database operation failed"}';
      break;
    case "PUT rpzs":
      // Validate RPZ fields
      $v = validateRpzFields($REQUEST);
      if (!$v['valid']) { $response='{"status":"failed","reason":"'.addslashes($v['error']).'"}'; break; }
      // Validated tRPZTrackSources (validateRpzFields guarantees Inherit/auto/true/false); absent/empty defaults to 'Inherit'
      $trackSources = (isset($REQUEST['tRPZTrackSources']) && $REQUEST['tRPZTrackSources'] !== '') ? $REQUEST['tRPZTrackSources'] : 'Inherit';
      // Validated per-feed DNS rate limits; empty/absent persists as NULL (inherit), not 0.
      $rlWindow = DB_intOrNull($REQUEST['tRPZRLWindow'] ?? null);
      $rlMaxRequests = DB_intOrNull($REQUEST['tRPZRLMaxRequests'] ?? null);
      $rpzid=intval($REQUEST['tRPZId']);
      $tkeys_new=DB_selectArray($db,"select rowid from tkeys where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZTKeys']))).")");
      $tkeys_old=DB_selectArray($db,"select rowid,tkey_id from rpzs_tkeys where rpz_id=$rpzid");
      $tkeys_groups_new=DB_selectArray($db,"select rowid from tkeys_groups where rowid in (".implode(",",getGroupsId(json_decode($REQUEST['tRPZTKeys']))).")");
      $tkeys_groups_old=DB_selectArray($db,"select rowid,tkey_group_id from rpzs_tkeys_groups where rpz_id=$rpzid");
      $servers_new=DB_selectArray($db,"select rowid from servers where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZSrvs']))).")");
      $servers_old=DB_selectArray($db,"select rowid,server_id from rpzs_servers where rpz_id=$rpzid");
      $sources_new=DB_selectArray($db,"select rowid from sources where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZSrc']))).")");
      $sources_old=DB_selectArray($db,"select rowid,source_id from rpzs_sources where rpz_id=$rpzid");
      $whlists_new=DB_selectArray($db,"select rowid from whitelists where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZWL']))).")");
      $whlists_old=DB_selectArray($db,"select rowid,whitelist_id from rpzs_whitelists where rpz_id=$rpzid");

      if (in_array($REQUEST['tRPZAction'],["nxdomain","nodata","passthru","drop","tcp-only"])) $action=$REQUEST['tRPZAction'];else $action=erlChLRecords($REQUEST['tRPZActionCustom']);

      // Association diffs. Each of these previously diffed against $tkeys_new regardless of
      // which relation was being processed, and compared a foreign key against a list of
      // associative rows, which never matches. The combined effect was that every
      // association was dropped and re-created on every save.
      $sql='';
      $tkeysDiff=diffAssociations($tkeys_old,'tkey_id',$tkeys_new);
      foreach($tkeysDiff['delete'] as $rowid){$sql.="delete from rpzs_tkeys where rowid=$rowid;\n";};
      foreach($tkeysDiff['insert'] as $tkeyid){$sql.="insert into rpzs_tkeys values($rpzid,$USERID,$tkeyid);\n";};

      $tkeysGroupsDiff=diffAssociations($tkeys_groups_old,'tkey_group_id',$tkeys_groups_new);
      foreach($tkeysGroupsDiff['delete'] as $rowid){$sql.="delete from rpzs_tkeys_groups where rowid=$rowid;\n";};
      foreach($tkeysGroupsDiff['insert'] as $groupid){$sql.="insert into rpzs_tkeys_groups values($rpzid,$USERID,$groupid);\n";};

      $serversDiff=diffAssociations($servers_old,'server_id',$servers_new);
      foreach($serversDiff['delete'] as $rowid){$sql.="delete from rpzs_servers where rowid=$rowid;\n";};
      foreach($serversDiff['insert'] as $serverid){$sql.="insert into rpzs_servers values($rpzid,$USERID,$serverid);\n";};

      // Servers needing a republished configuration: the union of those that served this
      // zone before the change and those that serve it after. Removed servers need a config
      // without the zone, added servers need one with it, and retained servers need one
      // reflecting the zone attributes updated below.
      //
      // This previously collected $item['rowid'] from $servers_old, which is the rowid of
      // the rpzs_servers association row rather than of the server, so the update marked
      // unrelated servers (or none) as needing a publish.
      $chCfgSrv=array_values(array_unique(array_merge(
        array_map('intval',array_column($servers_old,'server_id')),
        array_map('intval',array_column($servers_new,'rowid'))
      )));
      if ($chCfgSrv) $sql.="update servers set cfg_updated=1 where rowid in (".implode(",", $chCfgSrv).");\n";

      $sourcesDiff=diffAssociations($sources_old,'source_id',$sources_new);
      foreach($sourcesDiff['delete'] as $rowid){$sql.="delete from rpzs_sources where rowid=$rowid;\n";};
      foreach($sourcesDiff['insert'] as $sourceid){$sql.="insert into rpzs_sources values($rpzid,$USERID,$sourceid);\n";};

      $whlistsDiff=diffAssociations($whlists_old,'whitelist_id',$whlists_new);
      foreach($whlistsDiff['delete'] as $rowid){$sql.="delete from rpzs_whitelists where rowid=$rowid;\n";};
      foreach($whlistsDiff['insert'] as $whlistid){$sql.="insert into rpzs_whitelists values($rpzid,$USERID,$whlistid);\n";};

      $ip_old=DB_selectArray($db,"select rowid, notify from rpzs_notify where rpz_id=$rpzid");
      $notifyDiff=diffValueAssociations($ip_old,'notify',json_decode($REQUEST['tRPZNotify']));
      foreach($notifyDiff['delete'] as $rowid){
        $sql.="delete from rpzs_notify where rowid=$rowid;\n";
      };
      foreach($notifyDiff['insert'] as $ip){
        $sql.="insert into rpzs_notify values($rpzid,$USERID,'".DB_escape($db,$ip)."');\n";
      };

      $sql.="update rpzs set name='".DB_escape($db,$REQUEST['tRPZName'])."', soa_refresh=".intval($REQUEST['tRPZSOA_Refresh']).", soa_update_retry=".
            intval($REQUEST['tRPZSOA_UpdRetry']).",soa_expiration=".intval($REQUEST['tRPZSOA_Exp']).", soa_nx_ttl=".intval($REQUEST['tRPZSOA_NXTTL']).", cache=".
            DB_boolval($REQUEST['tRPZCache']).", wildcard=".DB_boolval($REQUEST['tRPZWildcard']).","."action='".DB_escape($db,$action)."',ioc_type='".
            DB_escape($db,$REQUEST['tRPZIOCType'])."',axfr_update=".intval($REQUEST['tRPZAXFR']).",ixfr_update=".intval($REQUEST['tRPZIXFR']).",disabled=".
            DB_boolval($REQUEST['tRPZDisabled']).",track_sources='".DB_escape($db,$trackSources)."',rl_window=$rlWindow,rl_max_requests=$rlMaxRequests where rowid=$rpzid";

      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "reason":"Database operation failed"}';

      break;
    case "DELETE rpzs":
      // Resolve the affected servers before the association rows are removed. The previous
      // version reused $ReqRowId - a list of rpz ids - as the server rowid filter, so it
      // flagged whichever servers happened to share those ids and missed the ones actually
      // serving the deleted zones.
      $affectedSrv=array_map('intval',array_column(
        DB_selectArray($db,"select distinct server_id from rpzs_servers where rpz_id in ($ReqRowId)"),
        'server_id'
      ));
      $sql="delete from rpzs_notify where rpz_id in ($ReqRowId);\n";
      $sql.="delete from rpzs_tkeys where rpz_id in ($ReqRowId);\n";
      $sql.="delete from rpzs_tkeys_groups where rpz_id in ($ReqRowId);\n";
      $sql.="delete from rpzs_servers where rpz_id in ($ReqRowId);\n";
      if ($affectedSrv) $sql.="update servers set cfg_updated=1 where rowid in (".implode(",", $affectedSrv).");\n";
      $sql.="delete from rpzs_whitelists where rpz_id in ($ReqRowId);\n";
      $sql.="delete from rpzs_sources where rpz_id in ($ReqRowId);\n";
      $sql.="delete from rpzs where rowid in ($ReqRowId);\n";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "reason":"Database operation failed"}';
      break;

    // rl_window / rl_max_requests are exposed so the feed editor can resolve and show
    // the inherited rate limit (zone -> server -> built-in default) without a save.
    case "GET rpz_servers":
      $response=json_encode(DB_selectArray($db,"select rowid as value, name as text, rl_window, rl_max_requests from servers"));
      break;

    case "GET rpz_tkeys":
#      $response=json_encode(DB_selectArray($db,"select rowid as value, name as text from tkeys where user_id=$USERID and mgmt!=1"));
      $response=json_encode(DB_selectArray($db,"select value,text from (select rowid as value, name as text, \"2\" as tbl from tkeys where mgmt!=1 union select \"gr_\"||rowid as value, group_name||\" (group)\" as text, \"1\" as tbl from tkeys_groups) order by tbl, text;"));
      break;

    case "GET rpz_tkeys_groups":
      $response=json_encode(DB_selectArray($db,"select rowid as value, group_name as text from tkeys_groups"));
      break;

    case "GET rpz_sources":
      $response=json_encode(DB_selectArray($db,"select rowid as value, name as text from sources;"));
      break;

    case "GET rpz_whitelists":
      $response=json_encode(DB_selectArray($db,"select rowid as value, name as text from whitelists;"));
      break;

    case "GET rpz_lists":
      $response=json_encode(DB_selectArray($db,"select rowid as value, name as text from rpzs where disabled=0;"));
      break;

    case "GET servercfg": //generate ioc2rpz configuration and pass it to the client
      $cfg=genConfig($db,$USERID,intval($REQUEST['rowid']));
      header("Content-Type: text/plain");
      header('Content-Disposition: attachment; filename="'.$cfg['filename'].'"');
      $response=$cfg['cfg'];
      break;

    case "POST publish_upd":
      //save ioc2rpz configuration and reconfigure service
      //support local file via local script. Here just set a relevant field in DB.
      //support S3. Upload file to S3 and send reconfugure signal
      $SrvId = $REQUEST['SrvId'] ?? '';
      if ($SrvId === '') { $response='{"status":"failed","reason":"Missing SrvId"}'; break; }
      $sql="update servers set publish_upd=1, cfg_updated=0 where ".($SrvId == 'all'?" disabled=0  and cfg_updated=1 and mgmt=1":" rowid=".intval($SrvId));
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "reason":"Database operation failed"}';

      break;

      //Users
    case "GET users":
      if ($_SESSION['perm'] == 1){
#        $response=json_encode(DB_selectArray($db,"select rowid, name, perm, loginattempts,  strftime('%Y-%m-%dT%H:%M:%SZ',min(lastlogin), 'unixepoch', 'utc') as lastlogin, strftime('%Y-%m-%dT%H:%M:%SZ',min(lastfailedlogin), 'unixepoch', 'utc') as lastfailedlogin from users;"));
        $response=json_encode(DB_selectArray($db,"select rowid, name, perm from users;"));
      }else{
        $response='{"status":"failed", "reason":"not supported"}';
      };
      break;
    case "POST users":
      if ($_SESSION['perm'] == 1){
        // Validate username
        if (strlen($REQUEST['name']) < 3 || !preg_match('/^[a-zA-Z0-9.\-_]+$/', $REQUEST['name'])) {
          $response='{"status":"failed","reason":"Username must be at least 3 characters and contain only letters, numbers, dots, hyphens, and underscores"}';
        // Validate password
        } elseif (!validatePassword($REQUEST['pwd'])) {
          $response='{"status":"failed","reason":"Password must be either: 8+ chars with uppercase, lowercase, number, and special char OR 16+ chars"}';
        } else {
          $hashedPassword = password_hash($REQUEST['pwd'], PASSWORD_BCRYPT);
          $sql="insert into users(name, password, salt, perm, loginattempts, lastlogin, lastfailedlogin) values('".DB_escape($db,$REQUEST['name'])."','".DB_escape($db,$hashedPassword)."','',".intval($REQUEST['perm']).",0,0,0)";
          if (DB_execute($db,$sql)) $response='{"status":"ok","description":"User created"}';
            else $response='{"status":"failed","description":"Unexpected error!"}';
        }
      }else{
        $response='{"status":"failed", "reason":"not supported"}';
      };
      break;
    case "PUT users":
      if ($_SESSION['perm'] == 1){
        // Validate username
        if (strlen($REQUEST['name']) < 3 || !preg_match('/^[a-zA-Z0-9.\-_]+$/', $REQUEST['name'])) {
          $response='{"status":"failed","reason":"Username must be at least 3 characters and contain only letters, numbers, dots, hyphens, and underscores"}';
        // Validate password if provided
        } elseif (!empty($REQUEST['pwd']) && !validatePassword($REQUEST['pwd'])) {
          $response='{"status":"failed","reason":"Password must be either: 8+ chars with uppercase, lowercase, number, and special char OR 16+ chars"}';
        } else {
          if (!empty($REQUEST['pwd'])) {
            $hashedPassword = password_hash($REQUEST['pwd'], PASSWORD_BCRYPT);
            $sql="update users set name='".DB_escape($db,$REQUEST['name'])."', password='".DB_escape($db,$hashedPassword)."', salt='', perm=".intval($REQUEST['perm']).", loginattempts=0 where rowid=".intval($REQUEST['rowid']);
          } else {
            $sql="update users set name='".DB_escape($db,$REQUEST['name'])."', perm=".intval($REQUEST['perm'])." where rowid=".intval($REQUEST['rowid']);
          }
          if (DB_execute($db,$sql)) $response='{"status":"ok","description":"User updated"}';
            else $response='{"status":"failed","description":"Unexpected error!"}';
        }
      }else{
        $response='{"status":"failed", "reason":"not supported"}';
      };
      break;
    // "PATCH user_passsword" removed: the case name was misspelled, the admin branch was
    // empty, and nothing in the frontend called it, so it only ever returned an
    // uninitialised response. Self-service password change is tracked in TODO.md; when it
    // lands it needs its own handler that authenticates the *current* user rather than
    // gating on perm == 1. Unknown endpoints now fall through to the default case.
    case "DELETE users":
      if ($_SESSION['perm'] == 1){
        $sql="delete from users where rowid=".intval($REQUEST['rowid']);
        if (DB_execute($db,$sql)) $response='{"status":"ok","description":"User updated!"}';
          else $response='{"status":"failed","description":"Unexpected error!"}';
      }else{
        $response='{"status":"failed", "reason":"not supported"}';
      };
      break;

      //RpiDNS
    case "GET rpidns":

# a812af12-0551-44b1-be98-e69c24488245
/*

insert into rpidns(user_id, name, rpidns_uuid, commentary, configuration) values (1,'rpidns','a812af12-0551-44b1-be98-e69c24488245','comment','{"dns":"bind","rpz":[{"feed":"bogons-ipv4.ioc2rpz","action":"cname"},{"feed":"dga-360.ioc2rpz","action":"cname"},{"feed":"dns-bh.ioc2rpz","action":"cname"},{"feed":"doh.ioc2rpz","action":"cname"},{"feed":"local.ioc2rpz","action":"cname"},{"feed":"notracking-dead.ioc2rpz","action":"cname"},{"feed":"notracking.ioc2rpz","action":"cname"},{"feed":"phishtank.ioc2rpz","action":"cname"},{"feed":"whitelist-ip.ioc2rpz","action":"passthru log no"},{"feed":"whitelist.ioc2rpz","action":"passthru log no"}],"name":"pi-dev","model":"pi4-4g","logging":"local","updconf":"1","dns_type":"primary","redirect":"default","dns_ipnet":"","logging_host":"","redirect_cname":""}');

{"dns":"bind","rpz":[{"feed":"bogons-ipv4.ioc2rpz","action":"cname"},{"feed":"dga-360.ioc2rpz","action":"cname"},{"feed":"dns-bh.ioc2rpz","action":"cname"},{"feed":"doh.ioc2rpz","action":"cname"},{"feed":"local.ioc2rpz","action":"cname"},{"feed":"notracking-dead.ioc2rpz","action":"cname"},{"feed":"notracking.ioc2rpz","action":"cname"},{"feed":"phishtank.ioc2rpz","action":"cname"},{"feed":"whitelist-ip.ioc2rpz","action":"passthru log no"},{"feed":"whitelist.ioc2rpz","action":"passthru log no"}],"name":"pi-dev","model":"pi4-4g","logging":"local","updconf":"1","dns_type":"primary","redirect":"default","dns_ipnet":"","logging_host":"","redirect_cname":""}

  {
    "dns": "bind",
    "rpz": [
        {"feed": "bogons-ipv4.ioc2rpz", "action": "cname"},
        {"feed": "dga-360.ioc2rpz","action": "cname"},
        {"feed": "dns-bh.ioc2rpz","action": "cname"},
        {"feed": "doh.ioc2rpz","action": "cname"},
        {"feed": "local.ioc2rpz","action": "cname"},
        {"feed": "notracking-dead.ioc2rpz","action": "cname"},
        {"feed": "notracking.ioc2rpz","action": "cname"},
        {"feed": "phishtank.ioc2rpz","action": "cname"},
        {"feed": "whitelist-ip.ioc2rpz","action": "passthru log no"},
        {"feed": "whitelist.ioc2rpz","action": "passthru log no"}
    ],
    "name": "pi-dev",
    "model": "pi4-4g",
    "logging": "local",
    "updconf": "1",
    "dns_type": "primary",
    "redirect": "default",
    "dns_ipnet": "",
    "logging_host": "",
    "redirect_cname": ""
}
*/
      $sql="SELECT rowid as id, name, commentary as comment, rpidns_uuid, json_extract(configuration,'$.model') as model, json_extract(configuration,'$.dns') as dns, json_extract(configuration,'$.updconf') as updconf, json_extract(configuration,'$.rpz') as rpz, json_extract(configuration,'$.redirect') as redirect, json_extract(configuration,'$.redirect_cname') as redirect_cname, json_extract(configuration,'$.logging') as logging, json_extract(configuration,'$.logging_host') as logging_host, json_extract(configuration,'$.dns_type') as dns_type, json_extract(configuration,'$.dns_ipnet') as dns_ipnet FROM rpidns order by rowid;"; //json_each(configuration.rpz)
#      $response='{"status":"success", "data":'.json_encode(DB_selectArray($db,$sql)).'}';
      $servers=DB_selectArray($db,$sql);
      $rarray = [];
      if ($servers) {
        foreach ($servers as $rpidns){
          $rarray[] = [
            'id' => intval($rpidns['id']),
            'rpidns_uuid' => $rpidns['rpidns_uuid'],
            'name' => $rpidns['name'],
            'comment' => $rpidns['comment'],
            'model' => $rpidns['model'],
            'dns' => $rpidns['dns'],
            'updconf' => $rpidns['updconf'] ? true : false,
            'status' => 'configured',
            'rpz' => json_decode($rpidns['rpz'], true),
            'redirect' => $rpidns['redirect'],
            'redirect_cname' => $rpidns['redirect_cname'],
            'logging' => $rpidns['logging'],
            'logging_host' => $rpidns['logging_host'],
            'dns_type' => $rpidns['dns_type'],
            'dns_ipnet' => $rpidns['dns_ipnet']
          ];
        };
      };
      $response=json_encode(['status' => 'success', 'data' => $rarray]);
      break;

    case "POST rpidns":
			// Validate rpz JSON structure
			$rpzData = $REQUEST['rpz'];
			if (!is_array($rpzData)) {
				$rpzData = json_decode($REQUEST['rpz'], true);
			}
			$rpzValidation = validateRpzJson($rpzData);
			if (!$rpzValidation['valid']) {
				$response = '[{"status":"error","description":"Invalid rpz configuration: ' . DB_escape($db, $rpzValidation['error']) . '"}]';
				break;
			}

			// Validate rpidns config fields to prevent shell/config injection in generated scripts
			$configValidation = validateRpidnsConfig($REQUEST);
			if (!$configValidation['valid']) {
				$response = '[{"status":"error","description":"' . DB_escape($db, $configValidation['error']) . '"}]';
				break;
			}

			$config='{"name":"'.DB_escape($db,$REQUEST['name']).'", "model":"'.DB_escape($db,$REQUEST['model']).'", "dns":"'.DB_escape($db,$REQUEST['dns']).'", "updconf":"'.DB_escape($db,$REQUEST['updconf']).'", "rpz":'.json_encode($rpzData).', "redirect":"'.DB_escape($db,$REQUEST['redirect']).'", "redirect_cname":"'.DB_escape($db,$REQUEST['redirect_cname']).'", "logging":"'.DB_escape($db,$REQUEST['logging']).'", "logging_host":"'.DB_escape($db,$REQUEST['logging_host']).'", "dns_type":"'.DB_escape($db,$REQUEST['dns_type']).'", "dns_ipnet":"'.DB_escape($db,$REQUEST['dns_ipnet']).'"}';
			$sql="insert into rpidns(user_id,name,commentary,configuration,rpidns_uuid) values($USERID,'".DB_escape($db,$REQUEST['name'])."','".DB_escape($db,$REQUEST['comment'])."','$config','".uuid()."')";
			if (DB_execute($db,$sql)) $response='[{"status":"success","description":"success"}]'; else $response='[{"status":"error","description":"Error"}]';
      break;

    case "PUT rpidns":
			// Validate rpz JSON structure
			$rpzData = $REQUEST['rpz'];
			if (!is_array($rpzData)) {
				$rpzData = json_decode($REQUEST['rpz'], true);
			}
			$rpzValidation = validateRpzJson($rpzData);
			if (!$rpzValidation['valid']) {
				$response = '[{"status":"error","description":"Invalid rpz configuration: ' . DB_escape($db, $rpzValidation['error']) . '"}]';
				break;
			}

			// Validate rpidns config fields to prevent shell/config injection in generated scripts
			$configValidation = validateRpidnsConfig($REQUEST);
			if (!$configValidation['valid']) {
				$response = '[{"status":"error","description":"' . DB_escape($db, $configValidation['error']) . '"}]';
				break;
			}

			$config='{"name":"'.DB_escape($db,$REQUEST['name']).'", "model":"'.DB_escape($db,$REQUEST['model']).'", "dns":"'.DB_escape($db,$REQUEST['dns']).'", "updconf":"'.DB_escape($db,$REQUEST['updconf']).'", "rpz":'.json_encode($rpzData).', "redirect":"'.DB_escape($db,$REQUEST['redirect']).'", "redirect_cname":"'.DB_escape($db,$REQUEST['redirect_cname']).'", "logging":"'.DB_escape($db,$REQUEST['logging']).'", "logging_host":"'.DB_escape($db,$REQUEST['logging_host']).'", "dns_type":"'.DB_escape($db,$REQUEST['dns_type']).'", "dns_ipnet":"'.DB_escape($db,$REQUEST['dns_ipnet']).'"}';
			$sql="update rpidns set user_id=$USERID, name='".DB_escape($db,$REQUEST['name'])."', commentary='".DB_escape($db,$REQUEST['comment'])."',configuration='$config' where rowid=".intval($REQUEST['id']);
			if (DB_execute($db,$sql)) $response='[{"status":"success","description":"success"}]'; else $response='[{"status":"error","description":"Error"}]';
      break;
    case "DELETE rpidns":

			$sql="delete from rpidns where rowid=".intval($REQUEST['id']);
			if (DB_execute($db,$sql)) $response='[{"status":"success","description":"success"}]'; else $response='[{"status":"error","description":"Error"}]';

      break;

    case "GET ioc_lookup":
      // Mgmt_Proxy: forward a single-indicator lookup to the selected server's
      // management REST API. The TSIG key name/secret are used only inside
      // CURLOPT_USERPWD and are NEVER placed into any response, error, or log.

      // --- Server-side input validation (do NOT contact the mgmt interface on failure) ---
      $ioc = isset($REQUEST['ioc']) ? $REQUEST['ioc'] : '';
      if (!validateIocLength($ioc)) {
        // Empty or over-length indicator (length outside [1,2048]): reject
        // without contacting the management interface
        $response='{"status":"failed","error":"validation","reason":"ioc"}';
        break;
      }
      $srvId = (isset($REQUEST['server']) && ctype_digit((string)$REQUEST['server'])) ? intval($REQUEST['server']) : 0;
      if ($srvId <= 0) {
        $response='{"status":"failed","error":"validation","reason":"server"}';
        break;
      }

      // --- Load the selected server's mgmt address and its management TSIG key (name + secret) ---
      // Scope by user_id so a session can only look up its own servers and use
      // its own management credentials (no cross-user access via a guessed rowid).
      $srvRow=DB_selectArray($db,"select ip from servers where rowid=$srvId and user_id=$USERID;");
      if (empty($srvRow) || empty($srvRow[0]['ip'])) {
        // Unknown server (or not owned by this user), or no management address configured
        $response='{"status":"failed","error":"validation","reason":"server"}';
        break;
      }
      $mgmtAddr=$srvRow[0]['ip'];
      $keyRow=DB_selectArray($db,"select tkeys.name as tname, tkeys.tkey as tsecret from servers_tsig left join tkeys on tkeys.rowid=servers_tsig.tsig_id where servers_tsig.server_id=$srvId and servers_tsig.user_id=$USERID and tkeys.mgmt=1 limit 1;");
      if (empty($keyRow) || empty($keyRow[0]['tname'])) {
        // No management TSIG credentials for this server
        $response='{"status":"failed","error":"validation","reason":"server"}';
        break;
      }
      $keyName=$keyRow[0]['tname'];
      $keySecret=$keyRow[0]['tsecret'];

      // --- Issue GET https://<addr>:<port>/api/v1/ioc/<urlencoded ioc>?tkey= (empty tkey) ---
      // URL construction and outcome classification live in io2fun.php as pure,
      // testable helpers (buildIocLookupUrl / classifyIocLookupResult). The TSIG
      // key name/secret are used only inside CURLOPT_USERPWD; classification is
      // credential-free by construction.
      // NOTE: the management key authenticates the request (basic auth, below);
      // it is deliberately NOT passed as the `tkey` query parameter. `tkey`
      // scopes which feeds the server returns, and a management key is not a
      // per-zone transfer key -- passing it would filter out every feed. An
      // empty tkey returns all feeds the indicator appears in (see
      // buildIocLookupUrl()).
      $url=buildIocLookupUrl($mgmtAddr, rest_mgmt_port, $ioc);
      $curl=curl_init($url);
      curl_setopt($curl, CURLOPT_HTTPGET, true);
      curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
      curl_setopt($curl, CURLOPT_USERPWD, $keyName.":".$keySecret);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, io2mgmt_verifyssl);
      curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, io2mgmt_verifyssl);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_TIMEOUT, 30);
      $res=curl_exec($curl);
      $errno=curl_errno($curl);
      $httpcode=intval(curl_getinfo($curl, CURLINFO_HTTP_CODE));
      curl_close($curl);
      // Drop credentials from memory as soon as the request is done
      unset($keyName, $keySecret);

      // --- Classify the outcome (credentials are never included in any branch) ---
      $response=classifyIocLookupResult($errno, $httpcode, $res);
      break;

    default:
      $response='{"status":"failed", "reason":"not supported"}';
endswitch;

echo $response;

DB_close($db);

?>
