<?
/* Copyright (c) Rik Snel 2011, license GNU AGPLv3 */

require_once('MDB2.php');

function mdb2() {
	static $mdb2;
	if (isset($mdb2)) return $mdb2;

	$mdb2 = MDB2::connect(config('DSN'));
	if (MDB2::isError($mdb2)) fatal_error($mdb2->getMessage().':'.$mdb2->getUserInfo());
        $mdb2->exec('SET SESSION group_concat_max_len = 65536');
        $mdb2->exec("SET NAMES 'utf8' COLLATE 'utf8_unicode_ci'");
	return $mdb2;
}

function mdb2_last_insert_id() {
	return mdb2()->lastInsertId();
}

function mdb2_res_table($res) {
	$no_columns = $res->numCols();
	$columns = $res->getColumnNames(true);
	//print_r($columns);
	if ($no_columns == 0) return;
	?><table><tr><?
	for ($i = 0; $i < $no_columns; $i++) { ?><th><? echo(isset($columns[$i])?$columns[$i]:'<i>?</i>'); ?></th><? } ?></tr>
<?	while ($row = $res->fetchRow(MDB2_FETCHMODE_ORDERED)) { ?><tr><?
	foreach($row as $field) { ?><td><? if ($field === NULL) echo('<i>NULL</i>'); else echo($field); ?></td><? } ?></tr>
<?	} ?>
</table>
<?  	$res->seek(); // reset row pointer
}

// format characters
// %% -> %
// %q -> string to be escaped, must already be insides 's in $format
// %w -> string to be escaped, must already be insides 's in $format, also escapes wildcard characters
// %i -> positive integer
// %I -> SQL IDENTIFIER quoting
function mdb2_vprintf($format, $args) {
	$out = '';
	$arg = 0;

	while (($pos = strpos($format, '%')) !== FALSE) {
		$out .= substr($format, 0, $pos);
		switch ($format[$pos + 1]) {
			case '%':
				$out .= '%';
				break;
			case 'I':
				if (count($args) <= $arg) fatal_error('te weinig argumenten');
				$out .= mdb2()->quoteIdentifier($args[$arg]);
				$arg++;
				break;
			case 'q':
				if (count($args) <= $arg) fatal_error('te weinig argumenten');
				$out .= mdb2()->escape($args[$arg]);
				$arg++;
				break;
			case 'w':
				if (count($args) <= $arg) fatal_error('te weinig argumenten');
				$out .= mdb2()->escape($args[$arg], true);
				$arg++;
				break;
			case 'i':
				if (count($args) <= $arg) fatal_error('te weinig argumenten');
				$val = (int)$args[$arg];
				if ($val === NULL) {
					$out .= 'NULL';
				} else {
					if ($val != $args[$arg]) fatal_error('SQL argument is geen integer');
					if ($val < 0) fatal_error('SQL argument is een negatieve integer: '.$val);
					$out .= $val;
				}
				$arg++;
				break;
			default: 
				fatal_error('onzinnig format character ->'.$format[$pos+1].'<-');
				break;
		}

		$format = substr($format, $pos + 2);
	}

	if ($arg != count($args)) fatal_error('te veel argumenten voor format string');

	//echo($out.$format);
	return $out.$format;
}

function mdb2_printf($format) {
        $args = func_get_args();
        array_shift($args);

        return mdb2_vprintf($format, $args);
}

function mdb2_vquery($format, $args) {
	$res = mdb2()->query(mdb2_vprintf($format, $args));
	if (MDB2::isError($res)) {
		$errorInfo = mdb2()->errorInfo($res);
		fatal_error($res->getMessage().': '.$errorInfo[2]." query ".$format);
	}

	return $res;
}

function mdb2_query($format) {
	$args = func_get_args();
	array_shift($args);

	return mdb2_vquery($format, $args);
}

function mdb2_vexec_error($err, $format, $args) {
	$affected = mdb2()->exec(mdb2_vprintf($format, $args));
	if (MDB2::isError($affected)) {
		if (MDB2::isError($affected, $err)) return false;
		else {
			$errorInfo = mdb2()->errorInfo($affected);
			fatal_error($affected->getMessage().': '.$errorInfo[2]);
		}
	}

	return true;
}

function mdb2_exec_error($err, $format) {
	$args = func_get_args();
	array_shift($args); array_shift($args);

	return mdb2_vexec_error($err, $format, $args);
}

function mdb2_vexec($format, $args) {
	$affected = mdb2()->exec(mdb2_vprintf($format, $args));
	if (MDB2::isError($affected)) {
		$errorInfo = mdb2()->errorInfo($affected);
		fatal_error($affected->getMessage().': '.$errorInfo[2]." query ".$format);
	}
	return $affected;
}

function mdb2_exec($format) {
	$args = func_get_args();
	array_shift($args);

	return mdb2_vexec($format, $args);
}

function mdb2_vsingle_row($mode, $format, $args) {
	$res = mdb2_vquery($format, $args);
	$array = $res->fetchRow($mode);
	$res->free();

	return $array;
}

function mdb2_single_row($mode, $format) {
	$args = func_get_args();
	array_shift($args); array_shift($args);

	return mdb2_vsingle_row($mode, $format, $args);
}

function mdb2_single_array($format) {
	$args = func_get_args();
	array_shift($args);

	return mdb2_vsingle_row(MDB2_FETCHMODE_ORDERED, $format, $args);
}

function mdb2_single_assoc($format) {
	$args = func_get_args();
	array_shift($args);

	return mdb2_vsingle_row(MDB2_FETCHMODE_ASSOC, $format, $args);
}

function mdb2_single_val($format) {
	$args = func_get_args();
	array_shift($args);

	$res = mdb2_vquery($format, $args);
	$array = $res->fetchRow();
	$res->free();

	if (isset($array[0])) return $array[0];
	else return NULL;
}

function mdb2_vall($mode, $rekey, $force_array, $group, $format, $args) {
        $res = mdb2_vquery($format, $args);
        $array = $res->fetchAll($mode, $rekey, $force_array, $group);
        $res->free();

        return $array;
}

function mdb2_vcol($no, $format, $args) {
        $res = mdb2_vquery($format, $args);
        $array = $res->fetchCol($no);
        $res->free();

        return $array;
}

function mdb2_all_assoc_rekey($format) {
        $args = func_get_args();
	array_shift($args);

        return mdb2_vall(MDB2_FETCHMODE_ASSOC, true, false, false, $format, $args);
}

function mdb2_all_ordered_rekey($format) {
        $args = func_get_args();
        array_shift($args);

        return mdb2_vall(MDB2_FETCHMODE_ORDERED, true, false, false, $format, $args);
}

function mdb2_all_assoc($format) {
        $args = func_get_args();
        array_shift($args);

        return mdb2_vall(MDB2_FETCHMODE_ASSOC, false, true, false, $format, $args);
}

function mdb2_col($no, $format) {
        $args = func_get_args();
	array_shift($args); array_shift($args);

        return mdb2_vcol($no, $format, $args);
}

?>
