<?php
/* Copyright (C) 2023-2025 EVARISK <technique@evarisk.com>
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
 * \file    view/workinghours_card.php
 * \ingroup dolisirh
 * \brief   Page to view working hours
 */

// Load DoliSIRH environment
$found = false;
for ($i = 1; $i <= 2; $i++) {
    $filePath = __DIR__ . str_repeat('/..', $i) . '/dolisirh.main.inc.php';
    if (file_exists($filePath)) {
        require_once $filePath;
        $found = true;
        break;
    }
}
if (!$found) {
    die('Include of dolisirh main fails');
}

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/usergroups.lib.php';

// load DoliSIRH libraries
require_once __DIR__ . '/../class/workinghours.class.php';

// Global variables definitions
global $db, $hookmanager, $langs, $user;

// Load translation files required by the page.
saturne_load_langs();

// Get parameters
$id         = GETPOSTINT('id');
$action     = GETPOST('action', 'aZ09');
$backToPage = GETPOST('backtopage', 'alpha');

// Initialize technical objects
$object  = new Workinghours($db);
$userTmp = new User($db);

// Initialize view objects
$form = new Form($db);

$hookmanager->initHooks(['userworkinghours', 'globalcard']); // Note that conf->hooks_modules contains array

// Load object
$userTmp->fetch($id);
$moreWhere = ' AND element_id = ' . $id . ' AND element_type = "' . $user->element . '" AND status = 1';
$object->fetch(0, '', $moreWhere);

// Security check - Protection if external user

// Build current user hierarchy
$userTmpHierarchy = [];
if ($userTmp->fk_user > 0) {
    $userTmpHierarchy[] = $userTmp->fk_user;

    $userTmpBoss = new User($db);
    $userTmpBoss->fetch($userTmp->fk_user);
    while ($userTmpBoss->fk_user > 0) {
        $userTmpHierarchy[] = $userTmpBoss->fk_user;
        $userTmpBoss->fetch($userTmpBoss->fk_user);
        // We do not want to loop between two users who would be each other bosses
        if (in_array($userTmpBoss->id, $userTmpHierarchy)) {
            break;
        }
    }
}

$permissionToManageWorkingHours = $user->hasRight('dolisirh', $object->element, 'myworkinghours')
    || $user->hasRight('dolisirh', $object->element, 'allworkinghours')
    || in_array($user->id, $userTmpHierarchy)
    || $user->admin == 1;

/*
 * Actions
 */

$parameters = [];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($resHook)) {
    if ($action == 'save_working_hours' && !empty($permissionToManageWorkingHours)) {
        $object->element_type = $user->element;
        $object->element_id   = $id;
        $object->status       = 1;
        $dayOfWeek            = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach ($dayOfWeek as $day) {
            $object->{'schedule_' . $day}     = GETPOST('schedule_' . $day);
            $object->{'workinghours_' . $day} = GETPOSTINT('workinghours_' . $day);
        }

        $result = $object->create($user);
        if ($result > 0) {
            setEventMessages('UserWorkingHoursSaved', []);
        } else {
            setEventMessages('Error', [], 'error');
        }
        header('Location: ' . (!empty($backToPage) ? $backToPage : $_SERVER['PHP_SELF'] . '?id=' . $id));
        exit;
    }
}

/*
 *  View
 */

$title   = $langs->trans(ucfirst($object->element));
$helpUrl = 'FR:Module_DoliSIRH';

saturne_header(0, '', $title, $helpUrl);

$head = user_prepare_head($userTmp);
print dol_get_fiche_head($head, 'workinghours', $langs->trans('User'), 0, 'user');
$linkBack = '<a href="' . DOL_URL_ROOT . '/user/list.php?restore_lastsearch_values=1">' . $langs->trans('BackToList') . '</a>';
dol_banner_tab($userTmp, 'id', $linkBack, 1, 'rowid', 'nom');

print dol_get_fiche_end();

print load_fiche_titre($langs->trans('WorkingHours'), '', $object->picto);

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?id=' . $id . '" >';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="save_working_hours">';
if (!empty($backToPage)) {
    print '<input type="hidden" name="backtopage" value="' . $backToPage . '">';
}

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th class="maxwidth100">' . $langs->trans('Day') . '</th>';
print '<th class="maxwidth100">' . $langs->trans('Schedules') . '</th>';
print '<th class="maxwidth100">' . $langs->trans('WorkingHours(min)') . '</th>';
print '</tr>';

$dayOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
foreach ($dayOfWeek as $day) {
    print '<tr class="oddeven"><td class="maxwidth100">';
    print $langs->trans(ucfirst($day));
    print '</td><td>';
    print '<input name="schedule_' . $day . '" class="maxwidth100" value="' . ($object->{'schedule_' . $day} ?: GETPOST('schedule_' . $day, 'alpha')) . '">';
    print $form->textwithpicto('', $langs->trans('OpeningHoursFormatDesc'));
    print '</td><td>';
    print '<input type="number" name="workinghours_' . $day . '" class="maxwidth100" min="0" value="' . ($object->{'workinghours_' . $day} ?: GETPOST('workinghours_' . $day, 'alpha')) . '">';
    print $form->textwithpicto('', $langs->trans('WorkingHoursFormatDesc'));
    print '</td></tr>';
}

print '</table>';

if (!empty($permissionToManageWorkingHours)) {
    print $form->buttonsSaveCancel('Save', '');
}

print '</form>';

// End of page
llxFooter();
$db->close();
