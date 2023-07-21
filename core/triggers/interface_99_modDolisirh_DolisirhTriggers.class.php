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
 * \file    core/triggers/interface_99_modDoliSIRH_DolisirhTriggers.class.php
 * \ingroup dolisirh
 * \brief   DoliSIRH trigger.
 */

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';

// Load DoliSIRH libraries.
require_once __DIR__ . '/../../lib/dolisirh.lib.php';
require_once __DIR__ . '/../../lib/dolisirh_function.lib.php';


/**
 * Class of triggers for DoliSIRH module.
 */
class InterfaceDoliSIRHTriggers extends DolibarrTriggers
{
    /**
     * @var DoliDB Database handler.
     */
    protected $db;

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;

        $this->name        = preg_replace('/^Interface/i', '', get_class($this));
        $this->family      = 'demo';
        $this->description = 'DoliSIRH triggers.';
        $this->version     = '1.3.1';
        $this->picto       = 'dolisirh@dolisirh';
    }

    /**
     * Trigger name.
     *
     * @return string Name of trigger file.
     */
    public function getName(): string
    {
        return parent::getName();
    }

    /**
     * Trigger description.
     *
     * @return string Description of trigger file.
     */
    public function getDesc(): string
    {
        return parent::getDesc();
    }

    /**
     * Function called when a Dolibarr business event is done.
     * All functions "runTrigger" are triggered if file
     * is inside directory core/triggers.
     *
     * @param  string       $action Event action code.
     * @param  CommonObject $object Object.
     * @param  User         $user   Object user.
     * @param  Translate    $langs  Object langs.
     * @param  Conf         $conf   Object conf.
     * @return int                  0 < if KO, 0 if no triggered ran, >0 if OK.
     * @throws Exception
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf): int
    {
        if (!isModEnabled('dolisirh')) {
            return 0; // If module is not enabled, we do nothing.
        }

        // Data and type of action are stored into $object and $action.
        dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . '. id=' . $object->id);

        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
        $now        = dol_now();
        $actioncomm = new ActionComm($this->db);

        $object->fetch($object->id);

        $actioncomm->elementtype = $object->element . '@dolisirh';
        $actioncomm->type_code   = 'AC_OTH_AUTO';
        $actioncomm->datep       = $now;
        $actioncomm->fk_element  = $object->id;
        $actioncomm->userownerid = $user->id;
        $actioncomm->percentage  = -1;

        if (getDolGlobalInt('DOLISIRH_ADVANCED_TRIGGER') && !empty($object->fields)) {
            $actioncomm->note_private = method_exists($object, 'getTriggerDescription') ? $object->getTriggerDescription($object) : '';
        }

        switch ($action) {
            // CREATE
            case 'ACTION_CREATE':
                if (((int) $object->fk_element) > 0 && $object->elementtype == 'ticket' && preg_match('/^TICKET_/', $object->code)) {
                    dol_syslog('Add time spent');
                    $result = 0;
                    $ticket = new Ticket($this->db);
                    $result = $ticket->fetch($object->fk_element);
                    dol_syslog(var_export($ticket, true), LOG_DEBUG);
                    if ($result > 0 && ($ticket->id) > 0) {
                        if (is_array($ticket->array_options) && array_key_exists('options_fk_task', $ticket->array_options) && $ticket->array_options['options_fk_task'] > 0 && !empty(GETPOST('timespent', 'int'))) {
                            require_once DOL_DOCUMENT_ROOT .'/projet/class/task.class.php';
                            $task   = new Task($this->db);
                            $result = $task->fetch($ticket->array_options['options_fk_task']);
                            dol_syslog(var_export($task, true), LOG_DEBUG);
                            if ($result > 0 && ($task->id) > 0) {
                                $task->timespent_note     = $object->note_private;
                                $task->timespent_duration = GETPOST('timespent', 'int') * 60; // We store duration in seconds
                                $task->timespent_date     = dol_now();
                                $task->timespent_withhour = 1;
                                $task->timespent_fk_user  = $user->id;

                                $id_message   = $task->id;
                                $name_message = $task->ref;

                                $task->addTimeSpent($user);
                                setEventMessages($langs->trans('MessageTimeSpentCreate') . ' : ' . '<a href="' . DOL_URL_ROOT . '/projet/tasks/time.php?id=' . $id_message . '">' . $name_message.'</a>', []);
                            } else {
                                setEventMessages($task->error, $task->errors, 'errors');
                                return -1;
                            }
                        }
                    } else {
                        setEventMessages($ticket->error, $ticket->errors, 'errors');
                        return -1;
                    }
                }
                if ($object->element == 'action' && $object->array_options['options_timespent'] == 1 && $object->fk_element > 0 && $object->elementtype == 'task' && !empty($object->datef)) {
                    require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
                    $task   = new Task($this->db);
                    $result = $task->fetch($object->fk_element);
                    if ($result > 0 && $task->id > 0) {
                        $contactsOfTask = $task->getListContactId();
                        if (in_array($user->id, $contactsOfTask)) {
                            $task->timespent_date     = dol_print_date($object->datep,'standard', 'tzuser');
                            $task->timespent_withhour = 1;
                            $task->timespent_note     = $langs->trans('TimeSpentAutoCreate', $object->id) . '<br>' . $object->label . '<br>' . $object->note_private;
                            $task->timespent_duration = $object->datef - $object->datep;
                            $task->timespent_fk_user  = $user->id;

                            $idMessage   = $task->id;
                            $nameMessage = $task->ref;

                            if ($task->timespent_duration > 0) {
                                $result = $task->addTimeSpent($user);
                            } else {
                                $result = -1;
                                $task->error = $langs->trans('ErrorTimeSpentDurationCantBeNegative');
                            }

                            if ($result > 0) {
                                setEventMessages($langs->trans('MessageTimeSpentCreate') . ' : ' . '<a href="' . DOL_URL_ROOT . '/projet/tasks/time.php?id=' . $idMessage . '">' . $nameMessage . '</a>', []);
                            } else {
                                setEventMessages($task->error, $task->errors, 'errors');
                                return -1;
                            }
                        } else {
                            setEventMessages($langs->trans('ErrorUserNotAssignedToTask'), $task->errors, 'errors');
                            return -1;
                        }
                    } else {
                        setEventMessages($task->error, $task->errors, 'errors');
                        return -1;
                    }
                } elseif ($object->element == 'action' && $object->array_options['options_timespent'] == 1 && $object->elementtype != 'task') {
                    setEventMessages('MissingTaskWithTimeSpentOption', $object->errors, 'errors');
                    return -1;
                } elseif ($object->element == 'action' && $object->array_options['options_timespent'] == 1 && empty($object->datef)) {
                    setEventMessages('MissingEndDateWithTimeSpentOption', $object->errors, 'errors');
                    return -1;
                }
                break;

            case 'BILL_CREATE':
                $categories = GETPOST('categories', 'array:int');
                $object->setCategoriesCommon($categories, 'invoice', false);
                break;

            case 'BILLREC_CREATE':
                require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

                $cat = new Categorie($this->db);

                $categories = $cat->containing(GETPOST('facid'), 'invoice');
                if (is_array($categories) && !empty($categories)) {
                    foreach ($categories as $category) {
                        $categoryArray[] =  $category->id;
                    }
                }
                if (!empty($categoryArray)) {
                    $object->setCategoriesCommon($categoryArray, 'invoicerec', false);
                }
                break;

            case 'ECMFILES_CREATE' :
                if ($object->src_object_type == 'dolisirh_timesheet') {
                    require_once __DIR__ . '/../../../saturne/class/saturnesignature.class.php';

                    $signatory = new SaturneSignature($this->db, 'dolisirh');

                    $signatories = $signatory->fetchSignatories($object->src_object_id, 'timesheet');
                    if (!empty($signatories) && $signatories > 0) {
                        foreach ($signatories as $signatory) {
                            $signatory->signature = $langs->transnoentities('FileGenerated');
                            $signatory->update($user, false);
                        }
                    }

                    $actioncomm->code       = 'AC_' . strtoupper($object->element) . '_GENERATE';
                    $actioncomm->label      = $langs->trans('ObjectGenerateTrigger', $langs->transnoentities(ucfirst($object->element)), $object->ref);
                    $actioncomm->fk_element = $object->src_object_id;
                    $actioncomm->create($user);
                }
                break;

            case 'TIMESHEET_CREATE' :
                if (!empty($object->fk_user_assign)) {
                    require_once __DIR__ . '/../../../saturne/class/saturnesignature.class.php';

                    $signatory = new SaturneSignature($this->db, 'dolisirh', $object->element);
                    $usertmp   = new User($this->db);

                    $usertmp->fetch($object->fk_user_assign);
                    $signatory->setSignatory($object->id, $object->element, 'user', [$object->fk_user_assign], 'Signatory');
                    $signatory->setSignatory($object->id, $object->element, 'user', [$usertmp->fk_user], 'Responsible');
                }

                if (getDolGlobalInt('DOLISIRH_PRODUCT_SERVICE_SET')) {
                    require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

                    $product    = new Product($this->db);
                    $objectline = new TimeSheetLine($this->db);

                    $i = 1;

                    $productOrServiceTimesheets = get_product_service_timesheet();

                    foreach ($productOrServiceTimesheets as $productOrServiceTimesheet) {
                        $product->fetch('', dol_sanitizeFileName(dol_string_nospecial(trim($langs->transnoentities($productOrServiceTimesheet['name'])))));
                        $objectline->date_creation  = $object->db->idate($now);
                        $objectline->qty            = 0;
                        $objectline->rang           = $i++;
                        $objectline->fk_timesheet   = $object->id;
                        $objectline->fk_parent_line = 0;
                        $objectline->fk_product     = $product->id;
                        $objectline->product_type   = 0;
                        $objectline->create($user);
                    }
                  }

                  $actioncomm->code  = 'AC_' . strtoupper($object->element) . '_CREATE';
                  $actioncomm->label = $langs->trans('ObjectCreateTrigger', $langs->transnoentities(ucfirst($object->element)));
                  $actioncomm->create($user);
                  break;

            case 'CERTIFICATE_CREATE' :
                if (!empty($object->fk_user_assign)) {
                    require_once __DIR__ . '/../../../saturne/class/saturnesignature.class.php';

                    $signatory = new SaturneSignature($this->db, 'dolisirh');
                    $userTmp   = new User($this->db);

                    $userTmp->fetch($object->fk_user_assign);
                    $signatory->setSignatory($object->id, $object->element, 'user', [$object->fk_user_assign], 'CERTIFICATE_SOCIETY_ATTENDANT');
                    $signatory->setSignatory($object->id, $object->element, 'user', [$userTmp->fk_user], 'CERTIFICATE_SOCIETY_RESPONSIBLE');
                }

                $actioncomm->code  = 'AC_' . strtoupper($object->element) . '_CREATE';
                $actioncomm->label = $langs->trans('ObjectCreateTrigger', $langs->transnoentities(ucfirst($object->element)), $object->ref);
                $actioncomm->create($user);
                break;

            // MODIFY
            case 'TIMESHEET_MODIFY' :
            case 'CERTIFICATE_MODIFY' :
                $actioncomm->code  = 'AC_' . strtoupper($object->element) . '_MODIFY';
                $actioncomm->label = $langs->trans('ObjectModifyTrigger', $langs->transnoentities(ucfirst($object->element)), $object->ref);
                $actioncomm->create($user);
                break;

            // DELETE
            case 'TIMESHEET_DELETE' :
            case 'CERTIFICATE_DELETE' :
                $actioncomm->code  = 'AC_' . strtoupper($object->element) . '_DELETE';
                $actioncomm->label = $langs->trans('ObjectDeleteTrigger', $langs->transnoentities(ucfirst($object->element)), $object->ref);
                $actioncomm->create($user);
                break;

            // VALIDATE
            case 'TIMESHEET_VALIDATE' :
                $actioncomm->code  = 'AC_' . strtoupper($object->element) . '_VALIDATE';
                $actioncomm->label = $langs->trans('ObjectValidateTrigger', $langs->transnoentities(ucfirst($object->element)), $object->ref);
                $actioncomm->create($user);
                break;

            // UNVALIDATE
            case 'TIMESHEET_UNVALIDATE' :
                $actioncomm->code  = 'AC_' . strtoupper($object->element) . '_UNVALIDATE';
                $actioncomm->label = $langs->trans('ObjectUnValidateTrigger', $langs->transnoentities(ucfirst($object->element)), $object->ref);
                $actioncomm->create($user);
                break;

            // LOCK
            case 'TIMESHEET_LOCK' :
                $actioncomm->code  = 'AC_' . strtoupper($object->element) . '_LOCK';
                $actioncomm->label = $langs->trans('ObjectLockedTrigger', $langs->transnoentities(ucfirst($object->element)), $object->ref);
                $actioncomm->create($user);
                break;

            // ARCHIVE
            case 'TIMESHEET_ARCHIVE' :
                $actioncomm->code  = 'AC_' . strtoupper($object->element) . '_ARCHIVE';
                $actioncomm->label = $langs->trans('ObjectArchivedTrigger', $langs->transnoentities(ucfirst($object->element)), $object->ref);
                $actioncomm->create($user);
                break;

            // SENTBYMAIL
            case 'TIMESHEET_SENTBYMAIL' :
                $actioncomm->code  = 'AC_' . strtoupper($object->element) . '_SENTBYMAIL';
                $actioncomm->label = $langs->trans('ObjectSentByMailTrigger', $langs->transnoentities(ucfirst($object->element)), $object->ref);
                $actioncomm->create($user);
                break;

        }
        return 0;
    }
}
