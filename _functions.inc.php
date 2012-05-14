<?php

/**
 * OS Commerce shop import script.
 *
 * This file includes helper functions used by OS Commerce importer.
 * Do not run this script directly.
 *
 * @copyright © OXID eSales AG 2009
 * @link http://www.oxid-esales.com/
 *
 */


/**
 * Show base path
 *
 * @return string
 */
function getShopBasePath()
{
    global $sOxidConfigDir;
    return $sOxidConfigDir . "/";
}

/**
 * Prints out the line
 *
 * @param string $sOut
 */
function printLine($sOut)
{
    echo $sOut . "\n";
    flush();
}

/**
 * Returns language suffix;
 *
 * @param int $iLang
 */
function getLangSuffix($iLang)
{
    $iLang--;
    if(!$iLang)
        return "";

    return "_".$iLang;
}

/**
 * Dumps var to file
 *
 * @param mixed $mVar var to be dumped
 */
function exportVar($mVar)
{
    ob_start();
    var_dump($mVar);
    $sDump = ob_get_contents();
    ob_end_clean();

    file_put_contents('out.txt', $sDump."\n\n", FILE_APPEND);
}

/**
 * OS Commerce to OXID import handler
 *
 */
class ImportHandler
{
    /**
     * Max language count
     *
     * @var int
     */
    protected $_iLangCount;

    /**
     * OS Commerce DB
     *
     * @var string
     */
    protected $_sOcmDb;

    /**
     * Shop id
     *
     * @var string
     */
    protected $_sShopId;

    /**
     * OS Commerce image dir
     *
     * @var string
     */
    protected $_sOscImageDir;

    /**
     * SQL snippet for OXINCL field
     *
     * @var string
     */
    protected $_sInclField = '';

    /**
     * SQL snippet for OXINCL field value
     *
     * @var string
     */
    protected $_sInclFieldVal = '';

    /**
     * Category image path
     *
     * @var string
     */
    protected $_sCategoryImagePath = '/';

    /**
     * Manufacturer image path
     *
     * @var string
     */
    protected $_sManufacturerImagePath = '/';

    /**
     * Constructs by setting shop id
     *
     * @param int $sShopId ShopId
     */
    public function __construct($sShopId)
    {
        global $iLangCount;
        global $sOcmDb;
        global $sOscImageDir;

        $this->_iLangCount = $iLangCount;
        $this->_sOcmDb = $sOcmDb;
        $this->_sShopId = $sShopId;
        $this->_sOscImageDir = $sOscImageDir;

        if (oxConfig::getInstance()->getEdition() == 'EE') {
            $this->_sInclField    = ', oxshopincl';
            $this->_sInclFieldVal = ', 1';
        }

        $sQ = "SELECT * FROM $sOcmDb.products";
        oxDb::getDb()->Execute($sQ);

        if (mysql_errno())
            die("FAILURE: Can't select from OSCommerce database '$sOcmDb'");

    }

    /**
     * Deletes all items
     *
     */
    public function cleanUpBeforeImport()
    {
        $sQ = "delete from oxcategories where oxshopid = '".$this->_sShopId."'";
        oxDb::getDb()->Execute($sQ);

        $sQ = "delete from oxarticles where oxshopid = '".$this->_sShopId."'";
        oxDb::getDb()->Execute($sQ);

        $sQ = "delete from oxartextends";
        oxDb::getDb()->Execute($sQ);
    }

    /**
     * Reads OSC languages and sets them to OXID config
     *
     */
    public function setLanguages()
    {
        $sOcmDb = $this->_sOcmDb;
        $sQ = "SELECT * FROM $sOcmDb.languages";
        $rs = oxDb::getDb(true)->Execute($sQ);
        $aLanguages = array();
        $aLangParams = array();
        $i = 0;
        while ($rs && !$rs->EOF) {
            $sName = $rs->fields["name"];
            $sCode = $rs->fields["code"];
            $sSort = $rs->fields["sort_order"];
            $aLanguages[$sCode] = $sName;
            $aConfLangs[$sCode] = array('active'=>1, 'sort' => $sSort, 'baseId'=>$i++);
            $rs->moveNext();
        }

        oxConfig::getInstance()->saveShopConfVar('aarr', 'aLanguages', serialize($aLanguages));
        oxConfig::getInstance()->saveShopConfVar('aarr', 'aLanguageParams', serialize($aConfLangs));
    }

