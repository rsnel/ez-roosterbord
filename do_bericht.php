<? require_once('common.php');

check_roostermaker($_POST['secret']);

if (!isset($_POST['from']) || trim($_POST['from']) == '') fatal_error("'Zichtbaar vanaf' is niet ingevuld.");
if (!isset($_POST['until']) || trim($_POST['until']) == '') fatal_error("'Zichtbaar until' is niet ingevuld.");

if (!($from = strtotime($_POST['from']))) fatal_error("het veld 'Zichtbaar vanaf' bevat geen geldige datum");
if (!($until = strtotime($_POST['until']))) fatal_error("het veld 'Zichtbaar vanaf' bevat geen geldige datum");

if (isset($_POST['bericht_id'])) {
	if ($_POST['submit'] == 'Opslaan') {
		// we wijzigen een bestaand bericht
		mdb2_exec("UPDATE berichten SET bericht_title = '%q', bericht_body = '%q', bericht_visiblefrom = '%q', bericht_visibleuntil = '%q', bericht_update = {$_SERVER['REQUEST_TIME']} WHERE bericht_id = %i", bbtohtml(htmlenc($_POST['title'])), bbtohtml(htmlenc($_POST['body'])), $from, $until, $_POST['bericht_id']);
		mdb2_exec("DELETE FROM entities2berichten WHERE bericht_id = %i", $_POST['bericht_id']);
		if (isset($_POST['entity_ids'])) foreach ($_POST['entity_ids'] as $entity_id)
			mdb2_exec("INSERT INTO entities2berichten ( entity_id, bericht_id ) VALUES ( %i, %i )", $entity_id, $_POST['bericht_id']);
	} else if ($_POST['submit'] == 'Wissen') {
		mdb2_exec("DELETE FROM berichten WHERE bericht_id = %i", $_POST['bericht_id']);
		mdb2_exec("DELETE FROM entities2berichten WHERE bericht_id = %i", $_POST['bericht_id']);
	} else fatal_error('onmogelijke submit!');
} else {
	mdb2_exec("INSERT INTO berichten ( bericht_title, bericht_body, bericht_visiblefrom, bericht_visibleuntil, bericht_update ) VALUES ( '%q', '%q', '%q', '%q', {$_SERVER['REQUEST_TIME']} )", bbtohtml(htmlenc($_POST['title'])), bbtohtml(htmlenc($_POST['body'])), $from, $until);
	$bericht_id = mdb2_last_insert_id();
	if (isset($_POST['entity_ids'])) foreach ($_POST['entity_ids'] as $entity_id) mdb2_exec("INSERT INTO entities2berichten ( entity_id, bericht_id ) VALUES ( %i, $bericht_id )", $entity_id);
}

header('Location: upload.php?secret='.$_POST['secret']);

?>
