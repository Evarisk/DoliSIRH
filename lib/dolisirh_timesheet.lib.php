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
 * \file    lib/dolisirh_timesheet.lib.php
 * \ingroup dolisirh
 * \brief   Library files with common functions for TimeSheet.
 */

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

// Load Saturne libraries.
dol_include_once('/saturne/lib/object.lib.php');

// Load DoliSIRH libraries.
require_once __DIR__ . '/../class/timesheet.class.php';
require_once __DIR__ . '/dolisirh_function.lib.php';
require_once __DIR__ . '/dolisirh_timespent.lib.php';

/**
 * Prepare timesheet pages header.
 *
 * @param  TimeSheet $object TimeSheet.
 * @return array     $head   Array of tabs.
 * @throws Exception
 */
function timesheet_prepare_head(TimeSheet $object): array
{
    global $conf, $langs, $user;

    $moreParams['documentType']       = 'TimeSheetDocument';
    $moreParams['attendantTableMode'] = 'simple';

    $head = saturne_object_prepare_head($object, [], $moreParams, true);

    if (!empty($user->rights->dolisirh->timesheet->read)) {
        $head[] = [
            dol_buildpath('/dolisirh/view/timesheet/timesheet_timespent.php', 1) . '?id=' . $object->id,
            $conf->browser->layout != 'phone' ? '<i class="fas fa-clock pictofixedwidth"></i>' . $langs->trans('ConsumedTime') : '<i class="fas fa-clock"></i>',
            'timespent',
        ];
    }

    return $head;
}

/**
 * Get the date from which a timesheet can be created: a few days before its end date.
 *
 * @param  int $dateEnd Timesheet end date.
 * @return int          Creation start date, at midnight.
 */
function timesheet_get_creation_start_date(int $dateEnd): int
{
    $dateEnd = dol_getdate($dateEnd);

    return dol_time_plus_duree(dol_mktime(0, 0, 0, $dateEnd['mon'], $dateEnd['mday'], $dateEnd['year']), -TimeSheet::CREATION_DAYS_BEFORE_DATE_END, 'd');
}

/**
 * Keep a user selectable as timesheet assignee even if not flagged as employee:
 * otherwise the select silently falls back on the first employee of the list.
 *
 * @param TimeSheet $object TimeSheet.
 * @param int       $userID User to keep selectable.
 */
function timesheet_keep_user_assign_selectable(TimeSheet $object, int $userID): void
{
    // Type is 'integer:User:user/class/user.class.php:1:Filter', the filter can contain ':'
    $type    = explode(':', $object->fields['fk_user_assign']['type'], 5);
    $type[4] = '(' . $type[4] . ') OR (t.rowid:=:' . $userID . ')';

    $object->fields['fk_user_assign']['type'] = implode(':', $type);
}

/**
 * Render the horizontal strip of daily cards for a timesheet, with hover details of recorded time.
 *
 * @param  TimeSheet          $object         Timesheet object.
 * @param  int                $firstDayToShow First day of the period.
 * @param  int                $lastDayToShow  Last day of the period.
 * @param  Workinghours|array $workingHours   Working hours of the assigned user.
 * @return string                             HTML output.
 */
