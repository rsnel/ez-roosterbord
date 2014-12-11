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
SELECT ll, GROUP_CONCAT(CONCAT(doc, '/', bla) SEPARATOR ';') info FROM (
	SELECT ll, doc, GROUP_CONCAT(vakken) bla FROM (
		SELECT DISTINCT ll.entity_name ll, doc.entity_name doc, lessen.vakken
		FROM grp2ppl
		JOIN entities2lessen AS grp2les ON grp2les.entity_id = grp2ppl.lesgroep_id
		JOIN files2lessen ON files2lessen.les_id = grp2les.les_id AND file_id = $file_id
		JOIN lessen ON lessen.les_id = files2lessen.les_id
		JOIN entities2lessen AS doc2les ON doc2les.les_id = grp2les.les_id AND file_id = $file_id
		JOIN entities AS doc ON doc.entity_id = doc2les.entity_id AND doc.entity_type = %i
		JOIN entities AS ll ON ll.entity_id = grp2ppl.ppl_id
		WHERE file_id_basis = $file_id
	) AS lijst
	GROUP BY ll, doc
) AS lijst
GROUP BY ll
EOQ
, DOCENT);

$legenda = array();
$legenda_rev = array();
$legenda_rev[0] = '';
$legenda_count = 1;
$lln = array();
while (($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC))) {
	$data = array();
	$data[] = $row['ll'];
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

fputcsv($file, $legenda_rev);

foreach ($lln as &$data) {
	for ($i = 1; $i < count($legenda); $i++) {
		if (!isset($data[$i])) $data[$i] = ' ';
	}
	ksort($data);
	fputcsv($file, $data);
}
unset($data);

?>
