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
$db=DB_open();

#var_dump($REQUEST);

switch ($REQUEST['method'].' '.$REQUEST["req"]):
    case "GET servers":
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

      /*[tSrvId] => 1
       //*[tSrvName] => server_1
       //*[tSrvIP] => 127.0.0.1
       //*[tSrvNS] => ns1.ioc2rpz.localdomain
       //*[tSrvEmail] => support.ioc2rpz.localdomain
       //*[tSrvMGMT] => 1
       *[tSrvMGMTIP] => ["127.0.0.1","127.0.0.2","127.0.0.3"]
       *[tSrvTKeys] => [1,7]
        $sql='insert into servers values(1,"server_1","127.0.0.1","ns1.ioc2rpz.localdomain","support.ioc2rpz.localdomain",1);'.
       'insert into servers_tsig values(1,1,1);'.
       'insert into mgmt_ips values(1,1,"127.0.0.1");';
       */
      
    case "POST servers":
      $tkeys=DB_selectArray($db,"select rowid from tkeys where user_id=$USERID and rowid in (".implode(",",json_decode($REQUEST['tSrvTKeys'])).")");
      $sql="insert into servers values($USERID,'${REQUEST['tSrvName']}','${REQUEST['tSrvIP']}','${REQUEST['tSrvNS']}','${REQUEST['tSrvEmail']}',${REQUEST['tSrvMGMT']},${REQUEST['tSrvDisabled']})";
      if (DB_execute($db,$sql)) {
        //safest way to get id?
        $srvid=DB_selectArray($db,"select max(rowid) as rowid from servers where user_id=$USERID and name='${REQUEST['tSrvName']}'")[0]['rowid'];
        $sql='';
        foreach($tkeys as $tkey){
          $sql.="insert into servers_tsig values($srvid,$USERID,${tkey['rowid']});\n";
        };       
        foreach(json_decode($REQUEST['tSrvMGMTIP']) as $ip){
          //TODO add uniq only
          $sql.="insert into mgmt_ips values($srvid,$USERID,'$ip');\n";
        };        
        if (DB_execute($db,$sql)) {          
          $response='{"status":"ok"}';
        }else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      }else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;

    case "PUT servers":
      $tkeys_new=DB_selectArray($db,"select rowid from tkeys where user_id=$USERID and rowid in (".implode(",",json_decode($REQUEST['tSrvTKeys'])).")");
      $tkeys_old=DB_selectArray($db,"select rowid,tsig_id from servers_tsig where user_id=$USERID and server_id=${REQUEST['tSrvId']}");
      $sql='';
      foreach($tkeys_old as $tkey){
        if ($k=array_search($tkey['tsig_id'],$tkeys_new)) unset($tkeys_new[$k]); else $sql.="delete from servers_tsig where rowid=${tkey['rowid']};\n";
      };       
      //$tkeys=DB_selectArray($db,"select rowid from tkeys where user_id=$USERID and rowid in (".implode(",",$tkeys_new).")");
      foreach($tkeys_new as $tkey){
        $sql.="insert into servers_tsig values(${REQUEST['tSrvId']},$USERID,${tkey['rowid']});\n";
      };
      $mgmtip_new=array_unique(json_decode($REQUEST['tSrvMGMTIP']));
      $mgmtip_old=DB_selectArray($db,"select rowid, mgmt_ip from mgmt_ips where user_id=$USERID and server_id=${REQUEST['tSrvId']}");
      foreach($mgmtip_old as $ip){
        if ($k=array_search($ip['mgmt_ip'],$mgmtip_new)) unset($mgmtip_new[$k]); else $sql.="delete from mgmt_ips where rowid=${ip['rowid']};\n";
      };       
      foreach($mgmtip_new as $ip){
        $sql.="insert into mgmt_ips values(${REQUEST['tSrvId']},$USERID,'$ip');\n";
      };
      $sql.="update servers set name='${REQUEST['tSrvName']}', ip='${REQUEST['tSrvIP']}', ns='${REQUEST['tSrvNS']}', email='${REQUEST['tSrvEmail']}', mgmt=${REQUEST['tSrvMGMT']}, disabled=${REQUEST['tSrvDisabled']} where user_id=$USERID and rowid=${REQUEST['tSrvId']}";
      
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
      $sql="insert into tkeys values($USERID,'${REQUEST['tKeyName']}','${REQUEST['tKeyAlg']}','${REQUEST['tKey']}',${REQUEST['tKeyMGMT']})";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
    //modify TSIG
    case "PUT tkeys": 
      $sql="update tkeys set name='${REQUEST['tKeyName']}', alg='${REQUEST['tKeyAlg']}', tkey='${REQUEST['tKey']}', mgmt=${REQUEST['tKeyMGMT']} where user_id=$USERID and rowid='${REQUEST['tKeyId']}'";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;

    //add whitelist
    case "POST whitelists":
      $sql="insert into whitelists values($USERID,'${REQUEST['tSrcName']}','${REQUEST['tSrcURL']}','${REQUEST['tSrcREGEX']}')";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
    //modify whitelist
    case "PUT whitelists": 
      $sql="update whitelists set name='${REQUEST['tSrcName']}', url='${REQUEST['tSrcURL']}', regex='${REQUEST['tSrcREGEX']}' where user_id=$USERID and rowid='${REQUEST['tSrcId']}'";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;

    //add sources
    case "POST sources":
      $sql="insert into sources values($USERID,'${REQUEST['tSrcName']}','${REQUEST['tSrcURL']}','${REQUEST['tSrcURLIXFR']}','${REQUEST['tSrcREGEX']}')";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
    //modify sources
    case "PUT sources": 
      $sql="update sources set name='${REQUEST['tSrcName']}', url='${REQUEST['tSrcURL']}', url_ixfr='${REQUEST['tSrcURLIXFR']}', regex='${REQUEST['tSrcREGEX']}' where user_id=$USERID and rowid='${REQUEST['tSrcId']}'";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
 
    //Delete rows
    case "DELETE sources":
    case "DELETE whitelists":
    case "DELETE tkeys":
      $sql="delete from ${REQUEST['req']} where user_id=$USERID and rowid=${REQUEST['rowid']}";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
    case "DELETE servers":
      $sql="delete from mgmt_ips where user_id=$USERID and server_id=${REQUEST['rowid']};\n";
      $sql.="delete from servers_tsig where user_id=$USERID and server_id=${REQUEST['rowid']};\n";
      $sql.="delete from servers where user_id=$USERID and rowid=${REQUEST['rowid']};\n";
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;

    case "GET rpzs":
