<? require_once('common.php');

header('Content-Type: text/plain; charset=UTF-8');
header('Content-Disposition: inline; filename=log.txt;');

check_roostermaker($_POST['secret']);

function get_file_id($md5, $type, $status) {
	return mdb2_single_val("SELECT file_id FROM files WHERE file_md5 = '$md5' AND file_type = $type AND file_status = $status");
}

function add_zermelo_id($zermelo_id_orig) {
	global $zermelo_ids;
	if ($zermelo_id_orig == '') return;

	if (isset($zermelo_ids[$zermelo_id_orig])) return $zermelo_ids[$zermelo_id_orig];

	mdb2_exec("INSERT INTO zermelo_ids ( zermelo_id_orig ) VALUES ( '%q' )", $zermelo_id_orig);
	$id = mdb2_single_val("SELECT zermelo_id FROM zermelo_ids WHERE zermelo_id_orig = '%q'", $zermelo_id_orig);
	$zermelo_ids[$zermelo_id_orig] = $id;
	return $id;
}

function add_entity($name, $type) {
	global $entities;
	if ($name == '') return;

	if (isset($entities[strtoupper($name)])) {
		$oldtype = $entities[strtoupper($name)][1];
		if ($oldtype == $type || ($oldtype == STAMKLAS && $type == LESGROEP) || ($oldtype == LESGROEP && $type == STAMKLAS)) return $entities[strtoupper($name)][0];
		else {
			$oldtype_name = show_type($oldtype);
			$type_name = show_type($type);
			// this corrects the case of the old name
			$old_name = mdb2_single_val("SELECT entity_name FROM entities WHERE entity_name = '%q'", $name);
			fatal_error('basisrooster has two entities of same name "'.$name.'", lokalen, docenten, leerlingen (nummers), '.
			'lesgroepen (ook stamklassen en categorieen) mogen niet dezelfde naam hebben, bestaande entity '.$old_name.' ('.$oldtype_name.'), toegevoegde entity '.$name.' ('.$type_name.')');
		}
	} 
	mdb2_exec("INSERT INTO entities ( entity_name, entity_type, entity_active ) VALUES ( '%q', %i, 1 )", $name, $type);
	$entity_id = mdb2_last_insert_id();

	$entities[strtoupper($name)] = array( $entity_id, $type );
	return $entity_id;
}

$grp2ppl = NULL;

function add_basis_grp2ppl($lesgroep_id, $ppl_id, $file_id_basis, $naam = NULL) {
	global $grp2ppl, $ppl2categorie;
	if (isset($grp2ppl[$lesgroep_id][$ppl_id])) return;

	if (preg_match('/^(.*?)\./', $naam, $match)) {
		if (isset($ppl2categorie[$ppl_id])) {
		       if ($ppl2categorie[$ppl_id] != $match[1]) return; // leerling kan hier niet worden geplaatst
		} else $ppl2categorie[$ppl_id] = $match[1];
	}

	mdb2_exec("INSERT INTO grp2ppl ( lesgroep_id, ppl_id, file_id_basis ) VALUES ( $lesgroep_id, $ppl_id, $file_id_basis )");
	$grp2ppl[$lesgroep_id][$ppl_id] = 1;
}

// soms staan er dingen dubbel in een les, niet over struikelen, maar gewoon doorgaan
function add_entities2lessen($entity_id, $les_id) {
	mdb2_exec("INSERT IGNORE INTO entities2lessen ( entity_id, les_id ) VALUES ( $entity_id, $les_id )");
}

// deze functie wordt alleen gebruikt om roosterwijzigingen in het oude
// roosterwijzigingen_wk??.txt format te lezen, in het nieuwe format
// wordt er gewerkt met lesuren in plaats van tijden (het enige voordeel...)
function get_uur($uur) {
        /*switch ($uur) {
	        case '08:30': return 1;
       		case '09:20': return 2;
        	case '10:10': return 3;
        	case '11:20': return 4;
        	case '12:10': return 5;
        	case '13:30': return 6;
        	case '14:20': return 7;
        	case '15:10': return 8;
        	case '16:00': return 9;
        	default: fatal_error('onbekend lesuur '.$uur);
}*/
	/* Heemlanden gebruikt Zermelo 2, overige gebruikers waar ik van weet
	 * zijn op Zermelo 3, alleen Heemlanden gebruikt deze functie op dit moment */
	switch ($uur) {
		case '08:20': return 1;
		case '08:30': return 1;
		case '09:40': return 2;
		case '09:45': return 2;
		case '10:55': return 3;
		case '11:10': return 3;
		case '11:20': return 3;
		case '12:20': return 4;
		case '12:30': return 4;
		case '12:35': return 4;
		case '13:45': return 5;
		case '13:50': return 5;
		case '14:00': return 5;
		case '15:00': return 6;
		case '15:10': return 6;
		case '15:15': return 6;
		case '15:45': return 7;
		case '16:25': return 7;
		default: fatal_error('onbekend lesuur '.$uur);
	}
}

