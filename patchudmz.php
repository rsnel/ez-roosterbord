<? require_once('common.php');

check_roostermaker($_GET['secret']);

// geen roosterID gegeven om te patchen
if (!isset($_GET['rooster_id']) || !$_GET['rooster_id']) {
	$res_geupload = mdb2_query(<<<EOQ
SELECT week, rooster_id, basis_id, wijz_id, file_id, $sub0 file_name, FROM_UNIXTIME(timestamp) timestamp, IFNULL(file_version, '-') file_version, IF(wijz_id != 0, CONCAT('<a href="patchudmz.php?rooster_id=', rooster_id, '&amp;secret={$_GET['secret']}">patch</a>'), '-') patch FROM roosters
JOIN weken USING (week_id)
JOIN files USING (file_id)
ORDER BY rooster_id DESC
EOQ
	);
?>
<!DOCTYPE HTML>
<html>
<head>
<title>UDMZ patchpagina</title>
</head>
<body>
<h3>Alle roosteruploads</h3>
<? mdb2_res_table($res_geupload); ?>
</body>
</html>
<?	exit;
}

$rooster_info = mdb2_single_assoc("SELECT * FROM roosters WHERE rooster_id = %i", $_GET['rooster_id']);
if ($rooster_info['wijz_id'] == 0) fatal_error('impossibru!');
//print_r($rooster_info);
$basis_info = mdb2_single_assoc("SELECT files.* FROM roosters JOIN files USING (file_id) WHERE basis_id = %i AND wijz_id = 0", $rooster_info['basis_id']);
//print_r($basis_info);
$wijzigingen = mdb2_all_assoc_rekey("SELECT zermelo_id_orig, lessen.* FROM files2lessen JOIN lessen USING (les_id) JOIN zermelo_ids USING (zermelo_id) WHERE file_id = %i", $rooster_info['file_id']);
//print_r($wijzigingen);

$name = tempnam(config('DATADIR'), 'udmzpatch');
//echo("tempfile=$name\n");
if (!($fpout = gzopen($name, 'wb'))) fatal_error("unable to open $name for writing");

// lees oorspronkelijke udmz file
$lines = array();
if (!($fpin = gzopen(config('DATADIR').$basis_info['file_md5'], 'rb'))) fatal_error("unable top open ".config('DATADIR').$basis_info['file_md5'].' for reading');

$prev1 = '';
$prev2 = '';
$state = 'SEARCHLES'; // 'LESLEGENDA' 'INLES' 'TAIL'
$indices = array();

while (!feof($fpin)) {
	$line = fgets($fpin);
	if ($line === false) fatal_error("unable to read from ".$basis_info['file_md5']);
	switch ($state) {
		case 'SEARCHLES':
			$prev2 = $prev1;
			$prev1 = $line;
			if ($prev2 == "########\n" && $prev1 == "Les\n") {
				$state = 'LESLEGENDA';
			}
			// fall through
		case 'TAIL';
			fputs($fpout, $line);
			break;
		case 'LESLEGENDA':
			$legenda = explode("\t", $line);
			if (($indices['lesgroepen'] = array_search('Grp', $legenda)) === false) fatal_error('column Grp not found');
			if (($indices['vakken'] = array_search('Vak', $legenda)) === false) fatal_error('column Vak not found');
			if (($indices['docenten'] = array_search('Doc', $legenda)) === false) fatal_error('column Doc not found');
			if (($indices['lokalen'] = array_search('Lok', $legenda)) === false) fatal_error('column Lok not found');
			if (($indices['dag'] = array_search('Dag', $legenda)) === false) fatal_error('column Dag not found');
			if (($indices['uur'] = array_search('Uur', $legenda)) === false) fatal_error('column Uur not found');
			//print_r($legenda);
			//print_r($indices);
			fputs($fpout, $line);
			$state = 'INLES';
			break;
		case 'INLES';
			if ($line == "########\n") {
				$state = 'TAIL';
				fputs($fpout, $line);
			} else {
				$split = explode("\t", $line);
				if (isset($wijzigingen[$split[0]])) {
					/*echo("---wijz---\n");
					foreach ($indices as $key => $index) {
						echo("$key => {$split[$index]}\n");
					}*/
					//print_r($wijzigingen[$split[0]]);
					$split[$indices['groepen']] = $wijzigingen[$split[0]]['groepen'];
					$split[$indices['vakken']] = $wijzigingen[$split[0]]['vakken'];
					$split[$indices['docenten']] = $wijzigingen[$split[0]]['docenten'];
					$split[$indices['lokalen']] = $wijzigingen[$split[0]]['lokalen'];
					$split[$indices['dag']] = print_dag($wijzigingen[$split[0]]['dag']);
					$split[$indices['uur']] = 'u'.$wijzigingen[$split[0]]['uur'];
					/*
					foreach ($indices as $key => $index) {
						echo("$key => {$split[$index]}\n");
					}*/
					$new = implode("\t", $split);
					// uitval...
					if ($wijzigingen[$split[0]]['dag'] != 0 && $wijzigingen[$split[0]]['uur'] != 0) fputs($fpout, $new);

				} else {
					$check = implode("\t", $split);
					assert($line == $check);
					fputs($fpout, $check);
				}
			}
			break;
		default:
			fatal_error("impossible state!");
	}

}	
fclose($fpout);
fclose($fpin);
header('Content-type: application/gzip');
header('Content-disposition: inline; filename='.$basis_info[file_name]);
readfile($name);
unlink($name);

?>
