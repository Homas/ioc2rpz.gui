<?php
#(c) Vadim Pavlov 2018
#ioc2rpz GUI DB init

define("IO2PATH", "/opt/ioc2rpz.gui"); #/opt/ioc2rpz.gui
const DBFile=IO2PATH."/www/io2cfg/io2db.sqlite";

require IO2PATH."/www/io2vars.php";

define("localCFGPath", IO2PATH."/export-cfg"); #/opt/ioc2rpz.gui


$db=DB_open();

$serv_upd=DB_selectArray($db,"select rowid,user_id,name,ip,disabled,stype,URL from servers where publish_upd=1");
foreach($serv_upd as $srv){
  $cfg=genConfig($db,$srv['user_id'],$srv['rowid']);
  switch ($srv['stype']){
    case 0: #local
      $fn=localCFGPath."/".($srv['URL']?$srv['URL']:($srv['name'].".cfg"));
      file_put_contents($fn,$cfg);
      if ($srv['ip'] and !$srv['disabled'] ) {
        $res=`/usr/bin/dig \@${srv['ip']} +tries=1 +time=1 ioc2rpz-reload-cfg TXT -c CHAOS`;
        echo $res;
      };
      #TODO check the response
      #ioc2rpz-reload-cfg.	900	IN	TXT	"ioc2rpz configuration was reloaded"
      
      $sql="update servers set publish_upd=0 where rowid=${srv['rowid']}";
      DB_execute($db,$sql); //TODO error handling
      break;
    case 1: #SFTP/SCP
      break;
    case 2: #AWS S3
      break;
  }
};

DB_close($db);


?>