<?php
/* Copyright (C) 2023 EVARISK <dev@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
* \file    lib/dolisirh_function.lib.php
* \ingroup dolisirh
* \brief   Library files with common functions for DoliCar
												   */

require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT.'/holiday/class/holiday.class.php';
require_once __DIR__ . '/../class/workinghours.class.php';

/**
 * Add or delete task from favorite by the user
 *
 * @param $task_id integer task id
 * @param $user_id integer id
 * @return int
 */
function toggleTaskFavorite($task_id, $user_id)
{
	global $db;
	$task = new Task($db);
	$task->fetch($task_id);
	$task->fetchObjectLinked();

	if (!empty($task->linkedObjects) && key_exists('user', $task->linkedObjects)) {
		foreach ($task->linkedObjects['user'] as $userLinked) {
			if ($userLinked->id == $user_id) {
				$link_exists = 1;
				$task->deleteObjectLinked($user_id, 'user');
			}
		}
	}

	if (!$link_exists) {
		$result = $task->add_object_linked('user', $user_id, '', '');
		return $result;
	}
	return 0;
}

/**
 * Check if task is set to favorite by the user
 *
 * @param $task_id integer task id
 * @param $user_id integer id
 * @return int
 */
function isTaskFavorite($task_id, $user_id)
{
	global $db;
	$task = new Task($db);
	$task->fetch($task_id);
	$task->fetchObjectLinked();
	$link_exists = 0;
	if (!empty($task->linkedObjects) && key_exists('user', $task->linkedObjects)) {
		foreach ($task->linkedObjects['user'] as $userLinked) {
			if ($userLinked->id == $user_id) {
				$link_exists = 1;
			}
		}
	}

	return $link_exists;
}

/**
 * Check if day is available
 *
 * @param $date datetime
 * @param $userid user id
 * @return int availability
 */
function isDayAvailable($date, $userid)
{
	global $db;

	if (empty($date)) {
		dol_print_error('', 'Error date parameter is empty');
	}

	$holiday      = new Holiday($db);

	$statusofholidaytocheck = Holiday::STATUS_APPROVED;

	$is_available_for_user = $holiday->verifDateHolidayForTimestamp($userid, $date, $statusofholidaytocheck);
	$is_public_holiday = num_public_holiday($date, dol_time_plus_duree($date, 1,'d'));

	$day_is_available = $is_available_for_user && !$is_public_holiday;

	return $day_is_available;
}

/**
 * Prepare array with list of tabs
 *
 * @param	string	$mode		Mode
 * @param   string  $fuser      Filter on user
 * @return  array				Array of tabs to show
 */
function timeSpendPrepareHead($mode, $fuser = null)
{
	global $langs, $conf, $user;
	$h = 0;
	$head = array();

	$h = 0;

	$param = '';
	$param .= ($mode ? '&mode='.$mode : '');
	if (is_object($fuser) && $fuser->id > 0 && $fuser->id != $user->id) {
		$param .= '&search_usertoprocessid='.$fuser->id;
	}

	if (empty($conf->global->PROJECT_DISABLE_TIMESHEET_PERMONTH)) {
		$head[$h][0] = DOL_URL_ROOT."/custom/dolisirh/view/timespent_month.php".($param ? '?'.$param : '');
		$head[$h][1] = $langs->trans("InputPerMonth");
		$head[$h][2] = 'inputpermonth';
		$h++;
	}

	if (empty($conf->global->PROJECT_DISABLE_TIMESHEET_PERWEEK)) {
		$head[$h][0] = DOL_URL_ROOT."/custom/dolisirh/view/timespent_week.php".($param ? '?'.$param : '');
		$head[$h][1] = $langs->trans("InputPerWeek");
		$head[$h][2] = 'inputperweek';
		$h++;
	}

	if (empty($conf->global->PROJECT_DISABLE_TIMESHEET_PERTIME)) {
		$head[$h][0] = DOL_URL_ROOT."/custom/dolisirh/view/timespent_day.php".($param ? '?'.$param : '');
		$head[$h][1] = $langs->trans("InputPerDay");
		$head[$h][2] = 'inputperday';
		$h++;
	}

	complete_head_from_modules($conf, $langs, null, $head, $h, 'project_timesheet');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'project_timesheet', 'remove');

	return $head;
}

/**
 * Sets object to given categories.
 *
 * Adds it to non existing supplied categories.
 * Deletes object from existing categories not supplied (if remove_existing==true).
 * Existing categories are left untouch.
 *
 * @param 	int[]|int 	$categories 		Category ID or array of Categories IDs
 * @param 	string 		$type_categ 		Category type ('customer', 'supplier', 'website_page', ...) definied into const class Categorie type
 * @param 	boolean		$remove_existing 	True: Remove existings categories from Object if not supplies by $categories, False: let them
 * @param 	CommonObject	$object 	Object
 * @return	int							<0 if KO, >0 if OK
 */
function setCategoriesObject($categories = array(), $type_categ = '', $remove_existing = true, $object)
{
	// Handle single category
	if (!is_array($categories)) {
		$categories = array($categories);
	}

	dol_syslog(get_class($object)."::setCategoriesCommon Oject Id:".$object->id.' type_categ:'.$type_categ.' nb tag add:'.count($categories), LOG_DEBUG);

	require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

	if (empty($type_categ)) {
		dol_syslog(__METHOD__.': Type '.$type_categ.'is an unknown category type. Done nothing.', LOG_ERR);
		return -1;
	}

	// Get current categories
	$c = new Categorie($object->db);
	$existing = $c->containing($object->id, $type_categ, 'id');
	if ($remove_existing) {
		// Diff
		if (is_array($existing)) {
			$to_del = array_diff($existing, $categories);
			$to_add = array_diff($categories, $existing);
		} else {
			$to_del = array(); // Nothing to delete
			$to_add = $categories;
		}
	} else {
		$to_del = array(); // Nothing to delete
		$to_add = array_diff($categories, $existing);
	}

	$error = 0;
	$ok = 0;

	// Process
	foreach ($to_del as $del) {
		if ($c->fetch($del) > 0) {
			$result=$c->del_type($object, $type_categ);
			if ($result < 0) {
				$error++;
				$object->error = $c->error;
				$object->errors = $c->errors;
				break;
			} else {
				$ok += $result;
			}
		}
	}
	foreach ($to_add as $add) {
		if ($c->fetch($add) > 0) {
			$result = $c->add_type($object, $type_categ);
			if ($result < 0) {
				$error++;
				$object->error = $c->error;
				$object->errors = $c->errors;
				break;
			} else {
				$ok += $result;
			}
		}
	}

	return $error ? -1 * $error : $ok;
}

/**
 * Load time spent within a time range for a project.
 * Note: array weekWorkLoad and weekWorkLoadPerTask are reset and filled at each call.
 *
 * @param  int       $datestart First day
 * @param  int       $dateend   Last day
 * @param  int       $project_id id of project
 * @param  int       $taskid    Filter on a task id
 * @param  int       $userid    Time spent by a particular user
 * @return array
 * @throws Exception
 */
function loadTimeSpentWithinRangeByProject($datestart, $dateend, $project_id, $taskid = 0, $userid = 0)
{
	global $db;

	$workLoad['monthWorkLoad'] = array();
	$workLoad['monthWorkLoadPerTask'] = array();

	if (empty($datestart)) {
		dol_print_error('', 'Error datestart parameter is empty');
	}

	$sql = "SELECT ptt.rowid as taskid, ptt.task_duration, ptt.task_date, ptt.task_datehour, ptt.fk_task";
	$sql .= " FROM ".MAIN_DB_PREFIX."projet_task_time AS ptt";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task as pt on pt.rowid = ptt.fk_task";
	$sql .= " WHERE pt.fk_projet = ".((int) $project_id);
	$sql .= " AND (ptt.task_datehour >= '".$db->idate($datestart)."' ";
	$sql .= " AND ptt.task_datehour < '".$db->idate(dol_time_plus_duree($dateend, 1, 'd'))."')";
	if ($taskid) {
		$sql .= " AND ptt.fk_task=".((int) $taskid);
	}
	if (is_numeric($userid)) {
		$sql .= " AND ptt.fk_user=".((int) $userid);
	}

	$resql = $db->query($sql);
	if ($resql) {
		$daylareadyfound = array();

		$num = $db->num_rows($resql);
		$i = 0;
		// Loop on each record found, so each couple (project id, task id)
		while ($i < $num) {
			$obj = $db->fetch_object($resql);
			$day = $db->jdate($obj->task_date); // task_date is date without hours
			if (empty($daylareadyfound[$day])) {
				$workLoad['monthWorkLoad'][$day] = $obj->task_duration;
				$workLoad['monthWorkLoadPerTask'][$day][$obj->fk_task] = $obj->task_duration;
			} else {
				$workLoad['monthWorkLoad'][$day] += $obj->task_duration;
				$workLoad['monthWorkLoadPerTask'][$day][$obj->fk_task] += $obj->task_duration;
			}
			$daylareadyfound[$day] = 1;
			$i++;
		}
		$db->free($resql);
		return $workLoad;
	} else {
		$error = "Error ".$db->lasterror();
		dol_syslog('loadTimeSpentWithinRangeByProject' . $error, LOG_ERR);
		return array();
	}
}

/**
 * Load time spent by tasks within a time range.
 *
 * @param  int       $datestart First day
 * @param  int       $dateend   Last day
 * @param  int       $userid    Time spent by a particular user
 * @return array                Array with minutes, hours and total time spent
 * @throws Exception
 */
