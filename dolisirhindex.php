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
require_once './core/modules/modDoliSIRH.class.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
$langs->load('dolisirh@dolisirh');

// Initialize technical objects
$dolisirh = new modDoliSIRH($db);

// Security check
if (!$user->rights->dolisirh->lire) accessforbidden();

/*
 * View
 */

$help_url = 'FR:Module_DoliSIRH';
$title    = $langs->trans('DoliSIRHArea');
$morejs   = ['/dolisirh/js/dolisirh.js.php'];
$morecss  = ['/dolisirh/css/dolisirh.css'];

llxHeader('', $title . ' ' . $dolisirh->version, $help_url, '', 0, 0, $morejs, $morecss);

print load_fiche_titre($title . ' ' . $dolisirh->version, '', 'dolisirh_red.png@dolisirh');

if ($conf->global->DOLISIRH_HR_PROJECT_SET == 0) : ?>
    <div class="wpeo-notice notice-info">
        <div class="notice-content">
            <div class="notice-title"><strong><?php echo $langs->trans('SetupDefaultDataNotCreated'); ?></strong></div>
            <div class="notice-subtitle"><strong><?php echo $langs->trans('HowToSetupDefaultData') . '  ' ?><a href="admin/setup.php"><?php echo $langs->trans('ConfigDefaultData'); ?></a></strong></div>
        </div>
    </div>
<?php endif;
// End of page
llxFooter();
$db->close();