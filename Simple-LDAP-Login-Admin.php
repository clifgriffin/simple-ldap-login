<html>
<head>
<style>
div.simpleldap_style{
	padding: 10px;
}
</style>
</head>
<?php 
//Load settings, etc
$simpleldap_account_suffix = get_option("simpleldap_account_suffix");
$simpleldap_base_dn = get_option("simpleldap_base_dn");
$simpleldap_domain_controllers = get_option("simpleldap_domain_controllers");

//Where are we?
$this_page = $_SERVER['PHP_SELF'].'?page='.$_GET['page'];


//If admin options updated (uses hidden field)
if ('process' == $_POST['stage']) {
    update_option('simpleldap_account_suffix', $_POST['account_suffix']);
	update_option('simpleldap_base_dn', $_POST['base_dn']);
	update_option('simpleldap_domain_controllers', $_POST['domain_controller']);

	$simpleldap_account_suffix = get_option("simpleldap_account_suffix");
	$simpledap_base_dn = get_option("simpleldap_base_dn");
	$simpleldap_domain_controllers = get_option("simpleldap_domain_controllers");	
}

?>
<body>
<div class="simpleldap_style">
<h2>Simple LDAP Login Settings</h2>
<hr />
<form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>&updated=true">
  <p>Account Suffix:<br />
<input type="text" name="account_suffix" value="<?php  echo $simpleldap_account_suffix; ?>" />
 </p><p>Base DN:<br />
<input type="text" name="base_dn" value="<?php  echo $simpleldap_base_dn; ?>" />
  </p><p>Domain Controller:<br />
<input type="text" name="domain_controller" value="<?php  echo $simpleldap_domain_controllers; ?>" />
  </p>
<input type="hidden" name="stage" value="process" />
<input type="submit" name="button_submit" value="<?php _e('Update Options', 'simple-ldap-login') ?> &raquo;" />
</form>
</div>
</body>
</html>
