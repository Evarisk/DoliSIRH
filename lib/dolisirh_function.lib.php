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
* \file    lib/dolisirh_function.lib.php
* \ingroup dolisirh
* \brief   Library files with common functions for DoliSIRH.
*/

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';

// Load DoliSIRH libraries.
require_once __DIR__ . '/../class/workinghours.class.php';

/**
 * Add or delete task from favorite by the user.
 *
 * @param  int $taskID task ID.
 * @param  int $userID User ID.
 * @return int
 */
function toggle_task_favorite(int $taskID, int $userID): int
{
    global $db;

    $linkExists = 0;

    $task = new Task($db);

    $task->fetch($taskID);
    $task->fetchObjectLinked();

    if (!empty($task->linkedObjects) && key_exists('user', $task->linkedObjects)) {
        foreach ($task->linkedObjects['user'] as $userLinked) {
            if ($userLinked->id == $userID) {
                $linkExists = 1;
                $task->deleteObjectLinked($userID, 'user');
            }
        }
    }

    if (!$linkExists) {
        return $task->add_object_linked('user', $userID, '', '');
    }
    return 0;
}

/**
 * Check if task is set to favorite by the user.
 *
 * @param  int $taskID     task ID.
 * @param  int $userID     User ID.
 * @return int $linkExists 0 = Not favorite | 1 = Favorite.
 */
function is_task_favorite(int $taskID, int $userID): int
{
    global $db;

    $linkExists = 0;

    $task = new Task($db);

    $task->fetch($taskID);
    $task->fetchObjectLinked();

    if (!empty($task->linkedObjects) && key_exists('user', $task->linkedObjects)) {
        foreach ($task->linkedObjects['user'] as $userLinked) {
            if ($userLinked->id == $userID) {
                $linkExists = 1;
            }
        }
    }

    return $linkExists;
}

/**
 * Check if day is available: false on a week-end or a public holiday.
 *
 * An approved leave does not make the day unavailable: it counts in the expected time, as on the timesheet document,
 * and the employee justifies it with the leave task. The test meant to leave approved leaves out never did, since
 * Holiday::verifDateHolidayForTimestamp() returns an array, always true: it is gone, with one query per day.
 *
 * @param  int  $date Timestamp.
 * @return bool       false = not availability | true = availability.
 */
function is_day_available(int $date): bool
{
    if (empty($date)) {
        dol_print_error('', 'Error date parameter is empty');
    }

    $date            = dol_stringtotime(dol_print_date($date, 'standard'));
    $isPublicHoliday = num_public_holiday($date, dol_time_plus_duree($date, 1, 'd'));

    return !$isPublicHoliday;
}

/**
 * Get the public holiday task: the absence task that justifies a public holiday on the timesheet.
 *
 * @return int Task ID, 0 when the task is not set up or does not exist anymore.
 */
function dolisirh_get_public_holiday_task_id(): int
{
    global $db;

    static $existingTaskIDs = [];

    $taskID = getDolGlobalInt('DOLISIRH_PUBLIC_HOLIDAY_TASK');
    if ($taskID <= 0) {
        return 0;
    }

    if (!isset($existingTaskIDs[$taskID])) {
        $resql                    = $db->query('SELECT rowid FROM ' . MAIN_DB_PREFIX . 'projet_task WHERE rowid = ' . $taskID);
        $existingTaskIDs[$taskID] = ($resql && $db->num_rows($resql) > 0) ? $taskID : 0;
    }

    return $existingTaskIDs[$taskID];
}

/**
 * Does a public holiday count in the expected time?
 *
 * Rule of the timesheet document (#739): a public holiday keeps the working hours of its weekday and is justified by
 * the public holiday task. Without that task, nothing can justify it: it is left out of the expected time.
 *
 * @return bool True when a public holiday counts in the expected time.
 */
function dolisirh_is_public_holiday_expected(): bool
{
    return dolisirh_get_public_holiday_task_id() > 0;
}

/**
 * Is a weekday a public holiday?
 *
 * num_public_holiday() reads its dates in GMT and counts the week-end too: the day is read in GMT, week-end left out.
 *
 * @param  int  $date Day, in the timezone of the server.
 * @return bool       True on a public holiday from Monday to Friday.
 */
