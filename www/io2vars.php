<?php
#(c) Vadim Pavlov 2018
#ioc2rpz GUI vars

const DB="sqlite"; //for a single user it is Ok
const DBFile="io2cfg/io2db.sqlite";
const DBCreateIfNotExists=true;

const ioc2rpzConf="io2cfg";

$USERID=1;

function checkDB(){
  
};

function initSQLiteDB(){
  $db = new SQLite3(DBFile);
  ###
  ###create tables
  ###

  
  //TODO create unique index name+userid
  //TODO move to a separate file

  //TODO enable foreign keys
  //PRAGMA foreign_keys = ON;

  #create users table
  $sql="create table if not exists users (name text, password text, salt text);";
  $db->exec($sql);
  
  #create tkeys table
  $sql="create table if not exists tkeys (user_id integer, name text, alg text, tkey text, mgmt integer, foreign key(user_id) references users(rowid));";
  $db->exec($sql);
  
  #create servers table, tkeys and mgmt_ips
  $sql="create table if not exists servers (user_id integer, name text uniq, ip text uniq, ns text, email text, mgmt integer, disabled integer, foreign key(user_id) references users(rowid));".
       "create table if not exists servers_tsig (server_id integer, user_id integer, tsig_id integer, foreign key(tsig_id) references tkeys(rowid), foreign key(user_id) references users(rowid), foreign key(server_id) references servers(rowid));\n".
       "create table if not exists mgmt_ips (server_id integer, user_id integer, mgmt_ip text, foreign key(user_id) references users(rowid), foreign key(server_id) references servers(rowid));";
  $db->exec($sql);
  
  #create whitelists table
  $sql="create table if not exists whitelists (user_id integer, name text, url text, regex text, foreign key(user_id) references users(rowid));";
  $db->exec($sql);

  #create sources table
  $sql="create table if not exists sources (user_id integer, name text, url text, url_ixfr text, regex text, foreign key(user_id) references users(rowid));";
  $db->exec($sql);

  #create rpzs table, servers, whitelists, sources, tkeys, notify
  $sql="create table if not exists rpzs (user_id integer, name text, soa_refresh integer, soa_update_retry integer, soa_expiration integer, soa_nx_ttl integer, cache integer, wildcard integer, action text, ioc_type text, axfr_update integer, ixfr_update integer, disabled integer, foreign key(user_id) references users(rowid));".
       "create table if not exists rpzs_servers (rpz_id integer, user_id integer, server_id integer, foreign key(rpz_id) references rpzs(rowid), foreign key(user_id) references users(rowid), foreign key(server_id) references servers(rowid));".
       "create table if not exists rpzs_tkeys (rpz_id integer, user_id integer, tkey_id integer, foreign key(rpz_id) references rpzs(rowid), foreign key(user_id) references users(rowid), foreign key(tkey_id) references tkeys(rowid));".
       "create table if not exists rpzs_whitelists (rpz_id integer, user_id integer, whitelist_id integer, foreign key(rpz_id) references rpzs(rowid), foreign key(user_id) references users(rowid), foreign key(whitelist_id) references whitelists(rowid));".
       "create table if not exists rpzs_sources (rpz_id integer, user_id integer, source_id integer, foreign key(rpz_id) references rpzs(rowid), foreign key(user_id) references users(rowid), foreign key(source_id) references sources(rowid));".
       "create table if not exists rpzs_notify (rpz_id integer, user_id integer, notify text, foreign key(rpz_id) references rpzs(rowid), foreign key(user_id) references users(rowid));";
  $db->exec($sql);
  
  ###insert sample data assuming that all tables were created empty
  $sql='insert into users values("io2admin","","");';
  $db->exec($sql);

  $sql='insert into tkeys values(1,"tkey_mgmt_1","md5","TSIG",1);'.
       'insert into tkeys values(1,"tkey_1","md5","TSIG",0);';
  $db->exec($sql);
  
  $sql='insert into servers values(1,"server_1","127.0.0.1","ns1.ioc2rpz.localdomain","support.ioc2rpz.localdomain",1,0);'.
       'insert into servers_tsig values(1,1,1);'.
       'insert into mgmt_ips values(1,1,"127.0.0.1");';
  $db->exec($sql);

  $sql='insert into whitelists values(1,"whitelist_1","file:'.ioc2rpzConf.'/whitelist1.txt","none");';
  $db->exec($sql);

//  $sql='insert into sources values(1,"dns-bh","http://mirror1.malwaredomains.com/files/spywaredomains.zones","[:AXFR:]",\'^zone \\\\\"([A-Za-z0-9\\\\-\\\\._]+)\\\\\".*$\');';
  $sql='insert into sources values(1,"dns-bh","http://mirror1.malwaredomains.com/files/spywaredomains.zones","[:AXFR:]",\'^zone \"([A-Za-z0-9\-\._]+)\".*$\');';
  $db->exec($sql);

  $sql='insert into rpzs values(1,"dns-bh.ioc2rpz",86400,3600,2592000,7200,1,1,"nx","m",604800,86400,0);'.
       'insert into rpzs_servers values(1,1,1);'.
       'insert into rpzs_whitelists values(1,1,1);'.
       'insert into rpzs_sources values(1,1,1);'.
       'insert into rpzs_notify values(1,1,"127.0.0.1");'.
       'insert into rpzs_tkeys values(1,1,2);';
  $db->exec($sql);

  #close DB
  DB_close($db);
};

function DB_open() 
{ 
  switch (DB){
    case "sqlite":
      $db = new SQLite3(DBFile); 
    break;
  }
  return $db; 
}

function DB_close($db) 
{ 
  switch (DB){
    case "sqlite":
      $db->close();
    break;
  }
}

function DB_select($db,$sql){
  switch (DB){
    case "sqlite":
      $result=$db->query($sql);
    break;
  }
  return $result;
};

function DB_selectArray($db,$sql){
  switch (DB){
    case "sqlite":
      $data=[];
      $result=$db->query($sql);
      while ($row=$result->fetchArray(SQLITE3_ASSOC)){
        $data[]=$row;
      };
    break;
  }
  return $data;
};


function DB_fetchArray($result){
  switch (DB){
    case "sqlite":
      $data=$result->fetchArray(SQLITE3_ASSOC);
    break;
  }
  return $data;
};

function DB_execute($db,$sql){
  switch (DB){
    case "sqlite":
      $result=$db->exec($sql);
    break;
  }
  return $result;
};


?>