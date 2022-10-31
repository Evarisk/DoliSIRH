<?php
/* Copyright (C) 2022 EVARISK <dev@evarisk.com>
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
 *	\file       view/dolisirhtools.php
 *	\ingroup    dolisirh
 *	\brief      Tools page of dolisirh top menu
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

global $conf, $db, $langs, $user;

// Load translation files required by the page
$langs->loadLangs(array("dolisirh@dolisirh"));

// Parameters
$action = GETPOST('action', 'alpha');


// Initialize technical objects
$usertmp = new User($db);

// Security check
$permissiontoread = $user->rights->dolisirh->adminpage->read;

if (!$permissiontoread) accessforbidden();

/*
 * Actions
 */

if (GETPOST('dataUpdate', 'alpha')) {
    $allusers = $usertmp->get_full_tree(0, 'u.employee = 1 AND u.fk_soc IS NOT NULL');
    if (!empty($allusers) && is_array($allusers)) {
        foreach ($allusers as $usersingle) {
            $usertmp->fetch($usersingle['id']);
            $usertmp->employee = 0;
            $usertmp->update($user, 1);
        }
        setEventMessage($langs->trans('DataUpdateSaved'));
    } else {
        setEventMessage($langs->trans('DataUpdateNotSaved'));
    }
}

/*
 * View
 */

$help_url = 'FR:Module_DoliSIRH';
$morejs   = array("/dolisirh/js/dolisirh.js");
$morecss  = array("/dolisirh/css/dolisirh.css");

llxHeader("", $langs->trans("Tools"), $help_url, '', '', '', $morejs, $morecss);

print load_fiche_titre($langs->trans("Tools"), '', 'wrench');

if ($user->rights->dolisirh->adminpage->read) { ?>
    <div class="wpeo-notice notice-info">
        <div class="notice-content">
            <div class="notice-title"><strong><?php echo $langs->trans("DataUpdateUserHaveTodoTitle"); ?></strong></div>
            <div class="notice-subtitle">
                <?php
                $allusers = $usertmp->get_full_tree(0, 'u.employee = 1 AND u.fk_soc IS NOT NULL');
                if (!empty($allusers) && is_array($allusers)) {
                    $dataUpdateUser = count($allusers);
                } else {
                    $dataUpdateUser = 0;
                }
                print $langs->trans("DataUpdateUserAll") . '<br>';
                print $langs->trans("DataUpdateUserInternalNotEmployees",  0)  . '<br>';
                print $langs->trans("DataUpdateUserInternal",  3 - $dataUpdateUser)  . '<br>';
                print $langs->trans("DataUpdateUserExternal", $dataUpdateUser) ?>
            </div>
        </div>
    </div>

    <?php print load_fiche_titre($langs->trans("DataUpdateManagement"), '', '');

	print '<form class="data-update-from" name="DataUpdate" id="DataUpdate" action="' . $_SERVER["PHP_SELF"] . '" method="POST">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="action" value="">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>' . $langs->trans("Name") . '</td>';
	print '<td>' . $langs->trans("Description") . '</td>';
	print '<td class="center">' . $langs->trans("Action") . '</td>';
	print '</tr>';

	print '<tr class="oddeven"><td>';
	print $langs->trans('DataUpdateUser');
	print "</td><td>";
	print $langs->trans('DataUpdateUserDescription');
	print '</td>';

	print '<td class="center data-update">';
	print '<input type="submit" class="button reposition data-update" name="dataUpdate" value="' . $langs->trans("DataUpdate") . '">';
	print '</td>';
	print '</tr>';
	print '</table>';
	print '</form>';
}

// End of page
llxFooter();
$db->close();
