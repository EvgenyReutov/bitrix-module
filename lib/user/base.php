<?php
/**
 * Created by PhpStorm.
 * User: carclay
 * Date: 07.01.19
 * Time: 18:04
 */

namespace Instrum\Main\User;


use CEvent;
use Instrum\Ajax\Controller;

class Base
{
    public $user;

    public function __construct()
    {
        $this->user = new \CUser;
    }

    public function findUser($email)
    {
        return \CUser::GetList($by = 'id', $order = 'asc', ['EMAIL' => $email])->Fetch();
    }

    public function replacePassword($id, $confirmcode)
    {
        $result = ['status' => false];

        if($user = \CUser::GetByID($id)->Fetch())
        {
            if($confirmcode == $user['CONFIRM_CODE'])
            {
                global $USER;
                $USER->Authorize($user["ID"]);
                LocalRedirect("/auth/change_password/");
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

    public function changePasswordAction()
    {
        $data = Controller::getData();
        $result = [
            'status' => false
        ];

        if($user = $this->findUser($data['email']))
        {
            global $USER;
            if($data['password'] !== $data['confirm-password'])
            {
                $result['error'] = 'Пароли должны совпадать';
            }
            else if(!$USER->IsAuthorized())
            {
                $result['error'] = 'Вы не авторизованы';
            }
            else if($data['email'] !== $USER->GetEmail())
            {
                $result['error'] = 'Введенная почта не соответствует почте текущего пользователя';
            }
            else
            {
                if(!defined("ADMIN_SECTION") || ADMIN_SECTION !== true)
                    $USER_LID = LANG;
                else
                    $USER_LID = false;

                (new \CUser)->Update($user["ID"], ['PASSWORD' => $data['password'], 'CONFIRM_PASSWORD' => $data['confirm-password']]);

                \CUser::SendUserInfo($user["ID"], $USER_LID, 'Вы запросили ваши регистрационные данные.', true, 'USER_PASS_REQUEST');
                $result['status'] = true;
                $result['dialog'] = [
                    'title' => 'Пароль изменен',
                    'description' => 'На адрес указанной электронной почты высланы новые регистрационные данные. Пожалуйста, проверьте почту.',
                    'buttons' => [
                        ['title' => 'Понятно', 'onclick' => 'window.location.href = "/";', 'classModifier' => 'btn-primary']
                    ]
                ];

            }
        }
        else
        {
            $result['error'] = 'Пользователь не найден';
        }

        Controller::response($result);
    }

    public function rememberAction()
    {
        $data = Controller::getData();
        $result = [
            'status' => false,
            'error' => 'Пользователь не найден'
        ];
        if($user = \CUser::GetList($by = 'ID', $order = 'ASC', ['EMAIL' => $data['email']])->Fetch())
        {
            if(!defined("ADMIN_SECTION") || ADMIN_SECTION !== true)
                $USER_LID = LANG;
            else
                $USER_LID = false;


            $ccode = randString(8);

            (new \CUser)->Update($user["ID"], ['CONFIRM_CODE' => $ccode]);

            $arFields = array(
                "USER_ID" => $user["ID"],
                "LOGIN" => $user["LOGIN"],
                "EMAIL" => $user["EMAIL"],
                "NAME" => $user["NAME"],
                "LAST_NAME" => $user["LAST_NAME"],
                "CONFIRM_CODE" => $ccode,
                "USER_IP" => $_SERVER["REMOTE_ADDR"],
                "USER_HOST" => @gethostbyaddr($_SERVER["REMOTE_ADDR"]),
            );

            $event = new CEvent;
            $event->Send("X_USER_PASS_REQUEST", SITE_ID, $arFields);

            $result = [
                'status' => true,
                'dialog' => [
                    'title' => 'Восстановление пароля',
                    'description' => 'На адрес указанной электронной почты выслана инструкция по восстановлению пароля. Пожалуйста, проверьте почту.',
                    'buttons' => [
                        ['title' => 'Понятно', 'onclick' => 'ModalHistory.release()', 'classModifier' => 'btn-primary']
                    ]
                ]
            ];
        }

        Controller::response($result);
    }

    public function authAction()
    {
        global $USER;
        $result = ['status' => false, 'error' => 'Неверный логин или пароль.'];

        if ($USER->IsAuthorized()) {
            $result = ['status' => true, 'x-data' => 'auth_before'];
        } else {
            $data = Controller::getData();
            if ($user = \CUser::GetList($by = 'id', $order = 'asc', ['=EMAIL' => $data['email']])->Fetch()) {
                if ($authData = $USER->Login($user['LOGIN'], $data['password'], 'Y')) {
                    if ($authData !== 1 && $authData['TYPE'] === 'ERROR') {
                        $result['error'] = $authData['MESSAGE'];
                        $result['x-error'] = $authData;
                    } else {
                        $result = ['status' => true];
                        $result['x-error'] = $authData;
                    }
                }
            }
        }
        Controller::response($result);
    }
}