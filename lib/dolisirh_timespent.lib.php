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

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

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
        $param .= '&search_user_id=' . $fkUser->id;
    }

    if (!getDolGlobalInt('PROJECT_DISABLE_TIMESHEET_PERMONTH')) {
        $head[$h][0] = DOL_URL_ROOT . '/custom/dolisirh/view/timespent_range.php?view_mode=month' . $param;
        $head[$h][1] = $langs->trans('InputPerMonth');
        $head[$h][2] = 'inputpermonth';
        $h++;
    }

    if (!getDolGlobalInt('PROJECT_DISABLE_TIMESHEET_PERWEEK')) {
        $head[$h][0] = DOL_URL_ROOT . '/custom/dolisirh/view/timespent_range.php?view_mode=week' . $param;
        $head[$h][1] = $langs->trans('InputPerWeek');
        $head[$h][2] = 'inputperweek';
        $h++;
    }

    if (!getDolGlobalInt('PROJECT_DISABLE_TIMESHEET_PERTIME')) {
        $head[$h][0] = DOL_URL_ROOT.'/custom/dolisirh/view/timespent_range.php?view_mode=day' . $param;
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
 * @param  int                $timestampStart Timestamp first day.
 * @param  int                $timestampEnd   Timestamp last day.
 * @param  Workinghours|array $workingHours   Working hours object.
 * @param  array              $daysAvailable  Available days.
 * @return array                              Array with minutes, days on time to spend.
 * @throws Exception
 */
function load_planned_time_within_range(int $timestampStart, int $timestampEnd, $workingHours, array $daysAvailable): array
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
 * @param  int                $timestampStart    Timestamp first day.
 * @param  int                $timestampEnd      Timestamp last day.
 * @param  Workinghours|array $workingHours      Working hours object.
 * @param  array              $daysAvailable     Available days.
 * @return array              $passedWorkingTime Array with minutes on passed working time.
 * @throws Exception
 */
function load_passed_time_within_range(int $timestampStart, int $timestampEnd, $workingHours, array $daysAvailable): array
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
 * @param  int                $timestampStart Timestamp first day.
 * @param  int                $timestampEnd   Timestamp last day.
 * @param  Workinghours|array $workingHours   Working hours object.
 * @param  array              $daysAvailable  Available days.
 * @param  int                $userID         Time spent by a particular user.
 * @return int                                Array with minutes on passed working time.
 * @throws Exception
 */
function load_difference_between_passed_and_spent_time_within_range(int $timestampStart, int $timestampEnd, $workingHours, array $daysAvailable, int $userID = 0): int
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
 * @param  int                $timestampStart Timestamp first day.
 * @param  int                $timestampEnd   Timestamp last day.
 * @param  Workinghours|array $workingHours   Working hours object.
 * @param  array              $daysAvailable  Available days.
 * @param  int                $userID         Time spent by a particular user.
 * @return array                              Array with all time spent infos.
 * @throws Exception
 */
function load_time_spending_infos_within_range(int $timestampStart, int $timestampEnd, $workingHours, array $daysAvailable, int $userID = 0): array
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
 * Sort order is on project, then on position of task, and last on start date of first level task.
 *
 * @param  User|int|null    $userT                Object user to limit tasks affected to a particular user.
 * @param  User|int|null    $userP                Object user to limit projects of a particular user and public projects.
 * @param  int              $projectID            Project ID.
 * @param  int              $socid                Third party ID.
 * @param  int              $mode                 0 = Return list of tasks and their projects, 1 = Return projects and tasks if exists.
 * @param  string           $filterOnProj         Filter on project ref or label.
 * @param  string           $filterOnProjStatus   Filter on project status ('-1' = no filter, '0,1' = Draft + Validated only).
 * @param  string           $moreWhereFilter      Add more filter into where SQL request (must start with ' AND ...').
 * @param  int              $filterOnProjUser     Filter on user that is a contact of project.
 * @param  int              $filterOnTaskUser     Filter on user assigned to task.
 * @param  ExtraFields|null $extraFields          Show additional column from project or task.
 * @param  int              $includeBillTime      Calculate also the time to bill and billed.
 * @param  array            $search_array_options Array of search.
 * @param  int              $loadExtras           Fetch all Extrafields on each task.
 * @return array|string     $tasks                Array of tasks or string error.
 * @throws Exception
 */
function get_tasks_array($userT = null, $userP = null, int $projectID = 0, int $socid = 0, int $mode = 0, string $filterOnProj = '', string $filterOnProjStatus = '-1', string $moreWhereFilter = '', int $filterOnProjUser = 0, int $filterOnTaskUser = 0, ExtraFields $extraFields = null, int $includeBillTime = 0, array $search_array_options = [], int $loadExtras = 0, array $timeArray = [], string $timeMode = 'month')
{
    global $conf, $db, $hookmanager, $user;

    require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';

    $task = new Task($db);

    $tasks = [];

    // List of tasks (does not care about permissions. Filtering will be done later).
    $sql = 'SELECT ';
    if ($filterOnProjUser > 0 || $filterOnTaskUser > 0) {
        $sql .= 'DISTINCT'; // We may get several time the same record if user has several roles on same project/task.
    }
    $sql .= ' p.rowid as projectid, p.ref, p.title as plabel, p.public, p.fk_statut as projectstatus, p.usage_bill_time,';
    $sql .= ' t.rowid as taskid, t.ref as taskref, t.label, t.description, t.fk_task_parent, t.duration_effective, t.progress, t.fk_statut as status,';
    $sql .= ' t.dateo as date_start, t.datee as date_end, t.planned_workload, t.rang,';
    $sql .= ' t.description, ';
    $sql .= ' t.budget_amount, ';
    $sql .= ' s.rowid as thirdparty_id, s.nom as thirdparty_name, s.email as thirdparty_email,';
    $sql .= ' p.fk_opp_status, p.opp_amount, p.opp_percent, p.budget_amount as project_budget_amount';

    if (!empty($extraFields->attributes['projet']['label'])) {
        foreach ($extraFields->attributes['projet']['label'] as $key => $val) {
            $sql .= ($extraFields->attributes['projet']['type'][$key] != 'separate' ? ',efp.' . $key . ' as options_' . $key : '');
        }
    }
    if (!empty($extraFields->attributes['projet_task']['label'])) {
        foreach ($extraFields->attributes['projet_task']['label'] as $key => $val) {
            $sql .= ($extraFields->attributes['projet_task']['type'][$key] != 'separate' ? ',efpt.' . $key . ' as options_' . $key : '');
        }
    }

    if ($includeBillTime) {
        $sql .= ', SUM(tt.task_duration * ' . $db->ifsql('invoice_id IS NULL', '1', '0') . ') as tobill, SUM(tt.task_duration * ' . $db->ifsql(
                'invoice_id IS NULL',
                '0',
                '1'
            ) . ') as billed';
    }

    $sql .= ' FROM '  .MAIN_DB_PREFIX . 'projet as p';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON p.fk_soc = s.rowid';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet_extrafields as efp ON (p.rowid = efp.fk_object)';

    if ($mode == 0) {
        if ($filterOnProjUser > 0) {
            $sql .= ', ' . MAIN_DB_PREFIX . 'element_contact as ec';
            $sql .= ', ' . MAIN_DB_PREFIX . 'c_type_contact as ctc';
        }
        $sql .= ', ' . MAIN_DB_PREFIX . 'projet_task as t';
        if ($includeBillTime) {
            $sql .= ' LEFT JOIN ' .MAIN_DB_PREFIX. 'projet_task_time as tt ON tt.fk_task = t.rowid';
        }
        if ($filterOnTaskUser > 0) {
            $sql .= ', ' .MAIN_DB_PREFIX. 'element_contact as ec2';
            $sql .= ', ' .MAIN_DB_PREFIX. 'c_type_contact as ctc2';
        }
        if ($user->conf->DOLISIRH_SHOW_ONLY_FAVORITE_TASKS > 0) {
            $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . "element_element as elel ON (t.rowid = elel.fk_target AND elel.targettype='project_task')";
        }
        if ($user->conf->DOLISIRH_SHOW_ONLY_TASKS_WITH_TIMESPENT > 0) {
            $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet_task_time as ptt ON (t.rowid = ptt.fk_task)';
        }
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet_task_extrafields as efpt ON (t.rowid = efpt.fk_object)';
        $sql .= ' WHERE p.entity IN (' . getEntity('project') . ')';
        $sql .= ' AND t.fk_projet = p.rowid';
        if ($user->conf->DOLISIRH_SHOW_ONLY_FAVORITE_TASKS > 0) {
            $sql .= ' AND elel.fk_target = t.rowid';
            $sql .= ' AND elel.fk_source = ' . $filterOnProjUser;
        }

        if ($user->conf->DOLISIRH_SHOW_ONLY_TASKS_WITH_TIMESPENT > 0) {
            $sql .= ' AND ptt.fk_task = t.rowid AND ptt.fk_user = ' . $filterOnProjUser;
            if ($timeMode == 'month') {
                $sql .= ' AND MONTH(ptt.task_date) = ' . $timeArray['month'];
            } elseif ($timeMode == 'week') {
                $sql .= ' AND WEEK(ptt.task_date, 7) = ' . $timeArray['week'];
            } elseif ($timeMode == 'day') {
                $sql .= ' AND DAY(ptt.task_date) = ' . $timeArray['day'];
            }
            $sql .= ' AND YEAR(ptt.task_date) = ' . $timeArray['year'];
        }
    } elseif ($mode == 1) {
        if ($filterOnProjUser > 0) {
            $sql .= ', ' . MAIN_DB_PREFIX . 'element_contact as ec';
            $sql .= ', ' . MAIN_DB_PREFIX . 'c_type_contact as ctc';
        }
        if ($filterOnTaskUser > 0) {
            $sql .= ', ' . MAIN_DB_PREFIX . 'projet_task as t';
            if ($includeBillTime) {
                $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet_task_time as tt ON tt.fk_task = t.rowid';
            }
            $sql .= ', ' . MAIN_DB_PREFIX . 'element_contact as ec2';
            $sql .= ', ' . MAIN_DB_PREFIX . 'c_type_contact as ctc2';
        } else {
            $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet_task as t on t.fk_projet = p.rowid';
            if ($includeBillTime) {
                $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet_task_time as tt ON tt.fk_task = t.rowid';
            }
        }
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet_task_extrafields as efpt ON (t.rowid = efpt.fk_object)';
        $sql .= ' WHERE p.entity IN (' . getEntity('project') . ')';
    } else {
        return 'BadValueForParameterMode';
    }

    if ($filterOnProjUser > 0) {
        $sql .= ' AND p.rowid = ec.element_id';
        $sql .= ' AND ctc.rowid = ec.fk_c_type_contact';
        $sql .= " AND ctc.element = 'project'";
        $sql .= ' AND ec.fk_socpeople = ' . $filterOnProjUser;
        $sql .= ' AND ec.statut = 4';
        $sql .= " AND ctc.source = 'internal'";
    }
    if ($filterOnTaskUser > 0) {
        $sql .= ' AND t.fk_projet = p.rowid';
        $sql .= ' AND p.rowid = ec2.element_id';
        $sql .= ' AND ctc2.rowid = ec2.fk_c_type_contact';
        $sql .= " AND ctc2.element = 'project_task'";
        $sql .= ' AND ec2.fk_socpeople = ' . $filterOnTaskUser;
        $sql .= ' AND ec2.statut = 4';
        $sql .= " AND ctc2.source = 'internal'";
    }
    if ($socid > 0) {
        $sql .= ' AND p.fk_soc = ' . $socid;
    }
    if ($projectID > 0) {
        $sql .= ' AND p.rowid = ' . $projectID;
    }
    if (dol_strlen($filterOnProj) > 0) {
        $sql .= natural_search(['p.ref', 'p.title'], $filterOnProj);
    }
    if (dol_strlen($filterOnProjStatus) > 0 && $filterOnProjStatus != '-1') {
        $sql .= ' AND p.fk_statut IN (' .$db->sanitize($filterOnProjStatus). ')';
    }
    if ($moreWhereFilter) {
        $sql .= $moreWhereFilter;
    }

    // Add where from extra fields.
    $extrafieldsobjectkey    = 'projet_task';
    $extrafieldsobjectprefix = 'efpt.';
    include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_sql.tpl.php';

    // Add where from hooks.
    $parameters = [];
    $hookmanager->executeHooks('printFieldListWhere', $parameters); // Note that $action and $object may have been modified by hook.
    $sql .= $hookmanager->resPrint;

    if ($includeBillTime) {
        $sql .= ' GROUP BY p.rowid, p.ref, p.title, p.public, p.fk_statut, p.usage_bill_time,';
        $sql .= ' t.datec, t.dateo, t.datee, t.tms,';
        $sql .= ' t.rowid, t.ref, t.label, t.description, t.fk_task_parent, t.duration_effective, t.progress, t.fk_statut,';
        $sql .= ' t.dateo, t.datee, t.planned_workload, t.rang,';
        $sql .= ' t.description, ';
        $sql .= ' t.budget_amount, ';
        $sql .= ' s.rowid, s.nom, s.email,';
        $sql .= ' p.fk_opp_status, p.opp_amount, p.opp_percent, p.budget_amount';
        if (!empty($extraFields->attributes['projet']['label'])) {
            foreach ($extraFields->attributes['projet']['label'] as $key => $val) {
                $sql .= ($extraFields->attributes['projet']['type'][$key] != 'separate' ? ',efp.' . $key : '');
            }
        }
        if (!empty($extraFields->attributes['projet_task']['label'])) {
            foreach ($extraFields->attributes['projet_task']['label'] as $key => $val) {
                $sql .= ($extraFields->attributes['projet_task']['type'][$key] != 'separate' ? ',efpt.' . $key : '');
            }
        }
    }

    $sql .= ' ORDER BY p.ref, t.rang, t.dateo';

    dol_syslog(get_class($task). '::get_tasks_array', LOG_DEBUG);
    $resql = $db->query($sql);
    if ($resql) {
        $num = $db->num_rows($resql);
        $i   = 0;
        // Loop on each record found, so each couple (project id, task id).
        while ($i < $num) {
            $error = 0;

            $obj = $db->fetch_object($resql);

            if ((!$obj->public) && (is_object($userP))) { // If not public project, and we ask a filter on project owned by a user.
                if (!$task->getUserRolesForProjectsOrTasks($userP, 0, $obj->projectid)) {
                    $error++;
                }
            }
            if (is_object($userT)) { // If we ask a filter on a user affected to a task.
                if (!$task->getUserRolesForProjectsOrTasks(0, $userT, $obj->projectid, $obj->taskid)) {
                    $error++;
                }
            }

            if (!$error) {
                $tasks[$i] = new Task($db);
                $tasks[$i]->id            = $obj->taskid;
                $tasks[$i]->ref           = $obj->taskref;
                $tasks[$i]->fk_project    = $obj->projectid;
                $tasks[$i]->projectref    = $obj->ref;
                $tasks[$i]->projectlabel  = $obj->plabel;
                $tasks[$i]->projectstatus = $obj->projectstatus;

                $tasks[$i]->fk_opp_status         = $obj->fk_opp_status;
                $tasks[$i]->opp_amount            = $obj->opp_amount;
                $tasks[$i]->opp_percent           = $obj->opp_percent;
                $tasks[$i]->budget_amount         = $obj->budget_amount;
                $tasks[$i]->project_budget_amount = $obj->project_budget_amount;
                $tasks[$i]->usage_bill_time       = $obj->usage_bill_time;

                $tasks[$i]->label            = $obj->label;
                $tasks[$i]->description      = $obj->description;
                $tasks[$i]->fk_parent        = $obj->fk_task_parent; // deprecated
                $tasks[$i]->fk_task_parent   = $obj->fk_task_parent;
                $tasks[$i]->duration         = $obj->duration_effective;
                $tasks[$i]->planned_workload = $obj->planned_workload;

                if ($includeBillTime) {
                    $tasks[$i]->tobill = $obj->tobill;
                    $tasks[$i]->billed = $obj->billed;
                }

                $tasks[$i]->progress   = $obj->progress;
                $tasks[$i]->fk_statut  = $obj->status;
                $tasks[$i]->public     = $obj->public;
                $tasks[$i]->date_start = $db->jdate($obj->date_start);
                $tasks[$i]->date_end   = $db->jdate($obj->date_end);
                $tasks[$i]->rang       = $obj->rang;

                $tasks[$i]->socid            = $obj->thirdparty_id; // For backward compatibility.
                $tasks[$i]->thirdparty_id    = $obj->thirdparty_id;
                $tasks[$i]->thirdparty_name	 = $obj->thirdparty_name;
                $tasks[$i]->thirdparty_email = $obj->thirdparty_email;

                if (!empty($extraFields->attributes['projet']['label'])) {
                    foreach ($extraFields->attributes['projet']['label'] as $key => $val) {
                        if ($extraFields->attributes['projet']['type'][$key] != 'separate') {
                            $tasks[$i]->{'options_'.$key} = $obj->{'options_'.$key};
                        }
                    }
                }

                if (!empty($extraFields->attributes['projet_task']['label'])) {
                    foreach ($extraFields->attributes['projet_task']['label'] as $key => $val) {
                        if ($extraFields->attributes['projet_task']['type'][$key] != 'separate') {
                            $tasks[$i]->{'options_'.$key} = $obj->{'options_'.$key};
                        }
                    }
                }

                if ($loadExtras) {
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
 * Output a task line into a per range.
 *
 * @param  int              $inc                    Line output identification (start to 0, then increased by recursive call).
 * @param  int              $timestampStart         Timestamp first day.
 * @param  int              $timestampEnd           Timestamp last day.
 * @param  User|null        $fuser                  Restrict list to user if defined.
 * @param  int              $parent                 ID of parent task to show (0 to show all).
 * @param  Task[]           $lines                  Array of lines (list of tasks, but we will show only if we have a specific role on task).
 * @param  int              $level                  Level (start to 0, then increased/decrease by recursive call).
 * @param  array            $projectsRole           Array of roles user has on project.
 * @param  array            $tasksRole              Array of roles user has on task.
 * @param  int              $mine                   Show only task lines I am assigned to.
 * @param  int              $restrictEditForMyTask  0 = No restriction, 1 = Enable add time only if task is assigned to me, 2 = Enable add time only if tasks is assigned to me and hide others.
 * @param  array            $daysAvailable          Array with data that say if user is available for several days for morning and afternoon.
 * @param  array            $timeSpentOnTasks       Array of all tasks with time spent infos.
 * @param  int              $oldProjectForBreak     Old project id of last project break.
 * @param  array            $arrayFields            Array of additional column.
 * @param  ExtraFields|null $extraFields            Object extrafields.
 * @return array            $totalForEachDay        Array with time spent for $fuser for each day of week on tasks in $lines and subtasks.
 */
function task_lines_within_range(int &$inc, int $timestampStart, int $timestampEnd, ?User $fuser, int $parent, array $lines, int &$level, array &$projectsRole, array &$tasksRole, int $mine, int $restrictEditForMyTask, array &$daysAvailable, array $timeSpentOnTasks, int $oldProjectForBreak = 0, array $arrayFields = [], ExtraFields $extraFields = null)
{
    global $conf, $db, $langs, $user;

    $project    = new Project($db);
    $task       = new Task($db);
    $thirdparty = new Societe($db);

    $numLines = count($lines);

    $lastProjectID      = 0;
    $totalForEachDay    = [];
    $linesWithoutLevel0 = [];

    $daysInRange = dolisirh_num_between_day($timestampStart, $timestampEnd, 1);

    // Create a smaller array with sublevels only to be used later. This increase dramatically performances.
    if ($parent == 0) { // Always and only if at first level.
        for ($i = 0; $i < $numLines; $i++) {
            if ($lines[$i]->fk_task_parent) {
                $linesWithoutLevel0[] = $lines[$i];
            }
        }
    }

    if (empty($oldProjectForBreak)) {
        $oldProjectForBreak = (!getDolGlobalInt('PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT') ? 0 : -1); // 0 = start break, -1 = never break.
    }

    $restrictBefore = null;

    if (getDolGlobalInt('PROJECT_TIMESHEET_PREVENT_AFTER_MONTHS')) {
        $restrictBefore = dol_time_plus_duree(dol_now(), - $conf->global->PROJECT_TIMESHEET_PREVENT_AFTER_MONTHS, 'm');
    }

    for ($i = 0; $i < $numLines; $i++) {
        if ($parent == 0) {
            $level = 0;
        }

        if ($lines[$i]->fk_task_parent != $parent && $user->conf->DOLISIRH_SHOW_ONLY_TASKS_WITH_TIMESPENT) {
            $lines[$i]->fk_task_parent = 0;
        }

        if ($lines[$i]->fk_task_parent == $parent) {
            // If we want all, or we have a role on task, we show it.
            if (empty($mine) || !empty($tasksRole[$lines[$i]->id])) {
                if ($restrictEditForMyTask == 2 && empty($tasksRole[$lines[$i]->id])) { // we have no role on task, and we request to hide such cases.
                    continue;
                }

                // Break on a new project.
                if ($parent == 0 && $lines[$i]->fk_project != $lastProjectID) {
                    $lastProjectID = $lines[$i]->fk_project;
                    $project->id   = $lines[$i]->fk_project;
                }

                $project->id              = $lines[$i]->fk_project;
                $project->ref             = $lines[$i]->projectref;
                $project->title           = $lines[$i]->projectlabel;
                $project->public          = $lines[$i]->public;
                $project->thirdparty_name = $lines[$i]->thirdparty_name;
                $project->status          = $lines[$i]->projectstatus;

                $task->id               = $lines[$i]->id;
                $task->ref              = ($lines[$i]->ref ?: $lines[$i]->id);
                $task->label            = $lines[$i]->label;
                $task->date_start       = $lines[$i]->date_start;
                $task->date_end         = $lines[$i]->date_end;
                $task->planned_workload = $lines[$i]->planned_workload;

                $thirdparty->id    = $lines[$i]->thirdparty_id;
                $thirdparty->name  = $lines[$i]->thirdparty_name;
                $thirdparty->email = $lines[$i]->thirdparty_email;

                if (empty($oldProjectForBreak) || ($oldProjectForBreak != -1 && $oldProjectForBreak != $project->id)) {
                    $addColSpan = 0;
                    if (!empty($arrayFields['timeconsumed']['checked'])) {
                        $addColSpan++;
                    }

                    print '<tr class="oddeven trforbreak nobold">';
                    print '<td colspan="' . (2 + $addColSpan + $daysInRange) . '">';
                    print $project->getNomUrl(1, '', 0, '<strong>' . $langs->transnoentitiesnoconv('YourRole') . ' : </strong> ' . $projectsRole[$lines[$i]->fk_project]);
                    if ($thirdparty->id > 0) {
                        print ' - ' . $thirdparty->getNomUrl(1);
                    }
                    if ($project->title) {
                        print ' - ';
                        print '<span class="secondary" title="' . $project->title . '">' . dol_trunc($project->title, '64') . '</span>';
                    }
                    print '</td>';
                    print '</tr>';
                }

                if ($oldProjectForBreak != -1) {
                    $oldProjectForBreak = $project->id;
                }

                print '<tr class="oddeven" data-task_id="' . $lines[$i]->id . '" >';

                // Ref.
                print '<td class="nowrap">';
                print '<!-- Task id = ' . $lines[$i]->id . ' -->';
                for ($k = 0; $k < $level; $k++) {
                    print '<div class="marginleftonly">';
                }
                print $task->getNomUrl(1, 'withproject', 'time');
                if (GETPOST('action') == 'toggleTaskFavorite') {
                    toggle_task_favorite((int) GETPOST('taskid', 'int'), $fuser->id);
                }
                if (is_task_favorite($task->id, $fuser->id)) {
                    print '<span class="fas fa-star toggleTaskFavorite" style="margin-left: 5px;" id="' . $task->id . '" value="' . $task->id . '"></span>';
                } else {
                    print '<span class="far fa-star toggleTaskFavorite" style="margin-left: 5px;" id="' . $task->id . '" value="' . $task->id . '"></span>';
                }
                if ($task->planned_workload != '') {
                    $tmpArray = $task->getSummaryOfTimeSpent();
                    if ($tmpArray['total_duration'] > 0 && !empty($task->planned_workload)) {
                        print '<span class="task-progress ' . get_task_progress_color_class(round($tmpArray['total_duration'] / $task->planned_workload * 100, 2)) . '" style="margin-left: 5px;">';
                        print convertSecondToTime($tmpArray['total_duration'], 'allhourmin');
                        print ' / ' . convertSecondToTime($task->planned_workload, 'allhourmin');
                        print ' - ' . round($tmpArray['total_duration'] / $task->planned_workload * 100, 2) . ' %';
                        print '</span>';
                    } else {
                        print ' 0 %';
                    }
                }
                // Label task.
                print '<span class="opacitymedium" style="margin-left: 5px;" title="' . $task->label . '">' . dol_trunc($task->label, '64') . '</span>';
                for ($k = 0; $k < $level; $k++) {
                    print '</div>';
                }
                print '</td>';

                if (!empty($arrayFields['timeconsumed']['checked'])) {
                    // Time spent by user.
                    print '<td class="right">';
                    $filter             = ' AND (t.task_date >= "' . $db->idate($timestampStart) . '" AND t.task_date < "' . $db->idate(dol_time_plus_duree($timestampEnd, 1, 'd')) . '")';
                    $summaryOfTimeSpent = $task->getSummaryOfTimeSpent($fuser->id, $filter);
                    if ($summaryOfTimeSpent['total_duration']) {
                        print convertSecondToTime($summaryOfTimeSpent['total_duration'], 'allhourmin');
                    } else {
                        print '--:--';
                    }
                    print '</td>';
                }

                $disabledTask = 1;

                if ($lines[$i]->public || !empty($projectsRole[$lines[$i]->fk_project]) || $user->rights->projet->all->creer) {
                    $disabledTask = 0;
                }
                if ($restrictEditForMyTask && empty($tasksRole[$lines[$i]->id])) {
                    $disabledTask = 1;
                }

                // Fields to show current time.
                for ($idw = 0; $idw < $daysInRange; $idw++) {
                    $cellCSS   = '';
                    $dayInLoop = dol_time_plus_duree($timestampStart, $idw, 'd');
                    if (!$daysAvailable[$dayInLoop]['morning'] && !$daysAvailable[$dayInLoop]['afternoon']) {
                        if ($daysAvailable[$dayInLoop]['morning_reason'] == 'public_holiday') {
                            $cellCSS = 'onholidayallday';
                        } elseif ($daysAvailable[$dayInLoop]['morning_reason'] == 'week_end') {
                            $cellCSS = 'weekend';
                        }
                    }

                    $tmpArray = dol_getdate($dayInLoop);

                    $totalForEachDay[$dayInLoop] = $timeSpentOnTasks[$lines[$i]->id][dol_print_date($dayInLoop, 'day')];

                    $alreadySpent = '';
                    if ($totalForEachDay[$dayInLoop] > 0) {
                        $timeSpentComments = $timeSpentOnTasks[$lines[$i]->id]['comments'][dol_print_date($dayInLoop, 'day')];
                        if (is_array($timeSpentComments) && !empty($timeSpentComments)) {
                            $textTooltip = implode('', $timeSpentComments);
                        } else {
                            $textTooltip = $langs->trans('TimeSpentAddWithNoComment');
                        }
                        $alreadySpent = convertSecondToTime($totalForEachDay[$dayInLoop], 'allhourmin');
                    }
                    $altTitle = $langs->trans('AddHereTimeSpentForDay', $tmpArray['day'], $tmpArray['mon']);

                    $disabledTaskDay = $disabledTask;

                    if (!$disabledTask && $restrictBefore && $dayInLoop < $restrictBefore) {
                        $disabledTaskDay = 1;
                    }

                    $tableCell = '<td class="center ' . $idw . ' ' . $cellCSS . '" data-cell="' . $idw . '">';
                    if ($alreadySpent) {
                        $tableCell .= '<span class="timesheetalreadyrecorded wpeo-tooltip-event" aria-label="' . $textTooltip . '"><input type="text" class="center smallpadd" size="2" disabled id="timespent[' . $inc . '][' . $idw . ']" name="task[' . $lines[$i]->id . '][' . $idw . ']" value="' . $alreadySpent . '"></span>';
                    }

                    $tableCell .= '<div class="modal-open">';
                    $tableCell .= '<input hidden class="modal-options" data-modal-to-open="timespent" data-from-id="' . $lines[$i]->id . '" data-from-module="dolisirh">';
                    $tableCell .= '<input type="text" alt="' . ($disabledTaskDay ? '' : $altTitle) . '" title="' . ($disabledTaskDay ? '' : $altTitle) . '" ' . ($disabledTaskDay ? 'disabled' : '') . ' class="center smallpadd timespent" size="2" id="timeadded[' . $inc . '][' . $idw.']" name="task[' . $lines[$i]->id . '][' . $idw . ']" data-task-id=' . $lines[$i]->id . ' data-timestamp=' . $dayInLoop . ' data-date=' . dol_print_date($dayInLoop, 'day') . ' data-cell=' . $idw . ' value="" cols="2"  maxlength="5">';
                    $tableCell .= '</div></td>';
                    print $tableCell;
                }
                print '<td></td>';
                print '</tr>';
            }

            // Call to show task with a lower level (task under the current task).
            $inc++;
            $level++;
            if ($lines[$i]->id > 0) {
                $ret = task_lines_within_range($inc, $timestampStart, $timestampEnd, $fuser, $lines[$i]->id, ($parent == 0 ? $linesWithoutLevel0 : $lines), $level, $projectsRole, $tasksRole, $mine, $restrictEditForMyTask, $daysAvailable, $timeSpentOnTasks, $oldProjectForBreak, $arrayFields, $extraFields);
                foreach ($ret as $key => $val) {
                    $totalForEachDay[$key] += $val;
                }
            }
            $level--;
        }
    }

    return $totalForEachDay;
}
