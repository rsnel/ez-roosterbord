<? require_once('common.php');

$PID = getmypid();

$groepvak = explode('/', $_GET['q']);
$q = $groepvak[0];
$result = mdb2_query(<<<EOQ
SELECT entity_id, entity_name, entity_type
FROM $roosterdb.entities
WHERE entity_name = '%q'
EOQ
       , $q);

$select_lijst = NULL;

if (!($row = $result->fetchRow()))
	goto start_html;

if (($row[2] != STAMKLAS || count($groepvak) == 1)  && $row[2] != LESGROEP) {
	$entity_name = $row[1];
	$file_id = mdb2_single_val(<<<EOQ
SELECT file_id FROM $roosterdb.files
WHERE file_type = 1 AND file_status = 1
ORDER BY file_version DESC
LIMIT 1
EOQ
);
	$select_lijst = mdb2_query(<<<EOQ
SELECT DISTINCT CASE WHEN lesgroepen.entity_type = 4 THEN lesgroepen.entity_name ELSE CONCAT(lesgroepen.entity_name, '/', lessen.vakken) END name FROM lessen
JOIN files2lessen USING (les_id)
JOIN entities2lessen AS lesgroepen2lessen USING (les_id)
JOIN entities AS lesgroepen USING (entity_id)
JOIN entities2lessen USING (les_id)
WHERE entities2lessen.entity_id = {$row[0]} AND file_id = $file_id  AND (lesgroepen.entity_type = %i OR lesgroepen.entity_type = %i)
ORDER BY name
EOQ
, LESGROEP, STAMKLAS);
	goto start_html;
}

$safe_id = $row[0];
if (isset($groepvak[1])) $vak = $groepvak[1];
else $vak = NULL;

$min_week_id = mdb2_single_val("SELECT MIN(week_id) FROM roosters");
if ($min_week_id) {
	$res = mdb2_query("SELECT week FROM weken WHERE week_id >= $min_week_id ORDER BY week_id");
	$weken = $res->fetchCol();
	$res->free();
} else $weken = array();

// als de roosterwijzigingen uit staan, zijn de enige geldige opties 'b' en 'x'
if (config('DISABLE_WIJZIGINGEN')) fatal_error('not supported');

if (!$min_week_id) fatal_error('nog geen rooster :(');

$target = $safe_id;
/*
$target = mdb2_single_val("SELECT entity_id FROM entities WHERE entity_name LIKE '%q'", $_GET['q']);

if (!$target) {
	//fatal_error('entity "'.$_GET['q'].'" niet gevonden');
	goto start_html;
}
 */

$weken = mdb2_query(<<<EOQ
SELECT weken.week_id, week, year, roosters.basis_id, roosters.wijz_id, roosters.rooster_id, ma, di, wo, do, vr FROM roosters
LEFT JOIN roosters AS more ON more.week_id = roosters.week_id AND ( more.basis_id > roosters.basis_id OR ( more.basis_id = roosters.basis_id AND more.wijz_id > roosters.wijz_id ) )
RIGHT JOIN weken ON weken.week_id = roosters.week_id
WHERE more.week_id IS NULL
EOQ
);

function rquery_inner($entity_ids1, $entity_ids2, $id1, $id2, $left, $wijz) {
        //echo("entity_ids = $entity_ids, id1 = $id1, id2 = $id2, left = $left, wijz = $wijz");
        if ($entity_ids1 === '') $entity_ids1 = 'NULL';
        if ($entity_ids2 === '') $entity_ids2 = 'NULL';
        else if (!$entity_ids1 || !$entity_ids2) return<<<EOQ
SELECT DISTINCT f2l.les_id f_id, l2f.les_id s_id, f2l.zermelo_id f_zid, l2f.zermelo_id s_zid, 1 vis, 1 vis2, $wijz wijz
FROM files2lessen AS f2l
{$left}JOIN files2lessen AS l2f ON f2l.zermelo_id = l2f.zermelo_id AND l2f.file_id = $id2
WHERE f2l.file_id = $id1
EOQ;
        return <<<EOQ
SELECT DISTINCT f2l.les_id f_id, l2f.les_id s_id, f2l.zermelo_id f_zid, l2f.zermelo_id s_zid,
        CASE WHEN l2e.entity_id > 0 THEN 1 ELSE 0 END AS vis,
        CASE WHEN l2e2.entity_id > 0 THEN 1 ELSE 0 END AS vis2,
        $wijz wijz
FROM files2lessen AS f2l
JOIN entities2lessen AS e2l ON e2l.les_id = f2l.les_id
{$left}JOIN files2lessen AS l2f ON f2l.zermelo_id = l2f.zermelo_id AND l2f.file_id = $id2
LEFT JOIN entities2lessen AS l2e ON l2f.les_id = l2e.les_id AND l2e.entity_id IN ($entity_ids1)
LEFT JOIN entities2lessen AS l2e2 ON l2f.les_id = l2e2.les_id AND l2e2.entity_id IN ($entity_ids2)
WHERE f2l.file_id = $id1 AND e2l.entity_id IN ($entity_ids1)
EOQ;
}

