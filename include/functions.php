<?php
function clearSess(){
	global $company, $user, $vpin, $_SESSION;
	$company = NULL;
	$user = NULL;
	$vpin = NULL;
	$_SESSION = array();
	unset($company);
	unset($user);
	unset($vpin);
	unset($_SESSION);
}

function Nhash($p, $len){
	for ($v = $j = 0, $i = $len; $i ; $j = ($j > 19) ? 0 : $j+1){
		$v=(($v=$v+($v%97)*10000+(ord(substr($p, $i---1))<<$j)+$j)>0x80000000)?($v&0xffff000)>>12:$v;
	}

	for ($v = $j = 0, $i = $len; $i ; $j = ($j > 19) ? 0 : $j+1) {
		$i--;
		$v+=($v%97)*10000;
		$pc=ord(substr($p, $i));
		$pc=($pc << $j) + $j;
		$v+=$pc;
		if($v>0x80000000) {
			$v&=0xffff000;
			$v=$v>>12;
		}
	}

	if ($v/100 == 0)
		$v+=($v*($j++))*100;

	if ($v/10000 == 0)
		$v+=($v*($j++))*1000;

	return ($v % 1000000);
}


function  Npassword1($uname, $rdate, $modules, $regnr, $nrusers)
{
	$tbuf = sprintf	("%s%02d.%02d.%02d%s%s%d",
			strtoupper(preg_replace("/[[:punct:] ]+/", "", $uname)),
			(int)$rdate/10000,
			(int)($rdate%10000)/100,
			(int)$rdate%100,
			$modules,
			$regnr,
			$nrusers
	);

	$pwcalc = Nhash($tbuf, strlen($tbuf));

	return $pwcalc;
}