function dolisirh_is_public_holiday(int $date): bool
{
    if ((int) date('N', $date) >= 6) {
        return false;
    }

    $gmtDay = dol_mktime(0, 0, 0, (int) date('m', $date), (int) date('d', $date), (int) date('Y', $date), 'gmt');

    return num_public_holiday($gmtDay, $gmtDay + 86400) > 0;
}

/**
 * Get the availability of a user on a day, as the expected time counts it.
 *
 * Entry of the $daysAvailable arrays: morning and afternoon available on a working day, otherwise the reason of the
 * day off (week_end, public_holiday), which greys the day. A public holiday stays a day off, greyed, but keeps the
 * working hours of its weekday in the expected time ('expected') when the public holiday task can justify it.
 *
 * @param  int   $date   Day, at midnight in the timezone of the server.
 * @param  int   $userID User ID. Their approved leaves do not change the availability: see is_day_available().
 * @return array         Availability of the day.
 */
function dolisirh_get_day_availability(int $date, int $userID): array
{
    if (is_day_available($date)) {
        return ['morning' => 1, 'afternoon' => 1];
    }

    if ((int) date('N', $date) >= 6) {
        return ['morning' => false, 'afternoon' => false, 'morning_reason' => 'week_end', 'afternoon_reason' => 'week_end'];
    }

    $dayOff = ['morning' => false, 'afternoon' => false, 'morning_reason' => 'public_holiday', 'afternoon_reason' => 'public_holiday'];
    if (dolisirh_is_public_holiday_expected() && dolisirh_is_public_holiday($date)) {
        $dayOff['expected'] = true;
    }

    return $dayOff;
}

/**
 * Does a day count in the expected time?
 *
 * @param  array $dayAvailability Availability of the day, from dolisirh_get_day_availability().
 * @return bool                   True on a working day, or on a public holiday that keeps its working hours.
 */
function dolisirh_is_day_expected(array $dayAvailability): bool
{
    return (!empty($dayAvailability['morning']) && !empty($dayAvailability['afternoon'])) || !empty($dayAvailability['expected']);
}

/**
 * Get task progress css class.
 *
 * @param  float $progress Progress of the task.
 * @return string          CSS class.
 */
function get_task_progress_color_class(float $progress): string
{
    switch ($progress) {
        case $progress < 50 :
            return 'progress-green';
        case $progress < 99 :
            return 'progress-yellow';
        default :
            return 'progress-red';
    }
}

/**
 * Function to return number of days between two dates (date must be UTC date !).
 * Example: 2012-01-01 2012-01-02 => 1 if lastday = 0, 2 if lastday = 1.
 *
 * @param   int $timestampStart Timestamp start UTC.
 * @param   int $timestampEnd   Timestamp end UTC.
 * @param   int $lastDay        Last day is included, 0: no, 1:yes.
 * @return  int $daysNumber     Number of days.
 * @seealso num_between_day(), num_public_holiday()
 */
function dolisirh_num_between_days(int $timestampStart, int $timestampEnd, int $lastDay = 0): int
{
    $daysNumber = 0;
    if ($timestampStart <= $timestampEnd) {
        $daysNumber = (int) round(($timestampEnd - $timestampStart) / (60 * 60 * 24)) + 1 - abs($lastDay - 1);
    }

    return $daysNumber;
}

/**
 * Add days to a date in the server timezone.
 *
 * Depuis Dolibarr 23, dol_time_plus_duree() ajoute les jours en UTC : parti d'un minuit du serveur,
 * le resultat tombe a 23:00 la veille une fois le changement d'heure franchi, et ne correspond
 * plus aux dates lues par jdate(). Le parametre $tz du coeur n'existe pas sur toutes les 23.x.
 *
 * @param  int $timestamp Timestamp in the server timezone, as returned by dol_mktime() or jdate().
 * @param  int $days      Number of days to add, may be negative.
 * @return int            Same time of day, $days days later in the server timezone.
 */
function dolisirh_add_days(int $timestamp, int $days): int
{
    $date = new DateTime('@' . $timestamp);
    $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
    $date->modify(($days < 0 ? '' : '+') . $days . ' day');

    return $date->getTimestamp();
}

/**
 * Get all HR project tasks info.
 *
 * @return array
 */
