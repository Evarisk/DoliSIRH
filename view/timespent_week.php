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
 *	\file       view/timespent_week.php
 *	\ingroup    dolisirh
 *	\brief      List timespent of tasks per week
 */

// Load DoliSIRH environment.
if (file_exists('../dolisirh.main.inc.php')) {
    require_once __DIR__ . '/../dolisirh.main.inc.php';
} elseif (file_exists('../../dolisirh.main.inc.php')) {
    require_once __DIR__ . '/../../dolisirh.main.inc.php';
} else {
    die('Include of dolisirh main fails');
}

// Libraries
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/holiday/class/holiday.class.php';
if (!empty($conf->categorie->enabled)) {
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcategory.class.php';
	require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
}

require_once __DIR__ . '/../lib/dolisirh_function.lib.php';
require_once __DIR__ . '/../lib/dolisirh_timespent.lib.php';
require_once __DIR__ . '/../class/workinghours.class.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
$langs->loadLangs(array('projects', 'users', 'companies'));

// Get parameters
$action      = GETPOST('action', 'aZ09');
$mode        = GETPOST('mode', 'alpha');
$id          = GETPOST('id', 'int');
$taskid      = GETPOST('taskid', 'int');
$contextpage = GETPOST('contextpage', 'aZ') ?GETPOST('contextpage', 'aZ') : 'perweekcard';

$mine = 0;
if ($mode == 'mine') {
	$mine = 1;
}

$projectid = GETPOSTISSET('id') ? GETPOST('id', 'int', 1) : GETPOST('projectid', 'int');

$hookmanager->initHooks(array('timespentperweeklist'));

// Security check
$permissiontoadd = $user->rights->projet->time;

$socid = 0;
$result = restrictedArea($user, 'projet', $projectid);

$now   = dol_now();
$year  = GETPOST('reyear', 'int') ?GETPOST('reyear', 'int') : (GETPOST('year', 'int') ?GETPOST('year', 'int') : date('Y'));
$month = GETPOST('remonth', 'int') ?GETPOST('remonth', 'int') : (GETPOST('month', 'int') ?GETPOST('month', 'int') : date('m'));
$day   = GETPOST('reday', 'int') ?GETPOST('reday', 'int') : (GETPOST('day', 'int') ?GETPOST('day', 'int') : date('d'));
$week  = GETPOST('week', 'int') ?GETPOST('week', 'int') : date('W');

$day = (int) $day;

$search_usertoprocessid = GETPOST('search_usertoprocessid', 'int');
$search_task_ref        = GETPOST('search_task_ref', 'alpha');
$search_task_label      = GETPOST('search_task_label', 'alpha');
$search_project_ref     = GETPOST('search_project_ref', 'alpha');
$search_thirdparty      = GETPOST('search_thirdparty', 'alpha');

if (!empty($conf->categorie->enabled)) {
	$search_category_array = GETPOST('search_category_' .Categorie::TYPE_PROJECT. '_list', 'array');
}

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');

$startdayarray = dol_get_first_day_week($day, $month, $year);
$prev = $startdayarray;
$prev_year  = $prev['prev_year'];
$prev_month = $prev['prev_month'];
$prev_day   = $prev['prev_day'];
$first_day  = $prev['first_day'];
$first_month = $prev['first_month'];
$first_year = $prev['first_year'];
$week = $prev['week'];

$next = dol_get_next_week($first_day, $week, $first_month, $first_year);
$next_year  = $next['year'];
$next_month = $next['month'];
$next_day   = $next['day'];

// Define firstdaytoshow and lastdaytoshow (warning: lastdaytoshow is last second to show + 1)
$firstdaytoshow = dol_mktime(0, 0, 0, $first_month, $first_day, $first_year);
$lastdayofweek  = dol_time_plus_duree($firstdaytoshow, 6, 'd');


$currentWeek = date('W', $now);
if ($currentWeek == $week) {
    $currentDate   = dol_getdate($now);
    $lastdaytoshow = dol_mktime(0, 0, 0, $currentDate['mon'], $currentDate['mday'], $currentDate['year']);
} else {
    $lastdaytoshow = $lastdayofweek;
}

$daysInRange = dolisirh_num_between_day($firstdaytoshow, $lastdaytoshow, 1);
$daysInWeek  = dolisirh_num_between_day($firstdaytoshow, $lastdayofweek, 1);

if (empty($search_usertoprocessid) || $search_usertoprocessid == $user->id) {
	$usertoprocess = $user;
	$search_usertoprocessid = $usertoprocess->id;
} elseif ($search_usertoprocessid > 0) {
	$usertoprocess = new User($db);
	$usertoprocess->fetch($search_usertoprocessid);
	$search_usertoprocessid = $usertoprocess->id;
} else {
	$usertoprocess = new User($db);
}

