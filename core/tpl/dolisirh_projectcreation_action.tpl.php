<?php
/* Copyright (C) 2022 EOXIA <dev@eoxia.com>
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

if ($conf->global->DOLISIRH_HR_PROJECT < 1) {
	require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
	require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
	require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
	require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
	require_once DOL_DOCUMENT_ROOT . '/core/modules/project/mod_project_simple.php';

	$project    = new Project($db);
	$usertmp    = new User($db);

	$obj = empty($conf->global->PROJECT_ADDON) ? 'mod_project_simple' : $conf->global->PROJECT_ADDON;

	if (!empty($conf->global->PROJECT_ADDON) && is_readable(DOL_DOCUMENT_ROOT . "/core/modules/project/" . $conf->global->PROJECT_ADDON . ".php")) {
		require_once DOL_DOCUMENT_ROOT . "/core/modules/project/" . $conf->global->PROJECT_ADDON . '.php';
		$modProject = new $obj;
		$projectRef = $modProject->getNextValue('', null);
	}

	$project->ref         = $projectRef;
	$project->title       = $langs->transnoentities('HumanResources') . ' - ' . $conf->global->MAIN_INFO_SOCIETE_NOM;
	$project->description = $langs->transnoentities('HRDescription');
	$project->date_c      = dol_now();
	$currentYear          = dol_print_date(dol_now(), '%Y');
	$fiscalMonthStart     = $conf->global->SOCIETE_FISCAL_MONTH_START;
	$startdate            = dol_mktime('0', '0', '0', $fiscalMonthStart ? $fiscalMonthStart : '1', '1', $currentYear);
	$project->date_start  = $startdate;

	$project->usage_task = 1;

	$startdateAddYear      = dol_time_plus_duree($startdate, 1, 'y');
	$startdateAddYearMonth = dol_time_plus_duree($startdateAddYear, -1, 'd');
	$enddate               = dol_print_date($startdateAddYearMonth, 'dayrfc');
	$project->date_end     = $enddate;
	$project->statut       = 1;

	$result = $project->create($user);

	if ($result > 0) {
		dolibarr_set_const($db, 'DOLISIRH_HR_PROJECT', $result, 'integer', 0, '', $conf->entity);
		$usertmp->fetchAll('', '', 0, 0, array('customsql' => 'fk_soc IS NULL'), 'AND', true);
		$liste_type_contacts = array_keys($project->listeTypeContacts());
		if (is_array($usertmp->users) && !empty($usertmp->users)) {
			foreach ($usertmp->users as $usersingle) {
				$test = $project->add_contact($usersingle->id, $liste_type_contacts[1], 'internal');
			}
		}

		$task = new Task($db);
		$defaultref = '';
		$obj = empty($conf->global->PROJECT_TASK_ADDON) ? 'mod_task_simple' : $conf->global->PROJECT_TASK_ADDON;

		if (!empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT . "/core/modules/project/task/" . $conf->global->PROJECT_TASK_ADDON . ".php")) {
			require_once DOL_DOCUMENT_ROOT . "/core/modules/project/task/" . $conf->global->PROJECT_TASK_ADDON . '.php';
			$modTask = new $obj;
			$defaultref = $modTask->getNextValue('', null);
		}

		$task->fk_project = $result;
		$task->ref = $defaultref;
		$task->label = $langs->transnoentities('Holidays');
		$task->date_c = dol_now();
		$task->create($user);

		$task->fk_project = $result;
		$task->ref = $modTask->getNextValue('', null);;
		$task->label = $langs->transnoentities('PaidHolidays');
		$task->date_c = dol_now();
		$task->create($user);

		$task->fk_project = $result;
		$task->ref = $modTask->getNextValue('', null);;
		$task->label = $langs->transnoentities('SickLeave');
		$task->date_c = dol_now();
		$task->create($user);

		$task->fk_project = $result;
		$task->ref = $modTask->getNextValue('', null);;
		$task->label = $langs->transnoentities('PublicHoliday');
		$task->date_c = dol_now();
		$task->create($user);

		$task->fk_project = $result;
		$task->ref = $modTask->getNextValue('', null);;
		$task->label = $langs->trans('RTT');
		$task->date_c = dol_now();
		$task->create($user);

		$taskarray = $task->getTasksArray(0, 0, $result);

		if (is_array($usertmp->users) && !empty($usertmp->users)) {
			foreach ($usertmp->users as $usersingle) {
				if (is_array($taskarray) && !empty($taskarray)) {
					foreach ($taskarray as $tasksingle) {
						$tasksingle->add_contact($usersingle->id, $liste_type_contacts[3], 'internal');
					}
				}
			}
		}

		dolibarr_set_const($db, 'DOLISIRH_RTT_TASK', 1, 'integer', 0, '', $conf->entity);
	}
}
