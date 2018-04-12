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
    <link type="text/css" rel="stylesheet" href="/css/io2.css"/>
  </head>
  <body>
  <div id="app" fluid class="h-100" v-cloak>
    <div id="navbar">
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
              <em>io2Admin</em>
            </template>
            <b-dropdown-item href="#">Profile</b-dropdown-item>
            <b-dropdown-item href="#">Signout</b-dropdown-item>
          </b-nav-item-dropdown>
        </b-navbar-nav>
    
      </b-collapse>
    </b-navbar>
  </div>
    
  <div id="ConfApp" class="h-100">
    <b-container fluid  class="h-100">
        <b-tabs ref="tabs_menu" pills vertical nav-wrapper-class="menu-bkgr h-100 col-md-2" class="h-100 corners" content-class="curl_angels" :value="cfgTab">
          <b-tab id="tab_overview" title="Overview" href='#/cfg/overview'>
            Overview
          </b-tab>
          <b-tab id="tab_servers" title="Servers" href="#/cfg/servers">
            <io2-table table="servers" ref="io2tbl_servers" :fields="servers_fields" /> 
          </b-tab>
          <b-tab id="tab_tkeys" title="TSIG keys" href="#/cfg/tkeys" > 
            <io2-table table="tkeys" ref="io2tbl_tkeys" :fields="tkeys_fields" />              
          </b-tab>
          <b-tab id="tab_whitelists" title="Whitelists" href='#/cfg/whitelists'>
            <io2-table table="whitelists" ref="io2tbl_whitelists" :fields="whitelists_fields" />
          </b-tab>
          <b-tab id="tab_sources" title="Sources" href='#/cfg/sources'>
            <io2-table table="sources" ref="io2tbl_sources" :fields="sources_fields" />
          </b-tab>
          <b-tab id="tab_rpzs" title="RPZs" href='#/cfg/rpzs'>
            <io2-table table="rpzs" ref="io2tbl_rpzs" :fields="rpzs_fields" />
          </b-tab>
          <b-tab id="tab_rpzs" title="Utils" href='#/cfg/utils'>
          </b-tab>
      </b-tabs>
    </b-container>
<!-- Modals -->

<!-- Error -->
    <b-modal id='mErrorMSG' centered title="Error">
      <span class='text-center'><span v-html="errorMSG"></span></span>
    </b-modal>

<!-- Delete confirmation -->
    <b-modal id='mConfDel' centered title="Confirmation required" @ok="tblDeleteRecord(deleteTbl,deleteRec)" ok-title="Confirm">
      <span class='text-center'><span v-html="modalMSG"></span></span>
    </b-modal>
    
<!-- TKey Add/Modify -->
    <b-modal id='mConfEditTSIG' centered title="TSIG Key" @ok="tblMgmtTKeyRecord('tkeys')">
      <span class='text-center'>
        <div>
          <b-row>
            <b-col :sm="infoWindow?10:9" class="form_row"><b-input v-model.trim="ftKeyName" :state="nameTKeyValid" ref="formKeyName" :readonly="infoWindow" placeholder="Enter TSIG Key Name" /></b-col>
            <b-col :sm="infoWindow?2:3" class="form_row text-left">
              <b-button v-b-tooltip.hover title="Generate" variant="outline-secondary" v-if="!infoWindow" @click="genRandom('tkeyName')"><i class="fa fa-sync-alt"></i></b-button>
              <b-button v-b-tooltip.hover title="Copy" variant="outline-secondary" @click="copyToClipboard('formKeyName')"><i class="fa fa-copy"></i></b-button>
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="infoWindow?10:9" class="form_row">
              <b-input v-model.trim="ftKey" ref="formKey" :readonly="infoWindow" placeholder="Enter TSIG Key Name" /></b-col>
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
    <b-modal id='mConfEditSources' centered :title="ftSrcTitle" @ok="tblMgmtSrcRecord(ftSrcType)" size="lg">
      <span class='text-center'>
        <div>
          <b-row>
            <b-col :sm="12" class="form_row"><b-input v-model.trim="ftSrcName" :state="nameSourceValid" ref="formSrcName" :readonly="infoWindow" placeholder="Enter source name" /></b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row"><b-textarea v-model="ftSrcURL" :rows="3" ref="formSrcURL" :readonly="infoWindow" placeholder="Enter source URL" /></b-col>
          </b-row>
          <b-row v-show="ftSrcType == 'sources'">
            <b-col :sm="12" class="form_row"><b-textarea v-model="ftSrcURLIXFR" :rows="3" ref="formSrcURLIXFR" :readonly="infoWindow" placeholder="Enter source update URL" /></b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row"><b-textarea v-model="ftSrcREGEX" :rows="3" ref="formREGEX" :readonly="infoWindow" placeholder="Enter REGEX" /></b-col>
          </b-row>
        </div>
      </span>
    </b-modal>

