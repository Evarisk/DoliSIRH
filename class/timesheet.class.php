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
 * \file        class/timesheet.class.php
 * \ingroup     dolisirh
 * \brief       This file is a CRUD class file for TimeSheet (Create/Read/Update/Delete)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

require_once __DIR__ . '/dolisirhsignature.class.php';

/**
 * Class for TimeSheet
 */
class TimeSheet extends CommonObject
{
	/**
	 * @var string ID of module.
	 */
	public $module = 'dolisirh';

	/**
	 * @var string ID to identify managed object.
	 */
	public $element = 'timesheet';

	/**
	 * @var string Name of table without prefix where object is stored. This is also the key used for extrafields management.
	 */
	public $table_element = 'dolisirh_timesheet';

	/**
	 * @var int  Does this object support multicompany module ?
	 * 0=No test on entity, 1=Test with field entity, 'field@table'=Test with link by field@table
	 */
	public $ismultientitymanaged = 1;

	/**
	 * @var int  Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 1;

	/**
	 * @var string String with name of icon for timesheet. Must be the part after the 'object_' into object_timesheet.png
	 */
	public $picto = 'timesheet@dolisirh';

	/**
	 * @var array Label status of const.
	 */
	public $labelStatus;

	/**
	 * @var array Label status short of const.
	 */
	public $labelStatusShort;

	const STATUS_DRAFT = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_LOCKED = 2;
	const STATUS_ARCHIVED = 3;

	/**
	 *  'type' field format ('integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter[:Sortfield]]]', 'sellist:TableName:LabelFieldName[:KeyFieldName[:KeyFieldParent[:Filter[:Sortfield]]]]', 'varchar(x)', 'double(24,8)', 'real', 'price', 'text', 'text:none', 'html', 'date', 'datetime', 'timestamp', 'duration', 'mail', 'phone', 'url', 'password')
	 *         Note: Filter can be a string like "(t.ref:like:'SO-%') or (t.date_creation:<:'20160101') or (t.nature:is:NULL)"
	 *  'label' the translation key.
	 *  'picto' is code of a picto to show before value in forms
	 *  'enabled' is a condition when the field must be managed (Example: 1 or '$conf->global->MY_SETUP_PARAM)
	 *  'position' is the sort order of field.
	 *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
	 *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). 5=Visible on list and view only (not create/not update). Using a negative value means field is not shown by default on list but can be selected for viewing)
	 *  'noteditable' says if field is not editable (1 or 0)
	 *  'default' is a default value for creation (can still be overwroted by the Setup of Default Values if field is editable in creation form). Note: If default is set to '(PROV)' and field is 'ref', the default value will be set to '(PROVid)' where id is rowid when a new record is created.
	 *  'index' if we want an index in database.
	 *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
	 *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
	 *  'isameasure' must be set to 1 or 2 if field can be used for measure. Field type must be summable like integer or double(24,8). Use 1 in most cases, or 2 if you don't want to see the column total into list (for example for percentage)
	 *  'css' and 'cssview' and 'csslist' is the CSS style to use on field. 'css' is used in creation and update. 'cssview' is used in view mode. 'csslist' is used for columns in lists. For example: 'css'=>'minwidth300 maxwidth500 widthcentpercentminusx', 'cssview'=>'wordbreak', 'csslist'=>'tdoverflowmax200'
	 *  'help' is a 'TranslationString' to use to show a tooltip on field. You can also use 'TranslationString:keyfortooltiponlick' for a tooltip on click.
	 *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
	 *  'disabled' is 1 if we want to have the field locked by a 'disabled' attribute. In most cases, this is never set into the definition of $fields into class, but is set dynamically by some part of code.
	 *  'arrayofkeyval' to set a list of values if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel"). Note that type can be 'integer' or 'varchar'
	 *  'autofocusoncreate' to have field having the focus on a create form. Only 1 field should have this property set to 1.
	 *  'comment' is not used. You can store here any text of your choice. It is not used by application.
	 *	'validate' is 1 if you need to validate with $this->validateField()
	 *  'copytoclipboard' is 1 or 2 to allow to add a picto to copy value into clipboard (1=picto after label, 2=picto after value)
	 *
	 *  Note: To have value dynamic, you can set value to 0 in definition and edit the value on the fly into the constructor.
	 */

