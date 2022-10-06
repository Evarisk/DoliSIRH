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
 *       \file       class/facturerecstats.class.php
 *       \ingroup    dolisirh
 *       \brief      Recurring invoice class to manage statistics reports
 */
include_once DOL_DOCUMENT_ROOT.'/custom/dolisirh/class/dolisirhstats.php';
include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php';
//include_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
include_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

/**
 *	Class to manage stats for recurring invoices (customer and supplier)
 */
class FactureRecStats extends DoliSIRHStats
{
	/**
	 * @var int  ID soc
	 */
	public $socid;

	/**
	 * @var int ID user
	 */
	public $userid;

	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public  $table_element;

	/**
	 * @var string Suffix to add to name of cache file (to avoid file name conflicts)
	 */
	public  $cachefilesuffix = '';

	/**
	 * @var string SQL from
	 */
	public  $from;

	/**
	 * @var string SQL field
	 */
	public $field;

	/**
	 * @var string SQL where
	 */
	public $where;

	/**
	 * @var string SQL join
	 */
	public $join;

	/**
	 * @var string SQL from line
	 */
	public $from_line;

	/**
	 * @var string SQL field line
	 */
	public $field_line;

	/**
	 * 	Constructor
	 *
	 * 	@param DoliDB $db			     Database handler
	 * 	@param int    $socid		     ID third party for filter. This value must be forced during the new to external user company if user is an external user.
	 * 	@param string $mode	   	         Option ('customer', 'supplier')
	 * 	@param int    $userid    	     ID user for filter (creation user)
	 * 	@param int    $typentid          ID typent of thirdparty for filter
	 * 	@param int    $categid           ID category of thirdparty for filter
	 * 	@param int    $categinvoicerecid ID category of Invoice rec for filter
	 */
	public function __construct(DoliDB $db, int $socid, string $mode, int $userid = 0, int $typentid = 0, int $categid = 0, int $categinvoicerecid = 0)
	{
		global $user;

		$this->db              = $db;
		$this->socid           = ($socid > 0 ?: 0);
		$this->userid          = $userid;
		$this->cachefilesuffix = $mode;
		$this->join            = '';

		if ($mode == 'customer') {
			$object           = new FactureRec($this->db);
			$this->from       = MAIN_DB_PREFIX.$object->table_element." as fr";
			$this->from_line  = MAIN_DB_PREFIX.$object->table_element_line." as tl";
			$this->field      = 'total_ht';
			$this->field_line = 'total_ht';
		}
//		if ($mode == 'supplier') {
//			$object = new FactureFournisseur($this->db);
//			$this->from = MAIN_DB_PREFIX.$object->table_element." as f";
//			$this->from_line = MAIN_DB_PREFIX.$object->table_element_line." as tl";
//			$this->field = 'total_ht';
//			$this->field_line = 'total_ht';
//		}

		$this->where = " fr.suspended >= 0";
		$this->where .= " AND fr.entity IN (".getEntity('invoicerec').")";
		if (empty($user->rights->societe->client->voir) && !$this->socid) {
			$this->where .= " AND fr.fk_soc = sc.fk_soc AND sc.fk_user = ".((int) $user->id);
		}
//		if ($mode == 'customer') {
//			$this->where .= " AND (fr.suspended <> 3 OR fr.close_code <> 'replaced')"; // Exclude replaced invoices as they are duplicated (we count closed invoices for other reasons)
//		}
		if ($this->socid) {
			$this->where .= " AND fr.fk_soc = ".((int) $this->socid);
		}
		if ($this->userid > 0) {
			$this->where .= ' AND fr.fk_user_author = '.($this->userid);
		}
//		if (!empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) {
//			$this->where .= " AND f.type IN (0,1,2,5)";
//		} else {
//			$this->where .= " AND f.type IN (0,1,2,3,5)";
//		}

		if ($typentid) {
			$this->join .= ' LEFT JOIN '.MAIN_DB_PREFIX.'societe as s ON s.rowid = fr.fk_soc';
			$this->where .= ' AND s.fk_typent = '.($typentid);
		}

		if ($categid) {
			$this->join .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_societe as cs ON cs.fk_soc = fr.fk_soc';
			$this->join .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie as c ON c.rowid = cs.fk_categorie';
			$this->where .= ' AND c.rowid = '.($categid);
		}

		if ($categinvoicerecid) {
			$this->join .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_invoicerec as cir ON cir.fk_invoicerec = fr.rowid';
			$this->join .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie as c ON c.rowid = cir.fk_categorie';
			$this->where .= ' AND c.rowid = '.($categinvoicerecid);
		}
	}