function get_hr_project_tasks(): array
{
    return [
        [
            'name'     => 'Holidays',
            'code'     => 'DOLISIRH_HOLIDAYS_TASK',
            'type'     => 'no_work_time',
            'position' => 1
        ],
        [
            'name'     => 'PaidHolidays',
            'code'     => 'DOLISIRH_PAID_HOLIDAYS_TASK',
            'type'     => 'no_work_time',
            'position' => 10
        ],
        [
            'name'     => 'SickLeave',
            'code'     => 'DOLISIRH_SICK_LEAVE_TASK',
            'type'     => 'no_work_time',
            'position' => 40
        ],
        [
            'name'     => 'PublicHoliday',
            'code'     => 'DOLISIRH_PUBLIC_HOLIDAY_TASK',
            'type'     => 'no_work_time',
            'position' => 30
        ],
        [
            'name'     => 'RTT',
            'code'     => 'DOLISIRH_RTT_TASK',
            'type'     => 'no_work_time',
            'position' => 20
        ],
        [
            'name' => 'Recovery',
            'code' => 'DOLISIRH_RECOVERY_TASK',
            'type' => 'no_work_time'
        ],
        [
            'name' => 'CompensatoryRest',
            'code' => 'DOLISIRH_COMPENSATORY_REST_TASK',
            'type' => 'no_work_time'
        ],
        [
            'name' => 'ExceptionalAbsence',
            'code' => 'DOLISIRH_EXCEPTIONAL_ABSENCE_TASK',
            'type' => 'no_work_time'
        ],
        [
            'name' => 'EffectiveWorkTime',
            'code' => 'DOLISIRH_EFFECTIVE_WORK_TIME_TASK',
            'type' => 'work_time'
        ],
        [
            'name' => 'ProfessionalTraining',
            'code' => 'DOLISIRH_PROFESSIONAL_TRAINING_TASK',
            'type' => 'work_time'
        ],
        [
            'name' => 'BusinessTravelMission',
            'code' => 'DOLISIRH_BUSINESS_TRAVEL_MISSION_TASK',
            'type' => 'work_time'
        ],
        [
            'name' => 'CSEDelegation',
            'code' => 'DOLISIRH_CSE_DELEGATION_TASK',
            'type' => 'work_time'
        ]
    ];
}

/**
 * Get the ID of the HR project tasks set in the module setup.
 *
 * @param  string $type Type of the tasks, work_time or no_work_time, empty for both.
 * @return int[]        Tasks ID.
 */
function get_hr_project_task_ids(string $type = ''): array
{
    $taskIDs = [];
    foreach (get_hr_project_tasks() as $hrProjectTask) {
        if ((empty($type) || ($hrProjectTask['type'] ?? '') === $type) && getDolGlobalInt($hrProjectTask['code']) > 0) {
            $taskIDs[] = getDolGlobalInt($hrProjectTask['code']);
        }
    }

    return array_values(array_unique($taskIDs));
}

/**
 * Get the tasks whose time spent the timesheet compares to the working hours.
 *
 * Since the HR project 2.0.0, presence and absence are clocked on the HR project tasks: the timesheet counts them
 * only, the time spent on the other projects tasks is analytic.
 * The time spent screens do the opposite: projects tasks and absences, without the work time tasks.
 * Without any work time task set up (HR project 1.x), the presence is clocked on the projects tasks: no filter.
 *
 * @return int[] Tasks ID, empty for all tasks.
 */
function get_timesheet_task_ids(): array
{
    if (empty(get_hr_project_task_ids('work_time'))) {
        return [];
    }

    return get_hr_project_task_ids();
}

/**
 * Get all product or service for timesheet line.
 *
 * @return array
 */
function get_timesheet_product_service(): array
{
    return [
        [
            'name' => 'MealTicket',
            'code' => 'DOLISIRH_MEAL_TICKET',
            'type' => 0
        ],
        [
            'name' => 'JourneySubscription',
            'code' => 'DOLISIRH_JOURNEY_SUBSCRIPTION',
            'type' => 1
        ],
        [
            'name' => '13thMonthBonus',
            'code' => 'DOLISIRH_13TH_MONTH_BONUS',
            'type' => 1
        ],
        [
            'name' => 'SpecialBonus',
            'code' => 'DOLISIRH_SPECIAL_BONUS',
            'type' => 1
        ],
        [
            'name' => 'MealBaskets',
            'code' => 'DOLISIRH_MEAL_BASKETS',
            'type' => 1
        ],
        [
            'name' => 'TeleworkingPackage',
            'code' => 'DOLISIRH_TELEWORKING_PACKAGE',
            'type' => 1
        ]
    ];
}
