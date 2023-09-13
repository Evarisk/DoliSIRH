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
 * \file    view/timesheet/timesheet_card.php
 * \ingroup dolisirh
 * \brief   Page to create/edit/view timesheet.
 */

// Load DoliSIRH environment.
if (file_exists('../../dolisirh.main.inc.php')) {
    require_once __DIR__ . '/../../dolisirh.main.inc.php';
} elseif (file_exists('../../../dolisirh.main.inc.php')) {
    require_once __DIR__ . '/../../../dolisirh.main.inc.php';
} else {
    die('Include of dolisirh main fails');
}

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

// Load Saturne libraries.
require_once __DIR__ . '/../../../saturne/class/saturnesignature.class.php';

// load DoliSIRH libraries.
require_once __DIR__ . '/../../lib/dolisirh_function.lib.php';
require_once __DIR__ . '/../../lib/dolisirh_timespent.lib.php';
require_once __DIR__ . '/../../lib/dolisirh_timesheet.lib.php';
require_once __DIR__ . '/../../class/timesheet.class.php';
require_once __DIR__ . '/../../class/dolisirhdocuments/timesheetdocument.class.php';
require_once __DIR__ . '/../../class/workinghours.class.php';

// Global variables definitions.
global $conf, $db, $hookmanager, $langs, $moduleNameLowerCase, $mysoc, $user;

// Load translation files required by the page.
saturne_load_langs();

// Get parameters.
$id                  = GETPOST('id', 'int');
$ref                 = GETPOST('ref', 'alpha');
$action              = GETPOST('action', 'aZ09');
$confirm             = GETPOST('confirm', 'alpha');
$cancel              = GETPOST('cancel', 'aZ09');
$contextPage         = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'timesheetcard'; // To manage different context of search.
$backtopage          = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');
$lineid              = GETPOST('lineid', 'int');
$year                = (GETPOST('year', 'int') ? GETPOST('year', 'int') : date('Y'));
$month               = (GETPOST('month', 'int') ? GETPOST('month', 'int') : date('m'));
$day                 = (GETPOST('day', 'int') ? GETPOST('day', 'int') : date('d'));

// Initialize technical objects.
$object       = new TimeSheet($db);
$objectLine   = new TimeSheetLine($db);
$signatory    = new SaturneSignature($db, $moduleNameLowerCase, $object->element);
$document     = new TimeSheetDocument($db);
$extrafields  = new ExtraFields($db);
$product      = new Product($db);
$workingHours = new Workinghours($db);
$task         = new Task($db);
$userTmp      = new User($db);

// Initialize view objects
$form = new Form($db);

$hookmanager->initHooks(['timesheetcard', 'globalcard']); // Note that conf->hooks_modules contains array.

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criterias
$searchAll = GETPOST('search_all', 'alpha');
$search    = [];
foreach ($object->fields as $key => $val) {
    if (GETPOST('search_' . $key, 'alpha')) {
        $search[$key] = GETPOST('search_' . $key, 'alpha');
    }
}

if (empty($action) && empty($id) && empty($ref)) {
    $action = 'view';
}

// Load object
require_once DOL_DOCUMENT_ROOT . '/core/actions_fetchobject.inc.php'; // Must be included, not include_once.

$upload_dir = $conf->dolisirh->multidir_output[$object->entity ?? 1];

$now = dol_now();

// Security check - Protection if external user
$permissionToRead   = $user->rights->dolisirh->timesheet->read;
$permissiontoadd    = $user->rights->dolisirh->timesheet->write;
$permissiontodelete = $user->rights->dolisirh->timesheet->delete || ($permissiontoadd && isset($object->status) && $object->status == TimeSheet::STATUS_DRAFT);
saturne_check_access($permissionToRead);

/*
 * Actions.
 */

