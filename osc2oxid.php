<?php

/**
 * OS Commerce (or XT Commerce) shop import script.
 *
 * This script imports OS Commerce shop catalog to OXID eShop
 *
 * What's imported:
 * Manufacturers, categories, product info, products in categories, images, reviews.
 * It tries to convert OSCommerce options to OXID variants.
 * Script automatically sets OXID lanuage config array according to OSCommerce languages.
 * support@oxid-esales.com
 *
 * This script also supports data import from XTCommerce (extended OSCommerce clone)
 * In this case additionally product search keywords, short description, EAN, image array,
 * general scale prices, crosseling, category short/long description,
 * newsletter subscriber list are imported and tag cloud are generated.
 *
 * Set configuration params in _config.inc.php
 * and run this script from command line (recommended):
 * >php osc2oxid.php
 *
 * @copyright (c) OXID eSales AG 2009
 * @link http://www.oxid-esales.com/
 *
 */

$iStartTime = time();

//CONFIGURATION
require_once("_config.inc.php");
require_once("_functions.inc.php");

//IMPLEMENTATION
set_time_limit(0);

//language count
$iLangCount = 4;


//init OXID framework
@include_once(getShopBasePath() . "/_version_define.php");
require_once(getShopBasePath(). "/core/oxfunctions.php");
require_once(getShopBasePath(). "/core/adodblite/adodb.inc.php");

//default OXID shop id
$sShopId = oxConfig::getInstance()->getBaseShopId();

if ($blIsXtc)
    $oIHandler = new XtImportHandler($sShopId);
else
    $oIHandler = new ImportHandler($sShopId);

//connect to db
//$oIHandler->mysqlConnect();

//empty tables:
//$oIHandler->cleanUpBeforeImport();

printLine("<pre>");

//--- LANGUAGES ----------------------------------------------------------
printLine("SETTING LANGUAGES");
$oIHandler->setLanguages();
printLine("Done.\n");
//------------------------------------------------------------------------

//--- MANUFACTURERS ------------------------------------------------------
printLine("IMPORTING MANUFACTURERS");
$oIHandler->importManufacturers();
printLine("Done.\n");
//------------------------------------------------------------------------

//--- CATEGORIES ---------------------------------------------------------
printLine("IMPORTING CATEGORIES");
printLine("Get categories..");
$oIHandler->importCategories();
printLine("Rebuilding category tree..");
$oIHandler->rebuildCategoryTree();
printLine("Done.\n");
//------------------------------------------------------------------------

//--- PRODUCTS -----------------------------------------------------------
printLine("IMPORTING PRODUCTS");
printLine("Get Products..");
$oIHandler->importProducts();
printLine("Get Relations..");
$oIHandler->importProduct2Categories();
printLine("Get Reviews..");
$oIHandler->importReviews();
printLine("Handle variants(options)..");
$oIHandler->importVariants();
printLine("Extended info..");
$oIHandler->importExtended();
printLine("Done.\n");
//------------------------------------------------------------------------

//--- IMAGES -------------------------------------------------------------
printLine("COPYING IMAGES");
printLine("Handle manufacturer icons..");
$oIHandler->handleManufacturerImages();
printLine("Handle category icons..");
$oIHandler->handleCategoryImages();
printLine("Handle product images..");
$oIHandler->handleProductImages();
printLine("Done.\n");
//------------------------------------------------------------------------

printLine("IMPORT DONE!");

printLine("</pre>");