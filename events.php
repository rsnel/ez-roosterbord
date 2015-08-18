<? require_once('common.php');

check_roostermaker($_GET['secret']);

$events = mdb2_query(<<<EOQ
SELECT CONCAT(startweken.week,
		CASE
		WHEN start_dag = 1 THEN 'ma'
		WHEN start_dag = 2 THEN 'di'
		WHEN start_dag = 3 THEN 'wo'
		WHEN start_dag = 4 THEN 'do'
		WHEN start_dag = 5 THEN 'vr'
		END,
		start_uur) van,
	CONCAT(eindweken.week,
		CASE
		WHEN eind_dag = 1 THEN 'ma'
		WHEN eind_dag = 2 THEN 'di'
		WHEN eind_dag = 3 THEN 'wo'
		WHEN eind_dag = 4 THEN 'do'
		WHEN eind_dag = 5 THEN 'vr'
		END,
		eind_uur) `t/m`,
	IFNULL(GROUP_CONCAT(entity_name ORDER BY entity_name), 'Allen') targets,
	beschrijving,
	CONCAT('<a href="event.php?secret=%q&amp;event_id=', event_id, '">wijzig</a>') wijz
FROM events
LEFT JOIN entities2events USING (event_id)
LEFT JOIN entities USING (entity_id)
JOIN weken AS startweken ON startweken.week_id = start_week_id
JOIN weken AS eindweken ON eindweken.week_id = eind_week_id
GROUP BY event_id
ORDER BY start_week_id, start_dag, start_uur
EOQ
, $_GET['secret']);

?>
<!DOCTYPE HTML>
<html>
<head>
<title>Eventspagina voor de roostermakers</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/ui-lightness/jquery-ui-1.10.4.custom.min.css">
<script type="text/javascript" src="js/jquery-1.11.1.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.10.4.custom.min.js"></script>
<script type="text/javascript" src="js/jquery.iframe-post-form.js"></script>
</head>
<body>
<div id="content">

<h3>Events</h3>
<p><a href="event.php?secret=<? echo($_GET['secret']) ?>">nieuw event toevoegen</a>
<? echo(mdb2_res_table($events)); ?>


</div>
</body>
</html>