function get_uur_udmz($uur) {
	if (config('LESUUR_FORMAT') == 'standaard') {
		if (preg_match('/u(\d+)/', $uur, $matches)) return $matches[1];
	} else if (config('LESUUR_FORMAT') == 'numeriek') {
		if (preg_match('/(\d+)/', $uur, $matches)) return $matches[1];
	} else fatal_error('ongeldige waarde van configuratieparameter LESUUR_FORMAT');
	fatal_error('onbekend lesuur '.$uur.' pas eventueel LESUUR_FORMAT aan');
}

function get_dag($dag) {
        switch ($dag) {
        	case 'ma': return 1;
        	case 'di': return 2;
        	case 'wo': return 3;
        	case 'do': return 4;
        	case 'vr': return 5;
        	default: fatal_error('onbekende dag '.$dag);
        }
}

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

function cleanup_lesgroepen($lesgroep) {
	// 1A.1A2A wordt 1A2A als er een stamklas met naam 1A2A bekend is in categorie 1A
	global $stamz;
	if (preg_match('/(\w+)\.(\w+)/', $lesgroep, $matches)) {
		if (config('IGNORE_BEFORE_DOT') == 1) return $matches[2];
		if (isset($stamz[$matches[2]]) && (config('DISABLE_INLEZEN_CATEGORIEEN') == 'true' || $stamz[$matches[2]] == $matches[1])) return $matches[2];
	}

	return $lesgroep;
}

function addslash($string) {
	if ($string == '') return '';
	return '/'.$string;
}

// Zermelo geeft in het nieuwe format van de roosterwijzigingen de zermelo_id
// er niet meer bij, het systeem moet nu in het basisrooster gaan zoeken welke les er
// eigenlijk wijzigt, merk op dat als een les aan meerdere lesgroepen wordt gegeven, deze
// groepen worden gescheiden door een slash '/', maar als er meerdere doceten/lokalen/vakken
// betrokken zijn, dan worden deze gescheiden door een spatie ' '
function find_les($separator, $dag0, $uur0, $vakken0, $lesgroepen0, $docenten0, $lokalen0, $basis_id) {
	$dag = ($dag0 == '')?0:get_dag($dag0);
	$uur = ($uur0 == '')?0:get_uur_udmz($uur0);
	if ($uur > config('MAX_LESUUR')) logit("lesuur in rooster ($uur) groter dan MAX_LESUUR");
	$lesgroepen1 = implode(',', $lesgroepen = array_map('cleanup_lesgroepen', explode_and_sort($separator, $lesgroepen0)));
	$vakken1 = implode(',', $vakken = explode_and_sort(' ', $vakken0));
	$docenten1 = implode(',', $docenten = explode_and_sort(' ', $docenten0));
	$lokalen1 = implode(',', $lokalen = explode_and_sort(' ', $lokalen0));

	//echo("zoek basis_id = $basis_id:  $dag $uur $vakken1 $lesgroepen1 $docenten1 $lokalen1\n");
	return mdb2_single_val(<<<EOQ
SELECT zermelo_id FROM lessen
JOIN files2lessen ON files2lessen.les_id = lessen.les_id AND file_id = $basis_id
WHERE dag = $dag AND uur = $uur AND vakken = '%q'
AND lesgroepen = '%q' AND docenten = '%q' AND lokalen = '%q'
EOQ
	, $vakken1, $lesgroepen1, $docenten1, $lokalen1);
	exit;
}

