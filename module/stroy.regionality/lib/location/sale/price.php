<?php

namespace Stroy\Regionality\Location\Sale;

use Bitrix\Main\Data\Cache;
use Stroy\Regionality\Location\CurrentLocationManager,
    Stroy\Regionality\Handler;

/**
 * Класс для работы с ценами в MainCity основном городе
 */
class Price extends Base
{
    /** @var string $cacheKey - ключ кеширования доступен для получения внутри класса */
    protected string $cacheKey = 'PRICE';

    /** @var string $priceSuffix - Основная цена с учётом скидок */
    protected string $priceSuffix = 'skidkaonline';

    /** @var string $priceBaseSuffix - Базовая цена */
    protected string $priceBaseSuffix = 'tsena';

    /** @var string $priceDiscountSuffix - Процент скидки от 0-100% разница между tsena и skidkaonline */
    protected string $priceDiscountSuffix = 'skidka';

    /** @var string $priceBonusSuffix - Сумма бонусов начисляемых после покупки товаров */
    protected string $priceBonusSuffix = 'bonus';

    /** @var string $priceOpSuffix - Оптовая цена ссылка вида almetyevsk.opt.stroylandiya.ru */
    protected string $priceOpSuffix = 'str3';

    /**
     * Init
     * @return void
     */
    public function init(): void
    {
        // чистим старый функционал хранения цен у пользователей
        unset($_SESSION['STROYLANDIYA'][$this->cacheKey]);
        $result = [];
        $cache = Cache::createInstance();
        if ($cache->initCache(60 * 60 * 24, $this->cacheKey . '_' . $this->config->get('LOCATION_CODE'))) {
            $result = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            if ($this->config->get('LOCATION_CODE')) {
                $result = $this->getPriceByCode();
            } else {
                $result = $this->getPriceDefault();
            }
            $cache->endDataCache($result);
        }

        $this->result->resetValues($result);
    }

    /**
     * Сброс всех данных
     * @return void
     */
    public function reset(): void
    {
        $this->getResult()->clear();
        $this->getConfig()->clear();
    }

    /**
     * Получить цены по LOCATION_CODE
     * @return array
     */
    protected function getPriceByCode(): array
    {
        $arResult = [];

        if (
            $this->getConfig()->get('LOCATION_CODE') &&
            $this->getConfig()->get('LOCATION_CODE') != $this->getResult()->get('LOC_CODE')
        ) {
            #Поиск местоположения
            #UF_LOC_CODE - Код местоположения
            #UF_MAIN_CITY_SUBDOMAIN - Основной город для поддомена
            #MAIN_CITY_OSNOVNOYSLKAD - Связь 1:1 с таблицей b_catalog_store XML_ID
            #MAIN_CITY_XML_ID - Связь 1:мн таблица b_catalog_store UF_CITY_XML_ID
            #MAIN_CITY_UF_PRICE_TYPE_PREFIX - Тип цены
            $arLocationsData = CurrentLocationManager::getLocationsDataList([
                array_merge(['UF_LOC_CODE' => $this->getConfig()->get('LOCATION_CODE')], $this->mainCityFilter)
            ], false, $this->excludedLocCodes);

            #Если местоположение не найдено, ищем ближайшее
            if (!$arLocationsData) {
                $arLocationsData = CurrentLocationManager::getLocationsDataList(['UF_LOC_CODE' => $this->getConfig()->get('LOCATION_CODE')], false);
                $arLocationsData = $this->getNearestCity($arLocationsData['UF_LATITUDE'], $arLocationsData['UF_LONGITUDE']);
            }

            if ($arLocationsData) {
                $arPrices = [];
                $arCatalogStore = CurrentLocationManager::getLocationsDataList($this->mainCityFilter, true);
                $arCatalogGroup = CurrentLocationManager::getCatalogGroupList([], true);

                foreach ($arCatalogStore as $arStore) {
                    $priceType = $arStore['MAIN_CITY_UF_PRICE_TYPE_PREFIX'];

                    #BASE_PRICE [tsena] - базовая цена
                    #PRICE [skidkaonline] - Основная цена
                    #DISCOUNT [skidka] - Процент скидки от 0-100% разница между tsena и skidkaonline
                    #BONUS [bonus] - Сумма бонусов начисляемых после покупки товаров
                    #OPT [str3] - Оптовая цена ссылка вида almetyevsk.opt.stroylandiya.ru
                    foreach ($arCatalogGroup as $arGroup) {
                        if (mb_strripos($arGroup['NAME'], $priceType) !== false) {
                            if ($arGroup['NAME'] == $priceType . $this->priceSuffix && !$arPrices[$priceType]['PRICE']) {
                                $arPrices[$priceType]['PRICE'] = ['ID' => $arGroup['ID'], 'NAME' => $arGroup['NAME']];
                            } else if ($arGroup['NAME'] == $priceType . $this->priceBaseSuffix && !$arPrices[$priceType]['BASE_PRICE']) {
                                $arPrices[$priceType]['BASE_PRICE'] = ['ID' => $arGroup['ID'], 'NAME' => $arGroup['NAME']];
                            } else if ($arGroup['NAME'] == $priceType . $this->priceDiscountSuffix && !$arPrices[$priceType]['DISCOUNT']) {
                                $arPrices[$priceType]['DISCOUNT'] = ['ID' => $arGroup['ID'], 'NAME' => $arGroup['NAME']];
                            } else if ($arGroup['NAME'] == $priceType . $this->priceBonusSuffix && !$arPrices[$priceType]['BONUS']) {
                                $arPrices[$priceType]['BONUS'] = ['ID' => $arGroup['ID'], 'NAME' => $arGroup['NAME']];
                            } else if ($arGroup['NAME'] == $priceType . $this->priceOpSuffix && !$arPrices[$priceType]['OPT']) {
                                $arPrices[$priceType]['OPT'] = ['ID' => $arGroup['ID'], 'NAME' => $arGroup['NAME']];
                            }
                        }
                    }
                }

                if ($arPrices[$arLocationsData['MAIN_CITY_UF_PRICE_TYPE_PREFIX']]) {
                    $arResult['NAME'] = $arLocationsData['UF_NAME'];
                    $arResult['LOC_CODE'] = $arLocationsData['UF_LOC_CODE'];
                    $arResult['PRICE_TYPE'] = $arLocationsData['MAIN_CITY_UF_PRICE_TYPE_PREFIX'];
                    $arResult['PRICE'] = $arPrices[$arLocationsData['MAIN_CITY_UF_PRICE_TYPE_PREFIX']];
                    $arResult['PRICES'] = $arPrices;
                }
            }

            if (!$arResult) {
                $arResult = $this->getPriceDefault();
            }
        }

        return $arResult;
    }

