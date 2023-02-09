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
if (file_exists('../../main.inc.php')) {
    require_once '../../main.inc.php';
} else {
    die('Include of main fails');
}

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

$morehtmlright = img_picto($langs->trans('Filter') . ' ' . $langs->trans('User'), 'user', 'class="paddingright pictofixedwidth"') . $form->select_dolusers($userID, 'search_userid', '', null, 0, '', null, 0, 0, 0, ' AND u.employee = 1', 0, '', 'maxwidth300 select-user-dashboard', 1);

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