<?php
// peppol_list.php
//
// Fetches Billit’s GET /v1/orders?include=OrderPDF, filters to “OrderDirection = Cost”
// (supplier invoices), and renders them in a Dolibarr‐styled table. The “Downloaden” link
// points at download_document.php?regId=…&fileId=…  (no apiKey header in cURL here).
//
// Save this file as: htdocs/custom/peppolexport/peppol_list.php
// ----------------------------------------------------------------------------

require '../../main.inc.php';
$langs->load('peppolexport@peppolexport');
llxHeader('', $langs->trans('Peppol'));

print_fiche_titre($langs->trans('IncomingPeppolInvoices'), '', 'peppol');

// --------------------------------------------------------------------
// 1) Read Billit “partyID” from Dolibarr global settings
// --------------------------------------------------------------------
$apiToken = 'e8924baa-8956-4b9d-b8d9-9271f84f1c70';
$partyId = '655986';
if (empty($partyId)) {
    print '<div class="error">' . $langs->trans('ErrorNoBillitPartyID') . "</div>\n";
    print '<div>' . $langs->trans('PleaseConfigureBillitPartyID') . "</div>\n";
    llxFooter();
    exit;
}

// --------------------------------------------------------------------
// 2) Build Billit “GET /v1/orders” URL with include=OrderPDF
// --------------------------------------------------------------------
// Sandbox:
$sandboxUrl = 'https://api.sandbox.billit.be/v1/orders?include=OrderPDF';
// Production (when live), comment out sandbox and uncomment this:
// $sandboxUrl = 'https://api.billit.be/v1/orders?include=OrderPDF';
$endpoint = $sandboxUrl;

// --------------------------------------------------------------------
// 3) cURL GET /v1/orders with headers: Accept, partyID, contextPartyID
// --------------------------------------------------------------------
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'apiKey: '  . $apiToken,
    'partyID: '         . $partyId,
    'contextPartyID: '  . $partyId,
]);
// If sandbox SSL issues, you can temporarily disable verify (NOT for production):
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

// --------------------------------------------------------------------
// 4) Error‐handling: if HTTP ≠ 200, show details & stop
// --------------------------------------------------------------------
if ($httpCode !== 200) {
    print '<div class="error">';
    print '<strong>' . $langs->trans('ErrorBillitHTTP') . "</strong><br>\n";
    print $langs->trans('Endpoint') . ': ' . htmlspecialchars($endpoint) . "<br>\n";
    print $langs->trans('HTTPStatusCode') . ': ' . intval($httpCode) . "<br>\n";
    if (!empty($curlErr)) {
        print $langs->trans('cURLError') . ': ' . htmlspecialchars($curlErr) . "<br>\n";
    }
    if (!empty($response)) {
        print $langs->trans('ResponseBody') . ":<pre>" . htmlspecialchars($response) . "</pre>";
    }
    print '</div>';
    llxFooter();
    exit;
}

// --------------------------------------------------------------------
// 5) Decode JSON into associative array
// --------------------------------------------------------------------
$decoded = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    print '<div class="error">' . $langs->trans('ErrorBillitInvalidJSON') . "</div>\n";
    llxFooter();
    exit;
}

// We expect: { "Items": [ { … }, { … }, … ] }
// If “Items” is missing or not an array, show “none found”
if (!isset($decoded['Items']) || !is_array($decoded['Items'])) {
    print '<div class="warning">' . $langs->trans('NoIncomingPeppolInvoicesFound') . "</div>\n";
    llxFooter();
    exit;
}

// All raw items returned by Billit
$allItems = $decoded['Items'];

// --------------------------------------------------------------------
// 6) Filter to only those with OrderDirection === "Cost" (supplier invoices)
// --------------------------------------------------------------------
$expenseItems = [];
foreach ($allItems as $inv) {
    if (isset($inv['OrderDirection']) && $inv['OrderDirection'] === 'Cost') {
        $expenseItems[] = $inv;
    }
}

// If no “Cost” items, show “none found” and exit
if (count($expenseItems) === 0) {
    print '<div class="warning">' . $langs->trans('NoIncomingPeppolInvoicesFound') . "</div>\n";
    llxFooter();
    exit;
}

// --------------------------------------------------------------------
// 7) Render a Dolibarr‐styled table for each “Cost” item
// --------------------------------------------------------------------
print '<table class="liste footable" widget="footable">';
print '<thead>';
print '<tr class="liste_titel">';
    print '<th>' . $langs->trans('InvoiceID') . '</th>';
    print '<th>' . $langs->trans('Date') . '</th>';
    print '<th>' . $langs->trans('Supplier') . '</th>';
    print '<th>' . $langs->trans('VATNumber') . '</th>';
    print '<th class="right">' . $langs->trans('Amount') . '</th>';
    print '<th>' . $langs->trans('Status') . '</th>';
    print '<th>' . $langs->trans('PreviewPDF') . '</th>';
    print '<th>' . $langs->trans('Import') . '</th>';