    /**
     * Category importer
     */
    public function importCategories()
    {
        $iLangCount = $this->_iLangCount;
        $sOcmDb = $this->_sOcmDb;
        $sShopId = $this->_sShopId;

        //insert first language categories
        $sQ = "REPLACE INTO oxcategories (oxid, oxshopid, oxactive, oxparentid, oxsort, oxtitle, oxthumb {$this->_sInclField})
                          (SELECT c.categories_id, '$sShopId', 1, parent_id, sort_order, categories_name, categories_image {$this->_sInclFieldVal}
                                    FROM $sOcmDb.categories AS c, $sOcmDb.categories_description AS cd
                                    WHERE c.categories_id = cd.categories_id
                                          AND language_id = 1)";
        oxDb::getDb()->Execute($sQ);

        //replace the rest of the languages
        for($i = 2; $i <= $iLangCount; $i++) {
            $iLang = $i - 1;
            $sLangSuffix = "_" . $iLang;
            $sQ = "UPDATE oxcategories AS c, (SELECT cd.categories_id AS id, cd.categories_name AS t FROM $sOcmDb.categories_description AS cd WHERE cd.language_id = $i) AS src SET c.oxtitle$sLangSuffix = src.t, c.oxactive$sLangSuffix = 1
                                    WHERE src.id = c.oxid";
            oxDb::getDb()->Execute($sQ);
        }

        $sQ = "update oxcategories set oxparentid = 'oxrootid' where oxparentid = 0";
        oxDb::getDb()->Execute($sQ);

        $sQ = "update oxcategories set oxrootid = oxid where oxparentid = 'oxrootid'";
        oxDb::getDb()->Execute($sQ);

    }

    /**
     * Rebuilds category tree.
     *
     */
    public function rebuildCategoryTree()
    {
        $oCatTree = oxNew("oxcategorylist");
        $oCatTree->updateCategoryTree(false);

    }

    /**
     * Manufacturer importer
     */
    public function importManufacturers()
    {
        $sOcmDb = $this->_sOcmDb;
        $sShopId = $this->_sShopId;

        //copy same title to all OXID languages
        $aLangs = oxConfig::getInstance()->getConfigParam("aLanguages");
        $iLangCount = count($aLangs);
        $sTitleFields = "";
        $sTitleVals = "";
        for($i = 1; $i < $iLangCount; $i++) {
            $sTitleFields .= ", oxtitle_".$i;
            $sTitleVals .= ", manufacturers_name";
        }

        $sQ = "REPLACE INTO oxmanufacturers (oxid, oxshopid, oxactive, oxicon, oxtitle $sTitleFields {$this->_sInclField})
                            (SELECT manufacturers_id, '$sShopId', 1, manufacturers_image, manufacturers_name $sTitleVals {$this->_sInclFieldVal} FROM $sOcmDb.manufacturers)";

        oxDb::getDb()->Execute($sQ);
    }

