<? require_once('common.php');

check_roostermaker($_GET['secret']);

$attachments = array ();

if (isset($_GET['bericht_id'])) {
	$ber = mdb2_single_assoc(<<<EOQ
SELECT * FROM berichten WHERE bericht_id = %i
EOQ
, $_GET['bericht_id']);

	$bericht_id = $_GET['bericht_id'];
	if (!$ber) fatal_error("bericht bestaat niet, en kan dus niet worden gewijzigd");

	$defaultfrom = date('Y-m-d', $ber['bericht_visiblefrom']);
	$defaultuntil = date('Y-m-d', $ber['bericht_visibleuntil']);
	if (config('ATTACHMENTS') == 'true') $attachments = mdb2_all_assoc_rekey("SELECT * FROM attachments2berichten JOIN attachments USING (attachment_id) WHERE bericht_id = %i", $bericht_id);
} else {
	$bericht_id = 0;
	$defaultfrom = date('Y-m-d');
	$defaultuntil = '';
}

$koppel_query = <<<EOQ
SELECT CONCAT('<span style="display: inline-block"><input type="checkbox" name="entity_ids[]" value="',
	entities.entity_id, '"', CASE WHEN bericht_id IS NOT NULL THEN ' checked' ELSE '' END, '>',
	entity_name, '</input></span>')
FROM entities
LEFT JOIN entities2berichten ON entities2berichten.entity_id = entities.entity_id AND entities2berichten.bericht_id = %i
WHERE (entity_active = 1 OR entities2berichten.entity_id IS NOT NULL) AND entity_type = %i ORDER BY entity_name
EOQ;

$koppel_count_query = <<<EOQ
SELECT COUNT(*) FROM entities2berichten JOIN entities ON entities.entity_id = entities2berichten.entity_id WHERE bericht_id = %i AND entity_type = %i
EOQ;

$k_stamklassen = mdb2_col(0, $koppel_query, $bericht_id, STAMKLAS);
$k_lesgroepen = mdb2_col(0, $koppel_query, $bericht_id, LESGROEP);
$k_categorieen = mdb2_col(0, $koppel_query, $bericht_id, CATEGORIE);

$count_stamklassen = mdb2_single_val($koppel_count_query,  $bericht_id, STAMKLAS);
$count_lesgroepen = mdb2_single_val($koppel_count_query,  $bericht_id, LESGROEP);
$count_categorieen = mdb2_single_val($koppel_count_query, $bericht_id, CATEGORIE);

header("Content-Type: text/html; charset=UTF-8"); ?>
<!DOCTYPE HTML>
<html>
<head>
<meta charset="UTF-8">
<title>Bericht</title>
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
	var filenum = 0;
	$("#from").datepicker({ dateFormat: 'yy-mm-dd', firstDay: 1 });
	$("#until").datepicker({ dateFormat: 'yy-mm-dd', firstDay: 1 });
	$("#accordion").accordion({
		heightStyle: "content"
	});
	$("#addfile").click(function () {
		filenum++;
		$("#filelist").append('<li id="filelist' + filenum + '"><input type="file" name="files[]"><a href="#" id="fileverwijder' + filenum + '">verwijder</a></li>');
		$("#fileverwijder" + filenum).click(function () {
			$(this).parent().remove();
			return false;
		});
		return false;
	});
});
//]]>
</script>
</head>
<body>
<div id="content">
Speciale opmaak voor inhoud bericht (werkt niet in de titel):
<ul>
<li><code><b>[url=</b>http://www.ovc.nl/<b>]</b>website van school<b>[/url]</b></code> wordt <a href="http://www.ovc.nl/">website van school</a>.</li>
<li><code><b>[b]</b>vetgedrukt<b>[/b]</b></code> wordt <b>vetgedrukt</b></li>
<li><code><b>[i]</b>italic<b>[/i]</b></code> wordt <i>italic</i></li>
</ul>

<p><form method="POST" action="do_bericht.php" name="wijzig" enctype="multipart/form-data" accept-charset="UTF-8">
titel: <input type="text" name="title" value="<? if (isset($ber)) echo(htmltobb($ber['bericht_title'])) ?>"><br>
<textarea rows="16" cols="72" name="body"><? if (isset($ber)) echo(htmltobb($ber['bericht_body'])); ?></textarea><br>
zichtbaar vanaf: <input id="from" name="from" value="<? echo($defaultfrom) ?>"></br>
zichtbaar tot: <input id="until" name="until" value="<? echo($defaultuntil) ?>"><br>
<input type="hidden" name="secret" value="<? echo($_GET['secret']) ?>">
<br>Bericht koppelen aan:
<div id="accordion">
<h3><a href="#">Stamklassen (<? echo($count_stamklassen); ?>)</a></h3>
<div> <? foreach ($k_stamklassen as $koppeling) echo($koppeling); echo('<br>'); ?> </div>
<h3><a href="#">Lesgroepen (<? echo($count_lesgroepen); ?>)</a></h3>
<div> <? foreach ($k_lesgroepen as $koppeling) echo($koppeling); echo('<br>'); ?> </div>
<h3><a href="#">Categorie&euml;n (<? echo($count_categorieen); ?>)</a></h3>
<div> <? foreach ($k_categorieen as $koppeling) echo($koppeling); echo('<br>'); ?> </div>
</div>
<? if (config('ATTACHMENTS') == 'true') { ?>
<h3>Attachments</h3>
Let op! In principe gebruiken we leerlingnummers en geen namen in documenten die naar buiten gaan. Zorg ervoor dat leerlingen zichzelf makkelijk kunnen vinden, door bijvoorbeeld te sorteren op leerlingnummer en door een bestandsformaat te gebruiken dat makkelijk te openen is, bijvoorbeeld <code>.pdf</code>.
<ul>
<? foreach ($attachments as $attachment) { ?>
<li><? echo($attachment['attachment_filename']) ?>
<input type="checkbox" name="del-<? echo($attachment['attachment2bericht_id']); ?>" value="delete">verwijderen
of vervangen door <input type="file" name="file-<? echo($attachment['attachment2bericht_id']); ?>"></li>
<? } ?>
</ul>
<a href="#" id="addfile">voeg attachment toe</a>
<ul id="filelist">
</ul>
<? } ?>
<input type="submit" name="submit" value="Opslaan">
<? if (isset($_GET['bericht_id'])) { ?>
<input type="hidden" name="bericht_id" value="<? echo($_GET['bericht_id']) ?>">
<input type="submit" name="submit" value="Wissen"><br>
laatste update: <? echo(date('r', $ber['bericht_update'])) ?>.<br>
<? } ?>
</form>
</div>
</body>
</html>