<!-- Servers Add/Modify -->
    <b-modal id='mConfEditSrv' centered title="Server" @ok="tblMgmtSrvRecord('servers')">
      <span class='text-center'>
        <div>
          <b-row>
            <b-col :sm="12" class="form_row"><b-input v-model.trim="ftSrvName" :state="srvNameValid" ref="formSrvName" :readonly="infoWindow" placeholder="Enter server name" /></b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row"><b-input v-model.trim="ftSrvIP" :state="srvIPValid" ref="formSrvIP" :readonly="infoWindow" placeholder="Enter management IP address" /></b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row"><b-input v-model.trim="ftSrvNS" :state="srvNSValid" ref="formSrvNS" :readonly="infoWindow" placeholder="Enter NS name" /></b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row"><b-input v-model.trim="ftSrvEmail" :state="srvEmailValid" ref="formSrvEmail" :readonly="infoWindow" placeholder="Enter admin email" /></b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row text-left">
              <b-form-group style="height: 4em; overflow-y: scroll; border: 1px solid #ced4da; border-radius: .25rem; margin: 0; padding: 5px">
                <b-form-checkbox-group :disabled="infoWindow" plain stacked v-model="ftSrvTKeys" :options="ftSrvTKeysAll" />
              </b-form-group>
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row text-left">
              <b-textarea v-model="ftSrvMGMTIP" :rows="3" ref="formSrcNotify" :readonly="infoWindow" placeholder="Enter management IPs" :no-resize=true />
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row text-left"><b-form-checkbox unchecked-value=0 value=1 :disabled="infoWindow"  v-model="ftSrvMGMT">Manage server</b-form-checkbox></b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row text-left"><b-form-checkbox unchecked-value=0 value=1 :disabled="infoWindow"  v-model="ftSrvDisabled">Disabled</b-form-checkbox></b-col>
          </b-row>
          <!-- keys, notify_list -->
        </div>
      </span>
    </b-modal>


<!-- RPZ Add/Modify -->
    <b-modal id='mConfEditRPZ' centered title="RPZ" @ok="tblMgmtRPZRecord('rpzs')" size="lg">
      <span class='text-center'>
        <div>
          <b-row>
            <b-col :sm="12" class="form_row"><b-input v-model.trim="ftRPZName" :state="rpzNameValid" ref="formRPZName" :readonly="infoWindow" placeholder="Enter RPZ name" /></b-col>
          </b-row>

          <b-row>
            <b-col :sm="6" class="form_row">
            Servers
            </b-col>
            <b-col :sm="6" class="form_row">
            Actions
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="6" class="form_row">
            Sources
            </b-col>
            <b-col :sm="6" class="form_row">
            Whitelists
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="4" class="form_row">
            IOC type
            </b-col>
            <b-col :sm="4" class="form_row">
              <b-form-checkbox unchecked-value=0 value=1 :disabled="infoWindow"  v-model="ftRPZCache">Cache zone</b-form-checkbox>
            </b-col>
            <b-col :sm="4" class="form_row">
              <b-form-checkbox unchecked-value=0 value=1 :disabled="infoWindow"  v-model="ftRPZWildcard">Generate wildcard rules</b-form-checkbox>
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="2" class="form_row"><b-input v-model.trim="ftRPZSOA_Refresh" :state="srvINTValid" ref="formRPZSOA_Refresh" :readonly="infoWindow" placeholder="Refresh" v-b-tooltip.hover title="SOA Record. Zone refresh time"  /></b-col>
            <b-col :sm="2" class="form_row"><b-input v-model.trim="ftRPZSOA_UpdRetry" :state="srvINTValid" ref="formRPZSOA_UpdRetry" :readonly="infoWindow" placeholder="Update retry" v-b-tooltip.hover title="SOA Record. Zone update retry time"  /></b-col>
            <b-col :sm="2" class="form_row"><b-input v-model.trim="ftRPZSOA_Exp" :state="srvINTValid" ref="formRPZSOA_Exp" :readonly="infoWindow" placeholder="Expiration" v-b-tooltip.hover title="SOA Record. Zone expiration time"  /></b-col>
            <b-col :sm="2" class="form_row"><b-input v-model.trim="ftRPZSOA_NXTTL" :state="srvINTValid" ref="formRPZSOA_NXTTL" :readonly="infoWindow" placeholder="NX TTL" v-b-tooltip.hover title="SOA Record. NXDomain TTL"  /></b-col>
            <b-col :sm="2" class="form_row"><b-input v-model.trim="ftRPZAXFR" :state="srvINTValid" ref="formRPZAXFR" :readonly="infoWindow" placeholder="Full update" v-b-tooltip.hover title="Zone full update time"  /></b-col>
            <b-col :sm="2" class="form_row"><b-input v-model.trim="ftRPZIXFR" :state="srvINTValid" ref="formRPZIXFR" :readonly="infoWindow" placeholder="Inc update" v-b-tooltip.hover title="Zone incrimental update time"  /></b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row text-left"><b-form-checkbox unchecked-value=0 value=1 :disabled="infoWindow"  v-model="ftRPZDisabled">Disabled</b-form-checkbox></b-col>
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
    <script src="/js/io2.js"></script>

  </body>
</html>
