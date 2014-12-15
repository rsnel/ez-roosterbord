<? require_once('common.php');

check_roostermaker($_GET['secret']);

$file_id = mdb2_single_val(<<<EOQ
SELECT file_id FROM files
WHERE file_type = 1 AND file_status = 1
ORDER BY file_version DESC
LIMIT 1
EOQ
);

$res = mdb2_query(<<<EOQ
SELECT ll, name, stamklassen.entity_name klas, GROUP_CONCAT(CONCAT(doc, '/', bla) SEPARATOR ';') info FROM (
	SELECT ppl_id, ll, doc, GROUP_CONCAT(vakken ORDER BY vakken) bla FROM (
		SELECT DISTINCT ll.entity_id ppl_id, ll.entity_name ll, doc.entity_name doc, lessen.vakken
		FROM grp2ppl
		JOIN entities2lessen AS grp2les ON grp2les.entity_id = grp2ppl.lesgroep_id
		JOIN files2lessen ON files2lessen.les_id = grp2les.les_id AND file_id = $file_id
		JOIN lessen ON lessen.les_id = files2lessen.les_id
		JOIN entities2lessen AS doc2les ON doc2les.les_id = grp2les.les_id AND file_id = $file_id
		JOIN entities AS doc ON doc.entity_id = doc2les.entity_id AND doc.entity_type = %i
		JOIN entities AS ll ON ll.entity_id = grp2ppl.ppl_id
		WHERE file_id_basis = $file_id
	) AS lijst
	GROUP BY ppl_id, doc
) AS lijst
JOIN names ON names.entity_id = ppl_id
JOIN grp2ppl ON grp2ppl.ppl_id = lijst.ppl_id AND file_id_basis = $file_id
JOIN entities AS stamklassen ON stamklassen.entity_id = grp2ppl.lesgroep_id AND stamklassen.entity_type = %i
GROUP BY lijst.ppl_id
ORDER BY klas, surname, firstname, prefix
EOQ
, DOCENT, STAMKLAS);

$legenda = array();
$legenda_rev = array();
$legenda_rev[0] = 'llnr';
$legenda_rev[1] = 'naam';
$legenda_rev[2] = 'klas';
$legenda_count = 3;
$lln = array();
while (($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC))) {
	$data = array();
	$data[] = $row['ll'];
	$data[] = $row['name'];
	$data[] = $row['klas'];
	foreach (explode(';', $row['info']) as $docvak_packed) {
		$docvak = explode('/', $docvak_packed);
		if (!isset($legenda[$docvak[0]])) {
			$legenda[$docvak[0]] = $legenda_count++;
			$legenda_rev[] = $docvak[0];
		}

		$data[$legenda[$docvak[0]]] = $docvak[1];
	}
	$lln[] = $data;
}

/* we'll send a .csv file */
header("Content-type: text/csv");

/* some red tape to avoid bugs and weird errormessages in IE */
header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: public");
header('Content-Disposition: attachment; filename=export.csv;');

$file = fopen('php://output', 'w');
if (!$file) fatal_error('unable to open output');

fputcsv($file, $legenda_rev, ';');

foreach ($lln as &$data) {
	for ($i = 1; $i < count($legenda); $i++) {
		if (!isset($data[$i])) $data[$i] = ' ';
	}
	ksort($data);
	fputcsv($file, $data, ';');
}
unset($data);

?>