    /**
     * Получить цены по умолчанию
     * @return array
     */
    protected function getPriceDefault(): array
    {
        $arResult = [];

        $arLocationsData = CurrentLocationManager::getLocationsDataList(['UF_LOC_CODE' => \Stroy\Regionality\Location\CurrentLocation::DEFAULT_LOCATION['LOCATION_CODE']], false);
        if ($arLocationsData) {
            $arPrices = [];
            $arCatalogStore = CurrentLocationManager::getLocationsDataList($this->mainCityFilter, true);
            $arCatalogGroup = CurrentLocationManager::getCatalogGroupList([], true);

            foreach ($arCatalogStore as $arStore) {
                $priceType = $arStore['MAIN_CITY_UF_PRICE_TYPE_PREFIX'];

                #BASE_PRICE [tsena] - базовая цена
                #PRICE [skidkaonline] - Основная цена
                #DISCOUNT [skidka] - Процент скидки от 0-100% разница между tsena и skidkaonline
                #BONUS [bonus] - Сумма бонусов начисляемых после покупки товаров
                #OPT [str3] - Оптовая цена ссылка вида almetyevsk.opt.stroylandiya.ru
                foreach ($arCatalogGroup as $arGroup) {
                    if (mb_strripos($arGroup['NAME'], $priceType) !== false) {
                        if ($arGroup['NAME'] == $priceType . $this->priceSuffix && !$arPrices[$priceType]['PRICE']) {
                            $arPrices[$priceType]['PRICE'] = ['ID' => $arGroup['ID'], 'NAME' => $arGroup['NAME']];
                        } else if ($arGroup['NAME'] == $priceType . $this->priceBaseSuffix && !$arPrices[$priceType]['BASE_PRICE']) {
                            $arPrices[$priceType]['BASE_PRICE'] = ['ID' => $arGroup['ID'], 'NAME' => $arGroup['NAME']];
                        } else if ($arGroup['NAME'] == $priceType . $this->priceDiscountSuffix && !$arPrices[$priceType]['DISCOUNT']) {
                            $arPrices[$priceType]['DISCOUNT'] = ['ID' => $arGroup['ID'], 'NAME' => $arGroup['NAME']];
                        } else if ($arGroup['NAME'] == $priceType . $this->priceBonusSuffix && !$arPrices[$priceType]['BONUS']) {
                            $arPrices[$priceType]['BONUS'] = ['ID' => $arGroup['ID'], 'NAME' => $arGroup['NAME']];
                        } else if ($arGroup['NAME'] == $priceType . $this->priceOpSuffix && !$arPrices[$priceType]['OPT']) {
                            $arPrices[$priceType]['OPT'] = ['ID' => $arGroup['ID'], 'NAME' => $arGroup['NAME']];
                        }
                    }
                }
            }

            if ($arPrices[$arLocationsData['MAIN_CITY_UF_PRICE_TYPE_PREFIX']]) {
                $arResult['NAME'] = $arLocationsData['UF_NAME'];
                $arResult['LOC_CODE'] = $arLocationsData['UF_LOC_CODE'];
                $arResult['PRICE_TYPE'] = $arLocationsData['MAIN_CITY_UF_PRICE_TYPE_PREFIX'];
                $arResult['PRICE'] = $arPrices[$arLocationsData['MAIN_CITY_UF_PRICE_TYPE_PREFIX']];
                $arResult['PRICES'] = $arPrices;
            }
        }

        return $arResult;
    }
}
