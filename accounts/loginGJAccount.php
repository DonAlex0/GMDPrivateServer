<?php
//Requesting files
include "../incl/lib/connection.php";
include "../config/email.php";
require "../incl/lib/generatePass.php";
require_once "../incl/lib/exploitPatch.php";
$ep = new exploitPatch();
//Getting IP
if(!empty($_SERVER['HTTP_CLIENT_IP'])){
	$ip = $_SERVER['HTTP_CLIENT_IP'];
}elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
	$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
}else{
	$ip = $_SERVER['REMOTE_ADDR'];
}
//Getting data
$udid = $ep->remove($_POST["udid"]);
$userName = $ep->remove($_POST["userName"]);
$password = $ep->remove($_POST["password"]);
//Registering
$query = $db->prepare("SELECT accountID FROM accounts WHERE userName LIKE :userName");
$query->execute([':userName' => $userName]);
if($query->rowCount() == 0){
	//Error
	exit("-1");
}
$account = $query->fetch();
//Log in limiting
$newtime = time() - 3600;
$query6 = $db->prepare("SELECT count(*) FROM actions WHERE type = '1' AND timestamp > :time AND value2 = :ip");
$query6->execute([':time' => $newtime, ':ip' => $ip]);
if($query6->fetchColumn() > 5){
	exit("-12");
}
//Authenticating
$generatePass = new generatePass();
$pass = $generatePass->isValidUsrname($userName, $password);
if($pass == 1){
	//Getting account ID
	$id = $account["accountID"];
	//Checking if active
	if($emailEnabled == 1){
		$query4 = $db->prepare("SELECT active FROM accounts WHERE accountID = :accID LIMIT 1");
		$query4->execute([':accID' => $id]);
		$result3 = $query4->fetchColumn();
		if($result3 == 0){
			//Error
			exit("-1");
		}
	}
	//Checking if banned
	$query3 = $db->prepare("SELECT isBanned FROM accounts WHERE accountID = :accountID");
	$query3->execute([':accountID' => $id]);
	$result2 = $query3->fetchColumn();
	if($result2 == 1){
		//Banned
		exit("-12");
	}
	$query3 = $db->prepare("SELECT IP FROM bannedips WHERE IP = :IP");
	$query3->execute([':IP' => $ip]);
	$result2 = $query3->fetchColumn();
	if($result2 == $ip){
		//Banned
		exit("-12");
	}
	//Getting user ID
	$query2 = $db->prepare("SELECT userID FROM users WHERE extID = :id");
	$query2->execute([':id' => $id]);
	if($query2->rowCount() > 0){
		$userID = $query2->fetchColumn();
	}else{
		//Registering
		$query = $db->prepare("INSERT INTO users (isRegistered, extID, userName)
		VALUES (1, :id, :userName)");

		$query->execute([':id' => $id, ':userName' => $userName]);
		$userID = $db->lastInsertId();
	}
	//Logging
	$query6 = $db->prepare("INSERT INTO actions (type, value, timestamp, value2) VALUES 
												('2',:username,:time,:ip)");
	$query6->execute([':username' => $userName, ':time' => time(), ':ip' => $ip]);
	//Result
	echo $id.",".$userID;
	if(!is_numeric($udid)){
		$query2 = $db->prepare("SELECT userID FROM users WHERE extID = :udid");
		$query2->execute([':udid' => $udid]);
		$usrid2 = $query2->fetchColumn();
		$query2 = $db->prepare("UPDATE levels SET userID = :userID, extID = :extID WHERE userID = :usrid2");
		$query2->execute([':userID' => $userID, ':extID' => $id, ':usrid2' => $usrid2]);	
	}
}else{
	//Failed
	echo "-1";
	$query6 = $db->prepare("INSERT INTO actions (type, value, timestamp, value2) VALUES 
												('1',:username,:time,:ip)");
	$query6->execute([':username' => $userName, ':time' => time(), ':ip' => $ip]);
}
?>