	/**
	 * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields = array(
		'rowid'          => array('type'=>'integer', 'label'=>'TechnicalID', 'enabled'=>'1', 'position'=>1, 'notnull'=>1, 'visible'=>0, 'noteditable'=>'1', 'index'=>1, 'css'=>'left', 'comment'=>"Id"),
		'ref'            => array('type'=>'varchar(128)', 'label'=>'Ref', 'enabled'=>'1', 'position'=>10, 'notnull'=>1, 'visible'=>4, 'noteditable'=>'1', 'default'=>'(PROV)', 'index'=>1, 'searchall'=>1, 'showoncombobox'=>'1', 'validate'=>'1', 'comment'=>"Reference of object"),
		'ref_ext'        => array('type'=>'varchar(128)', 'label'=>'RefExt', 'enabled'=>'1', 'position'=>20, 'notnull'=>0, 'visible'=>0,),
		'entity'         => array('type'=>'integer', 'label'=>'Entity', 'enabled'=>'1', 'position'=>30,'notnull'=>1, 'visible'=>0,),
		'date_creation'  => array('type'=>'datetime', 'label'=>'DateCreation', 'enabled'=>'1', 'position'=>40, 'notnull'=>1, 'visible'=>0,),
		'tms'            => array('type'=>'timestamp', 'label'=>'DateModification', 'enabled'=>'1', 'position'=>50, 'notnull'=>0, 'visible'=>0,),
		'import_key'     => array('type'=>'varchar(14)', 'label'=>'ImportId', 'enabled'=>'1', 'position'=>60, 'notnull'=>-1, 'visible'=>0,),
		'status'         => array('type'=>'integer', 'label'=>'Status', 'enabled'=>'1', 'position'=>70, 'notnull'=>1, 'visible'=>2, 'arrayofkeyval'=>array('0'=>'Draft', '1'=>'Validate', '2'=>'Locked', '3'=>'Archived'),),
		'label'          => array('type'=>'varchar(255)', 'label'=>'Label', 'enabled'=>'1', 'position'=>80, 'notnull'=>0, 'visible'=>1, 'searchall'=>1, 'css'=>'maxwidth500 widthcentpercentminusxx', 'cssview'=>'wordbreak', 'showoncombobox'=>'2',),
		'date_start'     => array('type'=>'date', 'label'=>'DateStart', 'enabled'=>'1', 'position'=>90, 'notnull'=>1, 'visible'=>1,),
		'date_end'       => array('type'=>'date', 'label'=>'DateEnd', 'enabled'=>'1', 'position'=>100, 'notnull'=>1, 'visible'=>1,),
		'description'    => array('type'=>'html', 'label'=>'Description', 'enabled'=>'1', 'position'=>110, 'notnull'=>0, 'visible'=>3,),
		'note_public'    => array('type'=>'html', 'label'=>'NotePublic', 'enabled'=>'1', 'position'=>120, 'notnull'=>0, 'visible'=>0,),
		'note_private'   => array('type'=>'html', 'label'=>'NotePrivate', 'enabled'=>'1', 'position'=>130, 'notnull'=>0, 'visible'=>0,),
		'last_main_doc'  => array('type'=>'varchar(255)', 'label'=>'LastMainDoc', 'enabled'=>'1', 'position'=>140, 'notnull'=>0, 'visible'=>0,),
		'model_pdf'      => array('type'=>'varchar(255)', 'label'=>'ModelPdf', 'enabled'=>'1', 'position'=>150, 'notnull'=>-1, 'visible'=>0,),
		'model_odt'      => array('type'=>'varchar(255)', 'label'=>'ModelOdt', 'enabled'=>'1', 'position'=>160, 'notnull'=>-1, 'visible'=>0,),
		'fk_user_creat'  => array('type'=>'integer:User:user/class/user.class.php', 'label'=>'UserAuthor', 'enabled'=>'1', 'position'=>170, 'notnull'=>1, 'visible'=>0, 'foreignkey'=>'user.rowid',),
		'fk_user_modif'  => array('type'=>'integer:User:user/class/user.class.php', 'label'=>'UserModif', 'enabled'=>'1', 'position'=>180, 'notnull'=>-1, 'visible'=>0,),
		'fk_project'     => array('type'=>'integer:Project:projet/class/project.class.php:1', 'label'=>'Project', 'enabled'=>'1', 'position'=>82, 'notnull'=>-1, 'visible'=>3, 'index'=>1, 'css'=>'maxwidth500 widthcentpercentminusxx',),
		'fk_societe'     => array('type'=>'integer:Societe:societe/class/societe.class.php:1', 'label'=>'ThirdParty', 'enabled'=>'1', 'position'=>101, 'notnull'=>-1, 'visible'=>3, 'index'=>1, 'css'=>'maxwidth500 widthcentpercentminusxx',),
		'fk_user_assign' => array('type'=>'integer:User:user/class/user.class.php:1:t.fk_soc IS NULL', 'label'=>'UserAssign', 'enabled'=>'1', 'position'=>85, 'notnull'=>1, 'visible'=>1, 'index'=>1, 'css'=>'maxwidth500 widthcentpercentminusxx',),
	);

	public $rowid;
	public $ref;
	public $ref_ext;
	public $entity;
	public $date_creation;
	public $tms;
	public $import_key;
	public $status;
	public $label;
	public $date_start;
	public $date_end;
	public $description;
	public $note_public;
	public $note_private;
	public $last_main_doc;
	public $model_pdf;
	public $model_odt;
	public $fk_user_creat;
	public $fk_user_modif;
	public $fk_project;
	public $fk_societe;
	public $fk_user_assign;

	// If this object has a subtable with lines

	 /**
	  * @var string    Name of subtable line
	  */
	 public $table_element_line = 'dolisirh_timesheetdet';

	 /**
	  * @var string    Field with ID of parent key if this object has a parent
	  */
	 public $fk_element = 'fk_timesheet';

	 /**
	  * @var string    Name of subtable class that manage subtable lines
	  */
	 public $class_element_line = 'TimeSheetline';

	 /**
	  * @var array	List of child tables. To test if we can delete object.
	  */
	 protected $childtables = array();

	 /**
	  * @var array    List of child tables. To know object to delete on cascade.
	  *               If name matches @ClassNAme:FilePathClass;ParentFkFieldName it will
	  *               call method deleteByParentField(parentId, ParentFkFieldName) to fetch and delete child object
	  */
	 protected $childtablesoncascade = array('dolisirh_timesheetdet');

	 /**
	  * @var TimeSheetLine[]     Array of subtable lines
	  */
	 public $lines = array();


	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $conf, $langs;

		$this->db = $db;

		if (empty($conf->global->MAIN_SHOW_TECHNICAL_ID) && isset($this->fields['rowid'])) {
			$this->fields['rowid']['visible'] = 0;
		}
		if (empty($conf->multicompany->enabled) && isset($this->fields['entity'])) {
			$this->fields['entity']['enabled'] = 0;
		}

		// Unset fields that are disabled
		foreach ($this->fields as $key => $val) {
			if (isset($val['enabled']) && empty($val['enabled'])) {
				unset($this->fields[$key]);
			}
		}

