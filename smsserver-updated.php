<?php
include 'db.php';
include 'sms-server.class.php';

//Connect to database
$con = dbConnect();
if(!$con)
    {
    die('Could not connect: ' .mysql_error());
    }

$sms = new SmsServer;

$xml=$_POST['xml'];

$pdu=$sms->getvaluebykey($xml,'pdu');

$contant=$pdu;

mysql_query("INSERT INTO sms_incomming VALUES ('".$sender."','".$contant."',".time().");");

mysql_close($con);

?>
