<? require_once('common.php');

check_roostermaker($_POST['secret']);

/* start: week dag uur
 * eind : week dag uur
 *
 * om invoer te vereenvoudigen hoeft niet alles te worden ingevuld
 * regels:
 * - de startweek MOET ingevuld zijn
 * - als de eindweek niet ingevuld is, dan maken we hem gelijk aan de beginweek
 * - als de startdag niet is ingevuld, mag de rest niet ingevuld zijn
 *   en maken we ervan: ma1 - vr9
 * - als de einddag niet is ingevuld, dan maken we hem gelijk aan de startdag
 * - als het startuur niet is ingevuld, dan mag het einduur ook niet ingevuld zijn
 *   en maken we ervan: 1 - 9
 *
 * Als het stof is neergedaald dan MOET het eind niet eerder zijn dan het begin,
 * dus startweek < eindweek OF (weken gelijk EN startdag < einddag OF (dagen gelijk EN startuur <= einduur) */

if ($_POST['eindweek'] == '-') $_POST['eindweek'] = $_POST['startweek'];
if ($_POST['startdag'] == '-') {
	if ($_POST['einddag'] != '-' || $_POST['startuur'] != '-' || $_POST['einduur'] != '-')
		fatal_error('als startdag niet gegeven is, dan mag de rest ook niet gegeven zijn');
	$_POST['startdag'] = 1;
	$_POST['startuur'] = 1;
	$_POST['einddag'] = 5;
	$_POST['einduur'] = 9;
}
if ($_POST['einddag'] == '-') $_POST['einddag'] = $_POST['startdag'];
if ($_POST['startuur'] == '-') {
	if ($_POST['einduur'] != '-') fatal_error('als startuur niet gegeven is, dan mag de rest ook niet gegeven zijn');
	$_POST['startuur'] = 1;
	$_POST['einduur'] = 9;
}

if (!($_POST['startweek'] < $_POST['eindweek'] || ($_POST['startweek'] == $_POST['eindweek'] && ($_POST['startdag'] < $_POST['einddag'] || ($_POST['startdag'] == $_POST['startdag'] && $_POST['startuur'] <= $_POST['einduur']))))) fatal_error('start mag niet na begin zijn!');

$beschrijving = trim(htmlenc($_POST['beschrijving']));

if ($beschrijving == '') fatal_error('beschrijving mag niet leeg zijn');

if (isset($_POST['event_id'])) {
	if ($_POST['submit'] == 'Opslaan') {
		// we wijzigen een bestaand event
		mdb2_exec("UPDATE events SET beschrijving = '%q', start_week_id = %i, start_dag = %i, start_uur = %i, eind_week_id = %i, eind_dag = %i, eind_uur = %i WHERE event_id = %i", $beschrijving, $_POST['startweek'], $_POST['startdag'], $_POST['startuur'], $_POST['eindweek'], $_POST['einddag'], $_POST['einduur'], $_POST['event_id']);
		mdb2_exec("DELETE FROM entities2events WHERE event_id = %i", $_POST['event_id']);
		if (isset($_POST['entity_ids'])) foreach ($_POST['entity_ids'] as $entity_id)
			mdb2_exec("INSERT INTO entities2events ( entity_id, event_id ) VALUES ( %i, %i )", $entity_id, $_POST['event_id']);
	} else if ($_POST['submit'] == 'Wissen') {
		mdb2_exec("DELETE FROM events WHERE event_id = %i", $_POST['event_id']);
		mdb2_exec("DELETE FROM entities2events WHERE event_id = %i", $_POST['event_id']);
	} else fatal_error('onmogelijke submit!');
} else {
	mdb2_exec("INSERT INTO events ( start_week_id, start_dag, start_uur, eind_week_id, eind_dag, eind_uur, beschrijving ) VALUES ( %i, %i, %i, %i, %i, %i, '%q' )",
		$_POST['startweek'], $_POST['startdag'], $_POST['startuur'],
		$_POST['eindweek'], $_POST['einddag'], $_POST['einduur'],
		$beschrijving);
	$event_id = mdb2_last_insert_id();
	if (isset($_POST['entity_ids'])) foreach ($_POST['entity_ids'] as $entity_id) mdb2_exec("INSERT INTO entities2events ( entity_id, event_id ) VALUES ( %i, $event_id )", $entity_id);
}

header('Location: events.php?secret='.$_POST['secret']);

?>
