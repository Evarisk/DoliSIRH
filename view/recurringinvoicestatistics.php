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
 * \file    view/recurringinvoicestatistics.php
 * \ingroup dolisirh
 * \brief   Recurring invoice statistics page
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if ( ! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if ( ! $res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) $res          = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
if ( ! $res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
// Try main.inc.php using relative path
if ( ! $res && file_exists("../../main.inc.php")) $res    = @include "../../main.inc.php";
if ( ! $res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if ( ! $res) die("Include of main fails");

// Libraries
require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
if (!empty($conf->category->enabled)) {
	require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
}

require_once __DIR__ . '/../class/facturerecstats.class.php';

// Global variables definitions
global $db, $langs, $user;

$WIDTH  = DolGraph::getDefaultGraphSizeForStats('width');
$HEIGHT = DolGraph::getDefaultGraphSizeForStats('height');

// Load translation files required by the page
$langs->loadLangs(array('bills', 'companies', 'other'));

$mode = GETPOST("mode") ? GETPOST("mode") : 'customer';
if ($mode == 'customer' && !$user->rights->facture->lire) {
	accessforbidden();
}
//if ($mode == 'supplier' && empty($user->rights->fournisseur->facture->lire)) {
//	accessforbidden();
//}

$object_status      = GETPOST('object_status', 'intcomma');
$typent_id          = GETPOST('typent_id', 'int');
$categ_id           = GETPOST('categ_id', 'categ_id');
$categinvoicerec_id = GETPOST('categinvoicerec_id');
$userid             = GETPOST('userid', 'int');
$socid              = GETPOST('socid', 'int');
$custcats           = GETPOST('custcats', 'array');
$invoicereccats     = GETPOST('invoicereccats', 'array');

// Security check
if ($user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

$nowyear   = strftime("%Y", dol_now());
$year      = GETPOST('year') > 0 ? GETPOST('year', 'int') : $nowyear;
$startyear = $year - (empty($conf->global->MAIN_STATS_GRAPHS_SHOW_N_YEARS) ? 2 : max(1, min(10, $conf->global->MAIN_STATS_GRAPHS_SHOW_N_YEARS)));
$endyear   = $year;

/*
 * View
 */
if (!empty($conf->category->enabled)) {
	$langs->load('categories');
}
$form        = new Form($db);
$formcompany = new FormCompany($db);
$formother   = new FormOther($db);

llxHeader();

$picto = 'bill';
$title = $langs->trans("RecurringInvoicesStatistics");
$dir = $conf->facture->dir_temp;

//if ($mode == 'supplier') {
//	$picto = 'supplier_invoice';
//	$title = $langs->trans("BillsStatisticsSuppliers");
//	$dir = $conf->fournisseur->facture->dir_temp;
//}

print load_fiche_titre($title, '', $picto);

dol_mkdir($dir);

$stats = new FactureRecStats($db, (int) $socid, $mode, ($userid > 0 ? $userid : 0), ($typent_id > 0 ? $typent_id : 0), ($categ_id > 0 ? $categ_id : 0),  ($categinvoicerec_id > 0 ? $categinvoicerec_id : 0));
if ($mode == 'customer') {
	if ($object_status != '' && $object_status >= 0) {
		$stats->where .= ' AND f.suspended IN ('.$db->sanitize($object_status).')';
	}
	if (is_array($custcats) && !empty($custcats)) {
		$stats->from .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_societe as cat ON (fr.fk_soc = cat.fk_soc)';
		$stats->where .= ' AND cat.fk_categorie IN ('.$db->sanitize(implode(',', $custcats)).')';
	}
	if (is_array($invoicereccats) && !empty($invoicereccats)) {
		$stats->from .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_facturerec as catinv ON (fr.rowid = catinv.fk_facturerec)';
		$stats->where .= ' AND catinv.fk_categorie IN ('.$db->sanitize(implode(',', $invoicereccats)).')';
	}
}

//if ($mode == 'supplier') {
//	if ($object_status != '' && $object_status >= 0) {
//		$stats->where .= ' AND f.fk_statut IN ('.$db->sanitize($object_status).')';
//	}
//	if (is_array($custcats) && !empty($custcats)) {
//		$stats->from .= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_fournisseur as cat ON (f.fk_soc = cat.fk_soc)';
//		$stats->where .= ' AND cat.fk_categorie IN ('.$db->sanitize(implode(',', $custcats)).')';
//	}
//}

// Build graphic number of object
$data = $stats->getNbByMonthWithPrevYear($endyear, $startyear, 0, 0, $conf->global->SOCIETE_FISCAL_MONTH_START);

$filenamenb = $dir."/invoicerecsnbinyear-".$year.".png";
if ($mode == 'customer') {
	$fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=dolisirh&file=invoicerecsnbinyear-'.$year.'.png';
}
//if ($mode == 'supplier') {
//	$fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=dolisirh&file=invoicesnbinyear-'.$year.'.png';
//}

$px1 = new DolGraph();
$mesg = $px1->isGraphKo();
if (!$mesg) {
	$px1->SetData($data);
	$i = $startyear;
	$legend = array();
	while ($i <= $endyear) {
		$legend[] = $i;
		$i++;
	}
	$px1->SetLegend($legend);
	$px1->SetMaxValue($px1->GetCeilMaxValue());
	$px1->SetWidth($WIDTH);
	$px1->SetHeight($HEIGHT);
	$px1->SetYLabel($langs->trans("NumberOfRecurringBills"));
	$px1->SetShading(3);
	$px1->SetHorizTickIncrement(1);
	$px1->mode = 'depth';
	$px1->SetTitle($langs->trans("NumberOfRecurringBillsByMonth"));

	$px1->draw($filenamenb, $fileurlnb);
}

// Build graphic amount of object
$data = $stats->getAmountByMonthWithPrevYear($endyear, $startyear, 0, 0, $conf->global->SOCIETE_FISCAL_MONTH_START);

$filenameamount = $dir."/invoicerecsamountinyear-".$year.".png";
if ($mode == 'customer') {
	$fileurlamount = DOL_URL_ROOT.'/viewimage.php?modulepart=dolisirh&amp;file=invoicerecsamountinyear-'.$year.'.png';
}
//if ($mode == 'supplier') {
//	$fileurlamount = DOL_URL_ROOT.'/viewimage.php?modulepart=billstatssupplier&amp;file=invoicesamountinyear-'.$year.'.png';
//}

$px2 = new DolGraph();
$mesg = $px2->isGraphKo();
if (!$mesg) {
	$px2->SetData($data);
	$i = $startyear;
	$legend = array();
	while ($i <= $endyear) {
		$legend[] = $i;
		$i++;
	}
	$px2->SetLegend($legend);
	$px2->SetMaxValue($px2->GetCeilMaxValue());
	$px2->SetMinValue(min(0, $px2->GetFloorMinValue()));
	$px2->SetWidth($WIDTH);
	$px2->SetHeight($HEIGHT);
	$px2->SetYLabel($langs->trans("AmountOfRecurringBills"));
	$px2->SetShading(3);
	$px2->SetHorizTickIncrement(1);
	$px2->mode = 'depth';
	$px2->SetTitle($langs->trans("AmountOfRecurringBillsByMonthHT"));

	$px2->draw($filenameamount, $fileurlamount);
}

// Build graphic average amount of object
$data = $stats->getAverageByMonthWithPrevYear($endyear, $startyear, 0, $conf->global->SOCIETE_FISCAL_MONTH_START);

if (empty($user->rights->societe->client->voir) || $user->socid) {
	$filename_avg = $dir.'/invoicerecsaverage-'.$user->id.'-'.$year.'.png';
	if ($mode == 'customer') {
		$fileurl_avg = DOL_URL_ROOT.'/viewimage.php?modulepart=dolisirh&file=invoicerecsaverage-'.$user->id.'-'.$year.'.png';
	}
	//  if ($mode == 'supplier') {
	//      $fileurl_avg = DOL_URL_ROOT.'/viewimage.php?modulepart=orderstatssupplier&file=ordersaverage-'.$user->id.'-'.$year.'.png';
	//  }
} else {
	$filename_avg = $dir.'/invoicerecsaverage-'.$year.'.png';
	if ($mode == 'customer') {
		$fileurl_avg = DOL_URL_ROOT.'/viewimage.php?modulepart=dolisirh&file=invoicerecsaverage-'.$year.'.png';
	}
	//  if ($mode == 'supplier') {
	//      $fileurl_avg = DOL_URL_ROOT.'/viewimage.php?modulepart=orderstatssupplier&file=ordersaverage-'.$year.'.png';
	//  }
}

$px3 = new DolGraph();
$mesg = $px3->isGraphKo();
if (!$mesg) {
	$px3->SetData($data);
	$i = $startyear;
	$legend = array();
	while ($i <= $endyear) {
		$legend[] = $i;
		$i++;
	}
	$px3->SetLegend($legend);
	$px3->SetYLabel($langs->trans("AmountAverage"));
	$px3->SetMaxValue($px3->GetCeilMaxValue());
	$px3->SetMinValue($px3->GetFloorMinValue());
	$px3->SetWidth($WIDTH);
	$px3->SetHeight($HEIGHT);
	$px3->SetShading(3);
	$px3->SetHorizTickIncrement(1);
	$px3->mode = 'depth';
	$px3->SetTitle($langs->trans("AmountAverage"));

	$px3->draw($filename_avg, $fileurl_avg);
}

// Show array
$data = $stats->getAllByYear();
$arrayyears = array();
foreach ($data as $val) {
	$arrayyears[$val['year']] = $val['year'];
}
if (!count($arrayyears)) {
	$arrayyears[$nowyear] = $nowyear;
}

$h = 0;
$head = array();
$head[$h][0] = DOL_URL_ROOT.'/custom/dolisirh/view/recurringinvoicestatistics.php?mode='.urlencode($mode);
$head[$h][1] = $langs->trans("ByMonthYear");
$head[$h][2] = 'byyear';
$h++;

if ($mode == 'customer') {
	$type = 'invoice_stats';
}
//if ($mode == 'supplier') {
//	$type = 'supplier_invoice_stats';
//}

complete_head_from_modules($conf, $langs, null, $head, $h, $type);

print dol_get_fiche_head($head, 'byyear', $langs->trans("Statistics"), -1);

// We use select_thirdparty_list instead of select_company so we can use $filter and share same code for customer and supplier.
$filter = '';
if ($mode == 'customer') {
	$filter = 's.client in (1,2,3)';
}
//if ($mode == 'supplier') {
//	$filter = 's.fournisseur = 1';
//}

print '<div class="fichecenter"><div class="fichethirdleft">';

// Show filter box
print '<form name="stats" method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="mode" value="'.$mode.'">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="liste_titre" colspan="2">'.$langs->trans("Filter").'</td></tr>';
// Company
print '<tr><td>'.$langs->trans("ThirdParty").'</td><td>';
print img_picto('', 'company', 'class="pictofixedwidth"');
print $form->select_company($socid, 'socid', $filter, 1, 0, 0, array(), 0, 'widthcentpercentminusx maxwidth300');
print '</td></tr>';

// ThirdParty Type
print '<tr><td>'.$langs->trans("ThirdPartyType").'</td><td>';
$sortparam_typent = (empty($conf->global->SOCIETE_SORT_ON_TYPEENT) ? 'ASC' : $conf->global->SOCIETE_SORT_ON_TYPEENT); // NONE means we keep sort of original array, so we sort on position. ASC, means next function will sort on label.
print $form->selectarray("typent_id", $formcompany->typent_array(0), $typent_id, 1, 0, 0, '', 0, 0, 0, $sortparam_typent, '', 1);
if ($user->admin) {
	print ' '.info_admin($langs->trans("YouCanChangeValuesForThisListFromDictionarySetup"), 1);
}
print '</td></tr>';

// Category
if (!empty($conf->category->enabled)) {
	if ($mode == 'customer') {
		$cat_type = Categorie::TYPE_CUSTOMER;
		$cat_label = $langs->trans("Category").' '.lcfirst($langs->trans("Customer"));
	}
	//  if ($mode == 'supplier') {
	//      $cat_type = Categorie::TYPE_SUPPLIER;
	//      $cat_label = $langs->trans("Category").' '.lcfirst($langs->trans("Supplier"));
	//  }
	print '<tr><td>'.$cat_label.'</td><td>';
	$cate_arbo = $form->select_all_categories($cat_type, null, 'parent', null, null, 1);
	print img_picto('', 'category', 'class="pictofixedwidth"');
	print $form->multiselectarray('custcats', $cate_arbo, GETPOST('custcats', 'array'), 0, 0, 'widthcentpercentminusx maxwidth300');
	//print $formother->select_categories($cat_type, $categ_id, 'categ_id', true);
	print '</td></tr>';
}

// Category invoice rec
if (!empty($conf->category->enabled)) {
	if ($mode == 'customer') {
		$cat_type = 'facturerec';
		$cat_label = $langs->trans("Category").' '.lcfirst($langs->trans("RecurringInvoice"));
	}
	//  if ($mode == 'supplier') {
	//      $cat_type = Categorie::TYPE_SUPPLIER;
	//      $cat_label = $langs->trans("Category").' '.lcfirst($langs->trans("Supplier"));
	//  }
	print '<tr><td>'.$cat_label.'</td><td>';
	$cate_arbo = $form->select_all_categories($cat_type, null, 'parent', null, null, 1);
	print img_picto('', 'category', 'class="pictofixedwidth"');
	print $form->multiselectarray('invoicereccats', $cate_arbo, GETPOST('invoicereccats', 'array'), 0, 0, 'widthcentpercentminusx maxwidth300');
	//print $formother->select_categories($cat_type, $categ_id, 'categ_id', true);
	print '</td></tr>';
}

// User
print '<tr><td>'.$langs->trans("CreatedBy").'</td><td>';
print img_picto('', 'user', 'class="pictofixedwidth"');
print $form->select_dolusers($userid, 'userid', 1, '', 0, '', '', 0, 0, 0, '', 0, '', 'widthcentpercentminusx maxwidth300');
print '</td></tr>';
// Status
print '<tr><td>'.$langs->trans("Status").'</td><td>';
if ($mode == 'customer') {
	$liststatus = array('0'=>$langs->trans("Draft"), '1'=>$langs->trans("Disable"));
	print $form->selectarray('object_status', $liststatus, $object_status, 1);
}
//if ($mode == 'supplier') {
//	$liststatus = array('0'=>$langs->trans("BillStatusDraft"), '1'=>$langs->trans("BillStatusNotPaid"), '2'=>$langs->trans("BillStatusPaid"));
//	print $form->selectarray('object_status', $liststatus, $object_status, 1);
//}
print '</td></tr>';
// Year
print '<tr><td>'.$langs->trans("Year").'</td><td>';
if (!in_array($year, $arrayyears)) {
	$arrayyears[$year] = $year;
}
if (!in_array($nowyear, $arrayyears)) {
	$arrayyears[$nowyear] = $nowyear;
}
arsort($arrayyears);
print $form->selectarray('year', $arrayyears, $year, 0, 0, 0, '', 0, 0, 0, '', 'width75');
print '</td></tr>';
print '<tr><td class="center" colspan="2"><input type="submit" name="submit" class="button small" value="'.$langs->trans("Refresh").'"></td></tr>';
print '</table>';
print '</form>';
print '<br><br>';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre" height="24">';
print '<td class="center">'.$langs->trans("Year").'</td>';
print '<td class="right">'.$langs->trans("NumberOfRecurringBills").'</td>';
print '<td class="right">%</td>';
print '<td class="right">'.$langs->trans("AmountTotal").'</td>';
print '<td class="right">%</td>';
print '<td class="right">'.$langs->trans("AmountAverage").'</td>';
print '<td class="right">%</td>';
print '</tr>';

$oldyear = 0;
foreach ($data as $val) {
	$year = $val['year'];
	while ($year && $oldyear > $year + 1) {	// If we have empty year
		$oldyear--;

		print '<tr class="oddeven" height="24">';
		print '<td align="center"><a href="'.$_SERVER["PHP_SELF"].'?year='.$oldyear.'&amp;mode='.$mode.($socid > 0 ? '&socid='.$socid : '').($userid > 0 ? '&userid='.$userid : '').'">'.$oldyear.'</a></td>';
		print '<td class="right">0</td>';
		print '<td class="right"></td>';
		print '<td class="right amount">0</td>';
		print '<td class="right"></td>';
		print '<td class="right amount">0</td>';
		print '<td class="right"></td>';
		print '</tr>';
	}

	print '<tr class="oddeven" height="24">';
	print '<td><a href="'.$_SERVER["PHP_SELF"].'?year='.$year.'&amp;mode='.$mode.($socid > 0 ? '&socid='.$socid : '').($userid > 0 ? '&userid='.$userid : '').'">'.$year.'</a></td>';
	print '<td class="right">'.$val['nb'].'</td>';
	print '<td class="right opacitylow" style="'.((empty($val['nb_diff']) || $val['nb_diff'] >= 0) ? 'color: green;' : 'color: red;').'">'.(!empty($val['nb_diff']) && $val['nb_diff'] < 0 ? '' : '+').round(!empty($val['nb_diff']) ? $val['nb_diff'] : 0).'%</td>';
	print '<td class="right"><span class="amount">'.price(price2num($val['total'], 'MT'), 1).'</span></td>';
	print '<td class="right opacitylow" style="'.((empty($val['total_diff']) || $val['total_diff'] >= 0) ? 'color: green;' : 'color: red;').'">'.( !empty($val['total_diff']) && $val['total_diff'] < 0 ? '' : '+').round(!empty($val['total_diff']) ? $val['total_diff'] : 0).'%</td>';
	print '<td class="right"><span class="amount">'.price(price2num($val['avg'], 'MT'), 1).'</span></td>';
	print '<td class="right opacitylow" style="'.((empty($val['avg_diff']) || $val['avg_diff'] >= 0) ? 'color: green;' : 'color: red;').'">'.(!empty($val['avg_diff']) && $val['avg_diff'] < 0 ? '' : '+').round(!empty($val['avg_diff']) ? $val['avg_diff'] : 0).'%</td>';
	print '</tr>';
	$oldyear = $year;
}

print '</table>';
print '</div>';

print '</div><div class="fichetwothirdright">';

// Show graphs
print '<table class="border centpercent"><tr class="pair nohover"><td>';
if ($mesg) {
	print $mesg;
} else {
	print $px1->show();
	print "<br>\n";
	print $px2->show();
	print "<br>\n";
	print $px3->show();
}
print '</td></tr></table>';

print '</div></div>';
print '<div style="clear:both"></div>';

print dol_get_fiche_end();

// End of page
llxFooter();
$db->close();
