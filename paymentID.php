<?php

$inputUri = explode("?", $_SERVER['REQUEST_URI']); 
$inputMerchant = explode("merchant=",$inputUri[1]);
$merchantID = explode("&", $inputMerchant[1])[0];
$inputAmount = explode("amount=",$inputUri[1]);
$amount = explode("&", $inputAmount[1])[0];
$inputUrl = explode("url=",$inputUri[1]);
$url = explode("&", $inputUrl[1])[0];
$inputID = explode("invoiceid=",$inputUri[1]);
$invoiceID = explode("&", $inputID[1])[0];

$rand = rand(0, 500000);
$time = time();
$paymentID = "pay".substr(sha1($merchantID.$rand.$time), -20);

function connectDB() {	
	$link = mysqli_connect("localhost", "user", "password", "db");
	return $link;
}


if (!is_numeric($merchantID)) {
	$paymentID = $empty;
	$error = "!is_numeric(merchant)";
} elseif ($amount < 0) {
	$paymentID = $empty;
	$error = "amount<0";
} elseif ($amount > 20000000) {
	$paymentID = $empty;
	$error = "amount>MAX_COIN";
} elseif (!is_numeric($amount)) {
	$paymentID = $empty;
	$error = "!is_numeric(amount)";
} elseif (empty($url)) {
	$paymentID = $empty;
	$error = "empty(url)";
} elseif (!empty($merchantID) || !empty($url) || !empty($amount)) {
	$dbLink = connectDB();
	$resMerchant = mysqli_query($dbLink, "SELECT * FROM merchant WHERE chatID = $merchantID");
	if (mysqli_num_rows($resMerchant) != 1) {
		$paymentID = $empty;
		$error = "!found(merchant)";
	} else {
		if (substr(strtolower($url),0,5) != "https") {
			$error = "warning:!https";
		}
		mysqli_query($dbLink, "INSERT INTO bookedpay (paymentID, merchantID, amount, url) VALUES ('$paymentID', '$merchantID', $amount, '$url')");
	}
}

$arr = array('paymentid' => $paymentID,
		'executed' => "false",
		'invoiceid' => $invoiceID,
		'error' => $error);
echo (json_encode($arr));