$parameters = ['id' => $id];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks.
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($resHook)) {
    $error = 0;

    $backurlforlist = dol_buildpath('/dolisirh/view/timesheet/timesheet_list.php', 1);

    if (empty($backtopage) || ($cancel && empty($id))) {
        if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
            if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
                $backtopage = $backurlforlist;
            } else {
                $backtopage = dol_buildpath('/dolisirh/view/timesheet/timesheet_card.php', 1) . '?id=' . ((!empty($id) && $id > 0) ? $id : '__ID__');
            }
        }
    }

    if (($action == 'add' || $action == 'update') && $permissiontoadd && !$cancel) {
        $userTmp->fetch(GETPOST('fk_user_assign'));
        $dateStart  = dol_mktime(0, 0, 0, GETPOST('date_startmonth', 'int'), GETPOST('date_startday', 'int'), GETPOST('date_startyear', 'int'));
        $dateEnd    = dol_mktime(0, 0, 0, GETPOST('date_endmonth', 'int'), GETPOST('date_endday', 'int'), GETPOST('date_endyear', 'int'));
        $filter     = ' AND ptt.task_date BETWEEN ' . "'"  . dol_print_date($dateStart, 'dayrfc') . "'" . ' AND ' . "'" . dol_print_date($dateEnd, 'dayrfc') . "'";
        $timeSpents = $task->fetchAllTimeSpent($userTmp, $filter);
        foreach ($timeSpents as $timespent) {
            $task->fetchObjectLinked(null, '', $timespent->timespent_id, 'project_task_time');
            if (isset($task->linkedObjects['dolisirh_timesheet'])) {
                $error++;
            }
        }

        if ($dateStart > $dateEnd) {
            setEventMessages($langs->trans('ErrorDateTimeSheet', dol_print_date($dateStart, 'dayreduceformat'), dol_print_date($dateEnd, 'dayreduceformat')), [], 'errors');
            if ($action == 'add') {
                $action = 'create';
            } elseif ($action == 'update') {
                $action = 'edit';
            }
        }

        if (getDolGlobalInt('DOLISIRH_TIMESHEET_CHECK_DATE_END')) {
            if ($dateEnd > $now) {
                setEventMessages($langs->trans('ErrorDateEndTimeSheet', dol_print_date($dateEnd, 'dayreduceformat'), dol_print_date($now, 'dayreduceformat')), [], 'errors');
                if ($action == 'add') {
                    $action = 'create';
                } elseif ($action == 'update') {
                    $action = 'edit';
                }
            }
        }

        if ($error > 0) {
            setEventMessages($langs->trans('ErrorLinkedElementTimeSheetTimeSpent', $userTmp->getFullName($langs), dol_print_date($dateStart, 'dayreduceformat'), dol_print_date($dateEnd, 'dayreduceformat')), [], 'errors');
            if ($action == 'add') {
                $action = 'create';
            } elseif ($action == 'update') {
                $action = 'edit';
            }
        }
    }

    // Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen.
    $triggermodname = 'TIMESHEET_MODIFY'; // Name of trigger action code to execute when we modify record.
    require_once DOL_DOCUMENT_ROOT . '/core/actions_addupdatedelete.inc.php';

    // Actions set_thirdparty, set_project
    require_once __DIR__ . '/../../../saturne/core/tpl/actions/banner_actions.tpl.php';

    // Actions builddoc, forcebuilddoc, remove_file.
    require_once __DIR__ . '/../../../saturne/core/tpl/documents/documents_action.tpl.php';

    // Action to generate pdf from odt file.
    require_once __DIR__ . '/../../../saturne/core/tpl/documents/saturne_manual_pdf_generation_action.tpl.php';

    // Action to add line.
    if ($action == 'addline' && $permissiontoadd) {
        // Get parameters.
        $qty           = GETPOST('qty');
        $prodEntryMode = GETPOST('prod_entry_mode');

        if ($prodEntryMode == 'free') {
            $productType = GETPOST('type');
            $description = GETPOST('dp_desc', 'restricthtml');

            // Check parameters.
            if ($productType < 0) {
                setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Type')), [], 'errors');
                $error++;
            }
            if (dol_strlen($description) < 0) {
                setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Description')), [], 'errors');
                $error++;
            }

            $objectLine->description  = $description;
            $objectLine->product_type = $productType;
        } elseif ($prodEntryMode == 'predef') {
            $productID = GETPOST('idprod');
            if ($productID > 0) {
                $product->fetch($productID);
                $objectLine->fk_product   = $productID;
                $objectLine->product_type = 0;
            } else {
                $error++;
            }
        } else {
            $error++;
        }

        // Initialize object timesheet line.
        $objectLine->date_creation   = $object->db->idate($now);
        $objectLine->qty             = $qty;
        $objectLine->rang            = 0;
        $objectLine->fk_timesheet    = $id;
        $objectLine->fk_parent_line  = 0;

        if (!$error) {
            $result = $objectLine->create($user);
            if ($result > 0) {
                // Creation timesheet line OK.
                $urlToGo = str_replace('__ID__', $result, $backtopage);
                $urlToGo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urlToGo); // New method to autoselect project after a New on another form object creation.
                header('Location: ' . $urlToGo);
                exit;
            } elseif (!empty($objectLine->errors)) {
                // Creation timesheet line KO.
                setEventMessages('', $objectLine->errors, 'errors');
            } else {
                setEventMessages($objectLine->error, [], 'errors');
            }
        }
    }

    // Action to edit line.
    if ($action == 'updateline' && $permissiontoadd) {
        // Get parameters.
        $qty          = GETPOST('qty');
        $description  = GETPOST('product_desc', 'restricthtml');

        $objectLine->fetch($lineid);

        // Initialize object timesheet line.
        $objectLine->qty         = $qty;
        $objectLine->description = $description;

        if (!$error) {
            $result = $objectLine->update($user);
            if ($result > 0) {
                // Update timesheet line OK.
                $urlToGo = str_replace('__ID__', $result, $backtopage);
                $urlToGo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urlToGo); // New method to autoselect project after a New on another form object creation.
                header('Location: ' . $urlToGo);
                exit;
            } elseif (!empty($objectLine->errors)) {
                // Update timesheet line KO.
                setEventMessages('', $objectLine->errors, 'errors');
            } else {
                setEventMessages($objectLine->error, [], 'errors');
            }
        }
    }

    // Action to set status STATUS_LOCKED.
    if ($action == 'confirm_set_Lock' && $permissiontoadd) {
        $errorLinked = 0;
        $object->fetch($id);
        if (!$error) {
            $userTmp->fetch($object->fk_user_assign);
            $filter     = ' AND ptt.task_date BETWEEN ' . "'"  . dol_print_date($object->date_start, 'dayrfc') . "'" . ' AND ' . "'" . dol_print_date($object->date_end, 'dayrfc') . "'";
            $timeSpents = $task->fetchAllTimeSpent($userTmp, $filter);
            foreach ($timeSpents as $timespent) {
                $task->fetchObjectLinked(null, '', $timespent->timespent_id, 'project_task_time');
                if (!isset($task->linkedObjects['dolisirh_timesheet'])) {
                    $task->id      = $timespent->timespent_id;
                    $task->element = 'project_task_time';
                    $task->add_object_linked('dolisirh_timesheet', $object->id);
                } else {
                    $errorLinked++;
                }
            }
            if ($errorLinked == 0) {
                $object->setLocked($user, false);
                // Set locked OK
                $urlToGo = str_replace('__ID__', $id, $backtopage);
                $urlToGo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urlToGo); // New method to autoselect project after a New on another form object creation.
                header('Location: ' . $urlToGo);
                exit;
            }
        } elseif (!empty($object->errors)) {
            // Set locked KO.
            setEventMessages('', $object->errors, 'errors');
        } else {
            setEventMessages($object->error, [], 'errors');
        }

        if ($errorLinked > 0) {
            setEventMessages($langs->trans('ErrorLinkedElementTimeSheetTimeSpent', $userTmp->getFullName($langs), dol_print_date($object->date_start, 'dayreduceformat'), dol_print_date($object->date_end, 'dayreduceformat')), [], 'errors');
        }
    }

    // Action confirm_archive.
    require_once __DIR__ . '/../../../saturne/core/tpl/signature/signature_action_workflow.tpl.php';

    // Actions to send emails.
    $triggersendname = strtoupper($object->element) . '_SENTBYMAIL';
    $autocopy        = 'MAIN_MAIL_AUTOCOPY_' . strtoupper($object->element) . '_TO';
    require_once DOL_DOCUMENT_ROOT . '/core/actions_sendmails.inc.php';
}

