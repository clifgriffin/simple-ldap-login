<html>
<head>
<style>
div.container{
	width: 950px;
}
div.simpleldap_style{
	padding: 5px;
	background: #EBEBEB;
	margin: 10px;
	width:450px;
	height:375px;
	font-family: Calibri,Helvetica,Arial,sans-serif;
	float: left; 
}
div.simpleldap_style_test{
	padding: 5px;
	margin: 0px 10px 10px 10px;
	background: #EBEBEB;
	width: 450px;
	height: 280px;
	font-family: Calibri,Helvetica,Arial,sans-serif;
	float: left;
}
div.information_pane{
	height:280px;
	width: 450px;
	padding: 5px;
	background: #EBEBEB;
	margin: 0px 10px 10px 0;
	font-family: Calibri,Helvetica,Arial,sans-serif;
	float:left;
}
div.advanced{
	padding: 5px;
	background: #EBEBEB;
	margin: 10px 10px 10px 0px;
	width:450px;
	height:375px;
	font-family: Calibri,Helvetica,Arial,sans-serif;
	float: left; 
}
div.banner{
	padding 5px;
	margin-left: 10px;
	margin-top: 15px;
	font-family: Calibri,Helvetica,Arial,sans-serif;
}
h1{
	margin: 0;
}
h2{
	margin: 0;
}
h3{
	margin: 0;
}
h4{
	margin: 0;
}
p{
	margin-bottom: 0;
}
</style>
</head>
<?php 
//Debug
$debug = "false";

//Where are we?
$this_page = $_SERVER['PHP_SELF'].'?page='.$_GET['page'];

//If this is a test, we will use this variable
$bool_test = 0;

//If admin options updated (uses hidden field)
if ($_POST['stage'] == 'process') 
{
    update_option('simpleldap_account_suffix', $_POST['account_suffix']);
	update_option('simpleldap_base_dn', $_POST['base_dn']);
	update_option('simpleldap_domain_controllers', $_POST['domain_controller']);

	//Version 1.3
	update_option('simpleldap_directory_type',$_POST['LDAP']);
	update_option('simpleldap_login_mode',$_POST['mode']);
	update_option('simpleldap_group',$_POST['group_name']);
	update_option('simpleldap_account_type',$_POST['create_type']);
	
	//Version 1.3.0.2
	update_option('simpleldap_security_mode',$_POST['security_mode']);
	
	//Version 1.4
	update_option('simpleldap_ol_login', $_POST['ol_login']);
	
	if(isset($_POST['use_tls']))
	{
		update_option("simpleldap_use_tls", $_POST['use_tls']);
	}
	else
	{
		update_option("simpleldap_use_tls", "no");
	}
	
}
//Test credentials
elseif ($_POST['stage'] == 'test') 
{
	global $bool_test;
	
	//Temporarily change security mode for test. Store old setting.
	$temp_holder = get_option("simpleldap_security_mode");
	update_option("simpleldap_security_mode", "security_high");
	
	$test_user = wp_authenticate($_POST['test_username'],$_POST['test_password']);
	
	//Restore security mode setting.
	update_option("simpleldap_security_mode", $temp_holder);
	
	if ($test_user->ID > 0)
	{
		$bool_test = 1;
	}
	else 
	{
		$bool_test = 2;
	}
}
//Load settings, etc
$simpleldap_account_suffix = get_option("simpleldap_account_suffix");
$simpleldap_base_dn = get_option("simpleldap_base_dn");
$simpleldap_domain_controllers = get_option("simpleldap_domain_controllers");

//Version 1.3
$simpleldap_directory_type = 	get_option("simpleldap_directory_type");
$simpleldap_login_mode = 	get_option("simpleldap_login_mode");
$simpleldap_group = 	get_option("simpleldap_group");
$simpleldap_account_type = 	get_option("simpleldap_account_type");

//Version 1.3.0.2
$simpleldap_security_mode = get_option("simpleldap_security_mode");

//Version 1.4
$simpleldap_ol_login = get_option("simpleldap_ol_login");
$simpleldap_use_tls = get_option("simpleldap_use_tls");

?>
<body>
<div class="container">
<div class="banner"><h1>Simple LDAP Login 1.4.0.5.1</h1></div>
<form style="display::inline;" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>&updated=true">
<div class="simpleldap_style">
<h2>Settings</h2>
<h3>These are rather important.</h3>
<p><strong>LDAP Directory:</strong><br/>
<input name="LDAP" type="radio" value="directory_ad" onClick="enable('advanced');" <?php if($simpleldap_directory_type=="directory_ad"){echo "checked";}?>> <label for="directory_ad">Active Directory. (default)</label><br/>
<input name="LDAP" type="radio" value="directory_ol" onClick="disable('advanced');"<?php if($simpleldap_directory_type=="directory_ol"){echo "checked";}?>> <label for="directory_ol">OpenLDAP (BETA, may support other LDAP directories)</label><br/>
</p>
<div style="float: left; width: 235px;"><p><strong>Account Suffix:</strong><br />
<input name="account_suffix" type="text" value="<?php  echo $simpleldap_account_suffix; ?>" size="20" /><br />
*Probably the suffix of your e-mail addresses. Example: @domain.com
 </p></div>
<div style="float:left; width: 200px;"><p><strong>LDAP Login Attribute (OpenLDAP only):</strong><br />
<input name="ol_login" type="text" value="<?php  echo $simpleldap_ol_login; ?>" size="15" /><br />
*In case your installation uses something other than <em>uid</em>.
 </p></div>
