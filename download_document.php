<?php
/**
 * download_document.php
 *
 * This proxy fetches the PDF from Billit’s “/v1/files/{FileID}/download” endpoint
 * (with the required headers) and streams it back to the user’s browser.
 *
 * Usage:
 *   download_document.php?fileId=<FileID>
 *
 * Place this file in your module, for example:
 *   htdocs/custom/peppolexport/download_document.php
 */

require '../../main.inc.php';

// 1) Read “fileId” from GET
$fileId = trim(GETPOST('fileId', 'alpha'));
if (empty($fileId)) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Missing fileId';
    exit;
}

// 2) Read Billit “partyID” from Dolibarr global settings
$partyId = '';
if (empty($partyId)) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Billit partyID not configured.';
    exit;
}

// 3) Build the Billit “/v1/files/{FileID}/download” URL
//    Sandbox:
$endpoint = 'https://api.sandbox.billit.be/v1/files/' . urlencode($fileId) . '/download';
//    Production (when you switch live), uncomment below and comment out sandbox:
// $endpoint = 'https://api.billit.be/v1/files/' . urlencode($fileId) . '/download';

// 4) Initialize cURL
$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// 5) Add the required headers (Accept + partyID + contextPartyID)
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/pdf',
    'partyID: '         . $partyId,
    'contextPartyID: '  . $partyId,
]);

// 6) If you have SSL certificate issues in sandbox only (NOT for production), disable verify:
//    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$result   = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

// 7) If Billit did not return HTTP 200, show a plain‐text error
if ($httpCode !== 200) {
    header('Content-Type: text/plain');
    header("HTTP/1.1 $httpCode");
    echo "Error fetching PDF from Billit (HTTP $httpCode)\n";
    if (!empty($curlErr)) {
        echo "cURL error: $curlErr\n";
    }
    if (!empty($result)) {
        // Billit often returns JSON or HTML error text here
        echo "\nBillit response:\n\n" . $result;
    }
    exit;
}

// 8) Otherwise, forward the PDF to the browser
header('Content-Type: application/pdf');
// If you want to force a “Save as…” dialog instead of inline display, comment out the line above and uncomment below:
// header('Content-Disposition: attachment; filename="billit_invoice_' . $fileId . '.pdf"');

echo $result;
exit;