/*
 * View.
 */

$title    = $langs->trans(ucfirst($object->element));
$help_url = 'FR:Module_DoliSIRH';

saturne_header(0, '', $title, $help_url);

// Part to create;
if ($action == 'create') {
    if (empty($permissiontoadd)) {
        accessforbidden($langs->trans('NotEnoughPermissions'), 0);
        exit;
    }

    print load_fiche_titre($langs->trans('New' . ucfirst($object->element)), '', 'object_' . $object->picto);

    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="add">';
    if ($backtopage) {
        print '<input type="hidden" name="backtopage" value="' . $backtopage . '">';
    }
    if ($backtopageforcancel) {
        print '<input type="hidden" name="backtopageforcancel" value="'. $backtopageforcancel . '">';
    }

    print dol_get_fiche_head();

    print '<table class="border centpercent tableforfieldcreate">';

    $specialCaseMonth = 0;
    if (getDolGlobalInt('DOLISIRH_TIMESHEET_PREFILL_DATE')) {
        if ($month == 01) {
            $specialCaseMonth = 12;
            $year--;
        }
    }

    $object->fields['label']['default']          = $langs->trans('TimeSheet') . ' ' . dol_print_date(dol_mktime(0, 0, 0, (!empty($conf->global->DOLISIRH_TIMESHEET_PREFILL_DATE) ? (($month != 01) ? $month - 1 : $specialCaseMonth) : $month), $day, $year), '%B %Y') . ' ' . $user->getFullName($langs, 0, 0);
    $object->fields['fk_project']['default']     = $conf->global->DOLISIRH_HR_PROJECT;
    $object->fields['fk_user_assign']['default'] = $user->id;

    $dateStart = dol_mktime(0, 0, 0, GETPOST('date_startmonth', 'int'), GETPOST('date_startday', 'int'), GETPOST('date_startyear', 'int'));
    $dateEnd   = dol_mktime(0, 0, 0, GETPOST('date_endmonth', 'int'), GETPOST('date_endday', 'int'), GETPOST('date_endyear', 'int'));

    $_POST['date_start'] = $dateStart;
    $_POST['date_end']   = $dateEnd;

    if (getDolGlobalInt('DOLISIRH_TIMESHEET_PREFILL_DATE') && empty($_POST['date_start']) && empty($_POST['date_end'])) {
        $firstDay = dol_get_first_day($year, (($month != 01) ? $month - 1 : $specialCaseMonth));
        $firstDay = dol_getdate($firstDay);

        $_POST['date_startday']   = $firstDay['mday'];
        $_POST['date_startmonth'] = $firstDay['mon'];
        $_POST['date_startyear']  = $firstDay['year'];

        $lastDay = dol_get_last_day($year, (($month != 01) ? $month - 1 : $specialCaseMonth));
        $lastDay = dol_getdate($lastDay);

        $_POST['date_endday']   = $lastDay['mday'];
        $_POST['date_endmonth'] = $lastDay['mon'];
        $_POST['date_endyear']  = $lastDay['year'];
    }

    // Common attributes.
    require_once DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';

    // Categories.
    if (isModEnabled('categorie')) {
        print '<tr><td>' . $langs->trans('Categories') . '</td><td>';
        $cateArbo = $form->select_all_categories($object->element, '', 'parent', 64, 0, 1);
        print img_picto('', 'category') . $form::multiselectarray('categories', $cateArbo, GETPOST('categories', 'array'), '', 0, 'quatrevingtpercent widthcentpercentminusx');
        print '</td></tr>';
    }

    // Other attributes.
    include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_add.tpl.php';

    print '</table>';

    print dol_get_fiche_end();

    print $form->buttonsSaveCancel('Create');

    print '</form>';
}

