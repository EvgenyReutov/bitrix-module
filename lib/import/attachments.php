<?php

namespace Instrum\Main\Import;

use Instrum\Main\Catalog\Product;
use SimpleXMLElement;

class Attachments{

    protected $url = 'https://'.$_SERVER["SERVER_NAME"].'/yandexmarket/1b78da37-0b26-45a6-a885-095183509075.xml';

    /** @var SimpleXMLElement */
    protected $xmlTree;

    // offers => [attachment_id => attachment_url]
    protected $attachments;

    const INSTRUCTION = 'instruktsiya';
    const CERTIFICATE = 'sertifikat';

    protected $old_descriptions = [
        self::INSTRUCTION => 'Инструкция',
        self::CERTIFICATE => 'Сертификат'
    ];

    protected $descriptions = [
        self::INSTRUCTION => 'Инструкция по эксплуатации',
        self::CERTIFICATE => 'Сертификат'
    ];

    protected $uploadPath = "/upload/attachments/catalog";

    public function print(...$msg)
    {
        $attrs = [];
        foreach($msg as $message)
        {
            if(is_scalar($message)) $attrs[] = $message;
            else $attrs[] = var_export($message, true);
        }
        fwrite(STDOUT, "[".__FILE__."] ".implode(" ", $attrs)."\n");
    }


    public function __construct(){}

    public function readXml()
    {
        $this->print("HasXml");
        foreach($this->xmlTree->shop->offers->children() as $offer)
        {

            if(
                ($vendor = $offer->xpath('vendorCode')) &&
                ($offerArticle = $vendor[0]->__toString())
            )
            {
                $attachments = [];

                foreach($offer->xpath("param") as $param)
                {
                    switch ($param["name"]->__toString())
                    {
                        case '_Ссылка на инструкцию':
                            $attachments[self::INSTRUCTION] = $param->__toString();
                            break;
                        case '_Ссылка на сертификат':
                            $attachments[self::CERTIFICATE] = $param->__toString();
                            break;

                    }
                }

                if($attachments)
                    $this->attachments[$offerArticle] = $attachments;
            }

        }
        unset($this->xmlTree);
    }


    public function downloadXml()
    {
        $this->print("Start downloading");

        if($result = $this->download($this->url))
        {
            $this->print("Data received, try parse result");
            $this->xmlTree = new SimpleXMLElement($result);
            return $this->xmlTree;
        }

        $this->print("Empty data");

        return false;
    }

    private function download($url, $minutes = 30, $show_info = false)
    {
        $cacheId = __FUNCTION__.md5(implode("", ["OfferAttachments",$url]));
        $result = [];

        if($show_info)
        {
            $this->print("[FROM URL]", $url);
        }

        $obCache = new \CPHPCache;
        if($minutes && $obCache->InitCache($minutes*60, $cacheId, "/"))
        {
            $result = $obCache->GetVars()['RESULT'];
        }
        else if(!$minutes || $obCache->StartDataCache())
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $result = curl_exec($ch);
            curl_exec($ch);

            if($show_info)
            {
                $this->print("[RETURN]", curl_getinfo($ch), "\n");
            }
            if($minutes)
                $obCache->EndDataCache(['RESULT' => $result]);
        }

