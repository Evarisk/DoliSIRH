<?php
/* Copyright (C) 2021-2023 EVARISK <technique@evarisk.com>
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
 * \file    lib/dolisirh_timespent.lib.php
 * \ingroup dolisirh
 * \brief   Library files with common functions for TimeSpent.
 */

// Load DoliSIRH libraries.
require_once __DIR__ . '/dolisirh_function.lib.php';

/**
 * Prepare timespent pages header.
 *
 * @param  string $mode   Mode.
 * @param  User   $fkUser Filter on user.
 * @return array  $head   Array of tabs.
 */
function timespent_prepare_head(string $mode, User $fkUser): array
{
    // Global variables definitions.
    global $conf, $langs, $user;

    // Initialize values.
    $h    = 0;
    $head = [];

    $param = ($mode ? '&mode=' . $mode : '');
    if ($fkUser->id > 0 && $fkUser->id != $user->id) {
        $param .= '&search_usertoprocessid=' . $fkUser->id;
    }

    if (!getDolGlobalInt($conf->global->PROJECT_DISABLE_TIMESHEET_PERMONTH)) {
        $head[$h][0] = DOL_URL_ROOT . '/custom/dolisirh/view/timespent_month.php' . ($param ? '?' . $param : '');
        $head[$h][1] = $langs->trans('InputPerMonth');
        $head[$h][2] = 'inputpermonth';
        $h++;
    }

    if (!getDolGlobalInt($conf->global->PROJECT_DISABLE_TIMESHEET_PERWEEK)) {
        $head[$h][0] = DOL_URL_ROOT . '/custom/dolisirh/view/timespent_week.php' . ($param ? '?' . $param : '');
        $head[$h][1] = $langs->trans('InputPerWeek');
        $head[$h][2] = 'inputperweek';
        $h++;
    }

    if (!getDolGlobalInt($conf->global->PROJECT_DISABLE_TIMESHEET_PERTIME)) {
        $head[$h][0] = DOL_URL_ROOT.'/custom/dolisirh/view/timespent_day.php' . ($param ? '?' . $param : '');
        $head[$h][1] = $langs->trans('InputPerDay');
        $head[$h][2] = 'inputperday';
        $h++;
    }

    complete_head_from_modules($conf, $langs, null, $head, $h, 'project_timesheet');

    complete_head_from_modules($conf, $langs, null, $head, $h, 'project_timesheet', 'remove');

    return $head;
}

/**
 * Load time spent by tasks within a time range.
 *
 * @param  int       $timestampStart Timestamp first day.
 * @param  int       $timestampEnd   Timestamp last day.
 * @param  int       $userID         Time spent by a particular user.
 * @param  array     $daysAvailable  Available days.
 * @return array                     Array with minutes, hours and total time spent.
 * @throws Exception
 */
function load_time_spent_on_tasks_within_range(int $timestampStart, int $timestampEnd, array $daysAvailable, int $userID = 0): array
{
    global $db;

    if (empty($timestampStart)) {
        dol_print_error('', 'Error datestart parameter is empty');
    }

    $task    = new Task($db);
    $userTmp = new User($db);

    if ($userID > 0) {
        $userTmp->fetch($userID);
    }

    $timeSpentOnTasks = ['days' => 0, 'hours' => 0, 'minutes' => 0, 'total' => 0];
    $timeSpentList    = $task->fetchAllTimeSpent($userTmp, 'AND (ptt.task_date >= "' . $db->idate($timestampStart) . '" AND ptt.task_date < "' . $db->idate($timestampEnd) . '")');
    if (is_array($timeSpentList) && !empty($timeSpentList)) {
        foreach ($timeSpentList as $timeSpent) {
            $hours   = floor($timeSpent->timespent_duration / 3600);
            $minutes = floor($timeSpent->timespent_duration / 60);
            if ($daysAvailable[$timeSpent->timespent_date]['morning'] && $daysAvailable[$timeSpent->timespent_date]['afternoon']) {
                $timeSpentOnTasks['hours']   += $hours;
                $timeSpentOnTasks['minutes'] += $minutes;
                $timeSpentOnTasks['total']   += $timeSpent->timespent_duration;
                if (!empty($timeSpent->timespent_note)) {
                    $timeSpentOnTasks[$timeSpent->fk_task]['comments'][dol_print_date($timeSpent->timespent_date, 'day')][$timeSpent->timespent_id] = $timeSpent->timespent_note;
                }
                $timeSpentOnTasks[$timeSpent->fk_task]['project_ref']   = $timeSpent->project_ref;
                $timeSpentOnTasks[$timeSpent->fk_task]['project_label'] = $timeSpent->project_label;
                $timeSpentOnTasks[$timeSpent->fk_task]['task_ref']      = $timeSpent->task_ref;
                $timeSpentOnTasks[$timeSpent->fk_task]['task_label']    = $timeSpent->task_label;

                $timeSpentOnTasks[$timeSpent->fk_task][dol_print_date($timeSpent->timespent_date, 'day')] += $timeSpent->timespent_duration;
            }
        }
        $timeSpentOnTasks['days'] = count($daysAvailable);
    }

    return $timeSpentOnTasks;
}

