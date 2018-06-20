<?php
#(c) Vadim Pavlov 2018
#ioc2rpz configuration
  require 'io2auth.php';
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>ioc2rpz configuration</title>
    <!-- BootstrapVue -->
    <link type="text/css" rel="stylesheet" href="//unpkg.com/bootstrap/dist/css/bootstrap.min.css"/>
    <!--
    <link type="text/css" rel="stylesheet" href="//unpkg.com/bootstrap-vue@2.0.0-rc.2/dist/bootstrap-vue.css"/>
    -->
    <link type="text/css" rel="stylesheet" href="//unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.css"/>
    <!-- font awesome -->
    <!-- https://fontawesome.com/icons?d=gallery&m=free -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.9/css/all.css" integrity="sha384-5SOiIsAziJl6AWe0HWRKTXlfcSHKmYV4RBF18PPJ173Kzn7jzMyFuTtk8JA7QQG1" crossorigin="anonymous">
    <!-- ioc2rpz CSS -->
    <link type="text/css" rel="stylesheet" href="/css/io2.css?<?=$io2ver?>"/>
  </head>
  <body>
  <div id="app" fluid class="h-100 d-flex flex-column" v-cloak>
    <div id="navbar" v-cloak>
    <b-navbar toggleable="md" type="dark" class="menu-bkgr">
    
      <b-navbar-toggle target="nav_collapse"></b-navbar-toggle>
    
      <b-navbar-brand href="#"><h2>ioc2rpz</h2></b-navbar-brand>
    
      <b-collapse is-nav id="nav_collapse">
    
        <b-navbar-nav>
          <b-nav-item href="#/dash/">Dashboard</b-nav-item>
          <b-nav-item href="#/cfg/" active>Configuration</b-nav-item>
          <b-nav-item href="#" disabled>Community</b-nav-item>
        </b-navbar-nav>
    
        <!-- Right aligned nav items -->
        <b-navbar-nav class="ml-auto">
          <b-nav-form><b-button variant="warning" v-show="publishUpdates" @click.stop="pushUpdatestoSRV('all')">Publish configuration</b-button></b-nav-form>
          <div class="spacer"></div>
    
<!--
          <b-nav-form>
            <b-form-input class="mr-sm-2" type="text" placeholder="Search"></b-form-input>
            <b-button variant="outline-secondary" class="my-2 my-sm-0">Search</b-button>
          </b-nav-form>

          <b-nav-item-dropdown text="Help" right>
            <b-dropdown-item href="#">ioc2rpz Wiki</b-dropdown-item>
            <b-dropdown-item href="#">ioc2rpz.gui Wiki</b-dropdown-item>
          </b-nav-item-dropdown>
    
          <b-nav-item-dropdown text="Lang" right>
            <b-dropdown-item href="#">EN</b-dropdown-item>
            <b-dropdown-item href="#">RU</b-dropdown-item>
            <b-dropdown-item href="#">DE</b-dropdown-item>
            <b-dropdown-item href="#">ES</b-dropdown-item>
            <b-dropdown-item href="#">FR</b-dropdown-item>
          </b-nav-item-dropdown>
