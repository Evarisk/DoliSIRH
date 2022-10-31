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
 * or see https://www.gnu.org/
 */

/**
 *  \file       core/modules/dolisirh/dolisirhdocuments/certificatedocuments/mod_certificatedocument_standard.php
 *  \ingroup    dolisirh
 *  \brief      File of class to manage CertificateDocument numbering rules standard
 */

/**
 *	Class to manage customer order numbering rules standard
 */
class mod_certificatedocument_standard
{
	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr'; // 'development', 'experimental', 'dolibarr'

	/**
	 * @var string document prefix
	 */
	public $prefix = 'CERTD';

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string name
	 */
	public $name = 'Skrymir';

	/**
	 *  Return description of numbering module
	 *
	 *  @return string Text with description
	 */
	public function info(): string
	{
		global $langs;
		$langs->load("dolisirh@dolisirh");
		return $langs->trans("DoliSIRHCertificateDocumentStandardModel", $this->prefix);
	}

	/**
	 *	Return if a module can be used or not
	 *
	 *	@return boolean true if module can be used
	 */
	public function isEnabled(): bool
	{
		return true;
	}

	/**
	 *  Return an example of numbering
	 *
	 *  @return string Example
	 */
	public function getExample(): string
	{
		return $this->prefix."0501-0001";
	}

	/**
	 *  Checks if the numbers already in the database do not
	 *  cause conflicts that would prevent this numbering working.
	 *
	 *  @param  Object  $object	Object we need next value for
	 *  @return boolean         false if are conflict, true if ok
	 */
	public function canBeActivated(object $object): bool
	{
		global $conf, $langs, $db;

		$coyymm = ''; $max = '';

		$posindice = strlen($this->prefix) + 6;
		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$posindice.") AS SIGNED)) as max";
		$sql .= " FROM ".MAIN_DB_PREFIX."dolisirh_dolisirhdocuments";
		$sql .= " WHERE ref LIKE '".$db->escape($this->prefix)."____-%'";
		if ($object->ismultientitymanaged == 1) {
			$sql .= " AND entity = ".$conf->entity;
		} elseif ($object->ismultientitymanaged == 2) {
			// TODO
		}

		$resql = $db->query($sql);
		if ($resql) {
			$row = $db->fetch_row($resql);
			if ($row) {
				$coyymm = substr($row[0], 0, 6); $max = $row[0];
			}
		}
		if ($coyymm && !preg_match('/'.$this->prefix.'[0-9][0-9][0-9][0-9]/i', $coyymm)) {
			$langs->load("errors");
			$this->error = $langs->trans('ErrorNumRefModel', $max);
			return false;
		}

		return true;
	}

	/**
	 * Return next free value
	 *
	 * @param  Object    $object  Object we need next value for
	 * @return string             Value if KO, <0 if KO
	 * @throws Exception
	 */
	public function getNextValue(object $object)
	{
		global $db, $conf;

		// first we get the max value
		$posindice = strlen($this->prefix) + 6;
		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM ".$posindice.") AS SIGNED)) as max";
		$sql .= " FROM ".MAIN_DB_PREFIX."dolisirh_dolisirhdocuments";
		$sql .= " WHERE ref LIKE '".$db->escape($this->prefix)."____-%'";
		if ($object->ismultientitymanaged == 1) {
			$sql .= " AND entity = ".$conf->entity;
		} elseif ($object->ismultientitymanaged == 2) {
			// TODO
		}

		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				$max = intval($obj->max);
			} else {
				$max = 0;
			}
		} else {
			dol_syslog("mod_certificatedocument_standard::getNextValue", LOG_DEBUG);
			return -1;
		}

		//$date=time();
        $date = !empty($object->date_creation) ? $object->date_creation : dol_now();
		$yymm = strftime("%y%m", $date);

		if ($max >= (pow(10, 4) - 1)) {
			$num = $max + 1; // If counter > 9999, we do not format on 4 chars, we take number as it is
		} else {
			$num = sprintf("%04s", $max + 1);
		}

		dol_syslog("mod_certificatedocument_standard::getNextValue return ".$this->prefix.$yymm."-".$num);
		return $this->prefix.$yymm."-".$num;
	}

	/**
	 *	Returns version of numbering module
	 *
	 *	@return string Value
	 */
	public function getVersion(): string
	{
		global $langs;
		$langs->load("admin");

		if ($this->version == 'development') {
			return $langs->trans("VersionDevelopment");
		}
		if ($this->version == 'experimental') {
			return $langs->trans("VersionExperimental");
		}
		if ($this->version == 'dolibarr') {
			return DOL_VERSION;
		}
		if ($this->version) {
			return $this->version;
		}
		return $langs->trans("NotAvailable");
	}
}
