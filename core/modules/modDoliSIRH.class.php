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
 */

/**
 * \defgroup dolisirh Module DoliSIRH.
 * \brief    DoliSIRH module descriptor.
 *
 * \file     core/modules/modDoliSIRH.class.php
 * \ingroup  dolisirh
 * \brief    Description and activation file for module DoliSIRH.
 */

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module DoliSIRH.
 */
class modDoliSIRH extends DolibarrModules
{
    /**
     * Constructor. Define names, constants, directories, boxes, permissions.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct($db)
    {
        global $langs, $conf;
        $this->db = $db;

        if (file_exists(__DIR__ . '/../../../saturne/lib/saturne_functions.lib.php')) {
            require_once __DIR__ . '/../../../saturne/lib/saturne_functions.lib.php';
            saturne_load_langs(['dolisirh@dolisirh']);
        } else {
            $this->error++;
            $this->errors[] = $langs->trans('activateModuleDependNotSatisfied', 'DoliSIRH', 'Saturne');
        }

        // Id for module (must be unique).
        $this->numero = 436310;

        // Key text used to identify module (for permissions, menus, etc...).
        $this->rights_class = 'dolisirh';

        // Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
        // It is used to group modules by family in module setup page.
        $this->family = '';

        // Module position in the family on 2 digits ('01', '10', '20', ...).
        $this->module_position = '';

        // Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
        $this->familyinfo = ['Evarisk' => ['position' => '01', 'label' => $langs->trans('Evarisk')]];
        // Module label (no space allowed), used if translation string 'ModuleDoliSIRHName' not found (DoliSIRH is name of module).
        $this->name = preg_replace('/^mod/i', '', get_class($this));

        // Module description, used if translation string 'ModuleDoliSIRHDesc' not found (DoliSIRH is name of module).
        $this->description = $langs->trans('DoliSIRHDescription');
        // Used only if file README.md and README-LL.md not found.
        $this->descriptionlong = $langs->trans('DoliSIRHDescriptionLong');

        // Author.
        $this->editor_name = 'Evarisk';
        $this->editor_url  = 'https://evarisk.com';

        // Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'.
        $this->version = '1.3.1';

        // Url to the file with your last numberversion of this module.
        //$this->url_last_version = 'http://www.example.com/versionmodule.txt';

        // Key used in llx_const table to save module status enabled/disabled (where DoliSIRH is value of property name of module in uppercase).
        $this->const_name  = 'MAIN_MODULE_' . strtoupper($this->name);

        // Name of image file used for this module.
        // If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
        // If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
        // To use a supported fa-xxx css style of font awesome, use this->picto='xxx'.
        $this->picto = 'dolisirh_color@dolisirh';

        // Define some features supported by module (triggers, login, substitutions, menus, css, etc...).
        $this->module_parts = [
            // Set this to 1 if module has its own trigger directory (core/triggers).
            'triggers' => 1,
            // Set this to 1 if module has its own login method file (core/login).
            'login' => 0,
            // Set this to 1 if module has its own substitution function file (core/substitutions).
            'substitutions' => 0,
            // Set this to 1 if module has its own menus handler directory (core/menus).
            'menus' => 0,
            // Set this to 1 if module overwrite template dir (core/tpl).
            'tpl' => 0,
            // Set this to 1 if module has its own barcode directory (core/modules/barcode).
            'barcode' => 0,
            // Set this to 1 if module has its own models' directory (core/modules/xxx).
            'models' => 1,
            // Set this to 1 if module has its own printing directory (core/modules/printing).
            'printing' => 0,
            // Set this to 1 if module has its own theme directory (theme).
            'theme' => 0,
            // Set this to relative path of css file if module has its own css file.
            'css' => [],
            // Set this to relative path of js file if module must load a js on all pages.
            'js' => [],
            // Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'.
            'hooks' => [
                'data' => [
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
                    'userihm',
                    'timesheetadmin',
                    'dolisirhadmindocuments',
                    'dolisirhindex',
                    'projectcard',
                    'invoicelist',
                    'invoicereclist',
                    'certificatecard'
                ]
            ],
            // Set this to 1 if features of module are opened to external users.
            'moduleforexternal' => 0,
        ];

        // Data directories to create when module is enabled.
        // Example: this->dirs = array("/dolisirh/temp","/dolisirh/subdir");
        $this->dirs = [
            '/dolisirh/temp',
            '/ecm/dolisirh/timesheetdocument',
            '/ecm/dolisirh/certificatedocument'
        ];

        // Config pages. Put here list of php page, stored into dolisirh/admin directory, to use to set up module.
        $this->config_page_url = ['setup.php@dolisirh'];

        // Dependencies.
        // A condition to hide module.
        $this->hidden = false;

        // List of module class names as string that must be enabled if this module is enabled. Example: array('always1'=>'modModuleToEnable1','always2'=>'modModuleToEnable2', 'FR1'=>'modModuleToEnableFR'...).
        $this->depends      = ['modProjet', 'modBookmark', 'modHoliday', 'modFckeditor', 'modSalaries', 'modProduct', 'modService', 'modSociete', 'modECM', 'modCategorie', 'modSaturne', 'modCron'];
        $this->requiredby   = []; // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...).
        $this->conflictwith = []; // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...).

        // The language file dedicated to your module.
        $this->langfiles = ['dolisirh@dolisirh'];

        // Prerequisites.
        $this->phpmin                = [7, 4];  // Minimum version of PHP required by module.
        $this->need_dolibarr_version = [16, 0]; // Minimum version of Dolibarr required by module.

        // Messages at activation.
        $this->warnings_activation     = []; // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','ES'='textes'...).
        $this->warnings_activation_ext = []; // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','ES'='textes'...).

        // Constants.
        // List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive).
        // Example: $this->const=array(1 => array('DoliSIRH_MYNEWCONST1', 'chaine', 'myvalue', 'This is a constant to add', 1),
        //                             2 => array('DoliSIRH_MYNEWCONST2', 'chaine', 'myvalue', 'This is another constant to add', 0, 'current', 1)
        // );
        $i = 0;
        $this->const = [
            // CONST CONFIGURATION.
            $i++ => ['DOLISIRH_DEFAUT_TICKET_TIME', 'chaine', '15', 'Default Time', 0, 'current'],
            $i++ => ['DOLISIRH_HR_PROJECT', 'integer', 0, '', 0, 'current'],
            $i++ => ['DOLISIRH_TIMESPENT_BOOKMARK_SET', 'integer', 0, '', 0, 'current'],
            $i++ => ['DOLISIRH_EXCEEDED_TIME_SPENT_COLOR', 'chaine', '#FF0000', '', 0, 'current'],
            $i++ => ['DOLISIRH_NOT_EXCEEDED_TIME_SPENT_COLOR', 'chaine', '#FFA500', '', 0, 'current'],
            $i++ => ['DOLISIRH_PERFECT_TIME_SPENT_COLOR', 'chaine', '#008000', '', 0, 'current'],
            $i++ => ['DOLISIRH_PRODUCT_SERVICE_SET', 'integer', 0, '', 0, 'current'],
            $i++ => ['DOLISIRH_HR_PROJECT_SET', 'integer', 0, '', 0, 'current'],
            $i++ => ['DOLISIRH_BACKWARD_COMPATIBILITY_PRODUCT_SERVICE', 'integer', 0, '', 0, 'current'],
            $i++ => ['DOLISIRH_ADVANCED_TRIGGER', 'integer', 1, '', 0, 'current'],
            $i++ => ['DOLISIRH_SHOW_PATCH_NOTE', 'integer', 1, '', 0, 'current'],
            $i++ => ['DOLISIRH_DB_VERSION', 'chaine', $this->version, '', 0, 'current'],
            $i++ => ['DOLISIRH_VERSION','chaine', $this->version, '', 0, 'current'],

            // CONST DOLISIRH DOCUMENTS.
            $i++ => ['DOLISIRH_AUTOMATIC_PDF_GENERATION', 'integer', 0, '', 0, 'current'],
            $i++ => ['DOLISIRH_MANUAL_PDF_GENERATION', 'integer', 0, '', 0, 'current'],
            $i++ => ['DOLISIRH_SHOW_SIGNATURE_SPECIMEN', 'integer', 0, '', 0, 'current'],

            // CONST TIME SPENT.
            $i++ => ['DOLISIRH_SHOW_TASKS_WITH_TIMESPENT_ON_TIMESHEET', 'integer', 1, '', 0, 'current'],

            // CONST TIME SHEET.
            $i++ => ['DOLISIRH_TIMESHEET_ADDON', 'chaine', 'mod_timesheet_standard', '', 0, 'current'],
            $i++ => ['DOLISIRH_TIMESHEET_PREFILL_DATE', 'integer', 1, '', 0, 'current'],
            $i++ => ['DOLISIRH_TIMESHEET_ADD_ATTENDANTS', 'integer', 0, '', 0, 'current'],
            $i++ => ['DOLISIRH_TIMESHEET_CHECK_DATE_END', 'integer', 1, '', 0, 'current'],

            // CONST TIMESHEET DOCUMENT.
            $i++ => ['DOLISIRH_TIMESHEETDOCUMENT_ADDON', 'chaine', 'mod_timesheetdocument_standard', '', 0, 'current'],
            $i++ => ['DOLISIRH_TIMESHEETDOCUMENT_ADDON_ODT_PATH', 'chaine', 'DOL_DOCUMENT_ROOT/custom/dolisirh/documents/doctemplates/timesheetdocument/', '', 0, 'current'],
            $i++ => ['DOLISIRH_TIMESHEETDOCUMENT_CUSTOM_ADDON_ODT_PATH', 'chaine', 'DOL_DATA_ROOT' . (($conf->entity == 1 ) ? '/' : '/' . $conf->entity . '/') . 'ecm/dolisirh/timesheetdocument/', '', 0, 'current'],
            $i++ => ['DOLISIRH_TIMESHEETDOCUMENT_DEFAULT_MODEL', 'chaine', 'timesheetdocument_odt', '', 0, 'current'],

            // CONST CERTIFICATE.
            $i++ => ['DOLISIRH_CERTIFICATE_ADDON', 'chaine', 'mod_certificate_standard', '', 0, 'current'],
            $i++ => ['DOLISIRH_CERTIFICATE_USER_RESPONSIBLE', 'integer', 0, '', 0, 'current'],

            // CONST CERTIFICATE DOCUMENT.
            $i++ => ['DOLISIRH_CERTIFICATEDOCUMENT_ADDON', 'chaine', 'mod_certificatedocument_standard', '', 0, 'current'],
            $i++ => ['DOLISIRH_CERTIFICATEDOCUMENT_ADDON_ODT_PATH', 'chaine', 'DOL_DOCUMENT_ROOT/custom/dolisirh/documents/doctemplates/certificatedocument/', '', 0, 'current'],
            $i++ => ['DOLISIRH_CERTIFICATEDOCUMENT_CUSTOM_ADDON_ODT_PATH', 'chaine', 'DOL_DATA_ROOT' . (($conf->entity == 1 ) ? '/' : '/' . $conf->entity . '/') . 'ecm/dolisirh/certificatedocument/', '', 0, 'current'],
            $i++ => ['DOLISIRH_CERTIFICATEDOCUMENT_DEFAULT_MODEL', 'chaine', 'certificatedocument_odt', '', 0, 'current'],

            // CONST PROJECT DOCUMENT.
            $i++ => ['DOLISIRH_PROJECTDOCUMENT_ADDON', 'chaine', 'mod_projectdocument_standard', '', 0, 'current'],
            $i++ => ['DOLISIRH_PROJECTDOCUMENT_ADDON_ODT_PATH', 'chaine', 'DOL_DOCUMENT_ROOT/custom/dolisirh/documents/doctemplates/projectdocument/', '', 0, 'current'],
            $i++ => ['DOLISIRH_PROJECTDOCUMENT_DEFAULT_MODEL', 'chaine', 'projectdocument_odt', '', 0, 'current'],

            // CONST DOLIBARR.
            $i => ['MAIN_ODT_AS_PDF', 'chaine', 'libreoffice', '', 0, 'current']
        ];

        if (!isset($conf->dolisirh) || !isset($conf->dolisirh->enabled)) {
            $conf->dolisirh = new stdClass();
            $conf->dolisirh->enabled = 0;
        }

        // Array to add new pages in new tabs.
        // Example:
        // $this->tabs[] = array('data'=>'objecttype:+tabname2:SUBSTITUTION_Title2:mylangfile@dolisirh:$user->rights->othermodule->read:/dolisirh/mynewtab2.php?id=__ID__',  	// To add another new tab identified by code tabname2. Label will be result of calling all substitution functions on 'Title2' key.
        // $this->tabs[] = array('data'=>'objecttype:-tabname:NU:conditiontoremove');
        $this->tabs   = [];
        $pictoPath    = dol_buildpath('/custom/dolisirh/img/dolisirh_color.png', 1);
        $pictoModule  = img_picto('', $pictoPath, '', 1, 0, 0, '', 'pictoModule');
        $this->tabs[] = ['data' => 'user:+workinghours:' . $pictoModule . $langs->trans('WorkingHours') . ':dolisirh@dolisirh:$user->rights->user->self->creer:/custom/dolisirh/view/workinghours_card.php?id=__ID__']; // To add a new tab identified by code tabname1.

        // Dictionaries.
        $this->dictionaries = [
            'langs' => 'dolisirh@dolisirh',
            // List of tables we want to see into dictionary editor.
            'tabname' => [
                MAIN_DB_PREFIX . 'c_timesheet_attendants_role',
                MAIN_DB_PREFIX . 'c_certificate_attendants_role'
            ],
            // Label of tables.
            'tablib' => [
                'Timesheet',
                'Certificate'
            ],
            // Request to select fields.
            'tabsql' => [
                'SELECT f.rowid as rowid, f.ref, f.label, f.description, f.position, f.active FROM ' . MAIN_DB_PREFIX . 'c_timesheet_attendants_role as f',
                'SELECT f.rowid as rowid, f.ref, f.label, f.description, f.position, f.active FROM ' . MAIN_DB_PREFIX . 'c_certificate_attendants_role as f'
            ],
            // Sort order.
            'tabsqlsort' => [
                'position ASC',
                'position ASC'
            ],
            // List of fields (result of select to show dictionary).
            'tabfield' => [
                'ref,label,description,position',
                'ref,label,description,position'
            ],
            // List of fields (list of fields to edit a record).
            'tabfieldvalue' => [
                'ref,label,description,position',
                'ref,label,description,position'
            ],
            // List of fields (list of fields for insert).
            'tabfieldinsert' => [
                'ref,label,description,position',
                'ref,label,description,position'
            ],
            // Name of columns with primary key (try to always name it 'rowid').
            'tabrowid' => [
                'rowid',
                'rowid'
            ],
            // Condition to show each dictionary.
            'tabcond' => [
                $conf->dolisirh->enabled,
                $conf->dolisirh->enabled
            ]
        ];

        // Boxes/Widgets
        // Add here list of php file(s) stored in dolisirh/core/boxes that contains a class to show a widget.
        $this->boxes = [];

        // Cronjobs (List of cron jobs entries to add when module is enabled).
        // unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week.
        $this->cronjobs = [
            0 => [
                'label'         => $langs->transnoentities('checkDateEndJob'),
                'jobtype'       => 'method',
                'class'         => '/saturne/class/saturnecertificate.class.php',
                'objectname'    => 'SaturneCertificate',
                'method'        => 'checkDateEnd',
                'parameters'    => 'certificate',
                'comment'       => $langs->transnoentities('checkDateEndJobComment'),
                'frequency'     => 1,
                'unitfrequency' => 86400,
                'status'        => 1,
                'test'          => '$conf->saturne->enabled && $conf->dolisirh->enabled',
                'priority'      => 50
            ]
        ];

        // Permissions provided by this module.
        $this->rights = [];
        $r = 0;

        /* DOLISIRH PERMISSIONS */
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used).
        $this->rights[$r][1] = $langs->trans('LireModule', 'DoliSIRH');   // Permission label.
        $this->rights[$r][4] = 'read';                                                // In php code, permission will be checked by test if ($user->rights->dolisirh->level1->level2).
        $r++;
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->trans('ReadModule', 'DoliSIRH');
        $this->rights[$r][4] = 'lire';
        $r++;

        /* TIMESHEET PERMISSIONS */
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('ReadObjects',$langs->transnoentities('Timesheets'));
        $this->rights[$r][4] = 'timesheet';
        $this->rights[$r][5] = 'read';
        $r++;
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('CreateObjects', $langs->transnoentities('Timesheets'));
        $this->rights[$r][4] = 'timesheet';
        $this->rights[$r][5] = 'write';
        $r++;
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('DeleteObjects', $langs->transnoentities('Timesheets'));
        $this->rights[$r][4] = 'timesheet';
        $this->rights[$r][5] = 'delete';
        $r++;

        /* CERTIFICATE PERMISSIONS */
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('ReadObjects', $langs->transnoentities('Certificates'));
        $this->rights[$r][4] = 'certificate';
        $this->rights[$r][5] = 'read';
        $r++;
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('CreateObjects', $langs->transnoentities('Certificates'));
        $this->rights[$r][4] = 'certificate';
        $this->rights[$r][5] = 'write';
        $r++;
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('DeleteObjects', $langs->transnoentities('Certificates'));
        $this->rights[$r][4] = 'certificate';
        $this->rights[$r][5] = 'delete';
        $r++;

        /* WORKING HOURS PERMISSIONS */
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('CreateAllWorkingHours');
        $this->rights[$r][4] = 'workinghours';
        $this->rights[$r][5] = 'allworkinghours';
        $r++;
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('CreateMyWorkingHours');
        $this->rights[$r][4] = 'workinghours';
        $this->rights[$r][5] = 'myworkinghours';
        $r++;

        /* ADMINPAGE PANEL ACCESS PERMISSIONS */
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('ReadAdminPage', 'DoliSIRH');
        $this->rights[$r][4] = 'adminpage';
        $this->rights[$r][5] = 'read';

        // Main menu entries to add.
        $this->menu = [];
        $r          = 0;

        // Add here entries to declare new menus.
        // DOLISIRH MENU.
        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisirh',
            'type'     => 'top',
            'titre'    => $langs->trans('DoliSIRH'),
            'prefix'   => '<i class="fas fa-home pictofixedwidth"></i>',
            'mainmenu' => 'dolisirh',
            'leftmenu' => '',
            'url'      => '/dolisirh/dolisirhindex.php',
            'langs'    => 'dolisirh@dolisirh',
            'position' => 1000 + $r,
            'enabled'  => '$conf->dolisirh->enabled && $user->rights->dolisirh->lire',
            'perms'    => '$user->rights->dolisirh->lire',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
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
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisirh,fk_leftmenu=timesheet',
            'type'     => 'left',
            'titre'    => '<i class="fas fa-tags pictofixedwidth" style="padding-right: 4px;"></i>' . $langs->transnoentities('Categories'),
            'mainmenu' => 'dolisirh',
            'leftmenu' => 'timesheettags',
            'url'      => '/categories/index.php?type=timesheet',
            'langs'    => 'dolisirh@dolisirh',
            'position' => 1000 + $r,
            'enabled'  => '$conf->dolisirh->enabled && $conf->categorie->enabled',
            'perms'    => '$user->rights->dolisirh->lire && $user->rights->dolisirh->timesheet->read && $user->rights->categorie->lire',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisirh',
            'type'     => 'left',
            'titre'    => $langs->trans('Certificate'),
            'prefix'   => '<i class="fas fa-user-graduate pictofixedwidth"></i>',
            'mainmenu' => 'dolisirh',
            'leftmenu' => 'certificate',
            'url'      => '/dolisirh/view/certificate/certificate_list.php',
            'langs'    => 'dolisirh@dolisirh',
            'position' => 1000 + $r,
            'enabled'  => '$conf->dolisirh->enabled',
            'perms'    => '$user->rights->dolisirh->certificate->read',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisirh,fk_leftmenu=certificate',
            'type'     => 'left',
            'titre'    => '<i class="fas fa-tags pictofixedwidth" style="padding-right: 4px;"></i>' . $langs->transnoentities('Categories'),
            'mainmenu' => 'dolisirh',
            'leftmenu' => 'certificatetags',
            'url'      => '/categories/index.php?type=certificate',
            'langs'    => 'dolisirh@dolisirh',
            'position' => 1000 + $r,
            'enabled'  => '$conf->dolisirh->enabled && $conf->categorie->enabled',
            'perms'    => '$user->rights->dolisirh->lire && $user->rights->dolisirh->certificate->read && $user->rights->categorie->lire',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisirh',
            'type'     => 'left',
            'titre'    => $langs->trans('TimeSpending'),
            'prefix'   => '<i class="far fa-clock pictofixedwidth"></i>',
            'mainmenu' => 'dolisirh',
            'leftmenu' => 'timespent',
            'url'      => '/dolisirh/view/timespent_range.php?view_mode=month',
            'langs'    => 'dolisirh@dolisirh',
            'position' => 1000 + $r,
            'enabled'  => '$conf->dolisirh->enabled && $conf->projet->enabled',
            'perms'    => '$user->rights->dolisirh->lire && $user->rights->projet->lire',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=dolisirh',
            'type'     => 'left',
            'titre'    => $langs->trans('Tools'),
            'prefix'   => '<i class="fas fa-wrench pictofixedwidth"></i>',
            'mainmenu' => 'dolisirh',
            'leftmenu' => 'dolisirhtools',
            'url'      => '/dolisirh/view/dolisirhtools.php',
            'langs'    => 'dolisirh@dolisirh',
            'position' => 1000 + $r,
            'enabled'  => '$conf->dolisirh->enabled',
            'perms'    => '$user->rights->dolisirh->adminpage->read',
            'target'   => '',
            'user'     => 0,
        ];

        // PROJECT MENU.
        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=project,fk_leftmenu=timespent',
            'type'     => 'left',
            'titre'    => '<i class="fas fa-id-card pictofixedwidth" style="padding-right: 4px; color: #d35968;"></i>' . $langs->transnoentities('DoliSIRHTimeSpent'),
            'mainmenu' => 'project',
            'leftmenu' => 'dolisirh_timespent_list',
            'url'      => '/dolisirh/view/timespent_list.php',
            'langs'    => 'dolisirh@dolisirh',
            'position' => 1000 + $r,
            'enabled'  => '$conf->dolisirh->enabled && $conf->projet->enabled',
            'perms'    => '$user->rights->dolisirh->lire && $user->rights->projet->lire',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=project,fk_leftmenu=timespent',
            'type'     => 'left',
            'titre'    => '<i class="fas fa-id-card pictofixedwidth" style="padding-right: 4px; color: #d35968;"></i>' . $langs->transnoentities('TimeSpending'),
            'mainmenu' => 'project',
            'leftmenu' => 'timespent',
            'url'      => '/dolisirh/view/timespent_range.php?view_mode=month',
            'langs'    => 'dolisirh@dolisirh',
            'position' => 1000 + $r,
            'enabled'  => '$conf->dolisirh->enabled && $conf->projet->enabled',
            'perms'    => '$user->rights->dolisirh->lire && $user->rights->projet->lire',
            'target'   => '',
            'user'     => 0,
        ];

        // HRM MENU.
        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=hrm,fk_leftmenu=timespent',
            'type'     => 'left',
            'titre'    => '<i class="fas fa-id-card pictofixedwidth" style="padding-right: 4px; color: #d35968;"></i>' . $langs->transnoentities('DoliSIRHTimeSpent'),
            'mainmenu' => 'hrm',
            'leftmenu' => 'dolisirh_timespent_list',
            'url'      => '/dolisirh/view/timespent_list.php',
            'langs'    => 'dolisirh@dolisirh',
            'position' => 1000 + $r,
            'enabled'  => '$conf->dolisirh->enabled && $conf->salaries->enabled',
            'perms'    => '$user->rights->dolisirh->lire && $user->rights->projet->lire',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=hrm,fk_leftmenu=timespent',
            'type'     => 'left',
            'titre'    => '<i class="fas fa-id-card pictofixedwidth" style="padding-right: 4px; color: #d35968;"></i>' . $langs->transnoentities('TimeSpending'),
            'mainmenu' => 'hrm',
            'leftmenu' => 'timespent',
            'url'      => '/dolisirh/view/timespent_range.php?view_mode=month',
            'langs'    => 'dolisirh@dolisirh',
            'position' => 1000 + $r,
            'enabled'  => '$conf->dolisirh->enabled && $conf->salaries->enabled',
            'perms'    => '$user->rights->dolisirh->lire && $user->rights->projet->lire',
            'target'   => '',
            'user'     => 0,
        ];

        // BILLING MENU.
        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=billing,fk_leftmenu=customers_bills',
            'type'     => 'left',
            'titre'    => '<i class="fas fa-id-card pictofixedwidth" style="padding-right: 4px; color: #d35968;"></i>' . $langs->transnoentities('RecurringInvoicesStatistics'),
            'mainmenu' => 'billing',
            'leftmenu' => 'customers_bills_recurring_stats',
            'url'      => '/dolisirh/view/recurringinvoicestatistics.php',
            'langs'    => 'dolisirh@dolisirh',
            'position' => 1000 + $r,
            'enabled'  => '$conf->dolisirh->enabled && $conf->facture->enabled',
            'perms'    => '$user->rights->dolisirh->lire && $user->rights->facture->lire',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=billing,fk_leftmenu=customers_bills',
            'type'     => 'left',
            'titre'    => '<i class="fas fa-tags pictofixedwidth" style="padding-right: 4px;"></i>' . $langs->transnoentities('FactureCategories'),
            'mainmenu' => 'billing',
            'leftmenu' => 'customers_bills_tags',
            'url'      => '/categories/index.php?type=facture',
            'langs'    => 'dolisirh@dolisirh',
            'position' => 1000 + $r,
            'enabled'  => '$conf->dolisirh->enabled && $conf->categorie->enabled && $conf->facture->enabled',
            'perms'    => '$user->rights->dolisirh->lire && $user->rights->categorie->lire && $user->rights->facture->lire',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=billing,fk_leftmenu=customers_bills',
            'type'     => 'left',
            'titre'    => '<i class="fas fa-tags pictofixedwidth" style="padding-right: 4px;"></i>' . $langs->transnoentities('FactureRecCategories'),
            'mainmenu' => 'billing',
            'leftmenu' => 'customers_bills_recurring_tags',
            'url'      => '/categories/index.php?type=facturerec',
            'langs'    => 'dolisirh@dolisirh',
            'position' => 1000 + $r,
            'enabled'  => '$conf->dolisirh->enabled && $conf->categorie->enabled && $conf->facture->enabled',
            'perms'    => '$user->rights->dolisirh->lire && $user->rights->categorie->lire && $user->rights->facture->lire',
            'target'   => '',
            'user'     => 0,
        ];
    }

    /**
     * Function called when module is enabled.
     * The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
     * It also creates data directories.
     *
     * @param  string     $options Options when enabling module ('', 'noboxes').
     * @return int                 1 if OK, 0 if KO.
     * @throws Exception
     */
    public function init($options = ''): int
    {
        global $conf, $langs, $user;

        // Permissions.
        $this->remove($options);

        $sql = [];
        // Load sql sub folders.
        $sqlFolder = scandir(__DIR__ . '/../../sql');
        foreach ($sqlFolder as $subFolder) {
            if (!preg_match('/\./', $subFolder)) {
                $this->_load_tables('/dolisirh/sql/' . $subFolder . '/');
            }
        }

        $result = $this->_load_tables('/dolisirh/sql/');
        if ($result < 0) {
            return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default').
        }

        dolibarr_set_const($this->db, 'DOLISIRH_VERSION', $this->version, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($this->db, 'DOLISIRH_DB_VERSION', $this->version, 'chaine', 0, '', $conf->entity);

        // Document models.
        delDocumentModel('timesheetdocument_odt', 'timesheetdocument');
        delDocumentModel('certificatedocument_odt', 'certificatedocument');
        delDocumentModel('projectdocument_odt', 'projectdocument');

        addDocumentModel('timesheetdocument_odt', 'timesheetdocument', 'ODT templates', 'DOLISIRH_TIMESHEETDOCUMENT_ADDON_ODT_PATH');
        addDocumentModel('certificatedocument_odt', 'certificatedocument', 'ODT templates', 'DOLISIRH_CERTIFICATEDOCUMENT_ADDON_ODT_PATH');
        addDocumentModel('projectdocument_odt', 'projectdocument', 'ODT templates', 'DOLISIRH_PROJECTDOCUMENT_ADDON_ODT_PATH');

        // Create extrafields during init.
        require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
        $extraFields = new ExtraFields($this->db);

        $param['options']['Task:projet/class/task.class.php'] = null;
        $extraFields->addExtraField('fk_task', 'Tâche', 'link', 100, null, 'facture', 1, 0, null, $param, 1, 1, 1); //extrafields invoice.
        unset($param);
        $param['options']['Facture:compta/facture/class/facture.class.php'] = null;
        $extraFields->addExtraField('fk_facture_name', 'Facture', 'link', 100, null, 'projet_task', 1, 0, null, $param, 1, 1, 1); //extrafields task.
        unset($param);
        $param['options']['projet_task:label:rowid::entity = $ENTITY$ AND fk_projet = ($SEL$ fk_project FROM '. MAIN_DB_PREFIX .'ticket WHERE rowid = $ID$)'] = null;
        $extraFields->update('fk_task', 'Tâche', 'sellist', '', 'ticket', 0, 0, 100, $param, 1, 1, '1', '','','',0);
        $extraFields->addExtraField('fk_task', 'Tâche', 'sellist', 100, null, 'ticket', 0, 0, null, $param, 1, 1, '1', '','',0); //extrafields ticket.
        unset($param);
        $extraFields->addExtraField('timespent', $langs->trans('MarkYourTime'), 'boolean', 100, null, 'actioncomm', 0, 0, null, 'a:1:{s:7:"options";a:1:{s:0:"";N;}}', 1, '$user->rights->projet->time', '3', '','',0, 'dolisirh@dolisirh', '$conf->dolisirh->enabled'); //extrafields ticket.

        if (getDolGlobalInt('DOLISIRH_BACKWARD_COMPATIBILITY_PRODUCT_SERVICE') == 0) {
            require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

            require_once __DIR__ . '/../../lib/dolisirh_function.lib.php';

            $product = new Product($this->db);

            $timesheetProductAndServices = get_timesheet_product_service();

            foreach ($timesheetProductAndServices as $timesheetProductAndService) {
                $product->fetch('', dol_sanitizeFileName(dol_string_nospecial(trim($langs->transnoentities($timesheetProductAndService['name'])))));
                dolibarr_set_const($this->db, $timesheetProductAndService['code'], $product->id, 'integer', 0, '', $conf->entity);
            }

            dolibarr_set_const($this->db, 'DOLISIRH_BACKWARD_COMPATIBILITY_PRODUCT_SERVICE', 1, 'integer', 0, '', $conf->entity);
        }

        return $this->_init([], $options);
    }

    /**
     * Function called when module is disabled.
     * Remove from database constants, boxes and permissions from Dolibarr database.
     * Data directories are not deleted.
     *
     * @param  string $options Options when enabling module ('', 'noboxes').
     * @return int             1 if OK, 0 if KO.
     */
    public function remove($options = ''): int
    {
        $sql = [];
        return $this->_remove($sql, $options);
    }
}