-->    
          <b-nav-item-dropdown right>
            <!-- Using button-content slot -->
            <template slot="button-content">
              <em>{{ ftUName }}</em>
            </template>
            <b-dropdown-item-button @click.stop="$emit('bv::show::modal', 'mUProfile')">Profile</b-dropdown-item-button>
            <b-dropdown-item-button @click.stop="signOut">Sign out</b-dropdown-item-button>
          </b-nav-item-dropdown>
        </b-navbar-nav>
    
      </b-collapse>
    </b-navbar>
  </div>
    
  <div id="ConfApp" class="h-100 d-flex flex-column" v-cloak>
    <b-container fluid  class="h-100 d-flex flex-column">
        <b-tabs ref="tabs_menu" pills vertical nav-wrapper-class="menu-bkgr h-100 col-md-2" class="h-100 corners" content-class="curl_angels" :value="cfgTab" @input="changeTab">
          <b-tab title="Overview" href='#/cfg/overview'>
            Overview
          </b-tab>
          <b-tab table="servers" title="Servers" href="#/cfg/servers">
            <io2-table table="servers" ref="io2tbl_servers" :fields="servers_fields" /> 
          </b-tab>
          <b-tab table="tkeys" title="TSIG keys" href="#/cfg/tkeys" > 
            <io2-table table="tkeys" ref="io2tbl_tkeys" :fields="tkeys_fields" />              
          </b-tab>
          <b-tab table="whitelists" title="Whitelists" href='#/cfg/whitelists'>
            <io2-table table="whitelists" ref="io2tbl_whitelists" :fields="whitelists_fields" />
          </b-tab>
          <b-tab table="sources" title="Sources" href='#/cfg/sources'>
            <io2-table table="sources" ref="io2tbl_sources" :fields="sources_fields" />
          </b-tab>
          <b-tab table="rpzs" title="RPZs" href='#/cfg/rpzs'>
            <io2-table table="rpzs" ref="io2tbl_rpzs" :fields="rpzs_fields" />
          </b-tab>
          <b-tab title="Utils" href='#/cfg/utils'>
           <?php
            require 'utils.php';
            ?>
          </b-tab>
      </b-tabs>
    </b-container>


<!--        -->
<!-- Modals -->
<!--        -->

<!-- Error -->
    <b-modal id='mErrorMSG' centered title="Error" body-class="pt-0 pb-0">
      <span class='text-center'><span v-html="errorMSG"></span></span>
    </b-modal>

<!-- Delete confirmation -->
    <b-modal id='mConfDel' centered title="Confirmation required" @ok="tblDeleteRecord(deleteTbl,deleteRec)" ok-title="Confirm" body-class="pt-0 pb-0" v-cloak>
      <span class='text-center'><span v-html="modalMSG"></span></span>
    </b-modal>
    
<!-- TKey Add/Modify -->
    <b-modal id='mConfEditTSIG' centered title="TSIG Key" @ok="tblMgmtTKeyRecord($event,'tkeys')" body-class="pt-0 pb-0" v-cloak>
      <span class='text-center'>
        <div>
          <b-row>
            <b-col :sm="infoWindow?10:9" class="form_row"><b-input v-model.trim="ftKeyName" :state="validateName('ftKeyName')" :formatter="formatName" ref="formKeyName" :readonly="infoWindow" placeholder="Enter TSIG Key Name" /></b-col>
            <b-col :sm="infoWindow?2:3" class="form_row text-left">
              <b-button v-b-tooltip.hover title="Generate" variant="outline-secondary" v-if="!infoWindow" @click="genRandom('tkeyName')"><i class="fa fa-sync-alt"></i></b-button>
              <b-button v-b-tooltip.hover title="Copy" variant="outline-secondary" @click="copyToClipboard('formKeyName')"><i class="fa fa-copy"></i></b-button>
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="infoWindow?10:9" class="form_row">
              <b-input v-model.trim="ftKey" ref="formKey" :readonly="infoWindow" placeholder="Enter TSIG Key" :state="validateB64('ftKey')" :formatter="formatB64" /></b-col>
            <b-col :sm="infoWindow?2:3" class="form_row text-left">
              <b-button v-b-tooltip.hover title="Generate" variant="outline-secondary" v-if="!infoWindow" @click="genRandom('tkey')"><i class="fa fa-sync-alt"></i></b-button>
              <b-button v-b-tooltip.hover title="Copy" variant="outline-secondary" @click="copyToClipboard('formKey')"><i class="fa fa-copy"></i></b-button>
            </b-col>
          </b-row>
          <b-row>
            <b-col sm="6" class="form_row">
              <b-form-select v-model="ftKeyAlg" :disabled="infoWindow" :options="tkeys_Alg" class="mb-3" @change="genRandom('tkey')" />
            </b-col>
            <b-col sm="6" class='text-left form_row'>
              <b-form-checkbox unchecked-value=0 value=1 :disabled="infoWindow"  v-model="ftKeyMGMT">Management key</b-form-checkbox>
            </b-col>
          </b-row>
        </div>
      </span>
    </b-modal>
    