$object = new Task($db);

// Extra fields
$extrafields = new ExtraFields($db);

// Definition of fields for list
$arrayfields = array();
$arrayfields['timeconsumed'] = array('label'=>'TimeConsumed', 'checked'=>1, 'enabled'=>1, 'position'=>15);

$arrayfields = dol_sort_array($arrayfields, 'position');

/*
 * Actions
 */

$parameters = array('id' => $id, 'taskid' => $taskid, 'projectid' => $projectid);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
// Purge criteria
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
	$action = '';
	$search_usertoprocessid = $user->id;
	$search_task_ref = '';
	$search_task_label = '';
	$search_project_ref = '';
	$search_thirdparty = '';

	$search_category_array = array();

	// We redefine $usertoprocess
	$usertoprocess = $user;
}
if (GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
	$action = '';
}

if (GETPOST('submitdateselect')) {
	if (GETPOST('remonth', 'int') && GETPOST('reday', 'int') && GETPOST('reyear', 'int')) {
		$daytoparse = dol_mktime(0, 0, 0, GETPOST('remonth', 'int'), GETPOST('reday', 'int'), GETPOST('reyear', 'int'));
	}

	$action = '';
}

if ($action == 'addtime' && $user->rights->projet->lire && GETPOST('assigntask') && GETPOST('formfilteraction') != 'listafterchangingselectedfields') {
	$action = 'assigntask';

	if ($taskid > 0) {
		$result = $object->fetch($taskid, $ref);
		if ($result < 0) {
			$error++;
		}
	} else {
		setEventMessages($langs->transnoentitiesnoconv('ErrorFieldRequired', $langs->transnoentitiesnoconv('Task')), '', 'errors');
		$error++;
	}
	if (!GETPOST('type')) {
		setEventMessages($langs->transnoentitiesnoconv('ErrorFieldRequired', $langs->transnoentitiesnoconv('Type')), '', 'errors');
		$error++;
	}

	if (!$error) {
		$idfortaskuser = $usertoprocess->id;
		$result = $object->add_contact($idfortaskuser, GETPOST('type'), 'internal');

		if ($result >= 0 || $result == -2) {	// Contact add ok or already contact of task
			// Test if we are already contact of the project (should be rare but sometimes we can add as task contact without being contact of project, like when admin user has been removed from contact of project)
			$sql = 'SELECT ec.rowid FROM '.MAIN_DB_PREFIX.'element_contact as ec, '.MAIN_DB_PREFIX.'c_type_contact as tc WHERE tc.rowid = ec.fk_c_type_contact';
			$sql .= ' AND ec.fk_socpeople = '.((int) $idfortaskuser). ' AND ec.element_id = ' .((int) $object->fk_project)." AND tc.element = 'project' AND source = 'internal'";
			$resql = $db->query($sql);
			if ($resql) {
				$obj = $db->fetch_object($resql);
				if (!$obj) {	// User is not already linked to project, so we will create link to first type
					$project = new Project($db);
					$project->fetch($object->fk_project);
					// Get type
					$listofprojcontact = $project->liste_type_contact('internal');

					if (count($listofprojcontact)) {
						$typeforprojectcontact = reset(array_keys($listofprojcontact));
						$result = $project->add_contact($idfortaskuser, $typeforprojectcontact, 'internal');
					}
				}
			} else {
				dol_print_error($db);
			}
		}
	}

	if ($result < 0) {
		$error++;
		if ($object->error == 'DB_ERROR_RECORD_ALREADY_EXISTS') {
			$langs->load('errors');
			setEventMessages($langs->trans('ErrorTaskAlreadyAssigned'), null, 'warnings');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}

	if (!$error) {
		setEventMessages('TaskAssignedToEnterTime', null);
		$taskid = 0;
	}

	$action = '';
}

if ($action == 'showOnlyFavoriteTasks') {
    $data = json_decode(file_get_contents('php://input'), true);

    $showOnlyFavoriteTasks = $data['showOnlyFavoriteTasks'];

    $tabparam['DOLISIRH_SHOW_ONLY_FAVORITE_TASKS'] = $showOnlyFavoriteTasks;

    dol_set_user_param($db, $conf, $user, $tabparam);
}

if ($action == 'showOnlyTasksWithTimeSpent') {
    $data = json_decode(file_get_contents('php://input'), true);

    $showOnlyTasksWithTimeSpent = $data['showOnlyTasksWithTimeSpent'];

    $tabparam['DOLISIRH_SHOW_ONLY_TASKS_WITH_TIMESPENT'] = $showOnlyTasksWithTimeSpent;

    dol_set_user_param($db, $conf, $user, $tabparam);
}

if ($action == 'addTimeSpent' && $permissiontoadd) {
    $data = json_decode(file_get_contents('php://input'), true);

    $taskID    = $data['taskID'];
    $timestamp = $data['timestamp'];
    $datehour  = $data['datehour'];
    $datemin   = $data['datemin'];
    $comment   = $data['comment'];
    $hour      = (int) $data['hour'];
    $min       = (int) $data['min'];

    $object->fetch($taskID);

    $object->timespent_date     = $timestamp;
    $object->timespent_datehour = $timestamp + ($datehour * 3600) + ($datemin * 60);
    if ($datehour > 0 || $datemin > 0) {
        $object->timespent_withhour = 1;
    }
    $object->timespent_note     = $comment;
    $object->timespent_duration = ($hour * 3600) + ($min * 60);
    $object->timespent_fk_user  = $user->id;

    if ($object->timespent_duration > 0) {
        $object->addTimeSpent($user);
    }
}

/*
 * View
 */

$form = new Form($db);
$formother = new FormOther($db);
$formcompany = new FormCompany($db);
$formproject = new FormProjets($db);
$projectstatic = new Project($db);
$project = new Project($db);
$taskstatic = new Task($db);
$thirdpartystatic = new Societe($db);
$holiday = new Holiday($db);

$title    = $langs->trans('TimeSpent');
$help_url = 'FR:Module_DoliSIRH';

$projectsListId = $projectstatic->getProjectsAuthorizedForUser($usertoprocess, (empty($usertoprocess->id) ? 2 : 0), 1); // Return all project i have permission on (assigned to me+public). I want my tasks and some of my task may be on a public projet that is not my project
//var_dump($projectsListId);
if ($id) {
	$project->fetch($id);
	$project->fetch_thirdparty();
}

$onlyopenedproject = 1; // or -1
$morewherefilter = '';

if ($search_project_ref) {
	$morewherefilter .= natural_search(array('p.ref', 'p.title'), $search_project_ref);
}
if ($search_task_ref) {
	$morewherefilter .= natural_search('t.ref', $search_task_ref);
}
if ($search_task_label) {
	$morewherefilter .= natural_search(array('t.ref', 't.label'), $search_task_label);
}
if ($search_thirdparty) {
	$morewherefilter .= natural_search('s.nom', $search_thirdparty);
}
if ($search_declared_progress) {
	$morewherefilter .= natural_search('t.progress', $search_declared_progress, 1);
}
if (!empty($conf->categorie->enabled)) {
	$morewherefilter .= Categorie::getFilterSelectQuery(Categorie::TYPE_PROJECT, 'p.rowid', $search_category_array);
}

$timeArray  = array('year' => $year, 'month' => $month, 'week' => $week, 'day' => $day);
$tasksarray = [];
$tasksarray = doliSirhGetTasksArray(0, 0, ($project->id ?: 0), $socid, 0, $search_project_ref, $onlyopenedproject, $morewherefilter, ($search_usertoprocessid ? $search_usertoprocessid : 0), 0, $extrafields,0,array(), 0, $timeArray, 'week');

$projectsrole = $taskstatic->getUserRolesForProjectsOrTasks($usertoprocess, 0, ($project->id ? $project->id : 0), 0, $onlyopenedproject);
$tasksrole = $taskstatic->getUserRolesForProjectsOrTasks(0, $usertoprocess, ($project->id ? $project->id : 0), 0, $onlyopenedproject);

saturne_header(0,'', $title, $help_url);

//print_barre_liste($title, $page, $_SERVER["PHP_SELF"], "", $sortfield, $sortorder, "", $num, '', 'project');

$param = '';
$param .= ($mode ? '&mode='.urlencode($mode) : '');
$param .= ($search_project_ref ? '&search_project_ref='.urlencode($search_project_ref) : '');
$param .= ($search_usertoprocessid > 0 ? '&search_usertoprocessid='.urlencode($search_usertoprocessid) : '');
$param .= ($search_thirdparty ? '&search_thirdparty='.urlencode($search_thirdparty) : '');
$param .= ($search_task_ref ? '&search_task_ref='.urlencode($search_task_ref) : '');
$param .= ($search_task_label ? '&search_task_label='.urlencode($search_task_label) : '');

// Show navigation bar
$nav = '<a class="inline-block valignmiddle" href="?year='.$prev_year. '&month=' .$prev_month. '&day=' .$prev_day.$param.'">'.img_previous($langs->trans('Previous'))."</a>\n";
$nav .= ' <span id="month_name">'.dol_print_date(dol_mktime(0, 0, 0, $first_month, $first_day, $first_year), '%Y'). ', ' .$langs->trans('WeekShort'). ' ' .$week." </span>\n";
$nav .= '<a class="inline-block valignmiddle" href="?year='.$next_year. '&month=' .$next_month. '&day=' .$next_day.$param.'">'.img_next($langs->trans('Next'))."</a>\n";
$nav .= ' '.$form->selectDate(-1, '', 0, 0, 2, 'addtime', 1, 1).' ';
$nav .= ' <button type="submit" name="submitdateselect" value="x" class="bordertransp"><span class="fa fa-search"></span></button>';

print '<form name="addtime" id="addtimeform" method="POST" action="'.$_SERVER['PHP_SELF'].'>';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="addtime">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
print '<input type="hidden" name="mode" value="'.$mode.'">';
print '<input type="hidden" name="day" value="'.$day.'">';
print '<input type="hidden" name="month" value="'.$month.'">';
print '<input type="hidden" name="year" value="'.$year.'">';

$head = timespent_prepare_head($mode, $usertoprocess);
print dol_get_fiche_head($head, 'inputperweek', $langs->trans('TimeSpent'), -1, 'clock');

// Show description of content
print '<div class="hideonsmartphone opacitymedium">';
if ($mine || ($usertoprocess->id == $user->id)) {
    $tooltipTaskInfo .= $langs->trans('MyTasksDesc').'.'.($onlyopenedproject ? ' '. '<b style="color: red">' . $langs->trans('OnlyOpenedProject') : '') . '</b><br>';
} elseif (empty($usertoprocess->id) || $usertoprocess->id < 0) {
    if ($user->rights->projet->all->lire && !$socid) {
        $tooltipTaskInfo .= $langs->trans('ProjectsDesc') . '.' . ($onlyopenedproject ? ' ' . '<b style="color: red">' . $langs->trans('OnlyOpenedProject') : '') . '</b><br>';
    } else {
        $tooltipTaskInfo .= $langs->trans('ProjectsPublicTaskDesc') . '.' . ($onlyopenedproject ? ' ' . '<b style="color: red">' . $langs->trans('OnlyOpenedProject') : '') . '</b><br>';
    }
}
if ($mine || ($usertoprocess->id == $user->id)) {
    $tooltipTaskInfo .= $langs->trans('OnlyYourTaskAreVisible').'<br>';
} else {
    $tooltipTaskInfo .= $langs->trans('AllTaskVisibleButEditIfYouAreAssigned').'<br>';
}
print '</div>';

print dol_get_fiche_end();

print '<div class="floatright right'.($conf->dol_optimize_smallscreen ? ' centpercent' : '').'">'.$nav.'</div>'; // We move this before the assign to components so, the default submit button is not the assign to.

print '<div class="colorbacktimesheet float valignmiddle">';
$titleassigntask = $langs->transnoentities('AssignTaskToMe');
if ($usertoprocess->id != $user->id) {
	$titleassigntask = $langs->transnoentities('AssignTaskToUser', $usertoprocess->getFullName($langs));
}
print '<div class="taskiddiv inline-block">';
print img_picto('', 'projecttask', 'class="pictofixedwidth"');
$formproject->selectTasks($socid ? $socid : -1, $taskid, 'taskid', 32, 0, '-- '.$langs->trans('ChooseANotYetAssignedTask').' --', 1, 0, 0, '', '', 'all', $usertoprocess);
print '</div>';
print ' ';
print $formcompany->selectTypeContact($object, 46, 'type', 'internal', 'rowid', 0, 'maxwidth150onsmartphone');
print '<input type="submit" class="button valignmiddle smallonsmartphone" name="assigntask" value="'.dol_escape_htmltag($titleassigntask).'">';
print '</div>';

print '<div class="clearboth" style="padding-bottom: 20px;"></div>';
$tooltipTaskInfo .= img_help(1, $langs->trans('KeyEvent')) .  ' ' . $langs->trans('KeyEventTips') . '<br><br>';
if ($user->conf->DOLISIRH_SHOW_ONLY_FAVORITE_TASKS > 0) {
    $tooltipTaskInfo .= '<div class="opacitymedium"><i class="fas fa-exclamation-triangle"></i>'.' '.$langs->trans('WarningShowOnlyFavoriteTasks').'</div>';
}

print '<div class="clearboth" style="padding-bottom: 20px;"></div>';

// Get if user is available or not for each day
$isavailable = array();
for ($idw = 0; $idw < $daysInWeek; $idw++) {
    $dayInLoop =  dol_time_plus_duree($firstdaytoshow, $idw, 'd');
    if (is_day_available($dayInLoop, $user->id)) {
        $isavailable[$dayInLoop] = array('morning'=>1, 'afternoon'=>1);
    } else if (date('N', $dayInLoop) >= 6) {
        $isavailable[$dayInLoop] = array('morning'=>false, 'afternoon'=>false, 'morning_reason'=>'week_end', 'afternoon_reason'=>'week_end');
    } else {
        $isavailable[$dayInLoop] = array('morning'=>false, 'afternoon'=>false, 'morning_reason'=>'public_holiday', 'afternoon_reason'=>'public_holiday');
    }
}

$moreforfilter = '';

// If the user can view user other than himself
$moreforfilter .= '<div class="divsearchfield">';
$moreforfilter .= '<div class="inline-block hideonsmartphone"></div>';

if (empty($user->rights->user->user->lire)) {
    $includeonly = array($user->id);
}

$moreforfilter .= img_picto($langs->trans('Filter').' '.$langs->trans('User'), 'user', 'class="paddingright pictofixedwidth"').$form->select_dolusers($search_usertoprocessid ?: $usertoprocess->id, 'search_usertoprocessid', $user->rights->user->user->lire ? 0 : 0, null, 0, $includeonly, null, 0, 0, 0, ' AND u.employee = 1', 0, '', 'maxwidth200', 1);
$moreforfilter .= '</div>';

if (empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT)) {
	$moreforfilter .= '<div class="divsearchfield">';
	$moreforfilter .= '<div class="inline-block"></div>';
	$moreforfilter .= img_picto($langs->trans('Filter').' '.$langs->trans('Project'), 'project', 'class="paddingright pictofixedwidth"').'<input type="text" name="search_project_ref" class="maxwidth100" value="'.dol_escape_htmltag($search_project_ref).'">';
	$moreforfilter .= '</div>';

	$moreforfilter .= '<div class="divsearchfield">';
	$moreforfilter .= '<div class="inline-block"></div>';
	$moreforfilter .= img_picto($langs->trans('Filter').' '.$langs->trans('ThirdParty'), 'company', 'class="paddingright pictofixedwidth"').'<input type="text" name="search_thirdparty" class="maxwidth100" value="'.dol_escape_htmltag($search_thirdparty).'">';
	$moreforfilter .= '</div>';
}