    /**
     * Product importer
     */
    public function importProducts()
    {
        $iLangCount = $this->_iLangCount;
        $sOcmDb = $this->_sOcmDb;
        $sShopId = $this->_sShopId;

        //insert first language categories
        $sQ = "REPLACE INTO oxarticles (oxid, oxshopid, oxactive, oxartnum,      oxstock,            oxthumb,        oxpic1,        oxprice,          oxinsert,            oxweight,       oxtitle,      oxexturl, oxmanufacturerid {$this->_sInclField})
                          (SELECT p.products_id, '$sShopId', 1,   products_model, products_quantity, products_image, products_image, products_price, products_date_added, products_weight, products_name, products_url, manufacturers_id {$this->_sInclFieldVal}
                                    FROM $sOcmDb.products AS p, $sOcmDb.products_description AS pd
                                    WHERE p.products_id = pd.products_id
                                          AND language_id = 1)";
        oxDb::getDb()->Execute($sQ);

        $sQ = "REPLACE INTO oxartextends (oxid, oxlongdesc)
                            (SELECT products_id, products_description
                                FROM $sOcmDb.products_description WHERE language_id = 1) ";
        oxDb::getDb()->Execute($sQ);

        //update the rest of the languages
        for($i = 2; $i <= $iLangCount; $i++) {
            $iLang = $i - 1;
            $sLangSuffix = "_" . $iLang;
            $sQ = "UPDATE oxarticles AS p, (SELECT pd.products_id AS id, pd.products_name AS t FROM $sOcmDb.products_description AS pd WHERE pd.language_id = $i) AS src SET p.oxtitle$sLangSuffix = src.t
                                    WHERE src.id = p.oxid";
            oxDb::getDb()->Execute($sQ);

            //dealing with long descr
            $sQ = "UPDATE oxartextends AS p, (SELECT pd.products_id AS id, pd.products_description AS d FROM $sOcmDb.products_description AS pd WHERE pd.language_id = $i) AS src SET p.oxlongdesc$sLangSuffix = src.d
                                    WHERE src.id = p.oxid";
            oxDb::getDb()->Execute($sQ);
        }

        //rating import
        $sQ = "UPDATE oxarticles AS t1, (SELECT products_id, AVG(reviews_rating) AS rating, count(products_id) AS cnt FROM $sOcmDb.reviews GROUP BY products_id) AS src SET t1.oxrating = src.rating, t1.oxratingcnt = src.cnt WHERE t1.oxid = src.products_id";
        oxDb::getDb()->Execute($sQ);

        //delete existing category assignments
        $sQ = "DELETE FROM oxobject2category WHERE oxobjectid IN (SELECT products_id FROM $sOcmDb.products)";
        oxDb::getDb()->Execute($sQ);

    }

    /**
     * Imports produc to category relations
     *
     */
    public function importProduct2Categories()
    {
        $sOcmDb = $this->_sOcmDb;

        if ($this->_sInclField)
            $sInclFieldVal = ", ".MAX_64BIT_INTEGER;

        $sQ = "INSERT INTO oxobject2category (oxid, oxobjectid, oxcatnid {$this->_sInclField})
                          (SELECT md5(concat(t.products_id, t.categories_id, RAND())), t.products_id, t.categories_id $sInclFieldVal
                                    FROM $sOcmDb.products_to_categories AS t)";

        oxDb::getDb()->Execute($sQ);

    }

    /**
     * Imports product reviews
     *
     */
    public function importReviews()
    {
        $sOcmDb = $this->_sOcmDb;

        $sQ = "REPLACE INTO oxreviews (oxid, oxactive, oxobjectid, oxtype,    oxtext,       oxcreate,     oxlang,         oxrating)
                          (SELECT t1.reviews_id, 1,  products_id, 'oxarticle',reviews_text, date_added, languages_id - 1, reviews_rating
                                    FROM $sOcmDb.reviews AS t1, $sOcmDb.reviews_description AS t2 WHERE t1.reviews_id = t2.reviews_id)";

        oxDb::getDb()->Execute($sQ);
    }