// Part to edit record.
if (($id || $ref) && $action == 'edit') {
    print load_fiche_titre($langs->trans('Modify' . ucfirst($object->element)), '', 'object_' . $object->picto);

    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="update">';
    print '<input type="hidden" name="id" value="' . $object->id . '">';
    if ($backtopage) {
        print '<input type="hidden" name="backtopage" value="' . $backtopage . '">';
    }
    if ($backtopageforcancel) {
        print '<input type="hidden" name="backtopageforcancel" value="' . $backtopageforcancel . '">';
    }

    print dol_get_fiche_head();

    print '<table class="border centpercent tableforfieldedit">';

    $dateStart = dol_mktime(0, 0, 0, GETPOST('date_startmonth', 'int'), GETPOST('date_startday', 'int'), GETPOST('date_startyear', 'int'));
    $dateEnd   = dol_mktime(0, 0, 0, GETPOST('date_endmonth', 'int'), GETPOST('date_endday', 'int'), GETPOST('date_endyear', 'int'));

    $dateStart = (!empty($dateStart) ? $dateStart : $object->date_start);
    $dateEnd   = (!empty($dateEnd) ? $dateEnd : $object->date_end);

    $dateStart = dol_getdate($dateStart);
    $dateEnd   = dol_getdate($dateEnd);

    $_POST['date_startday']   = $dateStart['mday'];
    $_POST['date_startmonth'] = $dateStart['mon'];
    $_POST['date_startyear']  = $dateStart['year'];

    $_POST['date_endday']   = $dateEnd['mday'];
    $_POST['date_endmonth'] = $dateEnd['mon'];
    $_POST['date_endyear']  = $dateEnd['year'];

    // Common attributes.
    require_once DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_edit.tpl.php';

    // Tags-Categories.
    if (isModEnabled('categorie')) {
        print '<tr><td>' . $langs->trans('Categories') . '</td><td>';
        $cate_arbo     = $form->select_all_categories($object->element, '', 'parent', 64, 0, 1);
        $categorie     = new Categorie($db);
        $cats          = $categorie->containing($object->id, $object->element);
        $arraySelected = [];
        if (is_array($cats)) {
            foreach ($cats as $cat) {
                $arraySelected[] = $cat->id;
            }
        }
        print img_picto('', 'category') . $form::multiselectarray('categories', $cate_arbo, $arraySelected, '', 0, 'quatrevingtpercent widthcentpercentminusx');
        print '</td></tr>';
    }

    // Other attributes.
    require_once DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_edit.tpl.php';

    print '</table>';

    print dol_get_fiche_end();

    print $form->buttonsSaveCancel();

    print '</form>';
}

