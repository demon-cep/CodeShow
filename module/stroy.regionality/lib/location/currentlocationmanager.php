<?php

namespace Stroy\Regionality\Location;

use Bitrix\Catalog\GroupTable;
use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\StoreTable;
use Bitrix\Iblock\ElementPropertyTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Sale\Internals\StoreProductTable;
use Stroy\Core\Catalog\PriceTypesManager;
use Stroy\Core\Iblock\CityShops;
use Stroy\Core\Product\StoresManager;
use Stroy\Core\Sale\DeliverySystem;
use Stroy\Core\Sale\PaymentSystem;
use Stroy\Core\Site\SitesManager;
use Stroy\Regionality\Orm\GorodaTable;
use Stroy\Regionality\Orm\LocationsDataTable;

/**
 * Класс для работы с местоположением
 */
class CurrentLocationManager
{

    private static $cacheTtl = 3600 * 30 * 30; //Время жизни кеша запросов

    /**
     * Получить данные из таблицы stroy_regionality_locations_data [местоположение] по фильтру
     * @param array $arFilter
     * @param bool $fetchAll
     */
    public static function getLocationsDataList(array $arFilter = [], bool $fetchAll = false, array $excludedLocCodes = []): array
    {
        if (!$arFilter) {
            return [];
        }

        $arResult = [];

        if (!empty($excludedLocCodes)) {
            $arFilter['!UF_LOC_CODE'] = $excludedLocCodes;
        }

        $obLocationsData = LocationsDataTable::getlist([
            'order' => ['ID' => 'ASC'],
            'filter' => $arFilter,
            'select' => [
                'ID',
                'UF_NAME',
                'UF_LOC_CODE',
                'UF_LATITUDE',
                'UF_LONGITUDE',
                'SUBDOMAIN_ID' => 'SUBDOMAIN.ID',
                'SUBDOMAIN_NAME' => 'SUBDOMAIN.UF_NAME',
                'MAIN_CITY_ID' => 'MAIN_CITY.ID',
                'MAIN_CITY_OSNOVNOYSLKAD' => 'MAIN_CITY.UF_OSNOVNOYSLKAD',
                'MAIN_CITY_XML_ID' => 'MAIN_CITY.UF_XML_ID',
                'MAIN_CITY_UF_PRICE_TYPE_PREFIX' => 'MAIN_CITY.UF_PRICE_TYPE_PREFIX'
            ],
            'limit' => $fetchAll ? '' : 1
        ]);
        while ($arItem = $obLocationsData->fetch()) {
            $arResult[] = $arItem;
        }

        if ($arResult && !$fetchAll) {
            $arResult = current($arResult);
        }

        return $arResult;
    }

    /**
     * Получить данные из таблицы b_catalog_store [склады] по фильтру
     * @param array $arFilter
     * @param bool $fetchAll
     * @return array
     */
    public static function getCatalogStoreList(array $arFilter = [], bool $fetchAll = false): array
    {
        $arResult = [];

        $obStore = StoreTable::getList([
            'order' => ['ID' => 'ASC'],
            'filter' => $arFilter,
            'select' => [
                'ID',
                'XML_ID',
                'CODE',
                'TITLE',
                'ACTIVE',
                'GPS_N',
                'GPS_S',
                'DESCRIPTION',
                'ADDRESS',
                'PHONE',
                'SCHEDULE',
                'UF_CITY_XML_ID',
                'UF_PVZ_M'
            ],
            'limit' => $fetchAll ? '' : 1
        ]);
        while ($arItem = $obStore->Fetch()) {
            $arResult[] = $arItem;
        }

        if ($arResult && !$fetchAll) {
            $arResult = current($arResult);
        }

        return $arResult;
    }

    /**
     * Получить пвз для текущего города
     * @return array
     */
    public static function getPvzStores(): array
    {
        $arResult = [];
        $obLocation = CurrentLocation::getInstance();
        $cityXmlId = $obLocation->getMainCity()->getResult()->get('CITY_XML_ID');

        if ($cityXmlId) {
            $arResult = self::getCatalogStoreList(['ACTIVE' => 'Y', 'UF_CITY_XML_ID' => $cityXmlId, 'UF_PVZ_M' => 'Y'], true);
        }

        return $arResult;
    }

