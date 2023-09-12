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
 * \file    view/certificate/certificate_card.php
 * \ingroup dolisirh
 * \brief   Page to create/edit/view certificate.
 */

// Load DoliSIRH environment.
if (file_exists('../../dolisirh.main.inc.php')) {
    require_once __DIR__ . '/../../dolisirh.main.inc.php';
} elseif (file_exists('../../../dolisirh.main.inc.php')) {
    require_once __DIR__ . '/../../../dolisirh.main.inc.php';
} else {
    die('Include of dolisirh main fails');
}

// Load Saturne libraries.
require_once __DIR__ . '/../../../saturne/class/saturnesignature.class.php';

// load DoliSIRH libraries.
require_once __DIR__ . '/../../lib/dolisirh_certificate.lib.php';
require_once __DIR__ . '/../../class/certificate.class.php';
require_once __DIR__ . '/../../class/dolisirhdocuments/certificatedocument.class.php';

// Global variables definitions.
global $conf, $db, $hookmanager, $langs, $mysoc, $user;

// Load translation files required by the page.
saturne_load_langs();

// Get parameters.
$id                  = GETPOST('id', 'int');
$ref                 = GETPOST('ref', 'alpha');
$action              = GETPOST('action', 'aZ09');
$confirm             = GETPOST('confirm', 'alpha');
$cancel              = GETPOST('cancel', 'aZ09');
$contextPage         = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'certificatecard'; // To manage different context of search.
$backtopage          = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');

// Initialize technical objects.
$object      = new Certificate($db);
$signatory   = new SaturneSignature($db, 'dolisirh', $object->element);
$document    = new CertificateDocument($db);
$extraFields = new ExtraFields($db);

// Initialize view objects.
$form = new Form($db);

$hookmanager->initHooks(['certificatecard', 'globalcard']); // Note that conf->hooks_modules contains array.

// Fetch optionals attributes and labels.
$extraFields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extraFields->getOptionalsFromPost($object->table_element, '', 'search_');

// Initialize array of search criterias.
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

// Load object.
include DOL_DOCUMENT_ROOT . '/core/actions_fetchobject.inc.php'; // Must be included, not include_once.

$upload_dir = $conf->dolisirh->multidir_output[$object->entity ?? 1];

// Security check - Protection if external user.
$permissionToRead   = $user->rights->dolisirh->certificate->read;
$permissiontoadd    = $user->rights->dolisirh->certificate->write;
$permissiontodelete = $user->rights->dolisirh->certificate->delete || ($permissiontoadd && isset($object->status) && $object->status == SaturneCertificate::STATUS_DRAFT);
saturne_check_access($permissionToRead);

/*
 * Actions.
 */

$parameters = [];
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks.
if ($reshook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
    $error = 0;

    $backurlforlist = dol_buildpath('/dolisirh/view/certificate/certificate_list.php', 1);

    if (empty($backtopage) || ($cancel && empty($id))) {
        if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
            if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
                $backtopage = $backurlforlist;
            } else {
                $backtopage = dol_buildpath('/dolisirh/view/certificate/certificate_card.php', 1) . '?id=' . ($id > 0 ? $id : '__ID__');
            }
        }
    }

    // Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen.
    include DOL_DOCUMENT_ROOT . '/core/actions_addupdatedelete.inc.php';

    // Actions save_project.
    require_once __DIR__ . '/../../../saturne/core/tpl/actions/edit_project_action.tpl.php';

    // Actions builddoc, forcebuilddoc, remove_file.
    require_once __DIR__ . '/../../../saturne/core/tpl/documents/documents_action.tpl.php';

    // Action to generate pdf from odt file.
    require_once __DIR__ . '/../../../saturne/core/tpl/documents/saturne_manual_pdf_generation_action.tpl.php';

    // Action confirm_lock, confirm_archive.
    require_once __DIR__ . '/../../../saturne/core/tpl/signature/signature_action_workflow.tpl.php';

    // Actions to send emails.
    $triggersendname = strtoupper($object->element) . '_SENTBYMAIL';
    $autocopy        = 'MAIN_MAIL_AUTOCOPY_' . strtoupper($object->element) . '_TO';
    $trackid         = $object->element . $object->id;
    require_once DOL_DOCUMENT_ROOT . '/core/actions_sendmails.inc.php';
}

/*
 * View.
 */

$title    = $langs->trans(ucfirst($object->element));
$help_url = 'FR:Module_DoliSIRH';

saturne_header(0, '', $title, $help_url);

