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
 * or see https://www.gnu.org/
 */

/**
 *	\file       core/modules/dolisirh/timesheetdocument/doc_timesheetdocument_odt.modules.php
 *	\ingroup    dolisirh
 *	\brief      File of class to build ODT documents for timesheet
 */

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/doc.lib.php';

require_once __DIR__ . '/modules_timesheetdocument.php';
require_once __DIR__ . '/mod_timesheetdocument_standard.php';
require_once DOL_DOCUMENT_ROOT.'/custom/dolisirh/class/workinghours.class.php';

/**
 *	Class to build documents using ODF templates generator
 */
class doc_timesheetdocument_odt extends ModeleODTTimeSheetDocument
{
	/**
	 * Issuer
	 * @var Societe
	 */
	public $emetteur;

	/**
	 * @var array Minimum version of PHP required by module.
	 * e.g.: PHP ≥ 5.6 = array(5, 6)
	 */
	public $phpmin = array(5, 6);

	/**
	 * @var string Dolibarr version of the loaded document
	 */
	public $version = 'dolibarr';

	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		// Load translation files required by the page
		$langs->loadLangs(array("main", "companies"));

		$this->db = $db;
		$this->name = $langs->trans('DoliSIRHTimeSheetDocumentTemplate');
		$this->description = $langs->trans("DocumentModelOdt");
		$this->scandir = 'DOLISIRH_TIMESHEETDOCUMENT_ADDON_ODT_PATH'; // Name of constant that is used to save list of directories to scan

		// Page size for A4 format
		$this->type = 'odt';
		$this->page_largeur = 0;
		$this->page_hauteur = 0;
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = 0;
		$this->marge_droite = 0;
		$this->marge_haute = 0;
		$this->marge_basse = 0;

		$this->option_logo = 1; // Display logo
		$this->option_tva = 0; // Manage the vat option FACTURE_TVAOPTION
		$this->option_modereg = 0; // Display payment mode
		$this->option_condreg = 0; // Display payment terms
		$this->option_codeproduitservice = 0; // Display product-service code
		$this->option_multilang = 1; // Available in several languages
		$this->option_escompte = 0; // Displays if there has been a discount
		$this->option_credit_note = 0; // Support credit notes
		$this->option_freetext = 1; // Support add of a personalised text
		$this->option_draft_watermark = 0; // Support add of a watermark on drafts

