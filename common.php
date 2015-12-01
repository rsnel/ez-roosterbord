<?
/* Copyright (c) Rik Snel 2011, license GNU AGPLv3 */
ini_set('memory_limit', '256M');

require_once('mdb2_utils.php');

// select configfile on the basis of the value of $_SERVER['EZ_ROOSTERBORD_CONFIG_KEY']
// if not set, use config.php, otherwise use config_VALUE.php
// you can use this, to host multiple instances from the same sourcedirectory

$config_file = 'config.php'; // default config file
$config_key = isset($_SERVER['EZ_ROOSTERBORD_CONFIG_KEY'])?$_SERVER['EZ_ROOSTERBORD_CONFIG_KEY']:'';
if ($config_key != '') $config_file = 'config_'.$config_key.'.php';

// logging depends on values in the config file, so we report the error 'by hand'
if (!file_exists($config_file)) { 
	echo("error: configfile $config_file does not exist, create it by copying and modifying config.php.test");
	exit;
}

require_once($config_file);

// controleer of alle verplichte velden in de configfile aanwezig zijn
foreach (array('UPLOAD_SECRET', 'DSN', 'DATADIR', 'LOGFILE', 'TIMEZONE',
		'INTERNAL_IPS', 'SCHOOL_VOLUIT', 'SCHOOL_AFKORTING',
		'ZERMELO_CATEGORY_IGNORE', 'ZERMELO_GROUP_IGNORE', 'ZERMELO_ENCODING') as $key) 
	if (!isset($config[$key])) {
		echo("mandatory key $key is missing in $config_file");
		exit;
	}

if (!is_writable(config('DATADIR'))) {
	echo(config('DATADIR').' cannot be written to by the webserver');
	exit;
}

date_default_timezone_set(config('TIMEZONE'));

// we assume all config info is there, for missing configuration items in the database (table config)
// defaults are provided

define('LEERLING', 1);
define('DOCENT', 2);
define('LOKAAL', 3);
define('LESGROEP', 4);
define('STAMKLAS', 5);
define('VAK', 6);
define('CATEGORIE',7);

$config_info = array(
	'ENABLE_TEST_WARNING' => '0',
	'HIDE_STUDENTS' => '0',
	'HIDE_ROOMS' => '0',
	'HIDE_ROOMS_SINCE_WEEK_ID' => '0',
	'SCHOOLJAAR_LONG' => '2015/2016',
	'SHOWHIDE_STUDENTNAMES' => '0',
	'DISABLE_WIJZIGINGEN' => '0',
	'KLASSENBOEK_URL' => 'false',
	'IGNORE_BEFORE_DOT' => '0',
	'VAKMATCH' => 'vakmatch_default',
	'CLEANUP_EXTRA' => 'false',
	'SHOW_TEACHERNAMES' => '0',
	'MAX_LESUUR' => 9
);

// get config from database and set all unconfigured items to default values
function get_config() {
	global $config_info;

	$config_local =  mdb2_all_assoc_rekey("SELECT config_key, config_value FROM config");

	foreach ($config_info as $key => $value) {
		if (!isset($config_local[$key])) {
		       	$config_local[$key] = $value;
			mdb2_exec("INSERT INTO config ( config_key, config_value ) VALUES ( '%q', '%q' )", $key, $value);
		}
	}

	return $config_local;
}

function config($key) {
	static $config_static;
	global $config;

	// UPLOAD_SECRET has a special meaning when empty
	if ($key == 'UPLOAD_SECRET' &&
	       		(!isset($config['UPLOAD_SECRET']) || $config['UPLOAD_SECRET'] == ''))
		fatal_error('toegang voor roostermakers is niet geconfigureerd');

	// is this key in the static config?
	// these values can be accessed without database access
	if (isset($config[$key])) return $config[$key];

	// nope, so look in the DB
	if (!isset($config_static)) $config_static = get_config();

	if (!isset($config_static[$key])) fatal_error("Config key $key is not set?!?!");

	/*
	echo('<pre>');
	print_r($config_static);
	echo('</pre>');
	exit;
	 */

	return $config_static[$key];
}

function check_roostermaker($secret) {
	if ($secret != config('UPLOAD_SECRET')) fatal_error('deze pagina is alleen toegankelijk voor de roostermakers');
}

