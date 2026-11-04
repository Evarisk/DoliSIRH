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
 * \file    view/timesheet/timesheet_page.php
 * \ingroup dolisirh
 * \brief   Dedicated page for timesheet overview and control across all employees.
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
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

// Load DoliSIRH libraries.
require_once __DIR__ . '/../../lib/dolisirh_function.lib.php';
require_once __DIR__ . '/../../lib/dolisirh_timespent.lib.php';
require_once __DIR__ . '/../../lib/dolisirh_timesheet.lib.php';
require_once __DIR__ . '/../../class/timesheet.class.php';
require_once __DIR__ . '/../../class/workinghours.class.php';

// Global variables definitions.
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page.
saturne_load_langs(['projects', 'users', 'companies']);

// Get parameters.
$action = GETPOST('action', 'aZ09');
$year   = GETPOSTINT('year');
$month  = GETPOSTINT('month');

// Initialize technical objects.
$object       = new TimeSheet($db);
$workingHours = new Workinghours($db);
$form         = new Form($db);

$hookmanager->initHooks(['timesheetpage']);

// Security check.
$permissionToRead = $user->rights->dolisirh->timesheet->read;
saturne_check_access($permissionToRead);

// Default to the latest timesheet month if year/month are not specified, otherwise current month.
if ($year <= 0 || $month <= 0 || $month > 12) {
    $sqlLatest = 'SELECT MAX(date_start) AS max_date FROM ' . MAIN_DB_PREFIX . 'dolisirh_timesheet WHERE entity IN (' . getEntity('timesheet') . ') AND status >= 0';
    $resLatest = $db->query($sqlLatest);
    $defaultTs = dol_now();
    if ($resLatest) {
        $objLatest = $db->fetch_object($resLatest);
        if (!empty($objLatest->max_date)) {
            $defaultTs = $db->jdate($objLatest->max_date);
        }
        $db->free($resLatest);
    }
    $year  = (int) date('Y', $defaultTs);
    $month = (int) date('m', $defaultTs);
}

$firstDayToShow = dol_get_first_day($year, $month, false);
$lastDayToShow  = dol_get_last_day($year, $month, false);
$lastDayToShow  = dol_mktime(0, 0, 0, (int) date('m', $lastDayToShow), (int) date('d', $lastDayToShow), (int) date('Y', $lastDayToShow), 'tzserver');

$prevYear  = ($month === 1) ? ($year - 1) : $year;
$prevMonth = ($month === 1) ? 12 : ($month - 1);
$nextYear  = ($month === 12) ? ($year + 1) : $year;
$nextMonth = ($month === 12) ? 1 : ($month + 1);

/*
 * Actions.
 */

$parameters = [];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

/*
 * View.
 */

$title    = $langs->trans('TimeSheet');
$help_url = 'FR:Module_DoliSIRH';

saturne_header(0, '', $title, $help_url);

print load_fiche_titre($title, '', $object->picto);

$startDate   = dol_print_date($firstDayToShow, 'dayreduceformat');
$endDate     = dol_print_date($lastDayToShow, 'dayreduceformat');
$periodTitle = dol_ucfirst(dol_print_date($firstDayToShow, '%B %Y'));

// Load all active employees.
$employees = [];
$sqlEmp    = 'SELECT u.rowid FROM ' . MAIN_DB_PREFIX . 'user AS u';
$sqlEmp   .= ' WHERE u.employee = 1 AND u.statut = 1';
$sqlEmp   .= ' AND u.entity IN (' . getEntity('user') . ')';
$sqlEmp   .= ' ORDER BY u.lastname ASC, u.firstname ASC';
$resEmp    = $db->query($sqlEmp);
if ($resEmp) {
    while ($objEmp = $db->fetch_object($resEmp)) {
        $empUser = new User($db);
        if ($empUser->fetch((int) $objEmp->rowid) > 0) {
            $employees[(int) $empUser->id] = $empUser;
        }
    }
    $db->free($resEmp);
}