/**
 * Load time to spend within a time range.
 *
 * @param  int          $timestampStart Timestamp first day.
 * @param  int          $timestampEnd   Timestamp last day.
 * @param  Workinghours $workingHours   Working hours object.
 * @param  array        $daysAvailable  Available days.
 * @return array                        Array with minutes, days on time to spend.
 * @throws Exception
 */
function load_planned_time_within_range(int $timestampStart, int $timestampEnd, Workinghours $workingHours, array $daysAvailable): array
{
    if (empty($timestampStart)) {
        dol_print_error('', 'Error datestart parameter is empty');
    }

    $timeToSpend = ['days' => 0, 'minutes' => 0];
    $daysInRange = dolisirh_num_between_day($timestampStart, $timestampEnd);
    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $newTimestampStart = dol_time_plus_duree($timestampStart, $idw, 'd'); // $firstdaytoshow is a date with hours = 0.
        if ($daysAvailable[$newTimestampStart]['morning'] && $daysAvailable[$newTimestampStart]['afternoon']) {
            $currentDay = date('l', $newTimestampStart);
            $currentDay = 'workinghours_' . strtolower($currentDay);

            $timeToSpend['minutes'] += $workingHours->$currentDay;
            if ($workingHours->$currentDay / 60 > 0) {
                $timeToSpend['days']++;
            }
        }
    }

    return $timeToSpend;
}

/**
 * Load time to spend within a time range.
 *
 * @param  int          $timestampStart    Timestamp first day.
 * @param  int          $timestampEnd      Timestamp last day.
 * @param  Workinghours $workingHours      Working hours object.
 * @param  array        $daysAvailable     Available days.
 * @return array        $passedWorkingTime Array with minutes on passed working time.
 * @throws Exception
 */
function load_passed_time_within_range(int $timestampStart, int $timestampEnd, Workinghours $workingHours, array $daysAvailable): array
{
    if (empty($timestampStart)) {
        dol_print_error('', 'Error datestart parameter is empty');
    }

    $passedWorkingTime = ['minutes' => 0];
    $daysInRange         = dolisirh_num_between_day($timestampStart, $timestampEnd);
    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $newTimestampStart = dol_time_plus_duree($timestampStart, $idw, 'd'); // $firstdaytoshow is a date with hours = 0.
        if ($daysAvailable[$newTimestampStart]['morning'] && $daysAvailable[$newTimestampStart]['afternoon']) {
            $currentDay = date('l', $newTimestampStart);
            $currentDay = 'workinghours_' . strtolower($currentDay);

            $passedWorkingTime['minutes'] += $workingHours->$currentDay;
        }
    }

    return $passedWorkingTime;
}

