<? require_once('common.php');
require_once('rquery.php');

$entity_ids = array();

if (isset($_GET['file_id']) && $_GET['file_id']) $file_id = $_GET['file_id'];
else $file_id = 60;

$entities = explode(',', $_GET['entities']);
sort($entities);

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
		$select_lok[] = "IFNULL(GROUP_CONCAT(IF(dag = $i AND uur = $j, IF(lokalen = '', '?', lokalen), NULL) SEPARATOR '<br>'), '-') {$dagen[$i-1]}$j";
		$select_doc[] = "IFNULL(GROUP_CONCAT(IF(dag = $i AND uur = $j, IF(LENGTH(docenten) > 6, '****', docenten), NULL) SEPARATOR '<br>'), '-') {$dagen[$i-1]}$j";
	}
}
$select_lok = implode(",\n", $select_lok);
$select_doc = implode(",\n", $select_doc);

// build query
$query = array();

foreach ($entity_ids as $entity_id) {
	$subquery = rquery($entity_id[0], $entity_id[0], $file_id, 0, 'LEFT ');
	if ($entity_id[1] == LOKAAL) $select = $select_doc;
	else $select = $select_lok;

	$query[] = <<<EOQ
SELECT (SELECT entity_name FROM entities WHERE entity_id = {$entity_id[0]} ) doc, $select
 FROM (
	$subquery
) AS base
JOIN lessen AS f ON base.f_id = f.les_id
EOQ;
}

$query = implode("\nUNION ALL\n", $query);

$result = mdb2_query($query);

?>
<!DOCTYPE html!>
<head>
<style>
td {
	text-align: center;
	padding-top: 1.4em;
}
</style>
</head>
<body>
<form action="raw.php" method="GET" accept-charset="UTF-8">
docenten,lokalen<input type="text" name="entities">
<input type="submit" value="Zoek">
</form>
<?
mdb2_res_table($result);
?>
</body>
</html>