<!-- Whitelists/Sources Add/Modify -->
    <b-modal id='mConfEditSources' centered :title="ftSrcTitle" @ok="tblMgmtSrcRecord($event,ftSrcType)" body-class="pt-0 pb-0" size="lg" v-cloak>
      <span class='text-center'>
        <div>
          <b-row>
            <b-col :sm="12" class="form_row"><b-input v-model.trim="ftSrcName" :state="validateName('ftSrcName')" :formatter="formatName" ref="formSrcName" :readonly="infoWindow" placeholder="Enter source name" /></b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row"><b-textarea v-model="ftSrcURL" :state="validateURL('ftSrcURL')" :formatter="formatURL" :rows="3" ref="formSrcURL" :readonly="infoWindow" placeholder="Enter source URL" /></b-col>
          </b-row>
          <b-row v-show="ftSrcType == 'sources'">
            <b-col :sm="12" class="form_row"><b-textarea v-model="ftSrcURLIXFR" :state="validateIXFRURL('ftSrcURLIXFR')" :formatter="formatIXFRURL" :rows="3" ref="formSrcURLIXFR" :readonly="infoWindow" placeholder="Enter source update URL" /></b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row"><b-textarea v-model="ftSrcREGEX" :state="validateREGEX('ftSrcREGEX')" :rows="3" ref="formREGEX" :readonly="infoWindow" placeholder="Enter REGEX" /></b-col>
          </b-row>
        </div>
      </span>
    </b-modal>

<!-- Servers Add/Modify -->

    <b-modal id='mConfEditSrv' ref='refmConfEditSrv' centered title="Server" @ok="tblMgmtSrvRecord($event,'servers')" body-class="pt-0 pb-0" size="lg" v-cloak>
      <span class='text-center'>
        <div>
          <b-row>
            <b-col :sm="4" class="form_row"><b-input v-model.trim="ftSrvName" :state="validateName('ftSrvName')" :formatter="formatName" ref="formSrvName" :readonly="infoWindow" placeholder="Enter server name"  v-b-tooltip.hover title="Name" /></b-col>
            <b-col :sm="4" class="form_row"><b-input v-model.trim="ftSrvPubIP" :state="validateIP('ftSrvPubIP')" :formatter="formatIP" ref="formSrvPubIP" :readonly="infoWindow" placeholder="Enter Server's Public IP or FQDN"  v-b-tooltip.hover title="Server's Public IP/FQDN" /></b-col>
            <b-col :sm="4" class="form_row"><b-input v-model.trim="ftSrvIP" :state="validateIP('ftSrvIP')" :formatter="formatIP" ref="formSrvIP" :readonly="infoWindow" placeholder="Enter Server's MGMT IP or FQDN"  v-b-tooltip.hover title="Server MGMT IP/FQDN" /></b-col>
          </b-row>
          <b-row>
            <b-col :sm="6" class="form_row"><b-input v-model.trim="ftSrvNS" :state="validateHostname('ftSrvNS')" :formatter="formatHostname" ref="formSrvNS" :readonly="infoWindow" placeholder="Enter NS name"  v-b-tooltip.hover title="Name server name"/></b-col>
            <b-col :sm="6" class="form_row"><b-input v-model.trim="ftSrvEmail" :state="validateEmail('ftSrvEmail')" :formatter="formatEmail" ref="formSrvEmail" :readonly="infoWindow" placeholder="Enter admin email"  v-b-tooltip.hover title="Administrator's email"/></b-col>
          </b-row>
          <b-row>
            <b-col :sm="6" class="form_row text-left">
              <b-form-group :style="{ height: (this.ftSrvTKeysAll.length<5?'5':'10')+'em' }" class="items_list" v-b-tooltip.hover title="TSIG Keys">
                <b-form-checkbox-group :disabled="infoWindow" plain stacked v-model="ftSrvTKeys" :options="ftSrvTKeysAll" />
              </b-form-group>
            </b-col>
            <b-col :sm="6" class="form_row text-left">
              <b-textarea v-model="ftSrvMGMTIP" :state="validateIPList('ftSrvMGMTIP')" :formatter="formatIP" style="height: 5em;" :rows="3" ref="formSrcNotify" :readonly="infoWindow" placeholder="Enter management stations IPs" :no-resize=true  v-b-tooltip.hover title="ACL/Management stations IPs" />
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row text-left"><b-form-checkbox unchecked-value=0 value=1 :disabled="infoWindow"  v-model="ftSrvMGMT">Manage server</b-form-checkbox></b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row text-left">
              <b-form-radio-group :disabled="infoWindow || (ftSrvMGMT == 0)" name="nSrvSType" v-model="ftSrvSType">
                <b-form-radio value="0">Local</b-form-radio>
                <b-form-radio value="1" disabled>SCP/SFTP</b-form-radio>
                <b-form-radio value="2" disabled>AWS S3</b-form-radio>
              </b-form-radio-group>
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row text-left">
              <b-input v-model.trim="ftSrvURL" :state="validateName('ftSrvURL')" :formatter="formatURL"  ref="formSrvURL" :readonly="infoWindow" placeholder="Enter file name"  v-b-tooltip.hover title="File Name" />
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row text-left"><b-form-checkbox unchecked-value=0 value=1 :disabled="infoWindow"  v-model="ftSrvDisabled">Disabled</b-form-checkbox></b-col>
          </b-row>
          <!-- keys, notify_list -->
        </div>
      </span>
    </b-modal>


