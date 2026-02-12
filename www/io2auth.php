<?php
/**
 * ioc2rpz.gui - Authentication Handler
 * 
 * This file handles user authentication and session management:
 * 
 * Features:
 * - User login with bcrypt password hashing
 * - Legacy MD5 password migration to bcrypt on login
 * - Account lockout after failed login attempts (5 attempts, 15 min lockout)
 * - Session management with user agent validation
 * - CSRF token generation for authenticated sessions
 * - Initial administrator creation
 * - Logout functionality
 * 
 * Security measures:
 * - Session regeneration on login (prevents session fixation)
 * - Rate limiting for login attempts
 * - Secure password requirements (8+ chars with complexity OR 16+ chars)
 * - Security headers via secHeaders()
 * 
 * @package ioc2rpz.gui
 * @author Vadim Pavlov
 * @copyright 2018-2026
 * @license MIT
 */

  require_once "io2vars.php";
  require_once 'io2fun.php';
  require_once 'vite-helpers.php';
  $REQUEST=getRequest();
  $proto=getProto();

//Logout
  if (isset($REQUEST['req']) and $REQUEST['req']=="logout" and $REQUEST['method']=='POST') { 
    session_start();
    session_destroy();
    exit;
  };
//END Logout
  
  $db=DB_open();

//Login
  // Rate limiting constants
  define('MAX_LOGIN_ATTEMPTS', 5);
  define('LOCKOUT_DURATION', 900); // 15 minutes in seconds

  // Session timeout constants
  define('SESSION_IDLE_TIMEOUT', 1800);    // 30 minutes idle timeout
  define('SESSION_ABSOLUTE_TIMEOUT', 28800); // 8 hours absolute timeout

  if (isset($REQUEST['req']) and $REQUEST['req']=="signin" and $REQUEST['method']=='POST') {
    $user=DB_selectArray($db,"select rowid,name,password,perm,salt,loginattempts,lastfailedlogin from users where name='".DB_escape($db,$REQUEST['login'])."'");
    
    $authenticated = false;
    $needsRehash = false;
    
    // Generic failure message — same for all failure scenarios to prevent user enumeration
    $authFailedMsg = '{"status":"authFailed","description":"Authentication failed!"}';

    // Check if account is locked due to too many failed attempts
    if (count($user)==1 && !empty($user[0])) {
      $loginAttempts = intval($user[0]['loginattempts']);
      $lastFailedLogin = intval($user[0]['lastfailedlogin']);
      $currentTime = time();
      
      // Check if account is locked
      if ($loginAttempts >= MAX_LOGIN_ATTEMPTS) {
        $timeSinceLastFailed = $currentTime - $lastFailedLogin;
        if ($timeSinceLastFailed < LOCKOUT_DURATION) {
          // Constant-time delay to prevent timing-based enumeration
          usleep(random_int(200000, 500000));
          echo $authFailedMsg;
          DB_close($db);
          exit;
        } else {
          // Lockout period has expired, reset the counter
          $sql = "update users set loginattempts=0 where rowid=".intval($user[0]['rowid']);
          DB_execute($db, $sql);
          $loginAttempts = 0;
        }
      }
    }
    
    if (count($user)==1 && !empty($user[0]) && !empty($user[0]['password'])) {
      $storedPassword = $user[0]['password'];
      $inputPassword = $REQUEST['pwd'];
      
      // Check if password is bcrypt hashed (starts with $2y$)
      if (substr($storedPassword, 0, 4) === '$2y$') {
        // Verify using bcrypt
        $authenticated = password_verify($inputPassword, $storedPassword);
      } else {
        // Legacy MD5 verification for backward compatibility
        if (!empty($user[0]['salt'])) {
          $authenticated = ($storedPassword === md5(md5($inputPassword).$user[0]['salt']));
          if ($authenticated) {
            $needsRehash = true; // Mark for migration to bcrypt
          }
        }
      }
    }
    
    if (!$authenticated) {
      // Increment failed login attempts (only if user exists)
      if (count($user)==1 && !empty($user[0])) {
        $newAttempts = intval($user[0]['loginattempts']) + 1;
        $sql = "update users set loginattempts=".intval($newAttempts).", lastfailedlogin=".time()." where rowid=".intval($user[0]['rowid']);
        DB_execute($db, $sql);
      } else {
        // User not found — run a dummy bcrypt hash to equalize timing with real password checks
        password_hash('dummy_timing_equalization', PASSWORD_BCRYPT);
      }
      // Constant-time delay to prevent timing-based enumeration
      usleep(random_int(200000, 500000));
      echo $authFailedMsg;
    } else {
      // Reset failed login attempts on successful login
      $sql = "update users set loginattempts=0, lastlogin=".time()." where rowid=".intval($user[0]['rowid']);
      DB_execute($db, $sql);
      
      // Migrate legacy MD5 password to bcrypt on successful login
      if ($needsRehash) {
        $newHash = password_hash($REQUEST['pwd'], PASSWORD_BCRYPT);
        $sql = "update users set password='".DB_escape($db,$newHash)."', salt='' where rowid=".intval($user[0]['rowid']);
        DB_execute($db, $sql);
      }
      
      session_start();
      session_regenerate_id(true); // Prevent session fixation attacks
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
      echo '{"status":"adminExists","description":"Administrator already exists!"}';
    }elseif ($REQUEST['pwd']!=$REQUEST['pwdConf']){
      echo '{"status":"pwdNotMatch","description":"Passwords do not match!"}';
    }elseif (strlen($REQUEST['login']) < 3 || !preg_match('/^[a-zA-Z0-9.\-_]+$/', $REQUEST['login'])){
      echo '{"status":"invalidUsername","description":"Username must be at least 3 characters and contain only letters, numbers, dots, hyphens, and underscores"}';
    }elseif (!validatePassword($REQUEST['pwd'])){
      echo '{"status":"weakPassword","description":"Password must be either: 8+ chars with uppercase, lowercase, number, and special char OR 16+ chars"}';
    }else{
      $hashedPassword = password_hash($REQUEST['pwd'], PASSWORD_BCRYPT);
      $sql="insert into users(name, password, salt, perm, loginattempts, lastlogin, lastfailedlogin) values('".DB_escape($db,$REQUEST['login'])."','".DB_escape($db,$hashedPassword)."','',1,0,0,0)";
      if (DB_execute($db,$sql)) echo '{"status":"createSuccess","description":"Administrator created!"}';
        else echo '{"status":"failed","description":"Unexpected error!"}';
    };
    DB_close($db);
    exit;
  };
