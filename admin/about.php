<?php
require '../../main.inc.php';
llxHeader('', 'PEPPOLexport');

print load_fiche_titre("Welkom bij de PEPPOLexport module");
print '<p>Gebruik de knop op facturen om een <strong>Peppol XML</strong>-bestand te genereren.</p>';
print '<p>Ga naar een bestaande factuur om het te testen.</p>';

llxFooter();
