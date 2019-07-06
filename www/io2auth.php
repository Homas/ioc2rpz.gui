<?php
#(c) Vadim Pavlov 2018
#ioc2rpz GUI auth
  require_once "io2vars.php";
  require_once 'io2fun.php';
  $REQUEST=getRequest();
  $proto=getProto();

//Logout
  if (isset($REQUEST['req']) and $REQUEST['req']=="logout" and $REQUEST['method']=='POST') { 
    session_start();
//    secHeaders();
    session_destroy();
//    header("Location: $proto://".$_SERVER['HTTP_HOST']."/");
    exit;
  };
//END Logout
  
  $db=DB_open();

//Login
  if (isset($REQUEST['req']) and $REQUEST['req']=="signin" and $REQUEST['method']=='POST') {
//    $user=DB_selectArray($db,"select count(rowid) as cnt from users where name='".DB_escape($db,$REQUEST['login'])."' and password=md5(concat(md5('".DB_escape($db,$REQUEST['pwd'])."'),salt))");
    $user=DB_selectArray($db,"select rowid,name,password,perm,salt from users where name='".DB_escape($db,$REQUEST['login'])."'");
    if (count($user)!=1 or empty($user[0]) or empty($user[0]['password']) or empty($user[0]['salt']) or $user[0]['password']!=md5(md5($REQUEST['pwd']).$user[0]['salt'])){
      //$sql="update users set loginattempts=IF(lastfailedlogin<now()-300,1,loginattempts+1),lastfailedlogin=now() where active=1 and emailconfirm=1 and name='".DB_escape($db,$REQUEST['login'])."'";
      echo '{"status":"authFailed","description":"Authentication failed!"}';
    }else{
      session_start();
      $_SESSION['idUser']=$user[0]['rowid'];
      $_SESSION['userName']=$user[0]['name'];
      $_SESSION['perm']=$user[0]['perm'];
      $_SESSION['UserAgent'] = md5($_SERVER['HTTP_USER_AGENT']);
      $_SESSION['loginTime']=date("Y-m-d H:i:s");
      $_SESSION['loginTimeStamp']=time();
      $_SESSION['uactionTime']=date("Y-m-d H:i:s");
      $_SESSION['uactionTimeStamp']=time();
      echo '{"status":"authSuccess","description":"Authentication success"}';
    };
    DB_close($db);
    exit;
  };
//END Login

  $nusers=DB_selectArray($db,"select count(rowid) as cnt from users")[0]['cnt'];
  if ($nusers==0) {$show_reg="";$show_signin="display:none";}else{$show_reg="display:none";$show_signin="";};

//Create admin
  if (isset($REQUEST['req']) and $REQUEST['req']=="createadmin" and $REQUEST['method']=='POST') {
    if ($nusers>0){
      //$sql="update users set loginattempts=IF(lastfailedlogin<now()-300,1,loginattempts+1),lastfailedlogin=now() where active=1 and emailconfirm=1 and name='".DB_escape($db,$REQUEST['login'])."'";
      echo '{"Status":"adminExists","description":"Administrator already exists!"}';
    }elseif ($REQUEST['pwd']!=$REQUEST['pwdConf']){
      echo '{"Status":"pwdNotMatch","description":"Passwords do not match!"}';
    }else{
      //(name text, password text, salt text, perm integer, loginattempts integer, lastlogin integer, lastfailedlogin integer);
      $salt = mt_rand(1000, 9999);
      $sql="insert into users(name, password, salt, perm, loginattempts, lastlogin, lastfailedlogin) values('".DB_escape($db,$REQUEST['login'])."','".md5(md5(DB_escape($db,$REQUEST['pwd'])).$salt)."','$salt',1,0,0,0)";
      if (DB_execute($db,$sql)) $response='{"status":"createSuccess","description":"Administrator created!"}';
        else $response='{"status":"failed","description":"Unexpected error!", "sql":"'.$sql.'"}'; //TODO remove SQL
    };
    echo $response;
    DB_close($db);
    exit;
  };
//END Create admin

//User signed on already
  //if (isset($_REQUEST[session_name()]))
  session_start();
  if (isset($_SESSION['idUser']) AND $_SESSION['UserAgent'] == md5($_SERVER['HTTP_USER_AGENT']) ) {
    $ioc2Admin=$_SESSION['userName'];
    $USERID=$_SESSION['idUser'];
    secHeaders();
    return;
  } 