function loadTimeSpentOnTasksWithinRange($datestart, $dateend, $userid = 0)
{
	global $db;

	$task = new Task($db);
	$userobj = new User($db);

	if ($userid > 0) {
		$userobj->fetch($userid);
	}

	$timeSpentList = $task->fetchAllTimeSpent($userobj, 'AND (ptt.task_date >= "'.$db->idate($datestart) .'" AND ptt.task_date < "'.$db->idate($dateend) . '")');

	$timeSpentOnTasks = [];

	if (is_array($timeSpentList) && !empty($timeSpentList)) {
		foreach ($timeSpentList as $timeSpent) {
			$timeSpentOnTasks[$timeSpent->fk_task][dol_print_date($timeSpent->timespent_date, 'day')] += $timeSpent->timespent_duration;
		}
	}

	return $timeSpentOnTasks;
}

/**
 * Load time spent within a time range.
 *
 * @param  int       $datestart First day
 * @param  int       $dateend   Last day
 * @param  int       $taskid    Filter on a task id
 * @param  int       $userid    Time spent by a particular user
 * @return array                Array with minutes, hours and total time spent
 * @throws Exception
 */
function loadTimeSpentWithinRange($datestart, $dateend, $isavailable, $userid = 0)
{
	global $db;

	if (empty($datestart)) {
		dol_print_error('', 'Error datestart parameter is empty');
	}

	$task = new Task($db);
	$userobj = new User($db);

	if ($userid > 0) {
		$userobj->fetch($userid);
	}

	$timeSpentList = $task->fetchAllTimeSpent($userobj, 'AND (ptt.task_date >= "'.$db->idate($datestart) .'" AND ptt.task_date < "'.$db->idate($dateend) . '")');

	$timeSpent = array(
		'days' => 0,
		'hours' => 0,
		'minutes' => 0,
		'total' => 0
	);


	if (is_array($timeSpentList) && !empty($timeSpentList)) {
		foreach ($timeSpentList as $timeSpentSingle) {

			$hours = floor($timeSpentSingle->timespent_duration / 3600);
			$minutes = floor($timeSpentSingle->timespent_duration / 60);

			if ($isavailable[$timeSpentSingle->timespent_date]['morning'] && $isavailable[$timeSpentSingle->timespent_date]['afternoon']) {
				$timeSpent['hours'] += $hours;
				$timeSpent['minutes'] += $minutes;
				$timeSpent['total'] += $timeSpentSingle->timespent_duration;
				$days_worked[$timeSpentSingle->timespent_date] = 1;
			}
		}
	}

	$timeSpent['days'] = is_array($days_worked) && !empty($days_worked) ? count($days_worked) : 0;

	return $timeSpent;
}

/**
 * Load time to spend within a time range.
 *
 * @param  int       $datestart First day
 * @param  int       $dateend   Last day
 * @param  int       $userid    Time spent by a particular user
 * @return int                  0 < if OK, >0 if KO
 * @throws Exception
 */
function loadPlannedTimeWithinRange($datestart, $dateend, $workingHours, $isavailable)
{
	if (empty($datestart)) {
		dol_print_error('', 'Error datestart parameter is empty');
	}

	$daysInRange = num_between_day($datestart, $dateend);

	$time_to_spend = array(
		'days' => 0,
		'minutes' => 0
	);

	for ($idw = 0; $idw < $daysInRange; $idw++) {
		$day_start_date = dol_time_plus_duree($datestart, $idw, 'd'); // $firstdaytoshow is a date with hours = 0

		if ($isavailable[$day_start_date]['morning'] && $isavailable[$day_start_date]['afternoon']) {
			$currentDay = date('l', $day_start_date);
			$currentDay = 'workinghours_' . strtolower($currentDay);
			$time_to_spend['minutes'] += $workingHours->$currentDay;
			if ($workingHours->$currentDay / 60 > 0) {
				$time_to_spend['days']++;
			}
		}
	}
	return $time_to_spend;
}

/**
 * Load time to spend within a time range.
 *
 * @param  int       $datestart First day
 * @param  int       $dateend   Last day
 * @return int                  0 < if OK, >0 if KO
 * @throws Exception
 */
function loadPassedTimeWithinRange($datestart, $dateend, $workingHours, $isavailable)
{
	if (empty($datestart)) {
		dol_print_error('', 'Error datestart parameter is empty');
	}

	$daysInRange = num_between_day($datestart, $dateend);

	$passed_working_time = array(
		'minutes' => 0
	);

	for ($idw = 0; $idw < $daysInRange; $idw++) {
		$day_start_date = dol_time_plus_duree($datestart, $idw, 'd'); // $firstdaytoshow is a date with hours = 0

		if ($isavailable[$day_start_date]['morning'] && $isavailable[$day_start_date]['afternoon']) {
			$currentDay = date('l', $day_start_date);
			$currentDay = 'workinghours_' . strtolower($currentDay);
			$passed_working_time['minutes'] += $workingHours->$currentDay;
		}
	}
	return $passed_working_time;
}

/**
 * Load difference between passed time and spent time within a time range.
 *
 * @param  int       $datestart First day
 * @param  int       $dateend   Last day
 * @param  int       $taskid    Filter on a task id
 * @param  int       $userid    Time spent by a particular user
 * @return int                  0 < if OK, >0 if KO
 * @throws Exception
 */
function loadDifferenceBetweenPassedAndSpentTimeWithinRange($datestart, $dateend, $workingHours, $isavailable, $userid = 0)
{
	global $db;

	if (empty($datestart)) {
		dol_print_error('', 'Error datestart parameter is empty');
	}
	$passed_working_time = loadPassedTimeWithinRange($datestart, $dateend, $workingHours, $isavailable);
	$spent_working_time = loadTimeSpentWithinRange($datestart, $dateend, $isavailable, $userid);

	return $passed_working_time['minutes'] - $spent_working_time['minutes'];
}

/**
 * Load all time spending infos within a time range.
 *
 * @param  int       $datestart First day
 * @param  int       $dateend   Last day
 * @param  int       $taskid    Filter on a task id
 * @param  int       $userid    Time spent by a particular user
 * @return int                  0 < if OK, >0 if KO
 * @throws Exception
 */
function loadTimeSpendingInfosWithinRange($datestart, $dateend, $workingHours, $isavailable, $userid = 0)
{
	global $db;

	if (empty($datestart)) {
		dol_print_error('', 'Error datestart parameter is empty');
	}
	$planned_working_time = loadPlannedTimeWithinRange($datestart, $dateend, $workingHours, $isavailable, $userid);
	$passed_working_time = loadPassedTimeWithinRange($datestart, $dateend, $workingHours, $isavailable, $userid);
	$spent_working_time = loadTimeSpentWithinRange($datestart, $dateend, $isavailable, $userid);
	$working_time_difference = loadDifferenceBetweenPassedAndSpentTimeWithinRange($datestart, $dateend, $workingHours, $isavailable, $userid);

	$time_spending_infos = array(
		'planned' => $planned_working_time,
		'passed' => $passed_working_time,
		'spent' => $spent_working_time,
		'difference' => $working_time_difference
	);

	return $time_spending_infos;
}

/**
 * Return list of tasks for all projects or for one particular project
 * Sort order is on project, then on position of task, and last on start date of first level task
 *
 * @param	integer	$task_id			task Id
 * @param	User	$usert				Object user to limit tasks affected to a particular user
 * @param	User	$userp				Object user to limit projects of a particular user and public projects
 * @param	int		$projectid			Project id
 * @param	int		$socid				Third party id
 * @param	int		$mode				0=Return list of tasks and their projects, 1=Return projects and tasks if exists
 * @param	string	$filteronproj    	Filter on project ref or label
 * @param	string	$filteronprojstatus	Filter on project status ('-1'=no filter, '0,1'=Draft+Validated only)
 * @param	string	$morewherefilter	Add more filter into where SQL request (must start with ' AND ...')
 * @param	string	$filteronprojuser	Filter on user that is a contact of project
 * @param	string	$filterontaskuser	Filter on user assigned to task
 * @param	array	$extrafields	    Show additional column from project or task
 * @param   int     $includebilltime    Calculate also the time to bill and billed
 * @param   array   $search_array_options Array of search
 * @param   int     $loadextras         Fetch all Extrafields on each task
 * @return 	array						Array of tasks
 */
