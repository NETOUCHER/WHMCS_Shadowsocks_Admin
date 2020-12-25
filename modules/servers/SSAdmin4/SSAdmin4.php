<?php

/**
 * @author Gaukas
 * @version 4.0.0	
**/

// READ ME
// This version is not compatible with the former version. The database structure is completely different.

use WHMCS\Database\Capsule;

/* Needs to be enabled after debugging */
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}


function SSAdmin4_MetaData()
{
    return array(
        'DisplayName' => 'ShadowsocksAdmin V4',
        'APIVersion' => '1.1', // Use API Version 1.1
        'RequiresServer' => false, 
    );
}

function SSAdmin4_ConfigOptions() {
	return [
	/****** MySQL Settings	******/
		"db_server" => [					//$db_server = $params['configoption1'];
			"FriendlyName" => "MySQL Server",
			"Type" => "text",
			"Size" => "40",
			"Description" => "MySQL Server for user data",
		],
		"db_port" => [						//$db_port = $params['configoption2'];
			"FriendlyName" => "Port",
			"Type" => "text",
			"Size" => "5",
			"Description" => "MySQL Port",
			"Default" => "3306",
		],
		"db_name" => [						//$db_name = $params['configoption3'];
			"FriendlyName" => "Database",
			"Type" => "text",             
			"Size" => "25",
			"Description" => "DB for user table",
			"Default" => "shadowsocks",
		],
		"db_charset" => [					//$db_charset = $params['configoption4'];
			"FriendlyName" => "Charset",
			"Type" => "text",             
			"Size" => "25",
			"Description" => "Charset for PDO_MySQL Connection",
			"Default" => "utf8",
		],
		"db_user" => [						//$db_user = $params['configoption5'];
			"FriendlyName" => "Username",
			"Type" => "text",             
			"Size" => "25",
			"Description" => "Username for MySQL",
			"Default" => "root",
		],
		"db_pwd" => [						//$db_pwd = $params['configoption6'];
			"FriendlyName" => "Password",
			"Type" => "password",             
			"Size" => "40",
			"Description" => "Password for MySQL",
			"Default" => "PASSWORD",
		],
		/****** Shadowsocks Configuration ******/
		"encrypt" => [						//$ss_encrypt = $params['configoption7'];
			"FriendlyName" => "Encryption", 
			"Type" => "text",               //echo "Encryption: ".$params['configoption7'];
			"Size" => "25",
			"Description" => "Transfer encrypt method",
			"Default" => "CHACHA20",
		],
		"min_port" => [						//$min_port = $params['configoption8'];
			"FriendlyName" => "Starting Port", 
			"Type" => "text",                 //$startport = $params['configoption8']; // Check if legal!
			"Size" => "6",
			"Description" => "Minimal port number to assign to user",
			"Default" => "10000",
		],
		"max_port" => [						//$max_port = $params['configoption9'];
			"FriendlyName" => "Ending Port", 		
			"Type" => "text",                 //$startport = $params['configoption3']; // Check if legal!
			"Size" => "6",
			"Description" => "Maximum port number to assign to user, set to 0 for a unlimited growth.",
			"Default" => "0",
		],
		"traffic" => [						//$traffic = $params['configoption10'];
			"FriendlyName" => "Default Traffic/payment(GB)", 
			"Type" => "text",                         // $traffic = $params['configoption10']*1024*1024*1024; (Remember to convert from gigabytes to bytes.)
			"Size" => "25",
			"Description" => "Default bandwidth if not specified by configurable options",
			"Default" => "10",
		],
		"server_nodes" => [					//$server_nodes = $params['configoption11'];
			"FriendlyName" => "Nodes List", // List of server to show to users
			"Type" => "textarea",
			"Description" => "All the ss-server in this product. Use semicolon in English (;) to devide if more than one.",
		],
	];
}

