<?php
/* Copyright (C) 2022 EOXIA <dev@eoxia.com>
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
 * \file    admin/timesheetdocument/timesheetdocument.php
 * \ingroup dolisirh
 * \brief   DoliSIRH timesheetdocument config page.
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
if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (!$res) die("Include of main fails");

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";

require_once '../lib/dolisirh.lib.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
$langs->loadLangs(array("admin", "dolisirh@dolisirh"));

// Get parameters
$action = GETPOST('action', 'alpha');
$value  = GETPOST('value', 'alpha');
$type   = GETPOST('type', 'alpha');
$const  = GETPOST('const', 'alpha');
$label  = GETPOST('label', 'alpha');

// Initialize objects
// View objects
$form = new Form($db);

// Access control
if (!$user->admin) accessforbidden();

/*
 * Actions
 */

// Activate a model
if ($action == 'set') {
	addDocumentModel($value, $type, $label, $const);
	header("Location: " . $_SERVER["PHP_SELF"]);
} elseif ($action == 'del') {
	delDocumentModel($value, $type);
	header("Location: " . $_SERVER["PHP_SELF"]);
}

// Set default model Or set numering module
if ($action == 'setdoc') {
	$constforval = "DOLISIRH_TIMESHEETDOCUMENT_DEFAULT_MODEL";
	$label       = '';

	if (dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity)) {
		$conf->global->$constforval = $value;
	}

	// On active le model
	$ret = delDocumentModel($value, $type);
	if ($ret > 0) {
		$ret = addDocumentModel($value, $type, $label);
	}
} elseif ($action == 'setmod') {
	$constforval = 'DOLISIRH_'.strtoupper($type)."_ADDON";
	dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity);
}

/*
 * View
 */

$help_url = 'FR:Module_DoliSIRH';
$title    = $langs->trans("TimeSheetDocument");
$morejs   = array("/dolisirh/js/dolisirh.js.php");
$morecss  = array("/dolisirh/css/dolisirh.css");

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss);

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($title, $linkback, 'dolisirh@dolisirh');

// Configuration header
$head = dolisirhAdminPrepareHead();
print dol_get_fiche_head($head, 'timesheetdocument', '', -1, "dolisirh@dolisirh");

$types = array(
	'TimeSheetDocument' => 'timesheetdocument'
);

$pictos = array(
	'TimeSheetDocument' => '<i class="fas fa-file"></i> '
);