function doliSirhGetTasksArray($usert = null, $userp = null, $projectid = 0, $socid = 0, $mode = 0, $filteronproj = '', $filteronprojstatus = '-1', $morewherefilter = '', $filteronprojuser = 0, $filterontaskuser = 0, $extrafields = array(), $includebilltime = 0, $search_array_options = array(), $loadextras = 0, $timeArray = array(), $timeMode = 'month')
{
	global $conf, $hookmanager, $db, $user;
	require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';

	$task = new Task($db);

	$tasks = array();

	//print $usert.'-'.$userp.'-'.$projectid.'-'.$socid.'-'.$mode.'<br>';

	// List of tasks (does not care about permissions. Filtering will be done later)
	$sql = "SELECT ";
	if ($filteronprojuser > 0 || $filterontaskuser > 0) {
		$sql .= " DISTINCT"; // We may get several time the same record if user has several roles on same project/task
	}
	$sql .= " p.rowid as projectid, p.ref, p.title as plabel, p.public, p.fk_statut as projectstatus, p.usage_bill_time,";
	$sql .= " t.rowid as taskid, t.ref as taskref, t.label, t.description, t.fk_task_parent, t.duration_effective, t.progress, t.fk_statut as status,";
	$sql .= " t.dateo as date_start, t.datee as date_end, t.planned_workload, t.rang,";
	$sql .= " t.description, ";
	$sql .= " t.budget_amount, ";
	$sql .= " s.rowid as thirdparty_id, s.nom as thirdparty_name, s.email as thirdparty_email,";
	$sql .= " p.fk_opp_status, p.opp_amount, p.opp_percent, p.budget_amount as project_budget_amount";
	if (!empty($extrafields->attributes['projet']['label'])) {
		foreach ($extrafields->attributes['projet']['label'] as $key => $val) {
			$sql .= ($extrafields->attributes['projet']['type'][$key] != 'separate' ? ",efp.".$key." as options_".$key : '');
		}
	}
	if (!empty($extrafields->attributes['projet_task']['label'])) {
		foreach ($extrafields->attributes['projet_task']['label'] as $key => $val) {
			$sql .= ($extrafields->attributes['projet_task']['type'][$key] != 'separate' ? ",efpt.".$key." as options_".$key : '');
		}
	}
	if ($includebilltime) {
		$sql .= ", SUM(tt.task_duration * ".$db->ifsql("invoice_id IS NULL", "1", "0").") as tobill, SUM(tt.task_duration * ".$db->ifsql("invoice_id IS NULL", "0", "1").") as billed";
	}

	$sql .= " FROM ".MAIN_DB_PREFIX."projet as p";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON p.fk_soc = s.rowid";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_extrafields as efp ON (p.rowid = efp.fk_object)";

	if ($mode == 0) {
		if ($filteronprojuser > 0) {
			$sql .= ", ".MAIN_DB_PREFIX."element_contact as ec";
			$sql .= ", ".MAIN_DB_PREFIX."c_type_contact as ctc";
		}
		$sql .= ", ".MAIN_DB_PREFIX."projet_task as t";
		if ($includebilltime) {
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task_time as tt ON tt.fk_task = t.rowid";
		}
		if ($filterontaskuser > 0) {
			$sql .= ", ".MAIN_DB_PREFIX."element_contact as ec2";
			$sql .= ", ".MAIN_DB_PREFIX."c_type_contact as ctc2";
		}
		if ($conf->global->DOLISIRH_SHOW_ONLY_FAVORITE_TASKS) {
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."element_element as elel ON (t.rowid = elel.fk_target AND elel.targettype='project_task')";
		}
		if ($conf->global->DOLISIRH_SHOW_ONLY_TASKS_WITH_TIMESPENT) {
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task_time as ptt ON (t.rowid = ptt.fk_task)";
		}
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields as efpt ON (t.rowid = efpt.fk_object)";
		$sql .= " WHERE p.entity IN (".getEntity('project').")";
		$sql .= " AND t.fk_projet = p.rowid";
		if ($conf->global->DOLISIRH_SHOW_ONLY_FAVORITE_TASKS) {
			$sql .= " AND elel.fk_target = t.rowid";
			$sql .= " AND elel.fk_source = " . $user->id;
		}
		if ($conf->global->DOLISIRH_SHOW_ONLY_TASKS_WITH_TIMESPENT) {
			$sql .= " AND ptt.fk_task = t.rowid AND ptt.fk_user = " . $user->id;
			if ($timeMode == 'month') {
				$sql .= " AND MONTH(ptt.task_date) = " . $timeArray['month'];
			} else if ($timeMode == 'week') {
				$sql .= " AND WEEK(ptt.task_date) = " . $timeArray['week'];
			} else if ($timeMode == 'day') {
				$sql .= " AND DAY(ptt.task_date) = " . $timeArray['day'];
			}
		}

	} elseif ($mode == 1) {
		if ($filteronprojuser > 0) {
			$sql .= ", ".MAIN_DB_PREFIX."element_contact as ec";
			$sql .= ", ".MAIN_DB_PREFIX."c_type_contact as ctc";
		}
		if ($filterontaskuser > 0) {
			$sql .= ", ".MAIN_DB_PREFIX."projet_task as t";
			if ($includebilltime) {
				$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task_time as tt ON tt.fk_task = t.rowid";
			}
			$sql .= ", ".MAIN_DB_PREFIX."element_contact as ec2";
			$sql .= ", ".MAIN_DB_PREFIX."c_type_contact as ctc2";
		} else {
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task as t on t.fk_projet = p.rowid";
			if ($includebilltime) {
				$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task_time as tt ON tt.fk_task = t.rowid";
			}
		}
		$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields as efpt ON (t.rowid = efpt.fk_object)";
		$sql .= " WHERE p.entity IN (".getEntity('project').")";
	} else {
		return 'BadValueForParameterMode';
	}

	if ($filteronprojuser > 0) {
		$sql .= " AND p.rowid = ec.element_id";
		$sql .= " AND ctc.rowid = ec.fk_c_type_contact";
		$sql .= " AND ctc.element = 'project'";
		$sql .= " AND ec.fk_socpeople = ".((int) $filteronprojuser);
		$sql .= " AND ec.statut = 4";
		$sql .= " AND ctc.source = 'internal'";
	}
	if ($filterontaskuser > 0) {
		$sql .= " AND t.fk_projet = p.rowid";
		$sql .= " AND p.rowid = ec2.element_id";
		$sql .= " AND ctc2.rowid = ec2.fk_c_type_contact";
		$sql .= " AND ctc2.element = 'project_task'";
		$sql .= " AND ec2.fk_socpeople = ".((int) $filterontaskuser);
		$sql .= " AND ec2.statut = 4";
		$sql .= " AND ctc2.source = 'internal'";
	}
	if ($socid) {
		$sql .= " AND p.fk_soc = ".((int) $socid);
	}
	if ($projectid) {
		$sql .= " AND p.rowid IN (".$db->sanitize($projectid).")";
	}
	if ($filteronproj) {
		$sql .= natural_search(array("p.ref", "p.title"), $filteronproj);
	}
	if ($filteronprojstatus && $filteronprojstatus != '-1') {
		$sql .= " AND p.fk_statut IN (".$db->sanitize($filteronprojstatus).")";
	}
	if ($morewherefilter) {
		$sql .= $morewherefilter;
	}
	// Add where from extra fields
	$extrafieldsobjectkey = 'projet_task';
	$extrafieldsobjectprefix = 'efpt.';
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_sql.tpl.php';
	// Add where from hooks
	$parameters = array();
	$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters); // Note that $action and $object may have been modified by hook
	$sql .= $hookmanager->resPrint;
	if ($includebilltime) {
		$sql .= " GROUP BY p.rowid, p.ref, p.title, p.public, p.fk_statut, p.usage_bill_time,";
		$sql .= " t.datec, t.dateo, t.datee, t.tms,";
		$sql .= " t.rowid, t.ref, t.label, t.description, t.fk_task_parent, t.duration_effective, t.progress, t.fk_statut,";
		$sql .= " t.dateo, t.datee, t.planned_workload, t.rang,";
		$sql .= " t.description, ";
		$sql .= " t.budget_amount, ";
		$sql .= " s.rowid, s.nom, s.email,";
		$sql .= " p.fk_opp_status, p.opp_amount, p.opp_percent, p.budget_amount";
		if (!empty($extrafields->attributes['projet']['label'])) {
			foreach ($extrafields->attributes['projet']['label'] as $key => $val) {
				$sql .= ($extrafields->attributes['projet']['type'][$key] != 'separate' ? ",efp.".$key : '');
			}
		}
		if (!empty($extrafields->attributes['projet_task']['label'])) {
			foreach ($extrafields->attributes['projet_task']['label'] as $key => $val) {
				$sql .= ($extrafields->attributes['projet_task']['type'][$key] != 'separate' ? ",efpt.".$key : '');
			}
		}
	}


	$sql .= " ORDER BY p.ref, t.rang, t.dateo";

	//print $sql;exit;
	dol_syslog(get_class($task)."::getTasksArray", LOG_DEBUG);
	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		$i = 0;
		// Loop on each record found, so each couple (project id, task id)
		while ($i < $num) {
			$error = 0;

			$obj = $db->fetch_object($resql);

			if ((!$obj->public) && (is_object($userp))) {	// If not public project and we ask a filter on project owned by a user
				if (!$task->getUserRolesForProjectsOrTasks($userp, 0, $obj->projectid, 0)) {
					$error++;
				}
			}
			if (is_object($usert)) {							// If we ask a filter on a user affected to a task
				if (!$task->getUserRolesForProjectsOrTasks(0, $usert, $obj->projectid, $obj->taskid)) {
					$error++;
				}
			}

			if (!$error) {
				$tasks[$i] = new Task($db);
				$tasks[$i]->id = $obj->taskid;
				$tasks[$i]->ref = $obj->taskref;
				$tasks[$i]->fk_project		= $obj->projectid;
				$tasks[$i]->projectref		= $obj->ref;
				$tasks[$i]->projectlabel = $obj->plabel;
				$tasks[$i]->projectstatus = $obj->projectstatus;

				$tasks[$i]->fk_opp_status = $obj->fk_opp_status;
				$tasks[$i]->opp_amount = $obj->opp_amount;
				$tasks[$i]->opp_percent = $obj->opp_percent;
				$tasks[$i]->budget_amount = $obj->budget_amount;
				$tasks[$i]->project_budget_amount = $obj->project_budget_amount;
				$tasks[$i]->usage_bill_time = $obj->usage_bill_time;

				$tasks[$i]->label = $obj->label;
				$tasks[$i]->description = $obj->description;
				$tasks[$i]->fk_parent = $obj->fk_task_parent; // deprecated
				$tasks[$i]->fk_task_parent = $obj->fk_task_parent;
				$tasks[$i]->duration		= $obj->duration_effective;
				$tasks[$i]->planned_workload = $obj->planned_workload;

				if ($includebilltime) {
					$tasks[$i]->tobill = $obj->tobill;
					$tasks[$i]->billed = $obj->billed;
				}

				$tasks[$i]->progress		= $obj->progress;
				$tasks[$i]->fk_statut = $obj->status;
				$tasks[$i]->public = $obj->public;
				$tasks[$i]->date_start = $db->jdate($obj->date_start);
				$tasks[$i]->date_end		= $db->jdate($obj->date_end);
				$tasks[$i]->rang	   		= $obj->rang;

				$tasks[$i]->socid           = $obj->thirdparty_id; // For backward compatibility
				$tasks[$i]->thirdparty_id = $obj->thirdparty_id;
				$tasks[$i]->thirdparty_name	= $obj->thirdparty_name;
				$tasks[$i]->thirdparty_email = $obj->thirdparty_email;

				if (!empty($extrafields->attributes['projet']['label'])) {
					foreach ($extrafields->attributes['projet']['label'] as $key => $val) {
						if ($extrafields->attributes['projet']['type'][$key] != 'separate') {
							$tasks[$i]->{'options_'.$key} = $obj->{'options_'.$key};
						}
					}
				}

				if (!empty($extrafields->attributes['projet_task']['label'])) {
					foreach ($extrafields->attributes['projet_task']['label'] as $key => $val) {
						if ($extrafields->attributes['projet_task']['type'][$key] != 'separate') {
							$tasks[$i]->{'options_'.$key} = $obj->{'options_'.$key};
						}
					}
				}

				if ($loadextras) {
					$tasks[$i]->fetch_optionals();
				}
			}

			$i++;
		}
		$db->free($resql);
	} else {
		dol_print_error($db);
	}

	return $tasks;
}

