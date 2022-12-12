<?php
#(c) Vadim Pavlov 2018-2021
#ioc2rpz GUI DB init

define("IO2PATH", "/opt/ioc2rpz.gui"); #/opt/ioc2rpz.gui
const DBFile=IO2PATH."/www/io2cfg/io2db.sqlite";

require IO2PATH."/www/io2vars.php";

define("localCFGPath", IO2PATH."/export-cfg"); #/opt/ioc2rpz.gui


$db=DB_open();

$serv_upd=DB_selectArray($db,"select servers.rowid,servers.user_id, servers.name as sname,ip,disabled,stype,URL, tkeys.name as tname, alg, tkey, servers.custom_config from servers left join servers_tsig on servers.rowid=servers_tsig.server_id left join tkeys on servers_tsig.tsig_id=tkeys.rowid where publish_upd=1");
foreach($serv_upd as $srv){
  $cfg=genConfig($db,$srv['user_id'],$srv['rowid']);
  switch ($srv['stype']){
    case 0: #local
      $fn=localCFGPath."/".($srv['URL']?$srv['URL']:($srv['sname'].".cfg"));
      file_put_contents($fn,$cfg['cfg']);
      if ($srv['ip'] and !$srv['disabled'] ) {
        if (io2mgmt == "dns" ){
          $cmd=dig." -y hmac-${srv['alg']}:${srv['tname']}:${srv['tkey']} \@${srv['ip']} +tries=1 +time=1 ioc2rpz-reload-cfg TXT -c CHAOS";
          $res=`$cmd`;
        }else{
          $curl = curl_init("https://${srv['ip']}:".rest_mgmt_port."/api/v1.0/mgmt/reload_cfg"); #Should be FQDN in settings
          curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
          curl_setopt($curl, CURLOPT_USERPWD, "${srv['tname']}:${srv['tkey']}");
          curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, io2mgmt_verifyssl);
          curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, io2mgmt_verifyssl);
          curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($curl, CURLOPT_TIMEOUT, 1000);
          $res = curl_exec($curl);
          curl_close($curl);
        };
        echo $res;
      };
      #TODO check the response
      #ioc2rpz-reload-cfg.	900	IN	TXT	"ioc2rpz configuration was reloaded"

      $sql="update servers set publish_upd=0 where rowid=${srv['rowid']}";
      DB_execute($db,$sql); //TODO error handling
      break;
    case 1: #SFTP/SCP
      if (preg_match("/^([^\@]+)\@([^\/]+)(\/.+)$/",$srv['URL'],$url)){
        //ioc2rpz@example.com/opt/ioc2rpz/cfg/ioc2rpz.cfg
        $fn = tempnam(sys_get_temp_dir(), 'ioc2rpz_');
        file_put_contents($fn,$cfg['cfg']);
        $scp = ssh2_connect($url[2], 22);
        //keys shoud be generated by
        //ssh-keygen -f server_2_rsa -t rsa -m PEM
        //the keys should be named as "{serverName}_rsa" and "{serverName}_rsa.pub" and stored in {localCFGPath}
        if (ssh2_auth_pubkey_file($scp, $url[1],localCFGPath.'/'.$srv['sname'].'_rsa.pub',localCFGPath.'/'.$srv['sname'].'_rsa')){
          ssh2_scp_send($scp, $fn, $url[3], 0644);
          $curl = curl_init("https://${srv['ip']}:".rest_mgmt_port."/api/v1.0/mgmt/reload_cfg"); #Should be FQDN in settings
          curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
          curl_setopt($curl, CURLOPT_USERPWD, "${srv['tname']}:${srv['tkey']}");
          curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, io2mgmt_verifyssl);
          curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, io2mgmt_verifyssl);
          curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($curl, CURLOPT_TIMEOUT, 1000);
          $res = curl_exec($curl);
          curl_close($curl);
        }
        ssh2_disconnect ($scp);
        unlink($fn);
      };
      $sql="update servers set publish_upd=0 where rowid=${srv['rowid']}";
      DB_execute($db,$sql); //TODO error handling
      break;
    case 2: #AWS S3
      break;
  }
};

DB_close($db);


?>
