<?php
include 'sms-server.class.php';

$pn= $_POST['pn'];
$txt= $_POST['txt'];

if ($pn){


$sms = new SmsServer;

$resp=$sms->SendSMS($pn, $txt);

if ($resp=="1"){
	echo "sms sent!";
}else{
	echo "error- sms module";
}
}else{
echo "error -no input";
}
?>
