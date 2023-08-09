<?php
/* Copyright (C) 2023 EVARISK <dev@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *   	\file       view/workinghours_card.php
 *		\ingroup    dolisirh
 *		\brief      Page to view Working Hours
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if ( ! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if ( ! $res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) $res          = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
if ( ! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
// Try main.inc.php using relative path
if ( ! $res && file_exists("../../main.inc.php")) $res    = @include "../../main.inc.php";
if ( ! $res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if ( ! $res) die("Include of main fails");

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/usergroups.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';

require_once './../class/workinghours.class.php';

// Global variables definitions
global $db, $hookmanager, $langs, $user;

// Load translation files required by the page
$langs->loadLangs(array("dolisirh@dolisirh"));

// Parameters
$action     = (GETPOST('action', 'aZ09') ? GETPOST('action', 'aZ09') : 'view');
$backtopage = GETPOST('backtopage', 'alpha');

$socid                                          = GETPOST('socid', 'int') ? GETPOST('socid', 'int') : GETPOST('id', 'int');
if ($usertmp->socid) $socid                        = $usertmp->socid;
if (empty($socid) && $action == 'view') $action = 'create';

$usertmp = new User($db);

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('userworkinghours', 'globalcard'));

if ($action == 'view' && $usertmp->fetch($socid) <= 0) {
	$langs->load("errors");
	print($langs->trans('ErrorRecordNotFound'));
	exit;
}

// Security check
$permissiontoadd = $usertmp->rights->societe->creer;

/*
 * Actions
 */

$parameters = array();
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $usertmp, $action); // Note that $action and $usertmp may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (($action == 'update' && ! GETPOST("cancel", 'alpha')) || ($action == 'updateedit')) {
	$object               = new Workinghours($db);
	$object->element_type = $usertmp->element;
	$object->element_id   = GETPOST('id');
	$object->status       = 1;
	$object->fk_user_creat       = $user->id;
	$object->schedule_monday       = GETPOST('schedule_monday', 'string');
	$object->schedule_tuesday      = GETPOST('schedule_tuesday', 'string');
	$object->schedule_wednesday    = GETPOST('schedule_wednesday', 'string');
	$object->schedule_thursday     = GETPOST('schedule_thursday', 'string');
	$object->schedule_friday       = GETPOST('schedule_friday', 'string');
	$object->schedule_saturday     = GETPOST('schedule_saturday', 'string');
	$object->schedule_sunday       = GETPOST('schedule_sunday', 'string');

	$object->workinghours_monday       = GETPOST('workinghours_monday', 'integer');
	$object->workinghours_tuesday      = GETPOST('workinghours_tuesday', 'integer');
	$object->workinghours_wednesday    = GETPOST('workinghours_wednesday', 'integer');
	$object->workinghours_thursday     = GETPOST('workinghours_thursday', 'integer');
	$object->workinghours_friday       = GETPOST('workinghours_friday', 'integer');
	$object->workinghours_saturday     = GETPOST('workinghours_saturday', 'integer');
	$object->workinghours_sunday       = GETPOST('workinghours_sunday', 'integer');
	$result = $object->create($usertmp);
	if ($result > 0) {
		setEventMessages($langs->trans('UserWorkingHoursSaved'), null, 'mesgs');
        if (!empty($backtopage)) {
            header('Location: ' . $backtopage);
        }
	} else {
		setEventMessages($langs->trans('UserWorkingHoursSave'), null, 'error');
	}
}

/*
 *  View
 */

$object = new Workinghours($db);

$morewhere  = ' AND element_id = ' . GETPOST('id');
$morewhere .= ' AND element_type = ' . "'" . $usertmp->element . "'";
$morewhere .= ' AND status = 1';

$object->fetch(0, '', $morewhere);

if ($socid > 0 && empty($usertmp->id)) {
	$result = $usertmp->fetch($socid);
	if ($result <= 0) dol_print_error('', $usertmp->error);
}

$title = $langs->trans("User");
if ( ! empty($conf->global->MAIN_HTML_TITLE) && preg_match('/thirdpartynameonly/', $conf->global->MAIN_HTML_TITLE) && $usertmp->name) $title = $usertmp->name . " - " . $langs->trans('WorkingHours');
$help_url = 'FR:Module_DoliSIRH';

$morecss = array("/dolisirh/css/dolisirh.css");

llxHeader('', $title, $help_url, '', '', '', array(), $morecss);

