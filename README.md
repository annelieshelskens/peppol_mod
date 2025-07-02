# PEPPOLEXPORT FOR [DOLIBARR ERP & CRM](https://www.dolibarr.org)

## Features

This module creates a button for the invoice page, the button allows us to send the invoice through the API off Billit software. The reason why i chose to use Billit as software is because it uses Peppol as an official AP.
The only change you need to make after downloading these files is the URL of billit itself "$ch = curl_init('https://api.sandbox.billit.be/v1/orders');" this is the link for the testenvironment of Billit. 

// === API Instellingen ===
$apiToken = '';
$partyId = '';

You should add the API token and Partyid from your billit environment. 
If you did these changes, it should send out an invoice.

Please make sure you have a billit account, and set up billit correctly (send peppol invoices automatically) this should take 1 day to send out to peppol itself automatically then.


-> Button
![image](https://github.com/user-attachments/assets/292347bb-2422-41ad-a222-078d411f4c5a)

![image](https://github.com/user-attachments/assets/4bb80518-86c2-4f5e-83fa-8562c4179579)

-> Currently if you have send an invoice with the button, you should get an output whereas the end it should show you that it has been send out.




## Translations

Translations can be completed manually by editing files in the module directories under `langs`.

<!--
This module contains also a sample configuration for Transifex, under the hidden directory [.tx](.tx), so it is possible to manage translation using this service.

For more information, see the [translator's documentation](https://wiki.dolibarr.org/index.php/Translator_documentation).

There is a [Transifex project](https://transifex.com/projects/p/dolibarr-module-template) for this module.
-->


## Installation


### From the ZIP file and GUI interface
if you want to use this module download the zip and change the name to:  `module_xxx-version.zip`,
go to menu `Home> Setup> Modules> Deploy external module` and upload the zip file.


### From a GIT repository

Clone the repository in `$dolibarr_main_document_root_alt/peppolexport`

```shell
cd ....../custom
git clone git@github.com:gitlogin/peppolexport.git peppolexport
```

-->

### Final steps

Using your browser:

  - Log into Dolibarr as a super-administrator
  - Go to "Setup"> "Modules"
  - You should now be able to find and enable the module



## Licenses

### Main code

GPLv3 or (at your option) any later version. See file COPYING for more information.

### Documentation

All texts and readme's are licensed under [GFDL](https://www.gnu.org/licenses/fdl-1.3.en.html).
