<?php
/**
 * peppol_import.php
 *
 * Allows the user to either:
 *   1) Pass a peppol_id via GET (e.g. from the “Import” link in peppol_list.php), OR
 *   2) If none is provided, show a small form where they can type in a Peppol ID manually.
 *
 * Once we have a non‐empty peppol_id, we fetch the invoice from Billit’s API and create
 * a Dolibarr supplier invoice (FactureFournisseur) so it appears under Expenditure → Invoices.
 */

require '../../main.inc.php';
dol_include_once('/compta/facture/class/facture.class.php');
dol_include_once('/societe/class/societe.class.php');
dol_include_once('/core/lib/functions2.lib.php'); // For dol_parse_date()
 
$langs->load('peppolexport@peppolexport');
llxHeader('', $langs->trans('ImportPeppolInvoice'));

$action = GETPOST('action', 'alpha');
$peppol_id = trim(GETPOST('peppol_id', 'alpha'));

// If neither GET nor POST provided a peppol_id, show the manual‐entry form:
if (empty($peppol_id) && $action !== 'process')
{
    print_fiche_titre($langs->trans('ImportPeppolInvoice'), '', 'peppol');

    // Show a simple form asking for Peppol ID
    print '<form method="POST" action="peppol_import.php">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="process">';
    print '<div class="form-group">';
    print '  <label for="peppol_id">'.$langs->trans('PeppolID').'</label>';
    print '  <input class="form-control" type="text" id="peppol_id" name="peppol_id" value="" placeholder="e.g. INV-2025-0001">';
    print '</div>';
    print '<div class="form-group">';
    print '  <button class="btn btn-primary" type="submit">'.$langs->trans('FetchAndImport').'</button>';
    print '</div>';
    print '</form>';

    llxFooter();
    exit;
}

// At this point, either we were called via GET with peppol_id, or the user just submitted the form
if (empty($peppol_id))
{
    // Still no ID: show an error and go back to the list
    setEventMessages($langs->trans('ErrorMissingPeppolID'), null, 'errors');
    header('Location: peppol_list.php');
    exit;
}

// === 1) YOUR BILLIT CREDENTIALS ===
// You can define these in Dolibarr’s global settings (e.g. via $conf->global), or hard‐code:
$apiToken = "e8924baa-8956-4b9d-b8d9-9271f84f1c70";     // e.g. 'e8924baa-8956-4b9d-b8d9-9271f84f1c70'
$partyId  = "655986";      // e.g. '655986'

if (empty($apiToken) || empty($partyId))
{
    setEventMessages($langs->trans('ErrorNoBillitApiCredentials'), null, 'errors');
    header('Location: peppol_list.php');
    exit;
}

// === 2) FETCH FULL INVOICE JSON FROM BILLIT ===
// Billit’s endpoint for a single Peppol invoice (sandbox):
$endpoint = 'https://api.sandbox.billit.be/v1/import' . urlencode($peppol_id);

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json',
    'apiKey: '  . $apiToken,
    'partyId: ' . $partyId,
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200)
{
    setEventMessages($langs->trans('ErrorBillitHTTP', $httpCode, $curlErr), null, 'errors');
    header('Location: peppol_list.php');
    exit;
}

$invoiceData = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE)
{
    setEventMessages($langs->trans('ErrorBillitInvalidJSON'), null, 'errors');
    header('Location: peppol_list.php');
    exit;
}

// Some Billit responses wrap the actual invoice under ["invoice"], so normalize:
if (isset($invoiceData['invoice']) && is_array($invoiceData['invoice']))
{
    $invoiceData = $invoiceData['invoice'];
}

// Now $invoiceData should contain something like:
//   ['id'], ['date'], ['supplierName'], ['supplierVAT'], ['lines'], ['totalNet'], ['totalVat'], ['totalGross'], etc.

// === 3) FIND (OR CREATE) THE THIRD PARTY (SUPPLIER) IN DOLIBARR ===
$supplierVAT = isset($invoiceData['supplierVAT']) ? $invoiceData['supplierVAT'] : '';
$soc = new Societe($db);

// Try to fetch an existing supplier by VAT number:
$thirdpartyId = 0;
if (!empty($supplierVAT))
{
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."societe WHERE vat_intra = '".$db->escape($supplierVAT)."' LIMIT 1";
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0)
    {
        $obj = $db->fetch_object($resql);
        $thirdpartyId = $obj->rowid;
    }
}

