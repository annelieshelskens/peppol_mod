<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

$id = GETPOST('id', 'int');
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Factuur-ID ontbreekt']);
    exit;
}

$invoice = new Facture($db);
$invoice->fetch($id);
$invoice->fetch_thirdparty();
$societe = $invoice->thirdparty;

// Genereer JSON volgens Billit formaat
$payload = [
    "OrderType" => "Invoice",
    "OrderDirection" => "Income",
    "OrderNumber" => $invoice->ref,
    "OrderDate" => dol_print_date($invoice->date, '%Y-%m-%d'),
    "ExpiryDate" => dol_print_date($invoice->date + (30 * 86400), '%Y-%m-%d'),
    "Customer" => [
        "Name" => $societe->name,
        "VATNumber" => $societe->tva_intra ?: 'BE0000000000',
        "PartyType" => "Customer",
        "Identifiers" => [[
            "IdentifierType" => "CBE",
            "Identifier" => preg_replace('/[^0-9]/', '', $societe->idprof1 ?: '0000000000')
        ]],
        "Addresses" => [[
            "AddressType" => "InvoiceAddress",
            "Name" => $societe->name,
            "Street" => $societe->address,
            "StreetNumber" => "",
            "City" => $societe->town,
            "Box" => "",
            "CountryCode" => strtoupper($societe->country_code ?: 'BE')
        ], [
            "AddressType" => "DeliveryAddress",
            "Name" => $societe->name,
            "Street" => $societe->address,
            "StreetNumber" => "",
            "City" => $societe->town,
            "Box" => "",
            "CountryCode" => strtoupper($societe->country_code ?: 'BE')
        ]]
    ],
    "OrderLines" => []
];

foreach ($invoice->lines as $line) {
    $payload["OrderLines"][] = [
        "Quantity" => (float) $line->qty,
        "UnitPriceExcl" => (float) $line->subprice,
        "Description" => $line->desc ?: 'Onbekend',
        "VATPercentage" => (float) $line->tva_tx
    ];
}

$json = json_encode($payload);

$apiToken = '1898e60f-4ed7-4ec6-9bc9-d1b5f73ad8bb';
$partyId = '27273177';

$ch = curl_init('https://api.billit.be/v1/orders');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
curl_setopt($ch, CURLOPT_HTTPSHEADER, [
    'Authorization: Bearer ' . $apiToken,
    'partyId: ' . $partyId,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 || $httpCode === 201) {
    echo "Factuur succesvol verzonden naar Billit.";
} else {
    echo "Fout bij verzenden: HTTP $httpCode\n";
    echo $response;
}
?>

