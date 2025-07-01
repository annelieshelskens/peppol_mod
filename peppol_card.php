<?php
/* Copyright (C) 2017       Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2025		SuperAdmin
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
 *    \file       peppol_card.php
 *    \ingroup    peppolexport
 *    \brief      Page to create/edit/view peppol
 */


// General defined Options
//if (! defined('CSRFCHECK_WITH_TOKEN'))     define('CSRFCHECK_WITH_TOKEN', '1');					// Force use of CSRF protection with tokens even for GET
//if (! defined('MAIN_AUTHENTICATION_MODE')) define('MAIN_AUTHENTICATION_MODE', 'aloginmodule');	// Force authentication handler
//if (! defined('MAIN_LANG_DEFAULT'))        define('MAIN_LANG_DEFAULT', 'auto');					// Force LANG (language) to a particular value
//if (! defined('MAIN_SECURITY_FORCECSP'))   define('MAIN_SECURITY_FORCECSP', 'none');				// Disable all Content Security Policies
//if (! defined('NOBROWSERNOTIF'))     		 define('NOBROWSERNOTIF', '1');					// Disable browser notification
//if (! defined('NOIPCHECK'))                define('NOIPCHECK', '1');						// Do not check IP defined into conf $dolibarr_main_restrict_ip
//if (! defined('NOLOGIN'))                  define('NOLOGIN', '1');						// Do not use login - if this page is public (can be called outside logged session). This includes the NOIPCHECK too.
//if (! defined('NOREQUIREAJAX'))            define('NOREQUIREAJAX', '1');       	  		// Do not load ajax.lib.php library
//if (! defined('NOREQUIREDB'))              define('NOREQUIREDB', '1');					// Do not create database handler $db
//if (! defined('NOREQUIREHTML'))            define('NOREQUIREHTML', '1');					// Do not load html.form.class.php
//if (! defined('NOREQUIREMENU'))            define('NOREQUIREMENU', '1');					// Do not load and show top and left menu
//if (! defined('NOREQUIRESOC'))             define('NOREQUIRESOC', '1');					// Do not load object $mysoc
//if (! defined('NOREQUIRETRAN'))            define('NOREQUIRETRAN', '1');					// Do not load object $langs
//if (! defined('NOREQUIREUSER'))            define('NOREQUIREUSER', '1');					// Do not load object $user
//if (! defined('NOSCANGETFORINJECTION'))    define('NOSCANGETFORINJECTION', '1');			// Do not check injection attack on GET parameters
//if (! defined('NOSCANPOSTFORINJECTION'))   define('NOSCANPOSTFORINJECTION', '1');			// Do not check injection attack on POST parameters
//if (! defined('NOSESSION'))                define('NOSESSION', '1');						// On CLI mode, no need to use web sessions
//if (! defined('NOSTYLECHECK'))             define('NOSTYLECHECK', '1');					// Do not check style html tag into posted data
//if (! defined('NOTOKENRENEWAL'))           define('NOTOKENRENEWAL', '1');					// Do not roll the Anti CSRF token (used if MAIN_SECURITY_CSRF_WITH_TOKEN is on)


// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
dol_include_once('/peppolexport/class/peppol.class.php');
dol_include_once('/peppolexport/lib/peppolexport_peppol.lib.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Societe $mysoc
 * @var Translate $langs
 * @var User $user
 */

<?php
// peppol_card.php – a “Manual New Peppol Invoice” form

require '../../main.inc.php';
dol_include_once('/compta/facture/class/facture.class.php');
dol_include_once('/societe/class/societe.class.php');
dol_include_once('/core/lib/functions2.lib.php'); // for dol_parse_date()

$langs->load('peppolexport@peppolexport');
 
// If user clicked “Save”:
if (GETPOST('action') === 'save')
{
    // Read submitted form values
    $peppol_id     = GETPOST('peppol_id', 'alpha');
    $supplier_id   = GETPOST('supplier_id', 'int');
    $invoice_date  = GETPOST('invoice_date', 'alpha');
    $amount_ht     = GETPOST('amount_ht', 'comma');
    $vat_rate      = GETPOST('vat_rate', 'int');

    // Basic validation
    if (empty($peppol_id) || empty($supplier_id) || empty($invoice_date) || empty($amount_ht))
    {
        setEventMessages($langs->trans('ErrorParameterMissing'), null, 'errors');
    }
    else
    {
        // Create supplier invoice object
        $invoice = new FactureFournisseur($db);

        // 1) Assign the Peppol ID into “Supplier reference”
        $invoice->ref_supplier = $peppol_id;

        // 2) Link to existing third party
        $invoice->socid = $supplier_id;

        // 3) Parse and set invoice date
        $ts = dol_parse_date($invoice_date, 'day');  
        if ($ts <= 0)
        {
            setEventMessages($langs->trans('ErrorInvalidDate'), null, 'errors');
        }
        else
        {
            $invoice->date       = $ts;
            $invoice->datee      = $ts;
            // Optionally set due date if your form has it:
            // $invoice->date_lim_reglement = dol_parse_date(GETPOST('due_date','alpha'), 'day');

            // 4) Build a single line
            $net_amt = price2num($amount_ht);
            $line = new FactureLigne($db);
            $line->desc      = $langs->trans('ManuallyEnteredLine');
            $line->qty       = 1;
            $line->subprice  = $net_amt;
            $line->tva_tx    = $vat_rate;
            $line->localtax1 = 0;
            $line->localtax2 = 0;
            $line->info_bits = 0;

            $invoice->lines[] = $line;

            // 5) Create the invoice
            $res = $invoice->create($user);
            if ($res < 0) {
                setEventMessages($langs->trans('ErrorFailedToCreateInvoice'), $invoice->errors, 'errors');
            } else {
                setEventMessages($langs->trans('InvoiceCreatedSuccessfully', $res), null, 'mesgs');
                Header('Location: ' . DOL_URL_ROOT . '/fourn/facture/card.php?id=' . $res);
                exit;
            }
        }
    }
}

// If not saving (or if errors), show the form

llxHeader('', $langs->trans('NewPeppolInvoice'));

print '<form method="POST" action="peppol_card.php">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="save">';

// 1) Peppol ID
print '<div class="form-group">';
print '  <label for="peppol_id">'.$langs->trans('PeppolID').'</label>';
print '  <input class="form-control" type="text" id="peppol_id" name="peppol_id" value="'.dol_htmlentities(GETPOST('peppol_id','alpha')).'">';
print '</div>';

// 2) Supplier (third party) dropdown
print '<div class="form-group">';
print '  <label for="supplier_id">'.$langs->trans('Supplier').'</label>';
print '  <select class="form-control" id="supplier_id" name="supplier_id">';
print '    <option value="">-- '.$langs->trans('None').' --</option>';
$sql = "SELECT rowid, nom FROM ".MAIN_DB_PREFIX."societe WHERE fournisseur = 1 ORDER BY nom";
$resql = $db->query($sql);
while ($obj = $db->fetch_object($resql)) {
    $selected = ($obj->rowid == GETPOST('supplier_id','int') ? ' selected' : '');
    print '<option value="'.$obj->rowid.'"'.$selected.'>'.dol_htmlentities($obj->nom).'</option>';
}
print '  </select>';
print '</div>';

// 3) Invoice date
print '<div class="form-group">';
print '  <label for="invoice_date">'.$langs->trans('Date').'</label>';
print '  <input class="form-control" type="text" id="invoice_date" name="invoice_date" value="'.dol_htmlentities(GETPOST('invoice_date','alpha') ?: dol_print_date(time(), '%Y-%m-%d')).'" placeholder="YYYY-MM-DD">';
print '</div>';

// 4) Net Amount
print '<div class="form-group">';
print '  <label for="amount_ht">'.$langs->trans('AmountHT').'</label>';
print '  <input class="form-control" type="text" id="amount_ht" name="amount_ht" value="'.dol_htmlentities(GETPOST('amount_ht','comma')).'" placeholder="1000,00">';
print '</div>';

// 5) VAT Rate
print '<div class="form-group">';
print '  <label for="vat_rate">'.$langs->trans('VATRatePercent').'</label>';
print '  <input class="form-control" type="text" id="vat_rate" name="vat_rate" value="'.dol_htmlentities(GETPOST('vat_rate','int') ?: '21').'" placeholder="21">';
print '</div>';

// 6) Submit button
print '<div class="form-group">';
print '  <button class="btn btn-primary" type="submit">'.$langs->trans('Save').'</button>';
print '</div>';

print '</form>';

llxFooter();
