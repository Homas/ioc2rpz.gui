<?php
#(c) Vadim Pavlov 2018-2020
#ioc2rpz GUI DB upgrade script

define("IO2PATH", "/opt/ioc2rpz.gui"); #/opt/ioc2rpz.gui
require IO2PATH."/www/io2vars.php";

define("DBVersion", 1);

function upgradeSQLiteDB($DBF){
  $db = new SQLite3($DBF);
  $db_version=DB_selectArray($db,"PRAGMA user_version")[0]["user_version"];
  $sql="";
  switch ($db_version) {
      case 0:
        $sql.="PRAGMA user_version=".DBVersion.";";
        $sql.="alter table whitelists add column userid text default NULL;";
        $sql.="alter table whitelists add column max_ioc integer default 0;";
        $sql.="alter table whitelists add column hotcache_time integer default 900;";
        $sql.="alter table whitelists add column hotcacheixfr_time integer default 0;";
        $sql.="alter table sources add column userid text default NULL;";
        $sql.="alter table sources add column max_ioc integer default 0;";
        $sql.="alter table sources add column hotcache_time integer default 900;";
        $sql.="alter table sources add column hotcacheixfr_time integer default 0;";
  };
  if ($db_version != DBVersion){
    echo "Upgrading DB from version $db_version to ".DBVersion;
    DB_execute($db,$sql);
  };

  DB_close($db);
};

upgradeSQLiteDB(IO2PATH."/www/".DBFile);

?>
