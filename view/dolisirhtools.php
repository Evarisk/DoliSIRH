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
if (file_exists('../../../main.inc.php')) {
    $res = include '../../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

global $conf, $db, $langs, $user;

// Load translation files required by the page
$langs->load('tools@dolisirh');

// Initialize technical objects
$usertmp = new User($db);

// Security check
$permissiontoread = $user->rights->dolisirh->adminpage->read;

if (!$permissiontoread) accessforbidden();

/*
 * Actions
 */

if (GETPOST('updateEmployeeExternalUsers', 'alpha')) {
    $employeeExternalUsers = $usertmp->get_full_tree(0, 'u.employee = 1 AND u.fk_soc IS NOT NULL');
    if (!empty($employeeExternalUsers) && is_array($employeeExternalUsers)) {
        foreach ($employeeExternalUsers as $userSingle) {
            $usertmp->fetch($userSingle['id']);
            $usertmp->employee = 0;
            $usertmp->update($user, 1);
        }
        setEventMessage($langs->trans('UpdateEmployeeExternalUsersSaved'));
    } else {
        setEventMessage($langs->trans('UpdateEmployeeExternalUsersNotSaved'));
    }
}

/*
 * View
 */

$help_url = 'FR:Module_DoliSIRH';
$morejs   = ['/dolisirh/js/dolisirh.js'];
$morecss  = ['/dolisirh/css/dolisirh.css'];

llxHeader('', $langs->trans('Tools'), $help_url, '', '', '', $morejs, $morecss);

print load_fiche_titre($langs->trans('Tools'), '', 'wrench');

if ($user->rights->dolisirh->adminpage->read) { ?>
    <div class="wpeo-notice notice-info">
        <div class="notice-content">
            <div class="notice-title"><strong><?php echo $langs->trans('UserStatusTitle'); ?></strong></div>
            <div class="notice-subtitle">
                <?php
                $usersData = [
                    'AllUsers'                 => $usertmp->get_full_tree(),
                    'InternalUsersNotEmployee' => $usertmp->get_full_tree(0, 'u.employee = 0 AND u.fk_soc IS NULL'),
                    'EmployeeInternalUsers'    => $usertmp->get_full_tree(0, 'u.employee = 1 AND u.fk_soc IS NULL'),
                    'EmployeeExternalUsers'    => $usertmp->get_full_tree(0, 'u.employee = 1 AND u.fk_soc IS NOT NULL'),
                ];
                foreach ($usersData as $key => $userData) {
                    if (!empty($userData) && is_array($userData)) {
                        $countedUserData = count($userData);
                    } else {
                        $countedUserData = 0;
                    }
                    print $langs->trans($key, $countedUserData) . '<br>';
                } ?>
            </div>
        </div>
    </div>

    <?php print load_fiche_titre($langs->trans('DataUpdateManagement'), '', '');

	print '<form class="data-update-from" name="DataUpdate" id="DataUpdate" action="' . $_SERVER['PHP_SELF'] . '" method="POST">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>' . $langs->trans('Name') . '</td>';
	print '<td>' . $langs->trans('Description') . '</td>';
	print '<td class="center">' . $langs->trans('Action') . '</td>';
	print '</tr>';

	print '<tr class="oddeven"><td>';
	print $langs->trans('UpdateEmployeeExternalUsers');
	print '</td><td>';
	print $langs->trans('UpdateEmployeeExternalUsersDescription');
	print '</td>';

	print '<td class="center">';
    if ($user->rights->user->user->creer) {
        print '<input type="submit" class="button" name="updateEmployeeExternalUsers" value="' . $langs->trans('Validate') . '">';
    } else {
        print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('PermissionDenied')) . '">' . $langs->trans('Validate') . '</span>';
    }
	print '</td>';
	print '</tr>';
	print '</table>';
	print '</form>';
}

// End of page
llxFooter();
$db->close();