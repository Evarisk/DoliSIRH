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
 * \file    admin/project.php
 * \ingroup dolisirh
 * \brief   DoliSIRH project/task config page.
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . "/core/class/html.formother.class.php";
require_once DOL_DOCUMENT_ROOT . "/core/class/html.formprojet.class.php";

require_once '../lib/dolisirh.lib.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Translations
$langs->loadLangs(array("errors", "admin", "dolisirh@dolisirh"));

// Get parameters
$action = GETPOST('action', 'alpha');

// Access control
$permissiontoread = $user->rights->dolisirh->adminpage->read;
if (empty($conf->dolisirh->enabled)) accessforbidden();
if (!$permissiontoread) accessforbidden();

/*
 * Actions
 */

if ($action == 'update') {
	$HRProject                   = GETPOST('HRProject', 'int');
    $holidaysTaskID              = GETPOST('holidaysTaskID', 'int');
    $paidHolidaysTaskID          = GETPOST('paidHolidaysTaskID', 'int');
    $sickLeaveTaskID             = GETPOST('sickLeaveTaskID', 'int');
    $publicHolidayTaskID         = GETPOST('publicHolidayTaskID', 'int');
    $RTTTaskID                   = GETPOST('RTTTaskID', 'int');
    $internalMeetingTaskID       = GETPOST('internalMeetingTaskID', 'int');
    $internalTrainingTaskID      = GETPOST('internalTrainingTaskID', 'int');
    $externalTrainingTaskID      = GETPOST('externalTrainingTaskID', 'int');
    $automaticTimeSpendingTaskID = GETPOST('automaticTimeSpendingTaskID', 'int');
    $miscellaneousTaskID         = GETPOST('miscellaneousTaskID', 'int');

    if (!empty($HRProject)) {
        dolibarr_set_const($db, 'DOLISIRH_HR_PROJECT', $HRProject, 'integer', 0, '', $conf->entity);
    }
    if (!empty($holidaysTaskID)) {
        dolibarr_set_const($db, 'DOLISIRH_HOLIDAYS_TASK', $holidaysTaskID, 'integer', 0, '', $conf->entity);
    }
    if (!empty($paidHolidaysTaskID)) {
        dolibarr_set_const($db, 'DOLISIRH_PAID_HOLIDAYS_TASK', $paidHolidaysTaskID, 'integer', 0, '', $conf->entity);
    }
    if (!empty($sickLeaveTaskID)) {
        dolibarr_set_const($db, 'DOLISIRH_SICK_LEAVE_TASK', $sickLeaveTaskID, 'integer', 0, '', $conf->entity);
    }
    if (!empty($publicHolidayTaskID)) {
        dolibarr_set_const($db, 'DOLISIRH_PUBLIC_HOLIDAY_TASK', $publicHolidayTaskID, 'integer', 0, '', $conf->entity);
    }
    if (!empty($RTTTaskID)) {
        dolibarr_set_const($db, 'DOLISIRH_RTT_TASK', $RTTTaskID, 'integer', 0, '', $conf->entity);
    }
    if (!empty($internalMeetingTaskID)) {
        dolibarr_set_const($db, 'DOLISIRH_INTERNAL_MEETING_TASK', $internalMeetingTaskID, 'integer', 0, '', $conf->entity);
    }
    if (!empty($internalTrainingTaskID)) {
        dolibarr_set_const($db, 'DOLISIRH_INTERNAL_TRAINING_TASK', $internalTrainingTaskID, 'integer', 0, '', $conf->entity);
    }
    if (!empty($externalTrainingTaskID)) {
        dolibarr_set_const($db, 'DOLISIRH_EXTERNAL_TRAINING_TASK', $externalTrainingTaskID, 'integer', 0, '', $conf->entity);
    }
    if (!empty($automaticTimeSpendingTaskID)) {
        dolibarr_set_const($db, 'DOLISIRH_AUTOMATIC_TIMESPENDING_TASK', $automaticTimeSpendingTaskID, 'integer', 0, '', $conf->entity);
    }
    if (!empty($miscellaneousTaskID)) {
        dolibarr_set_const($db, 'DOLISIRH_MISCELLANEOUS_TASK', $miscellaneousTaskID, 'integer', 0, '', $conf->entity);
    }

    setEventMessage('SavedConfig');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($action == 'updateThemeColor') {
	$val = (implode(',', (colorStringToArray(GETPOST('DOLISIRH_EXCEEDED_TIME_SPENT_COLOR'), array()))));
	if ($val == '') {
		dolibarr_del_const($db, 'DOLISIRH_EXCEEDED_TIME_SPENT_COLOR', $conf->entity);
	} else {
		dolibarr_set_const($db, 'DOLISIRH_EXCEEDED_TIME_SPENT_COLOR', $val, 'chaine', 0, '', $conf->entity);
	}

	$val = (implode(',', (colorStringToArray(GETPOST('DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR'), array()))));
	if ($val == '') {
		dolibarr_del_const($db, 'DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR', $conf->entity);
	} else {
		dolibarr_set_const($db, 'DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR', $val, 'chaine', 0, '', $conf->entity);
	}

	$val = (implode(',', (colorStringToArray(GETPOST('DOLISIRH_PERFECT_TIME_SPENT_COLOR'), array()))));
	if ($val == '') {
		dolibarr_del_const($db, 'DOLISIRH_PERFECT_TIME_SPENT_COLOR', $conf->entity);
	} else {
		dolibarr_set_const($db, 'DOLISIRH_PERFECT_TIME_SPENT_COLOR', $val, 'chaine', 0, '', $conf->entity);
	}
}

/*
 * View
 */

// Initialize view objects
$form        = new Form($db);
$formother   = new FormOther($db);
$formproject = new FormProjets($db);

$help_url = 'FR:Module_DoliSIRH';
$title    = $langs->trans("ProjectsAndTasks");
$morejs   = array("/dolisirh/js/dolisirh.js");
$morecss  = array("/dolisirh/css/dolisirh.css");

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss);

