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
    <!-- BootstrapVue FA -->
		<!--
    <link type="text/css" rel="stylesheet" href="/css/bootstrap.min.css"/>
    <link type="text/css" rel="stylesheet" href="/css/bootstrap-vue.css"/>
    <link rel="stylesheet" href="/css/all.min.css">
		-->

    <!-- Docker_Comm_Start -->
			<link type="text/css" rel="stylesheet" href="//unpkg.com/bootstrap/dist/css/bootstrap.min.css"/>
			<link type="text/css" rel="stylesheet" href="//unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.css"/>
  		<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.1/css/all.css">
    <!-- Docker_Comm_End -->

    <!-- Docker_CSS -->


    <!-- ioc2rpz CSS -->
    <link type="text/css" rel="stylesheet" href="/css/io2.css?<?=$io2ver?>"/>
  </head>
  <body>
  <div id="app" fluid class="h-100 d-flex flex-column" v-cloak>
    <div id="navbar" v-cloak>
    <b-navbar toggleable="md" type="dark" class="menu-bkgr">

      <b-navbar-toggle target="nav_collapse"></b-navbar-toggle>

      <b-navbar-brand href="#"><h2>ioc2rpz.gui</h2></b-navbar-brand>

      <b-collapse is-nav id="nav_collapse">
        <b-navbar-nav>
 <!--
          <b-nav-item href="#/dash/">Dashboard</b-nav-item>
          <b-nav-item href="#/cfg/">Configuration</b-nav-item>
  -->
          <b-nav-text>Explore: </b-nav-text>
          <b-nav-item href="https://ioc2rpz.net" target="_blank">Community</b-nav-item>
          <b-nav-item href="http://ioc2rpz.com" target="_blank">ioc2rpz</b-nav-item>
          <b-nav-item href="https://github.com/Homas/ioc2rpz.gui" target="_blank">ioc2rpz.gui</b-nav-item>
          <b-nav-item href="https://github.com/Homas/RpiDNS" target="_blank">RpiDNS</b-nav-item>
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
<!--
            <b-dropdown-item-button @click.stop="$emit('bv::show::modal', 'mUProfile')">Profile</b-dropdown-item-button>
-->
            <b-dropdown-item-button @click.stop="signOut">Sign out</b-dropdown-item-button>
          </b-nav-item-dropdown>
        </b-navbar-nav>

      </b-collapse>
    </b-navbar>
  </div>

  <div id="ConfApp" class="h-100 d-flex flex-column" v-cloak>
    <b-container fluid  class="h-100 d-flex flex-column" v-cloak>
        <b-tabs ref="tabs_menu" pills vertical nav-wrapper-class="menu-bkgr h-100" class="h-100 corners" content-class="curl_angels" :value="cfgTab" @input="changeTab" v-cloak>
          <b-tab table="servers" href="#/cfg/servers">
					<template slot="title"><i class="fas fa-server"></i>&nbsp;&nbsp;Servers</template>
<!-- Servers -->
					<div class="v-spacer"></div>
          <b-card body-class="p-2">
            <template slot="header">
              <b-row>
                <b-col cols="0" class="d-none d-lg-block"  lg="2"><span class="bold"><i class="fas fa-server"></i>&nbsp;&nbsp;Servers</span></b-col>
                <b-col cols="12" lg="10" class="text-right">
                  <b-form-group class="m-0">
                    <b-button v-b-tooltip.hover title="Add" @click.stop="mgmtRec('add', 'servers', '', $event.target)" variant="outline-secondary" size="sm"><i class="fa fa-plus"></i></b-button>
                    <b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl('io2tbl_servers')"><i class="fa fa-sync"></i></b-button>
                  </b-form-group>
                </b-col>
              </b-row>
            </template>
            <div>
              <b-row>
                <b-col md="12">

                  <b-table :items="get_tables" id="io2tbl_servers" ref="io2tbl_servers" :fields="servers_fields" api-url="/io2data.php/servers" no-border-collapse striped hover small :filter="servers_filter" responsive :sticky-header="`${logs_height}px`">
                    <template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
                    <template v-slot:cell(actions_e)="row">
                      <b-button size="sm" @click.stop="mgmtRec('info', 'servers', row, $event.target)" v-b-tooltip.hover.bottom title="Information" variant="outline-secondary"><i class="fa fa-info-circle"></i></b-button>
                      <b-button size="sm" @click.stop="mgmtRec('export', 'servers', row, $event.target)" v-b-tooltip.hover.bottom title="Export Configuration" variant="outline-secondary"><i class="fa fa-download"></i></b-button>
                      <b-button size="sm" @click.stop="mgmtRec('publish', 'servers', row, $event.target)" v-b-tooltip.hover.bottom title="Force Publish Configuration" :variant="row.item.cfg_updated == 1?'outline-primary':'outline-secondary'"><i class="fa fa-upload"></i></b-button>
                      <b-button size="sm" @click.stop="mgmtRec('edit', 'servers', row, $event.target)" v-b-tooltip.hover.bottom title="Edit" variant="outline-secondary"><i class="fa fa-pencil-alt"></i></b-button>
                      <b-button size="sm" @click.stop="requestDelete('servers',row)" class="" v-b-tooltip.hover.bottom title="Delete" variant="outline-secondary"><i class="fa fa-times-circle"></i></b-button>
                    </template>

                    <template v-slot:cell(disabled)="row">
                     <b-form-checkbox unchecked-value=0 value=1 disabled :checked="row.item.disabled"/>
                    </template>

                    <template v-slot:cell(mgmt)="row">
                      <b-form-checkbox unchecked-value=0 value=1 disabled :checked="row.item.mgmt"/>
                    </template>

                  </b-table>
                </b-col>
              </b-row>
            </div>
          </b-card>