function cidr_match($ip, $range) {
//	echo("cidr_match $ip $range");
	list ($subnet, $bits) = array_pad(explode('/', $range), 2, 0);

	// no /, so $range is a single IP address
	if (!$bits) {
		if ($ip == $range) return true;
		else return false;
	}

	$ip = ip2long($ip);
	if ($ip === false) return false;

	$subnet = ip2long($subnet);
	if ($subnet == false) fatal_error('invalid IP address in range '.$range);

	$mask = -1 << (32 - $bits);
	if ($subnet&$mask != $subnet) fatal_error('subnet in range ('.$range.') incorrecly arranged');
	return ($ip & $mask) == $subnet;
}

// van binnen school mogen we de namen van de leerlingen laten zien
// van buiten mag dat niet, deze functie moet detecteren waar het request
// vandaan komt
function binnen_school() {
	// testing?
	if (config('SHOWHIDE_STUDENTNAMES') > 0) return true;
	else if (config('SHOWHIDE_STUDENTNAMES') < 0) return false;
	
	foreach (config('INTERNAL_IPS') as $range)
		if (cidr_match($_SERVER['REMOTE_ADDR'], $range)) return true;

	return false;
}

function get_default_week($weken) {
	if (count($weken) == 0) return NULL;
	if (count($weken) == 1) return $weken[0];

	// de default week is de eerste lesweek na vrijdag het 9e uur in de vorige lesweek
	$week = date('W', $_SERVER['REQUEST_TIME'] + 2*24*60*60 + (10 + 7*60)*60);
	$year = date('o', $_SERVER['REQUEST_TIME'] + 2*24*60*60 + (10 + 7*60)*60);

	$startweek = $weken[0];
	$eindweek = $weken[count($weken) - 1];

	if ($week >= $startweek && $startweek > 30) {
		if ($year < substr(config('SCHOOLJAAR_LONG'), 0, 4)) {
			$week = $startweek;
		} else if ($year > substr(config('SCHOOLJAAR_LONG'), 0, 4)) {
			$week = $eindweek;
		}
	} else if ($week <= $eindweek && $eindweek < 31) {
		if ($year < substr(config('SCHOOLJAAR_LONG'), 5)) {
			$week = $startweek;
		} else if ($year > substr(config('SCHOOLJAAR_LONG'), 5)) {
			$week = $eindweek;
		}
	} else if ($year <= substr(config('SCHOOLJAAR_LONG'), 0, 4)) {
		$week = $startweek;
	} else {
		$week = $eindweek;
        }

        while (!in_array($week, $weken) && $week < 53) $week++;
        if (!in_array($week, $weken)) $week = 1;
        while (!in_array($week, $weken) && $week < 31) $week++;

	if ($week == 31) $week = $weken[count($weken) - 1];

        return $week;
}

function get_default_day($default_week) {
	$ret = 1;
	if ($default_week < 30) {
                $year = substr(config('SCHOOLJAAR_LONG'), 5);
	} else {
		$year = substr(config('SCHOOLJAAR_LONG'), 0, 4);
	}
	$day_in_week = strtotime(sprintf("$year-01-04 + %d weeks", $default_week - 1));
	$thismonday = $day_in_week - ((date('w', $day_in_week) + 6)%7)*24*60*60 + (50 + 16*60)*60;
	while ($ret < 5 && $_SERVER['REQUEST_TIME'] > $thismonday) {
		$ret++;
		$thismonday += 24*60*60;
	}
	return $ret;
}

/* get rid of slashes produced by a moronic default setting on which
 * some software still relies... */
function cleanup_magic_quotes(&$array) {
	foreach ($array as $key => $value) {
		if (is_array($value)) cleanup_magic_quotes($value);
		else $array[$key] = stripslashes($value);
	}
}

if (get_magic_quotes_gpc()) {
	if (isset($_GET)) cleanup_magic_quotes($_GET);
	if (isset($_POST)) cleanup_magic_quotes($_POST);
}

/*
function logit_r($data, $indent = '') {
	if (is_array($data)) {
		echo('Array ('."\n");
		foreach ($data as $key => $value) {
			echo($indent.'    ['.$key.'] => ');
			logit_r($data, $indent.'    ');
		}
		echo($indent.')'."\n");
	} else echo($data);
}*/

mb_internal_encoding("UTF-8");

