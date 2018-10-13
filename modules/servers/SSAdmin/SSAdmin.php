<?php

/**
 * @author Gaukas
 * @version 3.1.0
**/

use WHMCS\Database\Capsule;

/* Needs to be enabled after debugging
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}
*/

/*

    Beginning of functional functions called directly by WHMCS

*/
function SSAdmin_MetaData()
{
    return array(
        'DisplayName' => 'ShadowsocksAdmin',
        'APIVersion' => '1.1', // Use API Version 1.1
        'RequiresServer' => true, // Set true if module requires a server to work
    );
}

function SSAdmin_ConfigOptions() {
	return [
		"dbname" => [
			"FriendlyName" => "Database", // First the database name.
			"Type" => "text",             //$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
			"Size" => "25",
			"Description" => "User database name",
			"Default" => "shadowsocks",
		],
		"encrypt" => [
			"FriendlyName" => "Encryption", // Second the encryption method (like CHACHA20).
			"Type" => "text",               //echo "Encryption: ".$params['configoption2'];
			"Size" => "25",
			"Description" => "Transfer encrypt method",
			"Default" => "AES-256-CFB",
		],
		"port" => [
			"FriendlyName" => "Initial Port", // Third the initial port for default.
			"Type" => "text",                 //$startport = $params['configoption3']; Check the availibility before using!
			"Size" => "25",
			"Description" => "Default port if no users exist in current table",
			"Default" => "8000",
		],
		"traffic" => [
			"FriendlyName" => "Default Traffic(GiB)", // Fourth the default traffic per payment period (as the traffic usage will be reset by renewing).
			"Type" => "text",                         // $traffic = $params['configoption4']*1024*1024*1024; (Remember to transfer your Gibi Bytes  to Bytes.)
			"Size" => "25",
			"Description" => "Default bandwidth if not set specially",
			"Default" => "10",
		],
		"server" => [
			"FriendlyName" => "Server List", // Last as the list of the servers.
			"Type" => "textarea",
			"Description" => "All the ss-server in this product. Use semicolon in English (;) to devide if you have more than one.",
		],
	];
}

function SSAdmin_CreateAccount($params) {
	$serviceid			= $params["serviceid"]; //The unique ID of the product in WHMCS database.
    $password 			= $params["password"]; //
	$port = SSAdmin_NextPort($params);
	if(!is_numeric($port))
	{
		return 'Error occurred. '.$port; // A number is expected. Or it is a error message.
	}

	// Use WHMCS Capsule to get adminusername for API
	$pdo = Capsule::connection()->getPdo();
	$pdo->beginTransaction();
	try {
		$stmt = $pdo->query("SELECT username FROM tbladmins");
		$adminusername = $stmt->fetch(PDO::FETCH_ASSOC);
		$pdo->commit();
	} catch (\Exception $e) {
		$pdo->rollBack();
		return "Got error when trying to get adminusername {$e->getMessage()}";
	}
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];
	$attr = array(
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
		$pdo2 = new PDO($dsn, $username, $pwd, $attr);
		$stmt2 = $pdo2->prepare('SELECT pid FROM user WHERE pid=:serviceid');
		$stmt2->execute(array(':serviceid' => $serviceid));
		$select = $stmt2->fetch(PDO::FETCH_ASSOC);
	}
	catch(PDOException $e){
		return 'Cannot check if repeat.' . $e->getMessage();
	}

  if (!empty($select['pid'])) {
		$result = "Service already exists.";
  } else {
		if (isset($params['customfields']['password'])) {
			$command = 'EncryptPassword';
			$postData = array(
	  		'password2' => $params["customfields"]['password'],
			);
			try {
				$adminuser = $adminusername['username'];
			} catch (Exception $e) {
				die("Failure in adminuser define. No username in the ARRAY adminusername could be found.");
			}
			$adminuser = $adminusername['username'];
			$results = localAPI($command, $postData, $adminuser);
			$table = 'tblhosting';
			try {
    		$updatedUserCount = Capsule::table($table)
        	->where('id', $params["serviceid"])
        	->update(
          	[
            	'password' => $results['password'],
      			]
        	);
			} catch (\Exception $e) {
    		echo "Password update failed.Bad Capsule function. {$e->getMessage()}";
			}
			$password = $params["customfields"]['password'];
		}

		if(isset($params['configoptions']['Traffic']))
		{
      $traffic_GB = explode("G",$params['configoptions']['Traffic'])[0];
      $traffic = $traffic_GB*1024*1048576;
			$stmt3 = $pdo2->prepare("INSERT INTO user(pid,passwd,port,transfer_enable) VALUES (:serviceid,:password,:port,:traffic)");

			if($stmt3->execute(array(':serviceid'=>$params['serviceid'], ':password'=>$password, ':port'=>$port, ':traffic'=>$traffic)))
			{
				$result = 'success';
			}
			else
			{
				$result='Error during CreatingAccount-Inserting into user';
			}

		}
		else
		{
			if (!empty($params['configoption4']))
			{
				$max = $params['configoption4'];
			}
			if(isset($max))
			{
				$traffic = $max*1024*1048576;
			} else {
				$traffic = 53687091200;
			}

			$stmt3 = $pdo2->prepare("INSERT INTO user(pid,passwd,port,transfer_enable) VALUES (:serviceid,:password,:port,:traffic)");
						if($stmt3->execute(array(':serviceid'=>$params['serviceid'], ':password'=>$password, ':port'=>$port, ':traffic'=>$traffic)))
						{
								$result='success';
						}
						else
						{
								$result = 'Error. Could not Creat Account.';
						}
				}
  	}
  	return $result;
}

