<?php

if (strtolower($req) == "ok" || strtolower($req) == "ja" || strtolower($req) == "okay" || strtolower($req) == "confirma"){
	$answerOk = 0;
	$accountID = getAccountID($uid, $messenger, $accID);
	connectDB();


	$result = mysql_query("SELECT amount, recipient, address, messageID FROM bookedtx WHERE sender = '$accountID'");
	mysql_query("DELETE FROM bookedtx WHERE sender = '$accountID'");
	$rowBooked = mysql_fetch_row($result);
	$messageID = $rowBooked[3];
	
	$resSender = mysql_query("SELECT userID, balance, refHeight FROM user WHERE accountID = '$accountID'");
	$rowSender = mysql_fetch_row($resSender);
	$newBalSender = getNewBalance($rowSender[1], $rowSender[2], $refHeight);
	$shortBalSen = floor($newBalSender*1000)/1000;
	$finBalSender = bcsub($newBalSender,$rowBooked[0],8);
	$resRec = mysql_query("SELECT userID, balance, refHeight, telegram FROM user WHERE accountID = '$rowBooked[1]'");
	$rowRec = mysql_fetch_row($resRec);
	$recipient = strtolower($rowBooked[1]);
	$language = strtolower($req);
	$finBalReceiver;
	$amntSent;
	
	
	if (mysql_num_rows($result) != 1) {
		$answerOk = 1;
	} elseif ($finBalSender < 0 || (!empty($rowBooked[2]) && $finBalSender < 0.01)) {
		$answerOk = 2;
	} elseif (substr($recipient,0,1) == "@") {
		$recDb = substr($recipient, 1);
		$resTwit = mysql_query("SELECT balance, refHeight FROM twitter WHERE twitter_user = '$recDb'");
		$rowTwit = mysql_fetch_row($resTwit);
		$newBalRecTwit = getNewBalance($rowTwit[0], $rowTwit[1], $refHeight);
		$finBalRecTwit = bcdiv($newBalRecTwit + $rowBooked[0],1,8);
		$amntSent = floor($rowBooked[0]*1000)/1000;
		if (empty($rowTwit)) {
			mysql_query("UPDATE user SET balance = $finBalSender, refHeight = $refHeight WHERE accountID = '$accountID'");
			mysql_query("INSERT INTO twitter (twitter_user, balance, refHeight) VALUES ('$recDb', $rowBooked[0], $refHeight)");
			mysql_query("INSERT INTO transactions (messageID, amount, refHeight, sender, recipient) VALUES ('$messageID', $amntSent, $refHeight, '$accountID', '$recDb')");
		} else {
			mysql_query("UPDATE user SET balance = $finBalSender, refHeight = $refHeight WHERE accountID = '$accountID'");
			mysql_query("UPDATE twitter SET balance = $finBalRecTwit, refHeight = $refHeight WHERE twitter_user = '$recDb'");
			mysql_query("INSERT INTO transactions (messageID, amount, refHeight, sender, recipient) VALUES ('$rowBooked[3]', $amntSend, $refHeight, '$accountID', '$recDb')");
		}
		$answerOk = 4;
	} elseif (empty($rowRec[0]) && empty($rowBooked[2]) && substr($messageID,0,3) != "pay") {
		$answerOk = 3;
	} elseif (substr(strtolower($messageID),0,3) == "pay") {
		$resBookPay = mysql_query("SELECT merchantID, amount, url FROM bookedpay WHERE paymentID = '$messageID'");
		$rowBookPay = mysql_fetch_row($resBookPay);
		$amount = $rowBookPay[1];
		$merchantID = $rowBookPay[0];
		$urlPay = $rowBookPay[2];
		$recipient = $merchantID;
		$resMerchant = mysql_query("SELECT balance, refHeight FROM merchant WHERE chatID = '$merchantID'");
		$rowMer = mysql_fetch_row($resMerchant);
		$newBalReceiver = getNewBalance($rowMer[0], $rowMer[1], $refHeight);
		$finBalReceiver = bcdiv($newBalReceiver + $amount,1,8);
		$amntSent = floor($rowBookPay[1]*1000)/1000;
		mysql_query("INSERT INTO transactions (messageID, amount, refHeight, sender, recipient) VALUES ('$messageID', $amntSent, $refHeight, '$accountID', '$merchantID')");
		mysql_query("UPDATE user SET balance = $finBalSender, refHeight = $refHeight WHERE accountID = '$accountID'");
		mysql_query("UPDATE merchant SET balance = $finBalReceiver, refHeight = $refHeight WHERE chatID = '$merchantID'");
		mysql_query("DELETE FROM bookedpay WHERE paymentID = '$messageID'");
		file_get_contents("$urlPay?paymentID=$messageID&executed=true");
		$answerOk = 4;
		$telegramRecID = $merchantID;
		$answerRec = 0;
	} elseif (empty($rowBooked[2])) {
		$newBalReceiver = getNewBalance($rowRec[1], $rowRec[2], $refHeight);
		$finBalReceiver = bcdiv($newBalReceiver + $rowBooked[0],1,8);
		$amntSent = floor($rowBooked[0]*1000)/1000;
		mysql_query("INSERT INTO transactions (messageID, amount, refHeight, sender, recipient) VALUES ('$rowBooked[3]', $amntSent, $refHeight, '$accountID', '$rowBooked[1]')");
		mysql_query("UPDATE user SET balance = $finBalSender, refHeight = $refHeight WHERE accountID = '$accountID'");
		mysql_query("UPDATE user SET balance = $finBalReceiver, refHeight = $refHeight WHERE accountID = '$rowBooked[1]'");
		$answerOk = 4;
		$uidRec = $rowRec[0];
		$telegramRecID = $rowRec[3];
		$answerRec = 0;
	} else {
		$amount = $rowBooked[0];
		$arrived = send_request($accountID, $refHeight, $rowBooked[2], $amount, $mid);
		$error = $arrived->error;
		$txid = $arrived->message;
		$amount = bcdiv($amount, 1,3);
		$balSenderWfee = $finBalSender - 0.01;
		if (!empty($txid)) {
			mysql_query("INSERT INTO transactions (messageID, amount, refHeight, sender, address) VALUES ('$rowBooked[3]', $amount, $refHeight, '$accountID', '$rowBooked[2]')");
			mysql_query("UPDATE user SET balance = $balSenderWfee, refHeight = $refHeight WHERE accountID = '$accountID'");
			mysql_query("DELETE FROM bookedtx WHERE sender = '$accountID'");
			$answerOk = 5;
		} elseif (file_exists("withdrawonhold.txt")) {
			$answer = 6;
		} elseif (empty($error) && empty($txid)) {
			mysql_query("INSERT INTO transactions (messageID, amount, refHeight, sender, address) VALUES ('$rowBooked[3]', $amount, $refHeight, '$accountID', '$rowBooked[2]')");
			mysql_query("UPDATE user SET balance = $balSenderWfee, refHeight = $refHeight WHERE accountID = '$accountID'");
			mysql_query("DELETE FROM bookedtx WHERE sender = '$accountID'");
			$answerOk = 6;
			file_put_contents("withdrawonhold.txt", "Facebook; Sender: " . $accountID . " Address: " . $address . " " . $amount);
			file_get_contents("https://api.telegram.org/telegramBot/sendmessage?chat_id=123456789&text=ServerDown");
		} elseif (!empty($error)) {
			$answerOk = 7;
		} else { 
			$answerOk = 8;
		}
	}
	
	switch ($answerOk) {
		case 0:
			switch ($language) {
				case "ok": $answer = "There are no open transactions at the moment, please create a new transaction."; break;
				case "ja": $answer = "Sie haben momentan keine offenen Überweisungen, bitte erstellen Sie eine neue."; break;
				case "okay": $answer =  "Al momento non si dispone di un bonifico bancario aperto, si prega di crearne uno nuovo."; break;
				case "confirma": $answer = "Não há transações em aberto no momento, por favor faça uma nova transação.";
			} break;
		case 1:
			switch ($language) {
				case "ok": $answer = "There is no open transaction, type \"send account amount\" to create a transaction."; break;
				case "ja": $answer = "Sie haben momentan keine offene Überweisung, bitte erstellen Sie eine neue."; break;
				case "confirma": $answer = "Al momento non si dispone di un bonifico bancario aperto, si prega di crearne uno nuovo."; break;
				case "confirma": $answer = "Não há nenhuma transação em aberto, por favor escreva \"enviar (numero da conta) (quantia)\" para fazer uma transação.";
			} break;
		case 2:
			switch ($language) {
				case "ok": $answer = "Not enough Solidar (withdrawal fee: 0.01), the abailable balance is: $shortBalSen Solidar. Please use send to create another transaction."; break;
				case "ja": $answer = "Nicht genügend Solidar (Abhebegebühr: 0.01), der verfügbare Betrag ist: $shortBalSen Solidar. Bitte senden sie \"Senden\" um eine neue Überweisung zu erstellen."; break;
				case "okay": $answer = "Non abbastanza Solidar(withdrawal fee: 0.01), l' importo disponibile è: $shortBalSen Solidar. Inviare \"Inviare\" per creare un nuovo trasferimento."; break;
				case "confirma": $answer = "Saldo insuficiente(withdrawal fee: 0.01), seu saldo é: $shortBalSen Solidar. Por favor use enviar para fazer outra transação.";
			} break;
		case 3:
			switch ($language) {
				case "ok": $answer = "The accountID $recipient is not registered. Please check your spelling or contact info@winc-ev.org for questions."; break;
				case "ja": $answer = "Die accountID $recipient ist nicht registriert. Bitte Überprüfen Sie die Schreibweise oder kontaktieren Sie uns info@winc-ev.org bei Fragen."; break;
				case "okay": $answer = "Il contotID $recipient non è registrato. Per qualsiasi domanda, consultare l' ortografia o contattarci all' indirizzo info@winc-ev.org"; break;
				case "confirma": $answer = "Esse número de conta não existe, por favor reveja a conta ou contate info@winc-ev.org para mais dúvidas.";
			} break;
		case 4:
			switch ($language) {
				case "ok": $answer = "You sent $amntSent Solidar to $recipient. Your remaining balance is: $finBalSender."; break;
				case "ja": $answer = "Sie haben $amntSent Solidar an $recipient gesendet. Ihr Restguthaben: $finBalSender."; break;
				case "okay": $answer = "Hai inviato $amntSent Solidar a $recipient. Il saldo rimanente: $finBalSender."; break;
				case "confirma": $answer = "Você enviou $amntSent Solidar para $recipient. Seu saldo agora é: $finBalSender.";
			} 
			if (substr(strtolower($messageID),0,3) == "pay") {$answer = $answer." PaymentID = $messageID"; }
			break;
		case 5:
			switch ($language) {
				case "ok": $answer = "TxID = $txid. Your remaining balance is: $finBalSender."; break;
				case "ja": $answer = "TxID = $txid. Der neue Kontostand ist: $finBalSender."; break;
				case "okay": $answer = "TxID = $txid. Il nuovo saldo del conto è: $finBalSender."; break;
				case "confirma": $answer = "TxID $txid. Seu saldo é: $finBalSender.";
			} break;
		case 6:
			switch ($language) {
				case "ok": $answer = "Our server is offline, please wait \"ok\". If the error persists contact us at info@winc-ev.org"; break;
				case "ja": $answer = "Unser Server ist derzeit offline bitte warten Sie. Falls der Fehler bestehen bleibt kontaktieren Sie uns bitte - info@winc-ev.org"; break;
				case "okay": $answer = "Il nostro server è attualmente offline.. Se l' errore persiste, contattateci - info@winc-ev.org"; break;
				case "confirma": $answer = "Infelizmente nossos servidores estão offline, se o erro persistir, nos contate: info@winc-ev.org.";
			} break;
		case 7:
			switch ($language) {
				case "ok": $answer = "Error: \"$error\", the transaction cannot be send, please create a new transaction. If the problem cannot be solved contact us (info@winc-ev.org) with the error and the MID: \"$rowBooked[3]\"."; break;
				case "ja": $answer = "Fehler: \"$error\", die Überweisung konnte nicht gesendet werden, bitte erstellen Sie eine neue. Falls der Fehler bestehen bleibt kontaktieren Sie uns (info@winc-ev.org) mit der Fehlermeldungldung und der MitteilungsID: \"$rowBooked[3]\""; break;
				case "okay": $answer = "Errore: \"$error\". Non è stato possibile inviare il trasferimento, si prega di crearne uno nuovo. Se l' errore persiste, contattateci (info@winc-ev.org) con il messaggio di errore e il messaggioID: \"$rowBooked[3]\""; break;
				case "confirma": $answer = "Erro: \"$error\" a transação não foi finalizada, por favor faça outra transação. Se o problema persistir, entre em contato (info@winc-ev.org), lembre de mensionar o erro e o MID: \"$rowBooked[3]\".";
			} break;
		case 8:
			$answer =  "I feel a disturbance in the force. That should never happen, please contact info@win-ev.org if the error persist";
			
	}
	
	switch ($answerRec) {
		case 0:
			switch ($language) {
				case "ok": $answerRec = "User $accountID sent you $amntSent Solidar. Your available balance is: $finBalReceiver."; break;
				case "ja": $answerRec = "Der Nutzer $accountID hat Ihnen $amntSent Solidar gesendet. Ihr Kontostand ist: $finBalReceiver."; break;
				case "okay": $answerRec = "L' utente $accountID ha inviato $amntSent Solidar. Il tuo nuovo saldo conto è: $finBalReceiver."; break;
				case "confirma": $answerRec = "O usuario $accountID te enviou $amntSent Solidar. Seu novo saldo é: $finBalReceiver.";
			}
	if (substr(strtolower($messageID),0,3) == "pay") {$answerRec = $answerRec." PaymentID = $messageID"; }
	}
	
	if ($answerPending != "empty") {
		if ($answerOk == 0) {
			$answer = $answerPending;
		} else {
			$answer = $answer.$answerPending;
		}
	}
}

?>
