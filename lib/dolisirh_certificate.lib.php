<?php
/* Copyright (C) 2023 EVARISK <dev@evarisk.com>
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
 * \file    lib/dolisirh_certificate.lib.php
 * \ingroup dolisirh
 * \brief   Library files with common functions for Certificate
 */

/**
 * Prepare array of tabs for Certificate
 *
 * @param	Certificate	$object		Certificate
 * @return 	array					Array of tabs
 */
function certificatePrepareHead($object)
{
	global $db, $langs, $conf;

	$langs->load("dolisirh@dolisirh");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/dolisirh/view/certificate/certificate_card.php", 1).'?id='.$object->id;
	$head[$h][1] = '<i class="fas fa-info-circle pictofixedwidth"></i>' . $langs->trans("Card");
	$head[$h][2] = 'card';
	$h++;

	if (isset($object->fields['note_public']) || isset($object->fields['note_private'])) {
		$nbNote = 0;
		if (!empty($object->note_private)) {
			$nbNote++;
		}
		if (!empty($object->note_public)) {
			$nbNote++;
		}
		$head[$h][0] = dol_buildpath('/dolisirh/view/certificate/certificate_note.php', 1).'?id='.$object->id;
		$head[$h][1] = '<i class="fas fa-comment pictofixedwidth"></i>' . $langs->trans('Notes');
		if ($nbNote > 0) {
			$head[$h][1] .= (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '<span class="badge marginleftonlyshort">'.$nbNote.'</span>' : '');
		}
		$head[$h][2] = 'note';
		$h++;
	}

//	require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
//	require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
//	$upload_dir = $conf->dolisirh->dir_output."/certificate/".dol_sanitizeFileName($object->ref);
//	$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
//	$nbLinks = Link::count($db, $object->element, $object->id);
//	$head[$h][0] = dol_buildpath("/dolisirh/view/certificate/certificate_document.php", 1).'?id='.$object->id;
//	$head[$h][1] = $langs->trans('Documents');
//	if (($nbFiles + $nbLinks) > 0) {
//		$head[$h][1] .= '<span class="badge marginleftonlyshort">'.($nbFiles + $nbLinks).'</span>';
//	}
//	$head[$h][2] = 'document';
//	$h++;

	$head[$h][0] = dol_buildpath("/dolisirh/view/certificate/certificate_agenda.php", 1).'?id='.$object->id;
	$head[$h][1] = '<i class="fas fa-calendar-alt pictofixedwidth"></i>' . $langs->trans("Events");
	$head[$h][2] = 'agenda';
	$h++;

	$head[$h][0] = dol_buildpath("/dolisirh/view/certificate/certificate_attendants.php", 1) . '?id=' . $object->id;
	$head[$h][1] = '<i class="fas fa-file-signature pictofixedwidth"></i>' . $langs->trans("Attendants");
	$head[$h][2] = 'attendants';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'certificate@dolisirh');

	return $head;
}
