<?php
/**
 * @author Gaukas
 * @version 2.0.2
 */
use WHMCS\Database\Capsule;

function shadowsocks_ConfigOptions() {
	$configarray = array(
	"Database" => array("Type" => "text", "Size" => "25"),
	"Encryption" 	=> array("Type" => "text", "Size" => "25"),
	"Init port" 	=> array("Type" => "text", "Size" => "25"),
	"Server List" => array("Type" => "textarea"),
	"Basic Traffic (Gibibytes)" => array("Type" => "textarea")
	);
	return $configarray;
}

function shadowsocks_mysql($params) {
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
	    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

  try{
      //$pdo = new PDO($dsn, $username, $pwd, $attr);
			//if($stmt = $pdo->query("alter table user add pid varchar(50) not null"))
			//{
					$result=true;
			//}
  }
	catch(PDOException $e){
      die('Cannot add.' . $e->getMessage());
  }
	return $result;
}

function shadowsocks_CreateNewPort($params) {
	if(!isset($params['configoption3']) || $params['configoption3'] == "") {
		$start = 9100;
	} else {
		$start = $params['configoption3'];
	}
	$end = 65535;
	shadowsocks_mysql($params);
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
			if(!$select == "")
			{
					$stmt2 = $pdo->query("SELECT port FROM user order by port desc limit 1");

					$lastport = $stmt2->fetch(PDO::FETCH_ASSOC);
					$result = $lastport['port']+1;
					if ($result > $end)
					{
							$result = "Port exceeds the maximum value.";
					}
				}
					else
					{
							$result=$start;
					}
  }
	catch(PDOException $e){
      die('Cannot create new port.' . $e->getMessage());
  }
	return $result;
}

function shadowsocks_CreateAccount($params) {
	$serviceid			= $params["serviceid"]; # Unique ID of the product/service in the WHMCS Database
    $pid 				= $params["pid"]; # Product/Service ID
    $producttype		= $params["producttype"]; # Product Type: hostingaccount, reselleraccount, server or other
    $domain 			= $params["domain"];
  	$username 			= $params["username"];
  	$password 			= $params["password"];
    $clientsdetails 	= $params["clientsdetails"]; # Array of clients details - firstname, lastname, email, country, etc...
    $customfields 		= $params["customfields"]; # Array of custom field values for the product
    $configoptions 		= $params["configoptions"]; # Array of configurable option values for the product

    # Product module option settings from ConfigOptions array above
    $configoption1 		= $params["configoption1"];
    $configoption2 		= $params["configoption2"];

    # Additional variables if the product/service is linked to a server
    $server 			= $params["server"]; # True if linked to a server
    $serverid 			= $params["serverid"];
    $serverip 			= $params["serverip"];
    $serverusername 	= $params["serverusername"];
    $serverpassword		= $params["serverpassword"];
    $serveraccesshash 	= $params["serveraccesshash"];
    $serversecure 		= $params["serversecure"]; # If set, SSL Mode is enabled in the server config

		$pdo = Capsule::connection()->getPdo();
		$pdo->beginTransaction();

		try {
		    $stmt = $pdo->query("SELECT username FROM tbladmins");
				$adminusername = $stmt->fetch(PDO::FETCH_ASSOC);
		    $pdo->commit();
		} catch (\Exception $e) {
		    $result="Got error when trying to get adminusername {$e->getMessage()}";
		    $pdo->rollBack();
		}
		$port = shadowsocks_CreateNewPort($params);

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
	      die('Cannot find pid.' . $e->getMessage());
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
						}
				 catch (Exception $e)
				 {
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
						}
				catch (\Exception $e)  {
    				echo "Password update failed.Bad Capsule function. {$e->getMessage()}";
						}
				$password = $params["customfields"]['password'];
				}
				//Create Account

				if(isset($params['configoptions']['traffic'])) {
					$traffic = $params['configoptions']['traffic']*1024*1048576;
					$stmt3 = $pdo2->prepare("INSERT INTO user(pid,passwd,port,transfer_enable) VALUES (:serviceid,:password,:port,:traffic)");
					if($stmt3->execute(array(':serviceid'=>$params['serviceid'], ':password'=>$password, ':port'=>$port, ':traffic'=>$traffic)))
					{
						$result = 'success';
					}
					else {
						$result='Error during CreatingAccount-Inserting into user';
					}
			  } else {
						if (!empty($params['configoption5']))
						{
								$max = $params['configoption5'];
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
								$result = 'Error during CreatingAccount-Inserting into user';
						}
				}
  	}
  	return $result;
}

