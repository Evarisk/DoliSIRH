<?php
/* Copyright (C) 2021-2023 EVARISK <technique@evarisk.com>
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
 * \file   view/timespent_range.php
 * \ingroup dolisirh
 * \brief   List timespent of tasks per range.
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
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';

if (isModEnabled('projet')) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';
    require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
    require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
}

if (isModEnabled('categorie')) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcategory.class.php';
    require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
}

// Load DoliSIRH libraries.
require_once __DIR__ . '/../lib/dolisirh_function.lib.php';
require_once __DIR__ . '/../lib/dolisirh_timespent.lib.php';
require_once __DIR__ . '/../class/workinghours.class.php';

// Global variables definitions.
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page.
saturne_load_langs(['projects', 'users', 'companies']);

// Get parameters.
$action      = GETPOST('action', 'aZ09');
$id          = GETPOST('id', 'int');
$taskID      = GETPOST('task_id', 'int');
$projectID   = GETPOSTISSET('id') ? GETPOST('id', 'int', 1) : GETPOST('project_id', 'int');
$mode        = GETPOST('mode', 'alpha');
$viewMode    = GETPOSTISSET('view_mode') ? GETPOST('view_mode', 'aZ09') : 'month';
$contextPage = GETPOST('contextpage', 'aZ') ?GETPOST('contextpage', 'aZ') : 'per' . $viewMode . 'card';
$backToPage  = GETPOST('backtopage', 'alpha');

// Get date parameters.
$year  = GETPOST('reyear', 'int') ? GETPOST('reyear', 'int') : (GETPOST('year', 'int') ?GETPOST('year', 'int') : date('Y'));
$month = GETPOST('remonth', 'int') ? GETPOST('remonth', 'int') : (GETPOST('month', 'int') ?GETPOST('month', 'int') : date('m'));
$day   = GETPOST('reday', 'int') ? GETPOST('reday', 'int') : (GETPOST('day', 'int') ?GETPOST('day', 'int') : date('d'));
$week  = GETPOST('week', 'int') ? GETPOST('week', 'int') : date('W');

// Get search parameters.
$searchUserID     = GETPOST('search_user_id', 'int');
$searchProjectRef = GETPOST('search_project_ref', 'alpha');
$searchTaskRef    = GETPOST('search_task_ref', 'alpha');
$searchTaskLabel  = GETPOST('search_task_label', 'alpha');
$searchThirdparty = GETPOST('search_thirdparty', 'alpha');
if (isModEnabled('categorie')) {
    $searchCategoryArray = GETPOST('search_category_' . Categorie::TYPE_PROJECT . '_list', 'array');
} else {
    $searchCategoryArray = [];
}

// Get list parameters.
$sortField = GETPOST('sortfield', 'aZ09comma');
$sortOrder = GETPOST('sortorder', 'aZ09comma');

// Initialize objects.
// Technical objets.
$project      = new Project($db);
$task         = new Task($db);
$workingHours = new Workinghours($db);
$extraFields  = new ExtraFields($db);

// View objets.
$form        = new Form($db);
$formCompany = new FormCompany($db);
$formProject = new FormProjets($db);
if (isModEnabled('categorie')) {
    $formCategory = new FormCategory($db);
} else {
    $formCategory = '';
}

$hookmanager->initHooks(['timespentper' . $viewMode . 'list']);

// Define firstDayToShow and lastDayOfRange (warning: $lastDayOfRange is last second to show + 1).
$now            = dol_now();
$rangeDate      = '';
$range          = '';
$firstDayToShow = 0;
$lastDayOfRange = 0;
$firstYear      = 0;
$firstMonth     = 0;
$firstDay       = 0;
$prevYear       = 0;
$prevMonth      = 0;
$prevDay        = 0;
$nextYear       = 0;
$nextMonth      = 0;
$nextDay        = 0;
switch ($viewMode) {
    case 'month' :
        $prev       = dol_get_prev_month($month, $year);
        $prevYear   = $prev['year'];
        $prevMonth  = $prev['month'];

        $next      = dol_get_next_month($month, $year);
        $nextYear  = $next['year'];
        $nextMonth = $next['month'];

        $firstDayToShow = dol_get_first_day($year, $month);
        $lastDayOfRange = strtotime(date('Y-m-t', $firstDayToShow));
        $rangeDate      = 'm';
        $range          = $month;
        break;
    case 'week' :
        $prev       = dol_get_first_day_week($day, $month, $year);
        $prevYear   = $prev['prev_year'];
        $prevMonth  = $prev['prev_month'];
        $prevDay    = $prev['prev_day'];
        $firstYear  = $prev['first_year'];
        $firstMonth = $prev['first_month'];
        $firstDay   = $prev['first_day'];
        $week       = $prev['week'];

        $next      = dol_get_next_week($firstDay, $week, $firstMonth, $firstYear);
        $nextYear  = $next['year'];
        $nextMonth = $next['month'];
        $nextDay   = $next['day'];

        $firstDayToShow = dol_mktime(0, 0, 0, $firstMonth, $firstDay, $firstYear);
        $lastDayOfRange = dol_time_plus_duree($firstDayToShow, 6, 'd');
        $rangeDate      = 'W';
        $range          = $week;
        break;
    case 'day' :
        $prev      = dol_get_prev_day($day, $month, $year);
        $prevYear  = $prev['year'];
        $prevMonth = $prev['month'];
        $prevDay   = $prev['day'];

        $next      = dol_get_next_day($day, $month, $year);
        $nextYear  = $next['year'];
        $nextMonth = $next['month'];
        $nextDay   = $next['day'];

        $firstDayToShow = dol_mktime(0, 0, 0, $month, $day, $year);
        $lastDayOfRange = dol_mktime(0, 0, 0, $month, $day, $year);
        $rangeDate      = 'd';
        $range          = $day;
        break;
}

$currentRange = date($rangeDate, $now);
if ($currentRange == $range && date('Y', $now) == $year) {
    $currentDate    = dol_getdate($now);
    $lastDayOfRange = dol_mktime(0, 0, 0, $currentDate['mon'], $currentDate['mday'], $currentDate['year']);
}

$daysInRange = dolisirh_num_between_days($firstDayToShow, $lastDayOfRange, 1);

if (empty($searchUserID) || $searchUserID == $user->id) {
    $userTmp = $user;
    $searchUserID = $userTmp->id;
} elseif ($searchUserID > 0) {
    $userTmp = new User($db);
    $userTmp->fetch($searchUserID);
    $searchUserID = $userTmp->id;
} else {
    $userTmp = new User($db);
}

$mine = 0;
if ($mode == 'mine') {
    $mine = 1;
}

// Definition of fields for list.
$arrayFields                 = [];
$arrayFields['timeconsumed'] = ['label' => 'TimeConsumed', 'checked' => 1, 'enabled' => 1, 'position' => 15];
$arrayFields                 = dol_sort_array($arrayFields, 'position');

// Security check.
$permissiontoRead = $user->rights->projet->lire;
$permissiontoAdd  = $user->rights->projet->time;

// Security check - Protection if external user.
saturne_check_access($permissiontoRead, $task);

/*
 * Actions.
 */

