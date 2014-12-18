<? require_once('common.php');

check_roostermaker($_POST['secret']);

header('Content-type: text/plain');

$entities = mdb2_query(<<<EOQ
SELECT entity_id, CASE WHEN entity_active = 1 THEN 1 ELSE 0 END
FROM entities WHERE entity_type != %i AND entity_type != %i AND entity_type != 0
EOQ
, LEERLING, LESGROEP);

$inverted = array();

foreach ($_POST['entity_ids'] as $entity_id) $inverted[$entity_id] = 1;

//print_r($inverted);

while (($row = $entities->fetchRow())) {
	if ($row[1] == 1 && !isset($inverted[$row[0]])) {
		mdb2_exec("UPDATE entities SET entity_active = NULL WHERE entity_id = %i", $row[0]);
	       	//echo("wis {$row[0]}\n");
	} else if ($row[1] == 0 && isset($inverted[$row[0]])) {
		mdb2_exec("UPDATE entities SET entity_active = 1 WHERE entity_id = %i", $row[0]);
	       	//echo("set {$row[0]}\n");
	}
}

header('Location: upload.php?secret='.$_POST['secret']);
?>