function SSAdmin_TerminateAccount($params) {
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
			$pdo = new PDO($dsn, $username, $pwd, $attr);
			$stmt = $pdo->prepare('DELETE FROM user WHERE pid=:serviceid');
			if($stmt->execute(array(':serviceid' => $params['serviceid'])))
			{
				$result = 'success';
			} else {
				$result = 'Error. Could not Terminate this Account.';
			}
	}
	catch(PDOException $e){
			$result = 'PDO error:' . $e->getMessage();
	}
	return $result;
}

function SSAdmin_SuspendAccount($params) {
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];
	$attr = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);

	$password = md5(time().rand(0,100));
	try{
		$pdo = new PDO($dsn, $username, $pwd, $attr);
		$stmt = $pdo->prepare("SELECT pid FROM user WHERE pid=:serviceid");
		if($stmt->execute(array(':serviceid' => $params['serviceid'])))
		{
		$select = $stmt->fetch(PDO::FETCH_ASSOC);
		}
	 }catch(PDOException $e){
		$result = 'Error. Cloud not Select this Account';
		return $result;
	 }

		if ($select == "")
		{
			$result = "Can't find.";
		}
		else
		{
			try
			{
					$stmt = $pdo->prepare("UPDATE user SET  passwd=:passwd WHERE pid=:serviceid");
					if($stmt->execute(array(':passwd' => $password, ':serviceid' => $params['serviceid'])))
					{
						$result = 'success';
			  	}
					else
					{
						$result="failed";
					}
			 }
			 catch(PDOException $e)
			 {
					die('Error. Cloud not Suspend this Account' . $e->getMessage());
				}
		}
		return $result;
	}

function SSAdmin_UnSuspendAccount($params) {
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
			$pdo = new PDO($dsn, $username, $pwd, $attr);
			//if ($params['password'] == $params['customfields']['password']) {
			$password = $params['password'];
			//} else {
			//	$password = $params['customfields']['password'];
			//}
			$stmt = $pdo->prepare("SELECT pid FROM user WHERE pid=:serviceid");
			$stmt->execute(array(':serviceid' => $params['serviceid']));
			$select = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($select == "") {
				$result = "Can't find.";
			} else {
						$stmt = $pdo->prepare("UPDATE user SET  passwd=:passwd WHERE pid=:serviceid");
						if($stmt->execute(array(':passwd' => $password, ':serviceid' => $params['serviceid'])))
						{
							$result = 'success';
				  	}
						else
						{
							$result="failed";
						}
				 }
	}
	catch(PDOException $e){
			die('Cannot UnSuspendAccount. PDO Exception.' . $e->getMessage());
	}
	return $result;
}

