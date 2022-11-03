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
 *   	\file       view/timesheet/timesheet_card.php
 *		\ingroup    dolisirh
 *		\brief      Page to create/edit/view timesheet
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/holiday/class/holiday.class.php';

require_once __DIR__ . '/../../class/timesheet.class.php';
require_once __DIR__ . '/../../class/dolisirhdocuments/timesheetdocument.class.php';
require_once __DIR__ . '/../../class/workinghours.class.php';
require_once __DIR__ . '/../../lib/dolisirh_timesheet.lib.php';
require_once __DIR__ . '/../../lib/dolisirh_function.lib.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $mysoc, $user;

// Load translation files required by the page
$langs->loadLangs(array("dolisirh@dolisirh", "other"));

// Get parameters
$id                  = GETPOST('id', 'int');
$ref                 = GETPOST('ref', 'alpha');
$action              = GETPOST('action', 'aZ09');
$confirm             = GETPOST('confirm', 'alpha');
$cancel              = GETPOST('cancel', 'aZ09');
$contextpage         = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'timesheetcard'; // To manage different context of search
$backtopage          = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');
$lineid              = GETPOST('lineid', 'int');
$year                = (GETPOST("year", 'int') ? GETPOST("year", "int") : date("Y"));
$month               = (GETPOST("month", 'int') ? GETPOST("month", "int") : date("m"));
$day                 = (GETPOST("day", 'int') ? GETPOST("day", "int") : date("d"));

// Initialize technical objects
$object            = new TimeSheet($db);
$objectline        = new TimeSheetLine($db);
$signatory         = new TimeSheetSignature($db);
$timesheetdocument = new TimeSheetDocument($db);
$extrafields       = new ExtraFields($db);
$project           = new Project($db);
$product           = new Product($db);
$workinghours      = new Workinghours($db);
$holiday           = new Holiday($db);
$task              = new Task($db);
$usertmp           = new User($db);

$hookmanager->initHooks(array('timesheetcard', 'globalcard')); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criterias
$search_all = GETPOST("search_all", 'alpha');
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha')) {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
}

if (empty($action) && empty($id) && empty($ref)) {
	$action = 'view';
}

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be include, not include_once.

// There is several ways to check permission.
$permissiontoread   = $user->rights->dolisirh->timesheet->read;
$permissiontoadd    = $user->rights->dolisirh->timesheet->write; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
$permissiontodelete = $user->rights->dolisirh->timesheet->delete || ($permissiontoadd && isset($object->status) && $object->status == $object::STATUS_DRAFT);
$permissionnote     = $user->rights->dolisirh->timesheet->write; // Used by the include of actions_setnotes.inc.php

$upload_dir = $conf->dolisirh->multidir_output[isset($object->entity) ? $object->entity : 1];

// Security check (enable the most restrictive one)
if (empty($conf->dolisirh->enabled)) accessforbidden();
if (!$permissiontoread) accessforbidden();