// Subheader
print load_fiche_titre($title, '', 'dolisirh_red@dolisirh');

// Configuration header
$head = dolisirhAdminPrepareHead();
print dol_get_fiche_head($head, 'projecttasks', $title, -1, 'dolisirh_red@dolisirh');

// Project
print load_fiche_titre($langs->transnoentities("HRProject"), '', 'project');

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '" name="project_form">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->transnoentities("Name") . '</td>';
print '<td>' . $langs->transnoentities("ProjectOrTask") . '</td>';
print '</tr>';

// HRProject
print '<tr class="oddeven"><td><label for="HRProject">' . $langs->transnoentities('HRProject') . '</label></td><td>';
$formproject->select_projects(-1, (GETPOSTISSET('HRProject') ? GETPOST('HRProject', 'int') : $conf->global->DOLISIRH_HR_PROJECT), 'HRProject', 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 'maxwidth500 widthcentpercentminusx');
print ' <a href="' . DOL_URL_ROOT . '/projet/card.php?action=create&status=1&backtopage=' . urlencode(DOL_URL_ROOT . '/custom/dolisirh/admin/project.php?HRProject=&#95;&#95;ID&#95;&#95;') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->transnoentities('AddProject') . '"></span></a>';
print '</td></tr>';

// Holidays
print '<tr class="oddeven"><td><label for="Holidays">' . $langs->transnoentities('Holidays') . '</label></td><td>';
$formproject->selectTasks(-1, (GETPOSTISSET('holidaysTaskID') ? GETPOST('holidaysTaskID', 'int') : $conf->global->DOLISIRH_HOLIDAYS_TASK), 'holidaysTaskID', 0, 0, '1', 1, 0, 0, 'maxwidth500 widthcentpercentminusx', $conf->global->DOLISIRH_HR_PROJECT, '');
print ' <a href="' . DOL_URL_ROOT . '/projet/tasks.php?action=create&id=' . $conf->global->DOLISIRH_HR_PROJECT . '&backtopage=' . urlencode(DOL_URL_ROOT . '/custom/dolisirh/admin/project.php?holidaysTaskID=&#95;&#95;ID&#95;&#95;') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->transnoentities('AddTask') . '"></span></a>';
print '</td></tr>';