// Load existing timesheets for the selected month indexed by fk_user_assign.
$timesheetsByUser = [];
$sqlTs            = 'SELECT t.rowid, t.ref, t.label, t.status, t.fk_user_assign, t.date_start, t.date_end';
$sqlTs           .= ' FROM ' . MAIN_DB_PREFIX . 'dolisirh_timesheet AS t';
$sqlTs           .= ' WHERE t.entity IN (' . getEntity('timesheet') . ')';
$sqlTs           .= ' AND t.status >= 0';
$sqlTs           .= " AND t.date_start <= '" . $db->idate($lastDayToShow + 86399) . "'";
$sqlTs           .= " AND t.date_end >= '" . $db->idate($firstDayToShow) . "'";
$sqlTs           .= ' ORDER BY t.rowid DESC';
$resTs            = $db->query($sqlTs);
if ($resTs) {
    while ($objTs = $db->fetch_object($resTs)) {
        $uid = (int) $objTs->fk_user_assign;
        if ($uid > 0 && !isset($timesheetsByUser[$uid])) {
            $tsObj                 = new TimeSheet($db);
            $tsObj->id             = (int) $objTs->rowid;
            $tsObj->ref            = (string) $objTs->ref;
            $tsObj->label          = (string) $objTs->label;
            $tsObj->status         = (int) $objTs->status;
            $tsObj->fk_user_assign = $uid;
            $tsObj->date_start     = $db->jdate($objTs->date_start);
            $tsObj->date_end       = $db->jdate($objTs->date_end);
            $timesheetsByUser[$uid] = $tsObj;
        }
    }
    $db->free($resTs);
}

$daysInRange      = dolisirh_num_between_days($firstDayToShow, $lastDayToShow, 1);
$timesheetTaskIDs = get_timesheet_task_ids();

$exceededColor    = colorStringToArray(getDolGlobalString('DOLISIRH_EXCEEDED_TIME_SPENT_COLOR'));
$notExceededColor = colorStringToArray(getDolGlobalString('DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR'));
$perfectColor     = colorStringToArray(getDolGlobalString('DOLISIRH_PERFECT_TIME_SPENT_COLOR'));

// Top toolbar with period info and month navigation.
print '<div class="ts-page-toolbar">';
print '<div class="ts-page-toolbar-left">';
print '<span>' . $langs->trans('ExpectedWorkingHoursMonthTimeSheet', $startDate, $endDate) . '</span>';
print '<span class="ts-page-count-badge">' . count($employees) . ' ' . $langs->trans('Users') . '</span>';
print '</div>';
print '<div class="ts-page-toolbar-right">';
print '<a class="ts-page-nav-btn" href="' . $_SERVER['PHP_SELF'] . '?year=' . $prevYear . '&month=' . $prevMonth . '" title="' . dol_escape_htmltag($langs->transnoentities('Previous')) . '">&lsaquo;</a>';
print '<span class="ts-page-period-label">' . dol_escape_htmltag($periodTitle) . '</span>';
print '<a class="ts-page-nav-btn" href="' . $_SERVER['PHP_SELF'] . '?year=' . $nextYear . '&month=' . $nextMonth . '" title="' . dol_escape_htmltag($langs->transnoentities('Next')) . '">&rsaquo;</a>';
print '<a class="ts-page-nav-btn" href="' . dol_buildpath('/dolisirh/view/timesheet/timesheet_list.php', 1) . '">' . $langs->trans('TimeSheetList') . '</a>';
print '</div>';
print '</div>';

print '<div class="ts-emp-list">';

