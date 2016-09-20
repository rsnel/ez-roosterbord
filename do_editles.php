<? require_once('common.php'); 

function explode_and_sort($separator, $stuff) {
        $tmp = explode($separator, $stuff);

        if (count($tmp) == 1 && $tmp[0] == '') return $tmp;

        $new = array();

        foreach ($tmp as $value) {
                if (strlen($value) == 0 || $value[0] != '$') $new[] = $value;
        }

        sort($new);

        return $new;
}

function checkget_entity_id($name, $type) {
	$entity_id = mdb2_single_val("SELECT entity_id FROM entities WHERE entity_name = '%q' AND entity_type = %i",
		$name, $type);
	if (!$entity_id) {
		if ($type == LESGROEP) return checkget_entity_id($name, STAMKLAS);
		fatal_error('unknown entity '.$name);
	}
	return $entity_id;
}

function checkget_lokaal($name) {
	return checkget_entity_id($name, LOKAAL);
}

function checkget_vak($name) {
	return checkget_entity_id('/'.$name, VAK);
}

function checkget_lesgroep($name) { // STAMKLAS wordt ook meegenomen
	return checkget_entity_id($name, LESGROEP);
}

function checkget_docent($name) {
	return checkget_entity_id($name, DOCENT);
}

function get_les_id($dag0, $uur0, $lesgroepen0, $vakken0, $docenten0, $lokalen0, $notitie1) {
	if ($dag0 < 1 && $dag0 > 5) $dag = 0;
	else $dag = $dag0;
	if ($uur0 < 1 && $uur0 > config('MAX_LESUUR')) $uur = 0;
	else $uur = $uur0;
        $lesgroepen1 = implode(',', $lesgroepen = explode_and_sort(',', $lesgroepen0));
        $vakken1 = implode(',', $vakken = explode_and_sort(',', $vakken0));
        $docenten1 = implode(',', $docenten = explode_and_sort(',', $docenten0));
        $lokalen1 = implode(',', $lokalen = explode_and_sort(',', $lokalen0));
        $les_id =  mdb2_single_val(<<<EOQ
SELECT les_id FROM lessen
WHERE dag = %i AND uur = %i AND vakken = '%q'
AND lesgroepen = '%q' AND docenten = '%q' AND lokalen = '%q' AND notitie = '%q'
EOQ
        , $dag, $uur, $vakken1, $lesgroepen1, $docenten1, $lokalen1, $notitie1);
	if ($les_id) return $les_id;

	// les niet gevonden, maak de les
	$lokalen_ids = array_map('checkget_lokaal', $lokalen);
	$docenten_ids = array_map('checkget_docent', $docenten);
	$lesgroepen_ids = array_map('checkget_lesgroep', $lesgroepen);
	$vakken_ids = array_map('checkget_vak', $vakken);
	print_r($lokalen_ids);
	print_r($docenten_ids);
	print_r($lesgroepen_ids);
	print_r($vakken_ids);
	mdb2_exec("INSERT INTO lessen ( dag, uur, lesgroepen, vakken, docenten, lokalen, notitie ) VALUES ( %i, %i, '%q', '%q', '%q', '%q', '%q' )", $dag, $uur, $lesgroepen1, $vakken1, $docenten1, $lokalen1, $notitie1);

	$les_id = mdb2_single_val("SELECT LAST_INSERT_ID()");
	foreach (array_merge($lokalen_ids, $docenten_ids, $lesgroepen_ids, $vakken_ids)  as $entity_id )
		mdb2_exec("INSERT INTO entities2lessen ( entity_id, les_id ) VALUES ( '%i', '%i' )",
			$entity_id, $les_id);

	return $les_id;
}

if (!get_enable_edit()) exit;

header('Content-type: text/plain;charset=UTF-8');
print_r($_POST);

switch ($_POST['submit']) {
case 'Onwijzig':
	mdb2_exec("DELETE FROM files2lessen WHERE file_id = %i AND zermelo_id = %i", $_POST['file_id_wijz'], $_POST['zid']);
	break;
case 'Opslaan':
	$les_id = get_les_id($_POST['dag'], $_POST['uur'], $_POST['lesgroepen'], $_POST['vakken'], $_POST['docenten'], $_POST['lokalen'], $_POST['notitie']);
	mdb2_exec("INSERT INTO files2lessen ( file_id, zermelo_id, les_id ) VALUES ( %i, %i, %i ) ON DUPLICATE KEY UPDATE les_id = %i", $_POST['file_id_wijz'], $_POST['zid'], $les_id, $les_id);
	mdb2_exec("DELETE FROM files2lessen WHERE file_id = %i AND zermelo_id = %i AND les_id != %i", $_POST['file_id_wijz'], $_POST['zid'], $les_id);
	break;
default:
}

header('Location: '.dirname($_SERVER['PHP_SELF']).'/?q='.urlencode($_POST['q']).'&bw='.urlencode($_POST['bw']).'&wk='.urlencode($_POST['wk']).'&dy='.urlencode($_POST['dy']).'&c='.urlencode($_POST['c']));
?>
