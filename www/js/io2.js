const io2gui_app = new Vue({
  el: "#app",
  data: {
//          return {

      toggleMenu: 0, // show/hide menu
      cfgTab: 0,
      windowInnerWidth: 800,
      logs_height:150, //logs per page

      servers_fields: [
        //{ key: 'rowid', label: '', sortable: true },
        { key: 'name', label: 'Name', sortable: true },
        { key: 'ip', label: 'MGMT IP/FQDN', sortable: true },
        { key: 'ns', label: 'Name Server' },
        { key: 'email', label: 'Admin Email' },
        { key: 'mgmt', label: 'Manage', 'class': 'text-center' },
        { key: 'disabled', label: 'Disabled', 'class': 'text-center' },
        { key: 'actions_e', label: 'Actions', 'class': 'text-center',  'tdClass': 'width250'}
      ],

      tkeys_groups_fields: [
//        { key: 'rowid', label: '', sortable: true },
        { key: 'group_name', label: 'Group name', sortable: true },
        { key: 'actions_e', label: 'Actions', 'class': 'text-center',  'tdClass': 'width150'}
      ],

      tkeys_fields: [
//        { key: 'rowid', label: '', sortable: true },
        { key: 'name', label: 'Name', sortable: true },
        { key: 'alg', label: 'Algorithm', sortable: true  },
        { key: 'tkey', label: 'TSIG Key', formatter: (value) => { return value.length>45?value.substring(0, 44)+' ...':value; } },
        { key: 'mgmt', label: 'Management', 'class': 'text-center'},
        //, formatter: (value) => { var cb="<input type='checkbox' "+((value) ? 'checked' : '')+">"; return cb; }
        { key: 'actions_e', label: 'Actions', 'class': 'text-center',  'tdClass': 'width150'}
      ],
      whitelists_fields: [
//        { key: 'rowid', label: '', sortable: true },
        { key: 'name', label: 'Name', sortable: true },
        { key: 'url', label: 'URL', sortable: true  },
        { key: 'regex', label: 'RegEx', sortable: true},
        { key: 'actions_e', label: 'Actions', 'class': 'text-center',  'tdClass': 'width150' }
      ],
      sources_fields: [
//        { key: 'rowid', label: '', sortable: true },
        { key: 'name', label: 'Name', sortable: true },
        { key: 'url', label: 'URL', sortable: true, formatter: (value) => { return value.length>35?value.substring(0, 34)+' ...':value; }   },
        { key: 'url_ixfr', label: 'URL update', sortable: true, formatter: (value) => { return value.length>35?value.substring(0, 34)+' ...':value; }   },
        { key: 'regex', label: 'RegEx', sortable: true, formatter: (value) => { return value.length>25?value.substring(0, 24)+' ...':value; }  }, //encodeURI
        { key: 'actions_e', label: 'Actions', 'class': 'text-center',  'tdClass': 'width150' }
      ],
      rpzs_fields: [
//        { key: 'rowid', label: '', sortable: true },
        { key: 'name', label: 'Name', sortable: true },
        { key: 'servers_list', label: 'Servers', sortable: true },
        { key: 'ioc_type', label: 'IOC type', sortable: true }, // formatter: (value) => { return value=="m"?"mixed":value=="i"?"ip":"hostnames"; }
        { key: 'cache', label: 'Cachable', sortable: true, 'class': 'text-center' },
        { key: 'wildcard', label: 'Wildcards', sortable: true, 'class': 'text-center' },
        { key: 'action', label: 'Responce action', sortable: true, formatter: (value) => { return value=="nxdomain"?"NXDomain":value=="nodata"?"NoData":value=="passthru"?"Passthru":value=="drop"?"Drop":value=="tcp-only"?"TCP-Only":"Local Records"; } },
        { key: 'sources_list', label: 'Sources', sortable: true },
        //{ key: 'update', label: 'Update time', sortable: true  },
        { key: 'disabled', label: 'Disabled', 'class': 'text-center' },
        { key: 'actions_e', label: 'Actions', 'class': 'text-center',  'tdClass': 'width150' }
      ],

      //perm loginattempts
      users_fields: [
//        { key: 'rowid', label: '', sortable: true },
        { key: 'name', label: 'Login', sortable: true },
//        { key: 'perm', label: 'Perms', sortable: true, 'tdClass': 'width150'},
//        { key: 'loginattempts', label: 'Login Atempts', sortable: true, 'tdClass': 'width150'},
//        { key: 'lastlogin', label: 'Last Login', sortable: true,  'tdClass': 'width250'},
//        { key: 'lastfailedlogin', label: 'Last Failer Login Attempt', sortable: true, 'tdClass': 'width250'},
        { key: 'actions_e', label: 'Actions', 'class': 'text-center',  'tdClass': 'width150'}
      ],

      modalMSG: 'Modal',
      errorMSG: 'Error',

      /*
       * TODO check if it possible to move the values to relevant modal forms
       */

      deleteRec: 0,
      deleteTbl: '',


      cfgTab: 0, //Open CFG page
      //tkeys
      ftKeyId: 0,
      ftKeyName: '',
      ftKey: '',
      ftKeyMGMT: 0,
      ftKeyAlg: "md5",
      tkeys_Alg: ["md5","sha256","sha512"],
      ftTKeysGroups: [],
      ftTKeysAllGroups: [],

      //tkey groups
      ftKeyGId: -1,
      ftKeyGName: '',

      //whitelists n sources
      ftSrcId: 0,
      ftSrcName: '',
      ftSrcURL: '',
      ftSrcURLIXFR: '',
      ftSrcREGEX: '',
      ftSrcType: "sources",
      ftSrcTitle: "Source",
      ftSrcMaxIOC: '0',
      ftSrcHotCacheAXFR: '900',
      ftSrcHotCacheIXFR: '0',

      //Servers
      ftSrvId: 0,
      ftSrvName: '',
      ftSrvPubIP: '',
      ftSrvIP: '',
      ftSrvNS: '',
      ftSrvEmail: '',
      ftSrvMGMT: 0, //TODO 0 - disabled, 1 - tcp, 2 -tls
      ftSrvMGMTIP: '',
      ftSrvTKeys: [],
      ftSrvTKeysAll: [],
      ftSrvDisabled: 0, //TODO to add
      ftSrvSType: 0, //0 - local, 1 - sftp/scp, 3 - aws s3
      ftSrvURL: "",
      ftCertFile: "",
      ftKeyFile: "",
      ftCACertFile: "",
      ftCustomConfig: "",
      servers_filter: "",


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
      ftRPZSOA_Refresh: '',
      ftRPZSOA_UpdRetry: '',
      ftRPZSOA_Exp: '',
      ftRPZSOA_NXTTL: '',
      ftRPZCache: 0,
      ftRPZWildcard: 0,
      ftRPZAction: "nxdomain", //nx/nxdomain, nod/nodata, pass/passthru, drop, tcp/tcp-only, loc/local records
      ftRPZActionCustom: "",
      ftRPZIOCType: "mixed", // m/mixed, f/fqdn, i/ip
      ftRPZAXFR: '',
      ftRPZIXFR: '',
      ftRPZDisabled: 0, //TODO to add

      RPZ_Act_Options: [
        { value: 'nxdomain', text: 'NXDomain' },
        { value: 'nodata', text: 'NoData' },
        { value: 'passthru', text: 'Passthru' },
        { value: 'drop', text: 'Drop' },
        { value: 'tcp-only', text: 'TCP-only' },
        { value: 'local', text: 'Local records' },
      ],

      RPZ_IType_Options: [
        { value: 'mixed', text: 'mixed' },
        { value: 'fqdn', text: 'fqdn' },
        { value: 'ip', text: 'ip' },
      ],

      ftRPZProWindow: "",
      ftRPZProWindowInfo: "",
      RPZtabI: 0,

      infoWindow: true,
      publishUpdates: false, //TODO save in cookie
      editRow: {},

      mInfoMSGvis: false,
      msgInfoMSG: '',

      ftImpServName: '',
      ftImpServPubIP: '',
      ftImpServMGMTIP: '',
      ftImpFiles: [],
      ftImpFileDesc: '',
      ftImpPrefix: '',
      ftImpAction: 0,

      ftUId: 0,
      ftUName: '',
      ftUNameProf: '',
      ftUCPwd: '',
      ftUPwd: '',
      ftUpwdConf: '',
      ftUPerm: 0,
      UPerm_Options: [
        { value: 1, text: 'Super Admin' },
        { value: 100, text: 'RPZ Admin' },
        { value: 1000, text: 'Read Only', disabled: true},
      ],


      ftExRPZ: [],
      ftExRPZAll: [],
      ftExFormat: '',

      //export RPZ configs
      rpzExportSAll: false,
      rpzExportIBView: 'default',
      rpzExportIBMember: 'infoblox.localdomain',

      ftImportRec: '',

      RpiDNSList: [],
      RpiDNSListDash: [],

			addRpiDNSName: "",
			addRpiDNSComment: "",
			addRpiDNSModel: null,
			addRpiDNSServer: null,
			addRpiDNSOptions: [
				{ id: null, value: null, text: 'Select your hardware/VM', disabled: true },
				{ id: 'pizero', value: {type: 'pizero', max:500000}, text: 'Raspbian on Pi Zero/Zero W' },
				{ id: 'pi123', value: {type: 'pi123', max:500000}, text: 'Raspbian on Pi 1/2/3' },
				{ id: 'pi4-1g', value: {type: 'pi4-1g', max:2500000}, text: 'Raspbian on Pi 4 with 1Gb/2Gb' },
				{ id: 'pi4-4g', value: {type: 'pi4-4g', max:5000000}, text: 'Raspbian on Pi 4 with 4Gb' },
				{ id: 'ubuntu18', value: {type: 'ubuntu18', max:100000000}, text: 'Ubuntu 18.x', disabled: true  },
			],
			addRpiDNSServerOptions: [
				{ id: null, value: null, text: 'Select DNS server', disabled: true },
				{ id: 'bind', value: {type: 'bind', max:0}, text: 'ISC Bind' },
				{ id: 'powerdns', value: {type: 'powerdns', max:500000}, text: 'PowerDNS', disabled: true },
				{ id: 'pidns', value: {type: 'pidns', max:500000}, text: 'piDNS', disabled: true},
			],

			addRpiDNSFeedAction: [
				{ id: 'passthrunolog', value: 'passthru log no', text: 'Passthru - No log', type: "allow" },
				{ id: 'passthru', value: 'passthru', text: 'Passthru', type: "allow" },
				{ id: 'cname', value: 'cname', text: 'Block - Redirect', type: "deny" },
				{ id: 'nxdomain', value: 'nxdomain', text: 'Block - NXDomain', type: "deny" },
				{ id: 'nodata', value: 'nodata', text: 'Block - NoData', type: "deny" },
				{ id: 'drop', value: 'drop', text: 'Block - Drop', type: "deny" },
				{ id: 'disabled', value: 'disabled', text: 'Log only', type: "any" },
			],

    tRPZRpiDNS_fields: [
        { key: 'rowid', label: '', sortable: false,  'tdClass':'width005' },
        { key: 'name', label: 'Name', sortable: false, }, //250  'tdClass': 'width200'
        { key: 'action', label: 'Action', sortable: false,  'tdClass': 'width200' }, //250
			],
			addRpiDNSRulesCount: 0,
			ftRpiDNSRPZ:[],
			ftRpiDNSRPZrecom: [],
			ftRpiDNSRPZAction:{},
			addRpiDNSRedirect: "",
			addRpiDNSRedirectURL: "",
      addRpiDNSType: "",
      addRpiDNSTypeIPNet: "",
			addRpiDNSLogs: "",
			addRpiDNSLogsURL: "",
			addRpiDNSCheckConf: true,
			RpiDNSLabel: "Add RpiDNS",
			RpiDNSBttn: "Add",
			addRpiDNSid: 0,

      ftRPZInfoServerName:'',
      ftRPZInfoServerIP:'',
      ftRPZInfoTKeyName:'',
      ftRPZInfoTKeyAlg:'',
      ftRPZInfoTKey:'',
      ftRPZInfoDig:'',
      ftSrcIoCType: 'mixed',
      ftSrcKeepInCache: 0,

//          }
  },

  mounted: function () {
    if (window.location.hash) {
      var a=window.location.hash.split(/#|\//).filter(String);
      switch (a[0]){
        case "tabs_menu":
          this.cfgTab=parseInt(a[1]);
      };
    };
    this.ftUName=jsUser;
    this.ftUNameProf=jsUser;

		update_window_size(this);
    this.$nextTick(() => {
      window.addEventListener('resize', () => {update_window_size(this);});
    });

    if (window.localStorage.getItem('publishUpdates')){
       this.publishUpdates=(window.localStorage.getItem('publishUpdates')=="true");
    }

    this.refreshRpiDNS();

  },


  computed: {

  },

  methods: {


    refreshRpiDNS: function(){
      let obj=this;
      axios.get('/io2data.php/rpidns').then(function (response) {
				if (/DOCTYPE html/.test(response.data)){
					window.location.reload(true);
				}else if (response.data.status == "success"){
          obj.$root.RpiDNSList=[];
          response.data.data.forEach(function(El){
            El.dns_name=obj.addRpiDNSServerOptions.find(item => {return item.id === El.dns}).text;
            El.model_name=obj.addRpiDNSOptions.find(item => {return item.id === El.model}).text;
            obj.$root.RpiDNSList.push(El);
          });
          splitRpiDNSList(obj);
        }else{
          obj.showInfo(response.data.description,3);
        };
      }).catch(function (error){
        obj.showInfo('Unknown error!!!',3);
      })
    },


		rpidns_add: function(id){
      this.clear_rpidns_modal();
      this.RpiDNSLabel="Add RpiDNS";
      this.RpiDNSBttn="Add";
      this.addRpiDNSid=0;
      let doc=this;
      this.$emit('bv::show::modal', 'mAddRpiDNS');
		},

		rpidns_edit: function(id){
			this.clear_rpidns_modal();
			this.addRpiDNSid=id;
			this.RpiDNSLabel="Edit RpiDNS";
			this.RpiDNSBttn="Save";
			this.addRpiDNSRulesCount=0;
			var obj=this;
			let El = this.RpiDNSList.find(item => {return item.id === id});

			this.addRpiDNSName=El.name;
			this.addRpiDNSModel=this.addRpiDNSOptions.find(item => {return item.id === El.model}).value;
			this.addRpiDNSServer=this.addRpiDNSServerOptions.find(item => {return item.id === El.dns}).value;
			this.addRpiDNSCheckConf=El.updconf;
			this.addRpiDNSRedirect=El.redirect === undefined ? "default":El.redirect;
			this.addRpiDNSRedirectURL=El.redirect_cname === undefined ? "":El.redirect_cname;
			this.addRpiDNSLogs=El.logging === undefined ? "local":El.logging;
			this.addRpiDNSLogsURL=El.logging_host === undefined ? "":El.logging_host;
      this.addRpiDNSType=El.dns_type === undefined ? "primary":El.dns_type;
      this.addRpiDNSTypeIPNet=El.dns_ipnet === undefined ? "":El.dns_ipnet;

			El.rpz.forEach(function(item){if (obj.$refs.io2tbl_rpzs.localItems.filter(e => e.name === item.feed).length > 0) {obj.ftRpiDNSRPZAction[item.feed]=item.action;obj.ftRpiDNSRPZ.push(item.feed);}});

			this.addRpiDNSComment=El.comment;
			this.$emit('bv::show::modal', 'mAddRpiDNS')
		},

    validateHostnameIP: function(vrbl){
			return this.$data[vrbl].length == 0 ? null:checkHostIP(this.$data[vrbl]);
		},

    validateHostnameIPNet: function(vrbl){
			return this.$data[vrbl].length == 0 ? null:checkHostIPNet(this.$data[vrbl]);
		},

    formatHostnameIPNet: function(val,e){
      let a = val.replace(/[^a-zA-Z0-9\.\-\:\/\/\,]/g,"");
      if (e) e.currentTarget.value = a; // a bug in Vue.JS?
      return a;
    },

    formatHostnameIP: function(val,e){
      let a = val.replace(/[^a-zA-Z0-9\.\-\:\/]/g,"");
      if (e) e.currentTarget.value = a; // a bug in Vue.JS?
      return a;
    },

		clear_rpidns_modal: function (){
			this.addRpiDNSName="";
			this.addRpiDNSModel=null;
			this.addRpiDNSServer={type: 'bind', max:0};
			this.addRpiDNSCheckConf=true;
			this.ftRpiDNSRPZ=[];
			this.ftRpiDNSRPZAction={};
			var obj=this;
			obj.$refs.io2tbl_rpzs.localItems.forEach(function(item){obj.ftRpiDNSRPZAction[item.name]=((item.type=="v"||item.type=="w")?"passthru log no":"cname");});
			this.addRpiDNSComment="";
			this.addRpiDNSRulesCount=0;
			this.addRpiDNSRedirect="default";
			this.addRpiDNSRedirectURL="";
			this.addRpiDNSLogs="local";
			this.addRpiDNSLogsURL="";
      this.addRpiDNSType="primary";
      this.addRpiDNSTypeIPNet="";
		},

		add_rpidns: function(event){
			if (this.validateHostnameOnly('addRpiDNSName') && this.ftRpiDNSRPZ.length>0 && this.addRpiDNSModel !==null && this.addRpiDNSServer !==null && ((this.addRpiDNSType=='secondary' && checkIP(this.addRpiDNSTypeIPNet)) || this.addRpiDNSType=='primary')){
				let doc=this;
				var data,promise;
				let rpzfeeds = [];
				this.ftRpiDNSRPZ.forEach(function(item){rpzfeeds.push({"feed":item,"action":doc.ftRpiDNSRPZAction[item]})});

				data={id:this.addRpiDNSid, name: this.addRpiDNSName, comment: this.addRpiDNSComment, model:this.addRpiDNSModel.type, dns:this.addRpiDNSServer.type, updconf: this.addRpiDNSCheckConf, rpz: JSON.stringify(rpzfeeds), redirect:this.addRpiDNSRedirect, redirect_cname:this.addRpiDNSRedirectURL, logging:this.addRpiDNSLogs, logging_host:this.addRpiDNSLogsURL, dns_type:this.addRpiDNSType, dns_ipnet:this.addRpiDNSTypeIPNet};

				if (this.RpiDNSBttn=="Add") promise = axios.post('/io2data.php/rpidns',data); else promise = axios.put('/io2data.php/rpidns',data);
				var items=promise.then((data) => {
					if (data.data[0].status=="success") {
						doc.clear_rpidns_modal();
						doc.refreshRpiDNS();
					}else{
						doc.showInfo(data.data[0].description,3);
					}
				}).catch(error => {
					doc.showInfo('Unknown error!!!',3);
				})

			}else{
				event.preventDefault();

        if (!this.validateHostnameOnly('addRpiDNSName') || this.addRpiDNSName.length==0) this.showInfo('Please set correct RpiDNS name',3);//	this.$refs.refAddRpiDNSName.focus();
        else if (this.addRpiDNSType=='secondary' && !checkIP(this.addRpiDNSTypeIPNet)) this.showInfo('Please set a primary DNS server IP',3);
        else if (this.addRpiDNSModel ==null) this.showInfo('Please select RpiDNS model',3);
        else if (this.addRpiDNSServer ==null) this.showInfo('Please select DNS server software',3);
        else if (this.ftRpiDNSRPZ.length == 0) this.showInfo('Please select RPZ feeds',3);
        else this.showInfo('Please define all fields',3);
			};

		},

		rpidns_delete: function(rpidns_id){

			this.$bvModal.msgBoxConfirm('You are about to delete selected RpiDNS. This action is irreversible!', {
				title: 'Please confirm the action',
				size: 'md',
				buttonSize: 'md',
				okVariant: 'danger',
				okTitle: 'YES',
				cancelTitle: 'NO',
				footerClass: 'p-2',
				bodyClass: 'text-center',
				hideHeaderClose: false,
				centered: true
			})
				.then(value => {

					if (value) {
						let doc=this;
						var data={id: rpidns_id};
						let promise = axios.delete('/io2data.php/rpidns',{data});
						var items=promise.then((data) => {
							if (data.data[0].status=="success") {
								doc.refreshRpiDNS();
							}else{
								doc.showInfo(data.data[0].description,3);
							}
						}).catch(error => {
							doc.showInfo('Unknown error!!!',3);
						})

					};

			});
		},


		addRpiDNSFeedActionComp: function (type){
			//return this.addRpiDNSFeedAction.filter(item => (item.type == "allow" && (type=="w"||type=="v"))||item.type == "any"||(item.type == "deny" && type!="w" && type!="v"));
      return this.addRpiDNSFeedAction;
		},

    validateCustomAction:function(CustomActions) {
     let good=CustomActions==''?null:true;
     let gotcname=0; //only one CNAME rule is allowed
     CustomActions.split(/\r\n|\n|\r/).forEach(function(action) {
      rule=action.trim().split("=",2)
      switch(rule[0]){
        case "local_aaaa":
          good=good && typeof rule[1] !== 'undefined' && rule[1] != "" && checkIPv6(rule[1]);
          break;
        case "local_a":
          good=good && typeof rule[1] !== 'undefined' && rule[1] != "" && checkIPv4(rule[1]);
          break;
        case "redirect_ip":
          good=good && typeof rule[1] !== 'undefined' && rule[1] != "" && checkIP(rule[1]);
          break;
        case "local_cname":
        case "redirect_domain":
          good=good && typeof rule[1] !== 'undefined' && rule[1] != "" && checkHostName(rule[1]);
          gotcname++;
          break;
        case "local_txt":
          good = good && typeof rule[1] !== 'undefined' && rule[1] != "" && true;
          break;
        default:
          good = good && (action.startsWith("#") || action.startsWith("//") || action=="")
          //check comments which start with # or //
      };
     });
     return good && (gotcname <= 1);
    },

    get_tables (obj) {
      let promise = axios.get(obj.apiUrl)
      return promise.then((data) => {
				if (/DOCTYPE html/.test(data.data)){
					window.location.reload(true);
				}else{
          items = data.data
          this.totalRows=items.length;
          return(items)
        };
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

    refreshTbl(table){
      this.$root.$emit('bv::refresh::table', table);
    },

    importRec: function (action, table, row, target) {
      this.$root.ftImportRec='';
      this.$root.$emit('bv::show::modal', 'mImportRec');
    },

     mgmtRec: function (action, table, row, target) {
      this.$root.infoWindow=action == 'info'?true:false;
      switch (action+' '+table) {
        case "add users":
          this.$root.ftUId=0;
          this.$root.ftUNameProf="";
          this.$root.ftUPerm=1;
          this.$root.ftUPwd="";
          this.$root.ftUpwdConf="";
          this.$root.$emit('bv::show::modal', 'mUAdd');
        break;
        case "edit users":
          this.$root.ftUId=row.item.rowid;
          this.$root.ftUNameProf=row.item.name;
          this.$root.ftUPerm=row.item.perm;
          this.$root.ftUPwd="";
          this.$root.ftUpwdConf="";
          this.$root.$emit('bv::show::modal', 'mUAdd');
        break;
        case "add tkeys_groups":
          this.$root.ftKeyGId=-1;
          this.$root.ftKeyGName="";
          this.$root.$emit('bv::show::modal', 'mTGroups');
        break;
        case "edit tkeys_groups":
          this.$root.ftKeyGId=row.item.rowid;
          this.$root.ftKeyGName=row.item.group_name;
          this.$root.$emit('bv::show::modal', 'mTGroups');
        break;
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
          this.$root.ftSrcMaxIOC='0';
          this.$root.ftSrcHotCacheAXFR='900';
          this.$root.ftSrcHotCacheIXFR='0';
          this.$root.ftSrcTitle=(table=="sources")?"Source":"Whitelist";
          this.$root.editRow={};
          this.$root.ftSrcIoCType='mixed';
          this.$root.ftSrcKeepInCache=0;
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
          this.$root.ftSrcIoCType=row.item.ioc_type;
          this.$root.ftSrcKeepInCache=row.item.keep_in_cache;
          this.$root.ftSrcType=table;
          this.$root.ftSrcURLIXFR=(table=="sources")?row.item.url_ixfr:'';
          this.$root.ftSrcMaxIOC=`${row.item.max_ioc}`;
          this.$root.ftSrcHotCacheAXFR=`${row.item.hotcache_time}`;
          this.$root.ftSrcHotCacheIXFR=`${row.item.hotcacheixfr_time}`;
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
              if (/DOCTYPE html/.test(response.data)){window.location.reload(true);}else{
                let blob = new Blob([response.data], {type:'text/plain'});
                let link = document.createElement('a');
                link.href = window.URL.createObjectURL(blob);
                var sFN=response.headers['content-disposition'].match(/filename="([^"]+)"/)[1];
                link.download = sFN?sFN:row.item.name+'.conf';
                link.click();
              };
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

//          this.$root.ftRPZProWindowInfo=`<div class="form_row"><b>RPZ Name</b>: <input type=text readonly id='RPZInfoName' value='${row.item.name}'/> <button v-b-tooltip.hover title="Copy" class="btn btn-outline-secondary btn-sm" onclick="copyToClipboardID('RPZInfoName')"><i class="fa fa-copy"></i></button></div>`;

          this.$root.get_lists('rpz_servers','ftRPZSrvsAll');
          let list=[];
          row.item.servers.forEach(function(el) {
            list.push(el.rowid);
//            vm.$root.ftRPZProWindowInfo+=`<div class="form_row"><b>DNS Server ${el.name} Public IP</b>: <input type=text readonly id="RPZDNSIP_${el.name}" value='${el.pub_ip}'/><button v-b-tooltip.hover title="Copy" class="btn btn-outline-secondary btn-sm" onclick="copyToClipboardID('RPZDNSIP_${el.name}')"><i class="fa fa-copy"></i></button></div>`;
            dig_srv=dig_srv==""?el.pub_ip:dig_srv;
          });
          this.$root.ftRPZSrvs=list;

        this.$root.ftRPZInfoServerName=Array.isArray(row.item.servers) && row.item.servers.length?row.item.servers[0].name:'';
        this.$root.ftRPZInfoServerIP=Array.isArray(row.item.servers) && row.item.servers.length?row.item.servers[0].pub_ip:'';

        this.$root.ftRPZInfoTKeyName=Array.isArray(row.item.tkeys) && row.item.tkeys.length?row.item.tkeys[0].name:'';
        this.$root.ftRPZInfoTKeyAlg=Array.isArray(row.item.tkeys) && row.item.tkeys.length?'hmac-'+row.item.tkeys[0].alg:'';
        this.$root.ftRPZInfoTKey=Array.isArray(row.item.tkeys) && row.item.tkeys.length?row.item.tkeys[0].tkey:'';


          this.$root.get_lists('rpz_tkeys','ftRPZTKeysAll');
          list=[];
          row.item.tkeys.forEach(function(el) {
            list.push(el.rowid);
//            vm.$root.ftRPZProWindowInfo+=`<div class="form_row"><b>TSIG Key</b><input type=text readonly id='RPZTKEYN_${el.name}' value='${el.name}'/><button v-b-tooltip.hover title="Copy" class="btn btn-outline-secondary btn-sm" onclick="copyToClipboardID('RPZTKEYN_${el.name}')"><i class="fa fa-copy"></i></button><input type=text readonly id='RPZTKEYA_${el.name}' value='hmac-${el.alg}'/><button v-b-tooltip.hover title="Copy" class="btn btn-outline-secondary btn-sm" onclick="copyToClipboardID('RPZTKEYA_${el.name}')"><i class="fa fa-copy"></i></button><input size=28 type=text readonly id='RPZTKEYK_${el.name}' value='${el.tkey}'/><button v-b-tooltip.hover title="Copy" class="btn btn-outline-secondary btn-sm" onclick="copyToClipboardID('RPZTKEYK_${el.name}')"><i class="fa fa-copy"></i></button></div>`;
            dig_tkey=dig_tkey==""?"hmac-"+el.alg+":"+el.name+":"+el.tkey:dig_tkey;
          });
          this.$root.ftRPZTKeys=list;

//          vm.$root.ftRPZProWindowInfo+="<br><hr>You may check zone availability using the following dig command:<br>";
//          vm.$root.ftRPZProWindowInfo+=`<textarea rows="5" style="width:100%;resize: none;" readonly>dig +tcp @${dig_srv} -y ${dig_tkey} ${row.item.name} SOA</textarea>`

          this.$root.ftRPZInfoDig=this.$root.ftRPZInfoServerIP && this.$root.ftRPZInfoTKeyName?"dig +tcp @"+dig_srv+" -y "+dig_tkey+" "+row.item.name+" SOA":'';

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

    validateName: function(vrbl){
      return (this.$data[vrbl].length >= 3 && /^[a-zA-Z0-9\.\-\_]+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    validateNameAT: function(vrbl){
      return (this.$data[vrbl].length >= 3 && /^[a-zA-Z0-9@\/\.\-\_]+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    validateUName: function(vrbl){
      //todo check if name is unique
      return (this.$data[vrbl].length >= 3 && /^[a-zA-Z0-9\.\-\_]+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    formatName: function(val,e){
      let a = val.replace(/[^a-zA-Z0-9\.\-\_]/g,"");
      if (e) e.currentTarget.value = a; // a bug in Vue.JS?
      return a;
    },

    validateB64: function(vrbl){
      return (this.$data[vrbl].length>16 && /^(?:[A-Za-z0-9\+\/]{4})*(?:[A-Za-z0-9\+\/]{2}==|[A-Za-z0-9\+\/]{3}=)?$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    formatB64: function(val,e){
      let a = val.replace(/[^A-Za-z0-9/=\+\/]/g,"");
      if (e) e.currentTarget.value = a; // a bug in Vue.JS?
      return a;
    },

    validateInt: function(vrbl){
      return (this.$data[vrbl].length > 0 && /^[0-9]+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    formatInt: function(val,e){
      let a = val.replace(/[^0-9]/g,"");
      if (e) e.currentTarget.value = a; // a bug in Vue.JS?
      return a;
    },

    validateURL: function (vrbl) {
      return (this.$data[vrbl].length > 0 && checkSourceURL(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },


    formatURL: function(val,e){
      let a = val.replace(/[^A-Za-z0-9/=:\?#.\-_&]/g,"");
      if (e) e.currentTarget.value = a; // a bug in Vue.JS?
      return a;
    },

    formatURLAT: function(val,e){
      let a = val.replace(/[^A-Za-z0-9@/=:\?#.\-_&]/g,"");
      if (e) e.currentTarget.value = a; // a bug in Vue.JS?
      return a;
    },

    formatSourceURL: function(val,e){
      let a;
      if (/^shell:/.test(val) || /^file:/.test(val) || /^[:AXFR:]/.test(val)) a=val; else a = val.replace(/[^A-Za-z0-9/=:\?#.\-_&]/g,"");
      if (e) e.currentTarget.value = a; // a bug in Vue.JS?
      return a;
    },

    validateLocFile: function (vrbl) {
      return (this.$data[vrbl].length > 0) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    formatLocFile: function(val,e){
      let a = val.replace(/[^A-Za-z0-9/=:\?#.-_&]/g,"");
      if (e) e.currentTarget.value = a; // a bug in Vue.JS?
      return a;
    },

    validateIXFRURL: function (vrbl) {
      return this.$data[vrbl].length == 0 ? null: (this.validateURL(vrbl) || this.$data[vrbl]=='[:AXFR:]' || (/^\[:AXFR:\]((\?|\&)[;&a-zA-Z0-9\d%_.~+=-]*)?(\[:FTimestamp:\]|\[:ToTimestamp:\])?(\#[-a-zA-Z0-9\d_]*)?(\[:FTimestamp:\]|\[:ToTimestamp:\])?$/.test(this.$data[vrbl])));
    },

    formatIXFRURL: function(val,e){
      let a;// = val.replace(/[^A-Za-z0-9/=:\?#.\-_\[\]&]/g,"");
      if (/^shell:/.test(val) || /^file:/.test(val) || /^[:AXFR:]/.test(val)) a=val; else a = val.replace(/[^A-Za-z0-9/=:\?#.-_&]/g,"");
      if (e) e.currentTarget.value = a; // a bug in Vue.JS?
      return a;
    },

    validateREGEX: function(vrbl){
      //none
      return (this.$data[vrbl].length > 0 && /^.+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    validateIP: function(vrbl){
      return (this.$data[vrbl].length > 0 && checkIP(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    formatIP: function(val,e){
      let a = val.replace(/[^0-9\.:\-]/g,"");
      if (e) e.currentTarget.value = a; // a bug in Vue.JS?
      return a;
    },

    validateIPList: function(vrbl){
      return (this.$data[vrbl].length > 0 && this.$data[vrbl].trim().split(/,|\s|\;/g).every(checkIP)) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    formatIPList: function(val,e){
      let a = val.replace(/[^0-9\.:\-,; ]/g,"");
      if (e) e.currentTarget.value = a; // a bug in Vue.JS?
      return a;
    },


    formatHostnameIP: function(val,e){
      let a = val.replace(/[^a-zA-Z0-9\.\-\:\/]/g,"");
      if (e) e.currentTarget.value = a; // a bug in Vue.JS?
      return a;
    },

    validateHostnameIP: function(vrbl){
			return this.$data[vrbl].length == 0 ? null:checkHostIP(this.$data[vrbl]);
		},

    validateHostnameIPNet: function(vrbl){
			return this.$data[vrbl].length == 0 ? null:checkHostIPNet(this.$data[vrbl]);
		},


    validateHostname: function(vrbl){
      return (this.$data[vrbl].length > 5 && checkHostName(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    validateHostnameNum: function(vrbl){
      return (this.$data[vrbl].length > 5 && checkHostNameNum(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    validateHostnameOnly: function(vrbl){
      return (this.$data[vrbl].length > 5 && checkHostNameOnly(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    formatHostname: function(val,e){
      let a = val.replace(/[^a-zA-Z0-9\.\-\_]/g,"");
      if (e) e.currentTarget.value = a; // a bug in Vue.JS?
      return a;
    },

    validateEmail: function(vrbl){
      return (this.$data[vrbl].length > 0 && /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/.test(this.$data[vrbl].toLowerCase())) ? true : this.$data[vrbl].length == 0 ? null:false;
    },

    formatEmail: function(val,e){
      let a = val.replace(/[^a-zA-Z0-9\.\-\_@]/g,"");
      if (e) e.currentTarget.value = a; // a bug in Vue.JS?
      return a;
    },

    validatePass: function(pass1){
      return ((this.$data[pass1].length > 7 && /([0-9])/.test(this.$data[pass1]) && /([a-z])/.test(this.$data[pass1]) && /([A-Z])/.test(this.$data[pass1]) && /([!,%,&,@,#,$,^,*,?,_,~,\,,\.])/.test(this.$data[pass1])) || this.$data[pass1].length > 15) ? true : this.$data[pass1].length == 0 ? null:false;
    },

    validatePassMatch: function(pass1, pass2){
      return this.$data[pass1] == this.$data[pass2] ? true : false;
    },

    get_lists: function(table,variable) {
      let promise = axios.get('/io2data.php/'+table);
      var items=promise.then((data) => {
				if (/DOCTYPE html/.test(data.data)){
					window.location.reload(true);
				}else{
         this.$root.$data[variable]=data.data;
        };
      }).catch(error => {
        this.$root.$data[variable]=[];
      })
    },

    mgmtTableOk: function (response,obj,table){
      if (response.data.status == "ok"){
        //obj.$root.$refs['io2tbl_'+table].refreshTblKeepPage(table);
        obj.$root.$emit('bv::refresh::table', 'io2tbl_'+table);
      }else{
        //TODO better error handeling
        alert('sql error while adding '+table);
      };
    },

    mgmtTableError: function (errore,obj,table){
      //TODO better error handeling
      alert('error while adding '+table+' ');
    },

    /*
     *TODO check if PUT/POST json is valid (escape quotes etc)
     */
    //TKeys
    tblMgmtTKeyRecord: function (ev,table) {
      if (this.validateName('ftKeyName') && this.validateB64('ftKey')){
        var obj=this;
        if ((this.ftKeyId!=-1 && (this.$root.ftKeyName != this.editRow.name || this.$root.ftKey!=this.editRow.tkey || this.$root.ftKeyAlg!=this.editRow.alg || this.$root.ftKeyMGMT!=this.editRow.mgmt || this.$root.ftTKeysGroups!=this.editRow.tkey_groups))) toggleUpdates(0,this,true);
        let data={tKeyId: this.ftKeyId, tKeyName: this.ftKeyName, tKey: this.ftKey, tKeyAlg: this.ftKeyAlg, tKeyMGMT: this.ftKeyMGMT, tTKeysGroups: JSON.stringify(this.ftTKeysGroups)};
        if (this.ftKeyId==-1){
          //Add
          axios.post('/io2data.php/'+table,data).then((data) => {if (/DOCTYPE html/.test(data.data)){window.location.reload(true);} else obj.mgmtTableOk(data,obj,table);}).catch(function (error){obj.mgmtTableError(error,obj,table)})
        }else{
          //Modify
          axios.put('/io2data.php/'+table,data).then((data) => {if (/DOCTYPE html/.test(data.data)){window.location.reload(true);} else obj.mgmtTableOk(data,obj,table);}).catch(function (error){obj.mgmtTableError(error,obj,table)})
        };
      } else if (ev != null) {
        ev.preventDefault();
        if (!this.validateName('ftKeyName')) this.$refs.formKeyName.$el.focus()
          else this.$refs.formKey.$el.focus();
      };
    },

    //TKey Groups
    tblMgmtTKeyGRecord: function (ev,table) {
      if (this.validateName('ftKeyGName')){
        var obj=this;
        let data={tKeyGId: this.ftKeyGId, tKeyGName: this.ftKeyGName};
        if (this.ftKeyGId==-1){
          //Add
          axios.post('/io2data.php/'+table,data).then((data) => {if (/DOCTYPE html/.test(data.data)){window.location.reload(true);} else obj.mgmtTableOk(data,obj,table);}).catch(function (error){obj.mgmtTableError(error,obj,table)})
        }else{
          //Modify
          axios.put('/io2data.php/'+table,data).then((data) => {if (/DOCTYPE html/.test(data.data)){window.location.reload(true);} else obj.mgmtTableOk(data,obj,table);}).catch(function (error){obj.mgmtTableError(error,obj,table)})
        };
      } else if (ev != null) {
        ev.preventDefault();
        if (!this.validateName('ftKeyGName')) this.$refs.formKeyGName.$el.focus()
          else this.$refs.formKey.$el.focus();
      };
    },

    //Sources/Whitelists
    tblMgmtSrcRecord: function (ev,table) {
      if (this.validateName('ftSrcName') && this.validateURL('ftSrcURL') && (this.validateREGEX('ftSrcREGEX')==null || this.validateREGEX('ftSrcREGEX')) && (((this.validateIXFRURL('ftSrcURLIXFR') || this.validateIXFRURL('ftSrcURLIXFR')==null) && this.ftSrcType == 'sources') || this.ftSrcType != 'sources') && this.validateInt('ftSrcMaxIOC') && this.validateInt('ftSrcHotCacheAXFR') && this.validateInt('ftSrcHotCacheIXFR')) {
        var obj=this;
        if (this.ftSrcId!=-1 && (this.ftSrcName != this.editRow.name || this.ftSrcURL!=this.editRow.url || this.ftSrcREGEX!=this.editRow.regex || this.ftSrcMaxIOC!=this.editRow.max_ioc || this.ftSrcHotCacheAXFR!=this.editRow.hotcache_time || this.ftSrcHotCacheIXFR!=this.editRow.hotcacheixfr_time || (this.ftSrcURLIXFR!=this.editRow.url_ixfr  && this.ftSrcType == 'sources') || this.ftSrcIoCType!=this.editRow.ioc_type || this.ftSrcKeepInCache!=this.editRow.keep_in_cache)) toggleUpdates(0,this,true);
        let data={tSrcId: this.ftSrcId, tSrcName: this.ftSrcName, tSrcURL: this.ftSrcURL, tSrcREGEX: this.ftSrcREGEX, tSrcURLIXFR: this.ftSrcURLIXFR, tSrcMaxIOC: this.ftSrcMaxIOC, tSrcHotCacheAXFR: this.ftSrcHotCacheAXFR, tSrcHotCacheIXFR: this.ftSrcHotCacheIXFR, tSrcIoCType: this.ftSrcIoCType, tSrcKeepInCache: this.ftSrcKeepInCache};
        if (this.ftSrcId==-1){
          //Add
          axios.post('/io2data.php/'+table,data).then((data) => {if (/DOCTYPE html/.test(data.data)){window.location.reload(true);} else obj.mgmtTableOk(data,obj,table);}).catch(function (error){obj.mgmtTableError(error,obj,table)})
        }else{
          //Modify
          axios.put('/io2data.php/'+table,data).then((data) => {if (/DOCTYPE html/.test(data.data)){window.location.reload(true);} else obj.mgmtTableOk(data,obj,table);}).catch(function (error){obj.mgmtTableError(error,obj,table)})
        };
      } else if (ev != null) {
        ev.preventDefault();
        if (!this.validateName('ftSrcName')) this.$refs.formSrcName.$el.focus();
      	  else if (!this.validateURL('ftSrcURL') && this.validateREGEX('ftSrcURL')!=null) this.$refs.formSrcURL.$el.focus() ;
      	  else if (!this.validateREGEX('ftSrcREGEX') && this.validateREGEX('ftSrcREGEX')!=null) this.$refs.formREGEX.$el.focus();
          else this.$refs.formSrcURLIXFR.$el.focus();
      };
    },

    //Users
    manageUsers: function (ev) {
      if (this.validateUName('ftUNameProf') && this.validatePass('ftUPwd')&& this.validatePassMatch('ftUPwd','ftUpwdConf') ){
        let obj=this;
        let data={rowid: this.ftUId, name: this.ftUNameProf, pwd: this.ftUPwd, perm: this.ftUPerm};
        if (this.ftUId==0){
          //Add
          axios.post('/io2data.php/users',data).then((data) => {if (/DOCTYPE html/.test(data.data)){window.location.reload(true);} else obj.mgmtTableOk(data,obj,'users');}).catch(function (error){obj.mgmtTableError(error,obj,'users')})
        }else{
          //Modify
          axios.put('/io2data.php/users',data).then((data) => {if (/DOCTYPE html/.test(data.data)){window.location.reload(true);} else obj.mgmtTableOk(data,obj,'users');}).catch(function (error){obj.mgmtTableError(error,obj,'users')})
        };
      } else if (ev != null) {
        ev.preventDefault();
      };
    },

    //Server
    tblMgmtSrvRecord: function (ev,table) {

      if (this.validateName('ftSrvName') && (this.validateIP('ftSrvPubIP') || this.validateIP('ftSrvPubIP') == null) && (this.validateIP('ftSrvIP') || this.validateIP('ftSrvIP') == null) && this.validateHostname('ftSrvNS') && this.validateEmail('ftSrvEmail') && (this.validateIPList('ftSrvMGMTIP') || this.validateIP('ftSrvMGMTIP') == null)){
        var obj=this;
        if  (this.ftSrvName != this.editRow.name || this.ftSrvIP!=this.editRow.ip || this.ftSrvPubIP!=this.editRow.pub_ip || this.ftSrvNS!=this.editRow.ns || this.ftSrvEmail!=this.editRow.email || this.ftSrvMGMT!=this.editRow.mgmt || this.ftSrvSType!=this.editRow.stype || this.ftSrvURL!=this.editRow.URL || this.ftSrvMGMTIP!=this.editRow.mgmt_ips_str || this.ftSrvTKeys!=this.editRow.tkeys_arr|| this.ftCertFile!=this.editRow.certfile|| this.ftKeyFile!=this.editRow.keyfile|| this.ftCACertFile!=this.editRow.cacertfile|| this.ftCustomConfig!=this.editRow.custom_config) toggleUpdates(0,this,true);
        let data={tSrvId: this.ftSrvId, tSrvName: this.ftSrvName, tSrvIP: this.ftSrvIP, tSrvPubIP: this.ftSrvPubIP, tSrvNS: this.ftSrvNS, tSrvEmail: this.ftSrvEmail,
                  tSrvMGMT: this.ftSrvMGMT, tSrvMGMTIP: JSON.stringify(this.ftSrvMGMTIP.split(/,|\s/g).filter(String)), tSrvTKeys: JSON.stringify(this.ftSrvTKeys),
                  tSrvDisabled: this.ftSrvDisabled, tSrvSType: this.ftSrvSType, tSrvURL: this.ftSrvURL, tCertFile: this.ftCertFile, tKeyFile: this.ftKeyFile, tCACertFile: this.ftCACertFile, tCustomConfig: this.ftCustomConfig};
        if (this.ftSrvId==-1){
          //Add
          axios.post('/io2data.php/'+table,data).then((data) => {if (/DOCTYPE html/.test(data.data)){window.location.reload(true);} else obj.mgmtTableOk(data,obj,table);}).catch(function (error){obj.mgmtTableError(error,obj,table)})
        }else{
          //Modify
          axios.put('/io2data.php/'+table,data).then((data) => {if (/DOCTYPE html/.test(data.data)){window.location.reload(true);} else obj.mgmtTableOk(data,obj,table);}).catch(function (error){obj.mgmtTableError(error,obj,table)})
        };
      } else if (ev != null) {
        ev.preventDefault();
        if (!this.validateName('ftSrvName')) this.$refs.formSrvName.$el.focus();
      	  else if (!(this.validateIP('ftSrvPubIP') || this.validateIP('ftSrvPubIP') == null)) this.$refs.formSrvPubIP.$el.focus();
      	  else if (!(this.validateIP('ftSrvIP') || this.validateIP('ftSrvIP') == null)) this.$refs.formSrvIP.$el.focus();
      	  else if (!this.validateHostname('ftSrvNS')) this.$refs.formSrvNS.$el.focus();
      	  else if (!this.validateEmail('ftSrvEmail')) this.$refs.formSrvEmail.$el.focus();
      	  else if (!this.validateLocFile('ftCertFile')) this.$refs.formCertFile.$el.focus();
      	  else if (!this.validateLocFile('ftKeyFile')) this.$refs.formKeyFile.$el.focus();
      	  else if (!this.validateLocFile('ftCACertFile')) this.$refs.formCACertFile.$el.focus();
         else this.$refs.formSrcNotify.$el.focus();
      };

    },


    //RPZ
    tblMgmtRPZRecord: function (ev,table) {
      if (this.validateHostnameNum('ftRPZName') && (this.validateIPList('ftRPZNotify') || this.validateIPList('ftRPZNotify') == null) && ((this.validateCustomAction(this.ftRPZActionCustom) && this.ftRPZAction === 'local')||this.ftRPZAction != 'local') && this.validateInt('ftRPZSOA_Refresh') && this.validateInt('ftRPZSOA_UpdRetry') && this.validateInt('ftRPZSOA_Exp') && this.validateInt('ftRPZSOA_NXTTL') && this.validateInt('ftRPZAXFR') && this.validateInt('ftRPZIXFR')){
        var obj=this;
        if  (this.ftRPZName != this.editRow.name || this.ftRPZSOA_Refresh!=this.editRow.soa_refresh || this.ftRPZSOA_UpdRetry!=this.editRow.soa_update_retry || this.ftRPZSOA_Exp!=this.editRow.soa_expiration || this.ftRPZSOA_NXTTL!=this.editRow.soa_nx_ttl || this.ftRPZAXFR!=this.editRow.axfr_update || this.ftRPZIXFR!=this.editRow.ixfr_update || this.ftRPZCache!=this.editRow.cache || this.ftRPZWildcard!=this.editRow.wildcard || this.ftRPZAction!=this.editRow.action || this.ftRPZIOCType!=this.editRow.ioc_type || this.editRow.notify_str!=this.ftRPZNotify || this.editRow.servers_arr!=this.ftRPZSrvs || this.editRow.tkeys_arr!=this.ftRPZTKeys || this.editRow.sources_arr!=this.ftRPZSrc || this.editRow.whitelists_arr!=this.ftRPZWL ||this.ftRPZActionCustom!=this.editRow.actioncustom || this.ftRPZDisabled!=this.editRow.disabled) toggleUpdates(0,this,true);
        let data={tRPZId: this.ftRPZId, tRPZName: this.ftRPZName, tRPZSOA_Refresh: this.ftRPZSOA_Refresh, tRPZSOA_UpdRetry: this.ftRPZSOA_UpdRetry,
                  tRPZSOA_Exp: this.ftRPZSOA_Exp, tRPZSOA_NXTTL: this.ftRPZSOA_NXTTL, tRPZCache: this.ftRPZCache,tRPZWildcard: this.ftRPZWildcard,
                  tRPZNotify: JSON.stringify(this.ftRPZNotify.split(/,|\s/g).filter(String)), tRPZSrvs: JSON.stringify(this.ftRPZSrvs),
                  tRPZIOCType: this.ftRPZIOCType, tRPZAXFR: this.ftRPZAXFR, tRPZIXFR: this.ftRPZIXFR, tRPZDisabled: this.ftRPZDisabled,
                  tRPZTKeys: JSON.stringify(this.ftRPZTKeys), tRPZWL: JSON.stringify(this.ftRPZWL), tRPZSrc: JSON.stringify(this.ftRPZSrc),
                  tRPZAction: this.ftRPZAction, tRPZActionCustom: JSON.stringify(this.ftRPZActionCustom)}; //this.ftRPZActionCustom.split(/,|\s/g).filter(String)
        if (this.ftRPZId==-1){
          //Add RPZ
          axios.post('/io2data.php/'+table,data).then((data) => {if (/DOCTYPE html/.test(data.data)){window.location.reload(true);} else obj.mgmtTableOk(data,obj,table);}).catch(function (error){obj.mgmtTableError(error,obj,table)})
        }else{
          //Modify RPZ
          axios.put('/io2data.php/'+table,data).then((data) => {if (/DOCTYPE html/.test(data.data)){window.location.reload(true);} else obj.mgmtTableOk(data,obj,table);}).catch(function (error){obj.mgmtTableError(error,obj,table)})
        };
      } else if (ev != null) {
        ev.preventDefault();
        if (!this.validateHostnameNum('ftRPZName')) this.$refs.formRPZName.$el.focus();
      	  else if (!((this.validateIPList('ftRPZNotify') || this.validateIPList('ftRPZNotify') == null))) this.$refs.formRPZNotify.$el.focus() ;
      	  else if (!this.validateCustomAction(this.ftRPZActionCustom) && this.ftRPZAction === 'local') this.$refs.formRPZActionCustom.$el.focus() ;
      	  else if (!this.validateInt('ftRPZSOA_Refresh')) this.$refs.formRPZSOA_Refresh.$el.focus() ;
      	  else if (!this.validateInt('ftRPZSOA_UpdRetry')) this.$refs.formRPZSOA_UpdRetry.$el.focus() ;
      	  else if (!this.validateInt('ftRPZSOA_Exp')) this.$refs.formRPZSOA_Exp.$el.focus() ;
      	  else if (!this.validateInt('ftRPZSOA_NXTTL')) this.$refs.formRPZSOA_NXTTL.$el.focus() ;
      	  else if (!this.validateInt('ftRPZAXFR')) this.$refs.formRPZAXFR.$el.focus() ;
         else this.$refs.formRPZIXFR.$el.focus();
      };
    },

    tblDeleteRecord: function (table,rowid) {
      var el=this;
      if (table != 'users') toggleUpdates(0,this,true);
      axios.delete('/io2data.php/'+table+'?rowid='+JSON.stringify(rowid)).then(function (response) {
        if (/DOCTYPE html/.test(response.data)) {window.location.reload(true);}
        else if (response.data.status == "ok"){
          //el.$root.$refs['io2tbl_'+table].refreshTblKeepPage(table);
          el.$root.$emit('bv::refresh::table', 'io2tbl_'+table);
        }else{
          //TODO better error handeling
          alert('sql error while deleting '+table+' ' + rowid);
        };
      }).catch(function (error){
        //TODO better error handeling
        alert('error while deleting '+table+' ' + rowid);
      })
    },

    pushUpdatestoSRV: function (SrvId) {
      var obj=this;
      toggleUpdates(0,obj,false);
      axios.post(`/io2data.php/publish_upd?SrvId=${SrvId}`).then(function (response) {
        if (response.data.status == "ok"){
          obj.showInfo('Configuration will be updated in a few seconds',3);
          toggleUpdates(0,obj,false); obj.$root.$emit('bv::refresh::table', 'servers')
          //setTimeout(function(){}, 3 * 1000);
        }else{
          //TODO better error handeling
          if (/DOCTYPE html/.test(reponse.data)){window.location.reload(true);} else alert('Publishing error');
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


    ImportConfig: function (ev) {
      var file = new FileReader();
      var vm = this;
      //onprogress, onabort, onerror, onloadstart
      file.onload = function(e) {ImportIOC2RPZ(vm,e.target.result);};
      file.readAsText(vm.ftImpFiles[0]);
    },

    ImportConfigLine: function (ev) {
      //ftImpAction=0;
      ImportIOC2RPZ(this,this.ftImportRec);
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
          //let key=[];
          //key['md5'] = new Uint8Array(16);
          //key['sha256'] = new Uint8Array(32);
          //key['sha512'] = new Uint8Array(64);
          //window.crypto.getRandomValues(key[this.$root.ftKeyAlg]);
          //this.$root.ftKey=btoa(key[this.$root.ftKeyAlg]);

          let key = new Uint8Array(this.$root.ftKeyAlg == 'md5'?16:this.$root.ftKeyAlg == 'sha256'?32:64);
          do {
            window.crypto.getRandomValues(key);
            this.$root.ftKey=btoa(String.fromCharCode.apply(null, key));
          } while (!this.validateB64('ftKey')); //TODO doesn't work ....
          break;
      }
    },

    changeTab: function(tab){
      //update table
      //history.pushState(null, null, this.$refs.tabs_menu.$children[tab].href);
      history.pushState(null, null, '#tabs_menu/'+tab);
      if (this.$refs.tabs_menu.$children[tab].$attrs.table) this.$root.$emit('bv::refresh::table', this.$refs.tabs_menu.$children[tab].$attrs.table);
    },

    signOut: function(){
      axios.post('/io2auth.php/logout').then(function (response){window.location.reload(true)});
    },


    //Export RPZ zones
    exportShowModal: function(format){
      this.$root.ftExFormat=format;
      this.$root.get_lists('rpz_lists','ftExRPZAll');
      this.$root.ftExRPZ=[];
      this.$root.rpzExportSAll = false;
      this.$emit('bv::show::modal', 'mExpRPZ')
    },

    rpzExportToggleAll: function(checked){
      this.ftExRPZ = checked ? this.ftExRPZAll.map(function(el){return el.value}) : []
    },

    //Generate export configuration
    exportDNSConfig: async function(){
      let p = axios.get('/io2data.php/rpzs?rowid='+JSON.stringify(this.$root.ftExRPZ));
      var [rpzs] = await Promise.all([p]);
      var keys=[];
      var options="";
      var zone_opt=[];zone_opt['fqdn']="";zone_opt['mixed']="";zone_opt['ip']="";
      var keys_txt="";
      var zones="";
      let tkey_str="";

      switch(this.$root.ftExFormat){
        case 'bind':
          rpzs.data.forEach(function(el){
            let servers="";
            el['servers'].forEach(function(srv){
              if (el['tkeys'].length==0){tkey_str="";}else{tkey_str=` key "${el['tkeys'][0]['name']}"`;};
              servers+=`${srv['pub_ip']} ${tkey_str};`
            });
            zones+=`
zone "${el['name']}" {
  type slave;
  file "/var/cache/bind/${el['name']}";
  masters {${servers}};
};

          `;
          zone_opt[el['ioc_type']]+=`
    zone "${el['name']}" policy `+(el['action'] == 'local'?'given':el['action'])+";";
          if (el['tkeys'].length>0) {
            keys[el['tkeys'][0]['name']]=[];
            keys[el['tkeys'][0]['name']]['name']=el['tkeys'][0]['name'];
            keys[el['tkeys'][0]['name']]['alg']=el['tkeys'][0]['alg'];
            keys[el['tkeys'][0]['name']]['tkey']=el['tkeys'][0]['tkey'];
          };
          });
          options=`
options {
  #This is just options for RPZs. Add other options as required
  recursion yes;
  response-policy {
    ####FQDN only zones ${zone_opt['fqdn']}
    ####Mixed zones ${zone_opt['mixed']}
    ####IP only zones ${zone_opt['ip']}
  } qname-wait-recurse no break-dnssec yes;
};
          `;
          for(var i in keys) {
            keys_txt+=`
key "${keys[i]['name']}"{
  algorithm hmac-${keys[i]['alg']}; secret "${keys[i]['tkey']}";
};

            `;
          };
          //  hmac-md5, hmac-sha1, hmac-sha224, hmac-sha256, hmac-sha384 and hmac-sha512 a single key per master
          break;
        case 'PowerDNS':
//rpzMaster("192.168.56.43", "dns-bh.ioc2rpz", {defpol=Policy.Custom, defcontent="dns-bh.example.com", tsigname="tkey_1", tsigalgo="hmac-md5", tsigsecret="DkC1HNKF+XznNXBEfPUp8A=="})
          let RPZ_PowerDNS_Options={ 'nxdomain': 'defpol=Policy.NXDOMAIN', 'nodata': 'defpol=Policy.NODATA', 'passthru': 'defpol=Policy.NoAction', 'drop': 'defpol=Policy.Drop', 'tcp-only': 'defpol=Policy.Truncate', 'local': ''};
          let pdns_opt="";
          let cmm="";
          rpzs.data.forEach(function(el){
            //${el['action']} el['action'] == 'local' -- do not add defpol
            if (el['tkeys'].length==0){
              tkey_str="";
            }else{
              tkey_str=`tsigname="${el['tkeys'][0]['name']}", tsigalgo="hmac-${el['tkeys'][0]['alg']}", tsigsecret="${el['tkeys'][0]['tkey']}"`;
            };
            if (RPZ_PowerDNS_Options[el['action']]!="" && tkey_str!="") cmm=",";
            if (RPZ_PowerDNS_Options[el['action']]!="" || tkey_str!="") pdns_opt=`, {${RPZ_PowerDNS_Options[el['action']]}${cmm} ${tkey_str}}`; else pdns_opt="";
            zones+=`
rpzMaster("${el['servers'][0]['pub_ip']}", "${el['name']}"${pdns_opt})
`;
          });

          break;
        case 'Infoblox':
          let zone_pri=[]; zone_pri['fqdn']=[]; zone_pri['mixed']=[]; zone_pri['ip']=[];
          let zp=0;
          options="header-responsepolicyzone,fqdn*,zone_format*,rpz_policy,substitute_name,view,zone_type,external_primaries,grid_secondaries,priority";
          let RPZ_IB_Options={'nxdomain': 'Nxdomain', 'nodata': 'Nodata', 'passthru': 'Passthru', 'drop': 'Nxdomain', 'tcp-only': 'Passthru', 'local': 'Given'}; //SUBSTITUTE, DISABLED
          let TKEY_Alg={'md5': 'HMAC-MD5','sha256': 'HMAC-SHA256','sha512': 'HMAC-SHA512'}; //sha512 is not supported by IB
          //el['ioc_type']
          let IBMember=this.$root.rpzExportIBMember;
          let IBNView=this.$root.rpzExportIBView;

          rpzs.data.forEach(function(el){
            let tkey=-1;
            el['tkeys'].some(function(el){
              tkey++;
              return ((el['alg']!='sha512') && (el['tkey'].indexOf('/')==-1));
            });
            if (el['tkeys'].length==0){
              tkey_str=`${el['servers'][0]['name']}/${el['servers'][0]['pub_ip']}/FALSE/FALSE/FALSE`;
            }else{
              tkey_str=`${el['servers'][0]['name']}/${el['servers'][0]['pub_ip']}/FALSE/FALSE/TRUE/${el['tkeys'][tkey]['name']}/${el['tkeys'][tkey]['tkey']}/${TKEY_Alg[el['tkeys'][tkey]['alg']]}`;
            };
            zone_pri[el['ioc_type']].push(`
  responsepolicyzone,${el['name']},FORWARD,${RPZ_IB_Options[el['action']]},,${IBNView},responsepolicy,${tkey_str},${IBMember}/False/False/False,`);
          });
          zone_pri['fqdn'].forEach(function(el){zones+=el+zp;zp++;});
          zone_pri['mixed'].forEach(function(el){zones+=el+zp;zp++;});
          zone_pri['ip'].forEach(function(el){zones+=el+zp;zp++;});
//responsepolicyzone,ABC.NET,forward,given,,default,responsepolicy,name/ip/stealth/use_2x_tsig/use_tsig/tsig_name/tsig_key/tsig_key_algorithm,1001
          break;
      };
      downloadAsPlainText(this.$root.ftExFormat+"_sample_config.txt",options+keys_txt+zones);

    },


  }
});


function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

//(^\s*((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))\s*$)|(^\s*((?=.{1,255}$)[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?(?:\.[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?)*\.?)\s*$)|(^\s*((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?\s*$)

function checkHostIP(V) {
  return checkIPv4(V) || checkIPv6(V) || checkHostName(V);
}
function checkIP(IP) {
  return checkIPv4(IP) || checkIPv6(IP);
}

function checkIPv4(IP) {
  return /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/.test(IP);
}

function checkIPv6(IP) {
  return /^(([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))$/.test(IP);
}

//function checkHostName(HN) {
//  return /^(?=.{1,255}$)[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?(?:\.[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?)*\.?$/.test(HN);
//}
//
//function checkHostNameOnly(HN) {
//  return /^(?=.{1,255}$)[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?(?:\.[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?)*\.?$/.test(HN);
//}
//
//function checkHostNameNum(HN) {
//  return /^(?=.{1,255}$)[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?(?:\.[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?)*\.?$/.test(HN);
//}

function checkSourceURL(HN) {
  //TODO validation
  return /^(http:\/\/|https:\/\/|ftp:\/\/|file:|shell:)/.test(HN);
}

//"Dirty way"
function downloadAsPlainText(fileName,Data){
  //btoa()
  var dataStr = "data:text/plain;base64," + btoa(Data);
  var downloadAnchorNode = document.createElement('a');
  downloadAnchorNode.setAttribute("href",     dataStr);
  downloadAnchorNode.setAttribute("download", fileName);
  document.body.appendChild(downloadAnchorNode); // required for firefox
  downloadAnchorNode.click();
  downloadAnchorNode.remove();
}

function copyToClipboardID(id) {
  document.getElementById(id).select();
  document.execCommand('copy');
};


async function ImportIOC2RPZ(vm,txt){//e.target.result
        var SrvId;
        let ev = null;
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

        for(let line of txt.split(/\r|\n/)){
          //this.ftImpServName: '',
          //this.ftImpPrefix: '',
          //this.ftImpAction: 0,
          // {rpz,{
          var l=line.trim();
          if (m = l.match(/^{srv,{"([^"]+)","([^"]+)",\[([^\]]*)\],\[([^\]]*)\]}}\.(\t* *| *\t*%.*)$/) ){
            Srv['ns']=m[1];Srv['email']=m[2];Srv['tkeys']=[];
            m[3].split(/,|\s|"/g).filter(String).forEach(function(el){Srv['tkeys'].push(el);});
            Srv['mgmt']=m[4].replace(/"/g,'');//.split(/,|\s|"/g).filter(String);
          };
//{rpz,{"dga.ioc2rpz",21600,3600,2592000,7200,"true","true","nxdomain",["pub_demokey_1","at_demokey_1","priv_key_1"],"fqdn",172800,86400,["dga"],[],["whitelist_1"]}}.
//rpz record: name, SOA refresh, SOA update retry, SOA expiration, SOA NXDomain TTL, Cache, Wildcards, Action, [tkeys], ioc_type, AXFR_time, IXFR_time, [sources], [notify], [whitelists]
          if (m = l.match(/^{rpz,{"([^"]+)",([0-9]+),([0-9]+),([0-9]+),([0-9]+),"([^"]+)","([^"]+)","?([^"]+|\[[^\]]*\])"?,\[([^\]]*)\],"([^"]+)",([0-9]+),([0-9]+),\[([^\]]*)\],\[([^\]]*)\],\[([^\]]*)\]}}\.(\t* *| *\t*%.*)$/) ){
            Rpz[m[1]]=[];
            Rpz[m[1]]['tkeys']=[];
            if (m[9]) m[9].split(/,|\s|"/g).filter(String).forEach(function(el){Rpz[m[1]]['tkeys'].push(el);});

            Rpz[m[1]]['sources']=[];
            m[13].split(/,|\s|"/g).filter(String).forEach(function(el){Rpz[m[1]]['sources'].push(el);});

            Rpz[m[1]]['notify']=m[14].replace(/"/g,'');

            Rpz[m[1]]['whitelists']=[];
            if (m[15]) m[15].split(/,|\s|"/g).filter(String).forEach(function(el){Rpz[m[1]]['whitelists'].push(el);});

            Rpz[m[1]]['name']=m[1];
            Rpz[m[1]]['soa_refresh']=m[2];
            Rpz[m[1]]['soa_update']=m[3];
            Rpz[m[1]]['soa_exp']=m[4];
            Rpz[m[1]]['soa_nxttl']=m[5];
            Rpz[m[1]]['cache']=m[6]=="true"?1:0;
            Rpz[m[1]]['wildcards']=m[7]=="true"?1:0;

            Rpz[m[1]]['action']=m[8]; //

            Rpz[m[1]]['ioc_type']=m[10];
            Rpz[m[1]]['AXFR_time']=m[11];
            Rpz[m[1]]['IXFR_time']=m[12];
          };
          if (m = l.match(/^{key,{"([^"]+)","([^"]+)","([^"]+)"}}\.(\t* *| *\t*%.*)$/)){
            if (vm.ftImpAction==1 || (vm.ftImpAction==2 && (!TKeysAll[m[1]] || (!TKeysAll[vm.ftImpPrefix+m[1]] && vm.ftImpPrefix)))|| (vm.ftImpAction==0 && (!TKeysAll[vm.ftImpPrefix+m[1]]))) {
              vm.ftKeyId=(TKeysAll[vm.ftImpPrefix+m[1]] && vm.ftImpAction==1)?TKeysAll[vm.ftImpPrefix+m[1]]:-1;
              vm.ftKeyName=vm.ftImpAction!=2?vm.ftImpPrefix+m[1]:(TKeysAll[m[1]] && vm.ftImpAction==2)?vm.ftImpPrefix+m[1]:m[1];

              vm.ftKeyAlg=m[2]; vm.ftKey=m[3]; vm.ftKeyMGMT=Srv['tkeys'].includes(m[1])?1:0; //TODO check SRV first
              TKeys[vm.ftKeyName]=vm.ftKeyName;
              TKeys[m[1]]=vm.ftKeyName;
              await vm.tblMgmtTKeyRecord(ev,'tkeys');
              //await sleep(10); //SQLite too slow
            }else{
              TKeys[m[1]]=(TKeysAll[vm.ftImpPrefix+m[1]] && vm.ftImpAction!=2)?vm.ftImpPrefix+m[1]:(TKeysAll[m[1]] && vm.ftImpAction==2)?vm.ftImpPrefix+m[1]:m[1];
            };
          };
          if (m = l.match(/^{whitelist,{"([^"]+)","([^"]+)",(none|"(.*)")}}\.(\t* *| *\t*%.*)$/)){
            if (vm.ftImpAction==1 || (vm.ftImpAction==2 && (!WLAll[m[1]] || (!WLAll[vm.ftImpPrefix+m[1]] && vm.ftImpPrefix)))|| (vm.ftImpAction==0 && (!WLAll[vm.ftImpPrefix+m[1]]))) {
              vm.ftSrcId=(WLAll[vm.ftImpPrefix+m[1]] && vm.ftImpAction==1)?WLAll[vm.ftImpPrefix+m[1]]:-1;
              vm.ftSrcName=vm.ftImpAction!=2?vm.ftImpPrefix+m[1]:(WLAll[m[1]] && vm.ftImpAction==2)?vm.ftImpPrefix+m[1]:m[1];
              vm.ftSrcURL=m[2]; vm.ftSrcREGEX=m[4]!==undefined?m[4]:m[3]; vm.ftSrcURLIXFR="";
              vm.ftSrcMaxIOC='0';
              vm.ftSrcHotCacheAXFR='900';
              vm.ftSrcHotCacheIXFR='0';
              WL[vm.ftSrcName]=vm.ftSrcName;
              WL[m[1]]=vm.ftSrcName;
              vm.ftSrcType='whitelists';
              await vm.tblMgmtSrcRecord(ev,'whitelists');
            }else{
              WL[m[1]]=(WLAll[vm.ftImpPrefix+m[1]] && vm.ftImpAction!=2)?vm.ftImpPrefix+m[1]:(WLAll[m[1]] && vm.ftImpAction==2)?vm.ftImpPrefix+m[1]:m[1];
            };
          };
          if (m = l.match(/^{source,{"([^"]+)","([^"]+)","([^"]*)",(none|"(.*)")}}\.(\t* *| *\t*%.*)$/)){
            if (vm.ftImpAction==1 || (vm.ftImpAction==2 && (!SrcAll[m[1]] || (!SrcAll[vm.ftImpPrefix+m[1]] && vm.ftImpPrefix)))|| (vm.ftImpAction==0 && (!SrcAll[vm.ftImpPrefix+m[1]]))) {
              vm.ftSrcId=(SrcAll[vm.ftImpPrefix+m[1]] && vm.ftImpAction==1)?SrcAll[vm.ftImpPrefix+m[1]]:-1;
              vm.ftSrcName=vm.ftImpAction!=2?vm.ftImpPrefix+m[1]:(SrcAll[m[1]] && vm.ftImpAction==2)?vm.ftImpPrefix+m[1]:m[1];
              vm.ftSrcURL=m[2]; vm.ftSrcURLIXFR=m[3]; vm.ftSrcREGEX=m[5]!==undefined?m[5]:m[4];
              vm.ftSrcMaxIOC='0';
              vm.ftSrcHotCacheAXFR='900';
              vm.ftSrcHotCacheIXFR='0';
              Src[vm.ftSrcName]=vm.ftSrcName;
              Src[m[1]]=vm.ftSrcName;
              vm.ftSrcType='sources';
              await vm.tblMgmtSrcRecord(ev,'sources');
            }else{
              Src[m[1]]=(SrcAll[vm.ftImpPrefix+m[1]] && vm.ftImpAction!=2)?vm.ftImpPrefix+m[1]:(SrcAll[m[1]] && vm.ftImpAction==2)?vm.ftImpPrefix+m[1]:m[1];
            };
          };
//new format
          if (m = l.match(/^{whitelist,{"([^"]+)","([^"]+)",(none|"(.*)"),"([^"]*)",([0-9]+),([0-9]+),([0-9]+)}}\.(\t* *| *\t*%.*)$/)){
            if (vm.ftImpAction==1 || (vm.ftImpAction==2 && (!WLAll[m[1]] || (!WLAll[vm.ftImpPrefix+m[1]] && vm.ftImpPrefix)))|| (vm.ftImpAction==0 && (!WLAll[vm.ftImpPrefix+m[1]]))) {
              vm.ftSrcId=(WLAll[vm.ftImpPrefix+m[1]] && vm.ftImpAction==1)?WLAll[vm.ftImpPrefix+m[1]]:-1;
              vm.ftSrcName=vm.ftImpAction!=2?vm.ftImpPrefix+m[1]:(WLAll[m[1]] && vm.ftImpAction==2)?vm.ftImpPrefix+m[1]:m[1];
              vm.ftSrcURL=m[2]; vm.ftSrcREGEX=m[4]!==undefined?m[4]:m[3]; vm.ftSrcURLIXFR="";
              vm.ftSrcMaxIOC=m[6];
              vm.ftSrcHotCacheAXFR=m[7];
              vm.ftSrcHotCacheIXFR=m[8];
              WL[vm.ftSrcName]=vm.ftSrcName;
              WL[m[1]]=vm.ftSrcName;
              vm.ftSrcType='whitelists';
              await vm.tblMgmtSrcRecord(ev,'whitelists');
            }else{
              WL[m[1]]=(WLAll[vm.ftImpPrefix+m[1]] && vm.ftImpAction!=2)?vm.ftImpPrefix+m[1]:(WLAll[m[1]] && vm.ftImpAction==2)?vm.ftImpPrefix+m[1]:m[1];
            };
          };
          if (m = l.match(/^{source,{"([^"]+)","([^"]+)","([^"]*)",(none|"(.*)"),"([^"]*)",([0-9]+),([0-9]+),([0-9]+)}}\.(\t* *| *\t*%.*)$/)){
            if (vm.ftImpAction==1 || (vm.ftImpAction==2 && (!SrcAll[m[1]] || (!SrcAll[vm.ftImpPrefix+m[1]] && vm.ftImpPrefix)))|| (vm.ftImpAction==0 && (!SrcAll[vm.ftImpPrefix+m[1]]))) {
              vm.ftSrcId=(SrcAll[vm.ftImpPrefix+m[1]] && vm.ftImpAction==1)?SrcAll[vm.ftImpPrefix+m[1]]:-1;
              vm.ftSrcName=vm.ftImpAction!=2?vm.ftImpPrefix+m[1]:(SrcAll[m[1]] && vm.ftImpAction==2)?vm.ftImpPrefix+m[1]:m[1];
              vm.ftSrcURL=m[2]; vm.ftSrcURLIXFR=m[3]; vm.ftSrcREGEX=m[5]!==undefined?m[5]:m[4];
              vm.ftSrcMaxIOC=m[7];
              vm.ftSrcHotCacheAXFR=m[8];
              vm.ftSrcHotCacheIXFR=m[9];
              Src[vm.ftSrcName]=vm.ftSrcName;
              Src[m[1]]=vm.ftSrcName;
              vm.ftSrcType='sources';
              await vm.tblMgmtSrcRecord(ev,'sources');
            }else{
              Src[m[1]]=(SrcAll[vm.ftImpPrefix+m[1]] && vm.ftImpAction!=2)?vm.ftImpPrefix+m[1]:(SrcAll[m[1]] && vm.ftImpAction==2)?vm.ftImpPrefix+m[1]:m[1];
            };
          };

        };

        await sleep(1000); //SQLite is too slow
        p1 = axios.get('/io2data.php/tkeys');
        p2 = axios.get('/io2data.php/sources');
        p3 = axios.get('/io2data.php/whitelists');
        [tkeys, sources, whitelists] = await Promise.all([p1, p2, p3]);
        var TKeysAll=[], WLAll=[], SrcAll=[];
        if (tkeys.data) tkeys.data.forEach(function(el){TKeysAll[el['name']]=el['rowid']});
        if (sources.data) sources.data.forEach(function(el){SrcAll[el['name']]=el['rowid']});
        if (whitelists.data) whitelists.data.forEach(function(el){WLAll[el['name']]=el['rowid']});

        if(Srv.length > 0){
          vm.ftSrvId=-1;
          vm.ftSrvName=vm.ftImpServName;
          //vm.ftSrvIP vm.ftSrvMGMT vm.ftSrvDisabled
          vm.ftSrvNS=Srv['ns'];
          vm.ftSrvEmail=Srv['email'].replace('.', '@');;
          vm.ftSrvMGMTIP=Srv['mgmt'];
          if (Srv['tkeys']) Srv['tkeys'].forEach(function(el){
            if (TKeys[el] && TKeysAll[TKeys[el]]) vm.ftSrvTKeys.push(TKeysAll[TKeys[el]]);
          });
          vm.ftSrvSType=0;
          vm.ftSrvURL=vm.ftImpFiles[0].name
          vm.ftCertFile=Srv['certfile'];
          vm.ftKeyFile=Srv['keyfile'];
          vm.ftCACertFile=Srv['cacertfile'];
          vm.ftCustomConfig=Srv['custom_config'];
          //TODO Fix to ask values in the import form
          vm.ftSrvPubIP=vm.ftImpServPubIP;
          vm.ftSrvIP=vm.ftImpServMGMTIP;
          await vm.tblMgmtSrvRecord(ev,'servers');
          do {
            await sleep(1000); //SQLite is too slow
            p1 = axios.get('/io2data.php/servers');
            [servers] = await Promise.all([p1]);
            if (servers.data) servers.data.forEach(function(el){if (vm.ftSrvName==el['name']) SrvId=el['rowid']});
          } while (SrvId == undefined)
        };

      //          tRPZSrvs: JSON.stringify(this.ftRPZSrvs),
      //          tRPZDisabled: this.ftRPZDisabled,

        if(Rpz.length > 0){
          vm.ftRPZId=-1
          vm.ftRPZSrvs=[];
          vm.ftRPZSrvs.push(SrvId);
          for (var RpzName in Rpz) {
            vm.ftRPZName=RpzName; //If exists --- add srv???
            vm.ftRPZSOA_Refresh=Rpz[RpzName]['soa_refresh'];
            vm.ftRPZSOA_UpdRetry=Rpz[RpzName]['soa_update'];
            vm.ftRPZSOA_Exp=Rpz[RpzName]['soa_exp'];
            vm.ftRPZSOA_NXTTL=Rpz[RpzName]['soa_nxttl'];
            vm.ftRPZCache=Rpz[RpzName]['cache'];
            vm.ftRPZWildcard=Rpz[RpzName]['wildcards'];

            if (["nxdomain","nodata","passthru","drop","tcp-only"].includes(Rpz[RpzName]['action'])){
              vm.ftRPZAction=Rpz[RpzName]['action']; vm.ftRPZActionCustom="";
            }else{
              vm.ftRPZAction="local"; vm.ftRPZActionCustom=Rpz[RpzName]['action'];
            };

            vm.ftRPZIOCType=Rpz[RpzName]['ioc_type'];
            vm.ftRPZAXFR=Rpz[RpzName]['AXFR_time'];
            vm.ftRPZIXFR=Rpz[RpzName]['IXFR_time'];

            vm.ftRPZTKeys=[];
            if (Rpz[RpzName]['tkeys']) Rpz[RpzName]['tkeys'].forEach(function(el){
              if (TKeys[el] && TKeysAll[TKeys[el]]) vm.ftRPZTKeys.push(TKeysAll[TKeys[el]]);
            });

            vm.ftRPZSrc=[];
            Rpz[RpzName]['sources'].forEach(function(el){
              if (Src[el] && SrcAll[Src[el]]) vm.ftRPZSrc.push(SrcAll[Src[el]]);
            });

            vm.ftRPZNotify=Rpz[RpzName]['notify'];
            vm.ftRPZWL=[];
            if (Rpz[RpzName]['whitelists']) Rpz[RpzName]['whitelists'].forEach(function(el){
              if (WL[el] && WLAll[WL[el]]) vm.ftRPZWL.push(WLAll[WL[el]]);
            });

            vm.tblMgmtRPZRecord(ev,'rpzs');
          };
        };
};

function update_window_size(obj){
	if (obj.$refs  === undefined) {obj = io2c_app;};
	obj.logs_pp = window.innerHeight>500 && window.innerWidth>1000?Math.floor((window.innerHeight -350)/28):5;
	obj.logs_height = window.innerHeight>400?(window.innerHeight - 240):150; //250
	obj.windowInnerWidth = window.innerWidth;
  splitRpiDNSList(obj);
};

function toggleUpdates (srv, obj, state){
  window.localStorage.publishUpdates=state;
  obj.publishUpdates=state;
};

function splitRpiDNSList(obj){
	obj.RpiDNSListDash = [];
	var i,j, chunk = parseInt((obj.$refs.RpiDNSCards.offsetWidth==0?(window.innerWidth-165):obj.$refs.RpiDNSCards.offsetWidth-50) / 315); chunk = chunk>0?chunk:1;
	for (i=0,j=obj.RpiDNSList.length; i<j; i+=chunk) {
		obj.RpiDNSListDash.push(obj.RpiDNSList.slice(i,i+chunk));
	};
};



function checkHostIPNet(V) {
  return checkIPv4(V) || checkIPv4Net(V) || checkIPv6(V) || checkHostName(V);
};

function checkHostIP(V) {
  return checkIPv4(V) || checkIPv6(V) || checkHostName(V);
};

function checkIP(IP) {
  return checkIPv4(IP) || checkIPv6(IP);
};

function checkIPv4(IP) {
  return /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/.test(IP);
};

function checkIPv4Net(IP) {
  return /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\/([0-9]|[1-2][0-9]|3[0-2])$/.test(IP);
};

function checkIPv6(IP) {
  return /^(([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))(\/[0-9]+)?$/.test(IP);
};



function checkHostName(HN) {
//  return /^(?=.{1,255}$)[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?(?:\.[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?)*\.?$/.test(HN);
	return /(?=^.{4,253}$)(^((?!-)[a-zA-Z0-9-]{0,62}[a-zA-Z0-9]\.)+[a-zA-Z]{2,63}$)/.test(HN);
};

function checkHostNameNum(HN) {
//  return /^(?=.{1,255}$)[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?(?:\.[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?)*\.?$/.test(HN);
	return /(?=^.{4,253}$)(^((?!-)[a-zA-Z0-9-]{0,62}[a-zA-Z0-9]\.)+[a-zA-Z0-9]{2,63}$)/.test(HN);
};

function checkHostNameOnly(HN) {
//  return /^(?=.{1,255}$)[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?(?:\.[0-9A-Za-z](?:(?:[0-9A-Za-z]|\b-){0,61}[0-9A-Za-z])?)*\.?$/.test(HN);
	return /(?=^.{4,253}$)(^((?!-)[a-zA-Z0-9-]{0,62}[a-zA-Z0-9]\.?)+$)/.test(HN);
};

function checkSourceURL(HN) {
  //TODO validation
  return /^(http:\/\/|https:\/\/|ftp:\/\/|file:|shell:)/.test(HN);
};
