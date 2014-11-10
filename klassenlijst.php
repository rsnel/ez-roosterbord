<? require_once('common.php');

function html_start($collapsed = false) {
	header("Content-Type: text/html; charset=UTF-8"); ?>
<!DOCTYPE HTML>
<html>
<head>
<meta charset="UTF-8">
<link rel="apple-touch-icon" href="apple-touch-icon.png">
<link rel="apple-touch-icon" sizes="76x76" href="apple-touch-icon-76x76.png">
<link rel="apple-touch-icon" sizes="120x120" href="apple-touch-icon-120x120.png">
<link rel="apple-touch-icon" sizes="152x152" href="apple-touch-icon-152x152.png">
<link rel="shortcut icon" href="zermelo_zoom.ico">
<meta name="msapplication-config" content="none">
<title>Klassenlijsten <? echo(config('SCHOOL_AFKORTING').' '.config('SCHOOLJAAR_LONG')) ?></title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/print.css" media="print">
<link rel="stylesheet" href="css/ui-lightness/jquery-ui-1.10.4.custom.min.css">
<script type="text/javascript" src="js/jquery-1.11.1.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.10.4.custom.min.js"></script>
<script type="text/javascript">
//<![CDATA[
$(function(){
	$('#q').focus();
});
//]]>
</script>
</head
<body>
<? if (config('ENABLE_TEST_WARNING')) { ?>
<h1>DIT IS EEN TEST! Er vindt momenteel techisch onderhoud plaats
aan het roosterbord en de onderstaande data klopt dus mogelijk niet!</h1>
<? } ?>
<div id="lijstdiv">
<p><div class="noprint" style="float: left"><form id="search" method="GET" name="search" accept-charset="UTF-8"><input type="submit" value="Zoek:">
<input id="q" size="10" name="q"><? if ($_GET['q'] != '') { if ($entity_type === '') echo(' <span class="error">Zoekterm "'.htmlenc($_GET['q']).'" niet gevonden.</span>');
} ?>
<input name="wk" type="hidden" value="<? if ($safe_week != $default_week) { echo($safe_week); } ?>">
</form>
</div>
<? }

function html_end() {
	/*
	$schooljaar_long = config('SCHOOLJAAR_LONG');
	$year = date('Y', $_SERVER['REQUEST_TIME']);
	if ($year > substr($schooljaar_long, 0, 4)) $rooster_copy = str_replace('/', ', ', $schooljaar_long);
	else $rooster_copy = $year;
	$version_copy = '-'.exec('git describe').' &copy; '.substr(exec('git show -s --format=%ci'), 0, 4).' Rik Snel';
?><p><div id="footer">
<div class="noprint">
Rooster &copy; <? echo($rooster_copy.' '.config('SCHOOL_VOLUIT')) ?>, all rights reserved. Favicon and Touch Icons &copy; 1953 Konrad Jacobs, license <a href="http://creativecommons.org/licenses/by-sa/2.0/de/deed.en">CC-BY-SA-2.0-DE</a><br>
Deze webinterface, <a href="http://ez-roosterbord.nl/">ez-roosterbord</a><? echo($version_copy); ?> &lt;rik@snel.it&gt;. Powered by PHP <? echo(phpversion()); ?>.<br>
Released as <a href="http://www.gnu.org/philosophy/free-sw.html">free software</a> without warranties under <a href="http://www.fsf.org/licensing/licenses/agpl-3.0.html">GNU AGPL v3</a>.<br>
Sourcecode: <code>git clone <a href="https://github.com/rsnel/ez-roosterbord/">https://github.com/rsnel/ez-roosterbord/</a></code>.
</div>
<div class="onlyprint">
Rooster &copy; <? echo($rooster_copy.' '.config('SCHOOL_VOLUIT')) ?>, all rights reserved, ez-roosterbord<? echo($version_copy) ?> is vrije software onder de GNU Affero GPL v3.
</div>
	 */?>
</div>
</div>
</body></html>
<? }

function entity_prevnext($name, $type) {
	if ($type != LOKAAL && $type != DOCENT && $type != STAMKLAS && $type != VAK && $type != CATEGORIE) fatal_error('unsupported prevnext');

	$prev = mdb2_single_val("SELECT MAX(entity_name) FROM entities WHERE entity_name < '%q' AND entity_active IS NOT NULL AND entity_type = $type", $name);
	$next = mdb2_single_val("SELECT MIN(entity_name) FROM entities WHERE entity_name > '%q' AND entity_active IS NOT NULL AND entity_type = $type", $name);

	if ($type == VAK) $name = substr($name, 1);

	return '<span class="noprint">'.make_link_conditional($prev, '&lt;').'</span>'.htmlenc($name).'<span class="noprint">'.make_link_conditional($next, '&gt;').'</span>';
}

$PID = getmypid();