$parameters = ['id' => $id, 'taskID' => $taskID, 'projectID' => $projectID];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $task, $action); // Note that $action and $task may have been modified by some hooks.
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
if (empty($resHook)) {
    // Purge criteria.
    if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers.
        $action            = '';
        $searchUserID      = $user->id;
        $searchProjectRef  = '';
        $searchTaskRef     = '';
        $searchTaskLabel   = '';
        $searchThirdparty = '';

        $searchCategoryArray = [];

        // We redefine $userTmp.
        $userTmp = $user;
    }

    if (GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
        $action = '';
    }

    if ($action == 'add_time' && $permissiontoRead && GETPOST('assigned_task_id') > 0) {
        $action = 'assign_task';
        $error  = 0;

        if ($taskID > 0) {
            $result = $task->fetch($taskID);
            if ($result < 0) {
                $error++;
            }
        } else {
            setEventMessages($langs->transnoentitiesnoconv('ErrorFieldRequired', $langs->transnoentitiesnoconv('Task')), [], 'errors');
            $error++;
        }
        if (!GETPOST('type')) {
            setEventMessages($langs->transnoentitiesnoconv('ErrorFieldRequired', $langs->transnoentitiesnoconv('Type')), [], 'errors');
            $error++;
        }

        if (!$error) {
            $idForTaskUser = $userTmp->id;
            $result        = $task->add_contact($idForTaskUser, GETPOST('type'), 'internal');
            if ($result >= 0 || $result == -2) { // Contact add ok or already contact of task.
                // Test if we are already contact of the project (should be rare, but sometimes we can add as task contact without being contact of project, like when admin user has been removed from contact of project).
                $sql   = 'SELECT ec.rowid FROM ' . MAIN_DB_PREFIX . 'element_contact as ec, ' . MAIN_DB_PREFIX . 'c_type_contact as tc WHERE tc.rowid = ec.fk_c_type_contact';
                $sql  .= ' AND ec.fk_socpeople = ' . $idForTaskUser . ' AND ec.element_id = ' . $task->fk_project . " AND tc.element = 'project' AND source = 'internal'";
                $resql = $db->query($sql);
                if ($resql) {
                    $obj = $db->fetch_object($resql);
                    if (!$obj) { // User is not already linked to project, so we will create link to first type.
                        $project->fetch($task->fk_project);
                        // Get type.
                        $listOfProjContact = $project->liste_type_contact();
                        if (count($listOfProjContact)) {
                            $listOfProjContactKeys = array_keys($listOfProjContact);
                            $typeForProjectContact = reset($listOfProjContactKeys);
                            $project->add_contact($idForTaskUser, $typeForProjectContact, 'internal');
                        }
                    }
                } else {
                    dol_print_error($db);
                }
            } else {
                $error++;
                if ($task->error == 'DB_ERROR_RECORD_ALREADY_EXISTS') {
                    $langs->load('errors');
                    setEventMessages($langs->trans('ErrorTaskAlreadyAssigned'), [], 'warnings');
                } else {
                    setEventMessages($task->error, $task->errors, 'errors');
                }
            }
            setEventMessages('TaskAssignedToEnterTime', []);
            $taskID = 0;
        }

        $action = '';
    }

    if ($action == 'show_only_favorite_tasks') {
        $data = json_decode(file_get_contents('php://input'), true);

        $showOnlyFavoriteTasks = $data['showOnlyFavoriteTasks'];

        $tabParam['DOLISIRH_SHOW_ONLY_FAVORITE_TASKS'] = $showOnlyFavoriteTasks;

        dol_set_user_param($db, $conf, $user, $tabParam);
    }

    if ($action == 'show_only_tasks_with_timespent') {
        $data = json_decode(file_get_contents('php://input'), true);

        $showOnlyTasksWithTimeSpent = $data['showOnlyTasksWithTimeSpent'];

        $tabParam['DOLISIRH_SHOW_ONLY_TASKS_WITH_TIMESPENT'] = $showOnlyTasksWithTimeSpent;

        dol_set_user_param($db, $conf, $user, $tabParam);
    }

    if ($action == 'add_timespent' && $permissiontoAdd) {
        $data = json_decode(file_get_contents('php://input'), true);

        $taskID    = $data['taskID'];
        $timestamp = $data['timestamp'];
        $dateHour  = $data['datehour'];
        $dateMin   = $data['datemin'];
        $comment   = $data['comment'];
        $hour      = (int) $data['hour'];
        $min       = (int) $data['min'];

        $task->fetch($taskID);

        $task->timespent_date     = $timestamp;
        $task->timespent_datehour = $timestamp + ($dateHour * 3600) + ($dateMin * 60);
        if ($dateHour > 0 || $dateMin > 0) {
            $task->timespent_withhour = 1;
        }
        $task->timespent_note     = $comment;
        $task->timespent_duration = ($hour * 3600) + ($min * 60);
        $task->timespent_fk_user  = $user->id;

        if ($task->timespent_duration > 0) {
            $task->addTimeSpent($user);
        }
    }
}