		// Get source company
		$this->emetteur = $mysoc;
		if (!$this->emetteur->country_code) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2); // By default if not defined
		}
	}

	/**
	 *	Return description of a module
	 *
	 *	@param	Translate	$langs      Lang object to use for output
	 *	@return string       			Description
	 */
	public function info($langs)
	{
		global $conf, $langs;

		// Load translation files required by the page
		$langs->loadLangs(array("errors", "companies"));

		$texte = $this->description.".<br>";
		$texte .= '<table class="nobordernopadding centpercent">';

		// List of directories area
		$texte .= '<tr><td>';
		$texttitle = $langs->trans("ListOfDirectories");
		$listofdir = explode(',', preg_replace('/[\r\n]+/', ',', trim($conf->global->DOLISIRH_TIMESHEETDOCUMENT_ADDON_ODT_PATH)));
		$listoffiles = array();
		foreach ($listofdir as $key => $tmpdir) {
			$tmpdir = trim($tmpdir);
			$tmpdir = preg_replace('/DOL_DATA_ROOT/', DOL_DATA_ROOT, $tmpdir);
			$tmpdir = preg_replace('/DOL_DOCUMENT_ROOT/', DOL_DOCUMENT_ROOT, $tmpdir);
			if (!$tmpdir) {
				unset($listofdir[$key]);
				continue;
			}
			if (!is_dir($tmpdir)) {
				$texttitle .= img_warning($langs->trans("ErrorDirNotFound", $tmpdir), 0);
			} else {
				$tmpfiles = dol_dir_list($tmpdir, 'files', 0, '\.(ods|odt)');
				if (count($tmpfiles)) {
					$listoffiles = array_merge($listoffiles, $tmpfiles);
				}
			}
		}

		// Scan directories
		$nbofiles = count($listoffiles);
		if (!empty($conf->global->DOLISIRH_TIMESHEETDOCUMENT_ADDON_ODT_PATH)) {
			$texte .= $langs->trans("DoliSIRHNumberOfModelFilesFound").': <b>';
			$texte .= count($listoffiles);
			$texte .= '</b>';
		}

		if ($nbofiles) {
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
     *  Function to build a document on disk using the generic odt module.
     *
     * @param  TimeSheetDocument $objectDocument    Object source to build document
     * @param  Translate         $outputlangs       Lang output object
     * @param  string            $srctemplatepath   Full path of source filename for generator using a template file
     * @param  int               $hidedetails       Do not show line details
     * @param  int               $hidedesc          Do not show desc
     * @param  int               $hideref           Do not show ref
     * @param  TimeSheet         $object            TimeSheet Object
     * @return int                                  1 if OK, <=0 if KO
     * @throws Exception
     */
	public function write_file($objectDocument, $outputlangs, $srctemplatepath, $hidedetails = 0, $hidedesc = 0, $hideref = 0, $object)
	{
		// phpcs:enable
		global $action, $conf, $hookmanager, $langs, $mysoc, $user;

		if (empty($srctemplatepath)) {
			dol_syslog("doc_generic_odt::write_file parameter srctemplatepath empty", LOG_WARNING);
			return -1;
		}

		// Add odtgeneration hook
		if (!is_object($hookmanager)) {
			include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($this->db);
		}

		$hookmanager->initHooks(array('odtgeneration'));

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}

		$outputlangs->charset_output = 'UTF-8';
		$outputlangs->loadLangs(array("main", "dict", "companies", "bills"));

        $refModName          = new $conf->global->DOLISIRH_TIMESHEETDOCUMENT_ADDON($this->db);
        $objectDocumentRef   = $refModName->getNextValue($objectDocument);
        $objectDocument->ref = $objectDocumentRef;
        $objectDocumentID    = $objectDocument->create($user, true, $object);

        $objectDocument->fetch($objectDocumentID);

		$objectref = dol_sanitizeFileName($objectDocument->ref);
		$dir = $conf->dolisirh->multidir_output[isset($object->entity) ? $object->entity : 1] . '/timesheetdocument/' . $object->ref;

        if (!file_exists($dir)) {
            if (dol_mkdir($dir) < 0) {
                $this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
                return -1;
            }
        }

        if (file_exists($dir)) {
            $newfile = basename($srctemplatepath);
            $newfiletmp = preg_replace('/\.od(t|s)/i', '', $newfile);
            $newfiletmp = preg_replace('/template_/i', '', $newfiletmp);

            $date       = dol_print_date(dol_now(), 'dayxcard');
            $newfiletmp = $objectref . '_' . $date . '_' . $newfiletmp . '_' . $conf->global->MAIN_INFO_SOCIETE_NOM;

            $objectDocument->last_main_doc = $newfiletmp;

            $sql  = "UPDATE " . MAIN_DB_PREFIX . "dolisirh_dolisirhdocuments";
            $sql .= " SET last_main_doc =" . (!empty($newfiletmp) ? "'" . $this->db->escape($newfiletmp) . "'" : 'null');
            $sql .= " WHERE rowid = " . $objectDocument->id;

            dol_syslog("admin.lib::Insert last main doc", LOG_DEBUG);
            $this->db->query($sql);

            // Get extension (ods or odt)
            $newfileformat = substr($newfile, strrpos($newfile, '.') + 1);

            $filename = $newfiletmp . '.' . $newfileformat;
            $file     = $dir . '/' . $filename;

            dol_mkdir($conf->dolisirh->dir_temp);

            if (!is_writable($conf->dolisirh->dir_temp)) {
                $this->error = "Failed to write in temp directory ".$conf->dolisirh->dir_temp;
                dol_syslog('Error in write_file: '.$this->error, LOG_ERR);
                return -1;
            }

            // Open and load template
            require_once ODTPHP_PATH.'odf.php';
            try {
                $odfHandler = new odf(
                    $srctemplatepath,
                    array(
                    'PATH_TO_TMP'	  => $conf->dolisirh->dir_temp,
                    'ZIP_PROXY'		  => 'PclZipProxy', // PhpZipProxy or PclZipProxy. Got "bad compression method" error when using PhpZipProxy.
                    'DELIMITER_LEFT'  => '{',
                    'DELIMITER_RIGHT' => '}'
                    )
                );
            } catch (Exception $e) {
                $this->error = $e->getMessage();
                dol_syslog($e->getMessage());
                return -1;
            }

            // Define substitution array
            $substitutionarray = getCommonSubstitutionArray($outputlangs, 0, null, $object);
            $array_soc = $this->get_substitutionarray_mysoc($mysoc, $outputlangs);
            $array_soc['mycompany_logo']  = preg_replace('/_small/', '_mini', $array_soc['mycompany_logo']);

            $tmparray = array_merge($substitutionarray, $array_soc);
            complete_substitutions_array($tmparray, $outputlangs, $object);

            $usertmp      = new User($this->db);
            $task         = new Task($this->db);
            $project      = new Project($this->db);
            $signatory    = new TimeSheetSignature($this->db);
            $workinghours = new Workinghours($this->db);

            $usertmp->fetch($object->fk_user_assign);

            $daystarttoshow = $object->date_start - 12 * 3600;
            $lastdaytoshow  = $object->date_end - 12 * 3600;
            $daysInRange    = num_between_day($object->date_start, $object->date_end, 1);

            $workinghoursArray = $workinghours->fetchCurrentWorkingHours($object->fk_user_assign, 'user');

            for ($idw = 0; $idw < $daysInRange; $idw++) {
                $dayInLoop = dol_time_plus_duree($daystarttoshow, $idw, 'd');
                $day_is_available = isDayAvailable($daystarttoshow, $object->fk_user_assign);
                $day = dol_getdate($dayInLoop);
                $daysInRangeArray[] = $day['mday'];
                if ($day_is_available) {
                    $isavailable[$dayInLoop] = array('morning' => 1, 'afternoon' => 1);
                } else {
                    $isavailable[$dayInLoop] = array('morning' => false, 'afternoon' => false, 'morning_reason'=>'public_holiday', 'afternoon_reason'=>'public_holiday');
                }
            }

            $tmparray['employee_firstname'] = $usertmp->firstname;
            $tmparray['employee_lastname']  = $usertmp->lastname;
            $tmparray['date_start']         = dol_print_date($object->date_start, 'day', 'tzuser');
            $tmparray['date_end']           = dol_print_date($object->date_end, 'day', 'tzuser');
            $tmparray['note_public']        = $object->note_public;
            $tmparray['month_year']         = dol_print_date($object->date_start, "%B %Y", 'tzuser');

            $project->fetch($conf->global->DOLISIRH_HR_PROJECT);

            $tmparray['project_rh_ref'] = $project->ref;
            $tmparray['project_rh']     = $project->title;

            $society_responsible = $signatory->fetchSignatory('TIMESHEET_SOCIETY_RESPONSIBLE', $object->id, 'timesheet');
            $society_responsible = is_array($society_responsible) ? array_shift($society_responsible) : $society_responsible;
            $societey_attendant  = $signatory->fetchSignatory('TIMESHEET_SOCIETY_ATTENDANT', $object->id, 'timesheet');
            $societey_attendant  = is_array($societey_attendant) ? array_shift($societey_attendant) : $societey_attendant;

            $tempdir = $conf->dolisirh->multidir_output[isset($object->entity) ? $object->entity : 1] . '/temp/';

            //Signatures
            if (!empty($society_responsible) && $society_responsible > 0) {
                $tmparray['society_responsible_fullname']       = $society_responsible->lastname . ' ' . $society_responsible->firstname;
                $tmparray['society_responsible_signature_date'] = dol_print_date($society_responsible->signature_date, 'dayhoursec');

                $encoded_image = explode(",",  $society_responsible->signature)[1];
                $decoded_image = base64_decode($encoded_image);
                file_put_contents($tempdir . "signature.png", $decoded_image);
                $tmparray['society_responsible_signature'] = $tempdir . "signature.png";
            } else {
                $tmparray['society_responsible_fullname'] = '';
                $tmparray['society_responsible_signature_date'] = '';
                $tmparray['society_responsible_signature'] = '';
            }

            if (!empty($societey_attendant) && $societey_attendant > 0) {
                $tmparray['society_attendant_fullname']       = $societey_attendant->lastname . ' ' . $societey_attendant->firstname;
                $tmparray['society_attendant_signature_date'] = dol_print_date($societey_attendant->signature_date, 'dayhoursec');

                $encoded_image = explode(",",  $societey_attendant->signature)[1];
                $decoded_image = base64_decode($encoded_image);
                file_put_contents($tempdir . "signature1.png", $decoded_image);
                $tmparray['society_attendant_signature'] = $tempdir . "signature1.png";
            } else {
                $tmparray['society_attendant_fullname'] = '';
                $tmparray['society_attendant_signature_date'] = '';
                $tmparray['society_attendant_signature'] = '';
            }

            unset($tmparray['object_fields']);
            unset($tmparray['object_lines']);

            foreach ($tmparray as $key => $value) {
                try {
                    if ($key == 'society_responsible_signature' || $key == 'society_attendant_signature') { // Image
                        if (file_exists($value)) {
                            $list = getimagesize($value);
                            $newWidth = 350;
                            if ($list[0]) {
                                $ratio = $newWidth / $list[0];
                                $newHeight = $ratio * $list[1];
                                dol_imageResizeOrCrop($value, 0, $newWidth, $newHeight);
                            }
                            $odfHandler->setImage($key, $value);
                        } else {
                            $odfHandler->setVars($key, $langs->trans('NoData'), true, 'UTF-8');
                        }
                    } elseif (preg_match('/logo$/', $key)) {
                        if (file_exists($value)) $odfHandler->setImage($key, $value);
                        else $odfHandler->setVars($key, $langs->transnoentities('ErrorFileNotFound'), true, 'UTF-8');
                    } elseif (empty($value)) { // Text
                        $odfHandler->setVars($key, $langs->trans('NoData'), true, 'UTF-8');
                    } else {
                        $odfHandler->setVars($key, html_entity_decode($value, ENT_QUOTES | ENT_HTML5), true, 'UTF-8');
                    }
                } catch (OdfException $e) {
                    dol_syslog($e->getMessage());
                }
            }
            // Replace tags of lines
            try {
                $foundtagforlines = 1;
                try {
                    $listlines = $odfHandler->setSegment('days');
                } catch (OdfException $e) {
                    // We may arrive here if tags for lines not present into template
                    $foundtagforlines = 0;
                    dol_syslog($e->getMessage());
                }

                // Get table header days
                if ($foundtagforlines) {
                    for ($idw = 1; $idw <= 31; $idw++) {
                        if (in_array($idw, $daysInRangeArray)) {
                            $dayInLoop = dol_time_plus_duree($daystarttoshow, array_search($idw, $daysInRangeArray), 'd');
                            $tmparray['day'.$idw] = dol_print_date($dayInLoop, '%a');
                        } else {
                            $tmparray['day'.$idw] = '-';
                        }
                    }

                    unset($tmparray['object_fields']);
                    unset($tmparray['object_lines']);

                    foreach ($tmparray as $key => $val) {
                        try {
                            $listlines->setVars($key, $val, true, 'UTF-8');
                        } catch (SegmentException $e) {
                            dol_syslog($e->getMessage());
                        }
                    }
                    $listlines->merge();
                    $odfHandler->mergeSegment($listlines);
                }

                // Get all tasks except HR project task
                $foundtagforlines = 1;
                try {
                    $listlines = $odfHandler->setSegment('times');
                } catch (OdfException $e) {
                    // We may arrive here if tags for lines not present into template
                    $foundtagforlines = 0;
                    dol_syslog($e->getMessage());
                }

                if ($foundtagforlines) {
                    $filter = ' AND p.rowid != ' . $conf->global->DOLISIRH_HR_PROJECT;
                    $tasksArray = $task->getTasksArray(0, 0, 0, 0, 0, '', '', $filter,  $object->fk_user_assign);
                    if (is_array($tasksArray) && !empty($tasksArray)) {
                        foreach ($tasksArray as $tasksingle) {
                            $filter = ' AND ptt.fk_task = ' . $tasksingle->id . ' AND ptt.task_date BETWEEN ' . "'" . dol_print_date($object->date_start, 'dayrfc') . "'" . ' AND ' . "'" . dol_print_date($object->date_end, 'dayrfc') . "'";
                            $alltimespent = $task->fetchAllTimeSpent($usertmp, $filter);
                            $totaltimespent = 0;
                            if (is_array($alltimespent) && !empty($alltimespent)) {
                                foreach ($alltimespent as $timespent) {
                                    $totaltimespent += $timespent->timespent_duration;
                                }
                            }
                            if ($totaltimespent > 0) {
                                $project->fetch($tasksingle->fk_project);
                                $workLoad = loadTimeSpentWithinRangeByProject($daystarttoshow, $lastdaytoshow, $project->id, $tasksingle->id, $object->fk_user_assign);
                                for ($idw = 1; $idw <= 31; $idw++) {
                                    $tmparray['task_ref'] = $tasksingle->ref;
                                    $tmparray['task_label'] = dol_trunc($tasksingle->label, 16);
                                    if (in_array($idw, $daysInRangeArray)) {
                                        $dayInLoop = dol_time_plus_duree($daystarttoshow, array_search($idw, $daysInRangeArray), 'd');
                                        $tmparray['time' . $idw] = (($workLoad['monthWorkLoadPerTask'][$dayInLoop][$tasksingle->id] != 0) ? convertSecondToTime($workLoad['monthWorkLoadPerTask'][$dayInLoop][$tasksingle->id], (is_float($workLoad['monthWorkLoadPerTask'][$dayInLoop][$tasksingle->id]/3600) ? 'allhourmin' : 'allhour')) : '-');
                                    } else {
                                        $tmparray['time' . $idw] = '-';
                                    }
                                }

                                unset($tmparray['object_fields']);
                                unset($tmparray['object_lines']);

                                foreach ($tmparray as $key => $val) {
                                    try {
                                        if (empty($val)) {
                                            $listlines->setVars($key, $langs->trans('NoData'), true, 'UTF-8');
                                        } else {
                                            $listlines->setVars($key, html_entity_decode($val, ENT_QUOTES | ENT_HTML5), true, 'UTF-8');
                                        }
                                    } catch (SegmentException $e) {
                                        dol_syslog($e->getMessage());
                                    }
                                }
                                $listlines->merge();
                                $odfHandler->mergeSegment($listlines);
                            }
                        }
                    }
                }

                // Get HR project task
                $i = 0;
                $segment = array(
                    array('csss', 'cps', 'rtts', 'jfs', 'cms',), // Row name
                    array('css', 'cp', 'rtt', 'jf', 'cm',) // Cell name
                );

                $foundtagforlines = 1;
                try {
                    $listlines = $odfHandler->setSegment($segment[0][$i]);
                } catch (OdfException $e) {
                    // We may arrive here if tags for lines not present into template
                    $foundtagforlines = 0;
                    dol_syslog($e->getMessage());
                }

                if ($foundtagforlines) {
                    $tasksArray = $task->getTasksArray(0, 0, $conf->global->DOLISIRH_HR_PROJECT, 0, 0, '', '', '',  $object->fk_user_assign);
                    if (is_array($tasksArray) && !empty($tasksArray)) {
                        foreach ($tasksArray as $tasksingle) {
                            $workLoad = loadTimeSpentWithinRangeByProject($daystarttoshow, $lastdaytoshow, $conf->global->DOLISIRH_HR_PROJECT, $tasksingle->id, $object->fk_user_assign);
                            for ($idw = 1; $idw <= 31; $idw++) {
                                if (in_array($idw, $daysInRangeArray)) {
                                    $dayInLoop = dol_time_plus_duree($daystarttoshow, array_search($idw, $daysInRangeArray), 'd');
                                    $tmparray[$segment[1][$i] . $idw] = (($workLoad['monthWorkLoadPerTask'][$dayInLoop][$tasksingle->id] != 0) ? convertSecondToTime($workLoad['monthWorkLoadPerTask'][$dayInLoop][$tasksingle->id], (is_float($workLoad['monthWorkLoadPerTask'][$dayInLoop][$tasksingle->id] / 3600) ? 'allhourmin' : 'allhour')) : '-');
                                } else {
                                    $tmparray[$segment[1][$i] . $idw] = '-';
                                }
                            }

                            unset($tmparray['object_fields']);
                            unset($tmparray['object_lines']);

                            foreach ($tmparray as $key => $val) {
                                try {
                                    $listlines->setVars($key, $val, true, 'UTF-8');
                                } catch (SegmentException $e) {
                                    dol_syslog($e->getMessage());
                                }
                            }
                            $listlines->merge();
                            $odfHandler->mergeSegment($listlines);
                            $i++;
                        }
                    }
                }

                // Total time RH
                $foundtagforlines = 1;
                try {
                    $listlines = $odfHandler->setSegment('totalrhs');
                } catch (OdfException $e) {
                    // We may arrive here if tags for lines not present into template
                    $foundtagforlines = 0;
                    dol_syslog($e->getMessage());
                }

                if ($foundtagforlines) {
                    for ($idw = 1; $idw <= 31; $idw++) {
                        if (in_array($idw, $daysInRangeArray)) {
                            $dayInLoop = dol_time_plus_duree($daystarttoshow, array_search($idw, $daysInRangeArray), 'd'); // $daystarttoshow is a date with hours = 0
                            $tmparray['totalrh' . $idw] = (($workLoad['monthWorkLoad'][$dayInLoop] != 0) ? convertSecondToTime($workLoad['monthWorkLoad'][$dayInLoop], (is_float($workLoad['monthWorkLoad'][$dayInLoop]/3600) ? 'allhourmin' : 'allhour')) : '-');
                        } else {
                            $tmparray['totalrh' . $idw] = '-';
                        }
                    }

                    unset($tmparray['object_fields']);
                    unset($tmparray['object_lines']);

                    foreach ($tmparray as $key => $val) {
                        try {
                            $listlines->setVars($key, $val, true, 'UTF-8');
                        } catch (SegmentException $e) {
                            dol_syslog($e->getMessage());
                        }
                    }
                    $listlines->merge();
                    $odfHandler->mergeSegment($listlines);
                }

                // Total time consumed whithout Project RH
                $foundtagforlines = 1;
                try {
                    $listlines = $odfHandler->setSegment('totaltimes');
                } catch (OdfException $e) {
                    // We may arrive here if tags for lines not present into template
                    $foundtagforlines = 0;
                    dol_syslog($e->getMessage());
                }

                if ($foundtagforlines) {
                    for ($idw = 1; $idw <= 31; $idw++) {
                        if (in_array($idw, $daysInRangeArray)) {
                            $dayInLoop = dol_time_plus_duree($daystarttoshow, array_search($idw, $daysInRangeArray), 'd');
                            $totaltime = loadTimeSpentWithinRange($dayInLoop, dol_time_plus_duree($dayInLoop, 1, 'd'), 0, $object->fk_user_assign);
                            $tmparray['totaltime' . $idw] = (($totaltime['total'] != 0) ? convertSecondToTime($totaltime['total'] * 60, (is_float($totaltime['total'] * 60) ? 'allhourmin' : 'allhour')) : '-');
                        } else {
                            $tmparray['totaltime' . $idw] = '-';
                        }
                    }

                    unset($tmparray['object_fields']);
                    unset($tmparray['object_lines']);

                    foreach ($tmparray as $key => $val) {
                        try {
                            $listlines->setVars($key, $val, true, 'UTF-8');
                        } catch (SegmentException $e) {
                            dol_syslog($e->getMessage());
                        }
                    }
                    $listlines->merge();
                    $odfHandler->mergeSegment($listlines);
                }

                // Total time consumed
                $foundtagforlines = 1;
                try {
                    $listlines = $odfHandler->setSegment('totaltpss');
                } catch (OdfException $e) {
                    // We may arrive here if tags for lines not present into template
                    $foundtagforlines = 0;
                    dol_syslog($e->getMessage());
                }

                if ($foundtagforlines) {
                    for ($idw = 1; $idw <= 31; $idw++) {
                        if (in_array($idw, $daysInRangeArray)) {
                            $dayInLoop = dol_time_plus_duree($daystarttoshow, array_search($idw, $daysInRangeArray), 'd');
                            $totaltimespent = loadTimeSpentWithinRange($dayInLoop, dol_time_plus_duree($dayInLoop, 1, 'd'), 0, $object->fk_user_assign);
                            $tmparray['totaltps' . $idw] = (($totaltimespent['total'] != 0) ? convertSecondToTime($totaltimespent['total'] * 60, (is_float($totaltimespent['total'] * 60) ? 'allhourmin' : 'allhour')) : '-');
                        } else {
                            $tmparray['totaltps' . $idw] = '-';
                        }
                    }

                    unset($tmparray['object_fields']);
                    unset($tmparray['object_lines']);

                    foreach ($tmparray as $key => $val) {
                        try {
                            $listlines->setVars($key, $val, true, 'UTF-8');
                        } catch (SegmentException $e) {
                            dol_syslog($e->getMessage());
                        }
                    }
                    $listlines->merge();
                    $odfHandler->mergeSegment($listlines);
                }

                // Total time spent
                $foundtagforlines = 1;
                try {
                    $listlines = $odfHandler->setSegment('tas');
                } catch (OdfException $e) {
                    // We may arrive here if tags for lines not present into template
                    $foundtagforlines = 0;
                    dol_syslog($e->getMessage());
                }

                if ($foundtagforlines) {
                    for ($idw = 1; $idw <= 31; $idw++) {
                        if (in_array($idw, $daysInRangeArray)) {
                            $dayInLoop = dol_time_plus_duree($daystarttoshow, array_search($idw, $daysInRangeArray), 'd');  // $daystarttoshow is a date with hours = 0
                            if ($isavailable[$dayInLoop]['morning'] && $isavailable[$dayInLoop]['afternoon']) {
                                $currentDay = date('l', $dayInLoop);
                                $currentDay = 'workinghours_' . strtolower($currentDay);
                                $workinghoursMonth = $workinghoursArray->{$currentDay} * 60;
                            } else {
                                $workinghoursMonth = 0;
                            }
                            $tmparray['ta' . $idw] = (($workinghoursMonth != 0) ? convertSecondToTime($workinghoursMonth, (is_float($workinghoursMonth / 60 / 60) ? 'allhourmin' : 'allhour')) : '-');
                        } else {
                            $tmparray['ta' . $idw] = '-';
                        }
                    }

                    unset($tmparray['object_fields']);
                    unset($tmparray['object_lines']);

                    foreach ($tmparray as $key => $val) {
                        try {
                            $listlines->setVars($key, $val, true, 'UTF-8');
                        } catch (SegmentException $e) {
                            dol_syslog($e->getMessage());
                        }
                    }
                    $listlines->merge();
                    $odfHandler->mergeSegment($listlines);
                }

                // Diff between time consumed and time spent
                $foundtagforlines = 1;
                try {
                    $listlines = $odfHandler->setSegment('diffs');
                } catch (OdfException $e) {
                    // We may arrive here if tags for lines not present into template
                    $foundtagforlines = 0;
                    dol_syslog($e->getMessage());
                }

                if ($foundtagforlines) {
                    for ($idw = 1; $idw <= 31; $idw++) {
                        if (in_array($idw, $daysInRangeArray)) {
                            $dayInLoop = dol_time_plus_duree($daystarttoshow, array_search($idw, $daysInRangeArray), 'd');
                            if ($isavailable[$dayInLoop]['morning'] && $isavailable[$dayInLoop]['afternoon']) {
                                $currentDay = date('l', $dayInLoop);
                                $currentDay = 'workinghours_' . strtolower($currentDay);
                                $workinghoursMonth = $workinghoursArray->{$currentDay} * 60;
                            } else {
                                $workinghoursMonth = 0;
                            }
                            $difftotaltime = $workinghoursMonth - $totalforvisibletasks[$dayInLoop];
                            $tmparray['diff' . $idw] = (($difftotaltime != 0) ? convertSecondToTime(abs($difftotaltime), (is_float($difftotaltime / 60 / 60) ? 'allhourmin' : 'allhour')) : '-');
                        } else {
                            $tmparray['diff' . $idw] = '-';
                        }
                    }

                    unset($tmparray['object_fields']);
                    unset($tmparray['object_lines']);

                    foreach ($tmparray as $key => $val) {
                        try {
                            $listlines->setVars($key, $val, true, 'UTF-8');
                        } catch (SegmentException $e) {
                            dol_syslog($e->getMessage());
                        }
                    }
                    $listlines->merge();
                    $odfHandler->mergeSegment($listlines);
                }

                // TimeSheetDet
                $foundtagforlines = 1;
                try {
                    $listlines = $odfHandler->setSegment('timesheetdet');
                } catch (OdfException $e) {
                    // We may arrive here if tags for lines not present into template
                    $foundtagforlines = 0;
                    dol_syslog($e->getMessage());
                }

                if ($foundtagforlines) {
                    $object->fetchLines();
                    if (is_array($object->lines) && !empty($object->lines)) {
                        foreach ($object->lines as $line) {
                            if ($line->fk_product > 0) {
                                $product = new Product($this->db);
                                $product->fetch($line->fk_product);
                                $tmparray['timesheetdet_label'] = $product->label;
                                $tmparray['timesheetdet_qty']   = $line->qty;
                            } elseif (!empty($line->description)) {
                                $tmparray['timesheetdet_label'] = $line->description;
                                $tmparray['timesheetdet_qty']   = $line->qty;
                            }

                            unset($tmparray['object_fields']);
                            unset($tmparray['object_lines']);

                            foreach ($tmparray as $key => $val) {
                                try {
                                    $listlines->setVars($key, $val, true, 'UTF-8');
                                } catch (SegmentException $e) {
                                    dol_syslog($e->getMessage());
                                }
                            }
                            $listlines->merge();
                        }
                    }
                    $odfHandler->mergeSegment($listlines);
                }
            } catch (OdfException $e) {
                $this->error = $e->getMessage();
                dol_syslog($this->error, LOG_WARNING);
                return -1;
            }

            // Replace labels translated
            $tmparray = $outputlangs->get_translations_for_substitutions();
            foreach ($tmparray as $key => $value) {
                try {
                    $odfHandler->setVars($key, $value, true, 'UTF-8');
                } catch (OdfException $e) {
                    dol_syslog($e->getMessage());
                }
            }

            // Call the beforeODTSave hook
            $parameters = array('odfHandler' => &$odfHandler, 'file' => $file, 'object' => $object, 'outputlangs' => $outputlangs, 'substitutionarray' => &$tmparray);
            $hookmanager->executeHooks('beforeODTSave', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks

            // Write new file
            if (!empty($conf->global->MAIN_ODT_AS_PDF)) {
                try {
                    $odfHandler->exportAsAttachedPDF($file);
                } catch (Exception $e) {
                    $this->error = $e->getMessage();
                    dol_syslog($e->getMessage());
                    return -1;
                }
            } else {
                try {
                    $odfHandler->saveToDisk($file);
                } catch (Exception $e) {
                    $this->error = $e->getMessage();
                    dol_syslog($e->getMessage());
                    return -1;
                }
            }

            $parameters = array('odfHandler' => &$odfHandler, 'file' => $file, 'object' => $object, 'outputlangs' => $outputlangs, 'substitutionarray' => &$tmparray);
            $hookmanager->executeHooks('afterODTCreation', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks

            $odfHandler = null; // Destroy object

            dol_delete_file($tempdir . "signature.png");
            dol_delete_file($tempdir . "signature1.png");

            $this->result = array('fullpath'=>$file);

            return 1; // Success
        } else {
            $this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
            return -1;
        }

		return -1;
	}
}