/* een gebruiker wil kennelijk het rooster zien.... */
check_roostermaker($_GET['secret']);

$min_week_id = mdb2_single_val("SELECT MIN(week_id) FROM roosters");
if ($min_week_id) {
	$res = mdb2_query("SELECT week FROM weken WHERE week_id >= $min_week_id ORDER BY week_id");
	$weken = $res->fetchCol();
	$res->free();
} else $weken = array();


/* sanitize the input */
if (!isset($_GET['bw'])) $_GET['bw'] = 'w';
else if ($_GET['bw'] != 'w' && $_GET['bw'] != 'y' && $_GET['bw'] != 'b' && $_GET['bw'] != 'd' && $_GET['bw'] != 'x') $_GET['bw'] = 'w';

$default_week = get_default_week($weken); // calculate default week
$default_day = get_default_day($default_week);

$day_not_given = 0;

$week_not_given = 0;
if (!isset($_GET['wk']) || !in_array($_GET['wk'], $weken)) {
	$_GET['wk'] = $default_week;
	$week_not_given = 1;
}

$safe_week = (int)$_GET['wk'];
$week_index = array_search($safe_week, $weken);
$prev_week = NULL;
$next_week = NULL;
if ($week_index > 0) $prev_week = $weken[$week_index - 1];
if ($week_index < count($weken) - 1) $next_week = $weken[$week_index + 1];
$real_prev_week = $prev_week;
if ($prev_week == $default_week) $prev_week = '';
if ($next_week == $default_week) $next_week = '';


if ($safe_week != $_GET['wk']) fatal_error('sanity check failed');

$link_tail_wowk = '&amp;bw='.$_GET['bw'].'&amp;wk=';
$link_tail_tail = (isset($_GET['debug'])?'&amp;debug':'');

if ($safe_week != $default_week) $link_tail = $link_tail_wowk.$safe_week;
else $link_tail = $link_tail_wowk;

if ($safe_week == $default_week && $_GET['dy'] == $default_day) {
	$link_tail_wody = $link_tail.'&amp;dy=';
	$link_tail_nodebug = '&amp;dy='.$link_tail.'">';
	$link_tail .= '&amp;dy='.$link_tail_tail.'">';
} else {
	$link_tail_wody = $link_tail.'&amp;dy=';
	$link_tail_nodebug = '&amp;dy='.$_GET['dy'].$link_tail.'">';
	$link_tail .= '&amp;dy='.$_GET['dy'].$link_tail_tail.'">';
}

if (!isset($_GET['q'])) $_GET['q'] = '';
else $_GET['q'] = trim($_GET['q']);

$qs = explode(',', $_GET['q']);
//sort($qs);

// zoek week_id van deze week, meest actuele basisrooster, bijbehorende wijzigingen
$week_info = mdb2_single_array("SELECT week_id, ma, di, wo, do, vr FROM weken WHERE week = $safe_week");
if (!$week_info) fatal_error("impossible, week $safe_week bestaat niet in tabel weken?!?!!?");
$week_id = $week_info[0];

$basis = mdb2_single_assoc("SELECT file_id, basis_id, timestamp FROM roosters WHERE week_id <= $week_id AND wijz_id = 0 ORDER BY rooster_id DESC LIMIT 1");
if (!$basis) fatal_error("impossible: toch geen basisrooster in deze week?!?!?!?");
	
//print_r($basis);
//print_r($wijz);

$result = mdb2_query("SELECT entity_type, entity_id, entity_name FROM entities WHERE entity_name = '%q'", trim($qs[0]));

if (!($target = $result->fetchRow()) ||
	($target[0] == LOKAAL && config('HIDE_ROOMS')) ||
	($target[0] == LEERLING && config('HIDE_STUDENTS'))
) { // niet gevonden
	$safe_id = '';
	$entity_type = '';
	$entity_name = '';
	$type = '';
	$res_klas = mdb2_query("SELECT entity_name FROM entities WHERE entity_type = ".STAMKLAS.' AND entity_active IS NOT NULL ORDER BY entity_name');
	$res_groep = mdb2_query("SELECT entity_name FROM entities WHERE entity_type = ".LESGROEP.' AND entity_active IS NOT NULL ORDER BY entity_name');


	goto cont;
} else {
	$entity_type = $target[0];
	$entity_name = $target[2];

	$safe_id = (int)$target[1]; // is onze entity_id een integer?
	if ($safe_id != $target[1]) fatal_error('sanity check failed'); // nee?!?!?
	
}

if ($result->fetchRow()) fatal_error('impossibe'); // meer dan een resultaat?, kan niet!

$entity_multiple = 0;