/*
 * View.
 */

$title    = $langs->trans('TimeSpent');
$help_url = 'FR:Module_DoliSIRH';

if ($id) {
    $project->fetch($id);
    $project->fetch_thirdparty();
}

$onlyOpenedProject = -1; // or -1.
$moreWhereFilter   = '';

if ($searchProjectRef) {
    $moreWhereFilter .= natural_search(['p.ref', 'p.title'], $searchProjectRef);
}
if ($searchTaskRef) {
    $moreWhereFilter .= natural_search('t.ref', $searchTaskRef);
}
if ($searchTaskLabel) {
    $moreWhereFilter .= natural_search(['t.ref', 't.label'], $searchTaskLabel);
}
if ($searchThirdparty) {
    $moreWhereFilter .= natural_search('s.nom', $searchThirdparty);
}
if (isModEnabled('categorie')) {
    $moreWhereFilter .= Categorie::getFilterSelectQuery(Categorie::TYPE_PROJECT, 'p.rowid', $searchCategoryArray);
}

$timeArray  = ['year' => $year, 'month' => $month, 'day' => $day, 'week' => $week];
$tasksArray = get_tasks_array(0, 0, ($project->id ?: 0), 0, 0, $searchProjectRef, $onlyOpenedProject, $moreWhereFilter, ($searchUserID ?: 0), 0, $extraFields,0, [], 0,$timeArray, $viewMode);

$tasksRole    = $task->getUserRolesForProjectsOrTasks(0, $userTmp, ($project->id ?: 0), 0, $onlyOpenedProject);
$projectsRole = $task->getUserRolesForProjectsOrTasks($userTmp, 0, ($project->id ?: 0), 0, $onlyOpenedProject);

saturne_header(0,'', $title, $help_url);

$param  = (dol_strlen($viewMode) > 0 ? '&view_mode=' . urlencode($viewMode) : '');
$param .= ($mode ? '&mode=' . urlencode($mode) : '');
$param .= (dol_strlen($searchProjectRef) > 0 ? '&search_project_ref=' . urlencode($searchProjectRef) : '');
$param .= ($searchUserID > 0 ? '&search_user_id=' . urlencode($searchUserID) : '');
$param .= (dol_strlen($searchThirdparty) > 0 ? '&search_thirdparty=' . urlencode($searchThirdparty) : '');
$param .= (dol_strlen($searchTaskRef) > 0 ? '&search_task_ref=' . urlencode($searchTaskRef) : '');
$param .= (dol_strlen($searchTaskLabel) > 0 ? '&search_task_label=' . urlencode($searchTaskLabel) : '');

