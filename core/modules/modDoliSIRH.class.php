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
 */

/**
 * 	\defgroup   dolisirh     Module DoliSIRH
 *  \brief      DoliSIRH module descriptor.
 *
 *  \file       core/modules/modDoliSIRH.class.php
 *  \ingroup    dolisirh
 *  \brief      Description and activation file for module DoliSIRH
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module DoliSIRH
 */
class modDoliSIRH extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;
		$this->db = $db;

        $langs->load("dolisirh@dolisirh");

		$this->numero          = 436310;
		$this->rights_class    = 'dolisirh';
		$this->family          = "";
		$this->module_position = '';
		$this->familyinfo      = array('Evarisk' => array('position' => '01', 'label' => $langs->trans("Evarisk")));
		$this->name            = preg_replace('/^mod/i', '', get_class($this));
		$this->description 	   = $langs->trans('DoliSIRHDescription');
		$this->descriptionlong = $langs->trans('DoliSIRHDescriptionLong');
		$this->editor_name     = 'Evarisk';
		$this->editor_url      = 'https://evarisk.com';
		$this->version         = '1.2.0';
		$this->const_name      = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto           = 'dolisirh_red@dolisirh';

		$this->module_parts 			= array(
			'triggers' 					=> 1,
			'login' 					=> 0,
			'substitutions' 			=> 0,
			'menus' 					=> 0,
			'tpl' 						=> 0,
			'barcode' 					=> 0,
			'models' 					=> 1,
			'theme' 					=> 0,
			'css' 						=> array("/dolisirh/css/dolisirh_all.css"),
			'js' 						=> array(),
			'hooks' 					=> array(
				  'data' 				=> array(
					  'invoicecard',
					  'ticketcard',
					  'projecttaskcard',
					  'projecttaskscard',
					  'tasklist',
					  'category',
					  'categoryindex',
					  'invoicereccard',
					  'cron',
					  'cronjoblist',
					  'projecttasktime',
					  'timesheetcard',
                      'actioncard',
                      'userihm'
				  ),
			),
			'moduleforexternal' => 0,
		);

		$this->dirs = array(
			"/dolisirh/temp",
			"/ecm/dolisirh/timesheetdocument",
			"/ecm/dolisirh/certificatedocument"
		);

		// Config pages
		$this->config_page_url = array("setup.php@dolisirh");

		// Dependencies
		$this->hidden 					= false;
		$this->depends 					= array('modProjet', 'modBookmark', 'modHoliday', 'modFckeditor', 'modSalaries', 'modProduct', 'modService', 'modSociete', 'modECM', 'modCategorie');
		$this->requiredby 				= array(); // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->conflictwith 			= array(); // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...)
		$this->langfiles 				= array("dolisirh@dolisirh");
		$this->phpmin 					= array(7, 0); // Minimum version of PHP required by module
		$this->need_dolibarr_version 	= array(15, 0); // Minimum version of Dolibarr required by module
		$this->warnings_activation 		= array(); // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','ES'='textes'...)
		$this->warnings_activation_ext 	= array(); // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','ES'='textes'...)

		// Constants
        $i = 0;
		$this->const = array(
			// CONST CONFIGURATION
			$i++ => array('DOLISIRH_DEFAUT_TICKET_TIME', 'chaine', '15', 'Default Time', 0, 'current'),
            $i++ => array('DOLISIRH_HR_PROJECT', 'integer', 0, '', 0, 'current'),
            $i++ => array('DOLISIRH_TIMESPENT_BOOKMARK_SET', 'integer', 0, '', 0, 'current'),
            $i++ => array('DOLISIRH_EXCEEDED_TIME_SPENT_COLOR', 'chaine', '#FF0000', '', 0, 'current'),
            $i++ => array('DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR', 'chaine', '#FFA500', '', 0, 'current'),
            $i++ => array('DOLISIRH_PERFECT_TIME_SPENT_COLOR', 'chaine', '#008000', '', 0, 'current'),
            $i++ => array('DOLISIRH_PRODUCT_SERVICE_SET', 'integer', 0, '', 0, 'current'),
            $i++ => array('DOLISIRH_HR_PROJECT_SET', 'integer', 0, '', 0, 'current'),

            // CONST TIME SPENT
            $i++ => array('DOLISIRH_SHOW_ONLY_TASKS_WITH_TIMESPENT_ON_TIMESHEET', 'integer', 0, '', 0, 'current'),

            // CONST TIME SHEET
            $i++ => array('DOLISIRH_TIMESHEET_ADDON', 'chaine', 'mod_timesheet_standard', '', 0, 'current'),
            $i++ => array('DOLISIRH_TIMESHEET_PREFILL_DATE', 'integer', 1, '', 0, 'current'),
            $i++ => array('DOLISIRH_TIMESHEET_ADD_ATTENDANTS', 'integer', 0, '', 0, 'current'),
            $i++ => array('DOLISIRH_TIMESHEET_CHECK_DATE_END', 'integer', 1, '', 0, 'current'),

			// CONST TIMESHEET DOCUMENT
            $i++ => array('DOLISIRH_TIMESHEETDOCUMENT_ADDON', 'chaine', 'mod_timesheetdocument_standard', '', 0, 'current'),
            $i++ => array('DOLISIRH_TIMESHEETDOCUMENT_ADDON_ODT_PATH', 'chaine', 'DOL_DOCUMENT_ROOT/custom/dolisirh/documents/doctemplates/timesheetdocument/', '', 0, 'current'),
            $i++ => array('DOLISIRH_TIMESHEETDOCUMENT_CUSTOM_ADDON_ODT_PATH', 'chaine', 'DOL_DATA_ROOT' . (($conf->entity == 1 ) ? '/' : '/' . $conf->entity . '/') . 'ecm/dolisirh/timesheetdocument/', '', 0, 'current'),
			$i++ => array('DOLISIRH_TIMESHEETDOCUMENT_DEFAULT_MODEL', 'chaine', 'timesheetdocument_odt', '', 0, 'current'),

			// CONST CERTIFICATE
            $i++ => array('DOLISIRH_CERTIFICATE_ADDON', 'chaine', 'mod_certificate_standard', '', 0, 'current'),

			// CONST CERTIFICATE DOCUMENT
            $i++ => array('DOLISIRH_CERTIFICATEDOCUMENT_ADDON', 'chaine', 'mod_certificatedocument_standard', '', 0, 'current'),
            $i++ => array('DOLISIRH_CERTIFICATEDOCUMENT_ADDON_ODT_PATH', 'chaine', 'DOL_DOCUMENT_ROOT/custom/dolisirh/documents/doctemplates/certificatedocument/', '', 0, 'current'),
            $i++ => array('DOLISIRH_CERTIFICATEDOCUMENT_CUSTOM_ADDON_ODT_PATH', 'chaine', 'DOL_DATA_ROOT' . (($conf->entity == 1 ) ? '/' : '/' . $conf->entity . '/') . 'ecm/dolisirh/certificatedocument/', '', 0, 'current'),
            $i   => array('DOLISIRH_CERTIFICATEDOCUMENT_DEFAULT_MODEL', 'chaine', 'certificatedocument_odt', '', 0, 'current'),
		);

		if (!isset($conf->dolisirh) || !isset($conf->dolisirh->enabled)) {
			$conf->dolisirh = new stdClass();
			$conf->dolisirh->enabled = 0;
		}

		$this->tabs    = array();
        $pictopath     = dol_buildpath('/custom/dolisirh/img/dolisirh_red.png', 1);
        $pictoDoliSIRH = img_picto('', $pictopath, '', 1, 0, 0, '', 'pictoDoliSIRH');
		$this->tabs[]  = array('data' => 'user:+workinghours:' . $pictoDoliSIRH . $langs->trans('WorkingHours') . ':dolisirh@dolisirh:$user->rights->user->self->creer:/custom/dolisirh/view/workinghours_card.php?id=__ID__'); // To add a new tab identified by code tabname1

		$this->dictionaries = array();
		$this->boxes        = array();
		$this->cronjobs     = array();

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;

		/* DOLISIRH PERMISSIONS */
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = $langs->trans('LireDoliSIRH');
		$this->rights[$r][4] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = $langs->trans('ReadDoliSIRH');
		$this->rights[$r][4] = 'lire';
		$r++;

		/* TIMESHEET PERMISSIONS */
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = $langs->trans('ReadTimeSheet');
		$this->rights[$r][4] = 'timesheet';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = $langs->transnoentities('CreateTimeSheet');
		$this->rights[$r][4] = 'timesheet';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = $langs->trans('DeleteTimeSheet');
		$this->rights[$r][4] = 'timesheet';
		$this->rights[$r][5] = 'delete';
		$r++;

		/* CERTIFICATE PERMISSIONS */
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = $langs->trans('ReadCertificate');
		$this->rights[$r][4] = 'certificate';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = $langs->transnoentities('CreateCertificate');
		$this->rights[$r][4] = 'certificate';
		$this->rights[$r][5] = 'write';
		$r++;
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = $langs->trans('DeleteCertificate');
		$this->rights[$r][4] = 'certificate';
		$this->rights[$r][5] = 'delete';
		$r++;

		/* ADMINPAGE PANEL ACCESS PERMISSIONS */
		$this->rights[$r][0] = $this->numero . sprintf("%02d", $r + 1);
		$this->rights[$r][1] = $langs->transnoentities('ReadAdminPage');
		$this->rights[$r][4] = 'adminpage';
		$this->rights[$r][5] = 'read';

		// Main menu entries to add
		$this->menu = array();
		$r          = 0;

		// Add here entries to declare new menus
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=project,fk_leftmenu=timespent',                      // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'     => 'left',                                                           // This is a Top menu entry
			'titre'    => $langs->transnoentities('DoliSIRHTimeSpent'),
			'prefix'   => $pictoDoliSIRH,
			'mainmenu' => 'project',
			'leftmenu' => 'dolisirh_timespent_list',
			'url'      => '/dolisirh/view/timespent_list.php',
			'langs'    => 'dolisirh@dolisirh',                                              // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => '$conf->dolisirh->enabled && $conf->projet->enabled',             // Define condition to show or hide menu entry. Use '$conf->dolisirh->enabled' if entry must be visible if module is enabled.
			'perms'    => '$user->rights->dolisirh->lire && $user->rights->projet->lire',   // Use 'perms'=>'$user->rights->dolisirh->level1->level2' if you want your menu with a permission rules
			'target'   => '',
			'user'     => 0,                                                                // 0=Menu for internal users, 1=external users, 2=both
		);

		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=project,fk_leftmenu=timespent',                      // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'     => 'left',                                                           // This is a Top menu entry
			'titre'    => $langs->trans('TimeSpending'),
			'prefix'   => $pictoDoliSIRH,
			'mainmenu' => 'project',
			'leftmenu' => 'timespent',
			'url'      => '/dolisirh/view/timespent_day.php',
			'langs'    => 'dolisirh@dolisirh',                                              // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => '$conf->dolisirh->enabled',                                       // Define condition to show or hide menu entry. Use '$conf->dolisirh->enabled' if entry must be visible if module is enabled.
			'perms'    => '$user->rights->dolisirh->lire && $user->rights->projet->lire',   // Use 'perms'=>'$user->rights->dolisirh->level1->level2' if you want your menu with a permission rules
			'target'   => '',
			'user'     => 0,                                                                // 0=Menu for internal users, 1=external users, 2=both
		);

		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=hrm,fk_leftmenu=timespent',                          // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'     => 'left',                                                           // This is a Top menu entry
			'titre'    => $langs->transnoentities('DoliSIRHTimeSpent'),
			'prefix'   => $pictoDoliSIRH,
			'mainmenu' => 'hrm',
			'leftmenu' => 'dolisirh_timespent_list',
			'url'      => '/dolisirh/view/timespent_list.php',
			'langs'    => 'dolisirh@dolisirh',                                              // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => '$conf->dolisirh->enabled && $conf->salaries->enabled',           // Define condition to show or hide menu entry. Use '$conf->dolisirh->enabled' if entry must be visible if module is enabled.
			'perms'    => '$user->rights->dolisirh->lire && $user->rights->projet->lire',   // Use 'perms'=>'$user->rights->dolisirh->level1->level2' if you want your menu with a permission rules
			'target'   => '',
			'user'     => 0,                                                                // 0=Menu for internal users, 1=external users, 2=both
		);

		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=hrm,fk_leftmenu=timespent',                          // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'     => 'left',                                                           // This is a Top menu entry
			'titre'    => $langs->trans('TimeSpending'),
			'prefix'   => $pictoDoliSIRH,
			'mainmenu' => 'hrm',
			'leftmenu' => 'timespent',
			'url'      => '/dolisirh/view/timespent_day.php',
			'langs'    => 'dolisirh@dolisirh',                                              // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => '$conf->dolisirh->enabled',                                       // Define condition to show or hide menu entry. Use '$conf->dolisirh->enabled' if entry must be visible if module is enabled.
			'perms'    => '$user->rights->dolisirh->lire && $user->rights->projet->lire',   // Use 'perms'=>'$user->rights->dolisirh->level1->level2' if you want your menu with a permission rules
			'target'   => '',
			'user'     => 0,                                                                // 0=Menu for internal users, 1=external users, 2=both
		);

		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=billing,fk_leftmenu=customers_bills',                // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'     => 'left',                                                           // This is a Top menu entry
			'titre'    => $langs->transnoentities('RecurringInvoicesStatistics'),
			'prefix'   => $pictoDoliSIRH,
			'mainmenu' => 'billing',
			'leftmenu' => 'customers_bills_recurring_stats',
			'url'      => '/dolisirh/view/recurringinvoicestatistics.php',
			'langs'    => 'dolisirh@dolisirh',                                              // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => '$conf->dolisirh->enabled && $conf->facture->enabled',            // Define condition to show or hide menu entry. Use '$conf->dolisirh->enabled' if entry must be visible if module is enabled.
			'perms'    => '$user->rights->dolisirh->lire && $user->rights->facture->lire',  // Use 'perms'=>'$user->rights->dolisirh->level1->level2' if you want your menu with a permission rules
			'target'   => '',
			'user'     => 0,                                                                // 0=Menu for internal users, 1=external users, 2=both
		);

        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=billing,fk_leftmenu=customers_bills',
            'type'     => 'left',
            'titre'    => $langs->transnoentities('Categories'),
            'mainmenu' => 'billing',
            'leftmenu' => 'customers_bills_tags',
            'url'      => '/categories/index.php?type=invoice',
            'langs'    => 'dolisirh@dolisirh',
            'position' => 1000 + $r,
            'enabled'  => '$conf->dolisirh->enabled && $conf->categorie->enabled',
            'perms'    => '$user->rights->dolisirh->lire && $user->rights->categorie->lire',
            'target'   => '',
            'user'     => 0,
        );

        $this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=dolisirh',
			'type'     => 'top',
			'titre'    => $langs->trans('DoliSIRH'),
			'prefix'   =>  '<i class="fas fa-home pictofixedwidth"></i>',
			'mainmenu' => 'dolisirh',
			'leftmenu' => '',
			'url'      => '/dolisirh/dolisirhindex.php',
			'langs'    => 'dolisirh@dolisirh',
			'position' => 1000 + $r,
			'enabled'  => '$conf->dolisirh->enabled',
			'perms'    => '$user->rights->dolisirh->lire',
			'target'   => '',
			'user'     => 0,
		);

		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=dolisirh',
			'type'     => 'left',
			'titre'    => $langs->trans('TimeSheet'),
			'prefix'   => '<i class="fas fa-calendar-check pictofixedwidth"></i>',
			'mainmenu' => 'dolisirh',
			'leftmenu' => 'timesheet',
			'url'      => '/dolisirh/view/timesheet/timesheet_list.php',
			'langs'    => 'dolisirh@dolisirh',
			'position' => 1000 + $r,
			'enabled'  => '$conf->dolisirh->enabled',
			'perms'    => '$user->rights->dolisirh->timesheet->read',
			'target'   => '',
			'user'     => 0,
		);

        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=dolisirh,fk_leftmenu=timesheet',
            'type'     => 'left',
            'titre'    => '<i class="fas fa-tags pictofixedwidth" style="padding-right: 4px;"></i>' . $langs->transnoentities('Categories'),
            'mainmenu' => 'dolisirh',
            'leftmenu' => 'timesheettags',
            'url'      => '/categories/index.php?type=timesheet',
            'langs'    => 'dolisirh@dolisirh',
            'position' => 1000 + $r,
            'enabled'  => '$conf->dolisirh->enabled && $conf->categorie->enabled',
            'perms'    => '$user->rights->dolisirh->lire && $user->rights->categorie->lire',
            'target'   => '',
            'user'     => 0,
        );

