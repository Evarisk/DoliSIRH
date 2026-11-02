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
    $moreParams['documentType']       = 'TimeSheetDocument';
    $moreParams['attendantTableMode'] = 'simple';

    return saturne_object_prepare_head($object, [], $moreParams, true);
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