// PaidHolidays
print '<tr class="oddeven"><td><label for="PaidHolidays">' . $langs->transnoentities('PaidHolidays') . '</label></td><td>';
$formproject->selectTasks(-1, (GETPOSTISSET('paidHolidaysTaskID') ? GETPOST('paidHolidaysTaskID', 'int') : $conf->global->DOLISIRH_PAID_HOLIDAYS_TASK), 'paidHolidaysTaskID', 0, 0, '1', 1, 0, 0, 'maxwidth500 widthcentpercentminusx', $conf->global->DOLISIRH_HR_PROJECT, '');
print ' <a href="' . DOL_URL_ROOT . '/projet/tasks.php?action=create&id=' . $conf->global->DOLISIRH_HR_PROJECT . '&backtopage=' . urlencode(DOL_URL_ROOT . '/custom/dolisirh/admin/project.php?paidHolidaysTaskID=&#95;&#95;ID&#95;&#95;') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->transnoentities('AddTask') . '"></span></a>';
print '</td></tr>';

// SickLeave
print '<tr class="oddeven"><td><label for="SickLeave">' . $langs->transnoentities('SickLeave') . '</label></td><td>';
$formproject->selectTasks(-1, (GETPOSTISSET('sickLeaveTaskID') ? GETPOST('sickLeaveTaskID', 'int') : $conf->global->DOLISIRH_SICK_LEAVE_TASK), 'sickLeaveTaskID', 0, 0, '1', 1, 0, 0, 'maxwidth500 widthcentpercentminusx', $conf->global->DOLISIRH_HR_PROJECT, '');
print ' <a href="' . DOL_URL_ROOT . '/projet/tasks.php?action=create&id=' . $conf->global->DOLISIRH_HR_PROJECT . '&backtopage=' . urlencode(DOL_URL_ROOT . '/custom/dolisirh/admin/project.php?sickLeaveTaskID=&#95;&#95;ID&#95;&#95;') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->transnoentities('AddTask') . '"></span></a>';
print '</td></tr>';

// PublicHoliday
print '<tr class="oddeven"><td><label for="PublicHoliday">' . $langs->transnoentities('PublicHoliday') . '</label></td><td>';
$formproject->selectTasks(-1, (GETPOSTISSET('publicHolidayTaskID') ? GETPOST('publicHolidayTaskID', 'int') : $conf->global->DOLISIRH_PUBLIC_HOLIDAY_TASK), 'publicHolidayTaskID', 0, 0, '1', 1, 0, 0, 'maxwidth500 widthcentpercentminusx', $conf->global->DOLISIRH_HR_PROJECT, '');
print ' <a href="' . DOL_URL_ROOT . '/projet/tasks.php?action=create&id=' . $conf->global->DOLISIRH_HR_PROJECT . '&backtopage=' . urlencode(DOL_URL_ROOT . '/custom/dolisirh/admin/project.php?publicHolidayTaskID=&#95;&#95;ID&#95;&#95;') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->transnoentities('AddTask') . '"></span></a>';
print '</td></tr>';

// RTT
print '<tr class="oddeven"><td><label for="RTT">' . $langs->transnoentities('RTT') . '</label></td><td>';
$formproject->selectTasks(-1, (GETPOSTISSET('RTTTaskID') ? GETPOST('RTTTaskID', 'int') : $conf->global->DOLISIRH_RTT_TASK), 'RTTTaskID', 0, 0, '1', 1, 0, 0, 'maxwidth500 widthcentpercentminusx', $conf->global->DOLISIRH_HR_PROJECT, '');
print ' <a href="' . DOL_URL_ROOT . '/projet/tasks.php?action=create&id=' . $conf->global->DOLISIRH_HR_PROJECT . '&backtopage=' . urlencode(DOL_URL_ROOT . '/custom/dolisirh/admin/project.php?RTTTaskID=&#95;&#95;ID&#95;&#95;') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->transnoentities('AddTask') . '"></span></a>';
print '</td></tr>';

