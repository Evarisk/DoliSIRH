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
 * \file    lib/dolisirh.lib.php
 * \ingroup dolisirh
 * \brief   Library files with common functions for DoliSIRH
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function dolisirhAdminPrepareHead(): array
{
	global $conf, $langs;

	$langs->load("dolisirh@dolisirh");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/dolisirh/admin/project.php", 1);
	$head[$h][1] = '<i class="fas fa-project-diagram pictofixedwidth" style="padding-right: 4px;"></i>' . $langs->trans("ProjectsAndTasks");
	$head[$h][2] = 'projecttasks';
	$h++;

	$head[$h][0] = dol_buildpath("/dolisirh/admin/timesheet.php", 1);
	$head[$h][1] = '<i class="fas fa-calendar-check pictofixedwidth"></i>' . $langs->trans("TimeSheet");
	$head[$h][2] = 'timesheet';
	$h++;

//	$head[$h][0] = dol_buildpath("/dolisirh/admin/certificate.php", 1);
//	$head[$h][1] = '<i class="fas fa-user-graduate pictofixedwidth"></i>' . $langs->trans("Certificate");
//	$head[$h][2] = 'certificate';
//	$h++;

	$head[$h][0] = dol_buildpath("/dolisirh/admin/dolisirhdocuments.php", 1);
	$head[$h][1] = '<i class="fas fa-file-alt pictofixedwidth"></i>' . $langs->trans("YourDocuments");
	$head[$h][2] = 'dolisirhdocuments';
	$h++;

	$head[$h][0] = dol_buildpath("/dolisirh/admin/setup.php", 1);
	$head[$h][1] = '<i class="fas fa-cog pictofixedwidth"></i>' . $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath("/dolisirh/admin/about.php", 1);
	$head[$h][1] = '<i class="fab fa-readme pictofixedwidth" style="padding-right: 4px;"></i>' . $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'dolisirh');

	return $head;
}