<!-- Servers -->
          </b-tab>

					<b-tab>
<!-- RpiDNS -->
						<template slot="title"><i class="fas fa-atom"></i><span class="d-none d-lg-inline" v-bind:class="{ hidden: toggleMenu>0 }">&nbsp;&nbsp;RpiDNS</span>&nbsp;<span class="fa fa-beta"></span></template>
						<!--RpiDNS page-->
						<?php
							require 'rpidns.php';
            ?>
						<!--End RpiDNS page-->
<!-- RpiDNS -->
          </b-tab>

          <b-tab table="tkeys_groups" href="#/cfg/tkeys_groups" >
            <template slot="title"><i class="fas fa-users"></i>&nbsp;&nbsp;Key groups</template>
<!-- TKeys Groups -->

					<div class="v-spacer"></div>

            <b-card body-class="p-2">
              <template slot="header">
                <b-row>
                  <b-col cols="0" class="d-none d-lg-block"  lg="2"><span class="bold"><i class="fas fa-users"></i>&nbsp;&nbsp;Key groups</span></b-col>
                  <b-col cols="12" lg="10" class="text-right">
                    <b-form-group class="m-0">
                      <b-button v-b-tooltip.hover title="Add" @click.stop="mgmtRec('add', 'tkeys_groups', '', $event.target)" variant="outline-secondary" size="sm"><i class="fa fa-plus"></i></b-button>
                      <b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl('io2tbl_tkeys_groups')"><i class="fa fa-sync"></i></b-button>
                    </b-form-group>
                  </b-col>
                </b-row>
              </template>
              <div>
                <b-row>
                  <b-col md="12">

                    <b-table :items="get_tables" id="io2tbl_tkeys_groups" ref="io2tbl_tkeys_groups" :fields="tkeys_groups_fields" api-url="/io2data.php/tkeys_groups" no-border-collapse striped hover small :filter="servers_filter" responsive :sticky-header="`${logs_height}px`">
                      <template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
                      <template v-slot:cell(actions_e)="row">
                        <b-button size="sm" @click.stop="mgmtRec('edit', 'tkeys_groups', row, $event.target)" v-b-tooltip.hover.bottom title="Edit" variant="outline-secondary"><i class="fa fa-pencil-alt"></i></b-button>
                        <b-button size="sm" @click.stop="requestDelete('tkeys_groups',row)" class="" v-b-tooltip.hover.bottom title="Delete" variant="outline-secondary"><i class="fa fa-times-circle"></i></b-button>
                      </template>

                      <template v-slot:cell(disabled)="row">
                       <b-form-checkbox unchecked-value=0 value=1 disabled :checked="row.item.disabled"/>
                      </template>

                      <template v-slot:cell(mgmt)="row">
                        <b-form-checkbox unchecked-value=0 value=1 disabled :checked="row.item.mgmt"/>
                      </template>

                    </b-table>
                  </b-col>
                </b-row>
              </div>
            </b-card>

<!-- TKeys Groups -->
          </b-tab>


          <b-tab table="tkeys"href="#/cfg/tkeys" >
            <template slot="title"><i class="fas fa-key"></i>&nbsp;&nbsp;TSIG keys</template>
<!-- TKeys -->
					<div class="v-spacer"></div>
          <b-card body-class="p-2">
            <template slot="header">
              <b-row>
                <b-col cols="0" class="d-none d-lg-block"  lg="2"><span class="bold"><i class="fas fa-key"></i>&nbsp;&nbsp;TSIG Keys</span></b-col>
                <b-col cols="12" lg="10" class="text-right">
                  <b-form-group class="m-0">
                    <b-button v-b-tooltip.hover title="Add" @click.stop="mgmtRec('add', 'tkeys', '', $event.target)" variant="outline-secondary" size="sm"><i class="fa fa-plus"></i></b-button>
                    <b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl('io2tbl_tkeys')"><i class="fa fa-sync"></i></b-button>
                  </b-form-group>
                </b-col>
              </b-row>
            </template>
            <div>
              <b-row>
                <b-col md="12">

                  <b-table :items="get_tables" id="io2tbl_tkeys" ref="io2tbl_tkeys" :fields="tkeys_fields" api-url="/io2data.php/tkeys" no-border-collapse striped hover small :filter="servers_filter" responsive :sticky-header="`${logs_height}px`">
                    <template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
                    <template v-slot:cell(actions_e)="row">
                      <b-button size="sm" @click.stop="mgmtRec('info', 'tkeys', row, $event.target)" v-b-tooltip.hover.bottom title="Information" variant="outline-secondary"><i class="fa fa-info-circle"></i></b-button>
                      <b-button size="sm" @click.stop="mgmtRec('edit', 'tkeys', row, $event.target)" v-b-tooltip.hover.bottom title="Edit" variant="outline-secondary"><i class="fa fa-pencil-alt"></i></b-button>
                      <b-button size="sm" @click.stop="requestDelete('tkeys',row)" class="" v-b-tooltip.hover.bottom title="Delete" variant="outline-secondary"><i class="fa fa-times-circle"></i></b-button>
                    </template>

                    <template v-slot:cell(disabled)="row">
                     <b-form-checkbox unchecked-value=0 value=1 disabled :checked="row.item.disabled"/>
                    </template>

                    <template v-slot:cell(mgmt)="row">
                      <b-form-checkbox unchecked-value=0 value=1 disabled :checked="row.item.mgmt"/>
                    </template>

                  </b-table>
                </b-col>
              </b-row>
            </div>
          </b-card>