function SSAdmin_ChangePassword($params) {
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
			$pdo = new PDO($dsn, $username, $pwd, $attr);
			$stmt = $pdo->prepare("SELECT pid FROM user WHERE pid=:serviceid");
			$stmt->execute(array(':serviceid' => $params['serviceid']));
			$select = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($select == "") {
				$result = "Can't find.";
			} else {
				$stmt = $pdo->prepare("UPDATE user SET passwd=:password WHERE pid=:serviceid");
				$stmt->execute(array(':password' => $params['password'], ':serviceid' => $params['serviceid']));
				$result = "success";
			}
		}
		catch(PDOException $e){
    		die('Update userpassword Failed in ChangePassword' . $e->getMessage());
		}
		if ($result=="success")
		{
				$pdo2 = Capsule::connection()->getPdo();
				$pdo2->beginTransaction();
				try {
    			$statement = $pdo2->query('SELECT id FROM tblcustomfields WHERE fieldname=Password');//Editable 'Password'
    			$data = $statement->fetch(PDO::FETCH_ASSOC);
    			$pdo2->commit();
				} catch (\Exception $e) {
    			echo "Error when ChangePassword by WHMCS PDO {$e->getMessage()}";
    			$pdo2->rollBack();
				}
				$fieldid = $data['id'];
				$table = 'tblcustomfieldsvalues';
				try {
    				$updatePassword = Capsule::table($table)
        				->where('relid', $params["serviceid"])
        		    ->where('fieldid', $fieldid)
        				->update(
            				[
                				'value' => $params["password"],
            				]
        				);
						} catch (\Exception $e)  {
    				echo "Password reset failed in ChangePassword.Bad Capsule function. {$e->getMessage()}";
						}
				$result = 'success';
			} else {
				echo $result;
			}
	return $result;
}

function SSAdmin_ChangePackage($params) {
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
		$pdo = new PDO($dsn, $username, $pwd, $attr);
		if(isset($params['configoptions']['Traffic'])) {
          $traffic_GB = explode("G",$params['configoptions']['Traffic'])[0];
          $traffic = $traffic_GB*1024*1048576;
					$stmt = $pdo->prepare("UPDATE user SET transfer_enable=:traffic WHERE pid=:serviceid");
					$stmt->execute(array(':traffic' => $traffic, ':serviceid' => $params['serviceid']));
					return 'success';
		} else {
					if (!empty($params['configoption4'])) {
						$max = $params['configoption4'];
					}
					if(isset($max)) {
						$traffic = $max*1024*1048576;
					} else {
						$traffic = 53687091200;
					}
					$stmt = $pdo->prepare("UPDATE user SET transfer_enable=:traffic WHERE pid=:serviceid");
					$stmt->execute(array(':traffic' => $traffic, ':serviceid' => $params['serviceid']));
					return 'success';
		}
	}
	catch(PDOException $e){
		die('Update usertransfer Failed in ChangePackage' . $e->getMessage());
	}
}

function SSAdmin_Renew($params) {
  $result = SSAdmin_RstTraffic($params);
  //$result = SSAdmin_AddTraffic($params);
  switch ($result){
    case 'success':
      return 'success';
    case false:
      return 'Failed to execute PDO SQL query to reset/add traffic. Check the database.';
    default:
      return $result;
	}
}
/*

    Ending of functional functions called directly by WHMCS
    &&
    Beginning of supporting functions

*/