/**
 * Output a task line into a per day intput mode
 *
 * @param	string	   	$inc					Line number (start to 0, then increased by recursive call)
 * @param   string		$parent					Id of parent task to show (0 to show all)
 * @param	User|null	$fuser					Restrict list to user if defined
 * @param   Task[]		$lines					Array of lines
 * @param   int			$level					Level (start to 0, then increased/decrease by recursive call)
 * @param   string		$projectsrole			Array of roles user has on project
 * @param   string		$tasksrole				Array of roles user has on task
 * @param	string		$mine					Show only task lines I am assigned to
 * @param   int			$restricteditformytask	0=No restriction, 1=Enable add time only if task is assigned to me, 2=Enable add time only if tasks is assigned to me and hide others
 * @param	int			$preselectedday			Preselected day
 * @param   array       $isavailable			Array with data that say if user is available for several days for morning and afternoon
 * @param	int			$oldprojectforbreak		Old project id of last project break
 * @param	array		$arrayfields		    Array of additional column
 * @param	Extrafields	$extrafields		    Object extrafields
 * @return  array								Array with time spent for $fuser for each day of week on tasks in $lines and substasks
 */
function doliSirhLinesPerDay(&$inc, $parent, $fuser, $lines, &$level, &$projectsrole, &$tasksrole, $mine, $restricteditformytask, $preselectedday, &$isavailable, $oldprojectforbreak = 0, $arrayfields = array(), $extrafields = null)
{
	global $conf, $db, $user, $langs;
	global $form, $formother, $projectstatic, $taskstatic, $thirdpartystatic;

	$lastprojectid = 0;
	$totalforeachday = array();
	$workloadforid = array();
	$lineswithoutlevel0 = array();

	$numlines = count($lines);

	// Create a smaller array with sublevels only to be used later. This increase dramatically performances.
	if ($parent == 0) { // Always and only if at first level
		for ($i = 0; $i < $numlines; $i++) {
			if ($lines[$i]->fk_task_parent) {
				$lineswithoutlevel0[] = $lines[$i];
			}
		}
	}

	if (empty($oldprojectforbreak)) {
		$oldprojectforbreak = (empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT) ? 0 : -1); // 0 to start break , -1 no break
	}

	$restrictBefore = null;

	if (! empty($conf->global->PROJECT_TIMESHEET_PREVENT_AFTER_MONTHS)) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
		$restrictBefore = dol_time_plus_duree(dol_now(), - $conf->global->PROJECT_TIMESHEET_PREVENT_AFTER_MONTHS, 'm');
	}

	//dol_syslog('projectLinesPerDay inc='.$inc.' preselectedday='.$preselectedday.' task parent id='.$parent.' level='.$level." count(lines)=".$numlines." count(lineswithoutlevel0)=".count($lineswithoutlevel0));
	for ($i = 0; $i < $numlines; $i++) {
		if ($parent == 0) {
			$level = 0;
		}

		if ($lines[$i]->fk_task_parent == $parent) {
			$obj = &$lines[$i]; // To display extrafields

			// If we want all or we have a role on task, we show it
			if (empty($mine) || !empty($tasksrole[$lines[$i]->id])) {
				//dol_syslog("projectLinesPerWeek Found line ".$i.", a qualified task (i have role or want to show all tasks) with id=".$lines[$i]->id." project id=".$lines[$i]->fk_project);

				if ($restricteditformytask == 2 && empty($tasksrole[$lines[$i]->id])) {	// we have no role on task and we request to hide such cases
					continue;
				}

				// Break on a new project
				if ($parent == 0 && $lines[$i]->fk_project != $lastprojectid) {
					$lastprojectid = $lines[$i]->fk_project;
					if ($preselectedday) {
						$projectstatic->id = $lines[$i]->fk_project;
					}
				}

				if (empty($workloadforid[$projectstatic->id])) {
					if ($preselectedday) {
						$projectstatic->loadTimeSpent($preselectedday, 0, $fuser->id); // Load time spent from table projet_task_time for the project into this->weekWorkLoad and this->weekWorkLoadPerTask for all days of a week
						$workloadforid[$projectstatic->id] = 1;
					}
				}

				$projectstatic->id = $lines[$i]->fk_project;
				$projectstatic->ref = $lines[$i]->projectref;
				$projectstatic->title = $lines[$i]->projectlabel;
				$projectstatic->public = $lines[$i]->public;
				$projectstatic->status = $lines[$i]->projectstatus;

				$taskstatic->id = $lines[$i]->id;
				$taskstatic->ref = ($lines[$i]->ref ? $lines[$i]->ref : $lines[$i]->id);
				$taskstatic->label = $lines[$i]->label;
				$taskstatic->date_start = $lines[$i]->date_start;
				$taskstatic->date_end = $lines[$i]->date_end;

				$thirdpartystatic->id = $lines[$i]->socid;
				$thirdpartystatic->name = $lines[$i]->thirdparty_name;
				$thirdpartystatic->email = $lines[$i]->thirdparty_email;

				$addcolspan = 2;
				if (!empty($arrayfields['timeconsumed']['checked'])) {
					$addcolspan++;
					$addcolspan++;
				}
				foreach ($arrayfields as $key => $val) {
					if ($val['checked'] && substr($key, 0, 5) == 'efpt.') {
						$addcolspan++;
					}
				}

				if (empty($oldprojectforbreak) || ($oldprojectforbreak != -1 && $oldprojectforbreak != $projectstatic->id)) {
					print '<tr class="oddeven trforbreak nobold project-line" id="project-'. $projectstatic->id .'">'."\n";
					print '<td colspan="'. $addcolspan.'">';
					print $projectstatic->getNomUrl(1, '', 0, '<strong>'.$langs->transnoentitiesnoconv("YourRole").':</strong> '.$projectsrole[$lines[$i]->fk_project]);
					if ($thirdpartystatic->id > 0) {
						print ' - '.$thirdpartystatic->getNomUrl(1);
					}
					if ($projectstatic->title) {
						print ' - ';
						print '<span class="secondary">'.$projectstatic->title.'</span>';
					}

					print '</td>';
					print '<td style="text-align: center; ">';
					if (!empty($conf->use_javascript_ajax)) {
						print img_picto("Auto fill", 'rightarrow', "class='auto-fill-timespent-project' data-rowname='".$namef."' data-value='".($sign * $remaintopay)."'");
					}
					print ' ' . $langs->trans('DivideTimeIntoTasks');
					print '</td>';

					print '<td>';

					print '</td>';

					print '<td>';

					print '</td>';
					print '</tr>';
				}

				if ($oldprojectforbreak != -1) {
					$oldprojectforbreak = $projectstatic->id;
				}

				print '<tr class="oddeven project-'. $projectstatic->id .'" data-taskid="'.$lines[$i]->id.'">'."\n";

				// Project
				if (!empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT)) {
					print "<td>";
					if ($oldprojectforbreak == -1) {
						print $projectstatic->getNomUrl(1, '', 0, $langs->transnoentitiesnoconv("YourRole").': '.$projectsrole[$lines[$i]->fk_project]);
					}
					print "</td>";
				}

				// Thirdparty
				if (!empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT)) {
					print '<td class="tdoverflowmax100">';
					if ($thirdpartystatic->id > 0) {
						print $thirdpartystatic->getNomUrl(1, 'project', 10);
					}
					print '</td>';
				}

				// Ref
				print '<td>';
				print '<!-- Task id = '.$lines[$i]->id.' -->';
				for ($k = 0; $k < $level; $k++) {
					print '<div class="marginleftonly">';
				}
				print $taskstatic->getNomUrl(1, 'withproject', 'time');
				// Label task
				print '<br>';
				print '<span class="opacitymedium">'.$taskstatic->label.'</a>';
				for ($k = 0; $k < $level; $k++) {
					print "</div>";
				}
				print "</td>\n";

				// TASK extrafields
				$extrafieldsobjectkey = 'projet_task';
				$extrafieldsobjectprefix = 'efpt.';
				include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';

				if (!empty($arrayfields['timeconsumed']['checked'])) {
					// Time spent by everybody
					print '<td class="right">';
					// $lines[$i]->duration is a denormalised field = summ of time spent by everybody for task. What we need is time consummed by user
					if ($lines[$i]->duration) {
						print '<a href="'.DOL_URL_ROOT.'/projet/tasks/time.php?id='.$lines[$i]->id.'">';
						print convertSecondToTime($lines[$i]->duration, 'allhourmin');
						print '</a>';
					} else {
						print '--:--';
					}
					print "</td>\n";

					// Time spent by user
					print '<td class="right">';
					$tmptimespent = $taskstatic->getSummaryOfTimeSpent($fuser->id);
					if ($tmptimespent['total_duration']) {
						print convertSecondToTime($tmptimespent['total_duration'], 'allhourmin');
					} else {
						print '--:--';
					}
					print "</td>\n";
				}

				$disabledproject = 1;
				$disabledtask = 1;

				// If at least one role for project
				if ($lines[$i]->public || !empty($projectsrole[$lines[$i]->fk_project]) || $user->rights->projet->all->creer) {
					$disabledproject = 0;
					$disabledtask = 0;
				}
				// If $restricteditformytask is on and I have no role on task, i disable edit
				if ($restricteditformytask && empty($tasksrole[$lines[$i]->id])) {
					$disabledtask = 1;
				}

				if ($restrictBefore && $preselectedday < $restrictBefore) {
					$disabledtask = 1;
				}

				// Select hour
				print '<td class="nowraponall leftborder center minwidth150imp">';
				$tableCell = $form->selectDate($preselectedday, $lines[$i]->id, 1, 1, 2, "addtime", 0, 0, $disabledtask);
				print $tableCell;
				print '</td>';

				$cssonholiday = '';
				if (!$isavailable[$preselectedday]['morning'] && !$isavailable[$preselectedday]['afternoon']) {
					$cssonholiday .= 'onholidayallday ';
				} elseif (!$isavailable[$preselectedday]['morning']) {
					$cssonholiday .= 'onholidaymorning ';
				} elseif (!$isavailable[$preselectedday]['afternoon']) {
					$cssonholiday .= 'onholidayafternoon ';
				}

				global $daytoparse;
				$tmparray = dol_getdate($daytoparse, true); // detail of current day

				$idw = ($tmparray['wday'] - (empty($conf->global->MAIN_START_WEEK) ? 0 : 1));
				global $numstartworkingday, $numendworkingday;
				$cssweekend = '';
				if ((($idw + 1) < $numstartworkingday) || (($idw + 1) > $numendworkingday)) {	// This is a day is not inside the setup of working days, so we use a week-end css.
					$cssweekend = 'weekend';
				}

				// Duration
				print '<td class="center duration'.($cssonholiday ? ' '.$cssonholiday : '').($cssweekend ? ' '.$cssweekend : '').'">';
				$dayWorkLoad = $projectstatic->weekWorkLoadPerTask[$preselectedday][$lines[$i]->id];
				$totalforeachday[$preselectedday] += $dayWorkLoad;

				$alreadyspent = '';
				if ($dayWorkLoad > 0) {
					$alreadyspent = convertSecondToTime($dayWorkLoad, 'allhourmin');
				}

				$idw = 0;

				$tableCell = '';
				$tableCell .= '<span class="timesheetalreadyrecorded" title="texttoreplace"><input type="text" class="center" size="2" disabled id="timespent['.$inc.']['.$idw.']" name="task['.$lines[$i]->id.']['.$idw.']" value="'.$alreadyspent.'"></span>';
				$tableCell .= '<span class="hideonsmartphone"> + </span>';
				//$tableCell.='&nbsp;&nbsp;&nbsp;';
				if (!empty($conf->use_javascript_ajax)) {
					$tableCell .= img_picto("Auto fill", 'rightarrow', "class='auto-fill-timespent' data-rowname='".$namef."' data-value='".($sign * $remaintopay)."'");
				}
				$tableCell .= $form->select_duration($lines[$i]->id.'duration', '', $disabledtask, 'text', 0, 1);
				//$tableCell.='&nbsp;<input type="submit" class="button"'.($disabledtask?' disabled':'').' value="'.$langs->trans("Add").'">';
				print $tableCell;

				$modeinput = 'hours';

				print '<script type="text/javascript">';
				print "jQuery(document).ready(function () {\n";
				print " 	jQuery('.inputhour, .inputminute').bind('keyup', function(e) { updateTotal(0, '".$modeinput."') });";
				print "})\n";
				print '</script>';

				print '</td>';

				// Note
				print '<td class="center">';
				print '<textarea name="'.$lines[$i]->id.'note" rows="'.ROWS_2.'" id="'.$lines[$i]->id.'note"'.($disabledtask ? ' disabled="disabled"' : '').'>';
				print '</textarea>';
				print '</td>';

				// Warning
				print '<td class="right">';
				if ((!$lines[$i]->public) && $disabledproject) {
					print $form->textwithpicto('', $langs->trans("UserIsNotContactOfProject"));
				} elseif ($disabledtask) {
					$titleassigntask = $langs->trans("AssignTaskToMe");
					if ($fuser->id != $user->id) {
						$titleassigntask = $langs->trans("AssignTaskToUser", '...');
					}

					print $form->textwithpicto('', $langs->trans("TaskIsNotAssignedToUser", $titleassigntask));
				}
				print '</td>';

				print "</tr>\n";
			}

			$inc++;
			$level++;
			if ($lines[$i]->id > 0) {
				//var_dump('totalforeachday after taskid='.$lines[$i]->id.' and previous one on level '.$level);
				//var_dump($totalforeachday);
				$ret = doliSirhLinesPerDay($inc, $lines[$i]->id, $fuser, ($parent == 0 ? $lineswithoutlevel0 : $lines), $level, $projectsrole, $tasksrole, $mine, $restricteditformytask, $preselectedday, $isavailable, $oldprojectforbreak, $arrayfields, $extrafields);
				//var_dump('ret with parent='.$lines[$i]->id.' level='.$level);
				//var_dump($ret);
				foreach ($ret as $key => $val) {
					$totalforeachday[$key] += $val;
				}
				//var_dump('totalforeachday after taskid='.$lines[$i]->id.' and previous one on level '.$level.' + subtasks');
				//var_dump($totalforeachday);
			}
			$level--;
		} else {
			//$level--;
		}
	}

	return $totalforeachday;
}