// InternalMeeting
print '<tr class="oddeven"><td><label for="InternalMeeting">' . $langs->transnoentities('InternalMeeting') . '</label></td><td>';
$formproject->selectTasks(-1, (GETPOSTISSET('internalMeetingTaskID') ? GETPOST('internalMeetingTaskID', 'int') : $conf->global->DOLISIRH_INTERNAL_MEETING_TASK), 'internalMeetingTaskID', 0, 0, '1', 1, 0, 0, 'maxwidth500 widthcentpercentminusx', $conf->global->DOLISIRH_HR_PROJECT, '');
print ' <a href="' . DOL_URL_ROOT . '/projet/tasks.php?action=create&id=' . $conf->global->DOLISIRH_HR_PROJECT . '&backtopage=' . urlencode(DOL_URL_ROOT . '/custom/dolisirh/admin/project.php?internalMeetingTaskID=&#95;&#95;ID&#95;&#95;') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->transnoentities('AddTask') . '"></span></a>';
print '</td></tr>';

// InternalTraining
print '<tr class="oddeven"><td><label for="InternalTraining">' . $langs->transnoentities('InternalTraining') . '</label></td><td>';
$formproject->selectTasks(-1, (GETPOSTISSET('internalTrainingTaskID') ? GETPOST('internalTrainingTaskID', 'int') : $conf->global->DOLISIRH_INTERNAL_TRAINING_TASK), 'internalTrainingTaskID', 0, 0, '1', 1, 0, 0, 'maxwidth500 widthcentpercentminusx', $conf->global->DOLISIRH_HR_PROJECT, '');
print ' <a href="' . DOL_URL_ROOT . '/projet/tasks.php?action=create&id=' . $conf->global->DOLISIRH_HR_PROJECT . '&backtopage=' . urlencode(DOL_URL_ROOT . '/custom/dolisirh/admin/project.php?internalTrainingTaskID=&#95;&#95;ID&#95;&#95;') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->transnoentities('AddTask') . '"></span></a>';
print '</td></tr>';

// ExternalTraining
print '<tr class="oddeven"><td><label for="ExternalTraining">' . $langs->transnoentities('ExternalTraining') . '</label></td><td>';
$formproject->selectTasks(-1, (GETPOSTISSET('externalTrainingTaskID') ? GETPOST('externalTrainingTaskID', 'int') : $conf->global->DOLISIRH_EXTERNAL_TRAINING_TASK), 'externalTrainingTaskID', 0, 0, '1', 1, 0, 0, 'maxwidth500 widthcentpercentminusx', $conf->global->DOLISIRH_HR_PROJECT, '');
print ' <a href="' . DOL_URL_ROOT . '/projet/tasks.php?action=create&id=' . $conf->global->DOLISIRH_HR_PROJECT . '&backtopage=' . urlencode(DOL_URL_ROOT . '/custom/dolisirh/admin/project.php?externalTrainingTaskID=&#95;&#95;ID&#95;&#95;') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->transnoentities('AddTask') . '"></span></a>';
print '</td></tr>';

// AutomaticTimeSpending
print '<tr class="oddeven"><td><label for="AutomaticTimeSpending">' . $langs->transnoentities('AutomaticTimeSpending') . '</label></td><td>';
$formproject->selectTasks(-1, (GETPOSTISSET('automaticTimeSpendingTaskID') ? GETPOST('automaticTimeSpendingTaskID', 'int') : $conf->global->DOLISIRH_AUTOMATIC_TIMESPENDING_TASK), 'automaticTimeSpendingTaskID', 0, 0, '1', 1, 0, 0, 'maxwidth500 widthcentpercentminusx', $conf->global->DOLISIRH_HR_PROJECT, '');
print ' <a href="' . DOL_URL_ROOT . '/projet/tasks.php?action=create&id=' . $conf->global->DOLISIRH_HR_PROJECT . '&backtopage=' . urlencode(DOL_URL_ROOT . '/custom/dolisirh/admin/project.php?automaticTimeSpendingTaskID=&#95;&#95;ID&#95;&#95;') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->transnoentities('AddTask') . '"></span></a>';
print '</td></tr>';

