<?php

namespace Stroy\Core\Loyalty;

use Bitrix\Sale\ {
    Basket\Storage,
    BasketItem,
    Fuser,
    PriceMaths
};
use Stroy\Core\ {
    Api\Loymax as LoymaxApi,
    Catalog\Product,
    Hlblock\SvoystvaTovarovPoGorodam,
    Loymax\LoymaxAuth,
    Sale\Basket,
    User\Manager
};
use Bitrix\Main\ {
    ArgumentNullException,
    Context
};
use Stroy\Regionality\Location\CurrentLocationManager;

class LoyaltyCard
{
    private LoymaxApi $api;
    private LoymaxAuth $auth;

    /** @var string События, связанные с покупкой */
    public const HISTORY_PURCHASE_TYPE = 'PurchaseData';

    /** @var string События, связанные с начислением бонусов без покупки */
    public const HISTORY_REWARD_TYPE = 'RewardData';

    /** @var string События, связанные со списанием бонусов без покупкии */
    public const HISTORY_WITHDRAW_TYPE = 'WithdrawData';

    /** @var string[] Исключение типов начисления (исключаем Discount, потому что "Прямая скидка" приходит как зачисление)**/
    public const EXCLUDED_REWARD_TYPE = ['Discount'];

    /** @var string Номер телефона, к которому привязана карта */
    private string $cardPhoneNumber;

    /** @var string Лог для ошибок по карте лояльности(всегда пишется) */
    public const ERROR_LOG_FILE = '/local/var/log/loyalty_card_errors.log';

    /** @var int Время кеширования [3600 - 1 час] */
    private const CACHE_TIME = 3600 * 8;

    /** @var int Общая скидка */
    public static $totalDiscount = 0;

    public function __construct()
    {
        if (Manager::isLoymaxDiscountAccess()) {
            $this->cardPhoneNumber = Manager::getLoymaxCard();
        } else {
            throw new \Exception('Карта лояльности не привязана');
        }
        
        $this->api = new LoymaxApi();
        $this->auth = new LoymaxAuth();
    }

    /**
     * Возвращает объект LoymaxApi
     * @return LoymaxApi
     */
    public function getApi(): LoymaxApi
    {
        return $this->api;
    }

    /**
     * Возвращает объект Location
     * @return LoymaxAuth
     */
    public function getAuth(): LoymaxAuth
    {
        return $this->auth;
    }

    /**
     * Расчитывает скидки и бонусы с применением скидок сайта
     * @param float $paymentBonus
     * @param array $basketProductsIds
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\NotImplementedException
     */
    public function calculateWithSiteDiscounts(float $paymentBonus = 0, array $basketProductsIds = [], $basket = null)
    {
        $basketItems = $basket ?? Basket::getItems();

        if (!empty($basketProductsIds)) {
            $basketItems = array_filter($basketItems,
                fn ($basketItem) => in_array($basketItem->getProductId(), $basketProductsIds));
        }

        $checkLines = $this->getCheckLines($basketItems, true);
        $result = $this->api->calculateBonusesAndDiscounts($this->auth->getAccessToken(), $checkLines, $paymentBonus);

        $result['isSiteDiscount'] = true;

        return $result;
    }

    /**
     * Расчитывает скидки и бонусы с применением скидок акций
     * @param float $paymentBonus
     * @param float $basketSum
     * @param array $basketProductsIds
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\NotImplementedException
     */
    public function calculateWithActionDiscounts(float $paymentBonus = 0, float $basketSum = 0, array $basketProductsIds = []): array
    {
        $basketItems = Basket::getItems();

        if (!empty($basketProductsIds)) {
            $basketItems = array_filter($basketItems,
                fn ($basketItem) => in_array($basketItem->getProductId(), $basketProductsIds));
        }

        if (empty($basketItems)) {
            return [];
        }

        $checkLines = $this->getCheckLines($basketItems);
        $result = $this->api->calculateBonusesAndDiscounts($this->auth->getAccessToken(), $checkLines, $paymentBonus);

        $basketPrice = $basketSum;

        if (empty($basketPrice)) {
            /** @var BasketItem $basketItem */
            foreach ($basketItems as $basketItem) {
                $basketPrice += PriceMaths::roundPrecision($basketItem->getPrice() * $basketItem->getQuantity());
            }
        }

        $calculatedPrice = $result['totalAmount'];
        $result['isSiteDiscount'] = $basketPrice < $calculatedPrice;

        if ($result['isSiteDiscount']) {
            $checkLines = $this->getCheckLines($basketItems, true);
            $result = $this->api->calculateBonusesAndDiscounts($this->auth->getAccessToken(), $checkLines, $paymentBonus);
            $result['isSiteDiscount'] = true;
        }

        // Если скидка магазина больше, чем скидка из карты лояльности, то берем цены сайта
        if ($basketPrice < $calculatedPrice) {
            $totalBasePrice = 0;

            /** @var BasketItem $basketItem */
            foreach ($basketItems as $basketItem) {
                $result['positionsAmounts'][$basketItem->getProductId()] = $basketItem->getPrice() * $basketItem->getQuantity();
                $result['positionsDiscounts'][$basketItem->getProductId()] = abs($basketItem->getBasePrice() * $basketItem->getPrice());
                $totalBasePrice += PriceMaths::roundPrecision($basketItem->getBasePrice() * $basketItem->getQuantity());
            }

            $result['totalAmount'] = $basketPrice;
            $result['totalDiscount'] = $totalBasePrice - $basketPrice;
        }

        return $result ?? [];
    }

    /**
     * Списывает бонусы с карты и возвращает ответ, сколько бонусов было списано по каждому товару
     * @param array $calculateResult Ответ метода calculate
     * @return array [Ид товара => кол-во начисленных бонусов, ...]
     */
    public function withdraw(array $calculateResult, float $bonusPayment = 0): array
    {
        return $this->api->withdrawBonuses($this->auth->getAccessToken(), $calculateResult, $bonusPayment);
    }

    /**
     * Начисляет бонусы на карту и возвращает ИД транзакции и ответ, сколько бонусов было начислено по каждому товару
     * @param array $calculateResult Ответ метода calculate
     * @return array [ИД транзакции, [Ид товара => кол-во начисленных бонусов, ...]]]
     */
    public function reward(array $calculateResult): array
    {
        return $this->api->rewardBonuses($this->auth->getAccessToken(), $calculateResult);
    }

