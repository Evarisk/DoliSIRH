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
 * \file    lib/dolisirh_timesheet.lib.php
 * \ingroup dolisirh
 * \brief   Library files with common functions for TimeSheet
 */

/**
 * Prepare array of tabs for TimeSheet
 *
 * @param  TimeSheet $object TimeSheet
 * @return array             Array of tabs
 */
function timesheet_prepare_head(TimeSheet $object): array
{
	global $conf, $langs;

	$langs->load("dolisirh@dolisirh");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/dolisirh/view/timesheet/timesheet_card.php", 1).'?id='.$object->id;
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
		$head[$h][0] = dol_buildpath('/dolisirh/view/timesheet/timesheet_note.php', 1).'?id='.$object->id;
		$head[$h][1] = '<i class="fas fa-comment pictofixedwidth"></i>' . $langs->trans('Notes');
		if ($nbNote > 0) {
			$head[$h][1] .= (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER) ? '<span class="badge marginleftonlyshort">'.$nbNote.'</span>' : '');
		}
		$head[$h][2] = 'note';
		$h++;
	}

	$head[$h][0] = dol_buildpath("/dolisirh/view/timesheet/timesheet_agenda.php", 1).'?id='.$object->id;
	$head[$h][1] = '<i class="fas fa-calendar-alt pictofixedwidth"></i>' . $langs->trans("Events");
	$head[$h][2] = 'agenda';
	$h++;

	$head[$h][0] = dol_buildpath("/dolisirh/view/timesheet/timesheet_attendants.php", 1) . '?id=' . $object->id;
	$head[$h][1] = '<i class="fas fa-file-signature pictofixedwidth"></i>' . $langs->trans("Attendants");
	$head[$h][2] = 'attendants';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'timesheet@dolisirh');

	return $head;
}