function SSAdmin4_CreateAccount($params) {
	$serviceid			= $params["serviceid"]; //The unique ID of the product in WHMCS database.
  	$password 			= $params["password"]; //

	$port = SSAdmin4_NextPort($params);
	// Check the returned code.
	if($port == 1)
	{
		return "Ports exceeded port range.";
	}
	elseif($port == 2) {
		return "PDO_MySQL Error.";
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
	// PDO_mysql conn builder
	$db_server = $params['configoption1'];
	$db_port = $params['configoption2'];
	$db_name = $params['configoption3'];
	$db_charset = $params['configoption4'];
	$db_user = $params['configoption5'];
	$db_pwd = $params['configoption6'];
	$dsn = "mysql:host=".$db_server.";dbname=".$db_name.";port=".$db_port.";charset=".$db_charset;
	#$username = $params['serverusername'];
	#$pwd = $params['serverpassword'];
	$attr = array(
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
		$pdo2 = new PDO($dsn, $db_user, $db_pwd, $attr);
		$stmt2 = $pdo2->prepare('SELECT pid FROM user WHERE pid=:serviceid');
		$stmt2->execute(array(':serviceid' => $serviceid));
		$select = $stmt2->fetch(PDO::FETCH_ASSOC);
	}
	catch(PDOException $e){
		return 'Cannot find pid.' . $e->getMessage();
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
		} elseif (strpos($password,'#')!==false) {
			# TODO: Filter out all #'s in $password
			
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
			if (!empty($params['configoption10']))
			{
				$max = $params['configoption10'];
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

function SSAdmin4_TerminateAccount($params) {
	$db_server = $params['configoption1'];
	$db_port = $params['configoption2'];
	$db_name = $params['configoption3'];
	$db_charset = $params['configoption4'];
	$db_user = $params['configoption5'];
	$db_pwd = $params['configoption6'];
	$dsn = "mysql:host=".$db_server.";dbname=".$db_name.";port=".$db_port.";charset=".$db_charset;

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
			$pdo = new PDO($dsn, $db_user, $db_pwd, $attr);
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

function SSAdmin4_SuspendAccount($params) {
	$db_server = $params['configoption1'];
	$db_port = $params['configoption2'];
	$db_name = $params['configoption3'];
	$db_charset = $params['configoption4'];
	$db_user = $params['configoption5'];
	$db_pwd = $params['configoption6'];
	$dsn = "mysql:host=".$db_server.";dbname=".$db_name.";port=".$db_port.";charset=".$db_charset;
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];
	$attr = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);

	$password = md5(time().rand(0,100));
	try{
		$pdo = new PDO($dsn, $db_user, $db_pwd, $attr);
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

function SSAdmin4_UnSuspendAccount($params) {
	$db_server = $params['configoption1'];
	$db_port = $params['configoption2'];
	$db_name = $params['configoption3'];
	$db_charset = $params['configoption4'];
	$db_user = $params['configoption5'];
	$db_pwd = $params['configoption6'];
	$dsn = "mysql:host=".$db_server.";dbname=".$db_name.";port=".$db_port.";charset=".$db_charset;

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
			$pdo = new PDO($dsn, $db_user, $db_pwd, $attr);
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

function SSAdmin4_ChangePassword($params) {
	$db_server = $params['configoption1'];
	$db_port = $params['configoption2'];
	$db_name = $params['configoption3'];
	$db_charset = $params['configoption4'];
	$db_user = $params['configoption5'];
	$db_pwd = $params['configoption6'];
	$dsn = "mysql:host=".$db_server.";dbname=".$db_name.";port=".$db_port.";charset=".$db_charset;
	
	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
			$pdo = new PDO($dsn, $db_user, $db_pwd, $attr);
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

function SSAdmin4_ChangePackage($params) {
	$db_server = $params['configoption1'];
	$db_port = $params['configoption2'];
	$db_name = $params['configoption3'];
	$db_charset = $params['configoption4'];
	$db_user = $params['configoption5'];
	$db_pwd = $params['configoption6'];
	$dsn = "mysql:host=".$db_server.";dbname=".$db_name.";port=".$db_port.";charset=".$db_charset;
	
	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
		$pdo = new PDO($dsn, $db_user, $db_pwd, $attr);
		if(isset($params['configoptions']['Traffic'])) {
          $traffic_GB = explode("G",$params['configoptions']['Traffic'])[0];
          $traffic = $traffic_GB*1024*1048576;
					$stmt = $pdo->prepare("UPDATE user SET transfer_enable=:traffic WHERE pid=:serviceid");
					$stmt->execute(array(':traffic' => $traffic, ':serviceid' => $params['serviceid']));
					return 'success';
		} else {
					if (!empty($params['configoption10'])) {
						$max = $params['configoption10'];
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

function SSAdmin4_Renew($params) {
  $result = SSAdmin4_RstTraffic($params);
  //$result = SSAdmin4_AddTraffic($params);
  switch ($result){
    case 'success':
      return 'success';
    case false:
      return 'Failed to execute PDO SQL query to reset/add traffic. Check the database.';
    default:
      return $result;
	}
}

// The function NextPort will:
//		Return ($port_last_row + 1), if only min_port is set
//		Return minimal unused port, if max_port is set and valid (>min_port).
function SSAdmin4_NextPort($params) {
	$min_port=0;
	$max_port=0;
	// min_port
	if(!isset($params['configoption8']) || $params['configoption8'] == "") {
		$min_port = 10000;
	} else {
		$min_port = $params['configoption3'];
	}
	// max_port
	if(!isset($params['configoption9']) || $params['configoption9'] == "" || $params['configoption9']<= $max_port) {
		$max_port = 0;
	} else {
		$max_port = $params['configoption9'];
	}

	// PDO_mysql conn builder
	$db_server = $params['configoption1'];
	$db_port = $params['configoption2'];
	$db_name = $params['configoption3'];
	$db_charset = $params['configoption4'];
	$db_user = $params['configoption5'];
	$db_pwd = $params['configoption6'];
	$dsn = "mysql:host=".$db_server.";dbname=".$db_name.";port=".$db_port.";charset=".$db_charset;
	#$username = $params['serverusername'];
	#$pwd = $params['serverpassword'];

	
	$attr = array(
	    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

  try{
		$pdo = new PDO($dsn, $db_user, $db_pwd, $attr);
		$stmt = $pdo->query("SELECT port FROM user");
		$select = $stmt->fetch(PDO::FETCH_ASSOC);
		// Check whether there are former services in the table, will return a port as the last port + 1.
		if(!$select == "") // Not empty table
		{	
			// Branch: max_port unset vs set
			if($max_port==0) {
				$stmt2 = $pdo->query("SELECT port FROM user order by port desc limit 1"); //Check the last port
				$last = $stmt2->fetch(PDO::FETCH_ASSOC);
				// Check whether the ports have been used up
				if ($last['port'] >= 65535)
				{
					$result = 1; // Return 1 as a error code. Will deal with it in account creation.
				}	else {
					$result = $last['port']+1; // If not, then use next port.
					return $result;
				}
			} else {
				// When max_port is set, look for the minimal unused port
				$next_port=$min_port;
				while($next_port<=$max_port){
					$stmt2 = $pdo->prepare("SELECT port FROM user WHERE port=:next_port");
					$stmt2->execute(array(':next_port' => $next_port));
					$select2 = $stmt2->fetch(PDO::FETCH_ASSOC);
					if($select2 == "") { // Empty response
						return $next_port; // This port is unused. Thus viable.
					}
					$next_port+=1; // increment by 1 
				}
				if($next_port>$max_port || $next_port>65535){
					return 1;
				}
			}
		} else {
			$result=$start; // If no service in the table, ALWAYS RETURN $min_port
		}
  }
	catch(PDOException $e){
	  $result = 2;
      echo $e->getMessage();
  }
	return $result;
}
//The function RstTraffic will operate the database as setting the upload and download traffic to zero.
function SSAdmin4_RstTraffic($params) {
	$db_server = $params['configoption1'];
	$db_port = $params['configoption2'];
	$db_name = $params['configoption3'];
	$db_charset = $params['configoption4'];
	$db_user = $params['configoption5'];
	$db_pwd = $params['configoption6'];
	$dsn = "mysql:host=".$db_server.";dbname=".$db_name.";port=".$db_port.";charset=".$db_charset;
	
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
	  	if (!empty($params['configoption10']))
	  	{
			$traffic = $params['configoption10']*1024*1048576;
	  	} 	
	  	else
	  	{
		  	$traffic = 53687091200;
	  	}
	}

	try
	{
		$pdo = new PDO($dsn, $db_user, $db_pwd, $attr);
		$stmt = $pdo->prepare("UPDATE user SET `u`='0',`d`='0',`transfer_enable`=:traffic WHERE pid=:serviceid");
		if($stmt->execute(array(':traffic' => $traffic, ':serviceid' => $params['serviceid']))){
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
function SSAdmin4_AddTraffic($params) {
	$db_server = $params['configoption1'];
	$db_port = $params['configoption2'];
	$db_name = $params['configoption3'];
	$db_charset = $params['configoption4'];
	$db_user = $params['configoption5'];
	$db_pwd = $params['configoption6'];
	$dsn = "mysql:host=".$db_server.";dbname=".$db_name.";port=".$db_port.";charset=".$db_charset;
	
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
    	if (!empty($params['configoption10']))
    	{
      		$traffic = $params['configoption10']*1024*1048576;
		} 	
		else
		{
    		$traffic = 53687091200;
    	}
  	}

	try
	{
		$pdo = new PDO($dsn, $db_user, $db_pwd, $attr);
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

function SSAdmin4_RefrePort($params) {
	$db_server = $params['configoption1'];
	$db_port = $params['configoption2'];
	$db_name = $params['configoption3'];
	$db_charset = $params['configoption4'];
	$db_user = $params['configoption5'];
	$db_pwd = $params['configoption6'];
	$dsn = "mysql:host=".$db_server.";dbname=".$db_name.";port=".$db_port.";charset=".$db_charset;
	
	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
		$pdo = new PDO($dsn, $db_user, $db_pwd, $attr);
		$stmt = $pdo->prepare("SELECT u,d,port,passwd,transfer_enable FROM user WHERE pid=:serviceid");
		$stmt->execute(array(':serviceid' => $params['serviceid']));
		$Query = $stmt->fetch(PDO::FETCH_BOTH);
		$u = $Query['u'];
		$d = $Query['d'];
		$passwd = $Query['passwd'];
		$traffic = $Query['transfer_enable'];
		$port = $Query['port'];
    	$nextport = SSAdmin4_NextPort($params);

		if($nextport==0){
			return 'Sorry, next port exceeded.'; //If this is the last port, refuse the refresh request to prevent abuse.
		}

		// if($port==$nextport-1){
		//   return 'Sorry, this is a new port and is not eligible for refresh.'; //If this is the last port, refuse the refresh request to prevent abuse.
		// }

		$terminate=SSAdmin4_TerminateAccount($params);
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
			return 'Failed to refresh port. An error rather than PDO error occurred.';
		}
  	}
  	catch(PDOException $e){
    	die('PDO Error occurred.'.$e->getMessage());
  	}
}
//
//The function to divide every node by the character ';' and output as a node for each line in HTML (devide with <br>)
function SSAdmin4_node($params) {
	$node = $params['configoption11'];
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
function SSAdmin4_link($params) {
	$node = $params['configoption11'];
	$encrypt = $params['configoption7'];

	$db_server = $params['configoption1'];
	$db_port = $params['configoption2'];
	$db_name = $params['configoption3'];
	$db_charset = $params['configoption4'];
	$db_user = $params['configoption5'];
	$db_pwd = $params['configoption6'];
	$dsn = "mysql:host=".$db_server.";dbname=".$db_name.";port=".$db_port.";charset=".$db_charset;
	
	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
		$pdo = new PDO($dsn, $db_user, $db_pwd, $attr);
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

function SSAdmin4_qrcode($params) {
	$node = $params['configoption11'];
	$encrypt = $params['configoption7'];
	
	$db_server = $params['configoption1'];
	$db_port = $params['configoption2'];
	$db_name = $params['configoption3'];
	$db_charset = $params['configoption4'];
	$db_user = $params['configoption5'];
	$db_pwd = $params['configoption6'];
	$dsn = "mysql:host=".$db_server.";dbname=".$db_name.";port=".$db_port.";charset=".$db_charset;

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
		$pdo = new PDO($dsn, $db_user, $db_pwd, $attr);
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
      		$imgs .= '<img src="https://example.com/modules/servers/SSAdmin4/lib/QR_generator/qrcode.php?text='.$output.'" style="align=:center;" />&nbsp;';
		}
	} else {
		$origincode = $encrypt.':'.$password."@".$params['serverip'].':'.$Port;//ss://method[-auth]:password@hostname:port
		$output = 'ss://'.base64_encode($origincode);
    	$imgs = '<img src="https://example.com/modules/servers/SSAdmin4/lib/QR_generator/qrcode.php?text='.$output.'" style="align=:center;" />&nbsp;';
	}
  	//return $origincode;
	//return $output;
  	return $imgs;
}

function SSAdmin4_ClientArea($params) {
	$db_server = $params['configoption1'];
	$db_port = $params['configoption2'];
	$db_name = $params['configoption3'];
	$db_charset = $params['configoption4'];
	$db_user = $params['configoption5'];
	$db_pwd = $params['configoption6'];
	$dsn = "mysql:host=".$db_server.";dbname=".$db_name.";port=".$db_port.";charset=".$db_charset;
	
	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
		$pdo = new PDO($dsn, $db_user, $db_pwd, $attr);
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
		$usagerate = $usage/$traffic*100;
		$freerate = $free/$traffic*100;
		$node = SSAdmin4_node($params);
    	$sslink = SSAdmin4_link($params);
		$ssqr = SSAdmin4_qrcode($params);
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
		<div class=\"col-md-12\">
			<a class=\"btn btn-default\" role=\"button\" data-toggle=\"collapse\" href=\"#collapseExample\" aria-expanded=\"true\" aria-controls=\"collapseExample\">QR code</a>
			<div style=\"margin-top: 10px;\" class=\"collapse in\" id=\"collapseExample\" aria-expanded=\"true\">
  				<div class=\"well\" style=\"text-align:center;word-break:break-all; word-wrap:break-all;\">
    					{$ssqr}
					<br>
    					{$sslink}
  				</div>
			</div>
		</div>
  		<div style=\"text-align:center;margin-top:35px;\" class=\"col-md-3\">
    			<i class=\"fa fa-server fa-4x\"></i>
      			<h3>Server</h3>
      			<kbd>{$node}</kbd>
      		</div>
  		<div style=\"text-align:center;margin-top:35px;\" class=\"col-md-3\">
    			<i class=\"fa fa-crosshairs fa-4x\"></i>
      			<h3>Port</h3>
      			<kbd>{$port}</kbd>
      		</div>
  		<div style=\"text-align:center;margin-top:35px;\" class=\"col-md-3\">
   			<i class=\"fa fa-key fa-4x\"></i>
      			<h3>Password</h3>
      			<kbd>{$password}</kbd>
      		</div>
  		<div style=\"text-align:center;margin-top:35px;\" class=\"col-md-3\">
    			<i class=\"fa fa-lock fa-4x\"></i>
      			<h3>Encryption</h3>
      			<kbd>{$params['configoption7']}</kbd>
      		</div>
	</div>
	<br>
	<div class=\"progress\" style=\"align=center;width=80%;\">
    		<div role=\"progressbar\" aria-valuenow=\"60\" aria-valuemin=\"0\" aria-valuemax=\"100\" class=\"progress-bar progress-bar-warning\" style=\"width: {$usagerate}%;\">
			<span>Used {$usage} GB</span>
    		</div>
   		<div role=\"progressbar\" aria-valuenow=\"60\" aria-valuemin=\"0\" aria-valuemax=\"100\" class=\"progress-bar progress-bar-success\" style=\"width: {$freerate}%;\">
        		<span>Balance {$free} GB</span>
    		</div>
	</div>
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
			<h5>{$params['configoption7']}</h5>

			<hr />

			<h3><strong>Traffic Package</strong></h3>
			<h5>Bandwidth: Unmetered</h5>
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

function SSAdmin4_AdminServicesTabFields($params) {
	$db_server = $params['configoption1'];
	$db_port = $params['configoption2'];
	$db_name = $params['configoption3'];
	$db_charset = $params['configoption4'];
	$db_user = $params['configoption5'];
	$db_pwd = $params['configoption6'];
	$dsn = "mysql:host=".$db_server.";dbname=".$db_name.";port=".$db_port.";charset=".$db_charset;
	
	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	if (isset($params['configoptions']['Traffic'])) {
			$traffic = $params['configoptions']['Traffic']*1024;
	} else if(!empty($params['configoption10'])) {
			$traffic = $params['configoption10']*1024;
	} else {
			$traffic = 1048576;
	}

	try
	{
			$pdo = new PDO($dsn, $db_user, $db_pwd, $attr);
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

function SSAdmin4_ClientAreaButtonArray() {
  $buttonarray = array(
   "Refresh Port" => "RefrePort",
  );
  return $buttonarray;
}

function SSAdmin4_AdminCustomButtonArray() {
  $buttonarray = array(
   "Reset Traffic" => "RstTraffic",
   "Add Traffic" => "AddTraffic",
   "Refresh Port" => "RefrePort",
  );
  return $buttonarray;
}

?>
