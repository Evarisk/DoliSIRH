<?php
/* Copyright (C) 2021-2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    view/timesheet/timesheet_timespent.php
 * \ingroup dolisirh
 * \brief   Tab of time spent on HR project tasks for a timesheet.
 */

// Load DoliSIRH environment.
if (file_exists('../../dolisirh.main.inc.php')) {
    require_once __DIR__ . '/../../dolisirh.main.inc.php';
} elseif (file_exists('../../../dolisirh.main.inc.php')) {
    require_once __DIR__ . '/../../../dolisirh.main.inc.php';
} else {
    die('Include of dolisirh main fails');
}

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

// Load Saturne libraries.
dol_include_once('/saturne/class/task/saturnetask.class.php');

// Load DoliSIRH libraries.
require_once __DIR__ . '/../../lib/dolisirh_function.lib.php';
require_once __DIR__ . '/../../lib/dolisirh_timespent.lib.php';
require_once __DIR__ . '/../../lib/dolisirh_timesheet.lib.php';
require_once __DIR__ . '/../../class/timesheet.class.php';
require_once __DIR__ . '/../../class/workinghours.class.php';

// Global variables definitions.
global $conf, $db, $hookmanager, $langs, $moduleName, $moduleNameLowerCase, $user;

// Load translation files required by the page.
saturne_load_langs(['projects', 'users', 'companies']);

// Get parameters.
$id         = GETPOST('id', 'int');
$ref        = GETPOST('ref', 'alpha');
$action     = GETPOST('action', 'aZ09');
$cancel     = GETPOST('cancel', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

// Initialize technical objects.
$object       = new TimeSheet($db);
$project      = new Project($db);
$task         = new SaturneTask($db);
$thirdparty   = new Societe($db);
$workingHours = new Workinghours($db);
$extrafields  = new ExtraFields($db);
$userTmp      = new User($db);
$form         = new Form($db);

$hookmanager->initHooks(['timesheettimespent', 'globalcard']);

// Fetch optionals attributes and labels.
$extrafields->fetch_name_optionals_label($object->table_element);

// Load object.
require_once DOL_DOCUMENT_ROOT . '/core/actions_fetchobject.inc.php';

// Security check.
$permissionToRead = $user->rights->dolisirh->timesheet->read;
$permissionToAdd  = (($object->status >= TimeSheet::STATUS_LOCKED) ? 0 : $user->rights->dolisirh->timesheet->write);
saturne_check_access($permissionToRead);

/*
 * Actions.
 */

$parameters = ['id' => $id];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($resHook)) {
    if ($action == 'toggleTaskFavorite' && $object->fk_user_assign > 0) {
        toggle_task_favorite(GETPOSTINT('taskId'), (int) $object->fk_user_assign);
    }

    if ($action == 'add_timespent' && $permissionToAdd && $object->fk_user_assign > 0) {
        $data = json_decode((string) file_get_contents('php://input'), true);
        if (is_array($data)) {
            $taskID    = (int) ($data['taskID'] ?? 0);
            $timestamp = (int) ($data['timestamp'] ?? 0);
            $dateHour  = (int) ($data['datehour'] ?? 0);
            $dateMin   = (int) ($data['datemin'] ?? 0);
            $comment   = (string) ($data['comment'] ?? '');
            $hour      = (int) ($data['hour'] ?? 0);
            $min       = (int) ($data['min'] ?? 0);

            if ($taskID > 0 && $task->fetch($taskID) > 0) {
                $task->timespent_date     = $timestamp;
                $task->timespent_datehour = $timestamp + ($dateHour * 3600) + ($dateMin * 60);
                if ($dateHour > 0 || $dateMin > 0) {
                    $task->timespent_withhour = 1;
                }
                $task->timespent_note     = $comment;
                $task->timespent_duration = ($hour * 3600) + ($min * 60);
                $task->timespent_fk_user  = (int) $object->fk_user_assign;

                if ($task->timespent_duration > 0) {
                    $task->addTimeSpent($user);
                }
            }
        }
    }
}

