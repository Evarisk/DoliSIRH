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
function toggleTaskFavorite(int $taskID, int $userID): int
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
function isTaskFavorite(int $taskID, int $userID): int
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
function isDayAvailable(int $date, int $userID): bool
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
 * Sets object to given categories.
 *
 * Adds it to non-existing supplied categories.
 * Deletes object from existing categories not supplied (if remove_existing==true).
 * Existing categories are left untouch.
 *
 * @param int[]|int $categories Category ID or array of Categories IDs
 * @param string $type_categ Category type ('customer', 'supplier', 'website_page', ...) definied into const class Categorie type
 * @param boolean $remove_existing True: Remove existings categories from Object if not supplies by $categories, False: let them
 * @param CommonObject $object Object
 * @return    int                            <0 if KO, >0 if OK
 * @throws Exception
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
 * Get task progress css class.
 *
 * @param  float $progress Progress of the task.
 * @return string          CSS class.
 */
function getTaskProgressColorClass(float $progress): string
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
 *	Function to return number of days between two dates (date must be UTC date !)
 *  Example: 2012-01-01 2012-01-02 => 1 if lastday=0, 2 if lastday=1
 *
 *	@param	   int			$timestampStart     Timestamp start UTC
 *	@param	   int			$timestampEnd       Timestamp end UTC
 *	@param     int			$lastDay            Last day is included, 0: no, 1:yes
 *	@return    int								Number of days
 *  @seealso num_public_holiday(), num_open_day()
 */
function dolisirh_num_between_day($timestampStart, $timestampEnd, $lastDay = 0)
{
    if ($timestampStart <= $timestampEnd) {
        if ($lastDay == 1) {
            $bit = 0;
        } else {
            $bit = 1;
        }
        $daysNumber = (int) round(($timestampEnd - $timestampStart) / (60 * 60 * 24)) + 1 - $bit;
    }

    return $daysNumber;
}
