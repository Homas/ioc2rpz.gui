<?php

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

function getProto(){
  return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
};

function secHeaders(){
    header("Content-Security-Policy: frame-ancestors 'self';");
};


function uuid(){
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); 
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); 
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
};


function	generate_install_script($db, $uuid){ 
 $sql="select rpidns_uuid, rpi_name as name, comment, model, dns, updconf, feed as rpz, action, '' description, 'w' as type, pub_ip as ip, tkey_name, alg as tkey_alg, tkey,logging, logging_host, redirect, redirect_cname, dns_type, dns_ipnet, min(tkeys_rowid), min(srv_rowid),
case 
when action in ('disabled','passthrunolog','passthru') and ioc_type in ('fqdn','mixed') then 'w' 
when action in ('disabled','passthrunolog','passthru') and ioc_type='ip' then 'v' 
when action in ('cname','nxdomain','nodata', 'drop') and ioc_type='fqdn' then 'd' 
when action in ('cname','nxdomain','nodata', 'drop') and ioc_type='mixed' then 'm' 
when action in ('cname','nxdomain','nodata', 'drop') and ioc_type='ip' then 'i' 
end as action_type
 from (select *, tk.name as tkey_name, tk.rowid as tkeys_rowid, s.rowid as srv_rowid  from (SELECT name as rpi_name, commentary as comment, rpidns_uuid, json_extract(configuration,'$.model') as model, json_extract(configuration,'$.dns') as dns, json_extract(configuration,'$.updconf') as updconf, json_extract(configuration,'$.redirect') as redirect, json_extract(configuration,'$.redirect_cname') as redirect_cname, json_extract(configuration,'$.logging') as logging, json_extract(configuration,'$.logging_host') as logging_host, json_extract(configuration,'$.dns_type') as dns_type, json_extract(configuration,'$.dns_ipnet') as dns_ipnet,json_extract(value,'$.feed') as feed,json_extract(value,'$.action') as action FROM rpidns, json_each(json_extract(rpidns.configuration,'$.rpz')) where rpidns_uuid='$uuid') as f left join rpzs r on f.feed=r.name left join rpzs_servers rs on r.rowid=rs.rpz_id left join servers s on s.rowid=rs.server_id left join rpzs_tkeys rtk on r.rowid=rtk.rpz_id left join tkeys tk on tk.rowid=rtk.tkey_id where s.disabled=0 and tk.mgmt=0) group by rpidns_uuid, rpz
 order by CASE type WHEN 'w' THEN 1 WHEN 'd' THEN 2 WHEN 'v' THEN 2 WHEN 'm' THEN 3 WHEN 'i' THEN 4 ELSE 5 END;";
 
 
  $rpz_feeds=DB_selectArray($db,$sql);  
  //var_dump($rpz_feeds);
 
	$configure_rsyslog=false;

	$script = <<<'EOD'
#!/bin/bash

##check bash

if [[ $EUID -ne 0 ]]; then
    echo "The script requires root priviligies."
    echo "Run 'sudo bash $0'"
    exit 1
fi

RELEASE=`lsb_release -a 2>&1 | grep -e "Distributor" -e "Release" | awk -F":" '{print $2}' | tr -d '\n\r\t\s'`
#Rapbian9.11
#Ubuntu18.04

if [ "$RELEASE" != "Rapbian10" ]; then #[ "$RELEASE" != "Rapbian9.11" ] because we need Bind 9.11 features
    echo "$RELEASE is not supported!"
    echo "Please install Rapbian 10"
    exit 1
fi

date '+%Y-%m-%d %H:%M';echo -e "#########\nStarting RpiDNS installation\n#########\n"

echo -e "#########\nUpdate timezone to UTC\n#########\n"
timedatectl set-timezone UTC
timedatectl set-ntp true

EOD;

$script .= "\n\nDNSSERV=\"".$rpz_feeds[0]['dns']."\"\nUPDCFG=".$rpz_feeds[0]['updconf']."\n"."HOSTNAME=\"".$rpz_feeds[0]['name'].".rpidns.ioc2rpz.local\"\n";

$script .= <<<'EOD'

SYSUSER=`who am i | awk '{print $1}'`

WDIR=`pwd`

date '+%Y-%m-%d %H:%M';echo -e "#########\nUpgrading packages\n#########\n"

sed -i "s/#deb/deb/" /etc/apt/sources.list
apt-get -q -y update && apt-get -q -y upgrade && apt-get -q -y install ntpdate


if [ "${DNSSERV,,}" == "bind" ]; then
#bind9
date '+%Y-%m-%d %H:%M';echo -e "#########\nInstalling bind\n#########\n"

apt-get -q -y install bind9 dnsutils
#rndc-confgen -a

cp /etc/bind/named.conf.options /etc/bind/named.conf.options.backup.`date '+%Y%m%d%H%M'`

cat > /etc/bind/db.empty.pi << EOF
\$TTL	3600
@	IN	SOA	$HOSTNAME. root.$HOSTNAME. (
			      1		; Serial
          3600	; Refresh
          3600	; Retry
			2419200		; Expire
          300 )	; Negative Cache TTL
;
@	IN	NS	$HOSTNAME.
EOF

cat > /etc/bind/named.conf.options << EOF
##
##configuration generated for ioc2rpz.net
##

options {
	directory "/var/cache/bind";
	dnssec-validation auto;

	auth-nxdomain no;    # conform to RFC1035
	listen-on { any; };
	listen-on-v6 { any; };
	allow-query { any; };
	bindkeys-file "/etc/bind/bind.keys";
	empty-zones-enable yes;

  recursion yes;
  response-policy {
EOD;

	$feeds="\nzone \"wl.ioc2rpz.local\" policy passthru log no; #local whitelist\n";
	$feeds_files="\n";
	$tkey='key "'.$rpz_feeds[0]['tkey_name'].'" {algorithm '.$rpz_feeds[0]['tkey_alg'].'; secret "'.$rpz_feeds[0]['tkey']."\";};\n";
	$ztype="w";
	$action="nxdomain";
	$zone_files="";
	$rpiname=$rpz_feeds[0]['name'].".rpidns.ioc2rpz.local";
  $dns_type=$rpz_feeds[0]['dns_type'];
  $dns_ipnet=$rpz_feeds[0]['dns_ipnet'];

	$redirect_default="cname ".($rpz_feeds[0]['redirect'] == 'default'? $rpiname:$rpz_feeds[0]['redirect_cname']);
	foreach ($rpz_feeds as $rpz){
		#$feeds.="#".$ztype.$rpz['type']."\n";
		if ($ztype != $rpz['action_type']){
			switch ($ztype.$rpz['action_type']){
                
				case "wd": #w wl > d block> v wl ip > m mixed > i ip  
				# w -> disabled | passthrunolog | passthru + fqdn | mixed
				# d -> cname | nxdomain | nodata | drop + fqdn
				# v -> disabled | passthrunolog | passthru + ip
				# m -> cname | nxdomain | nodata | drop + mixed
				# i -> cname | nxdomain | nodata | drop + ip 
        # disabled > passthrunolog | passthru > cname | nxdomain | nodata | drop
				# fqdn > mixed > ip
				# disabled.fqdn > passthrunolog.fqdn | passthru.fqdn
					$feeds.="zone \"bl.ioc2rpz.local\" policy $redirect_default;#local blacklist\n";
					break;
				case "wv":
				case "wm":
					$feeds.="zone \"bl.ioc2rpz.local\" policy $redirect_default;#local blacklist\n";
					$feeds.="zone \"wl-ip.ioc2rpz.local\" policy passthru log no;#local whitelist ip-based\n";
					break;
				case "wi":
					$feeds.="zone \"bl.ioc2rpz.local\" policy $redirect_default;#local blacklist\n";
					$feeds.="zone \"wl-ip.ioc2rpz.local\" policy passthru log no;#local whitelist ip-based\n";
					$feeds.="zone \"bl-ip.ioc2rpz.local\" policy $redirect_default;#local blacklist ip-based\n";
					break;
				case "dv":
				case "dm":
					$feeds.="zone \"wl-ip.ioc2rpz.local\" policy passthru log no;#local whitelist ip-based\n";
					break;
				case "di":
					$feeds.="zone \"wl-ip.ioc2rpz.local\" policy passthru log no;#local whitelist ip-based\n";
					$feeds.="zone \"bl-ip.ioc2rpz.local\" policy $redirect_default;#local blacklist ip-based\n";
					break;
				case "vm":
				case "vi":
				//case "mi":
					$feeds.="zone \"bl-ip.ioc2rpz.local\" policy $redirect_default;#local blacklist ip-based\n";
					break;
			};
			$ztype = $rpz['action_type'];
		};

		$redirect=$rpz['redirect'] == 'default'? $rpiname :$rpz['redirect_cname'];
		$action = $rpz['action'] == 'cname'? 'cname '.$redirect:$rpz['action'];
		$feeds .= 'zone "'.$rpz['rpz'].'" policy '.$action.";#".$rpz['description']."\n\n";

#		$feeds .= 'zone "'.$rpz['rpz'].'" policy '.(($rpz['type']=='v' or $rpz['type']=='w')?"passthru log no":"nxdomain").";#".$rpz['description']."\n\n";
		$feeds_files .= "zone \"${rpz['rpz']}\" {type slave; file \"/var/cache/bind/${rpz['rpz']}\"; masters {".$rpz['ip']." key \"${rpz['tkey_name']}\";};};\n\n";
		$zone_files.='[ ! -f /var/cache/bind/'.$rpz['rpz'].' ] && cp /etc/bind/db.empty.pi /var/cache/bind/'.$rpz['rpz']."\n";
	};
	$script .= $feeds;

		####FQDN whitelists
		#wl.ioc2rpz.local
    ####FQDN only zones 
		#add local BL
		####IP whitelists 
		#add local WL-IP
    ####Mixed zones 
    ####IP only zones 
		#add local BL-IP
		
	$script .= <<<'EOD'
  } max-policy-ttl 30 qname-wait-recurse no break-dnssec yes;

};

logging {
    channel bind_log {
        file "/opt/rpidns/logs/bind.log" versions 10 size 20m;
        severity info;
        print-time yes;
        print-severity yes;
        print-category yes;
    };
    channel bind_qlog {
        file "/opt/rpidns/logs/bind_queries.log" versions 10 size 20m;
        severity info;
        print-time yes;
        print-severity yes;
        print-category yes;
    };
    channel rpz_log {
        file "/opt/rpidns/logs/bind_rpz.log" versions 10 size 20m;
        print-time yes;
        print-category yes;
        print-severity yes;
        severity info;
    };

    channel default_syslog {print-category yes; syslog local4; severity info;};
    category default {default_syslog;};
    category default {bind_log;};
    category queries {bind_qlog;};
    category rpz {bind_qlog;};
    category rpz {rpz_log;};
    category resolver {bind_qlog;};
EOD;

	if ($rpz['logging']=='forward'){
		$configure_rsyslog=$rpz['logging_host'];
		$script .= "
    category rpz {default_syslog;};
    category queries {default_syslog;};
   ";	
	}
  
  $script .="};
";
  
  $script .='
acl "allow_update" {
  localhost;
  '.(($dns_type=='primary' and $dns_ipnet!='')?$dns_ipnet.";":'').'
};
';

if ($dns_type=='primary') $script .= <<<'EOD'

zone "wl.ioc2rpz.local"{ #Whitelisted domains
  type master;
  notify yes;
	allow-update { allow_update; };
  file "/var/cache/bind/wl.ioc2rpz.local";
};

zone "wl-ip.ioc2rpz.local"{ #whitelisted IPs
  type master;
  notify yes;
	allow-update { allow_update; };
  file "/var/cache/bind/wl-ip.ioc2rpz.local";
};


zone "bl.ioc2rpz.local"{ #blacklisted domains and clientt
  type master;
  notify yes;
	allow-update { allow_update; };
  file "/var/cache/bind/bl.ioc2rpz.local";
};

zone "bl-ip.ioc2rpz.local"{ #blacklisted IPs
  type master;
  notify yes;
	allow-update { allow_update; };
  file "/var/cache/bind/bl-ip.ioc2rpz.local";
};

zone "rpidns.ioc2rpz.local"{ #local zone
  type master;
  notify yes;
	allow-update { allow_update; };
  file "/var/cache/bind/rpidns.ioc2rpz.local";
};
EOD;
  else	$script .= '

zone "wl.ioc2rpz.local"{ #Whitelisted domains
  type slave;
	masters {'.$rpz['dns_ipnet'].';};
  file "/var/cache/bind/wl.ioc2rpz.local";
};

zone "wl-ip.ioc2rpz.local"{ #whitelisted IPs
  type slave;
	masters {'.$dns_ipnet.';};
  file "/var/cache/bind/wl-ip.ioc2rpz.local";
};


zone "bl.ioc2rpz.local"{ #blacklisted domains and clientt
  type slave;
	masters {'.$dns_ipnet.';};
  file "/var/cache/bind/bl.ioc2rpz.local";
};

zone "bl-ip.ioc2rpz.local"{ #blacklisted IPs
  type slave;
	masters {'.$dns_ipnet.';};
  file "/var/cache/bind/bl-ip.ioc2rpz.local";
};

zone "rpidns.ioc2rpz.local"{ #local zone
  type slave;
	masters {'.$dns_ipnet.';};
  file "/var/cache/bind/rpidns.ioc2rpz.local";
};
';

	$script .="\n\n$tkey\n$feeds_files\nEOF\n\n";

	$script .= "\n\n#RPZ zones\n".$zone_files;
	
	$script .= <<<'EOD'

# Local zones
[ ! -f /var/cache/bind/wl.ioc2rpz.local ] && cp /etc/bind/db.empty.pi /var/cache/bind/wl.ioc2rpz.local
[ ! -f /var/cache/bind/wl-ip.ioc2rpz.local ] && cp /etc/bind/db.empty.pi /var/cache/bind/wl-ip.ioc2rpz.local
[ ! -f /var/cache/bind/bl.ioc2rpz.local ] && cp /etc/bind/db.empty.pi /var/cache/bind/bl.ioc2rpz.local
[ ! -f /var/cache/bind/bl-ip.ioc2rpz.local ] && cp /etc/bind/db.empty.pi /var/cache/bind/bl-ip.ioc2rpz.local

### create files for secondary zones
### may be use nsupdate?

IP=`ip route get 1.2.3.4 | grep 1.2.3.4 | awk '{print $7}'`
if [ ! -f /var/cache/bind/rpidns.ioc2rpz.local ]; then
	cp /etc/bind/db.empty.pi /var/cache/bind/rpidns.ioc2rpz.local
fi

EOD;

if ($dns_type=='primary') $script .= '
### add the DNS name of the box
	sed -i "s/@	IN	NS	localhost./@	IN	NS	$HOSTNAME./g" /var/cache/bind/rpidns.ioc2rpz.local
	cat >> /var/cache/bind/rpidns.ioc2rpz.local << EOF
@	IN	A	$IP
$HOSTNAME.	IN	A	$IP
www	60	IN	A	$IP
EOF
';
else
$script .= '
/usr/bin/nsupdate -v <(echo -e "server '.$rpz['dns_ipnet'].'\nupdate add $HOSTNAME. 86400 A $IP\nupdate add rpidns.ioc2rpz.local. 86400 NS $HOSTNAME.\nsend\n")
/usr/bin/nsupdate -v <(echo -e "server '.$rpz['dns_ipnet'].'\nupdate add $HOSTNAME.wl.ioc2rpz.local 60 CNAME rpz-passthru.\nsend\n")
/usr/bin/nsupdate -v <(echo -e "server '.$rpz['dns_ipnet'].'\nupdate add bl.ioc2rpz.local. 86400 NS $HOSTNAME.\nsend\n")
/usr/bin/nsupdate -v <(echo -e "server '.$rpz['dns_ipnet'].'\nupdate add bl-ip.ioc2rpz.local. 86400 NS $HOSTNAME.\nsend\n")
/usr/bin/nsupdate -v <(echo -e "server '.$rpz['dns_ipnet'].'\nupdate add wl.ioc2rpz.local. 86400 NS $HOSTNAME.\nsend\n")
/usr/bin/nsupdate -v <(echo -e "server '.$rpz['dns_ipnet'].'\nupdate add wl-ip.ioc2rpz.local. 86400 NS $HOSTNAME.\nsend\n")
';

	$script .= <<<'EOD'

mkdir -p /opt/rpidns/logs
chown pi:bind /opt/rpidns/logs
chmod 775 /opt/rpidns/logs
touch /opt/rpidns/logs/bind.log
chmod 664 /opt/rpidns/logs/bind.log
chown bind:$SYSUSER "/opt/rpidns/logs/bind.log"

touch /opt/rpidns/logs/bind_queries.log
chmod 664 /opt/rpidns/logs/bind_queries.log
chown bind:$SYSUSER "/opt/rpidns/logs/bind_queries.log"

touch /opt/rpidns/logs/bind_rpz.log
chmod 664 /opt/rpidns/logs/bind_rpz.log
chown bind:$SYSUSER "/opt/rpidns/logs/bind_rpz.log"

chown bind:bind /var/cache/bind/*

service bind9 restart

##### update resolv.conf
echo "nameserver 127.0.0.1" > /etc/resolv.conf.head
#sed '1 s/^/nameserver 127.0.0.1\n/' /etc/resolv.conf
#sed -i 's/.*static domain_name_servers=(.*)$/static domain_name_servers=127.0.0.1 \1/g' /etc/dhcpcd.conf

###whitelisting the PI
### add WL $HOSTNAME to RPZ
###/usr/bin/nsupdate -v <(echo -e "server 127.0.0.1\nupdate add $HOSTNAME. 60 A $IP\nsend\n")
/usr/bin/nsupdate -v <(echo -e "server 127.0.0.1\nupdate add $HOSTNAME.wl.ioc2rpz.local 60 CNAME rpz-passthru.\nsend\n")
#
fi

###
### OpenResty
###
# php “fastCGI process manager”

date '+%Y-%m-%d %H:%M';echo -e "#########\nInstalling OpenResty (http server)\n#########\n"

apt-get  -q -y install php-fpm git openssl sqlite php-sqlite3 apache2-utils
sed -i 's/;cgi.fix_pathinfo=1/cgi.fix_pathinfo=0/g' /etc/php/7.3/fpm/php.ini
service php7.3-fpm restart
mkdir -p /opt/rpidns/www; chown www-data:www-data /opt/rpidns/www

## Root CA
mkdir -p /opt/rpidns/conf/ssl/CA /opt/rpidns/conf/ssl_cache /opt/rpidns/conf/ssl_sign; chmod 700 /opt/rpidns/conf/ssl; chmod 700 /opt/rpidns/conf/ssl_sign;chown www-data:www-data /opt/rpidns/conf/ssl_cache;chown www-data:www-data /opt/rpidns/conf/ssl_sign
openssl req -new -newkey rsa:2048 -sha512 -days 3650 -nodes -x509 -extensions v3_ca -keyout /opt/rpidns/conf/ssl/CA/ioc2rpzCA.pkey -out /opt/rpidns/conf/ssl/CA/ioc2rpzCA.crt -subj "/C=US/ST=CA/O=ioc2rpz community/CN=ioc2rpz private root CA"
chmod 400 /opt/rpidns/conf/ssl/CA/ioc2rpzCA.pkey
cp /opt/rpidns/conf/ssl/CA/ioc2rpzCA.crt /opt/rpidns/conf/ssl_sign/
cp /opt/rpidns/conf/ssl/CA/ioc2rpzCA.crt /opt/rpidns/www/

# Intermediate CA
mkdir -p /opt/rpidns/conf/ssl/intermediate /opt/rpidns/conf/ssl/intermediate/certs /opt/rpidns/conf/ssl/intermediate/crl /opt/rpidns/conf/ssl/intermediate/csr /opt/rpidns/conf/ssl/intermediate/newcerts /opt/rpidns/conf/ssl/intermediate/private
chmod 700 /opt/rpidns/conf/ssl/intermediate
touch /opt/rpidns/conf/ssl/intermediate/index.txt
echo 1000 > /opt/rpidns/conf/ssl/intermediate/serial

# Admin passwords
ADMPWD=`date +%s | sha256sum | base64 | head -c 32 ; echo`
/usr/bin/htpasswd -cb /opt/rpidns/conf/rpiadmin.passwd rpiadmin $ADMPWD
chown www-data:www-data /opt/rpidns/conf/rpiadmin.passwd
chmod 400 /opt/rpidns/conf/rpiadmin.passwd

echo '
[ ca ]
default_ca = CA_default
[ CA_default ]
dir            = /opt/rpidns/conf/ssl/intermediate                     # Where everything is kept
certs          = $dir/certs               # Where the issued certs are kept
crl_dir        = $dir/crl                 # Where the issued crl are kept
database       = $dir/index.txt           # database index file.
new_certs_dir  = $dir/newcerts            # default place for new certs.
certificate    = $dir/cacert.pem          # The CA certificate
serial         = $dir/serial              # The current serial number
crl            = $dir/crl.pem             # The current CRL
private_key    = $dir/private/ca.key.pem  # The private key
RANDFILE       = $dir/.rnd                # private random number file
nameopt        = default_ca
certopt        = default_ca
policy         = policy_match
default_days   = 3650
default_md     = sha256

[ policy_match ]
countryName            = optional
stateOrProvinceName    = optional
organizationName       = optional
organizationalUnitName = optional
commonName             = supplied
emailAddress           = optional

[req]
req_extensions = v3_req
distinguished_name = req_distinguished_name

[req_distinguished_name]

[v3_req]
basicConstraints = CA:TRUE
' > /opt/rpidns/conf/ssl/intermediate/openssl.conf

openssl genrsa -out /opt/rpidns/conf/ssl/intermediate/ioc2rpzInt.pkey 2048
openssl req -sha256 -new -key /opt/rpidns/conf/ssl/intermediate/ioc2rpzInt.pkey -out /opt/rpidns/conf/ssl/intermediate/ioc2rpzInt.csr -subj '/C=US/ST=CA/O=ioc2rpz community intermediate certificate/CN=ioc2rpz intermediate certificate'
openssl ca -batch -config /opt/rpidns/conf/ssl/intermediate/openssl.conf -keyfile /opt/rpidns/conf/ssl/CA/ioc2rpzCA.pkey -cert /opt/rpidns/conf/ssl/CA/ioc2rpzCA.crt -extensions v3_req -notext -md sha256 -in /opt/rpidns/conf/ssl/intermediate/ioc2rpzInt.csr -out /opt/rpidns/conf/ssl/intermediate/ioc2rpzInt.crt
cp /opt/rpidns/conf/ssl/intermediate/ioc2rpzInt.crt /opt/rpidns/conf/ssl_sign/
cp /opt/rpidns/conf/ssl/intermediate/ioc2rpzInt.pkey /opt/rpidns/conf/ssl_sign/
chown -R www-data:www-data /opt/rpidns/conf/ssl_sign

# Fallback certificate for 820 days (because of catalina and iOS11 https://support.apple.com/en-us/HT210176)
#openssl genrsa -out /opt/rpidns/conf/ssl_sign/ioc2rpz.fallback.pkey 2048
#openssl req -new -sha256 -key /opt/rpidns/conf/ssl_sign/ioc2rpz.fallback.pkey -subj "/C=US/ST=CA/O=ioc2rpz community fallback certificate" -out /opt/rpidns/conf/ssl_sign/ioc2rpz.fallback.csr
#openssl x509 -req -in /opt/rpidns/conf/ssl_sign/ioc2rpz.fallback.csr -CA /opt/rpidns/conf/ssl_sign/ioc2rpzInt.crt -signkey /opt/rpidns/conf/ssl_sign/ioc2rpzInt.pkey  -CAcreateserial -out /opt/rpidns/conf/ssl_sign/ioc2rpz.fallback.crt -days 820 -sha256

openssl req -x509 -newkey rsa:3072 -sha256 -days 820 -nodes -keyout /opt/rpidns/conf/ssl_sign/ioc2rpz.fallback.pkey -out /opt/rpidns/conf/ssl_sign/ioc2rpz.fallback.crt -subj /CN=rpidns.ioc2rpz.local -addext subjectAltName=DNS:rpidns.ioc2rpz.local
#subjectAltName=DNS:example.com,DNS:example.net,IP:10.0.0.1

# default block page. should be replaced.
mkdir -p /opt/rpidns/www
echo "<html><body><h1 style=\"text-align: center;\">The request was blocked on DNS Firewall</h1></body></html>" > /opt/rpidns/www/blocked.php

# following steps are required to build openresty. prebuilt package are availble on ioc2rpz.net for download
# apt-get install libreadline-dev libncurses5-dev libpcre3-dev libssl-dev perl make build-essential checkinstall
# wget https://www.openssl.org/source/openssl-1.1.1d.tar.gz
# tar -xvzf openssl-1.1.1d.tar.gz
# cd ~/openssl-1.1.1d
# wget https://raw.githubusercontent.com/openresty/openresty/master/patches/openssl-1.1.1c-sess_set_get_cb_yield.patch
# patch -p1 -b < openssl-1.1.1c-sess_set_get_cb_yield.patch
# cd ~
# curl https://openresty.org/download/openresty-1.15.8.1.tar.gz -o openresty-1.15.8.1.tar.gz
# tar -xvzf openresty-1.15.8.1.tar.gz
# cd openresty-1.15.8.1
# ./configure --with-pcre-jit --with-http_ssl_module --with-luajit  --with-openssl=/home/pi/openssl-1.1.1d --with-http_gunzip_module --with-threads --with-ipv6 
# make
### checkinstall
### apt-mark hold openresty
### make install
# mkdir /usr/local/openresty/site/
# PATH=/usr/local/openresty/bin:$PATH; export PATH
# sudo /usr/local/openresty/bin/opm get spacewander/lua-resty-rsa
# sudo /usr/local/openresty/bin/opm get fffonion/lua-resty-openssl

######
cd ~
curl https://raw.githubusercontent.com/Homas/ioc2rpz.gui/dev/pkg/openresty-1.15.8.1-1_armhf.deb -o openresty-1.15.8.1-1_armhf.deb
dpkg -i openresty-1.15.8.1-1_armhf.deb
apt-mark hold openresty

mkdir /usr/local/openresty/site/
PATH=/usr/local/openresty/bin:$PATH; export PATH
/usr/local/openresty/bin/opm get fffonion/lua-resty-openssl
/usr/local/openresty/bin/opm update

# we need to use a workaround until lua-resty-openssl will be updated on OPM to 0.5.3
git clone https://github.com/Homas/lua-resty-openssl.git
cp -R ~/lua-resty-openssl/lib/resty /usr/local/openresty/site/lualib/

#nginx configuration
mkdir -p /opt/rpidns/conf /opt/rpidns/logs
cat > /opt/rpidns/conf/nginx.conf << EOF

#adjust based on your HW and load
worker_processes  1;

error_log  /opt/rpidns/logs/nginx_error.log;
#error_log /dev/stdout debug;

pid        /opt/rpidns/logs/nginx.pid;

#daemon off; #daemon mode

user www-data; #user under which the process will be executed

#adjust based on your HW and load
events {
    worker_connections  1024;
}

http {
		access_log  /opt/rpidns/logs/nginx_access.log;
    include       /usr/local/openresty/nginx/conf/mime.types;
    default_type  text/html;
    server_tokens off;
		add_header X-Frame-Options SAMEORIGIN;
		add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    lua_shared_dict ioc2rpz_locks 42k;

    resolver 127.0.0.1; #DNS server

    sendfile        on;
    keepalive_timeout  65;

    init_by_lua '
    ';

    server {
        listen 0.0.0.0:80;
				listen [::]:80;	
        server_name  _;
        location / {
						#respond with the default block page
            root /opt/rpidns/www;
						fastcgi_pass unix:/run/php/php7.3-fpm.sock;
						include         /usr/local/openresty/nginx/conf/fastcgi_params;
						fastcgi_index blocked.php;
						#variables described here: https://www.nginx.com/resources/wiki/start/topics/examples/phpfcgi/
						fastcgi_param   SCRIPT_FILENAME    \$document_root/blocked.php;
						fastcgi_param   SCRIPT_NAME        blocked.php;        }
    }

    server {
        listen 0.0.0.0:443 ssl;
				listen [::]:443 ssl; #http2

        server_name _;

        ssl_session_cache  builtin:1000  shared:SSL:10m;
        ssl_protocols  TLSv1 TLSv1.1 TLSv1.2 TLSv1.3;
        ssl_ciphers HIGH:!aNULL:!eNULL:!EXPORT:!CAMELLIA:!DES:!MD5:!PSK:!RC4;
        ssl_prefer_server_ciphers on;

				#fallback certificates are used to start nginx
        ssl_certificate /opt/rpidns/conf/ssl_sign/ioc2rpz.fallback.crt;
        ssl_certificate_key /opt/rpidns/conf/ssl_sign/ioc2rpz.fallback.pkey;

        ssl_certificate_by_lua_block {
-- lua script to dynamically generate certificates
local ssl = require "ngx.ssl" -- ssl connection management
ssl.clear_certs() -- clear fallback certificates
local common_name = ssl.server_name() -- get requested domain
if common_name == nil then
   common_name = "unknown"
end

-- trying to load previously generated certificate and private key from files
local pkey_pem = nil;
local f = io.open(string.format("/opt/rpidns/conf/ssl_cache/%s.pkey", common_name), "r")
if f then
  pkey_pem = f:read("*a")
  f:close()
end

local cert_pem = nil;
local f = io.open(string.format("/opt/rpidns/conf/ssl_cache/%s.crt", common_name), "r")
if f then
   cert_pem = f:read("*a")
   f:close()
end

-- if the private key and the certificate were loaded, use them to establish HTTPS connection
if pkey_pem and cert_pem then
 assert(ssl.set_priv_key(assert(ssl.parse_pem_priv_key(pkey_pem))))
 assert(ssl.set_cert(assert(ssl.parse_pem_cert(cert_pem))))
 return -- script exists here if the key and the cert chain were successfully set
end 

-- if there were no files the script will generate a new private certificate and sign with an intermediate certificate
-- Load a private key for the intermediate cert
local IntKey = nil
local f = io.open("/opt/rpidns/conf/ssl_sign/ioc2rpzInt.pkey", "r") -- change path and name based on your configuration
if f then
  IntKey =  assert(require("resty.openssl").pkey.new(f:read("*a"),"PEM"))
  f:close()
end

-- Load the intermidiate certificate
local f = io.open("/opt/rpidns/conf/ssl_sign/ioc2rpzInt.crt", "r")
local IntCert = nil -- Intermediate certificate
local IntCert_pem="" -- Intermediate certificate in PEM format. We need it in PEM format to add to the certificate chain
if f then
   IntCert_pem=f:read("*a")
   IntCert = assert(require("resty.openssl.x509").new(IntCert_pem))
   f:close()
end

-- Load the CA certificate. CA certificate should be added to the certificate chain
local CAcert_pem = "";
local f = io.open("/opt/rpidns/conf/ssl_sign/ioc2rpzCA.crt", "r")
if f then
   CAcert_pem=f:read("*a")
   f:close()
end

-- prevent generating the same certificate multiple times in parallel
local lock = require("resty.lock"):new("ioc2rpz_locks")
assert(lock:lock(common_name))
-- generate new keys (public and private)
local pk = assert(require("resty.openssl").pkey.new({type  = "EC", curve = "secp384r1",})) -- ECC keys are generated much faster in comparing with RSA which is very important on Raspberry Pi Zero

local x509, err = require("resty.openssl.x509").new() -- create a new certificate
local name, err = require("resty.openssl.x509.name").new() -- create name object. It is used to define the subject. We will generate certificate w/o CSR
local altname = assert(require("resty.openssl.x509.altname").new()) -- create alt name object. subjectAltName extension is required to pass Chrome security validation

-- add the subject and common names
assert(name:add("CN", common_name):add("C", "US"):add("ST", "California"):add("L", "San Jose"):add("O", "ioc2rpz Community"))
assert(altname:add("DNS", common_name):add("DNS", "*."..common_name))

assert(x509:set_version(3)) -- set certificate version
assert(x509:get_serial_number(42)) -- set serial. the certificate is fake so we don't care about the number
assert(x509:set_not_before(ngx.time())) -- set current time as the certificate's validity start date
assert(x509:set_not_after(ngx.time()+820*86400)) -- the certificate will be valid for 820 days (https://support.apple.com/en-us/HT210176). It is recommended to periodically clear the certificate cache
assert(x509:set_subject_name(name)) -- set certificate's subject
local issuer = assert(IntCert:get_subject_name()) -- the intermediate's certificate subject is used as the certificate's issuer
assert(x509:set_issuer_name(issuer)) -- set the issuer
assert(x509:set_subject_alt_name(altname)) -- add subjectAltName extension
assert(x509:set_pubkey(pk)) -- set the certificate's public key
assert(x509:set_basic_constraints({ cA = false, pathlen = 0})) -- set constraints

assert(x509:sign(IntKey)) -- sign the certificate with the Intermediate's certificate private key

cert_pem=assert(x509:to_PEM()) -- convert the certificate to PEM format
local cert = assert(ssl.parse_pem_cert(cert_pem..IntCert_pem..CAcert_pem)) -- combine the domain, intermediate and CA certificates
pkey_pem = assert(pk:to_PEM("private")) -- convert the private key to PEM format

-- save certificates to a local cache
local f = assert(io.open(string.format("/opt/rpidns/conf/ssl_cache/%s.crt", common_name), "w"))
f:write(cert_pem)
f:write(IntCert_pem)
f:write(CAcert_pem)
f:close()

-- save the private key to a local file
local f = assert(io.open(string.format("/opt/rpidns/conf/ssl_cache/%s.pkey", common_name), "w"))
f:write(pkey_pem)
f:close()

-- set the private key for the session
assert(ssl.set_priv_key(ssl.parse_pem_priv_key(pkey_pem)))

-- set the certificate for the session
assert(ssl.set_cert(cert))

-- unlock the common name
assert(lock:unlock())
-- end lua script to dynamically generate certificates
        }

        #lua_need_request_body on;
        #client_max_body_size 100k;
        #client_body_buffer_size 100k;

        #server_tokens off;
				root /opt/rpidns/www;
				
        location ~* \.(jpg|jpeg)\$ {
					rewrite ^.*\$ /blocked/blocked.jpg break;
        }

        location ~* \.(png)\$ {
					rewrite ^.*\$ /blocked/blocked.png break;
        }

        location ~* \.(js)\$ {
					rewrite ^.*\$ /blocked/blocked.js break;
        }

        location ~* \.(css)\$ {
					rewrite ^.*\$ /blocked/blocked.css break;
        }
				
				#location ~^ /uploads/ {
					#try_files \$uri =404;
				#	return 404
				#}

        location / {
					  #you may proxy request to another server or use nginx as a webserver
				    #proxy_set_header   X-Real-IP        $remote_addr;
            #proxy_ssl_verify off;
            #proxy_set_header Host \$host;
            #proxy_pass_header Server;
            #proxy_pass http://\$host:80;
						
						#respond with the default block page
            
						#rewrite ^.*\$ /index.html break;
						fastcgi_pass unix:/run/php/php7.3-fpm.sock;
						include         /usr/local/openresty/nginx/conf/fastcgi_params;
						fastcgi_index blocked.php;
						#variables described here: https://www.nginx.com/resources/wiki/start/topics/examples/phpfcgi/
						fastcgi_param   SCRIPT_FILENAME    \$document_root/blocked.php;
						fastcgi_param   SCRIPT_NAME        blocked.php;						
        }
				#	return 301 http://\$http_host\$request_uri;

				location /rpi_admin {
						auth_basic           "RpiDNS Administration";
						auth_basic_user_file /opt/rpidns/conf/rpiadmin.passwd;
						include         /usr/local/openresty/nginx/conf/fastcgi_params;
						try_files \$uri \$uri/index.php =404;
						index index.php;
						fastcgi_index index.php;
						fastcgi_pass unix:/run/php/php7.3-fpm.sock;					
						fastcgi_param  SCRIPT_FILENAME  \$document_root\$fastcgi_script_name;
						#root /opt/rpidns/www/rpi_admin;
				}				
				location ^~ /rpi_admin/css {
				}
				location ^~ /rpi_admin/webfonts {
				}
        location ^~ /rpi_admin/js {
				}
				location ^~ /rpi_admin/img {
				}				
				location ~ /\.ht {
						deny all;
      	}
    }
}

EOF

# create a startup script
#/usr/local/openresty/nginx/sbin/nginx -p /opt/rpidns -c /opt/rpidns/conf/nginx.conf
#####service nginx restart
cat > /etc/systemd/system/openresty.service  << EOF
[Unit]
Description=The OpenResty Application Platform
After=syslog.target network.target remote-fs.target nss-lookup.target

[Service]
Type=forking
PIDFile=/opt/rpidns/logs/nginx.pid
ExecStartPre=/usr/local/openresty/nginx/sbin/nginx -t -p /opt/rpidns -c /opt/rpidns/conf/nginx.conf
ExecStart=/usr/local/openresty/nginx/sbin/nginx -p /opt/rpidns -c /opt/rpidns/conf/nginx.conf
ExecReload=/bin/kill -s HUP $MAINPID
ExecStop=/bin/kill -s QUIT $MAINPID
PrivateTmp=true

[Install]
WantedBy=multi-user.target
EOF

systemctl enable openresty
service openresty start

#to access temperature
usermod -a -G video www-data

###};

date '+%Y-%m-%d %H:%M';echo -e "#########\nConfigure Rsyslog\n#########\n"
# configure syslog
# echo "local4.info		@@syslog:10514" > /etc/rsyslog.d/forward_logs.conf

EOD;

if ($rpz['logging']=='forward'){

	$script .= "
echo 'local4.info		@@$configure_rsyslog:10514' > /etc/rsyslog.d/forward_logs.conf
	";
}else{
	$script .= <<<'EOD'

echo '
Module (load="imptcp")

#$template changedate,"%TIMESTAMP:::date-rfc3339% %HOSTNAME% %syslogtag% %syslogseverity-text% %msg:::sp-if-no-1st-sp%%msg:::drop-last-lf%\n"
$template changedate,"%TIMESTAMP:::date-rfc3339% %fromhost-ip% %syslogtag% %syslogseverity-text% %msg:::sp-if-no-1st-sp%%msg:::drop-last-lf%\n"
$template FILENAME,"/opt/rpidns/logs/bind_%fromhost-ip%_queries.log"

ruleset(name="rpidns"){
    action(template="changedate" type="omfile" dynaFile="FILENAME") # fileGroup="pi" fileCreateMode="0660")
}

input(type="imptcp" port="10514" ruleset="rpidns")
' > /etc/rsyslog.d/listen_10514.conf

service rsyslog restart
		
EOD;

};

	$script .= <<<'EOD'

#install crontabs
cat >> /tmp/root.cron  << EOF
#root cron scripts
#Remove certificates which were created more than 30 days ago
12 1 * * * 	/usr/bin/find /opt/rpidns/conf/ssl_cache/ -type f -mtime +30 -execdir rm -- '{}' \;
#Remove certificates which were not used for 7 days (on raspberry pi atime is usually disabled)
12 0 * * * 	/usr/bin/find /opt/rpidns/conf/ssl_cache/ -type f -atime +7 -execdir rm -- '{}' \;
#@reboot /usr/sbin/ntpdate 0.debian.pool.ntp.org&
EOF
cat /tmp/root.cron | crontab -
rm -rf /tmp/root.cron

cd $WDIR
#git clone -b dev --single-branch https://github.com/Homas/RpiDNS.git
git clone --single-branch https://github.com/Homas/RpiDNS.git
cp -R RpiDNS/www /opt/rpidns/
cp -R RpiDNS/scripts /opt/rpidns/
/bin/bash /opt/rpidns/scripts/rpidns_install.sh

date '+%Y-%m-%d %H:%M';echo -e "#########\nRpiDNS was installed\n#########\n"

echo -e "Management console: https://$HOSTNAME/rpi_admin\nUsername: rpiadmin\nPassword: $ADMPWD"

echo -e "\n\nPlease reboot RpiDNS\n"

EOD;

  return $script;
};



?>