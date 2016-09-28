<? require_once('common.php'); 
require_once('rquery.php');

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
	//print_r($lokalen_ids);
	//print_r($docenten_ids);
	//print_r($lesgroepen_ids);
	//print_r($vakken_ids);
	mdb2_exec("INSERT INTO lessen ( dag, uur, lesgroepen, vakken, docenten, lokalen, notitie ) VALUES ( %i, %i, '%q', '%q', '%q', '%q', '%q' )", $dag, $uur, $lesgroepen1, $vakken1, $docenten1, $lokalen1, $notitie1);

	$les_id = mdb2_single_val("SELECT LAST_INSERT_ID()");
	foreach (array_merge($lokalen_ids, $docenten_ids, $lesgroepen_ids, $vakken_ids)  as $entity_id )
		mdb2_exec("INSERT INTO entities2lessen ( entity_id, les_id ) VALUES ( '%i', '%i' )",
			$entity_id, $les_id);

	return $les_id;
}

if (!get_enable_edit()) exit;

function get_les_lokaal_lesuur($lokaal_id, $file_id_basis, $file_id_wijz, $lesuur) {
	if (!preg_match('/^(\d)-(\d)$/', $lesuur, $matches)) fatal_error("lesuur $lesuur invalid format");
	$subquery = rquery($lokaal_id, $lokaal_id, $file_id_basis, $file_id_wijz, 'LEFT ');
	$info = mdb2_single_assoc(<<<EOQ
SELECT COUNT(base.f_id) count, f_zid, zermelo_id_orig, f.* FROM (
        $subquery
) AS base
JOIN lessen AS f ON base.f_id = f.les_id AND (wijz = 1 OR s_zid IS NULL)
JOIN zermelo_ids ON base.f_zid = zermelo_id
WHERE dag = %i AND uur = %i
EOQ
, $matches[1], $matches[2]); 
	if (!is_array($info) || count($info) == 0) fatal_error('les van lokaal '.$lokaal_id.' op dag='.$matches[1].' en uur='.$matches[2].' niet gevonden');
	$count = array_shift($info);
	if ($count > 1) fatal_error('te veel lessen gevonden in lokaal '.$lokaal_id.' op dag='.$matches[1].' en uur='.$matches[2]);
	return $count == 0?NULL:$info;
}

function get_diff($les_oud, $les_nieuw) {
	$dagen = array ('ma','di','wo','do','vr');
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

function logwijz($old_les_id, $new_les_id, $zermelo_id_orig, $file_id_basis = 0, $file_id_wijz = 0) {
	$old_les = mdb2_single_assoc("SELECT * FROM lessen WHERE les_id = %i", $old_les_id);
	$new_les = mdb2_single_assoc("SELECT * FROM lessen WHERE les_id = %i", $new_les_id);
	logit($_SERVER['PHP_AUTH_USER'].' file_id_{basis='.$file_id_basis.',wijz='.$file_id_wijz.'} '.$zermelo_id_orig.' '.get_diff($old_les, $new_les));
}

function lokaalwijzig($les, $oud_lokaal, $nieuw_lokaal, $file_id_basis, $file_id_wijz) {
	if (!$les) return;
	//echo('in onderstaande les '.$oud_lokaal[1].' -> '.$nieuw_lokaal[1]."\n");
	//print_r($les);
	$lokalen = explode_and_sort(',', $les['lokalen']);
	//print_r($lokalen);
	$lokaal_index = array_search($oud_lokaal[1], $lokalen);
	if ($lokaal_index === false) fatal_error("impossibru!");
	//echo("lokaal_index=$lokaal_index\n");
	$lokalen[$lokaal_index] = $nieuw_lokaal[1];
	//print_r($lokalen);
	sort($lokalen);
	$lokalen = implode(',', $lokalen);
	$nieuw_les_id = get_les_id($les['dag'], $les['uur'], $les['lesgroepen'], $les['vakken'], $les['docenten'], $lokalen, $les['notitie']);
	//echo("nieuwe_les_id=$nieuw_les_id\n");
	$nieuw_les = mdb2_single_assoc("SELECT * FROM lessen WHERE les_id = $nieuw_les_id");
	//print_r($nieuw_les);
	// staat deze les in het basisrooster?
	$basis_les_id = mdb2_single_val("SELECT les_id FROM files2lessen WHERE file_id = $file_id_basis AND zermelo_id = {$les['f_zid']}");
	//echo("basis_les_id=$basis_les_id\n");
	if ($basis_les_id == $nieuw_les_id) {
		echo("de nieuwe les staat in het basisrooster -> verwijder wijzigingen\n");
		mdb2_exec("DELETE FROM files2lessen WHERE file_id = $file_id_wijz AND zermelo_id = {$les['f_zid']} AND les_id = {$les['les_id']}");
	} else {
		echo("de nieuwe les staat niet in het basisrooster -> verwijder wijziging of pas aan\n");
		mdb2_exec("INSERT INTO files2lessen ( file_id, zermelo_id, les_id ) VALUES ( $file_id_wijz, {$les['f_zid']}, $nieuw_les_id )");
		mdb2_exec("DELETE FROM files2lessen WHERE file_id = $file_id_wijz AND zermelo_id = {$les['f_zid']} AND les_id != $nieuw_les_id");

	}
	logwijz($les['les_id'], $nieuw_les_id, $les['zermelo_id_orig'], $file_id_basis, $file_id_wijz);
}

?>
