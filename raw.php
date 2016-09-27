<? require_once('common.php');
require_once('rquery.php');

$entity_ids = array();

if (isset($_GET['file_id_basis']) && $_GET['file_id_basis']) $file_id_basis = $_GET['file_id_basis'];
else fatal_error("parameter file_id_basis is required");
$file_basis = mdb2_single_assoc("SELECT * FROM files WHERE file_id = %i", $file_id_basis);

if (!$file_basis) fatal_error("invalid file_id_basis");

if (isset($_GET['file_id_wijz']) && $_GET['file_id_wijz']) $file_id_wijz = $_GET['file_id_wijz'];
else $file_id_wijz = 0;

$file_wijz = mdb2_single_assoc("SELECT * FROM files WHERE file_id = %i", $file_id_basis);
if (!$file_wijz) fatal_error("invalid file_id_basis");

if (isset($_GET['dag']) && is_array($_GET['dag'])) $dispdagen = $_GET['dag'];
else $dispdagen = array ( 'ma', 'di', 'wo', 'do', 'vr');

function checknq($entity) {
	return (strtoupper($entity) != $_GET['nq']);
}

$dagen = array ( 'ma', 'di', 'wo', 'do', 'vr' );

$entities = explode(',', $_GET['q']);
sort($entities);
if (isset($_GET['nq'])) {
	$entities = array_filter($entities, 'checknq');
}
$entities = array_unique($entities);


function escape_and_quote($entity) {
	return "'".mdb2()->escape($entity, true)."'";
}

$escaped_entities = array_map('escape_and_quote', $entities);
$escaped_entities = implode(',', $escaped_entities);
$searchbox = implode(',', $entities);
$tail = '';
for ($i = 1; $i <= 5; $i++) if (in_array($dagen[$i-1], $dispdagen)) $tail .= "&amp;dag[]={$dagen[$i-1]}";
$tail .= '&amp;file_id_basis='.$file_id_basis.'&amp;file_id_wijz='.$file_id_wijz.'">';
$head = '<a href="raw.php?q='.$searchbox;

