<? require_once('common.php');

check_roostermaker($_GET['secret']);

if (isset($_GET['event_id'])) {
	$event = mdb2_single_assoc(<<<EOQ
SELECT * FROM events WHERE event_id = %i
EOQ
, $_GET['event_id']);

	$event_id = $_GET['event_id'];
	$start_week_id = $event['start_week_id'];
	$eind_week_id = $event['eind_week_id'];
	$start_dag = $event['start_dag'];
	$eind_dag = $event['eind_dag'];
	$start_uur = $event['start_uur'];
	$eind_uur = $event['eind_uur'];
	if (!$event) fatal_error("event bestaat niet, en kan dus niet worden gewijzigd");
//	print_r($event);
//	exit;
} else {
	$event_id = 0;
	$start_week_id = 0;
	$eind_week_id = 0;
	$start_dag = 0;
	$eind_dag = 0;
	$start_uur = 0;
	$eind_uur = 0;
}

$koppel_query = <<<EOQ
SELECT CONCAT('<span style="display: inline-block"><input type="checkbox" name="entity_ids[]" value="',
	entities.entity_id, '"', CASE WHEN event_id IS NOT NULL THEN ' checked' ELSE '' END, '>',
	entity_name, '</input></span>')
FROM entities
LEFT JOIN entities2events ON entities2events.entity_id = entities.entity_id AND entities2events.event_id = %i
WHERE (entity_active = 1 OR entities2events.entity_id IS NOT NULL) AND entity_type = %i ORDER BY entity_name
EOQ;

$koppel_count_query = <<<EOQ
SELECT COUNT(*) FROM entities2events JOIN entities ON entities.entity_id = entities2events.entity_id WHERE event_id = %i AND entity_type = %i
EOQ;

$k_stamklassen = mdb2_col(0, $koppel_query, $event_id, STAMKLAS);
$k_lesgroepen = mdb2_col(0, $koppel_query, $event_id, LESGROEP);
$k_categorieen = mdb2_col(0, $koppel_query, $event_id, CATEGORIE);
$k_vakken = mdb2_col(0, $koppel_query, $event_id, VAK);

$count_stamklassen = mdb2_single_val($koppel_count_query,  $event_id, STAMKLAS);
$count_lesgroepen = mdb2_single_val($koppel_count_query,  $event_id, LESGROEP);
$count_categorieen = mdb2_single_val($koppel_count_query, $event_id, CATEGORIE);
$count_vakken = mdb2_single_val($koppel_count_query, $event_id, VAKKEN);

$startweek_options = mdb2_single_val(<<<EOQ
SELECT CONCAT('<select name="startweek">', GROUP_CONCAT('<option ', IF(week_id = %i, 'selected ', ''), 'value="', week_id, '">', week, '</option>' SEPARATOR ''), '</select>')
FROM weken
EOQ
, $start_week_id);

$eindweek_options = mdb2_single_val(<<<EOQ
SELECT CONCAT('<select name="eindweek"><option>-</option>', GROUP_CONCAT('<option ', IF(week_id = %i, 'selected ', ''), 'value="', week_id, '">', week, '</option>' SEPARATOR ''), '</select>')
FROM weken
EOQ
, $eind_week_id);

$days = array('ma', 'di', 'wo', 'do', 'vr');
$startdag_options = '<select name="startdag">';
$startdag_options .= '<option>-</option>';
for ($i = 1; $i <= 5; $i++) {
	$startdag_options .= '<option ';
	if ($i == $start_dag) $startdag_options .= 'selected ';
	$startdag_options .= 'value="'.$i.'">'.$days[$i-1].'</option>';
}
$startdag_options .= '</select>';

$einddag_options = '<select name="einddag">';
$einddag_options .= '<option>-</option>';
for ($i = 1; $i <= 5; $i++) {
	$einddag_options .= '<option ';
	if ($i == $eind_dag) $einddag_options .= 'selected ';
	$einddag_options .= 'value="'.$i.'">'.$days[$i-1].'</option>';
}
$einddag_options .= '</select>';

