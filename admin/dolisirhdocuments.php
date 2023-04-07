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
 * \file    admin/dolisirhdocuments.php
 * \ingroup dolisirh
 * \brief   DoliSIRH dolisirhdocuments page.
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
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

require_once __DIR__ . '/../lib/dolisirh.lib.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['admin']);

// Initialize view objects
$form = new Form($db);

// Get parameters
$action     = GETPOST('action', 'alpha');
$value      = GETPOST('value', 'alpha');
$type       = GETPOST('type', 'alpha');
$const      = GETPOST('const', 'alpha');
$label      = GETPOST('label', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09'); // Used by actions_setmoduleoptions.inc.php

// Security check - Protection if external user
$permissiontoread = $user->rights->dolisirh->adminpage->read;
saturne_check_access($permissiontoread);

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
				setEventMessages($langs->trans('ErrorDirNotFound', $tmpdir), null, 'warnings');
			}
		} else {
			$upload_dir = $tmpdir;
			break;	// So we take the first directory found into setup $conf->global->$keyforuploaddir
		}
	}

	$filetodelete = $tmpdir.'/'.GETPOST('file');
	$result = dol_delete_file($filetodelete);
	if ($result > 0) {
		setEventMessages($langs->trans('FileWasRemoved', GETPOST('file')), null);
		header('Location: ' . $_SERVER['PHP_SELF']);
	}
}

// Activate a model
if ($action == 'set') {
	addDocumentModel($value, $type, $label, $const);
	header('Location: ' . $_SERVER['PHP_SELF']);
} elseif ($action == 'del') {
	delDocumentModel($value, $type);
	header('Location: ' . $_SERVER['PHP_SELF']);
}

// Set default model
if ($action == 'setdoc') {
	$constforval = 'DOLISIRH' .strtoupper($type). '_DEFAULT_MODEL';
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
	$constforval = 'DOLISIRH'.strtoupper($type). '_ADDON';
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
				setEventMessages($langs->trans('ErrorDirNotFound', $tmpdir), null, 'warnings');
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
					setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('File')), null, 'errors');
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

$title    = $langs->trans('YourDocuments');
$help_url = 'FR:Module_DoliSIRH';

saturne_header(0, '', $title, $help_url);

$types = [
    'TimeSheetDocument' => [
        'documentType' => 'timesheetdocument',
        'picto'        => 'fontawesome_fa-calendar-check_fas_#d35968'
    ],
    'CertificateDocument' => [
        'documentType' => 'certificatedocument',
        'picto'        => 'fontawesome_fa-user-graduate_fas_#d35968'
    ]
];

// Subheader
$selectorAnchor = '<select onchange="location = this.value;">';
foreach ($types as $type => $documentType) {
	$selectorAnchor .= '<option value="#' . $langs->trans($type) . '">' . $langs->trans($type) . '</option>';
}
$selectorAnchor .= '</select>';

print load_fiche_titre($title, $selectorAnchor, 'dolisirh_color.png@dolisirh');

// Configuration header
$head = dolisirh_admin_prepare_head();
print dol_get_fiche_head($head, 'documents', $title, -1, 'dolisirh_color@dolisirh');