function shadowsocks_TerminateAccount($params) {
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
				$result = 'Error. Cloud not Terminate this Account.';
			}
	}
	catch(PDOException $e){
			die('Cannot find pid.' . $e->getMessage());
	}
	return $result;
}

function shadowsocks_SuspendAccount($params) {
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



function shadowsocks_UnSuspendAccount($params) {
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

function shadowsocks_ChangePassword($params) {
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

function shadowsocks_ChangePackage($params) {
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
		$pdo = new PDO($dsn, $username, $pwd, $attr);
		if(isset($params['configoptions']['traffic'])) {
					$traffic = $params['configoptions']['traffic']*1024*1048576;
					$stmt = $pdo->prepare("UPDATE user SET transfer_enable=:traffic WHERE pid=:serviceid");
					$stmt->execute(array(':traffic' => $traffic, ':serviceid' => $params['serviceid']));
					return 'success';
		} else {
					if (!empty($params['configoption5'])) {
						$max = $params['configoption5'];
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

function shadowsocks_Renew($params) {
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
		$pdo = new PDO($dsn, $username, $pwd, $attr);
		$stmt = $pdo->prepare('SELECT sum(u+d) FROM user WHERE pid=:serviceid');
		$stmt->execute(array(':serviceid' => $serviceid));
		$Query = $stmt->fetch(PDO::FETCH_ASSOC);
		if($Query[0]!=0)
		{
			$stmt2 = $pdo->prepare("UPDATE user SET u='0',d='0' WHERE pid=:serviceid");
			$stmt2->execute(array(':serviceid' => $params['serviceid']));
			return 'success';
		} else {
			return 'Noneed to refresh.'
		}
	}
	catch(PDOException $e){
		die('Renew failed. ' . $e->getMessage());
	}
}

function shadowsocks_node($params) {
	$node = $params['configoption4'];
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


function shadowsocks_link($params) {
	$node = $params['configoption4'];
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

function shadowsocks_qrcode($params) {
	$node = $params['configoption4'];
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
		die('Select userinfo Failed in SSQrCode' . $e->getMessage());
	}
	/*$mysql = mysql_connect($params['serverip'],$params['serverusername'],$params['serverpassword']);
	$Query = mysql_query("SELECT port,passwd FROM user WHERE pid='".$params['serviceid']."'",$mysql);
	$Query = mysql_fetch_array($Query);*/
  $Port = $Query['port'];
  $password = $Query['passwd'];
	if (!empty($node) || isset($node)) {
		$str = explode(';', $node);
		foreach ($str as $key => $val) {
			$origincode = $encrypt.':'.$password."@".$str[$key].':'.$Port;//ss://method[-auth]:password@hostname:port
			$output = 'ss://'.base64_encode($origincode);
			//QRcode::png($output);
      $imgs .= '<img src="https://example.com/modules/servers/shadowsocks/QR/qrcode.php?text='.$output.'" />&nbsp;';
		}
	} else {
		$origincode = $encrypt.':'.$password."@".$params['serverip'].':'.$Port;//ss://method[-auth]:password@hostname:port
		$output = 'ss://'.base64_encode($origincode);
    $imgs = '<img src="https://example.com/modules/servers/shadowsocks/QR/qrcode.php?text='.$output.'" />&nbsp;';
	}
  //return $origincode;
	//return $output;
  return $imgs;
}

function shadowsocks_RstTraffic($params) {
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
		die('Select userinfo Failed in traffic reset' . $e->getMessage());
	}
}

function shadowsocks_ClientArea($params) {
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

	try
	{
		$pdo = new PDO($dsn, $username, $pwd, $attr);
		$traffic = $params['configoptions']['traffic'];
		$stmt = $pdo->prepare("SELECT sum(u+d),port,passwd,transfer_enable FROM user WHERE pid=:serviceid");
		$stmt->execute(array(':serviceid' => $params['serviceid']));
		$Query = $stmt->fetch(PDO::FETCH_BOTH);
		$Usage = $Query[0] / 1073741824;
    $traffic = $Query['transfer_enable'] / 1073741824;
		$Port = $Query['port'];
		$Free = $traffic  - $Usage;
		$password = $Query['passwd'];
		$traffic = round($traffic,2);
		$Usage = round($Usage,2);
		$Free = round($Free,2);
		$node = shadowsocks_node($params);
    $sslink = shadowsocks_link($params);
		$ssqr = shadowsocks_qrcode($params);
        //debug
        $decodeQuery = json_encode($Query);
	}
	catch(PDOException $e){
			$html='Error in establishing database connection with PDO_MySQL' . $e->getMessage();
			die('PDO Died' . $e->getMessage());
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

			<h3><strong>Server List</strong></h3>
			<h5>{$node}</h5>

			<hr />

			<h3>Service Port</h3>
			<h5>{$Port}</h5>

			<hr />

			<h3>Service Password</h3>
			<h5>{$password}</h5>

			<hr />

			<h3><strong>Encryption</strong></h3>
			<h5>{$params['configoption2']}</h5>

			<hr />

			<h3><strong>Traffic Package</strong></h3>
			<h5>Bandwidth: {$traffic} GB</h5>
			<h5>Used: {$Usage} GB</h5>
			<h5>Balance in current cycle: {$Free} GB</h5>

			<hr />

			<h3><strong>SS-Link</strong></h3>
			<h5>{$sslink}</h5>

			<hr />

			<h3><strong>QR Code</strong></h3>
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
			<h5>{$Port}</h5>

			<hr />

			<h3>Service Password</h3>
			<h5>{$password}</h5>

			<hr />

			<h3><strong>Encryption</strong></h3>
			<h5>{$params['configoption2']}</h5>

			<hr />

			<h3><strong>Traffic Package</strong></h3>
			<h5>Bandwidth: Unlimited</h5>
			<h5>Used: {$Usage}GB</h5>

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

function shadowsocks_AdminServicesTabFields($params) {
	$dsn = "mysql:host=".$params['serverip'].";dbname=".$params['configoption1'].";port=3306;charset=utf8";
	$username = $params['serverusername'];
	$pwd = $params['serverpassword'];

	$attr = array(
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
	);

		//Traffic
		$traffic = null;
		// $traffic = isset($params['configoptions']['traffic']) ? $params['configoptions']['traffic']*1024 : isset($params['configoption5']) ? $params['configoption5']*1024 : 1048576;
		if (isset($params['configoptions']['traffic'])) {
			$traffic = $params['configoptions']['traffic']*1024;
		} else if(!empty($params['configoption5'])) {
			$traffic = $params['configoption5']*1024;
		} else {
			$traffic = 1048576;
		}

		try
		{
			$pdo = new PDO($dsn, $username, $pwd, $attr);
			$stmt = $pdo->prepare("SELECT sum(u+d),port FROM user WHERE pid=:serviceid");
			$stmt->execute(array(':serviceid' => $params['serviceid']));
			$Query = $stmt->fetch(PDO::FETCH_BOTH);
			$Usage = $Query[0]/1048576;
			$Port = $Query['port'];
			//Free
			$Free = $traffic - $Usage;
			//Percentage
			$fieldsarray = array(
			 'Traffic Package' => $traffic.' MB',
			 'Used' => $Usage.' MB',
			 'Balance' => $Free.' MB',
			 'Service port' => $Port,
			);
			return $fieldsarray;
		}
		catch(PDOException $e){
				die('PDO died' . $e->getMessage());
		}


}

function shadowsocks_AdminCustomButtonArray() {
    $buttonarray = array(
   "Reset Traffic" => "RstTraffic",
  );
  return $buttonarray;
}
?>
