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
 * \file    class/actions_dolisirh.class.php
 * \ingroup dolisirh
 * \brief   DoliSIRH hook overload.
 */

/**
 * Class ActionsDoliSIRH
 */
class ActionsDoliSIRH
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * Constructor
	 *
	 *  @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param  array  $parameters Hook metadata (context, etc...)
	 * @param  object $object     The object to process
	 * @param  string $action     Current action (if set). Generally create or edit or null
	 * @return int                0 < on error, 0 on success, 1 to replace standard code
	 */
	public function doActions(array $parameters, $object, string $action): int
	{
		require_once DOL_DOCUMENT_ROOT.'/core/modules/project/task/mod_task_simple.php';

		global $conf, $db, $langs, $user;

		$error = 0; // Error counter

		if (in_array($parameters['currentcontext'], array('invoicecard'))) {
			// Action that will be done after pressing the button
			if ($action == 'createtask-dolisirh') {
				// Start
				// Variable : ref
				// Description : create the ref of the task
				$mod = new mod_task_simple();
				$ref = $mod->getNextValue(0, '');
				// End

				//Start
				//Variable : label
				//Description : creation of the label of the task

				//Contruction de la chaine de caractère sur le modèle AAAAMMJJ-nomprojet-tag
				//Variable : datef = Date de début de période de facturation
				$query = 'SELECT datef, ref FROM ' .MAIN_DB_PREFIX. 'facture';
				$result = $this->db->query($query);
				while ($row = $result->fetch_array()) {
					if ($row['ref'] == $object->ref) {
						$datef_invoice[0] = $row['datef'];
					}
				}
				$datef_invoice = explode('-', $datef_invoice[0]);
				$datef = implode($datef_invoice);
				//datef

				// Contruction de la chaine de caractère REGEX : AAAAMMJJ-nomprojet-tag
				// Wording retrieval
				$fk_projet_fac = $object->fk_project;
				$query = 'SELECT rowid, title FROM ' .MAIN_DB_PREFIX. 'projet';
				$result = $this->db->query($query);
				while ($row = $result->fetch_array()) {
					if ($row['rowid'] == $object->fk_project) {
						$title[0] = $row['title'];
					}
				}
				$wording = $title[0];

				//Tag retrieval
				//@todo REGEX à construire dans les réglages dans notre cas : DATEDEBUTPERIODE-NOMPROJET-TAGS EX: 20200801-evarisk.fr-ref
				$query = 'SELECT ref, fk_projet FROM ' .MAIN_DB_PREFIX. 'facture';
				$result = $this->db->query($query);
				while ($row = $result->fetch_array()) {
					if ($row['ref'] == $object->ref) {
						$invoice_fk_projet[0] = $row['fk_projet'];
					}
				}
				$query = 'SELECT fk_project, fk_categorie FROM ' .MAIN_DB_PREFIX. 'categorie_project';
				$result = $this->db->query($query);
				while ($row = $result->fetch_array()) {
					if ($row['fk_project'] == $invoice_fk_projet[0]) {
						$fk_categorie[0] = $row['fk_categorie'];
					}
				}
				$query = 'SELECT rowid, label FROM ' .MAIN_DB_PREFIX. 'categorie';
				$result = $this->db->query($query);
				while ($row = $result->fetch_array()) {
					if ($row['rowid'] == $fk_categorie[0]) {
						$tag[0] = $row['label'];
					}
				}
				//Concatenation of the date, wording and tag to obtain the label
				$label = $datef . '-' . $wording . '-' . $tag[0];
				//End

				//Start
				//Variable : fk_projet
				//Description : take the fk_projet from the invoice
				$fk_projet = $object->fk_project;
				//End

				//Start
				//Variable : dateo
				//Decription : retrieval of the start date of the invoice
				$i = 0;
				$query = 'SELECT fk_facture, date_start, date_end FROM ' .MAIN_DB_PREFIX. 'facturedet';
				$result = $this->db->query($query);
				while ($row = $result->fetch_array()) {
					if ($row['fk_facture'] == $object->lines[0]->fk_facture) {
						$date_start[$i] = $row['date_start'];
					}
				}
				$dateo = $date_start[0];
				//End

				//Start
				//Variable : datee
				//Description : retrieval of the end date of the invoice
				$i = 0;
				$query = 'SELECT fk_facture, date_start, date_end FROM ' .MAIN_DB_PREFIX. 'facturedet';
				$result = $this->db->query($query);
				while ($row = $result->fetch_array()) {
					if ($row['fk_facture'] == $object->lines[0]->fk_facture) {
						$date_end[$i] = $row['date_end'];
						$i += 1;
					}
				}
				$datee = $date_end[0];
				//End

				//Start
				//Variable : planned_workload
				//Description : time calculation of the planned workload
				//We recover all the products from the invoice
				$i = 0;
				//We recover the quantity of all the products
				$query = 'SELECT fk_facture, fk_product, qty FROM ' .MAIN_DB_PREFIX. 'facturedet';
				$result = $this->db->query($query);
				while ($row = $result->fetch_array()) {
					if ($row['fk_facture'] == $object->lines[0]->fk_facture) {
						$fk_product[$i] = $row['fk_product'];
						$fk_quantity[$i] = $row['qty'];
						$i += 1;
					}
				}
				$i = 0;
				$j = 0;
				//We recover the time of each product
				$query = 'SELECT rowid, duration FROM ' .MAIN_DB_PREFIX. 'product';
				$result = $this->db->query($query);
				while ($row = $result->fetch_array()) {
					while (isset($fk_product[$i])) {
						if ($row['rowid'] == $fk_product[$i]) {
							$duration[$i] = $row['duration'];
							$i += 1;
						}
						$i += 1;
					}
					$i = 0;
				}
				$i = 0;
				$j = 0;
				// We transform time into seconds
				while (isset($duration[$i])) {
					while (isset($duration[$i][$j])) {
						if ($duration[$i][$j] == 's') {
							$duration[$i] = substr($duration[$i], 0, -1);
							$duration[$i] *= 1;
						} elseif ($duration[$i][$j] == 'i') {
							$duration[$i] = substr($duration[$i], 0, -1);
							$duration[$i] *= 60;
						} elseif ($duration[$i][$j] == 'h') {
							$duration[$i] = substr($duration[$i], 0, -1);
							$duration[$i] *= 3600;
						} elseif ($duration[$i][$j] == 'd') {
							$duration[$i] = substr($duration[$i], 0, -1);
							$duration[$i] *= 86400;
						} elseif ($duration[$i][$j] == 'w') {
							$duration[$i] = substr($duration[$i], 0, -1);
							$duration[$i] *= 604800;
						} elseif ($duration[$i][$j] == 'm') {
							$duration[$i] = substr($duration[$i], 0, -1);
							$duration[$i] *= 2592000;
						} elseif ($duration[$i][$j] == 'y') {
							$duration[$i] = substr($duration[$i], 0, -1);
							$duration[$i] *= 31104000;
						}
						$j += 1;
					}
					$i += 1;
					$j = 0;
				}
				$i = 0;
				//We multiply the time by the duration
				while (isset($duration[$i])) {
					if (is_int($duration[$i])) {
						$duration[$i] *= intval($fk_quantity[$i]);
					}
					$i += 1;
				}
				$i = 0;
				//We add all the time to all the products
				$planned_workload = 0;
				while (isset($duration[$i])) {
					$planned_workload += intval($duration[$i]);
					$i += 1;
				}
				//End

				//Check if the invoice is already linked to the task
				$error_button = 0;
				$query = 'SELECT fk_facture_name FROM ' .MAIN_DB_PREFIX. 'projet_task_extrafields';
				$result = $this->db->query($query);
				while ($row = $result->fetch_array()) {
					if ($row['fk_facture_name'] == $object->id) {
						$error_button = 1;
					}
				}
				//Start
				//Filling of the llx_projet_task table with the variables to create the task
				if ($error_button == 0) {
					if (isset($fk_projet) && $planned_workload != 0 && isset($dateo) && isset($datee)) {
						$req = 'INSERT INTO '.MAIN_DB_PREFIX.'projet_task(ref, fk_projet, label, dateo, datee, planned_workload) VALUES("'.$ref.'", '.intval($fk_projet).', "'.$label.'", "'.$dateo.'", "'.$datee.'", '.intval($planned_workload).')';
						$this->db->query($req);
						$query = 'SELECT rowid, ref, fk_projet FROM ' .MAIN_DB_PREFIX. 'projet_task';
						$result = $this->db->query($query);
						while ($row = $result->fetch_array()) {
							if ($row['rowid']) {
								$rowid_last_task[0] = $row['rowid'];
							}
							if ($row['ref']) {
								$ref_last_task[0] = $row['ref'];
							}
						}
						//Filling of the llx_projet_task_extrafields table
						$req = 'INSERT INTO '.MAIN_DB_PREFIX.'projet_task_extrafields(fk_object, fk_facture_name) VALUES('.$rowid_last_task[0].', '.$object->lines[0]->fk_facture.')';
						$this->db->query($req);
						//Filling of the llx_facture_extrafields table
						$req = 'INSERT INTO '.MAIN_DB_PREFIX.'facture_extrafields(fk_object, fk_task) VALUES('.$object->lines[0]->fk_facture.', '.$rowid_last_task[0].')';
						$this->db->query($req);
						setEventMessages($langs->trans('MessageInfo').' : <a href="'.DOL_URL_ROOT.'/projet/tasks/task.php?id='.$rowid_last_task[0].'">'.$ref.'</a>', null, 'mesgs');
					} else {
						//Error messages
						if (!isset($fk_projet)) {
							setEventMessages($langs->trans('MessageInfoNoCreateProject'), null, 'errors');
						}
						if ($planned_workload == 0) {
							setEventMessages($langs->trans('MessageInfoNoCreateTime'), null, 'errors');
						}
						if (!isset($datee) || !isset($dateo)) {
							setEventMessages($langs->trans('MessageInfoNoCreatedate'), null, 'errors');
						}
					}
				}
				//End
			}
		}

        if ($parameters['currentcontext'] == 'userihm') {
            if ($action == 'update') {
                if (GETPOST('set_timespent_dataset_order') == 'on') {
                    $tabparam['DOLISIRH_TIMESPENT_DATASET_ORDER'] = 1;
                } else {
                    $tabparam['DOLISIRH_TIMESPENT_DATASET_ORDER'] = 0;
                }
                dol_set_user_param($db, $conf, $object, $tabparam);
            }
        }

        if ($parameters['currentcontext'] == 'projectcard') {
            if ($action == 'builddoc' && strstr(GETPOST('model'), 'projectdocument_odt')) {
                require_once __DIR__ . '/dolisirhdocuments/projectdocument.class.php';

                $document = new ProjectDocument($db);

                $moduleNameLowerCase = 'dolisirh';
                $permissiontoadd     = $user->rights->projet->creer;

                require_once __DIR__ . '/../../saturne/core/tpl/documents/documents_action.tpl.php';
            }

            if ($action == 'pdfGeneration') {
                global $conf;

                $moduleName          = 'DoliSIRH';
                $moduleNameLowerCase = strtolower($moduleName);
                $upload_dir          = $conf->dolisirh->multidir_output[$conf->entity ?? 1];

                // Action to generate pdf from odt file
                require_once __DIR__ . '/../../saturne/core/tpl/documents/saturne_manual_pdf_generation_action.tpl.php';

                $urltoredirect = $_SERVER['REQUEST_URI'];
                $urltoredirect = preg_replace('/#pdfGeneration$/', '', $urltoredirect);
                $urltoredirect = preg_replace('/action=pdfGeneration&?/', '', $urltoredirect); // To avoid infinite loop

                header('Location: ' . $urltoredirect );
                exit;
            }
        }

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	/**
	 * Overloading the addMoreActionsButtons function : replacing the parent's function with the one below
	 *
	 * @param  array  $parameters Hook metadata (context, etc...)
	 * @param  object $object     The object to process
	 * @return int                0 < on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreActionsButtons(array $parameters, $object): int
	{
		global $langs, $user;

		$error = 0; // Error counter

		if (in_array('invoicecard', explode(':', $parameters['context']))) {
			//Creation of the link that will be sendid
			if ( isset($_SERVER['HTTPS']) ) {
				if ( $_SERVER['HTTPS'] == 'on' ) {
					$server_protocol = 'https';
				} else {
					$server_protocol = 'http';
				}
			} else {
				$server_protocol = 'http';
			}
			$actual_link = $server_protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			$actual_link .= '&action=createtask-dolisirh'; //Action

			//Check if the invoice is already linked to the task
			$error_button = 0;
			$query = 'SELECT fk_facture_name FROM ' .MAIN_DB_PREFIX. 'projet_task_extrafields';
			$result = $this->db->query($query);
			while ($row = $result->fetch_array()) {
				if ($row['fk_facture_name'] == $object->id) {
					$error_button = 1;
				}
			}
			//Check for grey button
			//Start

			//Check date
			$i = 0;
			$query = 'SELECT fk_facture, date_start, date_end FROM ' .MAIN_DB_PREFIX. 'facturedet';
			$result = $this->db->query($query);
			while ($row = $result->fetch_array()) {
				if ($row['fk_facture'] == $object->lines[0]->fk_facture) {
					$date_end[$i] = $row['date_end'];
					$date_start[$i] = $row['date_start'];
					$i += 1;
				}
			}
			$datee = $date_end[0];
			$dateo = $date_start[0];

			//Check service time
			$i = 0;
			$query = 'SELECT fk_facture, fk_product, qty FROM ' .MAIN_DB_PREFIX. 'facturedet';
			$result = $this->db->query($query);
			while ($row = $result->fetch_array()) {
				if ($row['fk_facture'] == $object->lines[0]->fk_facture) {
					$fk_product[$i] = $row['fk_product'];
					$fk_quantity[$i] = $row['qty'];
					$i += 1;
				}
			}
			$i = 0;
			$j = 0;
			$query = 'SELECT rowid, duration FROM ' .MAIN_DB_PREFIX. 'product';
			$result = $this->db->query($query);
			while ($row = $result->fetch_array()) {
				while (isset($fk_product[$i])) {
					if ($row['rowid'] == $fk_product[$i]) {
						$duration[$i] = $row['duration'];
						$i += 1;
					}
					$i += 1;
				}
				$i = 0;
			}
			$i = 0;
			$j = 0;
			while (isset($duration[$i])) {
				while (isset($duration[$i][$j])) {
					if ($duration[$i][$j] == 's') {
						$duration[$i] = substr($duration[$i], 0, -1);
						$duration[$i] *= 1;
					} elseif ($duration[$i][$j] == 'i') {
						$duration[$i] = substr($duration[$i], 0, -1);
						$duration[$i] *= 60;
					} elseif ($duration[$i][$j] == 'h') {
						$duration[$i] = substr($duration[$i], 0, -1);
						$duration[$i] *= 3600;
					} elseif ($duration[$i][$j] == 'd') {
						$duration[$i] = substr($duration[$i], 0, -1);
						$duration[$i] *= 86400;
					} elseif ($duration[$i][$j] == 'w') {
						$duration[$i] = substr($duration[$i], 0, -1);
						$duration[$i] *= 604800;
					} elseif ($duration[$i][$j] == 'm') {
						$duration[$i] = substr($duration[$i], 0, -1);
						$duration[$i] *= 2592000;
					} elseif ($duration[$i][$j] == 'y') {
						$duration[$i] = substr($duration[$i], 0, -1);
						$duration[$i] *= 31104000;
					}
					$j += 1;
				}
				$i += 1;
				$j = 0;
			}
			$i = 0;
			while (isset($duration[$i])) {
				if (is_int($duration[$i])) {
					$duration[$i] *= intval($fk_quantity[$i]);
				}
				$i += 1;
			}
			$i = 0;
			$planned_workload = 0;
			while (isset($duration[$i])) {
				$planned_workload += intval($duration[$i]);
				$i += 1;
			}
			//End

			//Button
			if ($error_button == 0) {
				if (isset($object->fk_project) && isset($dateo) && isset($datee) && $planned_workload != 0) {
					print '<div class="inline-block divButAction"><a class="butAction" href="'. $actual_link .'">Créer tâche</a></div>';
				} elseif (!isset($object->fk_project)) {
					print '<div class="inline-block divButAction"><a class="butActionRefused classfortooltip" href="#" title="'.$langs->trans('ErrorNoProject').'">Créer tâche</a></div>';
				} elseif (!isset($dateo)) {
					print '<div class="inline-block divButAction"><a class="butActionRefused classfortooltip" href="#" title="'.$langs->trans('ErrorDateStart').'">Créer tâche</a></div>';
				} elseif (!isset($datee)) {
					print '<div class="inline-block divButAction"><a class="butActionRefused classfortooltip" href="#" title="'.$langs->trans('ErrorDateEnd').'">Créer tâche</a></div>';
				} elseif ($planned_workload == 0) {
					print '<div class="inline-block divButAction"><a class="butActionRefused classfortooltip" href="#" title="'.$langs->trans('ErrorServiceTime').'">Créer tâche</a></div>';
				}
			}
		}

        if (preg_match('/categorycard/', $parameters['context'])) {
            $id        = GETPOST('id');
            $elementId = GETPOST('element_id');
            $type      = GETPOST('type');
            if ($id > 0 && $elementId > 0 && ($type == 'timesheet' || $type == 'certificate' || $type == 'facture' || $type == 'facturerec') && ($user->rights->dolisirh->$type->write || $user->rights->facture->creer)) {
                switch ($type) {
                    case 'facture' :
                        require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

                        $newobject = new Facture($this->db);
                        break;
                    case 'facturerec' :
                        require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture-rec.class.php';

                        $newobject = new FactureRec($this->db);
                        break;
                    default :
                        require_once __DIR__ . '/' . $type . '.class.php';

                        $classname = ucfirst($type);
                        $newobject = new $classname($this->db);
                        break;
                }

                $newobject->fetch($elementId);

                if (GETPOST('action') == 'addintocategory') {
                    $result = $object->add_type($newobject, $type);
                    if ($result >= 0) {
                        setEventMessages($langs->trans("WasAddedSuccessfully", $newobject->ref), array());

                    } else {
                        if ($object->error == 'DB_ERROR_RECORD_ALREADY_EXISTS') {
                            setEventMessages($langs->trans("ObjectAlreadyLinkedToCategory"), array(), 'warnings');
                        } else {
                            setEventMessages($object->error, $object->errors, 'errors');
                        }
                    }
                } elseif (GETPOST('action') == 'delintocategory') {
                    $result = $object->del_type($newobject, $type);
                    if ($result < 0) {
                        dol_print_error('', $object->error);
                    }
                    $action = '';
                }
            }
        }

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	/**
	 * Overloading the printCommonFooter function : replacing the parent's function with the one below
	 *
	 * @param  array     $parameters Hook metadata (context, etc...)
	 * @return void
	 * @throws Exception
	 */
	public function printCommonFooter(array $parameters)
	{
		global $conf, $user, $langs, $form;
		$langs->load('projects');
		if (in_array('ticketcard', explode(':', $parameters['context']))) {
			if (GETPOST('action') == 'presend_addmessage') {
				$ticket = new Ticket($this->db);
				$result = $ticket->fetch('', GETPOST('ref', 'alpha'), GETPOST('track_id', 'alpha'));
				dol_syslog(var_export($ticket, true), LOG_DEBUG);
				if ($result > 0 && ($ticket->id) > 0) {
					if ( is_array($ticket->array_options) && array_key_exists('options_fk_task', $ticket->array_options) && $ticket->array_options['options_fk_task']>0) { ?>
						<script>
							let InputTime = document.createElement("input");
							InputTime.id = "timespent";
							InputTime.name = "timespent";
							InputTime.type = "number";
							InputTime.value = <?php echo (!empty($conf->global->DOLISIRH_DEFAUT_TICKET_TIME)?$conf->global->DOLISIRH_DEFAUT_TICKET_TIME:0); ?>;
							let $tr = $('<tr>');
							$tr.append($('<td>').append('<?php echo $langs->trans('DoliSIRHNewTimeSpent');?>'));
							$tr.append($('<td>').append(InputTime));

							let currElement = $("form[name='ticket'] > table tbody");
							currElement.append($tr);
						</script>
					<?php } else {
						setEventMessage($langs->trans('MessageNoTaskLink'), 'warnings');
					}
				} else {
					setEventMessages($ticket->error, $ticket->errors, 'errors');
				}
				dol_htmloutput_events();
			}
            if (((GETPOST('action') != 'edit_extras') || (GETPOST('action') == 'edit_extras') && GETPOST('attribute') != 'fk_task') && GETPOST('action') != 'create') {
				require_once __DIR__ . '/../../../projet/class/task.class.php';

				$task   = new Task($this->db);
				$ticket = new Ticket($this->db);

				$ticket->fetch(!empty(GETPOST('id')) ? (GETPOST('id')) : '', !empty(GETPOST('ref')) ? GETPOST('ref') : '', !empty(GETPOST('track_id')) ? GETPOST('track_id') : '');
				$ticket->fetch_optionals();

				$task_id = $ticket->array_options['options_fk_task'];

				$task->fetch($task_id);

                $out = $task->getNomUrl(1, 'blank', 'task', 1);

				if (!empty($task_id) && $task_id > 0) { ?>
					<script>
						  jQuery('#ticket_extras_fk_task_<?php echo $ticket->id ?>').html(<?php echo json_encode($out) ?>);
					</script>
				<?php }
			}
		}
		if (in_array($parameters['currentcontext'], array('projecttaskcard', 'projecttasktime'))) {
			require_once __DIR__ . '/../lib/dolisirh_function.lib.php';

			if (GETPOST('action') == 'toggleTaskFavorite') {
				toggle_task_favorite(GETPOST('id'), $user->id);
			}

			if (is_task_favorite(GETPOST('id'), $user->id)) {
				$favoriteStar = '<span class="fas fa-star toggleTaskFavorite" onclick="toggleTaskFavorite()"></span>';
			} else {
				$favoriteStar = '<span class="far fa-star toggleTaskFavorite" onclick="toggleTaskFavorite()"></span>';
			}
			?>
			<script>
				function toggleTaskFavorite () {
					let token = $('.fiche').find('input[name="token"]').val();
					$.ajax({
						url: document.URL + '&action=toggleTaskFavorite&token='+token,
						type: "POST",
						processData: false,
						contentType: false,
						success: function() {
							let element = $('.toggleTaskFavorite');
							if (element.hasClass('fas')) {
								element.removeClass('fas')
								element.addClass('far')
							} else if (element.hasClass('far')) {
								element.removeClass('far')
								element.addClass('fas')
							}
						},
						error: function ( resp ) {

						}
					});
				}
				let element = jQuery('.fas.fa-tasks');
				element.closest('.tabBar').find('.marginbottomonly.refid').html(<?php echo json_encode($favoriteStar) ?> + element.closest('.tabBar').find('.marginbottomonly.refid').html());
			</script>
			<?php
		}
		if ($parameters['currentcontext'] == 'projecttaskscard') {
			global $db;

			require_once __DIR__ . '/../lib/dolisirh_function.lib.php';

			$task = new Task($db);

			$tasksarray = $task->getTasksArray(0, 0, GETPOST('id'));
			if (is_array($tasksarray) && !empty($tasksarray)) {
				foreach ($tasksarray as $linked_task) {
					if (is_task_favorite($linked_task->id, $user->id)) {
						$favoriteStar = '<span class="fas fa-star toggleTaskFavorite" id="'. $linked_task->id .'" onclick="toggleTaskFavoriteWithId(this.id)"></span>';
					} else {
						$favoriteStar = '<span class="far fa-star toggleTaskFavorite" id="'. $linked_task->id .'" onclick="toggleTaskFavoriteWithId(this.id)"></span>';
					}
					?>
					<script>
						jQuery('#row-'+<?php echo json_encode($linked_task->id) ?>).find('.nowraponall').first().html(jQuery('#row-'+<?php echo json_encode($linked_task->id) ?>).find('.nowraponall').first().html()  + ' ' + <?php echo json_encode($favoriteStar) ?>  )
					</script>
					<?php
				}
			}
		}
		if ($parameters['currentcontext'] == 'tasklist') {
			global $db;

			require_once __DIR__ . '/../lib/dolisirh_function.lib.php';

			$task = new Task($db);

			$tasksarray = $task->getTasksArray(0, 0, GETPOST('id'));

			if (is_array($tasksarray) && !empty($tasksarray)) {
				foreach ($tasksarray as $linked_task) {
					if (is_task_favorite($linked_task->id, $user->id)) {
						$favoriteStar = '<span class="fas fa-star toggleTaskFavorite" id="'. $linked_task->id .'" onclick="toggleTaskFavoriteWithId(this.id)"></span>';
					} else {
						$favoriteStar = '<span class="far fa-star toggleTaskFavorite" id="'. $linked_task->id .'" onclick="toggleTaskFavoriteWithId(this.id)"></span>';
					}
					?>
					<script>
						if (typeof taskId == null) {
							taskId = <?php echo json_encode($linked_task->id); ?>
						} else {
							taskId = <?php echo json_encode($linked_task->id); ?>
						}
						jQuery("tr[data-rowid="+taskId+"] .nowraponall:not(.tdoverflowmax150)").html(jQuery("tr[data-rowid="+taskId+"] .nowraponall:not(.tdoverflowmax150)").html()  + ' ' + <?php echo json_encode($favoriteStar) ?>  )
					</script>
					<?php
				}
			}
		}

		if ($parameters['currentcontext'] == 'invoicereccard') {
			if (GETPOST('action') == 'create') {
				require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

				$form = new Form($this->db);

				// Categories
                if (!empty($conf->categorie->enabled)) {
					$html = '<td class="valignmiddle">'.$langs->trans('Categories').'</td><td>';
                    $cate_arbo = $form->select_all_categories('facturerec', '', 'parent', 64, 0, 1);
					$html .= img_picto('', 'category') . $form::multiselectarray('categories', $cate_arbo, GETPOST('categories', 'array'), '', 0, 'quatrevingtpercent widthcentpercentminusx');
					$html .= '</td>'; ?>
					<script>
						jQuery('.fiche').find('.tabBar').find('.border tr:last').first().html(<?php echo json_encode($html) ?>);
					</script>
				<?php }
			}
		}

        if ($parameters['currentcontext'] == 'actioncard') { ?>
            <script>
                function getDiffTimestampEvent() {
                    let dayap   = $('#apday').val();
                    let monthap = $('#apmonth').val();
                    let yearap  = $('#apyear').val();
                    let hourap  = $('#aphour').val() > 0 ? $('#aphour').val() : 0;
                    let minap   = $('#apmin').val() > 0 ? $('#apmin').val() : 0;

                    let dayp2   = $('#p2day').val();
                    let monthp2 = $('#p2month').val();
                    let yearp2  = $('#p2year').val();
                    let hourp2  = $('#p2hour').val() > 0 ? $('#p2hour').val() : 0;
                    let minp2   = $('#p2min').val() > 0 ? $('#p2min').val() : 0;

                    if (yearap != '' && monthap != '' && dayap != '' && yearp2 != '' && monthp2 != '' && dayp2 != '') {
                        let dateap = new Date(yearap, monthap - 1, dayap, hourap, minap);
                        let datep2 = new Date(yearp2, monthp2 - 1, dayp2, hourp2, minp2);

                        let difftimestamp = (datep2.getTime() - dateap.getTime()) / 3600000;
                        let difftimestampInMin = ((datep2.getTime() - dateap.getTime()) / 60000)
                        let displaydifftimestamp = '';
                        if ((difftimestampInMin % 60) == 0) {
                            displaydifftimestamp = difftimestamp + ' H';
                        } else {
                            displaydifftimestamp = (difftimestampInMin - (difftimestampInMin % 60)) / 60 + ' H ' + Math.abs((difftimestampInMin % 60)) + ' min';
                        }
                        let color = difftimestamp > 0 ? 'rgb(0,128,0)' : 'rgb(255,0,0)';
                        let element = '<span class="difftimestamp" style="color:' + color + '; font-weight: bold" >' + displaydifftimestamp + '</span>';
                        if ($('.difftimestamp').length > 0) {
                            $('.difftimestamp').remove();
                        }
                        return $('.fulldayendhour').parent().after(element);
                    }
                }
                $('#ap, #aphour, #apmin, #p2, #p2hour, #p2min').change(function () {
                    setTimeout(function () {
                        getDiffTimestampEvent();
                    }, 100);
                });
            </script>
		<?php }

        if ($parameters['currentcontext'] == 'userihm') {
            $pictopath = dol_buildpath('/custom/dolisirh/img/dolisirh_color.png', 1);
			$picto = img_picto('', $pictopath, '', 1, 0, 0, '', 'pictoModule');

            $out = '<tr class="oddeven"><td>' . $picto . $langs->trans('TimeSpentDatasetOrder') . '</td>';
            $out .= '<td>' . $langs->trans('ByProject/Task') .'</td>';
            $out .= '<td class="nowrap"><input class="oddeven" name="set_timespent_dataset_order" type="checkbox"' . (GETPOST('action') == 'edit' ? '' : ' disabled ') . (!empty($user->conf->DOLISIRH_TIMESPENT_DATASET_ORDER) ? ' checked' : '') . '>' . $langs->trans('UsePersonalValue') . '</td>';
            $out .= '<td>' . $langs->trans('ByTask/Project') . '</td></tr>';

            if (GETPOST('action') == 'edit') : ?>
                <script>
                    let currentElement = $('table:nth-child(7) .oddeven:last-child');
                    currentElement.after(<?php echo json_encode($out); ?>);
                </script>
            <?php else : ?>
                <script>
                    let currentElement = $('table:nth-child(1) tr.oddeven:last-child').first();
                    currentElement.after(<?php echo json_encode($out); ?>);
                </script>
            <?php endif;
        }

        if ($parameters['currentcontext'] == 'projectcard') {
            if (GETPOST('action') == 'view' || empty(GETPOST('action'))) {
                global $user;

                print '<link rel="stylesheet" type="text/css" href="../custom/saturne/css/saturne.min.css">';

                $moduleNameLowerCase = 'dolisirh';

                require_once __DIR__ . '/../../saturne/lib/documents.lib.php';

                $object = new Project($this->db);
                $object->fetch(GETPOST('id'), GETPOST('ref','alpha'));

                $upload_dir = $conf->dolisirh->multidir_output[$object->entity ?? 1];
                $objRef     = dol_sanitizeFileName($object->ref);
                $dirFiles   = $object->element . 'document/' . $objRef;
                $fileDir    = $upload_dir . '/' . $dirFiles;
                $urlSource  = $_SERVER['PHP_SELF'] . '?id=' . $object->id;

                $html = saturne_show_documents('dolisirh:ProjectDocument', $dirFiles, $fileDir, $urlSource, $user->rights->projet->creer, $user->rights->projet->supprimer, '', 1, 0, 0, 0, 0, '', 0, '', empty($soc->default_lang) ? '' : $soc->default_lang, $object, 0, 'remove_file', (($object->status > Project::STATUS_DRAFT) ? 1 : 0));
                ?>

                <script src="../custom/saturne/js/saturne.min.js"></script>
                <script>
                    jQuery('.fichehalfleft .div-table-responsive-no-min').append(<?php echo json_encode($html) ; ?>)
                </script>
                <?php
            }
        }

		if (in_array($parameters['currentcontext'], array('timesheetcard')) && GETPOST('action') == 'create') {
			?>
			<script>
				$('.field_fk_soc').find($('.butActionNew')).attr('href', $('.field_fk_soc').find($('.butActionNew')).attr('href').replace('fk_societe', 'fk_soc'))
			</script>
			<?php
		}

		if (preg_match('/categoryindex/', $parameters['context'])) {
			print '<script src="../custom/dolisirh/js/dolisirh.js"></script>';
		} elseif (preg_match('/categorycard/', $parameters['context']) && preg_match('/viewcat.php/', $_SERVER["PHP_SELF"])) {
            require_once __DIR__ . '/../../saturne/lib/object.lib.php';

            $id = GETPOST('id');
            $type = GETPOST('type');

            // Load variable for pagination
            $limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;
            $sortfield = GETPOST('sortfield', 'aZ09comma');
            $sortorder = GETPOST('sortorder', 'aZ09comma');
            $page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
            if (empty($page) || $page == -1) {
                $page = 0;
            }     // If $page is not defined, or '' or -1 or if we click on clear filters or if we select empty mass action
            $offset = $limit * $page;

            if ($type == 'timesheet' || $type == 'certificate' || $type == 'facture' || $type == 'facturerec') {
                switch ($type) {
                    case 'facture' :
                        require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

                        $classname = 'Facture';
                        $object    = new $classname($this->db);

                        $arrayObjects = saturne_fetch_all_object_type($classname);
                        break;
                    case 'facturerec' :
                        require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture-rec.class.php';

                        $classname = 'FactureRec';
                        $object    = new $classname($this->db);

                        $arrayObjects = saturne_fetch_all_object_type($classname);
                        break;
                    default :
                        require_once __DIR__ . '/' . $type . '.class.php';

                        $classname = ucfirst($type);
                        $object    = new $classname($this->db);

                        $arrayObjects = $object->fetchAll();
                        break;
                }

                if (is_array($arrayObjects) && !empty($arrayObjects)) {
                    foreach ($arrayObjects as $objectsingle) {
                        if ($objectsingle->element == 'facturerec') {
                            $array[$objectsingle->id] = $objectsingle->titre;
                        } else {
                            $array[$objectsingle->id] = $objectsingle->ref;
                        }
                    }
                }

                $category = new Categorie($this->db);
                $category->fetch($id);
                $objectsInCateg = $category->getObjectsInCateg($type, 0, $limit, $offset);

                $out = '<br>';

                $out .= '<form method="post" action="' . $_SERVER["PHP_SELF"] . '?id=' . $id . '&type=' . $type . '">';
                $out .= '<input type="hidden" name="token" value="'.newToken().'">';
                $out .= '<input type="hidden" name="action" value="addintocategory">';

                $out .= '<table class="noborder centpercent">';
                $out .= '<tr class="liste_titre"><td>';
                $out .= $langs->trans('AddObjectIntoCategory') . ' ';
                $out .= $form::selectarray('element_id', $array, '', 1);
                $out .= '<input type="submit" class="button buttongen" value="'.$langs->trans("ClassifyInCategory").'"></td>';
                $out .= '</tr>';
                $out .= '</table>';
                $out .= '</form>';

                $out .= '<br>';

                //$param = '&limit=' . $limit . '&id=' . $id . '&type=' . $type;
                //$num = count($objectsInCateg);
                //print_barre_liste($langs->trans(ucfirst($type)), $page, $_SERVER["PHP_SELF"], $param, '', '', '', $num, '', 'object_'.$type.'@dolisirh', 0, '', '', $limit);

                $out .= load_fiche_titre($langs->transnoentities($classname), '', 'object_' . $object->picto);
                $out .= '<table class="noborder centpercent">';
                $out .= '<tr class="liste_titre"><td colspan="3">'.$langs->trans("Ref").'</td></tr>';

                if (is_array($objectsInCateg) && !empty($objectsInCateg)) {
                    // Form to add record into a category
                    if (count($objectsInCateg) > 0) {
                        $i = 0;
                        foreach ($objectsInCateg as $element) {
                            $i++;
                            if ($i > $limit) break;

                            $out .= '<tr class="oddeven">';
                            $out .= '<td class="nowrap" valign="top">';
                            $out .= $element->getNomUrl(1);
                            $out .= '</td>';
                            // Link to delete from category
                            $out .= '<td class="right">';
                            if ($user->rights->categorie->creer) {
                                $out .= '<a href="' . $_SERVER["PHP_SELF"] . '?action=delintocategory&id=' . $id . '&type=' . $type . '&element_id=' . $element->id . '&token=' . newToken() . '">';
                                $out .= $langs->trans("DeleteFromCat");
                                $out .= img_picto($langs->trans("DeleteFromCat"), 'unlink', '', false, 0, 0, '', 'paddingleft');
                                $out .= '</a>';
                            }
                            $out .= '</td>';
                            $out .= '</tr>';
                        }
                    } else {
                        $out .= '<tr class="oddeven"><td colspan="2" class="opacitymedium">'.$langs->trans("ThisCategoryHasNoItems").'</td></tr>';
                    }
                } else {
                    $out .= '<tr class="oddeven"><td colspan="2" class="opacitymedium">'.$langs->trans("ThisCategoryHasNoItems").'</td></tr>';
                }

                $out .= '</table>';
            } ?>

            <script>
                jQuery('.fichecenter').last().after(<?php echo json_encode($out) ; ?>)
            </script>
            <?php
        }

		if (GETPOST('action') == 'toggleTaskFavorite') {
			toggle_task_favorite(GETPOST('taskId'), $user->id);
		}
		?>
		<script>
			function toggleTaskFavoriteWithId (taskId) {
				let token = $('#searchFormList').find('input[name="token"]').val();
				let querySeparator = '?';

				document.URL.match(/\?/) ? querySeparator = '&' : 1

				$.ajax({
					url: document.URL + querySeparator + 'action=toggleTaskFavorite&taskId='+ taskId +'&token='+token,
					type: "POST",
					processData: false,
					contentType: false,
					success: function() {
						let taskContainer = $('#'+taskId)

						if (taskContainer.hasClass('fas')) {
							taskContainer.removeClass('fas')
							taskContainer.addClass('far')
						} else if (taskContainer.hasClass('far')) {
							taskContainer.removeClass('far')
							taskContainer.addClass('fas')
						}
					},
					error: function(resp) {

					}
				});
			}
		</script>
		<?php
	}

    /**
     * Overloading the constructCategory function : replacing the parent's function with the one below
     *
     * @param  array $parameters Hook metadata (context, etc...)
     * @return void
     */
    public function constructCategory(array $parameters)
    {
        if (in_array($parameters['currentcontext'], ['category', 'invoicecard', 'invoicereccard', 'timesheetcard', 'certificatecard', 'invoicelist', 'invoicereclist'])) {
            $tags = [
                'facture' => [
                    'id' => 436370001,
                    'code' => 'facture',
                    'obj_class' => 'Facture',
                    'obj_table' => 'facture',
                ],
                'facturerec' => [
                    'id' => 436370002,
                    'code' => 'facturerec',
                    'obj_class' => 'FactureRec',
                    'obj_table' => 'facture_rec',
                ],
                'timesheet' => [
                    'id' => 436370003,
                    'code' => 'timesheet',
                    'obj_class' => 'TimeSheet',
                    'obj_table' => 'dolisirh_timesheet',
                ],
                'certificate' => [
                    'id' => 436370003,
                    'code' => 'certificate',
                    'obj_class' => 'Certificate',
                    'obj_table' => 'saturne_object_certificate',
                ]
            ];

			$this->results = $tags;
		}
	}

	/**
	 * Overloading the formObjectOptions function : replacing the parent's function with the one below
	 *
	 * @param array  $parameters Hook metadata (context, etc...)
	 * @param object $object     Object
	 * @param string $action     Current action (if set). Generally create or edit or null
	 * @return void
	 */
	public function formObjectOptions(array $parameters, $object, string $action)
	{
		global $conf, $langs;

		if ($parameters['currentcontext'] == 'invoicecard') {
			require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

			$form = new Form($this->db);

			if ($action == 'create') {
				if (!empty($conf->categorie->enabled)) {
					// Categories
					print '<tr><td>' . $langs->trans('Categories') . '</td><td>';
					$cate_arbo = $form->select_all_categories('facture', '', 'parent', 64, 0, 1);
					print img_picto('', 'category') . $form->multiselectarray('categories', $cate_arbo, GETPOST('categories', 'array'), '', 0, 'quatrevingtpercent widthcentpercentminusx', 0, 0);
					print '</td></tr>';
				}
			} elseif ($action == 'edit') {
              // Tags-Categories
              if ($conf->categorie->enabled) {
                  print '<tr><td>'.$langs->trans("Categories").'</td><td>';
                  $cate_arbo = $form->select_all_categories('facture', '', 'parent', 64, 0, 1);
                  $c = new Categorie($this->db);
                  $cats = $c->containing($object->id, 'facture');
                  $arrayselected = array();
                  if (is_array($cats)) {
                      foreach ($cats as $cat) {
                          $arrayselected[] = $cat->id;
                      }
                  }
                  print img_picto('', 'category').$form->multiselectarray('categories', $cate_arbo, $arrayselected, '', 0, 'quatrevingtpercent widthcentpercentminusx', 0, 0);
                  print "</td></tr>";
              }
			} elseif ($action == '') {
				// Categories
				if ($conf->categorie->enabled) {
					print '<tr><td class="valignmiddle">'.$langs->trans('Categories').'</td><td>';
					print $form->showCategories($object->id, 'facture', 1);
					print '</td></tr>';
				}
			}
		}
		if ($parameters['currentcontext'] == 'invoicereccard') {
			require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

			$form = new Form($this->db);

			if ($action == '') {
				// Categories
				if ($conf->categorie->enabled) {
					print '<tr><td class="valignmiddle">'.$langs->trans('Categories').'</td><td>';
					print $form->showCategories($object->id, 'facturerec', 1);
					print '</td></tr>';
				}
			}
		}
	}

	/**
	 * Overloading the afterCreationOfRecurringInvoice function : replacing the parent's function with the one below
	 *
	 * @param array  $parameters Hook metadata (context, etc...)
	 * @param object $object     Object
	 * @return void
	 */
	public function afterCreationOfRecurringInvoice(array $parameters, $object)
	{
		if (in_array($parameters['currentcontext'], array('cron', 'cronjoblist'))) {
			require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
			require_once __DIR__ . '/../lib/dolisirh_function.lib.php';

			$cat = new Categorie($this->db);

			$categories = $cat->containing($parameters['facturerec']->id, 'facturerec');
			if (is_array($categories) && !empty($categories)) {
				foreach ($categories as $category) {
					$categoryArray[] =  $category->id;
				}
				if (!empty($categoryArray)) {
                    $object->setCategoriesCommon($categoryArray, 'facture', false);
				}
			}
		}
	}

	/**
	 * Overloading the printObjectLine function : replacing the parent's function with the one below
	 *
	 * @param array $parameters Hook metadata (context, etc...)
	 * @return void
	 */
	public function printObjectLine(array $parameters)
	{
		if ($parameters['currentcontext'] == 'timesheetcard') {
			if ($parameters['line']->fk_product > 0) {
				require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

				$product = new Product($this->db);

				$product->fetch($parameters['line']->fk_product);
				$parameters['line']->ref           = $product->ref;
				$parameters['line']->label         = $product->label;
				$parameters['line']->product_label = $product->label;
				$parameters['line']->description   = $product->description;
			}
		}
	}

	/**
	 * Overloading the deleteFile function : replacing the parent's function with the one below
	 *
	 * @param  array     $parameters Hook metadata (context, etc...)
	 * @param  object    $object     Object
	 * @return void
	 * @throws Exception
	 */
	public function deleteFile(array $parameters, $object)
	{
		if ($parameters['currentcontext'] == 'timesheetcard' && !preg_match('/signature/', $parameters['file'])) {
			global $user;

			require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';

			$signatory = new SaturneSignature($this->db, 'dolisirh');
			$usertmp   = new User($this->db);
			$task      = new Task($this->db);

			$object->status = $object::STATUS_DRAFT;
			$object->update($user, false);
			$signatories = $signatory->fetchSignatories($object->id, 'timesheet');
			if ( ! empty($signatories) && $signatories > 0) {
				foreach ($signatories as $signatory) {
					$signatory->status = $signatory::STATUS_REGISTERED;
					$signatory->signature = '';
					$signatory->update($user, false);
				}
			}

			$usertmp->fetch($object->fk_user_assign);
			$filter = ' AND ptt.task_date BETWEEN ' . "'" .dol_print_date($object->date_start, 'dayrfc') . "'" . ' AND ' . "'" . dol_print_date($object->date_end, 'dayrfc'). "'";
			$alltimespent = $task->fetchAllTimeSpent($usertmp, $filter);
			foreach ($alltimespent as $timespent) {
				$task->fetchObjectLinked(null, '', $timespent->timespent_id, 'project_task_time');
				if (isset($task->linkedObjects['dolisirh_timesheet'])) {
					$object->element = 'dolisirh_timesheet';
					$object->deleteObjectLinked(null, '', $timespent->timespent_id, 'project_task_time');
				}
			}
		}
	}

	/**
	 * Overloading the printFieldListOption function : replacing the parent's function with the one below
	 *
	 * @param array $parameters Hook metadata (context, etc...)
	 * @return void
	 */
	public function printFieldListOption(array $parameters)
	{
		if ($parameters['currentcontext'] == 'projecttasktime') {
			global $langs;

			$parameters['arrayfields']['fk_timesheet'] = array(
				'label' => $langs->trans('TimeSheet'),
				'checked' => 1,
				'enabled' => 1
			);

			if (!empty($parameters['arrayfields']['fk_timesheet']['checked'])) {
				print '<td class="liste_titre"></td>';
			}
		}
	}

	/**
	 * Overloading the printFieldListTitle function : replacing the parent's function with the one below
	 *
	 * @param array $parameters Hook metadata (context, etc...)
	 * @return void
	 */
	public function printFieldListTitle(array $parameters)
	{
		if ($parameters['currentcontext'] == 'projecttasktime') {
			global $langs;

			$parameters['arrayfields']['fk_timesheet'] = array(
				'label' => $langs->trans('TimeSheet'),
				'checked' => 1,
				'enabled' => 1
			);

			if (!empty($parameters['arrayfields']['fk_timesheet']['checked'])) {
				print_liste_field_titre($parameters['arrayfields']['fk_timesheet']['label'], $_SERVER['PHP_SELF'], 'fk_timesheet', '', $parameters['param'], '', $parameters['sortfield'], $parameters['sortorder'], 'center ');
			}
		}
	}

	/**
	 * Overloading the printFieldListValue function : replacing the parent's function with the one below
	 *
	 * @param array $parameters Hook metadata (context, etc...)
	 * @return void
	 */
	public function printFieldListValue(array $parameters)
	{
		if ($parameters['currentcontext'] == 'projecttasktime') {
			global $langs;

			require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';

			$task = new Task($this->db);

			$parameters['arrayfields']['fk_timesheet'] = array(
				'label' => $langs->trans('TimeSheet'),
				'checked' => 1,
				'enabled' => 1
			);

			if (!empty($parameters['arrayfields']['fk_timesheet']['checked'])) {
				print '<td class="center">';
				$task->fetchObjectLinked(null, '', $parameters['obj']->rowid, 'project_task_time');
				if (isset($task->linkedObjects['dolisirh_timesheet'])) {
					$timesheet = (reset($task->linkedObjects['dolisirh_timesheet']));
					print $timesheet->getNomUrl(1);
				}
				print '</td>';
			}
		}
	}

    /**
     *  Overloading the printFieldListSelect function : replacing the parent's function with the one below
     *
     * @param  array $parameters Hook metadata (context, etc...).
     * @return int               0 < on error, 0 on success, 1 to replace standard code.
     */
    public function printFieldListSelect(array $parameters): int
    {
        global $conf;

        if (in_array($parameters['currentcontext'] , ['invoicelist', 'invoicereclist'])) {
            switch ($parameters['currentcontext']) {
                case 'invoicelist' :
                    $type = 'facture';
                    break;
                case 'invoicereclist' :
                    $type = 'facturerec';
                    break;
                default :
                    $type = '';
                    break;
            }
            if (isModEnabled('categorie') && GETPOSTISSET('search_category_' . $type . '_list')) {
                $conf->global->MAIN_DISABLE_FULL_SCANLIST = 1;
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     *  Overloading the printFieldListFrom function : replacing the parent's function with the one below
     *
     * @param  array               $parameters Hook metadata (context, etc...).
     * @param  CommonObject|string $object     The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...).
     * @return int                             0 < on error, 0 on success, 1 to replace standard code.
     */
    public function printFieldListFrom(array $parameters, $object): int
    {
        if (in_array($parameters['currentcontext'] , ['invoicelist', 'invoicereclist'])) {
            if (isModEnabled('categorie')) {
                require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
                $this->resprints = Categorie::getFilterJoinQuery($object->element, 'f.rowid');
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     *  Overloading the printFieldListWhere function : replacing the parent's function with the one below
     *
     * @param  array $parameters Hook metadata (context, etc...).
     * @return int               0 < on error, 0 on success, 1 to replace standard code.
     */
    public function printFieldListWhere(array $parameters): int
    {
        if (in_array($parameters['currentcontext'], ['invoicelist', 'invoicereclist'])) {
            if (isModEnabled('categorie')) {
                require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
                switch ($parameters['currentcontext']) {
                    case 'invoicelist' :
                        $type = 'facture';
                        break;
                    case 'invoicereclist' :
                        $type = 'facturerec';
                        break;
                    default :
                        $type = '';
                        break;
                }
                $this->resprints = Categorie::getFilterSelectQuery($type, 'f.rowid', GETPOST('search_category_' . $type . '_list', 'array'));
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     *  Overloading the printFieldPreListTitle function : replacing the parent's function with the one below
     *
     * @param  array $parameters Hook metadata (context, etc...).
     * @return int               0 < on error, 0 on success, 1 to replace standard code.
     */
    public function printFieldPreListTitle(array $parameters): int
    {
        global $db, $user;

        if (in_array($parameters['currentcontext'] , ['invoicelist', 'invoicereclist'])) {
            // Filter on categories.
            if (isModEnabled('categorie') && $user->rights->categorie->lire) {
                require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcategory.class.php';
                $formCategory = new FormCategory($db);
                switch ($parameters['currentcontext']) {
                    case 'invoicelist' :
                        $type = 'facture';
                        break;
                    case 'invoicereclist' :
                        $type = 'facturerec';
                        break;
                    default :
                        $type = '';
                        break;
                }
                $this->resprints = $formCategory->getFilterBox($type, GETPOST('search_category_' . $type . '_list', 'array'));
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     *  Overloading the printFieldListSearchParam function : replacing the parent's function with the one below
     *
     * @param  array               $parameters Hook metadata (context, etc...).
     * @param  CommonObject|string $object     The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...).
     * @return int                             0 < on error, 0 on success, 1 to replace standard code.
     */
    public function printFieldListSearchParam(array $parameters, $object): int
    {
        if (in_array($parameters['currentcontext'] , ['invoicelist', 'invoicereclist'])) {
            if (isModEnabled('categorie')) {
                $searchCategoryObjectList = GETPOST('search_category_' . $object->element . '_list', 'array');
                if (!empty($searchCategoryObjectList)) {
                    foreach ($searchCategoryObjectList as $searchCategoryObject) {
                        $this->resprints = '&search_category_' . $object->element . '_list[]=' . urlencode($searchCategoryObject);
                    }
                }
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the getNomUrl function : replacing the parent's function with the one below
     *
     * @param  array        $parameters Hook metadata (context, etc...)
     * @param  CommonObject $object     The object to process
     * @param  string       $action     Current action (if set). Generally create or edit or null
     * @return int                      0 < on error, 0 on success, 1 to replace standard code
     */
    public function getNomUrl(array $parameters, CommonObject $object, string $action): int
    {
        if (in_array('timespentpermonthlist', explode(':', $parameters['context'])) || in_array('timespentperweeklist', explode(':', $parameters['context']))) {
            $doc = new DOMDocument();
            $doc->loadHTML($parameters['getnomurl']);
            $links = $doc->getElementsByTagName('a');
            foreach ($links as $item) {
                if (!$item->hasAttribute('target')) {
                    $item->setAttribute('target','_blank');
                }
            }
            $content = $doc->saveHTML();
            $this->resprints = $content;
        }

        if (!empty($this->resprints)) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * Overloading the SaturneAdminObjectConst function : replacing the parent's function with the one below.
     *
     * @param  array $parameters Hook metadata (context, etc...)
     * @return int               0 < on error, 0 on success, 1 to replace standard code.
     */
    public function SaturneAdminObjectConst(array $parameters): int
    {
        if ($parameters['currentcontext'] == 'timesheetadmin') {
            $constArray['dolisirh'] = [
                'PrefillDate' => [
                    'name'        => 'PrefillDate',
                    'description' => 'PrefillDateDescription',
                    'code'        => 'DOLISIRH_TIMESHEET_PREFILL_DATE',
                ],
                'AddAttendantsConf' => [
                    'name'        => 'AddAttendantsConf',
                    'description' => 'AddAttendantsDescription',
                    'code'        => 'DOLISIRH_TIMESHEET_ADD_ATTENDANTS',
                ],
                'CheckDateEnd' => [
                    'name'        => 'CheckDateEnd',
                    'description' => 'CheckDateEndDescription',
                    'code'        => 'DOLISIRH_TIMESHEET_CHECK_DATE_END',
                ],
                'ShowTasksWithTimespentOnTimeSheet' => [
                    'name'        => 'ShowTasksWithTimespentOnTimeSheet',
                    'description' => 'ShowTasksWithTimespentOnTimeSheetDescription',
                    'code'        => 'DOLISIRH_SHOW_TASKS_WITH_TIMESPENT_ON_TIMESHEET',
                ],
            ];
            $this->results = $constArray;
            return 1;
        }

        return 0; // or return 1 to replace standard code.
    }

    /**
     * Overloading the SaturneAdminDocumentData function : replacing the parent's function with the one below.
     *
     * @param  array $parameters Hook metadata (context, etc...)
     * @return int               0 < on error, 0 on success, 1 to replace standard code.
     */
    public function SaturneAdminDocumentData(array $parameters): int
    {
        if ($parameters['currentcontext'] == 'dolisirhadmindocuments') {
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
            $this->results = $types;
        }

        return 0; // or return 1 to replace standard code.
    }

    /**
     * Overloading the SaturneIndex function : replacing the parent's function with the one below.
     *
     * @param  array $parameters Hook metadata (context, etc...)
     * @return void
     */
    public function SaturneIndex(array $parameters)
    {
        global $conf, $langs;

        if ($parameters['currentcontext'] == 'dolisirhindex') {
            if ($conf->global->DOLISIRH_HR_PROJECT_SET == 0) {
                $out = '<div class="wpeo-notice notice-info">';
                $out .= '<div class="notice-content">';
                $out .= '<div class="notice-title"><strong>' . $langs->trans('SetupDefaultDataNotCreated') . '</strong></div>';
                $out .= '<div class="notice-subtitle"><strong>' . $langs->trans('HowToSetupDefaultData') . ' <a href="admin/setup.php">' . $langs->trans('ConfigDefaultData') . '</a></strong></div>';
                $out .= '</div>';
                $out .= '</div>';

                $this->resprints = $out;
            }
        }
    }
}
