<? require_once('common.php');

check_roostermaker($_GET['secret']);

$local_filename = config('DATADIR').config('LOGFILE');

if (!is_readable($local_filename)) fatal_error("file $local_filename is not readable (doesn't exist/wrong permissions");

header('Content-type: text/plain; charset=UTF-8');
header('Content-disposition: inline; filename='.$config_logfile);
readfile($local_filename);
?>
