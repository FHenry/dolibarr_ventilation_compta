<?php
/* Copyright (C) 2007-2010 Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2007-2010 Jean Heimburger			<jean@tiaris.info>
 * Copyright (C) 2011		   Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2012		   Regis Houssin		<regis@dolibarr.fr>
 * Copyright (C) 2013		   Christophe Battarel	<christophe.battarel@altairis.fr>
 * Copyright (C) 2013-2014 Alexandre Spangaro	  	<alexandre.spangaro@gmail.com>
 * Copyright (C) 2013-2014 Florian Henry	      	<florian.henry@open-concept.pro>
 * Copyright (C) 2013-2014 Olivier Geffroy     		<jeff@jeffinfo.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file accountingex/journal/sellsjournal.php
 * \ingroup Accounting Expert
 * \brief Page with sells journal
 */

// Dolibarr environment
$res = @include ("../main.inc.php");
if (! $res && file_exists("../main.inc.php"))
	$res = @include ("../main.inc.php");
if (! $res && file_exists("../../main.inc.php"))
	$res = @include ("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php"))
	$res = @include ("../../../main.inc.php");
if (! $res)
	die("Include of main fails");
	
	// Class
dol_include_once("/core/lib/report.lib.php");
dol_include_once("/core/lib/date.lib.php");
dol_include_once("/accountingex/core/lib/account.lib.php");
dol_include_once("/compta/facture/class/facture.class.php");
dol_include_once("/societe/class/client.class.php");
dol_include_once("/accountingex/class/bookkeeping.class.php");
dol_include_once("/accountingex/class/accountingaccount.class.php");
dol_include_once("/accountingex/class/html.formventilation.class.php");

// Langs
$langs->load("compta");
$langs->load("bills");
$langs->load("other");
$langs->load("main");
$langs->load("accountingex@accountingex");

$date_startmonth = GETPOST('date_startmonth');
$date_startday = GETPOST('date_startday');
$date_startyear = GETPOST('date_startyear');
$date_endmonth = GETPOST('date_endmonth');
$date_endday = GETPOST('date_endday');
$date_endyear = GETPOST('date_endyear');

//$formfile = new FormQECompta($db);

// Security check
if ($user->societe_id > 0)
	accessforbidden();
if (! $user->rights->accountingex->access)
	accessforbidden();

$action = GETPOST('action');

/*
 * View
 */

$year_current = strftime("%Y", dol_now());
$current_month = strftime("%m", dol_now());
$pastmonth = strftime("%m", dol_now()) - 1;
$pastmonthyear = $year_current;
if ($pastmonth == 0) {
	$pastmonth = 12;
	$pastmonthyear --;
}

$date_start = dol_mktime(0, 0, 0, $date_startmonth, $date_startday, $date_startyear);
$date_end = dol_mktime(23, 59, 59, $date_endmonth, $date_endday, $date_endyear);

if (empty($date_start) || empty($date_end)) // We define date_start and date_end
{
	$date_start = dol_get_first_day($year_current, $current_month, false);
	$date_end = dol_get_last_day($year_current, $current_month, false);
}

$p = explode(":", $conf->global->MAIN_INFO_SOCIETE_COUNTRY);
$idpays = $p[0];

$sql = "SELECT f.rowid, f.facnumber, f.type, f.datef as df, f.ref_client,";
$sql .= " fd.rowid as fdid, fd.description, fd.product_type, fd.total_ht, fd.total_tva, fd.tva_tx, fd.total_ttc,";
$sql .= " s.rowid as socid, s.nom as name, s.code_compta, s.code_client,";
$sql .= " p.rowid as pid, p.ref as pref, p.accountancy_code_sell, aa.rowid as fk_compte, aa.account_number as compte, aa.label as label_compte, ";
$sql .= " ct.accountancy_code_sell as account_tva";
$sql .= ' ,pextra.pr_cd_analytics';
$sql .= ' ,proj.ref  as projet_ref';
$sql .= ' ,proj.rowid as projet_id';
$sql .= ' ,f.entity';
$sql .= " FROM " . MAIN_DB_PREFIX . "facturedet fd";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "product p ON p.rowid = fd.fk_product";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "accountingaccount aa ON aa.rowid = fd.fk_code_ventilation";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = fd.fk_facture";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "societe s ON s.rowid = f.fk_soc";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "product_extrafields pextra ON pextra.fk_object = p.rowid";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "projet proj ON proj.rowid = f.fk_projet";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "c_tva ct ON fd.tva_tx = ct.taux AND ct.fk_pays = '" . $idpays . "'";
$sql .= " WHERE fd.fk_code_ventilation > 0";
if (! empty($conf->multicompany->enabled)) {
	//$sql .= " AND f.entity = " . $conf->entity;
}
$sql .= " AND f.fk_statut > 0";
/*if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS))
	$sql .= " AND f.type IN (0,1,2)";
else*/	
	$sql .= " AND f.type IN (0,1,2,3)";
