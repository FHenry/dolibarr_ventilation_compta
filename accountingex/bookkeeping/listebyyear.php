<?PHP
/* Copyright (C) 2013-2014 Olivier Geffroy		<jeff@jeffinfo.com>
 * Copyright (C) 2013-2014 Alexandre Spangaro	<alexandre.spangaro@gmail.com> 
 * Copyright (C) 2013-2014 Florian Henry		<florian.henry@open-concept.pro>
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
 *
 */

/**
 * \file		accountingex/bookkeeping/listebyyear.php
 * \ingroup	Accounting Expert
 * \brief		Book keeping by year
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
dol_include_once("/core/lib/date.lib.php");
dol_include_once("/accountingex/core/lib/account.lib.php");

// Langs
$langs->load("accountingex@accountingex");

$page = GETPOST("page");
$sortorder = GETPOST("sortorder");
$sortfield = GETPOST("sortfield");

// Filter
$year = GETPOST("year", 'int');
if ($year == 0) {
	$year_current = strftime("%Y", time());
	$year_start = $year_current;
} else {
	$year_current = $year;
	$year_start = $year;
}

if ($sortorder == "")
	$sortorder = "ASC";
if ($sortfield == "")
	$sortfield = "bk.rowid";

$offset = $conf->liste_limit * $page;

llxHeader('', $langs->trans("Bookkeeping"));

$textprevyear = "<a href=\"listebyyear.php?year=" . ($year_current - 1) . "\">" . img_previous() . "</a>";
$textnextyear = " <a href=\"listebyyear.php?year=" . ($year_current + 1) . "\">" . img_next() . "</a>";

/*
 * Mode Liste
 *
 */

$sql = "SELECT bk.rowid, bk.doc_date, bk.doc_type, bk.doc_ref, bk.code_tiers, bk.numero_compte , bk.label_compte, bk.debit , bk.credit, bk.montant , bk.sens, bk.code_journal";
$sql .= " FROM " . MAIN_DB_PREFIX . "bookkeeping as bk";
// $sql .= " WHERE bk.doc_date >= '".$db->idate(dol_get_first_day($y,1,false))."'";
// $sql .= " AND bk.doc_date <= '".$db->idate(dol_get_last_day($y,12,false))."'";
$sql .= " ORDER BY $sortfield $sortorder " . $db->plimit($conf->liste_limit + 1, $offset);

dol_syslog('accountingex/bookkeeping/listebyyear.php:: $sql=' . $sql);
$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);
	$i = 0;
	
	print_barre_liste($langs->trans("Bookkeeping") . " $textprevyear " . $langs->trans("Year") . " $year_start $textnextyear", $page, $_SERVER ['PHP_SELF'], "", $sortfield, $sortorder, '', $num);
	print "<table class=\"noborder\" width=\"100%\">";
	
	print '<tr class="liste_titre">';
	print_liste_field_titre($langs->trans("Doctype"), $_SERVER ['PHP_SELF'], "bk.doc_type", "", "", "", $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Docdate"), $_SERVER ['PHP_SELF'], "bk.doc_date", "", "", "", $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Docref"), $_SERVER ['PHP_SELF'], "bk.doc_ref", "", "", "", $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Numerocompte"), $_SERVER ['PHP_SELF'], "bk.numero_compte", "", "", "", $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Code_tiers"), $_SERVER ['PHP_SELF'], "bk.code_tiers", "", "", "", $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Labelcompte"), $_SERVER ['PHP_SELF'], "bk_label_compte", "", "", "", $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Debit"), $_SERVER ['PHP_SELF'], "bk.debit", "", "", "", $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Credit"), $_SERVER ['PHP_SELF'], "bk.credit", "", "", "", $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Amount"), $_SERVER ['PHP_SELF'], "bk.montant", "", "", "", $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Sens"), $_SERVER ['PHP_SELF'], "bk.sens", "", "", "", $sortfield, $sortorder);
	print_liste_field_titre($langs->trans("Codejournal"), $_SERVER ['PHP_SELF'], "bk.code_journal", "", "", "", $sortfield, $sortorder);
	print_liste_field_titre("&nbsp;");
	print "</tr>\n";
	
	$var = True;
	
	while ( $i < min($num, $conf->liste_limit) ) {
		$obj = $db->fetch_object($resql);
		$var = ! $var;
		
		print "<tr $bc[$var]>";
		
		print '<td>' . $obj->doc_type . '</td>' . "\n";
		print '<td>' . dol_print_date($db->jdate($obj->doc_date)) . '</td>';
		print '<td>' . $obj->doc_ref . '</td>';
		print '<td>' . length_accountg($obj->numero_compte) . '</td>';
		print '<td>' . length_accounta($obj->code_tiers) . '</td>';
		print '<td>' . $obj->label_compte . '</td>';
		print '<td align="right">' . price($obj->debit) . '</td>';
		print '<td align="right">' . price($obj->credit) . '</td>';
		print '<td align="right">' . price($obj->montant) . '</td>';
		print '<td>' . $obj->sens . '</td>';
		print '<td>' . $obj->code_journal . '</td>';
		print '<td><a href="./fiche.php?action=update&id=' . $obj->rowid . '">' . img_edit() . '</a></td>';
		print "</tr>\n";
		
		$i ++;
	}
	print "</table>";
	$db->free($resql);
} else {
	dol_print_error($db);
}

llxFooter();
$db->close();