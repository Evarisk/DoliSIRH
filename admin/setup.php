<?php
/* Copyright (C) 2023 EVARISK <dev@evarisk.com>
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
 * \file    admin/setup.php
 * \ingroup dolisirh
 * \brief   DoliSIRH setup page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";

require_once '../lib/dolisirh.lib.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Translations
$langs->loadLangs(array("admin", "dolisirh@dolisirh"));

// Get parameters
$action = GETPOST('action', 'alpha');
$value  = GETPOST('value', 'alpha');

$arrayofparameters = array(
    'DOLISIRH_DEFAUT_TICKET_TIME' => array('css' => 'minwidth200', 'enabled' => 1),
);

// Access control
$permissiontoread = $user->rights->dolisirh->adminpage->read;
if (empty($conf->dolisirh->enabled)) accessforbidden();
if (!$permissiontoread) accessforbidden();

/*
 * Actions
 */

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

/*
 * Actions
 */

if (GETPOST('HRProjectSet', 'alpha')) {
    if ($conf->global->DOLISIRH_HR_PROJECT_SET == 0) {
        require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
        require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
        require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
        require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
        require_once DOL_DOCUMENT_ROOT . '/core/modules/project/mod_project_simple.php';

        $project = new Project($db);
        $usertmp = new User($db);

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
            $allusers = $usertmp->get_full_tree(0, 'u.employee = 1 AND u.fk_soc IS NULL AND u.statut = 1');
            if (!empty($allusers) && is_array($allusers)) {
                foreach ($allusers as $usersingle) {
                    $project->add_contact($usersingle['id'], 161, 'internal');
                }
            }

            $task       = new Task($db);
            $defaultref = '';
            $obj        = empty($conf->global->PROJECT_TASK_ADDON) ? 'mod_task_simple' : $conf->global->PROJECT_TASK_ADDON;

            if (!empty($conf->global->PROJECT_TASK_ADDON) && is_readable(DOL_DOCUMENT_ROOT . "/core/modules/project/task/" . $conf->global->PROJECT_TASK_ADDON . ".php")) {
                require_once DOL_DOCUMENT_ROOT . "/core/modules/project/task/" . $conf->global->PROJECT_TASK_ADDON . '.php';
                $modTask = new $obj;
                $defaultref = $modTask->getNextValue('', null);
            }

            $task->fk_project = $result;
            $task->ref        = $defaultref;
            $task->label      = $langs->transnoentities('Holidays');
            $task->date_c     = dol_now();
            $holidaysTaskID   = $task->create($user);

            dolibarr_set_const($db, 'DOLISIRH_HOLIDAYS_TASK', $holidaysTaskID, 'integer', 0, '', $conf->entity);

            $task->fk_project   = $result;
            $task->ref          = $modTask->getNextValue('', null);;
            $task->label        = $langs->transnoentities('PaidHolidays');
            $task->date_c       = dol_now();
            $paidHolidaysTaskID = $task->create($user);

            dolibarr_set_const($db, 'DOLISIRH_PAID_HOLIDAYS_TASK', $paidHolidaysTaskID, 'integer', 0, '', $conf->entity);

            $task->fk_project   = $result;
            $task->ref          = $modTask->getNextValue('', null);;
            $task->label        = $langs->transnoentities('SickLeave');
            $task->date_c       = dol_now();
            $sickLeaveTaskID    = $task->create($user);

            dolibarr_set_const($db, 'DOLISIRH_SICK_LEAVE_TASK', $sickLeaveTaskID, 'integer', 0, '', $conf->entity);

            $task->fk_project    = $result;
            $task->ref           = $modTask->getNextValue('', null);;
            $task->label         = $langs->transnoentities('PublicHoliday');
            $task->date_c        = dol_now();
            $publicHolidayTaskID = $task->create($user);

            dolibarr_set_const($db, 'DOLISIRH_PUBLIC_HOLIDAY_TASK', $publicHolidayTaskID, 'integer', 0, '', $conf->entity);

            $task->fk_project = $result;
            $task->ref        = $modTask->getNextValue('', null);;
            $task->label      = $langs->trans('RTT');
            $task->date_c     = dol_now();
            $RTTTaskID        = $task->create($user);

            dolibarr_set_const($db, 'DOLISIRH_RTT_TASK', $RTTTaskID, 'integer', 0, '', $conf->entity);

            $task->fk_project      = $result;
            $task->ref             = $modTask->getNextValue('InternalMeeting', null);;
            $task->label           = $langs->transnoentities('InternalMeeting');
            $task->date_c          = dol_now();
            $internalMeetingTaskID = $task->create($user);

            dolibarr_set_const($db, 'DOLISIRH_INTERNAL_MEETING_TASK', $internalMeetingTaskID, 'integer', 0, '', $conf->entity);

            $task->fk_project       = $result;
            $task->ref              = $modTask->getNextValue('', null);;
            $task->label            = $langs->trans('InternalTraining');
            $task->date_c           = dol_now();
            $internalTrainingTaskID = $task->create($user);

            dolibarr_set_const($db, 'DOLISIRH_INTERNAL_TRAINING_TASK', $internalTrainingTaskID, 'integer', 0, '', $conf->entity);

            $task->fk_project       = $result;
            $task->ref              = $modTask->getNextValue('', null);;
            $task->label            = $langs->trans('ExternalTraining');
            $task->date_c           = dol_now();
            $externalTrainingTaskID = $task->create($user);

            dolibarr_set_const($db, 'DOLISIRH_EXTERNAL_TRAINING_TASK', $externalTrainingTaskID, 'integer', 0, '', $conf->entity);

            $task->fk_project            = $result;
            $task->ref                   = $modTask->getNextValue('', null);;
            $task->label                 = $langs->transnoentities('AutomaticTimeSpending');
            $task->date_c                = dol_now();
            $automaticTimeSpendingTaskID = $task->create($user);

            dolibarr_set_const($db, 'DOLISIRH_AUTOMATIC_TIMESPENDING_TASK', $automaticTimeSpendingTaskID, 'integer', 0, '', $conf->entity);

            $task->fk_project    = $result;
            $task->ref           = $modTask->getNextValue('', null);;
            $task->label         = $langs->trans('Miscellaneous');
            $task->date_c        = dol_now();
            $miscellaneousTaskID = $task->create($user);

            dolibarr_set_const($db, 'DOLISIRH_MISCELLANEOUS_TASK', $miscellaneousTaskID, 'integer', 0, '', $conf->entity);

            $taskarray = $task->getTasksArray(0, 0, $result);

            if (!empty($allusers) && is_array($allusers)) {
                foreach ($allusers as $usersingle) {
                    if (is_array($taskarray) && !empty($taskarray)) {
                        foreach ($taskarray as $tasksingle) {
                            $tasksingle->add_contact($usersingle['id'], 181, 'internal');
                        }
                    }
                }
            }

            dolibarr_set_const($db, 'DOLISIRH_HR_PROJECT_SET', 1, 'integer', 0, '', $conf->entity);
        }
    }
}

