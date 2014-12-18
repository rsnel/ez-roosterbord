<? require_once('common.php');

check_roostermaker($_GET['secret']);

$koppel_query = <<<EOQ
SELECT CONCAT('<span style="display: inline-block"><input type="checkbox" name="entity_ids[]" value="',
	entities.entity_id, '"', CASE WHEN entity_active = 1 THEN ' checked' ELSE '' END, '>',
	entity_name, '</input></span>')
FROM entities WHERE entity_type != %i AND entity_type != %i AND entity_type != 0
ORDER BY entity_type, entity_name
EOQ;

$entities = mdb2_col(0, $koppel_query, LEERLING, LESGROEP);

header("Content-Type: text/html; charset=UTF-8"); ?>
<!DOCTYPE HTML>
<html>
<head>
<meta charset="UTF-8">
<title>Verberg entities in lijst</title>
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
//$(function(){
//});
//]]>
</script>
</head>
<body>
<div id="content">
<form action="do_hide.php" method="POST" accept-charset="UTF-8">
<h3>Verberg docenten/lokalen/vakken/stamklassen/categorie&euml;en</h3>
Hier kun je docenten/lokalen/vakken/stamklassen/categorie&euml;en zo instellen dat ze niet zichtbaar zijn op de homepagina van het rooster.
Een vinkje betekent 'wel zichtbaar.'

<p><div> <? foreach ($entities as $koppeling) echo($koppeling); echo('<br>'); ?> </div>
<p><input type="submit" value="Opslaan"><input type="hidden" name="secret" value="<? echo($_GET['secret']) ?>">
</form>
</div>
</body>
</html>