        return $result;
    }

    public function downloadAttachments()
    {
        $product = new Product();
        foreach($this->attachments as $offerArticle => $arAttach)
        {

            //$this->print("--",$offerArticle);
            $arFilter = [
                "IBLOCK_ID" => $product->getIblockId(),
                "PROPERTY_CML2_ARTICLE" => $offerArticle
            ];
            if($element = \CIBlockElement::GetList([], $arFilter)->GetNextElement())
            {
                $arElement = $element->GetFields();
                $arElement['PROPERTIES'] = $element->GetProperties();

                $brand = $this->getBrandCode($arElement);
                $this->print("Element", $offerArticle, $brand);

                $fileId = [];
                foreach($arAttach as $attachName => $attachUrl)
                {
                    if($arFile = $this->getFileArrayFromUrl(
                        $this->uploadPath,
                        $this->getAttachmentName($attachName, $brand, $offerArticle),
                        $attachUrl
                    ))
                    {
                        $fileId[] = ['VALUE' => $arFile, "DESCRIPTION" => $this->descriptions[$attachName]];
                    }
                }

                $rsOldFiles = \CIBlockElement::GetProperty($arElement["IBLOCK_ID"], $arElement["ID"], [], ["CODE" => "ATTACHMENTS"]);
                $oldFiles = [];
                while($e = $rsOldFiles->GetNext())
                {
                    $oldFiles[] = ["VALUE" => $e["VALUE"], "DESCRIPTION" => "", "del" => "Y"];
                }
                if($oldFiles) \CIBlockElement::SetPropertyValuesEx($arElement['ID'], $arElement['IBLOCK_ID'], ['ATTACHMENTS' => $oldFiles]);


                if($fileId)
                {
                    \CIBlockElement::SetPropertyValuesEx(
                        $arElement['ID'],
                        $arElement['IBLOCK_ID'],
                        ['ATTACHMENTS' => $fileId]
                    );
                }
            }
        }
    }

    public function getPublicLink($relPath)
    {
        if(
            ($publicPath = $this->uploadPath."/".$relPath) &&
            file_exists($_SERVER["DOCUMENT_ROOT"].$publicPath)
        )
        {
            return $publicPath;
        }

        return false;
    }

    public function getFixedName($oldName)
    {
	global $USER;

        if($key = array_search($oldName, $this->old_descriptions))
        {
            return $this->descriptions[$key] ? $this->descriptions[$key] : $oldName;
        }

        return $oldName;
    }

    private function getAttachmentName($attachName, $brand, $article)
    {
        return implode("_", [$attachName, $brand, $article]).".pdf";
    }

    protected function writeFileFromUrl($dir, $name, $url)
    {
        if (!file_exists($_SERVER["DOCUMENT_ROOT"].$dir)) {
            mkdir($_SERVER["DOCUMENT_ROOT"].$dir, 0777, true);
        }

        $returnLen = file_put_contents(
            $_SERVER['DOCUMENT_ROOT'].$dir."/".$name,
            $this->download($url, false)
        );

        if(mime_content_type($_SERVER['DOCUMENT_ROOT'].$dir."/".$name) === "text/html")
        {
            $returnLen = false;
        }

        return $returnLen;
    }

    protected function getFileArrayFromUrl($dir, $name, $url)
    {

        $cacheFileIrl = $_SERVER['DOCUMENT_ROOT'].$dir."/".$name;
        $minSize = 1024*1024;
        $cached = false;


        if(file_exists($_SERVER['DOCUMENT_ROOT'].$dir."/".$name))
        {
            $cached = true;

            //часть документов с фида оказалась редиректами, проверка на то, что закешировался pdf файл
            if($minSize > filesize($cacheFileIrl) && mime_content_type($cacheFileIrl) == "text/html")
            {
                $this->print("[RELOAD", $cacheFileIrl, "\n");
                $cached = false;
            }
        }

        if(
            $cached || $this->writeFileFromUrl($dir, $name, $url)
        )
        {
            $this->print("[SAVE]", $cacheFileIrl);
            $arFile = \CFile::MakeFileArray($_SERVER['DOCUMENT_ROOT'].$dir."/".$name);
            return $arFile;
        }

        return false;
    }

    protected function getBrandCode($arElement)
    {
        if(
            ($arBrand = \CIBlockElement::GetByID($arElement['PROPERTIES']['BRAND_']['VALUE'])->GetNext()) &&
            ($arBrand['CODE'])
        )
        {
            return strtolower($arBrand['CODE']);
        }

        return 'common';
    }

    public static function run()
    {
        set_time_limit(0);
        $attach = new Attachments();
        if($xml = $attach->downloadXml())
        {
            $attach->readXml();
            $attach->downloadAttachments();
        }
    }
}
