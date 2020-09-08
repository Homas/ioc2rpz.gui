<?php
#(c) Vadim Pavlov 2018-2020
#ioc2rpz.gui API

require_once 'io2auth.php';
require_once 'io2fun.php';


$REQUEST=getRequest();
if (!empty($REQUEST['rowid'])) $ReqRowId=ctype_digit($REQUEST['rowid'])?$REQUEST['rowid']:implode(",",array_filter(json_decode($REQUEST['rowid'],true),'is_numeric'));

$db=DB_open();

#var_dump($REQUEST);

switch ($REQUEST['method'].' '.$REQUEST["req"]):
    case "GET servers":
      $rarray=[];
      $result=DB_select($db,"select rowid,* from servers");
      while ($row = DB_fetchArray($result)) {
        unset($row['user_id']);
#        $subres=DB_selectArray($db,"select tkeys.rowid,tkeys.name from servers_tsig left join tkeys on tkeys.rowid=servers_tsig.tsig_id where servers_tsig.user_id=$USERID and servers_tsig.server_id=${row['rowid']};");
        $subres=DB_selectArray($db,"select tkeys.rowid,tkeys.name from servers_tsig left join tkeys on tkeys.rowid=servers_tsig.tsig_id where servers_tsig.server_id=${row['rowid']} union select \"gr_\"||tkeys_groups.rowid, tkeys_groups.group_name||\" (group)\" as name from servers_tsig_groups left join tkeys_groups on tkeys_groups.rowid=servers_tsig_groups.tsig_group_id where  servers_tsig_groups.server_id=${row['rowid']};");
        $row['tkeys']=$subres;
        $subres=DB_selectArray($db,"select mgmt_ips.rowid,mgmt_ips.mgmt_ip from mgmt_ips where mgmt_ips.server_id=${row['rowid']};");
        $row['mgmt_ips']=$subres;
        $rarray[]=$row;
      };
      $response=json_encode($rarray);
      break;

    case "POST servers":
      $tkeys=DB_selectArray($db,"select rowid from tkeys where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tSrvTKeys']))).")");
      $tkeys_groups=DB_selectArray($db,"select rowid from tkeys_groups where rowid in (".implode(",",getGroupsId(json_decode($REQUEST['tSrvTKeys']))).")");
      $sql="insert into servers values($USERID,'".DB_escape($db,$REQUEST['tSrvName'])."','".DB_escape($db,$REQUEST['tSrvIP'])."','".DB_escape($db,$REQUEST['tSrvPubIP']).
      "','".DB_escape($db,$REQUEST['tSrvNS'])."','".DB_escape($db,$REQUEST['tSrvEmail'])."',".DB_escape($db,$REQUEST['tSrvMGMT']).",".DB_boolval($REQUEST['tSrvDisabled']).",".intval($REQUEST['tSrvSType']).",'".DB_escape($db,$REQUEST['tSrvURL'])."',".DB_boolval($REQUEST['tSrvMGMT']).",0,'".DB_escape($db,$REQUEST['tCertFile'])."','".DB_escape($db,$REQUEST['tKeyFile'])."','".DB_escape($db,$REQUEST['tCACertFile'])."','".DB_escape($db,$REQUEST['tCustomConfig'])."')"; #certfile, keyfile, cacertfile, custom_config
      if (DB_execute($db,$sql)) {
        //safest way to get id?
        $srvid=DB_selectArray($db,"select max(rowid) as rowid from servers where name='".DB_escape($db,$REQUEST['tSrvName'])."'")[0]['rowid'];
        $sql='';
        foreach($tkeys as $tkey){
          $sql.="insert into servers_tsig values($srvid,$USERID,${tkey['rowid']});\n";
        };
        foreach($tkeys_groups as $tkey_group){
          $sql.="insert into servers_tsig_groups values($srvid,$USERID,${tkey_group['rowid']});\n";
        };
        foreach(json_decode($REQUEST['tSrvMGMTIP']) as $ip){
          //TODO add uniq only
          $sql.="insert into mgmt_ips values($srvid,$USERID,'".DB_escape($db,$ip)."');\n";
        };
        if (DB_execute($db,$sql)) {
          $response='{"status":"ok"}';
        }else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      }else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;

    case "PUT servers":
      $srvid=intval($REQUEST['tSrvId']);
      $tkeys_new=DB_selectArray($db,"select rowid from tkeys where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tSrvTKeys']))).")");
      $tkeys_old=DB_selectArray($db,"select rowid,tsig_id from servers_tsig where server_id=$srvid");
      $tkeys_groups_new=DB_selectArray($db,"select rowid from tkeys_groups where rowid in (".implode(",",getGroupsId(json_decode($REQUEST['tSrvTKeys']))).")");
      $tkeys_groups_old=DB_selectArray($db,"select rowid,tsig_group_id from servers_tsig_groups where server_id=$srvid");
      $sql='';
      foreach($tkeys_old as $tkey){
        if ($k=array_search($tkey['tsig_id'],$tkeys_new)) unset($tkeys_new[$k]); else $sql.="delete from servers_tsig where rowid=${tkey['rowid']};\n";
      };
      //$tkeys=DB_selectArray($db,"select rowid from tkeys where user_id=$USERID and rowid in (".implode(",",$tkeys_new).")");
      foreach($tkeys_new as $tkey){
        $sql.="insert into servers_tsig values($srvid,$USERID,${tkey['rowid']});\n";
      };

      foreach($tkeys_groups_old as $tkey_group){
        if ($k=array_search($tkey_group['tsig_group_id'],$tkeys_groups_new)) unset($tkeys_groups_new[$k]); else $sql.="delete from servers_tsig_groups where rowid=${tkey_group['rowid']};\n";
      };
			foreach($tkeys_groups_new as $tkey_group){
				$sql.="insert into servers_tsig_groups values($srvid,$USERID,${tkey_group['rowid']});\n";
			};

      $mgmtip_new=array_unique(json_decode($REQUEST['tSrvMGMTIP']));
      $mgmtip_old=DB_selectArray($db,"select rowid, mgmt_ip from mgmt_ips where server_id=$srvid");
      foreach($mgmtip_old as $ip){
        if ($k=array_search($ip['mgmt_ip'],$mgmtip_new)) unset($mgmtip_new[$k]); else $sql.="delete from mgmt_ips where rowid=${ip['rowid']};\n";
      };
      foreach($mgmtip_new as $ip){
        $sql.="insert into mgmt_ips values($srvid,$USERID,'".DB_escape($db,$ip)."');\n";
      };
      $sql.="update servers set name='".DB_escape($db,$REQUEST['tSrvName'])."', ip='".DB_escape($db,$REQUEST['tSrvIP'])."', pub_ip='".DB_escape($db,$REQUEST['tSrvPubIP']).
      "', ns='".DB_escape($db,$REQUEST['tSrvNS'])."', email='".DB_escape($db,$REQUEST['tSrvEmail'])."', mgmt=".DB_boolval($REQUEST['tSrvMGMT']).", disabled=".DB_boolval($REQUEST['tSrvDisabled'])." ,stype=".intval($REQUEST['tSrvSType']).", URL='".DB_escape($db,$REQUEST['tSrvURL'])."', cfg_updated=".DB_boolval(1).", certfile='".DB_escape($db,$REQUEST['tCertFile'])."', keyfile='".DB_escape($db,$REQUEST['tKeyFile'])."', cacertfile='".DB_escape($db,$REQUEST['tCACertFile'])."', custom_config='".DB_escape($db,$REQUEST['tCustomConfig'])."' where rowid=$srvid";

      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;

    //Select rows from tables
    case "GET sources":
    case "GET whitelists":
    case "GET tkeys_groups":
      $rarray=[];
      $result=DB_select($db,"select rowid,* from ${REQUEST['req']}");
      while ($row = DB_fetchArray($result)) {
        unset($row['user_id']);
        $rarray[]=$row;
      };
      $response=json_encode($rarray);
      break;
    case "POST tkeys_groups":
      $sql="insert into tkeys_groups values($USERID,'".DB_escape($db,$REQUEST['tKeyGName'])."')";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
    case "PUT tkeys_groups":
      $sql="update tkeys_groups set group_name='".DB_escape($db,$REQUEST['tKeyGName'])."' where rowid=".DB_escape($db,$REQUEST['tKeyGId']);
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
    case "GET tkeys":
      $rarray=[];
      $result=DB_select($db,"select rowid,* from ${REQUEST['req']}");
      while ($row = DB_fetchArray($result)) {
        unset($row['user_id']);
        $subres=DB_selectArray($db,"select tkeys_groups.rowid, tkeys_groups.group_name from tkeys_tsig_groups left join tkeys_groups on tkeys_groups.rowid=tkeys_tsig_groups.tsig_group_id where tkeys_tsig_groups.tsig_id=${row['rowid']};");
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
      $tkeys_groups=DB_selectArray($db,"select rowid from tkeys_groups where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tTKeysGroups']))).")");
      $sql="insert into tkeys values($USERID,'".DB_escape($db,$REQUEST['tKeyName'])."','".DB_escape($db,$REQUEST['tKeyAlg'])."','".DB_escape($db,$REQUEST['tKey'])."',".DB_boolval($REQUEST['tKeyMGMT']).");";
      if (DB_execute($db,$sql)) {
          $tkeyid=DB_selectArray($db,"select max(rowid) as rowid from tkeys where name='".DB_escape($db,$REQUEST['tKeyName'])."'")[0]['rowid'];
          $sql='';
    			foreach($tkeys_groups as $tkey_group){$sql.="insert into tkeys_tsig_groups values($tkeyid,$USERID,${tkey_group['rowid']});\n";};       // <!--- should be keyid
          DB_execute($db,$sql);
          $response='{"status":"ok"}';
        } else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL




			//TODO add tsig_groups
      break;
    //modify TSIG
    case "PUT tkeys":
      $sql_update="update servers set cfg_updated=1 where rowid in (select distinct server_id from rpzs_servers left join rpzs_${REQUEST['req']} on rpzs_${REQUEST['req']}.rpz_id=rpzs_servers.rpz_id where  rpzs_${REQUEST['req']}.".rtrim($REQUEST['req'],"s")."_id=".intval($REQUEST['tKeyId'])." UNION select server_id from servers_tsig where tsig_id=".intval($REQUEST['tKeyId']).");\n";
      $sql="update tkeys set name='".DB_escape($db,$REQUEST['tKeyName'])."', alg='".DB_escape($db,$REQUEST['tKeyAlg'])."', tkey='".DB_escape($db,$REQUEST['tKey'])."', mgmt=".DB_boolval($REQUEST['tKeyMGMT'])." where rowid='${REQUEST['tKeyId']}';\n$sql_update";
      $tkeys_groups_new=DB_selectArray($db,"select rowid from tkeys_groups where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tTKeysGroups']))).")");
      $tkeys_groups_old=DB_selectArray($db,"select rowid,tsig_group_id from tkeys_tsig_groups where tsig_id=".intval($REQUEST['tKeyId']));
      foreach($tkeys_groups_old as $tkey_group){
        if ($k=array_search($tkey_group['tsig_group_id'],$tkeys_groups_new)) unset($tkeys_groups_new[$k]); else $sql.="delete from tkeys_tsig_groups where rowid=${tkey_group['rowid']};\n";
      };
			foreach($tkeys_groups_new as $tkey_group){
				$sql.="insert into tkeys_tsig_groups values(".intval($REQUEST['tKeyId']).",$USERID,${tkey_group['rowid']});\n";
			};

      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
			//TODO add tsig_groups
      break;

    //add whitelist
    case "POST whitelists":
      $sql="insert into whitelists values($USERID,'".DB_escape($db,$REQUEST['tSrcName'])."','".DB_escape($db,$REQUEST['tSrcURL'])."','".DB_escape($db,$REQUEST['tSrcREGEX'])."',NULL,".intval($REQUEST['tSrcMaxIOC']).",".intval($REQUEST['tSrcHotCacheAXFR']).",".intval($REQUEST['tSrcHotCacheIXFR']).")";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
    //modify whitelist
    case "PUT whitelists":
      $sql_update="update servers set cfg_updated=1 where rowid in (select distinct server_id from rpzs_servers left join rpzs_${REQUEST['req']} on rpzs_${REQUEST['req']}.rpz_id=rpzs_servers.rpz_id where rpzs_${REQUEST['req']}.".rtrim($REQUEST['req'],"s")."_id=".intval($REQUEST['tSrcId']).");\n";
      $sql="update whitelists set name='".DB_escape($db,$REQUEST['tSrcName'])."', url='".DB_escape($db,$REQUEST['tSrcURL'])."', regex='".DB_escape($db,$REQUEST['tSrcREGEX'])."', max_ioc=".intval($REQUEST['tSrcMaxIOC']).", hotcache_time=".intval($REQUEST['tSrcHotCacheAXFR']).", hotcacheixfr_time=".intval($REQUEST['tSrcHotCacheIXFR'])." where rowid='".intval($REQUEST['tSrcId'])."';\n$sql_update";

      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;

    //add sources
    case "POST sources":
      $sql="insert into sources values($USERID,'".DB_escape($db,$REQUEST['tSrcName'])."','".DB_escape($db,$REQUEST['tSrcURL'])."','".DB_escape($db,$REQUEST['tSrcURLIXFR'])."','".DB_escape($db,$REQUEST['tSrcREGEX'])."',NULL,".intval($REQUEST['tSrcMaxIOC']).",".intval($REQUEST['tSrcHotCacheAXFR']).",".intval($REQUEST['tSrcHotCacheIXFR']).")";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
    //modify sources
    case "PUT sources":

      $sql_update="update servers set cfg_updated=1 where rowid in (select distinct server_id from rpzs_servers left join rpzs_${REQUEST['req']} on rpzs_${REQUEST['req']}.rpz_id=rpzs_servers.rpz_id where  rpzs_${REQUEST['req']}.".rtrim($REQUEST['req'],"s")."_id=".intval($REQUEST['tSrcId']).");\n";

      $sql="update sources set name='".DB_escape($db,$REQUEST['tSrcName'])."', url='".DB_escape($db,$REQUEST['tSrcURL'])."', url_ixfr='".DB_escape($db,$REQUEST['tSrcURLIXFR'])."', regex='".DB_escape($db,$REQUEST['tSrcREGEX'])."', max_ioc=".intval($REQUEST['tSrcMaxIOC']).", hotcache_time=".intval($REQUEST['tSrcHotCacheAXFR']).", hotcacheixfr_time=".intval($REQUEST['tSrcHotCacheIXFR'])." where rowid='".intval($REQUEST['tSrcId'])."';\n$sql_update";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;

    //Delete rows
    case "DELETE sources":
    case "DELETE whitelists":
    case "DELETE tkeys":
    case "DELETE tkeys_groups":
      $sql="delete from ${REQUEST['req']} where rowid in ($ReqRowId)";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
    case "DELETE servers":
      $sql="delete from mgmt_ips where server_id in ($ReqRowId);\n";
      $sql.="delete from servers_tsig where server_id in ($ReqRowId);\n";
      $sql.="delete from servers where rowid in ($ReqRowId);\n";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
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

        $subres=DB_selectArray($db,"select tkeys.rowid,tkeys.name, tkeys.alg, tkeys.tkey from rpzs_tkeys left join tkeys on tkeys.rowid=rpzs_tkeys.tkey_id where tkeys.mgmt=0 and rpzs_tkeys.rpz_id=${row['rowid']} union select \"gr_\"||tkeys_groups.rowid, tkeys_groups.group_name||\" (group)\" as name, \"\" as alg, \"\" as tkey from rpzs_tkeys_groups left join tkeys_groups on tkeys_groups.rowid=rpzs_tkeys_groups.tkey_group_id where rpzs_tkeys_groups.rpz_id=${row['rowid']};");
        $row['tkeys']=$subres;

        $subres=DB_selectArray($db,"select tkeys_groups.rowid,tkeys_groups.group_name from rpzs_tkeys_groups left join tkeys_groups on tkeys_groups.rowid=rpzs_tkeys_groups.tkey_group_id where rpzs_tkeys_groups.rpz_id=${row['rowid']};");
        $row['tkeys_groups']=$subres;

        $subres=DB_selectArray($db,"select servers.rowid,servers.name, pub_ip from rpzs_servers left join servers on servers.rowid=rpzs_servers.server_id where rpzs_servers.rpz_id=${row['rowid']};");
        $row['servers']=$subres;

        $subres=DB_selectArray($db,"select whitelists.rowid,whitelists.name from rpzs_whitelists left join whitelists on whitelists.rowid=rpzs_whitelists.whitelist_id where rpzs_whitelists.rpz_id=${row['rowid']};");
        $row['whitelists']=$subres;

        $subres=DB_selectArray($db,"select sources.rowid,sources.name from rpzs_sources left join sources on sources.rowid=rpzs_sources.source_id where rpzs_sources.rpz_id=${row['rowid']};");
        $row['sources']=$subres;

        $subres=DB_selectArray($db,"select rpzs_notify.rowid,rpzs_notify.notify from rpzs_notify where rpzs_notify.rpz_id=${row['rowid']};");
        $row['notify']=$subres;

        $rarray[]=$row;
      };
      $response=json_encode($rarray);
      break;
    case "POST rpzs":
      //actioncustom nx/nod/pass/drop/tcp/loc

      $tkeys=DB_selectArray($db,"select rowid from tkeys where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZTKeys']))).")");
      $tkeys_groups=DB_selectArray($db,"select rowid from tkeys_groups where rowid in (".implode(",",getGroupsId(json_decode($REQUEST['tRPZTKeys']))).")");
      $servers=DB_selectArray($db,"select rowid from servers where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZSrvs']))).")");
      $sources=DB_selectArray($db,"select rowid from sources where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZSrc']))).")");
      $whlists=DB_selectArray($db,"select rowid from whitelists where rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZWL']))).")");

      if (in_array($REQUEST['tRPZAction'],["nxdomain","nodata","passthru","drop","tcp-only"])) $action=$REQUEST['tRPZAction'];else $action=erlChLRecords($REQUEST['tRPZActionCustom']);

      $sql="insert into rpzs values($USERID,'".DB_escape($db,$REQUEST['tRPZName'])."',".intval($REQUEST['tRPZSOA_Refresh']).",".intval($REQUEST['tRPZSOA_UpdRetry']).",".
            intval($REQUEST['tRPZSOA_Exp']).",".intval($REQUEST['tRPZSOA_NXTTL']).",".DB_boolval($REQUEST['tRPZCache']).",".DB_boolval($REQUEST['tRPZWildcard']).",'".
            DB_escape($db,$action)."','".DB_escape($db,$REQUEST['tRPZIOCType'])."',".intval($REQUEST['tRPZAXFR']).",".intval($REQUEST['tRPZIXFR']).",".
            DB_boolval($REQUEST['tRPZDisabled']).");";
      if (DB_execute($db,$sql)) {
        //safest way to get id?
        $rpzid=DB_selectArray($db,"select max(rowid) as rowid from rpzs where name='".DB_escape($db,$REQUEST['tRPZName'])."'")[0]['rowid'];
        $sql='';
        foreach($tkeys as $tkey){
          $sql.="insert into rpzs_tkeys values($rpzid,$USERID,${tkey['rowid']});\n";
        };
        foreach($tkeys_groups as $tkey_group){
          $sql.="insert into rpzs_tkeys_groups values($rpzid,$USERID,${tkey_group['rowid']});\n";
        };
        foreach($servers as $tkey){
          $sql.="insert into rpzs_servers values($rpzid,$USERID,${tkey['rowid']});\n update servers set cfg_updated=1 where rowid=${tkey['rowid']};\n";
        };
        foreach($sources as $tkey){
          $sql.="insert into rpzs_sources values($rpzid,$USERID,${tkey['rowid']});\n";
        };
        foreach($whlists as $tkey){
          $sql.="insert into rpzs_whitelists values($rpzid,$USERID,${tkey['rowid']});\n";
        };
        foreach(array_unique(json_decode($REQUEST['tRPZNotify'])) as $ip){
          //TODO add uniq only
          $sql.="insert into rpzs_notify values($rpzid,$USERID,'".DB_escape($db,$ip)."');\n";
        };
        if (DB_execute($db,$sql)) {
          $response='{"status":"ok"}';
        }else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      }else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
    case "PUT rpzs":
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

      $sql='';
      foreach($tkeys_old as $tkey){if ($k=array_search($tkey['tkey_id'],$tkeys_new)) unset($tkeys_new[$k]); else $sql.="delete from rpzs_tkeys where rowid=${tkey['rowid']};\n";};
      foreach($tkeys_new as $tkey){$sql.="insert into rpzs_tkeys values($rpzid,$USERID,${tkey['rowid']});\n";};

      foreach($tkeys_groups_old as $tkeys_group){if ($k=array_search($tkeys_group['tkey_group_id'],$tkeys_new)) unset($tkeys_groups_new[$k]); else $sql.="delete from rpzs_tkeys_groups where rowid=${tkeys_group['rowid']};\n";};
      foreach($tkeys_groups_new as $tkeys_group){$sql.="insert into rpzs_tkeys_groups values($rpzid,$USERID,${tkeys_group['rowid']});\n";};

      $chCfgSrv=[];
      foreach($servers_old as $item){if ($k=array_search($item['server_id'],$tkeys_new)) unset($servers_new[$k]); else {$sql.="delete from rpzs_servers where rowid=${item['rowid']};\n";array_push($chCfgSrv,$item['rowid']);}};
      foreach($servers_new as $item){$sql.="insert into rpzs_servers values($rpzid,$USERID,${item['rowid']});\n";array_push($chCfgSrv,$item['rowid']);};
      $sql.="update servers set cfg_updated=1 where rowid in (".implode(",", $chCfgSrv).");\n";

      foreach($sources_old as $item){if ($k=array_search($item['source_id'],$tkeys_new)) unset($sources_new[$k]); else $sql.="delete from rpzs_sources where rowid=${item['rowid']};\n";};
      foreach($sources_new as $item){$sql.="insert into rpzs_sources values($rpzid,$USERID,${item['rowid']});\n";};

      foreach($whlists_old as $item){if ($k=array_search($item['whitelist_id'],$tkeys_new)) unset($whlists_new[$k]); else $sql.="delete from rpzs_whitelists where rowid=${item['rowid']};\n";};
      foreach($whlists_new as $item){$sql.="insert into rpzs_whitelists values($rpzid,$USERID,${item['rowid']});\n";};

      $ip_new=array_unique(json_decode($REQUEST['tRPZNotify']));
      $ip_old=DB_selectArray($db,"select rowid, notify from rpzs_notify where rpz_id=$rpzid");

      foreach($ip_old as $ip){
        if ($k=array_search($ip['notify'],$ip_new)) unset($ip_new[$k]); else $sql.="delete from rpzs_notify where rowid=${ip['rowid']};\n";
      };
      foreach($ip_new as $ip){
        $sql.="insert into rpzs_notify values($rpzid,$USERID,'$ip');\n";
      };

      $sql.="update rpzs set name='".DB_escape($db,$REQUEST['tRPZName'])."', soa_refresh=".intval($REQUEST['tRPZSOA_Refresh']).", soa_update_retry=".
            intval($REQUEST['tRPZSOA_UpdRetry']).",soa_expiration=".intval($REQUEST['tRPZSOA_Exp']).", soa_nx_ttl=".intval($REQUEST['tRPZSOA_NXTTL']).", cache=".
            DB_boolval($REQUEST['tRPZCache']).", wildcard=".DB_boolval($REQUEST['tRPZWildcard']).","."action='".DB_escape($db,$action)."',ioc_type='".
            DB_escape($db,$REQUEST['tRPZIOCType'])."',axfr_update=".intval($REQUEST['tRPZAXFR']).",ixfr_update=".intval($REQUEST['tRPZIXFR']).",disabled=".
            DB_boolval($REQUEST['tRPZDisabled'])." where rowid=$rpzid";

      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL

      break;
    case "DELETE rpzs":
      $sql="delete from rpzs_notify where rpz_id in ($ReqRowId);\n";
      $sql.="delete from rpzs_tkeys where rpz_id in ($ReqRowId);\n";
      $sql.="delete from rpzs_tkeys_groups where rpz_id in ($ReqRowId);\n";
      $sql.="delete from rpzs_servers where rpz_id in ($ReqRowId);\n update servers set cfg_updated=1 where rowid in ($ReqRowId);\n";
      $sql.="delete from rpzs_whitelists where rpz_id in ($ReqRowId);\n";
      $sql.="delete from rpzs_sources where rpz_id in ($ReqRowId);\n";
      $sql.="delete from rpzs where rowid in ($ReqRowId);\n";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;

    case "GET rpz_servers":
      $response=json_encode(DB_selectArray($db,"select rowid as value, name as text from servers"));
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
      $sql="update servers set publish_upd=1, cfg_updated=0 where ".($REQUEST['SrvId'] == 'all'?" disabled=0  and cfg_updated=1 and mgmt=1":" rowid=".intval($REQUEST['SrvId']));
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL

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
        $salt = mt_rand(1000, 9999);
        $sql="insert into users(name, password, salt, perm, loginattempts, lastlogin, lastfailedlogin) values('".DB_escape($db,$REQUEST['name'])."','".md5(md5(DB_escape($db,$REQUEST['pwd'])).$salt)."','$salt',".DB_escape($db,$REQUEST['perm']).",0,0,0)";
        if (DB_execute($db,$sql)) $response='{"status":"ok","description":"User created '.$sql.'"}';
          else $response='{"status":"failed","description":"Unexpected error!"}';
      }else{
        $response='{"status":"failed", "reason":"not supported"}';
      };
      break;
    case "PUT users":
      if ($_SESSION['perm'] == 1){
        $salt = mt_rand(1000, 9999);
        $sql="update users set name='".DB_escape($db,$REQUEST['name'])."', password='".md5(md5(DB_escape($db,$REQUEST['pwd'])).$salt)."', salt='$salt', perm=".DB_escape($db,$REQUEST['perm']).", loginattempts=0 where rowid=".DB_escape($db,$REQUEST['rowid']);
        if (DB_execute($db,$sql)) $response='{"status":"ok","description":"User updated"}';
          else $response='{"status":"failed","description":"Unexpected error!"}';
      }else{
        $response='{"status":"failed", "reason":"not supported"}';
      };
      break;
    case "PATCH user_passsword":
      if ($_SESSION['perm'] == 1){
      }else{
        $response='{"status":"failed", "reason":"not supported"}';
      };
      break;
    case "DELETE users":
      if ($_SESSION['perm'] == 1){
        $sql="delete from users where rowid=".DB_escape($db,$REQUEST['rowid']);
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
      $cmm="";
      $results="";
      if ($servers) {
        foreach ($servers as $rpidns){
          $results=$results.$cmm.'{"id":'.$rpidns['id'].',"rpidns_uuid":"'.$rpidns['rpidns_uuid'].'","name":"'.$rpidns['name'].'","comment":"'.$rpidns['comment'].'","model":"'.$rpidns['model'].'","dns":"'.$rpidns['dns'].'","updconf":'.($rpidns['updconf']?'true':'false').',"status":"configured","rpz":'.$rpidns['rpz'].',"redirect":"'.$rpidns['redirect'].'","redirect_cname":"'.$rpidns['redirect_cname'].'","logging":"'.$rpidns['logging'].'","logging_host":"'.$rpidns['logging_host'].'","dns_type":"'.$rpidns['dns_type'].'","dns_ipnet":"'.$rpidns['dns_ipnet'].'"}';
          $cmm=",";
        };
      };
      $response='{"status":"success","data":['.$results.']}';
      break;

    case "POST rpidns":

			$config='{"name":"'.$REQUEST['name'].'", "model":"'.$REQUEST['model'].'", "dns":"'.$REQUEST['dns'].'", "updconf":"'.$REQUEST['updconf'].'", "rpz":'.json_encode($REQUEST['rpz']).', "redirect":"'.$REQUEST['redirect'].'", "redirect_cname":"'.$REQUEST['redirect_cname'].'", "logging":"'.$REQUEST['logging'].'", "logging_host":"'.$REQUEST['logging_host'].'", "dns_type":"'.$REQUEST['dns_type'].'", "dns_ipnet":"'.$REQUEST['dns_ipnet'].'"}';
			$sql="insert into rpidns(user_id,name,commentary,configuration,rpidns_uuid) values($USERID,'".$REQUEST['name']."','".$REQUEST['comment']."','$config','".uuid()."')";
			if (DB_execute($db,$sql)) $response='[{"status":"success","description":"success"}]'; else $response='[{"status":"error","description":"Error"}]';
      break;

    case "PUT rpidns":
			$config='{"name":"'.$REQUEST['name'].'", "model":"'.$REQUEST['model'].'", "dns":"'.$REQUEST['dns'].'", "updconf":"'.$REQUEST['updconf'].'", "rpz":'.json_encode($REQUEST['rpz']).', "redirect":"'.$REQUEST['redirect'].'", "redirect_cname":"'.$REQUEST['redirect_cname'].'", "logging":"'.$REQUEST['logging'].'", "logging_host":"'.$REQUEST['logging_host'].'", "dns_type":"'.$REQUEST['dns_type'].'", "dns_ipnet":"'.$REQUEST['dns_ipnet'].'"}';
			$sql="update rpidns set user_id=$USERID, name='".$REQUEST['name']."', commentary='".$REQUEST['comment']."',configuration='$config' where rowid=".$REQUEST['id'];
			if (DB_execute($db,$sql)) $response='[{"status":"success","description":"success"}]'; else $response='[{"status":"error","description":"Error"}]';
      break;
    case "DELETE rpidns":

			$sql="delete from rpidns where rowid=".$REQUEST['id'];
			if (DB_execute($db,$sql)) $response='[{"status":"success","description":"success"}]'; else $response='[{"status":"error","description":"Error"}]';

      break;

    default:
      $response='{"status":"failed", "reason":"not supported"}';
endswitch;

echo $response;

DB_close($db);

?>
