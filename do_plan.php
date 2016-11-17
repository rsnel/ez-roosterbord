<? require_once('common.php');

check_roostermaker($_POST['secret']);

if ($_POST['naam'] == '') fatal_error('beschrijving mag niet leeg zijn');

$naam = htmlenc(trim($_POST['naam']));

if (isset($_POST['plan_id'])) {
	if ($_POST['submit'] == 'Opslaan') {
		// we wijzigen een bestaand plan
		mdb2_exec("UPDATE plan SET naam = '%q', gewicht = '%q', ord = %i WHERE plan_id = %i", $naam, $_POST['gewicht'], $_POST['ord'], $_POST['plan_id']);
		mdb2_exec("DELETE FROM entities2plan WHERE plan_id = %i", $_POST['plan_id']);
		if (isset($_POST['entity_ids'])) foreach ($_POST['entity_ids'] as $entity_id)
			mdb2_exec("INSERT INTO entities2plan ( entity_id, plan_id ) VALUES ( %i, %i )", $entity_id, $_POST['plan_id']);
	} else if ($_POST['submit'] == 'Wissen') {
		mdb2_exec("DELETE FROM plan WHERE plan_id = %i", $_POST['plan_id']);
		mdb2_exec("DELETE FROM entities2plan WHERE plan_id = %i", $_POST['plan_id']);
	} else fatal_error('onmogelijke submit!');
} else {
	mdb2_exec("INSERT INTO plan ( naam, gewicht, ord ) VALUES ( '%q', '%q', %i )", $naam, $_POST['gewicht'], $_POST['ord']);
	$plan_id = mdb2_last_insert_id();
	if (isset($_POST['entity_ids'])) foreach ($_POST['entity_ids'] as $entity_id) mdb2_exec("INSERT INTO entities2plan ( entity_id, plan_id ) VALUES ( %i, $plan_id )", $entity_id);
}

header('Location: plans.php?secret='.$_POST['secret']);

?>