<!-- TKeys -->
          </b-tab>
          <b-tab table="whitelists" href='#/cfg/whitelists'>
            <template slot="title"><i class="fas fa-list-alt"></i>&nbsp;&nbsp;Whitelists</template>
<!-- Whitelists -->
            <div class="v-spacer"></div>
            <b-card body-class="p-2">
              <template slot="header">
                <b-row>
                  <b-col cols="0" class="d-none d-lg-block"  lg="2"><span class="bold"><i class="fas fa-list-alt"></i>&nbsp;&nbsp;Whitelists</span></b-col>
                  <b-col cols="12" lg="10" class="text-right">
                    <b-form-group class="m-0">
                      <b-button v-b-tooltip.hover title="Add" @click.stop="mgmtRec('add', 'whitelists', '', $event.target)" variant="outline-secondary" size="sm"><i class="fa fa-plus"></i></b-button>
                      <b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl('io2tbl_whitelists')"><i class="fa fa-sync"></i></b-button>
                    </b-form-group>
                  </b-col>
                </b-row>
              </template>
              <div>
                <b-row>
                  <b-col md="12">

                    <b-table :items="get_tables" id="io2tbl_whitelists" ref="io2tbl_whitelists" :fields="whitelists_fields" api-url="/io2data.php/whitelists" no-border-collapse striped hover small :filter="servers_filter" responsive :sticky-header="`${logs_height}px`">
                      <template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
                      <template v-slot:cell(actions_e)="row">
                        <b-button size="sm" @click.stop="mgmtRec('info', 'whitelists', row, $event.target)" v-b-tooltip.hover.bottom title="Information" variant="outline-secondary"><i class="fa fa-info-circle"></i></b-button>
                        <b-button size="sm" @click.stop="mgmtRec('edit', 'whitelists', row, $event.target)" v-b-tooltip.hover.bottom title="Edit" variant="outline-secondary"><i class="fa fa-pencil-alt"></i></b-button>
                        <b-button size="sm" @click.stop="mgmtRec('clone', 'whitelists', row, $event.target)" v-b-tooltip.hover.bottom title="Clone" variant="outline-secondary"><i class="fa fa-clone"></i></b-button>
                        <b-button size="sm" @click.stop="requestDelete('whitelists',row)" class="" v-b-tooltip.hover.bottom title="Delete" variant="outline-secondary"><i class="fa fa-times-circle"></i></b-button>
                      </template>


                    </b-table>
                  </b-col>
                </b-row>
              </div>
            </b-card>

<!-- Whitelists -->
          </b-tab>

          <b-tab table="sources" href='#/cfg/sources'>
            <template slot="title"><i class="fas fa-list-ul"></i>&nbsp;&nbsp;Sources</template>
<!-- Sources -->
            <div class="v-spacer"></div>
            <b-card body-class="p-2">
              <template slot="header">
                <b-row>
                  <b-col cols="0" class="d-none d-lg-block"  lg="2"><span class="bold"><i class="fas fa-list-ul"></i>&nbsp;&nbsp;Sources</span></b-col>
                  <b-col cols="12" lg="10" class="text-right">
                    <b-form-group class="m-0">
                      <b-button v-b-tooltip.hover title="Add" @click.stop="mgmtRec('add', 'sources', '', $event.target)" variant="outline-secondary" size="sm"><i class="fa fa-plus"></i></b-button>
                      <b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl('io2tbl_sources')"><i class="fa fa-sync"></i></b-button>
                      <b-button size="sm" @click.stop="importRec('import', 'sources', '', $event.target)" class="" v-b-tooltip.hover title="Import" variant="outline-secondary"><i class="fa fa-download"></i></b-button>
                    </b-form-group>
                  </b-col>
                </b-row>
              </template>
              <div>
                <b-row>
                  <b-col md="12">

                    <b-table :items="get_tables" id="io2tbl_sources" ref="io2tbl_sources" :fields="sources_fields" api-url="/io2data.php/sources" no-border-collapse striped hover small responsive :filter="servers_filter" responsive :sticky-header="`${logs_height}px`">
                      <template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
                      <template v-slot:cell(actions_e)="row">
                        <b-button size="sm" @click.stop="mgmtRec('info', 'sources', row, $event.target)" v-b-tooltip.hover.bottom title="Information" variant="outline-secondary"><i class="fa fa-info-circle"></i></b-button>
                        <b-button size="sm" @click.stop="mgmtRec('edit', 'sources', row, $event.target)" v-b-tooltip.hover.bottom title="Edit" variant="outline-secondary"><i class="fa fa-pencil-alt"></i></b-button>
                        <b-button size="sm" @click.stop="mgmtRec('clone', 'sources', row, $event.target)" v-b-tooltip.hover.bottom title="Clone" variant="outline-secondary"><i class="fa fa-clone"></i></b-button>
                        <b-button size="sm" @click.stop="requestDelete('sources',row)" class="" v-b-tooltip.hover.bottom title="Delete" variant="outline-secondary"><i class="fa fa-times-circle"></i></b-button>
                      </template>


                    </b-table>
                  </b-col>
                </b-row>
              </div>
            </b-card>