function timesheet_render_daily_calendar(TimeSheet $object, int $firstDayToShow, int $lastDayToShow, $workingHours): string
{
    global $db, $langs;

    $userID = (int) $object->fk_user_assign;
    if ($userID <= 0 || $firstDayToShow <= 0 || $lastDayToShow < $firstDayToShow) {
        return '';
    }

    $presenceTaskIDs = get_hr_project_task_ids('work_time');
    $absenceTaskIDs  = get_hr_project_task_ids('no_work_time');
    $hasHrWorkTime   = !empty($presenceTaskIDs);

    $startCursor = dol_mktime(0, 0, 0, (int) date('m', $firstDayToShow), (int) date('d', $firstDayToShow), (int) date('Y', $firstDayToShow), 'tzserver');
    $endCursor   = dol_mktime(0, 0, 0, (int) date('m', $lastDayToShow), (int) date('d', $lastDayToShow), (int) date('Y', $lastDayToShow), 'tzserver');

    $dayStart = date('Y-m-d', $startCursor);
    $dayEnd   = date('Y-m-d', $endCursor);

    $daily = [];

    $sql  = 'SELECT et.rowid, et.fk_element, et.element_date, et.element_duration, et.note,';
    $sql .= ' pt.ref as task_ref, pt.label as task_label,';
    $sql .= ' p.ref as project_ref, p.title as project_title';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'element_time as et';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet_task as pt ON pt.rowid = et.fk_element';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet as p ON p.rowid = pt.fk_projet';
    $sql .= " WHERE et.elementtype = 'task'";
    $sql .= ' AND et.fk_user = ' . $userID;
    $sql .= " AND et.element_date >= '" . $db->escape($dayStart) . "'";
    $sql .= " AND et.element_date <= '" . $db->escape($dayEnd) . " 23:59:59'";
    $sql .= ' ORDER BY et.element_date ASC, et.rowid ASC';

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $dayKey   = dol_substr((string) $obj->element_date, 0, 10);
            $taskID   = (int) $obj->fk_element;
            $duration = (int) $obj->element_duration;
            if ($duration <= 0) {
                continue;
            }

            if (!isset($daily[$dayKey])) {
                $daily[$dayKey] = [
                    'pres_sec'     => 0,
                    'abs_sec'      => 0,
                    'proj_sec'     => 0,
                    'hr_entries'   => [],
                    'proj_entries' => [],
                ];
            }

            $entry = [
                'task_ref'      => (string) $obj->task_ref,
                'task_label'    => (string) $obj->task_label,
                'project_ref'   => (string) $obj->project_ref,
                'project_title' => (string) $obj->project_title,
                'duration'      => $duration,
                'note'          => trim((string) $obj->note),
            ];

            if (in_array($taskID, $absenceTaskIDs, true)) {
                $daily[$dayKey]['abs_sec']      += $duration;
                $entry['badge']                  = 'A';
                $daily[$dayKey]['hr_entries'][]  = $entry;
            } elseif ($hasHrWorkTime && in_array($taskID, $presenceTaskIDs, true)) {
                $daily[$dayKey]['pres_sec']     += $duration;
                $entry['badge']                  = 'P';
                $daily[$dayKey]['hr_entries'][]  = $entry;
            } elseif (!$hasHrWorkTime) {
                $daily[$dayKey]['pres_sec']       += $duration;
                $daily[$dayKey]['proj_sec']       += $duration;
                $daily[$dayKey]['proj_entries'][]  = $entry;
            } else {
                $daily[$dayKey]['proj_sec']       += $duration;
                $daily[$dayKey]['proj_entries'][]  = $entry;
            }
        }
        $db->free($resql);
    }

    $today    = dol_mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('Y'), 'tzserver');
    $todayStr = date('Y-m-d', $today);

    $out    = '<div class="ts-cal-strip">';
    $cursor = $startCursor;

    for ($i = 0; $cursor <= $endCursor && $i < 62; $i++) {
        $dayKey          = date('Y-m-d', $cursor);
        $dayOfWeek       = (int) date('N', $cursor);
        $isWeekEnd       = $dayOfWeek >= 6;
        $gmtDay          = dol_mktime(0, 0, 0, (int) date('m', $cursor), (int) date('d', $cursor), (int) date('Y', $cursor), 'gmt');
        $isPublicHoliday = !$isWeekEnd && num_public_holiday($gmtDay, $gmtDay + 86400) > 0;

        $expectedSec = ($isWeekEnd || $isPublicHoliday) ? 0 : dolisirh_working_hours_of_day($workingHours, 'workinghours_' . dol_strtolower(date('l', $cursor))) * 60;
        $presSec     = $daily[$dayKey]['pres_sec'] ?? 0;
        $absSec      = $daily[$dayKey]['abs_sec'] ?? 0;
        $projSec     = $daily[$dayKey]['proj_sec'] ?? 0;
        $totalDaySec = $presSec + $absSec;

        if ($isWeekEnd && $expectedSec === 0 && $totalDaySec === 0 && $projSec === 0) {
            $cursor = dolisirh_add_days($cursor, 1);
            continue;
        }

        $stateClass = 'cal-day-grey';
        $statusText = $langs->transnoentities('Status');
        if (($expectedSec > 0 && $totalDaySec === $expectedSec) || ($expectedSec === 0 && $totalDaySec > 0 && !$isWeekEnd)) {
            $stateClass = 'cal-day-ok';
            $statusText = $langs->transnoentities('TimeSheetBalanced');
        } elseif ($totalDaySec > $expectedSec && $expectedSec > 0) {
            $stateClass = 'cal-day-over';
            $diff       = $totalDaySec - $expectedSec;
            $statusText = '+' . convertSecondToTime($diff, 'allhourmin');
        } elseif (($cursor <= $today && $expectedSec > 0 && $totalDaySec !== $expectedSec) || ($cursor > $today && $totalDaySec > 0 && $totalDaySec !== $expectedSec) || ($isWeekEnd && $totalDaySec > 0)) {
            $stateClass = 'cal-day-ko';
            $diff       = $totalDaySec - $expectedSec;
            $statusText = ($diff > 0 ? '+' : '-') . convertSecondToTime(abs($diff), 'allhourmin');
        } else {
            $statusText = '00:00';
        }

        $paText  = '-';
        $paClass = 'is-none';
        if ($presSec > 0 && $absSec > 0) {
            $paText  = 'P/A';
            $paClass = 'is-pa';
        } elseif ($presSec > 0) {
            $paText  = 'P';
            $paClass = 'is-p';
        } elseif ($absSec > 0) {
            $paText  = 'A';
            $paClass = 'is-a';
        }

        $dayName = dol_strtoupper(dol_substr(dol_print_date($cursor, '%a', 'tzserver', $langs), 0, 3));
        $ddStr   = date('d', $cursor);
        $mmStr   = date('m', $cursor);
        $yyyyStr = date('Y', $cursor);
        $durStr  = $stateClass === 'cal-day-grey'
            ? ($expectedSec > 0 ? convertSecondToTime($expectedSec, 'allhourmin') : '00:00')
            : ($totalDaySec > 0 ? convertSecondToTime($totalDaySec, 'allhourmin') : '00:00');

        $expectedFmt = $expectedSec > 0 ? convertSecondToTime($expectedSec, 'allhourmin') : '00:00';
        $loggedFmt   = $totalDaySec > 0 ? convertSecondToTime($totalDaySec, 'allhourmin') : '00:00';
        $presFmt     = $presSec > 0 ? convertSecondToTime($presSec, 'allhourmin') : '00:00';
        $absFmt      = $absSec > 0 ? convertSecondToTime($absSec, 'allhourmin') : '00:00';
        $projFmt     = $projSec > 0 ? convertSecondToTime($projSec, 'allhourmin') : '00:00';

        $dayUrl = DOL_URL_ROOT . '/custom/dolisirh/view/timespent_range.php?year=' . (int) $yyyyStr . '&month=' . (int) $mmStr . '&day=' . (int) $ddStr . '&search_user_id=' . $userID . '&view_mode=month&backtopage=' . urlencode(DOL_URL_ROOT . '/custom/dolisirh/view/timesheet/timesheet_card.php?id=' . $object->id);

        $isTodayClass = ($dayKey === $todayStr) ? ' is-today' : '';

        $out .= '<a class="ts-cal-day ' . $stateClass . $isTodayClass . '" href="' . dol_escape_htmltag($dayUrl) . '" target="_blank">';
        $out .= '<div class="ts-cal-day-top">';
        $out .= '<span class="ts-cal-day-name">' . dol_escape_htmltag($dayName) . '</span>';
        $out .= '<span class="ts-cal-pa-badge ' . $paClass . '">' . dol_escape_htmltag($paText) . '</span>';
        $out .= '</div>';
        $out .= '<span class="ts-cal-day-num">' . dol_escape_htmltag($ddStr) . '</span>';
        $out .= '<span class="ts-cal-day-month">' . dol_escape_htmltag($mmStr . '/' . $yyyyStr) . '</span>';
        $out .= '<span class="ts-cal-day-dur">' . dol_escape_htmltag($durStr) . '</span>';

        // Structured hover tooltip showing recorded time (HR presence/absence + project tasks)
        $out .= '<div class="ts-cal-day-tooltip">';
        $out .= '<div class="ts-cal-tt-header">';
        $out .= '<span class="ts-cal-tt-date">' . dol_escape_htmltag($dayName . ' ' . $ddStr . '/' . $mmStr . '/' . $yyyyStr) . '</span>';
        $out .= '<span class="ts-cal-tt-status ' . $stateClass . '">' . dol_escape_htmltag($statusText) . '</span>';
        $out .= '</div>';

        $out .= '<div class="ts-cal-tt-summary">';
        $out .= '<span>' . dol_escape_htmltag($langs->transnoentities('TimeSheetExpected') . ' : ' . $expectedFmt) . '</span>';
        $out .= '<span>' . dol_escape_htmltag($langs->transnoentities('TimeSheetLogged') . ' : ' . $loggedFmt . ' (P: ' . $presFmt . ' | A: ' . $absFmt . ')') . '</span>';
        $out .= '</div>';

        $hrEntries   = $daily[$dayKey]['hr_entries'] ?? [];
        $projEntries = $daily[$dayKey]['proj_entries'] ?? [];

        if (!empty($hrEntries)) {
            $out .= '<div class="ts-cal-tt-section">';
            foreach ($hrEntries as $hrEntry) {
                $durEntry = convertSecondToTime($hrEntry['duration'], 'allhourmin');
                $desc     = $hrEntry['task_ref'] . ' - ' . $hrEntry['task_label'];
                if ($hrEntry['note'] !== '') {
                    $desc .= ' (' . $hrEntry['note'] . ')';
                }
                $out .= '<div class="ts-cal-tt-row">';
                $out .= '<span class="ts-cal-tt-left"><span class="ts-cal-pa-badge is-' . dol_strtolower($hrEntry['badge']) . '">' . dol_escape_htmltag($hrEntry['badge']) . '</span> ' . dol_escape_htmltag($desc) . '</span>';
                $out .= '<span class="ts-cal-tt-right">' . dol_escape_htmltag($durEntry) . '</span>';
                $out .= '</div>';
            }
            $out .= '</div>';
        }

        if ($hasHrWorkTime) {
            $projDiffClass = ($projSec === $presSec) ? 'is-ok' : 'is-ko';
            $out .= '<div class="ts-cal-tt-section">';
            $out .= '<div class="ts-cal-tt-sec-title">';
            $out .= '<span>' . dol_escape_htmltag($langs->transnoentities('Projects') . ' :') . '</span>';
            $out .= '<span class="ts-cal-tt-sec-total ' . $projDiffClass . '">' . dol_escape_htmltag($projFmt) . '</span>';
            $out .= '</div>';
            if (!empty($projEntries)) {
                foreach ($projEntries as $projEntry) {
                    $durEntry = convertSecondToTime($projEntry['duration'], 'allhourmin');
                    $desc     = $projEntry['task_ref'] . ' - ' . ($projEntry['note'] !== '' ? $projEntry['note'] : $projEntry['task_label']);
                    $out .= '<div class="ts-cal-tt-row">';
                    $out .= '<span class="ts-cal-tt-left">&bull; ' . dol_escape_htmltag($desc) . '</span>';
                    $out .= '<span class="ts-cal-tt-right">' . dol_escape_htmltag($durEntry) . '</span>';
                    $out .= '</div>';
                }
            } else {
                $out .= '<div class="ts-cal-tt-empty">-</div>';
            }
            $out .= '</div>';
        }

        $out .= '</div>';
        $out .= '</a>';

        $cursor = dolisirh_add_days($cursor, 1);
    }

    $out .= '</div>';

    return $out;
}