// deze functie is tijdelijk toegevoed, omdat zermelo (in haar oneindige
// wijsheid) in de nieuwe roosterwijzigingen_wk??.txt bestanden voor GROEPEN de '/'
// als separator gerbruikt, maar voor vakken, docenten een lokalen een spatie
function insert_les_nieuw($zermelo_id, $dag0, $uur0, $vakken0, $lesgroepen0, $docenten0, $lokalen0, $file_id, $notitie) {
	$dag = ($dag0 == '')?0:get_dag($dag0);
	$uur = ($uur0 == '')?0:get_uur_udmz($uur0);
	if ($uur > config('MAX_LESUUR')) logit("lesuur in rooster ($uur) groter dan MAX_LESUUR");
	$lesgroepen1 = implode(',', $lesgroepen = array_map('cleanup_lesgroepen', explode_and_sort('/', $lesgroepen0)));
	$vakken1 = implode(',', $vakken = explode_and_sort(' ', $vakken0));
	$docenten1 = implode(',', $docenten = explode_and_sort(' ', $docenten0));
	$lokalen1 = implode(',', $lokalen = explode_and_sort(' ', $lokalen0));
	// als er een dollarteken in een notitie voorkomt,
	// gooi het en alles erna (en whitespace ervoor) dan weg
	if (($clean = strstr($notitie, '$', true)) !== false) $notitie = trim($clean);

	// we hebben een lock, dus er is geen race condition

	// hebben we deze les al?
	$les_id = mdb2_single_val(<<<EOQ
SELECT les_id FROM lessen
WHERE dag = $dag AND uur = $uur AND vakken = '%q'
AND lesgroepen = '%q' AND docenten = '%q' AND lokalen = '%q' AND notitie = '%q'
EOQ
, $vakken1, $lesgroepen1, $docenten1, $lokalen1, $notitie);

	if (!$les_id) { // deze les is nieuw
		mdb2_exec(<<<EOT
INSERT INTO lessen ( dag, uur, vakken, lesgroepen, docenten, lokalen, notitie )
VALUES ( $dag, $uur, '%q', '%q', '%q', '%q', '%q' )
EOT
, $vakken1, $lesgroepen1, $docenten1, $lokalen1, $notitie);
		$les_id = mdb2_last_insert_id();
		$entity_ids = array();

		// FIXME schrap dl.+ van vakken
		$vakken = array_map('addslash', $vakken);

		if ($lesgroepen0) foreach ($lesgroepen as $naam) $entity_ids[] = add_entity($naam, LESGROEP);
		if ($vakken0) foreach ($vakken as $naam) $entity_ids[] = add_entity($naam, VAK);
		if ($docenten0) foreach ($docenten as $naam) $entity_ids[] = add_entity($naam, DOCENT);
		if ($lokalen0) foreach ($lokalen as $naam) $entity_ids[] = add_entity($naam, LOKAAL);

		foreach ($entity_ids as $entity_id) add_entities2lessen($entity_id, $les_id);
	}
	mdb2_exec("INSERT IGNORE INTO files2lessen ( file_id, zermelo_id, les_id ) VALUES ( $file_id, $zermelo_id, $les_id )");
}

function insert_les($separator, $zermelo_id, $dag0, $uur0, $vakken0, $lesgroepen0, $docenten0, $lokalen0, $file_id, $notitie) {
	$dag = ($dag0 == '')?0:get_dag($dag0);
	$uur = ($uur0 == '')?0:(($separator == '/')?get_uur($uur0):get_uur_udmz($uur0));
	if ($uur > config('MAX_LESUUR')) logit("lesuur in rooster ($uur) groter dan MAX_LESUUR");
	$lesgroepen1 = implode(',', $lesgroepen = array_map('cleanup_lesgroepen', explode_and_sort($separator, $lesgroepen0)));
	$vakken1 = implode(',', $vakken = explode_and_sort($separator, $vakken0));
	$docenten1 = implode(',', $docenten = explode_and_sort($separator, $docenten0));
	$lokalen1 = implode(',', $lokalen = explode_and_sort($separator, $lokalen0));
	// als er een dollarteken in een notitie voorkomt,
	// gooi het en alles erna (en whitespace ervoor) dan weg
	if (($clean = strstr($notitie, '$', true)) !== false) $notitie = trim($clean);

	// we hebben een lock, dus er is geen race condition

	// hebben we deze les al?
	$les_id = mdb2_single_val(<<<EOQ
SELECT les_id FROM lessen
WHERE dag = $dag AND uur = $uur AND vakken = '%q'
AND lesgroepen = '%q' AND docenten = '%q' AND lokalen = '%q' AND notitie = '%q'
EOQ
, $vakken1, $lesgroepen1, $docenten1, $lokalen1, $notitie);

	if (!$les_id) { // deze les is nieuw
		mdb2_exec(<<<EOT
INSERT INTO lessen ( dag, uur, vakken, lesgroepen, docenten, lokalen, notitie )
VALUES ( $dag, $uur, '%q', '%q', '%q', '%q', '%q' )
EOT
, $vakken1, $lesgroepen1, $docenten1, $lokalen1, $notitie);
		$les_id = mdb2_last_insert_id();
		$entity_ids = array();

		// FIXME schrap dl.+ van vakken
		$vakken = array_map('addslash', $vakken);

		if ($lesgroepen0) foreach ($lesgroepen as $naam) $entity_ids[] = add_entity($naam, LESGROEP);
		if ($vakken0) foreach ($vakken as $naam) $entity_ids[] = add_entity($naam, VAK);
		if ($docenten0) foreach ($docenten as $naam) $entity_ids[] = add_entity($naam, DOCENT);
		if ($lokalen0) foreach ($lokalen as $naam) $entity_ids[] = add_entity($naam, LOKAAL);

		foreach ($entity_ids as $entity_id) add_entities2lessen($entity_id, $les_id);
	}
	mdb2_exec("INSERT INTO files2lessen ( file_id, zermelo_id, les_id ) VALUES ( $file_id, $zermelo_id, $les_id )");
}

