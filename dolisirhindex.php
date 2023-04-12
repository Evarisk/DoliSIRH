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
 *	\file       dolisirhindex.php
 *	\ingroup    dolisirh
 *	\brief      Home page of dolisirh top menu
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if ( ! $res && ! empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if ( ! $res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . '/main.inc.php')) $res          = @include substr($tmp, 0, ($i + 1)) . '/main.inc.php';
if ( ! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php')) $res = @include dirname(substr($tmp, 0, ($i + 1))) . '/main.inc.php';
// Try main.inc.php using relative path
if ( ! $res && file_exists('../../main.inc.php')) $res    = @include '../../main.inc.php';
if ( ! $res && file_exists('../../../main.inc.php')) $res = @include '../../../main.inc.php';
if ( ! $res) die('Include of main fails');

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';

require_once __DIR__ . '/core/modules/modDoliSIRH.class.php';
require_once __DIR__ . '/class/dashboarddolisirhstats.class.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
$langs->load('dolisirh@dolisirh');

// Initialize technical objects
$modDoliSIRH = new modDoliSIRH($db);
$stats       = new DashboardDolisirhStats($db);

// Initialize view objects
$form = new Form($db);

// Get parameters
$action = GETPOST('action', 'alpha');
$userID = GETPOSTISSET('search_userid') ? GETPOST('search_userid', 'int') : $user->id;

// Security check
$permissiontoread = $user->rights->dolisirh->read;
if (empty($conf->dolisirh->enabled) || !$permissiontoread) {
    accessforbidden();
}

/*
 * View
 */

$help_url = 'FR:Module_DoliSIRH';
$title    = $langs->trans('DoliSIRHArea');
$morejs   = ['/dolisirh/js/dolisirh.js'];
$morecss  = ['/dolisirh/css/dolisirh.css'];

llxHeader('', $title . ' ' . $modDoliSIRH->version, $help_url, '', 0, 0, $morejs, $morecss);

$currentMonth = date('m', dol_now());
$currentYear  = date('Y', dol_now());

//$years = $currentYear - (empty($conf->global->MAIN_STATS_GRAPHS_SHOW_N_YEARS) ? 2 : max(1, min(10, $conf->global->MAIN_STATS_GRAPHS_SHOW_N_YEARS)));

$years = [2021, 2022, 2023];
$morehtmlright = ' ' . img_picto($langs->trans('Filter') . ' ' . $langs->trans('Year'), 'title_agenda', 'class="paddingright pictofixedwidth"') . $form->selectarray('search_year', $years, $currentYear, 0,0, 1, '', 0, 0, 0, '', 'maxwidth100');
$months = [1 => ucfirst($langs->trans('January')), 2 => ucfirst($langs->trans('February')), 3 => ucfirst($langs->trans('March')), 4 => ucfirst($langs->trans('April')), 5 => ucfirst($langs->trans('May')), 6 => ucfirst($langs->trans('June')), 7 => ucfirst($langs->trans('July')), 8 => ucfirst($langs->trans('August')), 9 => ucfirst($langs->trans('September')), 10 => ucfirst($langs->trans('October')), 11 => ucfirst($langs->trans('November')), 12 => ucfirst($langs->trans('December'))];
$morehtmlright .= ' ' . img_picto($langs->trans('Filter') . ' ' . $langs->trans('Month'), 'title_agenda', 'class="paddingright pictofixedwidth"') . $form->selectarray('search_month', $months, $currentMonth, 0,0, 0, '', 1, 0, 0, '', 'maxwidth100');
$morehtmlright .= ' ' . img_picto($langs->trans('Filter') . ' ' . $langs->trans('User'), 'user', 'class="paddingright pictofixedwidth"') . $form->select_dolusers($userID, 'search_userid', '', null, 0, '', null, 0, 0, 0, ' AND u.employee = 1', 0, '', 'maxwidth300', 1);
$morehtmlright .= '<div class="wpeo-button button-primary button-square-30 select-dataset-dashboard-info" style="color: white !important;"><i class="button-icon fas fa-redo"></i></div>';

print load_fiche_titre($title . ' ' . $modDoliSIRH->version, $morehtmlright, 'dolisirh_red.png@dolisirh');

if ($conf->global->DOLISIRH_HR_PROJECT_SET == 0) : ?>
    <div class="wpeo-notice notice-info">
        <div class="notice-content">
            <div class="notice-title"><strong><?php echo $langs->trans('SetupDefaultDataNotCreated'); ?></strong></div>
            <div class="notice-subtitle"><strong><?php echo $langs->trans('HowToSetupDefaultData') . '  ' ?><a href="admin/setup.php"><?php echo $langs->trans('ConfigDefaultData'); ?></a></strong></div>
        </div>
    </div>
<?php endif;

require_once __DIR__ . '/core/tpl/dolisirh_dashboard.tpl.php';

// End of page
llxFooter();
$db->close();