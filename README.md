# ioc2rpz.gui
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)  

## Overview
ioc2rpz.gui is a web interface for [ioc2rpz](https://github.com/Homas/ioc2rpz). ioc2rzp is custom DNS server which was built to automatically maintain and distribute RPZ feeds.
You can watch a demo of ioc2rpz technology including ioc2rpz.gui of the following video.
<p align="center"><a href="http://www.youtube.com/watch?feature=player_embedded&v=bvhyMFa_mBM" target="_blank"><img src="https://github.com/Homas/ioc2rpz/blob/master/ioc2rpz_demo.png"></a></p>

**Although ioc2rpz.gui was developed keeping security in a mind it was not tested on penetrations and must be installed and used in a management networks with a limited access.**

## Setup
1. The easiest way to install ioc2rpz.gui is using a docker container. Please refer the [Docker Container](#docker-container) section.
2. To install on a standalone web-server you may use "run_ioc2rpz.gui.sh" script as well. Please comment the last lines in the script which starts a crontab daemon and a web-server. By default ioc2rpz.gui is installed in "/opt/ioc2rpz.gui" directory.
3. To install it manually:
- check that all dependencies are installed
- create /opt/ioc2rpz.gui, /opt/ioc2rpz.gui/www/io2cfg, /opt/ioc2rpz.gui/export-cfg directories;
- download sources and copy them (maintaining the directory structure) under "/opt/ioc2rpz.gui";
- create a database by invoking "/opt/ioc2rpz.gui/scripts/init_db.php" script;
- create a crontab which will execute "/opt/ioc2rpz.gui/scripts/publish_cfg.php" script every 10 seconds;
- configure HTTP server.

Right now ioc2rpz.gui use only SQLite database with a database file stored in "/opt/ioc2rpz.gui/www/io2cfg" folder. Make sure that set up a relevant access permitions to the directory/db-file.

ioc2rpz configuration files are saved to "/opt/ioc2rpz.gui/export-cfg" folder. 

The database initialisation script also creates a sample configuration. To immediatelly start using the sample configuration it you need to update public and management IP-addresses of ioc2rpz server. If you already started ioc2rpz server please restart it or send a management signal to reload configuration.
The init script doesn't create a default user. You should create the administrator after the first start. Please do it ASAP.

### Dependencies
PHP7, SQLite, ISC Bind tools (dig only command). The following packets are required for Alpine linux with Apache web-server:
```
bash openrc curl coreutils openssl apache2 libxml2-dev apache2-utils php7 php7-apache2 php7-session php7-json php7-curl apache2-ssl sqlite php7-sqlite3 php7-ctype bind-tools
```
If you use a different distribution or a web-server please find out yourself required packages.

## Docker Container[](#docker-container)
ioc2rpz.gui is available on the Docker Hub. Just look for ioc2rpz.gui 
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
### Configuration overview
### TSIG Keys
### Servers
### Sources
### Whitelists
### RPZs
### Publishing configuration
### Export configuration
#### Bind
#### PowerDNS
#### Infoblox

## TODO
- [ ] Publishing.
    - [x] Track changes for all objects & update SRV flag.
    - [ ] Publishing button on a SRV flag.
- [x] Container. Sessions expiration
- [x] QA with Bind, PowerDNS
- [x] Default SOA for new RPZ
- [x] ioc2rpz http & file error handling
- [x] Default file configuration name
- [x] add sources https://github.com/notracking/hosts-blocklists
- [ ] README.md && Video && Slides
- [ ] Config import. Pub_IP & local management IP & Email & Management.

----- cut. an article to be an published -----
- [ ] Constraints enforcements on SQLite (requires redo the DB, keys etc) (if there is a named index, php doesn't see rowid.....)
- [ ] Changing management TSIG - publish config immediately.
- [ ] Servers table. Server online status.
- [ ] Source/whitelist check availability/rechability
- [ ] Server side. Intelligent publishing an updated server configuration
- [ ] Local RPZ rules validation (server side & gui)
- [ ] Monitoring/dashboards
- [ ] Wiki: AWS How-to (ioc2rpz & ioc2rpz.gui)
- [ ] MySQL support
- [ ] S3 support
- [ ] Link to the community
- [ ] Utils
    - [ ] Import configuration. Srv and RPZs uniqueness + add SRV params
    - [x] Export PowerDNS, Infoblox configuration
    - [ ] Import/Backup ioc2rpz.gui config

## Bugs
- [ ] Unchecked checkboxes in tables on odd lines
- [ ] TSIG generated in JS not always validated.
- [ ] (SQLite issue) Config import. Not all sources were added to a last RPZ. SRV was not added.
- [ ] REGEX. "-" in  [ ] must be last. Looks like erl bug. 
- [ ] Validate IOC in the ioc2rpz. Errors with tailing.

## Built with
- [VUE.js](https://vuejs.org/)
- [bootstrap-vue](https://bootstrap-vue.js.org/)

## Support on Beerpay
Hey dude! Help me out for a couple of :beers:!

[![Beerpay](https://beerpay.io/Homas/ioc2rpz/badge.svg?style=beer-square)](https://beerpay.io/Homas/ioc2rpz)  [![Beerpay](https://beerpay.io/Homas/ioc2rpz/make-wish.svg?style=flat-square)](https://beerpay.io/Homas/ioc2rpz?focus=wish)

# License
Copyright 2017 - 2018 Vadim Pavlov ioc2rpz[at]gmail[.]com

Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with the License.
You may obtain a copy of the License at  
  
    http://www.apache.org/licenses/LICENSE-2.0  
  
Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.
