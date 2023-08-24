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
 * \file    class/timesheet.class.php
 * \ingroup dolisirh
 * \brief   This file is a CRUD class file for TimeSheet (Create/Read/Update/Delete).
 */

// Load Saturne libraries.
require_once __DIR__ . '/../../saturne/class/saturneobject.class.php';
require_once __DIR__ . '/../../saturne/class/saturnesignature.class.php';

/**
 * Class for TimeSheet.
 */
class TimeSheet extends SaturneObject
{
    /**
     * @var string Module name.
     */
    public $module = 'dolisirh';

    /**
     * @var string Element type of object.
     */
    public $element = 'timesheet';

    /**
     * @var string Name of table without prefix where object is stored. This is also the key used for extrafields management.
     */
    public $table_element = 'dolisirh_timesheet';

    /**
     * @var int Does this object support multicompany module ?
     * 0 = No test on entity, 1 = Test with field entity, 'field@table' = Test with link by field@table.
     */
    public $ismultientitymanaged = 1;

    /**
     * @var int Does object support extrafields ? 0 = No, 1 = Yes.
     */
    public int $isextrafieldmanaged = 1;

    /**
     * @var string Name of icon for timesheet. Must be a 'fa-xxx' fontawesome code (or 'fa-xxx_fa_color_size') or 'timesheet@dolisirh' if picto is file 'img/object_timesheet.png'.
     */
    public string $picto = 'fontawesome_fa-calendar-check_fas_#d35968';

    public const STATUS_DELETED   = -1;
    public const STATUS_DRAFT     = 0;
    public const STATUS_VALIDATED = 1;
    public const STATUS_LOCKED    = 2;
    public const STATUS_ARCHIVED  = 3;

    /**
     * 'type' field format:
     *      'integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter[:Sortfield]]]',
     *      'select' (list of values are in 'options'),
     *      'sellist:TableName:LabelFieldName[:KeyFieldName[:KeyFieldParent[:Filter[:Sortfield]]]]',
     *      'chkbxlst:...',
     *      'varchar(x)',
     *      'text', 'text:none', 'html',
     *      'double(24,8)', 'real', 'price',
     *      'date', 'datetime', 'timestamp', 'duration',
     *      'boolean', 'checkbox', 'radio', 'array',
     *      'mail', 'phone', 'url', 'password', 'ip'
     *      Note: Filter can be a string like "(t.ref:like:'SO-%') or (t.date_creation:<:'20160101') or (t.nature:is:NULL)"
     * 'label' the translation key.
     * 'picto' is code of a picto to show before value in forms
     * 'enabled' is a condition when the field must be managed (Example: 1 or '$conf->global->MY_SETUP_PARAM' or '!empty($conf->multicurrency->enabled)' ...)
     * 'position' is the sort order of field.
     * 'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty '' or 0.
     * 'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). 5=Visible on list and view only (not create/not update). Using a negative value means field is not shown by default on list but can be selected for viewing)
     * 'noteditable' says if field is not editable (1 or 0)
     * 'default' is a default value for creation (can still be overwroted by the Setup of Default Values if field is editable in creation form). Note: If default is set to '(PROV)' and field is 'ref', the default value will be set to '(PROVid)' where id is rowid when a new record is created.
     * 'index' if we want an index in database.
     * 'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
     * 'searchall' is 1 if we want to search in this field when making a search from the quick search button.
     * 'isameasure' must be set to 1 or 2 if field can be used for measure. Field type must be summable like integer or double(24,8). Use 1 in most cases, or 2 if you don't want to see the column total into list (for example for percentage)
     * 'css' and 'cssview' and 'csslist' is the CSS style to use on field. 'css' is used in creation and update. 'cssview' is used in view mode. 'csslist' is used for columns in lists. For example: 'css'=>'minwidth300 maxwidth500 widthcentpercentminusx', 'cssview'=>'wordbreak', 'csslist'=>'tdoverflowmax200'
     * 'help' is a 'TranslationString' to use to show a tooltip on field. You can also use 'TranslationString:keyfortooltiponlick' for a tooltip on click.
     * 'showoncombobox' if value of the field must be visible into the label of the combobox that list record
     * 'disabled' is 1 if we want to have the field locked by a 'disabled' attribute. In most cases, this is never set into the definition of $fields into class, but is set dynamically by some part of code.
     * 'arrayofkeyval' to set a list of values if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel"). Note that type can be 'integer' or 'varchar'
     * 'autofocusoncreate' to have field having the focus on a create form. Only 1 field should have this property set to 1.
     * 'comment' is not used. You can store here any text of your choice. It is not used by application.
     * 'validate' is 1 if you need to validate with $this->validateField()
     * 'copytoclipboard' is 1 or 2 to allow to add a picto to copy value into clipboard (1=picto after label, 2=picto after value)
     *
     * Note: To have value dynamic, you can set value to 0 in definition and edit the value on the fly into the constructor.
     */

