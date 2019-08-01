# ioc2rpz.gui
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)  

## Overview
ioc2rpz.gui is a web interface for [ioc2rpz](https://github.com/Homas/ioc2rpz). ioc2rzp is a custom DNS server which was built to automatically maintain and distribute RPZ feeds.
You can watch a demo of ioc2rpz technology including ioc2rpz.gui on the following video.
<p align="center"><a href="http://www.youtube.com/watch?feature=player_embedded&v=bvhyMFa_mBM" target="_blank"><img src="https://github.com/Homas/ioc2rpz/blob/master/ioc2rpz_demo.png"></a></p>

**Although ioc2rpz.gui was developed keeping security in mind it was not tested on penetrations and must be installed and used in restricted management networks.**

## Setup
You may serup ioc2rp.gui using following options:
- A docker container. It is the to install ioc2rpz.gui. Please refer the [Docker Container](#docker-container) section.
- "run_ioc2rpz.gui.sh" script. "run_ioc2rpz.gui.sh" script is used to start services in a container and make required settings. To run the script:
    - check that all dependencies are installed
    - create /opt/ioc2rpz.gui, /opt/ioc2rpz.gui/www/io2cfg, /opt/ioc2rpz.gui/export-cfg directories;
    - download sources and copy them (maintaining the directory structure) to IO2_ROOT directory. By default to "/opt/ioc2rpz.gui";
    - comment last 2 lines (which start crontab and apache2 daemons);
    - execute the script with root permissions;
    - restart apache2 service.
- To install it manually:
    - check that all dependencies are installed
    - create /opt/ioc2rpz.gui, /opt/ioc2rpz.gui/www/io2cfg, /opt/ioc2rpz.gui/export-cfg directories;
    - download sources and copy them (maintaining the directory structure) to "/opt/ioc2rpz.gui";
    - create a database by invoking "/opt/ioc2rpz.gui/scripts/init_db.php" script;
    - create a crontab which will execute "/opt/ioc2rpz.gui/scripts/publish_cfg.php" script every 10 seconds;
    - configure HTTP server.

Right now ioc2rpz.gui use only SQLite database with a database file stored in "/opt/ioc2rpz.gui/www/io2cfg" folder. Make sure that set up a relevant access permissions to the directory/db-file.

ioc2rpz configuration files are saved to "/opt/ioc2rpz.gui/export-cfg" folder. 

The database initialization script also creates a sample configuration. You need to update public and management IP-addresses of ioc2rpz server before using it. If you already started ioc2rpz server please restart it or send a management signal to reload its configuration.

**The init script doesn't create a default user. You should create the administrator after the first start. Please do it ASAP.**

### Dependencies
PHP7, SQLite, ISC Bind tools (dig only command). The following packets are required for Alpine Linux with Apache web-server:
```
bash openrc curl coreutils openssl apache2 libxml2-dev apache2-utils php7 php7-apache2 php7-session php7-json php7-curl apache2-ssl sqlite php7-sqlite3 php7-ctype bind-tools
```
If you use other Linux distribution or a web-server please find out required packages by yourself.

## Docker Container[](#docker-container)
ioc2rpz.gui is available on the Docker Hub. Just search for ioc2rpz.gui 
- ioc2rpz.gui automatically create a sample configuration;
- ioc2rpz.gui use 80/tcp, 443/tcp ports. The ports should be exposed to a host system;
- ioc2rpz.gui use the following volumes:
    - "/opt/ioc2rpz.gui/export-cfg" to export ioc2rpz configurations. If you run ioc2rpz on the same host the folder should be shared;
    - "/opt/ioc2rpz.gui/www/io2cfg" to store SQLite database;
    - "/etc/apache2/ssl" to store SSL certificates.

You can start ioc2rpz.gui with the following command:
```
sudo docker run -d --name ioc2rpz.gui --log-driver=syslog  --restart always --mount type=bind,source=/home/ioc2rpz/cfg,target=/opt/ioc2rpz.gui/export-cfg --mount type=bind,source=/home/ioc2rpz/db,target=/opt/ioc2rpz.gui/www/io2cfg --mount type=bind,source=/home/ioc2rpz/ssl,target=/etc/apache2/ssl -p80:80 -p443:443 pvmdel/ioc2rpz.gui
```
where /home/ioc2rpz/cfg, /home/ioc2rpz/ssl, /home/ioc2rpz/db directories on a host system.

## ioc2rpz on AWS
You can run ioc2rpz and ioc2rpz.gui on AWS. For relatively small deployments (several hundreds thousands indicators) even free tier is enough.
The video below shows how to setup ioc2rpz and ioc2rpz.gui on AWS using ECS.
<p align="center"><a href="http://www.youtube.com/watch?feature=player_embedded&v=C-y4p5TXt8s" target="_blank"><img src="https://github.com/Homas/ioc2rpz/blob/master/ioc2rpz_aws_setup.png"></a></p>

## ioc2rpz configuration

### Configuration workflow
To configure ioc2rpz server you need to:
1. Create TSIG Keys for management and response policy zones transfers;
2. Create a server record;
3. Add sources;
4. (optional) Add whitelists;
5. Create a response policy zone;
6. Publish the ioc2rpz configuration;
7. (optional) Export RPZs configuration in a required format.

### TSIG keys
TSIG keys are used for ioc2rpz server management and RPZ transfer. It is not required to use TSIG keys for zone transfers but highly recommended.

To add a new TSIG key navigate to "Configuration" --> "TSIG keys" and press the "+" button.
The TSIG key and it's name automatically generated. You may generate a name and/or a key by using the "Generate" button or provide your values.
ioc2rpz supports md5, sha256, sha512 hash algorithms so you need to select required algorithm. Some DNS servers do not support all algorithms.
The "Management key" checkbox is used to distinguish keys which are used to manage ioc2rpz. These keys can not be used for RPZ transfers.

The action menu next to each TSIG key allows you to view, edit and remove the key. 

### Servers
Server tab is used to generate configurations and manages ioc2rpz servers. You can manage multiple ioc2rpz servers on a single ioc2rpz.gui instance.
Currently ioc2rpz.gui fully supports only ioc2rpz running on the same host but can save configurations to local files for multiple servers. In upcoming releases it will be possible to upload configurations to remote ftp/scp/sftp servers and/or S3 bucket. 

To add a server navigate to "Configuration" --> "Servers" and press the "+" button. All fields except "Management stations IPs" are required.
"Server's Public IP/FQDN" is used only in the export DNS configurations. "Server's MGMT IP/FQDN" is used to manage ioc2rpz service. The public and management IP-addresses are not exposed into ioc2rpz configuration. If you select "Disabled" checkbox when you still can change the server's configuration in the GUI but it will not be published.

The servers action menu allows you to view, edit, clone and remove servers, export and publish server's configuration. You may force publishing server's configuration independent on any changes.

### Sources
A source is a feed of malicious indicators. FQDNs, IPv4 and IPv6-addresses are supported. A source is a text file or a feed of text data. Indicators should be separated by newline/carriage return characters (/n,/r or both /r/n).  

To create a source navigate to "Configuration" --> "Sources" and press the "+" button. Fill the following fields and press "Ok":
- source name;
- source URL for full source transfer (AXFR). ioc2rpz can use http/https/ftp and local files to fetch indicators. Prefix "file:" is used for local files. Basic HTTP authentication is supported as well. You should include username/password in the URL in the following format "https://username:password@host.domain";
- source path for incremental source transfer (IXFR). AXFR,IXFR paths support keywords to shorten URLs and provide zone update timestamps:
  - **[:AXFR:]** - full AXFR path. Can be used only in IXFR paths;
  - **[:FTimestamp:]** - timestamp when the source was last time updated  (e.g. 1507946281)
  - **[:ToTimestamp:]** - current timestamp;
- REGEX which is used to extract indicators and their expiration time. The first match is an indicator, the second match is an expiration time. The expiration time is an optional parameter. If the field is left empty, a default REGEX will be used (`"^([A-Za-z0-9][A-Za-z0-9\-\._]+)[^A-Za-z0-9\-\._]*.*$"`). `none` is used if no REGEX is required (the source contains IOCs one per line w/o an expiration date).

The action menu allows you to view, edit, clone and remove sources.

### Whitelists
Whitelists are used to prevent possible errors and blocking trusted domains and IP addresses. The whitelisted IOCs are removed from response policy zones. ioc2rpz does check only exact match, so it will not split or discard a network if a whitelisted IP address is included into a blocked subnet and vice versa. A whitelist is a text file or a feed of text data. Indicators should be separated by newline characters (/n,/r or both /n/r).  Whitelists must contain valid FQDNs and/or IP addresses. 

To create a source navigate to "Configuration" --> "Whitelists" and press the "+" button. Fill the following fields and press "Ok":
- whitelist name;
- whitelist path. URLs(http/https/ftp) and local files are supported. Prefix "file:" is used for local files. Basic HTTP authentication is supported as well. You should include username/password in the URL in the following format "https://username:password@host.domain";
- REGEX which is used to extract indicators. A regular expression must be included in double quotes. If the field is left empty, a default REGEX will be used (`"^([A-Za-z0-9][A-Za-z0-9\-\._]+)[^A-Za-z0-9\-\._]*.*$"`). `none` is used if no REGEX is required (the whitelist contains IOCs one per line w/o an expiration date).

### RPZs
Response policy zones are managed under "Configuration" --> "RPZs" menu. To add an RPZ press "+", fill required fields and click "Ok".
To configure RPZ you should provide:
- name the of an RPZ feed. It must comply with a DNS zone naming format;
- select a distribution server. You may select several servers;
- select TSIG keys which will be used to authenticate zone transfers;
- select sources and whitelists. You must select minimum one source;
- select the zone action. If you choose "Local records" please check the record format in ioc2rpz documentation.
- select IOCs type. It is used for optimization;
- (optional) provide list of IP-addresses to notify when the RPZ updates;
- check "Cache zone" if the RPZ should be cached. Otherwise the zone will be generate on the fly by a request;
- "Generate wildcard rules" checkbox controls generating wildcards rules (starting with "*") to trigger on all subdomains;
- provide time values for SOA record;
- provide full and incremental RPZ update times. Using this parameters the zone will be automatically updated.

If you disable an RPZ it will not be published to servers.

The action menu allows you to view, edit, clone and remove RPZs.

**It is not recommended to mix domain based and IP based sources in a single RPZ**. IP-based rules require DNS servers to resolve the queries and if any IP-based feeds precede domain based feeds:
- it is not performance effective;
- it make RPZ useless for protection against DNS Tunneling, DNS Based Data Exfiltration/Infiltration and Water Torture/Phantom domain/Random subdomain attacks.

### Publishing configuration
A yellow "Publish configuration" button (in the top right corner next to login name) automatically displayed when a server configuration is changed, the "publish" button on the action menu is also highlighted in blue.
When you request to publish data:
- ioc2rpz configuration is saved to a file;
- ioc2rpz.gui sends a reload configuration signal to a server.
If ioc2rpz was started without configuration or there were changes in management keys it may be required to manually restart ioc2rpz service or manually send the reload configuration signal using a previous management key.

### Export configuration
You can export configuration in the following formats: ISC Bind, PowerDNS, Infoblox. The exported configuration should be embedded into the existing configuration.
To export a configuration navigate to "Configuration" --> "Utils" and click on a relevant button. After that select RPZ feeds which should be included into the configuration. Please check following sections about additional information how to set up a DNS server.
The export tool will export all tsig keys in the ISC Bind format but for a zone will use only a single TSIG key. For PowerDNS and Infoblox it will use a first available and usable key.

#### ISC Bind
The configuration consist of 3 parts: options, tsig keys, RPZ zones. The tsig keys, RPZ zones can be inserted "as is" into the ISC Bind's configuration.
You need to merge provided options settings with options configured on your DNS server.

#### PowerDNS
RPZ zone configuration is located in a separate lua-file. If you already use RPZs or any lua based settings just merge the exported file with your lua configuration file.
If you never used RPZs or lua configuration first of all you will need to add "lua-config-file" parameter in the configuration and after that use the exported configuration.

#### Infoblox
Infoblox doesn't support HMAC-SHA512 and it is not possible to import a key which contain slash "/".  The export tool will try to find out a supported key. If there are no supported keys available it will use the first key. The CSV import for such records will fail.
The Infoblox configuration is provided in Infoblox CSV import format. To import a downloaded file navigate to "Data Management" --> "DNS" --> "Response Policy Zones" and click "CSV Import". After that check the RPZs order. RPZs which contain domain based rules should precede RPZs with mixed rules and IP-rules only.

## Known Bugs
- [ ] Publishing deleted RPZ, sources
- [ ] Unchecked checkboxes in tables on odd lines
- [ ] TSIG generated in JS are not always valid.
- [ ] (SQLite issue) Config import. Not all sources were added to a last RPZ. SRV was not added.
- [ ] REGEX. "-" in  [ ] must be last. Looks like erl bug. 
- [ ] Validate IOC in the ioc2rpz. Errors with tailing.

## Built with
- [VUE.js](https://vuejs.org/)
- [bootstrap-vue](https://bootstrap-vue.js.org/)
- [Axios](https://github.com/axios/axios)

## Support on Beerpay
Hey dude! Help me out for a couple of :beers:!

[![Beerpay](https://beerpay.io/Homas/ioc2rpz/badge.svg?style=beer-square)](https://beerpay.io/Homas/ioc2rpz)  [![Beerpay](https://beerpay.io/Homas/ioc2rpz/make-wish.svg?style=flat-square)](https://beerpay.io/Homas/ioc2rpz?focus=wish)

# Contact us
You can contact us by email: feedback(at)ioc2rpz[.]net or in [Telegram](https://t.me/ioc2rpz).

# License
Copyright 2017 - 2019 Vadim Pavlov ioc2rpz[at]gmail[.]com

Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with the License.
You may obtain a copy of the License at  
  
    http://www.apache.org/licenses/LICENSE-2.0  
  
Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.