if ( ! empty($usertmp->id)) $res = $usertmp->fetch_optionals();

// Object card
// ------------------------------------------------------------
$morehtmlref  = '<div class="refidno">';
$morehtmlref .= '</div>';
$head = user_prepare_head($usertmp);
print dol_get_fiche_head($head, 'workinghours', $langs->trans("User"), 0, 'company');
$linkback = '<a href="' . DOL_URL_ROOT . '/societe/list.php?restore_lastsearch_values=1">' . $langs->trans("BackToList") . '</a>';
dol_banner_tab($usertmp, 'socid', $linkback, ($usertmp->socid ? 0 : 1), 'rowid', 'nom');

print dol_get_fiche_end();

print load_fiche_titre($langs->trans('WorkingHours'), '', '');

//Show common fields

if ( ! is_object($form)) $form = new Form($db);

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '?id=' . GETPOST('id') . '" >';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="id" value="' . GETPOST('id') . '">';
if ($backtopage) {
    print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
}

print '<table class="noborder centpercent editmode">';

print '<tr class="liste_titre"><th class="titlefield wordbreak">' . $langs->trans("Day") . '</th><th style="width: 300px">' . $langs->trans("Schedules") . '</th><th>'. $langs->trans('WorkingHours(min)') .'</th></tr>' . "\n";

print '<tr class="oddeven"><td>';
print $form->textwithpicto($langs->trans("Monday"), '');
print '</td><td>';
print '<input name="schedule_monday" id="schedule_monday" class="minwidth100" value="' . ($object->schedule_monday ?: GETPOST("schedule_monday", 'alpha')) . '"' . (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '' : ' autofocus="autofocus"') . '>';
print $form->textwithpicto('', $langs->trans("OpeningHoursFormatDesc"));
print '</td>';

print '<td>';
print '<input type="number" name="workinghours_monday" id="workinghours_monday" class="minwidth100" value="' . ($object->workinghours_monday ?: GETPOST("workinghours_monday", 'alpha')) . '"' . (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '' : ' autofocus="autofocus"') . '>';
print $form->textwithpicto('', $langs->trans("WorkingHoursFormatDesc"));
print '</td>';
print '</tr>' . "\n";

print '<tr class="oddeven"><td>';
print $form->textwithpicto($langs->trans("Tuesday"), '');
print '</td><td>';
print '<input name="schedule_tuesday" id="schedule_tuesday" class="minwidth100" value="' . ($object->schedule_tuesday ?: GETPOST("schedule_tuesday", 'alpha')) . '"' . (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '' : ' autofocus="autofocus"') . '>';
print $form->textwithpicto('', $langs->trans("OpeningHoursFormatDesc"));
print '</td>';
print '<td>';
print '<input type="number" name="workinghours_tuesday" id="workinghours_tuesday" class="minwidth100" value="' . ($object->workinghours_tuesday ?: GETPOST("workinghours_tuesday", 'alpha')) . '"' . (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '' : ' autofocus="autofocus"') . '>';
print $form->textwithpicto('', $langs->trans("WorkingHoursFormatDesc"));
print '</td>';
print '</tr>' . "\n";

print '<tr class="oddeven"><td>';
print $form->textwithpicto($langs->trans("Wednesday"), '');
print '</td><td>';
print '<input name="schedule_wednesday" id="schedule_wednesday" class="minwidth100" value="' . ($object->schedule_wednesday ?: GETPOST("schedule_wednesday", 'alpha')) . '"' . (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '' : ' autofocus="autofocus"') . '>';
print $form->textwithpicto('', $langs->trans("OpeningHoursFormatDesc"));
print '</td>';
print '<td>';
print '<input type="number" name="workinghours_wednesday" id="workinghours_wednesday" class="minwidth100" value="' . ($object->workinghours_wednesday ?: GETPOST("workinghours_wednesday", 'alpha')) . '"' . (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '' : ' autofocus="autofocus"') . '>';
print $form->textwithpicto('', $langs->trans("WorkingHoursFormatDesc"));
print '</td>';
print '</tr>' . "\n";

