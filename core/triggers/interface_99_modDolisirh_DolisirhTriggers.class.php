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
 * \file    core/triggers/interface_99_modDoliSIRH_DoliSIRHTriggers.class.php
 * \ingroup dolisirh
 * \brief   DoliSIRH trigger.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 *  Class of triggers for DoliSIRH module
 */
class InterfaceDoliSIRHTriggers extends DolibarrTriggers
{
	/**
	 * @var DoliDB Database handler
	 */
	protected $db;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'demo';
		$this->description = 'DoliSIRH triggers.';
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = '1.3.1';
		$this->picto = 'dolisirh@dolisirh';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc(): string
	{
		return $this->description;
	}

	/**
	 * Function called when a Dolibarr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param  string       $action Event action code
	 * @param  CommonObject $object Object
	 * @param  User         $user   Object user
	 * @param  Translate    $langs  Object langs
	 * @param  Conf         $conf   Object conf
	 * @return int                  0 < if KO, 0 if no triggered ran, >0 if OK
	 * @throws Exception
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf): int
	{
		if (empty($conf->dolisirh->enabled)) return 0; // If module is not enabled, we do nothing

		switch ($action) {
			// Actions
			case 'ACTION_CREATE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__. '. id=' .$object->id);
				if (((int) $object->fk_element) > 0 && $object->elementtype == 'ticket' && preg_match('/^TICKET_/', $object->code)) {
					dol_syslog('Add time spent');
					$result= 0;
					$ticket = new Ticket($this->db);
					$result = $ticket->fetch($object->fk_element);
					dol_syslog(var_export($ticket, true), LOG_DEBUG);
					if ($result > 0 && ($ticket->id) > 0) {
						if (is_array($ticket->array_options) && array_key_exists('options_fk_task', $ticket->array_options) && $ticket->array_options['options_fk_task']>0 && !empty(GETPOST('timespent', 'int'))) {
							require_once DOL_DOCUMENT_ROOT .'/projet/class/task.class.php';
							$task = new Task($this->db);
							$result = $task->fetch($ticket->array_options['options_fk_task']);
							dol_syslog(var_export($task, true), LOG_DEBUG);
							if ($result > 0 && ($task->id) > 0) {
								$task->timespent_note = $object->note_private;
								$task->timespent_duration = GETPOST('timespent', 'int') * 60; // We store duration in seconds
								$task->timespent_date = dol_now();
								$task->timespent_withhour = 1;
								$task->timespent_fk_user = $user->id;

								$id_message = $task->id;
								$name_message = $task->ref;

								$task->addTimeSpent($user);
								setEventMessages($langs->trans('MessageTimeSpentCreate').' : '.'<a href="'.DOL_URL_ROOT.'/projet/tasks/time.php?id='.$id_message.'">'.$name_message.'</a>', array());
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
                    $task = new Task($this->db);
                    $result = $task->fetch($object->fk_element);
                    if ($result > 0 && $task->id > 0) {
                        $contactsOfTask = $task->getListContactId();
                        if (in_array($user->id, $contactsOfTask)) {
                            $task->timespent_date = dol_print_date($object->datep,'standard', 'tzuser');
                            $task->timespent_withhour = 1;
                            $task->timespent_note = $langs->trans('TimeSpentAutoCreate', $object->id) . '<br>' . $object->label . '<br>' . $object->note_private;
                            $task->timespent_duration = $object->datef - $object->datep;
                            $task->timespent_fk_user = $user->id;

                            $idMessage = $task->id;
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
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__. '. id=' .$object->id);
				require_once __DIR__ . '/../../lib/dolisirh_function.lib.php';
				$categories = GETPOST('categories', 'array:int');
				setCategoriesObject($categories, 'invoice', false, $object);
				break;

			case 'BILLREC_CREATE':
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__. '. id=' .$object->id);
				require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
				require_once __DIR__ . '/../../lib/dolisirh_function.lib.php';
				$cat = new Categorie($this->db);
				$categories = $cat->containing(GETPOST('facid'), 'invoice');
				if (is_array($categories) && !empty($categories)) {
					foreach ($categories as $category) {
						$categoryArray[] =  $category->id;
					}
				}
				if (!empty($categoryArray)) {
					setCategoriesObject($categoryArray, 'invoicerec', false, $object);
				}
				break;

			// Timesheet
			case 'TIMESHEET_CREATE' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__. '. id=' .$object->id);
				require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

				require_once __DIR__ . '/../../class/timesheet.class.php';

				$signatory  = new SaturneSignature($this->db, 'dolisirh');
				$usertmp    = new User($this->db);
				$product    = new Product($this->db);
				$objectline = new TimeSheetLine($this->db);
				$actioncomm = new ActionComm($this->db);

				if (!empty($object->fk_user_assign)) {
					$usertmp->fetch($object->fk_user_assign);
				}

				$signatory->setSignatory($object->id, 'timesheet', 'user', array($object->fk_user_assign), 'TIMESHEET_SOCIETY_ATTENDANT');
				$signatory->setSignatory($object->id, 'timesheet', 'user', array($usertmp->fk_user), 'TIMESHEET_SOCIETY_RESPONSIBLE');

				$now = dol_now();

				if ($conf->global->DOLISIRH_PRODUCT_SERVICE_SET) {
					$product->fetch('', dol_sanitizeFileName(dol_string_nospecial(trim($langs->transnoentities('MealTicket')))));
					$objectline->date_creation  = $object->db->idate($now);
					$objectline->qty            = 0;
					$objectline->rang           = 1;
					$objectline->fk_timesheet   = $object->id;
					$objectline->fk_parent_line = 0;
					$objectline->fk_product     = $product->id;
					$objectline->product_type   = 0;
					$objectline->insert($user);

					$product->fetch('', dol_sanitizeFileName(dol_string_nospecial(trim($langs->transnoentities('JourneySubscription')))));
					$objectline->date_creation  = $object->db->idate($now);
					$objectline->qty            = 0;
					$objectline->rang           = 2;
					$objectline->fk_timesheet   = $object->id;
					$objectline->fk_parent_line = 0;
					$objectline->fk_product     = $product->id;
					$objectline->product_type   = 1;
					$objectline->insert($user);

					$product->fetch('', dol_sanitizeFileName(dol_string_nospecial(trim($langs->transnoentities('13thMonthBonus')))));
					$objectline->date_creation  = $object->db->idate($now);
					$objectline->qty            = 0;
					$objectline->rang           = 3;
					$objectline->fk_timesheet   = $object->id;
					$objectline->fk_parent_line = 0;
					$objectline->fk_product     = $product->id;
					$objectline->product_type   = 1;
					$objectline->insert($user);

					$product->fetch('', dol_sanitizeFileName(dol_string_nospecial(trim($langs->transnoentities('SpecialBonus')))));
					$objectline->date_creation  = $object->db->idate($now);
					$objectline->qty            = 0;
					$objectline->rang           = 4;
					$objectline->fk_timesheet   = $object->id;
					$objectline->fk_parent_line = 0;
					$objectline->fk_product     = $product->id;
					$objectline->product_type   = 1;
					$objectline->insert($user);
				}

				$actioncomm->elementtype = 'timesheet@dolisirh';
				$actioncomm->code        = 'AC_TIMESHEET_CREATE';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('TimeSheetCreateTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'TIMESHEET_MODIFY' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__. '. id=' .$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'timesheet@dolisirh';
				$actioncomm->code        = 'AC_TIMESHEET_MODIFY';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('TimeSheetModifyTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'TIMESHEET_DELETE' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__. '. id=' .$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'timesheet@dolisirh';
				$actioncomm->code        = 'AC_TIMESHEET_DELETE';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('TimeSheetDeleteTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'TIMESHEET_VALIDATE' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__. '. id=' .$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'timesheet@dolisirh';
				$actioncomm->code        = 'AC_TIMESHEET_VALIDATE';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('TimeSheetValidateTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'TIMESHEET_UNVALIDATE' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__. '. id=' .$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'timesheet@dolisirh';
				$actioncomm->code        = 'AC_TIMESHEET_UNVALIDATE';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('TimeSheetUnValidateTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'TIMESHEET_LOCKED' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__. '. id=' .$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'timesheet@dolisirh';
				$actioncomm->code        = 'AC_TIMESHEET_LOCKED';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('TimeSheetLockedTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'TIMESHEET_ARCHIVED' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__. '. id=' .$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'timesheet@dolisirh';
				$actioncomm->code        = 'AC_TIMESHEET_ARCHIVED';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('TimeSheetArchivedTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			// Certificate
			case 'CERTIFICATE_CREATE' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__. '. id=' .$object->id);
				require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

				require_once __DIR__ . '/../../class/certificate.class.php';

				$signatory  = new CertificateSignature($this->db);
				$usertmp    = new User($this->db);
				$actioncomm = new ActionComm($this->db);

				if (!empty($object->fk_user_assign)) {
					$usertmp->fetch($object->fk_user_assign);
					$signatory->setSignatory($object->id, 'timesheet', 'user', array($object->fk_user_assign), 'CERTIFICATE_SOCIETY_ATTENDANT');
					$signatory->setSignatory($object->id, 'timesheet', 'user', array($usertmp->fk_user), 'CERTIFICATE_SOCIETY_RESPONSIBLE');
				}

				$now = dol_now();

				$actioncomm->elementtype = 'certificate@dolisirh';
				$actioncomm->code        = 'AC_CERTIFICATE_CREATE';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('CertificateCreateTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'CERTIFICATE_MODIFY' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__. '. id=' .$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'certificate@dolisirh';
				$actioncomm->code        = 'AC_CERTIFICATE_MODIFY';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('CertificateModifyTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'CERTIFICATE_DELETE' :
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__. '. id=' .$object->id);
				require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = 'certificate@dolisirh';
				$actioncomm->code        = 'AC_CERTIFICATE_DELETE';
				$actioncomm->type_code   = 'AC_OTH_AUTO';
				$actioncomm->label       = $langs->trans('CertificateDeleteTrigger');
				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->id;
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'ECMFILES_CREATE' :
				if ($object->src_object_type == 'dolisirh_timesheet') {
					dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . '. id=' . $object->id);
					require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

					require_once __DIR__ . '/../../class/timesheet.class.php';

					$now        = dol_now();
					$signatory  = new SaturneSignature($this->db, 'dolisirh');
					$actioncomm = new ActionComm($this->db);

					$signatories = $signatory->fetchSignatories($object->src_object_id, 'timesheet');

					if (!empty($signatories) && $signatories > 0) {
						foreach ($signatories as $signatory) {
							$signatory->signature = $langs->transnoentities('FileGenerated');
							$signatory->update($user, false);
						}
					}

					$actioncomm->elementtype = 'timesheet@dolisirh';
					$actioncomm->code        = 'AC_TIMESHEET_GENERATE';
					$actioncomm->type_code   = 'AC_OTH_AUTO';
					$actioncomm->label       = $langs->trans('TimeSheetGenerateTrigger');
					$actioncomm->datep       = $now;
					$actioncomm->fk_element  = $object->src_object_id;
					$actioncomm->userownerid = $user->id;
					$actioncomm->percentage  = -1;

					$actioncomm->create($user);
				}
				break;

			case 'DOLISIRHSIGNATURE_ADDATTENDANT' :
				dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . '. id=' . $object->id);
				require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
				$now        = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype       = $object->object_type . '@dolisirh';
				$actioncomm->code              = 'AC_DOLISIRHSIGNATURE_ADDATTENDANT';
				$actioncomm->type_code         = 'AC_OTH_AUTO';
				$actioncomm->label             = $langs->transnoentities('DoliSIRHAddAttendantTrigger', $object->firstname . ' ' . $object->lastname);
				if ($object->element_type == 'socpeople') {
					$actioncomm->socpeopleassigned = array($object->element_id => $object->element_id);
				}
				$actioncomm->datep             = $now;
				$actioncomm->fk_element        = $object->fk_object;
				$actioncomm->userownerid       = $user->id;
				$actioncomm->percentage        = -1;

				$actioncomm->create($user);
				break;

			case 'DOLISIRHSIGNATURE_SIGNED' :
				dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . '. id=' . $object->id);
				require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
				$now = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype = $object->object_type . '@dolisirh';
				$actioncomm->code        = 'AC_DOLISIRHSIGNATURE_SIGNED';
				$actioncomm->type_code   = 'AC_OTH_AUTO';

				$actioncomm->label = $langs->transnoentities('DoliSIRHSignatureSignedTrigger') . ' : ' . $object->firstname . ' ' . $object->lastname;

				$actioncomm->datep       = $now;
				$actioncomm->fk_element  = $object->fk_object;
				if ($object->element_type == 'socpeople') {
					$actioncomm->socpeopleassigned = array($object->element_id => $object->element_id);
				}
				$actioncomm->userownerid = $user->id;
				$actioncomm->percentage  = -1;

				$actioncomm->create($user);
				break;

			case 'DOLISIRHSIGNATURE_PENDING_SIGNATURE' :

				dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . '. id=' . $object->id);
				require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
				$now        = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype       = $object->object_type . '@dolisirh';
				$actioncomm->code              = 'AC_DOLISIRHSIGNATURE_PENDING_SIGNATURE';
				$actioncomm->type_code         = 'AC_OTH_AUTO';
				$actioncomm->label             = $langs->transnoentities('DoliSIRHSignaturePendingSignatureTrigger') . ' : ' . $object->firstname . ' ' . $object->lastname;
				$actioncomm->datep             = $now;
				$actioncomm->fk_element        = $object->fk_object;
				if ($object->element_type == 'socpeople') {
					$actioncomm->socpeopleassigned = array($object->element_id => $object->element_id);
				}
				$actioncomm->userownerid       = $user->id;
				$actioncomm->percentage        = -1;

				$actioncomm->create($user);
				break;

			case 'DOLISIRHSIGNATURE_ABSENT' :

				dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . '. id=' . $object->id);
				require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
				$now        = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype       = $object->object_type . '@dolisirh';
				$actioncomm->code              = 'AC_DOLISIRHSIGNATURE_ABSENT';
				$actioncomm->type_code         = 'AC_OTH_AUTO';
				$actioncomm->label             = $langs->transnoentities('DoliSIRHSignatureAbsentTrigger') . ' : ' . $object->firstname . ' ' . $object->lastname;
				$actioncomm->datep             = $now;
				$actioncomm->fk_element        = $object->fk_object;
				if ($object->element_type == 'socpeople') {
					$actioncomm->socpeopleassigned = array($object->element_id => $object->element_id);
				}
				$actioncomm->userownerid       = $user->id;
				$actioncomm->percentage        = -1;

				$actioncomm->create($user);
				break;

			case 'DOLISIRHSIGNATURE_DELETED' :

				dol_syslog("Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . '. id=' . $object->id);
				require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
				$now        = dol_now();
				$actioncomm = new ActionComm($this->db);

				$actioncomm->elementtype       = $object->object_type . '@dolisirh';
				$actioncomm->code              = 'AC_DOLISIRHSIGNATURE_DELETED';
				$actioncomm->type_code         = 'AC_OTH_AUTO';
				$actioncomm->label             = $langs->transnoentities('DoliSIRHSignatureDeletedTrigger') . ' : ' . $object->firstname . ' ' . $object->lastname;
				$actioncomm->datep             = $now;
				$actioncomm->fk_element        = $object->fk_object;
				if ($object->element_type == 'socpeople') {
					$actioncomm->socpeopleassigned = array($object->element_id => $object->element_id);
				}
				$actioncomm->userownerid       = $user->id;
				$actioncomm->percentage        = -1;

				$actioncomm->create($user);
				break;

            default:
                dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__. '. id=' .$object->id);
                break;
		}
		return 0;
	}
}
