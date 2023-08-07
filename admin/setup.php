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
require_once __DIR__ . '/../lib/dolisirh_function.lib.php';

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

if (GETPOST('create_hr_project_tasks', 'alpha')) {
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

if (GETPOST('create_timesheet_product_service', 'alpha')) {
    if ($conf->global->DOLISIRH_PRODUCT_SERVICE_SET == 0 || $conf->global->DOLISIRH_PRODUCT_SERVICE_SET == 1) {
        require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

        $product = new Product($db);

        $timesheetProductAndServices = get_timesheet_product_service();

        foreach ($timesheetProductAndServices as $timesheetProductAndService) {
            $product->ref   = $langs->transnoentities($timesheetProductAndService['name']);
            $product->label = $langs->transnoentities($timesheetProductAndService['name']);
            $product->type  = $timesheetProductAndService['type'];
            if (isModEnabled('barcode')) {
                $product->barcode = -1;
            }
            if ($conf->global->$timesheetProductAndService['code'] > 0) {
                $productID = $product->fetch($conf->global->$timesheetProductAndService['code']);
            } else {
                $productID = $product->create($user);
            }
            dolibarr_set_const($db, $timesheetProductAndService['code'], $productID, 'integer', 0, '', $conf->entity);
        }

        dolibarr_set_const($db, 'DOLISIRH_PRODUCT_SERVICE_SET', 2, 'integer', 0, '', $conf->entity);
    }
}

if (GETPOST('create_bookmark', 'alpha')) {
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

if ($action == 'update') {
    $certificateUserResponsible = GETPOST('certificateUserResponsible');

    dolibarr_set_const($db, 'DOLISIRH_CERTIFICATE_USER_RESPONSIBLE', $certificateUserResponsible, 'integer', 0, '', $conf->entity);

    setEventMessage('SavedConfig');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
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
print $conf->global->DOLISIRH_HR_PROJECT_SET ? '<a class="butActionRefused">' . $langs->transnoentities('Create') . '</a>' : '<input type="submit" class="button" name="create_hr_project_tasks" value="' . $langs->transnoentities('Create') . '">';
print '</td>';
print '</tr>';

// Product/service set.
print '<tr class="oddeven"><td>' . $langs->transnoentities('ProductServiceSet') . '</td>';
print '<td>';
print $langs->transnoentities('ProductServiceSetHelp');
print '</td>';
print '<td class="center">';
print (($conf->global->DOLISIRH_PRODUCT_SERVICE_SET == 2) ? $langs->transnoentities('AlreadyCreated') : $langs->transnoentities('NotCreated'));
print '</td>';
print '<td class="center">';
print (($conf->global->DOLISIRH_PRODUCT_SERVICE_SET == 2) ? '<a class="butActionRefused">' . $langs->transnoentities('Create') . '</a>' : '<input type="submit" class="button" name="create_timesheet_product_service" value="' . $langs->transnoentities('Create') . '">');
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
print $conf->global->DOLISIRH_TIMESPENT_BOOKMARK_SET ? '<a class=" butActionRefused">' . $langs->transnoentities('Create') . '</a>' : '<input type="submit" class="button" name="create_bookmark" value="' . $langs->transnoentities('Create') . '">';
print '</td>';
print '</tr>';

print '</form>';
print '</table>';

print load_fiche_titre($langs->transnoentities('Config'), '', '');

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->transnoentities('Parameters') . '</td>';
print '<td>' . $langs->transnoentities('Description') . '</td>';
print '<td>' . $langs->transnoentities('Value') . '</td>';
print '</tr>';

// CertificateUserResponsible
print '<tr class="oddeven"><td>' . $langs->trans('CertificateUserResponsible') . '</td>';
print '<td>';
print $langs->transnoentities('CertificateUserResponsibleDescription');
print '</td><td>';
print img_picto('', 'user', 'class="pictofixedwidth"') . $form->select_dolusers($conf->global->DOLISIRH_CERTIFICATE_USER_RESPONSIBLE, 'certificateUserResponsible', 1, null, 0, '', '', $conf->entity, 0, 0, 'AND u.statut = 1', 0, '', 'minwidth300');
print '<a href="' . DOL_URL_ROOT . '/user/card.php?action=create&backtopage=' . urlencode($_SERVER["PHP_SELF"]) . '"><span class="fa fa-plus-circle valignmiddle paddingleft" title="' . $langs->trans('AddUser') . '"></span></a>';
print '</td></tr>';

print '</table>';
print '<div class="tabsAction"><input type="submit" class="butAction" name="save" value="' . $langs->trans('Save') . '"></div>';
print '</form>';

// Page end.
print dol_get_fiche_end();
llxFooter();
$db->close();