    /**
     * Creates OXID variants from OS Commerce option information. Does not fully handle multiple dimension variants
     *
     */
    public function importVariants()
    {
        $iLangCount = $this->_iLangCount;
        $sOcmDb = $this->_sOcmDb;
        $sShopId = $this->_sShopId;

        //remove imported variants
        $sQ = "DELETE FROM oxarticles WHERE oxparentid <> '' AND oxparentid IN (SELECT products_id FROM $sOcmDb.products)";
        oxDb::getDb()->Execute($sQ);

        //proably it would be possible to handle it over single sql, but lets do it in the loop instead of joining 3 tables

        //first selecting option names to be used in oxvarname
        $aOptNames = array();
        $sQ = "SELECT products_options_id, language_id, products_options_name FROM $sOcmDb.products_options";
        $rs = oxDb::getDb(true)->Execute($sQ);
        while($rs && $rs->recordCount()>0 && !$rs->EOF) {
            $iLang = $rs->fields["language_id"];
            $iOptId = $rs->fields["products_options_id"];
            $aOptNames[$iOptId][$iLang] = $rs->fields["products_options_name"];
            $rs->MoveNext();
        }

        //now lets read all attribute values and put them as variants
        $sQ = "SELECT *
                 FROM $sOcmDb.products_attributes";

        $rs = oxDb::getDb(true)->Execute($sQ);
        while ($rs && $rs->recordCount()>0 && !$rs->EOF) {
            $iParentProd = $rs->fields["products_id"];
            $iOption = $rs->fields["options_id"];
            $iOptValId = $rs->fields["options_values_id"];

            //parent OXVARNAME values
            foreach($aOptNames[$iOption] as $iLang => $sName) {
                    $sLangSuffix = getLangSuffix($iLang);
                    $sQ1 = "UPDATE oxarticles SET oxvarname$sLangSuffix = '$sName', oxvarstock = 1, oxvarcount = oxvarcount + 1 where oxid = '$iParentProd'";
                    oxDb::getDb(true)->Execute($sQ1);
            }

            //create variant article
            $sProdId = oxUtilsObject::getInstance()->generateUID();
            $dPrice = oxDb::getDb(true)->getOne("SELECT oxprice FROM oxarticles WHERE oxid = '$iParentProd'");
            if ($rs->fields["price_prefix"] == "+")
                $dPrice += $rs->fields["options_values_price"];
            if ($rs->fields["price_prefix"] == "-")
                $dPrice -= $rs->fields["options_values_price"];

            $iStock = $this->_getOptionStock($rs);
            $dWeight = $this->_getOptionWeight($rs);

            $sQ2 = "INSERT INTO oxarticles (oxid,      oxshopid, oxparentid, oxactive, oxprice, oxstockflag, oxstock, oxweight {$this->_sInclField})
                                  VALUES ('$sProdId','$sShopId','$iParentProd', 1,     $dPrice,  1,          '$iStock', '$dWeight' {$this->_sInclFieldVal})";

            oxDb::getDb(true)->Execute($sQ2);

            //OXVARSELECT VALUE

            for($i = 1; $i <= $iLangCount; $i++) {
                $sLangSuffix = getLangSuffix($i);
                $sQ3 = "SELECT products_options_values_name FROM $sOcmDb.products_options_values WHERE products_options_values_id = '$iOptValId'";
                $sOptName = oxDb::getDb(true)->getOne($sQ3);
                $sQ4 = "UPDATE oxarticles SET oxvarselect$sLangSuffix = '$sOptName' WHERE oxid = '$sProdId'";
                oxDb::getDb(true)->Execute($sQ4);
            }


            $rs->moveNext();
        }

    }

    /**
     * Copies manufacturer images
     *
     */
    public function handleManufacturerImages()
    {
        $sOscImageDir = $this->_sOscImageDir;

        $sQ = "SELECT oxid, oxicon FROM oxmanufacturers";
        $rs = oxDb::getDb(true)->Execute($sQ);
        while ($rs && $rs->recordCount()>0 && !$rs->EOF) {
            $sImg = $rs->fields["oxicon"];
            //copy image
            $sSrcName = $sOscImageDir . $this->_sManufacturerImagePath . $sImg;
            if (file_exists($sSrcName) && !is_dir($sSrcName))
                copy($sSrcName, oxConfig::getInstance()->getAbsDynImageDir() . "/icon/". basename($sImg));

            $sImg = basename($sImg);
            $sQ1 = "UPDATE oxmanufacturers SET oxicon = '$sImg' WHERE oxid = '".$rs->fields["oxid"]."'";
            oxDb::getDb(true)->Execute($sQ1);

            $rs->moveNext();
        }

    }

