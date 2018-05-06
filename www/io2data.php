<?php
#(c) Vadim Pavlov 2018
#ioc2rpz configuration API

require 'io2auth.php';

function getRequest(){
  #do it simple for now
  #support only 1 level request
  $rawRequest = file_get_contents('php://input');
  if (empty($rawRequest)){
    $Data=$_REQUEST;
  }else{
    $Data=json_decode($rawRequest,true);
  };
  $Data['method'] = $_SERVER['REQUEST_METHOD'];
  $Data['req'] = explode("/", substr(@$_SERVER['PATH_INFO'], 1))[0];
  /*
   * TODO escape values for SQL safety
   */
  //if ($Data['method'] == 'PUT') print_r($Data);
  return $Data;
};


$REQUEST=getRequest();
if (!empty($REQUEST['rowid'])) $ReqRowId=ctype_digit($REQUEST['rowid'])?$REQUEST['rowid']:implode(",",array_filter(json_decode($REQUEST['rowid'],true),'is_numeric'));

$db=DB_open();

#var_dump($REQUEST);

switch ($REQUEST['method'].' '.$REQUEST["req"]):
    case "GET servers":
      $rarray=[];
      $result=DB_select($db,"select rowid,* from servers where user_id=$USERID;");
      while ($row = DB_fetchArray($result)) {
        unset($row['user_id']);
        $subres=DB_selectArray($db,"select tkeys.rowid,tkeys.name from servers_tsig left join tkeys on tkeys.rowid=servers_tsig.tsig_id where servers_tsig.user_id=$USERID and servers_tsig.server_id=${row['rowid']};");
        $row['tkeys']=$subres;
        $subres=DB_selectArray($db,"select mgmt_ips.rowid,mgmt_ips.mgmt_ip from mgmt_ips where mgmt_ips.user_id=$USERID and mgmt_ips.server_id=${row['rowid']};");
        $row['mgmt_ips']=$subres;
        $rarray[]=$row;
      };
      $response=json_encode($rarray);
      break;
      
    case "POST servers":
      $tkeys=DB_selectArray($db,"select rowid from tkeys where user_id=$USERID and rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tSrvTKeys']))).")");
      $sql="insert into servers values($USERID,'".DB_escape($db,$REQUEST['tSrvName'])."','".DB_escape($db,$REQUEST['tSrvIP'])."','".DB_escape($db,$REQUEST['tSrvNS'])."','".DB_escape($db,$REQUEST['tSrvEmail'])."',".DB_escape($db,$REQUEST['tSrvMGMT']).",".DB_boolval($REQUEST['tSrvDisabled']).",".intval($REQUEST['tSrvSType']).",'".DB_escape($db,$REQUEST['tSrvURL'])."',".DB_boolval($REQUEST['tSrvMGMT']).",0)";
      if (DB_execute($db,$sql)) {
        //safest way to get id?
        $srvid=DB_selectArray($db,"select max(rowid) as rowid from servers where user_id=$USERID and name='".DB_escape($db,$REQUEST['tSrvName'])."'")[0]['rowid'];
        $sql='';
        foreach($tkeys as $tkey){
          $sql.="insert into servers_tsig values($srvid,$USERID,${tkey['rowid']});\n";
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
      $tkeys_new=DB_selectArray($db,"select rowid from tkeys where user_id=$USERID and rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tSrvTKeys']))).")");
      $tkeys_old=DB_selectArray($db,"select rowid,tsig_id from servers_tsig where user_id=$USERID and server_id=$srvid");
      $sql='';
      foreach($tkeys_old as $tkey){
        if ($k=array_search($tkey['tsig_id'],$tkeys_new)) unset($tkeys_new[$k]); else $sql.="delete from servers_tsig where rowid=${tkey['rowid']};\n";
      };       
      //$tkeys=DB_selectArray($db,"select rowid from tkeys where user_id=$USERID and rowid in (".implode(",",$tkeys_new).")");
      foreach($tkeys_new as $tkey){
        $sql.="insert into servers_tsig values($srvid,$USERID,${tkey['rowid']});\n";
      };
      $mgmtip_new=array_unique(json_decode($REQUEST['tSrvMGMTIP']));
      $mgmtip_old=DB_selectArray($db,"select rowid, mgmt_ip from mgmt_ips where user_id=$USERID and server_id=$srvid");
      foreach($mgmtip_old as $ip){
        if ($k=array_search($ip['mgmt_ip'],$mgmtip_new)) unset($mgmtip_new[$k]); else $sql.="delete from mgmt_ips where rowid=${ip['rowid']};\n";
      };       
      foreach($mgmtip_new as $ip){
        $sql.="insert into mgmt_ips values($srvid,$USERID,'".DB_escape($db,$ip)."');\n";
      };
      $sql.="update servers set name='".DB_escape($db,$REQUEST['tSrvName'])."', ip='".DB_escape($db,$REQUEST['tSrvIP'])."', ns='".DB_escape($db,$REQUEST['tSrvNS'])."', email='".DB_escape($db,$REQUEST['tSrvEmail'])."', mgmt=".DB_boolval($REQUEST['tSrvMGMT']).", disabled=".DB_boolval($REQUEST['tSrvDisabled'])." ,stype=".intval($REQUEST['tSrvSType']).", URL='".DB_escape($db,$REQUEST['tSrvURL'])."', cfg_updated=".DB_boolval($REQUEST['tSrvMGMT'])." where user_id=$USERID and rowid=$srvid";
      
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;

    //Select rows from tables
    case "GET sources":
    case "GET whitelists":
    case "GET tkeys":
      $rarray=[];
      $result=DB_select($db,"select rowid,* from ${REQUEST['req']} where user_id=$USERID;");
      while ($row = DB_fetchArray($result)) {
        unset($row['user_id']);
        $rarray[]=$row;
      };
      $response=json_encode($rarray);
      break;
    case "GET tkeys_mgmt":
      $response=json_encode(DB_selectArray($db,"select rowid as value, name as text from tkeys where user_id=$USERID and mgmt=1;"));
      break;
    //add TSIG
    case "POST tkeys":
      $sql="insert into tkeys values($USERID,'".DB_escape($db,$REQUEST['tKeyName'])."','".DB_escape($db,$REQUEST['tKeyAlg'])."','".DB_escape($db,$REQUEST['tKey'])."',".DB_boolval($REQUEST['tKeyMGMT']).")";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
    //modify TSIG
    case "PUT tkeys": 
      $sql="update tkeys set name='".DB_escape($db,$REQUEST['tKeyName'])."', alg='".DB_escape($db,$REQUEST['tKeyAlg'])."', tkey='".DB_escape($db,$REQUEST['tKey'])."', mgmt=".DB_boolval($REQUEST['tKeyMGMT'])." where user_id=$USERID and rowid='${REQUEST['tKeyId']}'";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;

    //add whitelist
    case "POST whitelists":
      $sql="insert into whitelists values($USERID,'".DB_escape($db,$REQUEST['tSrcName'])."','".DB_escape($db,$REQUEST['tSrcURL'])."','".DB_escape($db,$REQUEST['tSrcREGEX'])."')";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
    //modify whitelist
    case "PUT whitelists": 
      $sql="update whitelists set name='".DB_escape($db,$REQUEST['tSrcName'])."', url='".DB_escape($db,$REQUEST['tSrcURL'])."', regex='".DB_escape($db,$REQUEST['tSrcREGEX'])."' where user_id=$USERID and rowid='".intval($REQUEST['tSrcId'])."'";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;

    //add sources
    case "POST sources":
      $sql="insert into sources values($USERID,'".DB_escape($db,$REQUEST['tSrcName'])."','".DB_escape($db,$REQUEST['tSrcURL'])."','".DB_escape($db,$REQUEST['tSrcURLIXFR'])."','".DB_escape($db,$REQUEST['tSrcREGEX'])."')";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
    //modify sources
    case "PUT sources": 
      $sql="update sources set name='".DB_escape($db,$REQUEST['tSrcName'])."', url='".DB_escape($db,$REQUEST['tSrcURL'])."', url_ixfr='".DB_escape($db,$REQUEST['tSrcURLIXFR'])."', regex='".DB_escape($db,$REQUEST['tSrcREGEX'])."' where user_id=$USERID and rowid='".intval($REQUEST['tSrcId'])."'";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
 
    //Delete rows
    case "DELETE sources":
    case "DELETE whitelists":
    case "DELETE tkeys":
      $sql="delete from ${REQUEST['req']} where user_id=$USERID and rowid in ($ReqRowId)";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
    case "DELETE servers":
      $sql="delete from mgmt_ips where user_id=$USERID and server_id in ($ReqRowId);\n";
      $sql.="delete from servers_tsig where user_id=$USERID and server_id in ($ReqRowId);\n";
      $sql.="delete from servers where user_id=$USERID and rowid in ($ReqRowId);\n";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;

    case "GET rpzs":
#{rpz,{"dns-bh.ioc2rpz",86400,3600,2592000,7200,"true","true","nxdomain",["pub_demokey_1","at_demokey_1","priv_key_1"],"mixed",604800,86400,["dns-bh"],[],["whitelist_1"]}}.
#      $response='[{"name":"dns-bh.ioc2rpz", "servers":["server-1"], "soa_refresh":86400, "soa_update_retry":3600, "soa_expiration":2592000, "soa_nx_ttl":7200, "cache":"true", "wildcard":"true", "action":"nxdomain", "tkeys":["pub_demokey_1","at_demokey_1","priv_key_1"], "ioc_type":"mixed", "axfr_update":604800, "ixfr_update":86400, "sources":["dns-bh","dns-bh1"], "notify":[], "whitelists":["whitelist_1"]}]';

      $result=DB_select($db,"select rowid,* from rpzs where user_id=$USERID;");
      $rarray=[];
      while ($row = DB_fetchArray($result)) {
        unset($row['user_id']);

        //actioncustom nx/nod/pass/drop/tcp/loc
        if (in_array($row['action'],["nxdomain","nodata","passthru","drop","tcp-only"])) $row['actioncustom']="";else{$row['actioncustom']=$row['action'];$row['action']="local";};

        $subres=DB_selectArray($db,"select tkeys.rowid,tkeys.name from rpzs_tkeys left join tkeys on tkeys.rowid=rpzs_tkeys.tkey_id where rpzs_tkeys.user_id=$USERID and rpzs_tkeys.rpz_id=${row['rowid']};");
        $row['tkeys']=$subres;

        $subres=DB_selectArray($db,"select servers.rowid,servers.name from rpzs_servers left join servers on servers.rowid=rpzs_servers.server_id where rpzs_servers.user_id=$USERID and rpzs_servers.rpz_id=${row['rowid']};");
        $row['servers']=$subres;

        $subres=DB_selectArray($db,"select whitelists.rowid,whitelists.name from rpzs_whitelists left join whitelists on whitelists.rowid=rpzs_whitelists.whitelist_id where rpzs_whitelists.user_id=$USERID and rpzs_whitelists.rpz_id=${row['rowid']};");
        $row['whitelists']=$subres;

        $subres=DB_selectArray($db,"select sources.rowid,sources.name from rpzs_sources left join sources on sources.rowid=rpzs_sources.source_id where rpzs_sources.user_id=$USERID and rpzs_sources.rpz_id=${row['rowid']};");
        $row['sources']=$subres;

        $subres=DB_selectArray($db,"select rpzs_notify.rowid,rpzs_notify.notify from rpzs_notify where rpzs_notify.user_id=$USERID and rpzs_notify.rpz_id=${row['rowid']};");
        $row['notify']=$subres;

        $rarray[]=$row;
      };
      $response=json_encode($rarray);
      break;
    case "POST rpzs":
      //actioncustom nx/nod/pass/drop/tcp/loc
      
      $tkeys=DB_selectArray($db,"select rowid from tkeys where user_id=$USERID and rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZTKeys']))).")");
      $servers=DB_selectArray($db,"select rowid from servers where user_id=$USERID and rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZSrvs']))).")");
      $sources=DB_selectArray($db,"select rowid from sources where user_id=$USERID and rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZSrc']))).")");
      $whlists=DB_selectArray($db,"select rowid from whitelists where user_id=$USERID and rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZWL']))).")");

      if (in_array($REQUEST['tRPZAction'],["nxdomain","nodata","passthru","drop","tcp-only"])) $action=$REQUEST['tRPZAction'];else $action=erlChLRecords($REQUEST['tRPZActionCustom']);

      $sql="insert into rpzs values($USERID,'".DB_escape($db,$REQUEST['tRPZName'])."',".intval($REQUEST['tRPZSOA_Refresh']).",".intval($REQUEST['tRPZSOA_UpdRetry']).",".
            intval($REQUEST['tRPZSOA_Exp']).",".intval($REQUEST['tRPZSOA_NXTTL']).",".DB_boolval($REQUEST['tRPZCache']).",".DB_boolval($REQUEST['tRPZWildcard']).",'".
            DB_escape($db,$action)."','".DB_escape($db,$REQUEST['tRPZIOCType'])."',".intval($REQUEST['tRPZAXFR']).",".intval($REQUEST['tRPZIXFR']).",".
            DB_boolval($REQUEST['tRPZDisabled']).");";
      if (DB_execute($db,$sql)) {
        //safest way to get id?
        $rpzid=DB_selectArray($db,"select max(rowid) as rowid from rpzs where user_id=$USERID and name='".DB_escape($db,$REQUEST['tRPZName'])."'")[0]['rowid'];
        $sql='';
        foreach($tkeys as $tkey){
          $sql.="insert into rpzs_tkeys values($rpzid,$USERID,${tkey['rowid']});\n";
        };
        foreach($servers as $tkey){
          $sql.="insert into rpzs_servers values($rpzid,$USERID,${tkey['rowid']});\n";
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
      $tkeys_new=DB_selectArray($db,"select rowid from tkeys where user_id=$USERID and rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZTKeys']))).")");
      $tkeys_old=DB_selectArray($db,"select rowid,tkey_id from rpzs_tkeys where user_id=$USERID and rpz_id=$rpzid");
      $servers_new=DB_selectArray($db,"select rowid from servers where user_id=$USERID and rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZSrvs']))).")");
      $servers_old=DB_selectArray($db,"select rowid,server_id from rpzs_servers where user_id=$USERID and rpz_id=$rpzid");
      $sources_new=DB_selectArray($db,"select rowid from sources where user_id=$USERID and rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZSrc']))).")");
      $sources_old=DB_selectArray($db,"select rowid,source_id from rpzs_sources where user_id=$USERID and rpz_id=$rpzid");
      $whlists_new=DB_selectArray($db,"select rowid from whitelists where user_id=$USERID and rowid in (".implode(",",filterIntArr(json_decode($REQUEST['tRPZWL']))).")");
      $whlists_old=DB_selectArray($db,"select rowid,whitelist_id from rpzs_whitelists where user_id=$USERID and rpz_id=$rpzid");

      if (in_array($REQUEST['tRPZAction'],["nx","nod","pass","drop","tcp"])) $action=$REQUEST['tRPZAction'];else $action=erlChLRecords($REQUEST['tRPZActionCustom']);

      $sql='';
      foreach($tkeys_old as $tkey){if ($k=array_search($tkey['tkey_id'],$tkeys_new)) unset($tkeys_new[$k]); else $sql.="delete from rpzs_tkeys where rowid=${tkey['rowid']};\n";};       
      foreach($tkeys_new as $tkey){$sql.="insert into rpzs_tkeys values($rpzid,$USERID,${tkey['rowid']});\n";};

      foreach($servers_old as $item){if ($k=array_search($item['server_id'],$tkeys_new)) unset($servers_new[$k]); else $sql.="delete from rpzs_servers where rowid=${item['rowid']};\n";};       
      foreach($servers_new as $item){$sql.="insert into rpzs_servers values($rpzid,$USERID,${item['rowid']});\n";};

      foreach($sources_old as $item){if ($k=array_search($item['source_id'],$tkeys_new)) unset($sources_new[$k]); else $sql.="delete from rpzs_sources where rowid=${item['rowid']};\n";};       
      foreach($sources_new as $item){$sql.="insert into rpzs_sources values($rpzid,$USERID,${item['rowid']});\n";};

      foreach($whlists_old as $item){if ($k=array_search($item['whitelist_id'],$tkeys_new)) unset($whlists_new[$k]); else $sql.="delete from rpzs_whitelists where rowid=${item['rowid']};\n";};       
      foreach($whlists_new as $item){$sql.="insert into rpzs_whitelists values($rpzid,$USERID,${item['rowid']});\n";};

      $ip_new=array_unique(json_decode($REQUEST['tRPZNotify']));
      $ip_old=DB_selectArray($db,"select rowid, notify from rpzs_notify where user_id=$USERID and rpz_id=$rpzid");

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
            DB_boolval($REQUEST['tRPZDisabled'])." where user_id=$USERID and rowid=$rpzid";
      
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL

      break;
    case "DELETE rpzs":
      $sql="delete from rpzs_notify where user_id=$USERID and rpz_id in ($ReqRowId);\n";
      $sql.="delete from rpzs_tkeys where user_id=$USERID and rpz_id in ($ReqRowId);\n";
      $sql.="delete from rpzs_servers where user_id=$USERID and rpz_id in ($ReqRowId);\n";
      $sql.="delete from rpzs_whitelists where user_id=$USERID and rpz_id in ($ReqRowId);\n";
      $sql.="delete from rpzs_sources where user_id=$USERID and rpz_id in ($ReqRowId);\n";
      $sql.="delete from rpzs where user_id=$USERID and rowid in ($ReqRowId);\n";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;

    case "GET rpz_servers":
      $response=json_encode(DB_selectArray($db,"select rowid as value, name as text from servers where user_id=$USERID;"));
      break;

    case "GET rpz_tkeys":
      $response=json_encode(DB_selectArray($db,"select rowid as value, name as text from tkeys where user_id=$USERID and mgmt!=1;"));
      break;

    case "GET rpz_sources":
      $response=json_encode(DB_selectArray($db,"select rowid as value, name as text from sources where user_id=$USERID;"));
      break;

    case "GET rpz_whitelists":
      $response=json_encode(DB_selectArray($db,"select rowid as value, name as text from whitelists where user_id=$USERID;"));
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
      $sql="update servers set publish_upd=1 where user_id=$USERID and cfg_updated=1 and mgmt=1";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL

      break;
    default:
      $response='{"status":"failed", "reason":"not supported"}';
endswitch;

echo $response;

DB_close($db);

?>