function insert_name($id, $firstname, $prefix, $surname, $email = '') {
	global $names;

        $name = $firstname;
        if ($prefix != '') $name .= ' '.$prefix;
        $name .= ' '.$surname;

	if (isset($names[$id])) {
		if ($name != $names[$id]) {
			mdb2_exec(<<<EOT
UPDATE names SET name = '%q', firstname = '%q', prefix = '%q', surname = '%q', email = '%q' WHERE entity_id = $id
EOT
			, $name, $firstname, $prefix, $surname, $email);
		}
	} else {
		mdb2_exec(<<<EOT
INSERT INTO names ( entity_id, name, firstname, prefix, surname, email )
VALUES ( $id, '%q', '%q', '%q', '%q', '%q' )
EOT
		, $name, $firstname, $prefix, $surname, $email);
		$names[$id] = $name;
	}
}

function lock_renew_helper($state, $ratio = 'false') {
	if ($ratio != 'false') $ratio *= 100;
	set_time_limit(120); // reset time limit
	lock_renew('{ "state": '.$state.', "perc": '.$ratio.' }');
}

function incdone(&$done, $total, $state) {
	$done++;
	if (!($done%20)) lock_renew_helper($state, $done/$total);
}

function checkset($array, $name, $fields) {
	foreach ($fields as $field) if (!isset($array[$field])) {
		logit("required field $field missing from $name");
		return false;
	}
	return true;
}

