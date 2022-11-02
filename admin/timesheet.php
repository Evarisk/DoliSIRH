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
$object = new TimeSheet($db);

// Access control
if (!$user->admin) accessforbidden();

/*
 * View
 */

// Initialize view objects
$form = new Form($db);

$help_url = 'FR:Module_DoliSIRH';
$title    = $langs->trans("TimeSheet");
$morejs   = array("/dolisirh/js/dolisirh.js.php");
$morecss  = array("/dolisirh/css/dolisirh.css");

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss);

// Subheader
$linkback = '<a href="'.($backtopage ?: DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($title, $linkback, 'object_'.$object->picto);

// Configuration header
$head = dolisirhAdminPrepareHead();
print dol_get_fiche_head($head, 'timesheet', $title, -1, 'dolisirh_red@dolisirh');

print load_fiche_titre($langs->trans("TimeSheetManagement"), '', 'object_'.$object->picto);
print '<hr>';

/*
 *  Numbering module TimeSheet
 */

print load_fiche_titre($langs->trans("DoliSIRHTimeSheetNumberingModule"), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Name").'</td>';
print '<td>'.$langs->trans("Description").'</td>';
print '<td class="nowrap">'.$langs->trans("Example").'</td>';
print '<td class="center">'.$langs->trans("Status").'</td>';
print '<td class="center">'.$langs->trans("ShortInfo").'</td>';
print '</tr>';

clearstatcache();

$dir = dol_buildpath("/custom/dolisirh/core/modules/dolisirh/timesheet/");
if (is_dir($dir)) {
    $handle = opendir($dir);
    if (is_resource($handle)) {
        while (($file = readdir($handle)) !== false ) {
            if (!is_dir($dir.$file) || (substr($file, 0, 1) <> '.' && substr($file, 0, 3) <> 'CVS')) {
                $filebis = $file;

                $classname = preg_replace('/\.php$/', '', $file);
                $classname = preg_replace('/-.*$/', '', $classname);

                if (!class_exists($classname) && is_readable($dir.$filebis) && (preg_match('/mod_/', $filebis) || preg_match('/mod_/', $classname)) && substr($filebis, dol_strlen($filebis) - 3, 3) == 'php') {
                    // Charging the numbering class
                    require_once $dir.$filebis;

                    $module = new $classname($db);

                    if ($module->isEnabled()) {
                        print '<tr class="oddeven"><td>';
                        print $langs->trans($module->name);
                        print "</td><td>";
                        print $module->info();
                        print '</td>';

                        // Show example of numbering module
                        print '<td class="nowrap">';
                        $tmp = $module->getExample();
                        if (preg_match('/^Error/', $tmp)) print '<div class="error">'.$langs->trans($tmp).'</div>';
                        elseif ($tmp == 'NotConfigured') print $langs->trans($tmp);
                        else print $tmp;
                        print '</td>';

                        print '<td class="center">';
                        $confType = 'DOLISIRH_TIMESHEET_ADDON';
                        if ($conf->global->$confType == $file || $conf->global->$confType.'.php' == $file) {
                            print img_picto($langs->trans("Activated"), 'switch_on');
                        }
                        else {
                            print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=setmod&value='.preg_replace('/\.php$/', '', $file).'&const='.$module->scandir.'&label='.urlencode($module->name).'&token='.newToken().'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
                        }
                        print '</td>';

                        // Example for timesheet
                        $htmltooltip = '' . $langs->trans("Version") . ': <b>' . $module->getVersion() . '</b><br>';
                        $nextval = $module->getNextValue($object);
                        if ("$nextval" != $langs->trans("NotAvailable")) {  // Keep " on nextval
                            $htmltooltip .= $langs->trans("NextValue").': ';
                            if ($nextval) {
                                if (preg_match('/^Error/', $nextval) || $nextval == 'NotConfigured')
                                    $nextval = $langs->trans($nextval);
                                $htmltooltip .= $nextval.'<br>';
                            } else {
                                $htmltooltip .= $langs->trans($module->error).'<br>';
                            }
                        }

                        print '<td class="center">';
                        print $form->textwithpicto('', $htmltooltip, 1, 0);
                        if ($conf->global->$confType.'.php' == $file) { // If module is the one used, we show existing errors
                            if (!empty($module->error)) dol_htmloutput_mesg($module->error, '', 'error', 1);
                        }
                        print '</td>';
                        print "</tr>";
                    }
                }
            }
        }
        closedir($handle);
    }
}
print '</table>';

//Time spent
print load_fiche_titre($langs->transnoentities("TimeSheetData"), '', '');

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
