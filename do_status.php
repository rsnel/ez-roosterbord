<? require_once('common.php');

check_roostermaker($_GET['secret']);

header('Content-type: application/json; charset=UTF-8');
//header('Content-type: text/plain; charset=UTF-8');

$ret = mdb2_single_val("SELECT locking_status FROM locking WHERE locking_id = 0 AND locking_randid = '%q'", $_GET['randid']);

if ($ret === NULL) echo('{}');
else echo($ret);

?>