		// Translate some data of arrayofkeyval
		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
	}

	/**
	 * Create object into database
	 *
	 * @param  User $user      User that creates
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             0 < if KO, ID of created object if OK
	 */
	public function create(User $user, bool $notrigger = false): int
	{
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param  int         $id  ID object
	 * @param  string|null $ref Ref
	 * @return int              0 < if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, string $ref = null): int
	{
		$result = $this->fetchCommon($id, $ref);
		if ($result > 0 && !empty($this->table_element_line)) {
			$this->fetchLines();
		}
		return $result;
	}

	/**
	 * Load object lines in memory from the database
	 *
	 * @return int 0 < if KO, 0 if not found, >0 if OK
	 */
	public function fetchLines(): int
	{
		$this->lines = array();
		return $this->fetchLinesCommon();
	}

	/**
	 * Load list of objects in memory from the database.
	 *
	 * @param  string      $sortorder  Sort Order
	 * @param  string      $sortfield  Sort field
	 * @param  int         $limit      Limit
	 * @param  int         $offset     Offset
	 * @param  array       $filter     Filter array. Example array('field'=>'value', 'customurl'=>...)
	 * @param  string      $filtermode Filter mode (AND/OR)
	 * @return array|int               int <0 if KO, array of pages if OK
	 * @throws Exception
	 */
	public function fetchAll(string $sortorder = '', string $sortfield = '', int $limit = 0, int $offset = 0, array $filter = array(), string $filtermode = 'AND')
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$records = array();

		$sql = "SELECT ";
		$sql .= $this->getFieldList('t');
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t";
		if (isset($this->ismultientitymanaged) && $this->ismultientitymanaged == 1) {
			$sql .= " WHERE t.entity IN (".getEntity($this->element).")";
		} else {
			$sql .= " WHERE 1 = 1";
		}
		// Manage filter
		$sqlwhere = array();
		if (count($filter) > 0) {
			foreach ($filter as $key => $value) {
				if ($key == 't.rowid') {
					$sqlwhere[] = $key." = ".((int) $value);
				} elseif (in_array($this->fields[$key]['type'], array('date', 'datetime', 'timestamp'))) {
					$sqlwhere[] = $key." = '".$this->db->idate($value)."'";
				} elseif ($key == 'customsql') {
					$sqlwhere[] = $value;
				} elseif (strpos($value, '%') === false) {
					$sqlwhere[] = $key." IN (".$this->db->sanitize($this->db->escape($value)).")";
				} else {
					$sqlwhere[] = $key." LIKE '%".$this->db->escape($value)."%'";
				}
			}
		}
		if (count($sqlwhere) > 0) {
			$sql .= " AND (".implode(" ".$filtermode." ", $sqlwhere).")";
		}

		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}
		if (!empty($limit)) {
			$sql .= $this->db->plimit($limit, $offset);
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < ($limit ? min($limit, $num) : $num)) {
				$obj = $this->db->fetch_object($resql);

				$record = new self($this->db);
				$record->setVarsFromFetchObj($obj);

				$records[$record->id] = $record;

				$i++;
			}
			$this->db->free($resql);

			return $records;
		} else {
			$this->errors[] = 'Error '.$this->db->lasterror();
			dol_syslog(__METHOD__.' '.join(',', $this->errors), LOG_ERR);

			return -1;
		}
	}

	/**
	 * Update object into database
	 *
	 * @param  User $user      User that modifies
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             0 < if KO, >0 if OK
	 */
	public function update(User $user, bool $notrigger = false): int
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object in database
	 *
	 * @param  User $user      User that deletes
	 * @param  bool $notrigger false=launch triggers after, true=disable triggers
	 * @return int             0 < if KO, >0 if OK
	 */
	public function delete(User $user, bool $notrigger = false): int
	{
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 *  Delete a line of object in database
	 *
	 *	@param  User $user      User that delete
	 *  @param  int  $idline	ID of line to delete
	 *  @param  bool $notrigger false=launch triggers after, true=disable triggers
	 *  @return int             >0 if OK, <0 if KO
	 */
	public function deleteLine(User $user, int $idline, bool $notrigger = false): int
	{
		if ($this->status < 0) {
			$this->error = 'ErrorDeleteLineNotAllowedByObjectStatus';
			return -2;
		}

		return $this->deleteLineCommon($user, $idline, $notrigger);
	}

	/**
	 *    Validate object
	 *
	 * @param  User      $user      User making status change
	 * @param  int       $notrigger 1=Does not execute triggers, 0= execute triggers
	 * @return int                  0 < if OK, 0=Nothing done, >0 if KO
	 * @throws Exception
	 */
	public function validate(User $user, int $notrigger = 0): int
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

		$error = 0;

		// Protection
		if ($this->status == self::STATUS_VALIDATED) {
			dol_syslog(get_class($this)."::validate action abandonned: already validated", LOG_WARNING);
			return 0;
		}

		$now = dol_now();

		$this->db->begin();

		// Define new ref
		if ((preg_match('/^\(?PROV/i', $this->ref) || empty($this->ref))) { // empty should not happen, but when it occurs, the test save life
			$num = $this->getNextNumRef();
		} else {
			$num = $this->ref;
		}
		$this->newref = $num;

		if (!empty($num)) {
			// Validate
			$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element;
			$sql .= " SET ref = '".$this->db->escape($num)."',";
			$sql .= " status = ".self::STATUS_VALIDATED;
			if (!empty($this->fields['date_validation'])) {
				$sql .= ", date_validation = '".$this->db->idate($now)."'";
			}
			if (!empty($this->fields['fk_user_valid'])) {
				$sql .= ", fk_user_valid = ".((int) $user->id);
			}
			$sql .= " WHERE rowid = ".($this->id);

			dol_syslog(get_class($this)."::validate()", LOG_DEBUG);
			$resql = $this->db->query($sql);
			if (!$resql) {
				dol_print_error($this->db);
				$this->error = $this->db->lasterror();
				$error++;
			}

			if (!$error && !$notrigger) {
				// Call trigger
				$result = $this->call_trigger('TIMESHEET_VALIDATE', $user);
				if ($result < 0) {
					$error++;
				}
				// End call triggers
			}
		}

		if (!$error) {
			$this->oldref = $this->ref;

			// Rename directory if dir was a temporary ref
			if (preg_match('/^\(?PROV/i', $this->ref)) {
				// Now we rename also files into index
				$sql = 'UPDATE '.MAIN_DB_PREFIX."ecm_files set filename = CONCAT('".$this->db->escape($this->newref)."', SUBSTR(filename, ".(strlen($this->ref) + 1).")), filepath = 'timesheet/".$this->db->escape($this->newref)."'";
				$sql .= " WHERE filename LIKE '".$this->db->escape($this->ref)."%' AND filepath = 'timesheet/".$this->db->escape($this->ref)."' and entity = ".$conf->entity;
				$resql = $this->db->query($sql);
				if (!$resql) {
					$error++; $this->error = $this->db->lasterror();
				}

				// We rename directory ($this->ref = old ref, $num = new ref) in order not to lose the attachments
				$oldref = dol_sanitizeFileName($this->ref);
				$newref = dol_sanitizeFileName($num);
				$dirsource = $conf->dolisirh->dir_output.'/timesheet/'.$oldref;
				$dirdest = $conf->dolisirh->dir_output.'/timesheet/'.$newref;
				if (!$error && file_exists($dirsource)) {
					dol_syslog(get_class($this)."::validate() rename dir ".$dirsource." into ".$dirdest);

					if (@rename($dirsource, $dirdest)) {
						dol_syslog("Rename ok");
						// Rename docs starting with $oldref with $newref
						$listoffiles = dol_dir_list($conf->dolisirh->dir_output.'/timesheet/'.$newref, 'files', 1, '^'.preg_quote($oldref, '/'));
						foreach ($listoffiles as $fileentry) {
							$dirsource = $fileentry['name'];
							$dirdest = preg_replace('/^'.preg_quote($oldref, '/').'/', $newref, $dirsource);
							$dirsource = $fileentry['path'].'/'.$dirsource;
							$dirdest = $fileentry['path'].'/'.$dirdest;
							@rename($dirsource, $dirdest);
						}
					}
				}
			}
		}

		// Set new ref and current status
		if (!$error) {
			$this->ref = $num;
			$this->status = self::STATUS_VALIDATED;
		}

		if (!$error) {
			$this->db->commit();
			return 1;
		} else {
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *    Set draft status
	 *
	 * @param  User      $user      Object user that modify
	 * @param  int       $notrigger 1=Does not execute triggers, 0=Execute triggers
	 * @return int                  0 < if KO, >0 if OK
	 * @throws Exception
	 */
	public function setDraft(User $user, int $notrigger = 0): int
	{
		// Protection
		if ($this->status <= self::STATUS_DRAFT) {
			return 0;
		}

		$signatory = new TimeSheetSignature($this->db);
		$signatory->deleteSignatoriesSignatures($this->id, 'timesheet');
		return $this->setStatusCommon($user, self::STATUS_DRAFT, $notrigger, 'TIMESHEET_UNVALIDATE');
	}

	/**
	 *	Set locked status
	 *
	 *	@param  User $user	    Object user that modify
	 *  @param  int  $notrigger 1=Does not execute triggers, 0=Execute triggers
	 *	@return	int				0 < if KO, 0=Nothing done, >0 if OK
	 */
	public function setLocked(User $user, int $notrigger = 0): int
	{
		return $this->setStatusCommon($user, self::STATUS_LOCKED, $notrigger, 'TIMESHEET_LOCKED');
	}

	/**
	 *	Set archived status
	 *
	 *	@param  User $user	    Object user that modify
	 *  @param  int  $notrigger 1=Does not execute triggers, 0=Execute triggers
	 *	@return	int			    0 < if KO, >0 if OK
	 */
	public function setArchived(User $user, int $notrigger = 0): int
	{
		return $this->setStatusCommon($user, self::STATUS_ARCHIVED, $notrigger, 'TIMESHEET_ARCHIVED');
	}

	/**
	 *  Return a link to the object card (with optionaly the picto)
	 *
	 *  @param  int     $withpicto              Include picto in link (0=No picto, 1=Include picto into link, 2=Only picto)
	 *  @param  string  $option                 On what the link point to ('nolink', ...)
	 *  @param  int     $notooltip              1=Disable tooltip
	 *  @param  string  $morecss                Add more css on link
	 *  @param  int     $save_lastsearch_value -1=Auto, 0=No save of lastsearch_values when clicking, 1=Save lastsearch_values whenclicking
	 *  @return	string                          String with URL
	 */
	public function getNomUrl(int $withpicto = 0, string $option = '', int $notooltip = 0, string $morecss = '', int $save_lastsearch_value = -1): string
	{
		global $conf, $langs;

		if (!empty($conf->dol_no_mouse_hover)) {
			$notooltip = 1; // Force disable tooltips
		}

		$result = '';

		$label = '<i class="fas fa-calendar-check"></i> <u>'.$langs->trans("TimeSheet").'</u>';
		if (isset($this->status)) {
			$label .= ' '.$this->getLibStatut(5);
		}
		$label .= '<br>';
		$label .= '<b>'.$langs->trans('Ref').':</b> '.$this->ref;

		$url = dol_buildpath('/dolisirh/view/timesheet/timesheet_card.php', 1).'?id='.$this->id;

		if ($option != 'nolink') {
			// Add param to save lastsearch_values or not
			$add_save_lastsearch_values = ($save_lastsearch_value == 1 ? 1 : 0);
			if ($save_lastsearch_value == -1 && preg_match('/list\.php/', $_SERVER["PHP_SELF"])) {
				$add_save_lastsearch_values = 1;
			}
			if ($url && $add_save_lastsearch_values) {
				$url .= '&save_lastsearch_values=1';
			}
		}

		$linkclose = '';
		if (empty($notooltip)) {
			if (!empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER)) {
				$label = $langs->trans("ShowTimeSheet");
				$linkclose .= ' alt="'.dol_escape_htmltag($label, 1).'"';
			}
			$linkclose .= ' title="'.dol_escape_htmltag($label, 1).'"';
			$linkclose .= ' class="classfortooltip'.($morecss ? ' '.$morecss : '').'"';
		} else {
			$linkclose = ($morecss ? ' class="'.$morecss.'"' : '');
		}

		if ($option == 'nolink' || empty($url)) {
			$linkstart = '<span';
		} else {
			$linkstart = '<a href="'.$url.'"';
		}
		$linkstart .= $linkclose.'>';
		if ($option == 'nolink' || empty($url)) {
			$linkend = '</span>';
		} else {
			$linkend = '</a>';
		}

		$result .= $linkstart;

		if ($withpicto) $result .= '<i class="fas fa-calendar-check"></i>' . ' ';
		if ($withpicto != 2) {
			$result .= $this->ref;
		}

		$result .= $linkend;
		//if ($withpicto != 2) $result.=(($addlabel && $this->label) ? $sep . dol_trunc($this->label, ($addlabel > 1 ? $addlabel : 0)) : '');

		global $action, $hookmanager;
		$hookmanager->initHooks(array('timesheetdao'));
		$parameters = array('id'=>$this->id, 'getnomurl'=>$result);
		$reshook = $hookmanager->executeHooks('getNomUrl', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
		if ($reshook > 0) {
			$result = $hookmanager->resPrint;
		} else {
			$result .= $hookmanager->resPrint;
		}

		return $result;
	}

	/**
	 *  Return the label of the status
	 *
	 *  @param  int     $mode 0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return	string 	      Label of status
	 */
	public function getLibStatut(int $mode = 0): string
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 *  Return the status
	 *
	 *  @param  int    $status Id status
	 *  @param  int    $mode   0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto, 6=Long label + Picto
	 *  @return string 		   Label of status
	 */
	public function LibStatut(int $status, int $mode = 0): string
	{
		// phpcs:enable
		if (empty($this->labelStatus) || empty($this->labelStatusShort)) {
			global $langs;
			$langs->load("dolisirh@dolisirh");
			$this->labelStatus[self::STATUS_DRAFT]          = $langs->transnoentitiesnoconv('StatusDraft');
			$this->labelStatus[self::STATUS_VALIDATED]      = $langs->transnoentitiesnoconv('ValidatePendingSignature');
			$this->labelStatus[self::STATUS_LOCKED]         = $langs->transnoentitiesnoconv('Locked');
			$this->labelStatus[self::STATUS_ARCHIVED]       = $langs->transnoentitiesnoconv('Archived');

			$this->labelStatusShort[self::STATUS_DRAFT]     = $langs->transnoentitiesnoconv('StatusDraft');
			$this->labelStatusShort[self::STATUS_VALIDATED] = $langs->transnoentitiesnoconv('ValidatePendingSignature');
			$this->labelStatusShort[self::STATUS_LOCKED]    = $langs->transnoentitiesnoconv('Locked');
			$this->labelStatusShort[self::STATUS_ARCHIVED]  = $langs->transnoentitiesnoconv('Archived');
		}

		$statusType                                        = 'status' . $status;
		if ($status == self::STATUS_VALIDATED) $statusType = 'status3';
		if ($status == self::STATUS_LOCKED) $statusType    = 'status8';
		if ($status == self::STATUS_ARCHIVED) $statusType  = 'status8';

		return dolGetStatus($this->labelStatus[$status], $this->labelStatusShort[$status], '', $statusType, $mode);
	}

	/**
	 *	Load the info information in the object
	 *
	 *	@param  int   $id ID of object
	 *	@return	void
	 */
	public function info(int $id)
	{
		$sql = "SELECT rowid, date_creation as datec, tms as datem,";
		$sql .= " fk_user_creat, fk_user_modif";
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." as t";
		$sql .= " WHERE t.rowid = ".($id);

		$result = $this->db->query($sql);
		if ($result) {
			if ($this->db->num_rows($result)) {
				$obj = $this->db->fetch_object($result);
				$this->id = $obj->rowid;

				$this->date_creation = $this->db->jdate($obj->date_creation);
			}

			$this->db->free($result);
		} else {
			dol_print_error($this->db);
		}
	}

	/**
	 * Initialise object with example values
	 * ID must be 0 if object instance is a specimen
	 *
	 * @return void
	 */
	public function initAsSpecimen()
	{
		// Set here init that are not common fields
		// $this->property1 = ...
		// $this->property2 = ...

		$this->initAsSpecimenCommon();
	}

	/**
	 * Create an array of lines
	 *
	 * @return array|int array of lines if OK, <0 if KO
	 * @throws Exception
	 */
	public function getLinesArray()
	{
		$this->lines = array();

		$objectline = new TimeSheetLine($this->db);
		$result = $objectline->fetchAll('ASC', 'rang', 0, 0, array('customsql'=>'fk_timesheet = '.($this->id)));

		if (is_numeric($result)) {
			$this->error = $objectline->error;
			$this->errors = $objectline->errors;
			return $result;
		} else {
			$this->lines = $result;
			return $this->lines;
		}
	}

	/**
	 * Returns the reference to the following non-used object depending on the active numbering module.
	 *
	 *  @return string Object free reference
	 */
	public function getNextNumRef(): string
	{
		global $langs, $conf;
		$langs->load("dolisirh@dolisirh");

		if (empty($conf->global->DOLISIRH_TIMESHEET_ADDON)) {
			$conf->global->DOLISIRH_TIMESHEET_ADDON = 'mod_timesheet_standard';
		}

		if (!empty($conf->global->DOLISIRH_TIMESHEET_ADDON)) {
			$mybool = false;

			$file = $conf->global->DOLISIRH_TIMESHEET_ADDON.".php";
			$classname = $conf->global->DOLISIRH_TIMESHEET_ADDON;

			// Include file with class
			$dirmodels = array_merge(array('/'), $conf->modules_parts['models']);
			foreach ($dirmodels as $reldir) {
				$dir = dol_buildpath($reldir."core/modules/dolisirh/timesheet/");

				// Load file with numbering class (if found)
				$mybool |= @include_once $dir.$file;
			}

			if ($mybool === false) {
				dol_print_error('', "Failed to include file ".$file);
				return '';
			}

			if (class_exists($classname)) {
				$obj = new $classname();
				$numref = $obj->getNextValue($this);

				if ($numref != '' && $numref != '-1') {
					return $numref;
				} else {
					$this->error = $obj->error;
					//dol_print_error($this->db,get_class($this)."::getNextNumRef ".$obj->error);
					return "";
				}
			} else {
				print $langs->trans("Error")." ".$langs->trans("ClassNotFound").' '.$classname;
				return "";
			}
		} else {
			print $langs->trans("ErrorNumberingModuleNotSetup", $this->element);
			return "";
		}
	}

	/**
	 * Sets object to supplied categories.
	 *
	 * Deletes object from existing categories not supplied.
	 * Adds it to non-existing supplied categories.
	 * Existing categories are left untouched.
	 *
	 * @param  int[]|int $categories Category or categories IDs
	 * @return float|int
	 */
	public function setCategories($categories)
	{
		return parent::setCategoriesCommon($categories, 'timesheet');
	}

	/**
	 *  Create a document onto disk according to template module.
	 *
	 *  @param  string     $modele	    Force template to use ('' to not force)
	 *  @param  Translate  $outputlangs	Objet lang use for translate
	 *  @param  int        $hidedetails Hide details of lines
	 *  @param  int        $hidedesc    Hide description
	 *  @param  int        $hideref     Hide ref
	 *  @param  array|null $moreparams  Array to provide more information
	 *  @return int         		    0 if KO, 1 if OK
	 */
	public function generateDocument(string $modele, Translate $outputlangs, int $hidedetails = 0, int $hidedesc = 0, int $hideref = 0, array $moreparams = null): int
	{
		global $conf, $langs;

		$result = 0;

		$langs->load("dolisirh@dolisirh");

		if (!dol_strlen($modele)) {
			$modele = 'standard_timesheet';

			if (!empty($this->model_pdf)) {
				$modele = $this->model_pdf;
			} elseif (!empty($conf->global->TIMESHEET_ADDON_PDF)) {
				$modele = $conf->global->TIMESHEET_ADDON_PDF;
			}
		}

		$modelpath = "core/modules/dolisirh/timesheetdocument/";

		if (!empty($modele)) {
			$result = $this->commonGenerateDocument($modelpath, $modele, $outputlangs, $hidedetails, $hidedesc, $hideref, $moreparams['object']);
		}

		return $result;
	}
}

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobjectline.class.php';

/**
 * Class TimeSheetLine. You can also remove this and generate a CRUD class for lines objects.
 */
class TimeSheetLine extends CommonObjectLine
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error string
	 */
	public $error;

	/**
	 * @var int The object identifier
	 */
	public $id;

	/**
	 * @var string ID to identify managed object
	 */
	public $element = 'fk_timesheetdet';

	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'dolisirh_timesheetdet';

	/**
	 * @var int  Does object support extrafields ? 0=No, 1=Yes
	 */
	public $isextrafieldmanaged = 0;

	/**
	 *  'type' field format ('integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter[:Sortfield]]]', 'sellist:TableName:LabelFieldName[:KeyFieldName[:KeyFieldParent[:Filter[:Sortfield]]]]', 'varchar(x)', 'double(24,8)', 'real', 'price', 'text', 'text:none', 'html', 'date', 'datetime', 'timestamp', 'duration', 'mail', 'phone', 'url', 'password')
	 *         Note: Filter can be a string like "(t.ref:like:'SO-%') or (t.date_creation:<:'20160101') or (t.nature:is:NULL)"
	 *  'label' the translation key.
	 *  'picto' is code of a picto to show before value in forms
	 *  'enabled' is a condition when the field must be managed (Example: 1 or '$conf->global->MY_SETUP_PARAM)
	 *  'position' is the sort order of field.
	 *  'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty ('' or 0).
	 *  'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). 5=Visible on list and view only (not create/not update). Using a negative value means field is not shown by default on list but can be selected for viewing)
	 *  'noteditable' says if field is not editable (1 or 0)
	 *  'default' is a default value for creation (can still be overwroted by the Setup of Default Values if field is editable in creation form). Note: If default is set to '(PROV)' and field is 'ref', the default value will be set to '(PROVid)' where id is rowid when a new record is created.
	 *  'index' if we want an index in database.
	 *  'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
	 *  'searchall' is 1 if we want to search in this field when making a search from the quick search button.
	 *  'isameasure' must be set to 1 or 2 if field can be used for measure. Field type must be summable like integer or double(24,8). Use 1 in most cases, or 2 if you don't want to see the column total into list (for example for percentage)
	 *  'css' and 'cssview' and 'csslist' is the CSS style to use on field. 'css' is used in creation and update. 'cssview' is used in view mode. 'csslist' is used for columns in lists. For example: 'css'=>'minwidth300 maxwidth500 widthcentpercentminusx', 'cssview'=>'wordbreak', 'csslist'=>'tdoverflowmax200'
	 *  'help' is a 'TranslationString' to use to show a tooltip on field. You can also use 'TranslationString:keyfortooltiponlick' for a tooltip on click.
	 *  'showoncombobox' if value of the field must be visible into the label of the combobox that list record
	 *  'disabled' is 1 if we want to have the field locked by a 'disabled' attribute. In most cases, this is never set into the definition of $fields into class, but is set dynamically by some part of code.
	 *  'arrayofkeyval' to set a list of values if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel"). Note that type can be 'integer' or 'varchar'
	 *  'autofocusoncreate' to have field having the focus on a create form. Only 1 field should have this property set to 1.
	 *  'comment' is not used. You can store here any text of your choice. It is not used by application.
	 *	'validate' is 1 if you need to validate with $this->validateField()
	 *  'copytoclipboard' is 1 or 2 to allow to add a picto to copy value into clipboard (1=picto after label, 2=picto after value)
	 *
	 *  Note: To have value dynamic, you can set value to 0 in definition and edit the value on the fly into the constructor.
	 */

	/**
	 * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields = array(
		'rowid'          => array('type'=>'integer', 'label'=>'TechnicalID', 'enabled'=>'1', 'position'=>1, 'notnull'=>1, 'visible'=>0, 'noteditable'=>'1', 'index'=>1, 'css'=>'left', 'comment'=>"Id"),
		'date_creation'  => array('type'=>'datetime', 'label'=>'DateCreation', 'enabled'=>'1', 'position'=>10, 'notnull'=>1, 'visible'=>0,),
		'qty'            => array('type'=>'real', 'label'=>'Quantity', 'enabled'=>'1', 'position'=>20, 'notnull'=>0, 'visible'=>0,),
		'rang'           => array('type'=>'integer', 'label'=>'Order', 'enabled'=>'1', 'position'=>30, 'notnull'=>0, 'visible'=>0, 'default'=>0,),
		'description'    => array('type'=>'html', 'label'=>'Description', 'enabled'=>'1', 'position'=>40, 'notnull'=>0, 'visible'=>0,),
		'product_type'   => array('type'=>'integer', 'label'=>'ProductType', 'enabled'=>'1', 'position'=>50, 'notnull'=>0, 'visible'=>0,'default'=>0,),
		'fk_timesheet'   => array('type'=>'integer:TimeSheet:custom/dolisirh/class/timesheet.class.php:1', 'label'=>'TimeSheet', 'enabled'=>'1', 'position'=>60, 'notnull'=>1, 'visible'=>0,),
		'fk_product'     => array('type'=>'integer:Product:product/class/product.class.php:1', 'label'=>'Product', 'enabled'=>'1', 'position'=>70, 'notnull'=>-1, 'visible'=>0,),
		'fk_parent_line' => array('type'=>'integer:Product:product/class/product.class.php:1', 'label'=>'ParentProductLine', 'enabled'=>'1', 'position'=>80, 'notnull'=>-1, 'visible'=>0,),
	);

	public $rowid;
	public $date_creation;
	public $qty;
	public $rang;
	public $description;
	public $product_type;
	public $fk_timesheet;
	public $fk_product;
	public $fk_parent_line;

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 *	Load time sheet line from database
	 *
	 *	@param  int $rowid ID of timesheet line to get
	 *	@return	int		   0 < if KO, >0 if OK
	 */
	public function fetch(int $rowid): int
	{
		$sql  = 'SELECT tsd.rowid, tsd.date_creation, tsd.qty, tsd.rang, tsd.description, tsd.product_type,';
		$sql .= ' tsd.fk_timesheet, tsd.fk_product, tsd.fk_parent_line';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'dolisirh_timesheetdet as tsd';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON tsd.fk_product = p.rowid';
		$sql .= ' WHERE tsd.rowid = ' . $rowid;

		$result = $this->db->query($sql);
		if ($result) {
			$objp = $this->db->fetch_object($result);

			$this->id             = $objp->rowid;
			$this->date_creation  = $objp->date_creation;
			$this->qty            = $objp->qty;
			$this->rang           = $objp->rang;
			$this->description    = $objp->description;
			$this->product_type   = $objp->product_type;
			$this->fk_timesheet   = $objp->fk_timesheet;
			$this->fk_product     = $objp->fk_product;
			$this->fk_parent_line = $objp->fk_parent_line;

			$this->db->free($result);

			return $this->id;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Load timesheet line from database
	 *
	 * @param  string    $sortorder  Sort Order
	 * @param  string    $sortfield  Sort field
	 * @param  int       $limit      Offset limit
	 * @param  int       $offset     Offset limit
	 * @param  array     $filter     Filter array
	 * @param  string    $filtermode Filter mode (AND/OR)
	 * @param  int       $parent_id  Parent ID
	 * @return array|int
	 * @throws Exception
	 */
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, array $filter = array(), $filtermode = 'AND', int $parent_id = 0)
	{
		$sql  = 'SELECT tsd.rowid, tsd.date_creation, tsd.qty, tsd.rang, tsd.description, tsd.product_type,';
		$sql .= ' tsd.fk_timesheet, tsd.fk_product, tsd.fk_parent_line';
		$sql .= ' FROM ' . MAIN_DB_PREFIX . 'dolisirh_timesheetdet as tsd';
		$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON tsd.fk_product = p.rowid';
		if ($parent_id > 0) {
			$sql .= ' WHERE tsd.fk_timesheet = ' . $parent_id;
		} else {
			$sql .= ' WHERE 1=1';
		}
		// Manage filter
		$sqlwhere = array();
		if (count($filter) > 0) {
			foreach ($filter as $key => $value) {
				if ($key == 't.rowid') {
					$sqlwhere[] = $key." = ".((int) $value);
				} elseif (in_array($this->fields[$key]['type'], array('date', 'datetime', 'timestamp'))) {
					$sqlwhere[] = $key." = '".$this->db->idate($value)."'";
				} elseif ($key == 'customsql') {
					$sqlwhere[] = $value;
				} elseif (strpos($value, '%') === false) {
					$sqlwhere[] = $key." IN (".$this->db->sanitize($this->db->escape($value)).")";
				} else {
					$sqlwhere[] = $key." LIKE '%".$this->db->escape($value)."%'";
				}
			}
		}
		if (count($sqlwhere) > 0) {
			$sql .= " AND (".implode(" ".$filtermode." ", $sqlwhere).")";
		}

		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}
		if (!empty($limit)) {
			$sql .= $this->db->plimit($limit, $offset);
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < ($limit ? min($limit, $num) : $num)) {
				$obj = $this->db->fetch_object($resql);

				$record = new self($this->db);
				$record->setVarsFromFetchObj($obj);

				$records[$record->id] = $record;

				$i++;
			}
			$this->db->free($resql);

			if (!empty($records)) {
				return $records;
			}
		} else {
			$this->errors[] = 'Error '.$this->db->lasterror();
			dol_syslog(__METHOD__.' '.join(',', $this->errors), LOG_ERR);

			return -1;
		}
	}

	/**
	 *    Insert line into database
	 *
	 * @param  User      $user
	 * @param  bool      $notrigger 1 no triggers
	 * @return int                  0 < if KO, >0 if OK
	 * @throws Exception
	 */
	public function insert(User $user, bool $notrigger = false): int
	{
		global $user;

		// Clean parameters
		$this->description = trim($this->description);

		$this->db->begin();
		$now = dol_now();

		// Insertion dans base de la line
		$sql  = 'INSERT INTO ' . MAIN_DB_PREFIX . 'dolisirh_timesheetdet';
		$sql .= ' (date_creation, qty, rang, description, product_type,';
		$sql .= ' fk_timesheet, fk_product, fk_parent_line)';
		$sql .= " VALUES (";
		$sql .= "'" . $this->db->escape($this->db->idate($now)) . "'" . ", ";
		$sql .= price2num($this->qty, 'MS') . ", ";
		$sql .= $this->rang . ", ";
		$sql .= "'" . $this->db->escape($this->description) . "'" . ", ";
		$sql .= "'" . $this->db->escape($this->product_type) . "'" . ", ";
		$sql .= $this->fk_timesheet . ", ";
		$sql .= ($this->fk_product ? "'".$this->db->escape($this->fk_product)."'" : "null") . ", ";
		$sql .= ($this->fk_parent_line > 0 ? "'".$this->db->escape($this->fk_parent_line)."'" : "null");
		$sql .= ')';

		dol_syslog(get_class($this) . "::insert", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql) {
			$this->id    = $this->db->last_insert_id(MAIN_DB_PREFIX . 'timesheetdet');
			$this->rowid = $this->id; // For backward compatibility

			$this->db->commit();
			// Triggers
			if ( ! $notrigger) {
				// Call triggers
				$this->call_trigger(strtoupper(get_class($this)) . '_CREATE', $user);
				// End call triggers
			}
			return $this->id;
		} else {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -2;
		}
	}

	/**
	 *    Update line into database
	 *
	 * @param  User      $user      User object
	 * @param  bool      $notrigger Disable triggers
	 * @return int                  0 < if KO, >0 if OK
	 * @throws Exception
	 */
	public function update(User $user, bool $notrigger = false): int
	{
		global $user;

		// Clean parameters
		$this->description = trim($this->description);

		$this->db->begin();

		$sql  = "UPDATE " . MAIN_DB_PREFIX . "dolisirh_timesheetdet SET";
		$sql .= " qty = " . $this->qty . ",";
		if (!empty($this->rang)) {
			$sql .= " rang = " . $this->rang . ",";
		}
		$sql .= " description='" . $this->db->escape($this->description) . "'";

		$sql .= " WHERE rowid = " . $this->id;

		dol_syslog(get_class($this) . "::update", LOG_DEBUG);
		$resql = $this->db->query($sql);

		if ($resql) {
			$this->db->commit();
			// Triggers
			if ( ! $notrigger) {
				// Call triggers
				$this->call_trigger(strtoupper(get_class($this)) . '_MODIFY', $user);
				// End call triggers
			}
			return $this->id;
		} else {
			$this->error = $this->db->error();
			$this->db->rollback();
			return -2;
		}
	}

	/**
	 *    Delete line in database
	 *
	 * @param  User        $user      User object
	 * @param  bool        $notrigger Disable triggers
	 * @return int                    0 <if KO, >0 if OK
	 * @throws Exception
	 */
	public function delete(User $user, bool $notrigger = false): int
	{
		global $user;

		$this->db->begin();

		$sql = "DELETE FROM " . MAIN_DB_PREFIX . "dolisirh_timesheetdet WHERE rowid = " . $this->id;
		dol_syslog(get_class($this) . "::delete", LOG_DEBUG);
		if ($this->db->query($sql)) {
			$this->db->commit();
			// Triggers
			if ( ! $notrigger) {
				// Call trigger
				$this->call_trigger(strtoupper(get_class($this)) . '_DELETE', $user);
				// End call triggers
			}
			return 1;
		} else {
			$this->error = $this->db->error() . " sql=" . $sql;
			$this->db->rollback();
			return -1;
		}
	}
}

/**
 * Class TimeSheetSignature
 */

class TimeSheetSignature extends DoliSIRHSignature
{
	/**
	 * @var string Name of table without prefix where object is stored. This is also the key used for extrafields management.
	 */

	public $object_type = 'timesheet';

	/**
	 * @var array Context element object
	 */
	public $context = array();

	/**
	 * @var string String with name of icon for document. Must be the part after the 'object_' into object_document.png
	 */
	public $picto = 'timesheet@dolisirh';

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $conf, $langs;

		$this->db = $db;

		if (empty($conf->global->MAIN_SHOW_TECHNICAL_ID) && isset($this->fields['rowid'])) $this->fields['rowid']['visible'] = 0;
		if (empty($conf->multicompany->enabled) && isset($this->fields['entity'])) $this->fields['entity']['enabled']        = 0;

		// Unset fields that are disabled
		foreach ($this->fields as $key => $val) {
			if (isset($val['enabled']) && empty($val['enabled'])) {
				unset($this->fields[$key]);
			}
		}

		// Translate some data of arrayofkeyval
		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
	}
}