/*
 * View.
 */

$title    = $langs->trans('ConsumedTime') . ' - ' . $langs->trans(dol_ucfirst($object->element));
$help_url = 'FR:Module_DoliSIRH';

saturne_header(0, '', $title, $help_url);

if ($object->id > 0) {
    $object->fetch_optionals();

    saturne_get_fiche_head($object, 'timespent', $title);
    saturne_banner_tab($object);

    $userTmp->fetch((int) $object->fk_user_assign);

    // Due to Dolibarr issue in common field add we substract 12 hours in timestamp.
    $firstDayToShow = $object->date_start - 12 * 3600;
    $lastDayToShow  = $object->date_end - 12 * 3600;
    $startDate      = dol_print_date($firstDayToShow, 'dayreduceformat');
    $endDate        = dol_print_date($lastDayToShow, 'dayreduceformat');
    $daysInRange    = dolisirh_num_between_days($firstDayToShow, $lastDayToShow, 1);

    $isAvailable = [];
    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $dayInLoop = dolisirh_add_days($firstDayToShow, $idw);
        if (is_day_available($dayInLoop, (int) $object->fk_user_assign)) {
            $isAvailable[$dayInLoop] = ['morning' => 1, 'afternoon' => 1];
        } elseif (date('N', $dayInLoop) >= 6) {
            $isAvailable[$dayInLoop] = ['morning' => false, 'afternoon' => false, 'morning_reason' => 'week_end', 'afternoon_reason' => 'week_end'];
        } else {
            $isAvailable[$dayInLoop] = ['morning' => false, 'afternoon' => false, 'morning_reason' => 'public_holiday', 'afternoon_reason' => 'public_holiday'];
        }
    }

    $workingHours = $workingHours->fetchCurrentWorkingHours((int) $object->fk_user_assign, 'user');

    $hrProjectID = (int) ($object->fk_project > 0 ? $object->fk_project : getDolGlobalInt('DOLISIRH_HR_PROJECT'));
    if ($hrProjectID > 0) {
        $project->fetch($hrProjectID);
        $project->fetch_thirdparty();
    }

    $hrTasksByType    = timesheet_get_hr_project_tasks_by_type($hrProjectID);
    $allHrTaskIDs     = $hrTasksByType['all_ids'];
    $presenceTaskObjs = $hrTasksByType['work_time'];
    $absenceTaskObjs  = $hrTasksByType['no_work_time'];
    $presenceTaskIDs  = array_map(static function ($t): int {
        return (int) $t->id;
    }, $presenceTaskObjs);
    $absenceTaskIDs   = array_map(static function ($t): int {
        return (int) $t->id;
    }, $absenceTaskObjs);

    $timeSpendingInfos = load_time_spending_infos_within_range($firstDayToShow, dolisirh_add_days($lastDayToShow, 1), $workingHours, $isAvailable, (int) $object->fk_user_assign, $allHrTaskIDs);
    $timeSpentOnTasks  = $timeSpendingInfos['spent'];
    $plannedWorking    = $timeSpendingInfos['planned'];
    $passedWorking     = $timeSpendingInfos['passed'];
    $diffTotalMinutes  = $timeSpendingInfos['difference'];

    print '<div class="fichecenter">';
    print timesheet_render_daily_calendar($object, $firstDayToShow, $lastDayToShow, $workingHours);
    print '</div>';

    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '" name="addtime">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="id" value="' . $object->id . '">';

    print '<div class="div-table-responsive" style="min-height: 0;">';
    print '<table class="tagtable liste" id="tablelines3">';

    print '<thead style="position: sticky; top: 0; background: #FFFFFF; z-index: 1000;">';

    // Column headers: Task | Consumed time | Days of month.
    print '<tr class="liste_titre">';
    print '<th style="position: sticky; left: 0; background-color: var(--colorbacktitle1); z-index: 1040; max-width: 250px; width: 250px;">' . $langs->trans('Task') . '</th>';
    print '<th class="right maxwidth75">' . $langs->trans('TimeSpent');
    if ($userTmp->id > 0) {
        print '<br><span class="nowraponall">' . $userTmp->getNomUrl(-2) . '<span class="opacitymedium paddingleft">' . dol_trunc($userTmp->firstname, 10) . '</span></span>';
    }
    print '</th>';

    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $cellCSS   = '';
        $dayInLoop = dolisirh_add_days($firstDayToShow, $idw);
        if (!$isAvailable[$dayInLoop]['morning'] && !$isAvailable[$dayInLoop]['afternoon']) {
            if ($isAvailable[$dayInLoop]['morning_reason'] == 'public_holiday') {
                $cellCSS = 'onholidayallday';
            } elseif ($isAvailable[$dayInLoop]['morning_reason'] == 'week_end') {
                $cellCSS = 'weekend';
            }
        }

        $dayNum   = (int) date('d', $dayInLoop);
        $monthNum = (int) date('m', $dayInLoop);
        $yearNum  = (int) date('Y', $dayInLoop);

        print '<th class="center bold ' . $idw . ' ' . $cellCSS . '" style="font-size: 12px;">';
        print dol_strtoupper(dol_print_date($dayInLoop, '%a'));
        print ' <a href="' . dol_buildpath('/dolisirh/view/timespent_range.php', 1) . '?year=' . $yearNum . '&month=' . $monthNum . '&day=' . $dayNum . '&search_user_id=' . (int) $object->fk_user_assign . '&view_mode=day" target="_blank"><i class="fas fa-external-link-alt"></i></a>';
        print '<br>' . dol_print_date($dayInLoop, '%d/%m') . '</th>';
    }
    print '</tr>';
    print '</thead>';

    $dailyConsumedByTaskGroup = [
        'work_time'    => array_fill(0, $daysInRange, 0),
        'no_work_time' => array_fill(0, $daysInRange, 0),
        'all'          => array_fill(0, $daysInRange, 0),
    ];
    $totalPresenceSec = 0;
    $totalAbsenceSec  = 0;

    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $dayInLoop = dolisirh_add_days($firstDayToShow, $idw);
        $dayStr    = dol_print_date($dayInLoop, 'day');
        foreach ($presenceTaskIDs as $tid) {
            $sec = (int) ($timeSpentOnTasks[$tid][$dayStr] ?? 0);
            $dailyConsumedByTaskGroup['work_time'][$idw] += $sec;
            $dailyConsumedByTaskGroup['all'][$idw]       += $sec;
            $totalPresenceSec                            += $sec;
        }
        foreach ($absenceTaskIDs as $tid) {
            $sec = (int) ($timeSpentOnTasks[$tid][$dayStr] ?? 0);
            $dailyConsumedByTaskGroup['no_work_time'][$idw] += $sec;
            $dailyConsumedByTaskGroup['all'][$idw]          += $sec;
            $totalAbsenceSec                                += $sec;
        }
    }

    print '<tbody>';

    // HR Project banner row.
    if ($project->id > 0) {
        print '<tr class="oddeven trforbreak nobold">';
        print '<td colspan="' . (2 + $daysInRange) . '"' . ($project->status == Project::STATUS_CLOSED ? " style='background-color: #CBCDCD!important;'" : '') . '>';
        print '<span style="position: sticky; left: 5px;">' . $project->getNomUrl(1);
        if (is_object($project->thirdparty) && $project->thirdparty->id > 0) {
            print ' - ' . $project->thirdparty->getNomUrl(1);
        }
        if (!empty($project->title)) {
            print ' - <span class="secondary" title="' . dol_escape_htmltag($project->title) . '">' . dol_escape_htmltag(dol_trunc($project->title, 64)) . '</span>';
        }
        print ' - ' . $project->getLibStatut(5);
        print '</span></td>';
        print '</tr>';
    }

    $inc            = 0;
    $restrictBefore = null;
    if (getDolGlobalInt('PROJECT_TIMESHEET_PREVENT_AFTER_MONTHS')) {
        $restrictBefore = dol_time_plus_duree(dol_now(), -(int) $conf->global->PROJECT_TIMESHEET_PREVENT_AFTER_MONTHS, 'm');
    }

    $blocksToRender = [
        [
            'key'       => 'work_time',
            'badge'     => 'P',
            'badge_css' => 'is-p',
            'title'     => $langs->trans('TimeSheetPresence'),
            'total_sec' => $totalPresenceSec,
            'tasks'     => $presenceTaskObjs,
            'row_bg'    => '#eef5ff',
            'accent'    => '#2b6cb0',
        ],
        [
            'key'       => 'no_work_time',
            'badge'     => 'A',
            'badge_css' => 'is-a',
            'title'     => $langs->trans('TimeSheetAbsence'),
            'total_sec' => $totalAbsenceSec,
            'tasks'     => $absenceTaskObjs,
            'row_bg'    => '#fff7ed',
            'accent'    => '#d97706',
        ],
    ];

    foreach ($blocksToRender as $block) {
        $blockKey   = $block['key'];
        $blockTasks = $block['tasks'];

        // Block header row (Présence / Absence) with daily subtotals (collapsible, closed by default).
        print '<tr class="liste_total ts-block-toggle" data-block-key="' . dol_escape_htmltag($blockKey) . '" style="background-color: ' . $block['row_bg'] . '; cursor: pointer;">';
        print '<td class="liste_total nowrap" style="position: sticky; left: 0; background-color: ' . $block['row_bg'] . '; z-index: 600; border-left: 4px solid ' . $block['accent'] . '; max-width: 220px;">';
        print '<i class="fas fa-chevron-right ts-block-chevron" style="margin-right: 6px; font-size: 11px;"></i>';
        print '<span class="ts-cal-pa-badge ' . $block['badge_css'] . '" style="margin-right: 6px;">' . dol_escape_htmltag($block['badge']) . '</span>';
        print '<strong>' . dol_escape_htmltag($block['title']) . '</strong>';
        print '</td>';
        print '<td class="liste_total right" style="background-color: ' . $block['row_bg'] . ';"><strong>' . ($block['total_sec'] > 0 ? convertSecondToTime($block['total_sec'], 'allhourmin') : '00:00') . '</strong></td>';

        for ($idw = 0; $idw < $daysInRange; $idw++) {
            $daySec = $dailyConsumedByTaskGroup[$blockKey][$idw] ?? 0;
            print '<td class="liste_total center bold" style="background-color: ' . $block['row_bg'] . ';">';
            print ($daySec > 0 ? convertSecondToTime($daySec, 'allhourmin') : '00:00');
            print '</td>';
        }
        print '</tr>';

        // Task rows in this block (closed by default).
        foreach ($blockTasks as $taskLine) {
            $task->id               = $taskLine->id;
            $task->ref              = ($taskLine->ref ?: (string) $taskLine->id);
            $task->label            = $taskLine->label;
            $task->date_start       = $taskLine->date_start;
            $task->date_end         = $taskLine->date_end;
            $task->planned_workload = $taskLine->planned_workload;

            $taskHoverTitle = $task->ref . ' - ' . $task->label;
            $taskUrl        = DOL_URL_ROOT . '/projet/tasks/time.php?id=' . (int) $task->id . '&withproject=1';

            print '<tr class="oddeven ts-block-row ts-block-row-' . dol_escape_htmltag($blockKey) . '" data-task_id="' . $task->id . '" style="display: none;">';

            // Task Label (16 chars) with task ref & full label on hover.
            print '<td class="nowrap" style="position: sticky; left: 0; background-color: #FFFFFF; z-index: 500; border-left: 4px solid ' . $block['accent'] . '; max-width: 220px; overflow: hidden; text-overflow: ellipsis;">';
            print '<span class="ts-cal-pa-badge ' . $block['badge_css'] . '" style="margin-right: 6px;">' . dol_escape_htmltag($block['badge']) . '</span>';
            print '<a href="' . dol_escape_htmltag($taskUrl) . '" class="classfortooltip" title="' . dol_escape_htmltag($taskHoverTitle) . '" target="_blank">';
            print img_object('', 'projecttask', 'class="pictofixedwidth"');
            print dol_escape_htmltag(dol_trunc($task->label, 16));
            print '</a>';
            print '</td>';

            // Task total over the month for this user.
            $taskMonthSec = 0;
            for ($idw = 0; $idw < $daysInRange; $idw++) {
                $dayInLoop     = dolisirh_add_days($firstDayToShow, $idw);
                $dayStr        = dol_print_date($dayInLoop, 'day');
                $taskMonthSec += (int) ($timeSpentOnTasks[$task->id][$dayStr] ?? 0);
            }
            print '<td class="right"><strong>' . ($taskMonthSec > 0 ? convertSecondToTime($taskMonthSec, 'allhourmin') : '--:--') . '</strong></td>';

            // Daily cells.
            for ($idw = 0; $idw < $daysInRange; $idw++) {
                $cellCSS   = '';
                $dayInLoop = dolisirh_add_days($firstDayToShow, $idw);
                if (empty($isAvailable[$dayInLoop]['morning']) && empty($isAvailable[$dayInLoop]['afternoon'])) {
                    $reason = !empty($isAvailable[$dayInLoop]['morning_reason']) ? $isAvailable[$dayInLoop]['morning_reason'] : '';
                    if ($reason == 'public_holiday') {
                        $cellCSS = 'onholidayallday';
                    } elseif ($reason == 'week_end') {
                        $cellCSS = 'weekend';
                    }
                }

                $tmpArray = dol_getdate($dayInLoop);
                $dayStr   = dol_print_date($dayInLoop, 'day');
                $daySec   = (int) ($timeSpentOnTasks[$task->id][$dayStr] ?? 0);

                $alreadySpent = '';
                $textTooltip  = '';
                if ($daySec > 0) {
                    $timeSpentComments = $timeSpentOnTasks[$task->id]['comments'][$dayStr] ?? [];
                    if (is_array($timeSpentComments) && !empty($timeSpentComments)) {
                        $textTooltip = implode(' | ', $timeSpentComments);
                    } else {
                        $textTooltip = $langs->trans('TimeSpentAddWithNoComment');
                    }
                    $alreadySpent = convertSecondToTime($daySec, 'allhourmin');
                }
                $altTitle = $langs->trans('AddHereTimeSpentForDay', $tmpArray['mday'], $tmpArray['mon']);

                $disabledTaskDay = $permissionToAdd ? 0 : 1;
                if (!$disabledTaskDay && $restrictBefore && $dayInLoop < $restrictBefore) {
                    $disabledTaskDay = 1;
                }

                $tableCell = '<td class="center ' . $idw . ' ' . $cellCSS . '" data-cell="' . $idw . '">';
                if ($alreadySpent !== '') {
                    $tableCell .= '<span class="timesheetalreadyrecorded wpeo-tooltip-event" aria-label="' . dol_escape_htmltag($textTooltip) . '"><input type="text" class="center smallpadd" size="2" disabled id="timespent[' . $inc . '][' . $idw . ']" name="task[' . $task->id . '][' . $idw . ']" value="' . dol_escape_htmltag($alreadySpent) . '"></span>';
                }
                $tableCell .= '<div class="modal-open">';
                $tableCell .= '<input hidden class="modal-options" data-modal-to-open="timespent" data-from-id="' . $task->id . '" data-from-module="dolisirh">';
                $tableCell .= '<input type="text" alt="' . ($disabledTaskDay ? '' : dol_escape_htmltag($altTitle)) . '" title="' . ($disabledTaskDay ? '' : dol_escape_htmltag($altTitle)) . '" ' . ($disabledTaskDay ? 'disabled' : '') . ' class="center smallpadd timespent" size="2" id="timeadded[' . $inc . '][' . $idw . ']" data-task-id="' . $task->id . '" data-timestamp="' . $dayInLoop . '" data-date="' . dol_escape_htmltag(dol_print_date($dayInLoop, '%a') . ' - ' . dol_print_date($dayInLoop, 'dayreduceformat')) . '" data-cell="' . $idw . '" value="" cols="2" maxlength="5">';
                $tableCell .= '</div></td>';
                print $tableCell;
            }
            print '</tr>';
            $inc++;
        }
    }

    if (empty($presenceTaskObjs) && empty($absenceTaskObjs)) {
        print '<tr><td colspan="' . (2 + $daysInRange) . '"><span class="opacitymedium">' . $langs->trans('TimeSheetNoTaskConfigured') . '</span></td></tr>';
    }

    print '</tbody>';

    // Summary block in footer.
    print '<tfoot style="position: sticky; bottom: 0; background-color: var(--colorbacklineimpair2); z-index: 1040;">';

    // Row 1: Planned working hours.
    print '<tr class="liste_total">';
    print '<td class="liste_total" style="position: sticky; left: 0; background-color: var(--colorbacklineimpair2); z-index: 1040; max-width: 250px; white-space: normal;">';
    print $langs->trans('ExpectedWorkingHours');
    print '</td>';
    print '<td class="liste_total right"><strong><a href="' . dol_buildpath('/dolisirh/view/workinghours_card.php', 1) . '?id=' . (int) $object->fk_user_assign . '" target="_blank">';
    print (($plannedWorking['minutes'] != 0) ? convertSecondToTime($plannedWorking['minutes'] * 60, 'allhourmin') : '00:00') . '</a></strong></td>';

    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $cellCSS           = '';
        $dayInLoop         = dolisirh_add_days($firstDayToShow, $idw);
        $plannedHoursOnDay = load_planned_time_within_range($dayInLoop, dolisirh_add_days($firstDayToShow, $idw + 1), $workingHours, $isAvailable);
        if (!$isAvailable[$dayInLoop]['morning'] && !$isAvailable[$dayInLoop]['afternoon']) {
            if ($isAvailable[$dayInLoop]['morning_reason'] == 'public_holiday') {
                $cellCSS = 'onholidayallday';
            } elseif ($isAvailable[$dayInLoop]['morning_reason'] == 'week_end') {
                $cellCSS = 'weekend';
            }
        }
        print '<td class="liste_total ' . $idw . ' ' . $cellCSS . '" align="center">';
        print '<div class="' . $idw . '">' . (($plannedHoursOnDay['minutes'] != 0) ? convertSecondToTime($plannedHoursOnDay['minutes'] * 60, 'allhourmin') : '00:00') . '</div></td>';
    }
    print '</tr>';

    // Row 2: Due working hours.
    print '<tr class="liste_total planned-working-hours">';
    print '<td class="liste_total" style="position: sticky; left: 0; background-color: var(--colorbacklineimpair2); z-index: 1040; max-width: 250px; white-space: normal;">';
    print $langs->trans('TimeSheetExpectedTime');
    print '</td>';
    print '<td class="liste_total right"><strong>' . (($passedWorking['minutes'] != 0) ? convertSecondToTime($passedWorking['minutes'] * 60, 'allhourmin') : '00:00') . '</strong></td>';

    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $cellCSS          = '';
        $dayInLoop        = dolisirh_add_days($firstDayToShow, $idw);
        $passedHoursOnDay = load_passed_time_within_range($dayInLoop, dolisirh_add_days($firstDayToShow, $idw + 1), $workingHours, $isAvailable);
        if (!$isAvailable[$dayInLoop]['morning'] && !$isAvailable[$dayInLoop]['afternoon']) {
            if ($isAvailable[$dayInLoop]['morning_reason'] == 'public_holiday') {
                $cellCSS = 'onholidayallday';
            } elseif ($isAvailable[$dayInLoop]['morning_reason'] == 'week_end') {
                $cellCSS = 'weekend';
            }
        }
        print '<td class="liste_total ' . $idw . ' ' . $cellCSS . '" align="center">';
        print (($passedHoursOnDay['minutes'] != 0) ? convertSecondToTime($passedHoursOnDay['minutes'] * 60, 'allhourmin') : '00:00') . '</td>';
    }
    print '</tr>';

    // Row 3: Consumed working hours (Presence + Absence).
    $totalConsumedSec = (int) ($timeSpentOnTasks['total'] ?? 0);
    print '<tr class="liste_total spent-hours-in-range">';
    print '<td class="liste_total" style="position: sticky; left: 0; background-color: var(--colorbacklineimpair2); z-index: 1040; max-width: 250px; white-space: normal;">';
    print $langs->trans('ConsumedWorkingHours');
    print '</td>';
    print '<td class="liste_total right"><strong>' . convertSecondToTime($totalConsumedSec, 'allhourmin') . '</strong></td>';

    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $dayInLoop = dolisirh_add_days($firstDayToShow, $idw);
        $cellCSS   = '';
        if (!$isAvailable[$dayInLoop]['morning'] && !$isAvailable[$dayInLoop]['afternoon']) {
            if ($isAvailable[$dayInLoop]['morning_reason'] == 'public_holiday') {
                $cellCSS = 'onholidayallday';
            } elseif ($isAvailable[$dayInLoop]['morning_reason'] == 'week_end') {
                $cellCSS = 'weekend';
            }
        }
        $daySec = $dailyConsumedByTaskGroup['all'][$idw];
        print '<td class="liste_total bold ' . $idw . ' ' . $cellCSS . '" align="center">';
        print '<div class="totalDay' . $idw . '">' . ($daySec > 0 ? convertSecondToTime($daySec, 'allhourmin') : '00:00') . '</div></td>';
    }
    print '</tr>';

    // Row 4: Difference between due & consumed working hours.
    $diffTotalSec = $diffTotalMinutes * 60;
    if ($diffTotalSec < 0) {
        $morecss = colorStringToArray($conf->global->DOLISIRH_EXCEEDED_TIME_SPENT_COLOR);
    } elseif ($diffTotalSec > 0) {
        $morecss = colorStringToArray($conf->global->DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR);
    } else {
        $morecss = colorStringToArray($conf->global->DOLISIRH_PERFECT_TIME_SPENT_COLOR);
    }

    print '<tr class="liste_total planned-working-difference">';
    print '<td class="liste_total" style="position: sticky; left: 0; background-color: var(--colorbacklineimpair2); z-index: 1040; max-width: 250px; white-space: normal;">';
    print $langs->trans('TimeSheetDifference');
    print '</td>';
    print '<td class="liste_total right" style="color: rgb(' . $morecss[0] . ',' . $morecss[1] . ',' . $morecss[2] . ');"><strong>' . (($diffTotalSec != 0) ? convertSecondToTime(abs($diffTotalSec), 'allhourmin') : '00:00') . '</strong></td>';

    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $cellCSS          = '';
        $dayInLoop        = dolisirh_add_days($firstDayToShow, $idw);
        $passedHoursOnDay = load_passed_time_within_range($dayInLoop, dolisirh_add_days($firstDayToShow, $idw + 1), $workingHours, $isAvailable);
        $daySpentMinutes  = (int) floor($dailyConsumedByTaskGroup['all'][$idw] / 60);
        $dayDiffMinutes   = (int) $passedHoursOnDay['minutes'] - $daySpentMinutes;

        if (!$isAvailable[$dayInLoop]['morning'] && !$isAvailable[$dayInLoop]['afternoon']) {
            if ($isAvailable[$dayInLoop]['morning_reason'] == 'public_holiday') {
                $cellCSS = 'onholidayallday';
            } elseif ($isAvailable[$dayInLoop]['morning_reason'] == 'week_end') {
                $cellCSS = 'weekend';
            }
        }

        if ($dayDiffMinutes < 0) {
            $dayCss = colorStringToArray($conf->global->DOLISIRH_EXCEEDED_TIME_SPENT_COLOR);
        } elseif ($dayDiffMinutes > 0) {
            $dayCss = colorStringToArray($conf->global->DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR);
        } else {
            $dayCss = colorStringToArray($conf->global->DOLISIRH_PERFECT_TIME_SPENT_COLOR);
        }

        print '<td class="liste_total bold ' . $idw . ' ' . $cellCSS . '" align="center" style="color: rgb(' . $dayCss[0] . ',' . $dayCss[1] . ',' . $dayCss[2] . ');">';
        print '<div class="' . $idw . '">' . (($dayDiffMinutes != 0) ? convertSecondToTime(abs($dayDiffMinutes * 60), 'allhourmin') : '00:00') . '</div></td>';
    }
    print '</tr>';

    print '</tfoot>';
    print '</table>';
    print '</div>';
    print '</form>';
    ?>

    <!-- TIMESPENT ADD MODAL -->
    <div class="timespent-add-modal">
        <div class="wpeo-modal modal-timespent" id="timespent" style="z-index: 1200;">
            <div class="modal-container wpeo-modal-event" style="max-width: 400px; max-height: 300px;">
                <div class="modal-header">
                    <div class="modal-close"><i class="fas fa-times"></i></div>
                </div>
                <div class="modal-content">
                    <div class="timespent-container">
                        <input type="hidden" class="timespent-taskid" value="">
                        <input type="hidden" class="timespent-timestamp" value="">
                        <input type="hidden" class="timespent-cell" value="">
                        <div class="wpeo-gridlayout grid-3">
                            <div class="gridw-2">
                                <div class="title"><strong><i class="far fa-calendar-alt"></i> <?php echo $langs->trans('Date'); ?></strong></div>
                                <span><span class="timespent-date"></span><label><input class="flat maxwidth50 timespent-datehour" type="number" placeholder="H" min="0" max="23"></label> : <label><input class="flat maxwidth50 timespent-datemin" type="number" placeholder="mn" min="0" max="59"></label></span>
                            </div>
                            <div>
                                <div class="title"><strong><i class="far fa-clock"></i> <?php echo $langs->trans('Duration'); ?></strong></div>
                                <span><label><input class="flat maxwidth50 timespent-hour" type="number" placeholder="H" min="0" max="23"></label> : <label><input class="flat maxwidth50 timespent-min" type="number" placeholder="mn" min="0" max="59"></label></span>
                            </div>
                        </div>
                        <br/>
                        <div class="title"><strong><i class="far fa-comment-alt"></i> <?php echo $langs->trans('Comment'); ?></strong></div>
                        <label><textarea class="timespent-comment maxwidth100onsmartphone" name="timespent-comment" rows="6"></textarea></label>
                    </div>
                </div>
                <div class="modal-footer">
                    <?php if ($permissionToAdd > 0) : ?>
                        <div class="wpeo-button timespent-create button-green" value="">
                            <i class="fas fa-save"></i>
                        </div>
                    <?php else : ?>
                        <div class="wpeo-button button-grey wpeo-tooltip-event" aria-label="<?php echo $langs->trans('PermissionDenied') ?>">
                            <i class="fas fa-save"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- TIMESPENT ADD MODAL END -->

    <script>
        jQuery(document).on('click', '.ts-block-toggle', function () {
            var blockKey = jQuery(this).data('block-key');
            var rows = jQuery('.ts-block-row-' + blockKey);
            var chevron = jQuery(this).find('.ts-block-chevron');
            rows.toggle();
            if (rows.first().is(':visible')) {
                chevron.removeClass('fa-chevron-right').addClass('fa-chevron-down');
            } else {
                chevron.removeClass('fa-chevron-down').addClass('fa-chevron-right');
            }
        });
    </script>

    <?php
    print dol_get_fiche_end();
}

// End of page.
llxFooter();
$db->close();
