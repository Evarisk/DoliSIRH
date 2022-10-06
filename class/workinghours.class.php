<?php
/* Copyright (C) 2022 EOXIA <dev@eoxia.com>
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
 * \file        class/workinghours.class.php
 * \ingroup     dolisirh
 * \brief       This file is a CRUD class file for Workinghours (Create/Read/Update/Delete)
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commonobject.class.php';

/**
 * Class for Workinghours
 */
class Workinghours extends CommonObject
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string ID to identify managed object.
	 */
	public $element = 'workinghours';

	/**
	 * @var string Name of table without prefix where object is stored. This is also the key used for extrafields management.
	 */
	public $table_element = 'element_workinghours';

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
	 * @var string String with name of icon for workinghours. Must be the part after the 'object_' into object_workinghours.png
	 */
	public $picto = 'workinghours@dolisirh';

	/**
	 * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields = array(
		'rowid'                  => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => '1', 'index' => 1, 'comment' => "Id"),
		'entity'                 => array('type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 10, 'notnull' => 1, 'visible' => -1,),
		'date_creation'          => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 20, 'notnull' => 1, 'visible' => -2,),
		'tms'                    => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 30, 'notnull' => 0, 'visible' => -2,),
		'status'                 => array('type' => 'smallint', 'label' => 'Status', 'enabled' => '1', 'position' => 40, 'notnull' => 0, 'visible' => -1,),
		'element_type'           => array('type' => 'varchar(50)', 'label' => 'ElementType', 'enabled' => '1', 'position' => 50, 'notnull' => 0, 'visible' => -1,),
		'element_id'             => array('type' => 'integer', 'label' => 'ElementID', 'enabled' => '1', 'position' => 60, 'notnull' => 1, 'visible' => -1,),
		'schedule_monday'        => array('type' => 'varchar(128)', 'label' => 'Day 0', 'enabled' => '1', 'position' => 70, 'notnull' => 0, 'visible' => 1,),
		'schedule_tuesday'       => array('type' => 'varchar(128)', 'label' => 'Day 1', 'enabled' => '1', 'position' => 80, 'notnull' => 0, 'visible' => 1,),
		'schedule_wednesday'     => array('type' => 'varchar(128)', 'label' => 'Day 2', 'enabled' => '1', 'position' => 90, 'notnull' => 0, 'visible' => 1,),
		'schedule_thursday'      => array('type' => 'varchar(128)', 'label' => 'Day 3', 'enabled' => '1', 'position' => 100, 'notnull' => 0, 'visible' => 1,),
		'schedule_friday'        => array('type' => 'varchar(128)', 'label' => 'Day 4', 'enabled' => '1', 'position' => 110, 'notnull' => 0, 'visible' => 1,),
		'schedule_saturday'      => array('type' => 'varchar(128)', 'label' => 'Day 5', 'enabled' => '1', 'position' => 120, 'notnull' => 0, 'visible' => 1,),
		'schedule_sunday'        => array('type' => 'varchar(128)', 'label' => 'Day 6', 'enabled' => '1', 'position' => 130, 'notnull' => 0, 'visible' => 1,),
		'workinghours_monday'    => array('type' => 'integer', 'label' => 'Day 0', 'enabled' => '1', 'position' => 170, 'notnull' => 0, 'visible' => 1,),
		'workinghours_tuesday'   => array('type' => 'integer', 'label' => 'Day 1', 'enabled' => '1', 'position' => 180, 'notnull' => 0, 'visible' => 1,),
		'workinghours_wednesday' => array('type' => 'integer', 'label' => 'Day 2', 'enabled' => '1', 'position' => 190, 'notnull' => 0, 'visible' => 1,),
		'workinghours_thursday'  => array('type' => 'integer', 'label' => 'Day 3', 'enabled' => '1', 'position' => 200, 'notnull' => 0, 'visible' => 1,),
		'workinghours_friday'    => array('type' => 'integer', 'label' => 'Day 4', 'enabled' => '1', 'position' => 210, 'notnull' => 0, 'visible' => 1,),
		'workinghours_saturday'  => array('type' => 'integer', 'label' => 'Day 5', 'enabled' => '1', 'position' => 220, 'notnull' => 0, 'visible' => 1,),
		'workinghours_sunday'    => array('type' => 'integer', 'label' => 'Day 6', 'enabled' => '1', 'position' => 230, 'notnull' => 0, 'visible' => 1,),
		'fk_user_creat'          => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => '1', 'position' => 140, 'notnull' => 1, 'visible' => -2, 'foreignkey' => 'user.rowid',),
	);

	public $rowid;
	public $entity;
	public $date_creation;
	public $tms;
	public $status;
	public $element_type;
	public $element_id;
	public $schedule_monday;
	public $schedule_tuesday;
	public $schedule_wednesday;
	public $schedule_thursday;
	public $schedule_friday;
	public $schedule_saturday;
	public $schedule_sunday;
	public $workinghours_monday;
	public $workinghours_tuesday;
	public $workinghours_wednesday;
	public $workinghours_thursday;
	public $workinghours_friday;
	public $workinghours_saturday;
	public $workinghours_sunday;
	public $fk_user_creat;

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

	/**
	 * Create object into database
	 *
	 * @param  User       $user       User that creates
	 * @param  bool       $notrigger  false=launch triggers after, true=disable triggers
	 * @return int                    0 < if KO, ID of created object if OK
	 * @throws Exception
	 */
	public function create(User $user, bool $notrigger = false): int
	{
		$sql                                                                              = "UPDATE " . MAIN_DB_PREFIX . "$this->table_element";
		$sql                                                                             .= " SET status = 0";
		if (isset($this->ismultientitymanaged) && $this->ismultientitymanaged == 1) $sql .= ' WHERE entity IN (' . getEntity($this->table_element) . ')';
		else $sql                                                                        .= ' WHERE 1 = 1';
		$sql                                                                             .= " AND element_type = " . "'" . $this->element_type . "'";
		$sql                                                                             .= " AND element_id = " . $this->element_id;

		dol_syslog("admin.lib::create", LOG_DEBUG);
		$this->db->query($sql);
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param  int         $id        ID object
	 * @param  string|null $ref       Ref
	 * @param  string      $morewhere More SQL filters (' AND ...')
	 * @return int                    0 < if KO, 0 if not found, >0 if OK
	 */
	public function fetch(int $id, string $ref = null, string $morewhere = ''): int
	{
		return $this->fetchCommon($id, $ref, $morewhere);
	}

	/**
	 * Load list of objects in memory from the database.
	 *
	 * @param  string    $sortorder  Sort Order
	 * @param  string    $sortfield  Sort field
	 * @param  int       $limit      Limit
	 * @param  int       $offset     Offset
	 * @param  array     $filter     Filter array. Example array('field'=>'value', 'customurl'=>...)
	 * @param  string    $filtermode Filter mode (AND/OR)
	 * @return array|int             int <0 if KO, array of pages if OK
	 * @throws Exception
	 */
	public function fetchAll(string $sortorder = '', string $sortfield = '', int $limit = 0, int $offset = 0, array $filter = array(), string $filtermode = 'AND')
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$records = array();

		$sql = 'SELECT ';
		$sql .= $this->getFieldList();
		$sql .= ' FROM '.MAIN_DB_PREFIX.$this->table_element.' as t';
		if ($this->ismultientitymanaged) {
			$sql .= ' WHERE t.entity IN ('.getEntity($this->table_element).')';
		} else {
			$sql .= ' WHERE 1 = 1';
		}
		// Manage filter
		$sqlwhere = array();
		if (count($filter) > 0) {
			foreach ($filter as $key => $value) {
				if ($key == 't.rowid') {
					$sqlwhere[] = $key." = ".((int) $value);
				} elseif (strpos($key, 'date') !== false) {
					$sqlwhere[] = $key." = '".$this->db->idate($value)."'";
				} elseif ($key == 'customsql') {
					$sqlwhere[] = $value;
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
			$this->db->num_rows($resql);

			while ($obj = $this->db->fetch_object($resql)) {
				$record = new self($this->db);
				$record->setVarsFromFetchObj($obj);

				$records[$record->id] = $record;
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
	 * Load list of objects in memory from the database.
	 *
	 * @param  int        $id   ID object
	 * @param  string     $type Type of object
	 * @return array|int        int <0 if KO, array of pages if OK
	 * @throws Exception
	 */
	public function fetchCurrentWorkingHours(int $id, string $type)
	{
		$current_workinghours = $this->fetchAll('', '', 0, 0, array('element_type' => $type, 'element_id' => $id, 'status' => 1));
		if (is_array($current_workinghours) && !empty($current_workinghours)) {
			$current_workinghours = array_shift($current_workinghours);
		}
		return $current_workinghours;
	}

	/**
	 * Return label of contact status
	 *
	 * @return string Label of contact status
	 */
	public function getLibStatut(): string
	{
		return '';
	}
}