    /**
     * Возвращает список собственных складов населенного пункта
     * @return array
     * @throws Exception
     */
    public static function getOwnStores(): array
    {
        if (self::isLocationTypeMain()) {
            $cityXmlId = CurrentLocation::getInstance()->getMainCity()->getResult()->get('CITY_XML_ID');
            return StoresManager::getByCityXmlId([$cityXmlId]);
        }

        if (self::isLocationTypeSatellite()) {
            $cityXmlId = CurrentLocation::getInstance()->getSatelliteCity()->getResult()->get('XML_GOROD');
            return StoresManager::getByCityXmlId([$cityXmlId]);
        }

        return [];
    }

    /**
     * Получить данные из таблицы b_catalog_group [типы цен] по фильтру
     * @param array $arFilter
     * @param bool $fetchAll
     */
    public static function getCatalogGroupList(array $arFilter = [], bool $fetchAll = false): array
    {
        $arResult = [];

        $obCatalogGroup = GroupTable::getList([
            'order' => ['ID' => 'ASC'],
            'filter' => $arFilter,
            'select' => ['ID', 'NAME', 'BASE', 'XML_ID'],
            'limit' => $fetchAll ? '' : 1
        ]);
        while ($arItem = $obCatalogGroup->Fetch()) {
            $arResult[] = $arItem;
        }

        if ($arResult && !$fetchAll) {
            $arResult = current($arResult);
        }

        return $arResult;
    }