$sql .= " AND fd.product_type IN (0,1)";
if ($date_start && $date_end)
	$sql .= " AND f.datef >= '" . $db->idate($date_start) . "' AND f.datef <= '" . $db->idate($date_end) . "'";
//$sql .= " AND f.fk_soc=7335"; 
$sql .= " ORDER BY f.datef";

dol_syslog('accountingex/journal/sellsjournal.php:: $sql=' . $sql);
$result = $db->query($sql);
if ($result) {
	$tabfac = array ();
	$tabht = array ();
	$tabtva = array ();
	$tabttc = array ();
	$tabcompany = array ();
	$tabcomptanna = array ();
	
	$num = $db->num_rows($result);
	$i = 0;
	$resligne = array ();
	while ( $i < $num ) {
		$obj = $db->fetch_object($result);
		// les variables
		$cptcli = (! empty($conf->global->COMPTA_ACCOUNT_CUSTOMER)) ? $conf->global->COMPTA_ACCOUNT_CUSTOMER : $langs->trans("CodeNotDef");
		$compta_soc = (! empty($obj->code_compta)) ? $obj->code_compta : $cptcli;
		
		$compta_prod = $obj->compte;
		if (empty($compta_prod)) {
			if ($obj->product_type == 0)
				$compta_prod = (! empty($conf->global->COMPTA_PRODUCT_SOLD_ACCOUNT)) ? $conf->global->COMPTA_PRODUCT_SOLD_ACCOUNT : $langs->trans("CodeNotDef");
			else
				$compta_prod = (! empty($conf->global->COMPTA_SERVICE_SOLD_ACCOUNT)) ? $conf->global->COMPTA_SERVICE_SOLD_ACCOUNT : $langs->trans("CodeNotDef");
		}
		$cpttva = (! empty($conf->global->COMPTA_VAT_ACCOUNT)) ? $conf->global->COMPTA_VAT_ACCOUNT : $langs->trans("CodeNotDef");
		$compta_tva = (! empty($obj->account_tva) ? $obj->account_tva : $cpttva);
		
		// la ligne facture
		$tabfac[$obj->rowid]["date"] = $obj->df;
		$tabfac[$obj->rowid]["ref"] = $obj->facnumber;
		$tabfac[$obj->rowid]["type"] = $obj->type;
		$tabfac[$obj->rowid]["description"] = $obj->description;
		$tabfac[$obj->rowid]["fk_facturedet"] = $obj->fdid;
		$tabfac[$obj->rowid]["projet_id"] = $obj->projet_id;
		$tabfac[$obj->rowid]["projet_ref"] = $obj->projet_ref;
		$tabfac[$obj->rowid]["entity"] = $obj->entity;
		// Centre des congrés
		$tmprefix = 'N/A';
		if ($obj->entity == 3) {
			$tmprefix = '1';
		}
		// Parc des expo
		if ($obj->entity == 2) {
			$tmprefix = '2';
		}
		// BDC
		if ($obj->entity == 4) {
			$tmprefix = '3';
		}
		// Propre
		if ($obj->entity == 5) {
			$tabfac[$obj->rowid]["compta_ana"] = substr($obj->projet_ref, 7, 4);
		} /*elseif ($obj->entity == 2) {
			$tabfac[$obj->rowid]["compta_ana"] = '2AAA';
		}*/elseif (!empty($obj->pr_cd_analytics)) {
			$tabfac[$obj->rowid]["compta_ana"] = $tmprefix . $obj->pr_cd_analytics;
		} else {
			$tabfac[$obj->rowid]["compta_ana"] = $tmprefix . '000';
			//var_dump($obj->rowid);
		}
		
		if (! isset($tabttc[$obj->rowid][$compta_soc]))
			$tabttc[$obj->rowid][$compta_soc] = 0;
		if (! isset($tabht[$obj->rowid][$compta_prod]))
			$tabht[$obj->rowid][$compta_prod] = 0;
		if (! isset($tabtva[$obj->rowid][$compta_tva]))
			$tabtva[$obj->rowid][$compta_tva] = 0;
		
		$tabttc[$obj->rowid][$compta_soc] += $obj->total_ttc;
		$tabht[$obj->rowid][$compta_prod] += $obj->total_ht;
		$tabtva[$obj->rowid][$compta_tva] += $obj->total_tva;
		$tabcompany[$obj->rowid] = array (
				'id' => $obj->socid,
				'name' => $obj->name,
				'code_client' => $obj->code_compta 
		);
		
		$i ++;
	}
} else {
	dol_print_error($db);
}

