<?php

namespace Instrum\Main\Tools;

use Bitrix\Main\Loader;
use Bitrix\Seo\Engine\Bitrix;
use Instrum\Ajax\Controller;

class Order{

    protected function smsErrorDescription($errorCode)
    {
        static $data = [
            100 => 'Команда выполнена успешно (или сообщение принято к отправке)',
            100 => 'Сообщение находится в нашей очереди',
            101 => 'Сообщение передается оператору',
            102 => 'Сообщение отправлено (в пути)',
            103 => 'Сообщение доставлено',
            104 => 'Сообщение не может быть доставлено: время жизни истекло',
            105 => 'Сообщение не может быть доставлено: удалено оператором',
            106 => 'Сообщение не может быть доставлено: сбой в телефоне',
            107 => 'Сообщение не может быть доставлено: неизвестная причина',
            108 => 'Сообщение не может быть доставлено: отклонено',
            130 => 'Сообщение не может быть доставлено: превышено количество сообщений на этот номер в день',
            131 => 'Сообщение не может быть доставлено: превышено количество одинаковых сообщений на этот номер в минуту',
            132 => 'Сообщение не может быть доставлено: превышено количество одинаковых сообщений на этот номер в день',
            200 => 'Неправильный api_id',
            201 => 'Не хватает средств на лицевом счету',
            202 => 'Неправильно указан получатель',
            203 => 'Нет текста сообщения',
            204 => 'Имя отправителя не согласовано с администрацией',
            205 => 'Сообщение слишком длинное',
            206 => 'Будет превышен или уже превышен дневной лимит на отправку сообщений',
            207 => 'На этот номер (или один из номеров) нельзя отправлять сообщения',
            208 => 'Параметр time указан неправильно',
            209 => 'Вы добавили этот номер (или один из номеров) в стоп-лист',
            210 => 'Используется GET, где необходимо использовать POST',
            211 => 'Метод не найден',
            212 => 'Текст сообщения необходимо передать в кодировке UTF-8',
            220 => 'Сервис временно недоступен, попробуйте чуть позже.',
            230 => 'Превышен общий лимит количества сообщений на этот номер в день.',
            231 => 'Превышен лимит одинаковых сообщений на этот номер в минуту.',
            232 => 'Превышен лимит одинаковых сообщений на этот номер в день.',
            300 => 'Неправильный token (возможно истек срок действия, либо ваш IP изменился)',
            301 => 'Неправильный пароль, либо пользователь не найден',
            302 => 'Пользователь авторизован, но аккаунт не подтвержден (пользователь не ввел код, присланный в регистрационной смс)'
        ];
        if(!empty($data[$errorCode])) {
            return $data[$errorCode];
        }
        return null;
    }


    /*
     *  $products = array(['ID' => ?, 'QUANTITY' => ?], ...);
     */
    public function add2basket($products, $reset = false)
    {
        $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
            \Bitrix\Sale\Fuser::getId(),
            \Bitrix\Main\Context::getCurrent()->getSite()
        );

        if($reset)
        {
            foreach($basket->getBasketItems() as $item)
            {
                $item->delete();
            }
        }

        foreach($products as $product)
        {
            $productId = $product['ID'];
            $quantity = $product['QUANTITY'];

            if ($item = $basket->getExistsItem('catalog', $productId))
            {
                $item->setField('QUANTITY', $item->getQuantity() + $quantity);
            }
            else
            {
                $item = $basket->createItem('catalog', $productId);
                $item->setFields(array(
                    'QUANTITY' => $quantity,
                    'CURRENCY' => \Bitrix\Currency\CurrencyManager::getBaseCurrency(),
                    'LID' => \Bitrix\Main\Context::getCurrent()->getSite(),
                    'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
                ));
            }
        }

        $basket->save();
    }

    public function repeatAction()
    {
        $result = ['status' => false];
        $data = Controller::getData();

        if($order_id = $data['order_id'])
        {
            Loader::includeModule('sale');

            $order = \Bitrix\Sale\Order::load($order_id);
            $products = [];
            foreach($order->getBasket()->getBasketItems() as $arItem)
            {
                $products[] = [
                    'ID' => $arItem->getField('PRODUCT_ID'),
                    'QUANTITY' => $arItem->getField('QUANTITY')
                ];
            }
            $this->add2basket($products, true);

            $result = [
                'status' => true,
                'answer' => file_get_contents(__DIR__."/../../page/popup-order-repeat.php")
            ];
        }
        Controller::response($result);
    }

    public function cancelAction()
    {
        $result = ['status' => false];
        $data = Controller::getData();

        if($order_id = $data['order_id'])
        {
            Loader::includeModule('sale');

            $order = \Bitrix\Sale\Order::load($order_id);
            \CSaleOrder::CancelOrder($order_id, "Y", "Потому что передумал");
            $order->setField("STATUS_ID", "CA");
            $order->save();

            $result['status'] = true;
        }
        else
        {
            $result['data_error'] = 'Заказ не найден';
        }

        Controller::response($result);
    }

    public function sendSmsAction()
    {
        $result = ['status' => false];
        $data = Controller::getData();

        if($order_id = $data['order_id'])
        {
            Loader::includeModule('sale');
            Loader::includeModule('bxmaker.smsnotice');

            $orderPhone = null;
            $order = \Bitrix\Sale\Order::load($order_id);
            $propertyCollection = $order->getPropertyCollection();
            foreach ($propertyCollection as $property) {
                $fieldValues = $property->getFieldValues();
                if($fieldValues['CODE'] == 'PHONE') {
                    $orderPhone = $fieldValues['VALUE'];
                    break;
                }
            }

            if(!empty($orderPhone)) {
                $oManager = \Bxmaker\SmsNotice\Manager::getInstance();
                $smsResult = $oManager->send(
                    preg_replace('/[\D]/', '', $orderPhone),
                    'Номер вашего заказа - '.$order_id
                );

                if($smsResult->isSuccess()) {
                    $result['status'] = true;
                    $result['phone'] = $orderPhone;
                } else {
                    $messages = $smsResult->getErrorMessages();
                    foreach ($messages as $key => $message) {
                        if(is_numeric($message)) {
                            $description = $this->smsErrorDescription($message);
                            if(!empty($description)) {
                                $messages[$key] = $description;
                            }
                        }
                    }

                    $result['data_error'] = implode(', ', $messages);
                }
            } else {
                $result['data_error'] = 'К заказу не привязан номер телефона';
            }
        } else {
            $result['data_error'] = 'Заказ не найден';
        }

        Controller::response($result);
    }

}