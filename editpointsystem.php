<?php

global $DB, $OUTPUT, $PAGE, $USER;
 
require_once('../../config.php');
require_once('block_game_points_editps_form.php');
 
global $DB;
 
// Required variables
$courseid = required_param('courseid', PARAM_INT);
$pointsystemid = required_param('pointsystemid', PARAM_INT);
 
// Optional variables
$id = optional_param('id', 0, PARAM_INT);
 
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourse', 'block_game_points', $courseid);
}
 
require_login($course);
 
$PAGE->set_url('/blocks/game_points/editpointsystem.php', array('id' => $courseid));
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(get_string('editpointsystemheading', 'block_game_points')); 

$settingsnode = $PAGE->settingsnav->add(get_string('gamepointssettings', 'block_game_points'));
$editurl = new moodle_url('/blocks/game_points/editpointsystem.php', array('id' => $id, 'courseid' => $courseid, 'pointsystemid' => $pointsystemid));
$editnode = $settingsnode->add(get_string('editpointsystempage', 'block_game_points'), $editurl);
$editnode->make_active();

$addform = new block_game_points_editps_form($pointsystemid, $courseid);
if($addform->is_cancelled())
{
    $url = new moodle_url('/my/index.php');
    redirect($url);
}
else if($data = $addform->get_data())
{
	$record = new stdClass();
	$record->id = $pointsystemid;
	$record->type = $data->type;
	$record->conditionpoints = $data->event;
	$record->valuepoints = $data->value;
	$DB->update_record('points_system', $record);
	
    $url = new moodle_url('/my/index.php');
    redirect($url);
}
else
{
	$toform['pointsystemid'] = $pointsystemid;
	$toform['courseid'] = $courseid;
	$addform->set_data($toform);
	$site = get_site();
	echo $OUTPUT->header();
	$addform->display();
	echo $OUTPUT->footer();
}

?>