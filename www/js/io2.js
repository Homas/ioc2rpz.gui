
Vue.component('io2-table', {
  props: ['table','fields'],
  template: `
    <div style="margin: 5px">
        <b-row>
          <b-col md="4" class="my-1">
            <b-button v-b-tooltip.hover title="Add" @click.stop="mgmtRec('add', table, '', $event.target)" variant="outline-secondary" size="sm"><i class="fa fa-plus"></i></b-button>
            <b-button v-b-tooltip.hover title="Refresh" variant="outline-secondary" size="sm" @click.stop="refreshTbl(table)"><i class="fa fa-sync"></i></b-button>
            <b-button size="sm" @click.stop="requestDeleteMult(table)" class="" v-b-tooltip.hover title="Delete selected" variant="outline-secondary"><i class="fa fa-times-circle"></i></b-button>
          </b-col>
          <b-col md="4" class="my-1">
          </b-col>
          <b-col md="4" class="my-1">
            <b-form-group horizontal class="mb-0">
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
            <b-table :busy.sync="busy" :items="get_tables" :id="table" :ref="table" :fields="fields" :api-url="'/io2data.php/'+table" :current-page="currentPage" :per-page="perPage" :no-provider-paging=true :no-provider-sorting=true :no-provider-filtering=true :outlined=true :striped=true :small=true :filter="filter" @filtered="onFiltered" v-model="tblDispl">
              <slot></slot>
              <template slot="actions_e" slot-scope="row">
                <b-button size="sm" @click.stop="mgmtRec('info', table, row, $event.target)" class="" v-b-tooltip.hover title="Information" variant="outline-secondary"><i class="fa fa-info-circle"></i></b-button>
                <b-button size="sm" @click.stop="mgmtRec('export', table, row, $event.target)" class="" v-if="table == 'servers'" v-b-tooltip.hover title="Export Configuration" variant="outline-secondary"><i class="fa fa-download"></i></b-button>
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
  `,
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
          this.$root.$emit('bv::show::modal', 'mConfEditSources');
        break;
        case "add servers":
          this.$root.ftSrvId=-1;
          this.$root.ftSrvName='';
          this.$root.ftSrvIP='';
          this.$root.ftSrvNS='';
          this.$root.ftSrvEmail='';
          this.$root.ftSrvMGMT=0;
          this.$root.ftSrvTKeys=[];
          this.$root.ftSrvMGMTIP='';
          this.$root.ftSrvSType=0; //0 - local, 1 - sftp/scp, 3 - aws s3
          this.$root.ftSrvURL="";
          this.$root.get_lists('tkeys_mgmt','ftSrvTKeysAll');
          this.$root.$emit('bv::show::modal', 'mConfEditSrv');
        break;
        case "info servers":
        case "edit servers":
        case "clone servers":
          this.$root.ftSrvId=action=="clone"?-1:row.item.rowid;
          this.$root.ftSrvName=action=="clone"?row.item.name+"_clone":row.item.name;
          this.$root.ftSrvIP=row.item.ip;
          this.$root.ftSrvNS=row.item.ns;
          this.$root.ftSrvEmail=row.item.email;
          this.$root.ftSrvMGMT=row.item.mgmt;
          this.$root.ftSrvSType=row.item.stype; //0 - local, 1 - sftp/scp, 3 - aws s3
          this.$root.ftSrvURL=row.item.URL;
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
          
          this.$root.$emit('bv::show::modal', 'mConfEditSrv');
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
          this.$root.ftRPZId=-1;
          this.$root.ftRPZName='';
          this.$root.ftRPZSOA_Refresh='';
          this.$root.ftRPZSOA_UpdRetry='';
          this.$root.ftRPZSOA_Exp='';
          this.$root.ftRPZSOA_NXTTL='';
          this.$root.ftRPZAXFR='';
          this.$root.ftRPZIXFR='';
          this.$root.ftRPZCache=1;
          this.$root.ftRPZWildcard=1;

          this.$root.get_lists('rpz_servers','ftRPZSrvsAll');
          this.$root.ftRPZSrvs=[];
          this.$root.get_lists('rpz_tkeys','ftRPZTKeysAll');
          this.$root.ftRPZSrc=[];
          this.$root.get_lists('rpz_sources','ftRPZSrcAll');
          this.$root.ftRPZSrc=[];
          this.$root.get_lists('rpz_whitelists','ftRPZWLAll');
          this.$root.ftRPZWL=[];
          
          this.$root.ftRPZAction="nx";
          this.$root.ftRPZActionCustom=""; 
          this.$root.ftRPZIOCType="m";          
          this.$root.ftRPZNotify="";

          this.$root.$emit('bv::show::modal', 'mConfEditRPZ');
        break;
        case "info rpzs":
        case "edit rpzs":
        case "clone rpzs":
          this.$root.ftRPZId=action=="clone"?-1:row.item.rowid;
          this.$root.ftRPZName=action=="clone"?row.item.name+"_clone":row.item.name;
          this.$root.ftRPZSOA_Refresh=row.item.soa_refresh;
          this.$root.ftRPZSOA_UpdRetry=row.item.soa_update_retry;
          this.$root.ftRPZSOA_Exp=row.item.soa_expiration;
          this.$root.ftRPZSOA_NXTTL=row.item.soa_nx_ttl;
          this.$root.ftRPZAXFR=row.item.axfr_update;
          this.$root.ftRPZIXFR=row.item.ixfr_update;
          this.$root.ftRPZCache=row.item.cache;
          this.$root.ftRPZWildcard=row.item.wildcard;
          this.$root.ftRPZAction=row.item.action;
          this.$root.ftRPZActionCustom=row.item.actioncustom?JSON.parse(row.item.actioncustom):"";  //TODO check
          this.$root.ftRPZIOCType=row.item.ioc_type;
          var RPZNotify='';
          row.item.notify.forEach(function(el) {
            RPZNotify+=el.notify+' ';
          });
          this.$root.ftRPZNotify=RPZNotify.trim();
          
          this.$root.get_lists('rpz_servers','ftRPZSrvsAll');
          var list=[];
          row.item.servers.forEach(function(el) {
            list.push(el.rowid);
          });
          this.$root.ftRPZSrvs=list;
          
          
          this.$root.get_lists('rpz_tkeys','ftRPZTKeysAll');
          list=[];
          row.item.tkeys.forEach(function(el) {
            list.push(el.rowid);
          });
          this.$root.ftRPZTKeys=list;

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
    }
    
  }
});
      