// Show navigation bar.
switch ($viewMode) {
    case 'day' :
        $nav = '<a class="inline-block valignmiddle" href="?year=' . $prevYear . '&month=' . $prevMonth . '&day=' . $prevDay . $param . '">' . img_previous($langs->trans('Previous')) . '</a>';
        $nav .= dol_print_date(dol_mktime(0, 0, 0, $month, $day, $year), '%A') . ' ';
        $nav .= '<span id="month_name">' . dol_print_date(dol_mktime(0, 0, 0, $month, $day, $year), 'day') . '</span>';
        $nav .= '<a class="inline-block valignmiddle" href="?year=' . $nextYear . '&month=' . $nextMonth . '&day=' . $nextDay . $param . '">'.img_next($langs->trans('Next')) . '</a>';
        break;
    case 'week' :
        $nav = '<a class="inline-block valignmiddle" href="?year=' . $prevYear . '&month=' . $prevMonth . '&day=' . $prevDay . $param . '">' . img_previous($langs->trans('Previous')) . '</a>';
        $nav .= '<span id="month_name">' . dol_print_date(dol_mktime(0, 0, 0, $firstMonth, $firstDay, $firstYear), '%Y') . ', ' . $langs->trans('WeekShort') . ' ' . $week . '</span>';
        $nav .= '<a class="inline-block valignmiddle" href="?year=' . $nextYear . '&month=' . $nextMonth . '&day=' . $nextDay . $param . '">'.img_next($langs->trans('Next')) . '</a>';
        break;
    default :
        $nav = '<a class="inline-block valignmiddle" href="?year=' . $prevYear . '&month=' . $prevMonth . $param . '">' . img_previous($langs->trans('Previous')) . '</a>';
        $nav .= '<span id="month_name">' . dol_print_date(dol_mktime(0, 0, 0, $month, $day, $year), '%B %Y') . '</span>';
        $nav .= '<a class="inline-block valignmiddle" href="?year=' . $nextYear . '&month=' . $nextMonth . $param . '">'.img_next($langs->trans('Next')) . '</a>';
        break;
}
$nav .= $form->selectDate(-1, '', 0, 0, 2, 'addtime', 1, 1);
$nav .= '<button type="submit" name="button_search_x" class="bordertransp"><span class="fa fa-search"></span></button>';

print '<form name="addtime" id="addtimeform" method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="add_time">';
print '<input type="hidden" name="contextpage" value="' . $contextPage . '">';
print '<input type="hidden" name="view_mode" value="' . $viewMode . '">';
print '<input type="hidden" name="mode" value="' . $mode . '">';
print '<input type="hidden" name="day" value="' . $day . '">';
print '<input type="hidden" name="month" value="' . $month . '">';
print '<input type="hidden" name="year" value="' . $year . '">';
if ($backToPage) {
    print '<input type="hidden" name="backtopage" value="' . $backToPage . '">';
}

$head = timespent_prepare_head($mode, $userTmp);
print dol_get_fiche_head($head, 'inputper' . $viewMode, $langs->trans('TimeSpent'), -1, 'clock');

// Show description of content
print '<div class="hideonsmartphone opacitymedium">';
$tooltipTaskInfo = '';
if ($mine || ($userTmp->id == $user->id)) {
    $tooltipTaskInfo = $langs->trans('MyTasksDesc').'.'.($onlyOpenedProject ? ' ' . '<b style="color: #ff0000;">' . $langs->trans('OnlyOpenedProject') : '') . '</b><br>';
} elseif (empty($userTmp->id) || $userTmp->id < 0) {
    if ($user->rights->projet->all->lire) {
        $tooltipTaskInfo .= $langs->trans('ProjectsDesc') . '.' . ($onlyOpenedProject ? ' ' . '<b style="color: #ff0000;">' . $langs->trans('OnlyOpenedProject') : '') . '</b><br>';
    } else {
        $tooltipTaskInfo .= $langs->trans('ProjectsPublicTaskDesc') . '.' . ($onlyOpenedProject ? ' ' . $langs->trans('OnlyOpenedProject') : '') . '</b><br>';
    }
}
if ($mine || ($userTmp->id == $user->id)) {
    $tooltipTaskInfo .= $langs->trans('OnlyYourTaskAreVisible').'<br>';
} else {
    $tooltipTaskInfo .= $langs->trans('AllTaskVisibleButEditIfYouAreAssigned').'<br>';
}
print '</div>';

print dol_get_fiche_end();

print '<div class="floatright right'. ($conf->dol_optimize_smallscreen ? ' centpercent' : '') . '">' . $nav . '</div>'; // We move this before the assign to components so, the default submit button is not the assign to.

print '<div class="colorbacktimesheet float valignmiddle">';
$titleAssignTask = $langs->transnoentities('AssignTaskToMe');
if ($userTmp->id != $user->id) {
    $titleAssignTask = $langs->transnoentities('AssignTaskToUser', $userTmp->getFullName($langs));
}
print '<div class="taskiddiv inline-block">';
print img_picto('', 'projecttask', 'class="pictofixedwidth"');
$formProject->selectTasks(-1, $taskID, 'assigned_task_id', 32, 0, '-- ' . $langs->trans('ChooseANotYetAssignedTask') . ' --', 1, 0, 0, '', '', 'all', $userTmp);
print '</div>';
print ' ' . $formCompany->selectTypeContact($task, 46, 'type', 'internal', 'rowid', 0, 'maxwidth150onsmartphone');
print '<input type="submit" class="button valignmiddle smallonsmartphone" name="assigntask" value="' . dol_escape_htmltag($titleAssignTask) . '">';
print '</div>';

print '<div class="clearboth" style="padding-bottom: 20px;"></div>';
$tooltipTaskInfo .= img_help(1, $langs->trans('KeyEvent')) .  ' ' . $langs->trans('KeyEventTips') . '<br><br>';
if ($user->conf->DOLISIRH_SHOW_ONLY_FAVORITE_TASKS > 0) {
    $tooltipTaskInfo .= '<div class="opacitymedium"><i class="fas fa-exclamation-triangle"></i>' . ' ' . $langs->trans('WarningShowOnlyFavoriteTasks') . '</div>';
}