foreach ($entities as $entity) {
	$row = mdb2_single_array("SELECT entity_id, entity_type, entity_name FROM entities WHERE entity_name = '%q' AND 
( entity_type = %i OR entity_type = %i )", trim($entity), DOCENT, LOKAAL);
	if ($row) {
		$entity_ids[] = $row;
	}
}
function compare_entities($a, $b) {
	if ($a[1] < $b[1]) return 1;
	if ($a[1] > $b[1]) return -1;
	return strcmp($a[2], $b[2]);
}
usort($entity_ids, 'compare_entities');
$enable_edit = get_enable_edit();
$select_doc = array();
$select_lok = array();
for ($i = 1; $i <= 5; $i++) {
	if (!in_array($dagen[$i-1], $dispdagen)) continue;
	for ($j = 1; $j <= 9; $j++) {
		$uur = "$i-$j";
		if ($enable_edit) $colname = "`<input type=\"checkbox\" name=\"lesuur[]\" value=\"$i-$j\">{$dagen[$i-1]}$j`";
		else $colname = "{$dagen[$i-1]}$j";
		$select_lok[] = "IFNULL(GROUP_CONCAT(IF(dag = $i AND uur = $j, IF(lokalen = '', '?', IF(LENGTH(lokalen) > 6, '****', IF(lokalen IN ( $escaped_entities), lokalen, CONCAT('$head,', lokalen, '$tail', lokalen, '</a>')))), NULL) SEPARATOR '<br>'), '-') $colname";
		$select_doc[] = <<<EOQ
IFNULL(
	GROUP_CONCAT(
		IF(dag = $i AND uur = $j,
			IF(LENGTH(docenten) > 6, '****',
				IF (docenten IN ( $escaped_entities),
					CONCAT('<div class="dd$uur">', docenten, '</div>'),
					CONCAT('$head,', docenten, '$tail', docenten, '</a>')
				)
			),
			NULL
		) SEPARATOR '<br>'),
	'<div class="dd$uur">-</div>'
) $colname
EOQ;
	}
}
$select_lok = implode(",\n", $select_lok);
$select_doc = implode(",\n", $select_doc);

// build query
$query = array();

foreach ($entity_ids as $entity_id) {
	$subquery = rquery($entity_id[0], $entity_id[0], $file_id_basis, $file_id_wijz, 'LEFT ');
	if ($entity_id[1] == LOKAAL && $enable_edit) {
		$select = $select_doc;
		$dolo = "'<input type=\"checkbox\" name=\"lokaal[]\" value=\"{$entity_id[2]}\"> $head&amp;nq={$entity_id[2]}$tail{$entity_id[2]}</a>' dolo";
	} else {
		$select = $select_lok;
		$dolo = "'$head&amp;nq={$entity_id[2]}$tail{$entity_id[2]}</a>' dolo";
	}

	$query[] = <<<EOQ
SELECT $dolo, $select
 FROM (
	$subquery
) AS base
JOIN lessen AS f ON base.f_id = f.les_id
AND (wijz = 1 OR s_zid IS NULL)
EOQ;
}

$query = implode("\nUNION ALL\n", $query);

$result = mdb2_query($query);

?>
<!DOCTYPE html!>
<head>
<script type="text/javascript" src="js/jquery-1.11.1.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.10.4.custom.min.js"></script>
<script type="text/javascript">
//<![CDATA[
function initdd(i, j) {
	var spec = i + '-' + j;
	$('.dd' + spec).draggable({
		containment: "#tablecontainer",
		axis: "y",
		revert: 'invalid',
		opacity: 0.7, helper: "clone"
	});
	$('.dd' + spec).droppable({
		accept: '.dd' + spec,
		drop: function( event, ui ) {
			$('input', $(this).parent().parent()).attr('checked', true);
			$('input', ui.draggable.parent().parent()).attr('checked', true);
			$("input[value='" + spec + "']").attr('checked', true);
			$("#ruil").submit();
		},
		activate: function() {
			$(this).addClass('active');
		},
		deactivate: function() {
			$(this).removeClass('active');
		}
	});
}

$(function(){
<? if ($enable_edit) { ?>
	for (var i = 1; i <= 5; i++) {
		for (var j = 1; j <= 9; j++) {
			initdd(i, j);
		}
	}
<? } ?>
});
//]]>
</script>
<style>
a:link {
        text-decoration: none; color: blue;
}
a:visited {
        text-decoration: none; color: blue;
}
a:hover {
        text-decoration: underline;
}
.active {
	background-color: #FFFF99;
}
/*td {
	text-align: center;
	padding-top: 1.4em;
} */
</style>
</head>
<body>
<form action="raw.php" method="GET" accept-charset="UTF-8">
docenten,lokalen<input size="60" type="text" name="q" value="<? echo($searchbox); ?>">
<input type="checkbox" name="dag[]" <? if (in_array('ma', $dispdagen)) { ?>checked <? } ?>value="ma">ma
<input type="checkbox" name="dag[]" <? if (in_array('di', $dispdagen)) { ?>checked <? } ?>value="di">di
<input type="checkbox" name="dag[]" <? if (in_array('wo', $dispdagen)) { ?>checked <? } ?>value="wo">wo
<input type="checkbox" name="dag[]" <? if (in_array('do', $dispdagen)) { ?>checked <? } ?>value="do">do
<input type="checkbox" name="dag[]" <? if (in_array('vr', $dispdagen)) { ?>checked <? } ?>value="vr">vr
<input type="hidden" name="file_id_basis" value="<? echo($file_id_basis); ?>">
<input type="hidden" name="file_id_wijz" value="<? echo($file_id_wijz); ?>">
<input type="submit" value="Zoek">
</form>
<?
if ($enable_edit) { ?>
<form id="ruil" action="do_ruil_verticaal.php" method="POST" accept-charset="UTF-8">
<div id="tablecontainer">
<? } 
mdb2_res_table($result);
?></div><?
if ($enable_edit) {
	if (in_array('ma', $dispdagen)) { ?><input type="hidden" name="dag[]" value="ma"><? } 
	if (in_array('di', $dispdagen)) { ?><input type="hidden" name="dag[]" value="di"><? } 
	if (in_array('wo', $dispdagen)) { ?><input type="hidden" name="dag[]" value="wo"><? } 
	if (in_array('do', $dispdagen)) { ?><input type="hidden" name="dag[]" value="do"><? } 
	if (in_array('vr', $dispdagen)) { ?><input type="hidden" name="dag[]" value="vr"><? } 
?>
<input type="hidden" name="q" value="<? echo($searchbox); ?>">
Selecteer precies twee rijen en een aantal kolommen en <input type="submit" value="Ruil verticaal"> 
Je kunt ook een docent slepen naar een andere docent of een lege plek om van lokaal te ruilen. Je kunt alleen slepen met en slepen naar docenten van wie het rooster zichtbaar is.
<input type="hidden" name="file_id_basis" value="<? echo($file_id_basis); ?>">
<input type="hidden" name="file_id_wijz" value="<? echo($file_id_wijz); ?>">
<? } 
?>
</body>
</html>