#{rpz,{"dns-bh.ioc2rpz",86400,3600,2592000,7200,"true","true","nxdomain",["pub_demokey_1","at_demokey_1","priv_key_1"],"mixed",604800,86400,["dns-bh"],[],["whitelist_1"]}}.
#      $response='[{"name":"dns-bh.ioc2rpz", "servers":["server-1"], "soa_refresh":86400, "soa_update_retry":3600, "soa_expiration":2592000, "soa_nx_ttl":7200, "cache":"true", "wildcard":"true", "action":"nxdomain", "tkeys":["pub_demokey_1","at_demokey_1","priv_key_1"], "ioc_type":"mixed", "axfr_update":604800, "ixfr_update":86400, "sources":["dns-bh","dns-bh1"], "notify":[], "whitelists":["whitelist_1"]}]';

      $result=DB_select($db,"select rowid,* from rpzs where user_id=$USERID;");
      while ($row = DB_fetchArray($result)) {
        unset($row['user_id']);

        //actioncustom nx/nod/pass/drop/tcp/loc
        if (in_array($row['action'],["nx","nod","pass","drop","tcp"])) $row['actioncustom']="";else{$row['actioncustom']=$row['action'];$row['action']="loc";};

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
      
      $tkeys=DB_selectArray($db,"select rowid from tkeys where user_id=$USERID and rowid in (".implode(",",json_decode($REQUEST['tRPZTKeys'])).")");
      $servers=DB_selectArray($db,"select rowid from servers where user_id=$USERID and rowid in (".implode(",",json_decode($REQUEST['tRPZSrvs'])).")");
      $sources=DB_selectArray($db,"select rowid from sources where user_id=$USERID and rowid in (".implode(",",json_decode($REQUEST['tRPZSrc'])).")");
      $whlists=DB_selectArray($db,"select rowid from whitelists where user_id=$USERID and rowid in (".implode(",",json_decode($REQUEST['tRPZWL'])).")");

      if (in_array($REQUEST['tRPZAction'],["nx","nod","pass","drop","tcp"])) $action=$REQUEST['tRPZAction'];else $action=$REQUEST['tRPZActionCustom'];

      $sql="insert into rpzs values($USERID,'${REQUEST['tRPZName']}',${REQUEST['tRPZSOA_Refresh']},${REQUEST['tRPZSOA_UpdRetry']},${REQUEST['tRPZSOA_Exp']},".
           "${REQUEST['tRPZSOA_NXTTL']},${REQUEST['tRPZCache']},${REQUEST['tRPZWildcard']},'$action','${REQUEST['tRPZIOCType']}',${REQUEST['tRPZAXFR']},".
           "${REQUEST['tRPZIXFR']},${REQUEST['tRPZDisabled']});";
      if (DB_execute($db,$sql)) {
        //safest way to get id?
        $rpzid=DB_selectArray($db,"select max(rowid) as rowid from rpzs where user_id=$USERID and name='${REQUEST['tRPZName']}'")[0]['rowid'];
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
          $sql.="insert into rpzs_notify values($rpzid,$USERID,'$ip');\n";
        };        
        if (DB_execute($db,$sql)) {          
          $response='{"status":"ok"}';
        }else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      }else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL
      break;
    case "PUT rpzs":

      $tkeys_new=DB_selectArray($db,"select rowid from tkeys where user_id=$USERID and rowid in (".implode(",",json_decode($REQUEST['tRPZTKeys'])).")");
      $tkeys_old=DB_selectArray($db,"select rowid,tkey_id from rpzs_tkeys where user_id=$USERID and rpz_id=${REQUEST['tRPZId']}");
      $servers_new=DB_selectArray($db,"select rowid from servers where user_id=$USERID and rowid in (".implode(",",json_decode($REQUEST['tRPZSrvs'])).")");
      $servers_old=DB_selectArray($db,"select rowid,server_id from rpzs_servers where user_id=$USERID and rpz_id=${REQUEST['tRPZId']}");
      $sources_new=DB_selectArray($db,"select rowid from sources where user_id=$USERID and rowid in (".implode(",",json_decode($REQUEST['tRPZSrc'])).")");
      $sources_old=DB_selectArray($db,"select rowid,source_id from rpzs_sources where user_id=$USERID and rpz_id=${REQUEST['tRPZId']}");
      $whlists_new=DB_selectArray($db,"select rowid from whitelists where user_id=$USERID and rowid in (".implode(",",json_decode($REQUEST['tRPZWL'])).")");
      $whlists_old=DB_selectArray($db,"select rowid,whitelist_id from rpzs_whitelists where user_id=$USERID and rpz_id=${REQUEST['tRPZId']}");

      if (in_array($REQUEST['tRPZAction'],["nx","nod","pass","drop","tcp"])) $action=$REQUEST['tRPZAction'];else $action=$REQUEST['tRPZActionCustom'];

      $sql='';
      foreach($tkeys_old as $tkey){if ($k=array_search($tkey['tkey_id'],$tkeys_new)) unset($tkeys_new[$k]); else $sql.="delete from rpzs_tkeys where rowid=${tkey['rowid']};\n";};       
      foreach($tkeys_new as $tkey){$sql.="insert into rpzs_tkeys values(${REQUEST['tRPZId']},$USERID,${tkey['rowid']});\n";};

      foreach($servers_old as $item){if ($k=array_search($item['server_id'],$tkeys_new)) unset($servers_new[$k]); else $sql.="delete from rpzs_servers where rowid=${item['rowid']};\n";};       
      foreach($servers_new as $item){$sql.="insert into rpzs_servers values(${REQUEST['tRPZId']},$USERID,${item['rowid']});\n";};

      foreach($sources_old as $item){if ($k=array_search($item['source_id'],$tkeys_new)) unset($sources_new[$k]); else $sql.="delete from rpzs_sources where rowid=${item['rowid']};\n";};       
      foreach($sources_new as $item){$sql.="insert into rpzs_sources values(${REQUEST['tRPZId']},$USERID,${item['rowid']});\n";};

      foreach($whlists_old as $item){if ($k=array_search($item['whitelist_id'],$tkeys_new)) unset($whlists_new[$k]); else $sql.="delete from rpzs_whitelists where rowid=${item['rowid']};\n";};       
      foreach($whlists_new as $item){$sql.="insert into rpzs_whitelists values(${REQUEST['tRPZId']},$USERID,${item['rowid']});\n";};

      $ip_new=array_unique(json_decode($REQUEST['tRPZNotify']));
      $ip_old=DB_selectArray($db,"select rowid, notify from rpzs_notify where user_id=$USERID and rpz_id=${REQUEST['tRPZId']}");

      foreach($ip_old as $ip){
        if ($k=array_search($ip['notify'],$ip_new)) unset($ip_new[$k]); else $sql.="delete from rpzs_notify where rowid=${ip['rowid']};\n";
      };       
      foreach($ip_new as $ip){
        $sql.="insert into rpzs_notify values(${REQUEST['tRPZId']},$USERID,'$ip');\n";
      };
      
      $sql.="update rpzs set name='${REQUEST['tRPZName']}', soa_refresh=${REQUEST['tRPZSOA_Refresh']}, soa_update_retry=${REQUEST['tRPZSOA_UpdRetry']},".
            "soa_expiration=${REQUEST['tRPZSOA_Exp']}, soa_nx_ttl=${REQUEST['tRPZSOA_NXTTL']}, cache=${REQUEST['tRPZCache']}, wildcard=${REQUEST['tRPZWildcard']},".
            "action='$action',ioc_type='${REQUEST['tRPZIOCType']}',axfr_update=${REQUEST['tRPZAXFR']},ixfr_update=${REQUEST['tRPZIXFR']},".
            "disabled=${REQUEST['tRPZDisabled']} where user_id=$USERID and rowid=${REQUEST['tRPZId']}";
      
      if (DB_execute($db,$sql)) $response='{"status":"ok"}'; else $response='{"status":"failed", "sql":"'.$sql.'"}'; //TODO remove SQL

      break;
    case "DELETE rpzs":
      $sql="delete from rpzs_notify where user_id=$USERID and rpz_id=${REQUEST['rowid']};\n";
      $sql.="delete from rpzs_tkeys where user_id=$USERID and rpz_id=${REQUEST['rowid']};\n";
      $sql.="delete from rpzs_servers where user_id=$USERID and rpz_id=${REQUEST['rowid']};\n";
      $sql.="delete from rpzs_whitelists where user_id=$USERID and rpz_id=${REQUEST['rowid']};\n";
      $sql.="delete from rpzs_sources where user_id=$USERID and rpz_id=${REQUEST['rowid']};\n";
      $sql.="delete from rpzs where user_id=$USERID and rowid=${REQUEST['rowid']};\n";
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

      break;

    case "POST publishcfg": //save ioc2rpz configuration and reconfigure service
      //support local file via local script. Here just set a relevant field in DB.
      //support S3. Upload file to S3 and send reconfugure signal

      break;
    default:
      $response='{"status":"failed", "reason":"not supported"}';
endswitch;

echo $response."\n";

DB_close($db);

?>