	/**
	 * Return recurring invoices number by month for a year
	 *
	 * @param  int        $year   Year to scan
	 * @param  int        $format 0=Label of abscissa is a translated text, 1=Label of abscissa is month number, 2=Label of abscissa is first letter of month
	 * @return array              Array of values
	 * @throws Exception
	 */
	public function getNbByMonth(int $year, int $format = 0): array
	{
		global $user;

		$sql = "SELECT date_format(fr.date_when,'%m') as dm, COUNT(*) as nb";
		$sql .= " FROM ".$this->from;
		if (empty($user->rights->societe->client->voir) && !$this->socid) {
			$sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
		}
		$sql .= $this->join;
		$sql .= " WHERE fr.date_when BETWEEN '".$this->db->idate(dol_get_first_day($year))."' AND '".$this->db->idate(dol_get_last_day($year))."'";
		$sql .= " AND ".$this->where;
		$sql .= " GROUP BY dm";
		$sql .= $this->db->order('dm', 'DESC');

		return $this->_getNbByMonth($sql, $format);
	}


	/**
	 * Return recurring invoices number per year
	 *
	 * @return array     Array with number by year
	 * @throws Exception
	 */
	public function getNbByYear(): array
	{
		global $user;

		$sql = "SELECT date_format(fr.date_when,'%Y') as dm, COUNT(*), SUM(c.".$this->field.")";
		$sql .= " FROM ".$this->from;
		if (empty($user->rights->societe->client->voir) && !$this->socid) {
			$sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
		}
		$sql .= $this->join;
		$sql .= " WHERE ".$this->where;
		$sql .= " GROUP BY dm";
		$sql .= $this->db->order('dm', 'DESC');

		return $this->_getNbByYear($sql);
	}


	/**
	 * Return the recurring invoices amount by month for a year
	 *
	 * @param  int        $year   Year to scan
	 * @param  int        $format 0=Label of abscissa is a translated text, 1=Label of abscissa is month number, 2=Label of abscissa is first letter of month
	 * @return array              Array with amount by month
	 * @throws Exception
	 */
	public function getAmountByMonth(int $year, int $format = 0): array
	{
		global $user;

		$sql = "SELECT date_format(date_when,'%m') as dm, SUM(fr.".$this->field.")";
		$sql .= " FROM ".$this->from;
		if (empty($user->rights->societe->client->voir) && !$this->socid) {
			$sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
		}
		$sql .= $this->join;
		$sql .= " WHERE fr.date_when BETWEEN '".$this->db->idate(dol_get_first_day($year))."' AND '".$this->db->idate(dol_get_last_day($year))."'";
		$sql .= " AND ".$this->where;
		$sql .= " GROUP BY dm";
		$sql .= $this->db->order('dm', 'DESC');

		return $this->_getAmountByMonth($sql, $format);
	}

	/**
	 * Return average amount
	 *
	 * @param  int       $year   Year to scan
	 * @param  int       $format 0=Label of abscissa is a translated text, 1=Label of abscissa is month number, 2=Label of abscissa is first letter of month
	 * @return array             Array of values
	 * @throws Exception
	 */
	public function getAverageByMonth(int $year, int $format = 0): array
	{
		global $user;

		$sql = "SELECT date_format(date_when,'%m') as dm, AVG(fr.".$this->field.")";
		$sql .= " FROM ".$this->from;
		if (empty($user->rights->societe->client->voir) && !$this->socid) {
			$sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
		}
		$sql .= $this->join;
		$sql .= " WHERE fr.date_when BETWEEN '".$this->db->idate(dol_get_first_day($year))."' AND '".$this->db->idate(dol_get_last_day($year))."'";
		$sql .= " AND ".$this->where;
		$sql .= " GROUP BY dm";
		$sql .= $this->db->order('dm', 'DESC');

		return $this->_getAverageByMonth($sql, $format);
	}

	/**
	 * Return nb, total and average
	 *
	 * @return array     Array of values
	 * @throws Exception
	 */
	public function getAllByYear(): array
	{
		global $user;

		$sql = "SELECT date_format(date_when,'%Y') as year, COUNT(*) as nb, SUM(fr.".$this->field.") as total, AVG(fr.".$this->field.") as avg";
		$sql .= " FROM ".$this->from;
		if (empty($user->rights->societe->client->voir) && !$this->socid) {
			$sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
		}
		$sql .= $this->join;
		$sql .= " WHERE ".$this->where;
		$sql .= " GROUP BY year";
		$sql .= $this->db->order('year', 'DESC');

		return $this->_getAllByYear($sql);
	}

	/**
	 * Return the recurring invoices amount by year for a number of past years
	 *
	 * @param  int   $numberYears Years to scan
	 * @return array              Array with amount by year
	 */
	public function getAmountByYear(int $numberYears): array
	{
		global $user;

		$endYear = date('Y');
		$startYear = $endYear - $numberYears;
		$sql = "SELECT date_format(date_when,'%Y') as dm, SUM(fr.".$this->field.")";
		$sql .= " FROM ".$this->from;
		if (empty($user->rights->societe->client->voir) && !$this->socid) {
			$sql .= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
		}
		$sql .= $this->join;
		$sql .= " WHERE fr.date_when BETWEEN '".$this->db->idate(dol_get_first_day($startYear))."' AND '".$this->db->idate(dol_get_last_day($endYear))."'";
		$sql .= " AND ".$this->where;
		$sql .= " GROUP BY dm";
		$sql .= $this->db->order('dm', 'ASC');

		return $this->_getAmountByYear($sql);
	}
}

