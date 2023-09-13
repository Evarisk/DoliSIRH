<?php
/* Copyright (C) 2021-2023 EVARISK <technique@evarisk.com>
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
 * \brief   Library files with common functions for Admin conf.
 */

/**
 * Prepare admin pages header.
 *
 * @return array $head Array of tabs.
 */
function dolisirh_admin_prepare_head(): array
{
    // Global variables definitions.
    global $conf, $langs;

    // Load translation files required by the page.
    saturne_load_langs();

    // Initialize values.
    $h    = 0;
    $head = [];

    $head[$h][0] = dol_buildpath('/dolisirh/admin/project.php', 1);
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-project-diagram pictofixedwidth"></i>' . $langs->trans('ProjectsAndTasks') : '<i class="fas fa-project-diagram"></i>';
    $head[$h][2] = 'projecttasks';
    $h++;

    $head[$h][0] = dol_buildpath('/dolisirh/admin/product.php', 1);
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-cube pictofixedwidth"></i>' . $langs->trans('ProductOrService') : '<i class="fas fa-cube"></i>';
    $head[$h][2] = 'productorservice';
    $h++;

    $head[$h][0] = dol_buildpath('/saturne/admin/object.php', 1) . '?module_name=DoliSIRH&object_type=timesheet';
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-calendar-check pictofixedwidth"></i>' . $langs->trans('TimeSheet') : '<i class="fas fa-calendar-check"></i>';
    $head[$h][2] = 'timesheet';
    $h++;

    $head[$h][0] = dol_buildpath('/saturne/admin/object.php', 1) . '?module_name=DoliSIRH&object_type=certificate';
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-user-graduate pictofixedwidth"></i>' . $langs->trans('Certificate') : '<i class="fas fa-user-graduate"></i>';
    $head[$h][2] = 'certificate';
    $h++;

    $head[$h][0] = dol_buildpath('/saturne/admin/documents.php?module_name=DoliSIRH', 1);
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-file-alt pictofixedwidth"></i>' . $langs->trans('YourDocuments') : '<i class="fas fa-file-alt"></i>';
    $head[$h][2] = 'documents';
    $h++;

    $head[$h][0] = dol_buildpath('/dolisirh/admin/setup.php', 1);
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-cog pictofixedwidth"></i>' . $langs->trans('ModuleSettings') : '<i class="fas fa-cog"></i>';
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = dol_buildpath('/saturne/admin/about.php', 1) . '?module_name=DoliSIRH';
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fab fa-readme pictofixedwidth"></i>' . $langs->trans('About') : '<i class="fab fa-readme"></i>';
    $head[$h][2] = 'about';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'dolisirh@dolisirh');

    complete_head_from_modules($conf, $langs, null, $head, $h, 'dolisirh@dolisirh', 'remove');

    return $head;
}
