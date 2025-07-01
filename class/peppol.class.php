<?php
class PeppolExport
{
    private $db;
    private $apiToken = '1898e60f-4ed7-4ec6-9bc9-d1b5f73ad8bb'; // Vervang dit door je eigen token
    private $partyId = '27273177'; // Jouw party ID
    private $billitApiUrl = 'https://api.billit.be/v1/orders';

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function sendToBillit($invoiceId)
    {
        require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
        require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

        $invoice = new Facture($this->db);
        if ($invoice->fetch($invoiceId) <= 0) {
            return ['success' => false, 'error' => 'Factuur niet gevonden.'];
        }
        $invoice->fetch_thirdparty();
        $societe = $invoice->thirdparty;

        // JSON payload maken
        $json = [
            "OrderType" => "Invoice",
            "OrderDirection" => "Income",
            "OrderNumber" => $invoice->ref,
            "OrderDate" => date('Y-m-d', $invoice->date),
            "ExpiryDate" => date('Y-m-d', strtotime('+30 days', $invoice->date)),
            "Customer" => [
                "Name" => $societe->name,
                "VATNumber" => $societe->idprof1 ?: "BE0000000000",
                "PartyType" => "Customer",
                "Identifiers" => [],
                "Addresses" => [
                    [
                        "AddressType" => "InvoiceAddress",
                        "Name" => $societe->name,
                        "Street" => $societe->address,
                        "StreetNumber" => "",
                        "City" => $societe->town,
                        "Box" => "",
                        "CountryCode" => $societe->country_code
                    ]
                ]
            ],
            "OrderLines" => []
        ];

        foreach ($invoice->lines as $line) {
            $json["OrderLines"][] = [
                "Quantity" => (float) $line->qty,
                "UnitPriceExcl" => (float) $line->subprice,
                "Description" => $line->desc ?: 'Geen beschrijving',
                "VATPercentage" => 21.0
            ];
        }

        $jsonData = json_encode($json);

        // CURL-call uitvoeren
        $ch = curl_init($this->billitApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json',
            'partyId: ' . $this->partyId
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'response' => json_decode($response, true)];
        } else {
            return ['success' => false, 'error' => 'API error: ' . $response . ' | cURL error: ' . $error];
        }
    }
}
?>

