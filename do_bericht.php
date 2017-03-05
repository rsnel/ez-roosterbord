<? require_once('common.php');

check_roostermaker($_POST['secret']);

header('Content-type: text/plain');

function rearrange( $arr ){
	$new = array ();
	foreach( $arr as $key => $all ){
		foreach( $all as $i => $val ){
			if (!isset($new[$i])) $new[$i] = array ();
			$new[$i][$key] = $val;
		}
	}
	return $new;
}

function store_file($file) {
	echo("storing file\n");
	print_r($file);
	uploadcomplain($file);
	$md5 = calc_md5($file['tmp_name']);
	$attachment_id = mdb2_single_val("SELECT attachment_id FROM attachments WHERE attachment_md5 = '%q'", $md5);
	if ($attachment_id) return $attachment_id; // we hebben de file al

	$new_filename = move_upload($file, $md5);
	mdb2_exec("INSERT INTO attachments ( attachment_md5, attachment_mimetype, attachment_filename ) VALUES ( '%q', '%q', '%q' )",
			$md5, $file['type'], htmlenc($file['name']));
	return mdb2_last_insert_id();
	//echo("new_filename=$new_filename\n");
	// we hebben de file nog niet
	//
	//echo($md5."\n");
}

function link_new_attachments($bericht_id, $attachment_ids) {
	if (config('ATTACHMENTS') == 'false') return;

	foreach ($attachment_ids as $attachment_id) {
		$affected = mdb2_exec("INSERT INTO attachments2berichten ( attachment_id, bericht_id ) VALUES ( '%i', '%i' )", $attachment_id, $bericht_id);
		if ($affected == 0) fatal_error("new attachment kan niet gekoppeld worden");
	}
	//echo("here \$bericht_id=$bericht_id");
	//print_r($attachment_ids);
	//exit;
}

$attachment_ids = array();

if (isset($_FILES['files'])) {
	$files = rearrange($_FILES['files']);
	print_r($files);
	foreach ($files as $file) {
		if ($file['error'] == 4) continue; // geen file geupload
		$attachment_ids[] = store_file($file);
	}
}

//print_r($attachment_ids);
//exit;

if (!isset($_POST['from']) || trim($_POST['from']) == '') fatal_error("'Zichtbaar vanaf' is niet ingevuld.");
if (!isset($_POST['until']) || trim($_POST['until']) == '') fatal_error("'Zichtbaar until' is niet ingevuld.");

if (!($from = strtotime($_POST['from']))) fatal_error("het veld 'Zichtbaar vanaf' bevat geen geldige datum");
if (!($until = strtotime($_POST['until']))) fatal_error("het veld 'Zichtbaar vanaf' bevat geen geldige datum");

if (isset($_POST['bericht_id'])) {

	if (config('ATTACHMENTS') == 'true') {
		// ga na of er attachments gewijzigd of gewist moeten worden
		// maak lijst van huidige attachments
		print_r($_POST);
		print_r($_FILES);
		$current_a2b_ids = mdb2_col(0, "SELECT attachment2bericht_id FROM attachments2berichten WHERE bericht_id = %i", $_POST['bericht_id']);
		foreach ($current_a2b_ids as $a2b_id) {
			if (isset($_FILES) && isset($_FILES['file-'.$a2b_id]) && $_FILES['file-'.$a2b_id]['error'] != 4) {
				// nieuwe file geupload
				$attachment_id = store_file($_FILES['file-'.$a2b_id]);
				mdb2_exec("UPDATE attachments2berichten SET version = version + 1, attachment_id = %i WHERE attachment2bericht_id = %i AND bericht_id = %i",
					$attachment_id, $a2b_id, $_POST['bericht_id']);
			} else if (isset($_POST['del-'.$a2b_id])) {
				// attachment moet worden verwijderd (dit negeren we als er ook een vervangende file is geupload
				mdb2_exec("DELETE FROM attachments2berichten WHERE bericht_id = %i AND attachment2bericht_id = %i", $_POST['bericht_id'], $a2b_id);
			}
			//if (isset(
		}
	}
	if ($_POST['submit'] == 'Opslaan') {
		// we wijzigen een bestaand bericht
		mdb2_exec("UPDATE berichten SET bericht_title = '%q', bericht_body = '%q', bericht_visiblefrom = '%q', bericht_visibleuntil = '%q', bericht_update = {$_SERVER['REQUEST_TIME']} WHERE bericht_id = %i", bbtohtml(htmlenc($_POST['title'])), bbtohtml(htmlenc($_POST['body'])), $from, $until, $_POST['bericht_id']);
		mdb2_exec("DELETE FROM entities2berichten WHERE bericht_id = %i", $_POST['bericht_id']);
		if (isset($_POST['entity_ids'])) foreach ($_POST['entity_ids'] as $entity_id)
			mdb2_exec("INSERT INTO entities2berichten ( entity_id, bericht_id ) VALUES ( %i, %i )", $entity_id, $_POST['bericht_id']);
		link_new_attachments($_POST['bericht_id'], $attachment_ids);
	} else if ($_POST['submit'] == 'Wissen') {
		mdb2_exec("DELETE FROM berichten WHERE bericht_id = %i", $_POST['bericht_id']);
		mdb2_exec("DELETE FROM entities2berichten WHERE bericht_id = %i", $_POST['bericht_id']);
	} else fatal_error('onmogelijke submit!');
} else {
	mdb2_exec("INSERT INTO berichten ( bericht_title, bericht_body, bericht_visiblefrom, bericht_visibleuntil, bericht_update ) VALUES ( '%q', '%q', '%q', '%q', {$_SERVER['REQUEST_TIME']} )", bbtohtml(htmlenc($_POST['title'])), bbtohtml(htmlenc($_POST['body'])), $from, $until);
	$bericht_id = mdb2_last_insert_id();
	if (isset($_POST['entity_ids'])) foreach ($_POST['entity_ids'] as $entity_id) mdb2_exec("INSERT INTO entities2berichten ( entity_id, bericht_id ) VALUES ( %i, $bericht_id )", $entity_id);
	link_new_attachments($bericht_id, $attachment_ids);
}

header('Location: upload.php?secret='.$_POST['secret']);

?>