function htmlenc($string) {
	return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function fatal_error($string) {
	global $uploadfunction;
	/*
	if ($uploadfunction) {
		ob_end_clean();
		die($string);
		exit;
	}*/
	if (php_sapi_name() != 'cli') {
		header('Content-Type: text/plain');
		header('Content-Disposition: inline; filename=error.txt;');
	}
	logit('fatal error: '.$string);
	exit;
}

// log to user and to logfile
function logit($string) {
	$datetime = date("Y-m-d H:i:s", time());
	if ($fp = fopen(config('DATADIR').config('LOGFILE'), 'a+')) {
		fwrite($fp, $datetime.' '.$string."\n");
		fclose($fp);
	}
	echo($datetime.' '.$string."\n");
}

function fopen_or_fail($filename) {
	if ($fp = fopen($filename, 'r')) return $fp;
	fatal_error('opening file: '.$filename);
}

function read_all_sections(&$sections, $file_name, $ignore_multiple = false) {
	$section_name = 'PREAMBULE';
	$fp = fopen($file_name, 'r');

	if (!$fp) {
		logit("unable to open $file_name");
		$sections = false;
		return;
	}

	$sections = array();
	$sections[$section_name] = array();

	while (!feof($fp)) {
		$line = fgets($fp);
		if ($line === NULL) {
			logit('read error at line '.$no.' of '.$file_name);
			$sections = false;
			return;
		}
		$tmp = explode("\t", trim($line));
		if ($tmp[0] == '########') {
			if (!$ignore_multiple && isset($sections[$tmp[1]])) {
				logit('file '.$file_name.' contains multiple sections with the same name: '.$tmp[1]);
				$sections = false;
				return;
			}
			$section_name = $tmp[1];
			$sections[$section_name] = array();
		} else {
			$sections[$section_name][] = $tmp;
		}
	}

	if (!fclose($fp)) {
		logit('error at fclose of '.$file_name);
		$sections = false;
	}
}

function print_dag($dag) {
	switch ($dag) {
	case 0: return 'zo';
	case 1: return 'ma';
	case 2: return 'di';
	case 3: return 'wo';
	case 4: return 'do';
	case 5: return 'vr';
	case 6: return 'za';
	}
}

function print_rev($time, $rev = 0) {
	return 'r'.$rev.' '.date('W', $time).print_dag(date('w', $time)).date('G:i', $time);
	//return date('W', $time).print_dag(date('w', $time)).date('G:i T', $time);
}

function get_baselink() {
	$pos = strrpos($_SERVER['PHP_SELF'], '/');
	if ($pos === false) fatal_error('geen / gevonden in PHP_SELF');
	return 'http://'.$_SERVER['HTTP_HOST'].substr($_SERVER['PHP_SELF'], 0, $pos + 1);
}

function bbtohtml_rlo($t) {
	$pattern['br'] = '/\[br\]/is';
	$replacement['br'] = '<br>';
	$pattern['b'] = '/\[b\](.+?)\[\/b\]/is';
	$replacement['b'] = '<b>$1</b>';
	$pattern['u'] = '/\[u\](.+?)\[\/u\]/is';
	$replacement['u'] = '<ins>$1</ins>';
	$pattern['i'] = '/\[i\](.+?)\[\/i\]/is';
	$replacement['i'] = '$1';
	$pattern['doc'] = '/\[doc\](.+?)\[\/doc\]/is';
	$replacement['doc'] = '';
	return preg_replace($pattern, $replacement, $t);
}

function bbtohtml($t) {
	$pattern['url'] = '/\[url\=(.+?)\](.+?)\[\/url\]/is';
	$replacement['url'] = '<a href="$1">$2</a>';
	$pattern['lf'] = '/\r\n/is';
	$replacement['lf'] = '<br>';
	$pattern['b'] = '/\[b\](.+?)\[\/b\]/is';
	$replacement['b'] = '<b>$1</b>';
	$pattern['i'] = '/\[i\](.+?)\[\/i\]/is';
	$replacement['i'] = '<i>$1</i>';
	return preg_replace($pattern, $replacement, $t);
}

function htmltobb($t) {
	$pattern['lf'] = '/<br>/is';
	$replacement['lf'] = "\r\n";
	$pattern['url'] = '/<a href="(.+?)">(.+?)<\/a>/is';
	$replacement['url'] = '[url=$1]$2[/url]';
	$pattern['b'] = '/<b>(.+?)<\/b>/is';
	$replacement['b'] = '[b]$1[/b]';
	$pattern['i'] = '/<i>(.+?)<\/i>/is';
	$replacement['i'] = '[i]$1[/i]';
	return preg_replace($pattern, $replacement, $t);
}

function lock_release() {
	// we can only release a lock if it is ours, so we check by including the PID
	mdb2_exec('DELETE FROM locking WHERE locking_id = 0 AND locking_pid = '.getmypid());
}

function lock_acquire($string, $randid) {
	do {
		$lock = mdb2_single_assoc("SELECT * FROM locking WHERE locking_id = 0");
		if ($lock === NULL) {
			$PID = getmypid();
			$time = time();
			if (mdb2_exec_error(MDB2_ERROR_CONSTRAINT, <<<EOQ
INSERT INTO locking ( locking_id, locking_pid, locking_status, locking_last_timestamp, locking_randid )
VALUES ( 0, $PID, '%q', $time, '%q' )
EOQ
, $string, $randid)) return 1; // we hebben een lock!
			continue;
		}       

		// meh, er staat een lock, kijken hoe oud de lock is
                $oud = time() - $lock['locking_last_timestamp'];
		if ($oud < 120) return 0; // we krijgen geen lock, de oude is nog actueel
		
                // de lock is te oud om nog actief te zijn, we wissen de lock en proberen opnieuw een lock te verkrijgen
		mdb2_exec('DELETE FROM locking WHERE locking_id = 0');
	 } while (1);
}

function lock_renew($string) {
	// we can only renew a lock if it is ours, so we check by including the PID
	mdb2_exec("UPDATE locking SET locking_status = '%q', locking_last_timestamp = ".time()." WHERE locking_id = 0 AND locking_pid = ".getmypid(), $string);
}

//
// functions to read udmz file
//

function fix_charset_whitespace($string) {
	return iconv(config('ZERMELO_ENCODING'), 'UTF-8', trim($string));
}

function custom_explode($line) {
	return array_map('fix_charset_whitespace', explode("\t", trim($line)));
}

// the first element of the legenda is the uuid, we don't
// care about it's column title
function get_legenda($line) {
	return array_slice(custom_explode($line), 1);
}

function arrayarray(&$dest, $exploded, $val) {
	$next = &$dest[array_shift($exploded)];
	if ($exploded) arrayarray($next, $exploded, $val);
	else $next = $val;
}

function known_section($line, $preambule) {
	if (isset($preambule[trim($line)])) return true;
	else return false;
}

function add_record(&$curr, $legenda, $line) {
	$fields = custom_explode($line);
	$id = array_shift($fields);
	$deficit = count($legenda) - count($fields);
	if ($deficit < 0) fatal_error('deficit in udmz add_record cant be negative, but is?!?!');
	while ($deficit--) $fields[] = NULL;
	$curr[$id] = array_combine($legenda, $fields);
}

function read_udmz_lines($lines) {
	$out = array();
	$title = 'PREAMBULE';
	$curr = array();
	$legenda = get_legenda($lines[0]);

	for ($i = 1; $i < count($lines); $i++) {
		if (trim($lines[$i]) == '########') {
			if (isset($curr)) {
				arrayarray($out, explode('.', $title), $curr);
				unset($curr);
			}
			if (known_section($lines[$i + 1], $out['PREAMBULE'])) {
				$title = trim($lines[$i + 1]);
				$curr = array();
				$legenda = get_legenda($lines[$i+2]);
			}
			$i += 2;
		} else if (isset($curr)) add_record($curr, $legenda, $lines[$i]);
	}

	return $out;
}

// deze functie maakte voorheen gebruik van gzfile(), gzfile() kan
// echter niet werken met regels die langer zijn dan 8192 tekens, dus
// dat gaat mis, nu splitsen we de file 'met de hand'
function read_udmz_file($file) {
	$lines = array();
	$fp = gzopen($file, 'rb');
	if (!$fp) fatal_error("unable to open $file");
	while (!feof($fp)) {
		$line = fgets($fp);
		if (!$line) fatal_error("unable to read from $file");
		$lines[] = $line;
	}
	return read_udmz_lines($lines);
}

?>