print load_fiche_titre($langs->trans('Configs', $langs->trans('DocumentsMin')), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Name') . '</td>';
print '<td>' . $langs->trans('Description') . '</td>';
print '<td class="center">' . $langs->trans('Status') . '</td>';
print '</tr>';

// Automatic PDF generation
print '<tr class="oddeven"><td>';
print  $langs->trans('AutomaticPdfGeneration');
print '</td><td>';
print $langs->trans('AutomaticPdfGenerationDescription');
print '</td>';
print '<td class="center">';
print ajax_constantonoff('DOLISIRH_AUTOMATIC_PDF_GENERATION');
print '</td>';
print '</tr>';

// Manual PDF generation
print '<tr class="oddeven"><td>';
print  $langs->trans('ManualPdfGeneration');
print '</td><td>';
print $langs->trans('ManualPdfGenerationDescription');
print '</td>';
print '<td class="center">';
print ajax_constantonoff('DOLISIRH_MANUAL_PDF_GENERATION');
print '</td>';
print '</tr>';

// Show signature specimen
print '<tr class="oddeven"><td>';
print  $langs->trans('ShowSignatureSpecimen');
print '</td><td>';
print $langs->trans('ShowSignatureSpecimenDescription');
print '</td>';
print '<td class="center">';
print ajax_constantonoff('DOLISIRH_SHOW_SIGNATURE_SPECIMEN');
print '</td>';
print '</tr>';

print '</table>';

foreach ($types as $type => $documentData) {
    $filelist = [];
    if (preg_match('/_/', $documentData['documentType'])) {
        $documentType       = preg_split('/_/', $documentData['documentType']);
        $documentParentType = $documentType[0];
        $documentType       = $documentType[1];
    } else {
        $documentParentType = $documentData['documentType'];
    }

    print load_fiche_titre($langs->trans($type), '', $documentData['picto'], 0, $langs->trans($type));
    print '<hr>';

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

	$dir = dol_buildpath('/custom/dolisirh/core/modules/dolisirh/dolisirhdocuments/' . $documentParentType . '/');
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
                    if (preg_match('/mod_/', $file) && preg_match('/' . $documentParentType . '/i', $file)) {
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
                                $confType = 'DOLISIRH_' . strtoupper($documentParentType) . '_ADDON';
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

    print load_fiche_titre($langs->trans('DocumentTemplate'), '', '');

	// Select document models
	$def = [];
	$sql = 'SELECT nom';
	$sql .= ' FROM ' . MAIN_DB_PREFIX . 'document_model';
	$sql .= " WHERE type = '" . (!empty($documentParentType) ? $documentParentType : $documentType) . "'";
	$sql .= ' AND entity = ' . $conf->entity;
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
	print '<td>' . $langs->trans('Name') . '</td>';
	print '<td>' . $langs->trans('Description') . '</td>';
	print '<td class="center">' . $langs->trans('Status') . '</td>';
	print '<td class="center">' . $langs->trans('Default') . '</td>';
	print '<td class="center">' . $langs->trans('ShortInfo') . '</td>';
	print '<td class="center">' . $langs->trans('Preview') . '</td>';
	print '</tr>';

    if (is_array($filelist) && !empty($filelist)) {
		foreach ($filelist as $file) {
			if (preg_match('/\.modules\.php$/i', $file) && preg_match('/^(pdf_|doc_)/', $file) && preg_match('/' . $documentParentType . '/i', $file) && preg_match('/odt/i', $file)) {
				if (file_exists($dir.'/'.$file)) {
					$name      = substr($file, 4, dol_strlen($file) - 16);
					$classname = substr($file, 0, dol_strlen($file) - 12);

					require_once $dir  . '/' . $file;
					$module = new $classname($db);

					$modulequalified = 1;
                    if ($module->version == 'development' && $conf->global->MAIN_FEATURES_LEVEL < 2) {
                        $modulequalified = 0;
                    }
                    if ($module->version == 'experimental' && $conf->global->MAIN_FEATURES_LEVEL < 1) {
                        $modulequalified = 0;
                    }

					if ($modulequalified) {
                        print '<tr class="oddeven"><td>';
                        print (empty($module->name) ? $name : $module->name);
                        print '</td><td>';
                        if (method_exists($module, 'info')) {
                            print $module->info($langs);
                        }else {
                            print $module->description;
                        }
                        print '</td>';

                        // Active
                        print '<td class="center">';
                        if (in_array($name, $def)) {
                            print '<a href="' . $_SERVER['PHP_SELF'] . '?action=del&value=' . $name . '&const=' . $module->scandir . '&label=' . urlencode($module->name) . '&type=' . explode('_', $name)[0] . '&token=' . newToken() . '">';
                            print img_picto($langs->trans('Enabled'), 'switch_on');
                        } else {
                            print '<a href="' . $_SERVER['PHP_SELF'] . '?action=set&value=' . $name . '&const=' . $module->scandir . '&label=' . urlencode($module->name) . '&type=' . explode('_', $name)[0] . '&token=' . newToken() . '">';
                            img_picto($langs->trans('Disabled'), 'switch_off');
                        }
                        print '</a>';
                        print '</td>';

                        // Default
                        print '<td class="center">';
                        $defaultModelConf = 'DOLISIRH_' . strtoupper($documentParentType) . '_DEFAULT_MODEL';
                        if ($conf->global->$defaultModelConf == $name) {
                            print img_picto($langs->trans('Default'), 'on');
                        } else {
                            print '<a href="' . $_SERVER['PHP_SELF'] . '?action=setdoc&value=' . $name .'&const=' . $module->scandir . '&label=' . urlencode($module->name) . '&token=' . newToken() . '">' . img_picto($langs->trans('Disabled'), 'off') . '</a>';
                        }
                        print '</td>';

                        // Info
                        $htmltooltip = ''.$langs->trans('Name') . ': ' . $module->name;
                        $htmltooltip .= '<br>'.$langs->trans('Type') . ': ' . ($module->type ?: $langs->trans('Unknown'));
                        $htmltooltip .= '<br>'.$langs->trans('Width') . '/' . $langs->trans('Height') . ': ' . $module->page_largeur . '/' . $module->page_hauteur;
                        $htmltooltip .= '<br><br><u>' . $langs->trans('FeaturesSupported') . ':</u>';
                        $htmltooltip .= '<br>' . $langs->trans('Logo') . ': ' . yn($module->option_logo, 1, 1);
                        print '<td class="center">';
                        print $form->textwithpicto('', $htmltooltip, -1, 0);
                        print '</td>';

                        // Preview
                        print '<td class="center">';
                        if ($module->type == 'pdf') {
                            print '<a href="' . $_SERVER['PHP_SELF'] . '?action=specimen&module=' . $name . '">' . img_object($langs->trans('Preview'), 'intervention') . '</a>';
                        } else {
                            print img_object($langs->trans('PreviewNotAvailable'), 'generic');
                        }
                        print '</td>';
                        print '</tr>';
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