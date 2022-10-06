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

/**
 * \file    admin/timesheet/timesheet.php
 * \ingroup dolisirh
 * \brief   DoliSIRH timesheet config page.
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
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) die("Include of main fails");

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";

require_once '../lib/dolisirh.lib.php';
require_once '../class/timesheet.class.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
$langs->loadLangs(array("admin", "dolisirh@dolisirh"));

// Get parameters
$action     = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

// Initialize objects
// Technical objets
$timesheet = new TimeSheet($db);

// View objects
$form = new Form($db);

// Access control
if (!$user->admin) accessforbidden();

/*
 * View
 */

$help_url = 'FR:Module_DoliSIRH';
$title    = $langs->trans("TimeSheet");
$morejs   = array("/dolisirh/js/dolisirh.js.php");
$morecss  = array("/dolisirh/css/dolisirh.css");

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss);

// Subheader
$linkback = '<a href="'.($backtopage ?: DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($title, $linkback, 'dolisirh@dolisirh');

// Configuration header
$head = dolisirhAdminPrepareHead();
print dol_get_fiche_head($head, 'timesheet', '', -1, "timesheet@dolisirh");

//Time spent
print load_fiche_titre($langs->transnoentities("TimeSheetData"), '', 'object_timesheet@dolisirh');

print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<td>' . $langs->transnoentities("Parameters") . '</td>';
print '<td>' . $langs->transnoentities("Description") . '</td>';
print '<td class="center">' . $langs->transnoentities("Status") . '</td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print $langs->trans('PrefillDate');
print "</td><td>";
print $langs->trans('PrefillDateDescription');
print '</td>';
print '<td class="center">';
print ajax_constantonoff('DOLISIRH_TIMESHEET_PREFILL_DATE');
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print $langs->trans('AddAttendantsConf');
print "</td><td>";
print $langs->trans('AddAttendantsDescription');
print '</td>';
print '<td class="center">';
print ajax_constantonoff('DOLISIRH_TIMESHEET_ADD_ATTENDANTS');
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print $langs->trans('CheckDateEnd');
print "</td><td>";
print $langs->trans('CheckDateEndDescription');
print '</td>';
print '<td class="center">';
print ajax_constantonoff('DOLISIRH_TIMESHEET_CHECK_DATE_END');
print '</td>';
print '</tr>';

print '</table>';

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