foreach ($employees as $empID => $empUser) {
    $empTimeSheet = $timesheetsByUser[$empID] ?? null;
    if ($empTimeSheet === null) {
        $empTimeSheet                 = new TimeSheet($db);
        $empTimeSheet->id             = 0;
        $empTimeSheet->fk_user_assign = $empID;
    }

    $isAvailable = [];
    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $dayInLoop = dolisirh_add_days($firstDayToShow, $idw);
        if (is_day_available($dayInLoop, $empID)) {
            $isAvailable[$dayInLoop] = ['morning' => 1, 'afternoon' => 1];
        } elseif ((int) date('N', $dayInLoop) >= 6) {
            $isAvailable[$dayInLoop] = ['morning' => false, 'afternoon' => false, 'morning_reason' => 'week_end', 'afternoon_reason' => 'week_end'];
        } else {
            $isAvailable[$dayInLoop] = ['morning' => false, 'afternoon' => false, 'morning_reason' => 'public_holiday', 'afternoon_reason' => 'public_holiday'];
        }
    }

    $empWorkingHours         = $workingHours->fetchCurrentWorkingHours($empID, 'user');
    $timeSpendingInfos       = load_time_spending_infos_within_range($firstDayToShow, dolisirh_add_days($lastDayToShow, 1), $empWorkingHours, $isAvailable, $empID, $timesheetTaskIDs);
    $outsideWorkingHoursDays = check_time_spent_outside_working_hours($firstDayToShow, $lastDayToShow, $empWorkingHours, $empID, $timesheetTaskIDs);

    $plannedWorkingTime = $timeSpendingInfos['planned'];
    $passedWorkingTime  = $timeSpendingInfos['passed'];
    $workingTime        = $timeSpendingInfos['spent'];
    $diffTotalTime      = $timeSpendingInfos['difference'];

    $plannedDays  = (int) ($plannedWorkingTime['days'] ?? 0);
    $spentDays    = (int) ($workingTime['days'] ?? 0);
    $plannedHours = ($plannedWorkingTime['minutes'] != 0) ? convertSecondToTime($plannedWorkingTime['minutes'] * 60, 'allhourmin') : '00:00';
    $passedHours  = ($passedWorkingTime['minutes'] != 0) ? convertSecondToTime($passedWorkingTime['minutes'] * 60, 'allhourmin') : '00:00';
    $spentHours   = ($workingTime['total'] != 0) ? convertSecondToTime($workingTime['total'], 'allhourmin') : '00:00';
    $diffHours    = ($diffTotalTime != 0) ? convertSecondToTime(abs($diffTotalTime * 60), 'allhourmin') : '00:00';

    if ($plannedDays > $spentDays) {
        $morecssDays = $exceededColor;
    } elseif ($plannedDays < $spentDays) {
        $morecssDays = $notExceededColor;
    } else {
        $morecssDays = $perfectColor;
    }

    if ($diffTotalTime < 0 || !empty($outsideWorkingHoursDays)) {
        $moreCssHours = $exceededColor;
        $cardState    = 'is-ko';
        $ttStatusCls  = 'cal-day-ko';
    } elseif ($diffTotalTime > 0) {
        $moreCssHours = $notExceededColor;
        $cardState    = 'is-warn';
        $ttStatusCls  = 'cal-day-over';
    } else {
        $moreCssHours = $perfectColor;
        $cardState    = 'is-ok';
        $ttStatusCls  = 'cal-day-ok';
    }

    $daysRgb  = 'rgb(' . (int) ($morecssDays[0] ?? 0) . ',' . (int) ($morecssDays[1] ?? 0) . ',' . (int) ($morecssDays[2] ?? 0) . ')';
    $hoursRgb = 'rgb(' . (int) ($moreCssHours[0] ?? 0) . ',' . (int) ($moreCssHours[1] ?? 0) . ',' . (int) ($moreCssHours[2] ?? 0) . ')';

    $empCardUrl = $empTimeSheet->id > 0
        ? dol_buildpath('/dolisirh/view/timesheet/timesheet_card.php', 1) . '?id=' . (int) $empTimeSheet->id
        : dol_buildpath('/dolisirh/view/timespent_range.php', 1) . '?year=' . $year . '&month=' . $month . '&day=1&search_user_id=' . $empID . '&view_mode=month';

    $empFullName  = $empUser->getFullName($langs);
    $empShortName = !empty($empUser->firstname) ? $empUser->firstname : $empFullName;
    $empPhotoHtml = Form::showphoto('userphoto', $empUser, 40, 40, 0, 'photouserphoto', 'small', 0, 1);

    $tsRefBadge = $empTimeSheet->id > 0
        ? $empTimeSheet->ref . ' - ' . $empTimeSheet->getLibStatut(0)
        : $langs->transnoentities('NoTimeSheet');

    print '<div class="ts-emp-row">';

    // Left employee card with compact metrics and rich hover tooltip.
    print '<a class="ts-emp-card ' . $cardState . '" href="' . dol_escape_htmltag($empCardUrl) . '">';
    print '<div class="ts-emp-identity">';
    print '<div class="ts-emp-avatar">' . $empPhotoHtml . '</div>';
    print '<span class="ts-emp-short-name">' . dol_escape_htmltag($empShortName) . '</span>';
    print '</div>';

    print '<div class="ts-emp-metrics">';
    print '<span class="ts-emp-days">';
    print '<span class="ts-emp-days-planned">' . $plannedDays . '</span>';
    print '<span class="ts-emp-days-spent" style="color:' . $daysRgb . ';">' . $spentDays . '</span>';
    print '</span>';
    print '<span class="ts-emp-hours-planned">' . dol_escape_htmltag($plannedHours) . '</span>';
    print '<span class="ts-emp-hours-spent">' . dol_escape_htmltag($spentHours) . '</span>';
    print '<span class="ts-emp-hours-diff" style="color:' . $hoursRgb . ';">' . dol_escape_htmltag($diffHours) . '</span>';
    print '</div>';

    // Hover tooltip with full employee and timesheet summary.
    print '<div class="ts-emp-tooltip">';
    print '<div class="ts-cal-tt-header">';
    print '<span>' . dol_escape_htmltag($empFullName) . '</span>';
    print '<span class="ts-cal-tt-status ' . $ttStatusCls . '">' . dol_escape_htmltag($tsRefBadge) . '</span>';
    print '</div>';

    print '<div class="ts-cal-tt-section" style="border-top:none;padding-top:0;margin-top:0;">';
    print '<div class="ts-cal-tt-row"><span class="ts-cal-tt-left">' . dol_escape_htmltag($langs->transnoentities('ExpectedWorkingDayMonth')) . '</span><span class="ts-cal-tt-right">' . $plannedDays . '</span></div>';
    print '<div class="ts-cal-tt-row"><span class="ts-cal-tt-left">' . dol_escape_htmltag($langs->transnoentities('ConsumedWorkingDayMonth')) . '</span><span class="ts-cal-tt-right" style="color:' . $daysRgb . ';">' . $spentDays . '</span></div>';
    print '</div>';

    print '<div class="ts-cal-tt-section">';
    print '<div class="ts-cal-tt-row"><span class="ts-cal-tt-left">' . dol_escape_htmltag($langs->transnoentities('ExpectedWorkingHoursMonthTimeSheet', $startDate, $endDate)) . '</span><span class="ts-cal-tt-right">' . dol_escape_htmltag($plannedHours) . '</span></div>';
    print '<div class="ts-cal-tt-row"><span class="ts-cal-tt-left">' . dol_escape_htmltag($langs->transnoentities('SpentWorkingHoursMonth', $startDate, $endDate)) . '</span><span class="ts-cal-tt-right">' . dol_escape_htmltag($passedHours) . '</span></div>';
    print '<div class="ts-cal-tt-row"><span class="ts-cal-tt-left">' . dol_escape_htmltag($langs->transnoentities('ConsumedWorkingHoursMonth', $startDate, $endDate)) . '</span><span class="ts-cal-tt-right">' . dol_escape_htmltag($spentHours) . '</span></div>';
    print '<div class="ts-cal-tt-row"><span class="ts-cal-tt-left">' . dol_escape_htmltag($langs->transnoentities('DiffSpentAndConsumedWorkingHoursMonth', $startDate, $endDate)) . '</span><span class="ts-cal-tt-right" style="color:' . $hoursRgb . ';">' . dol_escape_htmltag($diffHours) . '</span></div>';
    print '</div>';

    if (!empty($outsideWorkingHoursDays)) {
        $outsideLabels = array_map(static function (array $dayInfo): string {
            return dol_escape_htmltag($dayInfo['label']);
        }, $outsideWorkingHoursDays);
        print '<div class="ts-emp-tt-alert">';
        print dol_escape_htmltag($langs->transnoentities('ConsumedTime') . ' (' . $langs->transnoentities('WorkingHours') . ' : 00:00) : ') . implode(', ', $outsideLabels);
        print '</div>';
    }

    print '</div>';
    print '</a>';

    // Right horizontal strip of daily calendar cards for this employee.
    print timesheet_render_daily_calendar($empTimeSheet, $firstDayToShow, $lastDayToShow, $empWorkingHours);

    print '</div>';
}

print '</div>';

// End of page.
llxFooter();
$db->close();