//		$this->menu[$r++] = array(
//			'fk_menu'  => 'fk_mainmenu=dolisirh',
//			'type'     => 'left',
//			'titre'    => $langs->trans('Certificate'),
//			'prefix'   => '<i class="fas fa-user-graduate pictofixedwidth"></i>',
//			'mainmenu' => 'dolisirh',
//			'leftmenu' => 'certificate',
//			'url'      => '/dolisirh/view/certificate/certificate_list.php',
//			'langs'    => 'dolisirh@dolisirh',
//			'position' => 1000 + $r,
//			'enabled'  => '$conf->dolisirh->enabled',
//			'perms'    => '$user->rights->dolisirh->certificate->read',
//			'target'   => '',
//			'user'     => 0,
//		);

		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=dolisirh',                                           // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'     => 'left',                                                           // This is a Top menu entry
			'titre'    => $langs->trans('TimeSpending'),
			'prefix'   => '<i class="far fa-clock pictofixedwidth"></i>',
			'mainmenu' => 'dolisirh',
			'leftmenu' => 'timespent',
			'url'      => '/dolisirh/view/timespent_month.php',
			'langs'    => 'dolisirh@dolisirh',                                              // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => '$conf->dolisirh->enabled && $conf->projet->enabled',             // Define condition to show or hide menu entry. Use '$conf->dolisirh->enabled' if entry must be visible if module is enabled.
			'perms'    => '$user->rights->dolisirh->lire && $user->rights->projet->lire',   // Use 'perms'=>'$user->rights->dolisirh->level1->level2' if you want your menu with a permission rules
			'target'   => '',
			'user'     => 0,                                                                // 0=Menu for internal users, 1=external users, 2=both
		);

        $this->menu[$r++] = array(
            'fk_menu'  => 'fk_mainmenu=dolisirh',                                           // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
            'type'     => 'left',                                                           // This is a Left menu entry
            'titre'    => $langs->trans('Tools'),
            'prefix'   => '<i class="fas fa-wrench pictofixedwidth"></i>',
            'mainmenu' => 'dolisirh',
            'leftmenu' => 'dolisirhtools',
            'url'      => '/dolisirh/view/dolisirhtools.php',
            'langs'    => 'dolisirh@dolisirh',                                              // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
            'position' => 1000 + $r,
            'enabled'  => '$conf->dolisirh->enabled',                                       // Define condition to show or hide menu entry. Use '$conf->dolisirh->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
            'perms'    => '$user->rights->dolisirh->adminpage->read',                       // Use 'perms'=>'$user->rights->dolisirh->level1->level2' if you want your menu with a permission rules
            'target'   => '',
            'user'     => 0,                                                                // 0=Menu for internal users, 1=external users, 2=both
        );

		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=dolisirh',                                           // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'     => 'left',                                                           // This is a Left menu entry
			'titre'    => $langs->trans('DoliSIRHConfig'),
			'prefix'   => '<i class="fas fa-cog pictofixedwidth"></i>',
			'mainmenu' => 'dolisirh',
			'leftmenu' => 'dolisirhconfig',
			'url'      => '/dolisirh/admin/setup.php',
			'langs'    => 'dolisirh@dolisirh',                                              // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => '$conf->dolisirh->enabled',                                       // Define condition to show or hide menu entry. Use '$conf->dolisirh->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'    => '$user->rights->dolisirh->adminpage->read',                       // Use 'perms'=>'$user->rights->dolisirh->level1->level2' if you want your menu with a permission rules
			'target'   => '',
			'user'     => 0,                                                                // 0=Menu for internal users, 1=external users, 2=both
		);

		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=dolisirh',                                           // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
			'type'     => 'left',                                                           // This is a Left menu entry
			'titre'    => $langs->transnoentities('MinimizeMenu'),
			'prefix'   => '<i class="fas fa-chevron-circle-left pictofixedwidth"></i>',
			'mainmenu' => 'dolisirh',
			'leftmenu' => 'minimizemenu',
			'url'      => '',
			'langs'    => 'dolisirh@dolisirh',                                              // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
			'position' => 1000 + $r,
			'enabled'  => '$conf->dolisirh->enabled',                                       // Define condition to show or hide menu entry. Use '$conf->dolisirh->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
			'perms'    => '$user->rights->dolisirh->lire',                                  // Use 'perms'=>'$user->rights->dolisirh->level1->level2' if you want your menu with a permission rules
			'target'   => '',
			'user'     => 0,                                                                // 0=Menu for internal users, 1=external users, 2=both
		);
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int             	1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $db, $langs, $user;
		$langs->load('dolisirh@dolisirh');

		$sql = array();
		// Load sql sub folders
		$sqlFolder = scandir(__DIR__ . '/../../sql');
		foreach ($sqlFolder as $subFolder) {
			if ( ! preg_match('/\./', $subFolder)) {
				$this->_load_tables('/dolisirh/sql/' . $subFolder . '/');
			}
		}

		$result = $this->_load_tables('/dolisirh/sql/');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}

		// Create extrafields during init
		include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
		$extra_fields = new ExtraFields($this->db);

		$param['options']['Task:projet/class/task.class.php'] = null;
		$extra_fields->addExtraField('fk_task', 'Tâche', 'link', 100, null, 'facture', 1, 0, null, $param, 1, 1, 1); //extrafields invoice
		unset($param);
		$param['options']['Facture:compta/facture/class/facture.class.php'] = null;
		$extra_fields->addExtraField('fk_facture_name', 'Facture', 'link', 100, null, 'projet_task', 1, 0, null, $param, 1, 1, 1); //extrafields task
		unset($param);
		$param['options']['projet_task:label:rowid::entity = $ENTITY$ AND fk_projet = ($SEL$ fk_project FROM '. MAIN_DB_PREFIX .'ticket WHERE rowid = $ID$)'] = null;
		$extra_fields->update('fk_task', 'Tâche', 'sellist', '', 'ticket', 0, 0, 100, $param, 1, 1, '1', '','','',0);
		$extra_fields->addExtraField('fk_task', 'Tâche', 'sellist', 100, null, 'ticket', 0, 0, null, $param, 1, 1, '1', '','',0); //extrafields ticket
		unset($param);
        $extra_fields->addExtraField('timespent', $langs->trans('MarkYourTime'), 'boolean', 100, null, 'actioncomm', 0, 0, null, 'a:1:{s:7:"options";a:1:{s:0:"";N;}}', 1, '$user->rights->projet->time', '3', '','',0, 'dolisirh@dolisirh', '$conf->dolisirh->enabled'); //extrafields ticket

		// Document models
		delDocumentModel('timesheetdocument_odt', 'timesheetdocument');
		delDocumentModel('certificatedocument_odt', 'certificatedocument');
		addDocumentModel('timesheetdocument_odt', 'timesheetdocument', 'ODT templates', 'DOLISIRH_TIMESHEETDOCUMENT_ADDON_ODT_PATH');
		addDocumentModel('certificatedocument_odt', 'certificatedocument', 'ODT templates', 'DOLISIRH_CERTIFICATEDOCUMENT_ADDON_ODT_PATH');

		return $this->_init(array(), $options);
	}

	/**
	 *  Function called when module is disabled.
	 *  Remove from database constants, boxes and permissions from Dolibarr database.
	 *  Data directories are not deleted
	 *
	 *  @param      string	$options    Options when enabling module ('', 'noboxes')
	 *  @return     int                 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