    /**
     * Получить остатки товаров по Id
     * @param array $productIds - id товаров
     * @param array $arParams - Массив параметров ['PRICE_TYPE' => 'moscow']
     * @return array
     */
    public static function getProductsStoreById(array $productIds = [], $arParams = []): array
    {
        if (!$productIds) {
            return [];
        }

        $arResult = [];

        /*
            Если город НЕприсутствия и НЕ спустник, то проверяем свойство недоступности доставки.
            Если недоступность доставки продукта установлена, то исключаем ID продукта (WEB-1230)
        */
        if (!self::isLocationTypeMain() && !self::isLocationTypeSatellite()) {
            $propNotDeliveredTK = PropertyTable::getList([
                'filter' => [
                    '=CODE' => 'IM_NEDOSTAVLYAETSYATK', //Свойство недоступности доставки через ТК
                    '=IBLOCK_ID' => IBLOCK_CAT
                ],
                'select' => ['ID'],
                'cache' => [
                    'ttl' => self::$cacheTtl,
                    'cache_joins' => true
                ],
                'limit' => 1
            ])->fetch();

            if (!empty($propNotDeliveredTK)) {
                $productsNotDeliveredTK = ElementPropertyTable::getList([
                    'filter' => [
                        '=IBLOCK_PROPERTY_ID' => $propNotDeliveredTK['ID'],
                        '=IBLOCK_ELEMENT_ID' => $productIds,
                        '=ENUM.XML_ID' => 'true'
                    ],
                    'select' => ['IBLOCK_ELEMENT_ID'],
                    'cache' => [
                        'ttl' => self::$cacheTtl,
                        'cache_joins' => true
                    ]
                ]);

                while ($productNotDeliveredTK = $productsNotDeliveredTK->fetch()) {
                    unset($productIds[array_search($productNotDeliveredTK['IBLOCK_ELEMENT_ID'], $productIds)]);
                }
            }
        }

        $obLocation = CurrentLocation::getInstance();
        $arNerestStores = $obLocation->getStore()->getResult()->get('NEAREST_STORES');
        $arStoreFilter = [
            '=PRODUCT_ID' => $productIds,
            '=STORE_ID' => $obLocation->getStore()->getResult()->get('STORES'),
            '>AMOUNT' => 0,
        ];
        $priceType = $obLocation->getStore()->getResult()->get('PRICE_TYPE');

        if ($arParams['PRICE_TYPE'] && $arNerestStores) {
            if ($arParams['PRICE_TYPE'] != $obLocation->getStore()->getResult()->get('PRICE_TYPE')) {
                foreach ($arNerestStores as $arStore) {
                    if ($arParams['PRICE_TYPE'] == $arStore['PRICE_TYPE']) {
                        $arStoreFilter['=STORE_ID'] = $arStore['ID'];
                        $priceType = $arStore['PRICE_TYPE'];
                        break;
                    }
                }
            }
        }

        #Наличие на складах основного города
        $obStoreProduct = StoreProductTable::getList([
            'order' => ['ID' => 'ASC'],
            'filter' => $arStoreFilter,
            'select' => ['*']
        ]);
        while ($arSPI = $obStoreProduct->Fetch()) {
            if (!$arResult[$arSPI['PRODUCT_ID']]) {
                $arResult[$arSPI['PRODUCT_ID']] = [
                    'PRODUCT_ID' => $arSPI['PRODUCT_ID'],
                    'PRICE_TYPE' => $priceType,
                    'STORES' => [$arSPI['STORE_ID']],
                    'STORES_AMOUNT' => [$arSPI['STORE_ID'] => $arSPI['AMOUNT']],
                    'AMOUNT' => $arSPI['AMOUNT']
                ];
            } else {
                if ($arResult[$arSPI['PRODUCT_ID']]['STORES'] && !in_array($arSPI['STORE_ID'], $arResult[$arSPI['PRODUCT_ID']]['STORES'])) {
                    $arResult[$arSPI['PRODUCT_ID']]['STORES'][] = $arSPI['STORE_ID'];
                    $arResult[$arSPI['PRODUCT_ID']]['STORES_AMOUNT'][$arSPI['STORE_ID']] = $arSPI['AMOUNT'];
                }
                $arResult[$arSPI['PRODUCT_ID']]['AMOUNT'] += $arSPI['AMOUNT'];
            }
        }

        //TODO отключаем поиск ближайшего склада
        if (
            !defined('EXCLUDED_LOCATION_STORES')
            || !is_array(EXCLUDED_LOCATION_STORES)
            || !in_array($obLocation->getMainCity()->getResult()->get('LOC_CODE'), EXCLUDED_LOCATION_STORES)
        ) {
            $arNerestStores = false;
        }

        #Поиск по складам других городов
        if ($arNerestStores) {
            $productEmptyIds = [];
            $arStores = [];

            foreach ($productIds as $id) {
                if ($arResult[$id]['AMOUNT'] <= 0) {
                    $productEmptyIds[] = $id;
                }
            }

            if ($productEmptyIds) {
                $obStoreProduct = StoreProductTable::getList([
                    'order' => ['ID' => 'ASC'],
                    'filter' => [
                        '=PRODUCT_ID' => $productEmptyIds,
                        '=STORE_ID' => array_column($arNerestStores, 'ID'),
                        '>AMOUNT' => 0
                    ],
                    'select' => ['*']
                ]);
                while ($arSPI = $obStoreProduct->Fetch()) {
                    $arStores[$arSPI['PRODUCT_ID']][$arSPI['STORE_ID']] = $arSPI['AMOUNT'];
                }

                foreach ($productEmptyIds as $id) {
                    foreach ($arNerestStores as $arStore) {
                        if ($arStores[$id][$arStore['ID']]) {
                            if (!$arResult[$id]) {
                                $arResult[$id] = [
                                    'PRODUCT_ID' => $id,
                                    'PRICE_TYPE' => $arStore['PRICE_TYPE'],
                                    'STORES' => [$arStore['ID']],
                                    'STORES_AMOUNT' => [$arStore['ID'] => $arStores[$id][$arStore['ID']]],
                                    'AMOUNT' => $arStores[$id][$arStore['ID']]
                                ];

                                #P.S. добавляем только первый склад с наличием
                                #Для получения всех складов убрать break
                                break;
                            } else {
                                if ($arResult[$id]['STORES'] && !in_array($arStore['ID'], $arResult[$id]['STORES'])) {
                                    $arResult[$id]['STORES'][] = $arStore['ID'];
                                    $arResult[$id]['STORES_AMOUNT'][$arStore['ID']] = $arStores[$id][$arStore['ID']];
                                }
                                $arResult[$id]['AMOUNT'] += $arStores[$id][$arStore['ID']];
                            }
                        }
                    }
                }
            }
        }

        return $arResult;
    }