function rquery($entity_ids1, $entity_ids2, $id1, $id2, $left) {
        return rquery_inner($entity_ids1, $entity_ids2, $id1, $id2, $left, 0).
                "\nUNION ALL\n".rquery_inner($entity_ids2, $entity_ids1, $id2, $id1, 'LEFT ', 1);
}
/* basis_id, prefix, postfix, own, dag, uur, vak, wijz_id */
/*define('BASIS_ID', 0);
define('LESGROEPEN', 1);
define('VAKKEN', 2);
define('DOCENTEN', 3);
define('LOKALEN', 4);
define('DAG', 5);
define('UUR', 6);
define('NOTITIE', 7);
define('WIJZ_ID', 8);
define('BASIS_ID2', 9);
define('LESGROEPEN2', 10);
define('VAKKEN2', 11);
define('DOCENTEN2', 12);
define('LOKALEN2', 13);
define('DAG2', 14);
define('UUR2', 15);
define('NOTITIE2', 16);
define('VIS', 17);
define('VIS2', 18);*/
//f_zid, f.lesgroepen AS f_lesgroepen, f.vakken AS f_vakken,
//       f.docenten AS f_docenten, f.lokalen AS f_lokalen,
//      f.dag AS f_dag, f.uur AS f_uur, f.notitie AS f_notitie, wijz,
//        s_zid, s.lesgroepen AS s_lesgroepen, s.vakken AS s_vakken,
//        s.docenten AS s_docenten, s.lokalen AS s_lokalen,
//        s.dag AS s_dag, s.uur AS s_uur, s.notitie AS s_notitie, vis, vis2, 
// -- , ' ', f.lesgroepen, '/', f.vakken, '/', f.docenten, '/', f.lokalen) les,

$include = '-- %q';
if ($vak) $include = <<<EOQ
JOIN entities2lessen AS vakken2lessen
ON vakken2lessen.les_id = f.les_id
JOIN entities AS vakken
ON vakken.entity_id = vakken2lessen.entity_id
AND vakken.entity_name = CONCAT('/', '%q')
EOQ;

$file_id_basis = 0;
$data = array();
$monday = array();
while ($row = $weken->fetchRow(MDB2_FETCHMODE_ASSOC)) {
	if ($row['rooster_id']) {
		$file_id_basis = mdb2_single_val("SELECT file_id FROM roosters WHERE basis_id = %i AND wijz_id = 0", $row['basis_id']);
		$file_id_wijz =  mdb2_single_val("SELECT file_id FROM roosters WHERE basis_id = %i AND wijz_id = %i", $row['basis_id'], $row['wijz_id']);
	} else $file_id_wijz = 0;
	//echo("week {$row['week']} file_id_basis $file_id_basis, file_id_wijz $file_id_wijz<br>");
	$subquery = rquery($target, $target, $file_id_basis, $file_id_wijz, 'LEFT ');
	$monday[$row['week']] = new DateTime();
	$monday[$row['week']]->setISODate($row['year'], $row['week']);
	$data[$row['week']] = mdb2_query(<<<EOQ
SELECT * FROM  (
SELECT
CONCAT(CASE WHEN f.dag = 1 THEN 'ma' WHEN f.dag = 2 THEN 'di' WHEN f.dag = 3 THEN 'wo' WHEN f.dag = 4 THEN 'do' WHEN f.dag = 5 THEN 'vr' END, f.uur) les,
IFNULL(GROUP_CONCAT(events.beschrijving), IF(s.dag = 0 OR (f.dag = 1 AND ma = 0) OR (f.dag = 2 AND di = 0) OR (f.dag = 3 AND wo = 0) OR (f.dag = 4 AND do = 0) OR (f.dag = 5 AND vr = 0), 'uitval', '-')) activiteit, (wijz = 1 OR s_zid IS NULL OR s.dag = 0) `show`, f.dag, f.uur
FROM ( $subquery ) AS sub
JOIN weken ON week_id = {$row['week_id']}
JOIN lessen AS f ON f.les_id = f_id
LEFT JOIN lessen AS s ON s.les_id = s_id
LEFT JOIN (
	SELECT * FROM events
	LEFT JOIN (
		SELECT event_id, COUNT(lesgroep2_id) tel FROM entities2events
		LEFT JOIN (
			SELECT * FROM grp2grp WHERE grp2grp.file_id_basis = $file_id_basis AND grp2grp.lesgroep2_id = $target
		) AS bla ON bla.lesgroep_id = entity_id 
		GROUP BY event_id
	) AS bla USING (event_id)
	WHERE tel = 1 OR tel IS NULL
) AS events
ON 10*(8*events.start_week_id + events.start_dag) + events.start_uur <= 10*(8*{$row['week_id']} + f.dag) + f.uur
AND 10*(8*events.eind_week_id + events.eind_dag) + events.eind_uur >= 10*(8*{$row['week_id']} + f.dag) + f.uur
$include
WHERE f.lesgroepen IS NOT NULL AND f.dag != 0 AND f.uur != 0 -- AND (wijz = 1 OR s_zid IS NULL OR s.dag = 0) OR (wijz = 0 AND s_zid IS NOT NULL AND s.dag != 0 AND (s.dag != f.uur OR f.dag != s.uur))
GROUP BY f_zid, wijz
) AS bla
ORDER BY dag, uur, CASE WHEN activiteit = '-' THEN 0 ELSE 1 END
EOQ
, $vak);
}