function import_basisrooster($file_id, $tmp_name) {
	global $grp2ppl, $stamz;
	lock_renew_helper(1);

	$udmz = read_udmz_file($tmp_name);

	// a previous update may have gone wrong, cleanup just in case
	mdb2_exec("DELETE FROM files2lessen WHERE file_id = $file_id");
	mdb2_exec("DELETE FROM grp2ppl WHERE file_id_basis = $file_id");
	mdb2_exec("DELETE FROM grp2grp WHERE file_id_basis = $file_id");

	if (count($udmz['Tijdpatroon']['Tijdvak']) > 1) {
		if (config('HALFSLACHTIGE_TIJDVAKKEN') == 'false') {
			logit('geuploade udmz bevat meer dan 1 tijdvak, ez-roosterbord kan daar (nog) niks mee');
			return;
		} else if (!isset($_POST['tijdvak']) || $_POST['tijdvak'] == '') {
			logit('geen tijdvak gegeven bij upload');
			return;
		} else if (!isset($udmz['Tijdpatroon']['Tijdvak'][$_POST['tijdvak']])) {
			logit("tijdvak met naam '{$_POST['tijdvak']} niet gevonden in udmz");
			return;
		}
	}

	// eerst lopen we alle leerlingen langs
	// dan alle docenten
	// dan alle groepen
	// en als laatste: alle lessen

	if (!checkset($udmz, 'udmz file', array('Groep', 'Leerling', 'Docent', 'Les'))) return;

	$leerlingen = 0;
	$categorieen_leerling = 0;
	foreach ($udmz['Leerling'] as $category => $value) {
		if (in_array($category, config('ZERMELO_CATEGORY_IGNORE'))) continue;
		$categorieen_leerling++;
		$leerlingen += count($value);
	}
	$docenten = count($udmz['Docent']);
	$groepen = 0;
	$categorieen_groep = 0;
	foreach ($udmz['Groep'] as $category => $value) {
		if (in_array($category, config('ZERMELO_CATEGORY_IGNORE'))) continue;
		$categorieen_groep++;
		foreach ($value as $id => $row) {
			if (in_array($id, config('ZERMELO_GROUP_IGNORE'))) continue;
			$groepen++;
		}
	}
	$lessen = count($udmz['Les']);

	/*
	logit('Categorieen leerling: '.$categorieen_leerling);
	logit('Leerlingen: '.$leerlingen);
	logit('Docenten: '.$docenten);
	logit('Categorieen groep: '.$categorieen_groep);
	logit('Groepen: '.$groepen);
	logit('Lessen: '.$lessen);
	*/

	$total = $categorieen_leerling +
		$leerlingen + $categorieen_groep +
		$groepen + $docenten + $lessen;
	$done = 0;

	lock_renew_helper(2, $done/$total);

	foreach ($udmz['Leerling'] as $category => $list) {
		if (in_array($category, config('ZERMELO_CATEGORY_IGNORE'))) continue;
		incdone($done, $total, 2);

		if (config('DISABLE_INLEZEN_CATEGORIEEN') != 'true')
			if (!($category_id = add_entity($category, CATEGORIE))) return;

		foreach ($list as $id => $row) {
			incdone($done, $total, 2);
			if (!checkset($row, "Leerling.$category", array('LASTNAME', 'FIRSTNAME',
				'BETWEENNAME', 'BASICCLASS'))) return;

			if (!($leerling_id = add_entity($id, LEERLING))) return;
			insert_name($leerling_id, $row['FIRSTNAME'],
				$row['BETWEENNAME'], $row['LASTNAME']);

			if (!($lesgroep_id = add_entity($row['BASICCLASS'], STAMKLAS)))
				return;

			$stamz[$row['BASICCLASS']] = $category;

			add_basis_grp2ppl($lesgroep_id, $leerling_id, $file_id);
			if (config('DISABLE_INLEZEN_CATEGORIEEN') != 'true')
				add_basis_grp2ppl($category_id, $leerling_id, $file_id);
		}
	}

	lock_renew_helper(2, $done/$total);

	foreach ($udmz['Docent'] as $id => $row) {
		incdone($done, $total, 2);
		if (!checkset($row, 'Docent', array ('Voornaam', 'Tussenvoegsel',
			'Achternaam', 'e-mail'))) continue;

		if (!($docent_id = add_entity($id, DOCENT))) return;
		if ($row['Achternaam'] != '' && $row['Voornaam'] != '')
			insert_name($docent_id, substr($row['Voornaam'], 0, 1).'.',
				$row['Tussenvoegsel'],
				$row['Achternaam'], $row['e-mail']);
	}

	lock_renew_helper(2, $done/$total);

	foreach ($udmz['Groep'] as $category => $list) {
		if (in_array($category, config('ZERMELO_CATEGORY_IGNORE'))) continue;
		incdone($done, $total, 2);

		if (config('DISABLE_INLEZEN_CATEGORIEEN') != 'true')
			if (!($category_id = add_entity($category, CATEGORIE))) return;

		foreach ($list as $id => $row) {
			if (in_array($id, config('ZERMELO_GROUP_IGNORE'))) continue;
			incdone($done, $total, 2);
			if (isset($stamz[$id])) {
				if ($stamz[$id] != $category) logit('stamklas in andere categorie?!?!?');
				else continue; // doe niks, want stamklassen hebben we al
			}

			if (!checkset($row, "Groep.$category", array('SET'))) return;

			if (!($lesgroep_id = add_entity((config('IGNORE_BEFORE_DOT')?$id:$category.'.'.$id), LESGROEP))) return;

			if ($row['SET'] == '') continue; // geen leerlingen in deze groep

			foreach (explode(',', $row['SET']) as $leerlingnummer) {
				if (!($leerling_id = add_entity($leerlingnummer, LEERLING))) return;
				add_basis_grp2ppl($lesgroep_id, $leerling_id, $file_id);
			}
		}
	}


	lock_renew_helper(2, $done/$total);

	mdb2_exec(<<<EOT
INSERT INTO grp2grp ( lesgroep_id, lesgroep2_id, file_id_basis )
SELECT DISTINCT grp2ppl.lesgroep_id, grp2ppl2.lesgroep_id, grp2ppl.file_id_basis
FROM grp2ppl
JOIN grp2ppl AS grp2ppl2 ON grp2ppl.ppl_id = grp2ppl2.ppl_id AND grp2ppl.file_id_basis = grp2ppl2.file_id_basis
WHERE grp2ppl.file_id_basis = $file_id
EOT
        );

	lock_renew_helper(2, $done/$total);

	foreach ($udmz['Les'] as $id => $row) {
		incdone($done, $total, 2);
		if (!checkset($row, 'Les', array('#WijzigComment', 'Dag', 'Uur', 'Vak', 'Grp', 'Doc', 'Lok', 'Tdv', 'IGNORED'))) return;

		// negeer lessen uit een ander tijdvak
		if (config('HALFSLACHTIGE_TIJDVAKKEN') == 'true' && $row['Tdv'] != $_POST['tijdvak']) continue;

		// negeer lessen die in het basisrooster op 'UITVAL' staan
		if (preg_match('/\<UITVAL\>/', $row['IGNORED'])) {
			logit('LES '.$id.' staat op \'uitval\' in basisrooster?!?!?');
			continue;
		}

		if (!($zermelo_id = add_zermelo_id($id))) return;
		insert_les(',', $zermelo_id, $row['Dag'], $row['Uur'], $row['Vak'], $row['Grp'], $row['Doc'], $row['Lok'], $file_id, $row['#WijzigComment']);
	}

	// als we hier zijn, dan is alles goed gegaan
	mdb2_exec("UPDATE files SET file_status = 1 WHERE file_id = $file_id");
	lock_renew_helper(3, $done/$total);
}