//END Create admin

//User signed on already
  //if (isset($_REQUEST[session_name()]))
  session_start();
  if (isset($_SESSION['idUser']) AND $_SESSION['UserAgent'] == md5($_SERVER['HTTP_USER_AGENT']) ) {
    $now = time();

    // Check absolute session timeout (time since login)
    if (isset($_SESSION['loginTimeStamp']) && ($now - $_SESSION['loginTimeStamp']) > SESSION_ABSOLUTE_TIMEOUT) {
      session_unset();
      session_destroy();
      header("Location: /");
      exit;
    }

    // Check idle timeout (time since last activity)
    if (isset($_SESSION['uactionTimeStamp']) && ($now - $_SESSION['uactionTimeStamp']) > SESSION_IDLE_TIMEOUT) {
      session_unset();
      session_destroy();
      header("Location: /");
      exit;
    }

    // Update last activity timestamp
    $_SESSION['uactionTimeStamp'] = $now;
    $_SESSION['uactionTime'] = date("Y-m-d H:i:s", $now);

    $ioc2Admin=$_SESSION['userName'];
    $USERID=$_SESSION['idUser'];
    // Generate CSRF token if not exists
    $csrfToken = getCsrfToken();
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
	<!-- BootstrapVue FA -->
  <!--

	<link type="text/css" rel="stylesheet" href="/css/bootstrap.min.css"/>
	<link type="text/css" rel="stylesheet" href="/css/bootstrap-vue.css"/>
	<link rel="stylesheet" href="/css/all.min.css">

	-->

    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.1/css/all.css">

    <!-- Vite CSS bundles -->
    <?= vite_css_tags('auth') ?>

  <!-- ioc2rpz CSS -->
  <link type="text/css" rel="stylesheet" href="/css/io2.css?<?=$io2ver?>"/>
</head>
<body>
  <div id="app" fluid class="h-100 d-flex flex-column bg_color_gray">
<!--        -->
<!-- Modals -->
<!--        -->

 <!-- User's registration Modal -->

    <div class="mx-auto vcentered" style="width: 400px; <?= $show_reg ?>">
      <b-card border-variant="light" class="text-center pu-10 pd-5">
        <template #header>
          <h4 class="mb-0">New administrator</h4>
        </template>
        <div>
          <b-row>
            <b-col :sm="12" class="form_row">
              <b-form-input v-model.trim="ftUNameProf" :state="validateName('ftUNameProf')" placeholder="Username"  v-b-tooltip.hover title="Username" />
            </b-col>
            <b-col :sm="12" class="form_row">
              <b-form-input type="password" v-model.trim="ftUPwd" :state="validatePass('ftUPwd')" placeholder="Password"  v-b-tooltip.hover title="Password" />
            </b-col>
            <b-col :sm="12" class="form_row">
              <b-form-input type="password" v-model.trim="ftUpwdConf" :state="validatePassMatch('ftUPwd','ftUpwdConf')" placeholder="Confirm password"  v-b-tooltip.hover title="Confirm password" />
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
      <b-card border-variant="light" class="text-center pu-10 pd-5">
        <template #header>
          <h4 class="mb-0">ioc2rpz.gui</h4>
        </template>
        <div>
					<b-form @submit="signIn($event)">
            <b-row>
              <b-col :sm="12" class="form_row">
                <b-form-input v-model.trim="ftUNameProf" placeholder="Username"  v-b-tooltip.hover title="Username" />
              </b-col>
              <b-col :sm="12" class="form_row">
                <b-form-input type="password" v-model.trim="ftUPwd" placeholder="Password"  v-b-tooltip.hover title="Password" />
              </b-col>
              <b-col :sm="12" class="form_row">
                <b-button  type="submit" @click.stop="signIn($event)" variant="outline-secondary">Sign in</b-button>
              </b-col>
            </b-row>
          </b-form>
        </div>
      </b-card>
    </div>

<!-- Message Modal -->
    <b-modal centered :hide-header="true" :hide-footer="true" v-model="mInfoMSGvis" body-class="text-center">
      <span class='text-center'>{{ msgInfoMSG }}</span>
    </b-modal>


  </div>

<!--
	<script src="/js/vue.js"></script>
	<script src="/js/polyfill.min.js"></script>
	<script src="/js/bootstrap-vue.js"></script>
	<script src="/js/axios.min.js"></script>
-->

    <!-- Vite JS bundles -->
    <?= vite_script_tag('auth') ?>

</body>
</html>
<?php
  exit(1);
  DB_close($db);
?>