start_html:

?>
<!DOCTYPE html>
<html>
<head>
<title>Jaarkalender</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/ui-lightness/jquery-ui-1.10.4.custom.min.css">
<style>
table {
	font-size: 0.25cm;
        width: 100%;
        border-collapse: collapse;
        border: 1px solid black;
}
table * td, table * th {
        padding-top: 1.1mm;
        padding-bottom: 1.1mm;
        padding-left: 1mm;
        padding-right: 1mm;
        border: 1px solid black;
}
table .shrink {
	width: 1%;
	white-space: nowrap;
}
table .expand {
}
</style>
<style media="screen">
body {
        background-color: #E6E6FA;
}
div.page {
        background-color: white;
        padding: 1.5cm;
        width: 180mm;
        height: 267mm;
        margin: .5cm auto;
        border: 1px solid;
        box-shadow: 10px 10px 5px #888888;

}
</style>
<style media="print">
@page {
	size: a4 portrait;
	margin: 1.5cm;
}
div.page+div.page {
        page-break-before: always;
}
.noprint {
	display: none;
}
</style>
<script type="text/javascript" src="js/jquery-1.11.1.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.10.4.custom.min.js"></script>
<script type="text/javascript" src="js/jquery.iframe-post-form.js"></script>
<script type="text/javascript">
$(function() {
	$('#box').focus();
});
</script>
</head>
<body>
<div id="content">
<div class="noprint">
<form>
<input type="submit" value="Zoek">
<input id="box" type="text" name="q"> (bijvoorbeeld <code>6V.wisB2</code> of <code>6V1/entl</code>)
</form>
</div>
<? if ($target) { ?>
<div class="page">
<h3>Jaarkalender van <? echo(htmlenc($_GET['q'])) ?></h3>
<table style="width: 100%"><tr><th>week</th><th>aantal</th><th>lessen</th></tr>
<? //mdb2_res_table($weken); ?>
<?
$totaallessen = 0;
$lessen = 0;
$perweek = array();
$uitval = 0;
foreach ($data as $key => $value) {
	$lastles = '';
	$perweek[$key] = 0;
	while ($row = $value->fetchRow()) {
		/*?><pre><?print_r($row)?></pre><?*/
		if ($lastles == $row[0]) continue;
		if (!$row[2]) continue;
		$lastles = $row[0];
		if ($row[1]  == '-') {
			$totaallessen++;
			$perweek[$key]++;
		}
	}
	$value->seek();
}
foreach ($data as $key => $value) {
	?><tr><td class="shrink"><? echo($key.' ('.$monday[$key]->format('j-n').')') ?></td><td class="shrink"><? echo($perweek[$key].' ('.$lessen.'/'.($totaallessen - $lessen).')'); ?></td><?
	$lastles = '';
	while ($row = $value->fetchRow()) {
		if ($lastles == $row[0]) continue;
		if (!$row[2]) continue;
		$lastles = $row[0];
		if ($row[1]  != '-') $uitval++;
		else $lessen++;
	?><td class="expand"><? echo($row[0].' '.$row[1]) ?></td><?
	}
	//mdb2_res_table($value);
?></tr><?
}
?>
</table>
Beschikbaar lessen: <? echo($lessen) ?>, uitval <? echo($uitval) ?>.
</div>
<? } else if (trim($_GET['q'])) {
	if (!$select_lijst) {
?>
<span style="color: red">"<? echo(htmlenc($_GET['q'])) ?>" niet gevonden</span>
<? } else {
?><h3>Lesgroepen/stamklassen+vak horende bij <? echo(htmlenc($entity_name)); ?></h3><ul><?
	while (($row = $select_lijst->fetchRow())) {
	?><li><a href="jaarkalender.php?q=<? echo($row[0]) ?>"><? echo($row[0]) ?></a></li><?
	}
?></ul><?
	}
} ?>
</div>
</body>
</html>