foreach ($types as $type => $documentType) {
	print load_fiche_titre($pictos[$type] . $langs->trans($type), '', '');
	print '<hr>';

	$trad = 'DoliSIRH' . $type . 'DocumentNumberingModule';
	print load_fiche_titre($langs->trans($trad), '', '');

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Name").'</td>';
	print '<td>'.$langs->trans("Description").'</td>';
	print '<td>'.$langs->trans("Example").'</td>';
	print '<td class="center">'.$langs->trans("Status").'</td>';
	print '<td class="center">'.$langs->trans("ShortInfo").'</td>';
	print '</tr>';

	clearstatcache();
	$dir = dol_buildpath("/custom/dolisirh/core/modules/dolisirh/".$documentType."/");
	if (is_dir($dir)) {
		$handle = opendir($dir);
		if (is_resource($handle)) {
			while (($file = readdir($handle)) !== false ) {
				if (!is_dir($dir.$file) || (substr($file, 0, 1) <> '.' && substr($file, 0, 3) <> 'CVS')) {
					$filebis = $file;

					$classname = preg_replace('/\.php$/', '', $file);
					$classname = preg_replace('/\-.*$/', '', $classname);

					if (!class_exists($classname) && is_readable($dir.$filebis) && (preg_match('/mod_/', $filebis) || preg_match('/mod_/', $classname)) && substr($filebis, dol_strlen($filebis) - 3, 3) == 'php') {
						// Charging the numbering class
						require_once $dir.$filebis;

						$module = new $classname($db);

						if ($module->isEnabled()) {
							print '<tr class="oddeven"><td>';
							print $langs->trans($module->name);
							print "</td><td>";
							print $module->info();
							print '</td>';

							// Show example of numbering module
							print '<td class="nowrap">';
							$tmp = $module->getExample();
							if (preg_match('/^Error/', $tmp)) print '<div class="error">'.$langs->trans($tmp).'</div>';
							elseif ($tmp == 'NotConfigured') print $langs->trans($tmp);
							else print $tmp;
							print '</td>';

							print '<td class="center">';
							$confType = 'DOLISIRH_' . strtoupper($documentType) . '_ADDON';
							if ($conf->global->$confType == $file || $conf->global->$confType.'.php' == $file) {
								print img_picto($langs->trans("Activated"), 'switch_on');
							} else {
								print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=setmod&value='.preg_replace('/\.php$/', '', $file).'&const='.$module->scandir.'&label='.urlencode($module->name).'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
							}
							print '</td>';

							// Example for timesheet
							$htmltooltip = '' . $langs->trans("Version") . ': <b>' . $module->getVersion() . '</b><br>';
							$nextval = $module->getNextValue($module);
							if ("$nextval" != $langs->trans("NotAvailable")) {  // Keep " on nextval
								$htmltooltip .= $langs->trans("NextValue").': ';
								if ($nextval) {
									if (preg_match('/^Error/', $nextval) || $nextval == 'NotConfigured')
										$nextval = $langs->trans($nextval);
									$htmltooltip .= $nextval.'<br>';
								} else {
									$htmltooltip .= $langs->trans($module->error).'<br>';
								}
							}

							print '<td class="center">';
							print $form->textwithpicto('', $htmltooltip, 1, 0);
							if ($conf->global->$confType.'.php' == $file) { // If module is the one used, we show existing errors
								if (!empty($module->error)) dol_htmloutput_mesg($module->error, '', 'error', 1);
							}
							print '</td>';
							print "</tr>";
						}
					}
				}
			}
			closedir($handle);
		}
	}

	/*
	*  Documents models for TimeSheet
	*/
	$trad = "DoliSIRHTemplateDocument" . $type;
	print load_fiche_titre($langs->trans($trad), '', '');

	// Define models table
	$def = array();
	$sql = "SELECT nom";
	$sql .= " FROM ".MAIN_DB_PREFIX."document_model";
	$sql .= " WHERE type = '".$documentType."'";
	$sql .= " AND entity = ".$conf->entity;
	$resql = $db->query($sql);
	if ($resql) {
		$i = 0;
		$num_rows = $db->num_rows($resql);
		while ($i < $num_rows) {
			$array = $db->fetch_array($resql);
			$def[] = $array[0];
			$i++;
		}
	} else {
		dol_print_error($db);
	}

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Name").'</td>';
	print '<td>'.$langs->trans("Description").'</td>';
	print '<td class="center">'.$langs->trans("Status")."</td>";
	print '<td class="center">'.$langs->trans("Default")."</td>";
	print '<td class="center">'.$langs->trans("ShortInfo").'</td>';
	print '<td class="center">'.$langs->trans("Preview").'</td>';
	print "</tr>";

	clearstatcache();
	$dir = dol_buildpath("/custom/dolisirh/core/modules/dolisirh/".$documentType."/");
	if (is_dir($dir)) {
		$handle = opendir($dir);
		if (is_resource($handle)) {
			while (($file = readdir($handle)) !== false) {
				$filelist[] = $file;
			}
			closedir($handle);
			arsort($filelist);

			foreach ($filelist as $file) {
				if (preg_match('/\.modules\.php$/i', $file) && preg_match('/^(pdf_|doc_)/', $file)) {
					if (file_exists($dir.'/'.$file)) {
						$name = substr($file, 4, dol_strlen($file) - 16);
						$classname = substr($file, 0, dol_strlen($file) - 12);

						require_once $dir.'/'.$file;
						$module = new $classname($db);

						print '<tr class="oddeven"><td>';
						print (empty($module->name) ? $name : $module->name);
						print "</td><td>";
						if (method_exists($module, 'info')) print $module->info($langs);
						else print $module->description;
						print '</td>';

						// Active
						print '<td class="center">';
						if (in_array($name, $def)) {
							print '<a href="'.$_SERVER["PHP_SELF"].'?action=del&value='.$name.'&const='.$module->scandir.'&label='.urlencode($module->name).'&type='. explode('_', $documentType)[0].'">';
							print img_picto($langs->trans("Enabled"), 'switch_on');
							print '</a>';
						} else {
							print '<a href="'.$_SERVER["PHP_SELF"].'?action=set&value='.$name.'&const='.$module->scandir.'&label='.urlencode($module->name).'&type='. explode('_', $documentType)[0].'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
						}
						print "</td>";

						// Default
						print '<td class="center">';
						$defaultModelConf = 'DOLISIRH_' . strtoupper($documentType) . '_DEFAULT_MODEL';
						if ($conf->global->$defaultModelConf == $name) {
							print img_picto($langs->trans("Default"), 'on');
						} else {
							print '<a href="'.$_SERVER["PHP_SELF"].'?action=setdoc&value='.$name.'&const='.$module->scandir.'&label='.urlencode($module->name).'">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
						}
						print '</td>';

						// Info
						$htmltooltip = ''.$langs->trans("Name").': '.$module->name;
						$htmltooltip .= '<br>'.$langs->trans("Type").': '.($module->type ?: $langs->trans("Unknown"));
						$htmltooltip .= '<br>'.$langs->trans("Width").'/'.$langs->trans("Height").': '.$module->page_largeur.'/'.$module->page_hauteur;
						$htmltooltip .= '<br><br><u>'.$langs->trans("FeaturesSupported").':</u>';
						$htmltooltip .= '<br>'.$langs->trans("Logo").': '.yn($module->option_logo, 1, 1);
						print '<td class="center">';
						print $form->textwithpicto('', $htmltooltip, -1, 0);
						print '</td>';

						// Preview
						print '<td class="center">';
						if ($module->type == 'pdf') {
							print '<a href="'.$_SERVER["PHP_SELF"].'?action=specimen&module='.$name.'">'.img_object($langs->trans("Preview"), 'pdf').'</a>';
						} else {
							print img_object($langs->trans("PreviewNotAvailable"), 'generic');
						}
						print '</td>';
						print '</tr>';
					}
				}
			}
		}
	}

	print '</table>';
}

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