<!-- Sources -->
          </b-tab>
          <b-tab table="rpzs"  href='#/cfg/rpzs'>
            <template slot="title"><i class="fas fa-rss"></i>&nbsp;&nbsp;RPZs</template>
<!-- RPZs -->
            <div class="v-spacer"></div>
            <b-card body-class="p-2">
              <template slot="header">
                <b-row>
                  <b-col cols="0" class="d-none d-lg-block"  lg="2"><span class="bold"><i class="fas fa-rss"></i>&nbsp;&nbsp;Response policy zones</span></b-col>
                  <b-col cols="12" lg="10" class="text-right">
                    <b-form-group class="m-0">
                      <b-button v-b-tooltip.hover title="Add" @click.stop="mgmtRec('add', 'rpzs', '', $event.target)" variant="outline-secondary" size="sm"><i class="fa fa-plus"></i></b-button>
                      <b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl('io2tbl_rpzs')"><i class="fa fa-sync"></i></b-button>
                    </b-form-group>
                  </b-col>
                </b-row>
              </template>
              <div>
                <b-row>
                  <b-col md="12">

                    <b-table :items="get_tables" id="io2tbl_rpzs" ref="io2tbl_rpzs" :fields="rpzs_fields" api-url="/io2data.php/rpzs" no-border-collapse striped hover small :filter="servers_filter" responsive :sticky-header="`${logs_height}px`">
                      <template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
                      <template v-slot:cell(actions_e)="row">
                        <b-button size="sm" @click.stop="mgmtRec('info', 'rpzs', row, $event.target)" v-b-tooltip.hover.bottom title="Information" variant="outline-secondary"><i class="fa fa-info-circle"></i></b-button>
                        <b-button size="sm" @click.stop="mgmtRec('edit', 'rpzs', row, $event.target)" v-b-tooltip.hover.bottom title="Edit" variant="outline-secondary"><i class="fa fa-pencil-alt"></i></b-button>
                        <b-button size="sm" @click.stop="mgmtRec('clone', 'rpzs', row, $event.target)" v-b-tooltip.hover.bottom title="Clone" variant="outline-secondary"><i class="fa fa-clone"></i></b-button>
                        <b-button size="sm" @click.stop="requestDelete('rpzs',row)" class="" v-b-tooltip.hover.bottom title="Delete" variant="outline-secondary"><i class="fa fa-times-circle"></i></b-button>
                      </template>

                      <template v-slot:cell(mgmt)="row">
                        <b-form-checkbox unchecked-value=0 value=1 disabled :checked="row.item.mgmt"/>
                      </template>

                      <template v-slot:cell(wildcard)="row">
                        <b-form-checkbox unchecked-value=0 value=1 disabled :checked="row.item.wildcard"/>
                      </template>
                     <template v-slot:cell(cache)="row" >
                        <b-form-checkbox unchecked-value=0 value=1 disabled :checked="row.item.cache"/>
                      </template>
                      <template v-slot:cell(update)="row">
                        {{ row.item.axfr_update }}/{{ row.item.ixfr_update }}
                      </template>

                      <template v-slot:cell(disabled)="row">
                       <b-form-checkbox unchecked-value=0 value=1 disabled :checked="row.item.disabled"/>
                      </template>

                      <template v-slot:cell(sources_list)="row">
                        <div v-if="row.item.sources.length<4">
                          <div v-for='item in row.item.sources'>
                            {{ item.name }}
                          </div>
                        </div>
                        <div :id="'rpz_src'+row.item.rowid" v-else>
                          {{ row.item.sources.length }} sources
                           <b-tooltip :target="'rpz_src'+row.item.rowid" placement="right">
                              <div v-for='item in row.item.sources'>
                                {{ item.name }}
                              </div>
                           </b-tooltip>
                        </div>
                      </template>
                      <template v-slot:cell(servers_list)="row">
                        <div v-if="row.item.servers.length<4">
                          <div v-for='item in row.item.servers'>
                            {{ item.name }}
                          </div>
                        </div>
                        <div :id="'rpz_src'+row.item.rowid" v-else>
                          {{ row.item.servers.length }} servers
                           <b-tooltip :target="'rpz_servers'+row.item.rowid" placement="right">
                              <div v-for='item in row.item.servers'>
                                {{ item.name }}
                              </div>
                           </b-tooltip>
                        </div>
                      </template>


                    </b-table>
                  </b-col>
                </b-row>
              </div>
            </b-card>