/**
 * Check if a user has logged time on days outside their declared working hours schedule
 * (e.g. Saturday or Sunday when schedule_<day> / workinghours_<day> are empty or 0).
 *
 * @param  int                $firstDayToShow Start timestamp of the period.
 * @param  int                $lastDayToShow  End timestamp of the period.
 * @param  Workinghours|array $workingHours   User working hours object or array.
 * @param  int                $userID         User ID.
 * @param  int[]              $taskIDs        Optional list of task IDs to filter on.
 * @return array                              Array of days with time spent outside declared working hours.
 */
function check_time_spent_outside_working_hours(int $firstDayToShow, int $lastDayToShow, $workingHours, int $userID, array $taskIDs = []): array
{
    global $db, $langs;

    if ($userID <= 0) {
        return [];
    }

    $startCursor = dol_mktime(0, 0, 0, (int) date('m', $firstDayToShow), (int) date('d', $firstDayToShow), (int) date('Y', $firstDayToShow), 'tzserver');
    $endCursor   = dol_mktime(0, 0, 0, (int) date('m', $lastDayToShow), (int) date('d', $lastDayToShow), (int) date('Y', $lastDayToShow), 'tzserver');
    $startDate   = date('Y-m-d', $startCursor);
    $endDate     = date('Y-m-d', $endCursor);

    $sql  = 'SELECT et.element_date, SUM(et.element_duration) AS total_duration';
    $sql .= ', ew.schedule_monday, ew.schedule_tuesday, ew.schedule_wednesday, ew.schedule_thursday, ew.schedule_friday, ew.schedule_saturday, ew.schedule_sunday';
    $sql .= ', ew.workinghours_monday, ew.workinghours_tuesday, ew.workinghours_wednesday, ew.workinghours_thursday, ew.workinghours_friday, ew.workinghours_saturday, ew.workinghours_sunday';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'element_time AS et';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet_task AS pt ON pt.rowid = et.fk_element';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'element_workinghours AS ew ON ew.element_id = et.fk_user AND ew.element_type = \'user\' AND ew.status = 1 AND ew.entity IN (' . getEntity('element_workinghours') . ')';
    $sql .= ' WHERE et.fk_user = ' . (int) $userID;
    $sql .= " AND et.elementtype = 'task'";
    $sql .= " AND et.element_date >= '" . $db->escape($startDate) . "'";
    $sql .= " AND et.element_date <= '" . $db->escape($endDate) . "'";
    if (!empty($taskIDs)) {
        $sql .= ' AND et.fk_element IN (' . $db->sanitize(implode(',', array_map('intval', $taskIDs))) . ')';
    }
    $sql .= ' GROUP BY et.element_date, ew.rowid';
    $sql .= ' HAVING SUM(et.element_duration) > 0';
    $sql .= ' ORDER BY et.element_date ASC';

    $outsideDays = [];
    $resql       = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $dayTs   = $db->jdate($obj->element_date);
            $dayKey  = date('Y-m-d', $dayTs);
            $weekday = dol_strtolower(date('l', $dayTs));
            $whProp  = 'workinghours_' . $weekday;

            $declaredMinutes = isset($obj->$whProp) ? (int) $obj->$whProp : dolisirh_working_hours_of_day($workingHours, $whProp);

            if ($declaredMinutes <= 0) {
                $duration = (int) $obj->total_duration;
                $dayLabel = dol_ucfirst(dol_print_date($dayTs, '%A %d/%m/%Y', 'tzserver', $langs));
                $outsideDays[$dayKey] = [
                    'date'      => $dayKey,
                    'timestamp' => $dayTs,
                    'duration'  => $duration,
                    'label'     => $dayLabel . ' (' . convertSecondToTime($duration, 'allhourmin') . ')',
                ];
            }
        }
        $db->free($resql);
    }

    return $outsideDays;
}

