<? require_once('common.php'); 
require_once('common_edit.php');
require_once('rquery.php');

if (!get_enable_edit()) exit;

header('Content-type: text/plain;charset=UTF-8');
print_r($_POST);
if (!isset($_POST['lokaal']) || !is_array($_POST['lokaal']) || count($_POST['lokaal']) != 2) fatal_error("invalid lokaal[] parameter");
if (!isset($_POST['lesuur']) || !is_array($_POST['lesuur']) || count($_POST['lesuur']) == 0) fatal_error("invalid lesuur[] parameter");

$lokaal0_info = mdb2_single_array("SELECT entity_id, entity_name FROM entities WHERE entity_type = ".LOKAAL." AND entity_name = '%q'", $_POST['lokaal'][0]);
$lokaal1_info = mdb2_single_array("SELECT entity_id, entity_name FROM entities WHERE entity_type = ".LOKAAL." AND entity_name = '%q'", $_POST['lokaal'][1]);

if (!$lokaal0_info) fatal_error("lokaal {$_POST['lokaal'][0]} niet gevonden");
if (!$lokaal1_info) fatal_error("lokaal {$_POST['lokaal'][1]} niet gevonden");

//echo("lokaal0_info\n");
//print_r($lokaal0_info);
//echo("lokaal1_info\n");
//print_r($lokaal1_info);

if (isset($_POST['file_id_basis']) && $_POST['file_id_basis']) $file_id_basis = $_POST['file_id_basis'];
else fatal_error("parameter file_id_basis is required");
$file_basis = mdb2_single_assoc("SELECT * FROM files WHERE file_id = %i", $file_id_basis);

if (!$file_basis) fatal_error("invalid file_id_basis");

if (isset($_POST['file_id_wijz']) && $_POST['file_id_wijz']) $file_id_wijz = $_POST['file_id_wijz'];
else $file_id_wijz = 0;

if ($file_id_wijz == 0) exit;

$file_wijz = mdb2_single_assoc("SELECT * FROM files WHERE file_id = %i", $file_id_wijz);
if (!$file_wijz) fatal_error("invalid file_id_wijz");

//echo("file_id_basis=$file_id_basis file_id_wijz=$file_id_wijz\n");

foreach ($_POST['lesuur'] as $lesuur) {
	$lokaal0_les = get_les_lokaal_lesuur($lokaal0_info[0], $file_id_basis, $file_id_wijz, $lesuur);
	$lokaal1_les = get_les_lokaal_lesuur($lokaal1_info[0], $file_id_basis, $file_id_wijz, $lesuur);
	lokaalwijzig($lokaal0_les, $lokaal0_info, $lokaal1_info, $file_id_basis, $file_id_wijz);
	lokaalwijzig($lokaal1_les, $lokaal1_info, $lokaal0_info, $file_id_basis, $file_id_wijz);
}

$dagparm = array_reduce($_POST['dag'],
       		function ($carry, $item) { return $carry.'dag[]='.$item.'&'; }, '');

header('Location: '.dirname($_SERVER['PHP_SELF']).'/doclok.php?q='.urlencode($_POST['q']).'&file_id_basis='.urlencode($file_id_basis).'&'.$dagparm.'file_id_wijz='.urlencode($file_id_wijz));

?>