<!-- RPZs -->
          </b-tab>
          <b-tab title="Utils" href='#/cfg/utils'>
            <template slot="title"><i class="fas fa-tools"></i>&nbsp;&nbsp;Utils</template>
<!-- Utils -->
           <?php
            require 'utils.php';
            ?>
<!-- Utils -->
          </b-tab>



<?php if ($_SESSION['perm'] == 1): ?>
					<b-tab>
<!-- Users -->
						<template slot="title"><i class="fas fa-user-secret"></i><span class="d-none d-lg-inline">&nbsp;&nbsp;Users</span>&nbsp;<span class="fa fa-beta"></span></template>
						<!--Users page-->


					<div class="v-spacer"></div>

            <b-card body-class="p-2">
              <template slot="header">
                <b-row>
                  <b-col cols="0" class="d-none d-lg-block"  lg="2"><span class="bold"><i class="fas fa-user-secret"></i>&nbsp;&nbsp;Users</span></b-col>
                  <b-col cols="12" lg="10" class="text-right">
                    <b-form-group class="m-0">
                      <b-button v-b-tooltip.hover title="Add" @click.stop="mgmtRec('add', 'users', '', $event.target)" variant="outline-secondary" size="sm"><i class="fa fa-plus"></i></b-button>
                      <b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl('io2tbl_users')"><i class="fa fa-sync"></i></b-button>
                    </b-form-group>
                  </b-col>
                </b-row>
              </template>
              <div>
                <b-row>
                  <b-col md="12">

                    <b-table :items="get_tables" id="io2tbl_users" ref="io2tbl_users" :fields="users_fields" api-url="/io2data.php/users" no-border-collapse striped hover small :filter="servers_filter" responsive :sticky-header="`${logs_height}px`">
                      <template v-slot:table-busy><div class="text-center text-second m-0 p-0"><b-spinner class="align-middle"></b-spinner>&nbsp;&nbsp;<strong>Loading...</strong></div></template>
                      <template v-slot:cell(actions_e)="row">
                        <b-button size="sm" @click.stop="mgmtRec('edit', 'users', row, $event.target)" v-b-tooltip.hover.bottom title="Edit" variant="outline-secondary"><i class="fa fa-pencil-alt"></i></b-button>
                        <b-button size="sm" @click.stop="requestDelete('users',row)" class="" v-b-tooltip.hover.bottom title="Delete" variant="outline-secondary"><i class="fa fa-times-circle"></i></b-button>
                      </template>

                      <template v-slot:cell(disabled)="row">
                       <b-form-checkbox unchecked-value=0 value=1 disabled :checked="row.item.disabled"/>
                      </template>

                      <template v-slot:cell(mgmt)="row">
                        <b-form-checkbox unchecked-value=0 value=1 disabled :checked="row.item.mgmt"/>
                      </template>

                    </b-table>
                  </b-col>
                </b-row>
              </div>
            </b-card>


						<!--End Users page-->
<!-- Users -->
          </b-tab>
<?php else: ?>
<?php endif ?>

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
          <b-row>
						<b-col :sm="12" class="form_row text-left">
							<b-form-group :style="{ height: (this.ftTKeysAllGroups.length<4 && ftTKeysAllGroups.length<4?'4':'8')+'em' }" class="items_list"  v-b-tooltip.hover title="TSIG Keys Groups">
								<b-form-checkbox-group :disabled="infoWindow" plain stacked v-model="ftTKeysGroups" :options="ftTKeysAllGroups" />
							</b-form-group>
						</b-col>
					</b-row>
        </div>
      </span>
    </b-modal>

