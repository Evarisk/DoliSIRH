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
 * \file    admin/certificate/certificate.php
 * \ingroup dolisirh
 * \brief   DoliSIRH certificate config page.
 */

// Load DoliSIRH environment
if (file_exists('../dolisirh.main.inc.php')) {
    require_once __DIR__ . '/../dolisirh.main.inc.php';
} elseif (file_exists('../../dolisirh.main.inc.php')) {
    require_once __DIR__ . '/../../dolisirh.main.inc.php';
} else {
    die('Include of dolisirh main fails');
}

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

require_once __DIR__ . '/../lib/dolisirh.lib.php';
require_once __DIR__ . '/../class/certificate.class.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['admin']);

// Get parameters
$action     = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

// Initialize technical objects
$object = new Certificate($db);

// Initialize view objects
$form = new Form($db);

// Security check - Protection if external user
$permissiontoread = $user->rights->dolisirh->adminpage->read;
saturne_check_access($permissiontoread);
/*
 * View
 */

$title    = $langs->trans(ucfirst($object->element));
$help_url = 'FR:Module_DoliSIRH';

saturne_header(0,'', $title, $help_url);

// Subheader
$linkback = '<a href="' . ($backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkback, 'dolisirh_color@dolisirh');

// Configuration header
$head = dolisirh_admin_prepare_head();
print dol_get_fiche_head($head, $object->element, $title, -1, 'dolisirh_color@dolisirh');

print load_fiche_titre($langs->trans('Configs', $langs->trans(ucfirst($object->element) . 'Min')), '', 'object_' . $object->picto);
print '<hr>';

/*
 *  Numbering module
 */

print load_fiche_titre($langs->trans('NumberingModule'), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Name') . '</td>';
print '<td>' . $langs->trans('Description') . '</td>';
print '<td class="nowrap">' . $langs->trans('Example') . '</td>';
print '<td class="center">' . $langs->trans('Status') . '</td>';
print '<td class="center">' . $langs->trans('ShortInfo') . '</td>';
print '</tr>';

clearstatcache();

$dir = dol_buildpath('/custom/dolisirh/core/modules/dolisirh/' . $object->element . '/');
if (is_dir($dir)) {
    $handle = opendir($dir);
    if (is_resource($handle)) {
        while (($file = readdir($handle)) !== false) {
            $filelist[] = $file;
        }
        closedir($handle);
        arsort($filelist);
        if (is_array($filelist) && !empty($filelist)) {
            foreach ($filelist as $file) {
                if (preg_match('/mod_/', $file) && preg_match('/' . $object->element . '/i', $file)) {
                    if (file_exists($dir . '/' . $file)) {
                        $classname = substr($file, 0, dol_strlen($file) - 4);

                        require_once $dir . '/' . $file;
                        $module = new $classname($db);

                        if ($module->isEnabled()) {
                            print '<tr class="oddeven"><td>';
                            print $langs->trans($module->name);
                            print '</td><td>';
                            print $module->info();
                            print '</td>';

                            // Show example of numbering module
                            print '<td class="nowrap">';
                            $tmp = $module->getExample();
                            if (preg_match('/^Error/', $tmp)) {
                                print '<div class="error">' . $langs->trans($tmp) . '</div>';
                            } elseif ($tmp == 'NotConfigured') {
                                print $langs->trans($tmp);
                            } else {
                                print $tmp;
                            }
                            print '</td>';

                            print '<td class="center">';
                            $confType = 'DOLISIRH_' . strtoupper($object->element) . '_ADDON';
                            if ($conf->global->$confType == $file || $conf->global->$confType . '.php' == $file) {
                                print img_picto($langs->trans('Activated'), 'switch_on');
                            } else {
                                print '<a class="reposition" href="' . $_SERVER['PHP_SELF'] . '?action=setmod&value=' . preg_replace('/\.php$/', '', $file) . '&const=' . $module->scandir . '&label=' . urlencode($module->name) . '&token=' . newToken() . '">' . img_picto($langs->trans('Disabled'), 'switch_off') . '</a>';
                            }
                            print '</td>';

                            // Example for listing risks action
                            $htmltooltip = '' . $langs->trans('Version') . ': <b>' . $module->getVersion() . '</b><br>';

//                                require_once __DIR__ . '/../class/dolisirhdocuments/' . $type . 'document.class.php';
//                                $classdocumentname = $type . 'Document';
//                                $object_document = new $classdocumentname($db);
//
//                                $nextval = $module->getNextValue($object_document);
//                                if ("$nextval" != $langs->trans('NotAvailable')) {  // Keep " on nextval
//                                    $htmltooltip .= $langs->trans('NextValue') . ': ';
//                                    if ($nextval) {
//                                        if (preg_match('/^Error/', $nextval) || $nextval == 'NotConfigured')
//                                            $nextval = $langs->trans($nextval);
//                                        $htmltooltip .= $nextval . '<br>';
//                                    } else {
//                                        $htmltooltip .= $langs->trans($module->error) . '<br>';
//                                    }
//                                }

                            print '<td class="center">';
                            print $form->textwithpicto('', $htmltooltip, 1, 0);
                            if ($conf->global->$confType . '.php' == $file) { // If module is the one used, we show existing errors
                                if (!empty($module->error)) {
                                    dol_htmloutput_mesg($module->error, '', 'error', 1);
                                }
                            }
                            print '</td>';
                            print '</tr>';
                        }
                    }
                }
            }
        }
    }
}
print '</table>';

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();