// Part to create.
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
        print '<input type="hidden" name="backtopageforcancel" value="' . $backtopageforcancel . '">';
    }

    print dol_get_fiche_head();

    print '<table class="border centpercent tableforfieldcreate">';

    $object->fields['fk_project']['default'] = $conf->global->DOLISIRH_HR_PROJECT;

    if (isModEnabled('user')) {
        $object->fields['element_type']['arrayofkeyval']['user'] = $langs->trans('User');
    }
    if (isModEnabled('product')) {
        $object->fields['element_type']['arrayofkeyval']['product'] = $langs->trans('Product');
    }

    switch (GETPOST('element_type')) {
        case 'user' :
            $object->fields['fk_element']['type']    = 'integer:User:user/class/user.class.php';
            $object->fields['fk_element']['picto']   = 'user';
            $object->fields['fk_element']['label']   = $langs->trans('User');
            $object->fields['element_type']['picto'] = 'user';
            break;
        case 'product' :
            $object->fields['fk_element']['type']    = 'integer:Product:product/class/product.class.php';
            $object->fields['fk_element']['picto']   = 'product';
            $object->fields['fk_element']['label']   = $langs->trans('Product');
            $object->fields['element_type']['picto'] = 'product';
            break;
    }

    // Common attributes.
    require_once DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_add.tpl.php';

    // Categories.
    if (isModEnabled('categorie')) {
        print '<tr><td>' . $langs->trans('Categories') . '</td><td>';
        $cateArbo = $form->select_all_categories($object->element, '', 'parent', 64, 0, 1);
        print img_picto('', 'category') . $form::multiselectarray('categories', $cateArbo, GETPOST('categories', 'array'), '', 0, 'quatrevingtpercent widthcentpercentminusx');
        print '</td></tr>';
    }

    // Other attributes.
    require_once DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_add.tpl.php';

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

    if (isModEnabled('user')) {
        $object->fields['element_type']['arrayofkeyval']['user'] = $langs->trans('User');
    }
    if (isModEnabled('product')) {
        $object->fields['element_type']['arrayofkeyval']['product'] = $langs->trans('Product');
    }

    switch (GETPOST('element_type')) {
        case 'user' :
            $object->fields['fk_element']['type']    = 'integer:User:user/class/user.class.php';
            $object->fields['fk_element']['picto']   = 'user';
            $object->fields['fk_element']['label']   = $langs->trans('User');
            $object->fields['element_type']['picto'] = 'user';
            break;
        case 'product' :
            $object->fields['fk_element']['type']    = 'integer:Product:product/class/product.class.php';
            $object->fields['fk_element']['picto']   = 'product';
            $object->fields['fk_element']['label']   = $langs->trans('Product');
            $object->fields['element_type']['picto'] = 'product';
            break;
    }

    // Common attributes.
    require_once DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_edit.tpl.php';

    // Tags-Categories.
    if (isModEnabled('categorie')) {
        print '<tr><td>' . $langs->trans('Categories') . '</td><td>';
        $cateArbo      = $form->select_all_categories($object->element, '', 'parent', 64, 0, 1);
        $categorie     = new Categorie($db);
        $cats          = $categorie->containing($object->id, $object->element);
        $arraySelected = [];
        if (is_array($cats)) {
            foreach ($cats as $cat) {
                $arraySelected[] = $cat->id;
            }
        }
        print img_picto('', 'category') . $form::multiselectarray('categories', $cateArbo, $arraySelected, '', 0, 'quatrevingtpercent widthcentpercentminusx');
        print '</td></tr>';
    }

    // Other attributes.
    require_once DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_edit.tpl.php';

    print '</table>';

    print dol_get_fiche_end();

    print $form->buttonsSaveCancel();

    print '</form>';
}