    /**
     * Поиск соседних складов по наличию
     * Временный, скопирован из кода выше. Возможно будет не актуален
     */
    public static function getNearestStoreForUnavailableProducts(array $productIds): array
    {
        if (!$productIds) {
            return [];
        }

        $result = [];
        $location = CurrentLocation::getInstance();
        $arNerestStores = $location->getStore()->getResult()->get('NEAREST_STORES');
        $stores = [];
        $obStoreProduct = StoreProductTable::getList([
            'order' => ['ID' => 'ASC'],
            'filter' => [
                '=PRODUCT_ID' => $productIds,
                '=STORE_ID' => array_column($arNerestStores, 'ID'),
                '>AMOUNT' => 0
            ],
            'select' => ['*']
        ]);
        while ($arSPI = $obStoreProduct->Fetch()) {
            $stores[$arSPI['PRODUCT_ID']][$arSPI['STORE_ID']] = $arSPI['AMOUNT'];
        }
        foreach ($productIds as $id) {
            foreach ($arNerestStores as $store) {
                if ($stores[$id][$store['ID']]) {
                    if (!$result[$id]) {
                        $result[$id] = [
                            'PRODUCT_ID' => $id,
                            'PRICE_TYPE' => $store['PRICE_TYPE'],
                            'STORES' => [$store['ID']],
                            'STORES_AMOUNT' => [$store['ID'] => $stores[$id][$store['ID']]],
                            'AMOUNT' => $stores[$id][$store['ID']],
                            'NEAREST_STORE' => 'Y'
                        ];
                        break;
                    } else {
                        if ($result[$id]['STORES'] && !in_array($store['ID'], $result[$id]['STORES'])) {
                            $result[$id]['STORES'][] = $store['ID'];
                            $result[$id]['STORES_AMOUNT'][$store['ID']] = $stores[$id][$store['ID']];
                        }
                        $result[$id]['AMOUNT'] += $stores[$id][$store['ID']];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Получить цены товаров по складам
     * @param array $arStore
     * @param array $arParams - Массив параметров ['PRICE_TYPE' => [BASE_PRICE,DISCOUNT,BONUS,PRICE,OPT]]
     * @return array
     */
    public static function getProductsPriceByStore(array $arStore, $arParams = []): array
    {
        if (!$arStore) {
            return [];
        }

        $arResult = [];
        $obLocation = CurrentLocation::getInstance();
        $arLocationPrices = $obLocation->getPrice()->getResult()->get('PRICES');
        $arProductsPrice = [];
        $arFilterPrice = ['>PRICE' => 0, '=PRODUCT_ID' => array_column($arStore, 'PRODUCT_ID')];

        #Цены
        if ($arParams['PRICE_TYPE']) {
            foreach ($arStore as $arStoreItem) {
                if (!$arStoreItem['PRICE_TYPE'] || !$arLocationPrices[$arStoreItem['PRICE_TYPE']]) {
                    continue;
                }

                if (!$arFilterPrice['=CATALOG_GROUP_ID']) {
                    $arFilterPrice['=CATALOG_GROUP_ID'] = [];
                }

                foreach ($arLocationPrices[$arStoreItem['PRICE_TYPE']] as $key => $arPriceItem) {
                    if (!in_array($arPriceItem['ID'], $arFilterPrice['=CATALOG_GROUP_ID'])) {
                        if ($arParams['PRICE_TYPE'] && !in_array($key, $arParams['PRICE_TYPE'])) {
                            continue;
                        }

                        $arFilterPrice['=CATALOG_GROUP_ID'][] = $arPriceItem['ID'];
                    }
                }
            }
        }

        $obPrice = PriceTable::getList([
            'order' => ['ID' => 'ASC'],
            'filter' => $arFilterPrice,
            'select' => ['PRODUCT_ID', 'PRICE', 'CATALOG_GROUP_ID', 'CURRENCY']
        ]);
        while ($arPI = $obPrice->Fetch()) {
            $arProductsPrice[$arPI['PRODUCT_ID']][$arPI['CATALOG_GROUP_ID']] = $arPI;
        }

        #Заполним данные
        foreach ($arStore as $arItem) {
            if (!$arItem['PRICE_TYPE'] || !$arLocationPrices[$arItem['PRICE_TYPE']]) {
                continue;
            }

            $arLocationPrice = $arLocationPrices[$arItem['PRICE_TYPE']];
            $arProductPrice = $arProductsPrice[$arItem['PRODUCT_ID']];

            $arPrices = SitesManager::isRetail() ? $arProductPrice[$arLocationPrice['PRICE']['ID']] : $arProductPrice[$arLocationPrice['OPT']['ID']];
            $price = (float) $arPrices['PRICE'];
            $basePrice = (float) $arProductPrice[$arLocationPrice['BASE_PRICE']['ID']]['PRICE'];
            $discount = (float) $arProductPrice[$arLocationPrice['DISCOUNT']['ID']]['PRICE'];

            if ($arPrices) {
                if ($discount >= 100 || $discount == $price || $discount == $basePrice) {
                    $discount = 0;
                }

                $arResult[$arItem['PRODUCT_ID']] = $arPrices;
                $arResult[$arItem['PRODUCT_ID']]['BASE_PRICE'] = $arProductPrice[$arLocationPrice['BASE_PRICE']['ID']]['PRICE'] ?: 0;
                $arResult[$arItem['PRODUCT_ID']]['DISCOUNT'] = $discount;
                $arResult[$arItem['PRODUCT_ID']]['BONUS'] = $arProductPrice[$arLocationPrice['BONUS']['ID']]['PRICE'] ?: 0;
            }
        }

        return $arResult;
    }

    /**
     * Получить тип цены по списку ID цен
     * @param array $arPriceIds
     * @return void
     */
    public static function getPriceTypeByPriceId(array $arPriceIds): array
    {
        if (!$arPriceIds) {
            return [];
        }

        $arResult = [];
        $obLocation = CurrentLocation::getInstance();
        $arLocationPrices = $obLocation->getPrice()->getResult()->get('PRICES');

        foreach ($arLocationPrices as $typeId => $arType) {
            foreach ($arType as $arItem) {
                if (in_array($arItem['ID'], $arPriceIds)) {
                    $arResult = [
                        'TYPE' => $typeId,
                        'PRICE' => $arItem,
                        'PRICES' => $arType
                    ];
                    break 2;
                }
            }
        }

        return $arResult;
    }

    /**
     * Получить фильтр location для ИБ
     * $params[PROP_LOCATION] - Код свойство ИБ местоположение
     * $params[PROP_EXCLUDE_LOCATION] - код свойство ИБ исключить местоположение
     * @param array $filter - Массив фильтра
     * @param bool $withExclude - Добавить в условие исключение по городам, в ИБ должно существовать свойство EXCLUDE_LOCATION
     * @param array $params - Массив параметров [PROP_LOCATION | PROP_EXCLUDE_LOCATION]
     * @return array|array[]
     */
    public static function getLocationFilter(array $filter = [], bool $withExclude = true, array $params = []): array
    {
        $obLocation = CurrentLocation::getInstance();

        if (!$obLocation->getMainCity()->getLocationPath()) {
            return $filter;
        }

        if (empty($params['PROP_LOCATION'])) {
            $params['PROP_LOCATION'] = 'LOCATION';
        }

        $filter[] = [
            'LOGIC' => 'OR',
            ['=PROPERTY_' . $params['PROP_LOCATION'] => $obLocation->getMainCity()->getLocationPath()],
            ['=PROPERTY_' . $params['PROP_LOCATION'] => $obLocation->getSatelliteCity()->getResult()->get('GORODA_LOC_CODE')]
        ];

        if ($withExclude) {
            if (empty($params['PROP_EXCLUDE_LOCATION'])) {
                $params['PROP_EXCLUDE_LOCATION'] = 'EXCLUDE_LOCATION';
            }
            $excludeFilter = ['LOGIC' => 'AND'];
            foreach ($obLocation->getMainCity()->getLocationPath() as $locationCode) {
                $excludeFilter[]['!ID'] = \CIBlockElement::SubQuery(
                    'ID',
                    ['=PROPERTY_' . $params['PROP_EXCLUDE_LOCATION'] => $locationCode]
                );
            }
            $filter[] = $excludeFilter;
        }

        return $filter;
    }

    /**
     * Получить фильтр location для пользовательских полей
     * $arParams[PROP_LOCATION] - Код свойство ИБ местоположение
     * $arParams[PROP_EXCLUDE_LOCATION] - код свойство ИБ исключить местоположение
     * $arParams[TYPE] - Тип пользовательского поля [CAT_STORE | BANNER_CHOOSE_CITY]
     * @param array $arFilter - Массив фильтра
     * @param array $arParams - Массив параметров [PROP_LOCATION | PROP_EXCLUDE_LOCATION || TYPE]
     * @return array
     */
    public static function getUserTypeFilter(array $arFilter = [], array $arParams = []): array
    {
        $obLocation = CurrentLocation::getInstance();

        if (!$arParams['TYPE'] || !$obLocation->getMainCity()->getLocationPath()) {
            return $arFilter;
        }

        if (!$arParams['PROP_LOCATION']) {
            $arParams['PROP_LOCATION'] = 'UF_LOCATION';
        }
        if (!$arParams['PROP_EXCLUDE_LOCATION']) {
            $arParams['PROP_EXCLUDE_LOCATION'] = 'UF_EXCLUDE_LOCATION';
        }

        $arFilter = array_merge(['=TYPE_SID' => 'SIDE_RIGHT', '=FIRST_SITE_ID' => SITE_ID, '=ACTIVE' => 'Y', '=LAMP' => 'green'], $arFilter);

        $arNotElFilter = $arFilter + ['=' . $arParams['PROP_EXCLUDE_LOCATION'] => $obLocation->getMainCity()->getLocationPath()];
        $arElFilter = $arFilter + [[
                'LOGIC' => 'OR',
                ['=' . $arParams['PROP_LOCATION'] => $obLocation->getMainCity()->getLocationPath()],
                ['=' . $arParams['PROP_LOCATION'] => false]
            ]];

        if ($arParams['TYPE'] == 'CAT_STORE') {
            $obTable = StoreTable::getList(
                [
                    'order' => ['ID' => 'ASC'],
                    'filter' => $arNotElFilter,
                    'select' => ['ID', $arParams['PROP_EXCLUDE_LOCATION']]
                ]
            );
        }

        if ($obTable) {
            while ($arItem = $obTable->Fetch()) {
                if (array_key_exists($arParams['PROP_EXCLUDE_LOCATION'], $arItem)) {
                    $arElFilter['!ID'][] = $arItem['ID'];
                }
            }
        }

        return $arElFilter;
    }

    /**
     * Получить текущее время
     * @return \DateTime
     */
    public static function getCurrentTime(): \DateTime
    {
        $obLocation = CurrentLocation::getInstance();
        $timeZone = $obLocation->getMainCity()->getResult()->get('TIMEZONE') ?: 'Asia/Yekaterinburg';

        return new \DateTime("now", new \DateTimeZone($timeZone));
    }

    /**
     * Получить текущее TimeZone
     * @return string
     */
    public static function getCurrentTimeZone()
    {
        $obLocation = CurrentLocation::getInstance();
        $timeZone = $obLocation->getMainCity()->getResult()->get('TIMEZONE') ?: 'Asia/Yekaterinburg';

        return $timeZone;
    }

    /**
     * Получить тип местоположения
     * MAIN - основной город
     * LOCATION - текущее местоположение
     * @return string
     */
    public static function getLocationType()
    {
        $obLocation = CurrentLocation::getInstance();
        return $obLocation->getMainCity()->getResult()->get('TYPE');
    }

    /**
     * Проверить тип местоположения основной город
     * @return bool
     */
    public static function isLocationTypeMain(): bool
    {
        return (self::getLocationType() == 'MAIN');
    }

    /**
     * Проверить тип местоположения город-спутник
     * @return bool
     */
    public static function isLocationTypeSatellite(): bool
    {
        return (self::getLocationType() == 'SATELLITE');
    }

    /**
     * Проверить доступна ли Яндекс-доставка в населенном пункте
     * @return bool
     */
    public static function isYandexDeliveryAvailable(): bool
    {
        if (self::isLocationTypeMain()) {
            $obLocation = CurrentLocation::getInstance();

            return (bool)$obLocation->getMainCity()->getResult()->get('CITY_YANDEX_DELIVERY');
        }

        return false;
    }

    /**
     * Проверить наличие пвз для текущего города
     * @return bool
     */
    public static function isPvzmStores(): bool
    {
        return count(self::getPvzStores()) > 0;
    }

    /**
     * Возвращает XML_ID местоположения по id типа цены или null, если xmlId не найден
     * Данные метод полезен в определении местоположения товара в корзине
     * @param int $id ИД типа цены
     * @return string|null
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function getXmlIdByPriceTypeId(int $id): ?string
    {
        $keyMap = [];
        $dbPriceTypeList = GroupTable::query()
            ->where('ID', $id)
            ->setSelect(['ID', 'NAME'])
            ->fetchAll();

        foreach ($dbPriceTypeList as $priceType) {
            $priceTypePrefix = str_replace(PriceTypesManager::SUFFIX_LIST, '', $priceType['NAME']);
            $keyMap[$priceTypePrefix] = $priceType['ID'];
        }

        $location = GorodaTable::query()
            ->setSelect(['UF_XML_ID', 'UF_PRICE_TYPE_PREFIX'])
            ->whereIn('UF_PRICE_TYPE_PREFIX', array_keys($keyMap))
            ->setLimit(1)
            ->fetch();

        return $location['UF_XML_ID'];
    }

    public static function getDeliveryPrice(): string
    {
        $storeId = CurrentLocation::getInstance()->getStore()->getResult()?->get('NEAREST_STORES')[0]['XML_ID'];
        if (!empty($storeId)) {
            $location = GorodaTable::query()
                ->setSelect(['UF_TSENADOSTAVKI'])
                ->whereIn('UF_OSNOVNOYSLKAD', $storeId)
                ->setLimit(1)
                ->setCacheTtl(86400)
                ->cacheJoins(true)
                ->fetch();
            return $location['UF_TSENADOSTAVKI'];
        }

        return '0';
    }

    /**
     * Возвращает список XmlId городов по списку ID типа цен, в качестве ключей возвращаемого массива используется
     * id типа цены, в качестве значений xmlId города
     * @param array $typeIdList список id типов цен, для которых нужно вернуть xmlId городов
     * @return array
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     */
    public static function getXmlIdListByPriceTypes(array $typeIdList): array
    {
        $keyMap = [];
        $dbPriceTypeList = GroupTable::query()
            ->whereIn('ID', array_unique($typeIdList))
            ->setSelect(['ID', 'NAME'])
            ->fetchAll();

        foreach ($dbPriceTypeList as $priceType) {
            $priceTypePrefix = str_replace(PriceTypesManager::SUFFIX_LIST, '', $priceType['NAME']);
            $keyMap[$priceTypePrefix] = $priceType['ID'];
        }

        $locationList = GorodaTable::query()
            ->setSelect(['UF_XML_ID', 'UF_PRICE_TYPE_PREFIX'])
            ->whereIn('UF_PRICE_TYPE_PREFIX', array_keys($keyMap))
            ->fetchAll();

        $xmlIdList = [];
        foreach ($locationList as $location) {
            $xmlIdList[$keyMap[$location['UF_PRICE_TYPE_PREFIX']]] = $location['UF_XML_ID'];
        }

        return $xmlIdList;
    }

    /**
     * Возвращает список местоположений по списку ID цен
     * @param array $ids - Список id типов цен
     * @return array
     */
    public static function getLocationByPriceId(array $ids): array
    {
        $arResult = [];
        $arCatalogGroup = [];

        $obCatalogGroup = GroupTable::getList([
            'order' => ['ID' => 'ASC'],
            'filter' => ['=ID' => array_unique($ids)],
            'select' => ['ID', 'NAME']
        ]);
        while ($arItem = $obCatalogGroup->Fetch()) {
            $priceTypePrefix = str_replace(PriceTypesManager::SUFFIX_LIST, '', $arItem['NAME']);
            $arCatalogGroup[$priceTypePrefix] = $arItem['ID'];
        }

        $obCatalogGroup = GorodaTable::getList([
            'order' => ['ID' => 'ASC'],
            'filter' => ['=UF_PRICE_TYPE_PREFIX' => array_keys($arCatalogGroup)],
            'select' => ['UF_XML_ID', 'UF_PRICE_TYPE_PREFIX', 'UF_LOC_CODE']
        ]);
        while ($arItem = $obCatalogGroup->Fetch()) {
            $arResult[$arCatalogGroup[$arItem['UF_PRICE_TYPE_PREFIX']]] = $arItem;
        }

        return $arResult;
    }

    public static function getAllowedPaymentSystemsIdList(): array
    {
        return array_column(PaymentSystem::getActivePaymentSystems(), 'ID');
    }

    public static function getAllowedDeliverySystemsIdList(): array
    {
        return DeliverySystem::getActiveDeliveriesIds();
    }

    public static function isDeliveryAllowed()
    {
        $currentLocation = CurrentLocation::getInstance()->getMainCity()->getResult();
        return DeliverySystem::inCityZones(
            $currentLocation->get('NAME'),
            [$currentLocation->get('LATITUDE'), $currentLocation->get('LONGITUDE')]
        );
    }

    /**
     * Входит ли IP в массив внутренних IP (для магазинов ПВЗ-М)
     * @param string $ipAddr
     * @return bool
     */
    public static function isPvzIp(string $ipAddr): bool
    {
        if (!defined('IP_PVZ')) {
            return false;
        }

        $arPVZIp = explode(',', IP_PVZ);

        return in_array($ipAddr, $arPVZIp, true);
    }

    /**
     * Возвращает список ИД складов. Если город-спутник, добавляются также склады ПВЗМ
     * @return array
     * @throws Exception
     */
    public static function getStoresIdsWithSatellitesPvzmsIds(): array
    {
        $storeList = CurrentLocation::getInstance()->getStore()->getResult()->get('STORES');

        if (!self::isLocationTypeSatellite()) {
            return $storeList;
        }

        $currentLocation = CurrentLocation::getInstance()->getMainCity()->getResult();

        $cityStores = StoresManager::getById($storeList[0]);

        $cityStores = array_merge(
            $cityStores,
            StoresManager::getByCityXmlId([$currentLocation->get('CITY_XML_ID')])
        );

        return array_map(function ($store) {
            return (int)$store['ID'];
        }, $cityStores);
    }

    public static function getMainStoreId(): int
    {
        return CurrentLocation::getInstance()->getStore()->getResult()->get('STORES')[0];
    }

    /**
     * возвращает id типа цен для текущего местоположения
     * @return int
     */
    public static function getPriceTypeId(): int
    {
        return self::getPriceTypeIdByCode('PRICE');
    }

    /**
     * возвращает id базового типа цен для текущего местоположения
     * @return int
     */
    public static function getBasePriceTypeId(): int
    {
        return self::getPriceTypeIdByCode('BASE_PRICE');
    }

    /**
     * возвращает id типа цен для текущего местоположения
     * @param string $code - код типа цены
     * @return int
     */
    public static function getPriceTypeIdByCode(string $code): int
    {
        return CurrentLocation::getInstance()
            ->getPrice()
            ->getResult()
            ->get('PRICE')[$code]['ID'] ?? 0;
    }

    /**
     * Возвращает список с ИД платежных систем, разрешенных для населенного пункта
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function getAllowedPaymentSystemsIds(): array
    {
        if (!self::isLocationTypeMain()) {
            return array_merge(PaymentSystem::getOnlinePaymentsIds(), PaymentSystem::getInShopPaymentsIds());
        } else {
            $paymentSystemsIds = array_map(function ($paymentSystem) {
                return (int)$paymentSystem['ID'];
            }, PaymentSystem::getActivePaymentSystems());

            return array_values($paymentSystemsIds);
        }
    }

    /**
     * Возвращает результат проверки, доступна ли покупка в текущем городе присутствия
     */
    public static function isBuyAvailable(): bool
    {
        $cityData = self::getCityDataFromShopIblock();

        return !(bool)$cityData[CityShops::SECTION_UF_LOCK_BUY];
    }

    /**
     * Возвращает ИД платежных систем, заблокированных для города присутствия
     */
    public static function getDisabledDeliverySystemsIds(): array
    {
        $cityData = self::getCityDataFromShopIblock();

        return $cityData[CityShops::SECTION_UF_LOCK_SHIPMENT] ?? [];
    }

    /**
     * Возвращает минимально возможную сумму заказа по ИД службы доставки
     * @param int $deliveryServiceId
     * @return float
     */
    public static function getMinOrderSumByDeliveryId(int $deliveryServiceId): float
    {
        switch ($deliveryServiceId) {
            case DeliverySystem::isPickup($deliveryServiceId):
                return self::getMinOrderSumForPickup();
            case DeliverySystem::isDeliveryToHome($deliveryServiceId):
                return self::getMinOrderSumForDelivery();
            default:
                return 0;
        }
    }

    /**
     * Возвращает минимально возможную сумму заказа для самовывоза в текущем городе присутствия
     */
    public static function getMinOrderSumForPickup(): float
    {
        $cityData = self::getCityDataFromShopIblock();

        return (float)$cityData[CityShops::SECTION_UF_MIN_ORDER_PICKPOINT];
    }

    /**
     * Возвращает минимально возможную сумму заказа для доставки в текущем городе присутствия
     */
    public static function getMinOrderSumForDelivery(): float
    {
        $cityData = self::getCityDataFromShopIblock();

        return (float)$cityData[CityShops::SECTION_UF_MIN_ORDER_DELIVERY];
    }


    /**
     * Возвращает данные для города из инфоблока "Адреса магазинов"
     * @return array
     * @throws Exception
     */
    public static function getCityDataFromShopIblock(): array
    {
        return (new CityShops())->getCityData();
    }

    /**
     * Возвращает список ИД склада Наличия по городу
     * @return array
     * @throws \Exception
     */
    public static function getStoreCityAvaliableId()
    {
        $location = CurrentLocation::getInstance()->getMainCity()->getResult();
        $subdomain = $location->get('SUBDOMAIN');
        if (self::isLocationTypeSatellite()) {
            $arStore = StoresManager::getByXmlId($subdomain . "_all_avaliable");
        } else {
            if ($subdomain == "stroylandiya") {
                $cityName = "orenburg";
            } else {
                $cityName = $subdomain;
            }
            if (self::isLocationTypeMain()) {
                $arStore = StoresManager::getByXmlId($cityName . "_all_avaliable");
            } else {
                $arStore = StoresManager::getByXmlId($cityName . "_main_avaliable");
            }
        }

        if ($arStore[0]["ID"]) {
            return $arStore[0]["ID"];
        }

        return false;
    }


    /**
     * Проверяет отсутствие ограничений по местоположению для служб типа Доставка
     *
     * @return bool
     */
    public static function isDeliveryAvailable(): bool
    {
        $locationCode = CurrentLocation::getInstance()->getMainCity()->getResult()->get('LOC_CODE');
        return !empty(DeliverySystem::getAvailableDeliveryTypeServicesByLocation($locationCode));
    }

}
