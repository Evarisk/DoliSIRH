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
 * or see https://www.gnu.org/
 */

/**
 * \file    htdocs/core/modules/dolisirh/dolisirhdocuments/certificatedocument/doc_certificatedocument_odt.modules.php
 * \ingroup dolisirh
 * \brief   File of class to build ODT certificate document.
 */

// Load Dolibarr Libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';

// Load DoliSIRH Libraries.
require_once __DIR__ . '/modules_certificatedocument.php';
require_once __DIR__ . '/mod_certificatedocument_standard.php';

/**
 * Class to build documents using ODF templates generator.
 */
class doc_certificatedocument_odt extends ModeleODTCertificateDocument
{
    /**
     * @var array Minimum version of PHP required by module.
     * e.g.: PHP ≥ 5.5 = array(5, 5)
     */
    public array $phpmin = [7, 4];

    /**
     * @var string Dolibarr version of the loaded document.
     */
    public string $version = 'dolibarr';

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        global $langs;

        // Load translation files required by the page
        $langs->loadLangs(['main', 'companies']);

        $this->db          = $db;
        $this->name        = $langs->trans('ODTDefaultTemplateName');
        $this->description = $langs->trans('DocumentModelOdt');
        $this->scandir     = 'DOLISIRH_CERTIFICATEDOCUMENT_ADDON_PDF_ODT_PATH'; // Name of constant that is used to save list of directories to scan.

        // Page size for A4 format.
        $this->type         = 'odt';
        $this->page_largeur = 0;
        $this->page_hauteur = 0;
        $this->format       = [$this->page_largeur, $this->page_hauteur];
        $this->marge_gauche = 0;
        $this->marge_droite = 0;
        $this->marge_haute  = 0;
        $this->marge_basse  = 0;

