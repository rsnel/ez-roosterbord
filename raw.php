<? require_once('common.php');
require_once('rquery.php');

$entity_ids = array();

if (isset($_GET['file_id_basis']) && $_GET['file_id_basis']) $file_id_basis = $_GET['file_id_basis'];
else fatal_error("parameter file_id_basis is required");

if (isset($_GET['file_id_wijz']) && $_GET['file_id_wijz']) $file_id_wijz = $_GET['file_id_wijz'];
else $file_id_wijz = 0;

function checknq($entity) {
	return (strtoupper($entity) != $_GET['nq']);
}

$entities = explode(',', $_GET['q']);
sort($entities);
if (isset($_GET['nq'])) {
	$entities = array_filter($entities, 'checknq');
}
$entities = array_unique($entities);
$searchbox = implode(',', $entities);
$tail = '&amp;file_id_basis='.$file_id_basis.'&amp;file_id_wijz='.$file_id_wijz.'">';
$head = '<a href="raw.php?q='.$searchbox;

foreach ($entities as $entity) {
	$row = mdb2_single_array("SELECT entity_id, entity_type FROM entities WHERE entity_name = '%q' AND 
( entity_type = %i OR entity_type = %i )", trim($entity), DOCENT, LOKAAL);
	if ($row) {
		$entity_ids[] = $row;
	}
}
$select_doc = array();
$select_lok = array();
$dagen = array ( 'ma', 'di', 'wo', 'do', 'vr' );
for ($i = 1; $i <= 5; $i++) {
	for ($j = 1; $j <= 9; $j++) {
		$select_lok[] = "IFNULL(GROUP_CONCAT(IF(dag = $i AND uur = $j, IF(lokalen = '', '?', CONCAT('$head,', lokalen, '$tail', lokalen, '</a>')), NULL) SEPARATOR '<br>'), '-') {$dagen[$i-1]}$j";
		$select_doc[] = "IFNULL(GROUP_CONCAT(IF(dag = $i AND uur = $j, IF(LENGTH(docenten) > 6, '****', CONCAT('$head,', docenten, '$tail', docenten, '</a>')), NULL) SEPARATOR '<br>'), '-') {$dagen[$i-1]}$j";
	}
}
$select_lok = implode(",\n", $select_lok);
$select_doc = implode(",\n", $select_doc);

// build query
$query = array();

foreach ($entity_ids as $entity_id) {
	$subquery = rquery($entity_id[0], $entity_id[0], $file_id_basis, $file_id_wijz, 'LEFT ');
	if ($entity_id[1] == LOKAAL) $select = $select_doc;
	else $select = $select_lok;

	$query[] = <<<EOQ
SELECT CONCAT('$head', '&amp;nq=', (SELECT entity_name FROM entities WHERE entity_id = {$entity_id[0]} ), '$tail', (SELECT entity_name FROM entities WHERE entity_id = {$entity_id[0]} ), '</a>') dolo, $select
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
/*td {
	text-align: center;
	padding-top: 1.4em;
} */
</style>
</head>
<body>
<form action="raw.php" method="GET" accept-charset="UTF-8">
docenten,lokalen<input size="100" type="text" name="q" value="<? echo($searchbox); ?>">
<input type="hidden" name="file_id_basis" value="<? echo($file_id_basis); ?>">
<input type="hidden" name="file_id_wijz" value="<? echo($file_id_wijz); ?>">
<input type="submit" value="Zoek">
</form>
<?
mdb2_res_table($result);
?>
</body>
</html>