//Check deposit invoices...
foreach($tabfac as $id=>$fact_array) {
	
	if ($fact_array['type']==0) {
		//Find this "normal" invoice have been payd by deposit invoice
		$sql_deposit = 'SELECT depositdet.total_ttc, depositdet.total_tva, depositdet.total_ht FROM '.MAIN_DB_PREFIX.'societe_remise_except as remx ';
		$sql_deposit .= ' INNER JOIN '.MAIN_DB_PREFIX.'facture as deposit ON deposit.rowid=remx.fk_facture_source AND deposit.type=3';
		$sql_deposit .= ' INNER JOIN '.MAIN_DB_PREFIX.'facturedet as depositdet ON depositdet.fk_facture=deposit.rowid';
		$sql_deposit .= '  WHERE remx.fk_facture='.$id;
		
		dol_syslog('accountingex/journal/sellsjournal.php:: deposit invoices $sql_deposit=' . $sql_deposit);
		$result_deposit = $db->query($sql_deposit);
		if ($result_deposit) {
			$objdeposit=$db->fetch_object($result_deposit);
			if (!empty($objdeposit->total_tva) || !empty($objdeposit->total_ttc)) {
				
				//var_dump($tabtva[$id]);
				$tabttc[$id][key($tabttc[$id])] -= $objdeposit->total_ttc;
				//$tabht[$id][key($tabht[$id])] -= $objdeposit->total_ht;
				$tabtva[$id][key($tabtva[$id])]-= $objdeposit->total_tva;
				$tabht[$id][$conf->global->ACCOUNTINGEX_ACCOUNT_DEPOSITFINALPAYEMENT] -= $objdeposit->total_ht;
				//var_dump($tabtva[$id]);
			}
			//var_dump($tabttc);
		}else {
			setEventMessage('Error:'.$db->lasterror(),'errors');
		}
	}
}

/*
 * Action
 */

