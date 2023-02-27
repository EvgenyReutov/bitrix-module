<?php
namespace Instrum\Main\User\Register;

use Bitrix\Main\Loader;
use CEvent;
use Instrum\Ajax\Controller;
use Instrum\Main\User\Base;

class Register{

    public static function getFields()
    {
        return [
            (new RegisterField('fio', 'FIO', 'Ваше имя'))
                ->setPlaceholder("Представьтесь, пожалуйста")
                ->setRequired(true),

            (new RegisterField('email', 'EMAIL', 'Email'))
                ->setPlaceholder("example@mail.ru")
                ->setType('email')
                ->setRequired(true),

            (new RegisterField('phone', 'PERSONAL_PHONE', 'Телефон'))
                ->setPlaceholder("(000) 000-00-00")
                ->setRequired(true)
                ->setType('picker')
                ->appendData("input","input-special")
                ->appendData("pattern","^\([0-6,9]{1}\d{2}\) \d{3}\-\d{2}\-\d{2}$")
                ->appendData("title","Номер должен быть в формате (999) 999-99-99")
                ->appendData("mask","(Z00) 000-00-00")
                ->appendData('mask-options', '{ "placeholder": "(000) 000-00-00", "translation": { "Z": { "pattern": "[0-6,9]" } } }')
                ->appendData("value-separator"," "),

            (new RegisterField('password', 'PASSWORD', 'Пароль'))
                ->setContainerId('rf_password-field')
                ->setPlaceholder("••••••••")
                ->setRequired(true)
                ->settype('password')
                ->appendData('min-length', 8)
                ->appendData("min-length-title","Пароль не менее 8 символов"),

            (new RegisterField('confirm-password', 'CONFIRM_PASSWORD', 'Подтвердите пароль'))
                ->setPlaceholder("••••••••")
                ->setRequired(true)
                ->setType('confirm_password')
                ->appendData("custom-title","Пароли не совпадают")
                ->appendData('linked', 'rf_password-field'),

              (new RegisterField('is_company', 'IS_COMPANY', 'Я представляю юридическое лицо'))
                ->setType('checkbox')
                ->setChildren([
                    (new RegisterField('company_name', 'WORK_COMPANY', 'Наименование организации'))
                        ->setPlaceholder("ООО Стройка плюс")
                        ->setRequired(true),

                    (new RegisterField('company_inn', 'WORK_NOTES', 'ИНН'))
                        ->setPlaceholder("10 или 12 цифр")
                        ->setRequired(true)
                        //->appendData("pattern","^[\d]{10}$")
                        ->appendData("filter","^[0-9]+$")
                        //->appendData("title","ИНН должен содержать 10 цифр")
                        ->appendData("external_validator", "return ValidationLib.validateInn(value, error)"),
                    (new RegisterField('company_kpp', 'WORK_NOTES', 'КПП'))
                        ->setPlaceholder("9 цифр")
                        ->setRequired(false)
                        ->appendData("pattern","^[\d]{0,9}$")
                        ->appendData("filter","^[0-9]+$")
                        ->appendData("title","КПП может состоять только из 9 цифр")
                        ->appendData("external_validator", "return ValidationLib.validateKpp(value, error)"),
                ]),

            (new RegisterField('submit', 'submit', 'Зарегистрироваться'))
                ->setType('submit'),

            /*(new RegisterField('agree', 'agree', ''))
                ->setType('checkbox')
                ->setClassSuffix("form-checkbox-special")
                ->setRequired(true),*/
        ];
    }


    public function getMap($data, $fields = null)
    {
        $result = [];
        if(!$fields)
            $fields =  self::getFields();

        /** @var RegisterField $field */
        foreach($fields as $field)
        {
            if($value = $data[$field->getName()])
            {
                $result[$field->getTableName()] = $value;

                if($children = $field->getChildren())
                {
                    $result = array_merge($result, $this->getMap($data, $children));
                }
            }
        }

        return $result;
    }

