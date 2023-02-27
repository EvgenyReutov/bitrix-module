<?php
namespace Instrum\Main\Catalog;

class Discount
{
    private $visiblePresets;
    private $couponItems = false;

    public function __construct()
    {
        $this->prepareVisibleDiscountPreset();
    }

    private function prepareVisibleDiscountPreset()
    {
        $this->visiblePresets = [
            'Sale\Handlers\DiscountPreset\SimpleProduct',
            'Sale\Handlers\DiscountPreset\OrderPerDay'
        ];
    }
    private function getCouponItems()
    {
        if(!$this->couponItems)
        {
            $basket = \Bitrix\Sale\Basket::loadItemsForFUser(\Bitrix\Sale\Fuser::getId(), \Bitrix\Main\Context::getCurrent()->getSite());
            $order = \Bitrix\Sale\Order::create(\Bitrix\Main\Context::getCurrent()->getSite(), \Bitrix\Sale\Fuser::getId());
            $order->appendBasket($basket);
            $discounts = $order->getDiscount();
            $coupons = $discounts->getApplyResult()['ORDER'];

            $couponItems = [];
            foreach($coupons as $coupon)
            {
                if(!$coupon['COUPON_ID']) continue;
                foreach($coupon['RESULT']['BASKET'] as $itemId => $itemData)
                {
                    $couponItems[] = $itemId;
                }
            }

            $this->couponItems = $couponItems;
        }
        return $this->couponItems;
    }

    /**
     * @param $itemId
     * @param int $quantity
     * @return bool
     */
    public function checkPreset($itemId, $quantity = 1)
    {
        $arPrice = \CCatalogProduct::GetOptimalPrice($itemId, $quantity);
        if($discountId = $arPrice['DISCOUNT']['ID'])
        {
            $arPreset = \Bitrix\Sale\Internals\DiscountTable::getList([
                'select' => ['PRESET_ID'],
                'filter' => ['ID' => $discountId]
            ])->fetch();

            if(in_array($arPreset['PRESET_ID'], $this->visiblePresets))
            {
                return true;
            }
        }
        return false;
    }

    public function checkCoupon($itemId)
    {
        return in_array($itemId, $this->getCouponItems());
    }

}