<? require_once('common.php');

check_roostermaker($_GET['secret']);

$plans = mdb2_query(<<<EOQ
SELECT 
IFNULL(GROUP_CONCAT(entity_name ORDER BY entity_name), 'Allen') targets,
        naam,
	gewicht,
	ord,
        CONCAT('<a href="plan.php?secret=%q&amp;plan_id=', plan_id, '">wijzig</a>') wijz

FROM plan
LEFT JOIN entities2plan USING (plan_id)
LEFT JOIN entities USING (entity_id)
GROUP BY plan_id
EOQ
, $_GET['secret']);

?>
<!DOCTYPE HTML>
<html>
<head>
<title>Planpagina voor de roostermakers</title>
<link rel="stylesheet" href="css/style.css">
<link rel="stylesheet" href="css/ui-lightness/jquery-ui-1.10.4.custom.min.css">
<script type="text/javascript" src="js/jquery-1.11.1.min.js"></script>
<script type="text/javascript" src="js/jquery-ui-1.10.4.custom.min.js"></script>
<script type="text/javascript" src="js/jquery.iframe-post-form.js"></script>
</head>
<body>
<div id="content">

<h3>Plan</h3>
<p><a href="plan.php?secret=<? echo($_GET['secret']) ?>">nieuw plan toevoegen</a>
<? echo(mdb2_res_table($plans)); ?>


</div>
</body>
</html>