/**
 * Output a task line into a per month intput mode
 *
 * @param	string	   	$inc					Line output identificator (start to 0, then increased by recursive call)
 * @param	int			$firstdaytoshow			First day to show
 * @param	int			$lastdaytoshow			Last day to show
 * @param	User|null	$fuser					Restrict list to user if defined
 * @param   string		$parent					ID of parent task to show (0 to show all)
 * @param   Task[]		$lines					Array of lines (list of tasks, but we will show only if we have a specific role on task)
 * @param   int			$level					Level (start to 0, then increased/decrease by recursive call)
 * @param   string		$projectsrole			Array of roles user has on project
 * @param   string		$tasksrole				Array of roles user has on task
 * @param	string		$mine					Show only task lines I am assigned to
 * @param   int			$restricteditformytask	0=No restriction, 1=Enable add time only if task is assigned to me, 2=Enable add time only if tasks is assigned to me and hide others
 * @param   array       $isavailable			Array with data that say if user is available for several days for morning and afternoon
 * @param	int			$oldprojectforbreak		Old project id of last project break
 * @param	array		$arrayfields		    Array of additional column
 * @param	Extrafields	$extrafields		    Object extrafields
 * @return  array								Array with time spent for $fuser for each day of week on tasks in $lines and substasks
 */
