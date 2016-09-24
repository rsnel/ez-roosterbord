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

function get_entity_ids($func, $list) {
	if (count($list) == 1 && $list[0] == '') return array ();
	return array_map($func, $list);
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
	$lokalen_ids = get_entity_ids('checkget_lokaal', $lokalen);
	$docenten_ids = array_map('checkget_docent', $docenten);
	$lesgroepen_ids = get_entity_ids('checkget_lesgroep', $lesgroepen);
	//$lesgroepen_ids = array_map('checkget_lesgroep', $lesgroepen);
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

$zermelo_id_orig = mdb2_single_val("SELECT zermelo_id_orig FROM zermelo_ids WHERE zermelo_id = %i", $_POST['zid']);
$orig_les_id = mdb2_single_val("SELECT les_id FROM files2lessen WHERE zermelo_id = %i AND file_id = %i", $_POST['zid'], $_POST['file_id_basis']);

$dagen = array ('ma','di','wo','do','vr');

function get_diff($les_oud, $les_nieuw) {
	global $dagen;
	$oud = array (); 
	$nieuw = array ();
	$nieuw[] = $dagen[$les_nieuw['dag']-1].$les_nieuw['uur'];
	if ($les_oud['dag'] != $les_nieuw['dag'] || $les_oud['uur'] != $les_nieuw['uur']) {
		$oud[] = $dagen[$les_oud['dag']-1].$les_oud['uur'];
	}
	$nieuw[] = $les_nieuw['lesgroepen'];
	if ($les_oud['lesgroepen'] != $les_nieuw['lesgroepen']) {
		$oud[] = $les_oud['lesgroepen'];
	}
	$nieuw[] = $les_nieuw['vakken'];
	if ($les_oud['vakken'] != $les_nieuw['vakken']) {
		$oud[] = $les_oud['vakken'];
	}
	$nieuw[] = $les_nieuw['docenten'];
	if ($les_oud['docenten'] != $les_nieuw['docenten']) {
		$oud[] = $les_oud['docenten'];
	}
	$nieuw[] = $les_nieuw['lokalen'];
	if ($les_oud['lokalen'] != $les_nieuw['lokalen']) {
		$oud[] = $les_oud['lokalen'];
	}
	return implode('/', $nieuw).' <- '.implode('/', $oud);
}

switch ($_POST['submit']) {
case 'Onwijzig':
	$les_id = mdb2_single_val("SELECT les_id FROM files2lessen WHERE zermelo_id = %i AND file_id = %i", $_POST['zid'], $_POST['file_id_wijz']);
	mdb2_exec("DELETE FROM files2lessen WHERE file_id = %i AND zermelo_id = %i", $_POST['file_id_wijz'], $_POST['zid']);
	$orig_les = mdb2_single_assoc("SELECT * FROM lessen WHERE les_id = %i", $orig_les_id);
	$les = mdb2_single_assoc("SELECT * FROM lessen WHERE les_id = %i", $les_id);
	if ($les_id) logit($_SERVER['PHP_AUTH_USER'].' '.$zermelo_id_orig.' '.get_diff($les, $orig_les));
	break;
case 'Opslaan':
	$oldwijz_les_id =  mdb2_single_val("SELECT les_id FROM files2lessen WHERE zermelo_id = %i AND file_id = %i", $_POST['zid'], $_POST['file_id_wijz']);
	$les_id = get_les_id($_POST['dag'], $_POST['uur'], $_POST['lesgroepen'], $_POST['vakken'], $_POST['docenten'], $_POST['lokalen'], $_POST['notitie']);
	if ($orig_les_id == $les_id) { // er is geen wijziging ten opzichte van basisrooster
		if ($oldwijz_les_id) {
			mdb2_exec("DELETE FROM files2lessen WHERE file_id = %i AND zermelo_id = %i", $_POST['file_id_wijz'], $_POST['zid']);
			$oldwijz_les = mdb2_single_assoc("SELECT * FROM lessen WHERE les_id = %i", $oldwijz_les_id);
			$orig_les = mdb2_single_assoc("SELECT * FROM lessen WHERE les_id = %i", $orig_les_id);
			logit($_SERVER['PHP_AUTH_USER'].' '.$zermelo_id_orig.' '.get_diff($oldwijz_les, $orig_les));
		}
	} else {
		mdb2_exec("INSERT INTO files2lessen ( file_id, zermelo_id, les_id ) VALUES ( %i, %i, %i ) ON DUPLICATE KEY UPDATE les_id = %i", $_POST['file_id_wijz'], $_POST['zid'], $les_id, $les_id);
		mdb2_exec("DELETE FROM files2lessen WHERE file_id = %i AND zermelo_id = %i AND les_id != %i", $_POST['file_id_wijz'], $_POST['zid'], $les_id);
		if ($oldwijz_les_id) {
			$oldwijz_les = mdb2_single_assoc("SELECT * FROM lessen WHERE les_id = %i", $oldwijz_les_id);
			$les = mdb2_single_assoc("SELECT * FROM lessen WHERE les_id = %i", $les_id);
			logit($_SERVER['PHP_AUTH_USER'].' '.$zermelo_id_orig.' '.get_diff($oldwijz_les, $les));
		} else {
			$orig_les = mdb2_single_assoc("SELECT * FROM lessen WHERE les_id = %i", $orig_les_id);
			$les = mdb2_single_assoc("SELECT * FROM lessen WHERE les_id = %i", $les_id);
			logit($_SERVER['PHP_AUTH_USER'].' '.$zermelo_id_orig.' '.get_diff($orig_les, $les));
		}
	}
	break;
default:
}

header('Location: '.dirname($_SERVER['PHP_SELF']).'/?q='.urlencode($_POST['q']).'&bw='.urlencode($_POST['bw']).'&wk='.urlencode($_POST['wk']).'&dy='.urlencode($_POST['dy']).'&c='.urlencode($_POST['c']));
?>
