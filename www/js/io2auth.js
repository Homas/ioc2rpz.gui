new Vue({
  el: "#app",
  data: {
    ftUNameProf: '',
    ftUPwd: '',
    ftUpwdConf: '',
    mInfoMSGvis: false,
    msgInfoMSG: '',
  },
  methods: {
    validateName: function(vrbl){
      return (this.$data[vrbl].length > 2 && /^[a-zA-Z0-9\.\-\_]+$/.test(this.$data[vrbl])) ? true : this.$data[vrbl].length == 0 ? null:false;
    },
    validatePass: function(pass1, pass2){
      return (this.$data[pass1].length > 0 && /^.+$/.test(this.$data[pass1])) ? true : this.$data[pass1].length == 0 ? null:false;
    },

    createUser: function(e){
      e.preventDefault();
      if (this.$root.ftUNameProf.length == 0) e.preventDefault(); //TODO check all values

      var data={login: this.ftUNameProf, pwd: this.ftUPwd, pwdConf: this.ftUpwdConf};
      var obj=this;
      axios.post('/io2auth.php/createadmin',data).then(function (response) {
        if (response.data.status == "createSuccess"){
          obj.showInfo(response.data.description,3);
          setTimeout(function(){
            window.location.href='/'; //???
          }, 3 * 1000);
        }else{
          obj.showInfo(response.data.description,3);
        };
      }).catch(function (error){
        obj.showInfo('Unknown error!!!',3);
      })
   },
    
    signIn: function(e){ //
//      if (this.$root.ftUNameProf.length == 0) e.preventDefault(); //TODO
      
      var data={login: this.ftUNameProf, pwd: this.ftUPwd};
      var obj=this;
      axios.post('/io2auth.php/signin',data).then(function (response) {
        if (response.data.status == "authSuccess"){
          if (~window.location.href.indexOf('/io2auth.php')) window.location.href='/'; else window.location.reload(false);
        }else{
          obj.showInfo(response.data.description,3);
        };
      }).catch(function (error){
        obj.showInfo('Unknown error!!!',3);
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
    
  }
});