function doliSirhTaskLinesWithinRange(&$inc, $firstdaytoshow, $lastdaytoshow, $fuser, $parent, $lines, &$level, &$projectsrole, &$tasksrole, $mine, $restricteditformytask, &$isavailable, $oldprojectforbreak = 0, $arrayfields = array(), $extrafields = null, $timeSpentOnTasks)
{
	global $conf, $db, $user, $langs;
    global $form, $formother, $projectstatic, $taskstatic, $thirdpartystatic;

	$numlines = count($lines);

	$lastprojectid = 0;
	$workloadforid = array();
	$totalforeachday = array();
	$lineswithoutlevel0 = array();

    $daysInRange = num_between_day($firstdaytoshow, $lastdaytoshow, 1);

    // Create a smaller array with sublevels only to be used later. This increase dramatically performances.
	if ($parent == 0) { // Always and only if at first level
		for ($i = 0; $i < $numlines; $i++) {
			if ($lines[$i]->fk_task_parent) {
				$lineswithoutlevel0[] = $lines[$i];
			}
		}
	}

	if (empty($oldprojectforbreak)) {
		$oldprojectforbreak = (empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT) ? 0 : -1); // 0 = start break, -1 = never break
	}

	$restrictBefore = null;

	if (! empty($conf->global->PROJECT_TIMESHEET_PREVENT_AFTER_MONTHS)) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
		$restrictBefore = dol_time_plus_duree(dol_now(), - $conf->global->PROJECT_TIMESHEET_PREVENT_AFTER_MONTHS, 'm');
	}

	for ($i = 0; $i < $numlines; $i++) {
		if ($parent == 0) {
			$level = 0;
		}

		if ($lines[$i]->fk_task_parent == $parent) {
			$obj = &$lines[$i]; // To display extrafields
			// If we want all or we have a role on task, we show it
			if (empty($mine) || !empty($tasksrole[$lines[$i]->id])) {
				if ($restricteditformytask == 2 && empty($tasksrole[$lines[$i]->id])) {    // we have no role on task and we request to hide such cases
					continue;
				}

				// Break on a new project
				if ($parent == 0 && $lines[$i]->fk_project != $lastprojectid) {
					$lastprojectid = $lines[$i]->fk_project;
					$projectstatic->id = $lines[$i]->fk_project;
				}

				$projectstatic->id = $lines[$i]->fk_project;
				$projectstatic->ref = $lines[$i]->projectref;
				$projectstatic->title = $lines[$i]->projectlabel;
				$projectstatic->public = $lines[$i]->public;
				$projectstatic->thirdparty_name = $lines[$i]->thirdparty_name;
				$projectstatic->status = $lines[$i]->projectstatus;

				$taskstatic->id = $lines[$i]->id;
				$taskstatic->ref = ($lines[$i]->ref ?: $lines[$i]->id);
				$taskstatic->label = $lines[$i]->label;
				$taskstatic->date_start = $lines[$i]->date_start;
				$taskstatic->date_end = $lines[$i]->date_end;

				$thirdpartystatic->id = $lines[$i]->thirdparty_id;
				$thirdpartystatic->name = $lines[$i]->thirdparty_name;
				$thirdpartystatic->email = $lines[$i]->thirdparty_email;

				if (empty($oldprojectforbreak) || ($oldprojectforbreak != -1 && $oldprojectforbreak != $projectstatic->id)) {
					$addcolspan = 0;
					if (!empty($arrayfields['timeconsumed']['checked'])) {
						$addcolspan++;
					}

					print '<tr class="oddeven trforbreak nobold">' . "\n";
					print '<td colspan="' . (2 + $addcolspan + $daysInRange) . '">';
					print $projectstatic->getNomUrl(1, '', 0, '<strong>' . $langs->transnoentitiesnoconv("YourRole") . ':</strong> ' . $projectsrole[$lines[$i]->fk_project]);
					if ($thirdpartystatic->id > 0) {
						print ' - ' . $thirdpartystatic->getNomUrl(1);
					}
					if ($projectstatic->title) {
						print ' - ';
						print '<span class="secondary" title="' . $projectstatic->title . '">' . dol_trunc($projectstatic->title, '64') . '</span>';
					}

					print '</td>';
					print '</tr>';
				}

				if ($oldprojectforbreak != -1) {
					$oldprojectforbreak = $projectstatic->id;
				}

				print '<tr class="oddeven" data-taskid="' . $lines[$i]->id . '" >' . "\n";

				// Ref
				print '<td class="nowrap">';
				print '<!-- Task id = ' . $lines[$i]->id . ' -->';
				for ($k = 0; $k < $level; $k++) {
					print '<div class="marginleftonly">';
				}
				print $taskstatic->getNomUrl(1, 'withproject', 'time');
				if (isTaskFavorite($taskstatic->id, $fuser->id)) {
					print '<span class="fas fa-star"></span>';
				} else {
					print '<span class="far fa-star"></span>';
				}
				// Label task
				print '<br>';
				print '<span class="opacitymedium" title="' . $taskstatic->label . '">' . dol_trunc($taskstatic->label, '64') . '</span>';
				for ($k = 0; $k < $level; $k++) {
					print "</div>";
				}
				print "</td>\n";

				if (!empty($arrayfields['timeconsumed']['checked'])) {
					// Time spent by user
					print '<td class="right">';
					$firstday = dol_print_date($firstdaytoshow, 'dayrfc');
					$lastday = dol_print_date($lastdaytoshow, 'dayrfc');
					$filter = ' AND t.task_datehour BETWEEN ' . "'" . $firstday . "'" . ' AND ' . "'" . $lastday . "'";
					$tmptimespent = $taskstatic->getSummaryOfTimeSpent($fuser->id, $filter);
					if ($tmptimespent['total_duration']) {
						print convertSecondToTime($tmptimespent['total_duration'], 'allhourmin');
					} else {
						print '--:--';
					}
					print "</td>\n";
				}

				$disabledtask = 1;

				if ($lines[$i]->public || !empty($projectsrole[$lines[$i]->fk_project]) || $user->rights->projet->all->creer) {
					$disabledproject = 0;
					$disabledtask = 0;
				}
				if ($restricteditformytask && empty($tasksrole[$lines[$i]->id])) {
					$disabledtask = 1;
				}

				// Fields to show current time
                $modeinput = 'hours';
				for ($idw = 0; $idw < $daysInRange; $idw++) {
					$tmpday = dol_time_plus_duree($firstdaytoshow, $idw, 'd');


					$cellCSS = '';

					if (!$isavailable[$tmpday]['morning'] && !$isavailable[$tmpday]['afternoon']) {

						if ($isavailable[$tmpday]['morning_reason'] == 'public_holiday') {
							$cellCSS = 'onholidayallday';
						} else if ($isavailable[$tmpday]['morning_reason'] == 'week_end') {
							$cellCSS = 'weekend';
						}
					} else {
						$cellCSS = '';
					}


					$tmparray = dol_getdate($tmpday);

					$totalforeachday[$tmpday] = $timeSpentOnTasks[$lines[$i]->id][dol_print_date($tmpday, 'day')];

					$alreadyspent = '';
					if ($totalforeachday[$tmpday] > 0) {
						$alreadyspent = convertSecondToTime($totalforeachday[$tmpday], 'allhourmin');
					}
					$alttitle = $langs->trans("AddHereTimeSpentForDay", $tmparray['day'], $tmparray['mon']);

//                    global $numstartworkingday, $numendworkingday;
//                    $cssweekend = '';
//                    if (($idw + 1 < $numstartworkingday) || ($idw + 1 > $numendworkingday)) {    // This is a day is not inside the setup of working days, so we use a week-end css.
//                        $cssweekend = 'weekend';
//                    }

					$disabledtaskday = $disabledtask;

					if (! $disabledtask && $restrictBefore && $tmpday < $restrictBefore) {
						$disabledtaskday = 1;
					}

					$tableCell = '<td class="center '.$idw. ' ' .$cellCSS.'">';
					$placeholder = '';
					if ($alreadyspent) {
						$tableCell .= '<span class="timesheetalreadyrecorded" title="texttoreplace"><input type="text" class="center smallpadd" size="2" disabled id="timespent['.$inc.']['.$idw.']" name="task['.$lines[$i]->id.']['.$idw.']" value="'.$alreadyspent.'"></span>';
					}
					$tableCell .= '<input type="text" alt="'.($disabledtaskday ? '' : $alttitle).'" title="'.($disabledtaskday ? '' : $alttitle).'" '.($disabledtaskday ? 'disabled' : $placeholder).' class="center smallpadd" size="2" id="timeadded['.$inc.']['.$idw.']" name="task['.$lines[$i]->id.']['.$idw.']" value="" cols="2"  maxlength="5"';
					$tableCell .= ' onkeypress="return regexEvent(this,event,\'timeChar\')"';
					$tableCell .= ' onkeyup="updateTotal('.$idw.',\''.$modeinput.'\')"';
					$tableCell .= ' onblur="regexEvent(this,event,\''.$modeinput.'\'); updateTotal('.$idw.',\''.$modeinput.'\')" />';
					$tableCell .= '</td>';
					print $tableCell;
				}
                print '<td></td>';
                print '</tr>';
			}

			// Call to show task with a lower level (task under the current task)
			$inc++;
			$level++;
			if ($lines[$i]->id > 0) {
				$ret = doliSirhTaskLinesWithinRange($inc, $firstdaytoshow, $lastdaytoshow, $fuser, $lines[$i]->id, ($parent == 0 ? $lineswithoutlevel0 : $lines), $level, $projectsrole, $tasksrole, $mine, $restricteditformytask, $isavailable, $oldprojectforbreak, $arrayfields, $extrafields, $timeSpentOnTasks);
				foreach ($ret as $key => $val) {
					$totalforeachday[$key] += $val;
				}
			}
			$level--;
		}
	}

	return $totalforeachday;
}

