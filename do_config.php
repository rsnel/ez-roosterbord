<? 
require_once('common.php');

check_roostermaker($_POST['secret']);

if (isset($_POST['hide_students'])) mdb2_query("UPDATE config SET config_value = '1' WHERE config_key = 'HIDE_STUDENTS'");
else mdb2_query("UPDATE config SET config_value = '0' WHERE config_key = 'HIDE_STUDENTS'");

if (isset($_POST['hide_rooms'])) mdb2_query("UPDATE config SET config_value = '1' WHERE config_key = 'HIDE_ROOMS'");
else mdb2_query("UPDATE config SET config_value = '0' WHERE config_key = 'HIDE_ROOMS'");

if (isset($_POST['hide_rooms_since_week_id'])) mdb2_query("UPDATE config SET config_value = '%i' WHERE config_key = 'HIDE_ROOMS_SINCE_WEEK_ID'", $_POST['hide_rooms_since_week_id']);
else mdb2_query("UPDATE config SET config_value = '0' WHERE config_key = 'HIDE_ROOMS_SINCE_WEEK_ID'");

if (isset($_POST['enable_test_warning'])) mdb2_query("UPDATE config SET config_value = '1' WHERE config_key = 'ENABLE_TEST_WARNING'");
else mdb2_query("UPDATE config SET config_value = '0' WHERE config_key = 'ENABLE_TEST_WARNING'");

header('Location: upload.php?secret='.$_POST['secret']);

?>