/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	$error = 0;

	$backurlforlist = dol_buildpath('/dolisirh/view/timesheet/timesheet_list.php', 1);

	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
			if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
				$backtopage = $backurlforlist;
			} else {
				$backtopage = dol_buildpath('/dolisirh/view/timesheet/timesheet_card.php', 1).'?id='.((!empty($id) && $id > 0) ? $id : '__ID__');
			}
		}
	}

	$triggermodname = 'TIMESHEET_MODIFY'; // Name of trigger action code to execute when we modify record

	if (($action == 'add' || $action == 'update') && $permissiontoadd && !$cancel) {
		$usertmp->fetch(GETPOST('fk_user_assign'));
		$date_start = dol_mktime(0, 0, 0, GETPOST('date_startmonth', 'int'), GETPOST('date_startday', 'int'), GETPOST('date_startyear', 'int'));
		$date_end   = dol_mktime(0, 0, 0, GETPOST('date_endmonth', 'int'), GETPOST('date_endday', 'int'), GETPOST('date_endyear', 'int'));
		$filter = ' AND ptt.task_date BETWEEN ' . "'" .dol_print_date($date_start, 'dayrfc') . "'" . ' AND ' . "'" . dol_print_date($date_end, 'dayrfc'). "'";
		$alltimespent = $task->fetchAllTimeSpent($usertmp, $filter);
		foreach ($alltimespent as $timespent) {
			$task->fetchObjectLinked(null, '', $timespent->timespent_id, 'project_task_time');
			if (isset($task->linkedObjects['dolisirh_timesheet'])) {
				$error++;
			}
		}

		if ($date_start > $date_end) {
			setEventMessages($langs->trans('ErrorDateTimeSheet', dol_print_date($date_start, 'dayreduceformat'), dol_print_date($date_end, 'dayreduceformat')), null, 'errors');
			if ($action == 'add') {
				$action = 'create';
			} elseif ($action == 'update') {
				$action = 'edit';
			}
		}

		if ($conf->global->DOLISIRH_TIMESHEET_CHECK_DATE_END > 0) {
			if ($date_end > dol_now()) {
				setEventMessages($langs->trans('ErrorDateEndTimeSheet', dol_print_date($date_end, 'dayreduceformat'), dol_print_date(dol_now(), 'dayreduceformat')), null, 'errors');
				if ($action == 'add') {
					$action = 'create';
				} elseif ($action == 'update') {
					$action = 'edit';
				}
			}
		}

		if ($error > 0) {
			setEventMessages($langs->trans('ErrorLinkedElementTimeSheetTimeSpent', $usertmp->getFullName($langs), dol_print_date($date_start, 'dayreduceformat'), dol_print_date($date_end, 'dayreduceformat')), null, 'errors');
			if ($action == 'add') {
				$action = 'create';
			} elseif ($action == 'update') {
				$action = 'edit';
			}
		}
	}

	// Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
	$conf->global->MAIN_DISABLE_PDF_AUTOUPDATE = 1;
	include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

    // Action to build doc
    if ($action == 'builddoc' && $permissiontoadd) {
        $outputlangs = $langs;
        $newlang     = '';

        if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id', 'aZ09')) $newlang = GETPOST('lang_id', 'aZ09');
        if ( ! empty($newlang)) {
            $outputlangs = new Translate("", $conf);
            $outputlangs->setDefaultLang($newlang);
        }

        // To be sure vars is defined
        if (empty($hidedetails)) $hidedetails = 0;
        if (empty($hidedesc)) $hidedesc       = 0;
        if (empty($hideref)) $hideref         = 0;
        if (empty($moreparams)) $moreparams   = null;

        $model = GETPOST('model', 'alpha');

        $moreparams['object'] = $object;
        $moreparams['user']   = $user;

        $result = $timesheetdocument->generateDocument($model, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
        if ($result <= 0) {
            setEventMessages($object->error, $object->errors, 'errors');
            $action = '';
        } else {
            if (empty($donotredirect)) {
                setEventMessages($langs->trans("FileGenerated") . ' - ' . $timesheetdocument->last_main_doc, null);
                $urltoredirect = $_SERVER['REQUEST_URI'];
                $urltoredirect = preg_replace('/#builddoc$/', '', $urltoredirect);
                $urltoredirect = preg_replace('/action=builddoc&?/', '', $urltoredirect); // To avoid infinite loop
                header('Location: ' . $urltoredirect . '#builddoc');
                exit;
            }
        }
    }

    // Delete file in doc form
    if ($action == 'remove_file' && $permissiontodelete) {
        if ( ! empty($upload_dir)) {
            require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

            $langs->load("other");
            $filetodelete = GETPOST('file', 'alpha');
            $file         = $upload_dir . '/' . $filetodelete;
            $ret          = dol_delete_file($file, 0, 0, 0, $object);
            if ($ret) setEventMessages($langs->trans("FileWasRemoved", $filetodelete), null, 'mesgs');
            else setEventMessages($langs->trans("ErrorFailToDeleteFile", $filetodelete), null, 'errors');

            // Make a redirect to avoid to keep the remove_file into the url that create side effects
            $urltoredirect = $_SERVER['REQUEST_URI'];
            $urltoredirect = preg_replace('/#builddoc$/', '', $urltoredirect);
            $urltoredirect = preg_replace('/action=remove_file&?/', '', $urltoredirect);

            header('Location: ' . $urltoredirect);
            exit;
        } else {
            setEventMessages('BugFoundVarUploaddirnotDefined', null, 'errors');
        }
    }

	if ($action == 'set_thirdparty' && $permissiontoadd) {
		$object->setValueFrom('fk_soc', GETPOST('fk_soc', 'int'), '', '', 'date', '', $user, $triggermodname);
	}

	if ($action == 'classin' && $permissiontoadd) {
		$object->setProject(GETPOST('projectid', 'int'));
	}

	// Action to add line
	if ($action == 'addline' && $permissiontoadd) {
		// Get parameters
		$qty             = GETPOST('qty');
		$prod_entry_mode = GETPOST('prod_entry_mode');

		$now = dol_now();

		if ($prod_entry_mode == 'free') {
			$product_type = GETPOST('type');
			$description  = GETPOST('dp_desc', 'restricthtml');

			// Check parameters
			if ($product_type < 0) {
				setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Type')), null, 'errors');
				$error++;
			}
			if (empty($description)) {
				setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Description')), null, 'errors');
				$error++;
			}

			$objectline->description  = $description;
			$objectline->product_type = $product_type;
		} elseif ($prod_entry_mode == 'predef') {
			$product_id = GETPOST('idprod');
			if ($product_id > 0) {
				$product->fetch($product_id);
				$objectline->fk_product   = $product_id;
				$objectline->product_type = 0;
			} else {
				$error++;
			}
		} else {
			$error++;
		}

		// Initialize object timesheet line
		$objectline->date_creation   = $object->db->idate($now);
		$objectline->qty             = $qty;
		$objectline->rang            = 0;
		$objectline->fk_timesheet    = $id;
		$objectline->fk_parent_line  = 0;

		if ( ! $error) {
			$result = $objectline->insert($user, false);
			if ($result > 0) {
				// Creation timesheet line OK
				$urltogo = str_replace('__ID__', $result, $backtopage);
				$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
				header("Location: " . $urltogo);
				exit;
			} else {
				// Creation timesheet line KO
				if ( ! empty($objectline->errors)) setEventMessages(null, $objectline->errors, 'errors');
				else setEventMessages($objectline->error, null, 'errors');
			}
		}
	}

	// Action to edit line
	if ($action == 'updateline' && $permissiontoadd) {
		// Get parameters
		$qty          = GETPOST('qty');
		$description  = GETPOST('product_desc', 'restricthtml');

		$objectline->fetch($lineid);

		// Initialize object timesheet line
		$objectline->qty         = $qty;
		$objectline->description = $description;

		if ( ! $error) {
			$result = $objectline->update($user, false);
			if ($result > 0) {
				// Update timesheet line OK
				$urltogo = str_replace('__ID__', $result, $backtopage);
				$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $parent_id, $urltogo); // New method to autoselect project after a New on another form object creation
				header("Location: " . $urltogo);
				exit;
			} else {
				// Update timesheet line KO
				if ( ! empty($objectline->errors)) setEventMessages(null, $objectline->errors, 'errors');
				else setEventMessages($objectline->error, null, 'errors');
			}
		}
	}

	// Action to set status STATUS_LOCKED
	if ($action == 'confirm_setLocked' && $permissiontoadd) {
		$errorlinked = 0;
		$object->fetch($id);
		if ( ! $error) {
			$usertmp->fetch($object->fk_user_assign);
			$filter = ' AND ptt.task_date BETWEEN ' . "'" .dol_print_date($object->date_start, 'dayrfc') . "'" . ' AND ' . "'" . dol_print_date($object->date_end, 'dayrfc'). "'";
			$alltimespent = $task->fetchAllTimeSpent($usertmp, $filter);
			foreach ($alltimespent as $timespent) {
				$task->fetchObjectLinked(null, '', $timespent->timespent_id, 'project_task_time');
				if (!isset($task->linkedObjects['dolisirh_timesheet'])) {
					$task->id = $timespent->timespent_id;
					$task->element = 'project_task_time';
					$task->add_object_linked('dolisirh_timesheet', $object->id);
				} else {
					$errorlinked++;
				}
			}
			if ($errorlinked == 0) {
				$object->setLocked($user, false);
				// Set locked OK
				$urltogo = str_replace('__ID__', $result, $backtopage);
				$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
				header("Location: " . $urltogo);
				exit;
			}
		} else {
			// Set locked KO
			if ( ! empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
			else setEventMessages($object->error, null, 'errors');
		}

		if ($errorlinked > 0) {
			setEventMessages($langs->trans('ErrorLinkedElementTimeSheetTimeSpent', $usertmp->getFullName($langs), dol_print_date($object->date_start, 'dayreduceformat'), dol_print_date($object->date_end, 'dayreduceformat')), null, 'errors');
		}
	}

	// Action to set status STATUS_ARCHIVED
	if ($action == 'setArchived' && $permissiontoadd) {
		$object->fetch($id);
		if ( ! $error) {
			$result = $object->setArchived($user, false);
			if ($result > 0) {
				// Set Archived OK
				$urltogo = str_replace('__ID__', $result, $backtopage);
				$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
				header("Location: " . $urltogo);
				exit;
			} else {
				// Set Archived KO
				if ( ! empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
				else setEventMessages($object->error, null, 'errors');
			}
		}
	}

	// Actions to send emails
	$triggersendname = 'DOLISIRH_TIMESHEET_SENTBYMAIL';
	$autocopy = 'MAIN_MAIL_AUTOCOPY_TIMESHEET_TO';
	$trackid = 'timesheet'.$object->id;
	include DOL_DOCUMENT_ROOT.'/core/actions_sendmails.inc.php';
}

/*
 * View
 */

// Initialize view objects
$form = new Form($db);

$title    = $langs->trans("TimeSheet");
$help_url = '';
$morejs   = array("/dolisirh/js/dolisirh.js.php");
$morecss  = array("/dolisirh/css/dolisirh.css");

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss);