/**
 *      Return a string to show the box with list of available documents for object.
 *      This also set the property $this->numoffiles
 *
 *      @param      string				$modulepart         Module the files are related to ('propal', 'facture', 'facture_fourn', 'mymodule', 'mymodule:nameofsubmodule', 'mymodule_temp', ...)
 *      @param      string				$modulesubdir       Existing (so sanitized) sub-directory to scan (Example: '0/1/10', 'FA/DD/MM/YY/9999'). Use '' if file is not into subdir of module.
 *      @param      string				$filedir            Directory to scan
 *      @param      string				$urlsource          Url of origin page (for return)
 *      @param      int|string[]        $genallowed         Generation is allowed (1/0 or array list of templates)
 *      @param      int					$delallowed         Remove is allowed (1/0)
 *      @param      string				$modelselected      Model to preselect by default
 *      @param      integer				$allowgenifempty	Allow generation even if list of template ($genallowed) is empty (show however a warning)
 *      @param      integer				$forcenomultilang	Do not show language option (even if MAIN_MULTILANGS defined)
 *      @param      int					$iconPDF            Deprecated, see getDocumentsLink
 * 		@param		int					$notused	        Not used
 * 		@param		integer				$noform				Do not output html form tags
 * 		@param		string				$param				More param on http links
 * 		@param		string				$title				Title to show on top of form. Example: '' (Default to "Documents") or 'none'
 * 		@param		string				$buttonlabel		Label on submit button
 * 		@param		string				$codelang			Default language code to use on lang combo box if multilang is enabled
 * 		@param		string				$morepicto			Add more HTML content into cell with picto
 *      @param      Object              $object             Object when method is called from an object card.
 *      @param		int					$hideifempty		Hide section of generated files if there is no file
 *      @param      string              $removeaction       (optional) The action to remove a file
 *      @param      int                 $active             (optional) To show gen button disabled
 *      @param      string              $tooltiptext       (optional) Tooltip text when gen button disabled
 * 		@return		string              					Output string with HTML array of documents (might be empty string)
 */
function doliSirhShowDocuments($modulepart, $modulesubdir, $filedir, $urlsource, $genallowed, $delallowed = 0, $modelselected = '', $allowgenifempty = 1, $forcenomultilang = 0, $notused = 0, $noform = 0, $param = '', $title = '', $buttonlabel = '', $codelang = '', $morepicto = '', $object = null, $hideifempty = 0, $removeaction = 'remove_file', $active = 1, $tooltiptext = '')
{
	global $db, $langs, $conf, $user, $hookmanager, $form;

	if ( ! is_object($form)) $form = new Form($db);

	include_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

	// Add entity in $param if not already exists
	if ( ! preg_match('/entity\=[0-9]+/', $param)) {
		$param .= ($param ? '&' : '') . 'entity=' . ( ! empty($object->entity) ? $object->entity : $conf->entity);
	}

	$hookmanager->initHooks(array('formfile'));

	// Get list of files
	$file_list = null;
	if ( ! empty($filedir)) {
		$file_list = dol_dir_list($filedir, 'files', 0, '(\.odt|\.zip|\.pdf)', '', 'date', SORT_DESC, 1);
	}
	if ($hideifempty && empty($file_list)) return '';

	$out         = '';
	$forname     = 'builddoc';
	$headershown = 0;
	$showempty   = 0;

	$out .= "\n" . '<!-- Start show_document -->' . "\n";

	$titletoshow                       = $langs->trans("Documents");
	if ( ! empty($title)) $titletoshow = ($title == 'none' ? '' : $title);

	// Show table
	if ($genallowed) {
		$submodulepart = $modulepart;
		// modulepart = 'nameofmodule' or 'nameofmodule:NameOfObject'
		$tmp = explode(':', $modulepart);
		if ( ! empty($tmp[1])) {
			$modulepart    = $tmp[0];
			$submodulepart = $tmp[1];
		}

		// For normalized external modules.
		$file = dol_buildpath('/' . $modulepart . '/core/modules/' . $modulepart . '/dolisirhdocuments/' . strtolower($submodulepart) . '/modules_' . strtolower($submodulepart) . '.php', 0);
		include_once $file;

		$class = 'ModeleODT' . $submodulepart;

		if (class_exists($class)) {
			if (preg_match('/specimen/', $param)) {
				$type      = strtolower($class) . 'specimen';
				$modellist = array();

				include_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
				$modellist = getListOfModels($db, $type, 0);
			} else {
				$modellist = call_user_func($class . '::liste_modeles', $db, 100);
			}
		} else {
			dol_print_error($db, "Bad value for modulepart '" . $modulepart . "' in showdocuments");
			return -1;
		}

		// Set headershown to avoid to have table opened a second time later
		$headershown = 1;

		if (empty($buttonlabel)) $buttonlabel = $langs->trans('Generate');

		if ($conf->browser->layout == 'phone') $urlsource .= '#' . $forname . '_form'; // So we switch to form after a generation
		if (empty($noform)) $out                          .= '<form action="' . $urlsource . (empty($conf->global->MAIN_JUMP_TAG) ? '' : '#builddoc') . '" id="' . $forname . '_form" method="post">';
		$out                                              .= '<input type="hidden" name="action" value="builddoc">';
		$out                                              .= '<input type="hidden" name="token" value="' . newToken() . '">';

		$out .= load_fiche_titre($titletoshow, '', '');
		$out .= '<div class="div-table-responsive-no-min">';
		$out .= '<table class="liste formdoc noborder centpercent">';

		$out .= '<tr class="liste_titre">';

		$addcolumforpicto = ($delallowed || $morepicto);
		$colspan          = (3 + ($addcolumforpicto ? 1 : 0)); $colspanmore = 0;

		$out .= '<th colspan="' . $colspan . '" class="formdoc liste_titre maxwidthonsmartphone center">';
		// Model
		if ( ! empty($modellist)) {
			asort($modellist);
			$out      .= '<span class="hideonsmartphone"> <i class="fas fa-file-word"></i> </span>';
			$modellist = array_filter($modellist, 'remove_indexdolisirh');
			if (is_array($modellist)) {    // If there is only one element
				foreach ($modellist as $key => $modellistsingle) {
					$arrayvalues              = preg_replace('/template_/', '', $modellistsingle);
					$modellist[$key] = $langs->trans($arrayvalues);
					$modelselected            = $key;
				}
			}
			$morecss                                        = 'maxwidth200';
			if ($conf->browser->layout == 'phone') $morecss = 'maxwidth100';
			$out                                           .= $form->selectarray('model', $modellist, $modelselected, $showempty, 0, 0, '', 0, 0, 0, '', $morecss);

			if ($conf->use_javascript_ajax) {
				$out .= ajax_combobox('model');
			}
		} else {
			$out .= '<div class="float">' . $langs->trans("Files") . '</div>';
		}

		// Button
		if ($active) {
			$genbutton  = '<input style="display : none" class="button buttongen" id="' . $forname . '_generatebutton" name="' . $forname . '_generatebutton" type="submit" value="' . $buttonlabel . '"' . '>';
			$genbutton .= '<label for="' . $forname . '_generatebutton">';
			$genbutton .= '<div class="wpeo-button button-square-40 button-blue wpeo-tooltip-event" aria-label="' . $langs->trans('Generate') . '"><i class="fas fa-print button-icon"></i></div>';
			$genbutton .= '</label>';
		} else {
			$genbutton  = '<input style="display : none" class="button buttongen disabled" name="' . $forname . '_generatebutton" style="cursor: not-allowed" value="' . $buttonlabel . '"' . '>';
			$genbutton .= '<label for="' . $forname . '_generatebutton">';
			if (empty($file_list)) {
				$genbutton .= '<i class="fas fa-exclamation-triangle pictowarning wpeo-tooltip-event" aria-label="' . $langs->trans($tooltiptext) . '"></i>';
			}
			$genbutton .= '<div class="wpeo-button button-square-40 button-grey wpeo-tooltip-event" aria-label="' . $langs->trans('Generate') . '"><i class="fas fa-print button-icon"></i></div>';
			$genbutton .= '</label>';
		}

		//      if ( ! $allowgenifempty && ! is_array($modellist) && empty($modellist)) $genbutton .= ' disabled';
		//      $genbutton                                                                         .= '>';
		//      if ($allowgenifempty && ! is_array($modellist) && empty($modellist) && empty($conf->dol_no_mouse_hover) && $modulepart != 'unpaid') {
		//          $langs->load("errors");
		//          $genbutton .= ' ' . img_warning($langs->transnoentitiesnoconv("WarningNoDocumentModelActivated"));
		//      }
		//      if ( ! $allowgenifempty && ! is_array($modellist) && empty($modellist) && empty($conf->dol_no_mouse_hover) && $modulepart != 'unpaid') $genbutton = '';
		//      if (empty($modellist) && ! $showempty && $modulepart != 'unpaid') $genbutton                                                                      = '';
		$out                                                                                                                                             .= $genbutton;
		//      if ( ! $active) {
		//          $htmltooltip  = '';
		//          $htmltooltip .= $tooltiptext;
		//
		//          $out .= '<span class="center">';
		//          $out .= $form->textwithpicto($langs->trans('Help'), $htmltooltip, 1, 0);
		//          $out .= '</span>';
		//      }

		$out .= '</th>';

		if ( ! empty($hookmanager->hooks['formfile'])) {
			foreach ($hookmanager->hooks['formfile'] as $module) {
				if (method_exists($module, 'formBuilddocLineOptions')) {
					$colspanmore++;
					$out .= '<th></th>';
				}
			}
		}
		$out .= '</tr>';

		// Execute hooks
		$parameters = array('colspan' => ($colspan + $colspanmore), 'socid' => (isset($GLOBALS['socid']) ? $GLOBALS['socid'] : ''), 'id' => (isset($GLOBALS['id']) ? $GLOBALS['id'] : ''), 'modulepart' => $modulepart);
		if (is_object($hookmanager)) {
			$reshook = $hookmanager->executeHooks('formBuilddocOptions', $parameters, $GLOBALS['object']);
			$out    .= $hookmanager->resPrint;
		}
	}

	// Get list of files
	if ( ! empty($filedir)) {
		$link_list = array();
		if (is_object($object) && $object->id > 0) {
			require_once DOL_DOCUMENT_ROOT . '/core/class/link.class.php';
			$link      = new Link($db);
			$sortfield = $sortorder = null;
			$res       = $link->fetchAll($link_list, $object->element, $object->id, $sortfield, $sortorder);
		}

		$out .= '<!-- html.formfile::showdocuments -->' . "\n";

		// Show title of array if not already shown
		if (( ! empty($file_list) || ! empty($link_list) || preg_match('/^massfilesarea/', $modulepart))
			&& ! $headershown) {
			$headershown = 1;
			$out        .= '<div class="titre">' . $titletoshow . '</div>' . "\n";
			$out        .= '<div class="div-table-responsive-no-min">';
			$out        .= '<table class="noborder centpercent" id="' . $modulepart . '_table">' . "\n";
		}

		// Loop on each file found
		if (is_array($file_list)) {
			foreach ($file_list as $file) {
				// Define relative path for download link (depends on module)
				$relativepath                    = $file["name"]; // Cas general
				if ($modulesubdir) $relativepath = $modulesubdir . "/" . $file["name"]; // Cas propal, facture...

				$out .= '<tr class="oddeven">';

				$documenturl                                                      = DOL_URL_ROOT . '/document.php';
				if (isset($conf->global->DOL_URL_ROOT_DOCUMENT_PHP)) $documenturl = $conf->global->DOL_URL_ROOT_DOCUMENT_PHP; // To use another wrapper

				// Show file name with link to download
				$out .= '<td class="minwidth200">';
				$out .= '<a class="documentdownload paddingright" href="' . $documenturl . '?modulepart=' . $modulepart . '&amp;file=' . urlencode($relativepath) . ($param ? '&' . $param : '') . '"';

				$mime                                  = dol_mimetype($relativepath, '', 0);
				if (preg_match('/text/', $mime)) $out .= ' target="_blank"';
				$out                                  .= '>';
				$out                                  .= img_mime($file["name"], $langs->trans("File") . ': ' . $file["name"]);
				$out                                  .= dol_trunc($file["name"], 150);
				$out                                  .= '</a>' . "\n";
				$out                                  .= '</td>';

				// Show file size
				$size = ( ! empty($file['size']) ? $file['size'] : dol_filesize($filedir . "/" . $file["name"]));
				$out .= '<td class="nowrap right">' . dol_print_size($size, 1, 1) . '</td>';

				// Show file date
				$date = ( ! empty($file['date']) ? $file['date'] : dol_filemtime($filedir . "/" . $file["name"]));
				$out .= '<td class="nowrap right">' . dol_print_date($date, 'dayhour', 'tzuser') . '</td>';

				if ($delallowed || $morepicto) {
					$out .= '<td class="right nowraponall">';
					if ($delallowed) {
						$tmpurlsource = preg_replace('/#[a-zA-Z0-9_]*$/', '', $urlsource);
						$out         .= '<a href="' . $tmpurlsource . ((strpos($tmpurlsource, '?') === false) ? '?' : '&amp;') . 'action=' . $removeaction . '&amp;file=' . urlencode($relativepath);
						$out         .= ($param ? '&amp;' . $param : '');
						$out         .= '">' . img_picto($langs->trans("Delete"), 'delete') . '</a>';
					}
					if ($morepicto) {
						$morepicto = preg_replace('/__FILENAMEURLENCODED__/', urlencode($relativepath), $morepicto);
						$out      .= $morepicto;
					}
					$out .= '</td>';
				}

				if (is_object($hookmanager)) {
					$parameters = array('colspan' => ($colspan + $colspanmore), 'socid' => (isset($GLOBALS['socid']) ? $GLOBALS['socid'] : ''), 'id' => (isset($GLOBALS['id']) ? $GLOBALS['id'] : ''), 'modulepart' => $modulepart, 'relativepath' => $relativepath);
					$res        = $hookmanager->executeHooks('formBuilddocLineOptions', $parameters, $file);
					if (empty($res)) {
						$out .= $hookmanager->resPrint; // Complete line
						$out .= '</tr>';
					} else {
						$out = $hookmanager->resPrint; // Replace all $out
					}
				}
			}
		}
		// Loop on each link found
		//      if (is_array($link_list))
		//      {
		//          $colspan = 2;
		//
		//          foreach ($link_list as $file)
		//          {
		//              $out .= '<tr class="oddeven">';
		//              $out .= '<td colspan="'.$colspan.'" class="maxwidhtonsmartphone">';
		//              $out .= '<a data-ajax="false" href="'.$file->url.'" target="_blank">';
		//              $out .= $file->label;
		//              $out .= '</a>';
		//              $out .= '</td>';
		//              $out .= '<td class="right">';
		//              $out .= dol_print_date($file->datea, 'dayhour');
		//              $out .= '</td>';
		//              if ($delallowed || $printer || $morepicto) $out .= '<td></td>';
		//              $out .= '</tr>'."\n";
		//          }
		//      }

		if (count($file_list) == 0 && count($link_list) == 0 && $headershown) {
			$out .= '<tr><td colspan="' . (3 + ($addcolumforpicto ? 1 : 0)) . '" class="opacitymedium">' . $langs->trans("None") . '</td></tr>' . "\n";
		}
	}

	if ($headershown) {
		// Affiche pied du tableau
		$out .= "</table>\n";
		$out .= "</div>\n";
		if ($genallowed) {
			if (empty($noform)) $out .= '</form>' . "\n";
		}
	}
	$out .= '<!-- End show_document -->' . "\n";

	return $out;
}

