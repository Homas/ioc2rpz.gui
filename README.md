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
- [x] Utils
    - [x] Export ISC Bind configuration
- [x] Srv/RPZ disabled (add/edit windows + check server side)
- [ ] Container. SSL persist config
- [ ] Container. Sessions expirations
- [ ] Container. Crontab
- [ ] README.md && Video
- [ ] ioc2rpz fix http error handeling
- [ ] Major bugs

----- cut. an article to be an published -----
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
- [ ] Unchecked checkboxes in tables on odd lines
- [ ] Sometimes changes are nit published

# License
Copyright 2017 - 2018 Vadim Pavlov ioc2rpz[at]gmail[.]com

Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with the License.
You may obtain a copy of the License at  
  
    http://www.apache.org/licenses/LICENSE-2.0  
  
Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the License.

## Built with
- [VUE.js](https://vuejs.org/)
- [bootstrap-vue](https://bootstrap-vue.js.org/)
