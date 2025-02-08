<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ERROR);
date_default_timezone_set("Europe/London");

require_once('include/db.php');
require_once('include/functions.php');

$actions = array();
$link = mysql_connect($DBHOST , $DBUSER , $DBPASSWORD);
mysql_select_db($DBDATABASE,$link);

mysql_query("SET time_zone = 'Europe/London'");

$firstLogIn = false;

if ($_POST['response'] == "cancel"){
	clearSess();
}

// Get the company if it has been recorded
if (isset($_COOKIE['company'])){
	$company = $_COOKIE['company'];
}

if (isset($_POST['type']) && $_POST['type']=="registration"){
	// Check database for valid code and save to cookie
	$code = trim(strtoupper($_POST['response']));

	mysql_select_db($MSOFT,$link);
	$sql = sprintf("select *, date_format(registered,'%%d-%%m-%%Y') as registered from license");
	$q = mysql_query($sql);
	if (mysql_num_rows($q)>0){
		while ($license = mysql_fetch_array($q)){
			$uname = trim(htmlspecialchars_decode($license['company']));
			$rdate = preg_replace("/[\-]+/", "", $license['registered']);
			$rdate = substr($rdate, 0, 4).substr($rdate, 6, 2);
			$modules = trim(preg_replace("/[ ]+/", "", $license['systems']));
			$regnr = trim($license['License']);
			$nrusers = $license['users'];
				
			$pwcalc = Npassword1($uname, $rdate, $modules, $regnr, $nrusers);

			// echo $pwcalc ."==". $code . '<br/>';

			if ($pwcalc == $code){
				$company = $license['id'];
				setcookie("company", $company);
			}
		}
		// exit;
	}
}

// Get the user
if (isset($_SESSION['user']) && $_SESSION['user'] != ""){
	$user = $_SESSION['user'];
	mysql_select_db($SABE,$link);
	$sql = sprintf("select id from user_messages where user_id='%d' && `read`=0", $user);
	$q = mysql_query($sql);
	$num_messages = mysql_num_rows($q);
}

if (isset($_POST['type']) && $_POST['type']=="user"){
	$user = $_POST['response'];
	mysql_select_db($SABE,$link);
	$sql = sprintf("select code, user_id, vpin, username from user where code='%s'", $user);
	$q = mysql_query($sql);
	if (mysql_num_rows($q)>0){
		$u = mysql_fetch_array($q);
		$user = $u['user_id'];
		if ($u['vpin'] == "") $firstLogIn = true;
		$_SESSION['user'] = $user;
		
		// Add to Log
		$fname = dirname(dirname($_SERVER['DOCUMENT_ROOT']))."/login-log/".$_SERVER['HTTP_HOST'].".txt";
		$log_line = sprintf("%s Intranet %s %s\n", date('d-m-Y H:i'), $u['username'], $_SERVER['REMOTE_ADDR']);
		$fp = fopen($fname, "a+");
		fwrite($fp, $log_line, strlen($log_line));
		fclose($fp);
		
	} else {
		clearSess();
	}
}

// Get the vpin
if (isset($_SESSION['vpin']) && $_SESSION['vpin'] != ""){
	$vpin = $_SESSION['vpin'];
}

if (isset($_POST['type']) && $_POST['type']=="vpin"){
	$entry = $_POST['response'];
	$digits = $_POST['digits'];
	if (strlen($entry)==4){
		
		mysql_select_db($SABE,$link);
		if (strlen($digits)==16){
			$sql = sprintf("SELECT vpin from user WHERE user_id = '%d'", $user);
			$q = mysql_query($sql);
			if ($q && mysql_num_rows($q)>0){
				$u = mysql_fetch_object($q);
				$vpin = $u->vpin;
				$cmp = "";
				for ($i=0; $i<4; $i++){
					$index = hexdec($vpin[$i]);
					$cmp.=$digits[$index];
				}
				
				if ($cmp == $entry){
					$_SESSION['vpin'] = $vpin;
				} else {
					unset($vpin);
				}
			}
		} else {
			// New user, so set vpin here
			$sql = sprintf("update user set vpin='%s' WHERE user_id = '%d'", $entry, $user);
			$q = mysql_query($sql);
			$_SESSION['vpin'] = $entry;
		}
	}
}