// Part to create
if ($action == 'create') {
	if (empty($permissiontoadd)) {
		accessforbidden($langs->trans('NotEnoughPermissions'), 0, 1);
		exit;
	}

	print load_fiche_titre($langs->trans("NewTimeSheet"), '', 'object_'.$object->picto);

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
	}

	print dol_get_fiche_head(array(), '');

	// Set some default values
	//if (! GETPOSTISSET('fieldname')) $_POST['fieldname'] = 'myvalue';

	print '<table class="border centpercent tableforfieldcreate">'."\n";

	$object->fields['label']['default'] = $langs->trans('TimeSheet') . ' ' . dol_print_date(dol_mktime(0, 0, 0, $month, $day, $year), "%B %Y") . ' ' . $user->getFullName($langs, 0, 0);
	$object->fields['fk_project']['default']   = $conf->global->DOLISIRH_HR_PROJECT;
	$object->fields['fk_user_assign']['default'] = $user->id;

	$date_start = dol_mktime(0, 0, 0, GETPOST('date_startmonth', 'int'), GETPOST('date_startday', 'int'), GETPOST('date_startyear', 'int'));
	$date_end   = dol_mktime(0, 0, 0, GETPOST('date_endmonth', 'int'), GETPOST('date_endday', 'int'), GETPOST('date_endyear', 'int'));

	$_POST['date_start'] = $date_start;
	$_POST['date_end']   = $date_end;

	if ($conf->global->DOLISIRH_TIMESHEET_PREFILL_DATE && empty($_POST['date_start']) && empty($_POST['date_end'])) {
		$firstday = dol_get_first_day($year, $month);
		$firstday = dol_getdate($firstday);

		$_POST['date_startday'] = $firstday['mday'];
		$_POST['date_startmonth'] = $firstday['mon'];
		$_POST['date_startyear'] = $firstday['year'];

		$lastday = dol_get_last_day($year, $month);
		$lastday = dol_getdate($lastday);

		$_POST['date_endday'] = $lastday['mday'];
		$_POST['date_endmonth'] = $lastday['mon'];
		$_POST['date_endyear'] = $lastday['year'];
	}

//	$object->fields['note_public']['visible']  = 1;
//	$object->fields['note_private']['visible'] = 1;

	// Common attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';

	// Categories
	if (!empty($conf->categorie->enabled)) {
		print '<tr><td>'.$langs->trans("Categories").'</td><td>';
		$cate_arbo = $form->select_all_categories('timesheet', '', 'parent', 64, 0, 1);
		print img_picto('', 'category').$form->multiselectarray('categories', $cate_arbo, GETPOST('categories', 'array'), '', 0, 'quatrevingtpercent widthcentpercentminusx', 0, 0);
		print "</td></tr>";
	}

	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_add.tpl.php';

	print '</table>'."\n";

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel("Create");

	print '</form>';

	//dol_set_focus('input[name="ref"]');
}