new Vue({
  el: "#app",
  data: {
//          return {
      servers_fields: [
        { key: 'rowid', label: '', sortable: true },
        { key: 'name', label: 'Name', sortable: true },
        { key: 'ip', label: 'MGMT IP/FQDN', sortable: true },
        { key: 'ns', label: 'Name Server' },
        { key: 'email', label: 'Admin Email' },
        { key: 'mgmt', label: 'Monitoring', 'class': 'text-center' },
        { key: 'disabled', label: 'Disabled', 'class': 'text-center' },
        { key: 'actions_e', label: 'Actions', 'class': 'text-center',  'tdClass': 'width200'}
      ],
      tkeys_fields: [
        { key: 'rowid', label: '', sortable: true },
        { key: 'name', label: 'Name', sortable: true },
        { key: 'alg', label: 'Algorithm', sortable: true  },
        { key: 'tkey', label: 'TSIG Key', formatter: (value) => { return value.length>45?value.substring(0, 44)+' ...':value; } },
        { key: 'mgmt', label: 'Management', 'class': 'text-center'},
        //, formatter: (value) => { var cb="<input type='checkbox' "+((value) ? 'checked' : '')+">"; return cb; } 
        { key: 'actions_e', label: 'Actions', 'class': 'text-center',  'tdClass': 'width150'}
      ],
      whitelists_fields: [
        { key: 'rowid', label: '', sortable: true },
        { key: 'name', label: 'Name', sortable: true },
        { key: 'url', label: 'URL', sortable: true  },
        { key: 'regex', label: 'RegEx', sortable: true},
        { key: 'actions_e', label: 'Actions', 'class': 'text-center',  'tdClass': 'width150' }
      ],
      sources_fields: [
        { key: 'rowid', label: '', sortable: true },
        { key: 'name', label: 'Name', sortable: true },
        { key: 'url', label: 'URL', sortable: true, formatter: (value) => { return value.length>35?value.substring(0, 34)+' ...':value; }   },
        { key: 'url_ixfr', label: 'URL update', sortable: true, formatter: (value) => { return value.length>35?value.substring(0, 34)+' ...':value; }   },
        { key: 'regex', label: 'RegEx', sortable: true, formatter: (value) => { return value.length>25?value.substring(0, 24)+' ...':value; }  }, //encodeURI
        { key: 'actions_e', label: 'Actions', 'class': 'text-center',  'tdClass': 'width150' }
      ],
      rpzs_fields: [
        { key: 'rowid', label: '', sortable: true },
        { key: 'name', label: 'Name', sortable: true },
        { key: 'servers_list', label: 'Servers', sortable: true },
        { key: 'ioc_type', label: 'IOC type', sortable: true, formatter: (value) => { return value=="m"?"mixed":value=="i"?"ip":"hostnames"; } },
        { key: 'cache', label: 'Cachable', sortable: true, 'class': 'text-center' },
        { key: 'wildcard', label: 'Wildcards', sortable: true, 'class': 'text-center' },
        { key: 'action', label: 'Responce action', sortable: true, formatter: (value) => { return value=="nx"?"NXDomain":value=="nod"?"NoData":value=="pass"?"Passthru":value=="drop"?"Drop":value=="tcp"?"TCP-Only":"Local Records"; } },
        { key: 'sources_list', label: 'Sources', sortable: true },
        { key: 'update', label: 'Update time', sortable: true  },
        { key: 'disabled', label: 'Disabled', 'class': 'text-center' },
        { key: 'actions_e', label: 'Actions', 'class': 'text-center',  'tdClass': 'width150' }
      ],
      modalMSG: 'Modal',
      errorMSG: 'Error',

      /*
       * TODO check if it possible to move the values to relevant modal forms
       */
      
      deleteRec: 0,
      deleteTbl: '',

      
      cfgTab: 1, //Open CFG page
      //tkeys
      ftKeyId: 0,
      ftKeyName: '',
      ftKey: '',
      ftKeyMGMT: 0,
      ftKeyAlg: "md5",
      tkeys_Alg: ["md5","sha256","sha512"],

      //whitelists n sources
      ftSrcId: 0,
      ftSrcName: '',
      ftSrcURL: '',
      ftSrcURLIXFR: '',
      ftSrcREGEX: '',
      ftSrcType: "sources",
      ftSrcTitle: "Source",
      
      //Servers
      ftSrvId: 0,
      ftSrvName: '',
      ftSrvIP: '',
      ftSrvNS: '',
      ftSrvEmail: '',
      ftSrvMGMT: 0,
      ftSrvMGMTIP: '',
      ftSrvTKeys: [],
      ftSrvTKeysAll: [],
      ftSrvDisabled: 0, //TODO to add
      ftSrvSType: 0, //0 - local, 1 - sftp/scp, 3 - aws s3
      ftSrvURL: "",

      //RPZs
      ftRPZId: 0,
      ftRPZName: '',
      ftRPZSrvs: [],
      ftRPZSrvsAll: [],
      ftRPZTKeys: [],
      ftRPZTKeysAll: [],
      ftRPZWL: [],
      ftRPZWLAll: [],
      ftRPZSrc: [],
      ftRPZSrcAll: [],
      ftRPZNotify: "",
      ftRPZSOA_Refresh: 0,
      ftRPZSOA_UpdRetry: 0,
      ftRPZSOA_Exp: 0,
      ftRPZSOA_NXTTL: 0,
      ftRPZCache: 0,
      ftRPZWildcard: 0,
      ftRPZAction: "nx", //nx/nxdomain, nod/nodata, pass/passthru, drop, tcp/tcp-only, loc/local records
      ftRPZActionCustom: "", 
      ftRPZIOCType: "m", // m/mixed, f/fqdn, i/ip
      ftRPZAXFR: 0,
      ftRPZIXFR: 0,
      ftRPZDisabled: 0, //TODO to add

      RPZ_Act_Options: [
        { value: 'nx', text: 'NXDomain' },
        { value: 'nod', text: 'NoData' },
        { value: 'pass', text: 'Passthru' },
        { value: 'drop', text: 'Drop' },
        { value: 'tcp', text: 'TCP-only' },
        { value: 'loc', text: 'Local records' },
      ],
      RPZ_IType_Options: [
        { value: 'm', text: 'Mixed' },
        { value: 'f', text: 'Hosts' },
        { value: 'i', text: 'IPs' },
      ],
      
//  $sql="create table if not exists rpzs (user_id integer, name text, soa_refresh integer, soa_update_retry integer, soa_expiration integer, soa_nx_ttl integer, cache integer, wildcard integer, action text, ioc_type text, axfr_update integer, ixfr_update integer, foreign key(user_id) references users(rowid));".


      infoWindow: true,
      publishUpdates: false, //TODO save in cookie
      
      mInfoMSGvis: false,
      msgInfoMSG: '',
      
      ftImpServName: '',
      ftImpFiles: [],
      ftImpFileDesc: '',
      ftImpPrefix: '',
      ftImpAction: 0,
      
      
//          }
  },

  computed: {
    validateCustomAction() {return this.infoWindow?null:true},
  },
  
  methods: {
    validateName: function(vrbl){
      return (this.$data[vrbl].length > 2 && /^[a-zA-Z0-9\.\-\_]+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },
    
    validateB64: function(vrbl){
      return (this.$data[vrbl].length>31 && /^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    validateInt: function(vrbl){
      //TODO FIX
      return (this.$data[vrbl].length > 0 && /^[0-9]+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    validateURL: function (vrbl) {
      // file: http: https: ftp:
      return (this.$data[vrbl].length > 0 && /^.+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },
    
    validateIXFRURL: function (vrbl) {
      return this.validateURL(vrbl) || this.$data[vrbl]=='[:AXFR:]';
    },
    
    validateREGEX: function(vrbl){
      //none
      return (this.$data[vrbl].length > 0 && /^.+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },
    
    validateIP: function(vrbl){
      return (this.$data[vrbl].length > 0 && /^.+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },
 
    validateIPList: function(vrbl){
      //this.$data[vrbl].split(/,|\s/g)
      return (this.$data[vrbl].length > 0 && /^.+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    validateHostname: function(vrbl){
      return (this.$data[vrbl].length > 0 && /^.+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },
 
    validateEmail: function(vrbl){
      return (this.$data[vrbl].length > 0 && /^.+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },
 
    get_lists: function(table,variable) {
      let promise = axios.get('/io2data.php/'+table);
      var items=promise.then((data) => {
         this.$root.$data[variable]=data.data;
      }).catch(error => {
        this.$root.$data[variable]=[];
      })
    },
    
    mgmtTableOk: function (response,obj,table){
      if (response.data.status == "ok"){
        obj.$root.$refs['io2tbl_'+table].refreshTblKeepPage(table);
      }else{
        //TODO better error handeling
        alert('sql error while adding '+table);
      };
    },
    
    mgmtTableError: function (errore,obj,table){
      //TODO better error handeling
      alert('error while adding '+table+' ' + rowid);
    },
    
    /*
     *TODO check if PUT/POST json is valid (escape quotes etc)
     */
    //TKeys
    tblMgmtTKeyRecord: function (table) {
      var obj=this;
      this.publishUpdates=true;
      var data={tKeyId: this.ftKeyId, tKeyName: this.ftKeyName, tKey: this.ftKey, tKeyAlg: this.ftKeyAlg, tKeyMGMT: this.ftKeyMGMT};
      if (this.ftKeyId==-1){
        //Add
        axios.post('/io2data.php/'+table,data).then(function (response) {obj.mgmtTableOk(response,obj,table)}).catch(function (error){obj.mgmtTableError(error,obj,table)})
      }else{
        //Modify
        axios.put('/io2data.php/'+table,data).then(function (response) {obj.mgmtTableOk(response,obj,table)}).catch(function (error){obj.mgmtTableError(error,obj,table)})
      };
    },
    
    //Sources/Whitelists
    tblMgmtSrcRecord: function (table) {
      var obj=this;
      this.publishUpdates=true;
      var data={tSrcId: this.ftSrcId, tSrcName: this.ftSrcName, tSrcURL: this.ftSrcURL, tSrcREGEX: this.ftSrcREGEX, tSrcURLIXFR: this.ftSrcURLIXFR};
      if (this.ftSrcId==-1){
        //Add
        axios.post('/io2data.php/'+table,data).then(function (response) {obj.mgmtTableOk(response,obj,table)}).catch(function (error){obj.mgmtTableError(error,obj,table)})
      }else{
        //Modify
        axios.put('/io2data.php/'+table,data).then(function (response) {obj.mgmtTableOk(response,obj,table)}).catch(function (error){obj.mgmtTableError(error,obj,table)})
      };
    },

    //Server
    tblMgmtSrvRecord: function (table) {
      var obj=this;
      this.publishUpdates=true;
      var data={tSrvId: this.ftSrvId, tSrvName: this.ftSrvName, tSrvIP: this.ftSrvIP, tSrvNS: this.ftSrvNS, tSrvEmail: this.ftSrvEmail, tSrvMGMT: this.ftSrvMGMT,
                tSrvMGMTIP: JSON.stringify(this.ftSrvMGMTIP.split(/,|\s/g).filter(String)), tSrvTKeys: JSON.stringify(this.ftSrvTKeys), tSrvDisabled: this.ftSrvDisabled,
                tSrvSType: this.ftSrvSType, tSrvURL: this.ftSrvURL};
      if (this.ftSrvId==-1){
        //Add
        axios.post('/io2data.php/'+table,data).then(function (response) {obj.mgmtTableOk(response,obj,table)}).catch(function (error){obj.mgmtTableError(error,obj,table)})
      }else{
        //Modify
        axios.put('/io2data.php/'+table,data).then(function (response) {obj.mgmtTableOk(response,obj,table)}).catch(function (error){obj.mgmtTableError(error,obj,table)})
      };
    },

    
    //RPZ
    tblMgmtRPZRecord: function (table) {
      var obj=this;
      this.publishUpdates=true;
      var data={tRPZId: this.ftRPZId, tRPZName: this.ftRPZName, tRPZSOA_Refresh: this.ftRPZSOA_Refresh, tRPZSOA_UpdRetry: this.ftRPZSOA_UpdRetry,
                tRPZSOA_Exp: this.ftRPZSOA_Exp, tRPZSOA_NXTTL: this.ftRPZSOA_NXTTL, tRPZCache: this.ftRPZCache,tRPZWildcard: this.ftRPZWildcard, 
                tRPZNotify: JSON.stringify(this.ftRPZNotify.split(/,|\s/g).filter(String)), tRPZSrvs: JSON.stringify(this.ftRPZSrvs),
                tRPZIOCType: this.ftRPZIOCType, tRPZAXFR: this.ftRPZAXFR, tRPZIXFR: this.ftRPZIXFR, tRPZDisabled: this.ftRPZDisabled,
                tRPZTKeys: JSON.stringify(this.ftRPZTKeys), tRPZWL: JSON.stringify(this.ftRPZWL), tRPZSrc: JSON.stringify(this.ftRPZSrc),
                tRPZAction: this.ftRPZAction, tRPZActionCustom: JSON.stringify(this.ftRPZActionCustom)}; //this.ftRPZActionCustom.split(/,|\s/g).filter(String)
      if (this.ftRPZId==-1){
        //Add RPZ
        axios.post('/io2data.php/'+table,data).then(function (response) {obj.mgmtTableOk(response,obj,table)}).catch(function (error){obj.mgmtTableError(error,obj,table)})
      }else{
        //Modify RPZ
        axios.put('/io2data.php/'+table,data).then(function (response) {obj.mgmtTableOk(response,obj,table)}).catch(function (error){obj.mgmtTableError(error,obj,table)})
      };
    },

    tblDeleteRecord: function (table,rowid) {
      var el=this;
      this.publishUpdates=true;
      axios.delete('/io2data.php/'+table+'?rowid='+JSON.stringify(rowid)).then(function (response) {
        if (response.data.status == "ok"){
          el.$root.$refs['io2tbl_'+table].refreshTblKeepPage(table);
        }else{
          //TODO better error handeling
          alert('sql error while deleting '+table+' ' + rowid);
        };
      }).catch(function (error){
        //TODO better error handeling
        alert('error while deleting '+table+' ' + rowid);
      })
    },
    
    pushUpdatestoSRV: function () {
      var obj=this;
      axios.post('/io2data.php/publish_upd').then(function (response) {
        if (response.data.status == "ok"){
          obj.publishUpdates=false;
          obj.showInfo('Configuration will be updated in a few seconds',3);
        }else{
          //TODO better error handeling
          alert('Publishing error');
        };
      }).catch(function (error){
        alert('Publishing error'); //TODO message
      })
    },

    showInfo: function (msg,time) {
      var self=this;
      this.msgInfoMSG=msg;
      this.mInfoMSGvis=true;
      setTimeout(function(){
        self.mInfoMSGvis = false; // Use your variable name
      }, time * 1000);
    },

    
    ImportConfig: function () {
      var file = new FileReader();
      var vm = this;
      //onprogress, onabort, onerror, onloadstart
      file.onload = async function(e) {
        let p1 = axios.get('/io2data.php/servers');
        let p2 = axios.get('/io2data.php/tkeys');
        let p3 = axios.get('/io2data.php/sources');
        let p4 = axios.get('/io2data.php/whitelists');
        let p5 = axios.get('/io2data.php/rpzs');
        var [servers, tkeys, sources, whitelists, rpzs] = await Promise.all([p1, p2, p3, p4, p5]);
        var TKeysAll=[], SrvAll=[], WLAll=[], SrcAll=[], RpzAll=[];
        var TKeys=[], Srv=[], WL=[], Src=[], Rpz=[];
        if (servers.data) servers.data.forEach(function(el){SrvAll[el['name']]=el['rowid']});
        if (tkeys.data) tkeys.data.forEach(function(el){TKeysAll[el['name']]=el['rowid']});
        if (sources.data) sources.data.forEach(function(el){SrcAll[el['name']]=el['rowid']});
        if (whitelists.data) whitelists.data.forEach(function(el){WLAll[el['name']]=el['rowid']});
        if (rpzs.data) rpzs.data.forEach(function(el){RpzAll[el['name']]=el['rowid']});
        
        for(let line of e.target.result.split(/\r|\n/)){
          //this.ftImpServName: '',
          //this.ftImpPrefix: '',
          //this.ftImpAction: 0,
          // {rpz,{
          var l=line.trim();
          if (m = l.match(/^{srv,{"([^"]+)","([^"]+)",\[([^\]]*)\],\[([^\]]*)\]}}\.$/) ){
            Srv['ns']=m[1];Srv['email']=m[2];Srv['tkeys']=[];
            m[3].split(/,|\s|"/g).filter(String).forEach(function(el){Srv['tkeys'].push(el);});
            Srv['notify']=m[4].replace(/"/g,'');//.split(/,|\s|"/g).filter(String);
          };
//{rpz,{"dga.ioc2rpz",21600,3600,2592000,7200,"true","true","nxdomain",["pub_demokey_1","at_demokey_1","priv_key_1"],"fqdn",172800,86400,["dga"],[],["whitelist_1"]}}.

          if (m = l.match(/^{rpz,{"([^"]+)",([0-9]+),([0-9]+),([0-9]+),([0-9]+),"([^"]+)","([^"]+)","?([^"]+|\[[^\]]*\])"?,\[([^\]]*)\],"([^"]+)",([0-9]+),([0-9]+),\[([^\]]*)\],\[([^\]]*)\],\[([^\]]*)\]}}\.$/) ){
            Rpz[m[1]]=m[1];
          };
          if (m = l.match(/^{key,{"([^"]+)","([^"]+)","([^"]+)"}}\.$/)){
            if (vm.ftImpAction==1 || (vm.ftImpAction==2 && (!TKeysAll[m[1]] || (!TKeysAll[vm.ftImpPrefix+m[1]] && vm.ftImpPrefix)))|| (vm.ftImpAction==0 && (!TKeysAll[vm.ftImpPrefix+m[1]]))) {
              vm.ftKeyId=(TKeysAll[vm.ftImpPrefix+m[1]] && vm.ftImpAction==1)?TKeysAll[vm.ftImpPrefix+m[1]]:-1;
              vm.ftKeyName=vm.ftImpAction!=2?vm.ftImpPrefix+m[1]:(TKeysAll[m[1]] && vm.ftImpAction==2)?vm.ftImpPrefix+m[1]:m[1];

              vm.ftKeyAlg=m[2]; vm.ftKey=m[3]; vm.ftKeyMGMT=Srv['tkeys'].includes(m[1])?1:0; //TODO check SRV first
              TKeys[vm.ftKeyName]=vm.ftKeyName;
              TKeys[m[1]]=vm.ftKeyName;
              vm.tblMgmtTKeyRecord('tkeys');
              await sleep(10); //SQLite too slow
            }else{
              TKeys[m[1]]=(TKeysAll[vm.ftImpPrefix+m[1]] && vm.ftImpAction!=2)?vm.ftImpPrefix+m[1]:(TKeysAll[m[1]] && vm.ftImpAction==2)?vm.ftImpPrefix+m[1]:m[1];
            };
          };
          if (m = l.match(/^{whitelist,{"([^"]+)","([^"]+)",(none|"(.*)")}}\.$/)){
            if (vm.ftImpAction==1 || (vm.ftImpAction==2 && (!WLAll[m[1]] || (!WLAll[vm.ftImpPrefix+m[1]] && vm.ftImpPrefix)))|| (vm.ftImpAction==0 && (!WLAll[vm.ftImpPrefix+m[1]]))) {
              vm.ftSrcId=(WLAll[vm.ftImpPrefix+m[1]] && vm.ftImpAction==1)?WLAll[vm.ftImpPrefix+m[1]]:-1;
              vm.ftSrcName=vm.ftImpAction!=2?vm.ftImpPrefix+m[1]:(WLAll[m[1]] && vm.ftImpAction==2)?vm.ftImpPrefix+m[1]:m[1];
              vm.ftSrcURL=m[2]; vm.ftSrcREGEX=m[4]!==undefined?m[4]:m[3]; vm.ftSrcURLIXFR="";
              WL[vm.ftSrcName]=vm.ftSrcName;
              WL[m[1]]=vm.ftSrcName;
              vm.tblMgmtSrcRecord('whitelists');
              await sleep(10); //SQLite too slow
            };
          };
          if (m = l.match(/^{source,{"([^"]+)","([^"]+)","([^"]*)",(none|"(.*)")}}\.$/)){
            if (vm.ftImpAction==1 || (vm.ftImpAction==2 && (!SrcAll[m[1]] || (!SrcAll[vm.ftImpPrefix+m[1]] && vm.ftImpPrefix)))|| (vm.ftImpAction==0 && (!SrcAll[vm.ftImpPrefix+m[1]]))) {
              vm.ftSrcId=(SrcAll[vm.ftImpPrefix+m[1]] && vm.ftImpAction==1)?SrcAll[vm.ftImpPrefix+m[1]]:-1;
              vm.ftSrcName=vm.ftImpAction!=2?vm.ftImpPrefix+m[1]:(SrcAll[m[1]] && vm.ftImpAction==2)?vm.ftImpPrefix+m[1]:m[1];
              vm.ftSrcURL=m[2]; vm.ftSrcURLIXFR=m[3]; vm.ftSrcREGEX=m[5]!==undefined?m[5]:m[4];
              Src[vm.ftSrcName]=vm.ftSrcName;
              Src[m[1]]=vm.ftSrcName;
              vm.tblMgmtSrcRecord('sources'); 
              await sleep(10); //SQLite too slow
            };
          };
        };

        p1 = axios.get('/io2data.php/tkeys');
        p2 = axios.get('/io2data.php/sources');
        p3 = axios.get('/io2data.php/whitelists');
        [tkeys, sources, whitelists] = await Promise.all([p1, p2, p3]);
        var TKeysAll=[], WLAll=[], SrcAll=[];
        if (tkeys.data) tkeys.data.forEach(function(el){TKeysAll[el['name']]=el['rowid']});
        if (sources.data) sources.data.forEach(function(el){SrcAll[el['name']]=el['rowid']});
        if (whitelists.data) whitelists.data.forEach(function(el){WLAll[el['name']]=el['rowid']});
        if(Srv !=[]){
          vm.ftSrvId=-1;
          vm.ftSrvName=vm.ftImpServName;
          //vm.ftSrvIP vm.ftSrvMGMT vm.ftSrvDisabled
          vm.ftSrvNS=Srv['ns'];
          vm.ftSrvEmail=Srv['email'];
          vm.ftSrvMGMTIP=Srv['notify'];
          Srv['tkeys'].forEach(function(el){
            if (TKeys[el] && TKeysAll[TKeys[el]]) vm.ftSrvTKeys.push(TKeysAll[TKeys[el]]);
          });
          vm.ftSrvSType=0;
          vm.ftSrvURL=vm.ftImpFiles[0].name
          vm.tblMgmtSrvRecord('servers');
          await sleep(10); //SQLite too slow
          p1 = axios.get('/io2data.php/servers');
          [servers] = await Promise.all([p1]);
          var SrvId;
          if (servers.data) tkeys.data.forEach(function(el){if (vm.ftSrvName==el['name']) SrvId=el['rowid']});
        };
        
        //vm.tblMgmtRPZRecord('rpzs');
        await sleep(10); //SQLite too slow
      //var data={tRPZId: this.ftRPZId, tRPZName: this.ftRPZName, tRPZSOA_Refresh: this.ftRPZSOA_Refresh, tRPZSOA_UpdRetry: this.ftRPZSOA_UpdRetry,
      //          tRPZSOA_Exp: this.ftRPZSOA_Exp, tRPZSOA_NXTTL: this.ftRPZSOA_NXTTL, tRPZCache: this.ftRPZCache,tRPZWildcard: this.ftRPZWildcard, 
      //          tRPZNotify: JSON.stringify(this.ftRPZNotify.split(/,|\s/g).filter(String)), tRPZSrvs: JSON.stringify(this.ftRPZSrvs),
      //          tRPZIOCType: this.ftRPZIOCType, tRPZAXFR: this.ftRPZAXFR, tRPZIXFR: this.ftRPZIXFR, tRPZDisabled: this.ftRPZDisabled,
      //          tRPZTKeys: JSON.stringify(this.ftRPZTKeys), tRPZWL: JSON.stringify(this.ftRPZWL), tRPZSrc: JSON.stringify(this.ftRPZSrc),
      //          tRPZAction: this.ftRPZAction, tRPZActionCustom: JSON.stringify(this.ftRPZActionCustom)}; //this.ftRPZActionCustom.split(/,|\s/g).filter(String)
        
      }
      file.readAsText(vm.ftImpFiles[0]);      
    },
    
    checkImpFile: function (e) {
      this.ftImpFiles = e.dataTransfer.files;
      this.ftImpFileDesc='File name: '+encodeURI(this.ftImpFiles[0].name)+", size: "+this.ftImpFiles[0].size+' bytes';
    },
    
    alert: function (txt) {
      alert(txt);
    },
    
    copyToClipboard(ref) {
      this.$refs[ref].$el.select();
      document.execCommand('copy');
    },
    genRandom(type){ 
      switch (type){
        case "tkeyName":
          this.$root.ftKeyName='tkey-'+Math.random().toString(36).substr(2, 10)+'-'+Math.random().toString(36).substr(2, 10);
          break;
        case "tkey":
          var key=[];
          key['md5'] = new Uint8Array(64); 
          key['sha256'] = new Uint8Array(32); 
          key['sha512'] = new Uint8Array(64); 
          window.crypto.getRandomValues(key[this.$root.ftKeyAlg]);
          this.$root.ftKey=btoa(String.fromCharCode.apply(null, key[this.$root.ftKeyAlg]));
          break;
      }
    },
    
  }
});


function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}