        $this->option_logo      = 1; // Display logo.
        $this->option_multilang = 1; // Available in several languages.
    }

    /**
     * Return description of a module
     *
     * @param  Translate $langs Lang object to use for output.
     * @return string           Description.
     */
    public function info(Translate $langs): string
    {
        global $conf, $langs;

        // Load translation files required by the page
        $langs->loadLangs(['errors', 'companies']);

        $texte = $this->description . ' . <br>';
        $texte .= '<form action="' . $_SERVER['PHP_SELF'] . '" method="POST">';
        $texte .= '<input type="hidden" name="token" value="' . newToken() . '">';
        $texte .= '<input type="hidden" name="action" value="setModuleOptions">';
        $texte .= '<input type="hidden" name="param1" value="DOLISIRH_CERTIFICATEDOCUMENT_ADDON_ODT_PATH">';
        $texte .= '<table class="nobordernopadding centpercent">';

        // List of directories area
        $texte .= '<tr><td>';
        $texttitle   = $langs->trans('ListOfDirectories');
        $listofdir   = explode(',', preg_replace('/[\r\n]+/', ',', trim($conf->global->DOLISIRH_CERTIFICATEDOCUMENT_ADDON_ODT_PATH)));
        $listoffiles = [];
        foreach ($listofdir as $key=>$tmpdir) {
            $tmpdir = trim($tmpdir);
            $tmpdir = preg_replace('/DOL_DATA_ROOT/', DOL_DATA_ROOT, $tmpdir);
            $tmpdir = preg_replace('/DOL_DOCUMENT_ROOT/', DOL_DOCUMENT_ROOT, $tmpdir);
            if (!$tmpdir) {
                unset($listofdir[$key]);
                continue;
            }
            if (!is_dir($tmpdir)) {
                $texttitle .= img_warning($langs->trans('ErrorDirNotFound', $tmpdir), 0);
            } else {
                $tmpfiles = dol_dir_list($tmpdir, 'files', 0, '\.(ods|odt)');
                if (count($tmpfiles)) {
                    $listoffiles = array_merge($listoffiles, $tmpfiles);
                }
            }
        }

        // Scan directories
        $nbFiles = count($listoffiles);
        if (!empty($conf->global->DOLISIRH_CERTIFICATEDOCUMENT_ADDON_ODT_PATH)) {
            $texte .= $langs->trans('NumberOfModelFilesFound') . ': <b>';
            $texte .= count($listoffiles);
            $texte .= '</b>';
        }

        if ($nbFiles) {
            $texte .= '<div id="div_' . get_class($this) . '" class="hidden">';
            foreach ($listoffiles as $file) {
                $texte .= $file['name'] . '<br>';
            }
            $texte .= '</div>';
        }

        $texte .= '</td>';
        $texte .= '</table>';
        $texte .= '</form>';

        return $texte;
    }

    /**
     * Function to build a document on disk using the generic odt module.
     *
     * @param CertificateDocument $objectDocument  Object source to build document.
     * @param Translate           $outputlangs     Lang output object.
     * @param string              $srctemplatepath Full path of source filename for generator using a template file.
     * @param int                 $hidedetails     Do not show line details.
     * @param int                 $hidedesc        Do not show desc.
     * @param int                 $hideref         Do not show ref.
     * @param array               $moreparam       More param (Object/user/etc).
     * @return        int                          1 if OK, <=0 if KO.
     * @throws Exception
     */
    public function write_file(CertificateDocument $objectDocument, Translate $outputlangs, string $srctemplatepath, int $hidedetails = 0, int $hidedesc = 0, int $hideref = 0, array $moreparam)
    {
        global $action, $conf, $hookmanager, $langs, $mysoc;

        $object = $moreparam['object'];

        if (empty($srctemplatepath)) {
            dol_syslog('doc_certificatedocument_odt::write_file parameter srctemplatepath empty', LOG_WARNING);
            return -1;
        }

        // Add odtgeneration hook.
        if (!is_object($hookmanager)) {
            include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
            $hookmanager = new HookManager($this->db);
        }
        $hookmanager->initHooks(['odtgeneration']);

        if (!is_object($outputlangs)) {
            $outputlangs = $langs;
        }

        $outputlangs->charset_output = 'UTF-8';
        $outputlangs->loadLangs(['main', 'dict', 'companies', 'dolisirh@dolisirh']);
        
        if ($conf->dolisirh->dir_output) {
            $refModName          = new $conf->global->DOLISIRH_CERTIFICATEDOCUMENT_ADDON($this->db);
            $objectDocumentRef   = $refModName->getNextValue($objectDocument);
            $objectDocument->ref = $objectDocumentRef;
            $objectDocumentID    = $objectDocument->create($moreparam['user'], true, $object);

            $objectDocument->fetch($objectDocumentID);

            $objectDocumentRef = dol_sanitizeFileName($objectDocument->ref);

            $dir = $conf->dolisirh->multidir_output[$object->entity ?? 1] . '/' . $object->element . 'document/' . $object->ref;
            if ($moreparam['specimen'] == 1 && $moreparam['zone'] == 'public') {
                $dir .= '/specimen';
            }

            if (!file_exists($dir)) {
                if (dol_mkdir($dir) < 0) {
                    $this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
                    return -1;
                }
            }

            if (file_exists($dir)) {
                $newFile     = basename($srctemplatepath);
                $newFileTmp  = preg_replace('/\.od(t|s)/i', '', $newFile);
                $newFileTmp  = preg_replace('/template_/i', '', $newFileTmp);
                $societyName = preg_replace('/\./', '_', $conf->global->MAIN_INFO_SOCIETE_NOM);

                $date       = dol_print_date(dol_now(), 'dayxcard');
                $newFileTmp = $date . '_' . $object->ref . '_' . $objectDocumentRef .'_' . $langs->transnoentities($newFileTmp) . '_' . $societyName;
                if ($moreparam['specimen'] == 1) {
                    $newFileTmp .= '_specimen';
                }
                $newFileTmp = str_replace(' ', '_', $newFileTmp);

                // Get extension (ods or odt).
                $newFileFormat = substr($newFile, strrpos($newFile, '.') + 1);
                $filename      = $newFileTmp . '.' . $newFileFormat;
                $file          = $dir . '/' . $filename;

                $objectDocument->last_main_doc = $filename;

                $sql  = 'UPDATE ' . MAIN_DB_PREFIX . 'dolisirh_dolisirhdocuments';
                $sql .= ' SET last_main_doc =' . (!empty($objectDocument->last_main_doc) ? "'" . $this->db->escape($objectDocument->last_main_doc) . "'" : 'null');
                $sql .= ' WHERE rowid = ' . $objectDocument->id;

                dol_syslog('dolisirh_dolisirhdocuments::Insert last main doc', LOG_DEBUG);
                $this->db->query($sql);

                dol_mkdir($conf->dolisirh->dir_temp);

                if (!is_writable($conf->dolisirh->dir_temp)) {
                    $this->error = 'Failed to write in temp directory ' . $conf->dolisirh->dir_temp;
                    dol_syslog('Error in write_file: ' . $this->error, LOG_ERR);
                    return -1;
                }

                // Make substitution.
                $substitutionarray = [];
                complete_substitutions_array($substitutionarray, $langs, $object);
                // Call the ODTSubstitution hook.
                $parameters = ['file' => $file, 'object' => $object, 'outputlangs' => $outputlangs, 'substitutionarray' => &$substitutionarray];
                $reshook = $hookmanager->executeHooks('ODTSubstitution', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks.

                // Open and load template.
                require_once ODTPHP_PATH . 'odf.php';
                try {
                    $odfHandler = new odf(
                        $srctemplatepath,
                        [
                            'PATH_TO_TMP'     => $conf->dolisirh->dir_temp,
                            'ZIP_PROXY'       => 'PclZipProxy', // PhpZipProxy or PclZipProxy. Got "bad compression method" error when using PhpZipProxy.
                            'DELIMITER_LEFT'  => '{',
                            'DELIMITER_RIGHT' => '}'
                        ]
                    );
                } catch (Exception $e) {
                    $this->error = $e->getMessage();
                    dol_syslog($e->getMessage());
                    return -1;
                }

                //Define substitution array
                $substitutionarray = getCommonSubstitutionArray($outputlangs, 0, null, $object);
                $array_soc = $this->get_substitutionarray_mysoc($mysoc, $outputlangs);
                $array_soc['mycompany_logo'] = preg_replace('/_small/', '_mini', $array_soc['mycompany_logo']);

                $tmparray = array_merge($substitutionarray, $array_soc);
                complete_substitutions_array($tmparray, $outputlangs, $object);
                
				foreach ($tmparray as $key => $value) {
					try {
						if (preg_match('/logo$/', $key)) {
							// Image
                            if (file_exists($value)) {
                                $odfHandler->setImage($key, $value);
                            } else {
                                $odfHandler->setVars($key, $langs->transnoentities('ErrorFileNotFound'), true, 'UTF-8');
                            }
                        } elseif (empty($value)) { // Text.
                            $odfHandler->setVars($key, $langs->trans('NoData'), true, 'UTF-8');
                        } else {
                            $odfHandler->setVars($key, html_entity_decode($value, ENT_QUOTES | ENT_HTML5), true, 'UTF-8');
                        }
					} catch (OdfException $e) {
						dol_syslog($e->getMessage());
					}
				}

                // Replace labels translated.
                $tmparray = $outputlangs->get_translations_for_substitutions();
                foreach ($tmparray as $key => $value) {
                    try {
                        $odfHandler->setVars($key, $value, true, 'UTF-8');
                    } catch (OdfException $e) {
                        dol_syslog($e->getMessage());
                    }
                }

                // Call the beforeODTSave hook.
                $parameters = ['odfHandler' => &$odfHandler, 'file' => $file, 'object' => $object, 'outputlangs' => $outputlangs, 'substitutionarray' => &$tmparray];
                $hookmanager->executeHooks('beforeODTSave', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks.


                $fileInfos = pathinfo($filename);
                $pdfName   = $fileInfos['filename'] . '.pdf';

                // Write new file.
                if (!empty($conf->global->MAIN_ODT_AS_PDF) && $conf->global->DOLISIRH_AUTOMATIC_PDF_GENERATION > 0) {
                    try {
                        $odfHandler->exportAsAttachedPDF($file);

                        global $moduleNameLowerCase;
                        $documentUrl = DOL_URL_ROOT . '/document.php';
                        setEventMessages($langs->trans('FileGenerated') . ' - ' . '<a href=' . $documentUrl . '?modulepart=' . $moduleNameLowerCase . '&file=' . urlencode('certificatedocument/' . $object->ref . '/' . $pdfName) . '&entity='. $conf->entity .'"' . '>' . $pdfName  . '</a>', []);
                    } catch (Exception $e) {
                        $this->error = $e->getMessage();
                        dol_syslog($e->getMessage());
                        setEventMessages($langs->transnoentities('FileCouldNotBeGeneratedInPDF') . '<br>' . $langs->transnoentities('CheckDocumentationToEnablePDFGeneration'), [], 'errors');
                    }
                }  else {
                    try {
                        $odfHandler->saveToDisk($file);
                    } catch (Exception $e) {
                        $this->error = $e->getMessage();
                        dol_syslog($e->getMessage());
                        return -1;
                    }
                }

                $parameters = ['odfHandler' => &$odfHandler, 'file' => $file, 'object' => $object, 'outputlangs' => $outputlangs, 'substitutionarray' => &$tmparray];
                $hookmanager->executeHooks('afterODTCreation', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks.

                if (!empty($conf->global->MAIN_UMASK)) {
                    @chmod($file, octdec($conf->global->MAIN_UMASK));
                }

                $odfHandler = null; // Destroy object.

                $this->result = ['fullpath' => $file];

                return 1; // Success.
            } else {
                $this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
                return -1;
            }
        }

        return -1;
    }
}