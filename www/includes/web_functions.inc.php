<?php

#Security level vars

$VALIDATED = FALSE;
$IS_ADMIN = FALSE;
$IS_SETUP_ADMIN = FALSE;
$ACCESS_LEVEL_NAME = array('account','admin');
unset($USER_ID);
$CURRENT_PAGE=htmlentities($_SERVER['PHP_SELF']);

$paths=explode('/',getcwd());
$THIS_MODULE_PATH=end($paths);

$GOOD_ICON = "&#9745;";
$WARN_ICON = "&#9888;";
$FAIL_ICON = "&#9940;";

include ("modules.inc.php");   # module definitions
include ("config.inc.php");    # get local settings

validate_passkey_cookie();

######################################################

function generate_passkey() {

 $rnd1 = rand(10000000,100000000000);
 $rnd2 = rand(10000000,100000000000);
 $rnd3 = rand(10000000,100000000000);
 return sprintf("%0x",$rnd1) . sprintf("%0x",$rnd2) . sprintf("%0x",$rnd3);

}


######################################################

function set_passkey_cookie($user_id,$is_admin) {

 # Create a random value, store it locally and set it in a cookie.

 global $LOGIN_TIMEOUT_MINS, $VALIDATED, $USER_ID, $IS_ADMIN;


 $passkey = generate_passkey();
 $this_time=time();
 $admin_val = 0;

 if ($is_admin == TRUE ) {
  $admin_val = 1;
  $IS_ADMIN = TRUE;
 }
 $filename = preg_replace('/[^a-zA-Z0-9]/','_', $user_id);
 file_put_contents("/tmp/$filename","$passkey:$admin_val:$this_time");
 setcookie('orf_cookie', "$user_id:$passkey", $this_time+(60 * $LOGIN_TIMEOUT_MINS), '/', $_SERVER["HTTP_HOST"]);

 $VALIDATED = TRUE;

}


######################################################

function validate_passkey_cookie() {

 global $LOGIN_TIMEOUT_MINS, $IS_ADMIN, $USER_ID, $VALIDATED;

 if (isset($_COOKIE['orf_cookie'])) {

  list($user_id,$c_passkey) = explode(":",$_COOKIE['orf_cookie']);
  $filename = preg_replace('/[^a-zA-Z0-9]/','_', $user_id);
  $session_file = file_get_contents("/tmp/$filename");
  if (!$session_file) {
   $VALIDATED = FALSE;
   unset($USER_ID);
   $IS_ADMIN = FALSE;
  }
  else {
   list($f_passkey,$f_is_admin,$f_time) = explode(":",$session_file);
   $this_time=time();
   if (!empty($c_passkey) and $f_passkey == $c_passkey and $this_time < $f_time+(60 * $LOGIN_TIMEOUT_MINS)) {
    if ($f_is_admin == 1) { $IS_ADMIN = TRUE; }
    $VALIDATED = TRUE;
    $USER_ID=$user_id;
    set_passkey_cookie($USER_ID,$IS_ADMIN);
   }
  }
 }
}


######################################################

function set_setup_cookie() {

 # Create a random value, store it locally and set it in a cookie.

 global $LOGIN_TIMEOUT_MINS, $IS_SETUP_ADMIN;

 $passkey = generate_passkey();
 $this_time=time();

 $IS_SETUP_ADMIN = TRUE;

 file_put_contents("/tmp/ldap_setup","$passkey:$this_time");
 setcookie('setup_cookie', "$passkey", $this_time+(60 * $LOGIN_TIMEOUT_MINS), '/', $_SERVER["HTTP_HOST"]);

}


######################################################

function validate_setup_cookie() {

 global $LOGIN_TIMEOUT_MINS, $IS_SETUP_ADMIN;

 if (isset($_COOKIE['setup_cookie'])) {

  $c_passkey = $_COOKIE['setup_cookie'];
  $session_file = file_get_contents("/tmp/ldap_setup");
  if (!$session_file) {
   $IS_SETUP_ADMIN = FALSE;
  }
  list($f_passkey,$f_time) = explode(":",$session_file);
  $this_time=time();
  if (!empty($c_passkey) and $f_passkey == $c_passkey and $this_time < $f_time+(60 * $LOGIN_TIMEOUT_MINS)) {
   $IS_SETUP_ADMIN = TRUE;
   set_setup_cookie();
  }

 }

}


######################################################

function log_out($method='normal') {

 # Delete the passkey from the database and the passkey cookie

 global $USER_ID;

 setcookie('orf_cookie', "", time()-20000 , "/", $_SERVER["HTTP_HOST"], 0);

 $filename = preg_replace('/[^a-zA-Z0-9]/','_', $USER_ID);
 unlink("/tmp/$filename");

 if ($method == 'auto') { $options = "?logged_out"; } else { $options = ""; }
 header("Location:  //${_SERVER["HTTP_HOST"]}/index.php$options\n\n");

}


######################################################

