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
 * \file    admin/product.php
 * \ingroup dolisirh
 * \brief   DoliSIRH product config page
 */

// Load DoliSIRH environment
if (file_exists('../dolisirh.main.inc.php')) {
    require_once __DIR__ . '/../dolisirh.main.inc.php';
} elseif (file_exists('../../dolisirh.main.inc.php')) {
    require_once __DIR__ . '/../../dolisirh.main.inc.php';
} else {
    die('Include of dolisirh main fails');
}

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

// Load DoliSIRH libraries
require_once __DIR__ . '/../lib/dolisirh.lib.php';
require_once __DIR__ . '/../lib/dolisirh_function.lib.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['admin']);

// Initialize view objects
$form = new Form($db);

// Get parameters
$action     = GETPOST('action', 'alpha');
$backToPage = GETPOST('backtopage', 'alpha');

$timesheetProductAndServices = get_timesheet_product_service();

// Security check - Protection if external user
$permissionToRead = $user->rights->dolisirh->adminpage->read;
saturne_check_access($permissionToRead);

/*
 * Actions
 */

if ($action == 'update') {
    foreach ($timesheetProductAndServices as $timesheetProductAndService) {
        $timesheetProductAndServiceID = GETPOST($timesheetProductAndService['name'], 'int');
        if ($timesheetProductAndServiceID > 0) {
            dolibarr_set_const($db, $timesheetProductAndService['code'], $timesheetProductAndServiceID, 'integer', 0, '', $conf->entity);
        }
    }

    setEventMessage('SavedConfig');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/*
 * View
 */

$title    = $langs->trans('ProductOrService');
$help_url = 'FR:Module_DoliSIRH';

saturne_header(0,'', $title, $help_url);

// Subheader
$linkBack = '<a href="' . ($backToPage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans('BackToModuleList') . '</a>';

print load_fiche_titre($title, $linkBack, 'title_setup');

// Configuration header
$head = dolisirh_admin_prepare_head();
print dol_get_fiche_head($head, 'productorservice', $title, -1, 'dolisirh_color@dolisirh');

// Product or Service
print load_fiche_titre($langs->transnoentities('ProductOrService'), '', 'product');

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" name="product_or_service_form">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->transnoentities('Name') . '</td>';
print '<td>' . $langs->transnoentities('ProductOrService') . '</td>';
print '</tr>';

// Timesheet product and services
foreach ($timesheetProductAndServices as $timesheetProductAndService) {
    print '<tr class="oddeven"><td><label for="' . $timesheetProductAndService['name'] . '">' . $langs->transnoentities($timesheetProductAndService['name']) . '</label></td><td>';
    print img_picto('', $timesheetProductAndService['type'] == 0 ? 'product' : 'service', 'class="pictofixedwidth"');
    $timesheetProductAndServiceCode = $timesheetProductAndService['code'];
    $form->select_produits((GETPOSTISSET($timesheetProductAndService['name']) ? GETPOST($timesheetProductAndService['name'], 'int') : $conf->global->$timesheetProductAndServiceCode), $timesheetProductAndService['name'], '', 0, 0, -1, 2, '', 0, [], 0, '1', 0, 'maxwidth500 widthcentpercentminusx');
    print ' <a href="' . DOL_URL_ROOT . '/product/card.php?action=create&type=' . ($timesheetProductAndService['type'] == 0 ? 0 : 1) . '&backtopage=' . urlencode(DOL_URL_ROOT . '/custom/dolisirh/admin/product.php') . '"><span class="fa fa-plus-circle valignmiddle" title="' . $langs->transnoentities('AddProduct') . '"></span></a>';
    print '</td></tr>';
}

print '</table>';
print '<div class="tabsAction"><input type="submit" class="butAction" name="save" value="' . $langs->trans('Save') . '"></div>';
print '</form>';

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
