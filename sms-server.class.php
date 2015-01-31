<?php
/*-------------------------------------------------------------------------------*/
// Description: php class to communicate with arduino SMS-server.
// File name: sms-server.class.php (Version 1.0)
// Last edit : 26.04.2012 By: Itai Arbel.
/*
//-----------------------------------------------------------------------------------------------------------//
public function SetIP($ip) - set manually sms server remote ip, if not - use the default ip $RemoteIP;
	e.g:
		$sms->SetIP('192.168.1.101');
	or
		$sms->SetIP('http://dynamicdns.no-ip.org');
//-----------------------------------------------------------------------------------------------------------//
public function SendSMS($phone, $text) - pdu encode the phone number and text and send it with the sms server.
	e.g:
		$sms->SendSMS('972524511865','some UTF-8 message');
//-----------------------------------------------------------------------------------------------------------//
public function pdu_decode($pdu) - decode pdu string recived from sms server and return array of: 'smsc','phone','ts','message'.
	e.g:
		$arr= $sms->pdu_decode($recived-pdu);
			echo 'sender number:'.$arr['phone'];
			echo '</br>';
			echo 'message:'.$arr['message'];
//-----------------------------------------------------------------------------------------------------------//
*/

class SmsServer {

		
		private $RemoteIP = "192.168.1.1"; //default

			public function SetIP($ip){
				$this->RemoteIP=$ip;
			}
	
			public function SendSMS($phone, $text) {
				$enc= $this->pdu_encode($phone,$text);
				$data= $this->Get_URL($this->RemoteIP."?p=".$enc);	
				//echo($this->RemoteIP."?p=".$enc);		
				return $this->getvaluebykey($data,"code");
			}

			public function pdu_decode($pdu){		

			
				$smscl= $this->hex2dec(substr($pdu,0,2))*2;  //smsc length in decimal
				$ret['smsc']= $this->unSemiOct(substr($pdu,4,$smscl-2));  // cut smsc after len, skip '91'
				$recive= substr($pdu,$smscl+2,2);  // cut recive code, recive == 20
				$pnl= $this->hex2dec(substr($pdu,$smscl+4,2));  // cut phone number len, in decimal
				$ret['phone']= $this->unSemiOct(substr($pdu,$smscl+8,$pnl));  // cut smsc after len, skip 91
				$cs= substr($pdu,$smscl+8+$pnl,4);  // cut cs
				//$ret['cs']=$cs;
				
				$ret['ts']= substr($pdu,$smscl+12+$pnl,14);  // cut time stamp, 6 oc = 12 chars
				$msgl= $this->hex2dec(substr($pdu,$smscl+26+$pnl,2))*2;  // get msg length in octant
				
				if ($cs=='0008'){
				$ret['message']= $this->decode16(substr($pdu,$smscl+28+$pnl,$msgl));  // cut msg
				}
				
				if ($cs=='0004'){
				$ret['message']= $this->decode8(substr($pdu,$smscl+28+$pnl,$msgl));  // cut msg
				}
				
				if ($cs=='0000'){
				$ret['message']= $this->decode7(substr($pdu,$smscl+28+$pnl,$msgl));  // cut msg
				}	
				
				return $ret;
			}

			public function getvaluebykey($string, $key){
				$start="<$key>";
				$end="</$key>";  
				$string = " ".$string;
				$ini = strpos($string,$start);
				if ($ini == 0) return "";
				$ini += strlen($start);   
				$len = strpos($string,$end,$ini) - $ini;
				return substr($string,$ini,$len);   
			}      

