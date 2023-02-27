<?php

namespace Instrum\Main;
use Bitrix\Main;
use Bitrix\Main\EventManager;
use Bitrix\Sale\Order;

class Referrer{

    public static function setupEvents()
    {
        EventManager::getInstance()->RegisterEventHandler(
            "sale",
            "OnSaleOrderBeforeSaved",
            'local.main',
            '\Instrum\Main\Referrer',
            'OnOrderCreate'
        );

        EventManager::getInstance()->RegisterEventHandler(
            'main',
            'OnProlog',
            'local.main',
            '\Instrum\Main\Referrer',
            'AORSEvent'
        );

    }

    public static function OnOrderCreate(Main\Event $event)
    {

        /** @var Order $order */
        $order = $event->getParameter("ENTITY");
	
        $arProps = [];
        foreach ($order->getPropertyCollection() as $prop)
        {
            $arProps[$prop->getField('CODE')] = $prop->getValue();
        }

        if (!defined("ADMIN_SECTION") || ADMIN_SECTION !== true ) {
            if (!$arProps['UTM_FIRST'] && !$arProps['UTM_LAST']) {
                foreach ($order->getPropertyCollection() as $prop) {
                    switch ($prop->getField('CODE')) {
                        case 'UTM_FIRST':
                            $prop->setValue(self::getFirstReferrerData());
                            break;

                        case 'UTM_LAST':
                            $prop->setValue(self::getLastReferrerData());
                            break;
                    }
                }
            }
        }

    }

    protected function getUTM()
    {
        $result = false;
        if(!empty($_GET['utm_source']) || !empty($_GET['utm_medium']) || !empty($_GET['utm_campaign'])){
            $result = array(
                'Usource' => $_GET['utm_source'],
                'Umedium' => $_GET['utm_medium'],
                'Ucampaign' => $_GET['utm_campaign'],
            );

            if($_GET['utm_content'])
            {
                $result['Ucontent'] = $_GET['utm_content'];
            }

            if($_GET['utm_term'])
            {
                $result['Uterm'] = $_GET['utm_term'];
            }

        }

        return $result;
    }

    //url имена сайта (без http[s]://)
    protected function getHostAliases()
    {
        return [$_SERVER['HTTP_HOST']];
    }

    protected function isReferral()
    {
        return !preg_match(
            '/'.implode("|", $this->getHostAliases()).'/',
            $_SERVER['HTTP_REFERER']
        );
    }

    protected function checkReferer()
    {
        if($_COOKIE['AORS_FIRST_CLICK'])
        {
            return $_SERVER['HTTP_REFERER'] && $this->isReferral();
        }

        return true;
    }

    protected function writeReferer($key, $data)
    {
        $_SESSION[$key] = $data;
        setcookie(
            $key,
            json_encode($data), time() + 60 * 60 * 24 * 365,
            '/',
            '.mysite.ru'
        );
    }

    public static function AORSEvent($event = null)
    {
        (new Referrer())->AORS();
    }

    public function AORS()
    {
        $new_track = false;
        if(!$_SESSION['utm_track_id'])
        {
            $_SESSION['utm_track_id'] = randString(32);
            $new_track = true;
        }

        $utm = $this->getUTM();
        $checkReferer = $this->checkReferer();

        if($utm || $checkReferer)
        {
            $referer = $_SERVER['HTTP_REFERER'];
            if(!$utm && $referer && $this->isReferral())
            {
                $arUrl = parse_url($referer);
                $utm = [
                    'Usource' => $arUrl['host'],
                    'Umedium' => 'referral',
                    'Ucampaign' => ''
                ];
            }
            else if(!$utm)
            {
                $utm = [
                    'Usource' => 'type-in',
                    'Umedium' => 'direct',
                    'Ucampaign' => ''
                ];
            }

            //if($referer) $utm['HTTP_Referer'] = $referer;

            $utm['url'] = $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
            $utm['date'] = date('Y-m-d H:i:s');
            $utm['trackid'] = $_SESSION['utm_track_id'];
        }

        if($utm)
        {
            if(!$_COOKIE['AORS_FIRST_CLICK'])
                $this->writeReferer('AORS_FIRST_CLICK', $utm);

            $this->writeReferer('AORS_LAST_CLICK', $utm);

            $_SESSION['LAST_REFERER'] = $_SERVER['HTTP_REFERER'];
            $this->log($utm, $new_track);
        }
    }

    public static function getFirstReferrerData()
    {
        return self::getReferrerData('AORS_FIRST_CLICK');
    }

    public static function getLastReferrerData()
    {
        return self::getReferrerData('AORS_LAST_CLICK');
    }

    protected static function getReferrerData($type)
    {
        if(
            ($utm = $_SESSION[$type]) ||
            ($utm = json_decode($_COOKIE[$type], true))
        )
        {
            $values = [];
            foreach($utm as $key => $val)
            {
                if($val)
                    $values[] = $key.': '.$val;
            }
            return $values;
        }
        return false;
    }

    protected function log($data, $new_track = false)
    {
        $log_dir = $_SERVER['DOCUMENT_ROOT'].'/local/modules/local.main/log/';
        if(!is_dir($log_dir))
        {
            mkdir($log_dir, 0777, true);
        }
        $file = $log_dir.'referrer_'.date("d_m_Y").'.txt';
        file_put_contents($file, "======[REF START|".$_SESSION['utm_track_id'].($new_track ? '(new)' : '')."]======\n", FILE_APPEND | LOCK_EX);

        file_put_contents($file, "==SESSION==\n", FILE_APPEND | LOCK_EX);
        file_put_contents($file, json_encode($_SESSION, JSON_PRETTY_PRINT)."\n", FILE_APPEND | LOCK_EX);

        file_put_contents($file, "==COOKIE==\n", FILE_APPEND | LOCK_EX);
        file_put_contents($file, json_encode($_COOKIE, JSON_PRETTY_PRINT)."\n", FILE_APPEND | LOCK_EX);

        file_put_contents($file, "==UTM==\n", FILE_APPEND | LOCK_EX);
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)."\n", FILE_APPEND | LOCK_EX);

        file_put_contents($file, "======[REF END]======\n", FILE_APPEND | LOCK_EX);
    }
}
