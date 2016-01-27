<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/blocks/game_points/classes/helper.php');

class block_game_points extends block_base
{

    public function init()
	{
        $this->title = get_string('title', 'block_game_points');
    }

	public function applicable_formats()
	{
        return array(
            'all'    => true
        );
    }
	
	public function instance_allow_multiple()
	{
	  return true;
	}
	
    public function get_content()
	{
		global $DB, $USER;
		$this->content = new stdClass;
	
		if($this->page->course->id == 1) // Pagina inicial
		{
			$sql = "SELECT sum(p.points) as points
				FROM
					{points_log} p
				INNER JOIN {logstore_standard_log} l ON p.logid = l.id
				INNER JOIN {points_system} s ON p.pointsystemid = s.id
				WHERE l.userid = :userid
					AND s.blockinstanceid = :blockinstanceid
				GROUP BY l.userid";	

			$params['userid'] = $USER->id;
			$params['blockinstanceid'] = $this->instance->id;

			$points = $DB->get_record_sql($sql, $params);
			
			if(empty($points))
			{
				$points = new stdClass();
				$points->points = 0;
			}
			
			$this->content->text = 'Seus pontos: <br><p align="center"><font size="28">' . $points->points . '</font></center>';
			
		}
		else // Pagina de um curso
		{
			$sql = "SELECT sum(p.points) as points
				FROM
					{points_log} p
				INNER JOIN {logstore_standard_log} l ON p.logid = l.id
				INNER JOIN {points_system} s ON p.pointsystemid = s.id
				WHERE l.userid = :userid
					AND l.courseid = :courseid
					AND s.blockinstanceid = :blockinstanceid
				GROUP BY l.userid";	

			$params['userid'] = $USER->id;
			$params['courseid'] = $this->page->course->id;
			$params['blockinstanceid'] = $this->instance->id;

			$points = $DB->get_record_sql($sql, $params);
			
			if(empty($points))
			{
				$points = new stdClass();
				$points->points = 0;
			}
			
			$this->content->text = 'Seus pontos: <br><p align="center"><font size="28">' . $points->points . '</font></center>';
			
			// Footer
			if(user_has_role_assignment($USER->id, 5))
			{
				$eventslist = report_eventlist_list_generator::get_non_core_event_list();
				$eventsarray = array();
				foreach($eventslist as $value)
				{
					$description = explode("\\", explode(".", strip_tags($value['fulleventname']))[0]);
					$eventsarray[$value['eventname']] = $description[0];
				}
				
				$pss = null;
				if(is_null($this->page->cm->modname))
				{
					$pss = $DB->get_records('points_system', array('deleted' => 0, 'blockinstanceid' => $this->instance->id));
				}
				else
				{
					$sql = "SELECT *
					FROM
						{points_system} p
					WHERE p.deleted = 0
						AND p.blockinstanceid = " . $this->instance->id . "
						AND p.conditionpoints LIKE '%" . $this->page->cm->modname . "%'";
					
					$pss = $DB->get_records_sql($sql);
				}
				if(!empty($pss))
				{
					$pointslist = '';
										
					foreach($pss as $pointsystem)
					{
						if($pointsystem->type == 'random')
						{
							$points = $pointsystem->valuepoints;
						}
						else if($pointsystem->type == 'fixed')
						{
							$points = $pointsystem->valuepoints;
						}
						else if($pointsystem->type == 'unique')
						{
							$sql = "SELECT count(p.id)
								FROM {points_log} p
									INNER JOIN {logstore_standard_log} l ON p.logid = l.id
								WHERE l.userid = :userid
									AND p.pointsystemid = :pointsystemid";
							$params['userid'] = $USER->id;
							$params['pointsystemid'] = $pointsystem->id;
							
							if($DB->count_records_sql($sql, $params) == 0)
							{
								$points = $pointsystem->valuepoints;
							}
							else
							{
								$points = 0;
							}
						}
						else if($pointsystem->type == 'scalar')
						{
							$sql = "SELECT count(p.id)
								FROM {points_log} p
									INNER JOIN {logstore_standard_log} l ON p.logid = l.id
								WHERE l.userid = :userid
									AND p.pointsystemid = :pointsystemid";
							$params['userid'] = $USER->id;
							$params['pointsystemid'] = $pointsystem->id;
							
							$times = $DB->count_records_sql($sql, $params) + 1;
							$pointsystem->valuepoints = str_replace('x', (string)$times, $pointsystem->valuepoints);
							eval('$points = ' . $pointsystem->valuepoints . ';');
							$points = (int)$points;
						}
						
						if($points > 0)
						{
							$eventdescription = is_null($pointsystem->eventdescription) ? $eventsarray[$pointsystem->conditionpoints] : $pointsystem->eventdescription;
							$pointslist = $pointslist . '<li>' . $points . ' pontos por ' . $eventdescription . '</li>';
						}
						
					}
					
					if(strlen($pointslist) > 0)
					{
						$this->content->footer = 'Você pode ganhar:<ul>' . $pointslist . '</ul>';
					}
					
				}
				
				if(isset($this->config))
				{
					$lastpointsnumber = isset($this->config->lastpointsnumber) ? $this->config->lastpointsnumber : 1;
				}
				else
				{
					$lastpointsnumber = 0;
				}
				
				if($lastpointsnumber > 0)
				{
					/*$sql = "SELECT p.id as id, p.points as points, s.eventdescription as eventdescription, s.conditionpoints as conditionpoints
						FROM
							{points_log} p
						INNER JOIN {logstore_standard_log} l ON p.logid = l.id
						INNER JOIN {points_system} s ON p.pointsystemid = s.id
						WHERE l.userid = :userid
							AND l.courseid = :courseid
							AND s.blockinstanceid = :blockinstanceid
							AND p.points > 0
						ORDER BY p.id DESC";*/
					$sql = "SELECT p.logid as logid, sum(p.points) as points, s.eventdescription as eventdescription, s.conditionpoints as conditionpoints
						FROM {points_log} p
						INNER JOIN {logstore_standard_log} l ON p.logid = l.id
						INNER JOIN {points_system} s ON p.pointsystemid = s.id
						WHERE l.userid = :userid
							AND l.courseid = :courseid
							AND s.blockinstanceid = :blockinstanceid
							AND p.points > 0
                        GROUP BY p.logid
						ORDER BY p.logid DESC";

					$params['userid'] = $USER->id;
					$params['courseid'] = $this->page->course->id;
					$params['blockinstanceid'] = $this->instance->id;

					$lastpoints = $DB->get_records_sql($sql, $params, 0, isset($this->config->lastpointsnumber) ? $this->config->lastpointsnumber : 1);
					
					if(!empty($lastpoints))
					{
						$lastpointslist = '';
						foreach($lastpoints as $lp)
						{
							$eventdescription = is_null($lp->eventdescription) ? $eventsarray[$lp->conditionpoints] : $lp->eventdescription;
							$lastpointslist = $lastpointslist . '<li>' . $lp->points . ' pontos por ' . $eventdescription . '</li>';
						}
						$this->content->footer = $this->content->footer . 'Você ganhou recentemente:<ul>' . $lastpointslist . '</ul>';
					}
				}
			 
			}
			 
		}
		
		return $this->content;
    }

	public function specialization()
	{
		if(isset($this->config))
		{
			if(empty($this->config->title))
			{
				$this->title = get_string('title', 'block_game_points');            
			}
			else
			{
				$this->title = $this->config->title;
			}
		}
	}
	
    public function has_config()
	{
        return true;
    }
}

?>