<?php
error_reporting(E_ERROR);
include __DIR__ . "/../includes/web_functions.inc.php";
include __DIR__ . "/../includes/ldap_functions.inc.php";

if (isset($_POST["admin_password"])) {

 $ldap_connection = open_ldap_connection();
 $user_auth = ldap_setup_auth($ldap_connection,$_POST["admin_password"]);
 ldap_close($ldap_connection);

 if ($user_auth != FALSE) {
  set_setup_cookie($user_auth);
  header("Location: //${_SERVER["HTTP_HOST"]}/${THIS_MODULE_PATH}/run_checks.php\n\n");
 }
 else {
  header("Location: //${_SERVER["HTTP_HOST"]}/${THIS_MODULE_PATH}/index.php?invalid\n\n");
 }

}
else {

 render_header("Setup log in");

 if (isset($_GET["invalid"])) {
 ?>
 <div class="alert alert-warning">
  <p class="text-center">The password was incorrect.</p>
 </div>
 <?php
 }
 ?>
 <div class="container">
  <div class="panel panel-default">
   <div class="panel-heading text-center">Password for <?php print $LDAP['admin_bind_dn']; ?></div>
    <div class="panel-body text-center">
     <form class="form-inline" action='' method='post'>
      <div class="form-group">
       <input type='password' class="form-control" name='admin_password'>
      </div>
      <div class="form-group">
       <input type='submit' class="btn btn-default" value='Log in'>
      </div>
     </form>
    </div>
   </div>
  </div>
 </div>
<?php
}
render_footer();
?>