print '<div class="clearboth" style="padding-bottom: 20px;"></div>';

// Get if user is available or not for each day.
$isAvailable = [];
for ($idw = 0; $idw < $daysInRange; $idw++) {
    $dayInLoop =  dol_time_plus_duree($firstDayToShow, $idw, 'd');
    if (is_day_available($dayInLoop, $user->id)) {
        $isAvailable[$dayInLoop] = ['morning'=>1, 'afternoon'=>1];
    } elseif (date('N', $dayInLoop) >= 6) {
        $isAvailable[$dayInLoop] = ['morning' => false, 'afternoon' => false, 'morning_reason' => 'week_end', 'afternoon_reason' => 'week_end'];
    } else {
        $isAvailable[$dayInLoop] = ['morning' => false, 'afternoon' => false, 'morning_reason' => 'public_holiday', 'afternoon_reason' => 'public_holiday'];
    }
}

// If the user can view user other than himself.
$moreForFilter  = '<div class="divsearchfield">';
$moreForFilter .= '<div class="inline-block hideonsmartphone"></div>';

$includeOnly = '';
if (empty($user->rights->user->user->lire)) {
    $includeOnly = [$user->id];
}

$moreForFilter .= img_picto($langs->trans('Filter') . ' ' . $langs->trans('User'), 'user', 'class="paddingright pictofixedwidth"') . $form->select_dolusers($searchUserID ?: $userTmp->id, 'search_user_id', 0, null, 0, $includeOnly, null, 0, 0, 0, ' AND u.employee = 1', 0, '', 'maxwidth200', 1);
$moreForFilter .= '</div>';

if (!getDolGlobalInt('PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT')) {
    $moreForFilter .= '<div class="divsearchfield">';
    $moreForFilter .= '<div class="inline-block"></div>';
    $moreForFilter .= img_picto($langs->trans('Filter') . ' ' . $langs->trans('Project'), 'project', 'class="paddingright pictofixedwidth"') . '<input type="text" name="search_project_ref" class="maxwidth100" value="' . dol_escape_htmltag($searchProjectRef) . '">';
    $moreForFilter .= '</div>';

    $moreForFilter .= '<div class="divsearchfield">';
    $moreForFilter .= '<div class="inline-block"></div>';
    $moreForFilter .= img_picto($langs->trans('Filter') . ' ' . $langs->trans('ThirdParty'), 'company', 'class="paddingright pictofixedwidth"') . '<input type="text" name="search_thirdparty" class="maxwidth100" value="' . dol_escape_htmltag($searchThirdparty) . '">';
    $moreForFilter .= '</div>';
}

// Filter on categories.
if (isModEnabled('categorie') && $user->rights->categorie->lire) {
    $moreForFilter .= $formCategory->getFilterBox(Categorie::TYPE_PROJECT, $searchCategoryArray);
}

if (!empty($moreForFilter)) {
    print '<div class="liste_titre liste_titre_bydiv centpercent">';
    print $moreForFilter;
    $parameters = [];
    $hookmanager->executeHooks('printFieldPreListTitle', $parameters); // Note that $action and $task may have been modified by hook.
    print $hookmanager->resPrint;
    print '</div>';
}

print '<div class="div-table-responsive">';
print '<table class="tagtable liste' . ($moreForFilter ? ' listwithfilterbefore' : '') . '" id="tablelines3">';

print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><input type="text" size="4" name="search_task_label" value="' . dol_escape_htmltag($searchTaskLabel) . '"></td>';
// TASK fields.
if (!empty($arrayFields['timeconsumed']['checked'])) {
    print '<td class="liste_titre"></td>';
}
for ($idw = 0; $idw < $daysInRange; $idw++) {
    print '<td class="liste_titre"></td>';
}
// Action column.
print '<td class="liste_titre nowrap right">';
print $form->showFilterAndCheckAddButtons();
print '</td>';
print '</tr>';

print '<tr class="liste_titre">';
print '<th>' . $form->textwithpicto($langs->trans('Task'), $tooltipTaskInfo);
print ' <i class="fas fa-star"></i>';
print '<input type="checkbox"  class="show-only-favorite-tasks"' . ($user->conf->DOLISIRH_SHOW_ONLY_FAVORITE_TASKS ? ' checked' : '') . '>';
print $form->textwithpicto('', $langs->trans('ShowOnlyFavoriteTasks'));
print ' <i class="fas fa-clock"></i>';
print '<input type="checkbox"  class="show-only-tasks-with-timespent"'. ($user->conf->DOLISIRH_SHOW_ONLY_TASKS_WITH_TIMESPENT ? ' checked' : '') . '>';
print $form->textwithpicto('', $langs->trans('ShowOnlyTasksWithTimeSpent'));
print '</th>';
// TASK fields.
if (!empty($arrayFields['timeconsumed']['checked'])) {
    print '<th class="right maxwidth75 maxwidth100">' . $langs->trans('TimeSpent') . ($userTmp->firstname ? '<br><span class="nowraponall">' . $userTmp->getNomUrl(-2) . '<span class="opacitymedium paddingleft">' . dol_trunc($userTmp->firstname, 10) . '</span></span>' : '') . '</th>';
}