<!-- Tkey Groups Add/Modify -->
    <b-modal id='mTGroups' centered title="TSIG Key Group" @ok="tblMgmtTKeyGRecord($event,'tkeys_groups')" body-class="pt-0 pb-0" size="lg" v-cloak>
      <span class='text-center'>
        <div>
          <b-row>
            <b-col :sm="12" class="form_row"><b-input v-model.trim="ftKeyGName" :state="validateName('ftKeyGName')" :formatter="formatName" ref="formKeyGName" placeholder="Enter group name" /></b-col>
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
            <b-col :sm="12" class="form_row"><b-textarea v-model="ftSrcURL" :state="validateURL('ftSrcURL')" :formatter="formatSourceURL" :rows="3" ref="formSrcURL" :readonly="infoWindow" placeholder="Enter source URL" /></b-col>
          </b-row>
          <b-row v-show="ftSrcType == 'sources'">
            <b-col :sm="12" class="form_row"><b-textarea v-model="ftSrcURLIXFR" :state="validateIXFRURL('ftSrcURLIXFR')" :formatter="formatIXFRURL" :rows="3" ref="formSrcURLIXFR" :readonly="infoWindow" placeholder="Enter source update URL" /></b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row"><b-textarea v-model="ftSrcREGEX" :state="validateREGEX('ftSrcREGEX')" :rows="3" ref="formREGEX" :readonly="infoWindow" placeholder="Enter REGEX" /></b-col>
          </b-row>
          <b-row>
            <b-col :sm="1" class="form_row"></b-col>

            <b-col :sm="2" class="form_row"><b-input v-model.trim="ftSrcMaxIOC" :state="validateInt('ftSrcMaxIOC')" :formatter="formatInt" ref="formSrcMaxIOC" :readonly="infoWindow" placeholder="Max IoCs" v-b-tooltip.hover title="Maximum IoCs (0 - unlimited)"  /></b-col>
            <b-col :sm="2" class="form_row"><b-input v-model.trim="ftSrcHotCacheAXFR" :state="validateInt('ftSrcHotCacheAXFR')" :formatter="formatInt" ref="formSrcHotCacheAXFR" :readonly="infoWindow" placeholder="Hot cache time (full update), in sec" v-b-tooltip.hover title="Hot cache time (full update)"  /></b-col>
            <b-col :sm="2" class="form_row"><b-input v-model.trim="ftSrcHotCacheIXFR" :state="validateInt('ftSrcHotCacheIXFR')" :formatter="formatInt" ref="formSrcHotCacheIXFR" :readonly="infoWindow" placeholder="Hot cache time (incremental update), in sec" v-b-tooltip.hover title="Hot cache time (incremental update)"  /></b-col>

            <b-col :sm="2" class="form_row">
              <b-form-select v-model="ftSrcIoCType" :options="RPZ_IType_Options" :disabled="infoWindow" v-b-tooltip.hover title="IoCs type" />
            </b-col>
            <b-col :sm="3" class="form_row align-self-center text-left">
              <b-form-checkbox unchecked-value=0 value=1 :disabled="infoWindow"  v-model="ftSrcKeepInCache">Keep in cache</b-form-checkbox>

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
            <b-col :sm="4" class="form_row"><b-input v-model.trim="ftSrvPubIP" :state="validateHostnameIP('ftSrvPubIP')" :formatter="formatHostnameIP" ref="formSrvPubIP" :readonly="infoWindow" placeholder="Enter Server's Public IP or FQDN"  v-b-tooltip.hover title="Server's Public IP/FQDN" /></b-col>
            <b-col :sm="4" class="form_row"><b-input v-model.trim="ftSrvIP" :state="validateIP('ftSrvIP')" :formatter="formatIP" ref="formSrvIP" :readonly="infoWindow" placeholder="Enter Server's MGMT IP or FQDN"  v-b-tooltip.hover title="Server's MGMT IP/FQDN" /></b-col>
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
              <b-textarea v-model="ftSrvMGMTIP" :state="validateIPList('ftSrvMGMTIP')" :formatter="formatIPList" style="height: 5em;" :rows="3" ref="formSrcNotify" :readonly="infoWindow" placeholder="Enter management stations IPs" :no-resize=true  v-b-tooltip.hover title="ACL/Management stations IPs" />
            </b-col>
          </b-row>

          <b-row>
            <b-col :sm="4" class="form_row"><b-input v-model.trim="ftCertFile" :state="validateLocFile('ftCertFile')" :formatter="formatLocFile" ref="formCertFile" :readonly="infoWindow" placeholder="Enter certificate file path"  v-b-tooltip.hover title="Certificate file" /></b-col>
            <b-col :sm="4" class="form_row"><b-input v-model.trim="ftKeyFile" :state="validateLocFile('ftKeyFile')" :formatter="formatLocFile" ref="formKeyFile" :readonly="infoWindow" placeholder="Enter private key file path"  v-b-tooltip.hover title="Private key file path" /></b-col>
            <b-col :sm="4" class="form_row"><b-input v-model.trim="ftCACertFile" :state="validateLocFile('ftCACertFile')" :formatter="formatLocFile" ref="formCACertFile" :readonly="infoWindow" placeholder="Enter CA certificate file path"  v-b-tooltip.hover title="CA certificate file path" /></b-col>
          </b-row>

          <b-row>
            <b-col :sm="12" class="form_row text-left"><b-form-checkbox unchecked-value=0 value=1 :disabled="infoWindow"  v-model="ftSrvMGMT">Manage server</b-form-checkbox></b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row text-left">
              <b-form-radio-group :disabled="infoWindow || (ftSrvMGMT == 0)" name="nSrvSType" v-model="ftSrvSType">
                <b-form-radio value="0">Local</b-form-radio>
                <b-form-radio value="1">SCP</b-form-radio>
                <b-form-radio value="2" disabled>AWS S3</b-form-radio>
              </b-form-radio-group>
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row text-left">
              <b-input v-model.trim="ftSrvURL" :state="validateNameAT('ftSrvURL')" :formatter="formatURLAT"  ref="formSrvURL" :readonly="infoWindow" placeholder="Enter file name"  v-b-tooltip.hover title="File Name" />
            </b-col>
          </b-row>
          <b-row>
            <b-col :sm="12" class="form_row text-left">
              <b-textarea :rows="3" v-model.trim="ftCustomConfig" ref="formCustomConfig" :readonly="infoWindow" placeholder="Enter custom configuration"  v-b-tooltip.hover title="Custom configuration" />
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
      <b-tabs :nav-class="ftRPZProWindow" v-model="RPZtabI">
        <b-tab title="Configuration" active>
          <span class='text-center'>
            <div>
              <b-row>
                <b-col :sm="12" class="form_row"><b-input v-model.trim="ftRPZName" :state="validateHostnameNum('ftRPZName')" :formatter="formatName" ref="formRPZName" :readonly="infoWindow" placeholder="Enter RPZ name"  v-b-tooltip.hover title="RPZ Name" /></b-col>
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

                <b-popover title="Local records" target="RPZActionCustom" triggers="hover">
                  Supported records: local_a, local_aaaa, local_cname, local_txt, redirect_ip, redirect_domain.<br>
                  One record per line.<br>
                  <b>Only one local_cname record is allowed</b>.<br>
                  Comments start with "#" or "//".<br>
                  Example:
                  <pre style="font-weight: 400">