/**
 * Load difference between passed time and spent time within a time range.
 *
 * @param  int          $timestampStart Timestamp first day.
 * @param  int          $timestampEnd   Timestamp last day.
 * @param  Workinghours $workingHours   Working hours object.
 * @param  array        $daysAvailable  Available days.
 * @param  int          $userID         Time spent by a particular user.
 * @return int                          Array with minutes on passed working time.
 * @throws Exception
 */
function load_difference_between_passed_and_spent_time_within_range(int $timestampStart, int $timestampEnd, Workinghours $workingHours, array $daysAvailable, int $userID = 0): int
{
    if (empty($timestampStart)) {
        dol_print_error('', 'Error datestart parameter is empty');
    }

    $passedWorkingTime = load_passed_time_within_range($timestampStart, $timestampEnd, $workingHours, $daysAvailable);
    $spentWorkingTime  = load_time_spent_on_tasks_within_range($timestampStart, $timestampEnd, $daysAvailable, $userID);

    return $passedWorkingTime['minutes'] - $spentWorkingTime['minutes'];
}

/**
 * Load all time spending infos within a time range.
 *
 * @param  int          $timestampStart Timestamp first day.
 * @param  int          $timestampEnd   Timestamp last day.
 * @param  Workinghours $workingHours   Working hours object.
 * @param  array        $daysAvailable  Available days.
 * @param  int          $userID         Time spent by a particular user.
 * @return array                        Array with all time spent infos.
 * @throws Exception
 */