// Part to show record.
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
    $res = $object->fetch_optionals();

    saturne_get_fiche_head($object, 'card', $title);
    saturne_banner_tab($object);

    $formConfirm = '';

    // Draft confirmation.
    if (($action == 'draft' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
        $formConfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ReOpenObject', $langs->transnoentities('The' . ucfirst($object->element))), $langs->trans('ConfirmReOpenObject', $langs->transnoentities('The' . ucfirst($object->element)), $langs->transnoentities('The' . ucfirst($object->element))), 'confirm_setdraft', '', 'yes', 'actionButtonInProgress', 350, 600);
    }
    // Pending signature confirmation.
    if (($action == 'pending_signature' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
        $formConfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('ValidateObject', $langs->transnoentities('The' . ucfirst($object->element))), $langs->trans('ConfirmValidateObject', $langs->transnoentities('The' . ucfirst($object->element)), $langs->transnoentities('The' . ucfirst($object->element))), 'confirm_validate', '', 'yes', 'actionButtonPendingSignature', 350, 600);
    }
    // Lock confirmation
    if (($action == 'lock' && (empty($conf->use_javascript_ajax) || !empty($conf->dol_use_jmobile))) || (!empty($conf->use_javascript_ajax) && empty($conf->dol_use_jmobile))) {
        $formConfirm .= $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('LockObject', $langs->transnoentities('The' . ucfirst($object->element))), $langs->trans('ConfirmLockObject', $langs->transnoentities('The' . ucfirst($object->element))), 'confirm_lock', '', 'yes', 'actionButtonLock', 350, 600);
    }
    // Delete confirmation.
    if ($action == 'delete') {
        $formConfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('DeleteObject', $langs->transnoentities('The' . ucfirst($object->element))), $langs->trans('ConfirmDeleteObject', $langs->transnoentities('The' . ucfirst($object->element))), 'confirm_delete', '', 'yes', 1);
    }

    // Call Hook formConfirm.
    $parameters = ['formConfirm' => $formConfirm];
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

    unset($object->fields['label']);        // Hide field already shown in banner.
    unset($object->fields['fk_soc']);       // Hide field already shown in banner.
    unset($object->fields['fk_project']);   // Hide field already shown in banner.
    unset($object->fields['element_type']); // Unwanted.

    switch ($object->element_type) {
        case 'user' :
            $object->fields['fk_element']['type'] = 'integer:User:user/class/user.class.php';
            $object->fields['fk_element']['picto'] = 'user';
            $object->fields['fk_element']['label'] = $langs->trans('User');
            $object->fields['element_type']['picto'] = 'user';
            break;
        case 'product' :
            $object->fields['fk_element']['type'] = 'integer:Product:product/class/product.class.php';
            $object->fields['fk_element']['picto'] = 'product';
            $object->fields['fk_element']['label'] = $langs->trans('Product');
            $object->fields['element_type']['picto'] = 'product';
            break;
    }

    // Common attributes.
    require_once DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_view.tpl.php';

    // Categories.
    if (isModEnabled('categorie')) {
        print '<tr><td class="valignmiddle">' . $langs->trans('Categories') . '</td><td>';
        print $form->showCategories($object->id, $object->element, 1);
        print '</td></tr>';
    }

    // Other attributes. Fields from hook formObjectOptions and Extrafields.
    require_once DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_view.tpl.php';

    print '</table>';
    print '</div>';
    print '</div>';

    print '<div class="clearboth"></div>';

    print dol_get_fiche_end();

    // Buttons for actions.
    if ($action != 'presend' ) {
        print '<div class="tabsAction">';
        $parameters = [];
        $reshook    = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook.
        if ($reshook < 0) {
            setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
        }

        if (empty($reshook) && $permissiontoadd) {
            // Modify.
            $displayButton = $onPhone ? '<i class="fas fa-edit fa-2x"></i>' : '<i class="fas fa-edit"></i>' . ' ' . $langs->trans('Modify');
            if ($object->status == SaturneCertificate::STATUS_DRAFT) {
                print '<a class="butAction" id="actionButtonEdit" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . ((dol_strlen($object->element_type) > 0) ? '&element_type=' . $object->element_type : '') . '&action=edit' . '">' . $displayButton . '</a>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeDraft', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Validate.
            $displayButton = $onPhone ? '<i class="fas fa-check fa-2x"></i>' : '<i class="fas fa-check"></i>' . ' ' . $langs->trans('Validate');
            if ($object->status == SaturneCertificate::STATUS_DRAFT) {
                print '<span class="butAction" id="actionButtonPendingSignature">' . $displayButton . '</span>';
            } elseif ($object->status < SaturneCertificate::STATUS_DRAFT) {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeDraft', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // ReOpen.
            $displayButton = $onPhone ? '<i class="fas fa-lock-open fa-2x"></i>' : '<i class="fas fa-lock-open"></i>' . ' ' . $langs->trans('ReOpenDoli');
            if ($object->status == SaturneCertificate::STATUS_VALIDATED) {
                print '<span class="butAction" id="actionButtonInProgress">' . $displayButton . '</span>';
            } elseif ($object->status > SaturneCertificate::STATUS_VALIDATED) {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeValidated', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Sign.
            $displayButton = $onPhone ? '<i class="fas fa-signature fa-2x"></i>' : '<i class="fas fa-signature"></i>' . ' ' . $langs->trans('Sign');
            if ($object->status == SaturneCertificate::STATUS_VALIDATED && !$signatory->checkSignatoriesSignatures($object->id, $object->element)) {
                print '<a class="butAction" id="actionButtonSign" href="' . dol_buildpath('/custom/saturne/view/saturne_attendants.php?id=' . $object->id . '&module_name=DoliSIRH&object_type=' . $object->element . '&document_type=CertificateDocument&attendant_table_mode=simple', 3) . '">' . $displayButton . '</a>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeValidatedToSign', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Lock.
            $displayButton = $onPhone ? '<i class="fas fa-lock fa-2x"></i>' : '<i class="fas fa-lock"></i>' . ' ' . $langs->trans('Lock');
            if ($object->status == SaturneCertificate::STATUS_VALIDATED && $signatory->checkSignatoriesSignatures($object->id, $object->element)) {
                print '<span class="butAction" id="actionButtonLock">' . $displayButton . '</span>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('AllSignatoriesMustHaveSigned', $langs->transnoentities('The' . ucfirst($object->element)))) . '">' . $displayButton . '</span>';
            }

            // Send email.
            $displayButton = $onPhone ? '<i class="fas fa-paper-plane fa-2x"></i>' : '<i class="fas fa-paper-plane"></i>' . ' ' . $langs->trans('SendMail') . ' ';
            if ($object->status >= SaturneCertificate::STATUS_VALIDATED) {
                $fileParams = dol_most_recent_file($upload_dir . '/' . $object->element . 'document' . '/' . $object->ref);
                $file       = $fileParams['fullname'];
                if (file_exists($file) && !strstr($fileParams['name'], 'specimen')) {
                    $forcebuilddoc = 0;
                } else {
                    $forcebuilddoc = 1;
                }
                print '<a class="butAction" id="actionButtonSign" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=presend&forcebuilddoc=' . $forcebuilddoc . '&mode=init#formmailbeforetitle' . '">' .  $displayButton . '</a>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeLockedToSendEmail', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Archive.
            $displayButton = $onPhone ? '<i class="fas fa-archive fa-2x"></i>' : '<i class="fas fa-archive"></i>' . ' ' . $langs->trans('Archive');
            if ($object->status >= SaturneCertificate::STATUS_VALIDATED) {
                print '<a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=confirm_archive&token=' . newToken() . '">' . $displayButton . '</a>';
            } else {
                print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('ObjectMustBeLockedToArchive', ucfirst($langs->transnoentities('The' . ucfirst($object->element))))) . '">' . $displayButton . '</span>';
            }

            // Delete (need delete permission, or if draft, just need create/modify permission).
            $displayButton = $onPhone ? '<i class="fas fa-trash fa-2x"></i>' : '<i class="fas fa-trash"></i>' . ' ' . $langs->trans('Delete');
            print dolGetButtonAction($displayButton, '', 'delete', $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=delete&token=' . newToken(), '', $permissiontodelete || ($object->status == SaturneCertificate::STATUS_DRAFT));
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

        print saturne_show_documents('dolisirh:' . ucfirst($object->element) . 'Document', $dirFiles, $fileDir, $urlSource, $permissiontoadd, $permissiontodelete, $conf->global->DOLISIRH_CERTIFICATEDOCUMENT_DEFAULT_MODEL, 1, 0, 0, 0, 0, '', '', '', $langs->defaultlang, $object, 0, 'remove_file', ($object->status > SaturneCertificate::STATUS_DRAFT), $langs->trans('ObjectMustBeValidatedToGenerate',  ucfirst($langs->transnoentities('The' . ucfirst($object->element)))));

        print '</div><div class="fichehalfright">';

        $moreHtmlCenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-bars imgforviewmode', dol_buildpath('/saturne/view/saturne_agenda.php', 1) . '?id=' . $object->id . '&module_name=DoliSIRH&object_type=' . $object->element);

        // List of actions on element.
        require_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
        $formActions = new FormActions($db);
        $formActions->showactions($object, $object->element . '@' . $object->module, 0, 1, '', 10, '', $moreHtmlCenter);

        print '</div></div>';
    }

    // Presend form.
    $modelmail    = $object->element;
    $defaulttopic = 'InformationMessage';
    $diroutput    = $conf->dolisirh->dir_output;
    $trackid      = $object->element . $object->id;

    require_once DOL_DOCUMENT_ROOT . '/core/tpl/card_presend.tpl.php';
}

// End of page.
llxFooter();
$db->close();