// Get the clock action
if (isset($_POST['clock'])){
	$time = explode(":", $_POST['time']);
	$hrs = $time[0];
	$mins = $time[1];
	$day = date('w');
	$week = date('W');
	$year = date('Y');

	mysql_select_db($SABE,$link);


	$onoff = ($_POST['clock'] == "on") ? "start" : "fin";

	// Store pricise times
	if ($hrs<12 || ($hrs == 12 && $mins == 0)){
		// Morning
		$timestamp = sprintf("m_%s='%s:%s'", $onoff, $hrs, $mins);
	}
	if (($hrs>12 && $hrs<17) || ($hrs == 12 && $mins>0) || ($hrs == 17 && $mins == 0)){
		// Afternoon
		$timestamp = sprintf("a_%s='%s:%s'", $onoff, $hrs, $mins);
	}
	if ($hrs>17){
		// Evening
		$timestamp = sprintf("e_%s='%s:%s'", $onoff, $hrs, $mins);
	}

	$sql = sprintf("select id from __time_sheets_precision where user_id=%d && day=%d && year=%d && week=%d", $user, $day, $year, $week);
	$q = mysql_query($sql);
	if (mysql_num_rows($q)>0){
		$r = mysql_fetch_object($q);
		$id = $r->id;
		$sql = sprintf("update __time_sheets_precision set %s where id=%d", $timestamp, $id);
	} else {
		$sql = sprintf("insert into __time_sheets_precision set user_id=%d, day=%d, year='%04d', week='%02d', %s", $user, $day, $year, $week, $timestamp);
	}
	mysql_query($sql);

	// Round to nearest 15min

	$mins = round($mins/15) * 15;
	if ($mins == 60){
		$mins = 0;
		$hrs+=1;
	}
	if ($hrs<12 || ($hrs == 12 && $mins == 0)){
		// Morning
		$timestamp = sprintf("m_%s='%02d:%02d'", $onoff, $hrs, $mins);
	}
	if (($hrs>12 && $hrs<17) || ($hrs == 12 && $mins>0) || ($hrs == 17 && $mins == 0)){
		// Afternoon
		$timestamp = sprintf("a_%s='%02d:%02d'", $onoff, $hrs, $mins);
	}
	if ($hrs>17){
		// Evening
		$timestamp = sprintf("e_%s='%02d:%02d'", $onoff, $hrs, $mins);
	}

	$sql = sprintf("select id from __time_sheets where user_id=%d && day=%d && year=%d && week=%d", $user, $day, $year, $week);
	$q = mysql_query($sql);
	if (mysql_num_rows($q)>0){
		$r = mysql_fetch_object($q);
		$id = $r->id;
		$sql = sprintf("update __time_sheets set %s where id=%d", $timestamp, $id);
	} else {
		$sql = sprintf("insert into __time_sheets set user_id=%d, day=%d, year='%04d', week='%02d', %s", $user, $day, $year, $week, $timestamp);
	}
	mysql_query($sql);

	clearSess();
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en"
	xml:lang="en">
<head>
<title>SABE Intranet</title>
<link rel="stylesheet" type="text/css" href="assets/stylesheet.css">
<script type="text/javascript"
	src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/ui/jquery-ui-1.8.16.custom.min.js"></script>
<script type="text/javascript" src="assets/javascript.js"></script>
</head>
<body>
	<div id="clock"></div>
	<form id="login" action="" method="post">
		<?php
		if (!$company){
			// enter license code
			?>
		<div class="container clear">

			<table cellpadding="20" class="keypad centre registration">
				<tr>
					<td>1</td>
					<td>2</td>
					<td>3</td>
				</tr>
				<tr>
					<td>4</td>
					<td>5</td>
					<td>6</td>
				</tr>
				<tr>
					<td>7</td>
					<td>8</td>
					<td>9</td>
				</tr>
				<tr>
					<td class="small clear">Clear</td>
					<td>0</td>
					<td class="small enter">Enter</td>
				</tr>
				<tr>
					<td colspan="3">Cancel</td>
				</tr>
			</table>
		</div>

		<p class="clear">Use the keypad to enter the registration code</p>
		<input type="hidden" name="type" value="registration" />
		<input type="text" name="response" value=""
			style="background: #000; text-align: center; border: 0; color: #fff"
			disabled="disabled" />

		<?php		
		}
		if ($company && !isset($user)){
			// choose user
			?>
		<div class="container clear">

			<table cellpadding="20" class="keypad centre user">
				<tr>
					<td>1</td>
					<td>2</td>
					<td>3</td>
				</tr>
				<tr>
					<td>4</td>
					<td>5</td>
					<td>6</td>
				</tr>
				<tr>
					<td>7</td>
					<td>8</td>
					<td>9</td>
				</tr>
				<tr>
					<td class="small clear">Clear</td>
					<td>0</td>
					<td class="small enter">Enter</td>
				</tr>
				<tr>
					<td colspan="3">Cancel</td>
				</tr>
			</table>
		</div>

		<p class="clear">Use the keypad to enter your user code</p>
		<input type="hidden" name="type" value="user" />
		<input type="text" name="response" value=""
			style="background: #000; text-align: center; border: 0; color: #fff"
			disabled="disabled" />


		<?php		
		}
		if ($company && ($user>0) && !isset($vpin) && $firstLogIn === false){
			// enter vpin
			?>
		<div class="container clear">
			<table cellpadding="20" class="v-pin">
				<tr>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
				</tr>
				<tr>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
				</tr>
				<tr>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
				</tr>
				<tr>
					<td></td>
					<td></td>
					<td></td>
					<td></td>
				</tr>
			</table>

			<table cellpadding="20" class="keypad v-pin">
				<tr>
					<td>1</td>
					<td>2</td>
					<td>3</td>
				</tr>
				<tr>
					<td>4</td>
					<td>5</td>
					<td>6</td>
				</tr>
				<tr>
					<td>7</td>
					<td>8</td>
					<td>9</td>
				</tr>
				<tr>
					<td class="small clear">Clear</td>
					<td>0</td>
					<td class="small enter">Enter</td>
				</tr>
				<tr>
					<td colspan="3">Cancel</td>
				</tr>
			</table>
		</div>

		<p class="clear">Use the keypad to enter your pin number</p>
		<input type="hidden" name="type" value="vpin" />
		<input type="password" name="response" value=""
			style="background: #000; text-align: center; border: 0; color: #fff"
			disabled="disabled" />
		<input type="hidden" name="digits" value="" />

		<?php		
		}
		
		if ($company && ($user>0) && !isset($vpin) && $firstLogIn === true){
			// create vpin
			?>
				<div class="container clear">
					<table cellpadding="20" class="v-pin chooser centre">
						<tr>
							<td></td>
							<td></td>
							<td></td>
							<td></td>
						</tr>
						<tr>
							<td></td>
							<td></td>
							<td></td>
							<td></td>
						</tr>
						<tr>
							<td></td>
							<td></td>
							<td></td>
							<td></td>
						</tr>
						<tr>
							<td></td>
							<td></td>
							<td></td>
							<td></td>
						</tr>
					</table>
				
	
				<p class="clear">Select 4 boxes in the grid in sequence.<br />The position and order will be your pin.</p>
				<input type="hidden" name="type" value="vpin" />
				<input type="hidden" name="response" value="" />
				<div class="keypad">
				<a class="button">Clear</a>
				<a class="button">Enter</a>
				<a class="button">Cancel</a>
				</div>
				<?php		
		}
				
		if ($company && ($user>0) && (isset($vpin))){
			// Menu
			$sql = sprintf("select webnote_name from user where user_id='%d'", $user);
			$u = mysql_query($sql);
			$u = mysql_fetch_object($u);
			
			?>
		<ol>
			<li><a href="#" onclick="logOut();">Log Out</a></li>
			<li><a href="/clockonoff.php">Clock On/Off</a></li>
			<li><a href="#absence" onclick="showDialog('absence')">Absence</a></li>
			<li><a href="/heating.php">Heating Controls</a></li>
			<li><a href="/jobs.php">Job Times</a></li>
			<li><a href="#timeoff" onclick="showDialog('timeoff')">Apply for time off</a></li>
			<li><a href="#webnotes" onclick="showDialog('webnotes', 'http://www.camwheat.com/webnote/<?php echo $u->webnote_name;?>')">Webnotes</a></li>
			<li><a href="#notices" onclick="showDialog('notices')">Notice Board</a></li>
			<?php
				if ($num_messages>0) 
					printf('<li><a href="#messages" onclick="showDialog(\'messages\')">Messages <span style="color:red">(%d)</span></a></li>', $num_messages);
			?>
			
		</ol>
		<input type="hidden" name="response" value="" />
		<?php		
		}
		?>

	</form>
</body>
</html>