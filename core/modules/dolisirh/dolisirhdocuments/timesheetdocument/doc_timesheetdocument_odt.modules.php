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
 * \file    core/modules/dolisirh/timesheetdocument/doc_timesheetdocument_odt.modules.php
 * \ingroup dolisirh
 * \brief   File of class to build ODT timesheet document.
 */

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';

// Load Saturne libraries.
require_once __DIR__ . '/../../../../../../saturne/class/saturnesignature.class.php';
require_once __DIR__ . '/../../../../../../saturne/core/modules/saturne/modules_saturne.php';

// Load DoliSIRH libraries.
require_once __DIR__ . '/mod_timesheetdocument_standard.php';

/**
 * Class to build documents using ODF templates generator.
 */
class doc_timesheetdocument_odt extends SaturneDocumentModel
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
     * @var string Module.
     */
    public string $module = 'dolisirh';

    /**
     * @var string Document type.
     */
    public string $document_type = 'timesheetdocument';

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        parent::__construct($db, $this->module, $this->document_type);
    }

    /**
     * Return description of a module.
     *
     * @param  Translate $langs Lang object to use for output.
     * @return string           Description.
     */
    public function info(Translate $langs): string
    {
        return parent::info($langs);
    }

    /**
     * Fill all odt tags for segments lines.
     *
     * @param  Odf       $odfHandler  Object builder odf library.
     * @param  Translate $outputLangs Lang object to use for output.
     * @param  array     $moreParam   More param (Object/user/etc).
     *
     * @return int                    1 if OK, <=0 if KO.
     * @throws Exception
     */
    public function fillTagsLines(Odf $odfHandler, Translate $outputLangs, array $moreParam): int
    {
        global $conf, $user;

        $object = $moreParam['object'];

        $task         = new Task($this->db);
        $workingHours = new Workinghours($this->db);

        $workingHours = $workingHours->fetchCurrentWorkingHours($object->fk_user_assign, 'user');

        $dayStartToShow = $object->date_start - 12 * 3600;
        $lastDayToShow  = $object->date_end - 12 * 3600;

        $daysInRange      = dolisirh_num_between_days($object->date_start, $object->date_end, 1);
        $daysInRange      = !empty($daysInRange) ? $daysInRange : 1;
        $daysInRangeArray = [];

        for ($idw = 0; $idw < $daysInRange; $idw++) {
            $dayInLoop          = dol_time_plus_duree($dayStartToShow, $idw, 'd');
            $day                = dol_getdate($dayInLoop);
            $daysInRangeArray[] = $day['mday'];
        }

        $isAvailable = [];
        for ($idw = 0; $idw < $daysInRange; $idw++) {
            $dayInLoop = dol_time_plus_duree($dayStartToShow, $idw, 'd');
            if (isDayAvailable($dayInLoop, $user->id)) {
                $isAvailable[$dayInLoop] = ['morning' => 1, 'afternoon '=> 1];
            } elseif (date('N', $dayInLoop) >= 6) {
                $isAvailable[$dayInLoop] = ['morning' => false, 'afternoon' => false, 'morning_reason' => 'week_end', 'afternoon_reason' => 'week_end'];
            } else {
                $isAvailable[$dayInLoop] = ['morning' => false, 'afternoon' => false, 'morning_reason' => 'public_holiday', 'afternoon_reason' => 'public_holiday'];
            }
        }

        $timeSpentOnTasks = loadTimeSpentOnTasksWithinRange($dayStartToShow, $lastDayToShow + 1, $isAvailable, $object->fk_user_assign);

        // Replace tags of lines.
        try {
            // Get table header days.
            $foundTagForLines = 1;
            try {
                $listLines = $odfHandler->setSegment('days');
            } catch (OdfException $e) {
                // We may arrive here if tags for lines not present into template.
                $foundTagForLines = 0;
                $listLines = '';
                dol_syslog($e->getMessage());
            }

            if ($foundTagForLines) {
                for ($idw = 1; $idw <= 31; $idw++) {
                    if (in_array($idw, $daysInRangeArray)) {
                        $dayInLoop              = dol_time_plus_duree($dayStartToShow, array_search($idw, $daysInRangeArray), 'd');
                        $tmpArray['day' . $idw] = dol_print_date($dayInLoop, '%a');
                    } else {
                        $tmpArray['day' . $idw] = '-';
                    }
                }

                $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                $odfHandler->mergeSegment($listLines);
            }

            // Get all tasks except five HR project task.
            $totalTimeWithoutHrProject = [];
            $foundTagForLines = 1;
            try {
                $listLines = $odfHandler->setSegment('times');
            } catch (OdfException $e) {
                // We may arrive here if tags for lines not present into template.
                $foundTagForLines = 0;
                $listLines = '';
                dol_syslog($e->getMessage());
            }

            if ($foundTagForLines) {
                $filter     = ' AND t.rowid NOT IN (' . $conf->global->DOLISIRH_HOLIDAYS_TASK . ',' . $conf->global->DOLISIRH_PAID_HOLIDAYS_TASK . ',' . $conf->global->DOLISIRH_RTT_TASK . ',' . $conf->global->DOLISIRH_PUBLIC_HOLIDAY_TASK . ',' . $conf->global->DOLISIRH_SICK_LEAVE_TASK . ')';
                $tasksArray = $task->getTasksArray(0, 0, 0, 0, 0, '', '', $filter,  $object->fk_user_assign);
                if (is_array($tasksArray) && !empty($tasksArray)) {
                    foreach ($tasksArray as $taskSingle) {
                        for ($idw = 1; $idw <= 31; $idw++) {
                            $tmpArray['task_ref']   = $taskSingle->ref;
                            $tmpArray['task_label'] = dol_trunc($taskSingle->label, 16);
                            if (in_array($idw, $daysInRangeArray)) {
                                $dayInLoop                              = dol_time_plus_duree($dayStartToShow, $idw - 1, 'd');
                                $tmpArray['time' . $idw]                = (($timeSpentOnTasks[$taskSingle->id][dol_print_date($dayInLoop, 'day')] != 0) ? convertSecondToTime($timeSpentOnTasks[$taskSingle->id][dol_print_date($dayInLoop, 'day')], (is_float($timeSpentOnTasks[$taskSingle->id][dol_print_date($dayInLoop, 'day')]/3600) ? 'allhourmin' : 'allhour')) : '-');
                                $totalTimeWithoutHrProject[$dayInLoop] += $timeSpentOnTasks[$taskSingle->id][dol_print_date($dayInLoop, 'day')];
                            } else {
                                $tmpArray['time' . $idw] = '-';
                            }
                        }
                        if ($conf->global->DOLISIRH_SHOW_TASKS_WITH_TIMESPENT_ON_TIMESHEET && $timeSpentOnTasks[$taskSingle->id] > 0) {
                            $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                        }
                    }
                    $odfHandler->mergeSegment($listLines);
                }
            }

            // Get HR project task.
            $totalTimeHrProject = [];
            $i = 0;
            $segment = [
                ['csss', 'cps', 'rtts', 'jfs', 'cms',], // Row name.
                ['css', 'cp', 'rtt', 'jf', 'cm',] // Cell name.
            ];

            $filter     = ' AND t.rowid IN (' . $conf->global->DOLISIRH_HOLIDAYS_TASK . ',' . $conf->global->DOLISIRH_PAID_HOLIDAYS_TASK . ',' . $conf->global->DOLISIRH_RTT_TASK. ',' . $conf->global->DOLISIRH_PUBLIC_HOLIDAY_TASK . ',' . $conf->global->DOLISIRH_SICK_LEAVE_TASK . ')';
            $tasksArray = $task->getTasksArray(0, 0, $conf->global->DOLISIRH_HR_PROJECT, 0, 0, '', '', $filter,  $object->fk_user_assign);
            if (is_array($tasksArray) && !empty($tasksArray)) {
                foreach ($tasksArray as $taskSingle) {
                    $foundTagForLines = 1;
                    try {
                        $listLines = $odfHandler->setSegment($segment[0][$i]);
                    } catch (OdfException $e) {
                        // We may arrive here if tags for lines not present into template.
                        $foundTagForLines = 0;
                        dol_syslog($e->getMessage());
                    }

                    if ($foundTagForLines) {
                        for ($idw = 1; $idw <= 31; $idw++) {
                            if (in_array($idw, $daysInRangeArray)) {
                                $dayInLoop                        = dol_time_plus_duree($dayStartToShow, $idw - 1, 'd');
                                $tmpArray[$segment[1][$i] . $idw] = (($timeSpentOnTasks[$taskSingle->id][dol_print_date($dayInLoop, 'day')] != 0) ? convertSecondToTime($timeSpentOnTasks[$taskSingle->id][dol_print_date($dayInLoop, 'day')], (is_float($timeSpentOnTasks[$taskSingle->id][dol_print_date($dayInLoop, 'day')]/3600) ? 'allhourmin' : 'allhour')) : '-');
                                $totalTimeHrProject[$dayInLoop]  += $timeSpentOnTasks[$taskSingle->id][dol_print_date($dayInLoop, 'day')];
                            } else {
                                $tmpArray[$segment[1][$i] . $idw] = '-';
                            }
                        }

                        $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                        $odfHandler->mergeSegment($listLines);
                    }
                    $i++;
                }
            }

            // Total time RH.
            $foundTagForLines = 1;
            try {
                $listLines = $odfHandler->setSegment('totalrhs');
            } catch (OdfException $e) {
                // We may arrive here if tags for lines not present into template.
                $foundTagForLines = 0;
                dol_syslog($e->getMessage());
            }

            if ($foundTagForLines) {
                for ($idw = 1; $idw <= 31; $idw++) {
                    if (in_array($idw, $daysInRangeArray)) {
                        $dayInLoop                  = dol_time_plus_duree($dayStartToShow, $idw - 1, 'd');
                        $tmpArray['totalrh' . $idw] = (($totalTimeHrProject[$dayInLoop] != 0) ? convertSecondToTime($totalTimeHrProject[$dayInLoop], (is_float($totalTimeHrProject[$dayInLoop]/3600) ? 'allhourmin' : 'allhour')) : '-');
                    } else {
                        $tmpArray['totalrh' . $idw] = '-';
                    }
                }

                $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                $odfHandler->mergeSegment($listLines);
            }

            // Total time spent without Project RH.
            $foundTagForLines = 1;
            try {
                $listLines = $odfHandler->setSegment('totaltimes');
            } catch (OdfException $e) {
                // We may arrive here if tags for lines not present into template.
                $foundTagForLines = 0;
                dol_syslog($e->getMessage());
            }

            if ($foundTagForLines) {
                for ($idw = 1; $idw <= 31; $idw++) {
                    if (in_array($idw, $daysInRangeArray)) {
                        $dayInLoop                    = dol_time_plus_duree($dayStartToShow, $idw - 1, 'd');
                        $tmpArray['totaltime' . $idw] = (($totalTimeWithoutHrProject[$dayInLoop] != 0) ? convertSecondToTime($totalTimeWithoutHrProject[$dayInLoop], (is_float($totalTimeWithoutHrProject[$dayInLoop]/3600) ? 'allhourmin' : 'allhour')) : '-');
                    } else {
                        $tmpArray['totaltime' . $idw] = '-';
                    }
                }

                $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                $odfHandler->mergeSegment($listLines);
            }

            // Total time spent.
            $totalTimeSpent   = [];
            $foundTagForLines = 1;
            try {
                $listLines = $odfHandler->setSegment('totaltpss');
            } catch (OdfException $e) {
                // We may arrive here if tags for lines not present into template.
                $foundTagForLines = 0;
                dol_syslog($e->getMessage());
            }

            if ($foundTagForLines) {
                for ($idw = 1; $idw <= 31; $idw++) {
                    if (in_array($idw, $daysInRangeArray)) {
                        $dayInLoop                   = dol_time_plus_duree($dayStartToShow, $idw - 1, 'd');
                        $totalTimeSpent[$dayInLoop]  = $totalTimeWithoutHrProject[$dayInLoop] + $totalTimeHrProject[$dayInLoop];
                        $tmpArray['totaltps' . $idw] = (($totalTimeSpent[$dayInLoop] != 0) ? convertSecondToTime($totalTimeSpent[$dayInLoop], (is_float($totalTimeSpent[$dayInLoop]) ? 'allhourmin' : 'allhour')) : '-');
                    } else {
                        $tmpArray['totaltps' . $idw] = '-';
                    }
                }

                $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                $odfHandler->mergeSegment($listLines);
            }

            // Total time planned.
            $totalTimePlanned = [];
            $foundTagForLines = 1;
            try {
                $listLines = $odfHandler->setSegment('tas');
            } catch (OdfException $e) {
                // We may arrive here if tags for lines not present into template.
                $foundTagForLines = 0;
                dol_syslog($e->getMessage());
            }

            if ($foundTagForLines) {
                for ($idw = 1; $idw <= 31; $idw++) {
                    if (in_array($idw, $daysInRangeArray)) {
                        $dayInLoop                    = dol_time_plus_duree($dayStartToShow, $idw - 1, 'd');
                        $totalTimePlanned[$dayInLoop] = loadPassedTimeWithinRange($dayInLoop, dol_time_plus_duree($dayInLoop, 1, 'd'), $workingHours, $isAvailable);
                        $tmpArray['ta' . $idw]        = (($totalTimePlanned[$dayInLoop]['minutes'] != 0) ? convertSecondToTime($totalTimePlanned[$dayInLoop]['minutes'] * 60, (is_float($totalTimePlanned[$dayInLoop]['minutes']/60) ? 'allhourmin' : 'allhour')) : '-');
                    } else {
                        $tmpArray['ta' . $idw] = '-';
                    }
                }

                $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                $odfHandler->mergeSegment($listLines);
            }

            // Diff between time planned and time spent.
            $diffTotalTime    = [];
            $foundTagForLines = 1;
            try {
                $listLines = $odfHandler->setSegment('diffs');
            } catch (OdfException $e) {
                // We may arrive here if tags for lines not present into template.
                $foundTagForLines = 0;
                dol_syslog($e->getMessage());
            }

            if ($foundTagForLines) {
                for ($idw = 1; $idw <= 31; $idw++) {
                    if (in_array($idw, $daysInRangeArray)) {
                        $dayInLoop                 = dol_time_plus_duree($dayStartToShow, $idw - 1, 'd');
                        $diffTotalTime[$dayInLoop] = $totalTimePlanned[$dayInLoop]['minutes'] * 60 - $totalTimeSpent[$dayInLoop];
                        $tmpArray['diff' . $idw]   = (($diffTotalTime[$dayInLoop] != 0) ? convertSecondToTime(abs($diffTotalTime[$dayInLoop]), (is_float($diffTotalTime[$dayInLoop]/3600) ? 'allhourmin' : 'allhour')) : '-');
                    } else {
                        $tmpArray['diff' . $idw] = '-';
                    }
                }

                $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                $odfHandler->mergeSegment($listLines);
            }

            // TimeSheetDet.
            $foundTagForLines = 1;
            try {
                $listLines = $odfHandler->setSegment('timesheetdet');
            } catch (OdfException $e) {
                // We may arrive here if tags for lines not present into template.
                $foundTagForLines = 0;
                dol_syslog($e->getMessage());
            }

            if ($foundTagForLines) {
                $object->fetchLines();
                if (is_array($object->lines) && !empty($object->lines)) {
                    foreach ($object->lines as $line) {
                        if ($line->fk_product > 0) {
                            $product = new Product($this->db);
                            $product->fetch($line->fk_product);
                            $tmpArray['timesheetdet_label'] = $product->label;
                            $tmpArray['timesheetdet_qty']   = $line->qty;
                        } elseif (!empty($line->description)) {
                            $tmpArray['timesheetdet_label'] = $line->description;
                            $tmpArray['timesheetdet_qty']   = $line->qty;
                        } else {
                            $tmpArray['timesheetdet_label'] = '';
                            $tmpArray['timesheetdet_qty']   = '';
                        }

                        $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                    }
                }
                $odfHandler->mergeSegment($listLines);
            }
        } catch (OdfException $e) {
            $this->error = $e->getMessage();
            dol_syslog($this->error, LOG_WARNING);
            return -1;
        }
        return 0;
    }

    /**
     * Function to build a document on disk.
     *
     * @param  SaturneDocuments $objectDocument  Object source to build document.
     * @param  Translate        $outputLangs     Lang object to use for output.
     * @param  string           $srcTemplatePath Full path of source filename for generator using a template file.
     * @param  int              $hideDetails     Do not show line details.
     * @param  int              $hideDesc        Do not show desc.
     * @param  int              $hideRef         Do not show ref.
     * @param  array            $moreParam       More param (Object/user/etc).
     * @return int                               1 if OK, <=0 if KO.
     * @throws Exception
     */
    public function write_file(SaturneDocuments $objectDocument, Translate $outputLangs, string $srcTemplatePath, int $hideDetails = 0, int $hideDesc = 0, int $hideRef = 0, array $moreParam): int
    {
        global $conf, $langs;

        $object = $moreParam['object'];

        $userTmp   = new User($this->db);
        $project   = new Project($this->db);
        $signatory = new SaturneSignature($this->db, 'dolisirh', $object->element);

        $userTmp->fetch($object->fk_user_assign);

        $moreParam['documentName'] = strtoupper($userTmp->lastname) . '_' . ucfirst($userTmp->firstname) . '_';

        $tmpArray['employee_firstname'] = ucfirst($userTmp->firstname);
        $tmpArray['employee_lastname']  = strtoupper($userTmp->lastname);
        $tmpArray['date_start']         = dol_print_date($object->date_start, 'day', 'tzuser');
        $tmpArray['date_end']           = dol_print_date($object->date_end, 'day', 'tzuser');
        $tmpArray['note_public']        = $object->note_public;
        $tmpArray['month_year']         = dol_print_date($object->date_start, '%B %Y', 'tzuser');

        $project->fetch($conf->global->DOLISIRH_HR_PROJECT);

        $tmpArray['project_rh_ref'] = $project->ref;
        $tmpArray['project_rh']     = $project->title;

        $signatoryResponsible = $signatory->fetchSignatory('Responsible', $object->id, $object->element);
        $signatoryAttendant   = $signatory->fetchSignatory('Signatory', $object->id, $object->element);

        // SignatoryResponsible.
        if (is_array($signatoryResponsible) && !empty($signatoryResponsible)) {
            $signatoryResponsible                           = array_shift($signatoryResponsible);
            $tmpArray['society_responsible_fullname']       = strtoupper($signatoryResponsible->lastname) . ' ' . ucfirst($signatoryResponsible->firstname);
            $tmpArray['society_responsible_signature_date'] = dol_print_date($signatoryResponsible->signature_date, 'dayhoursec');
        } else {
            $tmpArray['society_responsible_fullname']       = '';
            $tmpArray['society_responsible_signature_date'] = '';
        }

        if (dol_strlen($signatoryResponsible->signature) > 0 && $signatoryResponsible->signature != $langs->transnoentities('FileGenerated')) {
            if ($moreParam['specimen'] == 0 || ($moreParam['specimen'] == 1 && $conf->global->DOLISIRH_SHOW_SIGNATURE_SPECIMEN == 1)) {
                $tempDir      = $conf->dolisirh->multidir_output[$object->entity ?? 1] . '/temp/';
                $encodedImage = explode(',', $signatoryResponsible->signature)[1];
                $decodedImage = base64_decode($encodedImage);
                file_put_contents($tempDir . 'signature.png', $decodedImage);
                $tmpArray['society_responsible_signature'] = $tempDir . 'signature.png';
            } else {
                $tmpArray['society_responsible_signature'] = '';
            }
        } else {
            $tmpArray['society_responsible_signature'] = '';
        }

        // SignatoryAttendant.
        if (is_array($signatoryAttendant) && !empty($signatoryAttendant)) {
            $signatoryAttendant                           = array_shift($signatoryAttendant);
            $tmpArray['society_attendant_fullname']       = strtoupper($signatoryAttendant->lastname) . ' ' . ucfirst($signatoryAttendant->firstname);
            $tmpArray['society_attendant_signature_date'] = dol_print_date($signatoryAttendant->signature_date, 'dayhoursec');
        } else {
            $tmpArray['society_attendant_fullname']       = '';
            $tmpArray['society_attendant_signature_date'] = '';
        }

        if (dol_strlen($signatoryAttendant->signature) > 0 && $signatoryAttendant->signature != $langs->transnoentities('FileGenerated')) {
            if ($moreParam['specimen'] == 0 || ($moreParam['specimen'] == 1 && $conf->global->DOLISIRH_SHOW_SIGNATURE_SPECIMEN == 1)) {
                $tempDir      = $conf->dolisirh->multidir_output[$object->entity ?? 1] . '/temp/';
                $encodedImage = explode(',', $signatoryAttendant->signature)[1];
                $decodedImage = base64_decode($encodedImage);
                file_put_contents($tempDir . 'signature.png', $decodedImage);
                $tmpArray['society_attendant_signature'] = $tempDir . 'signature.png';
            } else {
                $tmpArray['society_attendant_signature'] = '';
            }
        } else {
            $tmpArray['society_attendant_signature'] = '';
        }

        $moreParam['tmparray'] = $tmpArray;

        return parent::write_file($objectDocument, $outputLangs, $srcTemplatePath, $hideDetails, $hideDesc, $hideRef, $moreParam);
	}
}