if (GETPOST('ProductServiceSet', 'alpha')) {
    if ($conf->global->DOLISIRH_PRODUCT_SERVICE_SET == 0) {
        require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

        $product = new Product($db);

        $product->ref   = $langs->transnoentities('MealTicket');
        $product->label = $langs->transnoentities('MealTicket');
        $product->create($user);

        $product->ref   = $langs->transnoentities('JourneySubscription');
        $product->label = $langs->transnoentities('JourneySubscription');
        $product->type  = $product::TYPE_SERVICE;
        $product->create($user);

        $product->ref   = $langs->transnoentities('13thMonthBonus');
        $product->label = $langs->transnoentities('13thMonthBonus');
        $product->type  = $product::TYPE_SERVICE;
        $product->create($user);

        $product->ref   = $langs->transnoentities('SpecialBonus');
        $product->label = $langs->transnoentities('SpecialBonus');
        $product->type  = $product::TYPE_SERVICE;
        $product->create($user);

        dolibarr_set_const($db, 'DOLISIRH_PRODUCT_SERVICE_SET', 1, 'integer', 0, '', $conf->entity);
    }
}

if (GETPOST('BookmarkSet', 'alpha')) {
    if ($conf->global->DOLISIRH_TIMESPENT_BOOKMARK_SET == 0) {
        require_once DOL_DOCUMENT_ROOT . '/bookmarks/class/bookmark.class.php';

        $bookmark = new Bookmark($db);

        $bookmark->title    = $langs->transnoentities('TimeSpent');
        $bookmark->url      = DOL_URL_ROOT . '/custom/dolisirh/view/timespent_month.php';
        $bookmark->target   = 0;
        $bookmark->position = 10;
        $bookmark->create();

        dolibarr_set_const($db, 'DOLISIRH_TIMESPENT_BOOKMARK_SET', 1, 'integer', 0, '', $conf->entity);
    }
}

/*
 * View
 */

// Initialize view objects
$form = new Form($db);

