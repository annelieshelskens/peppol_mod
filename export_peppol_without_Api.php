<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
include_once DOL_DOCUMENT_ROOT . '/core/lib/functions_be.lib.php';



$id = GETPOST('id', 'int');
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Factuur-ID ontbreekt']);
    exit;
}


// === Factuur ophalen ===
$invoice = new Facture($db);
$invoice->fetch($id);
$invoice->fetch_thirdparty();
$societe = $invoice->thirdparty;

// === PDF ophalen en base64 encoderen ===
$pdfPath = DOL_DATA_ROOT . '/facture/' . $invoice->ref . '/' . $invoice->ref . '.pdf';
if (!file_exists($pdfPath)) {
    require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
    require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
    $outputlangs = $langs;
    if (!is_object($outputlangs)) {
        $outputlangs = new Translate('', $conf);
    }
    $invoice->generateDocument('standard', $outputlangs);
}

if (!file_exists($pdfPath)) {
    die("❌ PDF-bestand werd niet gevonden of kon niet worden gegenereerd: $pdfPath");
}
$pdfBase64 = base64_encode(file_get_contents($pdfPath));

// === Gestructureerde mededeling genereren volgens Belgische standaard ===
$structuredMessage = dolBECalculateStructuredCommunication($invoice->ref, $invoice->type);



// === JSON opbouwen ===
$data = [
    'OrderType' => 'Invoice',
    'OrderDirection' => 'Income',
    'PaymentReference' => $structuredMessage,

    'OrderNumber' => $invoice->ref,
    'OrderDate' => dol_print_date($invoice->date, '%Y-%m-%d'),
    'ExpiryDate' => dol_print_date($invoice->date + 3600 * 24 * 30, '%Y-%m-%d'),
    'Customer' => [
        'Name' => $societe->name,
        'VATNumber' => $societe->tva_intra,
        'PartyType' => 'Customer',
        'Identifiers' => [
            [
                'IdentifierType' => 'CBE',
                'Identifier' => preg_replace('/[^0-9]/', '', $societe->idprof1)
            ]
        ],
        'Addresses' => [
            [
                'AddressType' => 'InvoiceAddress',
                'Name' => $societe->name,
                'Street' => $societe->address,
                'StreetNumber' => '',
                'City' => $societe->town,
                'Box' => '',
                'CountryCode' => $societe->country_code
            ]
        ]
    ],
    'OrderPDF' => [
        'FileName' => $invoice->ref . '.pdf',
        'FileContent' => $pdfBase64
    ],
    'OrderLines' => []
];

foreach ($invoice->lines as $line) {
    $description = $line->desc ?: $line->product_label ?: 'Geen omschrijving';
    $ogm = $invoice->total_ttc > 0 ? $invoice->ref_client : '';

    $data["OrderLines"][] = [
        "Quantity" => (string) $line->qty,
        "UnitPriceExcl" => (float) $line->subprice,
        "Description" => $description,
        "VATPercentage" => number_format($line->tva_tx, 4, '.', '')
    ];
}


// === API Instellingen ===
$apiToken = '';
$partyId = '';



// === Uitzenden naar Billit ===
$jsonBody = json_encode($data, JSON_PRETTY_PRINT);

// Debug output naar pagina
echo "<h2>Verzonden JSON naar Billit API:</h2><pre>" . htmlspecialchars($jsonBody) . "</pre>";

$ch = curl_init('https://api.sandbox.billit.be/v1/orders');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'apiKey: ' . $apiToken,
    'partyId: ' . $partyId
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 || $httpCode === 201) {
    echo "<p style='color:green;'>✅ Factuur succesvol verzonden naar Billit.</p>";
} else {
    echo "<p style='color:red;'>❌ Fout bij verzenden: HTTP $httpCode</p>";
    echo "<pre>$response</pre>";
}
?>

