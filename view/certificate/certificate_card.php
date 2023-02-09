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
 *   	\file       view/certificate/certificate_card.php
 *		\ingroup    dolisirh
 *		\brief      Page to create/edit/view certificate
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
require_once __DIR__ . '/../../class/certificate.class.php';
require_once __DIR__ . '/../../class/dolisirhdocuments/certificatedocument.class.php';
require_once __DIR__ . '/../../lib/dolisirh_certificate.lib.php';
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
$contextpage         = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'certificatecard'; // To manage different context of search
$backtopage          = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');
$lineid              = GETPOST('lineid', 'int');

// Initialize technical objects
$object              = new Certificate($db);
//$objectline          = new CertificateLine($db);
$signatory           = new CertificateSignature($db);
$certificatedocument = new CertificateDocument($db);
$extrafields         = new ExtraFields($db);
$project             = new Project($db);

$hookmanager->initHooks(array('certificatecard', 'globalcard')); // Note that conf->hooks_modules contains array

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
include DOL_DOCUMENT_ROOT . '/core/actions_fetchobject.inc.php'; // Must be include, not include_once.

// There is several ways to check permission.
$permissiontoread   = $user->rights->dolisirh->certificate->read;
$permissiontoadd    = $user->rights->dolisirh->certificate->write; // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
$permissiontodelete = $user->rights->dolisirh->certificate->delete || ($permissiontoadd && isset($object->status) && $object->status == $object::STATUS_DRAFT);
$permissionnote     = $user->rights->dolisirh->certificate->write; // Used by the include of actions_setnotes.inc.php

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

	$backurlforlist = dol_buildpath('/dolisirh/view/certificate/certificate_list.php', 1);

	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
			if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
				$backtopage = $backurlforlist;
			} else {
				$backtopage = dol_buildpath('/dolisirh/view/certificate/certificate_card.php', 1).'?id='.((!empty($id) && $id > 0) ? $id : '__ID__');
			}
		}
	}

	$triggermodname = 'CERTIFICATE_MODIFY'; // Name of trigger action code to execute when we modify record

	// Actions cancel, add, update, update_extras, confirm_validate, confirm_delete, confirm_deleteline, confirm_clone, confirm_close, confirm_setdraft, confirm_reopen
    $conf->global->MAIN_DISABLE_PDF_AUTOUPDATE = 1;
	include DOL_DOCUMENT_ROOT . '/core/actions_addupdatedelete.inc.php';

	// Action to move up and down lines of object
	//include DOL_DOCUMENT_ROOT.'/core/actions_lineupdown.inc.php';

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

        $result = $certificatedocument->generateDocument($model, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams);
        if ($result <= 0) {
            setEventMessages($object->error, $object->errors, 'errors');
            $action = '';
        } else {
            if (empty($donotredirect)) {
                setEventMessages($langs->trans("FileGenerated") . ' - ' . $certificatedocument->last_main_doc, null);
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
	$triggersendname = 'DOLISIRH_CERTIFICATE_SENTBYMAIL';
	$autocopy = 'MAIN_MAIL_AUTOCOPY_CERTIFICATE_TO';
	$trackid = 'certificate'.$object->id;
	include DOL_DOCUMENT_ROOT . '/core/actions_sendmails.inc.php';
}

/*
 * View
 */

// Initialize view objects
$form = new Form($db);

$title    = $langs->trans("Certificate");
$help_url = 'FR:Module_DoliSIRH';
$morejs   = array("/dolisirh/js/dolisirh.js");
$morecss  = array("/dolisirh/css/dolisirh.css");

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss);

// Part to create
if ($action == 'create') {
	if (empty($permissiontoadd)) {
		accessforbidden($langs->trans('NotEnoughPermissions'), 0, 1);
		exit;
	}

	print load_fiche_titre($langs->trans("NewCertificate"), '', 'object_'.$object->picto);

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

	$object->fields['fk_project']['default'] = $conf->global->DOLISIRH_HR_PROJECT;

	// Common attributes
	include DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_add.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_add.tpl.php';

	print '</table>'."\n";

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel("Create");

	print '</form>';

	//dol_set_focus('input[name="ref"]');
}

