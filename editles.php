<? require_once('common.php'); 
if (!get_enable_edit()) exit;
$row_basis = mdb2_single_assoc("SELECT * FROM files2lessen JOIN lessen USING (les_id) WHERE file_id = %i AND zermelo_id = %i", $_GET['file_id_basis'], $_GET['zid']);
$row_wijz = mdb2_single_assoc("SELECT * FROM files2lessen JOIN lessen USING (les_id) WHERE file_id = %i AND zermelo_id = %i", $_GET['file_id_wijz'], $_GET['zid']);
$zermelo_id = mdb2_single_val("SELECT zermelo_id_orig FROM zermelo_ids WHERE zermelo_id = %i", $_GET['zid']);

if (is_array($row_wijz)) {
	$lesgroepen = $row_wijz['lesgroepen'];
	$vakken = $row_wijz['vakken'];
	$docenten = $row_wijz['docenten'];
	$lokalen = $row_wijz['lokalen'];
	$notitie = $row_wijz['notitie'];
	$dag = $row_wijz['dag'];
	$uur = $row_wijz['uur'];
} else {
	$lesgroepen = $row_basis['lesgroepen'];
	$vakken = $row_basis['vakken'];
	$docenten = $row_basis['docenten'];
	$lokalen = $row_basis['lokalen'];
	$notitie = $row_basis['notitie'];
	$dag = $row_basis['dag'];
	$uur = $row_basis['uur'];
}

?>
<!DOCTYPE html>
<html>
<head>
<title>Editles</title>
</head>
<body>
<pre>
<? //print_r($_SERVER); ?>
<? //print_r(explode(',', config('GEMACHTIGD_EDIT_WIJZ'))); ?>
<? //print_r($row_basis); ?>
<? //print_r($row_wijz); ?>
</pre>
<h3>les <? echo($zermelo_id); ?></h3>
<form action="do_editles.php" accept-charset="UTF-8" method="POST">
<table>
<tr>
<th></th>
<th>dag</th>
<th>uur</th>
<th>lesgroepen</th>
<th>vakken</th>
<th>docenten</th>
<th>lokalen</th>
<th>notitie</th>
</tr>
<tr>
<td>basis</td>
<td><? echo(print_dag($row_basis['dag'])); ?></td>
<td><? echo($row_basis['uur']); ?></td>
<td><? echo($row_basis['lesgroepen']); ?></td>
<td><? echo($row_basis['vakken']); ?></td>
<td><? echo($row_basis['docenten']); ?></td>
<td><? echo($row_basis['lokalen']); ?></td>
<td><? echo($row_basis['notitie']); ?></td>
</tr>
<tr>
<td>wijz</td>
<td><select name="dag">
<option <? if ($dag == 1) { ?>selected <? } ?>value="1">ma</option>
<option <? if ($dag == 2) { ?>selected <? } ?>value="2">di</option>
<option <? if ($dag == 3) { ?>selected <? } ?>value="3">wo</option>
<option <? if ($dag == 4) { ?>selected <? } ?>value="4">do</option>
<option <? if ($dag == 5) { ?>selected <? } ?>value="5">vr</option>
</select></td>
<td><select name="uur">
<option <? if ($uur == 1) { ?>selected <? } ?>value="1">1</option>
<option <? if ($uur == 2) { ?>selected <? } ?>value="2">2</option>
<option <? if ($uur == 3) { ?>selected <? } ?>value="3">3</option>
<option <? if ($uur == 4) { ?>selected <? } ?>value="4">4</option>
<option <? if ($uur == 5) { ?>selected <? } ?>value="5">5</option>
<option <? if ($uur == 6) { ?>selected <? } ?>value="6">6</option>
<option <? if ($uur == 7) { ?>selected <? } ?>value="7">7</option>
<option <? if ($uur == 8) { ?>selected <? } ?>value="8">8</option>
<option <? if ($uur == 9) { ?>selected <? } ?>value="9">9</option>
</select></td>
<td><input type="text" name="lesgroepen" value="<? echo($lesgroepen); ?>"></td>
<td><input type="text" name="vakken" value="<? echo($vakken); ?>"></td>
<td><input type="text" name="docenten" value="<? echo($docenten); ?>"></td>
<td><input type="text" name="lokalen" value="<? echo($lokalen); ?>"></td>
<td><input type="text" name="notitie" value="<? echo($notitie); ?>"></td>
</tr>
</table>
<input type="hidden" name="file_id_basis" value="<? echo(htmlenc($_GET['file_id_basis'])); ?>">
<input type="hidden" name="file_id_wijz" value="<? echo(htmlenc($_GET['file_id_wijz'])); ?>">
<input type="hidden" name="zid" value="<? echo(htmlenc($_GET['zid'])); ?>">
<input type="hidden" name="q" value="<? echo(htmlenc($_GET['q'])); ?>">
<input type="hidden" name="bw" value="<? echo(htmlenc($_GET['bw'])); ?>">
<input type="hidden" name="wk" value="<? echo(htmlenc($_GET['wk'])); ?>">
<input type="hidden" name="dy" value="<? echo(htmlenc($_GET['dy'])); ?>">
<input type="hidden" name="c" value="<? echo(htmlenc($_GET['c'])); ?>">
<input type="submit" name="submit" value="Opslaan">
<input type="submit" name="submit" value="Onwijzig">
<input type="submit" name="uitval" disabled value="Uitval">
</form>
</body>
</html>
