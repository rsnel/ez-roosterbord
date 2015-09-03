<? require_once('common.php');

header('Content-Type: text/plain; charset=UTF-8');
header('Content-Disposition: inline; filename=log.txt;');

check_roostermaker($_GET['secret']);

$weken = array(2015 => array(35, 36, 37, 38),
	2016 => array(1, 2, 3, 4));

$isleeg = mdb2_single_val('SELECT COUNT(*) FROM weken');

if ($isleeg > 0) {
	echo("fout! dit script mag alleen gedraaid worden als de tabel weken leeg is");
	exit;
}

foreach ($weken as $jaar => $lijst) {
	foreach ($lijst as $week) {
		mdb2_exec("INSERT INTO weken ( year, week, ma, di, wo, do, vr ) VALUES ( $jaar, $week, 1, 1, 1, 1, 1 )");
		echo("week $week van $jaar toegevoegd\n");
	}
}

echo("klaar\n");
?>
