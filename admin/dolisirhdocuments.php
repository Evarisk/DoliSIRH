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
 * \file    admin/dolisirhdocuments.php
 * \ingroup dolisirh
 * \brief   DoliSIRH dolisirhdocuments page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
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

// Global variables definitions
global $conf, $db, $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

require_once '../lib/dolisirh.lib.php';

// Translations
$langs->loadLangs(array("admin", "dolisirh@dolisirh"));


// Parameters
$action     = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');
$value      = GETPOST('value', 'alpha');
$type       = GETPOST('type', 'alpha');
$const 		= GETPOST('const', 'alpha');
$label 		= GETPOST('label', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');	// Used by actions_setmoduleoptions.inc.php

// Access control
if (!$user->admin) accessforbidden();

/*
 * Actions
 */

if ($action == 'deletefile' && $modulepart == 'ecm' && !empty($user->admin)) {
	include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	$keyforuploaddir = GETPOST('keyforuploaddir', 'aZ09');

	$listofdir = explode(',', preg_replace('/[\r\n]+/', ',', trim(getDolGlobalString($keyforuploaddir))));
	foreach ($listofdir as $key => $tmpdir) {
		$tmpdir = preg_replace('/DOL_DATA_ROOT\/*/', '', $tmpdir);	// Clean string if we found a hardcoded DOL_DATA_ROOT
		if (!$tmpdir) {
			unset($listofdir[$key]);
			continue;
		}
		$tmpdir = DOL_DATA_ROOT.'/'.$tmpdir;	// Complete with DOL_DATA_ROOT. Only files into DOL_DATA_ROOT can be reach/set
		if (!is_dir($tmpdir)) {
			if (empty($nomessageinsetmoduleoptions)) {
				setEventMessages($langs->trans("ErrorDirNotFound", $tmpdir), null, 'warnings');
			}
		} else {
			$upload_dir = $tmpdir;
			break;	// So we take the first directory found into setup $conf->global->$keyforuploaddir
		}
	}

	$filetodelete = $tmpdir.'/'.GETPOST('file');
	$result = dol_delete_file($filetodelete);
	if ($result > 0) {
		setEventMessages($langs->trans("FileWasRemoved", GETPOST('file')), null);
		header("Location: " . $_SERVER["PHP_SELF"]);
	}
}

// Activate a model
if ($action == 'set') {
	addDocumentModel($value, $type, $label, $const);
	header("Location: " . $_SERVER["PHP_SELF"]);
} elseif ($action == 'del') {
	delDocumentModel($value, $type);
	header("Location: " . $_SERVER["PHP_SELF"]);
}

// Set default model
if ($action == 'setdoc') {
	$constforval = "DOLISIRH".strtoupper($type)."_DEFAULT_MODEL";
	$label       = '';

	if (dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity)) {
		$conf->global->$constforval = $value;
	}

	// Active model
	$ret = delDocumentModel($value, $type);

	if ($ret > 0) {
		$ret = addDocumentModel($value, $type, $label);
	}
} elseif ($action == 'setmod') {
	$constforval = 'DOLISIRH'.strtoupper($type)."_ADDON";
	dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity);
}