    /**
     * Copy category images
     *
     */
    public function handleCategoryImages()
    {
        $sOscImageDir = $this->_sOscImageDir;
        $sOcmDb = $this->_sOcmDb;

        $sQ = "SELECT oxid, oxthumb FROM oxcategories WHERE oxid IN (SELECT categories_id FROM {$sOcmDb}.categories)";
        $rs = oxDb::getDb(true)->Execute($sQ);
        while ($rs && $rs->recordCount()>0 && !$rs->EOF) {
            $sImg = $rs->fields["oxthumb"];
            //copy image
            $sSrcName = $sOscImageDir . $this->_sCategoryImagePath . $sImg;
            if (file_exists($sSrcName) && !is_dir($sSrcName)) {
                copy($sSrcName, oxConfig::getInstance()->getAbsDynImageDir() . "/0/". basename($sImg));
            }

            $sImg = basename($sImg);
            $sQ1 = "UPDATE oxcategories SET oxthumb = '$sImg' WHERE oxid = '".$rs->fields["oxid"]."'";
            oxDb::getDb(true)->Execute($sQ1);

            $rs->moveNext();
        }
    }

    /**
     * Copy product images
     *
     */
    public function handleProductImages()
    {
        $sOscImageDir = $this->_sOscImageDir;
        $sOcmDb = $this->_sOcmDb;

        $sQ = "SELECT oxid, oxthumb, oxpic1 FROM oxarticles WHERE oxid in (SELECT products_id FROM $sOcmDb.products)";
        $rs = oxDb::getDb(true)->Execute($sQ);
        while ($rs && $rs->recordCount()>0 && !$rs->EOF) {
            $sImg = $rs->fields["oxthumb"];
            //copy image
            if ($sImg) {
                $sSrcName = $sOscImageDir . "/" . $sImg;
                if (file_exists($sSrcName) && !is_dir($sSrcName)) {
                    copy($sSrcName, oxConfig::getInstance()->getAbsDynImageDir() . "/0/". basename($sImg));
                    copy($sSrcName, oxConfig::getInstance()->getAbsDynImageDir() . "/1/". basename($sImg));
                }

                $sImg = basename($sImg);
                $sQ1 = "UPDATE oxarticles SET oxthumb = '$sImg', oxpic1 = '$sImg' WHERE oxid = '".$rs->fields["oxid"]."'";
                oxDb::getDb(true)->Execute($sQ1);
            }

            $rs->moveNext();
        }
    }

    /**
     * Reserved for extended info import
     *
     */
    public function importExtended()
    {

    }

    /**
     * Returns option stock value
     *
     * @param resource $rs
     * @return int
     */
    protected function _getOptionStock($rs)
    {
        return 1;
    }

    /**
     * Gets Variant weight
     *
     * @param resource $rs
     * @return int
     */
    protected function _getOptionWeight($rs)
    {
        return 0;
    }
}

class XtImportHandler extends ImportHandler
{

    /**
     * Max image count
     *
     * @var int
     */
    protected $_iMaxImages = 7;

    /**
     * Category image path
     *
     * @var string
     */
    protected $_sCategoryImagePath = '/categories/';

    /**
     * Manufacturer image path
     *
     * @var string
     */
    protected $_sManufacturerImagePath = '/';


