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

Currently if you have send an invoice with the button, you should get an output whereas the end it should show you that it has been send out.

<!--
![image](https://github.com/user-attachments/assets/292347bb-2422-41ad-a222-078d411f4c5a)
![image](https://github.com/user-attachments/assets/4bb80518-86c2-4f5e-83fa-8562c4179579)


-->

Other external modules are available on [Dolistore.com](https://www.dolistore.com).

## Translations

Translations can be completed manually by editing files in the module directories under `langs`.

<!--
This module contains also a sample configuration for Transifex, under the hidden directory [.tx](.tx), so it is possible to manage translation using this service.

For more information, see the [translator's documentation](https://wiki.dolibarr.org/index.php/Translator_documentation).

There is a [Transifex project](https://transifex.com/projects/p/dolibarr-module-template) for this module.
-->


## Installation

Prerequisites: You must have Dolibarr ERP & CRM software installed. You can download it from [Dolistore.org](https://www.dolibarr.org).
You can also get a ready-to-use instance in the cloud from https://saas.dolibarr.org


### From the ZIP file and GUI interface

If the module is a ready-to-deploy zip file, so with a name `module_xxx-version.zip` (e.g., when downloading it from a marketplace like [Dolistore](https://www.dolistore.com)),
go to menu `Home> Setup> Modules> Deploy external module` and upload the zip file.

<!--

Note: If this screen tells you that there is no "custom" directory, check that your setup is correct:

- In your Dolibarr installation directory, edit the `htdocs/conf/conf.php` file and check that following lines are not commented:

    ```php
    //$dolibarr_main_url_root_alt ...
    //$dolibarr_main_document_root_alt ...
    ```

- Uncomment them if necessary (delete the leading `//`) and assign the proper value according to your Dolibarr installation

    For example :

    - UNIX:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';
        ```

    - Windows:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = 'C:/My Web Sites/Dolibarr/htdocs/custom';
        ```
-->

<!--

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
