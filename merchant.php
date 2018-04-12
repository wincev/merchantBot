<?php

$botToken = "botToken";
$website = "https://api.telegram.org/bot".$botToken;

$update = file_get_contents("php://input");
$input = json_decode($update, TRUE);
$chatID = $input["message"]["chat"]["id"];
$userID = $input["message"]["from"]["id"];
$req = $input["message"]["text"];
$timestamp = $input["message"]["date"] * 1000;
$refHeight = (int) ($timestamp / 600000);
$messenger = "telegram";
$accountID = $input["message"]["chat"]["id"];
$mid = $input["message"]["message_id"].$input["update_id"];

if (substr($req,0,1) == "/") {
	$req = substr($req, 1);
}
if (substr($chatID,0,1) == "-") {
	$chatIDdb = substr($chatID, 1);
}

$answer = "Hello i am your Solidar bot for merchant accounts. Please select \"/\" to see available commands.";

function connectDB() {	
	$link = mysqli_connect("localhost", "user", "password", "db");
	return $link;
}
	
function checkActive ($dbLink, $ID) {
	$knownID = mysqli_query($dbLink, "SELECT userID FROM merchant WHERE chatID = '$ID'");
	if (mysqli_num_rows($knownID) > 0) {
		return true;
	}
	return false;
}

function getNewBalance($balance, $oldTime, $newTime) {
	$dTime = $newTime - $oldTime;
	while ($dTime >= 100000){ 
		$balance = $balance * 0.69249573468584;
		$dTime = $dTime - 100000;
	}
	while ($dTime >= 20000){ 
		$balance = $balance * 0.92914484250231;
		$dTime = $dTime - 20000;
	}
	while ($dTime >= 5000){ 
		$balance = $balance * 0.98179508840687;
		$dTime = $dTime - 5000;
	}
	while ($dTime >= 1000){ 
		$balance = $balance * 0.99633221082889;
		$dTime = $dTime - 1000;
	}
	while ($dTime >= 200){ 
		$balance = $balance * 0.99926536357709;
		$dTime = $dTime - 200;
	}
	while ($dTime >= 50){ 
		$balance = $balance * 0.99981629027658;
		$dTime = $dTime - 50;
	}
	while ($dTime >= 10){ 
		$balance = $balance * 0.99996325535508;
		$dTime = $dTime - 10;
	}
	while ($dTime >= 2){ 
		$balance = $balance * 0.999992650963;
		$dTime = $dTime - 2;
	}
	while ($dTime >= 1){ 
		$balance = $balance * 0.99999632547475;
		$dTime = $dTime - 1;
	}
	
	return $balance;
}

if (strtolower($req) == "info") {
	file_get_contents($website."/sendmessage?chat_id=".$chatID."&text=Hello i am your Solidar bot for merchant accounts. You can add me to group chats for multiple user accounts.");
} else {
	$dbLink = connectDB();
	$check = checkActive($dbLink, $chatID);
	if (strtolower($req) == "activate" || strtolower($req) == "start") {
		if ($check) {
			file_get_contents($website."/sendmessage?chat_id=".$chatID."&text=The account is already active.");
		} else {
			mysqli_query($dbLink, "INSERT INTO merchant (chatID, userID, balance, refHeight) VALUES ('$chatID', $userID, 0, $refHeight)");
			file_get_contents($website."/sendmessage?chat_id=".$chatID."&text=The account is now active.");
			$check = true;
		}
	}
	if (!$check) {
		file_get_contents($website."/sendmessage?chat_id=".$chatID."&text=The account is not active yet, please activate it first.");
	}  elseif(strtolower($req) == "balance") {
		$result = mysqli_query($dbLink, "SELECT balance, refHeight FROM merchant WHERE chatID = '$chatID'");
		$row = mysqli_fetch_row($result);
		$oldBalance = $row[0];
		$oldHeight = $row[1];
		$newBalance = getNewBalance($oldBalance, $oldHeight, $refHeight);
		$newBalance = bcdiv($newBalance,1,8);
		mysqli_query($dbLink, "UPDATE merchant SET balance = $newBalance, refHeight = $refHeight WHERE chatID = $chatID");
		$shortBalance = floor($newBalance*1000)/1000;
		file_get_contents($website."/sendmessage?chat_id=".$chatID."&text=Your remaing balance is: ".$shortBalance);
	} elseif (strtolower($req) == "id") {
		file_get_contents($website."/sendmessage?chat_id=".$chatID."&text=".$chatID);
	} elseif (strtolower($req) == "send") {
		$answer = "Error, your request could not be sequested, please contact info@winc-ev.org.";
		require 'twitterSend.php';
		file_get_contents($website."/sendmessage?chat_id=".$chatID."&text=".$answer);
	} elseif (strtolower($req) == "withdraw") {
		$answer = "Error, your request could not be sequested, please contact info@winc-ev.org.";
		require 'withdraw.php';
		file_get_contents($website."/sendmessage?chat_id=".$chatID."&text=".$answer);
	}
}

?>