<!-- RPZ Add/Modify -->
    <b-modal id='mConfEditRPZ' centered title="RPZ" @ok="tblMgmtRPZRecord($event,'rpzs')" body-class="pt-0 pb-0" size="lg" v-cloak>
      <span class='text-center'>
        <div>
          <b-row>
            <b-col :sm="12" class="form_row"><b-input v-model.trim="ftRPZName" :state="validateHostname('ftRPZName')" :formatter="formatName" ref="formRPZName" :readonly="infoWindow" placeholder="Enter RPZ name"  v-b-tooltip.hover title="RPZ Name" /></b-col>
          </b-row>

          <b-row>
            <b-col :sm="6" class="form_row text-left">
              <b-form-group :style="{ height: (this.ftRPZSrvsAll.length<4 && ftRPZTKeysAll.length<4?'4':'8')+'em' }" class="items_list" v-b-tooltip.hover title="Servers" >
                <b-form-checkbox-group :disabled="infoWindow" plain stacked v-model="ftRPZSrvs" :options="ftRPZSrvsAll" />
              </b-form-group>
            </b-col>
            <b-col :sm="6" class="form_row text-left">
              <b-form-group :style="{ height: (this.ftRPZSrvsAll.length<4 && ftRPZTKeysAll.length<4?'4':'8')+'em' }" class="items_list"  v-b-tooltip.hover title="TSIG Keys">
                <b-form-checkbox-group :disabled="infoWindow" plain stacked v-model="ftRPZTKeys" :options="ftRPZTKeysAll" />
              </b-form-group>
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="6" class="form_row text-left">
              <b-form-group :style="{ height: (this.ftRPZSrcAll.length<4 && ftRPZWLAll.length<4?'4':'8')+'em' }" class="items_list" v-b-tooltip.hover title="Sources">
                <b-form-checkbox-group :disabled="infoWindow" plain stacked v-model="ftRPZSrc" :options="ftRPZSrcAll" />
              </b-form-group>
            </b-col>
            <b-col :sm="6" class="form_row text-left">
              <b-form-group :style="{ height: (this.ftRPZSrcAll.length<4 && ftRPZWLAll.length<4?'4':'8')+'em' }" class="items_list" v-b-tooltip.hover title="Whitelists">
                <b-form-checkbox-group :disabled="infoWindow" plain stacked v-model="ftRPZWL" :options="ftRPZWLAll" />
              </b-form-group>
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="6" class="form_row text-left">
              <b-form-select v-model="ftRPZAction" :options="RPZ_Act_Options" :disabled="infoWindow"  v-b-tooltip.hover title="Action" />
            </b-col>
            <b-col :sm="6" class="form_row text-left">
              <b-textarea v-model="ftRPZNotify" :state="validateIPList('ftRPZNotify')" :formatter="formatIPList" :rows="1" ref="formRPZNotify" :readonly="infoWindow" placeholder="Enter IPs to notify" :no-resize=true  v-b-tooltip.hover title="IPs to notify" />
            </b-col>
          </b-row>
          <b-row v-show="ftRPZAction === 'local'">
            <b-col :sm="12" class="form_row text-left">
              <b-textarea v-model="ftRPZActionCustom" :state="validateCustomAction" :rows="3" ref="formRPZActionCustom" :readonly="infoWindow" placeholder="Enter local records" :no-resize=true  v-b-tooltip.hover title="Local records" />
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="4" class="form_row text-center">
              <b-form-select v-model="ftRPZIOCType" :options="RPZ_IType_Options" :disabled="infoWindow" v-b-tooltip.hover title="IOCs type" />
            </b-col>
            <b-col :sm="4" class="form_row text-center">
              <b-form-checkbox unchecked-value=0 value=1 :disabled="infoWindow"  v-model="ftRPZCache">Cache zone</b-form-checkbox>
            </b-col>
            <b-col :sm="4" class="form_row text-center">
              <b-form-checkbox unchecked-value=0 value=1 :disabled="infoWindow"  v-model="ftRPZWildcard">Generate wildcard rules</b-form-checkbox>
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="2" class="form_row"><b-input v-model.trim="ftRPZSOA_Refresh" :state="validateInt('ftRPZSOA_Refresh')" :formatter="formatInt" ref="formRPZSOA_Refresh" :readonly="infoWindow" placeholder="Refresh" v-b-tooltip.hover title="SOA Record. Zone refresh time"  /></b-col>
            <b-col :sm="2" class="form_row"><b-input v-model.trim="ftRPZSOA_UpdRetry" :state="validateInt('ftRPZSOA_UpdRetry')" :formatter="formatInt" ref="formRPZSOA_UpdRetry" :readonly="infoWindow" placeholder="Update retry" v-b-tooltip.hover title="SOA Record. Zone update retry time"  /></b-col>
            <b-col :sm="2" class="form_row"><b-input v-model.trim="ftRPZSOA_Exp" :state="validateInt('ftRPZSOA_Exp')" :formatter="formatInt" ref="formRPZSOA_Exp" :readonly="infoWindow" placeholder="Expiration" v-b-tooltip.hover title="SOA Record. Zone expiration time"  /></b-col>
            <b-col :sm="2" class="form_row"><b-input v-model.trim="ftRPZSOA_NXTTL" :state="validateInt('ftRPZSOA_NXTTL')" :formatter="formatInt" ref="formRPZSOA_NXTTL" :readonly="infoWindow" placeholder="NX TTL" v-b-tooltip.hover title="SOA Record. NXDomain TTL"  /></b-col>
            <b-col :sm="2" class="form_row"><b-input v-model.trim="ftRPZAXFR" :state="validateInt('ftRPZAXFR')" :formatter="formatInt" ref="formRPZAXFR" :readonly="infoWindow" placeholder="Full update" v-b-tooltip.hover title="Zone full update time"  /></b-col>
            <b-col :sm="2" class="form_row"><b-input v-model.trim="ftRPZIXFR" :state="validateInt('ftRPZIXFR')" :formatter="formatInt" ref="formRPZIXFR" :readonly="infoWindow" placeholder="Inc update" v-b-tooltip.hover title="Zone incrimental update time"  /></b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row text-left"><b-form-checkbox unchecked-value=0 value=1 :disabled="infoWindow"  v-model="ftRPZDisabled">Disabled</b-form-checkbox></b-col>
          </b-row>
        </div>
      </span>
    </b-modal>