/**
 *	Exclude index.php files from list of models for document generation
 *
 * @param   string $model
 * @return  '' or $model
 */
function remove_indexdolisirh($model)
{
	if (preg_match('/index.php/', $model)) {
		return '';
	} else {
		return $model;
	}
}

/**
 * 	Return list of activated modules usable for document generation
 *
 * 	@param	DoliDB		$db				    Database handler
 * 	@param	string		$type			    Type of models (company, invoice, ...)
 *  @param  int		    $maxfilenamelength  Max length of value to show
 * 	@return	array|int			    		0 if no module is activated, or array(key=>label). For modules that need directory scan, key is completed with ":filename".
 */
function doliSirhGetListOfModels($db, $type, $maxfilenamelength = 0)
{
	global $conf, $langs;
	$liste = array();
	$found = 0;
	$dirtoscan = '';

	$sql = "SELECT nom as id, nom as doc_template_name, libelle as label, description as description";
	$sql .= " FROM ".MAIN_DB_PREFIX."document_model";
	$sql .= " WHERE type = '".$db->escape($type)."'";
	$sql .= " AND entity IN (0,".$conf->entity.")";
	$sql .= " ORDER BY description DESC";

	dol_syslog('/core/lib/function2.lib.php::getListOfModels', LOG_DEBUG);
	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		$i = 0;
		while ($i < $num) {
			$found = 1;

			$obj = $db->fetch_object($resql);

			// If this generation module needs to scan a directory, then description field is filled
			// with the constant that contains list of directories to scan (COMPANY_ADDON_PDF_ODT_PATH, ...).
			if (!empty($obj->description)) {	// A list of directories to scan is defined
				include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

				$const = $obj->description;
				//irtoscan.=($dirtoscan?',':'').preg_replace('/[\r\n]+/',',',trim($conf->global->$const));
				$dirtoscan = preg_replace('/[\r\n]+/', ',', trim($conf->global->$const));

				$listoffiles = array();

				// Now we add models found in directories scanned
				$listofdir = explode(',', $dirtoscan);
				foreach ($listofdir as $key => $tmpdir) {
					$tmpdir = trim($tmpdir);
					$tmpdir = preg_replace('/DOL_DATA_ROOT/', DOL_DATA_ROOT, $tmpdir);
					$tmpdir = preg_replace('/DOL_DOCUMENT_ROOT/', DOL_DOCUMENT_ROOT, $tmpdir);

					if (!$tmpdir) {
						unset($listofdir[$key]);
						continue;
					}
					if (is_dir($tmpdir)) {
						// all type of template is allowed
						$tmpfiles = dol_dir_list($tmpdir, 'files', 0, '', '', 'name', SORT_ASC, 0);
						if (count($tmpfiles)) {
							$listoffiles = array_merge($listoffiles, $tmpfiles);
						}
					}
				}

				if (count($listoffiles)) {
					foreach ($listoffiles as $record) {
						$max = ($maxfilenamelength ? $maxfilenamelength : 28);
						$liste[$obj->id.':'.$record['fullname']] = dol_trunc($record['name'], $max, 'middle');
					}
				} else {
					$liste[0] = $obj->label.': '.$langs->trans("None");
				}
			} else {
				if ($type == 'member' && $obj->doc_template_name == 'standard') {   // Special case, if member template, we add variant per format
					global $_Avery_Labels;
					include_once DOL_DOCUMENT_ROOT.'/core/lib/format_cards.lib.php';
					foreach ($_Avery_Labels as $key => $val) {
						$liste[$obj->id.':'.$key] = ($obj->label ? $obj->label : $obj->doc_template_name).' '.$val['name'];
					}
				} else {
					// Common usage
					$liste[$obj->id] = $obj->label ? $obj->label : $obj->doc_template_name;
				}
			}
			$i++;
		}
	} else {
		dol_print_error($db);
		return -1;
	}

	if ($found) {
		return $liste;
	} else {
		return 0;
	}
}