for ($idw = 0; $idw < $daysInRange; $idw++) {
    $cellCSS   = '';
    $dayInLoop = dol_time_plus_duree($firstDayToShow, $idw, 'd');
    if (!$isAvailable[$dayInLoop]['morning'] && !$isAvailable[$dayInLoop]['afternoon']) {
        if ($isAvailable[$dayInLoop]['morning_reason'] == 'public_holiday') {
            $cellCSS = 'onholidayallday';
        } elseif ($isAvailable[$dayInLoop]['morning_reason'] == 'week_end') {
            $cellCSS = 'weekend';
        }
    }

    print '<th width="6%" class="center bold ' . $idw . ' ' . $cellCSS . '" style="font-size : 12px">';
    print dol_print_date($dayInLoop, '%a');
    $splittedDate = preg_split('/\//', dol_print_date($dayInLoop, 'day'));
    $day   = $splittedDate[0];
    $month = $splittedDate[1];
    $year  = $splittedDate[2];
    print '<a href="timespent_range.php?year=' . $year . '&month=' . $month . '&day=' . $day . '&search_user_id=' . $userTmp->id . '&view_mode=day"><i class="fas fa-external-link-alt"></i></a>';
    print '<br>' . dol_print_date($dayInLoop, '%d/%m') . '</th>';
}
print_liste_field_titre('', $_SERVER['PHP_SELF'], '', '', '', '', $sortField, $sortOrder, 'center maxwidthsearch');

print '</tr>';

$colspan = 1 + (!getDolGlobalInt('PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT') ? 0 : 2);

$workingHours = $workingHours->fetchCurrentWorkingHours($userTmp->id, 'user');

if ($conf->use_javascript_ajax) {
    $plannedWorkingTime = load_planned_time_within_range($firstDayToShow, dol_time_plus_duree($lastDayOfRange, 1, 'd'), $workingHours, $isAvailable);

    print '<tr class="liste_total">';
    print '<td class="liste_total" colspan="' . $colspan . '">';
    print $langs->trans('Total');
    print '<span class="opacitymediumbycolor">  - ';
    if ($viewMode == 'month') {
        print $langs->trans('ExpectedWorkingHoursMonth', dol_print_date(dol_mktime(0, 0, 0, $month, $day, $year), '%B %Y'));
    } else {
        print $langs->trans('ExpectedWorkingHoursWeek', dol_print_date($firstDayToShow, 'dayreduceformat'), dol_print_date($lastDayOfRange, 'dayreduceformat'));
    }
    print ' : <strong><a href="' . DOL_URL_ROOT . '/custom/dolisirh/view/workinghours_card.php?id=' . $userTmp->id.'" target="_blank">';
    print (($plannedWorkingTime['minutes'] != 0) ? convertSecondToTime($plannedWorkingTime['minutes'] * 60, 'allhourmin') : '00:00') . '</a></strong></span>';
    print '</td>';
    if (!empty($arrayFields['timeconsumed']['checked'])) {
        print '<td class="liste_total"></td>';
    }

    // Fill days data.
    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $cellCSS           = '';
        $dayInLoop         =  dol_time_plus_duree($firstDayToShow, $idw, 'd');
        $plannedHoursOnDay = load_planned_time_within_range($dayInLoop, dol_time_plus_duree($firstDayToShow, $idw + 1, 'd'), $workingHours, $isAvailable);
        if (!$isAvailable[$dayInLoop]['morning'] && !$isAvailable[$dayInLoop]['afternoon']) {
            if ($isAvailable[$dayInLoop]['morning_reason'] == 'public_holiday') {
                $cellCSS = 'onholidayallday';
            } elseif ($isAvailable[$dayInLoop]['morning_reason'] == 'week_end') {
                $cellCSS = 'weekend';
            }
        }

        print '<td class="liste_total ' . $idw . ' ' . $cellCSS. '" align="center">';
        print '<div class="' . $idw . '">' . (($plannedHoursOnDay['minutes'] != 0) ? convertSecondToTime($plannedHoursOnDay['minutes'] * 60, 'allhourmin') : '00:00') . '</div></td>';
    }
    print '<td></td>';
    print '</tr>';
}

// By default, we can edit only tasks we are assigned to.
$restrictViewForMyTask = ((!isset($conf->global->PROJECT_TIME_SHOW_TASK_NOT_ASSIGNED)) ? 2 : $conf->global->PROJECT_TIME_SHOW_TASK_NOT_ASSIGNED);

$j     = 0;
$level = 0;

// Show tasks lines.
$timeSpentOnTasks = load_time_spent_on_tasks_within_range($firstDayToShow, dol_time_plus_duree($lastDayOfRange, 1, 'd'), $isAvailable, $userTmp->id);

task_lines_within_range($j, $firstDayToShow, $lastDayOfRange, $userTmp, 0, $tasksArray, $level, $projectsRole, $tasksRole, $mine, $restrictViewForMyTask, $isAvailable, $timeSpentOnTasks, 0, $arrayFields, $extraFields); ?>

