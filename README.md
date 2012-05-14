Importing from osCommerce/xt:Commerce

OXID eSales provides a module with allows you to easily import data from osCommerce or xt:Commerce to OXID eShop.

-------------
What is imported?
-------------

- Manufacturers
- Categories
- Products
- Product assignments to categories
- Product images, category images and manufacturer images
- Product reviews and ratings
- Language settings
- It tries to convert osCommerce options to OXID variants.

If import is made from xt:Commerce, the following data is additionally imported:
- search keywords for products
- further product images
- general scale prices
- crossselling products
- newsletter subscriptions
- tag cloud is generated

-------------
How to import
-------------

1) Copy oxCommerce/xt:Commerce database to eShop Server

For importing, the oxCommerce/xt:Commerce database and the eShop database have to be on the same server (The databases are accessed with the same mysql user).
- Create a copy of your oxCommerce/xt:Commerce database. For instance, you can use the export function of phpmyadmin.
- Insert the copy into a new database located on the same server as the eShop database. For example, you can use the import function of phpmyadmin.

2) Edit settings in _config.inc.php
In the importer, there is a file named _config.inc.php. In this file, several settings have to be made:

- $sOxidConfigDir
This is the path to your OXID eShop on the Server (not the URL!). You can find this path in the eShop admin:
- Log in to eShop Admin.
- Go to Service -> System Info. Search the setting _SERVER["DOCUMENT_ROOT"]. The value shown on the right is the path to your eShop.

- $blIsXtc (Sets if import is made from osCommerce or From xt:Commerce.)
- Set the value to true if import is made from xt:Commerce.
- Set the value to false if import is made from osCommerce.

- $sOcmDb (Name of the Database the osCommerce/xt:Commerce data is stored. The database has to be on the same server as the OXID database.)

- $sOscImageDir (The path to the directory the osCommerce/xt:Commerce pictures are stored.)

3) Copy all import files to server
Copy all files from the import script to the server the eShop runs on.

4) Run osc2oxid.php
Next, the import script has to be run. As the import may take some time, the script should be called
from the command line of your server with: php osc2oxid.php 
If you don't know how to access the command line of your server, please ask your web host for
assistance. After calling the script, the import is started. In the command line, the different steps of the import are shown. When the import is finished, the total time the import needed is shown.

5) Delete copied database
As the copied osCommerce/xt:Commerce database is not needed any more, you can delete it after successful import.