    /**
     * @var array Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
     */
    public $fields = [
        'rowid'          => ['type' => 'integer',      'label' => 'TechnicalID',      'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1, 'comment' => 'Id'],
        'ref'            => ['type' => 'varchar(128)', 'label' => 'Ref',              'enabled' => 1, 'position' => 10,  'notnull' => 1, 'visible' => 4, 'noteditable' => 1, 'default' => '(PROV)', 'index' =>1, 'searchall' => 1, 'showoncombobox' => 1, 'validate' => 1, 'comment' => 'Reference of object'],
        'ref_ext'        => ['type' => 'varchar(128)', 'label' => 'RefExt',           'enabled' => 1, 'position' => 20,  'notnull' => 0, 'visible' => 0],
        'entity'         => ['type' => 'integer',      'label' => 'Entity',           'enabled' => 1, 'position' => 30,  'notnull' => 1, 'visible' => 0, 'index' => 1],
        'date_creation'  => ['type' => 'datetime',     'label' => 'DateCreation',     'enabled' => 1, 'position' => 40,  'notnull' => 1, 'visible' => 0],
        'tms'            => ['type' => 'timestamp',    'label' => 'DateModification', 'enabled' => 1, 'position' => 50,  'notnull' => 0, 'visible' => 0],
        'import_key'     => ['type' => 'varchar(14)',  'label' => 'ImportId',         'enabled' => 1, 'position' => 60,  'notnull' => 0, 'visible' => 0],
        'status'         => ['type' => 'smallint',     'label' => 'Status',           'enabled' => 1, 'position' => 180, 'notnull' => 1, 'visible' => 2, 'default' => 0, 'index' => 1, 'arrayofkeyval' => [0 => 'StatusDraft', 1 => 'ValidatePendingSignature', 2 => 'Locked', 3 => 'Archived']],
        'label'          => ['type' => 'varchar(255)', 'label' => 'Label',            'enabled' => 1, 'position' => 70,  'notnull' => 1, 'visible' => 1, 'searchall' => 1, 'css' => 'minwidth300', 'cssview' => 'wordbreak', 'showoncombobox' => 2, 'validate' => 1, 'autofocusoncreate' => 1],
        'date_start'     => ['type' => 'date',         'label' => 'DateStart',        'enabled' => 1, 'position' => 100, 'notnull' => 1, 'visible' => 1],
        'date_end'       => ['type' => 'date',         'label' => 'DateEnd',          'enabled' => 1, 'position' => 110, 'notnull' => 1, 'visible' => 1],
        'description'    => ['type' => 'html',         'label' => 'Description',      'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => 3, 'validate' => 1],
        'note_public'    => ['type' => 'html',         'label' => 'NotePublic',       'enabled' => 1, 'position' => 140, 'notnull' => 0, 'visible' => 0, 'cssview' => 'wordbreak', 'validate' => 1],
        'note_private'   => ['type' => 'html',         'label' => 'NotePrivate',      'enabled' => 1, 'position' => 150, 'notnull' => 0, 'visible' => 0, 'cssview' => 'wordbreak', 'validate' => 1],
        'fk_user_creat'  => ['type' => 'integer:User:user/class/user.class.php',                                                          'label' => 'UserAuthor', 'picto' => 'user',    'enabled' => 1,                         'position' => 160, 'notnull' => 1, 'visible' => 0, 'foreignkey' => 'user.rowid'],
        'fk_user_modif'  => ['type' => 'integer:User:user/class/user.class.php',                                                          'label' => 'UserModif',  'picto' => 'user',    'enabled' => 1,                         'position' => 170, 'notnull' => 0, 'visible' => 0, 'foreignkey' => 'user.rowid'],
        'fk_user_assign' => ['type' => 'integer:User:user/class/user.class.php:1:(t.employee:=:1:AND:t.fk_soc:IS:NULL:AND:t.statut:=:1)', 'label' => 'UserAssign', 'picto' => 'user',    'enabled' => 1,                         'position' => 90,  'notnull' => 1, 'visible' => 1, 'index' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'validate' => 1, 'foreignkey' => 'user.rowid'],
        'fk_project'     => ['type' => 'integer:Project:projet/class/project.class.php:1',                                                'label' => 'Project',    'picto' => 'project', 'enabled' => '$conf->project->enabled', 'position' => 80,  'notnull' => 0, 'visible' => 1, 'index' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'validate' => 1, 'foreignkey' => 'projet.rowid'],
        'fk_soc'         => ['type' => 'integer:Societe:societe/class/societe.class.php:1',                                               'label' => 'ThirdParty', 'picto' => 'company', 'enabled' => '$conf->societe->enabled', 'position' => 120, 'notnull' => 0, 'visible' => 1, 'index' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'validate' => 1, 'foreignkey' => 'societe.rowid'],
    ];

    /**
     * @var int ID.
     */
    public int $rowid;

    /**
     * @var string Ref.
     */
    public $ref;

    /**
     * @var string Ref ext.
     */
    public $ref_ext;

    /**
     * @var int Entity.
     */
    public $entity;

    /**
     * @var int|string Creation date.
     */
    public $date_creation;

    /**
     * @var int|string Timestamp.
     */
    public $tms;

    /**
     * @var string Import key.
     */
    public $import_key;

    /**
     * @var int Status.
     */
    public $status;

    /**
     * @var string label.
     */
    public string $label;

    /**
     * @var int|string Date start.
     */
    public $date_start;

    /**
     * @var int|string Date end.
     */
    public $date_end;

    /**
     * @var string Description.
     */
    public string $description;

    /**
     * @var string Public note.
     */
    public $note_public;

    /**
     * @var string Private note.
     */
    public $note_private;

    /**
     * @var int User ID.
     */
    public int $fk_user_creat;

    /**
     * @var int|null User ID.
     */
    public ?int $fk_user_modif;

    /**
     * @var int|string|null Project ID.
     */
    public $fk_project;

    /**
     * @var int|string|null Thirdparty ID.
     */
    public $fk_soc;

    /**
     * @var int User assign ID.
     */
    public int $fk_user_assign;

    /**
     * @var string Name of subtable line.
     */
    public $table_element_line = 'dolisirh_timesheetdet';

    /**
     * @var string Field with ID of parent key if this object has a parent.
     */
    public $fk_element = 'fk_timesheet';

    /**
     * @var string Name of subtable class that manage subtable lines.
     */
    public string $class_element_line = 'TimeSheetline';

    /**
     * @var array List of child tables. To test if we can delete object.
     */
    protected $childtables = [];

    /**
     * @var array List of child tables. To know object to delete on cascade.
     *            If name matches @ClassNAme:FilePathClass;ParentFkFieldName it will
     *            call method deleteByParentField(parentId, ParentFkFieldName) to fetch and delete child object.
     */
    protected $childtablesoncascade = ['dolisirh_timesheetdet'];

    /**
     * @var TimeSheetLine[] Array of subtable lines.
     */
    public $lines = [];

    /**
     * Constructor.
     *
     * @param DoliDb $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        parent::__construct($db, $this->module, $this->element);
    }

    /**
     * Delete a line of object in database.
     *
     * @param  User $user      User that delete.
     * @param  int  $idline    ID of line to delete.
     * @param  bool $notrigger false = launch triggers after, true = disable triggers.
     * @return int             >0 if OK, <0 if KO.
     */
    public function deleteLine(User $user, int $idline, bool $notrigger = false): int
    {
        if ($this->status < self::STATUS_DRAFT) {
            $this->error = 'ErrorDeleteLineNotAllowedByObjectStatus';
            return -2;
        }

        return $this->deleteLineCommon($user, $idline, $notrigger);
    }

    /**
     * Set draft status.
     *
     * @param  User      $user      Object user that modify.
     * @param  int       $notrigger 1 = Does not execute triggers, 0 = Execute triggers.
     * @return int                  0 < if KO, >0 if OK.
     * @throws Exception
     */
    public function setDraft(User $user, int $notrigger = 0): int
    {
        $signatory = new SaturneSignature($this->db, 'dolisirh', 'timesheet');
        $signatory->deleteSignatoriesSignatures($this->id, 'timesheet');

        return parent::setDraft($user, $notrigger);
    }

    /**
     * Return the status.
     *
     * @param  int    $status ID status.
     * @param  int    $mode   0 = long label, 1 = short label, 2 = Picto + short label, 3 = Picto, 4 = Picto + long label, 5 = Short label + Picto, 6 = Long label + Picto.
     * @return string         Label of status.
     */
    public function LibStatut(int $status, int $mode = 0): string
    {
        if (empty($this->labelStatus) || empty($this->labelStatusShort)) {
            global $langs;

            $this->labelStatus[self::STATUS_DELETED]   = $langs->transnoentitiesnoconv('Deleted');
            $this->labelStatus[self::STATUS_DRAFT]     = $langs->transnoentitiesnoconv('StatusDraft');
            $this->labelStatus[self::STATUS_VALIDATED] = $langs->transnoentitiesnoconv('ValidatePendingSignature');
            $this->labelStatus[self::STATUS_LOCKED]    = $langs->transnoentitiesnoconv('Locked');
            $this->labelStatus[self::STATUS_ARCHIVED]  = $langs->transnoentitiesnoconv('Archived');

            $this->labelStatusShort[self::STATUS_DELETED]   = $langs->transnoentitiesnoconv('Deleted');
            $this->labelStatusShort[self::STATUS_DRAFT]     = $langs->transnoentitiesnoconv('StatusDraft');
            $this->labelStatusShort[self::STATUS_VALIDATED] = $langs->transnoentitiesnoconv('ValidatePendingSignature');
            $this->labelStatusShort[self::STATUS_LOCKED]    = $langs->transnoentitiesnoconv('Locked');
            $this->labelStatusShort[self::STATUS_ARCHIVED]  = $langs->transnoentitiesnoconv('Archived');
        }

        $statusType = 'status' . $status;
        if ($status == self::STATUS_VALIDATED) {
            $statusType = 'status3';
        }
        if ($status == self::STATUS_LOCKED || $status == self::STATUS_ARCHIVED) {
            $statusType = 'status8';
        }
        if ($status == self::STATUS_DELETED) {
            $statusType = 'status9';
        }

        return dolGetStatus($this->labelStatus[$status], $this->labelStatusShort[$status], '', $statusType, $mode);
    }

    /**
     * Create an array of lines.
     *
     * @return array|int array of lines if OK, <0 if KO.
     * @throws Exception
     */
    public function getLinesArray()
    {
        $this->lines = [];

        $objectLine = new TimeSheetLine($this->db);
        $result = $objectLine->fetchAll('ASC', 'rang', 0, 0, ['customsql'=>'fk_timesheet = '. $this->id]);

        if (is_numeric($result)) {
            $this->error = $objectLine->error;
            $this->errors = $objectLine->errors;
            return $result;
        } else {
            $this->lines = $result;
            return $this->lines;
        }
    }
}

/**
 * Class TimeSheetLine.
 */
class TimeSheetLine extends SaturneObject
{
    /**
     * @var string Module name.
     */
    public $module = 'dolisirh';

    /**
     * @var string Element type of object.
     */
    public $element = 'fk_timesheetdet';

    /**
     * @var string Name of table without prefix where object is stored. This is also the key used for extrafields management.
     */
    public $table_element = 'dolisirh_timesheetdet';

    /**
     * @var int Does this object support multicompany module ?
     * 0 = No test on entity, 1 = Test with field entity, 'field@table' = Test with link by field@table.
     */
    public $ismultientitymanaged = 0;

    /**
     * @var int Does object support extrafields ? 0 = No, 1 = Yes.
     */
    public int $isextrafieldmanaged = 1;

    /**
     * 'type' field format:
     *      'integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter[:Sortfield]]]',
     *      'select' (list of values are in 'options'),
     *      'sellist:TableName:LabelFieldName[:KeyFieldName[:KeyFieldParent[:Filter[:Sortfield]]]]',
     *      'chkbxlst:...',
     *      'varchar(x)',
     *      'text', 'text:none', 'html',
     *      'double(24,8)', 'real', 'price',
     *      'date', 'datetime', 'timestamp', 'duration',
     *      'boolean', 'checkbox', 'radio', 'array',
     *      'mail', 'phone', 'url', 'password', 'ip'
     *      Note: Filter can be a string like "(t.ref:like:'SO-%') or (t.date_creation:<:'20160101') or (t.nature:is:NULL)"
     * 'label' the translation key.
     * 'picto' is code of a picto to show before value in forms
     * 'enabled' is a condition when the field must be managed (Example: 1 or '$conf->global->MY_SETUP_PARAM' or '!empty($conf->multicurrency->enabled)' ...)
     * 'position' is the sort order of field.
     * 'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty '' or 0.
     * 'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). 5=Visible on list and view only (not create/not update). Using a negative value means field is not shown by default on list but can be selected for viewing)
     * 'noteditable' says if field is not editable (1 or 0)
     * 'default' is a default value for creation (can still be overwroted by the Setup of Default Values if field is editable in creation form). Note: If default is set to '(PROV)' and field is 'ref', the default value will be set to '(PROVid)' where id is rowid when a new record is created.
     * 'index' if we want an index in database.
     * 'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
     * 'searchall' is 1 if we want to search in this field when making a search from the quick search button.
     * 'isameasure' must be set to 1 or 2 if field can be used for measure. Field type must be summable like integer or double(24,8). Use 1 in most cases, or 2 if you don't want to see the column total into list (for example for percentage)
     * 'css' and 'cssview' and 'csslist' is the CSS style to use on field. 'css' is used in creation and update. 'cssview' is used in view mode. 'csslist' is used for columns in lists. For example: 'css'=>'minwidth300 maxwidth500 widthcentpercentminusx', 'cssview'=>'wordbreak', 'csslist'=>'tdoverflowmax200'
     * 'help' is a 'TranslationString' to use to show a tooltip on field. You can also use 'TranslationString:keyfortooltiponlick' for a tooltip on click.
     * 'showoncombobox' if value of the field must be visible into the label of the combobox that list record
     * 'disabled' is 1 if we want to have the field locked by a 'disabled' attribute. In most cases, this is never set into the definition of $fields into class, but is set dynamically by some part of code.
     * 'arrayofkeyval' to set a list of values if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel"). Note that type can be 'integer' or 'varchar'
     * 'autofocusoncreate' to have field having the focus on a create form. Only 1 field should have this property set to 1.
     * 'comment' is not used. You can store here any text of your choice. It is not used by application.
     * 'validate' is 1 if you need to validate with $this->validateField()
     * 'copytoclipboard' is 1 or 2 to allow to add a picto to copy value into clipboard (1=picto after label, 2=picto after value)
     *
     * Note: To have value dynamic, you can set value to 0 in definition and edit the value on the fly into the constructor.
     */

    /**
     * @var array Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
     */
    public array $fields = [
        'rowid'          => ['type' => 'integer',  'label' => 'TechnicalID',  'enabled' => 1, 'position' => 1,  'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1, 'css' => 'left', 'comment' => 'Id'],
        'entity'         => ['type' => 'integer',  'label' => 'Entity',       'enabled' => 1, 'position' => 5,  'notnull' => 1, 'visible' => 0],
        'date_creation'  => ['type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 0],
        'qty'            => ['type' => 'real',     'label' => 'Quantity',     'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 0],
        'rang'           => ['type' => 'integer',  'label' => 'Order',        'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 0, 'default' => 0],
        'description'    => ['type' => 'html',     'label' => 'Description',  'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 0],
        'product_type'   => ['type' => 'integer',  'label' => 'ProductType',  'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 0,'default' => 0],
        'fk_timesheet'   => ['type' => 'integer:TimeSheet:custom/dolisirh/class/timesheet.class.php:1', 'label' => 'TimeSheet',         'enabled' => 1, 'position' => 60, 'notnull' => 1, 'visible' => 0],
        'fk_product'     => ['type' => 'integer:Product:product/class/product.class.php:1',             'label' => 'Product',           'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 0],
        'fk_parent_line' => ['type' => 'integer:Product:product/class/product.class.php:1',             'label' => 'ParentProductLine', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => 0],
    ];

    /**
     * @var int ID.
     */
    public int $rowid;

    /**
     * @var int Entity.
     */
    public $entity;

    /**
     * @var int|string Creation date.
     */
    public $date_creation;

    /**
     * @var float|null Quantity.
     */
    public ?float $qty;

    /**
     * @var int|null Rang.
     */
    public ?int $rang;

    /**
     * @var string Description.
     */
    public string $description = '';

    /**
     * @var int|null Product type.
     */
    public ?int $product_type;

    /**
     * @var int Timesheet ID.
     */
    public int $fk_timesheet;

    /**
     * @var int|null Product ID.
     */
    public ?int $fk_product;

    /**
     * @var int|null Parent line ID.
     */
    public ?int $fk_parent_line;

    /**
     * Constructor.
     *
     * @param DoliDb $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        parent::__construct($db, $this->module, $this->element);
    }
}