function import_wijzigingen($file_id, $week, $tmp_name, $basis_id) {
	global $wijz, $stamz;
	unset($GLOBALS['wijz']);
	$wijz = array();
	lock_renew_helper(1);

	// fill stamz array, so stuff like 2A.2A1A can be changed to 2A1A
	if (config('DISABLE_INLEZEN_CATEGORIEEN') != 'true') {
		$stamz = mdb2_all_assoc_rekey(<<<EOQ
SELECT entities.entity_name, entities2.entity_name AS value
FROM entities
JOIN grp2grp ON grp2grp.lesgroep_id = entities.entity_id AND grp2grp.file_id_basis = $basis_id
JOIN entities AS entities2 ON entities2.entity_id = grp2grp.lesgroep2_id AND entities2.entity_type = %i
WHERE entities.entity_type = %i
EOQ
, CATEGORIE, STAMKLAS);
	} else {
		$stamz = mdb2_all_assoc_rekey(<<<EOQ
SELECT entities.entity_name, 1 AS value
FROM entities
JOIN grp2grp ON grp2grp.lesgroep_id = entities.entity_id AND grp2grp.file_id_basis = $basis_id
WHERE entities.entity_type = %i
EOQ
, STAMKLAS);
	}

	// als de roostermakers roosterwijzigingen wissen, dan zou de wijzigingenfile kleiner moeten worden
	// zermelo doet overwrite zonder truncate, na het wissen van roosterwijzigingen kunnen secties dubbel
	// voorkomen. Dat negeren we, want we hebben alleen de eerste sectie nodig
	read_all_sections($sections, $tmp_name, true);
	if ($sections === false) fatal_error('read error on '.$tmp_name.', unable to perform update');

	// a previous update may have gone wrong, cleanup just in case
	mdb2_exec("DELETE FROM files2lessen WHERE file_id = $file_id");

	//logit('no_sections='.count($sections));
	//foreach ($sections as $section => $lines) {
	//	logit('section name '.$section.', '.count($lines).' lines');
	//}

	$total = count($sections['PREAMBULE']);
	$done = 0;

	lock_renew_helper(2, 0);

	foreach ($sections['PREAMBULE'] as $atoms) {
		incdone($done, $total, 2);
		$old = explode(',', $atoms[1]);

		$max = count($atoms) - 1;

		if ($max > 1) $new = explode(',', $atoms[2]);
		//echo("nieuwe wijziging, aantal entries: $max\n");
		//print_r($old);
		//print_r($new);
		if ($max < 5) {
			// nieuw format roosterwijzigingen?
			if ($max < 1) logit('te weinig records in roosterwijziging');

			if ($max == 3) {
				$notitie = $atoms[3];
				logit("notitie van roostermakers: ".$notitie);
			} else $notitie = NULL;

			if ($atoms[1] == '' && $atoms[2] == '') {
				// dit is een wijziging van niks naar niks WTF?!?!?
				continue;
			}

			if ($max < 2 || $atoms[2] == '') { // lesuitval
				//logit("lesuitval {$atoms[1]}");
				if (!preg_match('/^([a-z]{2}) (u\d+)$/', $old[2], $matches)) {
					logit("ongeldig format voor 'dag uur'-veld in wijzigingen file: {$old[2]}");
					continue;
				}
				$dag = $matches[1];
				$uur = $matches[2];
				$zermelo_id = find_les('/', $dag, $uur, $old[1], $old[0], $old[3], $old[4], $basis_id);
				if (!$zermelo_id) {
					logit("oorspronkelijke les {$atoms[1]} niet gevonden?!?! wijziging {$atom[0]} kan niet worden ingelezen");
					continue;
				}
				//echo('zermelo_id = '.find_les('/', $dag, $uur, $old[1], $old[0], $old[3], $old[4], $basis_id)."\n");
				insert_les('/', $zermelo_id, '', '', '', '', '', '',  $file_id, $notitie);
			} else {
				//logit("echte wijziging {$atoms[1]} {$atoms[2]}");

				// we zetten de zermelo_id vast op 0, alsof er sprake is van een extra les
				// mocht er een oude les zijn, dan passen we de zermelo_id aan
				$zermelo_id = 0;

				if ($atoms[1] != '') {
					//er is een oude les
					if (!preg_match('/^([a-z]{2}) (u\d+)$/', $old[2], $matches)) {
						logit("ongeldig format voor 'dag uur'-veld in wijzigingen file: {$old[2]}");
						continue;
					}
					$dag = $matches[1];
					$uur = $matches[2];
					$zermelo_id = find_les('/', $dag, $uur, $old[1], $old[0], $old[3], $old[4], $basis_id);
					if (!$zermelo_id) {
						logit("oorspronkelijke les {$atoms[1]} niet gevonden?!?! wijziging {$atom[0]} kan niet worden ingelezen");
						continue;
					}
					//echo('zermelo_id = '.find_les('/', $dag, $uur, $old[1], $old[0], $old[3], $old[4], $basis_id)."\n");
				}

				if (!preg_match('/^([a-z]{2}) (u\d+)$/', $new[2], $matches)) {
					logit("ongeldig format voor 'dag uur'-veld in wijzigingen file: {$old[2]}");
					continue;
				}
				$dag = $matches[1];
				$uur = $matches[2];

				insert_les_nieuw($zermelo_id, $dag, $uur, $new[1], $new[0], $new[3], $new[4],  $file_id, $notitie);
			}
			continue;
		}

		if ($max > 6) {
			logit('(te?) veel records in roosterwijziging');
		}

		if ($atoms[$max - 1] != 'WEEKNUMMERS') {
			logit("onverwachte data in op een-na-laatste record van wijzigingen, verwacht WEEKNUMMERS: ");
			print_r($atoms);
			continue;
		}

/*
		if ($atoms[$max] != $week) {
			logit("onverwachte data in laatste record van wijzigingen, verwacht $week");
			return;
		}
*/

		if ($atoms[1] == '0' && $atoms[2] == '0') {
			// dit is een wijziging van niks naar niks WTF?!?!?
			continue;
		}

		if ($max == 6) {
			$notitie = $atoms[4];
			//logit("notitie van roostermakers: ".$atoms[4]);
		} else $notitie = NULL;

		if ($atoms[2] == '0') { // lesuitval
			if (!($zermelo_id = add_zermelo_id($old[0]))) return;
			insert_les('/', $zermelo_id, '', '', '', '', '', '', $file_id, $notitie);
		} else {
			// echte wijziging?
			if (count($new) != 9 || ($atoms[1] != 0 && count($old) != 9)) {
				print_r($old);
				print_r($new);
				logit('rare leswijziging, deze negeren we');
				continue;
			}
			if (!($zermelo_id = add_zermelo_id($new[0]))) return;

			insert_les('/', $zermelo_id, $new[7], $new[5], $new[4], $new[1], $new[2], $new[3], $file_id, $notitie);
		}
	}
	//exit;

	// als we hier zijn, dan is alles goed gegaan
	mdb2_exec("UPDATE files SET file_status = 1 WHERE file_id = $file_id");
	lock_renew_helper(3, $done/$total);
}

