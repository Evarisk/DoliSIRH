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
require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT . '/holiday/class/holiday.class.php';

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
 * Check if day is available.
 *
 * @param  int      $date   timestamp.
 * @param  int      $userID User ID.
 * @return bool             false = not availability | true = availability.
 */
function is_day_available(int $date, int $userID): bool
{
    global $db;

    if (empty($date)) {
        dol_print_error('', 'Error date parameter is empty');
    }

    $holiday = new Holiday($db);

    $statusOfHolidayToCheck = Holiday::STATUS_APPROVED;

    $date               = dol_stringtotime(dol_print_date($date, 'standard'));
    $isAvailableForUser = $holiday->verifDateHolidayForTimestamp($userID, $date, $statusOfHolidayToCheck);
    $isPublicHoliday    = num_public_holiday($date, dol_time_plus_duree($date, 1,'d'));

    return $isAvailableForUser && !$isPublicHoliday;
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
 * Get all HR project tasks info.
 *
 * @return array
 */
function get_hr_project_tasks(): array
{
    return [
        [
            'name' => 'Holidays',
            'code' => 'DOLISIRH_HOLIDAYS_TASK'
        ],
        [
            'name' => 'PaidHolidays',
            'code' => 'DOLISIRH_PAID_HOLIDAYS_TASK'
        ],
        [
            'name' => 'SickLeave',
            'code' => 'DOLISIRH_SICK_LEAVE_TASK'
        ],
        [
            'name' => 'PublicHoliday',
            'code' => 'DOLISIRH_PUBLIC_HOLIDAY_TASK'
        ],
        [
            'name' => 'RTT',
            'code' => 'DOLISIRH_RTT_TASK'
        ],
        [
            'name' => 'InternalMeeting',
            'code' => 'DOLISIRH_INTERNAL_MEETING_TASK'
        ],
        [
            'name' => 'InternalTraining',
            'code' => 'DOLISIRH_INTERNAL_TRAINING_TASK'
        ],
        [
            'name' => 'ExternalTraining',
            'code' => 'DOLISIRH_EXTERNAL_TRAINING_TASK'
        ],
        [
            'name' => 'AutomaticTimeSpending',
            'code' => 'DOLISIRH_AUTOMATIC_TIMESPENDING_TASK'
        ],
        [
            'name' => 'Miscellaneous',
            'code' => 'DOLISIRH_MISCELLANEOUS_TASK'
        ]
    ];
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