function load_time_spending_infos_within_range(int $timestampStart, int $timestampEnd, Workinghours $workingHours, array $daysAvailable, int $userID = 0): array
{
    if (empty($timestampStart)) {
        dol_print_error('', 'Error datestart parameter is empty');
    }

    $plannedWorkingTime    = load_planned_time_within_range($timestampStart, $timestampEnd, $workingHours, $daysAvailable);
    $passedWorkingTime     = load_passed_time_within_range($timestampStart, $timestampEnd, $workingHours, $daysAvailable);
    $spentWorkingTime      = load_time_spent_on_tasks_within_range($timestampStart, $timestampEnd, $daysAvailable, $userID);
    $workingTimeDifference = load_difference_between_passed_and_spent_time_within_range($timestampStart, $timestampEnd, $workingHours, $daysAvailable, $userID);

    return [
        'planned'    => $plannedWorkingTime,
        'passed'     => $passedWorkingTime,
        'spent'      => $spentWorkingTime,
        'difference' => $workingTimeDifference
    ];
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
        $sql .= "DISTINCT"; // We may get several time the same record if user has several roles on same project/task
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
        if ($user->conf->DOLISIRH_SHOW_ONLY_FAVORITE_TASKS > 0) {
            $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."element_element as elel ON (t.rowid = elel.fk_target AND elel.targettype='project_task')";
        }
        if ($user->conf->DOLISIRH_SHOW_ONLY_TASKS_WITH_TIMESPENT > 0) {
            $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task_time as ptt ON (t.rowid = ptt.fk_task)";
        }
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet_task_extrafields as efpt ON (t.rowid = efpt.fk_object)";
        $sql .= " WHERE p.entity IN (".getEntity('project').")";
        $sql .= " AND t.fk_projet = p.rowid";
        if ($user->conf->DOLISIRH_SHOW_ONLY_FAVORITE_TASKS > 0) {
            $sql .= " AND elel.fk_target = t.rowid";
            $sql .= " AND elel.fk_source = " . $filteronprojuser;
        }

        if ($user->conf->DOLISIRH_SHOW_ONLY_TASKS_WITH_TIMESPENT > 0) {
            $sql .= " AND ptt.fk_task = t.rowid AND ptt.fk_user = " . $filteronprojuser;
            if ($timeMode == 'month') {
                $sql .= " AND MONTH(ptt.task_date) = " . $timeArray['month'];
            } else if ($timeMode == 'week') {
                $sql .= " AND WEEK(ptt.task_date, 7) = " . $timeArray['week'];
            } else if ($timeMode == 'day') {
                $sql .= " AND DAY(ptt.task_date) = " . $timeArray['day'];
            }
            $sql .= " AND YEAR(ptt.task_date) = " . $timeArray['year'];
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
 * @param   array       $daysAvailable			Array with data that say if user is available for several days for morning and afternoon
 * @param	int			$oldprojectforbreak		Old project id of last project break
 * @param	array		$arrayfields		    Array of additional column
 * @param	Extrafields	$extrafields		    Object extrafields
 * @return  array								Array with time spent for $fuser for each day of week on tasks in $lines and substasks
 */
function doliSirhLinesPerDay(&$inc, $parent, $fuser, $lines, &$level, &$projectsrole, &$tasksrole, $mine, $restricteditformytask, $preselectedday, &$daysAvailable, $oldprojectforbreak = 0, $arrayfields = array(), $extrafields = null)
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
                if (is_task_favorite($taskstatic->id, $fuser->id)) {
                    print ' <span class="fas fa-star"></span>';
                } else {
                    print ' <span class="far fa-star"></span>';
                }
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
                if (!$daysAvailable[$preselectedday]['morning'] && !$daysAvailable[$preselectedday]['afternoon']) {
                    $cssonholiday .= 'onholidayallday ';
                } elseif (!$daysAvailable[$preselectedday]['morning']) {
                    $cssonholiday .= 'onholidaymorning ';
                } elseif (!$daysAvailable[$preselectedday]['afternoon']) {
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
                $ret = doliSirhLinesPerDay($inc, $lines[$i]->id, $fuser, ($parent == 0 ? $lineswithoutlevel0 : $lines), $level, $projectsrole, $tasksrole, $mine, $restricteditformytask, $preselectedday, $daysAvailable, $oldprojectforbreak, $arrayfields, $extrafields);
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
 * @param   array       $daysAvailable			Array with data that say if user is available for several days for morning and afternoon
 * @param	int			$oldprojectforbreak		Old project id of last project break
 * @param	array		$arrayfields		    Array of additional column
 * @param	Extrafields	$extrafields		    Object extrafields
 * @return  array								Array with time spent for $fuser for each day of week on tasks in $lines and substasks
 */
function doliSirhTaskLinesWithinRange(&$inc, $firstdaytoshow, $lastdaytoshow, $fuser, $parent, $lines, &$level, &$projectsrole, &$tasksrole, $mine, $restricteditformytask, &$daysAvailable, $oldprojectforbreak = 0, $arrayfields = array(), $extrafields = null, $timeSpentOnTasks)
{
    global $conf, $db, $user, $langs;
    global $form, $formother, $projectstatic, $taskstatic, $thirdpartystatic;

    $numlines = count($lines);

    $lastprojectid = 0;
    $workloadforid = array();
    $totalforeachday = array();
    $lineswithoutlevel0 = array();

    $daysInRange = dolisirh_num_between_day($firstdaytoshow, $lastdaytoshow, 1);

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

        if ($lines[$i]->fk_task_parent != $parent && $user->conf->DOLISIRH_SHOW_ONLY_TASKS_WITH_TIMESPENT) {
            $lines[$i]->fk_task_parent = 0;
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
                $taskstatic->planned_workload = $lines[$i]->planned_workload;
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
                if (GETPOST('action') == 'toggleTaskFavorite') {
                    toggle_task_favorite(GETPOST('id'), $fuser->id);
                }
                if (is_task_favorite($taskstatic->id, $fuser->id)) {
                    print ' <span class="fas fa-star toggleTaskFavorite" id="'. $taskstatic->id .'" value="'. $taskstatic->id .'"></span>';
                } else {
                    print ' <span class="far fa-star toggleTaskFavorite" id="'. $taskstatic->id .'" value="'. $taskstatic->id .'"></span>';
                }
                if ($taskstatic->planned_workload != '') {
                    $tmparray = $taskstatic->getSummaryOfTimeSpent();
                    if ($tmparray['total_duration'] > 0 && !empty($taskstatic->planned_workload)) {
                        print ' <span class="task-progress ' . get_task_progress_color_class(round($tmparray['total_duration'] / $taskstatic->planned_workload * 100, 2)) . '">' . ' ' . round($tmparray['total_duration'] / $taskstatic->planned_workload * 100, 2) . ' %' . '</span>';
                        print ' <span>' . ' ' . convertSecondToTime($taskstatic->planned_workload, 'allhourmin') . '</span>';
                    } else {
                        print ' 0 %';
                    }
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
                    $filter = ' AND (t.task_date >= "' . $db->idate($firstdaytoshow) . '" AND t.task_date < "' . $db->idate(dol_time_plus_duree($lastdaytoshow, 1, 'd')) . '")';
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

                    if (!$daysAvailable[$tmpday]['morning'] && !$daysAvailable[$tmpday]['afternoon']) {

                        if ($daysAvailable[$tmpday]['morning_reason'] == 'public_holiday') {
                            $cellCSS = 'onholidayallday';
                        } else if ($daysAvailable[$tmpday]['morning_reason'] == 'week_end') {
                            $cellCSS = 'weekend';
                        }
                    } else {
                        $cellCSS = '';
                    }


                    $tmparray = dol_getdate($tmpday);

                    $totalforeachday[$tmpday] = $timeSpentOnTasks[$lines[$i]->id][dol_print_date($tmpday, 'day')];

                    $alreadyspent = '';
                    if ($totalforeachday[$tmpday] > 0) {
                        $timeSpentComments = $timeSpentOnTasks[$lines[$i]->id]['comments'][dol_print_date($tmpday, 'day')];
                        if (is_array($timeSpentComments) && !empty($timeSpentComments)) {
                            $text_tooltip = implode('', $timeSpentComments);
                        } else {
                            $text_tooltip = $langs->trans('TimeSpentAddWithNoComment');
                        }
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

                    $tableCell = '<td class="center '.$idw. ' ' .$cellCSS.'" data-cell="' . $idw . '">';
                    $placeholder = '';
                    if ($alreadyspent) {
                        $tableCell .= '<span class="timesheetalreadyrecorded wpeo-tooltip-event" aria-label="' . $text_tooltip . '"><input type="text" class="center smallpadd" size="2" disabled id="timespent['.$inc.']['.$idw.']" name="task['.$lines[$i]->id.']['.$idw.']" value="'.$alreadyspent.'"></span>';
                    }

                    $tableCell .= '<div class="modal-open">';
                    $tableCell .= '<input hidden class="modal-options" data-modal-to-open="timespent" data-from-id="' . $lines[$i]->id . '" data-from-module="dolisirh">';
                    $tableCell .= '<input type="text" alt="'.($disabledtaskday ? '' : $alttitle).'" title="'.($disabledtaskday ? '' : $alttitle).'" '.($disabledtaskday ? 'disabled' : $placeholder).' class="center smallpadd timespent" size="2" id="timeadded['.$inc.']['.$idw.']" name="task['.$lines[$i]->id.']['.$idw.']" data-task-id=' . $lines[$i]->id . ' data-timestamp=' . $tmpday . ' data-date=' . dol_print_date($tmpday, 'day') . ' data-cell=' . $idw . ' value="" cols="2"  maxlength="5">';
                    $tableCell .= '</div></td>';
                    print $tableCell;
                }
                print '<td></td>';
                print '</tr>';
            }

            // Call to show task with a lower level (task under the current task)
            $inc++;
            $level++;
            if ($lines[$i]->id > 0) {
                $ret = doliSirhTaskLinesWithinRange($inc, $firstdaytoshow, $lastdaytoshow, $fuser, $lines[$i]->id, ($parent == 0 ? $lineswithoutlevel0 : $lines), $level, $projectsrole, $tasksrole, $mine, $restricteditformytask, $daysAvailable, $oldprojectforbreak, $arrayfields, $extrafields, $timeSpentOnTasks);
                foreach ($ret as $key => $val) {
                    $totalforeachday[$key] += $val;
                }
            }
            $level--;
        }
    }

    return $totalforeachday;
}