//
//The function NextPort will send a query to database to know the next port number
function SSAdmin_NextPort($params) {
	if(!isset($params['configoption3']) || $params['configoption3'] == "") {
			$start = 8800;
	} else {
			$start = $params['configoption3'];
	}
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
	    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

  try{
		$pdo = new PDO($dsn, $username, $pwd, $attr);
		$stmt = $pdo->query("SELECT port FROM user");
		$select = $stmt->fetch(PDO::FETCH_ASSOC);
		// Check whether there are former services in the table, will return a port as the last port + 1.
		if(!$select == "")
		{
			$stmt2 = $pdo->query("SELECT port FROM user order by port desc limit 1"); //Check the last port
			$last = $stmt2->fetch(PDO::FETCH_ASSOC);
			// Check whether the ports have been used up
			if ($last['port'] >= 65535)
			{
				$result = 'There is no available port. You may need a new database.'; // If last port is 65535 or more, there will be no space for a larger port number.
			}	else {
				$result = $last['port']+1; // If port is available, then use next port.
			}
		}	else {
			$result=$start; // If no service in the table, will create accounts with the default port.
		}
  }
	catch(PDOException $e){
      $result = 'PDOSQL Error: '.$e->getMessage(); // If PDO error, will return the error.
  }
	return $result;
}
//The function RstTraffic will operate the database as setting the upload and download traffic to zero.
function SSAdmin_RstTraffic($params) {
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
		$pdo = new PDO($dsn, $username, $pwd, $attr);
		$stmt = $pdo->prepare("UPDATE user SET u='0',d='0' WHERE pid=:serviceid");
		if($stmt->execute(array(':serviceid' => $params['serviceid']))){
			return 'success';
		}
		else {
			return false;
		}
	}
	catch(PDOException $e){
		die('PDO Error occurred in resetting traffic' . $e->getMessage());
	}
}
//The function AddTraffic will operate the database as set transfer_enable as transfer_enable+$params[configoptions][Traffic]. An alternative idea for traffic calculation.
function SSAdmin_AddTraffic($params) {
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

  if(isset($params['configoptions']['Traffic']))
  {
    $traffic_GB = explode("G",$params['configoptions']['Traffic'])[0];
    $traffic = $traffic_GB*1024*1048576;
  }
  else
  {
    if (!empty($params['configoption4']))
    {
      $traffic = $params['configoption4']*1024*1048576;
    } else {
      $traffic = 53687091200;
    }
  }

	try
	{
		$pdo = new PDO($dsn, $username, $pwd, $attr);
		$stmt = $pdo->prepare("UPDATE `user` SET `transfer_enable`=`transfer_enable`+:traffic WHERE `pid`=:serviceid");
		if($stmt->execute(array(':traffic' => $traffic, ':serviceid' => $params['serviceid']))){
			return 'success';
		}
		else {
			return false;
		}
	}
	catch(PDOException $e){
		die('PDO Error occurred in adding traffic' . $e->getMessage());
	}
}

function SSAdmin_RefrePort($params) {
  $dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
		$pdo = new PDO($dsn, $username, $pwd, $attr);
		$stmt = $pdo->prepare("SELECT u,d,port,passwd,transfer_enable FROM user WHERE pid=:serviceid");
		$stmt->execute(array(':serviceid' => $params['serviceid']));
		$Query = $stmt->fetch(PDO::FETCH_BOTH);
		$u = $Query['u'];
    $d = $Query['d'];
    $passwd = $Query['passwd'];
    $traffic = $Query['transfer_enable'];
		$port = $Query['port'];
    $nextport = SSAdmin_NextPort($params);

    if($nextport==0){
      return 'Sorry, next port exceeded.'; //If this is the last port, refuse the refresh request to prevent abuse.
    }

    if($port==$nextport-1){
      return 'Sorry, this is a new port and is not eligible for refresh.'; //If this is the last port, refuse the refresh request to prevent abuse.
    }

    $terminate=SSAdmin_TerminateAccount($params);
    if($terminate!='success')
    {
      return $terminate;
    }

    $stmt3 = $pdo->prepare("INSERT INTO user(u,d,port,passwd,transfer_enable,pid) VALUES (:u,:d,:port,:password,:traffic,:serviceid)");
    if($stmt3->execute(array(':u'=>$u, ':d'=>$d, ':port'=>$nextport, ':password'=>$passwd, ':traffic'=>$traffic, ':serviceid'=>$params['serviceid'])))
    {
      return 'success';
    }
    else
    {
      return 'Failed to refresh port. An error out of PDO occurred.';
    }
  }
  catch(PDOException $e){
    die('PDO Error occurred.'.$e->getMessage());
  }
}
//
//The function to divide every node by the character ';' and output as a node for each line in HTML (devide with <br>)
function SSAdmin_node($params) {
	$node = $params['configoption5'];
	if (!empty($node) || isset($node)) {
		$str = explode(';', $node);
		foreach ($str as $key => $val) {
			$html .= $str[$key].'<br>';
		}
	} else {
		$str = $params['serverip'];
		$html .= $str.'<br>';
	}
	return $html;
}
//Show the SS link as ss://{method[-auth]:password@hostname:port} (the string in {} was encrypted by base64)
function SSAdmin_link($params) {
	$node = $params['configoption5'];
	$encrypt = $params['configoption2'];

	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
		$pdo = new PDO($dsn, $username, $pwd, $attr);
		$stmt = $pdo->prepare("SELECT port,passwd FROM user WHERE pid=:serviceid");
		$stmt->execute(array(':serviceid' => $params['serviceid']));
		$Query = $stmt->fetch(PDO::FETCH_ASSOC);
	}
	catch(PDOException $e){
		die('Select userinfo Failed in SSLink' . $e->getMessage());
	}

	$Port = $Query['port'];
  $password = $Query['passwd'];
	if (!empty($node) || isset($node)) {
		$str = explode(';', $node);
		foreach ($str as $key => $val) {
			$origincode = $encrypt.':'.$password."@".$str[$key].':'.$Port;//ss://method[-auth]:password@hostname:port
			$output .= 'ss://'.base64_encode($origincode).'<br>';
		}
	} else {
		$origincode = $encrypt.':'.$password."@".$params['serverip'].':'.$Port;//ss://method[-auth]:password@hostname:port
		$output .= 'ss://'.base64_encode($origincode).'<br>';
	}
  //return $origincode;
	return $output;
}