$help_url = 'FR:Module_DoliSIRH';
$title    = $langs->trans("DoliSIRHSetup");
$morejs   = array("/dolisirh/js/dolisirh.js");
$morecss  = array("/dolisirh/css/dolisirh.css");

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss);

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1'.'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($title, $linkback, "dolisirh_color@dolisirh");

// Configuration header
$head = dolisirhAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $title, -1, 'dolisirh_color@dolisirh');

// Setup page goes here
echo '<span class="opacitymedium">'.$langs->trans("DoliSIRHSetupPage").'</span><br><br>';

if ($action == 'edit') {
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

	foreach ($arrayofparameters as $key => $val) {
		print '<tr class="oddeven"><td>';
		$tooltiphelp = (($langs->trans($key.'Tooltip') != $key.'Tooltip') ? $langs->trans($key.'Tooltip') : '');
		print $form->textwithpicto($langs->trans($key), $tooltiphelp);
		print '</td><td><input name="'.$key.'"  class="flat '.(empty($val['css']) ? 'minwidth200' : $val['css']).'" value="'.$conf->global->$key.'"></td></tr>';
	}
	print '</table>';

	print '<br><div class="center">';
	print '<input class="button" type="submit" value="'.$langs->trans("Save").'">';
	print '</div>';

	print '</form>';
	print '<br>';
} else {
	if (!empty($arrayofparameters)) {
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

		foreach ($arrayofparameters as $key => $val) {
			print '<tr class="oddeven"><td>';
			$tooltiphelp = (($langs->trans($key.'Tooltip') != $key.'Tooltip') ? $langs->trans($key.'Tooltip') : '');
			print $form->textwithpicto($langs->trans($key), $tooltiphelp);
			print '</td><td>'.$conf->global->$key.'</td></tr>';
		}

		print '</table>';

		print '<div class="tabsAction">';
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit">'.$langs->trans("Modify").'</a>';
		print '</div>';
	}
}

print load_fiche_titre($langs->transnoentities("SetupDefaultData"), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->transnoentities("Parameters") . '</td>';
print '<td>' . $langs->transnoentities("Description") . '</td>';
print '<td class="center">' . $langs->transnoentities("Status") . '</td>';
print '<td class="center">' . $langs->transnoentities("Action") . '</td>';
print '</tr>';

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="">';

// HR project set
print '<tr class="oddeven"><td>' . $langs->transnoentities("HRProjectSet") . '</td>';
print '<td>';
print $langs->transnoentities("HRProjectSetHelp");
print '</td>';
print '<td class="center">';
print $conf->global->DOLISIRH_HR_PROJECT_SET ? $langs->transnoentities('AlreadyCreated') : $langs->transnoentities('NotCreated');
print '</td>';
print '<td class="center">';
print $conf->global->DOLISIRH_HR_PROJECT_SET ? '<a type="" class=" butActionRefused" value="">'.$langs->transnoentities('Create') .'</a>' : '<input type="submit" class="button" name="HRProjectSet" value="'.$langs->transnoentities('Create') .'">';
print '</td>';
print '</tr>';

// Product/service set
print '<tr class="oddeven"><td>' . $langs->transnoentities("ProductServiceSet") . '</td>';
print '<td>';
print $langs->transnoentities("ProductServiceSetHelp");
print '</td>';
print '<td class="center">';
print $conf->global->DOLISIRH_PRODUCT_SERVICE_SET ? $langs->transnoentities('AlreadyCreated') : $langs->transnoentities('NotCreated');
print '</td>';
print '<td class="center">';
print $conf->global->DOLISIRH_PRODUCT_SERVICE_SET ? '<a type="" class=" butActionRefused" value="">'.$langs->transnoentities('Create') .'</a>' : '<input type="submit" class="button" name="ProductServiceSet" value="'.$langs->transnoentities('Create') .'">';
print '</td>';
print '</tr>';

// Bookmark set
print '<tr class="oddeven"><td>' . $langs->transnoentities("BookmarkSet") . '</td>';
print '<td>';
print $langs->transnoentities("BookmarkSetHelp");
print '</td>';
print '<td class="center">';
print $conf->global->DOLISIRH_TIMESPENT_BOOKMARK_SET ? $langs->transnoentities('AlreadyCreated') : $langs->transnoentities('NotCreated');
print '</td>';
print '<td class="center">';
print $conf->global->DOLISIRH_TIMESPENT_BOOKMARK_SET ? '<a type="" class=" butActionRefused" value="">'.$langs->transnoentities('Create') .'</a>' : '<input type="submit" class="button" name="BookmarkSet" value="'.$langs->transnoentities('Create') .'">';
print '</td>';
print '</tr>';

print '</form>';
print '</table>';

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
