<?php
#(c) Vadim Pavlov 2018-2020
#ioc2rpz GUI DB init script

#chmod 664 /srv/www/io2cfg/io2db.sqlite
#sudo chown homas:_apache /srv/www/io2cfg/io2db.sqlite

define("IO2PATH", "/opt/ioc2rpz.gui"); #/opt/ioc2rpz.gui
require IO2PATH."/www/io2vars.php";

function initSQLiteDB($DBF){
  $db = new SQLite3($DBF);
  ###
  ###create tables
  ###
  #2020-06-08
  #create table if not exists rpidns (user_id integer, name text, create_time DATETIME DEFAULT CURRENT_TIMESTAMP, rpidns_uuid text, commentary text, configuration json, foreign key(user_id) references users(rowid));
  #
	#2019-06-15
	/*
	ALTER TABLE servers ADD column custom_config text;
	create table if not exists tkeys_groups (user_id integer, group_name text, foreign key(user_id) references users(rowid));
	create table if not exists tkeys_tsig_groups (tsig_id integer, user_id integer, tsig_group_id integer, foreign key(tsig_group_id) references tkeys_groups(rowid), foreign key(user_id) references users(rowid), foreign key(tsig_id) references tkeys(rowid));
	create table if not exists servers_tsig_groups (server_id integer, user_id integer, tsig_group_id integer, foreign key(tsig_group_id) references tkeys_groups(rowid), foreign key(user_id) references users(rowid), foreign key(server_id) references servers(rowid));
	create table if not exists rpzs_tkeys_groups (rpz_id integer, user_id integer, tkey_group_id integer, foreign key(rpz_id) references rpzs(rowid), foreign key(user_id) references users(rowid), foreign key(tkey_group_id) references tkeys_groups(rowid));		

	insert into tkeys_groups values(1,"mgmt"),(1,"public");

	*/
	
  #2019-03-01
  #ALTER TABLE servers ADD column certfile text;
  #ALTER TABLE servers ADD column keyfile text;
  #ALTER TABLE servers ADD column cacertfile text;
  #update servers set certfile="", keyfile="", cacertfile="";
  
  //TODO create unique index name+userid
  //TODO move to a separate file

  //TODO enable foreign keys
  //PRAGMA foreign_keys = ON;

  #create users table
  $sql="create table if not exists users (name text, password text, salt text, perm integer, loginattempts integer, lastlogin integer, lastfailedlogin integer);";
  $db->exec($sql);
  
  #create tkeys table
  $sql="create table if not exists tkeys (user_id integer, name text, alg text, tkey text, mgmt integer, foreign key(user_id) references users(rowid));";
  $db->exec($sql);

  #create tkeys_groups table
  $sql="create table if not exists tkeys_groups (user_id integer, group_name text, foreign key(user_id) references users(rowid));".
			 "create table if not exists tkeys_tsig_groups (tsig_id integer, user_id integer, tsig_group_id integer, foreign key(tsig_group_id) references tkeys_groups(rowid), foreign key(user_id) references users(rowid), foreign key(tsig_id) references tkeys(rowid));\n";
  $db->exec($sql);
  
  #create servers table, tkeys and mgmt_ips
  //stype 0 - local, 1  - sftp/scp, 2 - AWS S3
  $sql="create table if not exists servers (user_id integer, name text, ip text, pub_ip text uniq, ns text, email text, mgmt integer, disabled integer, stype integer, URL text, cfg_updated integer, publish_upd integer, certfile text, keyfile text, cacertfile text, custom_config text,  foreign key(user_id) references users(rowid));".
       "create table if not exists servers_tsig (server_id integer, user_id integer, tsig_id integer, foreign key(tsig_id) references tkeys(rowid), foreign key(user_id) references users(rowid), foreign key(server_id) references servers(rowid));\n".
			 "create table if not exists servers_tsig_groups (server_id integer, user_id integer, tsig_group_id integer, foreign key(tsig_group_id) references tkeys_groups(rowid), foreign key(user_id) references users(rowid), foreign key(server_id) references servers(rowid));\n".
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
       "create table if not exists rpzs_tkeys_groups (rpz_id integer, user_id integer, tkey_group_id integer, foreign key(rpz_id) references rpzs(rowid), foreign key(user_id) references users(rowid), foreign key(tkey_group_id) references tkeys_groups(rowid));".
			 "create table if not exists rpzs_whitelists (rpz_id integer, user_id integer, whitelist_id integer, foreign key(rpz_id) references rpzs(rowid), foreign key(user_id) references users(rowid), foreign key(whitelist_id) references whitelists(rowid));".
       "create table if not exists rpzs_sources (rpz_id integer, user_id integer, source_id integer, foreign key(rpz_id) references rpzs(rowid), foreign key(user_id) references users(rowid), foreign key(source_id) references sources(rowid));".
       "create table if not exists rpzs_notify (rpz_id integer, user_id integer, notify text, foreign key(rpz_id) references rpzs(rowid), foreign key(user_id) references users(rowid));"; // index on notify
  $db->exec($sql);
  
  #RpiDNS
  $sql="create table if not exists rpidns (user_id integer, name text, create_time DATETIME DEFAULT CURRENT_TIMESTAMP, rpidns_uuid text, commentary text, configuration json, foreign key(user_id) references users(rowid));";
  $db->exec($sql);

  
  
  # Sample data
  $sql='insert into tkeys_groups values(1,"mgmt"),(1,"public");';
  $db->exec($sql);
  
  $sql='insert into tkeys values(1,"tkey_mgmt_1","md5","'.base64_encode(random_bytes(16)).'",1);'.
       'insert into tkeys values(1,"tkey_1","md5","'.base64_encode(random_bytes(16)).'",0);';
  $db->exec($sql);

  $sql='insert into servers values(1,"server_1","127.0.0.1","127.0.0.1","ns1.ioc2rpz.local","support@ioc2rpz.local",1,0,0,"ioc2rpz.conf",1,0,"","","","");'.
       'insert into servers_tsig values(1,1,1);'.
       'insert into mgmt_ips values(1,1,"127.0.0.1");';
  $db->exec($sql);

  $sql='insert into whitelists values(1,"whitelist_1","file:/opt/ioc2rpz/cfg/whitelist1.txt","none");';
  $db->exec($sql);

  $sql='insert into sources values(1,"dns-bh","http://mirror1.malwaredomains.com/files/spywaredomains.zones","[:AXFR:]",\'^zone \"([A-Za-z0-9\-\._]+)\".*$\');'.
       'insert into sources values(1,"notracking_hosts","https://raw.githubusercontent.com/notracking/hosts-blocklists/master/hostnames.txt","[:AXFR:]","^0\.0\.0\.0 ([A-Za-z0-9\._\-]+[A-Za-z])$");'.
       'insert into sources values(1,"notracking_domains","https://raw.githubusercontent.com/notracking/hosts-blocklists/master/domains.txt","[:AXFR:]","^address=\/([A-Za-z0-9\._\-]+[A-Za-z])\/0\.0\.0\.0$");';
  $db->exec($sql);
  
  $sql='insert into rpzs values(1,"dns-bh.ioc2rpz",86400,3600,2592000,7200,1,1,"nxdomain","mixed",604800,86400,0);'.
       'insert into rpzs values(1,"notracking.ioc2rpz",86400,3600,2592000,7200,1,1,"nxdomain","mixed",604800,86400,0);'.
       'insert into rpzs_servers values(1,1,1);'.
       'insert into rpzs_servers values(2,1,1);'.
       'insert into rpzs_whitelists values(1,1,1);'.
       'insert into rpzs_whitelists values(2,1,1);'.
       'insert into rpzs_sources values(1,1,1);'.
       'insert into rpzs_sources values(2,1,2);'.
       'insert into rpzs_sources values(2,1,3);'.
       'insert into rpzs_notify values(1,1,"127.0.0.1");'.
       'insert into rpzs_tkeys values(1,1,2);'.
       'insert into rpzs_tkeys values(2,1,2);';
  $db->exec($sql);

  #close DB
  DB_close($db);
};


initSQLiteDB(IO2PATH."/www/".DBFile);

?>