    /**
     * Aditionally import meta keywords, search words, short description, EAN, images, scale prices, crossselling products
     *
     */
    public function importProducts()
    {
        $sOcmDb = $this->_sOcmDb;
        $iLangCount = $this->_iLangCount;
        $sShopId = $this->_sShopId;

        parent::importProducts();

        //import EAN
        $sQ = "UPDATE oxarticles, (SELECT products_id, products_ean FROM $sOcmDb.products) AS src SET oxean = src.products_ean WHERE  src.products_id = oxid";
        oxDb::getDb(true)->Execute($sQ);

        //Importing search keywords
        for($i = 1; $i <= $iLangCount; $i++) {
            $sLangSuffix = getLangSuffix($i);

            $sQ = "UPDATE oxarticles, (SELECT products_keywords, products_short_description, products_id FROM $sOcmDb.products_description WHERE language_id = $i) AS src SET oxsearchkeys$sLangSuffix = src.products_keywords, oxshortdesc$sLangSuffix = src.products_short_description WHERE  src.products_id = oxid";
            oxDb::getDb(true)->Execute($sQ);

            $sQ = "UPDATE oxartextends, (SELECT products_keywords, products_id FROM $sOcmDb.products_description WHERE language_id = $i) AS src SET oxtags$sLangSuffix = src.products_keywords WHERE  src.products_id = oxid";
            oxDb::getDb(true)->Execute($sQ);
        }

        //import additional images
        for($i = 0; $i < $this->_iMaxImages; $i++) {
            $sZoomImg = '';
            if ($i <=4)
                $sZoomImg = ", oxzoom$i = src.image_name ";
            $j = $i + 1;
            $sQ = "UPDATE oxarticles, (SELECT image_name, products_id FROM $sOcmDb.products_images WHERE image_nr = $i) AS src SET oxpic$j = src.image_name $sZoomImg WHERE  src.products_id = oxid";
            oxDb::getDb(true)->Execute($sQ);
        }

        //import scale prices
        //delete existing scale price assignments
        $sQ = "DELETE FROM oxprice2article WHERE oxartid IN (SELECT products_id FROM $sOcmDb.products)";
        oxDb::getDb()->Execute($sQ);

        $sQ = "SELECT * FROM $sOcmDb.products_graduated_prices";
        $aScalePrices = array();
        $rs = oxDb::getDb(true)->Execute($sQ);
        while($rs && $rs->recordCount()>0 && !$rs->EOF) {
            $iProduct = $rs->fields["products_id"];
            $iQuantity = $rs->fields["quantity"];
            $dPrice = $rs->fields["unitprice"];
            $aScalePrices[$iProduct][$iQuantity] = $dPrice;
            $rs->MoveNext();
        }

        foreach($aScalePrices as $iProduct => $aPrices) {
            ksort($aPrices);
            $iQFrom = 0;
            foreach ($aPrices as $iQuantity => $dPrice) {
                $iQTo = $iQuantity - 1;
                if ($iQFrom && $iQTo) {
                    $sQ = "INSERT INTO oxprice2article (oxid,                oxshopid,   oxartid, oxaddabs,   oxamount, oxamountto) VALUES
                                (md5(concat('$iProduct', $iQFrom, RAND())), '$sShopId','$iProduct', $dNewPrice, $iQFrom, $iQTo)";
                    oxDb::getDb(true)->Execute($sQ);
                }
                $dNewPrice = $dPrice;
                $iQFrom = $iQuantity;
            }

            $iQTo = 99999999;
            $sQ = "INSERT INTO oxprice2article (oxid,                oxshopid,   oxartid, oxaddabs,   oxamount, oxamountto) VALUES
                                (md5(concat('$iProduct', $iQFrom, RAND())), '$sShopId','$iProduct', $dNewPrice, $iQFrom, $iQTo)";
            oxDb::getDb(true)->Execute($sQ);
        }


        //import crossell
        $sQ = "REPLACE INTO oxobject2article (oxid, oxobjectid, oxarticlenid, oxsort)
                                        (SELECT ID, xsell_id, products_id, sort_order
                                           FROM $sOcmDb.products_xsell)";
        oxDb::getDb(true)->Execute($sQ);

    }

    /**
     * Additionally imports category description
     */
    public function importCategories()
    {
        $sOcmDb = $this->_sOcmDb;
        $iLangCount = $this->_iLangCount;
        $sShopId = $this->_sShopId;

        parent::importCategories();

        //Importing category description
        for($i = 1; $i <= $iLangCount; $i++) {
            $sLangSuffix = getLangSuffix($i);
            $sQ = "UPDATE oxcategories, (SELECT categories_id, categories_heading_title, categories_description FROM $sOcmDb.categories_description WHERE language_id = $i) AS src SET oxlongdesc$sLangSuffix = src.categories_description, oxdesc$sLangSuffix = src.categories_heading_title WHERE  src.categories_id = oxid";
            oxDb::getDb(true)->Execute($sQ);
        }



    }

