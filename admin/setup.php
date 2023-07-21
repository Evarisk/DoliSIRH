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
 * \file    admin/setup.php
 * \ingroup dolisirh
 * \brief   DoliSIRH setup page.
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

// Load DoliSIRH libraries.
require_once __DIR__ . '/../lib/dolisirh.lib.php';

// Global variables definitions.
global $conf, $db, $langs, $user;

// Load translation files required by the page.
saturne_load_langs(['admin']);

// Initialize view objects.
$form = new Form($db);

// Get parameters.
$action     = GETPOST('action', 'alpha');
$value      = GETPOST('value', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

// Security check - Protection if external user.
$permissiontoread = $user->rights->dolisirh->adminpage->read;
saturne_check_access($permissiontoread);

/*
 * Actions.
 */

if (GETPOST('hr_project_set', 'alpha')) {
    if ($conf->global->DOLISIRH_HR_PROJECT_SET == 0) {
        require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
        require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';

        $error           = 0;
        $now             = dol_now();
        $projectRefClass = empty($conf->global->PROJECT_ADDON) ? 'mod_project_simple' : $conf->global->PROJECT_ADDON;

        if (!empty($projectRefClass) && is_readable(DOL_DOCUMENT_ROOT . '/core/modules/project/' . $projectRefClass . '.php')) {
            require_once DOL_DOCUMENT_ROOT . '/core/modules/project/' . $projectRefClass . '.php';
            $modProject = new $projectRefClass();
            $projectRef = $modProject->getNextValue('', null);
        } else {
            $projectRef = '';
            $error++;
        }

        $project = new Project($db);
        $userTmp = new User($db);

        if ($error == 0) {
            $project->ref         = $projectRef;
            $project->title       = $langs->transnoentities('HumanResources') . ' - ' . $conf->global->MAIN_INFO_SOCIETE_NOM;
            $project->description = $langs->transnoentities('HRDescription');
            $project->date_c      = $now;
            $currentYear          = dol_print_date(dol_now(), '%Y');
            $fiscalMonthStart     = $conf->global->SOCIETE_FISCAL_MONTH_START;
            $dateStart            = dol_mktime('0', '0', '0', $fiscalMonthStart ?: '1', '1', $currentYear);
            $project->date_start  = $dateStart;

            $project->usage_task = 1;

            $dateStartAddYear      = dol_time_plus_duree($dateStart, 1, 'y');
            $dateStartAddYearMonth = dol_time_plus_duree($dateStartAddYear, -1, 'd');
            $dateEnd               = dol_print_date($dateStartAddYearMonth, 'dayrfc');
            $project->date_end     = $dateEnd;
            $project->statut       = 1;

            $projectID = $project->create($user);
        } else {
            $projectID = 0;
            $error++;
        }

        if ($projectID > 0 && $error == 0) {
            dolibarr_set_const($db, 'DOLISIRH_HR_PROJECT', $projectID, 'integer', 0, '', $conf->entity);
            $users = $userTmp->get_full_tree(0, 'u.employee = 1 AND u.fk_soc IS NULL AND u.statut = 1');
            if (!empty($users) && is_array($users)) {
                foreach ($users as $userSingle) {
                    $project->add_contact($userSingle['id'], 'PROJECTCONTRIBUTOR', 'internal');
                }
            }

            require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';

            $task = new Task($db);

            $taskRefClass = empty($conf->global->PROJECT_TASK_ADDON) ? 'mod_task_simple' : $conf->global->PROJECT_TASK_ADDON;

            if (!empty($taskRefClass) && is_readable(DOL_DOCUMENT_ROOT . '/core/modules/project/task/' . $taskRefClass . '.php')) {
                require_once DOL_DOCUMENT_ROOT . '/core/modules/project/task/' . $conf->global->PROJECT_TASK_ADDON . '.php';
                $modTask = new $taskRefClass();
                $modTask->getNextValue('', null);
            } else {
                $modTask = null;
                $error++;
            }

            if ($error == 0) {
                $hrProjectTasks = get_hr_project_tasks();

                foreach ($hrProjectTasks as $hrProjectTask) {
                    $task->date_c     = $now;
                    $task->fk_project = $projectID;
                    $task->ref        = $modTask->getNextValue('', null);
                    $task->label      = $langs->transnoentities($hrProjectTask['name']);
                    $taskID           = $task->create($user);
                    dolibarr_set_const($db, $hrProjectTask['code'], $taskID, 'integer', 0, '', $conf->entity);
                }

                $taskArray = $task->getTasksArray(0, 0, $projectID);

                if (!empty($users) && is_array($users)) {
                    foreach ($users as $userSingle) {
                        if (is_array($taskArray) && !empty($taskArray)) {
                            foreach ($taskArray as $task) {
                                $task->add_contact($userSingle['id'], 'TASKCONTRIBUTOR', 'internal');
                            }
                        }
                    }
                }

                dolibarr_set_const($db, 'DOLISIRH_HR_PROJECT_SET', 1, 'integer', 0, '', $conf->entity);
            }
        }
    }
}

if (GETPOST('product_service_set', 'alpha')) {
    if ($conf->global->DOLISIRH_PRODUCT_SERVICE_SET == 0) {
        require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

        $product = new Product($db);

        $productOrServiceTimesheets = get_product_service_timesheet();

        foreach ($productOrServiceTimesheets as $productOrServiceTimesheet) {
            $product->ref   = $productOrServiceTimesheet['name'];
            $product->label = $productOrServiceTimesheet['name'];
            $product->create($user);
        }

        dolibarr_set_const($db, 'DOLISIRH_PRODUCT_SERVICE_SET', 1, 'integer', 0, '', $conf->entity);
    }
}

if (GETPOST('bookmark_set', 'alpha')) {
    if ($conf->global->DOLISIRH_TIMESPENT_BOOKMARK_SET == 0) {
        require_once DOL_DOCUMENT_ROOT . '/bookmarks/class/bookmark.class.php';

        $bookmark = new Bookmark($db);

        $bookmark->title    = $langs->transnoentities('TimeSpent');
        $bookmark->url      = DOL_URL_ROOT . '/custom/dolisirh/view/timespent_range.php?view_mode=month';
        $bookmark->target   = 0;
        $bookmark->position = 10;
        $bookmark->create();

        dolibarr_set_const($db, 'DOLISIRH_TIMESPENT_BOOKMARK_SET', 1, 'integer', 0, '', $conf->entity);
    }
}

/*
 * View.
 */

$title    = $langs->trans('ModuleSetup', 'DoliSIRH');
$help_url = 'FR:Module_DoliSIRH';

saturne_header(0,'', $title, $help_url);

// Subheader
$linkback = '<a href="' . ($backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans('BackToModuleList') . '</a>';

print load_fiche_titre($title, $linkback, 'title_setup');

// Configuration header
$head = dolisirh_admin_prepare_head();
print dol_get_fiche_head($head, 'settings', $title, -1, 'dolisirh_color@dolisirh');

print load_fiche_titre($langs->transnoentities('SetupDefaultData'), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->transnoentities('Parameters') . '</td>';
print '<td>' . $langs->transnoentities('Description') . '</td>';
print '<td class="center">' . $langs->transnoentities('Status') . '</td>';
print '<td class="center">' . $langs->transnoentities('Action') . '</td>';
print '</tr>';

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="">';

// HR project set.
print '<tr class="oddeven"><td>' . $langs->transnoentities('HRProjectSet') . '</td>';
print '<td>';
print $langs->transnoentities('HRProjectSetHelp');
print '</td>';
print '<td class="center">';
print $conf->global->DOLISIRH_HR_PROJECT_SET ? $langs->transnoentities('AlreadyCreated') : $langs->transnoentities('NotCreated');
print '</td>';
print '<td class="center">';
print $conf->global->DOLISIRH_HR_PROJECT_SET ? '<a class="butActionRefused">' . $langs->transnoentities('Create') . '</a>' : '<input type="submit" class="button" name="hr_project_set" value="' . $langs->transnoentities('Create') . '">';
print '</td>';
print '</tr>';

// Product/service set.
print '<tr class="oddeven"><td>' . $langs->transnoentities('ProductServiceSet') . '</td>';
print '<td>';
print $langs->transnoentities('ProductServiceSetHelp');
print '</td>';
print '<td class="center">';
print $conf->global->DOLISIRH_PRODUCT_SERVICE_SET ? $langs->transnoentities('AlreadyCreated') : $langs->transnoentities('NotCreated');
print '</td>';
print '<td class="center">';
print $conf->global->DOLISIRH_PRODUCT_SERVICE_SET ? '<a class="butActionRefused">' . $langs->transnoentities('Create') . '</a>' : '<input type="submit" class="button" name="product_service_set" value="' . $langs->transnoentities('Create') . '">';
print '</td>';
print '</tr>';

// Bookmark set.
print '<tr class="oddeven"><td>' . $langs->transnoentities('BookmarkSet') . '</td>';
print '<td>';
print $langs->transnoentities('BookmarkSetHelp');
print '</td>';
print '<td class="center">';
print $conf->global->DOLISIRH_TIMESPENT_BOOKMARK_SET ? $langs->transnoentities('AlreadyCreated') : $langs->transnoentities('NotCreated');
print '</td>';
print '<td class="center">';
print $conf->global->DOLISIRH_TIMESPENT_BOOKMARK_SET ? '<a class=" butActionRefused">' . $langs->transnoentities('Create') . '</a>' : '<input type="submit" class="button" name="bookmark_set" value="' . $langs->transnoentities('Create') . '">';
print '</td>';
print '</tr>';

print '</form>';
print '</table>';

// Page end.
print dol_get_fiche_end();
llxFooter();
$db->close();