//END User signed on already



  secHeaders();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>ioc2rpz configuration</title>
  <!-- BootstrapVue -->
	<!-- Docker_Comm_Start -->
  <link type="text/css" rel="stylesheet" href="//unpkg.com/bootstrap/dist/css/bootstrap.min.css"/>
  <link type="text/css" rel="stylesheet" href="//unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.css"/>
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.9/css/all.css" integrity="sha384-5SOiIsAziJl6AWe0HWRKTXlfcSHKmYV4RBF18PPJ173Kzn7jzMyFuTtk8JA7QQG1" crossorigin="anonymous">
	<!-- Docker_Comm_End -->
	<!-- Docker_CSS -->

  <!-- ioc2rpz CSS -->
  <link type="text/css" rel="stylesheet" href="/css/io2.css?<?=$io2ver?>"/>
</head>
<body>
  <div id="app" fluid class="h-100 d-flex flex-column bg_color_gray" v-cloak>
<!--        -->
<!-- Modals -->
<!--        -->

 <!-- User's registration Modal -->

    <div class="mx-auto vcentered" style="width: 400px; <?= $show_reg ?>">
      <b-card border-variant="light" class="text-center pu-10 pd-5" v-cloak>
        <h4 slot="header" class="mb-0">New administrator</h4>
        <div>
          <b-row>
            <b-col :sm="12" class="form_row">
              <b-input v-model.trim="ftUNameProf" :state="validateName('ftUNameProf')" placeholder="Username"  v-b-tooltip.hover title="Username" />
            </b-col>
            <b-col :sm="12" class="form_row">
              <b-input type="password" v-model.trim="ftUPwd" :state="validatePass('ftUPwd')" placeholder="Password"  v-b-tooltip.hover title="Password" />
            </b-col>
            <b-col :sm="12" class="form_row">
              <b-input type="password" v-model.trim="ftUpwdConf" :state="validatePassMatch('ftUPwd','ftUpwdConf')" placeholder="Confirm password"  v-b-tooltip.hover title="Confirm password" />
            </b-col>
            <b-col :sm="12" class="form_row">
              <b-button @click.stop="createUser($event)" variant="outline-secondary">Create</b-button>
            </b-col>
          </b-row>
        </div>
      </b-card>
    </div>



 <!-- Sign in -->
    <div class="mx-auto vcentered" style="width: 400px; <?= $show_signin ?>">
      <b-card border-variant="light" class="text-center pu-10 pd-5" v-cloak>
        <h4 slot="header" class="mb-0">ioc2rpz.gui</h4>
        <div>
          <b-row>
            <b-col :sm="12" class="form_row">
              <b-input v-model.trim="ftUNameProf" placeholder="Username"  v-b-tooltip.hover title="Username" />
            </b-col>
            <b-col :sm="12" class="form_row">
              <b-input type="password" v-model.trim="ftUPwd" placeholder="Password"  v-b-tooltip.hover title="Password" />
            </b-col>
            <b-col :sm="12" class="form_row">
              <b-button @click.stop="signIn($event)" variant="outline-secondary">Sign in</b-button>
            </b-col>
          </b-row>
        </div>
      </b-card>
    </div>

<!-- Message Modal -->
    <b-modal centered :hide-header="true" :hide-footer="true" :visible="mInfoMSGvis" body-class="text-center">
      <span class='text-center' v-html="msgInfoMSG"></span>
    </b-modal>


  </div>

  <!-- Docker_Comm_Start -->
  <script src="https://cdn.jsdelivr.net/npm/vue@2.5.16/dist/vue.js"></script>
  <script src="//unpkg.com/babel-polyfill@latest/dist/polyfill.min.js"></script>
  <script src="//unpkg.com/bootstrap-vue@latest/dist/bootstrap-vue.js"></script>
  <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
	<script src="/js/axios.min.js"></script>
	<!-- Docker_Comm_End -->
	<!-- Docker_JS -->

  <!-- JS -->
  <script src="/js/io2auth.js?<?=$io2ver?>"></script>
</body>
</html>
<?php
  exit(1);
  DB_close($db);
?>