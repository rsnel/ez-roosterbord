<?
require_once('../common.php');
header('Content-type: text/plain;charset=UTF-8');
if (!isset($_GET) || !isset($_GET['name'])) fatal_error("dit kan niet");

$info = explode('/', $_GET['name']);

// als alleen de attachemnt2bericht_id gegeven is, dan redirecten we naar de file
if (count($info) == 1 || (count($info) == 2 && $info[1] == '')) {
	// attachment2bericht_id is gegeven, maar filenaam niet
	// we zoeken de filenaam op
	echo("here!");
	$filename = mdb2_single_val(<<<EOQ
SELECT attachment_filename FROM attachments
JOIN attachments2berichten USING (attachment_id)
WHERE attachment2bericht_id = %i
EOQ
	, $info[0]);
	echo($filename);
	if ($filename) {
		//echo($filename);
		if (count($info) == 1) header("Location: ".$info[0].'/'.rawurlencode($filename));
		else header("Location: ".rawurlencode($filename));
		exit;
	}
}

if (count($info) != 2) fatal_error("dit kan ook niet");

$file = mdb2_single_assoc(<<<EOQ
SELECT * FROM attachments
JOIN attachments2berichten USING (attachment_id)
JOIN berichten USING (bericht_id)
WHERE attachment_filename = '%q' 
AND attachment2bericht_id = %i 
AND bericht_visibleuntil > {$_SERVER['REQUEST_TIME']}
AND bericht_visiblefrom <= {$_SERVER['REQUEST_TIME']}
EOQ
, $info[1], $info[0]
);
//, urldecode($info[1]), $info[1], $info[0]

//print_r($_GET);
//print_r($info);
//print_r($file);

if (!is_array($file) || count($file) == 0) {
	http_response_code(404);
	echo("file not found\n");
	echo("\$_SERVER['QUERY_STRING']={$_SERVER['QUERY_STRING']}\n");
	print_r($_GET['name']);
	print_r($info);
	exit;
}


header("Content-type: {$file['attachment_mimetype']}");
header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: public");
flush();
//echo("path: ".config('DATADIR')."\n");
readfile(config('DATADIR').$file['attachment_md5']);
mdb2_query("UPDATE attachments SET attachment_download_count = attachment_download_count + 1 WHERE attachment_id = %i", $file['attachment_id']);


?>