<!-- TIMESPENT ADD MODAL -->
<div class="timespent-add-modal">
    <div class="wpeo-modal modal-timespent" id="timespent">
        <div class="modal-container wpeo-modal-event" style="max-width: 400px; max-height: 300px;">
            <!-- Modal-Header -->
            <div class="modal-header">
                <div class="modal-close"><i class="fas fa-times"></i></div>
            </div>
            <!-- Modal-Content -->
            <div class="modal-content">
                <div class="timespent-container">
                    <input type="hidden" class="timespent-taskid" value="">
                    <input type="hidden" class="timespent-timestamp" value="">
                    <input type="hidden" class="timespent-cell" value="">
                    <div class="wpeo-gridlayout grid-3">
                        <div class="gridw-2">
                            <div class="title"><strong><i class="far fa-calendar-alt"></i> <?php echo $langs->trans('Date'); ?></strong></div>
                            <span><span class="timespent-date"></span><label><input class="flat maxwidth50 timespent-datehour" type="number" placeholder="H" min="0" max="23"></label> : <label><input class="flat maxwidth50 timespent-datemin" type="number" placeholder="mn" min="0" max="59"></label></span>
                        </div>
                        <div>
                            <div class="title"><strong><i class="far fa-clock"></i> <?php echo $langs->trans('Duration'); ?></strong></div>
                            <span><label><input class="flat maxwidth50 timespent-hour" type="number" placeholder="H" min="0" max="23"></label> : <label><input class="flat maxwidth50 timespent-min" type="number" placeholder="mn" min="0" max="59"></label></span>
                        </div>
                    </div>
                    <br/>
                    <div class="title"><strong><i class="far fa-comment-alt"></i> <?php echo $langs->trans('Comment'); ?></strong></div>
                    <label><textarea class="timespent-comment maxwidth100onsmartphone" name="timespent-comment" rows="6"></textarea></label>
                </div>
            </div>
            <!-- Modal-Footer -->
            <div class="modal-footer">
                <?php if ($permissiontoAdd > 0) : ?>
                    <div class="wpeo-button timespent-create button-green" value="">
                        <i class="fas fa-save"></i>
                    </div>
                <?php else : ?>
                    <div class="wpeo-button button-grey wpeo-tooltip-event" aria-label="<?php echo $langs->trans('PermissionDenied') ?>">
                        <i class="fas fa-save"></i>
                    </div>
                <?php endif;?>
            </div>
        </div>
    </div>
</div>
<!-- TIMESPENT ADD MODAL END -->