if ($action == 'setModuleOptions') {
	$error = 0;
	include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
	$keyforuploaddir = GETPOST('keyforuploaddir', 'aZ09');

	$listofdir = explode(',', preg_replace('/[\r\n]+/', ',', trim(getDolGlobalString($keyforuploaddir))));
	foreach ($listofdir as $key => $tmpdir) {
		$tmpdir = preg_replace('/DOL_DATA_ROOT\/*/', '', $tmpdir);	// Clean string if we found a hardcoded DOL_DATA_ROOT
		if (!$tmpdir) {
			unset($listofdir[$key]);
			continue;
		}
		$tmpdir = DOL_DATA_ROOT.'/'.$tmpdir;	// Complete with DOL_DATA_ROOT. Only files into DOL_DATA_ROOT can be reach/set
		if (!is_dir($tmpdir)) {
			if (empty($nomessageinsetmoduleoptions)) {
				setEventMessages($langs->trans("ErrorDirNotFound", $tmpdir), null, 'warnings');
			}
		} else {
			$upload_dir = $tmpdir;
			break;	// So we take the first directory found into setup $conf->global->$keyforuploaddir
		}
	}

	if (!empty($_FILES)) {
		if (is_array($_FILES['userfile']['tmp_name'])) {
			$userfiles = $_FILES['userfile']['tmp_name'];
		} else {
			$userfiles = array($_FILES['userfile']['tmp_name']);
		}

		foreach ($userfiles as $key => $userfile) {
			if (empty($_FILES['userfile']['tmp_name'][$key])) {
				$error++;
				if ($_FILES['userfile']['error'][$key] == 1 || $_FILES['userfile']['error'][$key] == 2) {
					setEventMessages($langs->trans('ErrorFileSizeTooLarge'), null, 'errors');
				} else {
					setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("File")), null, 'errors');
				}
			}
			if (preg_match('/__.*__/', $_FILES['userfile']['name'][$key])) {
				$error++;
				setEventMessages($langs->trans('ErrorWrongFileName'), null, 'errors');
			}
		}

		if (!$error) {
			$allowoverwrite = (GETPOST('overwritefile', 'int') ? 1 : 0);
			if (!empty($tmpdir)) {
				$result = dol_add_file_process($tmpdir, $allowoverwrite, 1, 'userfile', GETPOST('savingdocmask', 'alpha'));
			}
		}
	}

}

/*
 * View
 */

// Initialize objects
$form = new Form($db);

$help_url = 'FR:Module_DoliSIRH';
$title    = $langs->trans("YourDocuments");
$morejs   = array("/dolisirh/js/dolisirh.js");
$morecss  = array("/dolisirh/css/dolisirh.css");

llxHeader('', $title, $help_url, '', '', '', $morejs, $morecss);

$types = array(
	'TimeSheet'   => 'timesheetdocument',
	'Certificate' => 'certificatedocument',
);

$pictos = array(
	'TimeSheet' => '<i class="fas fa-calendar-check"></i> ',
	'Certificate' => '<i class="fas fa-user-graduate"></i> ',
);

// Subheader
$selectorAnchor = '<select onchange="location = this.value;">';
foreach ($types as $type => $documentType) {
	$selectorAnchor .= '<option value="#' . $langs->trans($type) . '">' . $langs->trans($type) . '</option>';
}
$selectorAnchor .= '</select>';

print load_fiche_titre($title, $selectorAnchor, 'dolisirh_red.png@dolisirh');

// Configuration header
$head = dolisirhAdminPrepareHead();
print dol_get_fiche_head($head, 'dolisirhdocuments', $title, -1, 'dolisirh_red@dolisirh');

