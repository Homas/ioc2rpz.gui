 <template>
    <div style="margin: 5px">
        <b-row>
          <b-col md="4" class="my-1">
            <b-button v-b-tooltip.hover title="Add" @click.stop="mgmtRec('add', table, '', $event.target)" variant="outline-secondary" size="sm"><i class="fa fa-plus"></i></b-button>
            <b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl(table)"><i class="fa fa-sync"></i></b-button>
            <b-button size="sm" @click.stop="importRec('import', table, '', $event.target)" class="" v-if="table == 'sources'" v-b-tooltip.hover title="Import" variant="outline-secondary"><i class="fa fa-download"></i></b-button>
            <b-button size="sm" @click.stop="requestDeleteMult(table)" class="" v-b-tooltip.hover title="Delete selected" variant="outline-secondary"><i class="fa fa-times-circle"></i></b-button>
          </b-col>
          <b-col md="4" class="my-1">
          </b-col>
          <b-col md="4" class="my-1">
            <b-form-group class="mb-0">
              <b-input-group>
                <b-form-input v-model="filter" placeholder="Search" />
                <b-input-group-append>
                  <b-btn :disabled="!filter" @click="filter = ''">Clear</b-btn>
                </b-input-group-append>
              </b-input-group>
            </b-form-group>
          </b-col>
        </b-row>
        <b-row>
          <b-col md="12">
            <b-table :busy="busy" :items="get_tables" :id="table" :ref="table" :fields="fields" :api-url="'/io2data.php/'+table" :current-page="currentPage" :per-page="perPage" :no-provider-paging=true :no-provider-sorting=true :no-provider-filtering=true :outlined=true :striped=true :small=true :filter="filter" @filtered="onFiltered" @refreshed="onRefreshred(table)" v-model="tblDispl">
              <template slot="actions_e" slot-scope="row">
                <b-button size="sm" @click.stop="mgmtRec('info', table, row, $event.target)" class="" v-b-tooltip.hover title="Information" variant="outline-secondary"><i class="fa fa-info-circle"></i></b-button>
                <b-button size="sm" @click.stop="mgmtRec('export', table, row, $event.target)" class="" v-if="table == 'servers'" v-b-tooltip.hover title="Export Configuration" variant="outline-secondary"><i class="fa fa-download"></i></b-button>
                <b-button size="sm" @click.stop="mgmtRec('publish', table, row, $event.target)" class="" v-if="table == 'servers'" v-b-tooltip.hover title="Force Publish Configuration" :variant="row.item.cfg_updated == 1?'outline-primary':'outline-secondary'"><i class="fa fa-upload"></i></b-button>
                <b-button size="sm" @click.stop="mgmtRec('edit', table, row, $event.target)" class=""  v-b-tooltip.hover title="Edit" variant="outline-secondary"><i class="fa fa-pencil-alt"></i></b-button>
                <b-button size="sm" @click.stop="mgmtRec('clone', table, row, $event.target)" class="" v-if="table != 'tkeys'" v-b-tooltip.hover title="Clone" variant="outline-secondary"><i class="fa fa-clone"></i></b-button>
                <b-button size="sm" @click.stop="requestDelete(table,row)" class="" v-b-tooltip.hover title="Delete" variant="outline-secondary"><i class="fa fa-times-circle"></i></b-button>
              </template>  

              
              <template slot="HEAD_rowid" slot-scope="table">
                <b-form-checkbox @click.native.stop @change="toggleAll" v-model="checkAll"/>
              </template>              

              <template slot="rowid" slot-scope="row">
                <b-form-checkbox :value="row.item.rowid" :name="'ch_tbl_'+table" v-model="checkedItems"/>
              </template>  
              <template slot="mgmt" slot-scope="row">
                <b-form-checkbox unchecked-value=0 value=1 disabled :checked="row.item.mgmt"/>
              </template>  
              <template slot="wildcard" slot-scope="row">
                <b-form-checkbox unchecked-value=0 value=1 disabled :checked="row.item.wildcard"/>
              </template>                
             <template slot="cache" slot-scope="row" >
                <b-form-checkbox unchecked-value=0 value=1 disabled :checked="row.item.cache"/>
              </template>
              <template slot="update" slot-scope="row">
                {{ row.item.axfr_update }}/{{ row.item.ixfr_update }}
              </template>