function render_header($title="",$menu=TRUE) {

 global $SITE_NAME, $IS_ADMIN, $LDAP_CONNECTION_WARNING;

 if (empty($title)) { $title = $SITE_NAME; }

 #Initialise the HTML output for the page.

 ?>
<HTML>
<HEAD>
 <TITLE><?php print "$title"; ?></TITLE>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width, initial-scale=1">

 <meta http-equiv="content-type" content="text/html; charset=utf-8 ;">
 <meta http-equiv="Content-Security-Policy" content="script-src * 'unsafe-inline' 'unsafe-eval'; style-src * 'unsafe-inline' 'unsafe-eval'; media-src *">
 <meta http-equiv="Content-Security-Policy" content="default-src * 'unsafe-inline' 'unsafe-eval'; child-src 'none'; object-src 'none'">

 <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
 <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
 <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</HEAD>
<BODY>
<?php

 if ($menu == TRUE) {
  render_menu();
 }

}


######################################################

function render_menu() {

 #Render the navigation menu.
 #The menu is dynamically rendered the $MODULES hash

 global $SITE_NAME, $MODULES, $THIS_MODULE_PATH, $VALIDATED, $IS_ADMIN;

 ?>
  <nav class="navbar navbar-default">
   <div class="container-fluid">
   <div class="navbar-header">
     <a class="navbar-brand" href="#"><?php print $SITE_NAME ?></a>
   </div>
     <ul class="nav navbar-nav">
     <?php
     foreach ($MODULES as $module => $access) {

      $this_module_name=stripslashes(ucwords(preg_replace('/_/',' ',$module)));

      $show_this_module = TRUE;
      if ($VALIDATED == TRUE) {
       if ($access == 'hidden_on_login') { $show_this_module = FALSE; }
       if ($IS_ADMIN == FALSE and $access == 'admin' ){ $show_this_module = FALSE; }
      }
      else {
       if ($access != 'hidden_on_login') { $show_this_module = FALSE; }
      }
      #print "<p>$module - access is $access & show is $show_this_module</p>";
      if ($show_this_module == TRUE ) {
       if ($module == $THIS_MODULE_PATH) {
        print "<li class='active'>";
       }
       else {
        print '<li>';
       }
       print "<a href='/{$module}'>$this_module_name</a></li>\n";
      }
     }
    ?>
    </ul>
   </div>
  </nav>
 <?php
}


######################################################

function render_footer() {

#Finish rendering an HTML page.

?>
 </BODY>
</HTML>
<?php

}


######################################################

function set_page_access($level) {

 global $IS_ADMIN, $IS_SETUP_ADMIN, $VALIDATED;

 #Set the security level needed to view a page.
 #This should be one of the first pieces of code
 #you call on a page.
 #Either 'setup', 'admin' or 'user'.

 if ($level == "setup") {
  if ($IS_SETUP_ADMIN == TRUE) {
   return;
  }
  else {
   header("Location: //" . $_SERVER["HTTP_HOST"] . "/setup/index.php?unauthorised\n\n");
   exit(0);
  }
 }

 if ($level == "admin") {
  if ($IS_ADMIN == TRUE and $VALIDATED == TRUE) {
   return;
  }
  else {
   header("Location: //" . $_SERVER["HTTP_HOST"] . "/index.php?unauthorised\n\n");
   exit(0);
  }
 }

 if ($level == "user") {
  if ($VALIDATED == TRUE){
   return;
  }
  else {
   header("Location: //" . $_SERVER["HTTP_HOST"] . "/index.php?unauthorised\n\n");
   exit(0);
  }
 }

}


######################################################

function is_valid_email($email) {

 return (!preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $email)) ? FALSE : TRUE;

}


function render_js_username_check(){

  global $USERNAME_REGEX;

 print <<<EoCheckJS

<script>

 function check_entity_name_validity(name,div_id) {

  var check_regex = /$USERNAME_REGEX/;

  if (! check_regex.test(name) ) {
   document.getElementById(div_id).classList.add("has-error");
  }
  else {
   document.getElementById(div_id).classList.remove("has-error");
  }

 }

</script>
EoCheckJS;

}


######################################################

function render_js_username_generator($firstname_field_id,$lastname_field_id,$username_field_id,$username_div_id) {

 #Parameters are the IDs of the input fields and username name div in the account creation form.
 #The div will be set to warning if the username is invalid.

 global $USERNAME_FORMAT, $USERNAME_REGEX;

  render_js_username_check();

  print <<<EoRenderJS
<script>

 function update_username() {

  var first_name = document.getElementById('$firstname_field_id').value;
  var last_name  = document.getElementById('$lastname_field_id').value;
  var template = '$USERNAME_FORMAT';

  var actual_username = template;

  actual_username = actual_username.replace('{first_name}', first_name.toLowerCase() );
  actual_username = actual_username.replace('{first_name_initial}', first_name.charAt(0).toLowerCase() );
  actual_username = actual_username.replace('{last_name}', last_name.toLowerCase() );
  actual_username = actual_username.replace('{last_name_initial}', last_name.charAt(0).toLowerCase() );

  check_entity_name_validity(actual_username,'$username_div_id');

  document.getElementById('$username_field_id').value = actual_username;

 }
</script>
EoRenderJS;

}


######################################################

function render_js_email_generator($username_field_id,$email_field_id) {

 global $EMAIL_DOMAIN;

  print <<<EoRenderEmailJS
<script>

 var auto_email_update = true;

 function update_email() {

  if ( auto_email_update == true && "$EMAIL_DOMAIN" != ""  ) {
    var username = document.getElementById('$username_field_id').value;
    document.getElementById('$email_field_id').value = username + '@' + "$EMAIL_DOMAIN";
  }
 }
</script>
EoRenderEmailJS;

}

?>