foreach ($types as $type => $documentType) {
	print load_fiche_titre($pictos[$type] . $langs->trans($type), '', '', 0, $langs->trans($type));
	print '<hr>';

	$trad = 'DoliSIRH' . $type . 'DocumentNumberingModule';
	print load_fiche_titre($langs->trans($trad), '', '');

	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Name").'</td>';
	print '<td>'.$langs->trans("Description").'</td>';
	print '<td class="nowrap">'.$langs->trans("Example").'</td>';
	print '<td class="center">'.$langs->trans("Status").'</td>';
	print '<td class="center">'.$langs->trans("ShortInfo").'</td>';
	print '</tr>';

	clearstatcache();

	$dir = dol_buildpath("/custom/dolisirh/core/modules/dolisirh/dolisirhdocuments/".$documentType."/");
	if (is_dir($dir)) {
		$handle = opendir($dir);
		if (is_resource($handle)) {
			while (($file = readdir($handle)) !== false ) {
				if (!is_dir($dir.$file) || (substr($file, 0, 1) <> '.' && substr($file, 0, 3) <> 'CVS')) {
					$filebis = $file;

					$classname = preg_replace('/\.php$/', '', $file);
					$classname = preg_replace('/-.*$/', '', $classname);

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
							}
							else {
								print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=setmod&value='.preg_replace('/\.php$/', '', $file).'&const='.$module->scandir.'&label='.urlencode($module->name).'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
							}
							print '</td>';

							// Example for listing risks action
							$htmltooltip = '' . $langs->trans("Version") . ': <b>' . $module->getVersion() . '</b><br>';

							require_once __DIR__ . '/../class/dolisirhdocuments/'.$type.'document.class.php';
							$classdocumentname = $type.'Document';
							$object_document   = new $classdocumentname($db);

							$nextval = $module->getNextValue($object_document);
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

	$trad = "DoliSIRH" . $type . "DocumentTemplateDocument";
	print load_fiche_titre($langs->trans($trad), '', '');

	// Select document models
	$def = array();
	$sql = "SELECT nom";
	$sql .= " FROM ".MAIN_DB_PREFIX."document_model";
	$sql .= " WHERE type = '".$documentType."'";
	$sql .= " AND entity = ".$conf->entity;
	$resql = $db->query($sql);
	if ($resql) {
		$i = 0;
		$num_rows = $db->num_rows($resql);
		while ($i < $num_rows)
		{
			$array = $db->fetch_array($resql);
			array_push($def, $array[0]);
			$i++;
		}
	}
	else {
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

	$dir = dol_buildpath("/custom/dolisirh/core/modules/dolisirh/dolisirhdocuments/".$documentType."/");
	if (is_dir($dir)) {
		$handle = opendir($dir);
		if (is_resource($handle)) {
			while (($file = readdir($handle)) !== false) {
				$filelist[] = $file;
			}
			closedir($handle);
			arsort($filelist);

			foreach ($filelist as $file) {
				if (preg_match('/\.modules\.php$/i', $file) && preg_match('/^(pdf_|doc_)/', $file) && preg_match('/' . $documentType . '/i', $file) && preg_match('/odt/i', $file)) {
					if (file_exists($dir.'/'.$file)) {
						$name = substr($file, 4, dol_strlen($file) - 16);
						$classname = substr($file, 0, dol_strlen($file) - 12);

						require_once $dir.'/'.$file;
						$module = new $classname($db);

						$modulequalified = 1;
						if ($module->version == 'development' && $conf->global->MAIN_FEATURES_LEVEL < 2) $modulequalified = 0;
						if ($module->version == 'experimental' && $conf->global->MAIN_FEATURES_LEVEL < 1) $modulequalified = 0;

						if ($modulequalified) {
							print '<tr class="oddeven"><td>';
							print (empty($module->name) ? $name : $module->name);
							print "</td><td>";
							if (method_exists($module, 'info')) print $module->info($langs);
							else print $module->description;
							print '</td>';

							// Active
							print '<td class="center">';
							if (in_array($name, $def)) {
								print '<a href="'.$_SERVER["PHP_SELF"].'?action=del&amp;value='.$name.'&amp;const='.$module->scandir.'&amp;label='.urlencode($module->name).'&type='.preg_split('/_/',$name)[0].'">';
								print img_picto($langs->trans("Enabled"), 'switch_on');
								print '</a>';
							}
							else {
								print '<a href="'.$_SERVER["PHP_SELF"].'?action=set&amp;value='.$name.'&amp;const='.$module->scandir.'&amp;label='.urlencode($module->name).'&type='.preg_split('/_/',$name)[0].'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
							}
							print "</td>";

							// Default
							print '<td class="center">';
							$defaultModelConf = 'DOLISIRH_' . strtoupper($documentType) . '_DEFAULT_MODEL';
							if ($conf->global->$defaultModelConf == $name) {
								print img_picto($langs->trans("Default"), 'on');
							}
							else {
								print '<a href="'.$_SERVER["PHP_SELF"].'?action=setdoc&amp;value='.$name.'&amp;const='.$module->scandir.'&amp;label='.urlencode($module->name).'">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
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
								print '<a href="'.$_SERVER["PHP_SELF"].'?action=specimen&module='.$name.'">'.img_object($langs->trans("Preview"), 'intervention').'</a>';
							}
							else {
								print img_object($langs->trans("PreviewNotAvailable"), 'generic');
							}
							print '</td>';
							print '</tr>';
						}
					}
				}
			}
		}
	}
	print '</table>';
	print '<hr>';
}
// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
