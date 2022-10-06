<?php
/* Copyright (C) 2022 EOXIA <dev@eoxia.com>
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
 *   	\file       view/timesheet/timesheet_attendants.php
 *		\ingroup    dolisirh
 *		\brief      Page to add/edit/view timesheet_signature
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

require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';

require_once __DIR__ . '/../../class/timesheet.class.php';
require_once __DIR__ . '/../../lib/dolisirh_timesheet.lib.php';
require_once __DIR__ . '/../../lib/dolisirh_function.lib.php';

global $db, $hookmanager, $langs, $user;

// Load translation files required by the page
$langs->loadLangs(array("dolisirh@dolisirh", "other"));

// Get parameters
$id          = GETPOST('id', 'int');
$ref         = GETPOST('ref', 'alpha');
$action      = GETPOST('action', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'timesheetsignature'; // To manage different context of search
$backtopage  = GETPOST('backtopage', 'alpha');
$cancel      = GETPOST('cancel', 'aZ09');

// Initialize technical objects
$object     = new TimeSheet($db);
$signatory  = new TimeSheetSignature($db);
$usertmp    = new User($db);
$contact    = new Contact($db);
$form       = new Form($db);
$project    = new Project($db);
$thirdparty = new Societe($db);

$object->fetch($id);

$hookmanager->initHooks(array($object->element.'signature', 'globalcard')); // Note that conf->hooks_modules contains array

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be include, not include_once  // Must be include, not include_once. Include fetch and fetch_thirdparty but not fetch_optionals

//Security check
$object_type = $object->element;
$permissiontoread   = $user->rights->dolisirh->$object_type->read;
$permissiontoadd    = $user->rights->dolisirh->$object_type->write;
$permissiontodelete = $user->rights->dolisirh->$object_type->delete;

if ( ! $permissiontoread) accessforbidden();

/*
 * Actions
 */

$parameters = array();
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($backtopage) || ($cancel && empty($id))) {
	if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
		$backtopage = dol_buildpath('/dolisirh/view/'. $object->element .'/' . $object->element .'_attendants.php', 1) . '?id=' . ($object->id > 0 ? $object->id : '__ID__');
	}
}

