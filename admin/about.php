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
 * \file    admin/about.php
 * \ingroup dolisirh
 * \brief   About page of module DoliSIRH.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

// Libraries
require_once '../lib/dolisirh.lib.php';
require_once '../core/modules/modDoliSIRH.class.php';

// Global variables definitions
global $db, $langs, $user;

// Translations
$langs->loadLangs(array("errors", "admin", "dolisirh@dolisirh"));

// Initialize technical objects
$modDoliSIRH = new modDoliSIRH($db);

// Access control
$permissiontoread = $user->rights->dolisirh->adminpage->read;
if (empty($conf->dolisirh->enabled)) accessforbidden();
if (!$permissiontoread) accessforbidden();

/*
 * View
 */

$help_url = 'FR:Module_DoliSIRH';
$title    = $langs->trans("DoliSIRHAbout");
$morejs   = array("/dolisirh/js/dolisirh.js");
$morecss  = array("/dolisirh/css/dolisirh.css");

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss);

// Subheader
print load_fiche_titre($title, '', 'dolisirh_red@dolisirh');

// Configuration header
$head = dolisirhAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $title, -1, 'dolisirh_red@dolisirh');

print $modDoliSIRH->getDescLong();

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();