// Part to edit record
if (($id || $ref) && $action == 'edit') {
	print load_fiche_titre($langs->trans("ModifyCertificate"), '', 'object_'.$object->picto);

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

	// Common attributes
	include DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_edit.tpl.php';

	// Other attributes
	include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_edit.tpl.php';

	print '</table>';

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel();

	print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
	$res = $object->fetch_optionals();

	$head = certificatePrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("Certificate"), -1, $object->picto);

	$formconfirm = '';
	// Confirmation to delete
	if ($action == 'delete') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteCertificate'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 1);
	}
//	// Confirmation to delete line
//	if ($action == 'deleteline') {
//		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&lineid='.$lineid, $langs->trans('DeleteLine'), $langs->trans('ConfirmDeleteLine'), 'confirm_deleteline', '', 0, 1);
//	}

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
	$linkback = '<a href="' . dol_buildpath('/dolisirh/view/certificate/certificate_list.php', 1) . '">' . $langs->trans("BackToList") . '</a>';

	$morehtmlref = '<div class="refidno">';
    // Thirdparty
    if (! empty($conf->societe->enabled)) {
        $object->fetch_thirdparty($object->fk_soc);
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

    $object->picto = 'certificate_small@dolisirh';
	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">'."\n";

	// Common attributes
	//$keyforbreak='fieldkeytoswitchonsecondcolumn';	// We change column just before this field

	unset($object->fields['fk_project']);				// Hide field already shown in banner
	unset($object->fields['fk_soc']);					// Hide field already shown in banner

	include DOL_DOCUMENT_ROOT . '/core/tpl/commonfields_view.tpl.php';

	// Other attributes. Fields from hook formObjectOptions and Extrafields.
	include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_view.tpl.php';

	print '</table>';
	print '</div>';
	print '</div>';

	print '<div class="clearboth"></div>';

	print dol_get_fiche_end();

//	/*
//	 * Lines
//	 */
//
//	if (!empty($object->table_element_line)) {
//		// Show object lines
//		$result = $object->getLinesArray();
//
//		print '	<form name="addproduct" id="addproduct" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.(($action != 'editline') ? '' : '#line_'.GETPOST('lineid', 'int')).'" method="POST">
//		<input type="hidden" name="token" value="' . newToken().'">
//		<input type="hidden" name="action" value="' . (($action != 'editline') ? 'addline' : 'updateline').'">
//		<input type="hidden" name="mode" value="">
//		<input type="hidden" name="page_y" value="">
//		<input type="hidden" name="id" value="' . $object->id.'">
//		';
//
//		if (!empty($conf->use_javascript_ajax) && $object->status == 0) {
//			include DOL_DOCUMENT_ROOT . '/core/tpl/ajaxrow.tpl.php';
//		}
//
//		print '<div class="div-table-responsive-no-min">';
//		if (!empty($object->lines) || ($object->status == $object::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
//			print '<table id="tablelines" class="noborder noshadow" width="100%">';
//		}
//
//		if (!empty($object->lines)) {
//			$object->printObjectLines($action, $mysoc, null, GETPOST('lineid', 'int'), 1);
//		}
//
//		// Form to add new line
//		if ($object->status == 0 && $permissiontoadd && $action != 'selectlines') {
//			if ($action != 'editline') {
//				// Add products/services form
//
//				$parameters = array();
//				$reshook = $hookmanager->executeHooks('formAddObjectLine', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
//				if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
//				if (empty($reshook))
//					$object->formAddObjectLine(1, $mysoc, $soc);
//			}
//		}
//
//		if (!empty($object->lines) || ($object->status == $object::STATUS_DRAFT && $permissiontoadd && $action != 'selectlines' && $action != 'editline')) {
//			print '</table>';
//		}
//		print '</div>';
//
//		print "</form>\n";
//	}

	// Buttons for actions
	if ($action != 'presend' && $action != 'editline') {
		print '<div class="tabsAction">'."\n";
		$parameters = array();
		$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
		if ($reshook < 0) {
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}

		if (empty($reshook)) {
            // Modify
			print '<a class="' . ($object->status == $object::STATUS_DRAFT ? 'butAction' : 'butActionRefused classfortooltip') . '" id="actionButtonEdit" title="' . ($object->status == $object::STATUS_DRAFT ? '' : dol_escape_htmltag($langs->trans("CertificateMustBeDraft"))) . '" href="' . ($object->status == $object::STATUS_DRAFT ? ($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=edit') : '#') . '">' . $langs->trans("Modify") . '</a>';

            // Validate
            print '<span class="' . ($object->status == $object::STATUS_DRAFT ? 'butAction' : 'butActionRefused classfortooltip') . '" id="' . ($object->status == $object::STATUS_DRAFT ? 'actionButtonPendingSignature' : '') . '" title="' . ($object->status == $object::STATUS_DRAFT ? '' : dol_escape_htmltag($langs->trans("TimeSheetMustBeDraftToValidate"))) . '" href="' . ($object->status == $object::STATUS_DRAFT ? ($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=setPendingSignature') : '#') . '">' . $langs->trans("Validate") . '</span>';

            // Send
            //@TODO changer le send to
            //print '<a class="' . ($object->status == $object::STATUS_LOCKED ? 'butAction' : 'butActionRefused classfortooltip') . '" id="actionButtonSign" title="' . dol_escape_htmltag($langs->trans("TimeSheetMustBeLockedToSendEmail")) . '" href="' . ($object->status == $object::STATUS_LOCKED ? ($_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=presend&mode=init#formmailbeforetitle&sendto=' . $allLinks['LabourInspectorSociety']->id[0]) : '#') . '">' . $langs->trans('SendMail') . '</a>';

            // Archive
            print '<a class="' . ($object->status == $object::STATUS_VALIDATED  && !empty(dol_dir_list($upload_dir . '/certificatedocument/' . dol_sanitizeFileName($object->ref))) ? 'butAction' : 'butActionRefused classfortooltip') . '" id="" title="' . ($object->status == $object::STATUS_VALIDATED && !empty(dol_dir_list($upload_dir . '/certificatedocument/' . dol_sanitizeFileName($object->ref))) ? '' : dol_escape_htmltag($langs->trans("CertificateMustBeLockedGenerated"))) . '" href="' . ($object->status == $object::STATUS_VALIDATED && !empty(dol_dir_list($upload_dir . '/certificatedocument/' . dol_sanitizeFileName($object->ref))) ? ($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&action=setArchived') : '#') . '">' . $langs->trans("Archive") . '</a>';

			// Clone
			//print dolGetButtonAction($langs->trans('ToClone'), '', 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.(!empty($object->socid)?'&socid='.$object->socid:'').'&action=clone&token='.newToken(), '', $permissiontoadd);

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

            print doliSirhShowDocuments('dolisirh:CertificateDocument', $dir_files, $filedir, $urlsource, $genallowed, $object->status == $object::STATUS_VALIDATED ? $delallowed : 0, $conf->global->DOLISIRH_CERTIFICATEDOCUMENT_DEFAULT_MODEL, 1, 0, 0, 0, 0, '', '', '', $langs->defaultlang, $object, 0, 'removefile', $object->status == $object::STATUS_VALIDATED && empty(dol_dir_list($filedir)), $langs->trans('CertificateMustBeLocked'));
		}

		print '</div><div class="fichehalfright">';

		$MAXEVENT = 10;

		$morehtmlcenter = dolGetButtonTitle($langs->trans('SeeAll'), '', 'fa fa-list-alt imgforviewmode', dol_buildpath('/dolisirh/view/certificate/certificate_agenda.php', 1).'?id='.$object->id);

		// List of actions on element
		include_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
		$formactions = new FormActions($db);
		$somethingshown = $formactions->showactions($object, $object->element.'@'.$object->module, (is_object($object->thirdparty) ? $object->thirdparty->id : 0), 1, '', $MAXEVENT, '', $morehtmlcenter);

		print '</div></div>';
	}

	//Select mail models is same action as presend
	if (GETPOST('modelselected')) {
		$action = 'presend';
	}

	// Presend form
	$modelmail = 'certificate';
	$defaulttopic = 'InformationMessage';
	$diroutput = $conf->dolisirh->dir_output;
	$trackid = 'certificate'.$object->id;

	include DOL_DOCUMENT_ROOT . '/core/tpl/card_presend.tpl.php';
}

// End of page
llxFooter();
$db->close();