// Bookkeeping Write
if ($action == 'writebookkeeping') {
	$now = dol_now();
	
	foreach ( $tabfac as $key => $val ) {
		foreach ( $tabttc[$key] as $k => $mt ) {
			$bookkeeping = new BookKeeping($db);
			$bookkeeping->doc_date = $val["date"];
			$bookkeeping->doc_ref = $val["ref"];
			$bookkeeping->date_create = $now;
			$bookkeeping->doc_type = 'customer_invoice';
			$bookkeeping->fk_doc = $key;
			$bookkeeping->fk_docdet = $val["fk_facturedet"];
			$bookkeeping->code_tiers = $tabcompany[$key]['code_client'];
			$bookkeeping->numero_compte = $conf->global->COMPTA_ACCOUNT_CUSTOMER;
			$bookkeeping->label_compte = $tabcompany[$key]['name'];
			$bookkeeping->montant = $mt;
			$bookkeeping->sens = ($mt >= 0) ? 'D' : 'C';
			$bookkeeping->debit = ($mt >= 0) ? $mt : 0;
			$bookkeeping->credit = ($mt < 0) ? $mt : 0;
			$bookkeeping->code_journal = $conf->global->ACCOUNTINGEX_SELL_JOURNAL;
			
			$bookkeeping->create();
		}
		
		// Product / Service
		foreach ( $tabht[$key] as $k => $mt ) {
			if ($mt) {
				// get compte id and label
				$compte = new AccountingAccount($db);
				if ($compte->fetch(null, $k)) {
					$bookkeeping = new BookKeeping($db);
					$bookkeeping->doc_date = $val["date"];
					$bookkeeping->doc_ref = $val["ref"];
					$bookkeeping->date_create = $now;
					$bookkeeping->doc_type = 'customer_invoice';
					$bookkeeping->fk_doc = $key;
					$bookkeeping->fk_docdet = $val["fk_facturedet"];
					$bookkeeping->code_tiers = '';
					$bookkeeping->numero_compte = $k;
					$bookkeeping->label_compte = dol_trunc($val["description"], 128);
					$bookkeeping->montant = $mt;
					$bookkeeping->sens = ($mt < 0) ? 'D' : 'C';
					$bookkeeping->debit = ($mt < 0) ? $mt : 0;
					$bookkeeping->credit = ($mt >= 0) ? $mt : 0;
					$bookkeeping->code_journal = $conf->global->ACCOUNTINGEX_SELL_JOURNAL;
					
					$bookkeeping->create();
				}
			}
		}
		
		// VAT
		// var_dump($tabtva);
		foreach ( $tabtva[$key] as $k => $mt ) {
			if ($mt) {
				$bookkeeping = new BookKeeping($db);
				$bookkeeping->doc_date = $val["date"];
				$bookkeeping->doc_ref = $val["ref"];
				$bookkeeping->date_create = $now;
				$bookkeeping->doc_type = 'customer_invoice';
				$bookkeeping->fk_doc = $key;
				$bookkeeping->fk_docdet = $val["fk_facturedet"];
				$bookkeeping->fk_compte = $compte->id;
				$bookkeeping->code_tiers = '';
				$bookkeeping->numero_compte = $k;
				$bookkeeping->label_compte = $langs->trans("VAT");
				$bookkeeping->montant = $mt;
				$bookkeeping->sens = ($mt < 0) ? 'D' : 'C';
				$bookkeeping->debit = ($mt < 0) ? $mt : 0;
				$bookkeeping->credit = ($mt >= 0) ? $mt : 0;
				$bookkeeping->code_journal = $conf->global->ACCOUNTINGEX_SELL_JOURNAL;
				
				$bookkeeping->create();
			}
		}
	}
}
// export csv
if ($action == 'export_csv') {
	$sep = $conf->global->ACCOUNTINGEX_SEPARATORCSV;
	
	header('Content-Type: text/csv');
	header('Content-Disposition: attachment;filename=journal_ventes.csv');
	
	$companystatic = new Client($db);
	
	if ($conf->global->ACCOUNTINGEX_MODELCSV == 1) 	// Modèle Export Cegid Expert
	{
		foreach ( $tabfac as $key => $val ) {
			$companystatic->id = $tabcompany[$key]['id'];
			$companystatic->name = $tabcompany[$key]['name'];
			$companystatic->client = $tabcompany[$key]['code_client'];
			
			$date = dol_print_date($db->jdate($val["date"]), '%d%m%Y');
			
			print $date . $sep;
			print $conf->global->ACCOUNTINGEX_SELL_JOURNAL . $sep;
			print length_accountg($conf->global->COMPTA_ACCOUNT_CUSTOMER) . $sep;
			foreach ( $tabttc[$key] as $k => $mt ) {
				print length_accounta(html_entity_decode($k)) . $sep;
				print ($mt < 0 ? 'C' : 'D') . $sep;
				print ($mt <= 0 ? price(- $mt) : $mt) . $sep;
				print utf8_decode($companystatic->name) . $sep;
			}
			print $val["ref"];
			print "\n";
			
			// Product / Service
			foreach ( $tabht[$key] as $k => $mt ) {
				if ($mt) {
					print $date . $sep;
					print $conf->global->ACCOUNTINGEX_SELL_JOURNAL . $sep;
					print length_accountg(html_entity_decode($k)) . $sep;
					print $sep;
					print ($mt < 0 ? 'D' : 'C') . $sep;
					print ($mt <= 0 ? price(- $mt) : $mt) . $sep;
					print dol_trunc($val["description"], 32) . $sep;
					print $val["ref"];
					print "\n";
				}
			}
			// TVA
			foreach ( $tabtva[$key] as $k => $mt ) {
				if ($mt) {
					print $date . $sep;
					print $conf->global->ACCOUNTINGEX_SELL_JOURNAL . $sep;
					print length_accountg(html_entity_decode($k)) . $sep;
					print $sep;
					print ($mt < 0 ? 'D' : 'C') . $sep;
					print ($mt <= 0 ? price(- $mt) : $mt) . $sep;
					print $langs->trans("VAT") . $sep;
					print $val["ref"];
					print "\n";
				}
			}
		}
	} else 	// Modèle Export Classique
	{
		foreach ( $tabfac as $key => $val ) {
			$companystatic->id = $tabcompany[$key]['id'];
			$companystatic->name = $tabcompany[$key]['name'];
			$companystatic->client = $tabcompany[$key]['code_client'];
			
			$date = dol_print_date($db->jdate($val["date"]), 'day');
			print '"0029"' . $sep;
			print '"' . $val['compta_ana'] . '"' . $sep;
			print '"002"' . $sep;
			
			// print '"' . $date . '"' . $sep;
			// print '"' . $val ["ref"] . '"' . $sep;
			foreach ( $tabttc[$key] as $k => $mt ) {
				print '"' . length_accounta(html_entity_decode($k)) . '"' . $sep;
				print '"' . utf8_decode($companystatic->name) . ' ' . $val["ref"] . '"' . $sep;
				print '"' . ($mt >= 0 ? price($mt) : '') . '"' . $sep;
				print '"' . ($mt < 0 ? price(- $mt) : '') . '"' . $sep;
				if ((substr($k, 0, 2) == '40') || (substr($k, 0, 2) == '41')) {
					//print '"029CCCC"' . $sep;
					print substr($k, 0, 3).str_pad($companystatic->id, 5, "0", STR_PAD_LEFT) . $sep;
				} else {
					print '""' . $sep;
				}
				print '"' . $date . '"';
			}
			print "\n";
			
			// Product / Service
			foreach ( $tabht[$key] as $k => $mt ) {
				if ($mt) {
					print '"0029"' . $sep;
					print '"' . $val['compta_ana'] . '"' . $sep;
					print '"002"' . $sep;
					// print '"' . $date . '"' . $sep;
					// print '"' . $val ["ref"] . '"' . $sep;
					print '"' . length_accountg(html_entity_decode($k)) . '"' . $sep;
					print '"' . utf8_decode($companystatic->name) . ' ' . $val["ref"] . '"' . $sep;
					// print '"'.$langs->trans("Products").'"'.$sep;
					print '"' . ($mt < 0 ? price(- $mt) : '') . '"' . $sep;
					print '"' . ($mt >= 0 ? price($mt) : '') . '"' . $sep;
					if ((substr($k, 0, 2) == '40') || (substr($k, 0, 2) == '41')) {
						//print '"029CCCC"' . $sep;
						print substr($k, 0, 3).str_pad($companystatic->id, 5, "0", STR_PAD_LEFT) . $sep;
					} else {
						print '""' . $sep;
					}
					print '"' . $date . '"';
					print "\n";
				}
			}
			
			// VAT
			// var_dump($tabtva);
			foreach ( $tabtva[$key] as $k => $mt ) {
				if ($mt) {
					print '"0029"' . $sep;
					print '"' . $val['compta_ana'] . '"' . $sep;
					print '"002"' . $sep;
					// print '"' . $date . '"' . $sep;
					// print '"' . $val ["ref"] . '"' . $sep;
					// print '"'.utf8_decode($companystatic->name).' '.$val ["ref"].'"'.$sep;
					print '"' . length_accountg(html_entity_decode($k)) . '"' . $sep;
					print '"' . utf8_decode($companystatic->name) . ' ' . $val["ref"] . '"' . $sep;
					// print '"'.$langs->trans("VAT").'"'.$sep;
					print '"' . ($mt < 0 ? price(- $mt) : '') . '"' . $sep;
					print '"' . ($mt >= 0 ? price($mt) : '') . '"' . $sep;
					print '""' . $sep;
					print '"' . $date . '"';
					print "\n";
				}
			}
		}
	}
} else {
	
	$form = new Form($db);
	$formfile = new FormVentilation($db);
	
	llxHeader('', $langs->trans("SellsJournal"));
	
	$nom = $langs->trans("SellsJournal");
	$nomlink = '';
	$periodlink = '';
	$exportlink = '';
	$builddate = time();
	$description = $langs->trans("DescSellsJournal") . '<br>';
	//if (! empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS))
		//$description .= $langs->trans("DepositsAreNotIncluded");
	//else
		$description .= $langs->trans("DepositsAreIncluded");
	$period = $form->select_date($date_start, 'date_start', 0, 0, 0, '', 1, 0, 1) . ' - ' . $form->select_date($date_end, 'date_end', 0, 0, 0, '', 1, 0, 1);
	report_header($nom, $nomlink, $period, $periodlink, $description, $builddate, $exportlink, array (
			'action' => '' 
	));
	
	print '<input type="button" class="button" style="float: right;" value="Export CSV" onclick="launch_export();" />';
	
	print '<input type="button" class="button" value="' . $langs->trans("WriteBookKeeping") . '" onclick="writebookkeeping();" />';
	
	print '
	<script type="text/javascript">
		function launch_export() {
		    $("div.fiche div.tabBar form input[name=\"action\"]").val("export_csv");
			$("div.fiche div.tabBar form input[type=\"submit\"]").click();
		    $("div.fiche div.tabBar form input[name=\"action\"]").val("");
		}
		function writebookkeeping() {
		    $("div.fiche div.tabBar form input[name=\"action\"]").val("writebookkeeping");
			$("div.fiche div.tabBar form input[type=\"submit\"]").click();
		    $("div.fiche div.tabBar form input[name=\"action\"]").val("");
		}
	</script>';
	
	/*
	 * Show result array
	 */
	print '<br><br>';
	
	$i = 0;
	print "<table class=\"noborder\" width=\"100%\">";
	print "<tr class=\"liste_titre\">";
	print "<td>" . $langs->trans("Date") . "</td>";
	print "<td>" . $langs->trans("Piece") . ' (' . $langs->trans("InvoiceRef") . ")</td>";
	print "<td></td>"; 
	print "<td>" . $langs->trans("Account") . "</td>";
	print "<td>" . $langs->trans("Type") . "</td>";
	print "<td>" . $langs->trans("Code Auxiliaire") . "</td>";
	print "<td align='right'>" . $langs->trans("Debit") . "</td>";
	print "<td align='right'>" . $langs->trans("Credit") . "</td>";
	print "<th align='right'>" . $langs->trans("Projet") . "</th>";
	print "<th align='right'>" . $langs->trans("Compta Analytique") . "</th>";
	print "</tr>\n";
	
	$var = true;
	$r = '';
	
	$invoicestatic = new Facture($db);
	$companystatic = new Client($db);
	$total_credit=0;
	$total_debit=0;
	$total_balance=0;
	foreach ( $tabfac as $key => $val ) {
		$invoicestatic->id = $key;
		$invoicestatic->ref = $val["ref"];
		$invoicestatic->type = $val["type"];
		$invoicestatic->description = html_entity_decode(dol_trunc($val["description"], 32));
		
		$date = dol_print_date($db->jdate($val["date"]), 'day');
		
		print "<tr " . $bc[$var] . ">";
		
		// Third party
		// print "<td>".$conf->global->COMPTA_JOURNAL_SELL."</td>";
		print "<td>" . $date . "</td>";
		print "<td>" . $invoicestatic->getNomUrl(1) . "</td>";
		print "<td>";
		$filename=dol_sanitizeFileName($invoicestatic->ref);
		$filedir=DOL_DATA_ROOT.'/'.$val['entity'].'/facture' . '/' . dol_sanitizeFileName($invoicestatic->ref);
		//print $filedir;
		print $formfile->getDocumentsLinkCompta($invoicestatic->element, $filename, $filedir);
		print "</td>";
		foreach ( $tabttc[$key] as $k => $mt ) {
			$companystatic->id = $tabcompany[$key]['id'];
			$companystatic->name = $tabcompany[$key]['name'];
			$companystatic->client = $tabcompany[$key]['code_client'];
			print "<td>" . length_accounta($k);
			print "</td><td>" . $langs->trans("ThirdParty");
			print ' (' . $companystatic->getNomUrl(0, 'customer', 16) . ')';
			print "</td>";
			print "<td>" ;
			print substr($k, 0, 3).str_pad($companystatic->id, 5, "0", STR_PAD_LEFT);
			print "</td>";
			print "<td align='right'>" . ($mt >= 0 ? price($mt) : '') . "</td>";
			print "<td align='right'>" . ($mt < 0 ? price(- $mt) : '') . "</td>";
			print '<td align="right">' . $val['projet_ref'] . '</td>';
			print '<td align="right">' . $val['compta_ana'] . '</td>';
			
			$total_credit +=($mt >= 0 ? $mt : 0);
			$total_debit +=($mt < 0 ? - $mt : 0);
		}
		print "</tr>";
		
		// Product / Service
		foreach ( $tabht[$key] as $k => $mt ) {
			if ($mt) {
				print "<tr " . $bc[$var] . ">";
				// print "<td>".$conf->global->COMPTA_JOURNAL_SELL."</td>";
				print "<td>" . $date . "</td>";
				print "<td>" . $invoicestatic->getNomUrl(1)."</td>";
				print "<td>";
				$filename=dol_sanitizeFileName($invoicestatic->ref);
				$filedir=DOL_DATA_ROOT.'/'.$val['entity'].'/facture' . '/' . dol_sanitizeFileName($invoicestatic->ref);
				print $formfile->getDocumentsLinkCompta($invoicestatic->element, $filename, $filedir);
				print "</td>";
				
				print "<td>" . length_accountg($k) . "</td>";
				//var_dump($k);
				if ($k==$conf->global->ACCOUNTINGEX_ACCOUNT_DEPOSITFINALPAYEMENT) {
					print "<td>" . 'Accompte' . "</td>";
				} else {
					print "<td>" . 'Produit' . "</td>";
				}
				print "<td>" ;
				print "</td>" ;
				print "<td align='right'>" . ($mt < 0 ? price(- $mt) : '') . "</td>";
				print "<td align='right'>" . ($mt >= 0 ? price($mt) : '') . "</td>";
				print '<td align="right">' . $val['projet_ref'] . '</td>';
				print '<td align="right">' . $val['compta_ana'] . '</td>';
				print "</tr>";
				
				$total_credit +=($mt < 0 ? - $mt : 0);
				$total_debit +=($mt >= 0 ? $mt : 0);
			}
		}
		
		// VAT
		// var_dump($tabtva);
		foreach ( $tabtva[$key] as $k => $mt ) {
			if ($mt) {
				print "<tr " . $bc[$var] . ">";
				// print "<td>".$conf->global->COMPTA_JOURNAL_SELL."</td>";
				print "<td>" . $date . "</td>";
				print "<td>" . $invoicestatic->getNomUrl(1) . "</td>";
				print "<td>";
				$filename=dol_sanitizeFileName($invoicestatic->ref);
				$filedir=DOL_DATA_ROOT.'/'.$val['entity'].'/facture' . '/' . dol_sanitizeFileName($invoicestatic->ref);
				print $formfile->getDocumentsLinkCompta($invoicestatic->element, $filename, $filedir);
				print "</td>";
				print "<td>" . length_accountg($k) . "</td>";
				print "<td>" . $langs->trans("VAT") . "</td>";
				print "<td>" ;
				print "</td>" ;
				print "<td align='right'>" . ($mt < 0 ? price(- $mt) : '') . "</td>";
				print "<td align='right'>" . ($mt >= 0 ? price($mt) : '') . "</td>";
				print '<td align="right">' . $val['projet_ref'] . '</td>';
				print '<td align="right">' . $val['compta_ana'] . '</td>';
				print "</tr>";
				
				$total_credit +=($mt < 0 ? - $mt : 0);
				$total_debit +=($mt >= 0 ? $mt : 0);
			}
		}
		
		$var = ! $var;
		
		
	}
	
	print "<tr " . $bc[$var] . ">";
	// print "<td>".$conf->global->COMPTA_JOURNAL_SELL."</td>";
	print "<td>TOTAL</td>";
	print "<td></td>";
	print "<td></td>";
	print "<td></td>";
	print "<td></td>";
	print "<td align='right'>" . price($total_credit) . "</td>";
	print "<td align='right'>" .  price($total_debit) . "</td>";
	print '<td align="right"></td>';
	print '<td align="right"></td>';
	print "</tr>";
	
	print "</table>";
	
	// End of page
	llxFooter();
}
$db->close();