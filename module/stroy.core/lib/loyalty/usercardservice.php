<?php

namespace Stroy\Core\Loyalty;

use Bitrix\Main\Application;
use Stroy\Core\Location\CurrentLocation;
use Stroy\Core\User\Manager as UserManager;

class UserCardService
{
    /**
     *
     * Текущая стоимость всех товаров корзины, учитывая возможность оплачивать товары с низкой ценой у карты
     *
     * @param  boolean  $includeLowPrice  - учитывать товары c низкой ценой
     * @param  null|integer  $orderId  - Номер заказа, null - текущая корзина
     */
    public static function getAllBasketSummConsideringLowPrice($includeLowPrice, $orderId = null,
        $availableProductsIds = []) {
        /** @var float $basketSumm Стоимость всех товаров, за исключением товаров с низкой ценой */

        /** @var array $productIds ID товаров в корзине */
        $productIds = [];
        /** @var array $basketItems Товары в корзине */
        $prepareBasketItems = [];
        if (is_null($orderId)) {
            $basket = \Bitrix\Sale\Basket::loadItemsForFUser(
                \Bitrix\Sale\Fuser::getId(),
                \Bitrix\Main\Context::getCurrent()->getSite()
            );
        } else {
            $basket = \Bitrix\Sale\Order::load($orderId)->getBasket();
        }

        $basketItems = $basket->getBasketItems();

        foreach ($basketItems as $basketItem) {
            if (!empty($availableProductsIds) && !in_array((int)$basketItem->getProductId(), $availableProductsIds)) {
                continue;
            }

            if (is_null($orderId)) {
                //если смотрим текущую корзину, то нужно проверить товары на доступность к покупке
                if (!$basketItem->canBuy()) {
                    continue;
                }
                if ($basketItem->isDelay() != false) {
                    continue;
                }
            }
            $productIds[] = $basketItem->getProductId();
            $prepareBasketItems[] = [
                'PRODUCT_ID' => $basketItem->getProductId(),
                'SUM_VALUE'  => $basketItem->getFinalPrice(),
            ];
        }

        /** @var array $lowPriceProducts ID товаров с низкой ценой */
        $lowPriceProducts = [];
        if (!$includeLowPrice) {
            //если НЕ учитываем товары с низкой ценой, то ищем такие товары
            $lowPriceProducts = self::filterLowPriceProducts($productIds);
        }

        $basketSummForBonus = 0;
        foreach ($prepareBasketItems as $arItem) {
            if (!in_array($arItem['PRODUCT_ID'], $lowPriceProducts)) {
                $basketSummForBonus += $arItem['SUM_VALUE'];
            }
        }

        return $basketSummForBonus;
    }

    /**
     * Возвращает ID товаров с низкой ценой
     *
     * @param  array  $productsIds  - ID товаров среди которых будет производится поиск
     */
    public static function filterLowPriceProducts($productsIds)
    {
        $location = CurrentLocation::getInstance();

        $rsElem = \CIBlockElement::GetList(
            [],
            [
                'ID' => $productsIds,
            ],
            false,
            false,
            [
                'ID',
                'PROPERTY_STATUS_' . strtoupper($location->getDomain()),
            ]
        );
        /** @var array $lowPriceProducts ID товаров с низкой ценой */
        $lowPriceProducts = [];
        while ($arElem = $rsElem->GetNext()) {
            if ($arElem['PROPERTY_STATUS_' . strtoupper($location->getDomain()) . '_VALUE'] == 'Низкая цена') {
                $lowPriceProducts[] = $arElem['ID'];
            }
        }

        return $lowPriceProducts;
    }
}