while (array_shift($qs) && count($qs)) {
	// we hebben extra dingen met komma's
	$result = mdb2_query("SELECT entity_type, entity_id, entity_name FROM entities WHERE entity_name = '%q'", trim($qs[0]));
	if (($target = $result->fetchRow()) && ($entity_type == $target[0] ||
		( $entity_type == LESGROEP && $target[0] == STAMKLAS ) ||
		( $entity_type == STAMKLAS && $target[0] == LESGROEP ))) { // gevonden
		$safe_id .= ','.$target[1];
		$entity_name .= ','.$target[2];
		$entity_multiple = 1;
	}
}

// sorteer de gekozen entities als het er meer zijn
if ($entity_multiple) {
	$tmp = explode(',', $entity_name);
	sort($tmp);
	$entity_name = implode(',', $tmp);
}

//print_r($basis);
//echo($entity_name.' '.$safe_id);
/* basis_id, prefix, postfix, own, dag, uur, vak, wijz_id */
//exit;

$result2 = mdb2_query(<<<EOT
SELECT entities.entity_name leerlingnummer, name, GROUP_CONCAT(stamklassen.entity_name), IFNULL(CONCAT(lijst.dossier, ' ', lijst.extra), '-') bijz, surname, firstname, prefix
FROM grp2ppl
JOIN entities ON entity_id = ppl_id
JOIN names ON names.entity_id = entities.entity_id
JOIN grp2ppl AS grp2ppl_back ON grp2ppl_back.ppl_id = grp2ppl.ppl_id AND grp2ppl_back.file_id_basis = grp2ppl.file_id_basis
JOIN entities AS stamklassen ON grp2ppl_back.lesgroep_id = stamklassen.entity_id AND stamklassen.entity_type = %i
LEFT JOIN llwwmi.lijst ON lijst.llnr = entities.entity_name
WHERE grp2ppl.file_id_basis = {$basis['file_id']} AND grp2ppl.lesgroep_id IN ( $safe_id )
GROUP BY entities.entity_id
ORDER BY surname, firstname, prefix
EOT
, STAMKLAS);

cont:

function make_link2($target, $text = NULL) {
	global $link_tail;
	return '<a rel="external" href="?'.'q='.urlencode($target).$link_tail.($text?$text:htmlenc($target)).'</a>';
}

function make_link($target, $text = NULL, $day = NULL) {
	global $link_tail, $link_tail_wody;
	if (!$day || $day == $_GET['dy']) return '<a data-transition="flip" href="?'.(isset($_GET['m'])?'m&amp;':'').'q='.urlencode($target).$link_tail.($text?$text:htmlenc($target)).'</a>';
	else return '<a data-transition="flow"'.(($day < $_GET['dy'])?' data-direction="reverse"':'').' href="?'.(isset($_GET['m'])?'m&amp;':'').'q='.urlencode($target).$link_tail_wody.$day.'">'.($text?$text:htmlenc($target)).'</a>';
}

html_start($entity_type !== ''); ?><div class="clear" style="padding-top: 1px;">
<?
if ($entity_type === '') { 
	echo('<p>Selecteer hieronder een groep');
	echo('<p>Klassen:');
	while ($row = $res_klas->fetchRow()) echo(' '.make_link($row[0]));
	echo('<p>Lesgroepen:');
	while ($row = $res_groep->fetchRow()) echo(' '.make_link($row[0]));
	echo('<p>'."\n");
} else {
	?><p>Klassenlijst van <? echo($entity_name); ?><p><table id="klassenlijst">
<tr><th>llnr</th><th>naam</th><th>stamklas</th><th>bijzonderheden</th><th>present</th><th>tijd</th></tr>
<?
while ($row = $result2->fetchRow(MDB2_FETCHMODE_ORDERED)) {
	?><tr><?
	?><td class="llnr"><? echo($row[0]); ?></td><?
	?><td class="naam"><? echo($row[1]); ?></td><?
	?><td class="stamklas"><? echo($row[2]); ?></td><?
	?><td class="bijz"><? echo($row[3]) ?></td><?
	?><td class="present">&nbsp;</td><?
	?><td class="tijd">&nbsp;</td><?
	?></tr>
<?  } ?>
</table>
<?
} ?>
<p>
<? if ($_GET['bw'] == 'x') { ?>
<span id="updateinfo">
<? if ($basis['file_id']) { ?>
Oud basisrooster <? echo(print_rev($basis['timestamp'], $basis['basis_id'])); ?>
<? } else { ?>
Er is geen oud basisrooster<? } ?>
<? if ($wijz['file_id']) { ?>, nieuw basisrooster <? echo(print_rev($wijz['timestamp'], $wijz['basis_id'])); } else { ?>, er is nog geen nieuw basisrooster voor deze week<? } ?>.
<? } else { ?>
<span id="updateinfo">Update basisrooster <? echo(print_rev($basis['timestamp'], $basis['basis_id'])); ?>
<? } ?>.
</span>

<? html_end(); ?>