    /**
     * Copy product images
     *
     */
    public function handleProductImages()
    {
        $sOcmDb = $this->_sOcmDb;
        $sOscImageDir = $this->_sOscImageDir;

        $aPics = array();
        for($i = 1; $i < $this->_iMaxImages; $i++)
            $aPics[] = "oxpic".$i;
        $sPics = implode(', ', $aPics);

        //take all imported products
        $sQ = "SELECT oxid, oxthumb, $sPics FROM oxarticles WHERE oxid in (SELECT products_id FROM $sOcmDb.products)";
        $rs = oxDb::getDb(true)->Execute($sQ);
        while ($rs && $rs->recordCount()>0 && !$rs->EOF) {
            $sImg = $rs->fields["oxthumb"];
            //copy images
            if ($sImg) {
                $sSrcName = $sOscImageDir . "/product_images/thumbnail_images/" . $sImg;
                if (file_exists($sSrcName) && !is_dir($sSrcName))
                    copy($sSrcName, oxConfig::getInstance()->getAbsDynImageDir() . "/0/". basename($sImg));

                $sImg = basename($sImg);
                $sQ1 = "UPDATE oxarticles SET oxthumb = '$sImg' WHERE oxid = '".$rs->fields["oxid"]."'";
                oxDb::getDb(true)->Execute($sQ1);
            }

            for($i = 1; $i < $this->_iMaxImages; $i++) {
                $sImg = $rs->fields["oxpic".$i];
                if ($sImg) {
                    //copy oxpic1,2,3,.. images
                    $sSrcName = $sOscImageDir . "/product_images/info_images/" . $sImg;
                    if (file_exists($sSrcName) && !is_dir($sSrcName))
                        copy($sSrcName, oxConfig::getInstance()->getAbsDynImageDir() . "/$i/". basename($sImg));
                    //copy oxzoom1,2,.. imagess
                    $sSrcName = $sOscImageDir . "/product_images/popup_images/" . $sImg;
                    if (file_exists($sSrcName) && !is_dir($sSrcName))
                        copy($sSrcName, oxConfig::getInstance()->getAbsDynImageDir() . "/z$i/". basename($sImg));

                    $sImg = basename($sImg);
                    $sZoomUpdate = '';
                    if ($i <= 4)
                        $sZoomUpdate = ", oxzoom$i = '$sImg' ";
                    $sQ1 = "UPDATE oxarticles SET oxpic$i = '$sImg' $sZoomUpdate WHERE oxid = '".$rs->fields["oxid"]."'";
                    oxDb::getDb(true)->Execute($sQ1);
                }
            }

            $rs->moveNext();
        }
    }

    /**
     * Imports newsletter information
     *
     */
    public function importExtended()
    {
        $sOcmDb = $this->_sOcmDb;
        $sQ = "REPLACE INTO oxnewssubscribed (oxid, oxfname,             oxlname,            oxemail,                oxsubscribed)
                                   (SELECT mail_id, customers_firstname, customers_lastname, customers_email_address, date_added
                                           FROM $sOcmDb.newsletter_recipients WHERE mail_id NOT IN (SELECT oxid FROM oxnewssubscribed)) ";
        oxDb::getDb(true)->Execute($sQ);
    }

    /**
     * Returns option stock value
     *
     * @param resource $rs
     * @return int
     */
    protected function _getOptionStock($rs)
    {
        return $rs->fields["attributes_stock"];
    }

    /**
     * Gets Variant weight
     *
     * @param resource $rs
     * @return int
     */
    protected function _getOptionWeight($rs)
    {
        return $rs->fields["options_values_weight"];
    }

}