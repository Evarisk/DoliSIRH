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
 * \file    dolisirhindex.php
 * \ingroup dolisirh
 * \brief   Home page of dolisirh top menu
 */

// Load DoliSIRH environment
if (file_exists('dolisirh.main.inc.php')) {
    require_once __DIR__ . '/dolisirh.main.inc.php';
} elseif (file_exists('../dolisirh.main.inc.php')) {
    require_once __DIR__ . '/../dolisirh.main.inc.php';
} else {
    die('Include of dolisirh main fails');
}

// Global variables definitions.
global $conf, $db, $langs, $user;

// Initialize view objects
$form = new Form($db);

// Get parameters.
$userID = GETPOSTISSET('search_userid') ? GETPOST('search_userid', 'int') : $user->id;

$currentMonth = date('m', dol_now());
$currentYear  = date('Y', dol_now());

$showYears = (empty($conf->global->MAIN_STATS_GRAPHS_SHOW_N_YEARS) ? 2 : max(1, min(10, $conf->global->MAIN_STATS_GRAPHS_SHOW_N_YEARS)));
while ($showYears != 0) {
    $years[] = $currentYear - $showYears;
    $showYears--;
}
$years[] = $currentYear;

$morehtmlright = ' ' . img_picto($langs->trans('Filter') . ' ' . $langs->trans('Year'), 'title_agenda', 'class="paddingright pictofixedwidth"') . $form::selectarray('search_year', $years, $currentYear, 0,0, 1, '', 0, 0, 0, '', 'maxwidth100');
$months = [1 => ucfirst($langs->trans('January')), 2 => ucfirst($langs->trans('February')), 3 => ucfirst($langs->trans('March')), 4 => ucfirst($langs->trans('April')), 5 => ucfirst($langs->trans('May')), 6 => ucfirst($langs->trans('June')), 7 => ucfirst($langs->trans('July')), 8 => ucfirst($langs->trans('August')), 9 => ucfirst($langs->trans('September')), 10 => ucfirst($langs->trans('October')), 11 => ucfirst($langs->trans('November')), 12 => ucfirst($langs->trans('December'))];
$morehtmlright .= ' ' . img_picto($langs->trans('Filter') . ' ' . $langs->trans('Month'), 'title_agenda', 'class="paddingright pictofixedwidth"') . $form::selectarray('search_month', $months, $currentMonth, 0,0, 0, '', 1, 0, 0, '', 'maxwidth100');
$morehtmlright .= ' ' . img_picto($langs->trans('Filter') . ' ' . $langs->trans('User'), 'user', 'class="paddingright pictofixedwidth"') . $form->select_dolusers($userID, 'search_userid', '', null, 0, '', null, 0, 0, 0, ' AND u.employee = 1', 0, '', 'maxwidth300', 1);
$morehtmlright .= '<div class="wpeo-button button-primary button-square-30 select-dataset-dashboard-info" style="color: #ffffff !important;"><i class="button-icon fas fa-redo"></i></div>';

require_once __DIR__ . '/../saturne/core/tpl/index/index_view.tpl.php';