print '</tr>';
print '</thead>';
print '<tbody>';

// Loop through each supplier invoice
foreach ($expenseItems as $inv) {
    // — 7a) registrationID = OrderID (needed by the download proxy)
    $regId = isset($inv['OrderID']) ? (string)$inv['OrderID'] : '';

    // — 7b) InvoiceID (human‐readable) = OrderNumber
    $invId = isset($inv['OrderNumber']) ? $inv['OrderNumber'] : '';

    // — 7c) Date = first 10 chars of OrderDate
    $invDateTime = isset($inv['OrderDate']) ? $inv['OrderDate'] : '';
    $invDate     = substr($invDateTime, 0, 10);

    // — 7d) Supplier name = CounterParty.DisplayName
    $supplier = '';
    if (isset($inv['CounterParty']) && is_array($inv['CounterParty'])) {
        $supplier = isset($inv['CounterParty']['DisplayName'])
                  ? $inv['CounterParty']['DisplayName']
                  : '';
    }

    // — 7e) Supplier VAT number = CounterParty.VATNumber
    $supplierVAT = '';
    if (isset($inv['CounterParty']) && is_array($inv['CounterParty'])) {
        $supplierVAT = isset($inv['CounterParty']['VATNumber'])
                    ? $inv['CounterParty']['VATNumber']
                    : '';
    }

    // — 7f) Amount to pay (ToPay) + Currency
    $toPay    = isset($inv['ToPay']) ? (float)$inv['ToPay'] : 0.0;
    $currency = isset($inv['Currency']) ? $inv['Currency'] : '';
    $formattedToPay = number_format($toPay, 2, ',', ' ');

    // — 7g) Status = OrderStatus
    $status = isset($inv['OrderStatus']) ? $inv['OrderStatus'] : '';

    // — 7h) FileID for the PDF is now in inv['OrderPDF']['FileID'] (because we used include=OrderPDF)
   // — 1) Extract FileID from the JSON (now that you used include=OrderPDF)
	$fileId = '';
	if (isset($inv['OrderPDF']) && is_array($inv['OrderPDF'])) {
		$fileId = isset($inv['OrderPDF']['FileID']) ? $inv['OrderPDF']['FileID'] : '';
	}

	// — 2) Build the “Downloaden” URL (pointing to our new proxy):
	$pdfLink = '';
	if (!empty($fileId)) {
		$pdfLink = DOL_URL_ROOT
				. '/custom/peppolexport/download_document.php'
				. '?fileId=' . urlencode($fileId);
	}

	// — 3) Render the “PreviewPDF” cell:
	print '<td>';
	if (!empty($pdfLink)) {
		print '<a href="'.dol_htmlentities($pdfLink).'" target="_blank">'
			. $langs->trans('Download')
			. '</a>';
	} else {
		print $langs->trans('NoPDF');
	}
	print '</td>';

    // — 7j) Render the table row
    print '<tr class="oddeven">';
        // InvoiceID
        print '<td>' . dol_htmlentities($invId) . '</td>';

        // Date
        print '<td>' . dol_htmlentities($invDate) . '</td>';

        // Supplier
        print '<td>' . dol_htmlentities($supplier) . '</td>';

        // VATNumber
        print '<td>' . dol_htmlentities($supplierVAT) . '</td>';

        // Amount (ToPay + currency)
        print '<td class="right">' 
            . $formattedToPay . ' ' . dol_htmlentities($currency) 
            . '</td>';

        // Status
        print '<td>' . dol_htmlentities($status) . '</td>';

        // PreviewPDF: link to the proxy (download_document.php?regId=…&fileId=…)
        print '<td>';
        if (!empty($pdfLink)) {
            print '<a href="' . dol_htmlentities($pdfLink) . '" target="_blank">'
                . $langs->trans('Download')
                . '</a>';
        } else {
            print $langs->trans('NoPDF');
        }
        print '</td>';

        // Importeren: calls peppol_import.php?peppol_id=<OrderNumber>
        print '<td>';
        if (!empty($invId)) {
            print '<a class="butAction" href="peppol_import.php?peppol_id=' 
                  . urlencode($invId) . '">'
                  . $langs->trans('Import')
                  . '</a>';
        }
        print '</td>';
    print '</tr>';
}

print '</tbody>';
print '</table>';

llxFooter();
$db->close();
