<? require_once('common.php');
require_once('rquery.php');

$PID = getmypid();

exit;
//$file_id = 488; 
//$file_id = 456; 
//$file_id = 386; 
//$file_id = 1;

// work in progress tussenurenteller
// - roep rooster op per leerling, maak lijst van alle momenten waar een (minstens 1) les staat
// - bereken per dag:
//    * het eerste uur dat een leerling les heeft
//    * het laatste uur dat een leerling les heeft
//    * het aantal lessen dat een leerling heeft
//    Het aantal tussenuren op die dag is dan LAATSTE - EERSTE + 1 - LESSEN

$list = mdb2_query(<<<EOQ
SELECT llnr, cat, SUM(tussen) tussen FROM (
	SELECT cat, llnr, dag, MAX(uur) - MIN(uur) + 1 - COUNT(cat) tussen FROM (
		SELECT cat.entity_name cat, ppl.entity_name llnr, dag, uur FROM entities AS cat
		JOIN grp2ppl AS cat2ppl ON cat2ppl.lesgroep_id = cat.entity_id AND cat2ppl.file_id_basis = $file_id
		JOIN grp2ppl ON grp2ppl.ppl_id = cat2ppl.ppl_id AND grp2ppl.file_id_basis = $file_id
		JOIN entities AS ppl ON ppl.entity_id = cat2ppl.ppl_id
		JOIN entities AS grp ON grp.entity_id = grp2ppl.lesgroep_id AND grp.entity_type != 7 -- CATEGORIE
		JOIN entities2lessen ON entities2lessen.entity_id = grp.entity_id
		JOIN files2lessen ON files2lessen.les_id = entities2lessen.les_id AND files2lessen.file_id = $file_id
		JOIN lessen ON lessen.les_id = files2lessen.les_id
		WHERE cat.entity_type = 7
		GROUP BY cat2ppl.ppl_id, dag, uur
	) AS bla
	GROUP BY llnr, dag
) AS bla2
GROUP BY llnr
EOQ
);

//echo($file_id);
//mdb2_res_table($list);

header('Content-type: text/plain');
while (($row = $list->fetchRow(MDB2_FETCHMODE_ASSOC))) {
	echo($row['llnr'].','.$row['cat'].','.$row['tussen']."\n");
}
?>