<!-- Message Modal -->
    <b-modal centered :hide-header="true" :hide-footer="true" :visible="mInfoMSGvis" body-class="text-center">
      <span class='text-center' v-html="msgInfoMSG"></span>
    </b-modal>
 
<!-- Import ioc2rpz config -->
    <b-modal id='mImportConfig' ref='refImportConfig' centered title="Import ioc2rpz configuration" @ok="ImportConfig()" ok-title="Import" body-class="pt-0 pb-0" size="lg" v-cloak>
      <span class='text-center'>
        <div>
          <b-row>
            <b-col :sm="12" class="form_row">
              <div class="drop_zone" @dragover.stop.prevent @drop.stop.prevent="checkImpFile">Drop file here</div>
              <output class="text-left"><strong>{{ ftImpFileDesc }}</strong></output>
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="6" class="form_row">
              <div style="margin-bottom:10px"><b-input v-model="ftImpServName" :state="validateName('ftImpServName')" :formatter="formatName" placeholder="Enter server name" /></div>
              <div><b-input v-model="ftImpPrefix" :state="validateName('ftImpPrefix')" :formatter="formatName" placeholder="Enter prefix" /></div>
            </b-col>
            <b-col :sm="6" class="form_row text-left">
              <b-form-radio-group v-model="ftImpAction">
                <b-form-radio value=0>Keep existing records</b-form-radio>
                <b-form-radio value=1>Replace existing records</b-form-radio>
                <b-form-radio value=2>Add prefix on duplicates only</b-form-radio>
              </b-form-radio-group>
            </b-col>
          </b-row>
        </div>
      </span>
    </b-modal> 
 
 <!-- User's profile Modal -->
    <b-modal centered title="Profile" id="mUProfile" ref="refUProfile" body-class="text-center pt-0 pb-0" size="sm" v-cloak>
      <span class='text-center'>
        <div>
          <b-row>
            <b-col :sm="12" class="form_row">
              <b-input v-model.lazy="ftUNameProf" :state="validateName('ftUNameProf')" placeholder="Username"  v-b-tooltip.hover title="Username" :formatter="formatName" />
            </b-col>
            <b-col :sm="12" class="form_row">
              <b-input type="password" v-model.trim="ftUCPwd" placeholder="Current password"  v-b-tooltip.hover title="Current password" />
            </b-col>
            <b-col :sm="12" class="form_row">
              <b-input type="password" v-model.trim="ftUPwd" :state="validatePass('ftUPwd')" placeholder="New password"  v-b-tooltip.hover title="New password" />
            </b-col>
            <b-col :sm="12" class="form_row">
              <b-input type="password" v-model.trim="ftUpwdConf" :state="validatePassMatch('ftUPwd','ftUpwdConf')" placeholder="Confirm new password"  v-b-tooltip.hover title="Confirm new password" />
            </b-col>
          </b-row>
        </div>

      </span>
    </b-modal>

 <!-- Export RPZ config -->
    <b-modal centered title="Export configuration" id="mExpRPZ" ref="refExpRPZ" body-class="text-center pt-0 pb-0" ok-title="Export" @ok="exportDNSConfig" v-cloak>
      <span class='text-center'>
        <div>
          <b-row>
            <b-col :sm="12"  class="form_row text-left">
              <b-form-group v-b-tooltip.hover title="Select Response Policy Zones">
                &nbsp;<b-form-checkbox v-model="rpzExportSAll" aria-describedby="flavours" aria-controls="flavours" @change="rpzExportToggleAll">
                  {{ rpzExportSAll ? 'Un-select All' : 'Select All' }}
                </b-form-checkbox>
                <b-form-checkbox-group :style="{ height: (this.ftExRPZAll.length<4 && ftExRPZAll.length<4?'5':'10')+'em' }" plain stacked class="items_list" v-model="ftExRPZ" :options="ftExRPZAll" />
              </b-form-group>
            </b-col>
          </b-row>
        </div>

      </span>
    </b-modal>

    
<!-- End Modals -->
  </div>
  </div>
  <div class="copyright"><p>Copyright © 2018 Vadim Pavlov</p></div>
<?php
?>
    <script>
      var jsUser='<?= $ioc2Admin ?>';
    </script>
    <!-- Vue -->
    <script src="https://cdn.jsdelivr.net/npm/vue@2.5.16/dist/vue.js"></script>
    <!-- BootstrapVue -->
    <script src="//unpkg.com/babel-polyfill@latest/dist/polyfill.min.js"></script>
<!--
    <script src="//unpkg.com/bootstrap-vue@2.0.0-rc.2/dist/bootstrap-vue.js"></script>
-->
    <script src="//unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.js"></script>
    <!-- Axios -->
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
    <!-- JS -->
    <script src="/js/io2.js?<?=$io2ver?>"></script>

  </body>
</html>
