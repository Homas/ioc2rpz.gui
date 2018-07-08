# ioc2rpz.gui
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)  

## Overview
ioc2rpz.gui is a web interface for [ioc2rpz](https://github.com/Homas/ioc2rpz)

<p align="center"><img src="https://github.com/Homas/ioc2rpz.gui/blob/master/ioc2rpz.gui.png"></p>

The project is still under development but you can create and export the configuration already.
[Video preview](https://youtu.be/rFhmmGy-MSs)

## Setup
### Dependencies
### Configuration script

## Docker Container
ioc2rpz.gui is available on the Docker Hub. Just look for ioc2rpz.gui 



## TODO
- [ ] Publishing.
    - [x] Track changes for all objects & update SRV flag.
    - [ ] Publishing button on a SRV flag.
- [x] Container. Sessions expirations
- [x] QA with Bind, PowerDNS
- [x] Default SOA for new RPZ
- [x] ioc2rpz http & file error handeling
- [x] Default file configuration name
- [x] add sources https://github.com/notracking/hosts-blocklists
- [ ] README.md && Video && Slides
- [ ] Config import. Pub_IP & local management IP & Email & Management.

----- cut. an article to be an published -----
- [ ] Constraints enforsments on SQLite (requires redo the DB, keys etc) (if there is a named index, php doesn't see rowid.....)
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
    - [ ] Export PowerDNS, Infoblox configuration
    - [ ] Import/Backup ioc2rpz.gui config

## Bugs
- [x] Export BIND check all check box is not cleared
- [ ] Unchecked checkboxes in tables on odd lines
- [ ] TSIG generated in JS not always validated.
- [ ] (SQLite issue) Config import. Not all sources were added to a last RPZ. SRV was not added.
- [ ] REGEX. "-" in  [ ] must be last. Looks like erl bug. 
- [ ] Validate IOC in the ioc2rpz. Errors with tailing.

# License
Copyright 2017 - 2018 Vadim Pavlov ioc2rpz[at]gmail[.]com

Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with the License.
You may obtain a copy of the License at  
  
    http://www.apache.org/licenses/LICENSE-2.0  
  
Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.

## Built with
- [VUE.js](https://vuejs.org/)
- [bootstrap-vue](https://bootstrap-vue.js.org/)
