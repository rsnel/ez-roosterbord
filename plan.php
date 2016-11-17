<? require_once('common.php');

check_roostermaker($_GET['secret']);

if (isset($_GET['plan_id'])) {
	$plan = mdb2_single_assoc(<<<EOQ
SELECT * FROM plan WHERE plan_id = %i
EOQ
, $_GET['plan_id']);

	$plan_id = $plan['plan_id'];
	$naam = $plan['naam'];
	$gewicht = $plan['gewicht'];
	$ord = $plan['ord'];
	if (!$plan) fatal_error("plan bestaat niet, en kan dus niet worden gewijzigd");
//	print_r($plan);
//	exit;
} else {
	$plan_id = 0;
	$naam = '';
	$gewicht = 1;
	$ord = 1;
}

$koppel_query = <<<EOQ
SELECT CONCAT('<span style="display: inline-block"><input type="checkbox" name="entity_ids[]" value="',
	entities.entity_id, '"', CASE WHEN plan_id IS NOT NULL THEN ' checked' ELSE '' END, '>',
	entity_name, '</input></span>')
FROM entities
LEFT JOIN entities2plan ON entities2plan.entity_id = entities.entity_id AND entities2plan.plan_id = %i
WHERE (entity_active = 1 OR entities2plan.entity_id IS NOT NULL) AND entity_type = %i ORDER BY entity_name
EOQ;

$koppel_count_query = <<<EOQ
SELECT COUNT(*) FROM entities2plan JOIN entities ON entities.entity_id = entities2plan.entity_id WHERE plan_id = %i AND entity_type = %i
EOQ;

$k_stamklassen = mdb2_col(0, $koppel_query, $plan_id, STAMKLAS);
$k_lesgroepen = mdb2_col(0, $koppel_query, $plan_id, LESGROEP);
$k_categorieen = mdb2_col(0, $koppel_query, $plan_id, CATEGORIE);
$k_vakken = mdb2_col(0, $koppel_query, $plan_id, VAK);

$count_stamklassen = mdb2_single_val($koppel_count_query,  $plan_id, STAMKLAS);
$count_lesgroepen = mdb2_single_val($koppel_count_query,  $plan_id, LESGROEP);
$count_categorieen = mdb2_single_val($koppel_count_query, $plan_id, CATEGORIE);
$count_vakken = mdb2_single_val($koppel_count_query, $plan_id, VAKKEN);

header("Content-Type: text/html; charset=UTF-8"); ?>
<!DOCTYPE HTML>
<html>
<head>
<meta charset="UTF-8">
<title>Plan</title>
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
<p><form method="POST" action="do_plan.php" name="wijzig" accept-charset="UTF-8">
naam: <input type="text" name="naam" value="<? echo($naam); ?>"><br>
gewicht: <input type="text" name="gewicht" value="<? echo($gewicht); ?>"><br>
order: <input type="text" name="ord" value="<? echo($ord); ?>"><br>
<input type="hidden" name="secret" value="<? echo($_GET['secret']) ?>">
<br>Plan koppelen aan:
<div id="accordion">
<h3><a href="#">Categorie&euml;n (<? echo($count_categorieen); ?>)</a></h3>
<div> <? foreach ($k_categorieen as $koppeling) echo($koppeling); echo('<br>'); ?> </div>
<h3><a href="#">Vakken (<? echo($count_vakken); ?>)</a></h3>
<div> <? foreach ($k_vakken as $koppeling) echo($koppeling); echo('<br>'); ?> </div>
<h3><a href="#">Stamklassen (<? echo($count_stamklassen); ?>)</a></h3>
<div> <? foreach ($k_stamklassen as $koppeling) echo($koppeling); echo('<br>'); ?> </div>
<h3><a href="#">Lesgroepen (<? echo($count_lesgroepen); ?>)</a></h3>
<div> <? foreach ($k_lesgroepen as $koppeling) echo($koppeling); echo('<br>'); ?> </div>
</div>
<input type="submit" name="submit" value="Opslaan">
<? if (isset($_GET['plan_id'])) { ?>
<input type="hidden" name="plan_id" value="<? echo($plan_id); ?>">
<input type="submit" name="submit" value="Wissen"><br>
<? } ?>
</form>
</div>
</body>
</html>