    private function createUser($data)
    {
        Loader::includeModule('sale');
        Loader::includeModule('catalog');

        $result = [
            'status' => false,
            'error' => 'Неизвестная ошибка'
        ];
        $data['ACTIVE'] = 'N';
        $data['CONFIRM_CODE'] = randString(10);

        $user = new \CUser;
        $ID = $user->Add($data);

        if($ID)
        {
            $arFields = array(
                "NAME" => $data['LOGIN'],
                "USER_ID" => $ID,
                "PERSON_TYPE_ID" => $data['IS_COMPANY'] ? 2 : 1
            );

            $arFields['ORDER_PROP_14'] = $data['WORK_COMPANY'];
            $USER_PROPS_ID = \CSaleOrderUserProps::Add($arFields);

            $result = ['status' => true, 'profile' => $USER_PROPS_ID, 'user_id' => $ID];

            if(!defined("ADMIN_SECTION") || ADMIN_SECTION !== true)
                $USER_LID = LANG;
            else
                $USER_LID = false;
        }
        else
        {
            $result['error'] = $user->LAST_ERROR;
        }

        return $result;
    }

    private function updateUser($user)
    {
        $u = new \CUser();
        $u->Update($user['ID'], ['CONFIRM_CODE' => randString(10)]);

        return ['status' => true];
    }

    public function registerAction()
    {
        $result = ['status' => false];

        $postData = Controller::getData();
        $data = $this->getMap($postData);
        $data['IS_COMPANY'] = filter_var($data['IS_COMPANY'], FILTER_VALIDATE_BOOLEAN);

        if(($fio = explode(" ", $data['FIO'])))
        {
            $data['NAME'] = $fio[0];

            if($last_name = $fio[1])
                $data['LAST_NAME'] = $last_name;

            if($second_name = $fio[2])
                $data['SECOND_NAME'] = $second_name;

            unset($data['FIO']);
        }

        $data['LOGIN'] = $data['EMAIL'];
        $data['UF_PERSON_TYPE'] = $data['IS_COMPANY'] ? 2 : 1;

        $state = ['status' => false];
        if($user = (new Base())->findUser($data['EMAIL']))
        {
            if($user['ACTIVE'] == 'Y')
            {
                $state = ['error' => 'Пользователь с таким Email уже зарегистрирован'];
            }
            else
            {
                $state = $this->updateUser($user);
                $user_id = $user['ID'];
            }
        }
        else
        {
            $state = $this->createUser($data);
            $user_id = $state['user_id'];
        }

        if($state['status'])
        {
            $user = \CUser::GetByID($user_id)->GetNext();

            $arFields = array(
                "USER_ID" => $user["ID"],
                "LOGIN" => $user["LOGIN"],
                "EMAIL" => $user["EMAIL"],
                "NAME" => $user["NAME"],
                "LAST_NAME" => $user["LAST_NAME"],
                "CONFIRM_CODE" => $user["CONFIRM_CODE"],
                "USER_IP" => $_SERVER["REMOTE_ADDR"],
                "USER_HOST" => @gethostbyaddr($_SERVER["REMOTE_ADDR"]),
            );

            $event = new CEvent;
            $event->Send("X_NEW_USER_CONFIRM", SITE_ID, $arFields);

            $result['x-data'] = [
                'state' => $state,
                'user' => $user,
                'uid' => $user_id
            ];

            $result['profile'] = $state['profile'];
            $result['status'] = true;
            $result['dialog'] = [
                'title' => 'Регистрация прошла успешно!',
                'subtitle' => 'На Ваш email адрес отправлено письмо.',
                'description' => 'Для подтверждения регистрации перейдите по ссылке в письме.
Если Вы не подтвердите регистрацию в течение 8 часов, Ваш аккаунт будет удален.',
                'buttons' => [
                    ['title' => 'Понятно', 'onclick' => 'ModalHistory.release()', 'classModifier' => 'btn-primary']
                ]
            ];
        }
        else
        {
            $result['error'] = $state['error'];
        }

        echo json_encode($result);
    }

    public function activateUser($id, $confirm_code)
    {
        $result = ['status' => false];

        if($user = \CUser::GetByID($id)->Fetch())
        {
            if($user['CONFIRM_CODE'] == $confirm_code)
            {
                $u = new \CUser;
                $u->Update($user['ID'], ['ACTIVE' => 'Y']);
                $result['status'] = true;
            }
            else
            {
                $result['error'] = 'Неверный проверочный код';
            }
        }
        else
        {
            $result['error'] = 'Пользователь не найден';
        }

        return $result;
    }
}