<? require_once('common.php');
require_once('rquery.php');


if (isset($_GET['file_id_basis']) && $_GET['file_id_basis']) $file_id_basis = $_GET['file_id_basis'];
else fatal_error("parameter file_id_basis is required");

$file_basis = mdb2_single_assoc("SELECT * FROM files WHERE file_id = %i", $file_id_basis);
if (!$file_basis) fatal_error("invalid file_id_basis");

if (isset($_GET['file_id_wijz']) && $_GET['file_id_wijz']) $file_id_wijz = $_GET['file_id_wijz'];
else $file_id_wijz = 0;

$file_wijz = mdb2_single_assoc("SELECT * FROM files WHERE file_id = %i", $file_id_wijz);
if (!$file_wijz) fatal_error("invalid file_id_wijz");


$subquery = rquery(NULL, NULL, $file_id_basis, isset($_GET['file_id_wijz'])?$_GET['file_id_wijz']:0, 'LEFT ');

$query = <<<EOQ
SELECT  
	CONCAT(CASE WHEN f.dag = 1 THEN 'ma' WHEN f.dag = 2 THEN 'di' WHEN f.dag = 3 THEN 'wo' WHEN f.dag = 4 THEN 'do' WHEN f.dag = 5 THEN 'vr' END, f.uur) uur, /* f.notitie AS f_notitie,  CASE WHEN MAX(wijz) = 0 THEN 'basis' ELSE 'wijz' END waar,  */
	/*s_zid, */
	entity_name, /*, COUNT(entities.entity_id) count */
       GROUP_CONCAT(CONCAT(CASE WHEN wijz = 0 THEN 'basis' ELSE 'wijz' END, ': ', f.lesgroepen, '/', f.vakken, '/', f.docenten, '/', f.lokalen) SEPARATOR '<br>') conflict
FROM ( $subquery ) AS sub
JOIN lessen AS f ON f.les_id = f_id
JOIN entities2lessen ON entities2lessen.les_id = f_id
JOIN entities ON entities.entity_id = entities2lessen.entity_id
LEFT JOIN lessen AS s ON s.les_id = s_id
WHERE f.lesgroepen IS NOT NULL AND f.dag != 0 AND f.uur != 0
AND ( wijz = 1 OR s_zid IS NULL ) AND entity_type != %i
GROUP BY f.dag, f.uur, entities.entity_id
HAVING  COUNT(entities.entity_id) > 1
ORDER BY f.dag, f.uur, f_zid DESC
EOQ;

$result = mdb2_query($query, VAK);

?>
<!DOCTYPE html!>
<head>
<style>
/* td {
	text-align: center;
	padding-top: 1.4em;
} */
</style>
</head>
<body>
<?
mdb2_res_table($result);
?>
</body>
</html>