/**
 * Load the tasks of the HR project grouped into presence ('work_time') and absence ('no_work_time').
 *
 * @param  int   $hrProjectID HR project ID.
 * @return array              Array with 'work_time' => Task[], 'no_work_time' => Task[], 'all_ids' => int[].
 */
function timesheet_get_hr_project_tasks_by_type(int $hrProjectID): array
{
    global $db;

    $result = [
        'work_time'    => [],
        'no_work_time' => [],
        'all_ids'      => [],
    ];

    $configuredWorkTimeIDs   = get_hr_project_task_ids('work_time');
    $configuredNoWorkTimeIDs = get_hr_project_task_ids('no_work_time');
    $configuredAllIDs        = array_values(array_unique(array_merge($configuredWorkTimeIDs, $configuredNoWorkTimeIDs)));

    $whereParts = [];
    if ($hrProjectID > 0) {
        $whereParts[] = 'pt.fk_projet = ' . (int) $hrProjectID;
    }
    if (!empty($configuredAllIDs)) {
        $whereParts[] = 'pt.rowid IN (' . $db->sanitize(implode(',', array_map('intval', $configuredAllIDs))) . ')';
    }
    if (empty($whereParts)) {
        return $result;
    }

    $sql  = 'SELECT pt.rowid, pt.ref, pt.fk_projet, pt.fk_task_parent, pt.label, pt.description, pt.duration_effective, pt.planned_workload, pt.progress, pt.fk_statut, pt.dateo, pt.datee, pt.rang';
    $sql .= ', p.public AS project_public';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'projet_task AS pt';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet AS p ON p.rowid = pt.fk_projet';
    $sql .= ' WHERE (' . implode(' OR ', $whereParts) . ')';
    if ($hrProjectID > 0) {
        $sql .= ' AND pt.fk_projet = ' . (int) $hrProjectID;
    }
    $sql .= ' ORDER BY pt.rang ASC, pt.rowid ASC';

    $tasksByID = [];
    $resql     = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $taskObj                   = new Task($db);
            $taskObj->id               = (int) $obj->rowid;
            $taskObj->ref              = (string) $obj->ref;
            $taskObj->fk_project       = (int) $obj->fk_projet;
            $taskObj->fk_task_parent   = (int) $obj->fk_task_parent;
            $taskObj->label            = (string) $obj->label;
            $taskObj->description      = (string) $obj->description;
            $taskObj->duration         = (int) $obj->duration_effective;
            $taskObj->planned_workload = (int) $obj->planned_workload;
            $taskObj->progress         = (int) $obj->progress;
            $taskObj->fk_statut        = (int) $obj->fk_statut;
            $taskObj->date_start       = $db->jdate($obj->dateo);
            $taskObj->date_end         = $db->jdate($obj->datee);
            $taskObj->rang             = (int) $obj->rang;
            $taskObj->public           = (int) $obj->project_public;

            $tasksByID[$taskObj->id] = $taskObj;
        }
        $db->free($resql);
    }

    // First place configured HR tasks in their defined order
    $placedIDs = [];
    foreach ($configuredWorkTimeIDs as $tid) {
        if (isset($tasksByID[$tid])) {
            $result['work_time'][] = $tasksByID[$tid];
            $placedIDs[$tid]       = true;
        }
    }
    foreach ($configuredNoWorkTimeIDs as $tid) {
        if (isset($tasksByID[$tid])) {
            $result['no_work_time'][] = $tasksByID[$tid];
            $placedIDs[$tid]          = true;
        }
    }

    // Classify any remaining tasks of the HR project by label
    foreach ($tasksByID as $tid => $taskObj) {
        if (isset($placedIDs[$tid])) {
            continue;
        }
        $labelLower = dol_strtolower($taskObj->label);
        $isPresence = (
            strpos($labelLower, 'temps de travail') !== false
            || strpos($labelLower, 'work time') !== false
            || strpos($labelLower, 'formation') !== false
            || strpos($labelLower, 'training') !== false
            || strpos($labelLower, 'déplacement') !== false
            || strpos($labelLower, 'deplacement') !== false
            || strpos($labelLower, 'travel') !== false
            || strpos($labelLower, 'délégation') !== false
            || strpos($labelLower, 'delegation') !== false
            || strpos($labelLower, 'cse') !== false
        ) && strpos($labelLower, 'no work time') === false && strpos($labelLower, 'hors temps') === false;

        if ($isPresence) {
            $result['work_time'][] = $taskObj;
        } else {
            $result['no_work_time'][] = $taskObj;
        }
    }

    $result['all_ids'] = array_keys($tasksByID);

    return $result;
}