<?php if ($conf->use_javascript_ajax) {
    // Passed working hours.
    $passedWorkingTime = load_passed_time_within_range($firstDayToShow, dol_time_plus_duree($lastDayOfRange, 1, 'd'), $workingHours, $isAvailable);

    print '<tr class="liste_total planned-working-hours">';
    print '<td class="liste_total" colspan="' . $colspan . '">';
    print $langs->trans('Total');
    print '<span class="opacitymediumbycolor">  - ';
    print $langs->trans('SpentWorkingHoursMonth', dol_print_date($firstDayToShow, 'dayreduceformat'), dol_print_date($lastDayOfRange, 'dayreduceformat'));
    print ' : <strong>' . (($passedWorkingTime['minutes'] != 0) ? convertSecondToTime($passedWorkingTime['minutes'] * 60, 'allhourmin') : '00:00') . '</strong></span>';
    print '</td>';
    if (!empty($arrayFields['timeconsumed']['checked'])) {
        print '<td class="liste_total right"></td>';
    }

    // Fill days data.
    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $cellCSS          = '';
        $dayInLoop        = dol_time_plus_duree($firstDayToShow, $idw, 'd');
        $passedHoursOnDay = load_passed_time_within_range($dayInLoop, dol_time_plus_duree($firstDayToShow, $idw + 1, 'd'), $workingHours, $isAvailable);
        if (!$isAvailable[$dayInLoop]['morning'] && !$isAvailable[$dayInLoop]['afternoon']) {
            if ($isAvailable[$dayInLoop]['morning_reason'] == 'public_holiday') {
                $cellCSS = 'onholidayallday';
            } elseif ($isAvailable[$dayInLoop]['morning_reason'] == 'week_end') {
                $cellCSS = 'weekend';
            }
        }
        print '<td class="liste_total ' . $idw . ' ' . $cellCSS . '" align="center">';
        print (($passedHoursOnDay['minutes'] != 0) ? convertSecondToTime($passedHoursOnDay['minutes'] * 60, 'allhourmin') : '00:00') . '</div></td>';
    }
    print '<td></td>';
    print '</tr>';

    // Spent hours within dates range.
    $timeSpent = load_time_spent_on_tasks_within_range($firstDayToShow, dol_time_plus_duree($lastDayOfRange, 1, 'd'), $isAvailable, $userTmp->id);

    print '<tr class="liste_total spent-hours-in-range">';
    print '<td class="liste_total" colspan="' . $colspan . '">';
    print $langs->trans('Total');
    $totalConsumedTime = $timeSpent['total'];
    print '<span class="opacitymediumbycolor">  - ' . $langs->trans('ConsumedWorkingHoursMonth', dol_print_date($firstDayToShow, 'dayreduceformat'), dol_print_date($lastDayOfRange, 'dayreduceformat')) . ' : <strong>' . convertSecondToTime($totalConsumedTime, 'allhourmin') . '</strong></span>';
    print '</td>';
    if (!empty($arrayFields['timeconsumed']['checked'])) {
        print '<td class="liste_total right"><strong>' . convertSecondToTime($totalConsumedTime, 'allhourmin') . '</strong></td>';
    }

    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $cellCSS             = '';
        $dayInLoop           = dol_time_plus_duree($firstDayToShow, $idw, 'd');
        $timespentHoursOnDay = load_time_spent_on_tasks_within_range($dayInLoop, dol_time_plus_duree($firstDayToShow, $idw + 1, 'd'), $isAvailable, $userTmp->id);
        if (!$isAvailable[$dayInLoop]['morning'] && !$isAvailable[$dayInLoop]['afternoon']) {
            if ($isAvailable[$dayInLoop]['morning_reason'] == 'public_holiday') {
                $cellCSS = 'onholidayallday';
            } elseif ($isAvailable[$dayInLoop]['morning_reason'] == 'week_end') {
                $cellCSS = 'weekend';
            }
        }
        print '<td class="liste_total bold ' . $idw . ' ' . $cellCSS . '" align="center">';
        print '<div class="totalDay' . $idw . '">' . (($timespentHoursOnDay['minutes'] != 0) ? convertSecondToTime($timespentHoursOnDay['minutes'] * 60, 'allhourmin') : '00:00') . '</div></td>';
    }
    print '<td></td>';
    print '</tr>';

    //Difference between planned & working hours
    $timeSpentDiff = load_difference_between_passed_and_spent_time_within_range($firstDayToShow, dol_time_plus_duree($lastDayOfRange, 1, 'd'), $workingHours, $isAvailable, $userTmp->id);

    print '<tr class="liste_total planned-working-difference">';
    print '<td class="liste_total" colspan="' . $colspan . '">';
    print $langs->trans('Total');
    $diffTotalTime = $timeSpentDiff * 60;
    if ($diffTotalTime < 0) {
        $morecss = colorStringToArray($conf->global->DOLISIRH_EXCEEDED_TIME_SPENT_COLOR);
    } elseif ($diffTotalTime > 0) {
        $morecss = colorStringToArray($conf->global->DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR);
    } elseif ($diffTotalTime == 0) {
        $morecss = colorStringToArray($conf->global->DOLISIRH_PERFECT_TIME_SPENT_COLOR);
    } else {
        $morecss = '';
    }
    print '<span class="opacitymediumbycolor">  - ' . $langs->trans('DiffSpentAndConsumedWorkingHoursMonth', dol_print_date($firstDayToShow, 'dayreduceformat'), dol_print_date($lastDayOfRange, 'dayreduceformat')) . ' : <strong style="color:' . 'rgb(' . $morecss[0] . ',' . $morecss[1] . ',' . $morecss[2] . ');' . '">' . (($diffTotalTime != 0) ? convertSecondToTime(abs($diffTotalTime), 'allhourmin') : '00:00') . '</strong></span>';
    print '</td>';
    if (!empty($arrayFields['timeconsumed']['checked'])) {
        print '<td class="liste_total right" style="color:' . 'rgb(' . $morecss[0] . ',' . $morecss[1] . ',' . $morecss[2] . ');' . '"><strong>' . (($diffTotalTime != 0) ? convertSecondToTime(abs($diffTotalTime), 'allhourmin') : '00:00') . '</strong></td>';
    }

    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $cellCSS              = '';
        $dayInLoop            = dol_time_plus_duree($firstDayToShow, $idw, 'd');
        $timeSpentDiffThisDay = load_difference_between_passed_and_spent_time_within_range($dayInLoop, dol_time_plus_duree($firstDayToShow, $idw + 1, 'd'), $workingHours, $isAvailable, $userTmp->id);
        if (!$isAvailable[$dayInLoop]['morning'] && !$isAvailable[$dayInLoop]['afternoon']) {
            if ($isAvailable[$dayInLoop]['morning_reason'] == 'public_holiday') {
                $cellCSS = 'onholidayallday';
            } elseif ($isAvailable[$dayInLoop]['morning_reason'] == 'week_end') {
                $cellCSS = 'weekend';
            }
        }

        if ($timeSpentDiffThisDay < 0) {
            $morecss = colorStringToArray($conf->global->DOLISIRH_EXCEEDED_TIME_SPENT_COLOR);
        } elseif ($timeSpentDiffThisDay > 0) {
            $morecss = colorStringToArray($conf->global->DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR);
        } elseif ($timeSpentDiffThisDay == 0) {
            $morecss = colorStringToArray($conf->global->DOLISIRH_PERFECT_TIME_SPENT_COLOR);
        }

        print '<td class="liste_total bold ' . $idw . ' ' . $cellCSS;
        print '" align="center" style="color:' . 'rgb(' . $morecss[0] . ',' . $morecss[1] . ',' . $morecss[2] . ');' . '"><div class="' . $idw . '">';
        print (($timeSpentDiffThisDay != 0) ? convertSecondToTime(abs($timeSpentDiffThisDay * 60), 'allhourmin') : '00:00') . '</div></td>';
    }
    print '<td></td>';
    print '</tr>';
}

if (count($tasksArray) == 0) {
    print '<tr><td colspan="' . ($colspan + 2 + $daysInRange) . '"><span class="opacitymedium">' . $langs->trans('NoAssignedTasks') . '</span></td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

// End of page
llxFooter();
$db->close();