    /**
     * Возвращает информацию о пользователях
     * @param array $arFilter - Фильтр пользователей
     * @return array
     */
    public function getUsers(array $arFilter = []): array
    {
        return $this->api->getUsers($this->auth->getAccessToken('apm'), $arFilter);
    }

    /**
     * Возвращает список карт пользователя
     * @param int $userId - ID пользователя в системе лоймакс
     * @return array
     */
    public function getUserCards(int $userId): array
    {
        return $this->api->getUserCards($this->auth->getAccessToken('apm'), $userId);
    }

    /**
     * Проверить тип карты. Новосёл [000000056, 000000035, 000000039]
     * @return bool
     */
    public function isUserCardNovosel(): bool
    {
        return in_array($this->auth->getCardType(), ['000000056']);
    }

    /**
     * Проверить тип карты. Накопительная [000000075]
     * @return bool
     */
    public function isUserCardNakopitelnaya(): bool
    {
        return in_array($this->auth->getCardType(), ['000000075']);
    }

    /**
     * Проверить тип карты. Виртуальная [000000054]
     * @return bool
     */
    public function isUserCardVirtual(): bool
    {
        return in_array($this->auth->getCardType(), ['000000054']);
    }

    /**
     * Возвращает список доступных значений счетчика
     * @param int $counterId - ID пользователя в системе лоймакс
     * @param array $arFilter - Фильтр
     * @return array
     */
    public function getUserCountersValues(int $counterId, array $arFilter = []): array
    {
        return $this->api->getUserCountersValues($this->auth->getAccessToken('apm'), $counterId, $arFilter);
    }

    /**
     * Возвращает информацию обо всех операциях активации и сгораниях по конкретному счету клиента.
     * @param int $userId - ID пользователя в системе лоймакс
     * @param int $currencyId - ID валюты, счёта
     * @param array $arFilter - Фильтр
     * @return array
     */
    public function getUserDetailedBalanceById(int $userId, int $currencyId, array $arFilter = []): array
    {
        return $this->api->getUserDetailedBalanceById($this->auth->getAccessToken('apm'), $userId, $currencyId, $arFilter);
    }

    /**
     * Возвращает информацию по изображению
     * @param string $imageId - ID изображения
     * @return array
     */
    public function getImagesById(string $imageId): array
    {
        return $this->api->getImagesById($this->auth->getAccessToken('apm'), $imageId);
    }

    /**
     * Возвращает данные по балансу карты
     * @return array
     * @throws \Exception
     */
    public function getBalance(): array
    {
        return $this->api->getBalance($this->auth->getAccessToken('pl'));
    }

    /**
     * Возвращает операции с бонусами
     * @param int $currencyId ИД валюты
     * @return array
     * @throws \Exception
     */
    public function getOperationsByCurrencyId(int $currencyId): array
    {
        return $this->api->getOperationsByCurrencyId($this->auth->getAccessToken('pl'), $currencyId);
    }

    /**
     * Возвращает операции с бонусами
     * @param int $itemsCount кол-во записей
     * @param bool $withCheckItems включить в ответ позиции чека
     * @return array
     * @throws \Exception
     */
    public function getHistory(int $itemsCount, bool $withCheckItems): array
    {
        $history = [];

        $items = $this->api->getHistory($this->auth->getAccessToken('pl'), $itemsCount) ?? [];

        foreach ($items as $item) {
            $historyItem = [
                'type' => $item['type'],
                'date' => $item['dateTime'],
            ];

            if ($withCheckItems) {
                $historyItem['chequeItems'] = $item['data']['chequeItems'] ?? [];
            }

            switch ($item['type']) {
            case self::HISTORY_REWARD_TYPE:
            case self::HISTORY_WITHDRAW_TYPE:
                $historyItem['amounts'] = [$item['data']['amount']['amount'] ?? 0];
                break;

            case self::HISTORY_PURCHASE_TYPE:
                $sum = $item['data']['amount']['amount'] ?? 0;

                $historyItem['orderSum'] = $sum;
                $rewards = $item['data']['rewards'] ?? [];
                $reward = 0;

                foreach ($rewards as $rewardsItem) {
                    if (in_array($rewardsItem['rewardType'], self::EXCLUDED_REWARD_TYPE)) {
                        continue;
                    }
                    $reward += $rewardsItem['amount']['amount'];
                }

                if ($reward) {
                    $historyItem['amounts'][] = $reward;
                }

                $withdraws = $item['data']['withdraws'] ?? [];
                $withdraw = 0;

                foreach ($withdraws as $withdrawItem) {
                    $withdraw += $withdrawItem['amount']['amount'];
                }

                if ($withdraw) {
                    $historyItem['amounts'][] = $withdraw;
                }
            }
            $history[] = $historyItem;
        }

        return $history;
    }

    /**
     * Возвращает операции с бонусами
     * @param int $userId - ID пользователя в системе лоймакс
     * @param array $arFilter - Фильтр
     * @return array
     */
    public function getHistoryFull(int $userId, array $arFilter = []): array
    {
        return $this->api->getHistoryFull($this->auth->getAccessToken('apm'), $userId, $arFilter);
    }

