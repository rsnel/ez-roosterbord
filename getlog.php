<? require_once('common.php');

check_roostermaker($_GET['secret']);

header('Content-type: text/plain; charset=UTF-8');
header('Content-disposition: inline; filename='.$config_logfile);
if (readfile(config('DATADIR').config('LOGFILE')) === false) {
	header('HTTP/1.0 404 Not Found');
	header_remove('Content-disposition');
	header_remove('Content-type');
	echo('<html><head><title>Error 404 Not Found</title></head><body><h1>Error 404</h1><p>Gevraagde file is er niet ?!?!?</body></html>');
}
?>