// If no match, create a new Societe (supplier)
if (empty($thirdpartyId))
{
    $soc->name        = (string)$invoiceData['supplierName'];
    $soc->client      = 0;
    $soc->fournisseur = 1;
    $soc->typent_id   = '1';              // “Other” by default—adjust if you have specific types
    $soc->address     = '';               // If Billit provides address fields, map them here
    $soc->zip         = '';
    $soc->town        = '';
    $soc->country_id  = '';               // Or look up based on Billit data
    $soc->socpeople   = [];
    $soc->idprof1     = $supplierVAT;     // VAT number
    $soc->idprof2     = '';
    $soc->statut      = 1;                // Active

    $res = $soc->create($user);
    if ($res < 0)
    {
        setEventMessages($langs->trans('ErrorFailedToCreateSupplier', $soc->errors), null, 'errors');
        header('Location: peppol_list.php');
        exit;
    }
    $thirdpartyId = $res;
}
else
{
    $soc->fetch($thirdpartyId);
}

// === 4) CREATE A NEW FactureFournisseur (Supplier Invoice) ===
$supplierInvoice = new FactureFournisseur($db);
$supplierInvoice->ref_supplier = (string)$invoiceData['id'];               // Billit’s Peppol Invoice ID
$supplierInvoice->socid        = $thirdpartyId;                            // Link to Societe
$supplierInvoice->date         = dol_parse_date(substr($invoiceData['date'], 0, 10), 'day');
$supplierInvoice->datee        = $supplierInvoice->date;                   // Invoice date
$supplierInvoice->date_lim_reglement = '';                                  // If Billit sends due date, set it with dol_parse_date()
$supplierInvoice->note_private = '';                                        // Internal notes
$supplierInvoice->note_public  = '';                                        // Public notes
$supplierInvoice->paye         = 0;                                         // Not paid yet
$supplierInvoice->cond_reglement_id = 0;                                     // Payment terms (0 = none)
$supplierInvoice->mode_reglement_id  = 0;                                    // Payment mode (0 = none)
$supplierInvoice->multicurrency_code  = '';                                  // Usually empty unless multi-currency
$supplierInvoice->multicurrency_tx    = 1;                                   // 1 = no conversion, or actual rate if multi-currency
$supplierInvoice->fk_incoterms       = 0;                                    // Incoterms if any
$supplierInvoice->fk_bank            = 0;                                    // Bank account if any

// === 4a) Parse detail lines if Billit returned them ===
if (isset($invoiceData['lines']) && is_array($invoiceData['lines']) && count($invoiceData['lines']) > 0)
{
    $linePos = 0;
    foreach ($invoiceData['lines'] as $line)
    {
        $desc     = (string)$line['description'];
        $qty      = (float)$line['quantity'];
        $pu       = (float)$line['unitPrice'];      // Net unit price
        $vatRate  = (float)$line['vatRate'];        // e.g. 21
        $totalNet = (float)$line['totalLineAmount']; // Net total for this line (unused, Dolibarr recalculates)

        // Create a new line object
        $supplierInvoice->lines[$linePos] = new FactureLigne($db);
        $supplierInvoice->lines[$linePos]->fk_product   = 0;     // or a mapped product if you have one
        $supplierInvoice->lines[$linePos]->desc         = $desc;
        $supplierInvoice->lines[$linePos]->qty          = $qty;
        $supplierInvoice->lines[$linePos]->subprice     = $pu;
        // Set VAT% exactly as Billit sends (e.g. “21” for 21%)
        $supplierInvoice->lines[$linePos]->tva_tx       = $vatRate;
        $supplierInvoice->lines[$linePos]->localtax1    = 0;
        $supplierInvoice->lines[$linePos]->localtax2    = 0;
        $supplierInvoice->lines[$linePos]->info_bits    = 0;     // no special flags

        $linePos++;
    }
}
// === 4b) If Billit only returned totals (no lines), set totals directly ===
if (!isset($invoiceData['lines']) || count($invoiceData['lines']) === 0)
{
    // Example fields that Billit might provide:
    //    ['totalNet']   = total excluding VAT
    //    ['totalVat']   = total VAT amount
    //    ['totalGross'] = total including VAT
    $supplierInvoice->total_net = isset($invoiceData['totalNet'])   ? (float)$invoiceData['totalNet']   : 0;
    $supplierInvoice->total_tva = isset($invoiceData['totalVat'])   ? (float)$invoiceData['totalVat']   : 0;
    $supplierInvoice->total_ttc = isset($invoiceData['totalGross']) ? (float)$invoiceData['totalGross'] : 0;
}

// === 5) SAVE (CREATE) THE SUPPLIER INVOICE IN DOLIBARR ===
$result = $supplierInvoice->create($user, 0, $conf->global->MAIN_AUTODESTROY_SHIPPING_LINES);
if ($result < 0)
{
    setEventMessages($langs->trans('ErrorFailedToCreateInvoice', implode(', ', $supplierInvoice->errors)), null, 'errors');
}
else
{
    setEventMessages($langs->trans('SupplierInvoiceCreatedOK', $result), null, 'mesgs');
}

// After attempting to create, go back to the Peppol list
header('Location: peppol_list.php');
exit;
