<?php
/* Copyright (C) 2022-2023 EVARISK <technique@evarisk.com>
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
 * \file    core/modules/dolisirh/dolisirhdocuments/projectdocument/doc_projectdocument_odt.modules.php
 * \ingroup dolisirh
 * \brief   File of class to build ODT project document.
 */

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';

require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';

// Load Saturne libraries.
require_once __DIR__ . '/../../../../../../saturne/class/saturnesignature.class.php';
require_once __DIR__ . '/../../../../../../saturne/core/modules/saturne/modules_saturne.php';

// Load DoliSMQ libraries.
require_once __DIR__ . '/mod_projectdocument_standard.php';

/**
 * Class to build documents using ODF templates generator.
 */
class doc_projectdocument_odt extends SaturneDocumentModel
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
    public string $document_type = 'projectdocument';

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
        $object = $moreParam['object'];

        require_once __DIR__ . '/../../../../../../saturne/class/task/saturnetask.class.php';
        $saturneTask = new SaturneTask($this->db);
        $allTasks    = $saturneTask->getTasksArray(null, null, $object->id);

        $contacts         = [];
        $internalContacts = $object->liste_contact(-1, 'internal');
        $externalContacts = $object->liste_contact(-1, 'external');

        if (!empty($externalContacts) && is_array($externalContacts)) {
            $contacts = array_merge($contacts, $externalContacts);
        }

        if (!empty($internalContacts) && is_array($internalContacts)) {
            $contacts = array_merge($contacts, $internalContacts);
        }

        // Replace tags of lines.
        try {
            // Get project users.
            $foundTagForLines = 1;
            try {
                $listLines = $odfHandler->setSegment('projectUsers');
            } catch (OdfException $e) {
                // We may arrive here if tags for lines not present into template.
                $foundTagForLines = 0;
                $listLines = '';
                dol_syslog($e->getMessage());
            }

            if ($foundTagForLines) {
                if (!empty($object)) {
                    if (!empty($contacts)) {
                        foreach ($contacts as $contact) {
                            $tmpArray['project_user_lastname']  = strtoupper($contact['lastname']);
                            $tmpArray['project_user_firstname'] = ucfirst($contact['firstname']);
                            $tmpArray['project_user_role']      = $contact['libelle'];
                            if (is_array($allTasks) && !empty($allTasks)) {
                                $allTimespentUser = 0;
                                foreach ($allTasks as $task) {
                                    $filter         = ' AND ptt.fk_task = ' . $task->id . ' AND ptt.fk_user = ' . $contact['id'];
                                    $timespentsUser = $saturneTask->fetchAllTimeSpentAllUsers($filter);
                                    foreach ($timespentsUser as $timespent) {
                                        $allTimespentUser += $timespent->timespent_duration;
                                    }
                                    $tmpArray['project_user_timespent'] = convertSecondToTime($allTimespentUser, 'allhourmin');
                                }
                            }
                            $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                        }
                    } else {
                        $tmpArray['project_user_lastname']  = '';
                        $tmpArray['project_user_firstname'] = '';
                        $tmpArray['project_user_role']      = '';
                        $tmpArray['project_user_timespent'] = '';
                        $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                    }
                    $odfHandler->mergeSegment($listLines);
                }
            }

            // Get project tasks.
            $foundTagForLines = 1;
            try {
                $listLines = $odfHandler->setSegment('projectTasks');
            } catch (OdfException $e) {
                // We may arrive here if tags for lines not present into template.
                $foundTagForLines = 0;
                $listLines = '';
                dol_syslog($e->getMessage());
            }

            if ($foundTagForLines) {
                if (!empty($object)) {
                    if (is_array($allTasks) && !empty($allTasks)) {
                        foreach ($allTasks as $task) {
                            $tmpArray['project_task_ref']         = $task->ref;
                            $tmpArray['project_task_description'] = $task->description;
                            $tmpArray['project_task_timespent']   = convertSecondToTime($task->duration, 'allhourmin');
                            $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                        }
                    } else {
                        $tmpArray['project_task_ref']         = '';
                        $tmpArray['project_task_description'] = '';
                        $tmpArray['project_task_timespent']   = '';
                        $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                    }
                    $odfHandler->mergeSegment($listLines);
                }
            }

            // Get project task timespents.
            $foundTagForLines = 1;
            try {
                $listLines = $odfHandler->setSegment('projectTaskTimespents');
            } catch (OdfException $e) {
                // We may arrive here if tags for lines not present into template.
                $foundTagForLines = 0;
                $listLines = '';
                dol_syslog($e->getMessage());
            }

            if ($foundTagForLines) {
                if (!empty($object)) {
                    if (!empty($contacts)) {
                        foreach ($contacts as $contact) {
                            if (is_array($allTasks) && !empty($allTasks)) {
                                foreach ($allTasks as $task) {
                                    $filter         = ' AND ptt.fk_task = ' . $task->id . ' AND ptt.fk_user = ' . $contact['id'];
                                    $timespentsUser = $saturneTask->fetchAllTimeSpentAllUsers($filter);
                                    foreach ($timespentsUser as $timespent) {
                                        $tmpArray['project_task_timespent_date']     = dol_print_date($timespent->task_datehour, 'dayhour');
                                        $tmpArray['project_task_timespent_task_ref'] = $task->ref;
                                        $tmpArray['project_task_timespent_user']     = strtoupper($contact['lastname']) . ' ' . ucfirst($contact['firstname']);
                                        $tmpArray['project_task_timespent_note']     = $timespent->note;
                                        $tmpArray['project_task_timespent_duration'] = convertSecondToTime($timespent->timespent_duration, 'allhourmin');
                                        $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                                    }
                                }
                            }
                        }
                    } else {
                        $tmpArray['project_task_timespent_date']     = '';
                        $tmpArray['project_task_timespent_task_ref'] = '';
                        $tmpArray['project_task_timespent_user']     = '';
                        $tmpArray['project_task_timespent_note']     = '';
                        $tmpArray['project_task_timespent_duration'] = '';
                        $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                    }
                    $odfHandler->mergeSegment($listLines);
                }
            }

            // Get project extrafields.
            $foundTagForLines = 1;
            try {
                $listLines = $odfHandler->setSegment('projectExtrafields');
            } catch (OdfException $e) {
                // We may arrive here if tags for lines not present into template.
                $foundTagForLines = 0;
                $listLines = '';
                dol_syslog($e->getMessage());
            }

            if ($foundTagForLines) {
                if (!empty($object)) {
                    $extrafields = new ExtraFields($this->db);
                    $extrafields->fetch_name_optionals_label($object->table_element);
                    if (!empty($object->array_options)) {
                        $extrafieldsArray = [];
                        foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $extrafieldsLabel) {
                            $extrafieldsArray[$extrafieldsLabel] = $object->array_options['options_' . $key];
                        }
                        foreach ($extrafieldsArray as $key => $extrafields) {
                            $tmpArray['project_extrafields_label'] = $key;
                            $tmpArray['project_extrafields_value'] = $extrafields;
                            $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                        }
                    } else {
                        $tmpArray['project_extrafields_label'] = '';
                        $tmpArray['project_extrafields_value'] = '';
                        $this->setTmpArrayVars($tmpArray, $listLines, $outputLangs);
                    }
                    $odfHandler->mergeSegment($listLines);
                }
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
        global $conf;

        $object = $moreParam['object'];

        if (!empty($object->photo)) {
            $path              = $conf->dolisirh->multidir_output[$conf->entity] . '/' . $object->element . '/' . $object->ref . '/photos';
            $fileSmall         = saturne_get_thumb_name($object->photo);
            $image             = $path . '/thumbs/' . $fileSmall;
            $tmpArray['photo'] = $image;
        } else {
            $noPhoto           = '/public/theme/common/nophoto.png';
            $tmpArray['photo'] = DOL_DOCUMENT_ROOT . $noPhoto;
        }

        $tmpArray['project_ref']         = $object->ref;
        $tmpArray['project_label']       = $object->title;
        $tmpArray['project_description'] = $object->description;

        $category   = new Categorie($this->db);
        $categories = $category->containing($object->id, Categorie::TYPE_PROJECT);
        if (!empty($categories)) {
            $allCategories = [];
            foreach ($categories as $cat) {
                $allCategories[] = $cat->label;
            }
            $tmpArray['project_tags'] = implode(', ', $allCategories);
        } else {
            $tmpArray['project_tags'] = '';
        }
        
        $tmpArray['project_start_date'] = dol_print_date($object->date_start, 'day');
        $tmpArray['project_end_date']   = dol_print_date($object->date_end, 'day');

        $task       = new Task($this->db);
        $tasksArray = $task->getTasksArray(null, null, $object->id);

        $totalProgress        = 0;
        $totalPlannedWorkload = 0;
        $totalConsumedTime    = 0;
        $nbTasks              = 0;
        if (is_array($tasksArray) && !empty($tasksArray)) {
            $nbTasks = count($tasksArray);
            foreach ($tasksArray as $task) {
                $totalProgress        += $task->progress;
                $totalPlannedWorkload += $task->planned_workload;
                $totalConsumedTime    += $task->duration;
            }
        }

        $tmpArray['project_progress']         = (($totalProgress) ? price2num($totalProgress / $nbTasks, 2) . ' %' : '0 %');
        $tmpArray['project_status']           = $object->getLibStatut();
        $tmpArray['project_planned_workload'] = convertSecondToTime($totalPlannedWorkload, 'allhourmin');
        $tmpArray['project_timespent']        = convertSecondToTime($totalConsumedTime, 'allhourmin');

        $moreParam['tmparray'] = $tmpArray;
        
        return parent::write_file($objectDocument, $outputLangs, $srcTemplatePath, $hideDetails, $hideDesc, $hideRef, $moreParam);
    }
}