<p><strong>Base DN:</strong><br />
<input name="base_dn" type="text" value="<?php  echo $simpleldap_base_dn; ?>" size="35" /><br />
*Example: For subdomain.domain.sufix use DC=subdomain,DC=domain,DC=suffix 
  </p>
 <p><strong>Domain Controller(s):</strong><br />
<input name="domain_controller" type="text" value="<?php  echo $simpleldap_domain_controllers; ?>" size="50" /><br />
*Separate with semi-colons.
  </p>
<input type="hidden" name="stage" value="process" />
<input type="submit" name="button_submit" value="<?php _e('Update Options', 'simple-ldap-login') ?> &raquo;" />
</div>
<div class="advanced">
<h2>Advanced</h2>
<h3>For the intrepid and daring among you.</h3>
<p style="margin-bottom:0px;"><strong>Login mode:</strong><br>
<input name="mode" type="radio" value="mode_normal" <?php if($simpleldap_login_mode=="mode_normal"){echo "checked";}?> > <label for="mode_normal">Authenticate Wordpress users against LDAP. I will create the accounts in wordpress myself. (default)</label><br/>
<input name="mode" type="radio" value="mode_create_all" <?php if($simpleldap_login_mode=="mode_create_all"){echo "checked";}?> > <label for="mode_create_all">Create Wordpress account for anyone who successfully authenticates against LDAP.</label><br/>
<input name="mode" type="radio" value="mode_create_group" <?php if($simpleldap_login_mode=="mode_create_group"){echo "checked";}?>> <label for="mode_create_group">Create Wordpress account for users in specified AD group:</label> <input name="group_name" type="text" value="<?php  echo $simpleldap_group; ?>" size="12"/></p>
<p style="margin-left:15px; margin-top:0px;"><strong>For latter two options, create account as:</strong><br/>
<select name="create_type">
<option value="Administrator" <?php if($simpleldap_account_type=="Administrator"){echo 'selected="selected"';}?> >Administrator</option>
<option value="Editor" <?php if($simpleldap_account_type=="Editor"){echo 'selected="selected"';}?> >Editor</option>
<option value="Author" <?php if($simpleldap_account_type=="Author"){echo 'selected="selected"';}?> >Author</option>
<option value="Contributor" <?php if($simpleldap_account_type=="Contributor"){echo 'selected="selected"';}?> >Contributor</option>
<option value="Subscriber" <?php if($simpleldap_account_type=="Subscriber"){echo 'selected="selected"';}?> >Subscriber</option>
</select>
</p>
<p>
<strong>Security mode:</strong><br>
<input name="security_mode" type="radio" id="security_low" value="security_low" <?php if($simpleldap_security_mode=="security_low"){echo "checked";}?> > <label for="security_low"><strong>Low.</strong> Default mode. First attempts to login with LDAP password, failing that, it attempts to login using the local wordpress password. If you intend to use a mixture of local and LDAP accounts, leave this mode enabled.</label><br/>
<input name="security_mode" type="radio" id="security_high" value="security_high" <?php if($simpleldap_security_mode=="security_high"){echo "checked";}?> > <label for="security_high"><strong>High.</strong> Restrict login to only LDAP passwords. If a wordpress username fails to authenticate against LDAP, login will fail. More secure.</label><br/>
</p>
<p>
<strong>TLS:</strong><br />
<input name="use_tls" type="checkbox" value="yes" id="use_tls" <?php if($simpleldap_use_tls=="yes"){echo "checked";}?>> <label for="use_tls">Use TLS? (Beta. Very beta.)</label>
</p>
</div>
</form>
<div class="simpleldap_style_test">
<h2>Test Settings</h2>
<h3>Use this form as a limited test for those settings you saved.* This <em>will</em> test user creation and group membership. If settings don't work, use another browser to try actually logging in. (unless you'd rather get locked out)</h3>
<h4>*You did save them, right?</h4>
<form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
  <p>Username:<br />
<input name="test_username" type="text" size="35" />
 </p><p>Password:<br />
<input name="test_password" type="password" size="35" />
  </p>
<input type="hidden" name="stage" value="test" />
<input type="submit" name="button_submit" value="<?php _e('Test Settings', 'simple-ldap-login') ?> &raquo;" />
</form>
<p>
<h4>Test Results:</h4>
<?php
if($bool_test == 0)
{
	echo "Nothing to report yet, Mr. Fahrenheit.";
}
if($bool_test == 1)
{
	echo "Congratulations! The test succeeded. This account is able to login.";
}
elseif($bool_test == 2)
{
	echo "Failure. Your settings do not seem to work yet or the credentials are either wrong or have insufficient group membership.";
}
?>
</p>
</div>
<div class="information_pane">
<? echo "<iframe src =\"http://clifgriffin.com/plugins/simple-ldap-login/news.htm\" width=\"98%\" height=\"280px\" border=\"0\"><p>Oddly, your version of PHP doesn't allow file_get_contents to use URLs. But even more oddly, your browser doesn't allow frames! I think it's time for you to consider leaving 1998 in the past.</p></iframe>"; ?>
</div>
</div>
<?php
//Echo settings
if($debug == "true")
{
echo "<p style=\"clear:both;\">Debug Info:<br/>";
echo "simpleldap_directory_type: ".get_option("simpleldap_directory_type")."/".$_POST['LDAP']."<br/>";
echo "simpleldap_login_mode: ".get_option("simpleldap_login_mode")."/".$_POST['mode']."<br/>";
echo "simpleldap_group: ".get_option("simpleldap_group")."/".$_POST['group_name']."<br/>";
echo "simpleldap_account_type: ".get_option("simpleldap_account_type")."/".$_POST['create_type']."<br/></p>";
}
?>
</body>
</html>