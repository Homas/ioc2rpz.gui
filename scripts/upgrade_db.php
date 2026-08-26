<?php
#(c) Vadim Pavlov 2018-2023
#ioc2rpz GUI DB upgrade script

define("IO2PATH", "/opt/ioc2rpz.gui"); #/opt/ioc2rpz.gui
require IO2PATH."/www/io2vars.php";

define("DBVersion", 4);

function upgradeSQLiteDB($DBF){
  $db = new SQLite3($DBF);
  $db_version=DB_selectArray($db,"PRAGMA user_version")[0]["user_version"];
  $sql="";
  switch ($db_version) {
      case 0:
        $sql.="alter table whitelists add column userid text default NULL;";
        $sql.="alter table whitelists add column max_ioc integer default 0;";
        $sql.="alter table whitelists add column hotcache_time integer default 900;";
        $sql.="alter table whitelists add column hotcacheixfr_time integer default 0;";
        $sql.="alter table sources add column userid text default NULL;";
        $sql.="alter table sources add column max_ioc integer default 0;";
        $sql.="alter table sources add column hotcache_time integer default 900;";
        $sql.="alter table sources add column hotcacheixfr_time integer default 0;";
      case 1:
        $sql.="alter table whitelists ADD column ioc_type text default 'mixed';";
        $sql.="alter table whitelists ADD column keep_in_cache integer default 0;";
        $sql.="alter table sources ADD column ioc_type text default 'mixed';";
        $sql.="alter table sources ADD column keep_in_cache integer default 0;";
      case 2:
        $sql.="alter table servers add column track_default text default 'off';";
        $sql.="alter table rpzs add column track_sources text default 'Inherit';";
      case 3:
        #DNS rate limits. NULL means "inherit": the srv value falls back to the
        #ioc2rpz compile-time default, the rpz value falls back to the srv value.
        #Existing rows keep NULL, so their generated configuration is unchanged.
        $sql.="alter table servers add column rl_window integer default NULL;";
        $sql.="alter table servers add column rl_max_requests integer default NULL;";
        $sql.="alter table servers add column rl_max_unknown_requests integer default NULL;";
        $sql.="alter table rpzs add column rl_window integer default NULL;";
        $sql.="alter table rpzs add column rl_max_requests integer default NULL;";
      default:
        $sql.="PRAGMA user_version=".DBVersion.";";
  };
  if ($db_version != DBVersion){
    echo "Upgrading DB from version $db_version to ".DBVersion;
    DB_execute($db,$sql);
  };

  DB_close($db);
};

upgradeSQLiteDB(IO2PATH."/www/".DBFile);

?>