#local records
local_a=127.0.0.1
local_aaaa=::1
local_txt=Local TXT record
local_cname=www.example.com
                  </pre>

                </b-popover>

                <b-col :sm="12" class="form_row text-left">
                  <b-textarea id="RPZActionCustom" v-model="ftRPZActionCustom" :state="validateCustomAction(ftRPZActionCustom)" :rows="3" ref="formRPZActionCustom" :readonly="infoWindow" placeholder="Enter local records" :no-resize=true  v-b-tooltip.hover title="Local records" />
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
        </b-tab>
        <b-tab title="Provision Info">
          <div style="height: 400px;display:block;">
          <span class='text-center'>
            <div>
              <b-row><b-col :sm="12"><div class="v-spacer"></div></b-col></b-row>
              <b-row class='d-none d-sm-flex'>
                <b-col :sm="2">
                    <span class="d-flex align-self-center bold">RPZ Name:&nbsp;&nbsp;&nbsp;</span>
                </b-col>
                <b-col :sm="10" class="pl-0">
                  <b-input-group>
                    <b-form-input readonly v-model.trim="ftRPZName" ref="formRPZName"></b-form-input>
                    <b-button v-b-tooltip.hover slot="append" title="Copy" variant="outline-secondary" size="sm" @click="copyToClipboard('formRPZName')"><i class="fa fa-copy"></i></b-button>
                  </b-input-group>
                </b-col>
              </b-row>
              <b-row><b-col :sm="12"><div class="v-spacer"></div></b-col></b-row>
              <b-row class='d-none d-sm-flex'>
                <b-col :sm="2">
                    <span class="d-flex align-self-center bold">Server:&nbsp;&nbsp;&nbsp;</span>
                </b-col>
                <b-col :sm="4" class="pr-0 pl-0">
                  <b-input-group>
                    <b-form-input readonly v-model.trim="ftRPZInfoServerName" ref="formRPZInfoServerName"></b-form-input>
                  </b-input-group>
                </b-col>
                <b-col :sm="6">
                  <b-input-group>
                    <span class="d-flex align-self-center bold">public IP:&nbsp;&nbsp;&nbsp;</span>
                    <b-form-input readonly v-model.trim="ftRPZInfoServerIP" ref="formRPZInfoServerIP"></b-form-input>
                    <b-button v-b-tooltip.hover slot="append" title="Copy" variant="outline-secondary" size="sm" @click="copyToClipboard('formRPZInfoServerIP')"><i class="fa fa-copy"></i></b-button>
                  </b-input-group>
                </b-col>
              </b-row>
              <b-row><b-col :sm="12"><div class="v-spacer"></div></b-col></b-row>
              <b-row class='d-none d-sm-flex'>
                <b-col :sm="2">
                    <span class="d-flex align-self-center bold">Key name:&nbsp;&nbsp;&nbsp;</span>
                </b-col>
                <b-col :sm="2" class="pr-0 pl-0">
                  <b-input-group>
                    <b-form-input readonly v-model.trim="ftRPZInfoTKeyName" ref="formRPZInfoTKeyName"></b-form-input>
                    <b-button v-b-tooltip.hover slot="append" title="Copy" variant="outline-secondary" size="sm" @click="copyToClipboard('formRPZInfoTKeyName')"><i class="fa fa-copy"></i></b-button>
                  </b-input-group>
                </b-col>
                <b-col :sm="4" class="pr-0">
                  <b-input-group>
                    <span class="d-flex align-self-center bold">Alg:&nbsp;&nbsp;&nbsp;</span>
                    <b-form-input readonly v-model.trim="ftRPZInfoTKeyAlg" ref="formRPZInfoTKeyAlg"></b-form-input>
                    <b-button v-b-tooltip.hover slot="append" title="Copy" variant="outline-secondary" size="sm" @click="copyToClipboard('formRPZInfoTKeyAlg')"><i class="fa fa-copy"></i></b-button>
                  </b-input-group>
                </b-col>
                <b-col :sm="4">
                  <b-input-group>
                    <span class="d-flex align-self-center bold">Key:&nbsp;&nbsp;&nbsp;</span>
                    <b-form-input readonly v-model.trim="ftRPZInfoTKey" ref="formRPZInfoTKey"></b-form-input>
                    <b-button v-b-tooltip.hover slot="append" title="Copy" variant="outline-secondary" size="sm" @click="copyToClipboard('formRPZInfoTKey')"><i class="fa fa-copy"></i></b-button>
                  </b-input-group>
                </b-col>
              </b-row>
              <b-row><b-col :sm="12"><div class="v-spacer"></div></b-col></b-row>
              <b-row><b-col :sm="12"><div class="v-spacer"></div></b-col></b-row>
              <b-row><b-col :sm="12"><div class="v-spacer"></div></b-col></b-row>
              <b-row class='d-none d-sm-flex'>
                <b-col :sm="12">
                  <span class="bold float-left">You may check the zone availability using the following dig command:</span>
                  <b-form-textarea id="textarea" v-model="ftRPZInfoDig" rows="6" max-rows="9" readonly></b-form-textarea>
                </b-col>
              </b-row>
            </div>
          </span>

            <span v-html="ftRPZProWindowInfo"></span>
          </div>
        </b-tab>
      </b-tabs>
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
              <div style="margin-bottom:10px"><b-input v-model="ftImpServName" :state="validateName('ftImpServName')" :formatter="formatName" placeholder="Server name" /></div>
              <div style="display: flex">
                <div style="margin-bottom:10px; width:50%"><b-input v-model="ftImpServPubIP" :state="validateIP('ftImpServPubIP')" :formatter="formatIP" placeholder="Public IP" /></div>
                <div style="margin-bottom:10px; width:50%"><b-input v-model="ftImpServMGMTIP" :state="validateIP('ftImpServMGMTIP')" :formatter="formatIP" placeholder="Management IP" /></div>
              </div>
              <div><b-input v-model="ftImpPrefix" :state="validateName('ftImpPrefix')" :formatter="formatName" placeholder="Prefix" /></div>
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