// Part to show record.
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
    $object->fetch_optionals();

    saturne_get_fiche_head($object, 'card', $title);
    saturne_banner_tab($object);

    $formConfirm = '';

    // Draft confirmation
    if (($action == 'draft' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
        $formConfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ReOpenObject', $langs->transnoentities('The' . ucfirst($object->element))),  $langs->trans('ConfirmReOpenObject', $langs->transnoentities('The' . ucfirst($object->element)), $langs->transnoentities('The' . ucfirst($object->element))), 'confirm_setdraft', '', 'yes', 'actionButtonInProgress', 350, 600);
    }
    // Pending signature confirmation
    if (($action == 'pending_signature' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
        $formConfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ValidateObject', $langs->transnoentities('The' . ucfirst($object->element))), $langs->trans('ConfirmValidateObject', $langs->transnoentities('The' . ucfirst($object->element)), $langs->transnoentities('The' . ucfirst($object->element))) . $langs->trans('ConfirmValidateTimeSheet'), 'confirm_validate', '', 'yes', 'actionButtonPendingSignature', 350, 600);
    }
    // Lock confirmation
    if (($action == 'lock' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
        $formConfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('LockObject', $langs->transnoentities('The' . ucfirst($object->element))), $langs->trans('ConfirmLockObject', $langs->transnoentities('The' . ucfirst($object->element))), 'confirm_set_Lock', '', 'yes', 'actionButtonLock', 350, 600);
    }
    // Delete confirmation
    if ($action == 'delete') {
        $formConfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('DeleteObject', $langs->transnoentities('The' . ucfirst($object->element))), $langs->trans('ConfirmDeleteObject', $langs->transnoentities('The' . ucfirst($object->element))), 'confirm_delete', '', 'yes', 1);
    }
    // Delete line confirmation
    if ($action == 'ask_deleteline') {
        $formConfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id . '&lineid=' . $lineid, $langs->trans('DeleteProductLine'), $langs->trans('ConfirmDeleteProductLine'), 'confirm_deleteline', '', 0, 1);
    }
    // Remove file confirmation
    if ($action == 'removefile') {
        $formConfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id . '&file=' . GETPOST('file') . '&entity=' . $conf->entity, $langs->trans('RemoveFileTimeSheet'), $langs->trans('ConfirmRemoveFileTimeSheet'), 'remove_file', '', 'yes', 1, 350, 600);
    }

    // Call Hook formConfirm.
    $parameters = ['formConfirm' => $formConfirm, 'lineid' => $lineid];
    $reshook    = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action); // Note that $action and $object may have been modified by hook.
    if (empty($reshook)) {
        $formConfirm .= $hookmanager->resPrint;
    } elseif ($reshook > 0) {
        $formConfirm = $hookmanager->resPrint;
    }

    // Print form confirm.
    print $formConfirm;

    if ($conf->browser->layout == 'phone') {
        $onPhone = 1;
    } else {
        $onPhone = 0;
    }

    print '<div class="fichecenter">';
    print '<div class="fichehalfleft">';
    print '<table class="border centpercent tableforfield">';

    // Common attributes
    unset($object->fields['label']);      // Hide field already shown in banner
    unset($object->fields['fk_project']); // Hide field already shown in banner
    unset($object->fields['fk_soc']);     // Hide field already shown in banner

    require_once DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_view.tpl.php';

    // Categories.
    if (isModEnabled('categorie')) {
        print '<tr><td class="valignmiddle">' . $langs->trans('Categories') . '</td><td>';
        print $form->showCategories($object->id, $object->element, 1);
        print '</td></tr>';
    }

    $dateStart = dol_getdate($object->date_start, false, 'Europe/Paris');

    // Due to Dolibarr issue in common field add we do substract 12 hours in timestamp.
    $firstDayToShow = $object->date_start - 12 * 3600;
    $lastDayToShow  = $object->date_end - 12 * 3600;

    $startDate = dol_print_date($firstDayToShow, 'dayreduceformat');
    $endDate   = dol_print_date($lastDayToShow, 'dayreduceformat');

    $daysInRange = dolisirh_num_between_days($firstDayToShow, $lastDayToShow, 1);

    $isAvailable = [];
    for ($idw = 0; $idw < $daysInRange; $idw++) {
        $dayInLoop = dol_time_plus_duree($firstDayToShow, $idw, 'd');
        if (is_day_available($dayInLoop, $user->id)) {
            $isAvailable[$dayInLoop] = ['morning' => 1, 'afternoon' => 1];
        } elseif (date('N', $dayInLoop) >= 6) {
            $isAvailable[$dayInLoop] = ['morning' => false, 'afternoon' => false, 'morning_reason' => 'week_end', 'afternoon_reason' => 'week_end'];
        } else {
            $isAvailable[$dayInLoop] = ['morning' => false, 'afternoon' => false, 'morning_reason' => 'public_holiday', 'afternoon_reason' => 'public_holiday'];
        }
    }

    $workingHours = $workingHours->fetchCurrentWorkingHours($object->fk_user_assign, 'user');

    $timeSpendingInfos = load_time_spending_infos_within_range($firstDayToShow, dol_time_plus_duree($lastDayToShow, 1, 'd'), $workingHours, $isAvailable, $object->fk_user_assign);

    // Planned working time.
    $plannedWorkingTime = $timeSpendingInfos['planned'];

    print '<tr class="liste_total"><td class="liste_total">';
    print $langs->trans('Total');
    print '<span class="opacitymediumbycolor">  - ';
    print $langs->trans('ExpectedWorkingHoursMonthTimeSheet', $startDate, $endDate);
    print ' : <strong><a href="' . DOL_URL_ROOT . '/custom/dolisirh/view/workinghours_card.php?id=' . $object->fk_user_assign . '" target="_blank">';
    print (($plannedWorkingTime['minutes'] != 0) ? convertSecondToTime($plannedWorkingTime['minutes'] * 60, 'allhourmin') : '00:00') . '</a></strong>';
    print '<span>' . ' - ' . $langs->trans('ExpectedWorkingDayMonth') . ' <strong>' . $plannedWorkingTime['days'] . '</strong></span>';
    print '</span>';
    print '</td></tr>';

    // Hours passed.
    $passedWorkingTime = $timeSpendingInfos['passed'];

    print '<tr class="liste_total"><td class="liste_total">';
    print $langs->trans('Total');
    print '<span class="opacitymediumbycolor">  - ';
    print $langs->trans('SpentWorkingHoursMonth', $startDate, $endDate);
    print ' : <strong>' . (($passedWorkingTime['minutes'] != 0) ? convertSecondToTime($passedWorkingTime['minutes'] * 60, 'allhourmin') : '00:00') . '</strong></span>';
    print '</td></tr>';

    // Difference between passed and working hours.
    $diffTotalTime = $timeSpendingInfos['difference'];

    if ($diffTotalTime < 0) {
        $moreCssHours  = colorStringToArray($conf->global->DOLISIRH_EXCEEDED_TIME_SPENT_COLOR);
        $moreCssNotice = 'error';
        $noticeTitle   = $langs->trans('TimeSpentDiff', dol_print_date($firstDayToShow, 'dayreduceformat'), dol_print_date($lastDayToShow, 'dayreduceformat'), dol_print_date(dol_mktime(0, 0, 0, $dateStart['mon'], $dateStart['mday'], $dateStart['year']), '%B %Y'));
    } elseif ($diffTotalTime > 0) {
        $moreCssHours  = colorStringToArray($conf->global->DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR);
        $moreCssNotice = 'warning';
        $noticeTitle   = $langs->trans('TimeSpentMustBeCompleted', dol_print_date($firstDayToShow, 'dayreduceformat'), dol_print_date($lastDayToShow, 'dayreduceformat'), dol_print_date(dol_mktime(0, 0, 0, $dateStart['mon'], $dateStart['mday'], $dateStart['year']), '%B %Y'));
    } elseif ($diffTotalTime == 0) {
        $moreCssHours  = colorStringToArray($conf->global->DOLISIRH_PERFECT_TIME_SPENT_COLOR);
        $moreCssNotice = 'success';
        $noticeTitle   = $langs->trans('TimeSpentPerfect');
    } else {
        $moreCssHours  = '';
        $moreCssNotice = '';
        $noticeTitle   = '';
    }

    // Working hours.
    $workingTime = $timeSpendingInfos['spent'];
    if ($plannedWorkingTime['days'] > $workingTime['days']) {
        $morecssDays = colorStringToArray($conf->global->DOLISIRH_EXCEEDED_TIME_SPENT_COLOR);
    } elseif ($plannedWorkingTime['days'] < $workingTime['days']){
        $morecssDays = colorStringToArray($conf->global->DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR);
    } else {
        $morecssDays = colorStringToArray($conf->global->DOLISIRH_PERFECT_TIME_SPENT_COLOR);
    }

    print '<tr class="liste_total"><td class="liste_total">';
    print $langs->trans('Total');
    print '<span class="opacitymediumbycolor"> - ';
    print $langs->trans('ConsumedWorkingHoursMonth', $startDate, $endDate);
    print ' : <strong>' . convertSecondToTime($workingTime['total'], 'allhourmin') . '</strong>';
    print '<span>' . ' - ' . $langs->trans('ConsumedWorkingDayMonth') . ' <strong style="color:' . 'rgb(' . $morecssDays[0] . ',' . $morecssDays[1] . ',' . $morecssDays[2] . ');' . '">';
    print $workingTime['days'] . '</strong></span>';
    print '</span>';
    print '</td></tr>';

    // Difference between working hours & planned working hours.
    print '<tr class="liste_total"><td class="liste_total">';
    print $langs->trans('Total');
    print '<span class="opacitymediumbycolor">  - ';
    print $langs->trans('DiffSpentAndConsumedWorkingHoursMonth', $startDate, $endDate);
    print ' : <strong style="color:' . 'rgb(' . $moreCssHours[0] . ',' . $moreCssHours[1] . ',' . $moreCssHours[2] . ');' . '">';
    print (($diffTotalTime != 0) ? convertSecondToTime(abs($diffTotalTime * 60), 'allhourmin') : '00:00') . '</strong>';
    print '</span>';
    print '</td></tr>';

    // Other attributes. Fields from hook formObjectOptions and Extrafields.
    require_once DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_view.tpl.php';

    print '</table>';
    print '</div>';
    print '</div>';

    $userTmp->fetch($object->fk_user_assign);

    print '<div class="clearboth"></div>'; ?>

    <?php if ($plannedWorkingTime['minutes'] == 0) : ?>
        <div class="wpeo-notice notice-error">
            <div class="notice-content">
                <div class="notice-title"><?php echo $langs->trans('ErrorConfigWorkingHours') ?></div>
            </div>
            <a class="butAction" style="width = 100%; margin-right : 0;" target="_blank" href="<?php echo DOL_URL_ROOT . '/custom/dolisirh/view/workinghours_card.php?id=' . $object->fk_user_assign . '&backtopage=' . DOL_URL_ROOT . '/custom/dolisirh/view/timesheet/timesheet_card.php?id=' . $id ?>"><?php echo $langs->trans('GoToWorkingHours', $userTmp->getFullName($langs)) ?></a>
        </div>
    <?php else : ?>
        <div class="wpeo-notice notice-<?php echo $moreCssNotice ?>">
            <div class="notice-content">
                <div class="notice-title"><?php echo $noticeTitle ?></div>
            </div>
            <a class="butAction" style="width = 100% ; margin-right : 0;" target="_blank" href="<?php echo DOL_URL_ROOT . '/custom/dolisirh/view/timespent_range.php?year=' . $dateStart['year'] . '&month=' . $dateStart['mon'] . '&day=' . $dateStart['mday'] . '&search_user_id=' . $object->fk_user_assign . '&view_mode=month&backtopage=' . DOL_URL_ROOT . '/custom/dolisirh/view/timesheet/timesheet_card.php?id=' . $id ?>"><?php echo $langs->trans('GoToTimeSpent', dol_print_date(dol_mktime(0, 0, 0, $dateStart['mon'], $dateStart['mday'], $dateStart['year']), '%B %Y')) ?></a>
        </div>
    <?php endif; ?>

    <?php if (empty($conf->global->DOLISIRH_HR_PROJECT) || empty($conf->global->DOLISIRH_HOLIDAYS_TASK) || empty($conf->global->DOLISIRH_PAID_HOLIDAYS_TASK) || empty($conf->global->DOLISIRH_PAID_HOLIDAYS_TASK) || empty($conf->global->DOLISIRH_PAID_HOLIDAYS_TASK)
        || empty($conf->global->DOLISIRH_PUBLIC_HOLIDAY_TASK) || empty($conf->global-> DOLISIRH_RTT_TASK) || empty($conf->global->DOLISIRH_INTERNAL_MEETING_TASK) || empty($conf->global->DOLISIRH_INTERNAL_TRAINING_TASK) || empty($conf->global->DOLISIRH_EXTERNAL_TRAINING_TASK)
        || empty($conf->global->DOLISIRH_AUTOMATIC_TIMESPENDING_TASK) || empty($conf->global->DOLISIRH_MISCELLANEOUS_TASK)) : ?>
        <div class="wpeo-notice notice-error">
            <div class="notice-content">
                <div class="notice-title"><?php echo $langs->trans('ErrorConfigProjectPage') ?></div>
            </div>
            <a class="butAction" style="width = 100%; margin-right : 0;" target="_blank" href="<?php echo DOL_URL_ROOT . '/custom/dolisirh/admin/project.php'; ?>"><?php echo $langs->trans('GoToConfigProjectPage') ?></a>
        </div>
    <?php endif; ?>

    <?php print dol_get_fiche_end();

    /*
     * Lines.
     */

    if (!empty($object->table_element_line)) {
        // Show object lines.
        $result = $object->getLinesArray();

        print '	<form name="addproduct" id="addproduct" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . (($action != 'editline') ? '' : '#line_' . $lineid) . '" method="POST">
        <input type="hidden" name="token" value="' . newToken() . '">
        <input type="hidden" name="action" value="' . (($action != 'editline') ? 'addline' : 'updateline') . '">
        <input type="hidden" name="mode" value="">
        <input type="hidden" name="page_y" value="">
        <input type="hidden" name="id" value="' . $object->id . '">
        ';

        if (!empty($conf->use_javascript_ajax) && $object->status == 0) {
            include DOL_DOCUMENT_ROOT . '/core/tpl/ajaxrow.tpl.php';
        }

        print '<div class="div-table-responsive-no-min">';
        if (!empty($object->lines) || ($object->status == TimeSheet::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
            print '<table id="tablelines" class="noborder noshadow">';
        }

        if (!empty($object->lines)) {
            if ($permissiontoadd) {
                $object->statut = $object->status;
            }
            $object->printObjectLines($action, $mysoc, null, $lineid, 1); ?>
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

        // Form to add new line.
        if ($object->status == TimeSheet::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines') {
            if ($action != 'editline') {
                // Add products/services form.
                $parameters = [];
                $reshook    = $hookmanager->executeHooks('formAddObjectLine', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
                if ($reshook < 0) {
                    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
                }
                if (empty($reshook)) {
                    $object->formAddObjectLine(1, $mysoc, $mysoc);
                } ?>
                <script>
                    jQuery('.linecolvat').remove();
                    jQuery('.linecoluht').remove();
                    jQuery('.linecoldiscount').remove();
                    jQuery('#trlinefordates').remove();
                </script>
            <?php }
        }

        if (!empty($object->lines) || ($object->status == TimeSheet::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
            print '</table>';
        }
        print '</div>';

        print '</form>';
    }

    // Buttons for actions.
    if ($action != 'presend' && $action != 'editline') {
        print '<div class="tabsAction">';
        $parameters = [];
        $resHook    = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook.
        if ($resHook < 0) {
            setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
        }

        if (empty($resHook) && $permissiontoadd) {
            // Modify.
            $displayButton = $onPhone ? '<i class="fas fa-edit fa-2x"></i>' : '<i class="fas fa-edit"></i>' . ' ' . $langs->trans('Modify');
            if ($object->status == TimeSheet::STATUS_DRAFT) {
                print '<a class="butAction" id="actionButtonEdit" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=edit' . '">' . $displayButton . '</a>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeDraft', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Validate.
            $displayButton = $onPhone ? '<i class="fas fa-check fa-2x"></i>' : '<i class="fas fa-check"></i>' . ' ' . $langs->trans('Validate');
            if ($object->status == TimeSheet::STATUS_DRAFT && $plannedWorkingTime['minutes']  != 0) {
                print '<span class="butAction" id="actionButtonPendingSignature">' . $displayButton . '</span>';
            } elseif ($object->status < TimeSheet::STATUS_DRAFT) {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeDraft', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // ReOpen.
            $displayButton = $onPhone ? '<i class="fas fa-lock-open fa-2x"></i>' : '<i class="fas fa-lock-open"></i>' . ' ' . $langs->trans('ReOpenDoli');
            if ($object->status == TimeSheet::STATUS_VALIDATED) {
                print '<span class="butAction" id="actionButtonInProgress">' . $displayButton . '</span>';
            } elseif ($object->status > TimeSheet::STATUS_VALIDATED) {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeValidated', $langs->transnoentities('The' . ucfirst($object->element)))) . '">' . $displayButton . '</span>';
            }

            // Sign.
            $displayButton = $onPhone ? '<i class="fas fa-signature fa-2x"></i>' : '<i class="fas fa-signature"></i>' . ' ' . $langs->trans('Sign');
            if ($object->status == TimeSheet::STATUS_VALIDATED && !$signatory->checkSignatoriesSignatures($object->id, $object->element)) {
                print '<a class="butAction" id="actionButtonSign" href="' . dol_buildpath('/custom/saturne/view/saturne_attendants.php?module_name=DoliSIRH&object_type=timesheet&document_type=TimeSheetDocument&attendant_table_mode=simple&id=' . $object->id, 3) . '">' . $displayButton . '</a>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeValidatedToSign', $langs->transnoentities('The' . ucfirst($object->element)))) . '">' . $displayButton . '</span>';
            }

            // Lock.
            $displayButton = $onPhone ? '<i class="fas fa-lock fa-2x"></i>' : '<i class="fas fa-lock"></i>' . ' ' . $langs->trans('Lock');
            if ($object->status == TimeSheet::STATUS_VALIDATED && $signatory->checkSignatoriesSignatures($object->id, $object->element) && $diffTotalTime == 0) {
                print '<span class="butAction" id="actionButtonLock">' . $displayButton . '</span>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('AllSignatoriesMustHaveSignedAndDiffTimeSetAt0')) . '">' . $displayButton . '</span>';
            }

            // Send email.
            $displayButton = $onPhone ? '<i class="fas fa-paper-plane fa-2x"></i>' : '<i class="fas fa-paper-plane"></i>' . ' ' . $langs->trans('SendMail') . ' ';
            if ($object->status == TimeSheet::STATUS_LOCKED) {
                $fileParams = dol_most_recent_file($upload_dir . '/' . $object->element . 'document' . '/' . $object->ref);
                $file       = $fileParams['fullname'];
                if (file_exists($file) && !strstr($fileParams['name'], 'specimen')) {
                    $forcebuilddoc = 0;
                } else {
                    $forcebuilddoc = 1;
                }
                print dolGetButtonAction($displayButton, '', 'default', $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=presend&forcebuilddoc=' . $forcebuilddoc . '&mode=init#formmailbeforetitle');
            } else {
                print '<span class="butActionRefused classfortooltip" title="'.dol_escape_htmltag($langs->trans('ObjectMustBeLockedToSendEmail', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Archive.
            $displayButton = $onPhone ?  '<i class="fas fa-archive fa-2x"></i>' : '<i class="fas fa-archive"></i>' . ' ' . $langs->trans('Archive');
            if ($object->status == TimeSheet::STATUS_LOCKED  && !empty(dol_dir_list($upload_dir . '/timesheetdocument/' . dol_sanitizeFileName($object->ref)))) {
                print '<a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=confirm_archive&token=' . newToken() . '">' . $displayButton . '</a>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeLockedToArchive', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Delete (need delete permission, or if draft, just need create/modify permission).
            $displayButton = $onPhone ? '<i class="fas fa-trash fa-2x"></i>' : '<i class="fas fa-trash"></i>' . ' ' . $langs->trans('Delete');
            print dolGetButtonAction($displayButton, '', 'delete', $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=delete&token=' . newToken(), '', $permissiontodelete || ($object->status == TimeSheet::STATUS_DRAFT));
        }
        print '</div>';
    }

    // Select mail models is same action as presend.
    if (GETPOST('modelselected')) {
        $action = 'presend';
    }

    if ($action != 'presend') {
        print '<div class="fichecenter"><div class="fichehalfleft">';
        // Documents.
        $objRef    = dol_sanitizeFileName($object->ref);
        $dirFiles  = $object->element . 'document/' . $objRef;
        $fileDir   = $upload_dir . '/' . $dirFiles;
        $urlSource = $_SERVER['PHP_SELF'] . '?id=' . $object->id;

        print saturne_show_documents('dolisirh:' . ucfirst($object->element) . 'Document', $dirFiles, $fileDir, $urlSource, $permissiontoadd, $permissiontodelete, $conf->global->DOLISIRH_TIMESHEETDOCUMENT_DEFAULT_MODEL, 1, 0, 0, 0, 0, '', '', '', $langs->defaultlang, $object, 0, 'remove_file', $object->status == TimeSheet::STATUS_LOCKED && empty(dol_dir_list($fileDir)), $langs->trans('ObjectMustBeLockedToGenerate', ucfirst($langs->transnoentities('The' . ucfirst($object->element)))));

        print '</div><div class="fichehalfright">';

        $moreHtmlCenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars imgforviewmode', dol_buildpath('/saturne/view/saturne_agenda.php', 1) . '?id=' . $object->id . '&module_name=DoliSIRH&object_type=' . $object->element);

        // List of actions on element.
        require_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
        $formActions = new FormActions($db);
        $formActions->showactions($object, $object->element . '@' . $object->module, 0, 1, '', 10, '', $moreHtmlCenter);

        print '</div></div>';
    }

    //Select mail models is same action as presend.
    if (GETPOST('modelselected')) {
        $action = 'presend';
    }

    // Presend form.
    $modelmail    = $object->element;
    $defaulttopic = 'InformationMessage';
    $diroutput    = $conf->dolisirh->dir_output;
    $trackid      = $object->element . $object->id;

    require_once DOL_DOCUMENT_ROOT.'/core/tpl/card_presend.tpl.php';
}

// End of page.
llxFooter();
$db->close();