    /**
     * Возвращает список позиций
     * @param array $basketItems
     * @param bool $withPartnerSale Использовать скидку партнера. Скидки по акциям не будут расчитываться
     * @return array
     */
    public function getCheckLines(array $basketItems, bool $withPartnerSale = false)
    {
        #Местоположению пользователя
        $obLocation = \Stroy\Regionality\Location\CurrentLocation::getInstance();
        $resLocationPath = $obLocation->getMainCity()->getResult()->get('LOCATION_PATH');
        $resLocationPath = array_reverse($resLocationPath);
        $locUserCity = $locUserRegion = '';
        foreach ($resLocationPath as $arItem) {
            if (!$locUserCity && in_array($arItem['TYPE_ID'], ['5'])) {
                $locUserCity = $arItem['CODE'];
            } else {
                if (!$locUserRegion && in_array($arItem['TYPE_ID'], ['3', '4'])) {
                    $locUserRegion = $arItem['CODE'];
                }
            }
        }

        #Информация по корзине
        $arProducts = [];
        foreach ($basketItems as $basketItem) {
            $arProducts[$basketItem->getProductId()] = [
                'ID' => $basketItem->getField('PRODUCT_ID'),
                'NAME' => $basketItem->getField('NAME'),
                'PRICE_TYPE_ID' => $basketItem->getField('PRICE_TYPE_ID')
            ];
        }

        #Информация из ИБ
        $resProductByIds = Product::getByIds(array_keys($arProducts), ['ID', 'XML_ID', 'PROPERTY_CML2_ARTICLE']);
        if ($resProductByIds) {
            $productsXmlIds = array_map(fn($arItem) => $arItem['XML_ID'] ?? '', $resProductByIds);
            $productsStatuses = $this->getStatuses($productsXmlIds);
            foreach ($arProducts as $key => $arItem) {
                $arProducts[$key]['XML_ID'] = $resProductByIds[$arItem['ID']]['XML_ID'];
                $arProducts[$key]['PROPERTY_CML2_ARTICLE_VALUE'] = $resProductByIds[$arItem['ID']]['PROPERTY_CML2_ARTICLE_VALUE'];
                $arProducts[$key]['STATUS'] = $productsStatuses[$arItem['ID']]['STATUS'] ?? 'Обычный';
            }
            unset($resProductByIds, $productsXmlIds, $productsStatuses);
        }

        #Местоположению товара по складу
        $resLocation = CurrentLocationManager::getLocationByPriceId(array_column($arProducts, 'PRICE_TYPE_ID'));
        if ($resLocation) {
            foreach ($arProducts as $key => $arItem) {
                $arProducts[$key]['LOCATION'] = $resLocation[$arItem['PRICE_TYPE_ID']];
            }
            unset($resLocation);
        }

        $lines = [];
        foreach ($basketItems as $basketItem) {
            $arProduct = $arProducts[$basketItem->getProductId()];

            $productParams = [
                ['name' => 'Status', 'value' => $arProduct['STATUS'], 'type' => 'String'],
                ['name' => 'ChannelSale', 'value' => 'Интернет магазин', 'type' => 'String'],
                ['name' => 'CardType', 'value' => $this->auth->getCardType(), 'type' => 'String'],
                ['name' => 'Base', 'value' => 'InternetShop', 'type' => 'String'],
                // ['name' => 'LocUserCity', 'value' => $locUserCity, 'type' => 'String'],
                // ['name' => 'LocUserRegion', 'value' => $locUserRegion, 'type' => 'String'],
                ['name' => 'LocProductCity', 'value' => $arProduct['LOCATION']['UF_LOC_CODE'], 'type' => 'String'],
            ];

            if (isset($arProduct['FIX_PRICE'])) {
                $productParams[] = $arProduct['FIX_PRICE'];
            }

            $price = PriceMaths::roundPrecision($basketItem->getBasePrice());
            $priceWithDiscount = $price;
            $discount = 0;

            if ($withPartnerSale) {
                $priceWithDiscount = $basketItem->getPrice();
                $discount = $price - $priceWithDiscount;
                $productParams[] = [
                    'name' => 'PartnerSale',
                    'value' => 'PartnerSale',
                    'type' => 'String'
                ];
            }

            $quantity = $basketItem->getQuantity();
            $amount = PriceMaths::roundPrecision($priceWithDiscount * $quantity);

            $line = [
                'position' => $arProduct['ID'],
                'amount' => (string)round($amount, 2),
                'goodsId' => $arProduct['PROPERTY_CML2_ARTICLE_VALUE'],
                'quantity' => $quantity,
                'name' => $arProduct['NAME'],
                'price' => (string)round($price, 2),
                'priceOld' => $basketItem->getBasePrice() ?? $basketItem->getPrice(),
                'params' => [
                    'paramsList' => $productParams
                ]
            ];

            if ($withPartnerSale && $discount > 0) {
                $positionDiscount = $discount * $quantity;
                $line['discount'] = (string)round($positionDiscount, 2);
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Получение статусов товаров по номенклатуре из таблицы SvoystvaTovarovPoGorodam
     *
     * @param array $productsXmlIds - массив из номенклатур позиций [ИД товара => XML_ID]
     * @return array
     * @throws \Exception
     */
    private function getStatuses(array $productsXmlIds): array
    {
        try {
            $propertiesOfCity = new SvoystvaTovarovPoGorodam();
            $productStatuses = $propertiesOfCity->get($productsXmlIds);
        } catch (\Exception $ex) {
            return [];
        }

        $statuses = [];
        foreach ($productStatuses as $productStatus) {
            $productId = array_search($productStatus['UF_NOMENKLATURA'], $productsXmlIds);
            $statuses[$productId]['STATUS'] = $productStatus['UF_STATUS'];

            /**
             * Проверяем на фиксированную цену и формируем доп. узел для запроса
             */
            if ($productStatus['UF_STATUS'] == 'Фиксированная цена') {
                $statuses[$productId]['FIX_PRICE'] = [
                    'name' => 'StopSale',
                    'value' => 'StopSale',
                    'type' => 'String'
                ];
            }
        }

        return $statuses;
    }

    /**
     * @throws ArgumentNullException
     */
    public function getActionsBasket(): array
    {
        $result = ['items' => [], 'totalDiscount' => 0, 'notes' => ''];

        $resActions = $this->getBasketDiscounts();
        foreach ($resActions as $action) {
            if ($action['label'] == '' || empty($action['appliedForProducts'])) {
                continue;
            }
            foreach ($action['appliedForProducts'] as $productId) {
                $result['items'][$productId]['name'] = $action['name'];
                $result['items'][$productId]['labels'][] = $action['label'];
            }
        }
        $result['totalDiscount'] = self::$totalDiscount;

        if (count($resActions)) {
            $resLoymaxLevelAccess = Manager::getLoymaxLevelAccess();
            if ($resLoymaxLevelAccess['LEVEL_MIN'] == 1) {
                $result['notes'] = 'Спецпредложения и акции действительны по <a href="/profile/club/" title="Выпустить клубную карту">карте лояльности</a>. <a href="/profile/club/" title="Выпустить клубную карту">Оформить карту</a>';
            } else if ($resLoymaxLevelAccess['LEVEL_MIN'] > 1) {
                $result['notes'] = 'Спецпредложения и акции действительны по <a href="javascript:void(0);" data-fancybox="" data-type="ajax" data-touch="false" data-src="/ajax/forms/popup_enter.php" title="Авторизоваться на сайте">карте лояльности</a>. <a href="javascript:void(0);" data-fancybox="" data-type="ajax" data-touch="false" data-src="/ajax/forms/popup_enter.php" title="Авторизоваться на сайте">Оформить карту</a>';
            }
        }

        return $result;
    }

    /**
     * Возвращает номер телефона, к которому привязана карта лояльности
     * @return string
     */
    public function getCardPhoneNumber(): string
    {
        return $this->cardPhoneNumber;
    }

    /**
     * Скидки для товаров в корзине
     * @return array
     * @throws ArgumentNullException
     */
    public function getBasketDiscounts(): array
    {
        $arResult = [];

        #Получим корзину пользователя
        $fUserId = Fuser::getId();
        $siteId = Context::getCurrent()->getSite();

        $obBasketStorage = Storage::getInstance($fUserId, $siteId);
        $obFullBasket = $obBasketStorage->getBasket();
        $obBasketItems = $obFullBasket->getBasketItems();

        try {
            $arCheckLines = $this->getCheckLines($obBasketItems);
            $resCalcPurchases = $this->api->calculatePurchases($this->auth->getAccessToken(), $arCheckLines);
        } catch (\Exception $e) {
            return $arResult;
        }

        #Получим описание акций из системы Лоймакс
        $arAppliedForProducts = [];
        if ($resCalcPurchases['data'] && $resCalcPurchases['data'][0]['cheque']['totalDiscount']) {
            self::$totalDiscount = $resCalcPurchases['data'][0]['cheque']['totalDiscount'];
            $resCalcPurchases = current($resCalcPurchases['data']);
            foreach ($resCalcPurchases['cheque']['lines'] as $arItem) {
                if (!$arItem['discount'] || empty($arItem['appliedOffers'])) {
                    continue;
                }
                foreach ($arItem['appliedOffers'] as $arItemAction) {
                    $arAppliedForProducts[$arItemAction['id']][] = $arItem['position'];
                    if ($arItemAction['prerefenceValue']) {
                        $arResult[$arItemAction['id']] = [
                            'id' => $arItemAction['id'],
                            'name' => $arItemAction['name']
                        ];
                    }
                }
            }
            foreach ($arResult as $actionId => & $row) {
                $row['appliedForProducts'] = $arAppliedForProducts[$actionId];
            }
            if (!$arResult) {
                return $arResult;
            }

            #Получим все активные акции
            $resActionOffers = [];
            $arFilterAction = ['states' => 'Run'];
            $obCache = \Bitrix\Main\Data\Cache::createInstance();
            $cacheId = md5('actionOffers' . serialize($arFilterAction));
            $cachePath = '/stroy.core/loymax/ActionOffers/';

            if ($obCache->initCache(28800, $cacheId, $cachePath)) {
                $resActionOffers = $obCache->GetVars();
            } elseif ($obCache->startDataCache()) {
                $resActionOffers = $this->api->getActionOffers($this->auth->getAccessToken('apm'), $arFilterAction);
                $obCache->endDataCache($resActionOffers);
            }

            if ($resActionOffers['result']["state"] == 'Success') {
                foreach ($resActionOffers["data"] as $item) {
                    if (!isset($arResult[$item['id']]) || !isset($item['description'])) {
                        continue;
                    }
                    $description = json_decode($item['description'], true);
                    if (!isset($description['LABEL']) || isset($description['PARENT_ID'])) {
                        continue;
                    }
                    $arResult[$item['id']]['label'] = $description['LABEL'];
                    $arResult[$item['id']]['name'] = $description['TITLE'];
                }
            }
        }

        return $arResult;
    }

    /**
     * Предсказываем скидку по Id товара
     * @param $productId - Id товара
     * @param $basketVirtual - Включить режим виртуальной корзины с Id товаром
     * @return array
     */
    public function getPredictionDiscounts(int $productId, bool $basketVirtual = true): array
    {
        $result = [];

        #Получим корзину пользователя
        $fUserId = Fuser::getId();
        $siteId = Context::getCurrent()->getSite();
        $quantity = 1;

        $basketStorage = Storage::getInstance($fUserId, $siteId);
        $fullBasket = $basketStorage->getBasket()->createClone();
        if ($basketVirtual) {
            if ($basketItem = $fullBasket->getExistsItems('catalog', $productId, null)) {
                $basketItem = current($basketItem);
                $basketItem->setField('QUANTITY', $basketItem->getQuantity() + $quantity);
            } else {
                $basketItem = $fullBasket->createItem('catalog', $productId);
                $basketItem->setFields([
                    'QUANTITY' => $quantity,
                    'CURRENCY' => \Bitrix\Currency\CurrencyManager::getBaseCurrency(),
                    'LID' => $siteId,
                    'PRODUCT_PROVIDER_CLASS' => '\Stroy\Core\Catalog\CatalogProductProvider'
                ]);
            }
            unset($basketItem);
        }

        if ($fullBasket->isEmpty()) {
            return $result;
        }

        #Расчитаем скидки в системе Лоймакс
        $basketItems = $fullBasket->getBasketItems();
        try {
            $resCheckLines = $this->getCheckLines($basketItems);
            $resCalcPurchases = $this->api->calculatePurchases($this->auth->getAccessToken(), $resCheckLines);
        } catch (\Exception $e) {
            return $result;
        }

        #Получим описание акций из системы Лоймакс
        if ($resCalcPurchases['data']) {
            $resCalcPurchases = current($resCalcPurchases['data']);
            foreach ($resCalcPurchases['cheque']['lines'] as $item) {
                if ($item['position'] == $productId && $item['appliedOffers']) {
                    foreach ($item['appliedOffers'] as $itemAction) {
                        $result[$itemAction['id']] = ['ID' => $itemAction['id'], 'NAME' => $itemAction['name'], 'STATUS' => 'ACTIVATED'];
                    }
                }
            }
            unset($resCalcPurchases);

            if (!$result) {
                return $result;
            }

            #Получим все активные акции
            $resActionOffers = [];
            $filterAction = ['states' => 'Run'];
            $cache = \Bitrix\Main\Data\Cache::createInstance();
            $cacheId = md5('getActionOffers' . serialize($filterAction));
            $cachePath = '/stroy.core/loymax/getActionOffers/';

            if ($cache->initCache(self::CACHE_TIME, $cacheId, $cachePath)) {
                $resActionOffers = $cache->GetVars();
            } elseif ($cache->startDataCache()) {
                $resActionOffers = $this->api->getActionOffers($this->auth->getAccessToken('apm'), $filterAction);
                if ($resActionOffers['result']['state'] == 'Success' && $resActionOffers['data']) {
                    foreach ($resActionOffers['data'] as $key => $item) {
                        if ($item['description']) {
                            $resActionOffers['data'][$key]['description'] = json_decode($item['description'], true);
                            if ($resActionOffers['data'][$key]['description']['GIFT_GOODS_GROUP']) {
                                $resActionOffers['data'][$key]['description']['GIFT_GOODS_GROUP'] = explode(',', $resActionOffers['data'][$key]['description']['GIFT_GOODS_GROUP']);
                            }
                            if ($resActionOffers['data'][$key]['description']['ITEMS_GOODS_GROUP']) {
                                $resActionOffers['data'][$key]['description']['ITEMS_GOODS_GROUP'] = explode(',', $resActionOffers['data'][$key]['description']['ITEMS_GOODS_GROUP']);
                            }
                        }
                    }
                }
                $cache->endDataCache($resActionOffers);
            }

            if ($resActionOffers['result']['state'] == 'Success' && $resActionOffers['data']) {
                #Заменим данные акции предсказании на родительскую
                foreach ($resActionOffers['data'] as $item) {
                    if ($result[$item['id']] && isset($item['description']['PARENT_ID'])) {
                        unset($result[$item['id']]);
                        if (!$result[$item['description']['PARENT_ID']]) {
                            $result[$item['description']['PARENT_ID']] = ['ID' => $item['description']['PARENT_ID'], 'NAME' => $item['name'], 'STATUS' => 'ENABLE'];
                        }
                    }
                }

                #Получим дополнительную информацию по акциям
                foreach ($resActionOffers['data'] as $key => $item) {
                    if ($result[$item['id']]) {
                        if (!is_array($item['description'])) {
                            continue;
                        }

                        #Период действия акции
                        if ($item['description']['TIMER'] == 'ACTIVE') {
                            if ($item['description']['TYPE'] == 'BIRTHDAY') {
                                $cacheId = md5('getActionOffersBirthday-' . $item['id'] . '-' . $item['versionId']);
                                $cachePath = '/stroy.core/loymax/getActionOffersBirthday/';
                                if ($cache->initCache(self::CACHE_TIME, $cacheId, $cachePath)) {
                                    $birthDateEnd = $cache->GetVars();
                                } elseif ($cache->startDataCache()) {
                                    $birthDateEnd = '';
                                    $resUser = $this->api->getUsers($this->auth->getAccessToken('apm'), ['identifierText' => $this->getCardPhoneNumber()]);
                                    if ($resUser['result']['state'] == 'Success' && $resUser['data'] && $resUser['data'][0]['birthDay']) {
                                        $resActionId = $this->api->getActionOfferExportById($this->auth->getAccessToken('apm'), $item['id']);
                                        if ($resActionId['id']) {
                                            $birthDateEnd = new \DateTime(date('Y').'-'.date('m-d 23:59:59', strtotime($resUser['data'][0]['birthDay'])));
                                            // Определим фильтры акции, рассматриваем только 1 событие[events[0]]
                                            foreach ($resActionId['rules']['events'][0]['chains'] as $rulesChain) {
                                                foreach ($rulesChain['filters'] as $rulesFilters) {
                                                    if (
                                                        strpos($rulesFilters['value']['$type'], 'Offers.Filters.QuestionnaireIntFilterValue') !== false
                                                        && $rulesFilters['value']['operator'] == 'BetweenEqual'
                                                        && $rulesFilters['value']['mode'] == 'Float'
                                                        && $rulesFilters['value']['secondValue'] > 0
                                                    ) {
                                                        $birthDateEnd->modify('+' . $rulesFilters['value']['secondValue'] . ' day');
                                                        break(2);
                                                    }
                                                }
                                            }

                                            $birthDateEnd = $birthDateEnd->format('Y-m-d H:i:s');
                                        }
                                    }

                                    $cache->endDataCache($birthDateEnd);
                                }

                                $resActionOffers['data'][$key]['endTime'] = $birthDateEnd;
                            } else {
                                $cacheId = md5('getActionOffersPeriod-' . $item['id'] . '-' . $item['versionId']);
                                $cachePath = '/stroy.core/loymax/getActionOffersPeriod/';

                                if ($cache->initCache(self::CACHE_TIME, $cacheId, $cachePath)) {
                                    $resActionOffersPeriod = $cache->GetVars();
                                } elseif ($cache->startDataCache()) {
                                    $resActionOffersPeriod = $this->api->getActionOffersPeriod($this->auth->getAccessToken('apm'), $item['id'], $item['versionId']);
                                    $cache->endDataCache($resActionOffersPeriod);
                                }

                                if ($resActionOffersPeriod['result']['state'] == 'Success' && $resActionOffersPeriod['data']['startDate']) {
                                    $resActionOffers['data'][$key]['period'] = $resActionOffersPeriod['data'];
                                }
                            }
                        }
                    }
                }

                #Соберём все данные
                foreach ($resActionOffers['data'] as $item) {
                    if ($result[$item['id']]) {
                        if (!is_array($item['description'])) {
                            unset($result[$item['id']]);
                            continue;
                        }

                        if ($item['beginTime']) {
                            #Получим beginTime без учёта таймзоны
                            $beginTimeReset = new \DateTime((new \DateTime($item['beginTime']))->format('Y-m-d H:i:s'));
                            $item['beginTimeReset'] = $beginTimeReset->format('Y-m-d H:i:s');
                            $item['beginTimeResetUnix'] = $beginTimeReset->getTimestamp();
                        }

                        if ($item['endTime']) {
                            #Сбрасываем Таймзону
                            $endTimeReset = new \DateTime((new \DateTime($item['endTime']))->format('Y-m-d H:i:s'));
                            $item['endTimeReset'] = $endTimeReset->format('Y-m-d H:i:s');
                            $item['endTimeResetUnix'] = $endTimeReset->getTimestamp();

                            #Таймзона Лоймакса по умолчанию Москва
                            $loymaxTimeZone = new \DateTimeZone('Europe/Moscow');
                            $locationTimeZone = new \DateTimeZone(CurrentLocationManager::getCurrentTimeZone());
                            $timeZoneDiff = $loymaxTimeZone->getOffset($endTimeReset) - $locationTimeZone->getOffset($endTimeReset);
                            $endTimeReset->modify($timeZoneDiff.' second');
                            $item['endTimeLocation'] = $endTimeReset->format('Y-m-d H:i:s');
                            $item['endTimeLocationUnix'] = $endTimeReset->getTimestamp();
                        }

                        #Расчёт таймера
                        if ($item['description']['TIMER'] == 'ACTIVE') {
                            $timerTime = $item['endTimeLocationUnix'];

                            if ($item['period']['daysOfWeek'] || $item['period']['endTime']) {
                                $timerTime = ['CURRENT' => new \DateTime(), 'END' => (new \DateTime())->setTime(23, 59, 59)];
                                if ($item['period']['endTime']) {
                                    $hour = $item['period']['endTime']['hour'] ?: 0;
                                    $minute = $item['period']['endTime']['minute'] ?: 0;
                                    $timerTime['END']->setTime($hour, $minute, 59);
                                }

                                $timerTime = $timerTime['END']->getTimestamp();
                            }
                        }

                        $item['description']['ICON'] = $item['description']['TYPE'] ?: '';
                        if ($item['description']['ICON'] == 'ADVICE') {
                            $item['description']['ICON'] = '';
                        }

                        $result[$item['id']] = [
                            'ID' => $item['id'],
                            'VERSION_ID' => $item['versionId'],
                            'EXTERNAL_ID' => $item['externalID'],
                            'NAME' => $item['description']['TITLE'] ?: $item['name'],
                            'BEGIN_TIME' => $item['beginTime'] ?: '',
                            'BEGIN_TIME_RESET' => $item['beginTimeReset'] ?: '',
                            'BEGIN_TIME_RESET_UNIX' => $item['beginTimeResetUnix'] ?: '',
                            'END_TIME' => $item['endTime'] ?: '',
                            'END_TIME_RESET' => $item['endTimeReset'] ?: '',
                            'END_TIME_RESET_UNIX' => $item['endTimeResetUnix'] ?: '',
                            'TIMER_TIME' => $timerTime ?: 0,
                            'DESCRIPTION' => $item['description']['DESCRIPTION'] ?: '',
                            'DESCRIPTION_SHORT' => $item['description']['DESCRIPTION_SHORT'] ?: '',
                            'TYPE' => $item['description']['TYPE'] ?: 'DEFAULT',
                            'ICON' => $item['description']['ICON'],
                            'TIMER' => $item['description']['TIMER'] ?: '',
                            'LABEL' => $item['description']['LABEL'] ?: '',
                            'STATUS' => $result[$item['id']]['STATUS']
                        ];

                        if ($item['description']['GIFT_GOODS_GROUP']) {
                            $result[$item['id']]['GIFT_GOODS_GROUP'] = $item['description']['GIFT_GOODS_GROUP'];
                        }
                        if ($item['description']['ITEMS_GOODS_GROUP']) {
                            $result[$item['id']]['ITEMS_GOODS_GROUP'] = $item['description']['ITEMS_GOODS_GROUP'];
                        }
                    }
                }
            }

            #Проверям массив $arResult, удаляем пустые данные
            foreach ($result as $key => $item) {
                if (count($item) <= 5) {
                    unset($result[$key]);
                }
            }
        }

        return $result;
    }

    /**
     * Рассчитать статус акции
     * @param $actionId - Id акции в системе лоймакс
     * @return array
     * status ENABLE - включена, акция включена и может быть рассчитана.
     * status APPLY - применить, акция готова к применению.
     * status ACTIVATED - активирована, акция уже применена.
     * status DISABLE - выключен, акция выключена и не может быть рассчитана.
     */
    public function getActionCalculateStatus(int $actionId): array
    {
        // Получим корзину пользователя
        $fUserId = Fuser::getId();
        $siteId = Context::getCurrent()->getSite();
        $basketStorage = Storage::getInstance($fUserId, $siteId);
        $fullBasket = $basketStorage->getBasket();

        if ($fullBasket->isEmpty()) {
            return [];
        }

        // Получим правила акции
        $cache = \Bitrix\Main\Data\Cache::createInstance();
        $cacheId = md5('getActionOfferExportById-' . $actionId);
        $cachePath = '/stroy.core/loymax/getActionOfferExportById/';

        if ($cache->initCache(self::CACHE_TIME, $cacheId, $cachePath)) {
            $resAction = $cache->GetVars();
        } elseif ($cache->startDataCache()) {
            $resAction = $this->api->getActionOfferExportById($this->auth->getAccessToken('apm'), $actionId);
            if ($resAction['id']) {
                if ($resAction['description']) {
                    $resAction['description'] = json_decode($resAction['description'], true);
                    if ($resAction['description']['GIFT_GOODS_GROUP']) {
                        $resAction['description']['GIFT_GOODS_GROUP'] = explode(',', $resAction['description']['GIFT_GOODS_GROUP']);
                    }
                    if ($resAction['description']['ITEMS_GOODS_GROUP']) {
                        $resAction['description']['ITEMS_GOODS_GROUP'] = explode(',', $resAction['description']['ITEMS_GOODS_GROUP']);
                    }
                }

                $resAction['externalID'] = $resAction['id'];
                $resAction['id'] = $actionId;
            }
            $cache->endDataCache($resAction);
        }

        if (!$resAction['id'] || !is_array($resAction['description'])) {
            return [];
        }

        $result = [
            'ID' => $resAction['id'],
            'EXTERNAL_ID' => $resAction['externalID'],
            'NAME' => $resAction['description']['TITLE'] ?: $resAction['name'],
            'BEGIN_TIME' => $resAction['applyChangesDate'] ?: '',
            'END_TIME' => $resAction['expirationDate'] ?: '',
            'DESCRIPTION' => $resAction['description']['DESCRIPTION'] ?: '',
            'DESCRIPTION_SHORT' => $resAction['description']['DESCRIPTION_SHORT'] ?: '',
            'TYPE' => $resAction['description']['TYPE'] ?: 'DEFAULT',
            'TIMER' => $resAction['description']['TIMER'] ?: '',
            'LABEL' => $resAction['description']['LABEL'] ?: '',
            'STATUS' => 'DISABLE'
        ];
        if ($resAction['description']['ITEMS_GOODS_GROUP']) {
            $result['ITEMS_GOODS_GROUP'] = $resAction['description']['ITEMS_GOODS_GROUP'];// Группа акционных товаров
            $result['ITEMS'] = [];
            $result['ITEMS_MIN_QUANTITY'] = [];// Минимальное количество элементов с которой начинается акция
        }
        if ($resAction['description']['GIFT_GOODS_GROUP']) {
            $result['GIFT_GOODS_GROUP'] = $resAction['description']['GIFT_GOODS_GROUP'];// Группа подарочных товаров
            $result['GIFT_ITEMS'] = [];
            $result['GIFT_MIN_QUANTITY'] = [];// Минимальное количество элементов с которой начинается акция
        }

        $goodsGroups = [];
        if ($resAction['description']['ITEMS_GOODS_GROUP']) {
            $goodsGroups = array_merge($goodsGroups, $resAction['description']['ITEMS_GOODS_GROUP']);
        }
        if ($resAction['description']['GIFT_GOODS_GROUP']) {
            $goodsGroups = array_merge($goodsGroups, $resAction['description']['GIFT_GOODS_GROUP']);
        }
        if (!$goodsGroups) {
            return $result;
        }

        $result['STATUS'] = 'ENABLE';

        // Получим товары из групп
        $cacheId = md5('getActionGoodsGroupsExport' . serialize($goodsGroups));
        $cachePath = '/stroy.core/loymax/getActionGoodsGroupsExport/';

        if ($cache->initCache(self::CACHE_TIME, $cacheId, $cachePath)) {
            $goodsGroups = $cache->GetVars();
        } elseif ($cache->startDataCache()) {
            foreach ($goodsGroups as $key => $item) {
                unset($goodsGroups[$key]);
                $resActionGoodsGroups = $this->api->getActionGoodsGroupsExportById($this->auth->getAccessToken('apm'), $item);
                if ($resActionGoodsGroups['goodsGroups']) {
                    foreach ($resActionGoodsGroups['goodsGroups'] as $goodsItem) {
                        $goodsGroups[$goodsItem['id']] = [
                            'ID' => $item,
                            'EXTERNAL_ID' => $goodsItem['id'],
                            'NAME' => $goodsItem['name'],
                            'TYPE' => 'ITEMS',
                            'COMPOSITION' => ['LOGIC' => 'QUANTITY', 'VALUE' => []],
                            'ITEMS' => []
                        ];
                        if ($resAction['description']['GIFT_GOODS_GROUP'] && in_array($item, $resAction['description']['GIFT_GOODS_GROUP'])) {
                            $goodsGroups[$goodsItem['id']]['TYPE'] = 'GIFT';
                        }

                        foreach ($goodsItem['includingSets'] as $goodsSets) {
                            foreach ($goodsSets['catalogItems'] as $goodsCatalog) {
                                foreach ($goodsCatalog['items'] as $goodsCatalogItem) {
                                    $goodsGroups[$goodsItem['id']]['ITEMS'][] = $goodsCatalogItem['value'];
                                }
                            }
                        }
                    }
                }
            }
            unset($item, $resActionGoodsGroups, $goodsItem, $goodsSets, $goodsCatalog, $goodsCatalogItem);
            $cache->endDataCache($goodsGroups);
        }

        if (!$goodsGroups) {
            return $result;
        }

        // Определим условия акции, рассматриваем только 1 событие[events[0]]
        foreach ($resAction['rules']['events'][0]['chains'] as $rulesChain) {
            foreach ($rulesChain['actions'] as $rulesAction) {
                foreach ($rulesAction['compositionInfo'] as $rulesGoods) {
                    if ($goodsGroups[$rulesGoods['goodsGroup']['id']]) {
                        $goodsGroups[$rulesGoods['goodsGroup']['id']]['COMPOSITION']['VALUE'][] = $rulesGoods['value'];
                        if (!$goodsGroups[$rulesGoods['goodsGroup']['id']]['COMPOSITION']['LOGIC']) {
                            $goodsGroups[$rulesGoods['goodsGroup']['id']]['COMPOSITION']['LOGIC'] = $rulesAction['targetFieldType'];
                        }
                    }
                }
            }
        }

        // Развернём массив GoodsGroups=>id на id=>GoodsGroups
        $itemsGroups = [];
        foreach ($goodsGroups as $goodsItem) {
            if (is_array($goodsItem['COMPOSITION']['VALUE'])) {
                if ($goodsItem['TYPE'] == 'GIFT') {
                    $result['GIFT_MIN_QUANTITY'] = array_merge((array)$result['GIFT_MIN_QUANTITY'], $goodsItem['COMPOSITION']['VALUE']);
                } else {
                    if (in_array($result['TYPE'], ['GIFT', 'ADVICE'])) {
                        $result['ITEMS_MIN_QUANTITY'] = array_merge((array)$result['ITEMS_MIN_QUANTITY'], $goodsItem['COMPOSITION']['VALUE']);
                    } else {
                        $result['ITEMS_MIN_QUANTITY'] = [1];
                    }
                }
            }

            foreach ($goodsItem['ITEMS'] as $item) {
                if (!$itemsGroups[$goodsItem['TYPE']][$item]) {
                    $itemsGroups[$goodsItem['TYPE']][$item] = ['GOODS_GROUPS' => [], 'COMPOSITION' => []];
                }
                if (!in_array($goodsItem['EXTERNAL_ID'], $itemsGroups[$goodsItem['TYPE']][$item]['GOODS_GROUPS'])) {
                    $itemsGroups[$goodsItem['TYPE']][$item]['GOODS_GROUPS'][] = $goodsItem['EXTERNAL_ID'];
                }
                if (is_array($goodsItem['COMPOSITION']['VALUE'])) {
                    if (in_array($result['TYPE'], ['GIFT', 'ADVICE'])) {
                        $itemsGroups[$goodsItem['TYPE']][$item]['COMPOSITION'] = array_merge($itemsGroups[$goodsItem['TYPE']][$item]['COMPOSITION'], $goodsItem['COMPOSITION']['VALUE']);
                    } else {
                        $itemsGroups[$goodsItem['TYPE']][$item]['COMPOSITION'] = [1];
                    }
                }
            }
        }

        if ($resAction['description']['ITEMS_GOODS_GROUP']) {
            $result['ITEMS_MIN_QUANTITY'] = min($result['ITEMS_MIN_QUANTITY']) ?: 1;
        }
        if ($resAction['description']['GIFT_GOODS_GROUP']) {
            $result['GIFT_MIN_QUANTITY'] = min($result['GIFT_MIN_QUANTITY']) ?: 1;
        }

        // Присутствие ITEMS товаров в корзине
        $basketProducts = [];
        foreach ($fullBasket->getBasketItems() as $basketItem) {
            $basketProducts[$basketItem->getProductId()] = $basketItem->getQuantity();
        }

        $products = Product::getByIds(array_keys($basketProducts), ['ID', 'XML_ID', 'PROPERTY_CML2_ARTICLE']);
        foreach ($products as $key => $item) {
            $products[$key]['BASKET_QUANTITY'] = $item['BASKET_QUANTITY'] = $basketProducts[$item['ID']];
            $products[$key]['ACTION_MIN_QUANTITY'] = 1;
            if ($itemsGroups['ITEMS'][$item['PROPERTY_CML2_ARTICLE_VALUE']] && is_array($itemsGroups['ITEMS'][$item['PROPERTY_CML2_ARTICLE_VALUE']]['COMPOSITION'])) {
                $products[$key]['ACTION_MIN_QUANTITY'] = min($itemsGroups['ITEMS'][$item['PROPERTY_CML2_ARTICLE_VALUE']]['COMPOSITION']);
            }
        }

        // Присутствие ITEMS товаров в корзине
        if ($itemsGroups['ITEMS']) {
            foreach ($products as $item) {
                if (
                    $item['PROPERTY_CML2_ARTICLE_VALUE']
                    && $itemsGroups['ITEMS'][$item['PROPERTY_CML2_ARTICLE_VALUE']]
                    && $item['BASKET_QUANTITY'] >= $item['ACTION_MIN_QUANTITY']
                ) {
                    $result['STATUS'] = 'APPLY';
                    break;
                }
            }

            $result['ITEMS'] = array_keys($itemsGroups['ITEMS']);
        }

        // Присутствие GIFT товаров в корзине
        if ($resAction['description']['TYPE'] == 'GIFT' && $itemsGroups['GIFT']) {
            foreach ($products as $item) {
                if (
                    $item['PROPERTY_CML2_ARTICLE_VALUE']
                    && $itemsGroups['GIFT'][$item['PROPERTY_CML2_ARTICLE_VALUE']]
                    && $item['BASKET_QUANTITY'] >= $item['ACTION_MIN_QUANTITY']
                    && $result['STATUS'] == 'APPLY'
                ) {
                    $result['STATUS'] = 'ACTIVATED';
                    break;
                }
            }

            $result['GIFT_ITEMS'] = array_keys($itemsGroups['GIFT']);
        }

        return $result;
    }

    /**
     * Останавливаем все версии акции
     * @param $offerId - Внутренний идентификатор акции
     * @return array
     */
    public function getActionVersionsStop(int $offerId): array
    {
        $filterAction = ['count' => '200'];
        $result = $this->api->getActionVersions($this->auth->getAccessToken('apm'), $offerId, $filterAction);
        if ($result['result']['state'] == 'Success' && $result['data']) {
            foreach ($result['data'] as $key => $item) {
                if ($key > 5 && $item['isStopped'] !== true) {
                    $result = $this->api->getActionVersionsStop($this->auth->getAccessToken('apm'), $offerId, $item['id']);
                }
            }
        }

        return $result ?: [];
    }

    /**
     * Получить настройки modal
     * @return array
     */
    public static function getSettingsModal(): array
    {
        $result = [
            'css' => [],
            'js' => [],
            'settings' => ['user' => [], 'modal' => []]
        ];

        global $USER;
        if ($USER->IsAuthorized()) {
            $result['settings']['user'] = \Bitrix\Main\UserTable::getList(['order' => ['ID' => 'ASC'], 'filter' => ['ID' => $USER->GetID()], 'select' => ['ID', 'UF_CARD']])->fetch();
            if (!$result['settings']['user']['UF_CARD']) {
                if (!$_SESSION['STROYLANDIYA']['POPUP']['LOYMAX_LOYALTY_CARD']) {
                    $_SESSION['STROYLANDIYA']['POPUP']['LOYMAX_LOYALTY_CARD'] = ['TIME_END' => 0];
                }
                if ($_SESSION['STROYLANDIYA']['POPUP']['LOYMAX_LOYALTY_CARD']['TIME_END'] < time()) {
                    $result['css'][] = './src/css/modal.css';
                    $result['js'][] = './src/js/modal.js';
                    $result['settings']['modal']['loyaltyCard'] = true;
                }
            }
        }

        return $result;
    }

    /**
     * Установить настройку modal окна "информация о возможности завести карту лояльности"
     * @return bool
     */
    public static function setSettingsModalLoyaltyCard(): bool
    {
        if (!$_SESSION['STROYLANDIYA']['POPUP']['LOYMAX_LOYALTY_CARD']) {
            $_SESSION['STROYLANDIYA']['POPUP']['LOYMAX_LOYALTY_CARD'] = ['TIME_END' => 0];
        }
        $_SESSION['STROYLANDIYA']['POPUP']['LOYMAX_LOYALTY_CARD'] = ['TIME_END' => (time() + 3600)];

        return true;
    }

    /**
     * Бонусы по корзине
     *
     * @return array
     */
    public function getBonusesBasket(): array
    {
        $result = [];

        // получим корзину пользователя
        $fullBasket = Basket::getBasket()->createClone();

        if ($fullBasket->isEmpty()) {
            return $result;
        }

        // расчитаем скидки в системе Лоймакс
        $basketItems = $fullBasket->getBasketItems();
        try {
            $resCheckLines = $this->getCheckLines($basketItems);
            $resCalcPurchases = $this->api->calculatePurchases($this->auth->getAccessToken(), $resCheckLines);
        } catch (\Exception $e) {
            return $result;
        }

        // получим описание акций из системы Лоймакс
        if ($resCalcPurchases['data']) {
            $resCalcPurchases = current($resCalcPurchases['data']);
            foreach ($resCalcPurchases['cheque']['lines'] as $item) {
                $result[$item['position']] = $item['cashback'];
            }
        }

        return $result;
    }

    /**
     * Бонусы по Id товара
     *
     * @param $productId - Id товара
     * @return float
     */
    public function getPredictionBonuses(int $productId): float
    {
        $result = 0;

        // получим корзину пользователя
        $siteId = Context::getCurrent()->getSite();

        $fullBasket = Basket::getBasket()->createClone();
        $fullBasket->clearCollection();
        $basketItem = $fullBasket->createItem('catalog', $productId);
        $basketItem->setFields([
            'QUANTITY' => 1,
            'CURRENCY' => \Bitrix\Currency\CurrencyManager::getBaseCurrency(),
            'LID' => $siteId,
            'PRODUCT_PROVIDER_CLASS' => '\Stroy\Checkout\Catalog\CatalogProductProvider'
        ]);

        unset($basketItem);

        if ($fullBasket->isEmpty()) {
            return $result;
        }

        // расчитаем скидки в системе Лоймакс
        $basketItems = $fullBasket->getBasketItems();
        try {
            $resCheckLines = $this->getCheckLines($basketItems);
            $resCalcPurchases = $this->api->calculateBonusesAndDiscounts($this->auth->getAccessToken(), $resCheckLines);
            $result = (float) $resCalcPurchases['bonusAmount'];
        } catch (\Exception $e) {
        }

        return $result;
    }
}