// Part to edit record
if (($id || $ref) && $action == 'edit') {
	print load_fiche_titre($langs->trans("ModifyTimeSheet"), '', 'object_'.$object->picto);

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';
	}

	print dol_get_fiche_head();

	print '<table class="border centpercent tableforfieldedit">'."\n";

	$date_start = dol_mktime(0, 0, 0, GETPOST('date_startmonth', 'int'), GETPOST('date_startday', 'int'), GETPOST('date_startyear', 'int'));
	$date_end   = dol_mktime(0, 0, 0, GETPOST('date_endmonth', 'int'), GETPOST('date_endday', 'int'), GETPOST('date_endyear', 'int'));

	$_POST['date_start'] = (!empty($date_start) ? $date_start : $object->date_start);
	$_POST['date_end'] = (!empty($date_end) ? $date_end : $object->date_end);

	$object->fields['note_public']['visible']  = 1;
	$object->fields['note_private']['visible'] = 1;

	// Common attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_edit.tpl.php';

	// Tags-Categories
	if ($conf->categorie->enabled) {
		print '<tr><td>'.$langs->trans("Categories").'</td><td>';
		$cate_arbo = $form->select_all_categories('timesheet', '', 'parent', 64, 0, 1);
		$c = new Categorie($db);
		$cats = $c->containing($object->id, 'timesheet');
		$arrayselected = array();
		if (is_array($cats)) {
			foreach ($cats as $cat) {
				$arrayselected[] = $cat->id;
			}
		}
		print img_picto('', 'category').$form->multiselectarray('categories', $cate_arbo, $arrayselected, '', 0, 'quatrevingtpercent widthcentpercentminusx', 0, 0);
		print "</td></tr>";
	}

	// Other attributes
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_edit.tpl.php';

	print '</table>';

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel();

	print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
	$res = $object->fetch_optionals();

	$head = timesheetPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("TimeSheet"), -1, $object->picto);

	$formconfirm = '';
	// setDraft confirmation
	if (($action == 'setDraft' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile)))        // Output when action = clone if jmobile or no js
		|| (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {                            // Always output when not jmobile nor js
		$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('ReOpenTimeSheet'), $langs->trans('ConfirmReOpenTimeSheet', $object->ref), 'confirm_setdraft', '', 'yes', 'actionButtonInProgress', 350, 600);
	}
	// setPendingSignature confirmation
	if (($action == 'setPendingSignature' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile)))        // Output when action = clone if jmobile or no js
		|| (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {                            // Always output when not jmobile nor js
		$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('ValidateTimeSheet'), $langs->trans('ConfirmValidateTimeSheet', $object->ref), 'confirm_validate', '', 'yes', 'actionButtonPendingSignature', 350, 600);
	}
	// setLocked confirmation
	if (($action == 'setLocked' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile)))        // Output when action = clone if jmobile or no js
		|| (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {                            // Always output when not jmobile nor js
		$formconfirm .= $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('LockTimeSheet'), $langs->trans('ConfirmLockTimeSheet', $object->ref), 'confirm_setLocked', '', 'yes', 'actionButtonLock', 350, 600);
	}
	// Confirmation to delete
	if ($action == 'delete') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('DeleteTimeSheet'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 1);
	}
	// Confirmation to delete line
	if ($action == 'ask_deleteline') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&lineid=' . $lineid, $langs->trans('DeleteProductLine'), $langs->trans('ConfirmDeleteProductLine'), 'confirm_deleteline', '', 0, 1);
	}
	// Confirmation remove file
	if ($action == 'removefile') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&file=' . GETPOST('file') . '&entity=' . $conf->entity, $langs->trans('RemoveFileTimeSheet'), $langs->trans('ConfirmRemoveFileTimeSheet'), 'remove_file', '', 'yes', 1, 350, 600);
	}

	// Call Hook formConfirm
	$parameters = array('formConfirm' => $formconfirm, 'lineid' => $lineid);
	$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	if (empty($reshook)) {
		$formconfirm .= $hookmanager->resPrint;
	} elseif ($reshook > 0) {
		$formconfirm = $hookmanager->resPrint;
	}

	// Print form confirm
	print $formconfirm;

	// Object card
	// ------------------------------------------------------------
	$linkback = '<a href="' . dol_buildpath('/dolisirh/view/timesheet/timesheet_list.php', 1) . '">' . $langs->trans("BackToList") . '</a>';

	$morehtmlref = '<div class="refidno">';
	// Thirdparty
	if (!empty($conf->societe->enabled)) {
		$object->fetch_thirdparty();
		$morehtmlref .= $langs->trans('ThirdParty') . ' : ' . (is_object($object->thirdparty) ? $object->thirdparty->getNomUrl(1) : '');
	}
	// Project
	if (!empty($conf->projet->enabled)) {
		$langs->load("projects");
		$morehtmlref .= '<br>' . $langs->trans('Project') . ' ';
		if (!empty($object->fk_project)) {
			$project->fetch($object->fk_project);
			$morehtmlref .= ': ' . $project->getNomUrl(1, '', 1);
		} else {
			$morehtmlref .= '';
		}
	}
	$morehtmlref .= '</div>';

    $object->picto = 'timesheet_small@dolisirh';
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">' . "\n";

	// Common attributes
	//$keyforbreak='fieldkeytoswitchonsecondcolumn';	// We change column just before this field

	unset($object->fields['fk_project']);                // Hide field already shown in banner
	unset($object->fields['fk_soc']);                    // Hide field already shown in banner

	include DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_view.tpl.php';

	// Categories
	if ($conf->categorie->enabled) {
		print '<tr><td class="valignmiddle">' . $langs->trans("Categories") . '</td><td>';
		print $form->showCategories($object->id, 'timesheet', 1);
		print "</td></tr>";
	}

	$now = dol_now();
	$datestart = dol_getdate($object->date_start, false, 'Europe/Paris');
	//$firstdaytoshow = dol_get_first_day($datestart['year'], $datestart['mon']);
	//$firstdaytoshowgmt = dol_get_first_day($datestart['year'], $datestart['mon'], true);

	// Due to Dolibarr issue in common field add we do substract 12 hours in timestamp
	$firstdaytoshow = $object->date_start - 12 * 60 * 60;
	$firstdaytoshowgmt = $object->date_start - 12 * 60 * 60;
	//$dayInMonth = cal_days_in_month(CAL_GREGORIAN, $datestart['mon'], $datestart['year']);
	$dayInMonth = num_between_day($object->date_start, $object->date_end, 1);
	//$lastdaytoshow = dol_get_last_day($datestart['year'], $datestart['mon']);
	$lastdaytoshow = $object->date_end - 12 * 60 * 60;
	$currentDayCurrent = date('d', $now);
	$currentMonth = date('m', $now);
	$isavailable = array();
	$workinghoursArray = $workinghours->fetchCurrentWorkingHours($object->fk_user_assign, 'user');
	$workinghoursMonth = 0;
	$nbworkinghoursMonth = 0;
	if ($currentMonth == $datestart['mon']) {
		$dayInMonthCurrent = $dayInMonth;
	} else {
		$dayInMonthCurrent = $dayInMonth;
	}

	for ($idw = 0; $idw < $dayInMonth; $idw++) {
		$dayinloopfromfirstdaytoshow = dol_time_plus_duree($firstdaytoshow, $idw, 'd'); // $firstdaytoshow is a date with hours = 0
		$dayinloopfromfirstdaytoshowgmt = dol_time_plus_duree($firstdaytoshowgmt, $idw, 'd'); // $firstdaytoshow is a date with hours = 0

		$statusofholidaytocheck = Holiday::STATUS_APPROVED;

		$isavailablefordayanduser = $holiday->verifDateHolidayForTimestamp($object->fk_user_assign, $dayinloopfromfirstdaytoshow, $statusofholidaytocheck);
		$isavailable[$dayinloopfromfirstdaytoshow] = $isavailablefordayanduser; // in projectLinesPerWeek later, we are using $firstdaytoshow and dol_time_plus_duree to loop on each day

		$test = num_public_holiday($dayinloopfromfirstdaytoshowgmt, $dayinloopfromfirstdaytoshowgmt + 86400, $mysoc->country_code);
		if ($test) {
			$isavailable[$dayinloopfromfirstdaytoshow] = array('morning' => false, 'afternoon' => false, 'morning_reason' => 'public_holiday', 'afternoon_reason' => 'public_holiday');
		}
	}

	$tasksarray = $task->getTasksArray(0, 0, 0, 0, 0, '', '', '', $object->fk_user_assign, 0, array());

	if (count($tasksarray) > 0) {
		$usertmp->fetch($object->fk_user_assign);
		$j = 0;
		$level = 0;
		$projectsrole = $task->getUserRolesForProjectsOrTasks($usertmp, 0, 0, 0, 1);
		$tasksrole = $task->getUserRolesForProjectsOrTasks(0, $usertmp, 0, 0, 1);
		$restrictviewformytask = ((!isset($conf->global->PROJECT_TIME_SHOW_TASK_NOT_ASSIGNED)) ? 2 : $conf->global->PROJECT_TIME_SHOW_TASK_NOT_ASSIGNED);
		$conf->global->DOLISIRH_SHOW_ONLY_FAVORITE_TASKS = 0;
		$totalforvisibletasks = loadTimeSpentWithinRange($object->date_start, $object->date_end, 0, $object->fk_user_assign);
	}

	print '<tr class="liste_total"><td class="liste_total">';
	print $langs->trans("Total");
	for ($idw = 0; $idw < $dayInMonth; $idw++) {
		$dayinloopfromfirstdaytoshow = dol_time_plus_duree($firstdaytoshow, $idw, 'd');
		if ($isavailable[$dayinloopfromfirstdaytoshow]['morning'] && $isavailable[$dayinloopfromfirstdaytoshow]['afternoon']) {
			$currentDay = date('l', $dayinloopfromfirstdaytoshow);
			$currentDay = 'workinghours_' . strtolower($currentDay);
			$workinghoursMonth += $workinghoursArray->{$currentDay} * 60;
			if ($workinghoursArray->{$currentDay} / 60 > 0) {
				$nbworkinghoursMonth++;
			}
			if ($totalforvisibletasks[$dayinloopfromfirstdaytoshow] > 0) {
				$nbconsumedworkinghoursMonth++;
			}
		}
	}
	print '<span class="opacitymediumbycolor">  - ' . $langs->trans("ExpectedWorkedHoursMonthTimeSheet", dol_print_date($firstdaytoshow, "dayreduceformat"), (($dayInMonth == $dayInMonthCurrent) ? dol_print_date($lastdaytoshow, "dayreduceformat") : dol_print_date($now, "dayreduceformat"))) . ' : <strong><a href="' . DOL_URL_ROOT . '/custom/dolisirh/view/workinghours_card.php?id=' . $object->fk_user_assign . '" target="_blank">' . (($workinghoursMonth != 0) ? convertSecondToTime($workinghoursMonth, 'allhourmin') : '00:00') . '</a></strong>';
	print '<span>' . ' - ' . $langs->trans("ExpectedWorkedDayMonth") . ' <strong>' . $nbworkinghoursMonth . '</strong></span>';
	print '</span>';
	print '</td></tr>';
	print '<tr class="liste_total"><td class="liste_total">';
	print $langs->trans("Total");
	$workinghoursMonth = 0;
	for ($idw = 0; $idw < $dayInMonthCurrent; $idw++) {
		$dayinloopfromfirstdaytoshow = dol_time_plus_duree($firstdaytoshow, $idw, 'd');
		if ($isavailable[$dayinloopfromfirstdaytoshow]['morning'] && $isavailable[$dayinloopfromfirstdaytoshow]['afternoon']) {
			$currentDay = date('l', $dayinloopfromfirstdaytoshow);
			$currentDay = 'workinghours_' . strtolower($currentDay);
			$workinghoursMonth += $workinghoursArray->{$currentDay} * 60;
		}
	}
	$totalspenttime = $workinghoursMonth/60/60;
	print '<span class="opacitymediumbycolor">  - ' . $langs->trans("SpentWorkedHoursMonth", dol_print_date($firstdaytoshow, "dayreduceformat"), (($dayInMonth == $dayInMonthCurrent) ? dol_print_date($lastdaytoshow, "dayreduceformat") : dol_print_date($now, "dayreduceformat"))) . ' : <strong>' . (($workinghoursMonth != 0) ? convertSecondToTime($workinghoursMonth, 'allhourmin') : '00:00') . '</strong></span>';
	print '</td></tr>';
	print '<tr class="liste_total"><td class="liste_total">';
	print $langs->trans("Total");
	if (!empty($totalforvisibletasks)) {
		foreach ($totalforvisibletasks as $tasksingle) {
			$totalconsumedtime += $tasksingle;
		}
	}
	$diffworkinghoursMonth = $nbworkinghoursMonth - $nbconsumedworkinghoursMonth;
	if ($diffworkinghoursMonth < 0) {
		$morecss = colorStringToArray($conf->global->DOLISIRH_EXCEEDED_TIME_SPENT_COLOR);
	} elseif ($diffworkinghoursMonth > 0) {
		$morecss = colorStringToArray($conf->global->DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR);
	} elseif ($diffworkinghoursMonth == 0) {
		$morecss = colorStringToArray($conf->global->DOLISIRH_PERFECT_TIME_SPENT_COLOR);
	}
	print '<span class="opacitymediumbycolor">  - '.$langs->trans("ConsumedWorkedHoursMonth", dol_print_date($firstdaytoshow, "dayreduceformat"), (($dayInMonth == $dayInMonthCurrent) ? dol_print_date($lastdaytoshow, "dayreduceformat") : dol_print_date($now, "dayreduceformat"))).' : <strong>'.convertSecondToTime($totalconsumedtime, 'allhourmin').'</strong>';
	print '<span>' . ' - ' . $langs->trans("ConsumedWorkedDayMonth") . ' <strong style="color:'.'rgb('.$morecss[0].','.$morecss[1].','.$morecss[2].')'.'">' . (!empty($nbconsumedworkinghoursMonth) ? $nbconsumedworkinghoursMonth : 0) . '</strong></span>';
	print '</span>';
	print '</td></tr>';
	print '<tr class="liste_total"><td class="liste_total">';
	print $langs->trans("Total");
	$difftotaltime = $totalspenttime * 60 * 60 - $totalconsumedtime;
	if ($difftotaltime < 0) {
		$morecss = colorStringToArray($conf->global->DOLISIRH_EXCEEDED_TIME_SPENT_COLOR);
		$morecssnotice = 'error';
		$noticetitle = $langs->trans('TimeSpentDiff', dol_print_date($firstdaytoshow, "dayreduceformat"), (($dayInMonth == $dayInMonthCurrent) ? dol_print_date($lastdaytoshow, "dayreduceformat") : dol_print_date($now, "dayreduceformat")), dol_print_date(dol_mktime(0, 0, 0, $datestart['mon'], $datestart['mday'], $datestart['year']), "%B %Y"));
	} elseif ($difftotaltime > 0) {
		$morecss = colorStringToArray($conf->global->DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR);
		$morecssnotice = 'warning';
		$noticetitle = $langs->trans('TimeSpentMustBeCompleted', dol_print_date($firstdaytoshow, "dayreduceformat"), (($dayInMonth == $dayInMonthCurrent) ? dol_print_date($lastdaytoshow, "dayreduceformat") : dol_print_date($now, "dayreduceformat")), dol_print_date(dol_mktime(0, 0, 0, $datestart['mon'], $datestart['mday'], $datestart['year']), "%B %Y"));
	} elseif ($difftotaltime == 0) {
		$morecss = colorStringToArray($conf->global->DOLISIRH_PERFECT_TIME_SPENT_COLOR);
		$morecssnotice = 'success';
		$noticetitle = $langs->trans('TimeSpentPerfect');
	}
	print '<span class="opacitymediumbycolor">  - '.$langs->trans("DiffSpentAndConsumedWorkedHoursMonth", dol_print_date($firstdaytoshow, "dayreduceformat"), (($dayInMonth == $dayInMonthCurrent) ? dol_print_date($lastdaytoshow, "dayreduceformat") : dol_print_date($now, "dayreduceformat"))).' : <strong style="color:'.'rgb('.$morecss[0].','.$morecss[1].','.$morecss[2].')'.'">'.(($difftotaltime != 0) ? convertSecondToTime(abs($difftotaltime), 'allhourmin') : '00:00').'</strong></span>';
	print '</td></tr>';

	// Other attributes. Fields from hook formObjectOptions and Extrafields.
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

	print '</table>';
	print '</div>';
	print '</div>';

	$usertmp->fetch($object->fk_user_assign);

	print '<div class="clearboth"></div>'; ?>

	<?php if ($workinghoursMonth == 0) : ?>
		<div class="wpeo-notice notice-error">
			<div class="notice-content">
				<div class="notice-title"><?php echo $langs->trans('ErrorConfigWorkingHours') ?></div>
			</div>
			<a class="butAction" style="width = 100%;margin-right:0" target="_blank" href="<?php echo DOL_URL_ROOT . '/custom/dolisirh/view/workinghours_card.php?id=' . $object->fk_user_assign . '&backtopage=' . DOL_URL_ROOT . '/custom/dolisirh/view/timesheet/timesheet_card.php?id=' . $id ?>"><?php echo $langs->trans("GoToWorkingHours", $usertmp->getFullName($langs)) ?></a>
		</div>
	<?php else : ?>
		<div class="wpeo-notice notice-<?php echo $morecssnotice ?>">
			<div class="notice-content">
				<div class="notice-title"><?php echo $noticetitle ?></div>
			</div>
			<a class="butAction" style="width = 100%;margin-right:0" target="_blank" href="<?php echo DOL_URL_ROOT . '/custom/dolisirh/view/timespent_month.php?year='.$datestart['year'].'&month='.$datestart['mon'].'&day='.$datestart['mday'].'&search_usertoprocessid='.$object->fk_user_assign . '&backtopage=' . DOL_URL_ROOT . '/custom/dolisirh/view/timesheet/timesheet_card.php?id=' . $id ?>"><?php echo $langs->trans("GoToTimeSpent", dol_print_date(dol_mktime(0, 0, 0, $datestart['mon'], $datestart['mday'], $datestart['year']), "%B %Y")) ?></a>
		</div>
	<?php endif; ?>

	<?php print dol_get_fiche_end();

	/*
	 * Lines
	 */

	if (!empty($object->table_element_line)) {
		// Show object lines
		$result = $object->getLinesArray();

		print '	<form name="addproduct" id="addproduct" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.(($action != 'editline') ? '' : '#line_'.GETPOST('lineid', 'int')).'" method="POST">
		<input type="hidden" name="token" value="' . newToken().'">
		<input type="hidden" name="action" value="' . (($action != 'editline') ? 'addline' : 'updateline').'">
		<input type="hidden" name="mode" value="">
		<input type="hidden" name="page_y" value="">
		<input type="hidden" name="id" value="' . $object->id.'">
		';

		if (!empty($conf->use_javascript_ajax) && $object->status == 0) {
			include DOL_DOCUMENT_ROOT.'/core/tpl/ajaxrow.tpl.php';
		}

		print '<div class="div-table-responsive-no-min">';
		if (!empty($object->lines) || ($object->status == $object::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
			print '<table id="tablelines" class="noborder noshadow" width="100%">';
		}

		if (!empty($object->lines)) {
			if ($permissiontoadd) {
				$user->rights->timesheet = new stdClass();
				$user->rights->timesheet->creer = 1;
				$object->statut = $object::STATUS_DRAFT;
				if ($object->status >= $object::STATUS_VALIDATED) {
					$disableedit = 1;
					$disableremove = 1;
					$disablemove = 1;
				}
			}
			$object->printObjectLines($action, $mysoc, null, GETPOST('lineid', 'int'), 1); ?>
			<script>
				jQuery('.linecolvat').remove();
				jQuery('.linecoluht').remove();
				jQuery('.linecoldiscount').remove();
				jQuery('.linecolht').remove();
				jQuery('.tredited .right #tva_tx').remove();
				jQuery('.tredited .right #price_ht').remove();
				jQuery('.tredited .right #remise_percent').remove();
				jQuery('.tredited .nowrap').remove();
				jQuery('.treditedlinefordate').remove();
			</script>
		<?php }

		// Form to add new line
		if ($object->status == 0 && $permissiontoadd && $action != 'selectlines') {
			if ($action != 'editline') {
				// Add products/services form
				$object->socid = 0;
				$parameters = array();
				$reshook = $hookmanager->executeHooks('formAddObjectLine', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
				if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
				if (empty($reshook)) $object->formAddObjectLine(1, $mysoc, $soc); ?>
				<script>
					jQuery('.linecolvat').remove();
					jQuery('.linecoluht').remove();
					jQuery('.linecoldiscount').remove();
					jQuery('#trlinefordates').remove();
				</script>
			<?php }
		}

		if (!empty($object->lines) || ($object->status == $object::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
			print '</table>';
		}
		print '</div>';

		print "</form>\n";
	}

	// Buttons for actions
	if ($action != 'presend' && $action != 'editline') {
		print '<div class="tabsAction">'."\n";
		$parameters = array();
		$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}

		if (empty($reshook) && $permissiontoadd) {
			// Modify
			print '<a class="' . ($object->status == $object::STATUS_DRAFT ? 'butAction' : 'butActionRefused classfortooltip') . '" id="actionButtonEdit" title="' . ($object->status == $object::STATUS_DRAFT ? '' : dol_escape_htmltag($langs->trans("TimeSheetMustBeDraft"))) . '" href="' . ($object->status == $object::STATUS_DRAFT ? ($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=edit') : '#') . '">' . $langs->trans("Modify") . '</a>';

			// Validate
			print '<span class="' . ($object->status == $object::STATUS_DRAFT && $workinghoursMonth != 0 ? 'butAction' : 'butActionRefused classfortooltip') . '" id="' . ($object->status == $object::STATUS_DRAFT && $workinghoursMonth != 0 ? 'actionButtonPendingSignature' : '') . '" title="' . ($object->status == $object::STATUS_DRAFT && $workinghoursMonth != 0 ? '' : dol_escape_htmltag($langs->trans("TimeSheetMustBeDraftToValidate"))) . '" href="' . ($object->status == $object::STATUS_DRAFT && $workinghoursMonth != 0 ? ($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=setPendingSignature') : '#') . '">' . $langs->trans("Validate") . '</span>';

			// ReOpen
			print '<span class="' . ($object->status == $object::STATUS_VALIDATED ? 'butAction' : 'butActionRefused classfortooltip') . '" id="' . ($object->status == $object::STATUS_VALIDATED ? 'actionButtonInProgress' : '') . '" title="' . ($object->status == $object::STATUS_VALIDATED ? '' : dol_escape_htmltag($langs->trans("TimeSheetMustBeValidated"))) . '" href="' . ($object->status == $object::STATUS_VALIDATED ? ($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=setDraft') : '#') . '">' . $langs->trans("ReOpenDoli") . '</span>';

			// Sign
			print '<a class="' . (($object->status == $object::STATUS_VALIDATED && ! $signatory->checkSignatoriesSignatures($object->id, 'timesheet')) ? 'butAction' : 'butActionRefused classfortooltip') . '" id="actionButtonSign" title="' . (($object->status == $object::STATUS_VALIDATED && ! $signatory->checkSignatoriesSignatures($object->id, 'timesheet')) ? '' : dol_escape_htmltag($langs->trans("TimeSheetMustBeValidatedToSign"))) . '" href="' . (($object->status == $object::STATUS_VALIDATED && ! $signatory->checkSignatoriesSignatures($object->id, 'timesheet')) ? dol_buildpath('/custom/dolisirh/view/timesheet/timesheet_attendants.php?id=' . $object->id, 3) : '#') . '">' . $langs->trans("Sign") . '</a>';

			// Lock
			print '<span class="' . (($object->status == $object::STATUS_VALIDATED && $signatory->checkSignatoriesSignatures($object->id, 'timesheet') && $difftotaltime == 0 && $diffworkinghoursMonth == 0) ? 'butAction' : 'butActionRefused classfortooltip') . '" id="' . (($object->status == $object::STATUS_VALIDATED && $signatory->checkSignatoriesSignatures($object->id, 'timesheet') && $difftotaltime == 0 && $diffworkinghoursMonth == 0) ? 'actionButtonLock' : '') . '" title="' . (($object->status == $object::STATUS_VALIDATED && $signatory->checkSignatoriesSignatures($object->id, 'timesheet') && $difftotaltime == 0 && $diffworkinghoursMonth == 0) ? '' : dol_escape_htmltag($langs->trans("AllSignatoriesMustHaveSignedAndDiffTimeSetAt0"))) . '">' . $langs->trans("Lock") . '</span>';

			// Send
			//@TODO changer le send to
			//print '<a class="' . ($object->status == $object::STATUS_LOCKED ? 'butAction' : 'butActionRefused classfortooltip') . '" id="actionButtonSign" title="' . dol_escape_htmltag($langs->trans("TimeSheetMustBeLockedToSendEmail")) . '" href="' . ($object->status == $object::STATUS_LOCKED ? ($_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=presend&mode=init#formmailbeforetitle&sendto=' . $allLinks['LabourInspectorSociety']->id[0]) : '#') . '">' . $langs->trans('SendMail') . '</a>';

			// Archive
			print '<a class="' . ($object->status == $object::STATUS_LOCKED  && !empty(dol_dir_list($upload_dir . '/timesheetdocument/' . dol_sanitizeFileName($object->ref))) ? 'butAction' : 'butActionRefused classfortooltip') . '" id="" title="' . ($object->status == $object::STATUS_LOCKED && !empty(dol_dir_list($upload_dir . '/timesheetdocument/' . dol_sanitizeFileName($object->ref))) ? '' : dol_escape_htmltag($langs->trans("TimeSheetMustBeLockedGenerated"))) . '" href="' . ($object->status == $object::STATUS_LOCKED && !empty(dol_dir_list($upload_dir . '/timesheetdocument/' . dol_sanitizeFileName($object->ref))) ? ($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=setArchived') : '#') . '">' . $langs->trans("Archive") . '</a>';

			// Delete (need delete permission, or if draft, just need create/modify permission)
			//print dolGetButtonAction($langs->trans('Delete'), '', 'delete', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken(), '', $permissiontodelete || ($object->status == $object::STATUS_DRAFT && $permissiontoadd));
		}
		print '</div>'."\n";
	}

	// Select mail models is same action as presend
	if (GETPOST('modelselected')) {
		$action = 'presend';
	}

	if ($action != 'presend') {
		print '<div class="fichecenter"><div class="fichehalfleft">';
		print '<a name="builddoc"></a>'; // ancre

		$includedocgeneration = 1;

		// Documents
		if ($includedocgeneration) {
			$objref = dol_sanitizeFileName($object->ref);
			$dir_files = $object->element . 'document/' . $objref;
			$filedir = $upload_dir . '/' . $dir_files;
			$urlsource = $_SERVER["PHP_SELF"]."?id=".$object->id;
			$genallowed = $permissiontoadd; // If you can read, you can build the PDF to read content
			$delallowed = $permissiontodelete; // If you can create/edit, you can remove a file on card

			print doliSirhShowDocuments('dolisirh:TimeSheetDocument', $dir_files, $filedir, $urlsource, $genallowed, $object->status == $object::STATUS_LOCKED ? $delallowed : 0, $conf->global->DOLISIRH_TIMESHEETDOCUMENT_DEFAULT_MODEL, 1, 0, 0, 0, 0, '', '', '', $langs->defaultlang, $object, 0, 'removefile', $object->status == $object::STATUS_LOCKED && empty(dol_dir_list($filedir)), $langs->trans('TimeSheetMustBeLocked'));
		}

		print '</div><div class="fichehalfright">';

		$MAXEVENT = 10;

		$morehtmlcenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-list-alt imgforviewmode', dol_buildpath('/dolisirh/view/timesheet/timesheet_agenda.php', 1).'?id='.$object->id);

		// List of actions on element
		include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
		$formactions = new FormActions($db);
		$somethingshown = $formactions->showactions($object, $object->element.'@'.$object->module, (is_object($object->thirdparty) ? $object->thirdparty->id : 0), 1, '', $MAXEVENT, '', $morehtmlcenter);

		print '</div></div>';
	}

	//Select mail models is same action as presend
	if (GETPOST('modelselected')) {
		$action = 'presend';
	}

	// Presend form
	$modelmail = 'timesheet';
	$defaulttopic = 'InformationMessage';
	$diroutput = $conf->dolisirh->dir_output;
	$trackid = 'timesheet'.$object->id;

	include DOL_DOCUMENT_ROOT.'/core/tpl/card_presend.tpl.php';
}

// End of page
llxFooter();
$db->close();
