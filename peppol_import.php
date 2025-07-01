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
$apiToken = 'e8924baa-8956-4b9d-b8d9-9271f84f1c70';
$partyId = '655986';

if (empty($apiToken) || empty($partyId))
{
    setEventMessages($langs->trans('ErrorNoBillitApiCredentials'), null, 'errors');
    header('Location: peppol_list.php');
    exit;
}

// === 2) FETCH FULL INVOICE JSON FROM BILLIT ===
// Billit’s endpoint for a single Peppol invoice (sandbox):

$ch = curl_init('https://api.sandbox.billit.be/v1/orders');
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


// After attempting to create, go back to the Peppol list
header('Location: peppol_list.php');
exit;
