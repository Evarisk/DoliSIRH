<?php
/* Copyright (C) 2021-2023 EVARISK <technique@evarisk.com>
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

// Load DoliSIRH environment.
if (file_exists('../dolisirh.main.inc.php')) {
    require_once __DIR__ . '/../dolisirh.main.inc.php';
} elseif (file_exists('../../dolisirh.main.inc.php')) {
    require_once __DIR__ . '/../../dolisirh.main.inc.php';
} else {
    die('Include of dolisirh main fails');
}

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';

// Load DoliSIRH libraries.
require_once __DIR__ . '/../lib/dolisirh_function.lib.php';

// Global variables definitions.
global $conf, $db, $langs, $user;

// Load translation files required by the page.
saturne_load_langs(['admin']);

// Initialize view objects.
$form        = new Form($db);
$formproject = new FormProjets($db);

// Get parameters.
$action     = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

$hrProjectTasks = get_hr_project_tasks();

// Security check - Protection if external user.
$permissiontoread = $user->rights->dolisirh->adminpage->read;
saturne_check_access($permissiontoread);

/*
 * Actions.
 */

if ($action == 'update') {
    $hrProject = GETPOST('hrProject', 'int');
    if ($hrProject > 0) {
        dolibarr_set_const($db, 'DOLISIRH_HR_PROJECT', $hrProject, 'integer', 0, '', $conf->entity);
    }

    foreach ($hrProjectTasks as $hrProjectTask) {
        $hrProjectTaskID = GETPOST($hrProjectTask['name'], 'int');
        if ($hrProjectTaskID > 0) {
            dolibarr_set_const($db, $hrProjectTask['code'], $hrProjectTaskID, 'integer', 0, '', $conf->entity);
        }
    }

    setEventMessage('SavedConfig');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($action == 'update_timespent') {
    $defaultTicketTimeSpent = GETPOST('DefaultTicketTimeSpent', 'int');
    if ($defaultTicketTimeSpent > -1) {
        dolibarr_set_const($db, 'DOLISIRH_DEFAUT_TICKET_TIME', $defaultTicketTimeSpent, 'integer', 0, '', $conf->entity);
    }

    setEventMessage('SavedConfig');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($action == 'update_theme_color') {
    $val = (implode(',', (colorStringToArray(GETPOST('DOLISIRH_EXCEEDED_TIME_SPENT_COLOR'), []))));
    if ($val == '') {
        dolibarr_del_const($db, 'DOLISIRH_EXCEEDED_TIME_SPENT_COLOR', $conf->entity);
    } else {
        dolibarr_set_const($db, 'DOLISIRH_EXCEEDED_TIME_SPENT_COLOR', $val, 'chaine', 0, '', $conf->entity);
    }

    $val = (implode(',', (colorStringToArray(GETPOST('DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR'), []))));
    if ($val == '') {
        dolibarr_del_const($db, 'DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR', $conf->entity);
    } else {
        dolibarr_set_const($db, 'DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR', $val, 'chaine', 0, '', $conf->entity);
    }

    $val = (implode(',', (colorStringToArray(GETPOST('DOLISIRH_PERFECT_TIME_SPENT_COLOR'), []))));
    if ($val == '') {
        dolibarr_del_const($db, 'DOLISIRH_PERFECT_TIME_SPENT_COLOR', $conf->entity);
    } else {
        dolibarr_set_const($db, 'DOLISIRH_PERFECT_TIME_SPENT_COLOR', $val, 'chaine', 0, '', $conf->entity);
    }

    setEventMessage('SavedConfig');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/*
 * View.
 */

$title    = $langs->trans('ProjectsAndTasks');
$help_url = 'FR:Module_DoliSIRH';

saturne_header(0,'', $title, $help_url);

// Subheader.
$linkback = '<a href="' . ($backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans('BackToModuleList') . '</a>';

print load_fiche_titre($title, $linkback, 'title_setup');

// Configuration header.
$head = dolisirh_admin_prepare_head();
print dol_get_fiche_head($head, 'projecttasks', $title, -1, 'dolisirh_color@dolisirh');

// Project.
print load_fiche_titre($langs->transnoentities('HRProject'), '', 'project');

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" name="project_form">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->transnoentities('Name') . '</td>';
print '<td>' . $langs->transnoentities('ProjectOrTask') . '</td>';
print '</tr>';

// HRProject.
print '<tr class="oddeven"><td><label for="hrProject">' . $langs->transnoentities('HRProject') . '</label></td><td>';
print img_picto('', 'project', 'class="pictofixedwidth"');
$formproject->select_projects(-1, (GETPOSTISSET('hrProject') ? GETPOST('hrProject', 'int') : $conf->global->DOLISIRH_HR_PROJECT), 'hrProject', 0, 0, 0, 0, 0, 0, 0, '', 0, 0, 'maxwidth500 widthcentpercentminusx');
print ' <a href="' . DOL_URL_ROOT . '/projet/card.php?action=create&status=1&backtopage=' . urlencode(DOL_URL_ROOT . '/custom/dolisirh/admin/project.php?HRProject=&#95;&#95;ID&#95;&#95;') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->transnoentities('AddProject') . '"></span></a>';
print '</td></tr>';

// HRProjectTasks.
foreach ($hrProjectTasks as $hrProjectTask) {
    print '<tr class="oddeven"><td><label for="' . $hrProjectTask['name'] . '">' . $langs->transnoentities($hrProjectTask['name']) . '</label></td><td>';
    print img_picto('', 'projecttask', 'class="pictofixedwidth"');
    $hrProjectTaskCode = $hrProjectTask['code'];
    $formproject->selectTasks(-1, (GETPOSTISSET($hrProjectTask['name']) ? GETPOST($hrProjectTask['name'], 'int') : $conf->global->$hrProjectTaskCode), $hrProjectTask['name'], 0, 0, '1', 1, 0, 0, 'maxwidth500 widthcentpercentminusx', $conf->global->DOLISIRH_HR_PROJECT, '');
    print ' <a href="' . DOL_URL_ROOT . '/projet/tasks.php?action=create&id=' . $conf->global->DOLISIRH_HR_PROJECT . '&backtopage=' . urlencode(DOL_URL_ROOT . '/custom/dolisirh/admin/project.php?' . $hrProjectTask['name'] . '=&#95;&#95;ID&#95;&#95;') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->transnoentities('AddTask') . '"></span></a>';
    print '</td></tr>';
}

print '</table>';
print '<div class="tabsAction"><input type="submit" class="butAction" name="save" value="' . $langs->trans('Save') . '"></div>';
print '</form>';

// Time spent.
print load_fiche_titre($langs->transnoentities('TimeSpent'), '', 'clock');

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" name="timespent_form">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update_timespent">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->transnoentities('Parameters') . '</td>';
print '<td>' . $langs->trans('Description') . '</td>';
print '<td class="center">' . $langs->trans('Status') . '</td>';
print '<td class="center">' . $langs->trans('Action') . '</td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print $langs->transnoentities('SpendMoreTimeThanPlanned');
print '</td><td>';
print $langs->transnoentities('SpendMoreTimeThanPlannedDescription');
print '</td>';
print '<td class="center">';
print ajax_constantonoff('DOLISIRH_SPEND_MORE_TIME_THAN_PLANNED');
print '</td><td></td></tr>';

print '<tr class="oddeven"><td><label for="DefaultTicketTimeSpent">' . $langs->trans('DefaultTicketTimeSpent') . '</label></td>';
print '<td>' . $langs->trans('DefaultTicketTimeSpentDescription') . '</td>';
print '<td class="center"><input type="number" min="0" name="DefaultTicketTimeSpent" value="' . $conf->global->DOLISIRH_DEFAUT_TICKET_TIME . '"></td>';
print '<td class="center">';
print '<input type="submit" class="button" name="save" value="' . $langs->trans('Save') . '">';
print '</td></tr>';

print '</form>';
print '</table>';

// Theme dashboard time spent.
print load_fiche_titre($langs->transnoentities('ThemeDashboardTimeSpent'), '', 'clock');

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" name="color_form">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update_theme_color">';
print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<td>' . $langs->transnoentities('Parameters') . '</td>';
print '<td>' . $langs->transnoentities('Value') . '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>' . $langs->trans('ExceededTimeSpentColor') . '</td>';
print '<td>';
print FormOther::selectColor(colorArrayToHex(colorStringToArray((!empty($conf->global->DOLISIRH_EXCEEDED_TIME_SPENT_COLOR) ? $conf->global->DOLISIRH_EXCEEDED_TIME_SPENT_COLOR : ''), []), ''), 'DOLISIRH_EXCEEDED_TIME_SPENT_COLOR', '', 1, '', '', 'dolisirhexceededtimespentcolor');
print '<span class="nowraponall opacitymedium">' . $langs->trans('Default') . '</span>: <strong>#FF0000</strong>';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>' . $langs->trans('NotExceededTimeSpentColor') . '</td>';
print '<td>';
print FormOther::selectColor(colorArrayToHex(colorStringToArray((!empty($conf->global->DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR) ? $conf->global->DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR : ''), []), ''), 'DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR', '', 1, '', '', 'dolisirhnotexceededtimespentcolor');
print '<span class="nowraponall opacitymedium">' . $langs->trans('Default') . '</span>: <strong>#FFA500</strong>';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>' . $langs->trans('PerfectTimeSpentColor') . '</td>';
print '<td>';
print FormOther::selectColor(colorArrayToHex(colorStringToArray((!empty($conf->global->DOLISIRH_PERFECT_TIME_SPENT_COLOR) ? $conf->global->DOLISIRH_PERFECT_TIME_SPENT_COLOR : ''), []), ''), 'DOLISIRH_PERFECT_TIME_SPENT_COLOR', '', 1, '', '', 'dolisirhperfecttimespentcolor');
print '<span class="nowraponall opacitymedium">' . $langs->trans('Default') . '</span>: <strong>#008000</strong>';
print '</td>';
print '</tr>';

print '</table>';
print '<div class="tabsAction"><input type="submit" class="butAction" name="save" value="' . $langs->trans('Save') . '"></div>';
print '</form>';

// Page end.
print dol_get_fiche_end();
llxFooter();
$db->close();
