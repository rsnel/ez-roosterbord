<? require_once('common.php');

header('Content-Type: text/plain; charset=UTF-8');
header('Content-Disposition: inline; filename=log.txt;');

check_roostermaker($_POST['secret']);

uploadcomplain($_FILES['uploadedFile']);

if ($_FILES['uploadedFile']['size'] == 0) fatal_error('file with no content uploaded, impossible!');

$filename = $_FILES['uploadedFile']['name'];

print_r($_FILES);

echo($filename);

$lines = file($_FILES['uploadedFile']['tmp_name']);

/* use trim() to remove trailing \n */
$legenda = explode("\t", trim(array_shift($lines)));
print_r($legenda);

if (($llnr_index = array_search('Object', $legenda)) === FALSE) fatal_error("kolom Object niet gevonden in geupload bestand");

if (($voornaam_index = array_search('Voornaam', $legenda)) === FALSE) fatal_error("kolom Voornaam niet gevonden in geupload bestand");

if (($tussenvoegsels_index = array_search('Tussenvoegsels', $legenda)) === FALSE) fatal_error("kolom Tussenvoegsels niet gevonden in geupload bestand");

if (($achternaam_index = array_search('Achternaam', $legenda)) === FALSE) fatal_error("kolom Achternaam niet gevonden in geupload bestand");

echo("llnr_index=$llnr_index\n");

foreach ($lines as $line) {
	/* user trim() to remove trailing \n */
	$columns = explode("\t", trim($line));
	$llnr = $columns[$llnr_index];
	$voornaam = fix_charset_whitespace($columns[$voornaam_index]);
	$tussenvoegsels = fix_charset_whitespace($columns[$tussenvoegsels_index]);
	$achternaam = fix_charset_whitespace($columns[$achternaam_index]);
	// a broken export contained Tussenvoegsels after Achternaam
	//$achternaam2 = explode(',', $achternaam);
	//$achternaam = $achternaam2[0];
	if ($tussenvoegsels == '') $name = $voornaam.' '.$achternaam;
	else $name = $voornaam.' '.$tussenvoegsels.' '.$achternaam;
	echo($llnr.' '.$name."\n");
	$entity_id = mdb2_single_val("SELECT entity_id FROM entities WHERE entity_type = 1 AND entity_name = '%q'", $llnr);
	echo("entity_id=$entity_id\n");
	mdb2_exec("UPDATE names SET name = '%q', firstname = '%q', prefix = '%q', surname = '%q' WHERE entity_id = '%q'", $name, $voornaam, $tussenvoegsels, $achternaam, $entity_id);
}

?>