<!--
              <template v-slot:cell(disabled)="row">
-->                            
              <template slot="disabled" slot-scope="row" >

               <b-form-checkbox unchecked-value=0 value=1 disabled :checked="row.item.disabled"/>
              </template>
              <template slot="sources_list" slot-scope="row">
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
              <template slot="servers_list" slot-scope="row">
                <div v-for="item in row.item.servers">
                  {{ item.name }}
                </div>
              </template>                
            </b-table>
          </b-col>
        </b-row>
        <b-row>
          <b-col md="2">
            <b-pagination size="sm" :total-rows="totalRows" :per-page="perPage" v-model="currentPage" class="my-0" @input="backToCurrPage" />
          </b-col>
          <b-col md="9">
          </b-col>
          <b-col md="1">
            <b-form-select :options="pageOptions" v-model="perPage" size="sm" class="my-select" />
          </b-col>
        </b-row>
    </div>
</template>

<script>
 props: ['table','fields'], 
 export default {
  data () {
    return {
      busy: false,
      filter: null,
      currentPage: 1,
      toPage: 0,
      perPage: 10,
      totalRows: 0,
      pageOptions: [ 5, 10, 20 ],            
      checkedItems: [],
      tblDispl: [],
      checkAll: false,
    }
  },
  methods: {
    //Get data from table
    
    toggleAll: function (obj){
      if (!this.checkAll) this.checkedItems=this.tblDispl.map(a => a.rowid); else this.checkedItems=[];
    },
    
    get_tables (obj) {
      let promise = axios.get(obj.apiUrl)
      return promise.then((data) => {
        items = data.data
        this.totalRows=items.length;
        return(items)
      }).catch(error => {
        return []
      })
    },
    //Update pagination on a filter
    onFiltered (filteredItems) {
      this.checkedItems = [];
      this.checkAll = false;
      this.totalRows = filteredItems.length;
      this.currentPage = 1;
    },

    onRefreshred (table) {
//      var obj=this;
//      obj.$root.publishUpdates=false;
//      if (table == 'servers') obj.$root.$refs.io2tbl_servers.$refs.servers.value.forEach(function(el) {
//            if (el.cfg_updated == 1) obj.$root.publishUpdates=true;
//        });
    },
    

    
    //Refresh button click
    refreshTbl(table){
      this.$root.$emit('bv::refresh::table', table);
    },
    backToCurrPage(){
      if (this.toPage>0){
        //TODO redo. This is just a workaround which doesn't work correctly
        var maxPages=Math.ceil(this.totalRows/this.perPage);
        this.currentPage = (maxPages>=this.toPage)?this.toPage:maxPages;
        this.toPage=0;
      }
    },
    refreshTblKeepPage(table){
      this.toPage=this.currentPage;
      this.$root.$emit('bv::refresh::table', table);
    },
        
    //Row management buttons
    mgmtRec: function (action, table, row, target) {
      this.$root.infoWindow=action == 'info'?true:false;
      switch (action+' '+table) {
        case "add tkeys":
          this.$root.ftKeyId=-1;
          this.$root.genRandom('tkeyName');
          this.$root.genRandom('tkey');
          this.$root.ftKeyAlg='md5';
          this.$root.ftKeyMGMT=0;
          this.$root.editRow={};
          this.$root.get_lists('tkeys_groups_list','ftTKeysAllGroups');
          this.$root.ftTKeysGroups=[];
          this.$root.$emit('bv::show::modal', 'mConfEditTSIG');
        break;
        case "info tkeys":
        case "edit tkeys":
        case "clone tkeys":
          this.$root.ftKeyId=action=="clone"?-1:row.item.rowid;
          this.$root.ftKeyName=action=="clone"?row.item.name+"_clone":row.item.name;
          this.$root.ftKey=row.item.tkey;
          this.$root.ftKeyAlg=row.item.alg;
          this.$root.ftKeyMGMT=row.item.mgmt;
          this.$root.editRow=row.item;
          this.$root.get_lists('tkeys_groups_list','ftTKeysAllGroups');
          var tkey_groups=[];
          row.item.tkey_groups.forEach(function(el) {
            tkey_groups.push(el.rowid);
          });
          this.$root.ftTKeysGroups=tkey_groups,
          this.$root.$emit('bv::show::modal', 'mConfEditTSIG');
        break;
        case "add whitelists":
        case "add sources":
          this.$root.ftSrcId=-1;
          this.$root.ftSrcName='';
          this.$root.ftSrcURL='';
          this.$root.ftSrcREGEX='';
          this.$root.ftSrcType=table;
          this.$root.ftSrcURLIXFR='';
          this.$root.ftSrcTitle=(table=="sources")?"Source":"Whitelist";
          this.$root.editRow={};
          this.$root.$emit('bv::show::modal', 'mConfEditSources');
        break;
        case "info whitelists":
        case "edit whitelists":
        case "clone whitelists":
        case "info sources":
        case "edit sources":
        case "clone sources":
          this.$root.ftSrcId=action=="clone"?-1:row.item.rowid;
          this.$root.ftSrcName=action=="clone"?row.item.name+"_clone":row.item.name;
          this.$root.ftSrcURL=row.item.url;
          this.$root.ftSrcREGEX=row.item.regex;
          this.$root.ftSrcType=table;
          this.$root.ftSrcURLIXFR=(table=="sources")?row.item.url_ixfr:'';
          this.$root.ftSrcTitle=(table=="sources")?"Source":"Whitelist";
          this.$root.editRow=row.item;
          this.$root.$emit('bv::show::modal', 'mConfEditSources');
        break;
        case "add servers":
          this.$root.ftSrvId=-1;
          this.$root.ftSrvName='';
          this.$root.ftSrvIP='';
          this.$root.ftSrvPubIP='';
          this.$root.ftSrvNS='';
          this.$root.ftSrvEmail='';
          this.$root.ftSrvMGMT=0;
          this.$root.ftSrvTKeys=[];
          this.$root.ftSrvMGMTIP='';
          this.$root.ftSrvSType=0; //0 - local, 1 - sftp/scp, 3 - aws s3
          this.$root.ftSrvURL="";
          this.$root.ftCertFile="";
          this.$root.ftKeyFile="";
          this.$root.ftCACertFile="";
          this.$root.ftCustomConfig="";
          this.$root.ftSrvDisabled=0;
          this.$root.get_lists('tkeys_mgmt','ftSrvTKeysAll');
          this.$root.editRow={};
          this.$root.$emit('bv::show::modal', 'mConfEditSrv');
        break;
        case "info servers":
        case "edit servers":
        case "clone servers":
          this.$root.ftSrvId=action=="clone"?-1:row.item.rowid;
          this.$root.ftSrvName=action=="clone"?row.item.name+"_clone":row.item.name;
          this.$root.ftSrvIP=row.item.ip;
          this.$root.ftSrvPubIP=row.item.pub_ip;
          this.$root.ftSrvNS=row.item.ns;
          this.$root.ftSrvEmail=row.item.email;
          this.$root.ftSrvMGMT=row.item.mgmt;
          this.$root.ftSrvSType=row.item.stype; //0 - local, 1 - sftp/scp, 3 - aws s3
          this.$root.ftSrvURL=row.item.URL;
          this.$root.ftCertFile=row.item.certfile;
          this.$root.ftKeyFile=row.item.keyfile;
          this.$root.ftCACertFile=row.item.cacertfile;
          this.$root.ftCustomConfig=row.item.custom_config;
          this.$root.ftSrvDisabled=row.item.disabled;
          var IPs='';
          row.item.mgmt_ips.forEach(function(el) {
            IPs+=el.mgmt_ip+' ';
          });
          this.$root.ftSrvMGMTIP=IPs.trim();
          this.$root.get_lists('tkeys_mgmt','ftSrvTKeysAll');

          var tkeys=[];
          row.item.tkeys.forEach(function(el) {
            tkeys.push(el.rowid);
          });
          this.$root.ftSrvTKeys=tkeys,
          
          this.$root.editRow=row.item;
          this.$root.editRow.mgmt_ips_str=this.$root.ftSrvMGMTIP;
          this.$root.editRow.tkeys_arr=this.$root.ftSrvTKeys;
          this.$root.$emit('bv::show::modal', 'mConfEditSrv');
        break;
        case "publish servers":
          this.$root.pushUpdatestoSRV(row.item.rowid);
        break;
        case "export servers":
          //alert("export "+row.item.name+" configuration");
          axios.get('/io2data.php/servercfg?rowid='+row.item.rowid,{responseType: 'blob'}).then(function (response) {
                let blob = new Blob([response.data], {type:'text/plain'});
                let link = document.createElement('a');
                link.href = window.URL.createObjectURL(blob);               
                var sFN=response.headers['content-disposition'].match(/filename="([^"]+)"/)[1];
                link.download = sFN?sFN:row.item.name+'.conf';
                link.click();
            }).catch(function (error){alert("export failed")})
          
        break;
        case "add rpzs":
          this.$root.RPZtabI=0;
          this.$root.ftRPZProWindow="hidden";
          this.$root.ftRPZProWindowInfo="";
          this.$root.ftRPZId=-1;
          this.$root.ftRPZName='';
          this.$root.ftRPZSOA_Refresh='86400';
          this.$root.ftRPZSOA_UpdRetry='3600';
          this.$root.ftRPZSOA_Exp='2592000';
          this.$root.ftRPZSOA_NXTTL='7200';
          this.$root.ftRPZAXFR='604800';
          this.$root.ftRPZIXFR='86400';
          this.$root.ftRPZCache=1;
          this.$root.ftRPZWildcard=1;

          this.$root.get_lists('rpz_servers','ftRPZSrvsAll');
          this.$root.ftRPZSrvs=[];
          this.$root.get_lists('rpz_tkeys','ftRPZTKeysAll');
          this.$root.ftRPZTKeys=[];
          this.$root.get_lists('rpz_sources','ftRPZSrcAll');
          this.$root.ftRPZSrc=[];
          this.$root.get_lists('rpz_whitelists','ftRPZWLAll');
          this.$root.ftRPZWL=[];
          
          this.$root.ftRPZAction="nxdomain";
          this.$root.ftRPZActionCustom=""; 
          this.$root.ftRPZIOCType="mixed";          
          this.$root.ftRPZNotify="";
          
          this.$root.ftRPZDisabled=0;

          this.$root.editRow={};
          this.$root.$emit('bv::show::modal', 'mConfEditRPZ');
        break;
        case "info rpzs":
        case "edit rpzs":
        case "clone rpzs":
          this.$root.RPZtabI=0;
          this.$root.ftRPZProWindow=action=="info"?"":"hidden";
          this.$root.ftRPZId=action=="clone"?-1:row.item.rowid;
          this.$root.ftRPZName=action=="clone"?row.item.name+"_clone":row.item.name;
          this.$root.ftRPZSOA_Refresh=`${row.item.soa_refresh}`;
          this.$root.ftRPZSOA_UpdRetry=`${row.item.soa_update_retry}`;
          this.$root.ftRPZSOA_Exp=`${row.item.soa_expiration}`;
          this.$root.ftRPZSOA_NXTTL=`${row.item.soa_nx_ttl}`;
          this.$root.ftRPZAXFR=`${row.item.axfr_update}`;
          this.$root.ftRPZIXFR=`${row.item.ixfr_update}`;
          this.$root.ftRPZCache=row.item.cache;
          this.$root.ftRPZWildcard=row.item.wildcard;
          this.$root.ftRPZAction=row.item.action;
          this.$root.ftRPZActionCustom=row.item.actioncustom?JSON.parse(row.item.actioncustom):"";  //TODO check
          this.$root.ftRPZIOCType=row.item.ioc_type;
          this.$root.ftRPZDisabled=row.item.disabled;
          let vm=this;
          var RPZNotify='';
          
          let dig_srv="";
          let dig_tkey="";
          
          row.item.notify.forEach(function(el) {
            RPZNotify+=el.notify+' ';
          });
          this.$root.ftRPZNotify=RPZNotify.trim();
          
          this.$root.ftRPZProWindowInfo=`<div class="form_row"><b>RPZ Name</b>: <input type=text readonly id='RPZInfoName' value='${row.item.name}'/> <button v-b-tooltip.hover title="Copy" class="btn btn-outline-secondary btn-sm" onclick="copyToClipboardID('RPZInfoName')"><i class="fa fa-copy"></i></button></div>`;

          this.$root.get_lists('rpz_servers','ftRPZSrvsAll');
          let list=[];
          row.item.servers.forEach(function(el) {
            list.push(el.rowid);
            vm.$root.ftRPZProWindowInfo+=`<div class="form_row"><b>DNS Server ${el.name} Public IP</b>: <input type=text readonly id="RPZDNSIP_${el.name}" value='${el.pub_ip}'/><button v-b-tooltip.hover title="Copy" class="btn btn-outline-secondary btn-sm" onclick="copyToClipboardID('RPZDNSIP_${el.name}')"><i class="fa fa-copy"></i></button></div>`;
            dig_srv=dig_srv==""?el.pub_ip:dig_srv;
          });
          this.$root.ftRPZSrvs=list;
          
          
          this.$root.get_lists('rpz_tkeys','ftRPZTKeysAll');
          list=[];
          row.item.tkeys.forEach(function(el) {
            list.push(el.rowid);
            vm.$root.ftRPZProWindowInfo+=`<div class="form_row"><b>TSIG Key</b><input type=text readonly id='RPZTKEYN_${el.name}' value='${el.name}'/><button v-b-tooltip.hover title="Copy" class="btn btn-outline-secondary btn-sm" onclick="copyToClipboardID('RPZTKEYN_${el.name}')"><i class="fa fa-copy"></i></button><input type=text readonly id='RPZTKEYA_${el.name}' value='hmac-${el.alg}'/><button v-b-tooltip.hover title="Copy" class="btn btn-outline-secondary btn-sm" onclick="copyToClipboardID('RPZTKEYA_${el.name}')"><i class="fa fa-copy"></i></button><input size=28 type=text readonly id='RPZTKEYK_${el.name}' value='${el.tkey}'/><button v-b-tooltip.hover title="Copy" class="btn btn-outline-secondary btn-sm" onclick="copyToClipboardID('RPZTKEYK_${el.name}')"><i class="fa fa-copy"></i></button></div>`;
            dig_tkey=dig_tkey==""?"hmac-"+el.alg+":"+el.name+":"+el.tkey:dig_tkey;
          });
          this.$root.ftRPZTKeys=list;

          vm.$root.ftRPZProWindowInfo+="<br><hr>You may check zone availability using the following dig command:<br>";
          vm.$root.ftRPZProWindowInfo+=`<textarea rows="5" style="width:100%;resize: none;" readonly>dig +tcp @${dig_srv} -y ${dig_tkey} ${row.item.name} SOA</textarea>`
          
          this.$root.get_lists('rpz_sources','ftRPZSrcAll');
          list=[];
          row.item.sources.forEach(function(el) {
            list.push(el.rowid);
          });
          this.$root.ftRPZSrc=list;

          this.$root.get_lists('rpz_whitelists','ftRPZWLAll');
          list=[];
          row.item.whitelists.forEach(function(el) {
            list.push(el.rowid);
          });
          this.$root.ftRPZWL=list;

          
         this.$root.editRow=row.item;
         this.$root.editRow.notify_str=this.$root.ftRPZNotify;
         this.$root.editRow.servers_arr=this.$root.ftRPZSrvs;
         this.$root.editRow.tkeys_arr=this.$root.ftRPZTKeys;
         this.$root.editRow.sources_arr=this.$root.ftRPZSrc;
         this.$root.editRow.whitelists_arr=this.$root.ftRPZWL;
         
         this.$root.$emit('bv::show::modal', 'mConfEditRPZ');
        break;
        default:
          alert(action+' '+table); //+' ' + row.item.name          
      };
    },
    requestDelete: function (table,row) {
      this.$root.deleteRec=row.item.rowid;
      this.$root.deleteTbl=table;
      this.$root.modalMSG='<b>Do you want to delete '+row.item.name+'?</b>';
      this.$root.$emit('bv::show::modal', 'mConfDel');
    },
    
    requestDeleteMult: function (table){
      if (this.checkedItems.length>0){
        this.$root.deleteRec=this.checkedItems;
        this.$root.deleteTbl=table;
        this.$root.modalMSG='<b>Do you want to delete '+this.checkedItems.length+' record'+(this.checkedItems.length==1?'':'s')+'?</b>';
        this.$root.$emit('bv::show::modal', 'mConfDel');
      }
    },
    
    importRec: function (action, table, row, target) {
      this.$root.ftImportRec='';
      this.$root.$emit('bv::show::modal', 'mImportRec');
    },
    
  },

	components: {
		io2-table
	}
} 
</script>