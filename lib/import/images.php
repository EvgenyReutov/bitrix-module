<?php

namespace Instrum\Main\Import;

use \Bitrix\Main\Application;

/**
 * Class Images
 * @package Instrum\Main\Import
 */
class Images
{
    /**
     * @var array
     */
    private $modules = ['iblock'];
    /**
     * @var string
     */
    private $dir = "/upload/ftp_photo/";

    private $bImported = false;

    /**
     * Images constructor.
     */
    function __construct()
    {
        foreach ($this->modules as $module) {
            \Bitrix\Main\Loader::includeModule($module);
        }

        $this->app = Application::getConnection();
        $this->el = new \CIBlockElement;


        $this->checkTmpTable();
    }

    private function checkTmpTable()
    {
        // $this->app->query("DROP TABLE `image_import_tmp`");
        $this->app->query("
            CREATE TABLE IF NOT EXISTS `image_import_tmp`(
                `ID` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `ARTNUMBER` VARCHAR(15),
                `ELEMENT_ID` INT,
                `FILE` VARCHAR(255),
                `IS_MAIN` BOOLEAN DEFAULT 0,
                UNIQUE KEY `imp_img_file` (`FILE`, `ELEMENT_ID`, `ARTNUMBER`)
            )
            ENGINE=InnoDB DEFAULT CHARSET=utf8
        ");
    }

    /**
     * Запуск импорта.
     */
    function import()
    {
        $bImported = false;
        $files = scandir($_SERVER["DOCUMENT_ROOT"] . $this->dir, 1);
        foreach ($files as $i => $file) {
            if (!$this->_isPic($_SERVER["DOCUMENT_ROOT"] . $this->dir . $file)) {
                continue;
            }

            $this->obtainTmpTable($file);
        }

        do {
            $res = $this->setImages();
        } while ($res);

        if ($this->bImported) {
            \CIBlock::clearIBlockTagCache(CATALOG_IB);
        }
    }

    private function setImages()
    {
        if (!$elementID = $this->app->query("SELECT `ELEMENT_ID` FROM `image_import_tmp` GROUP BY `ELEMENT_ID`")->Fetch()["ELEMENT_ID"]) {
            return false;
        }

        // получаем все картинки к этому эелементу
        $rsImg = $this->app->query("SELECT * FROM `image_import_tmp` WHERE `ELEMENT_ID` = '" . $elementID . "' ORDER BY `FILE` ASC");

        $mainImg = false;
        $arGallery = $arFiles = [];
        $toClean = false;
        while ($result = $rsImg->Fetch()) {
            if ($result['TO_CLEAN'])
                $toClean = true;
            $arFile = \CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"] . $this->dir . $result["FILE"]);
            if ($result["IS_MAIN"]) {
                $mainImg = $arFile;
            } else {
                $arGallery[] = ["VALUE" => $arFile, "DESCRIPTION" => "", 'POS' => $this->GetPosition($result["FILE"])];
            }

            $arFiles[] = $result["FILE"];
        }

        if ($mainImg) {
            $this->el->Update($elementID, [
                'PREVIEW_PICTURE' => $mainImg,
                'DETAIL_PICTURE' => $mainImg,
            ], false, false, true, false);
        }

        if (!empty($arGallery)) {
            // обнуляем множественное свойство галереи и заливаем новые картинки
            if ($toClean) {
                $this->resetMorePhotoProperty($elementID);

                \CIBlockElement::SetPropertyValueCode($elementID, "MORE_PHOTO", $arGallery);

            } else {
                $iterator = \CIBlockElement::GetPropertyValues(20, array('ID' => $elementID), true, ['ID' => 10158]);
                while ($row = $iterator->Fetch()) {
                    foreach ($arGallery as $arImage) {

                        \CIBlockElement::SetPropertyValueCode($elementID, "MORE_PHOTO", [(int)$row['PROPERTY_VALUE_ID'][10158][$arImage['POS']] => $arImage]);
                    }
                }
            }
        }

        if ($mainImg || !empty($arGallery)) {
            $this->bImported = true;
        }

        foreach ($arFiles as $file) {
            $this->removePicture($file);
        }

        $this->app->query("DELETE FROM `image_import_tmp` WHERE `ELEMENT_ID` = '" . $elementID . "'");

        return $elementID;
    }

    private function resetMorePhotoProperty($elementID)
    {
        $db_props = \CIBlockElement::GetProperty(CATALOG_IB, $elementID, "sort", "asc", array("CODE" => "MORE_PHOTO"));
        while ($ar_props = $db_props->Fetch()) {
            if ($ar_props["VALUE"]) {
                $ar_val = $ar_props["VALUE"];
                $ar_val_id = $ar_props["PROPERTY_VALUE_ID"];

                $arr[$ar_props['PROPERTY_VALUE_ID']] = array("VALUE" => array("del" => "Y"));
                \CIBlockElement::SetPropertyValueCode($elementID, "MORE_PHOTO", $arr);
            }
        }
    }

    private function obtainTmpTable($file)
    {
        $article = $this->_getArticle($file);
        if (!$article) {
            return false;
        }

        $elementId = $this->_getIdByArticle($article);
        if (!$elementId) {
            return false;
        }

        // определим главная или в галерею
        $isMain = $this->checkIsMain($file);
        $toClean = $this->checkToClean($file);
        $this->app->query("
            INSERT IGNORE INTO `image_import_tmp`
            SET
                `ARTNUMBER` = '" . $article . "',
                `ELEMENT_ID` = '" . $elementId . "',
                `FILE` = '" . $file . "',
                `IS_MAIN` = '" . $isMain . "',
                `TO_CLEAN` = '" . $toClean . "'
        ");
    }

    private function checkIsMain($file)
    {
        $fileName = array_diff(explode(".", $file), [""]);
        array_pop($fileName);
        $fileName = implode(".", $fileName);

        $number = array_diff(explode("_", $fileName), [""]);
        return count($number) == 1;
    }

    private function GetPosition($file)
    {
        $fileName = array_diff(explode(".", $file), [""]);
        array_pop($fileName);
        $fileName = implode(".", $fileName);

        $number = array_diff(explode("_", $fileName), [""]);
        return (int)$number[1] - 1;
    }

    private function checkToClean($file)
    {
        $fileName = array_diff(explode(".", $file), [""]);
        array_pop($fileName);
        $fileName = implode(".", $fileName);

        $number = array_diff(explode("_", $fileName), [""]);
        if (in_array('x', $number))
            return 1;
        return 0;
    }

    /**
     * Импорт фото в товар.
     * @param $file
     * @return bool
     */
    function importPicture($file)
    {

        $article = $this->_getArticle($file);
        if (!$article) {
            return false;
        }

        $elementId = $this->_getIdByArticle($article);

        if (!$elementId) {
            return false;
        }


        $this->_savePhoto($elementId, $file);
        return true;
    }

    /**
     * Удалить фото.
     * @param $file
     */
    function removePicture($file)
    {
        $this->_deleteFile($file);
    }

    /**
     * @param $elementId
     * @param $file
     */
    private function _savePhoto($elementId, $file)
    {
        // определяем, является ли файл для детальной или галереи
        $hasPhoto = $this->_hasDetailPhoto($elementId);
        $arFile = \CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"] . $this->dir . $file);
        if ($hasPhoto) {
            \CIBlockElement::SetPropertyValueCode($elementId, "MORE_PHOTO", $arFile);
        } else {
            $this->el->Update($elementId, [
                'PREVIEW_PICTURE' => $arFile,
                'DETAIL_PICTURE' => $arFile,
            ], false, false, true, false);
        }
    }

    private function _hasDetailPhoto($elementId)
    {
        $product = \Bitrix\Iblock\ElementTable::getRow([
            'select' => ['DETAIL_PICTURE'],
            'filter' => ['ID' => $elementId]
        ]);
        if ($product) {
            return $product["DETAIL_PICTURE"] > 0;
        }
        return false;
    }

    /**
     * @param $file
     * @return bool
     */
    private function _deleteFile($file)
    {
        return unlink($_SERVER["DOCUMENT_ROOT"] . $this->dir . $file);
    }

    /**
     * @param $article
     * @return bool
     */
    private function _getIdByArticle($article)
    {
        $rs = \CIBlockElement::GetList([], ["IBLOCK_ID" => CATALOG_IB, "=PROPERTY_CML2_ARTICLE" => $article], false, false, ["ID"]);
        if ($result = $rs->Fetch()) {
            return $result["ID"];
        }
        return false;
    }

    /**
     * @param $filename
     * @return int
     */
    private function _getArticle($filename)
    {
        return intval($filename, 10);
    }

    /**
     * @param $filename
     * @return bool
     */
    private function _isPic($filename)
    {
        return $this->_array_strpos(strtolower($filename), array(".png", ".jpg", ".jpeg"));
    }

    /**
     * @param $haystack
     * @param $needles
     * @return bool
     */
    private function _array_strpos($haystack, $needles)
    {
        foreach ($needles as $needle)
            if (strpos($haystack, $needle) !== false) return true;
        return false;
    }
}