<? require_once('common.php');

check_roostermaker($_GET['secret']);

$res = mdb2_single_assoc("SELECT * FROM files WHERE file_id = %i", $_GET['file_id']);

if ($res === NULL) fatal_error('file met file_id="'.$_GET['file_id'].'" niet gevonden');

$local_filename = config('DATADIR').$res['file_md5'];

if (!is_readable($local_filename)) fatal_error("file $local_filename is not readable (doesn't exist/wrong permissions)");

if ($res['file_type'] == 2)
	header('Content-type: text/plain; charset='.config('ZERMELO_ENCODING'));
else if ($res['file_type'] == 1)
	header('Content-type: application/gzip');

header('Content-disposition: inline; filename='.$res[file_name]);
readfile($local_filename);

?>