// Action to add internal attendant
if ($action == 'addSocietyAttendant') {
	$error = 0;
	$object->fetch($id);
	$attendant_id = GETPOST('user_attendant');

	if ( ! $error) {
		$role = strtoupper(GETPOST('attendantRole'));
		$result = $signatory->setSignatory($object->id, $object->element, 'user', array($attendant_id), strtoupper($object->element).'_' . $role, $role == 'SOCIETY_RESPONSIBLE ' ? 0 : 1);
		if ($result > 0) {
			$usertmp = $user;
			$usertmp->fetch($attendant_id);
			setEventMessages($langs->trans('AddAttendantMessage') . ' ' . $usertmp->firstname . ' ' . $usertmp->lastname, array());
			$signatory->call_trigger('DOLISIRHSIGNATURE_ADDATTENDANT', $user);
			// Creation attendant OK
			$urltogo = str_replace('__ID__', $result, $backtopage);
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
			header("Location: " . $urltogo);
			exit;
		} else {
			// Creation attendant KO
			if ( ! empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
			else setEventMessages($object->error, null, 'errors');
		}
	}
}

// Action to add record
if ($action == 'addSignature') {
	$signatoryID  = GETPOST('signatoryID');
	$data = json_decode(file_get_contents('php://input'), true);

	$signatory->fetch($signatoryID);
	$signatory->signature      = $data['signature'];
	$signatory->signature_date = dol_now('tzuser');

	if ( ! $error) {
		$result = $signatory->update($user, false);

		if ($result > 0) {
			// Creation signature OK
			$signatory->setSigned($user, 0);
			setEventMessages($langs->trans('SignatureEvent') . ' ' . $signatory->firstname . ' ' . $signatory->lastname, array());
			$urltogo = str_replace('__ID__', $result, $backtopage);
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
			header("Location: " . $urltogo);
			exit;
		} else {
			// Creation signature KO
			if ( ! empty($signatory->errors)) setEventMessages(null, $signatory->errors, 'errors');
			else setEventMessages($signatory->error, null, 'errors');
		}
	}
}

// Action to set status STATUS_ABSENT
if ($action == 'setAbsent') {
	$signatoryID = GETPOST('signatoryID');

	$signatory->fetch($signatoryID);

	if ( ! $error) {
		$result = $signatory->setAbsent($user, 0);
		if ($result > 0) {
			// set absent OK
			setEventMessages($langs->trans('Attendant') . ' ' . $signatory->firstname . ' ' . $signatory->lastname . ' ' . $langs->trans('SetAbsentAttendant'), array());
			$urltogo = str_replace('__ID__', $result, $backtopage);
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
			header("Location: " . $urltogo);
			exit;
		} else {
			// set absent KO
			if ( ! empty($signatory->errors)) setEventMessages(null, $signatory->errors, 'errors');
			else setEventMessages($signatory->error, null, 'errors');
		}
	}
}

// Action to send Email
if ($action == 'send') {
	$signatoryID = GETPOST('signatoryID');
	$signatory->fetch($signatoryID);

	if ( ! $error) {
		$langs->load('mails');

		if (!dol_strlen($signatory->email)) {
			if ($signatory->element_type == 'user') {
				$usertmp = $user;
				$usertmp->fetch($signatory->element_id);
				if (dol_strlen($usertmp->email)) {
					$signatory->email = $usertmp->email;
					$signatory->update($user, true);
				}
			} else if ($signatory->element_type == 'socpeople') {
				$contact->fetch($signatory->element_id);
				if (dol_strlen($contact->email)) {
					$signatory->email = $contact->email;
					$signatory->update($user, true);
				}
			}
		}

		$sendto = $signatory->email;

		if (dol_strlen($sendto) && ( ! empty($conf->global->MAIN_MAIL_EMAIL_FROM))) {
			require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';

			$from = $conf->global->MAIN_MAIL_EMAIL_FROM;
			$url  = dol_buildpath('/custom/dolisirh/public/signature/add_signature.php?track_id=' . $signatory->signature_url  . '&type=' . $object->element, 3);

			$message = $langs->trans('SignatureEmailMessage') . ' ' . $url;
			$subject = $langs->trans('SignatureEmailSubject') . ' ' . $object->ref;

			// Create form object
			// Send mail (substitutionarray must be done just before this)
			$mailfile = new CMailFile($subject, $sendto, $from, $message, array(), array(), array(), "", "", 0, -1, '', '', '', '', 'mail');

			if ($mailfile->error) {
				setEventMessages($mailfile->error, $mailfile->errors, 'errors');
			} else {
				if ( ! empty($conf->global->MAIN_MAIL_SMTPS_ID)) {
					$result = $mailfile->sendfile();
					if ($result) {
						$signatory->last_email_sent_date = dol_now('tzuser');
						$signatory->update($user, true);
						$signatory->setPending($user, false);
						setEventMessages($langs->trans('SendEmailAt') . ' ' . $signatory->email, array());
						// This avoid sending mail twice if going out and then back to page
						header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
						exit;
					} else {
						$langs->load("other");
						$mesg = '<div class="error">';
						if ($mailfile->error) {
							$mesg .= $langs->transnoentities('ErrorFailedToSendMail', dol_escape_htmltag($from), dol_escape_htmltag($sendto));
							$mesg .= '<br>' . $mailfile->error;
						} else {
							$mesg .= $langs->transnoentities('ErrorFailedToSendMail', dol_escape_htmltag($from), dol_escape_htmltag($sendto));
						}
						$mesg .= '</div>';
						setEventMessages($mesg, null, 'warnings');
					}
				} else {
					setEventMessages($langs->trans('ErrorSetupEmail'), '', 'errors');
				}
			}
		} else {
			$langs->load("errors");
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv("MailTo")), null, 'warnings');
			dol_syslog('Try to send email with no recipient defined', LOG_WARNING);
		}
	} else {
		// Mail sent KO
		if ( ! empty($signatory->errors)) setEventMessages(null, $signatory->errors, 'errors');
		else setEventMessages($signatory->error, null, 'errors');
	}
}

