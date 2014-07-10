<? 
require_once('common.php');

check_roostermaker($_POST['secret']);

$res = mdb2_query("SELECT week_id, ma, di, wo, do, vr FROM weken");

function do_dag($week_id, $dag, $rd) {
	if (isset($_POST['id'.$week_id.$dag]) && !$rd) mdb2_exec("UPDATE weken SET $dag = 1 WHERE week_id = $week_id");
	else if (!isset($_POST['id'.$week_id.$dag]) && $rd) mdb2_exec("UPDATE weken SET $dag = 0 WHERE week_id = $week_id");
}

while ($row = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
	$week_id = $row['week_id'];
	do_dag($week_id, 'ma', $row['ma']);
	do_dag($week_id, 'di', $row['di']);
	do_dag($week_id, 'wo', $row['wo']);
	do_dag($week_id, 'do', $row['do']);
	do_dag($week_id, 'vr', $row['vr']);
}

header('Location: upload.php?secret='.$_POST['secret']);

?>