// Miscellaneous
print '<tr class="oddeven"><td><label for="Miscellaneous">' . $langs->transnoentities('Miscellaneous') . '</label></td><td>';
$formproject->selectTasks(-1, (GETPOSTISSET('miscellaneousTaskID') ? GETPOST('miscellaneousTaskID', 'int') : $conf->global->DOLISIRH_MISCELLANEOUS_TASK), 'miscellaneousTaskID', 0, 0, '1', 1, 0, 0, 'maxwidth500 widthcentpercentminusx', $conf->global->DOLISIRH_HR_PROJECT, '');
print ' <a href="' . DOL_URL_ROOT . '/projet/tasks.php?action=create&id=' . $conf->global->DOLISIRH_HR_PROJECT . '&backtopage=' . urlencode(DOL_URL_ROOT . '/custom/dolisirh/admin/project.php?miscellaneousTaskID=&#95;&#95;ID&#95;&#95;') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->transnoentities('AddTask') . '"></span></a>';
print '</td></tr>';

print '</table>';
print '<div class="tabsAction"><input type="submit" class="butAction" name="save" value="' . $langs->trans('Save') . '"></div>';
print '</form>';

//Time spent
print load_fiche_titre($langs->transnoentities("TimeSpent"), '', 'clock');

print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<td>' . $langs->transnoentities("Parameters") . '</td>';
print '<td>' . $langs->transnoentities("Description") . '</td>';
print '<td class="center">' . $langs->transnoentities("Status") . '</td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print $langs->transnoentities("SpendMoreTimeThanPlanned");
print '</td><td>';
print $langs->transnoentities("SpendMoreTimeThanPlannedDescription");
print '</td>';
print '<td class="center">';
print ajax_constantonoff('DOLISIRH_SPEND_MORE_TIME_THAN_PLANNED');
print '</td>';
print '</tr>';

print '</table>';

//Theme dashboard time spent
print load_fiche_titre($langs->transnoentities("ThemeDashboardTimeSpent"), '', 'clock');

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '" name="color_form">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="updateThemeColor">';
print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<td>' . $langs->transnoentities("Parameters") . '</td>';
print '<td>' . $langs->transnoentities("Value") . '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("ExceededTimeSpentColor").'</td>';
print '<td>';
print $formother->selectColor(colorArrayToHex(colorStringToArray((!empty($conf->global->DOLISIRH_EXCEEDED_TIME_SPENT_COLOR) ? $conf->global->DOLISIRH_EXCEEDED_TIME_SPENT_COLOR : ''), array()), ''), 'DOLISIRH_EXCEEDED_TIME_SPENT_COLOR', '', 1, '', '', 'dolisirhexceededtimespentcolor');
print '<span class="nowraponall opacitymedium">'.$langs->trans("Default").'</span>: <strong>#FF0000</strong>';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("NotExceededTimeSpentColor").'</td>';
print '<td>';
print $formother->selectColor(colorArrayToHex(colorStringToArray((!empty($conf->global->DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR) ? $conf->global->DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR : ''), array()), ''), 'DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR', '', 1, '', '', 'dolisirhnotexceededtimespentcolor');
print '<span class="nowraponall opacitymedium">'.$langs->trans("Default").'</span>: <strong>#FFA500</strong>';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("PerfectTimeSpentColor").'</td>';
print '<td>';
print $formother->selectColor(colorArrayToHex(colorStringToArray((!empty($conf->global->DOLISIRH_PERFECT_TIME_SPENT_COLOR) ? $conf->global->DOLISIRH_PERFECT_TIME_SPENT_COLOR : ''), array()), ''), 'DOLISIRH_PERFECT_TIME_SPENT_COLOR', '', 1, '', '', 'dolisirhperfecttimespentcolor');
print '<span class="nowraponall opacitymedium">'.$langs->trans("Default").'</span>: <strong>#008000</strong>';
print '</td>';
print '</tr>';

print '</table>';
print '<div class="tabsAction"><input type="submit" class="butAction" name="save" value="' . $langs->trans('Save') . '"></div>';
print '</form>';

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