uploadcomplain($_FILES['uploadedfile']);

if ($_FILES['uploadedfile']['size'] == 0) fatal_error('file with no content uploaded, impossible!');

$filename = $_FILES['uploadedfile']['name'];

function shutdown_function() {
	lock_release();
	if (connection_aborted()) {
		fatal_error('user cancel');
	}
}

register_shutdown_function('shutdown_function');
if (!lock_acquire('{ "state": 1, "perc": 0 }', $_POST['randid'])) fatal_error('er is al een update bezig, even geduld AUB');

// cache contents of table entities
$entities = mdb2_all_ordered_rekey('SELECT UPPER(entity_name), entity_id, entity_type FROM entities');

// cache een lijst leerlingen van wie we de naam al weten
$names = mdb2_all_ordered_rekey('SELECT entity_id, name FROM names');

// cache een lijst met zermelo_id's
$zermelo_ids = mdb2_all_assoc_rekey('SELECT zermelo_id_orig, zermelo_id FROM zermelo_ids');

$stamz = array();

function move_upload($bw, $md5, $week) {
	$new_filename = config('DATADIR').$md5;
	logit($_FILES['uploadedfile']['name'].' -> '.$md5);
	if (!move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $new_filename)) {
		logit('unable to store uploaded file for future reference');
	}
	return $new_filename;
}