<!-- import ioc2rpz configuration record -->
    <b-modal id='mImportRec' centered title="Import configuration" @ok="ImportConfigLine($event)" body-class="pt-0 pb-0" size="lg" v-cloak>
      <span class='text-center'>
        <div>
          <b-row>
            <b-col :sm="12" class="form_row"><b-textarea v-model="ftImportRec" :rows="5" ref="formImportRec" placeholder="Enter configuration lines" /></b-col>
          </b-row>
        </div>
      </span>
    </b-modal>


 <!-- User's profile Modal -->
    <b-modal centered title="Profile" id="mUProfile" ref="refUProfile" body-class="text-center pt-0 pb-0" size="md" v-cloak>
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

 <!-- Manage users Modal -->
    <b-modal centered title="User" id="mUAdd" ref="refUAdd" body-class="text-center pt-0 pb-0" size="md" @ok="manageUsers($event)"  v-cloak>
      <span class='text-center'>
        <div>
          <b-row>
            <b-col :sm="12" class="form_row">
              <b-input v-model.lazy="ftUNameProf" :state="validateUName('ftUNameProf')" placeholder="Username"  v-b-tooltip.hover title="Username" :formatter="formatName" />
            </b-col>
            <b-col :sm="12" class="form_row">
              <b-form-select v-model="ftUPerm" :disabled="infoWindow" :options="UPerm_Options" class="mb-3"/>
            </b-col>
            <b-col :sm="12" class="form_row">
              <b-input type="password" v-model.trim="ftUPwd" :state="validatePass('ftUPwd')" placeholder="New password"  v-b-tooltip.hover title="Password" />
            </b-col>
            <b-col :sm="12" class="form_row">
              <b-input type="password" v-model.trim="ftUpwdConf" :state="validatePassMatch('ftUPwd','ftUpwdConf')" placeholder="Confirm new password"  v-b-tooltip.hover title="Confirm password" />
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

          <b-row v-show="ftExFormat == 'Infoblox'">
            <b-col :sm="6" class="form_row"><b-input v-model.trim="rpzExportIBMember" :state="validateHostname('rpzExportIBMember')" :formatter="formatName" ref="formMemberName" placeholder="Enter Infoblox member name"  v-b-tooltip.hover title="Infoblox member name" /></b-col>
            <b-col :sm="6" class="form_row"><b-input v-model.trim="rpzExportIBView" :state="validateName('rpzExportIBView')" :formatter="formatName" ref="formDNSView" placeholder="Enter DNS View"  v-b-tooltip.hover title="DNS View" /></b-col>
          </b-row>

        </div>

      </span>
    </b-modal>


<!-- End Modals -->
  </div>
  </div>
  <div class="copyright"><p>Copyright © 2020 Vadim Pavlov</p></div>
<?php
?>
    <script>
      var jsUser='<?= $ioc2Admin ?>';
    </script>
<!--
    <script src="/js/vue.js"></script>
    <script src="/js/polyfill.min.js"></script>
    <script src="/js/bootstrap-vue.js"></script>
    <script src="/js/axios.min.js"></script>
-->

    <!-- Docker_Comm_Start -->
    <script src="https://cdn.jsdelivr.net/npm/vue@latest/dist/vue.js"></script>
    <script src="//unpkg.com/babel-polyfill@latest/dist/polyfill.min.js"></script>
    <script src="//unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.js"></script>
    <script src="//unpkg.com/axios/dist/axios.min.js"></script>
    <!-- Docker_Comm_End -->
    <!-- Docker_JS -->


    <!-- JS -->
    <script src="/js/io2.js?<?=$io2ver?>"></script>

  </body>
</html>
