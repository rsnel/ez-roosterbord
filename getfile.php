<? require_once('common.php');

check_roostermaker($_GET['secret']);

$res = mdb2_single_assoc("SELECT * FROM files WHERE file_id = %i", $_GET['file_id']);

if ($res === NULL) fatal_error('file met file_id="'.$_GET['file_id'].'" niet gevonden');

$local_filename = config('DATADIR').(($res['file_type'] == 1)?'basis':'wijz').'-'.$res['file_md5'].'.txt';
header('Content-type: text/plain; charset='.config('ZERMELO_ENCODING'));
header('Content-disposition: inline; filename='.$res[file_name]);

if (readfile($local_filename) === false) {
	header('HTTP/1.0 404 Not Found');
	header_remove('Content-disposition');
	header_remove('Content-type');
	echo('<html><head><title>Error 404 Not Found</title></head><body><h1>Error 404</h1><p>Gevraagde file is er niet ?!?!?</body></html>');
}
?>
