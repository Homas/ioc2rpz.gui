<?php
#(c) Vadim Pavlov 2018-2020
#ioc2rpz.gui RpiDNS

require_once 'io2auth.php';
require_once 'io2fun.php';

?>
<div class="v-spacer"></div>
<div>
	<b-card header-class="bold" style="max-height:calc(100vh - 100px)">
		<template slot="header" ><!-- class="py-0 d-flex" -->
      <b-row>
        <b-col cols="0" class="d-none d-lg-block"  lg="2"><span class="bold"><i class="fas fa-atom"></i>&nbsp;&nbsp;RpiDNS</span></b-col>
        <b-col cols="12" lg="10" class="text-right">
          <b-form-group class="m-0">
            <b-button v-b-tooltip.hover title="Add" @click.stop="rpidns_add()" variant="outline-secondary" size="sm"><i class="fa fa-plus"></i></b-button>
            <b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshRpiDNS()"><i class="fa fa-sync"></i></b-button>
          </b-form-group>
        </b-col>
      </b-row>
		</template> 
		<b-alert show class="d-none d-md-block">
			Provision and manage your DNS servers.
		</b-alert>

		<b-container ref="RpiDNSCards" fluid class="pt-2 pb-2 pl-2" style="overflow-y:scroll;position:relative;height: calc(80vh - 100px)">
		<template v-for="cardrow in RpiDNSListDash">			
			<b-card-group deck>
			<template v-for="item in cardrow">
				<b-card :header="'Title'+item.name" header-tag="header" style="max-width: 300px">
					<template v-slot:header>
						<h6 class="mb-0">{{item.name}} <span class="float-right clickable close-icon" data-effect="fadeOut" @click="rpidns_delete(item.id)"><i class="fa fa-times"></i></span> </h6>
					</template>
					<b-card-text>
						OS: {{item.model_name}}<br>
						DNS: {{item.dns_name}}<br>
						RPZ: {{item.rpz.length}}<br>
						Status: {{item.status}}<br>
					</b-card-text>
					<span class="float-right">
						<b-button href="#" variant="primary" size="sm" @click="rpidns_edit(item.id)"><i class="fa fa-edit"></i></b-button>	
						<b-button :id="'rpidns_burl'+item.id" href="#" variant="primary" size="sm"><i class="fa fa-link"></i></b-button>
							<b-popover :target="'rpidns_burl'+item.id" triggers="hover" placement="top">
								<template v-slot:title>Script URL</template>
								URL:
								<b-input-group>
								<b-input type="url" readonly size="sm" :id="'formRpiDNSURL'+item.id" :value="this.document.location.origin +'/rpidns_config.php/rpidns_config?uuid='+item.rpidns_uuid" style="width: 200px;"></b-input>
									<b-button size="sm" slot="append" v-b-tooltip.hover title="Copy" variant="outline-secondary" @click="this.copyToClipboardID('formRpiDNSURL'+item.id)"><i class="fa fa-copy"></i></b-button>
								</b-input-group>
								<br>
								cURL:
								<b-input-group>
								<b-form-input type="text" readonly size="sm" :id="'formRpiDNScURL'+item.id" :value="'curl \''+this.document.location.origin +'/rpidns_config.php/rpidns_config?uuid='+item.rpidns_uuid+'\' -o '+item.name+'_install.sh -k'" style="width: 200px;"></b-form-input>
									<b-button size="sm" slot="append" v-b-tooltip.hover title="Copy" variant="outline-secondary" @click="this.copyToClipboardID('formRpiDNScURL'+item.id)"><i class="fa fa-copy"></i></b-button>
								</b-input-group>
							</b-popover>

						<a :href="this.document.location.origin +'/rpidns_config.php/rpidns_config?uuid='+item.rpidns_uuid" class="btn btn-primary btn-sm"><i class="fa fa-download"></i></a>
					</span>
				</b-card>
			</template>			
			</b-card-group>
			<div class="v-spacer"></div>		
		</template>
		</b-container>


    
  </b-card>
</div>


