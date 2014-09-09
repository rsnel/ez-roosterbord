<? require_once('common.php');

check_roostermaker($_GET['secret']);

$res = mdb2_query(<<<EOQ
SELECT weken.week_id, week, MAX(basis_id) basis_id FROM weken LEFT JOIN roosters ON roosters.week_id = weken.week_id AND roosters.wijz_id = 0 GROUP BY week_id ORDER BY week_id
EOQ
);

$week_options = '';
$test = 0;
while ($wk = $res->fetchRow(MDB2_FETCHMODE_ASSOC)) {
	$week_options .= '<option'.((!$wk['basis_id'] && !$test++)?' selected':'').' value="'.$wk['week_id'].'">week '.$wk['week'].($wk['basis_id']?' ('.mdb2_single_val("SELECT file_version FROM files JOIN roosters ON roosters.file_id = files.file_id WHERE wijz_id = 0 AND basis_id = {$wk['basis_id']}").')':'').'</option>'."\n";
}

$sub0 = "CONCAT('<a href=\"getfile.php?file_id=', file_id, '&amp;secret=".config('UPLOAD_SECRET')."\">', file_name, '</a>')";

$res = mdb2_query(<<<EOQ
SELECT week, 
CONCAT('<input type="checkbox"', CASE WHEN ma = 1 THEN ' checked' ELSE '' END, ' name="id', week_id, 'ma">') ma,
CONCAT('<input type="checkbox"', CASE WHEN di = 1 THEN ' checked' ELSE '' END, ' name="id', week_id, 'di">') di,
CONCAT('<input type="checkbox"', CASE WHEN wo = 1 THEN ' checked' ELSE '' END, ' name="id', week_id, 'wo">') wo,
CONCAT('<input type="checkbox"', CASE WHEN do = 1 THEN ' checked' ELSE '' END, ' name="id', week_id, 'do">') do,
CONCAT('<input type="checkbox"', CASE WHEN vr = 1 THEN ' checked' ELSE '' END, ' name="id', week_id, 'vr">') vr
FROM weken
ORDER BY week_id
EOQ
);  

$res_geupload = mdb2_query(<<<EOQ
SELECT week, basis_id, wijz_id, $sub0 file_name, FROM_UNIXTIME(file_time) file_time, IFNULL(file_version, '-') file_version FROM roosters
JOIN weken USING (week_id)
JOIN files USING (file_id)
ORDER BY rooster_id DESC
EOQ
);
	$berichten = mdb2_query(<<<EOQ
SELECT bericht_id, bericht_title, bericht_visiblefrom, bericht_visibleuntil FROM berichten ORDER BY bericht_visibleuntil DESC
EOQ
);

?>
<!DOCTYPE HTML>
<html>
<head>
<title>Upload en berichtenpagina voor de roostermakers</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/ui-lightness/jquery-ui-1.10.4.custom.min.css">
<script type="text/javascript" src="js/jquery-1.11.1.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.10.4.custom.min.js"></script>
<script type="text/javascript" src="js/jquery.iframe-post-form.js"></script>
<script type="text/javascript">
//<![CDATA[
$(function(){
	var interval, randid, d, state;
	$('#submitbutton').removeAttr('disabled');
	$('.progress_udmz').iframePostForm({
		post: function() {
			// disable Upload button
			$('#submitbutton').attr('disabled', 'disabled');
			state = 0;
       			d = $('#dialog_udmz').clone();
			$('.progressbar', d).progressbar({
				value: false
			});
			d.dialog();
			$('.progress-label').html('Uploading....');
			randid = 'id' + String(Math.random()).slice(2);
			d.addClass(randid);
			$('.randid').val(randid);
			interval = setInterval(function (){
				$.ajax({
					cache: false,
					url: "do_status.php?secret=<? echo($_GET['secret']) ?>&randid=" + randid,
					success: function(json) {
						if ($.isEmptyObject(json)) {
							if (state == 1) {
								$('.progress-label').html('Complete');
								d.append('finished');
								clearInterval(interval);
								$('#submitbutton').removeAttr('disabled');
								d.dialog('destroy');
							}
						} else {
							state = 1;
							$('.bullet:eq(' + json['state'] + ')', d).html('*');
							$('.bullet:lt(' + json['state'] + ')', d).html('X');
							if (json['perc'] == false) {
								$('.progress-label').html('Parsing....');
							} else {
								$('.progress-label').html(Math.round(json['perc']) + '%');
							}
							$('.progressbar', d).progressbar('value', json['perc']);
						}
					}
				});
		       	}, 1000);
		},
		complete: function(response) {
			state = 1;
			// enable Upload button
			alert(response);
			//location.reload(true);
		}
	});
});
//]]>
</script>
</head>
<body>
<div id="content">
<?
$max_upload = (int)(ini_get('upload_max_filesize'));
$max_post = (int)(ini_get('post_max_size'));
$memory_limit = (int)(ini_get('memory_limit'));
$upload_mb = min($max_upload, $max_post, $memory_limit);
//echo $max_upload.' '.$max_post.' '.$memory_limit.'<br>';
?>

