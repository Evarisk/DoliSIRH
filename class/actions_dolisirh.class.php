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
        global $conf, $langs, $user;

        if ($parameters['currentcontext'] == 'invoicecard') {
            if ($action == 'task_create') {
                require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
                require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

                require_once __DIR__ . '/../../saturne/lib/object.lib.php';

                $product   = new Product($this->db);
                $task      = new Task($this->db);
                $project   = new Project($this->db);
                $categorie = new Categorie($this->db);

                $numRefConf = strtoupper($task->element) . '_ADDON';

                $numberingModuleName = [
                    'project/task' => $conf->global->$numRefConf,
                ];
                list($modTask) = saturne_require_objects_mod($numberingModuleName);

                $dateStart       = 0;
                $dateEnd         = 0;
                $plannedWorkload = 0;
                if (is_array($object->lines) && !empty($object->lines)) {
                    foreach ($object->lines as $factureLine) {
                        if ($factureLine->fk_product > 0) {
                            $product->fetch($factureLine->fk_product);

                            $dateStart       = $factureLine->date_start;
                            $dateEnd         = $factureLine->date_end;
                            $quantity        = $factureLine->qty;
                            $durationValue   = $product->duration_value;
                            $durationUnit    = $product->duration_unit;
                            $plannedWorkload = $quantity * dol_time_plus_duree(0, $durationValue, $durationUnit);
                        }
                    }

                    $project->fetch($object->fk_project);

                    $cats      = $categorie->containing($project->id, $project->element);
                    $tagsLabel = '';
                    if (is_array($cats)) {
                        foreach ($cats as $cat) {
                            $tagsLabel .= $cat->label . '-';
                        }
                        $tagsLabel = rtrim($tagsLabel, '-');
                    }

                    $task->ref                              = $modTask->getNextValue(0, $task);
                    $task->label                            = dol_print_date($object->date, 'dayxcard') . '-' . $project->title . '-' . $tagsLabel;
                    $task->fk_project                       = $object->fk_project;
                    $task->date_start                       = $dateStart;
                    $task->date_end                         = $dateEnd;
                    $task->planned_workload                 = $plannedWorkload;
                    $task->array_options['fk_facture_name'] = $object->id;

                    $taskID = $task->create($user);

                    $object->array_options['fk_task'] = $taskID;
                    $object->update($user, false);

                    setEventMessages($langs->trans('TaskCreate') . ' : <a href="' . DOL_URL_ROOT . '/projet/tasks/task.php?id=' . $taskID . '">' . $task->ref . '</a>', []);
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id );
                    exit;
                }
            }
        }

        if ($parameters['currentcontext'] == 'userihm') {
            if ($action == 'update') {
                if (GETPOST('set_timespent_dataset_order') == 'on') {
                    $tabParam['DOLISIRH_TIMESPENT_DATASET_ORDER'] = 1;
                } else {
                    $tabParam['DOLISIRH_TIMESPENT_DATASET_ORDER'] = 0;
                }
                dol_set_user_param($this->db, $conf, $object, $tabParam);
            }
        }

        if ($parameters['currentcontext'] == 'projectcard') {
            if ($action == 'builddoc' && strstr(GETPOST('model'), 'projectdocument_odt')) {
                require_once __DIR__ . '/dolisirhdocuments/projectdocument.class.php';

                $document = new ProjectDocument($this->db);

                $moduleNameLowerCase = 'dolisirh';
                $permissiontoadd     = $user->rights->projet->creer;

                require_once __DIR__ . '/../../saturne/core/tpl/documents/documents_action.tpl.php';
            }

            if ($action == 'pdfGeneration') {
                $moduleName          = 'DoliSIRH';
                $moduleNameLowerCase = strtolower($moduleName);
                $upload_dir          = $conf->dolisirh->multidir_output[$conf->entity ?? 1];

                // Action to generate pdf from odt file
                require_once __DIR__ . '/../../saturne/core/tpl/documents/saturne_manual_pdf_generation_action.tpl.php';

                $urlToRedirect = $_SERVER['REQUEST_URI'];
                $urlToRedirect = preg_replace('/#pdfGeneration$/', '', $urlToRedirect);
                $urlToRedirect = preg_replace('/action=pdfGeneration&?/', '', $urlToRedirect); // To avoid infinite loop

                header('Location: ' . $urlToRedirect);
                exit;
            }
        }

        if (preg_match('/categorycard/', $parameters['context'])) {
            require_once __DIR__ . '/../class/timesheet.class.php';
            require_once __DIR__ . '/../class/certificate.class.php';
            require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
            require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture-rec.class.php';
        }

        return 0; // or return 1 to replace standard code
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

        if ($parameters['currentcontext'] == 'invoicecard') {
            $product = new Product($this->db);
            if (is_array($object->lines) && !empty($object->lines)) {
                foreach ($object->lines as $factureLine) {
                    if ($factureLine->fk_product > 0) {
                        $product->fetch($factureLine->fk_product);

                        $dateStart       = $factureLine->date_start;
                        $dateEnd         = $factureLine->date_end;
                        $quantity        = $factureLine->qty;
                        $durationValue   = $product->duration_value;
                        $durationUnit    = $product->duration_unit;
                        $plannedWorkload = $quantity * dol_time_plus_duree(0, $durationValue, $durationUnit);
                    }
                }
            }

            if (empty($object->array_options['options_fk_task'])) {
                if (isset($object->fk_project) && !empty($dateStart) && !empty($dateEnd) && (isset($plannedWorkload) && $plannedWorkload != 0)) {
                    print '<div class="inline-block divButAction"><a class="butAction" href="' . $_SERVER['REQUEST_URI'] . '&action=task_create">' . $langs->trans('AddTask') . '</a></div>';
                } else {
                    $mesgs  = !isset($object->fk_project) ? $langs->trans('ErrorNoProject') . '<br>' : '';
                    $mesgs .= empty($dateStart) ? $langs->trans('ErrorDateStart') . '<br>' : '';
                    $mesgs .= empty($dateEnd) ? $langs->trans('ErrorDateEnd') . '<br>' : '';
                    $mesgs .= (!isset($plannedWorkload) || $plannedWorkload == 0) ? $langs->trans('ErrorServiceTime') . '<br>' : '';
                    print '<div class="inline-block divButAction"><span class="butActionRefused classfortooltip" title="' . $mesgs . '">' . $langs->trans('AddTask') . '</span></div>';
                }
            }
        }

        return 0; // or return 1 to replace standard code
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
				toggle_task_favorite(GETPOSTINT('id'), $user->id);
			}

			if (is_task_favorite(GETPOSTINT('id'), $user->id)) {
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
     * Overloading the constructCategory function : replacing the parent's function with the one below.
     *
     * @param  array $parameters Hook metadata (context, etc...).
     * @return void
     */
    public function constructCategory(array $parameters)
    {
        if (in_array($parameters['currentcontext'], ['category', 'invoicecard', 'invoicereccard', 'timesheetcard', 'certificatecard', 'invoicelist', 'invoicereclist'])) {
            $tags = [
                'facture' => [
                    'id'        => 436370001,
                    'code'      => 'facture',
                    'obj_class' => 'Facture',
                    'obj_table' => 'facture',
                ],
                'facturerec' => [
                    'id'        => 436370002,
                    'code'      => 'facturerec',
                    'obj_class' => 'FactureRec',
                    'obj_table' => 'facture_rec',
                ],
                'timesheet' => [
                    'id'        => 436370003,
                    'code'      => 'timesheet',
                    'obj_class' => 'TimeSheet',
                    'obj_table' => 'dolisirh_timesheet',
                ],
                'certificate' => [
                    'id'        => 436370004,
                    'code'      => 'certificate',
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
                  print img_picto('', 'category').$form->multiselectarray('categories', $cate_arbo, (GETPOSTISSET('categories') ? GETPOST('categories', 'array') : $arrayselected), '', 0, 'quatrevingtpercent widthcentpercentminusx', 0, 0);
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

		if ($parameters['currentcontext'] == 'timesheetcard' && !preg_match('/signature|odtaspdf/', $parameters['file'])) {
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
            $versionEighteenOrMore = 0;
            if ((float) DOL_VERSION >= 18.0) {
                $versionEighteenOrMore = 1;
            }
            if ($versionEighteenOrMore) {
                $filter = ' AND ptt.element_date BETWEEN ' . "'" .dol_print_date($object->date_start, 'dayrfc') . "'" . ' AND ' . "'" . dol_print_date($object->date_end, 'dayrfc'). "'";
            } else {
                $filter = ' AND ptt.task_date BETWEEN ' . "'" .dol_print_date($object->date_start, 'dayrfc') . "'" . ' AND ' . "'" . dol_print_date($object->date_end, 'dayrfc'). "'";
            }
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
     * Overloading the saturneAdminObjectConst function : replacing the parent's function with the one below.
     *
     * @param  array $parameters Hook metadata (context, etc...)
     * @return int               0 < on error, 0 on success, 1 to replace standard code.
     */
    public function saturneAdminObjectConst(array $parameters): int
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
     * Overloading the saturneAdminDocumentData function : replacing the parent's function with the one below.
     *
     * @param  array $parameters Hook metadata (context, etc...)
     * @return int               0 < on error, 0 on success, 1 to replace standard code.
     */
    public function saturneAdminDocumentData(array $parameters): int
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
     * Overloading the saturneIndex function : replacing the parent's function with the one below.
     *
     * @param  array $parameters Hook metadata (context, etc...)
     * @return void
     */
    public function saturneIndex(array $parameters)
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
