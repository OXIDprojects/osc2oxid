<?php

/**
 * Configuration file for osc2oxid.php script
 * Edit this file before running the importer.
 *
 * @copyright © OXID eSales AG 2009
 * @link http://www.oxid-esales.com/
 *
 */

//the path of fully installed OXID eShop
$sOxidConfigDir = "/htdocs/oxid/";

//Do we import from OS commerce clone XTCommerce?
//In this case available extended information is imported
$blIsXtc = false;

//installed OS Commerce DB name. (assuming the db is on the same server as OXID db)
$sOcmDb = "oscommerce";

//picture import
//OSCommerce image dir:
$sOscImageDir = "/htdocs/oscommerce/images/";

//that's it!
//now run the import script.