function SSAdmin_qrcode($params) {
	$node = $params['configoption5'];
	$encrypt = $params['configoption2'];
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
		$pdo = new PDO($dsn, $username, $pwd, $attr);
		$stmt = $pdo->prepare("SELECT port,passwd FROM user WHERE pid=:serviceid");
		$stmt->execute(array(':serviceid' => $params['serviceid']));
		$Query = $stmt->fetch(PDO::FETCH_ASSOC);
	}
	catch(PDOException $e){
		die('Select userinfo Failed in qrCode' . $e->getMessage());
	}

  $Port = $Query['port'];
  $password = $Query['passwd'];
	if (!empty($node) || isset($node)) {
		$str = explode(';', $node);
		foreach ($str as $key => $val) {
			$origincode = $encrypt.':'.$password."@".$str[$key].':'.$Port; // method[-auth]:password@hostname:port ,-auth for OTA.
			$output = 'ss://'.base64_encode($origincode);
      $imgs .= '<img src="https://example.com/modules/servers/SSAdmin/lib/QR_generator/qrcode.php?text='.$output.'" />&nbsp;';
		}
	} else {
		$origincode = $encrypt.':'.$password."@".$params['serverip'].':'.$Port;//ss://method[-auth]:password@hostname:port
		$output = 'ss://'.base64_encode($origincode);
    $imgs = '<img src="https://example.com/modules/servers/SSAdmin/lib/QR_generator/qrcode.php?text='.$output.'" />&nbsp;';
	}
  //return $origincode;
	//return $output;
  return $imgs;
}
/*

      Ending of supporting functions
      &&
      Beginning of WHMCS UI related functions

*/
function SSAdmin_ClientArea($params) {
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
		$pdo = new PDO($dsn, $username, $pwd, $attr);
		$traffic = $params['configoptions']['Traffic'];
		$stmt = $pdo->prepare("SELECT sum(u+d),port,passwd,transfer_enable FROM user WHERE pid=:serviceid");
		$stmt->execute(array(':serviceid' => $params['serviceid']));
		$query = $stmt->fetch(PDO::FETCH_BOTH);
		$usage = $query[0] / 1073741824;
    $traffic = $query['transfer_enable'] / 1073741824;
		$port = $query['port'];
		$free = $traffic - $usage;
		$password = $query['passwd'];
		$traffic = round($traffic,2);
		$usage = round($usage,2);
		$free = round($free,2);
		$node = SSAdmin_node($params);
    $sslink = SSAdmin_link($params);
		$ssqr = SSAdmin_qrcode($params);
        //debug
        $decodeQuery = json_encode($query);
	}
	catch(PDOException $e){
			$html=" 
			<div class=\"row\">
			<!--<div class=\"col-sm-4\">-->
			<!--<div class=\"panel-collapse collapse in\">-->

			<h3 style=\"color: #ffffff; background-color: #ff0000\"><strong>SERVICE OUT OF ORDER</strong></h3>

			<hr />

			<h4><strong>Feel free to contact our customer service if you don't think you should see this.</strong></h4>

			<hr />

			<h4style=\"color: #000000; background-color: #ffffff\"><strong>". $e->getMessage() ."</strong></h4>

			<hr />

			</div>
		<!--</div>-->
    	";
	}

    if (isset( $traffic )) {
    	$html = "
    	<div class=\"row\">
			<!--<div class=\"col-sm-4\">-->
			<!--<div class=\"panel-collapse collapse in\">-->

			<h3 style=\"color:red;\"><strong>All the information below should be kept secret or may cause security issues.</strong></h3>

			<hr />

			<h4><strong>Feel free to contact our customer service if you get trouble in configure your clients.</strong></h4>

			<hr />

			<h3>Server</h3>
			<h5>{$node}</h5>

			<hr />

			<h3>Service Port</h3>
			<h5>{$port}</h5>

			<hr />

			<h3>Service Password</h3>
			<h5>{$password}</h5>

			<hr />

			<h3>Encryption</h3>
			<h5>{$params['configoption2']}</h5>

			<hr />

			<h3>Traffic Package</h3>
			<h5>Bandwidth: {$traffic} GB</h5>
			<h5>Used: {$usage} GB</h5>
			<h5>Balance: {$free} GB</h5>

			<hr />

			<h3>SS-Link</h3>
			<h5>{$sslink}</h5>

			<hr />

			<h3>QR Code</h3>
			<h5>{$ssqr}</h5>

				<!--</div></div>-->
				<!--<div class=\"col-sm-8\">-->
			</div>
		<!--</div>-->
    	";
    } else {
    	$html = "
			<div class=\"row\">
			<!--<div class=\"col-sm-4\">-->
			<!--<div class=\"panel-collapse collapse in\">-->

			<h3 style=\"color:red;\"><strong>All the information below should be kept secret or may cause security issues.</strong></h3>

			<hr />

			<h4><strong>Feel free to contact our customer service if you get trouble in configure your clients.</strong></h4>

			<hr />

			<h3><strong>Server List</strong></h3>
			<h5>{$node}</h5>

			<hr />

			<h3>Service Port</h3>
			<h5>{$port}</h5>

			<hr />

			<h3>Service Password</h3>
			<h5>{$password}</h5>

			<hr />

			<h3><strong>Encryption</strong></h3>
			<h5>{$params['configoption2']}</h5>

			<hr />

			<h3><strong>Traffic Package</strong></h3>
			<h5>Bandwidth: Unlimited</h5>
			<h5>Used: {$usage}GB</h5>

			<hr />

			<h3><strong>SS-Link</strong></h3>
			<h5>{$sslink}</h5>

			<hr />

			<h3><strong>QR Code</strong></h3>
			<h5>{$ssqr}</h5>
			</div>
			</div>
		<!--</div>-->
    	";
    }
    return $html;
}