<!-- modal add RpiDNS --> 
	 <b-modal centered size="lg" :title="RpiDNSLabel" id="mAddRpiDNS" ref="refAddRpiDNS" body-class="pt-0 pb-0" :ok-title="RpiDNSBttn" @ok="add_rpidns" v-cloak>
		 <span class=''>
			<b-container fluid>
				<b-row class="pb-2">
					<b-col md="12" class="p-0">
						<b-input v-model.trim="addRpiDNSName" ref="refAddRpiDNSName" :state="validateName('addRpiDNSName')" :formatter="formatName" ref="formAddRpiDNS" placeholder="Enter name"  v-b-tooltip.hover title="Name" />
					</b-col>
				</b-row>

				<b-row class="pb-1">
					<b-col md="6" class="p-0 pr-1">
						<b-form-select v-model="addRpiDNSModel" :options="addRpiDNSOptions"></b-form-select>
					</b-col>
					<b-col md="6" class="p-0">
						<b-form-select v-model="addRpiDNSServer" :options="addRpiDNSServerOptions"></b-form-select>
					</b-col>
				</b-row>

				<b-row class="pt-1">
					<b-col md="12" class="p-0">
						<div v-if="addRpiDNSRulesCount > (addRpiDNSModel === null?addRpiDNSRulesCount:addRpiDNSModel.max)">
								<b-alert variant="danger" show class="d-none d-md-block">
									Your DNS server may not be able to handle {{addRpiDNSRulesCount}} rules.
								</b-alert>
						</div>
					</b-col>
				</b-row>

				<b-row class="pb-1">
					<b-col md="12" class="p-0">

						<template>
								<div >
									<b-table striped hover sticky-header="300px" no-border-collapse  :items="get_tables" api-url="/io2data.php/rpzs" :fields="tRPZRpiDNS_fields" ref="tRPZRpiDNS" small>
																				 
					
										<template v-slot:cell(rowid)="row">
											<b-form-checkbox :value="row.item.name" :name="'ch_tbl_rpidns'+row.item.name"  v-model="ftRpiDNSRPZ"  /> 
										</template>
					
										<template v-slot:cell(description)="row">
											<b-button size="sm" @click="row.toggleDetails" class="mr-2">
												{{ row.detailsShowing ? 'Hide' : 'Show'}} Description
											</b-button>
										</template>
							
										<template v-slot:row-details="row">
											<span>{{ row.item.description }}</span>
										</template>
					
										<template v-slot:cell(action)="row" :id="'rpidns_action_'+row.item.name">
											<b-form-select size="sm"  v-model="ftRpiDNSRPZAction[row.item.name]">
												<option v-for="action in addRpiDNSFeedActionComp(row.item.action)" :value="action.value">{{action.text}}</option>
											</b-form-select>
										</template>
					
									</b-table>
								</div>
							</template>
	
					</b-col>
				</b-row>

				<b-row class="pb-2">
					<b-col md="3" class="p-0 pr-1">
						<b-form-select v-model="addRpiDNSRedirect">
							<b-form-select-option value="default">Default redirect</b-form-select-option>
							<b-form-select-option value="custom">Custom redirect</b-form-select-option>							
						</b-form-select>
					</b-col>
					<b-col md="3" class="p-0 pr-1">
						<b-form-input placeholder="Custom redirect" v-model="addRpiDNSRedirectURL" :readonly="addRpiDNSRedirect=='default'" :state="validateHostname('addRpiDNSRedirectURL')" :formatter="formatHostname"></b-form-input>
					</b-col>
					<b-col md="3" class="p-0 pr-1">
						<b-form-select v-model="addRpiDNSLogs">
							<b-form-select-option value="local">Log locally</b-form-select-option>
							<b-form-select-option value="forward">Forward logs</b-form-select-option>							
						</b-form-select>
					</b-col>
					<b-col md="3" class="p-0">
						<b-form-input placeholder="Logs destination" v-model="addRpiDNSLogsURL" :readonly="addRpiDNSLogs=='local'" :state="validateHostnameIP('addRpiDNSLogsURL')" :formatter="formatHostnameIP"></b-form-input>
					</b-col>
				</b-row>


				<b-row class="pb-2">
					<b-col md="3" class="p-0 pr-1">
						<b-form-select v-model="addRpiDNSType"  v-b-tooltip.hover title="RpiDNS server type">
							<b-form-select-option value="primary">Primary DNS</b-form-select-option>
							<b-form-select-option value="secondary">Secondary DNS</b-form-select-option>							
						</b-form-select>
					</b-col>
					<b-col md="3" class="p-0 pr-1">
						<b-form-input :placeholder="(addRpiDNSType == 'primary')?'Local subnet':'Primary DNS IP'" v-model="addRpiDNSTypeIPNet" :state="validateHostnameIPNet('addRpiDNSTypeIPNet')" :formatter="formatHostnameIPNet"></b-form-input>
					</b-col>
					<b-col md="3" class="p-0 pr-1">

					</b-col>
					<b-col md="3" class="p-0">
					</b-col>
				</b-row>
				
				<b-row class="pb-1">
					<b-col md="12" class="p-0">
						<b-textarea rows="2" max-rows="3" maxlength="250" v-model.trim="addRpiDNSComment" ref="formAddRpiDNSComment" placeholder="Commentary"  v-b-tooltip.hover title="Commentary" />						
					</b-col>
				</b-row>

				<b-row class="pb-1">
					<b-col md="12" class="p-0">
					<!--
            <b-form-checkbox v-b-popover.hover.top="" v-model="addRpiDNSCheckConf" hidden> Check configuration updates</b-form-checkbox>
          -->
					</b-col>
				</b-row>
			</b-container>

		 </span>
	 </b-modal>


<?php
?>