// Action to delete attendant
if ($action == 'deleteAttendant') {
	$signatoryToDeleteID = GETPOST('signatoryID');
	$signatory->fetch($signatoryToDeleteID);

	if ( ! $error) {
		$result = $signatory->setDeleted($user, 0);
		if ($result > 0) {
			setEventMessages($langs->trans('DeleteAttendantMessage') . ' ' . $signatory->firstname . ' ' . $signatory->lastname, array());
			// Deletion attendant OK
			$urltogo = str_replace('__ID__', $result, $backtopage);
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
			header("Location: " . $urltogo);
			exit;
		} else {
			// Deletion attendant KO
			if ( ! empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
			else setEventMessages($object->error, null, 'errors');
		}
	}
}

/*
 *  View
 */

$title    = $langs->trans("TimeSheetAttendants");
$help_url = '';
$morejs   = array("/dolisirh/js/signature-pad.min.js", "/dolisirh/js/dolisirh.js.php");
$morecss  = array("/dolisirh/css/dolisirh.css");

llxHeader('', $title, $help_url, '', '', '', $morejs, $morecss);

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
	if ( ! empty($object->id)) $res = $object->fetch_optionals();

	// Object card
	// ------------------------------------------------------------

	$prepareHead = $object->element . 'PrepareHead';
	$head = $prepareHead($object);
	print dol_get_fiche_head($head, 'attendants', $langs->trans("TimeSheet"), -1, 'dolisirh@dolisirh');

	$linkback = '<a href="'.dol_buildpath('/dolisirh/view/timesheet/timesheet_list.php', 1).'">'.$langs->trans("BackToList").'</a>';

	$morehtmlref = '<div class="refidno">';
	// Thirdparty
	if (! empty($conf->societe->enabled)) {
		$object->fetch_thirdparty();
		$morehtmlref .= $langs->trans('ThirdParty') . ' : ' . (is_object($object->thirdparty) ? $object->thirdparty->getNomUrl(1) : '');
	}
	// Project
	if (! empty($conf->projet->enabled)) {
		$langs->load("projects");
		$morehtmlref .= '<br>' . $langs->trans('Project') . ' ';
		if (! empty($object->fk_project)) {
			$project->fetch($object->fk_project);
			$morehtmlref .= ': ' . $project->getNomUrl(1, '', 1);
		} else {
			$morehtmlref .= '';
		}
	}
	$morehtmlref .= '</div>';

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div class="underbanner clearboth"></div>';

	print dol_get_fiche_end();

	print '<div class="fichecenter">'; ?>

	<?php if ( $object->status == $object::STATUS_DRAFT ) : ?>
		<div class="wpeo-notice notice-warning">
			<div class="notice-content">
				<div class="notice-title"><?php echo $langs->trans('DisclaimerSignatureTitle') ?></div>
				<div class="notice-subtitle"><?php echo $langs->trans("TimeSheetMustBeValidatedToSign") ?></div>
			</div>
			<a class="butAction" style="width = 100%;margin-right:0" href="<?php echo DOL_URL_ROOT ?>/custom/dolisirh/view/<?php echo $object->element ?>/<?php echo $object->element ?>_card.php?id=<?php echo $id ?>"><?php echo $langs->trans("GoToValidate") ?></a>;
		</div>
	<?php endif; ?>
		<div class="noticeSignatureSuccess wpeo-notice notice-success hidden">
			<div class="all-notice-content">
				<div class="notice-content">
					<div class="notice-title"><?php echo $langs->trans('AddSignatureSuccess') ?></div>
					<div class="notice-subtitle"><?php echo $langs->trans("AddSignatureSuccessText") . GETPOST('signature_id')?></div>
				</div>
			</div>
		</div>
	<?php

	print '<div class="signatures-container">';

	if ($signatory->checkSignatoriesSignatures($object->id, $object->element) && $object->status < $object::STATUS_LOCKED) {
		print '<a class="butAction" style="width = 100%;margin-right:0" href="' . DOL_URL_ROOT . '/custom/dolisirh/view/' . $object->element . '/' . $object->element . '_card.php?id=' . $id . '">' . $langs->trans("GoToLock") . '</a>';
	}

	//Society attendants -- Participants de la société
	$society_intervenants = $signatory->fetchSignatory(strtoupper($object->element).'_SOCIETY_ATTENDANT', $object->id, $object->element);
	$society_responsible  = $signatory->fetchSignatory(strtoupper($object->element).'_SOCIETY_RESPONSIBLE', $object->id, $object->element);

	$society_intervenants = array_merge($society_intervenants, $society_responsible);

	print load_fiche_titre($langs->trans("Attendants"), '', '');

	print '<table class="border centpercent tableforfield">';

	print '<tr class="liste_titre">';
	print '<td>' . $langs->trans("Name") . '</td>';
	print '<td>' . $langs->trans("Role") . '</td>';
	print '<td class="center">' . $langs->trans("SignatureLink") . '</td>';
	print '<td class="center">' . $langs->trans("SendMailDate") . '</td>';
	print '<td class="center">' . $langs->trans("SignatureDate") . '</td>';
	print '<td class="center">' . $langs->trans("Status") . '</td>';
	print '<td class="center">' . $langs->trans("ActionsSignature") . '</td>';
	print '<td class="center">' . $langs->trans("Signature") . '</td>';
	print '</tr>';

	$already_added_users = array();
	$j = 1;
	if (is_array($society_intervenants) && ! empty($society_intervenants) && $society_intervenants > 0) {
		foreach ($society_intervenants as $element) {
			$usertmp = $user;
			$usertmp->fetch($element->element_id);
			print '<tr class="oddeven"><td class="minwidth200">';
			print $usertmp->getNomUrl(1);
			print '</td><td>';
			print $langs->trans($element->role);
			print '</td><td class="center">';
			if ($object->status == $object::STATUS_VALIDATED) {
				$signatureUrl = dol_buildpath('/custom/dolisirh/public/signature/add_signature.php?track_id=' . $element->signature_url  . '&type=' . $object->element, 3);
				print '<a href=' . $signatureUrl . ' target="_blank"><i class="fas fa-external-link-alt"></i></a>';
			} else {
				print '-';
			}

			print '</td><td class="center">';
			print dol_print_date($element->last_email_sent_date, 'dayhour');
			print '</td><td class="center">';
			print dol_print_date($element->signature_date, 'dayhour');
			print '</td><td class="center">';
			print $element->getLibStatut(5);
			print '</td>';
			print '<td class="center">';
			if ($permissiontoadd && $object->status < $object::STATUS_LOCKED) {
				require __DIR__ . "/../../core/tpl/signature/dolisirh_signature_action_view.tpl.php";
			}
			print '</td>';
			print '<td class="center">';
			if ($element->signature != $langs->transnoentities("FileGenerated") && $permissiontoadd) {
				require __DIR__ . "/../../core/tpl/signature/dolisirh_signature_view.tpl.php";
			}
			print '</td>';
			print '</tr>';
			$already_added_users[$element->element_id] = $element->element_id;
			$j++;
		}
	} else {
		print '<tr><td>';
		print $langs->trans('NoSocietyAttendants');
		print '</td></tr>';
	}

	if ($object->status == $object::STATUS_DRAFT && $conf->global->DOLISIRH_TIMESHEET_ADD_ATTENDANTS && $permissiontoadd) {
		print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
		print '<input type="hidden" name="token" value="' . newToken() . '">';
		print '<input type="hidden" name="action" value="addSocietyAttendant">';
		print '<input type="hidden" name="id" value="' . $id . '">';
		print '<input type="hidden" name="backtopage" value="' . $backtopage . '">';

		//Participants interne
		print '<tr class="oddeven"><td class="maxwidth200">';
		print $form->select_dolusers('', 'user_attendant', 1, $already_added_users, 0, '', '', $conf->entity);
		print '</td>';
		print '<td>';
		print '<select class="minwidth200" id="attendantRole" name="attendantRole">';
		print '<option value="society_attendant">' . $langs->trans("SocietyAttendant") . '</option>';
		print '<option value="society_responsible">' . $langs->trans("SocietyResponsible") . '</option>';
		print '</select>';
		print ajax_combobox('attendantRole');
		print '</td><td class="center">';
		print '-';
		print '</td><td class="center">';
		print '-';
		print '</td><td class="center">';
		print '-';
		print '</td><td class="center">';
		print '-';
		print '</td><td class="center">';
		print '<button type="submit" class="wpeo-button button-blue but" name="addline" id="addline"><i class="fas fa-plus"></i>  ' . $langs->trans('Add') . '</button>';
		print '<td class="center">';
		print '-';
		print '</td>';
		print '</tr>';
		print '</table>';
		print '</form>';
	}
	print '</div>';
}

// End of page
llxFooter();
$db->close();