if ($_POST['type'] == 'wijz' && preg_match('/^roosterwijzigingen_wk(\d+).txt$/', $filename, $matches)) {
	$week = $matches[1];
	//logit('ontvangen roosterwijzigingen van week '.$week);
	$week_id = mdb2_single_val("SELECT week_id FROM weken WHERE week = %i", $week);
	if (!$week_id) fatal_error('wijzingingen geupload van een week die geen lesweek is?!?!');
	$basis_id = mdb2_single_val("SELECT basis_id FROM roosters WHERE week_id = $week_id AND wijz_id = 0 ORDER BY rooster_id DESC LIMIT 1");
	$basis_file_id =  mdb2_single_val("SELECT file_id FROM roosters WHERE week_id = $week_id AND wijz_id = 0 ORDER BY rooster_id DESC LIMIT 1");
	if (!$basis_id) fatal_error('geen basisrooster beschikbaar in deze week, dus er kunnen geen wijzingen op');

	$md5 = calc_md5($_FILES['uploadedfile']['tmp_name']);
	$file_id = get_file_id($md5, 2, 1);
	if (!$file_id)  { 
		$file_id = get_file_id($md5, 2, 0);
		if ($file_id) logit('deze wijzigingen kennen we al, maar import is eerder mis gegaan');
		else {
			mdb2_exec("INSERT INTO files ( file_name, file_md5, file_time, file_type, file_status ) VALUES ( '%q', '$md5', %i, 2, 0 )", $filename, time());
			$file_id = get_file_id($md5, 2, 0);
		}
		$new_filename = move_upload('wijz', $md5, $week);
		import_wijzigingen($file_id, $week, $new_filename, $basis_file_id);
		$status = mdb2_single_val("SELECT file_status FROM files WHERE file_id = $basis_file_id");
		if (!$status) fatal_error('de import is fout gegaan, we kunnen deze wijzigingen niet publiceren :(, mail r.snel@ovc.nl');

		//logit('import succesvol, nu nog koppelen aan weken');
	} else {
		//logit('file was al succesvol geimporteerd');
	}
	$wijz_id = mdb2_single_val("SELECT MAX(wijz_id) FROM roosters WHERE week_id = $week_id AND basis_id = $basis_id");
	if (!$wijz_id) $wijz_id = 0;
	if (!mdb2_single_val("SELECT rooster_id FROM roosters WHERE basis_id = $basis_id AND file_id = $file_id AND wijz_id = $wijz_id")) {
		$wijz_id++;
		mdb2_exec("INSERT INTO roosters ( week_id, file_id, basis_id, wijz_id, timestamp ) VALUES ( $week_id, $file_id, $basis_id, $wijz_id, %i )", time());
		logit($md5.' week='.$week.' basis_id='.$basis_id.' wijz_id='.$wijz_id);
	} else fatal_error('deze wijzigigen hebben we al op deze week bij het meest recente basisrooster dat geldt voor deze week');
		

} else if ($_POST['type'] == 'basis' && preg_match('/_(\d+).udmz$/', $filename, $matches)) {
	$version = $matches[1];
	//logit('ontv basis '.$year_start.'/'.$year_end.' versie '.$version);
	if (mdb2_single_val("SELECT rooster_id FROM roosters WHERE week_id = %i", $_POST['week_id']) && isset($_POST['overwrite']) && $_POST['overwrite'] != 'true')
		fatal_error("er staat al een basisrooster in deze week, vink de checkbox aan als je wilt overschrijven");

	$md5 = calc_md5($_FILES['uploadedfile']['tmp_name']);
	$file_id = get_file_id($md5, 1, 1);
	$week = mdb2_single_val("SELECT week FROM weken WHERE week_id = %i",
		$_POST['week_id']);

	if (!$file_id) {
		$file_id = get_file_id($md5, 1, 0);
		if ($file_id) logit('dit basisrooster kennen we al, maar de import is eerder mis gegaan');
		else {
			mdb2_exec("INSERT INTO files ( file_name, file_md5, file_time, file_type, file_status, file_version ) VALUES ( '%q', '$md5', %i, 1, 0, $version )", $filename, time());
			$file_id = get_file_id($md5, 1, 0);
		}
		$new_filename = move_upload('basis', $md5, $week);
		import_basisrooster($file_id, $new_filename);
		$status = mdb2_single_val("SELECT file_status FROM files WHERE file_id = $file_id");
		if (!$status) fatal_error('de import is fout gegaan, we kunnen dit rooster niet publiceren :(, mail r.snel@ovc.nl');
		//logit('import succesvol, nu nog koppelen aan weken');
	} else {
		//logit('file was al succesvol geimporteerd');
	}
	
	$basis_id = mdb2_single_val("SELECT MAX(basis_id) FROM roosters");
	if (!$basis_id) $basis_id = 0;
	// fixme, controleer of deze upload invloed heeft op de roosters
	// als er op geen enkel rooster invloed heeft (dit is het huidige basisrooster voor alle weken waarop deze upload van toepassing is en in geen enkele week
	// zijn wijzigingen ingelezen) dan moet er geen update worden geregistreerd
	$basis_id++;
	mdb2_exec("INSERT INTO roosters ( week_id, file_id, basis_id, wijz_id, timestamp ) VALUES ( %i, $file_id, $basis_id, 0, %i )", $_POST['week_id'], time());
	logit($md5.' week='.$week.' basis_id='.$basis_id.' wijz_id=0');
} else fatal_error('filename niet herkend, we verwachten een file van de vorm Schooljaar 2013-2014_234.udmz (basisrooster) of roosterwijzigingen_wk39.txt (wijzigingen)');

// lock automatically released in shutdown function
?>