function SSAdmin_AdminServicesTabFields($params) {
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	if (isset($params['configoptions']['Traffic'])) {
			$traffic = $params['configoptions']['Traffic']*1024;
	} else if(!empty($params['configoption4'])) {
			$traffic = $params['configoption4']*1024;
	} else {
			$traffic = 1048576;
	}

	try
	{
			$pdo = new PDO($dsn, $username, $pwd, $attr);
			$stmt = $pdo->prepare("SELECT sum(u+d),port,transfer_enable FROM user WHERE pid=:serviceid");
			$stmt->execute(array(':serviceid' => $params['serviceid']));
			$Query = $stmt->fetch(PDO::FETCH_BOTH);
			$Usage = $Query[0]/1048576;
      		$traffic = $Query['transfer_enable'] / 1048576;
			$Port = $Query['port'];
			$Free = $traffic - $Usage;
			$fieldsarray = array(
			 'Traffic Package' => $traffic.' MB',
			 'Used' => $Usage.' MB',
			 'Balance' => $Free.' MB',
			 'Service port' => $Port,
			);
			return $fieldsarray;
	}
	catch(PDOException $e){
			$fieldsarray = array(
			 'Status' => 'ERROR',
			 'Reason' => 'Failed to establish connection to database',
			 'ErrMsg' => $e->getMessage(),
		   	);
		   	return $fieldsarray;
	}
}

function SSAdmin_ClientAreaButtonArray() {
  $buttonarray = array(
   "Refresh Port" => "RefrePort",
  );
  return $buttonarray;
}

function SSAdmin_AdminCustomButtonArray() {
  $buttonarray = array(
   "Reset Traffic" => "RstTraffic",
   "Add Traffic" => "AddTraffic",
   "Refresh Port" => "RefrePort",
  );
  return $buttonarray;
}
/*

      Ending of WHMCS UI related functions

*/

?>
