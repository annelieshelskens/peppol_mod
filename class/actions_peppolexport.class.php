<?php
class ActionsPeppolexport
{
    function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;

        if (get_class($object) == 'Facture') {
            print '<a class="butAction" href="' . dol_buildpath('/peppolexport/export_peppol.php?id=' . $object->id, 1) . '" target="_blank">';
            print 'Verstuur via Peppol';
            print '</a>';
        }

        return 0;
    }
}
?>