print '<tr class="oddeven"><td>';
print $form->textwithpicto($langs->trans("Thursday"), '');
print '</td><td>';
print '<input name="schedule_thursday" id="schedule_thursday" class="minwidth100" value="' . ($object->schedule_thursday ?: GETPOST("schedule_thursday", 'alpha')) . '"' . (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '' : ' autofocus="autofocus"') . '>';
print $form->textwithpicto('', $langs->trans("OpeningHoursFormatDesc"));
print '</td>';
print '<td>';
print '<input type="number" name="workinghours_thursday" id="workinghours_thursday" class="minwidth100" value="' . ($object->workinghours_thursday ?: GETPOST("workinghours_thursday", 'alpha')) . '"' . (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '' : ' autofocus="autofocus"') . '>';
print $form->textwithpicto('', $langs->trans("WorkingHoursFormatDesc"));
print '</td>';
print '</tr>' . "\n";

print '<tr class="oddeven"><td>';
print $form->textwithpicto($langs->trans("Friday"), '');
print '</td><td>';
print '<input name="schedule_friday" id="schedule_friday" class="minwidth100" value="' . ($object->schedule_friday ?: GETPOST("schedule_friday", 'alpha')) . '"' . (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '' : ' autofocus="autofocus"') . '>';
print $form->textwithpicto('', $langs->trans("OpeningHoursFormatDesc"));
print '</td>';
print '<td>';
print '<input type="number" name="workinghours_friday" id="workinghours_friday" class="minwidth100" value="' . ($object->workinghours_friday ?: GETPOST("workinghours_friday", 'alpha')) . '"' . (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '' : ' autofocus="autofocus"') . '>';
print $form->textwithpicto('', $langs->trans("WorkingHoursFormatDesc"));
print '</td>';
print '</tr>' . "\n";

print '<tr class="oddeven"><td>';
print $form->textwithpicto($langs->trans("Saturday"), '');
print '</td><td>';
print '<input name="schedule_saturday" id="schedule_saturday" class="minwidth100" value="' . ($object->schedule_saturday ?: GETPOST("schedule_saturday", 'alpha')) . '"' . (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '' : ' autofocus="autofocus"') . '>';
print $form->textwithpicto('', $langs->trans("OpeningHoursFormatDesc"));
print '</td>';
print '<td>';
print '<input type="number" name="workinghours_saturday" id="workinghours_saturday" class="minwidth100" value="' . ($object->workinghours_saturday ?: GETPOST("workinghours_saturday", 'alpha')) . '"' . (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '' : ' autofocus="autofocus"') . '>';
print $form->textwithpicto('', $langs->trans("WorkingHoursFormatDesc"));
print '</td>';
print '</tr>' . "\n";

print '<tr class="oddeven"><td>';
print $form->textwithpicto($langs->trans("Sunday"), '');
print '</td><td>';
print '<input name="schedule_sunday" id="schedule_sunday" class="minwidth100" value="' . ($object->schedule_sunday ?: GETPOST("schedule_sunday", 'alpha')) . '"' . (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '' : ' autofocus="autofocus"') . '>';
print $form->textwithpicto('', $langs->trans("OpeningHoursFormatDesc"));
print '</td>';
print '<td>';
print '<input type="number" name="workinghours_sunday" id="workinghours_sunday" class="minwidth100" value="' . ($object->workinghours_sunday ?: GETPOST("workinghours_sunday", 'alpha')) . '"' . (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '' : ' autofocus="autofocus"') . '>';
print $form->textwithpicto('', $langs->trans("WorkingHoursFormatDesc"));
print '</td>';
print '</tr>' . "\n";

print '</table>';

// Buid current user hierarchy
if ($usertmp->fk_user > 0) {
	$usertmphierarchy = array($usertmp->fk_user);
	$usertmpboss = new User($object->db);
	$usertmpboss->fetch($usertmp->fk_user);
	while ($usertmpboss->fk_user > 0) {
		$usertmphierarchy[] = $usertmpboss->fk_user;
		$usertmpboss->fetch($usertmpboss->fk_user);
		// We do not want to loop between two users who would be each other bosses
		if (in_array($usertmpboss->id, $usertmphierarchy)) {
			break;
		}
	}
}

if (($user->rights->dolisirh->workinghours->myworkinghours && $usertmp->id == $user->id) || ($user->rights->dolisirh->workinghours->allworkinghours && in_array($user->id, $usertmphierarchy)) || $user->admin == 1){
    print '<br><div class="center">';
    print '<input type="submit" class="button" name="save" value="' . $langs->trans("Save") . '">';
    print '</div>';
    print '<br>';
}

print '</form>';

// End of page
llxFooter();
$db->close();
