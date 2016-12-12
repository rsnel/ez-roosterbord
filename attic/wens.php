<?
require_once('common.php');
require_once('rquery.php');
require_once('common_edit.php');

// programma om docenten naar hun 'vaste' lokaal toe te 'ruilen'

exit;
check_roostermaker($_GET['secret']);
if (!get_enable_edit()) fatal_error('permission non');

if (isset($_GET['file_id_basis']) && $_GET['file_id_basis']) $file_id_basis = $_GET['file_id_basis'];
else fatal_error("parameter file_id_basis is required");
$file_basis = mdb2_single_assoc("SELECT * FROM files WHERE file_id = %i", $file_id_basis);

if (!$file_basis) fatal_error("invalid file_id_basis");

if (isset($_GET['file_id_wijz']) && $_GET['file_id_wijz']) $file_id_wijz = $_GET['file_id_wijz'];
else $file_id_wijz = 0;

$file_wijz = mdb2_single_assoc("SELECT * FROM files WHERE file_id = %i", $file_id_basis);
if (!$file_wijz) fatal_error("invalid file_id_basis");

$wens = array(
	'AMER' => '008',
	'ALTA' => '012',
	'ANTW' => '115',
	'BAGG' => '267',
	'BOED' => '234',
	'BOEL' => '104',
	'BOEM' => '154',
	'BOON' => '158',
	'BOSC' => '163',
	'BRAC' => '256',
	'BROI' => '232',
	'BUUL' => '231',
	'CAMP' => '243',
	'CEBA' => '156',
	'CORN' => '235',
	'CRAM' => '153',
	'CRAN' => '035',
	'DAMB' => '111',
	'DILL' => '250',
	'DRON' => '214',
	'EEDE' => '241',
	'EILE' => '230',
	'FADL' => '205',
	'FETT' => 'L02',
	'GOUW' => '242',
	'HARD' => '207',
	'HEND' => '258',
	'HOEF' => '030',
	'HOKK' => '013',
	'HULS' => 'L01',
	'ISEN' => '215',
	'JONG' => '015',
	'KEIJ' => '261',
	'KIKI' => '260',
	'KNOP' => '011',
	'KOUR' => '157',
	'KRUI' => '223',
	'KUYE' => '255',
	'LAND' => '010',
	'LEMM' => '253',
	'LEYT' => '266',
	'LUIJ' => '263',
	'MATH' => '038',
	'MESW' => '239',
	'MUNG' => '031',
	'MUSS' => '257',
	'NIJN' => '122',
	'OOST' => '014',
	'ORAN' => '228',
	'OTTE' => '160',
	'RIJS' => '106',
	'SCHP' => '107',
	'SMIT' => '053',
	'SNEL' => '155',
	'SNOE' => '226',
	'STRA' => '206',
	'STRU' => '233',
	'TAMA' => '123',
	'TEUN' => '152',
	'TOLL' => '009',
	'TURK' => '224',
	'VELF' => '162',
	'VOSW' => '066',
	'VRIE' => '125',
	'YEUN' => '227',
	'ZIJT' => '105',
	'ZIMY' => '130',
	'ZWAA' => '236',
	'ZWIP' => '238'
);
header('Content-type: text/plain');

//print_r($wens);

function wish_come_true($docent, $lokaal, $file_id_basis, $file_id_wijz) {
	$docent_info = mdb2_single_array("SELECT entity_id, entity_name FROM entities WHERE entity_name = '%q' AND entity_type = ".DOCENT, $docent);
	$lokaal_info = mdb2_single_array("SELECT entity_id, entity_name FROM entities WHERE entity_name = '%q' AND entity_type = ".LOKAAL, $lokaal);
	if (!$docent_info) fatal_error("docent $docent niet gevonden in DB");
	if (!$lokaal_info) fatal_error("docent $lokaal niet gevonden in DB");
	echo("docent_id={$docent_info[0]} ({$docent_info[1]}), lokaal_id={$lokaal_info[0]} ({$lokaal_info[1]})\n");
	$subquery_doc = rquery($docent_info[0], $docent_info[0], $file_id_basis, $file_id_wijz, 'LEFT ');
	$subquery_lok = rquery($lokaal_info[0], $lokaal_info[0], $file_id_basis, $file_id_wijz, 'LEFT ');

	// we willen alleen enkelvoudige lokalen
	$rooster_doc = mdb2_all_assoc_rekey(<<<EOQ
SELECT CONCAT(lessen.dag, '-', lessen.uur) lesuur, COUNT(les_id) count, zermelo_id_orig, base.f_zid, lessen.* FROM (
	$subquery_doc
) base
JOIN lessen ON lessen.les_id = base.f_id
JOIN zermelo_ids ON zermelo_ids.zermelo_id = base.f_zid
WHERE lesgroepen != '' AND ( wijz = 1 OR s_zid IS NULL ) AND lokalen NOT LIKE '%%,%%'
GROUP BY dag, uur
HAVING count = 1
EOQ
);
	print_r($rooster_doc);
	

	$rooster_lok = mdb2_all_assoc_rekey(<<<EOQ
SELECT CONCAT(lessen.dag, '-', lessen.uur) lesuur, COUNT(les_id) count, zermelo_id_orig, base.f_zid, lessen.* FROM (
	$subquery_lok
) base
JOIN lessen ON lessen.les_id = base.f_id
JOIN zermelo_ids ON zermelo_ids.zermelo_id = base.f_zid
WHERE ( wijz = 1 OR s_zid IS NULL )
GROUP BY dag, uur
EOQ
);
	print_r($rooster_lok);

	foreach ($rooster_doc as $lesuur => $info) {
		if ($info['lokalen'] == $lokaal) continue;
		$lokaal0_info = mdb2_single_array("SELECT entity_id, entity_name FROM entities WHERE entity_name = '%q' AND entity_type = ".LOKAAL, $info['lokalen']);
		if (isset($rooster_lok[$lesuur])) {
			/* give up of room is double booked */
			if ($rooster_lok[$lesuur]['count'] > 1) continue;
			echo("ruilen met andere les\n");
			lokaalwijzig($rooster_lok[$lesuur], $lokaal_info, $lokaal0_info, $file_id_basis, $file_id_wijz);
			lokaalwijzig($info, $lokaal0_info, $lokaal_info, $file_id_basis, $file_id_wijz);
		} else {
			/*
			echo("ruilen met leeg lokaal\n");
			echo("info=\n");
			print_r($info);
			echo("lokaal0_info=\n");
			print_r($lokaal0_info);
			echo("lokaal_info=\n");
			print_r($lokaal_info);*/
			lokaalwijzig($info, $lokaal0_info, $lokaal_info, $file_id_basis, $file_id_wijz);
		}
	}
}

foreach ($wens AS $doc => $lok) {
	//wish_come_true('SNEL', '155', $file_id_basis, $file_id_wijz);
	//wish_come_true('JONG', '015', $file_id_basis, $file_id_wijz);
	wish_come_true($doc, $lok, $file_id_basis, $file_id_wijz);
}
?>