// Filter on categories
if (!empty($conf->categorie->enabled) && $user->rights->categorie->lire) {
	$formcategory = new FormCategory($db);
	$moreforfilter .= $formcategory->getFilterBox(Categorie::TYPE_PROJECT, $search_category_array);
}

if (!empty($moreforfilter)) {
	print '<div class="liste_titre liste_titre_bydiv centpercent">';
	print $moreforfilter;
	$parameters = array();
	$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters); // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;
	print '</div>';
}

print '<div class="div-table-responsive">';
print '<table class="tagtable liste'.($moreforfilter ? ' listwithfilterbefore' : '').'" id="tablelines3">'."\n";

print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><input type="text" size="4" name="search_task_label" value="'.dol_escape_htmltag($search_task_label).'"></td>';
// TASK fields
if (!empty($arrayfields['timeconsumed']['checked'])) {
	print '<td class="liste_titre"></td>';
}
for ($idw = 0; $idw < $daysInRange; $idw++) {
	print '<td class="liste_titre"></td>';
}
// Action column
print '<td class="liste_titre nowrap right">';
$searchpicto = $form->showFilterAndCheckAddButtons(0);
print $searchpicto;
print '</td>';
print "</tr>\n";

print '<tr class="liste_titre">';
print '<th>' . $form->textwithpicto($langs->trans('Task'), $tooltipTaskInfo);
print ' <i class="fas fa-star"></i>';
print '<input type="checkbox"  class="show-only-favorite-tasks"'. ($user->conf->DOLISIRH_SHOW_ONLY_FAVORITE_TASKS ? ' checked' : '').' >';
print $form->textwithpicto('', $langs->trans('ShowOnlyFavoriteTasks'));
print ' <i class="fas fa-clock"></i>';
print '<input type="checkbox"  class="show-only-tasks-with-timespent"'. ($user->conf->DOLISIRH_SHOW_ONLY_TASKS_WITH_TIMESPENT ? ' checked' : '').' >';
print  $form->textwithpicto('', $langs->trans('ShowOnlyTasksWithTimeSpent'));
print '</th>';
// TASK fields
if (!empty($arrayfields['timeconsumed']['checked'])) {
	print '<th class="right maxwidth75 maxwidth100">'.$langs->trans('TimeSpent').($usertoprocess->firstname ? '<br><span class="nowraponall">'.$usertoprocess->getNomUrl(-2).'<span class="opacitymedium paddingleft">'.dol_trunc($usertoprocess->firstname, 10).'</span></span>' : '').'</th>';
}
for ($idw = 0; $idw < $daysInRange; $idw++) {
    $dayInLoop =  dol_time_plus_duree($firstdaytoshow, $idw, 'd');

    $cellCSS = '';

    if (!$isavailable[$dayInLoop]['morning'] && !$isavailable[$dayInLoop]['afternoon']) {
        if ($isavailable[$dayInLoop]['morning_reason'] == 'public_holiday') {
            $cellCSS = 'onholidayallday';
        } else if ($isavailable[$dayInLoop]['morning_reason'] == 'week_end') {
            $cellCSS = 'weekend';
        }
    } else {
        $cellCSS = '';
    }

	print '<th width="6%" class="center bold hide'.$idw.$cellCSS.'">';
	print dol_print_date($dayInLoop, '%a');
	$splitted_date = preg_split('/\//', dol_print_date($dayInLoop, 'day'));
	$day = $splitted_date[0];
	$month = $splitted_date[1];
	$year = $splitted_date[2];
	print ' <a href="timespent_day.php?year='. $year .'&month='. $month .'&day='. $day .'&search_usertoprocessid=' . $usertoprocess->id .'"><i class="fas fa-external-link-alt"></i></a>';
	print '<br>'.dol_print_date($dayInLoop, 'dayreduceformat').'</th>';
}
//print '<td></td>';
print_liste_field_titre('', $_SERVER['PHP_SELF'], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');

print "</tr>\n";

$colspan = 1 + (empty($conf->global->PROJECT_TIMESHEET_DISABLEBREAK_ON_PROJECT) ? 0 : 2);

$workinghours = new Workinghours($db);
$workingHours = $workinghours->fetchCurrentWorkingHours($usertoprocess->id, 'user');

$planned_working_time = load_planned_time_within_range($firstdaytoshow, dol_time_plus_duree($lastdayofweek, 1, 'd'), $workingHours, $isavailable);

$workinghoursWeek = 0;

if ($conf->use_javascript_ajax) {
	print '<tr class="liste_total">';
	print '<td class="liste_total" colspan="'.($colspan + $addcolspan).'">';
	print $langs->trans('Total');
    print '<span class="opacitymediumbycolor">  - ';
	print $langs->trans('ExpectedWorkingHoursWeek', dol_print_date($firstdaytoshow, 'dayreduceformat'), dol_print_date($lastdayofweek, 'dayreduceformat'));
    print ' : <strong><a href="'. DOL_URL_ROOT . '/custom/dolisirh/view/workinghours_card.php?id=' . $usertoprocess->id.'" target="_blank">';
    print (($planned_working_time['minutes'] != 0) ? convertSecondToTime($planned_working_time['minutes'] * 60, 'allhourmin') : '00:00').'</a></strong></span>';
	print '</td>';
	if (!empty($arrayfields['timeconsumed']['checked'])) {
		print '<td class="liste_total"></td>';
	}

    //Fill days data
	for ($idw = 0; $idw < $daysInRange; $idw++) {
        $dayInLoop =  dol_time_plus_duree($firstdaytoshow, $idw, 'd');
        $planned_hours_on_day = load_planned_time_within_range($dayInLoop, dol_time_plus_duree($firstdaytoshow, $idw + 1, 'd'), $workingHours, $isavailable);

        $cellCSS = '';

        if (!$isavailable[$dayInLoop]['morning'] && !$isavailable[$dayInLoop]['afternoon']) {
            if ($isavailable[$dayInLoop]['morning_reason'] == 'public_holiday') {
                $cellCSS = 'onholidayallday';
            } else if ($isavailable[$dayInLoop]['morning_reason'] == 'week_end') {
                $cellCSS = 'weekend';
            }
        } else {
            $cellCSS = '';
        }

        print '<td class="liste_total '.$idw.' ' . $cellCSS. '" align="center">';
        print '<div class="'.$idw.'">'.(($planned_hours_on_day['minutes'] != 0) ? convertSecondToTime($planned_hours_on_day['minutes'] * 60, 'allhourmin') : '00:00').'</div></td>';
	}
	print '<td></td>';
	print '</tr>';
}

// By default, we can edit only tasks we are assigned to
$restrictviewformytask = ((!isset($conf->global->PROJECT_TIME_SHOW_TASK_NOT_ASSIGNED)) ? 2 : $conf->global->PROJECT_TIME_SHOW_TASK_NOT_ASSIGNED);

$j = 0;
$level = 0;

//Show tasks lines
$timeSpentOnTasks = load_time_spent_on_tasks_within_range($firstdaytoshow, dol_time_plus_duree($lastdaytoshow, 1, 'd'), $isavailable, $usertoprocess->id);

doliSirhTaskLinesWithinRange($j, $firstdaytoshow, $lastdaytoshow, $usertoprocess, 0, $tasksarray, $level, $projectsrole, $tasksrole, $mine, $restrictviewformytask, $isavailable, 0, $arrayfields, $extrafields, $timeSpentOnTasks); ?>

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
                            <span><span class="timespent-date"></span> <input class="flat maxwidth50 timespent-datehour" type="number" placeholder="H" min="0" max="23"> : <input class="flat maxwidth50 timespent-datemin" type="number" placeholder="mn" min="0" max="59"></span>
                        </div>
                        <div>
                            <div class="title"><strong><i class="far fa-clock"></i> <?php echo $langs->trans('Duration'); ?></strong></div>
                            <span><input class="flat maxwidth50 timespent-hour" type="number" placeholder="H" min="0" max="23"> : <input class="flat maxwidth50 timespent-min" type="number" placeholder="mn" min="0" max="59"></span>
                        </div>
                    </div>
                    <br/>
                    <div class="title"><strong><i class="far fa-comment-alt"></i> <?php echo $langs->trans('Comment'); ?></strong></div>
                    <textarea class="timespent-comment maxwidth100onsmartphone" name="timespent-comment" rows="6"></textarea>
                </div>
            </div>
            <!-- Modal-Footer -->
            <div class="modal-footer">
                <?php if ($permissiontoadd > 0) : ?>
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
    //Passed working hours
    $passed_working_time = load_passed_time_within_range($firstdaytoshow, dol_time_plus_duree($lastdaytoshow, 1, 'd'), $workingHours, $isavailable);

    print '<tr class="liste_total">';
    print '<td class="liste_total" colspan="'.($colspan + $addcolspan).'">';
    print $langs->trans('Total');

    print '<span class="opacitymediumbycolor">  - ';
    print $langs->trans('SpentWorkingHoursMonth', dol_print_date($firstdaytoshow, 'dayreduceformat'), dol_print_date($lastdaytoshow, 'dayreduceformat'));
    print ' : <strong>'.(($passed_working_time['minutes'] != 0) ? convertSecondToTime($passed_working_time['minutes'] * 60, 'allhourmin') : '00:00').'</strong></span>';
    print '</td>';
    if (!empty($arrayfields['timeconsumed']['checked'])) {
        print '<td class="liste_total right"></td>';
    }

    //Fill days data
    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $dayInLoop =  dol_time_plus_duree($firstdaytoshow, $idw, 'd');
        $passed_hours_on_day = load_passed_time_within_range($dayInLoop, dol_time_plus_duree($firstdaytoshow, $idw + 1, 'd'), $workingHours, $isavailable);

        $cellCSS = '';

        if (!$isavailable[$dayInLoop]['morning'] && !$isavailable[$dayInLoop]['afternoon']) {
            if ($isavailable[$dayInLoop]['morning_reason'] == 'public_holiday') {
                $cellCSS = 'onholidayallday';
            } else if ($isavailable[$dayInLoop]['morning_reason'] == 'week_end') {
                $cellCSS = 'weekend';
            }
        } else {
            $cellCSS = '';
        }
        print '<td class="liste_total '.$idw.' ' . $cellCSS. '" align="center">';
        print '<div class="'.$idw.'">'.(($passed_hours_on_day['minutes'] != 0) ? convertSecondToTime($passed_hours_on_day['minutes'] * 60, 'allhourmin') : '00:00').'</div></td>';
    }
    print '<td></td>';
    print '</tr>';

    // Spent hours within dates range
    print '<tr class="liste_total">';
    print '<td class="liste_total" colspan="'.($colspan + $addcolspan).'">';
    print $langs->trans('Total');

    $timeSpent = load_time_spent_on_tasks_within_range($firstdaytoshow, dol_time_plus_duree($lastdaytoshow, 1, 'd'), $isavailable, $usertoprocess->id);

    $totalconsumedtime = $timeSpent['total'];
    print '<span class="opacitymediumbycolor">  - '.$langs->trans('ConsumedWorkingHoursMonth', dol_print_date($firstdaytoshow, 'dayreduceformat'), dol_print_date($lastdaytoshow, 'dayreduceformat')).' : <strong>'.convertSecondToTime($totalconsumedtime, 'allhourmin').'</strong></span>';
    print '</td>';
    if (!empty($arrayfields['timeconsumed']['checked'])) {
        print '<td class="liste_total right"><strong>'.convertSecondToTime($totalconsumedtime, 'allhourmin').'</strong></td>';
    }

    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $dayInLoop =  dol_time_plus_duree($firstdaytoshow, $idw, 'd');
        $timespent_hours_on_day = load_time_spent_on_tasks_within_range($dayInLoop, dol_time_plus_duree($firstdaytoshow, $idw + 1, 'd'), $isavailable, $usertoprocess->id);

        echo '<pre>'; print_r( $timespent_hours_on_day ); echo '</pre>';


        $cellCSS = '';

        if (!$isavailable[$dayInLoop]['morning'] && !$isavailable[$dayInLoop]['afternoon']) {
            if ($isavailable[$dayInLoop]['morning_reason'] == 'public_holiday') {
                $cellCSS = 'onholidayallday';
            } else if ($isavailable[$dayInLoop]['morning_reason'] == 'week_end') {
                $cellCSS = 'weekend';
            }
        } else {
            $cellCSS = '';
        }

        print '<td class="liste_total bold '.$idw.' ' . $cellCSS. '" align="center">';
        print '<div class="totalDay'.$idw.'">'.(($timespent_hours_on_day['minutes'] != 0) ? convertSecondToTime($timespent_hours_on_day['minutes'] * 60, 'allhourmin') : '00:00').'</div></td>';
    }
    print '<td></td>';
    print '</tr>';

    //Difference between planned & working hours
    $timeSpentDiff = load_difference_between_passed_and_spent_time_within_range($firstdaytoshow, dol_time_plus_duree($lastdaytoshow, 1, 'd'), $workingHours, $isavailable, $usertoprocess->id);

    print '<tr class="liste_total planned-working-difference">';
    print '<td class="liste_total" colspan="'.($colspan + $addcolspan).'">';
    print $langs->trans('Total');
    $difftotaltime = $timeSpentDiff * 60;
    if ($difftotaltime < 0) {
        $morecss = colorStringToArray($conf->global->DOLISIRH_EXCEEDED_TIME_SPENT_COLOR);
    } elseif ($difftotaltime > 0) {
        $morecss = colorStringToArray($conf->global->DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR);
    } elseif ($difftotaltime == 0) {
        $morecss = colorStringToArray($conf->global->DOLISIRH_PERFECT_TIME_SPENT_COLOR);
    }
    print '<span class="opacitymediumbycolor">  - '.$langs->trans('DiffSpentAndConsumedWorkingHoursMonth', dol_print_date($firstdaytoshow, 'dayreduceformat'), dol_print_date($lastdaytoshow, 'dayreduceformat')).' : <strong style="color:'.'rgb('.$morecss[0].','.$morecss[1].','.$morecss[2].')'.'">'.(($difftotaltime != 0) ? convertSecondToTime(abs($difftotaltime), 'allhourmin') : '00:00').'</strong></span>';
    print '</td>';
    if (!empty($arrayfields['timeconsumed']['checked'])) {
        print '<td class="liste_total right" style="color:'.'rgb('.$morecss[0].','.$morecss[1].','.$morecss[2].')'.'"><strong>'.(($difftotaltime != 0) ? convertSecondToTime(abs($difftotaltime), 'allhourmin') : '00:00').'</strong></td>';
    }

    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $dayInLoop = dol_time_plus_duree($firstdaytoshow, $idw, 'd');
        $timeSpentDiffThisDay = load_difference_between_passed_and_spent_time_within_range(dol_time_plus_duree($firstdaytoshow, $idw, 'd'), dol_time_plus_duree($firstdaytoshow, $idw + 1, 'd'), $workingHours, $isavailable, $usertoprocess->id);

        if ($timeSpentDiffThisDay < 0) {
            $morecss = colorStringToArray($conf->global->DOLISIRH_EXCEEDED_TIME_SPENT_COLOR);
        } elseif ($timeSpentDiffThisDay > 0) {
            $morecss = colorStringToArray($conf->global->DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR);
        } elseif ($timeSpentDiffThisDay == 0) {
            $morecss = colorStringToArray($conf->global->DOLISIRH_PERFECT_TIME_SPENT_COLOR);
        }

        $cellCSS = '';

        if (!$isavailable[$dayInLoop]['morning'] && !$isavailable[$dayInLoop]['afternoon']) {

            if ($isavailable[$dayInLoop]['morning_reason'] == 'public_holiday') {
                $cellCSS = 'onholidayallday';
            } else if ($isavailable[$dayInLoop]['morning_reason'] == 'week_end') {
                $cellCSS = 'weekend';
            }
        } else {
            $cellCSS = '';
        }

        print '<td class="liste_total bold '.$idw. ' ' . $cellCSS;
        print '" align="center" style="color:'.'rgb('.$morecss[0].','.$morecss[1].','.$morecss[2].')'.'"><div class="'.$idw.'">';
        print (($timeSpentDiffThisDay != 0) ? convertSecondToTime(abs($timeSpentDiffThisDay*60), 'allhourmin') : '00:00').'</div></td>';
    }
    print '<td></td>';
    print '</tr>';
}

if (count($tasksarray) == 0) {
    print '<tr><td colspan="' . ($colspan + 2 + $daysInRange) . '"><span class="opacitymedium">'.$langs->trans('NoAssignedTasks').'</span></td></tr>';
}

print '</table>';
print '</div>';

print '<input type="hidden" id="numberOfLines" name="numberOfLines" value="'.count($tasksarray).'"/>'."\n";

print '</form>';

// End of page
llxFooter();
$db->close();
