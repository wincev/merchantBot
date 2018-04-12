<?
if (substr(strtolower($req),0,3) == "pay"){
	$arr = explode(' ', trim($req));
	$arr = array_diff($arr, array(""));
	$accountID = getAccountID($uid, $messenger, $accID);
	connectDB();
	$result = mysql_query("SELECT userID FROM user WHERE accountID = '$accountID'");
	$row = mysql_fetch_row($result);
	$payID = strtolower($arr[0]);
	$language = "en";
	if (!empty($arr[1])) {
		$language = strtolower($arr[1]);
	}
	$dbuserID = $row[0];
	$resultPay = mysql_query("SELECT merchantID, amount FROM bookedpay WHERE paymentID = '$payID'");
	$rowPay = mysql_fetch_row($resultPay);
	$merchantID = $rowPay[0];
	$answerSend = 12345;
	$shortAmount = 0;
	
	if (preg_match('/[^a-f0-9]/', substr($payID,3)) || strtolower(substr($payID,0,3) != "pay")) {
		$answerPay = 0; 
	} elseif (mysql_num_rows($resultPay) != 1) {
		$answerPay = 1;
	} else {
		$amount = $rowPay[1];
		$shortAmount = bcdiv($amount,1,3);
		mysql_query("DELETE FROM bookedtx WHERE sender = '$accountID'");
		mysql_query("INSERT INTO bookedtx (messageID, amount, sender, recipient) VALUES ('$payID', '$amount', '$accountID', '$merchantID')");
		$answerPay = 2;
	}

	// ====>>>>>switch languages.==========>>>>>
	
	switch ($answerPay) {
		case 0:
			switch ($language) {
				case "en":
					$answer = "Please use a payment ID to send Solidar. IDs consist of numbers and abcdef starting with pay. Example: \"pay29098fa3afe65b\"."; break;
				case "de":
					$answer = "Bitte verwenden Sie eine gültige payment ID zum Überweisen. IDs bestehen aus Nummern und abcdef. Beispiel: \"pay29098fa3afe65b de\"."; break;
				case "it":
					$answer = "Por favor use um número de payment válido para enviar Solidars. Ele consiste de números e as letras abcedf. Por exemplo: \"pay29098fa3afe65b it\"."; break;
				case "po":
					$answer = "Si prega di utilizzare un payment ID valido per trasferire denaro. Gli ID sono costituiti da numeri e abcdef. Esempio: \"pay29098fa3afe65b po\".";
			} break;
		case 1:
			switch ($language) {
				case "en": $answer = "The ID $payID is not booked. Please check the spelling. $merchantID"; break;
				case "de": $answer = "Die verkaufs ID $payID ist nicht gebucht. Bitte Überprüfen Sie die Schreibweise oder kontaktieren Sie uns info@winc-ev.org bei Fragen."; break;
				case "it": $answer = "Il spaccio ID $payID non è registrato. Per qualsiasi domanda, consultare il venditore o contattarci all' indirizzo info@winc-ev.org"; break;
				case "po": $answer = "Esse número de venda ID $payID não existe, por favor reveja a ID ou contate info@winc-ev.org para mais dúvidas.";
			} break;
		case 2:
			switch ($language) {
				case "en":
					$answer = "send $shortAmount Solidar to $merchantID? Type \"ok\" to send. To discard, create a new transaction ."; break;
				case "de":
					$answer =  "Überweisen von $shortAmount Solidar an: $merchantID? Senden Sie \"ja\" zum Bestätigen. Zum Verwerfen erstellen Sie bitte eine neue Überweisung."; break;
				case "it":
					$answer = "Trasferimento da $shortAmunt Solidar a: $merchantID? Inviatelo \"okay\" per confermare. Per scartare, creare un nuovo trasferimento."; break;
				case "po":
					$answer = "Deseja enviar $shortAmount Solidar para: $merchantID? Escreva \"confirma\" para confirmar. Para cancelar faça outra transação.";
			} break;
	}
}


?>