<h3>Upload basisrooster (udmz)</h3>
<p><form class="progress_udmz" enctype="multipart/form-data" action="do_upload.php" method="POST" accept-charset="UTF-8">
De filenaam moet van de vorm <code>Schooljaar 2013-2014_212.udmz</code>
zijn, maximale grootte <? echo $upload_mb ?>MB.<br>
<input type="file" size="80" name="uploadedfile"><br>
<input type="hidden" name="type" value="basis">
<select name="week_id"><? echo($week_options); ?></select><br>
Er staat al een basisrooster in de gegeven week en ik wil het overschrijven <input name="overwrite" value="true" type="checkbox"><br>
<input type="hidden" name="secret" value="<? echo(config('UPLOAD_SECRET')); ?>">
<input type="hidden" class="randid" name="randid">
<input id="submitbutton" type="submit" value="Upload">
</form>

<h3>Upload roosterwijzigingen (txt)</h3>
<p><form class="progress_udmz" enctype="multipart/form-data" action="do_upload.php" method="POST" accept-charset="UTF-8">
De filenaam moet van de vorm <code>roosterwijzigingen_wk36.txt</code>
zijn, maximale grootte <? echo $upload_mb ?>MB.<br>
<input type="file" size="80" name="uploadedfile"><br>
<input type="hidden" name="type" value="wijz">
<input type="hidden" name="secret" value="<? echo(config('UPLOAD_SECRET')); ?>">
<input type="hidden" class="randid" name="randid">
<input id="submitbutton" type="submit" value="Upload">
</form>

<h3>Berichten</h3>
<p><a href="bericht.php?secret=<? echo($_GET['secret']) ?>">nieuw bericht toevoegen</a>
<? $row = $berichten->fetchRow(MDB2_FETCHMODE_ASSOC); 
if (!$row) echo("<p>Er zijn geen actuele berichten.\n");
else { ?>
<table><tr><th>vanaf</th><th>tot</th><th>titel</th></tr>
<?
	do { ?>
<tr><td><? echo(date('Y-m-d', $row['bericht_visiblefrom'])) ?></td><td><? echo(date('Y-m-d', $row['bericht_visibleuntil'])) ?></td><td><? echo($row['bericht_title']) ?></td>
<td><a href="bericht.php?bericht_id=<? echo($row['bericht_id']) ?>&amp;secret=<? echo($_GET['secret']) ?>">wijzig</a></td></tr>
<?
	} while ($row = $berichten->fetchRow(MDB2_FETCHMODE_ASSOC));
}
?>
</table>


<h3>Lesweken/lesdagen</h3> 
<form action="do_lesdagen.php" method="POST">
<? mdb2_res_table($res); ?>
<input type="submit" value="Lesdagen opslaan">
<input type="hidden" name="secret" value="<? echo(urlencode($_GET['secret'])) ?>">
</form>

<h3>Configuratie</h3>
<form action="do_config.php" method="POST">
<br>Verberg lokalen <input <? if (config('HIDE_ROOMS')) { ?>checked <? } ?>type="checkbox" name="hide_rooms" value="1">
<br>Verberg leerlingen <input <? if (config('HIDE_STUDENTS')) { ?>checked <? } ?>type="checkbox" name="hide_students" value="1">
<br>Toon test waarschuwing <input <? if (config('ENABLE_TEST_WARNING')) { ?>checked <? } ?>type="checkbox" name="enable_test_warning" value="1">
<input type="hidden" name="secret" value="<? echo(urlencode($_GET['secret'])) ?>">
<br><input type="submit" value="Opslaan">
</form>

<h3>Alle roosteruploads</h3>
<? mdb2_res_table($res_geupload); ?>

<h3>Logfile</h3>

Toegang tot de <a href="getlog.php?secret=<? echo($_GET['secret']) ?>">logfile</a>.

<div id="dialog_udmz" style="display: none" title="Roosterupload">
<div class="progressbar"><div class="progress-label" style=" position: absolute;
left: 50%;
top: 50%;
font-weight: bold;
text-shadow: 1px 1px 0 #fff;">Uploading....</div></div>
<ul style="list-style-type: none; padding: 0px; margin: 0px;">
<li><span class="bullet">*</span> File uploaden</li>
<li><span class="bullet">o</span> File inlezen in geheugen</li>
<li><span class="bullet">o</span> Database vullen</li>
</ul>
</div>
</div>
</body>
</html>
