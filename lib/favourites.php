<?php


namespace Instrum\Main;


use Bitrix\Main\Application;
use Bitrix\Main\Web\Cookie;
use CUser;

class Favourites
{
    const FAV_COOKIE_NAME = 'shop_favorites';
    const FAV_COOKIE_DURATION = 30 * 86400;
    const FAV_USER_FIELD = 'UF_FAVOURITES';

    /** @var Application */
    protected $application;
    /** @var CUser */
    protected $user;

    /**
     * Favourites constructor.
     * @param Application $application
     * @param CUser $user
     */
    public function __construct($application, $user)
    {
        $this->application = $application;
        $this->user = $user;
    }

    /**
     * @param int $productId
     * @param string $action
     * @return int
     */
    public function update($productId, $action)
    {
        if($this->user->IsAuthorized()) {
            return $this->updateForAuthorized($productId, $action);
        }
        return $this->updateForUnauthorized($productId, $action);
    }

    /**
     * @return array
     */
    protected function getCookieAvailableList()
    {
        $context = $this->application->getContext();
        $request = $context->getRequest();

        $availableList = $request->getCookie(self::FAV_COOKIE_NAME);
        $availableList = empty($availableList) ? [] : explode(',', $availableList);
        return $availableList;
    }

    /**
     * @param $availableList
     */
    protected function setCookieAvailableList($availableList)
    {
        $context = $this->application->getContext();

        $domain = '.' . join('.', array_slice(explode('.', $context->getServer()->getHttpHost()), -2));

        $cookie = new Cookie(self::FAV_COOKIE_NAME, join(',', $availableList), time() + self::FAV_COOKIE_DURATION);
        $cookie->setSpread(Cookie::SPREAD_DOMAIN);
        $cookie->setDomain($domain);
        $cookie->setPath('/');
        $cookie->setSecure(false);
        $cookie->setHttpOnly(false);

        $response = $context->getResponse();
        $response->addCookie($cookie);
        $response->flush('');
    }

    /**
     * @return array
     */
    protected function getUserAvailableList()
    {
        $userId = $this->user->getID();
        $rs = CUser::GetByID($userId);
        $availableList = [];
        if($rs) {
            $user = $rs->Fetch();
            $availableList = empty($user[self::FAV_USER_FIELD]) ? [] : explode(',', $user[self::FAV_USER_FIELD]);
        }
        return $availableList;
    }

    /**
     * @param array $availableList
     */
    protected function setUserAvailableList($availableList)
    {
        $userId = $this->user->getID();
        $this->user->Update($userId, [
            self::FAV_USER_FIELD => join(',', $availableList)
        ]);
    }

    /**
     * @param array $list
     * @param int $productId
     * @param string $action
     * @return array
     */
    protected function updateList($list, $productId, $action)
    {
        if($action == 'add') {
            $list[] = $productId;
            $list = array_unique($list);
        } else {
            $list = array_filter($list, function ($value) use ($productId) {
                return $value != $productId;
            });
        }
        return $list;
    }

    /**
     * @param int $productId
     * @param string $action
     * @return int
     */
    protected function updateForUnauthorized($productId, $action)
    {
        $availableList = $this->getCookieAvailableList();
        $availableList = $this->updateList($availableList, $productId, $action);
        $this->setCookieAvailableList($availableList);
        return count($availableList);
    }

    /**
     * @param int $productId
     * @param string $action
     * @return int
     */
    protected function updateForAuthorized($productId, $action)
    {
        $availableList = $this->getUserAvailableList();
        $availableList = $this->updateList($availableList, $productId, $action);
        $this->setUserAvailableList($availableList);
        return count($availableList);
    }

    /**
     * @return array
     */
    public function get()
    {
        $cookieList = $this->getCookieAvailableList();
        $userList = $this->user->IsAuthorized() ? $this->getUserAvailableList() : [];
        return array_unique(array_merge($cookieList, $userList));
    }

    /**
     *
     */
    public function merge()
    {
        if($this->user->IsAuthorized()) {
            $this->setUserAvailableList($this->get());
        }
    }
}