$startuur_options = '<select name="startuur">';
$startuur_options .= '<option>-</option>';
for ($i = 1; $i <= 9; $i++) {
	$startuur_options .= '<option';
	if ($i == $start_uur) $startuur_options .= ' selected';
	$startuur_options .= '>'.$i.'</option>';
}
$startuur_options .= '</select>';

$einduur_options = '<select name="einduur">';
$einduur_options .= '<option>-</option>';
for ($i = 1; $i <= 9; $i++) {
	$einduur_options .= '<option';
	if ($i == $eind_uur) $einduur_options .= ' selected';
	$einduur_options .= '>'.$i.'</option>';
}
$einduur_options .= '</select>';

header("Content-Type: text/html; charset=UTF-8"); ?>
<!DOCTYPE HTML>
<html>
<head>
<meta charset="UTF-8">
<title>Event</title>
<link rel="stylesheet" href="css/ui-lightness/jquery-ui-1.10.4.custom.min.css">
<style type="text/css"><!--
body {
        font-size: 10px;
}
#content {
        font-size: 13px;
}
--></style>
<script type="text/javascript" src="js/jquery-1.11.1.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.10.4.custom.min.js"></script>
<script type="text/javascript">
//<![CDATA[
$(function(){
	$("#accordion").accordion({
                heightStyle: "content"
        });
});
//]]>
</script>
</head>
<body>
<div id="content">
<pre>
/* om invoer te vereenvoudigen hoeft niet alles te worden ingevuld
 * regels:
 * - de startweek MOET ingevuld zijn
 * - als de eindweek niet ingevuld is, dan maken we hem gelijk aan de beginweek
 * - als de startdag niet is ingevuld, mag de rest niet ingevuld zijn en maken we ervan: ma1 - vr9
 * - als de einddag niet is ingevuld, dan maken we hem gelijk aan de startdag
 * - als het startuur niet is ingevuld, dan mag het einduur ook niet ingevuld zijn en maken we ervan: 1 - 9
 *
 * Ook mag het eind niet eerder zijn dan het begin,
 * dus startweek &lt; eindweek OF (weken gelijk EN startdag &lt; einddag OF (dagen gelijk EN startuur &leq; einduur)) */
</pre>
<p><form method="POST" action="do_event.php" name="wijzig" accept-charset="UTF-8">
van: <? echo($startweek_options); ?> <? echo($startdag_options); ?> <? echo($startuur_options); ?><br>
t/m: <? echo($eindweek_options); ?> <? echo($einddag_options); ?> <? echo($einduur_options); ?><br>
beschrijving: <input type="text" name="beschrijving" value="<? if (isset($event)) echo($event['beschrijving']) ?>"><br>
<input type="hidden" name="secret" value="<? echo($_GET['secret']) ?>">
<br>Event koppelen aan:
<div id="accordion">
<h3><a href="#">Categorie&euml;n (<? echo($count_categorieen); ?>)</a></h3>
<div> <? foreach ($k_categorieen as $koppeling) echo($koppeling); echo('<br>'); ?> </div>
<h3><a href="#">Vakken (<? echo($count_vakken); ?>)</a></h3>
<div> <? foreach ($k_vakken as $koppeling) echo($koppeling); echo('<br>'); ?> </div>
<!--<h3><a href="#">Stamklassen (<? echo($count_stamklassen); ?>)</a></h3>
<div> <? foreach ($k_stamklassen as $koppeling) echo($koppeling); echo('<br>'); ?> </div>
<h3><a href="#">Lesgroepen (<? echo($count_lesgroepen); ?>)</a></h3>
<div> <? foreach ($k_lesgroepen as $koppeling) echo($koppeling); echo('<br>'); ?> </div>-->
</div>
<input type="submit" name="submit" value="Opslaan">
<? if (isset($_GET['event_id'])) { ?>
<input type="hidden" name="event_id" value="<? echo($_GET['event_id']) ?>">
<input type="submit" name="submit" value="Wissen"><br>
<? } ?>
</form>
</div>
</body>
</html>