			private Function Get_URL($url){
				if (function_exists(curl_init)==true){
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL,$url);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					$data=curl_exec($ch);
					curl_close($ch);  
				}else{
						$file = fopen ($url, "r");
						if (!$file) {
								echo "Unable to open remote file.";
								exit;
						}
						while (!feof ($file)){
								$data .= fgets ($file, 64);
						}
						fclose($file);
					}  
				return $data; 
			}
	 
			private function rmv_quote($value){
				if(get_magic_quotes_gpc()){
					$value = stripslashes( $value );
				}else{
					$value = addslashes( $value );
				}
				return $value;
			}
			
			private function pdu_encode($pn,$txt){
				$pdumsg=$this->sms_unicode($txt);
				$pduphone=$this->SemiOct($pn);
				$t="001100";
				$t.=$this->dec2hex(strlen($pduphone));
				$t.="91";
				$t.=$pduphone;
				$t.="0008AA";
				$t.=$this->dec2hex($this->bitlen($pdumsg));
				$t.=$pdumsg;
				return $t;  
			}
  
			private function sms_unicode($message) {
				if (function_exists('iconv')) {
					$latin = @iconv('UTF-8', 'ISO-8859-1', $message);	
					$arr = unpack('H*hex', @iconv('UTF-8', 'UCS-2BE', $message));
					return strtoupper($arr['hex']);
				}
				return FALSE;
			}

			private function bitlen($str){
				return round(strlen($str)/2);
			}

			private function SemiOct($str){
				$t="";
				if (!((strlen($str)-1)%2)){
					$str=$str."F";
				}
				do{
					$t=$t.($this->fliper(substr($str,0,2)));
					$str=  substr($str,2,strlen($str)-2); 
				}while (strlen($str)>=1);
				return strtoupper($t);
			}
			
			private function unSemiOct($str){
				$t="";
				do{
					$t=$t.($this->fliper(substr($str,0,2)));
					$str=  substr($str,2,strlen($str)-2); 
				}while (strlen($str)>=1);
				if (substr($t,strlen($t)-1,1)=='F'){
					$t=substr($t,0,strlen($t)-1);
				}
				return strtoupper($t);
			}

			private function fliper($str){
				$t="";
				do{
					$t=$t.substr($str,strlen($str)-1,1);
					$str= substr($str,0,strlen($str)-1);
				}while (strlen($str)>=1);
				return $t;
			}
	
			private function fliper2($str){
				$t="";
				do{
					$t=$t.substr($str,strlen($str)-2,2);
					$str= substr($str,0,strlen($str)-2);
				}while (strlen($str)>=1);
				return $t;
			}
				

			private function dec2hex($dec){
				$hex=dechex($dec);
				if ($dec<=15){
					$hex="0".$hex;
				}
				return strtoupper($hex);
			}

			private function hex2dec($hex){
				return hexdec($hex);
			}
			
			private function decode16($input){
				$msg="";
				for($i=0;$i<strlen($input);$i=$i+4){
					$hex= substr($input,$i,4);
					$c= $this->dec_to_utf8($this->hex2dec($hex));
					$msg=$msg.$c;
				}
				return $msg;
			}
			
			private function decode8($input){
				$msg="";
				for($i=0;$i<strlen($input);$i=$i+2){
					$hex= substr($input,$i,2);
					$c= $this->dec_to_utf8($this->hex2dec($hex));
					$msg=$msg.$c;
				}
				return $msg;
			}
			
			private function fix0($s){
				for($i=strlen($s)+1;$i<=8;$i=$i+1){
				$s="0".$s;
				}
				
			//echo "[".$s.",".strlen($s)."]";
			return $s;
			
			}
			
			 private function cut0($s){
				return substr($s,strpos($s,"1"));
				}
			
			
			private function decode7($n){
			
					//$n = substr($n,1, strlen($n) - 1);
					
					
					$n = preg_replace('/\s+/', '', $n);
					
			

				//	echo "**".$n."**<br/>";
					
					$n=$this->fliper2($n);		

				//	echo "<".base_convert($n, 16, 2).">";

					
					$bi="";
					
				//	echo "**".$n."**<br/>";
					
					for($i=0;$i<=strlen($n)-1;$i+=2){
						$b=base_convert($n[$i].$n[$i+1], 16, 2);
						

						$bi=$bi.$this->fix0($b);
					//	echo "#".$n[$i].",".$n[$i+1]."--".$this->fix0($b)."#<br/>";
								
					}
					
					
					
					
					$n=$this->cut0($bi);
					
					//$n=$bi;
					
				//	$n="0".$n;
					
					if (strlen($n)%7!=0){
						$n="0".$n;
					}
					
					//$n=substr($n,1,strlen($n)-1);
					
					
					
				//	echo "%".$n."%<br/>";
					
					
				$msg="";
				
					
				for($i=0;$i<strlen($n);$i=$i+7){
					
					
					//$bchr= $this->fix0(substr($n,$i,7));
					$bchr= substr($n,$i,7);
					
					
					
					$bchr= chr(base_convert($bchr, 2, 10));
					$msg=$msg.$bchr;
				}
				return $this->fliper($msg);			
			}
			
			

			
			
			function Convert7BitTo8Bit($string){
	$total = "";
	for($i = 0; $i < strlen($string); ){
		// Get 1st character string, it's 2 character hex
		$X = $string[$i++].$string[$i++];
		// Convert it to binary
		
		
		
		$my7bit = hex2bin($X);
		
		//print "(8bit) ==> $my8bit\n";
		// remove left side of octet, it shall be septet
		// e.g 2A in octet is 00101010 (8 bit), remove most left 0 --> 0101010 (7 bit)
	//	$my8bit = substr($my8bit,1,8);
		//print "(7bit) ==>  $my7bit\n";
        // Concatenate it
		$total = $my7bit.$total;
	}
	// Padding the string
	if(strlen($total) % 8 != 0){
		$p1     = (intval((strlen($total) / 8)) + 1) *  8;
		$total  = str_pad($total,$p1,'0',STR_PAD_LEFT);
	}
	$pad   = 7;
	// Conversion begin
	for($i = strlen($total) - 1; $i >= 0 ; $i--){
		$mypad[$pad--] = $total[$i];
		if($pad < 0 || $i <= 0){
			$pad  = 7;
			$tmp1 = array_reverse($mypad);
			//print_r($tmp1);
			$tmp2 = implode($tmp1);
			$res = binhex($tmp2);
			$result .= "$res";
		}
	}
	return $result;
}
		
			private function dec_to_utf8($decnum) {
				if ($decnum <= 0x7F) return chr($decnum);
				if ($decnum <= 0x7FF) {           // 2 byte UTF8
					$binstr = str_pad(base_convert("$decnum", 10, 2), 11, "0", STR_PAD_LEFT);
					$bs1 = "110" . substr($binstr, 0, 5);
					$bs2 = "10" . substr($binstr, 5, 6);
					$ds1 = base_convert ($bs1, 2, 10);
					$ds2 = base_convert ($bs2, 2, 10);
					return chr($ds1) . chr($ds2);
				}
				if ($decnum <= 0xFFFF) {          // 3 byte UTF8
					$binstr = str_pad(base_convert("$decnum", 10, 2), 16, "0", STR_PAD_LEFT);
					$bs1 = "1110" . substr($binstr, 0, 4);
					$bs2 = "10" . substr($binstr, 4, 6);
					$bs3 = "10" . substr($binstr, 10, 6);
					$ds1 = base_convert ($bs1, 2, 10);
					$ds2 = base_convert ($bs2, 2, 10);
					$ds3 = base_convert ($bs3, 2, 10);
					return chr($ds1) . chr($ds2) . chr($ds3);
				}
				return 'X';    
			}
}
