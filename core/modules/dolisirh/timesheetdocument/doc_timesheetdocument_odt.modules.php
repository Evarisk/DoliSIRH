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
		$this->name = $langs->trans('TimeSheetDocumentDoliSIRHTemplate');
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

		$texte = $this->description.".<br>\n";
		$texte .= '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
		$texte .= '<input type="hidden" name="token" value="'.newToken().'">';
		$texte .= '<input type="hidden" name="page_y" value="">';
		$texte .= '<input type="hidden" name="action" value="setModuleOptions">';
		$texte .= '<input type="hidden" name="param1" value="DOLISIRH_TIMESHEETDOCUMENT_ADDON_ODT_PATH">';
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
			//$texte.=$nbofiles?'<a id="a_'.get_class($this).'" href="#">':'';
			$texte .= count($listoffiles);
			//$texte.=$nbofiles?'</a>':'';
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

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Function to build a document on disk using the generic odt module.
	 *
	 *	@param		TimeSheet	$object				Object source to build document
	 *	@param		Translate	$outputlangs		Lang output object
	 * 	@param		string		$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int			$hidedetails		Do not show line details
	 *  @param		int			$hidedesc			Do not show desc
	 *  @param		int			$hideref			Do not show ref
	 *	@return		int         					1 if OK, <=0 if KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath, $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $user, $langs, $conf, $mysoc, $hookmanager;

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
		global $action;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		$sav_charset_output = $outputlangs->charset_output;
		$outputlangs->charset_output = 'UTF-8';

		$outputlangs->loadLangs(array("main", "dict", "companies", "bills"));

		if ($conf->dolisirh->dir_output) {
			// If $object is id instead of object
			if (!is_object($object)) {
				$id = $object;
				$object = new TimeSheet($this->db);
				$result = $object->fetch($id);
				if ($result < 0) {
					dol_print_error($this->db, $object->error);
					return -1;
				}
			}

			$object->fetch_thirdparty();

			$objectref = dol_sanitizeFileName($object->ref);
			$dir = $conf->dolisirh->multidir_output[isset($object->entity) ? $object->entity : 1] . '/timesheetdocument/' . $objectref;

			//          if (!preg_match('/specimen/i', $objectref)) {
			//              $dir .= "/".$objectref;
			//          }

			if (!file_exists($dir)) {
				if (dol_mkdir($dir) < 0) {
					$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
					return -1;
				}
			}

			if (file_exists($dir)) {
				//print "srctemplatepath=".$srctemplatepath;	// Src filename
				$newfile = basename($srctemplatepath);
				$newfiletmp = preg_replace('/\.od(t|s)/i', '', $newfile);
				$newfiletmp = preg_replace('/template_/i', '', $newfiletmp);
				$newfiletmp = preg_replace('/modele_/i', '', $newfiletmp);

				$date = dol_print_date(dol_now(), 'dayxcard');

				$newfiletmp = $objectref . '_' . $date . '_' . $newfiletmp . '_' . $conf->global->MAIN_INFO_SOCIETE_NOM;

				// Get extension (ods or odt)
				$newfileformat = substr($newfile, strrpos($newfile, '.') + 1);
				if (!empty($conf->global->MAIN_DOC_USE_TIMING)) {
					$format = $conf->global->MAIN_DOC_USE_TIMING;
					if ($format == '1') {
						$format = '%Y%m%d%H%M%S';
					}
					$filename = $newfiletmp.'-'.dol_print_date(dol_now(), $format).'.'.$newfileformat;
				} else {
					$filename = $newfiletmp.'.'.$newfileformat;
				}
				$file = $dir.'/'.$filename;
				//print "newdir=".$dir;
				//print "newfile=".$newfile;
				//print "file=".$file;
				//print "conf->societe->dir_temp=".$conf->societe->dir_temp;

				dol_mkdir($conf->dolisirh->dir_temp);
				if (!is_writable($conf->dolisirh->dir_temp)) {
					$this->error = "Failed to write in temp directory ".$conf->dolisirh->dir_temp;
					dol_syslog('Error in write_file: '.$this->error, LOG_ERR);
					return -1;
				}

				// If CUSTOMER contact defined on order, we use it
				$usecontact = false;
				$arrayidcontact = $object->getIdContact('external', 'CUSTOMER');
				if (count($arrayidcontact) > 0) {
					$usecontact = true;
					$result = $object->fetch_contact($arrayidcontact[0]);
				}

				// Recipient name
				$contactobject = null;
				if (!empty($usecontact)) {
					// We can use the company of contact instead of thirdparty company
					if ($object->contact->socid != $object->thirdparty->id && (!isset($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT) || !empty($conf->global->MAIN_USE_COMPANY_NAME_OF_CONTACT))) {
						$object->contact->fetch_thirdparty();
						$socobject = $object->contact->thirdparty;
						$contactobject = $object->contact;
					} else {
						$socobject = $object->thirdparty;
						// if we have a CUSTOMER contact and we dont use it as thirdparty recipient we store the contact object for later use
						$contactobject = $object->contact;
					}
				} else {
					$socobject = $object->thirdparty;
				}

				// Make substitution
				$substitutionarray = array(
					'__FROM_NAME__' => $this->emetteur->name,
					'__FROM_EMAIL__' => $this->emetteur->email,
					'__TOTAL_TTC__' => $object->total_ttc,
					'__TOTAL_HT__' => $object->total_ht,
					'__TOTAL_VAT__' => $object->total_tva
				);
				complete_substitutions_array($substitutionarray, $langs, $object);
				// Call the ODTSubstitution hook
				$parameters = array('file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs, 'substitutionarray'=>&$substitutionarray);
				$reshook = $hookmanager->executeHooks('ODTSubstitution', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks

				// Line of free text
				$newfreetext = '';
				$paramfreetext = 'ORDER_FREE_TEXT';
				if (!empty($conf->global->$paramfreetext)) {
					$newfreetext = make_substitutions($conf->global->$paramfreetext, $substitutionarray);
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
					dol_syslog($e->getMessage(), LOG_INFO);
					return -1;
				}
				// After construction $odfHandler->contentXml contains content and
				// [!-- BEGIN row.lines --]*[!-- END row.lines --] has been replaced by
				// [!-- BEGIN lines --]*[!-- END lines --]
				//print html_entity_decode($odfHandler->__toString());
				//print exit;


				// Make substitutions into odt of freetext
				try {
					$odfHandler->setVars('free_text', $newfreetext, true, 'UTF-8');
				} catch (OdfException $e) {
					dol_syslog($e->getMessage(), LOG_INFO);
				}

				// Define substitution array
				$substitutionarray = getCommonSubstitutionArray($outputlangs, 0, null, $object);
				$array_object_from_properties = $this->get_substitutionarray_each_var_object($object, $outputlangs);
				$array_objet = $this->get_substitutionarray_object($object, $outputlangs);
				$array_user = $this->get_substitutionarray_user($user, $outputlangs);
				$array_soc = $this->get_substitutionarray_mysoc($mysoc, $outputlangs);
				$array_soc['mycompany_logo']  = preg_replace('/_small/', '_mini', $array_soc['mycompany_logo']);
				$array_thirdparty = $this->get_substitutionarray_thirdparty($socobject, $outputlangs);
				$array_other = $this->get_substitutionarray_other($outputlangs);
				// retrieve contact information for use in object as contact_xxx tags
				$array_thirdparty_contact = array();
				if ($usecontact && is_object($contactobject)) {
					$array_thirdparty_contact = $this->get_substitutionarray_contact($contactobject, $outputlangs, 'contact');
				}

				$tmparray = array_merge($substitutionarray, $array_object_from_properties, $array_user, $array_soc, $array_thirdparty, $array_objet, $array_other, $array_thirdparty_contact);
				complete_substitutions_array($tmparray, $outputlangs, $object);

				// Call the ODTSubstitution hook
				$parameters = array('odfHandler'=>&$odfHandler, 'file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs, 'substitutionarray'=>&$tmparray);
				$reshook = $hookmanager->executeHooks('ODTSubstitution', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks

				require_once DOL_DOCUMENT_ROOT.'/holiday/class/holiday.class.php';

				$usertmp     = new User($this->db);
				$task        = new Task($this->db);
				$project     = new Project($this->db);
				$extrafields = new ExtraFields($this->db);
				$holiday     = new Holiday($this->db);

				$usertmp->fetch($object->fk_user_assign);

				$datestart          = dol_getdate($object->date_start);
				$firstdaytoshow     = dol_get_first_day($datestart['year'], $datestart['mon']);
				$firstdaytoshowgmt  = dol_get_first_day($datestart['year'], $datestart['mon'], true);
				$dayInMonth         = cal_days_in_month(CAL_GREGORIAN, $datestart['mon'], $datestart['year']);
				$daystarttoshow     = $object->date_start - 12 * 60 * 60;
				$daystarttoshowgmt  = $object->date_start - 12 * 60 * 60;
				$dayInDateRange     = num_between_day($object->date_start, $object->date_end, 1);
				$lastdaytoshow      = $object->date_end - 12 * 60 * 60;

				for ($idw = 0; $idw < $dayInDateRange; $idw++) {
					$dayinloopfromfirstdaytoshow = dol_time_plus_duree($daystarttoshow, $idw, 'd');
					$day = dol_getdate($dayinloopfromfirstdaytoshow);
					$dayInDateRangeArray[] = $day['mday'];
				}

				$tasksarray = $task->getTasksArray(0, 0, 0, 0, 0, '', '', '',  $object->fk_user_assign, 0, $extrafields);

				$isavailable = array();

				for ($idw = 0; $idw < $dayInMonth; $idw++) {
					$dayinloopfromfirstdaytoshow = dol_time_plus_duree($firstdaytoshow, $idw, 'd'); // $daystarttoshow is a date with hours = 0
					$dayinloopfromfirstdaytoshowgmt = dol_time_plus_duree($firstdaytoshowgmt, $idw, 'd'); // $daystarttoshow is a date with hours = 0

					$statusofholidaytocheck = Holiday::STATUS_APPROVED;

					$isavailablefordayanduser = $holiday->verifDateHolidayForTimestamp($object->fk_user_assign, $dayinloopfromfirstdaytoshow, $statusofholidaytocheck);
					$isavailable[$dayinloopfromfirstdaytoshow] = $isavailablefordayanduser; // in projectLinesPerWeek later, we are using $daystarttoshow and dol_time_plus_duree to loop on each day

					$test = num_public_holiday($dayinloopfromfirstdaytoshowgmt, $dayinloopfromfirstdaytoshowgmt + 86400, $mysoc->country_code);
					if ($test) {
						$isavailable[$dayinloopfromfirstdaytoshow] = array('morning'=>false, 'afternoon'=>false, 'morning_reason'=>'public_holiday', 'afternoon_reason'=>'public_holiday');
					}
				}

				$j = 0;
				$level = 0;
				$projectsrole = $task->getUserRolesForProjectsOrTasks($usertmp, 0, ($project->id ?: 0), 0, 1);
				$tasksrole = $task->getUserRolesForProjectsOrTasks(0, $usertmp, ($project->id ?: 0), 0, 1);
				$restrictviewformytask = ((!isset($conf->global->PROJECT_TIME_SHOW_TASK_NOT_ASSIGNED)) ? 2 : $conf->global->PROJECT_TIME_SHOW_TASK_NOT_ASSIGNED);
				$totalforvisibletasks = projectLinesPerDayOnMonth($j, $daystarttoshow, $lastdaytoshow, $usertmp, 0, $tasksarray, $level, $projectsrole, $tasksrole, 0, $restrictviewformytask, $isavailable, 0, array(), $extrafields, $dayInMonth, 1);

				$tmparray['employee_firstname'] = $usertmp->firstname;
				$tmparray['employee_lastname']  = $usertmp->lastname;
				$tmparray['date_start']         = dol_print_date($object->date_start, 'day', 'tzuser');
				$tmparray['date_end']           = dol_print_date($object->date_end, 'day', 'tzuser');
				$tmparray['note_public']        = $object->note_public;

				$tmparray['month_year']        = dol_print_date($object->date_start, "%B %Y", 'tzuser');

				$project->fetch($conf->global->DOLISIRH_HR_PROJECT);

				$tmparray['project_rh_ref'] = $project->ref;
				$tmparray['project_rh']     = $project->title;

				$signatory = new TimeSheetSignature($this->db);

				$society_responsible = $signatory->fetchSignatory('TIMESHEET_SOCIETY_RESPONSIBLE', $object->id, 'timesheet');
				$society_responsible = is_array($society_responsible) ? array_shift($society_responsible) : $society_responsible;
				$societey_attendant  = $signatory->fetchSignatory('TIMESHEET_SOCIETY_ATTENDANT', $object->id, 'timesheet');
				$societey_attendant  = is_array($societey_attendant) ? array_shift($societey_attendant) : $societey_attendant;

				$tempdir = $conf->dolisirh->multidir_output[isset($object->entity) ? $object->entity : 1] . '/temp/';

				//Signatures
				if ( ! empty($society_responsible) && $society_responsible > 0) {
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
				if ( ! empty($societey_attendant) && $societey_attendant > 0) {
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
						dol_syslog($e->getMessage(), LOG_INFO);
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
						dol_syslog($e->getMessage(), LOG_INFO);
					}
					if ($foundtagforlines) {
						$linenumber = 0;
						for ($idw = 1; $idw <= 31; $idw++) {
							if (in_array($idw, $dayInDateRangeArray)) {
								$dayinloopfromfirstdaytoshow = dol_time_plus_duree($daystarttoshow, array_search($idw, $dayInDateRangeArray), 'd'); // $daystarttoshow is a date with hours = 0
								$tmparray['day'.$idw] = dol_print_date($dayinloopfromfirstdaytoshow, '%a');
							} else {
								$tmparray['day'.$idw] = '-';
							}
						}

						//                      $linenumber++;
						//                      $tmparray = $this->get_substitutionarray_lines($line, $outputlangs, $linenumber);
						//                      complete_substitutions_array($tmparray, $outputlangs, $object, $line, "completesubstitutionarray_lines");

						unset($tmparray['object_fields']);
						unset($tmparray['object_lines']);

						// Call the ODTSubstitutionLine hook
						$parameters = array('odfHandler'=>&$odfHandler, 'file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs, 'substitutionarray'=>&$tmparray, 'line'=>$line);
						$reshook = $hookmanager->executeHooks('ODTSubstitutionLine', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
						foreach ($tmparray as $key => $val) {
							try {
								$listlines->setVars($key, $val, true, 'UTF-8');
							} catch (OdfException $e) {
								dol_syslog($e->getMessage(), LOG_INFO);
							} catch (SegmentException $e) {
								dol_syslog($e->getMessage(), LOG_INFO);
							}
						}
						$listlines->merge();
						$odfHandler->mergeSegment($listlines);
					}

					$filter = ' AND p.rowid != ' . $conf->global->DOLISIRH_HR_PROJECT;
					$tasksArray = $task->getTasksArray(0, 0, 0, 0, 0, '', '', $filter,  $object->fk_user_assign, 0, $extrafields);
					if (is_array($tasksArray) && !empty($tasksArray)) {
						foreach ($tasksArray as $tasksingle) {
							$filter = ' AND ptt.fk_task = ' . $tasksingle->id . ' AND ptt.task_date BETWEEN ' . "'" .dol_print_date($object->date_start, 'dayrfc') . "'" . ' AND ' . "'" . dol_print_date($object->date_end, 'dayrfc'). "'";
							$alltimespent = $task->fetchAllTimeSpent($usertmp, $filter);
							$totaltimespent = 0;
							if (is_array($alltimespent) && !empty($alltimespent)) {
								foreach ($alltimespent as $timespent) {
									$totaltimespent += $timespent->timespent_duration;
								}
							}
							if ($totaltimespent > 0) {
								$foundtagforlines = 1;
								try {
									$listlines = $odfHandler->setSegment('times');
								} catch (OdfException $e) {
									// We may arrive here if tags for lines not present into template
									$foundtagforlines = 0;
									dol_syslog($e->getMessage(), LOG_INFO);
								}
								if ($foundtagforlines) {
									$linenumber = 0;

									$project->fetch($tasksingle->fk_project);

									loadTimeSpentMonthByDay($daystarttoshow, $lastdaytoshow, $tasksingle->id, $object->fk_user_assign, $project);

									for ($idw = 1; $idw <= 31; $idw++) {
										$tmparray['task_ref'] = $tasksingle->ref;
										$tmparray['task_label'] = dol_trunc($tasksingle->label, 16);
										if (in_array($idw, $dayInDateRangeArray)) {
											$dayinloopfromfirstdaytoshow = dol_time_plus_duree($daystarttoshow, array_search($idw, $dayInDateRangeArray), 'd'); // $daystarttoshow is a date with hours = 0
											$tmparray['time' . $idw] = (($project->monthWorkLoadPerTask[$dayinloopfromfirstdaytoshow][$tasksingle->id] != 0) ? convertSecondToTime($project->monthWorkLoadPerTask[$dayinloopfromfirstdaytoshow][$tasksingle->id], (is_float($project->monthWorkLoadPerTask[$dayinloopfromfirstdaytoshow][$tasksingle->id] / 60 / 60) ? 'allhourmin' : 'allhour')) : '-');
										} else {
											$tmparray['time' . $idw] = '-';
										}
									}

									//                      $linenumber++;
									//                      $tmparray = $this->get_substitutionarray_lines($line, $outputlangs, $linenumber);
									//                      complete_substitutions_array($tmparray, $outputlangs, $object, $line, "completesubstitutionarray_lines");

									unset($tmparray['object_fields']);
									unset($tmparray['object_lines']);

									// Call the ODTSubstitutionLine hook
									$parameters = array('odfHandler' => &$odfHandler, 'file' => $file, 'object' => $object, 'outputlangs' => $outputlangs, 'substitutionarray' => &$tmparray, 'line' => $line);
									$reshook = $hookmanager->executeHooks('ODTSubstitutionLine', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
									foreach ($tmparray as $key => $val) {
										try {
											if (empty($val)) {
												$listlines->setVars($key, $langs->trans('NoData'), true, 'UTF-8');
											} else {
												$listlines->setVars($key, html_entity_decode($val, ENT_QUOTES | ENT_HTML5), true, 'UTF-8');
											}
										} catch (OdfException $e) {
											dol_syslog($e->getMessage(), LOG_INFO);
										} catch (SegmentException $e) {
											dol_syslog($e->getMessage(), LOG_INFO);
										}
									}
									$listlines->merge();
								}
							}
						}
						$odfHandler->mergeSegment($listlines);
					}

					$project->fetch($conf->global->DOLISIRH_HR_PROJECT);
					$tasksArray = $task->getTasksArray(0, 0, $conf->global->DOLISIRH_HR_PROJECT, 0, 0, '', '', '',  $object->fk_user_assign, 0, $extrafields);
					$segment = array(
						array(
						'csss',
						'cps',
						'rtts',
						'jfs',
						'cms',
						),
						array(
							'css',
							'cp',
							'rtt',
							'jf',
							'cm',
						)
					);
					$i = 0;
					if (is_array($tasksArray) && !empty($tasksArray)) {
						foreach ($tasksArray as $tasksingle) {
							$foundtagforlines = 1;
							try {
								$listlines = $odfHandler->setSegment($segment[0][$i]);
							} catch (OdfException $e) {
								// We may arrive here if tags for lines not present into template
								$foundtagforlines = 0;
								dol_syslog($e->getMessage(), LOG_INFO);
							}
							if ($foundtagforlines) {
								$linenumber = 0;

								loadTimeSpentMonthByDay($daystarttoshow, $lastdaytoshow, $tasksingle->id, $object->fk_user_assign, $project);

								//echo '<pre>'; print_r($project->monthWorkLoad); echo '</pre>';

								for ($idw = 1; $idw <= 31; $idw++) {
									if (in_array($idw, $dayInDateRangeArray)) {
										$dayinloopfromfirstdaytoshow = dol_time_plus_duree($daystarttoshow, array_search($idw, $dayInDateRangeArray), 'd'); // $daystarttoshow is a date with hours = 0
										$tmparray[$segment[1][$i] . $idw] = (($project->monthWorkLoadPerTask[$dayinloopfromfirstdaytoshow][$tasksingle->id] != 0) ? convertSecondToTime($project->monthWorkLoadPerTask[$dayinloopfromfirstdaytoshow][$tasksingle->id], (is_float($project->monthWorkLoadPerTask[$dayinloopfromfirstdaytoshow][$tasksingle->id] / 60 / 60) ? 'allhourmin' : 'allhour')) : '-');
									} else {
										$tmparray[$segment[1][$i] . $idw] = '-';
									}
								}

								//                      $linenumber++;
								//                      $tmparray = $this->get_substitutionarray_lines($line, $outputlangs, $linenumber);
								//                      complete_substitutions_array($tmparray, $outputlangs, $object, $line, "completesubstitutionarray_lines");

								unset($tmparray['object_fields']);
								unset($tmparray['object_lines']);

								// Call the ODTSubstitutionLine hook
								$parameters = array('odfHandler'=>&$odfHandler, 'file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs, 'substitutionarray'=>&$tmparray, 'line'=>$line);
								$reshook = $hookmanager->executeHooks('ODTSubstitutionLine', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
								foreach ($tmparray as $key => $val) {
									try {
										$listlines->setVars($key, $val, true, 'UTF-8');
									} catch (OdfException $e) {
										dol_syslog($e->getMessage(), LOG_INFO);
									} catch (SegmentException $e) {
										dol_syslog($e->getMessage(), LOG_INFO);
									}
								}
								$listlines->merge();
								$odfHandler->mergeSegment($listlines);
							}
							$i++;
						}
					}

					// Total time RH
					$foundtagforlines = 1;
					try {
						$listlines = $odfHandler->setSegment('totalrhs');
					} catch (OdfException $e) {
						// We may arrive here if tags for lines not present into template
						$foundtagforlines = 0;
						dol_syslog($e->getMessage(), LOG_INFO);
					}
					if ($foundtagforlines) {
						$linenumber = 0;
						for ($idw = 1; $idw <= 31; $idw++) {
							if (in_array($idw, $dayInDateRangeArray)) {
								$dayinloopfromfirstdaytoshow = dol_time_plus_duree($daystarttoshow, array_search($idw, $dayInDateRangeArray), 'd'); // $daystarttoshow is a date with hours = 0
								$tmparray['totalrh' . $idw] = (($project->monthWorkLoad[$dayinloopfromfirstdaytoshow] != 0) ? convertSecondToTime($project->monthWorkLoad[$dayinloopfromfirstdaytoshow], (is_float($project->monthWorkLoad[$dayinloopfromfirstdaytoshow] / 60 / 60) ? 'allhourmin' : 'allhour')) : '-');
							} else {
								$tmparray['totalrh' . $idw] = '-';
							}
						}

						//                      $linenumber++;
						//                      $tmparray = $this->get_substitutionarray_lines($line, $outputlangs, $linenumber);
						//                      complete_substitutions_array($tmparray, $outputlangs, $object, $line, "completesubstitutionarray_lines");

						unset($tmparray['object_fields']);
						unset($tmparray['object_lines']);

						// Call the ODTSubstitutionLine hook
						$parameters = array('odfHandler'=>&$odfHandler, 'file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs, 'substitutionarray'=>&$tmparray, 'line'=>$line);
						$reshook = $hookmanager->executeHooks('ODTSubstitutionLine', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
						foreach ($tmparray as $key => $val) {
							try {
								$listlines->setVars($key, $val, true, 'UTF-8');
							} catch (OdfException $e) {
								dol_syslog($e->getMessage(), LOG_INFO);
							} catch (SegmentException $e) {
								dol_syslog($e->getMessage(), LOG_INFO);
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
						dol_syslog($e->getMessage(), LOG_INFO);
					}
					if ($foundtagforlines) {
						$linenumber = 0;

						for ($idw = 1; $idw <= 31; $idw++) {
							if (in_array($idw, $dayInDateRangeArray)) {
								$dayinloopfromfirstdaytoshow = dol_time_plus_duree($daystarttoshow, array_search($idw, $dayInDateRangeArray), 'd'); // $daystarttoshow is a date with hours = 0
								$totaltime = $totalforvisibletasks[$dayinloopfromfirstdaytoshow] - $project->monthWorkLoad[$dayinloopfromfirstdaytoshow];
								$tmparray['totaltime' . $idw] = (($totaltime != 0) ? convertSecondToTime($totaltime, (is_float($totaltime / 60 / 60) ? 'allhourmin' : 'allhour')) : '-');
							} else {
								$tmparray['totaltime' . $idw] = '-';
							}
						}

						//                      $linenumber++;
						//                      $tmparray = $this->get_substitutionarray_lines($line, $outputlangs, $linenumber);
						//                      complete_substitutions_array($tmparray, $outputlangs, $object, $line, "completesubstitutionarray_lines");

						unset($tmparray['object_fields']);
						unset($tmparray['object_lines']);

						// Call the ODTSubstitutionLine hook
						$parameters = array('odfHandler'=>&$odfHandler, 'file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs, 'substitutionarray'=>&$tmparray, 'line'=>$line);
						$reshook = $hookmanager->executeHooks('ODTSubstitutionLine', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
						foreach ($tmparray as $key => $val) {
							try {
								$listlines->setVars($key, $val, true, 'UTF-8');
							} catch (OdfException $e) {
								dol_syslog($e->getMessage(), LOG_INFO);
							} catch (SegmentException $e) {
								dol_syslog($e->getMessage(), LOG_INFO);
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
						dol_syslog($e->getMessage(), LOG_INFO);
					}
					if ($foundtagforlines) {
						$linenumber = 0;
						for ($idw = 1; $idw <= 31; $idw++) {
							if (in_array($idw, $dayInDateRangeArray)) {
								$dayinloopfromfirstdaytoshow = dol_time_plus_duree($daystarttoshow, array_search($idw, $dayInDateRangeArray), 'd'); // $daystarttoshow is a date with hours = 0
								$tmparray['totaltps' . $idw] = (($totalforvisibletasks[$dayinloopfromfirstdaytoshow] != 0) ? convertSecondToTime($totalforvisibletasks[$dayinloopfromfirstdaytoshow], (is_float($totalforvisibletasks[$dayinloopfromfirstdaytoshow] / 60 / 60) ? 'allhourmin' : 'allhour')) : '-');
							} else {
								$tmparray['totaltps' . $idw] = '-';
							}
						}

						//                      $linenumber++;
						//                      $tmparray = $this->get_substitutionarray_lines($line, $outputlangs, $linenumber);
						//                      complete_substitutions_array($tmparray, $outputlangs, $object, $line, "completesubstitutionarray_lines");

						unset($tmparray['object_fields']);
						unset($tmparray['object_lines']);

						// Call the ODTSubstitutionLine hook
						$parameters = array('odfHandler'=>&$odfHandler, 'file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs, 'substitutionarray'=>&$tmparray, 'line'=>$line);
						$reshook = $hookmanager->executeHooks('ODTSubstitutionLine', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
						foreach ($tmparray as $key => $val) {
							try {
								$listlines->setVars($key, $val, true, 'UTF-8');
							} catch (OdfException $e) {
								dol_syslog($e->getMessage(), LOG_INFO);
							} catch (SegmentException $e) {
								dol_syslog($e->getMessage(), LOG_INFO);
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
						dol_syslog($e->getMessage(), LOG_INFO);
					}
					if ($foundtagforlines) {
						$linenumber = 0;

						$workinghours = new Workinghours($this->db);
						$workinghoursArray = $workinghours->fetchCurrentWorkingHours($object->fk_user_assign, 'user');
						$workinghoursMonth = 0;

						for ($idw = 1; $idw <= 31; $idw++) {
							if (in_array($idw, $dayInDateRangeArray)) {
								$dayinloopfromfirstdaytoshow = dol_time_plus_duree($daystarttoshow, array_search($idw, $dayInDateRangeArray), 'd');  // $daystarttoshow is a date with hours = 0
								if ($isavailable[$dayinloopfromfirstdaytoshow]['morning'] && $isavailable[$dayinloopfromfirstdaytoshow]['afternoon']) {
									$currentDay = date('l', $dayinloopfromfirstdaytoshow);
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

						//                      $linenumber++;
						//                      $tmparray = $this->get_substitutionarray_lines($line, $outputlangs, $linenumber);
						//                      complete_substitutions_array($tmparray, $outputlangs, $object, $line, "completesubstitutionarray_lines");

						unset($tmparray['object_fields']);
						unset($tmparray['object_lines']);

						// Call the ODTSubstitutionLine hook
						$parameters = array('odfHandler'=>&$odfHandler, 'file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs, 'substitutionarray'=>&$tmparray, 'line'=>$line);
						$reshook = $hookmanager->executeHooks('ODTSubstitutionLine', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
						foreach ($tmparray as $key => $val) {
							try {
								$listlines->setVars($key, $val, true, 'UTF-8');
							} catch (OdfException $e) {
								dol_syslog($e->getMessage(), LOG_INFO);
							} catch (SegmentException $e) {
								dol_syslog($e->getMessage(), LOG_INFO);
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
						dol_syslog($e->getMessage(), LOG_INFO);
					}
					if ($foundtagforlines) {
						$linenumber = 0;

						$workinghours = new Workinghours($this->db);
						$workinghoursArray = $workinghours->fetchCurrentWorkingHours($object->fk_user_assign, 'user');
						$workinghoursMonth = 0;

						for ($idw = 1; $idw <= 31; $idw++) {
							if (in_array($idw, $dayInDateRangeArray)) {
								$dayinloopfromfirstdaytoshow = dol_time_plus_duree($daystarttoshow, array_search($idw, $dayInDateRangeArray), 'd');  // $daystarttoshow is a date with hours = 0
								if ($isavailable[$dayinloopfromfirstdaytoshow]['morning'] && $isavailable[$dayinloopfromfirstdaytoshow]['afternoon']) {
									$currentDay = date('l', $dayinloopfromfirstdaytoshow);
									$currentDay = 'workinghours_' . strtolower($currentDay);
									$workinghoursMonth = $workinghoursArray->{$currentDay} * 60;
								} else {
									$workinghoursMonth = 0;
								}
								$difftotaltime = $workinghoursMonth - $totalforvisibletasks[$dayinloopfromfirstdaytoshow];
								$tmparray['diff' . $idw] = (($difftotaltime != 0) ? convertSecondToTime(abs($difftotaltime), (is_float($difftotaltime / 60 / 60) ? 'allhourmin' : 'allhour')) : '-');
							} else {
								$tmparray['diff' . $idw] = '-';
							}
						}

						//                      $linenumber++;
						//                      $tmparray = $this->get_substitutionarray_lines($line, $outputlangs, $linenumber);
						//                      complete_substitutions_array($tmparray, $outputlangs, $object, $line, "completesubstitutionarray_lines");

						unset($tmparray['object_fields']);
						unset($tmparray['object_lines']);

						// Call the ODTSubstitutionLine hook
						$parameters = array('odfHandler'=>&$odfHandler, 'file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs, 'substitutionarray'=>&$tmparray, 'line'=>$line);
						$reshook = $hookmanager->executeHooks('ODTSubstitutionLine', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks

						foreach ($tmparray as $key => $val) {
							try {
								$listlines->setVars($key, $val, true, 'UTF-8');
							} catch (OdfException $e) {
								dol_syslog($e->getMessage(), LOG_INFO);
							} catch (SegmentException $e) {
								dol_syslog($e->getMessage(), LOG_INFO);
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
						dol_syslog($e->getMessage(), LOG_INFO);
					}
					if ($foundtagforlines) {
						$linenumber = 0;

						$object->fetchLines();
						if (is_array($object->lines) && !empty($object->lines)) {
							foreach ($object->lines as $line) {
								if ($line->fk_product > 0) {
									$product = new Product($this->db);
									$product->fetch($line->fk_product);
									$tmparray['timesheetdet_label'] = $product->label;
									$tmparray['timesheetdet_qty'] = $line->qty;
								} elseif (!empty($line->description)) {
									$tmparray['timesheetdet_label'] = $line->description;
									$tmparray['timesheetdet_qty'] = $line->qty;
								}
								//$linenumber++;
								//$tmparray = $this->get_substitutionarray_lines($line, $outputlangs, $linenumber);
								//complete_substitutions_array($tmparray, $outputlangs, $object, $line, "completesubstitutionarray_lines");

								unset($tmparray['object_fields']);
								unset($tmparray['object_lines']);

								// Call the ODTSubstitutionLine hook
								$parameters = array('odfHandler' => &$odfHandler, 'file' => $file, 'object' => $object, 'outputlangs' => $outputlangs, 'substitutionarray' => &$tmparray, 'line' => $line);
								$reshook = $hookmanager->executeHooks('ODTSubstitutionLine', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks

								foreach ($tmparray as $key => $val) {
									try {
										$listlines->setVars($key, $val, true, 'UTF-8');
									} catch (OdfException $e) {
										dol_syslog($e->getMessage(), LOG_INFO);
									} catch (SegmentException $e) {
										dol_syslog($e->getMessage(), LOG_INFO);
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
						dol_syslog($e->getMessage(), LOG_INFO);
					}
				}

				// Call the beforeODTSave hook

				$parameters = array('odfHandler'=>&$odfHandler, 'file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs, 'substitutionarray'=>&$tmparray);
				$reshook = $hookmanager->executeHooks('beforeODTSave', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks

				// Write new file
				if (!empty($conf->global->MAIN_ODT_AS_PDF)) {
					try {
						$odfHandler->exportAsAttachedPDF($file);
					} catch (Exception $e) {
						$this->error = $e->getMessage();
						dol_syslog($e->getMessage(), LOG_INFO);
						return -1;
					}
				} else {
					try {
						$odfHandler->saveToDisk($file);
					} catch (Exception $e) {
						$this->error = $e->getMessage();
						dol_syslog($e->getMessage(), LOG_INFO);
						return -1;
					}
				}

				$parameters = array('odfHandler'=>&$odfHandler, 'file'=>$file, 'object'=>$object, 'outputlangs'=>$outputlangs, 'substitutionarray'=>&$tmparray);
				$reshook = $hookmanager->executeHooks('afterODTCreation', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks

				if (!empty($conf->global->MAIN_UMASK)) {
					@chmod($file, octdec($conf->global->MAIN_UMASK));
				}

				$odfHandler = null; // Destroy object

				dol_delete_file($tempdir . "signature.png");
				dol_delete_file($tempdir . "signature1.png");

				$this->result = array('fullpath'=>$file);

				return 1; // Success
			} else {
				$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return -1;
			}
		}

		return -1;
	}
}
