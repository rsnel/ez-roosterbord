<?

function rquery_inner($entity_ids1, $entity_ids2, $id1, $id2, $left, $wijz) {
	//echo("entity_ids = $entity_ids, id1 = $id1, id2 = $id2, left = $left, wijz = $wijz");
	if ($entity_ids1 === '') $entity_ids1 = 'NULL';
	if ($entity_ids2 === '') $entity_ids2 = 'NULL';
	else if (!$entity_ids1 || !$entity_ids2) return<<<EOQ
SELECT DISTINCT f2l.les_id f_id, l2f.les_id s_id, f2l.zermelo_id f_zid, l2f.zermelo_id s_zid, 1 vis, 1 vis2, $wijz wijz
FROM files2lessen AS f2l
{$left}JOIN files2lessen AS l2f ON f2l.zermelo_id = l2f.zermelo_id AND l2f.file_id = $id2
WHERE f2l.file_id = $id1
EOQ;
	return <<<EOQ
SELECT DISTINCT f2l.les_id f_id, l2f.les_id s_id, f2l.zermelo_id f_zid, l2f.zermelo_id s_zid,
	CASE WHEN l2e.entity_id > 0 THEN 1 ELSE 0 END AS vis,
	CASE WHEN l2e2.entity_id > 0 THEN 1 ELSE 0 END AS vis2,
	$wijz wijz
FROM files2lessen AS f2l
JOIN entities2lessen AS e2l ON e2l.les_id = f2l.les_id
{$left}JOIN files2lessen AS l2f ON f2l.zermelo_id = l2f.zermelo_id AND l2f.file_id = $id2
LEFT JOIN entities2lessen AS l2e ON l2f.les_id = l2e.les_id AND l2e.entity_id IN ($entity_ids1)
LEFT JOIN entities2lessen AS l2e2 ON l2f.les_id = l2e2.les_id AND l2e2.entity_id IN ($entity_ids2)
WHERE f2l.file_id = $id1 AND e2l.entity_id IN ($entity_ids1)
EOQ;
}

function rquery($entity_ids1, $entity_ids2, $id1, $id2, $left) {
	return rquery_inner($entity_ids1, $entity_ids2, $id1, $id2, $left, 0).
		"\nUNION ALL\n".rquery_inner($entity_ids2, $entity_ids1, $id2, $id1, 'LEFT ', 1);
}

?>
