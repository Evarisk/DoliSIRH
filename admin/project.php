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
	$HRProject = GETPOST('HRProject', 'none');
	$HRProject = explode('_', $HRProject);

	dolibarr_set_const($db, "DOLISIRH_HR_PROJECT", $HRProject[0], 'integer', 0, '', $conf->entity);
	setEventMessages($langs->transnoentities('TicketProjectUpdated'), array());
	header("Location: " . $_SERVER["PHP_SELF"]);
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
$morejs   = array("/dolisirh/js/dolisirh.js.php");
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
print '<td>' . $langs->transnoentities("SelectProject") . '</td>';
print '<td>' . $langs->transnoentities("Action") . '</td>';
print '</tr>';

if ( ! empty($conf->projet->enabled)) {
	$langs->load("projects");
	print '<tr class="oddeven"><td><label for="HRProject">' . $langs->transnoentities("HRProject") . '</label></td><td>';
	$formproject->select_projects(-1,  (GETPOST('projectid')) ? GETPOST('projectid') : $conf->global->DOLISIRH_HR_PROJECT, 'HRProject', 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 'maxwidth500');
	print ' <a href="' . DOL_URL_ROOT . '/projet/card.php?&action=create&status=1&backtopage=' . urlencode($_SERVER["PHP_SELF"] . '?action=create') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->transnoentities("AddProject") . '"></span></a>';
	print '<td><input type="submit" class="button" name="save" value="' . $langs->transnoentities("Save") . '">';
	print '</td></tr>';
}

print '</table>';
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

print '<div class="center">';
print '<input class="button button-save reposition" type="submit" name="submit" value="' . $langs->trans("Save") . '">';
print '</div>';

print '</form>';

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
