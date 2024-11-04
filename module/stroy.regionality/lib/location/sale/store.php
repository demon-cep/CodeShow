<?php

namespace Stroy\Regionality\Location\Sale;

use Bitrix\Main\Data\Cache;
use Stroy\Regionality\Location\CurrentLocationManager,
    Stroy\Regionality\Handler;

/**
 * Класс для работы с остатками и складами в MainCity основном городе
 */
class Store extends Base
{
    /** @var string $cacheKey - ключ кеширования доступен для получения внутри класса */
    protected string $cacheKey = 'STORE';

    /**
     * Init
     * @return void
     */
    public function init(): void
    {
        // чистим старый функционал хранения складов у пользователей
        unset($_SESSION['STROYLANDIYA'][$this->cacheKey]);
        $result = [];
        $cache = Cache::createInstance();
        if ($cache->initCache(60 * 60 * 24, $this->cacheKey . '_' . $this->config->get('LOCATION_CODE'))) {
            $result = $cache->getVars();
        } elseif ($cache->startDataCache()) {
            if ($this->config->get('LOCATION_CODE')) {
                $result = $this->getStoreByCode();
            } else {
                $result = $this->getStoreDefault();
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
     * Получить склад по LOCATION_CODE
     * @return array
     */
    protected function getStoreByCode(): array
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
                $arCatalogStore = [];
                if ($arLocationsData['MAIN_CITY_XML_ID']) {
                    $arCatalogStore['STORES'] = CurrentLocationManager::getCatalogStoreList(['=UF_CITY_XML_ID' => $arLocationsData['MAIN_CITY_XML_ID'], 'ACTIVE' => 'Y'], true);
                } else {
                    $arCatalogStore['STORES'] = CurrentLocationManager::getCatalogStoreList(['=XML_ID' => $arLocationsData['MAIN_CITY_OSNOVNOYSLKAD'], 'ACTIVE' => 'Y'], true);
                }

                #Сортировка основной склад города первым
                if ($arCatalogStore['STORES'] && $arLocationsData['MAIN_CITY_OSNOVNOYSLKAD']) {
                    uasort($arCatalogStore['STORES'], function ($a, $b) use ($arLocationsData) {
                        return ($a['XML_ID'] == $arLocationsData['MAIN_CITY_OSNOVNOYSLKAD']) ? -1 : 1;
                    });

                    $arCatalogStore['STORES'] = array_values($arCatalogStore['STORES']);
                }

                $arCatalogStore['NEAREST_STORES'] = $this->getNearestStores($arLocationsData['UF_LATITUDE'], $arLocationsData['UF_LONGITUDE']);

                $arResult = [
                    'NAME' => $arLocationsData['UF_NAME'],
                    'LOC_CODE' => $arLocationsData['UF_LOC_CODE'],
                    'PRICE_TYPE' => $arLocationsData['MAIN_CITY_UF_PRICE_TYPE_PREFIX'],
                    'STORES' => array_column($arCatalogStore['STORES'], 'ID'),
                    'NEAREST_STORES' => $arCatalogStore['NEAREST_STORES']
                ];
            }

            if (!$arResult) {
                $arResult = $this->getStoreDefault();
            }
        }

        return $arResult;
    }

    /**
     * Получить склад по умолчанию
     * @return array
     */
    protected function getStoreDefault(): array
    {
        $arResult = [];

        $arLocationsData = CurrentLocationManager::getLocationsDataList(['UF_LOC_CODE' => \Stroy\Regionality\Location\CurrentLocation::DEFAULT_LOCATION['LOCATION_CODE']], false);
        if ($arLocationsData && $arLocationsData['MAIN_CITY_OSNOVNOYSLKAD']) {
            $arCatalogStore = [];
            $arCatalogStore['STORES'] = CurrentLocationManager::getCatalogStoreList(['XML_ID' => $arLocationsData['MAIN_CITY_OSNOVNOYSLKAD'], 'ACTIVE' => 'Y'], true);
            $arCatalogStore['NEAREST_STORES'] = $this->getNearestStores($arLocationsData['UF_LATITUDE'], $arLocationsData['UF_LONGITUDE']);
        }

        if ($arLocationsData && $arCatalogStore) {
            $arResult = [
                'NAME' => $arLocationsData['UF_NAME'],
                'LOC_CODE' => $arLocationsData['UF_LOC_CODE'],
                'PRICE_TYPE' => $arLocationsData['MAIN_CITY_UF_PRICE_TYPE_PREFIX'],
                'STORES' => array_column($arCatalogStore['STORES'], 'ID'),
                'NEAREST_STORES' => $arCatalogStore['NEAREST_STORES']
            ];
        }

        return $arResult;
    }

    /**
     * Вычисляем Ближайшие склады
     * @return array
     */
    protected function getNearestStores($latitude, $longitude): array
    {
        $arResult = [];

        $arLocationsData = CurrentLocationManager::getLocationsDataList($this->mainCityFilter, true);

        if ($arLocationsData) {
            #Получить склады по фильтру
            $arCatalogStore = CurrentLocationManager::getCatalogStoreList(['XML_ID' => array_column($arLocationsData, 'MAIN_CITY_OSNOVNOYSLKAD'), 'ACTIVE' => 'Y'], true);
            if ($arCatalogStore) {
                foreach ($arCatalogStore as $key => $arItem) {
                    unset($arCatalogStore[$key]);
                    $arCatalogStore[$arItem['XML_ID']] = $arItem;
                }
            }

            #Поиск ближайших точек
            foreach ($arLocationsData as $arItem) {
                if ($arItem['UF_LATITUDE'] && $arItem['UF_LONGITUDE'] && $arCatalogStore[$arItem['MAIN_CITY_OSNOVNOYSLKAD']]) {
                    $arItem['MAIN_CITY_OSNOVNOYSLKAD_ID'] = $arCatalogStore[$arItem['MAIN_CITY_OSNOVNOYSLKAD']]['ID'];
                    $matResult['ITEMS'][$arItem['ID']] = $arItem;
                    #Формула вычисления длины от точки А до точки Б
                    //$matResult['DOT'][$arItem['ID']] = \Stroy\Regionality\Services\GeoDistance\Base::calculateNearestDot(
                    #Формула вычисления длины в метрах
                    $matResult['DOTS'][$arItem['ID']] = \Stroy\Regionality\Services\GeoDistance\Base::calculateDistanceMeters(
                        [$arItem['UF_LATITUDE'], $arItem['UF_LONGITUDE']],
                        [$latitude, $longitude]
                    );
                }
            }

            #Сортировка точек по возрастанию
            asort($matResult['DOTS']);

            #Собираем result массив
            foreach ($matResult['DOTS'] as $id => $item) {
                if ($matResult['ITEMS'][$id]) {
                    $arResult[] = [
                        'ID' => $matResult['ITEMS'][$id]['MAIN_CITY_OSNOVNOYSLKAD_ID'],
                        'XML_ID' => $matResult['ITEMS'][$id]['MAIN_CITY_OSNOVNOYSLKAD'],
                        'NAME' => $matResult['ITEMS'][$id]['UF_NAME'],
                        'PRICE_TYPE' => $matResult['ITEMS'][$id]['MAIN_CITY_UF_PRICE_TYPE_PREFIX'],
                    ];
                }
            }
        }

        return $arResult;
    }
}
