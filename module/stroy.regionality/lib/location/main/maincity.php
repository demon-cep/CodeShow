<?php

namespace Stroy\Regionality\Location\Main;

use Stroy\Regionality\Services\Crawlerdetect\CrawlerDetect,
    Stroy\Regionality\Location\CurrentLocation,
    Stroy\Regionality\Handler;

/**
 * Класс для работы с основным городом у которого есть магазины [b_gorda]
 */
class MainCity extends Base
{
    /** @var string $cacheKey - ключ кеширования доступен для получения внутри класса */
    protected string $cacheKey = 'MAIN_CITY';

    /**
     * Init
     * @return void
     */
    public function init(): void
    {
        $obCrawlerDetect = new CrawlerDetect;

        if ($obCrawlerDetect->isCrawler()) {
            $arResult = $this->getLocationDefaultByDomain();
            $this->getMapResult($arResult);
        } else {
            if ($this->isCache()) {
                $arResult = $this->getCache();
            } else {
                if ($this->config->get('LATITUDE') && $this->config->get('LONGITUDE')) {
                    $arResult = $this->getNearestDot($this->config->get('LATITUDE'), $this->config->get('LONGITUDE'));
                } else if ($this->config->get('LOCATION_CODE')) {
                    $arResult = $this->getLocationByCode();
                } else {
                    $arResult = $this->getLocationByDomain();
                }

                #Class голосует за сохранение данных
                $this->config->set('SAVE', true);

                #Показываем Popup подтверждения города
                $_SESSION['STROYLANDIYA']['POPUP']['LOCATION'] = 'Y';
            }
        }

        if ($arResult) {
            $this->result->resetValues($arResult);
        }
    }

    /**
     * Вычисляем Ближайшую точку
     * @return array
     */
    protected function getNearestDot($latitude, $longitude): array
    {
        $arResult = [];

        $arLocations = \Stroy\Regionality\Orm\LocationsDataTable::getExList(['UF_MAIN_CITY_SUBDOMAIN' => 1], true);
        if ($arLocations) {
            foreach ($arLocations as $arItem) {
                if ($arItem['UF_LATITUDE'] && $arItem['UF_LONGITUDE']) {
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

            #Вычисляем минимальное значение, то есть ближайшую точку к нам
            $dotMinIndex = array_search(min($matResult['DOTS']), $matResult['DOTS']);

            $arResult = $matResult['ITEMS'][$dotMinIndex];
            if ($arResult) {
                $this->getPrepareResult($arResult);
                $this->getMapResult($arResult);
            }
        }

        return $arResult;
    }

    /**
     * Получить город по LOCATION_CODE
     * @return array
     */
    protected function getLocationByCode(): array
    {
        $arResult = [];

        if (
            $this->getConfig()->get('LOCATION_CODE') &&
            $this->getConfig()->get('LOCATION_CODE') != $this->getResult()->get('LOC_CODE')
        ) {
            $arResult = \Stroy\Regionality\Orm\LocationsDataTable::getExList(['UF_LOC_CODE' => $this->getConfig()->get('LOCATION_CODE')], false);
            if ($arResult) {
                $this->getPrepareResult($arResult);
                $this->getMapResult($arResult);
            }
        }

        return $arResult;
    }

    /**
     * Получить город по domain
     * @return array
     */
    protected function getLocationByDomain(): array
    {
        $arResult = [];

        if ($this->config->get('DOMAIN')) {
            $domain = $this->config->get('DOMAIN');
        } else {
            $requestHelper = new \Stroy\Regionality\Helpers\Request();
            $domain = $requestHelper->getSubDomain();
        }

        if ($domain) {
            $resLocation = \Stroy\Regionality\Orm\LocationsDataTable::getByDomain($domain);
            if ($resLocation) {
                $arResult = \Stroy\Regionality\Orm\LocationsDataTable::getExList(['ID' => $resLocation['ID']], false);
            }
        }

        if (!$arResult) {
            $arResult = $this->getLocationDefaultByDomain();
            if ($arResult['SUBDOMAIN_NAME'] != $domain) {
                $arResult['SUBDOMAIN_NAME'] = $domain;
            }
        }

        if ($arResult) {
            $this->getPrepareResult($arResult);
            $this->getMapResult($arResult);
        }

        return $arResult;
    }

    /**
     * Получить город по умолчанию
     */
    protected function getLocationDefaultByDomain()
    {
        $arResult = \Stroy\Regionality\Orm\LocationsDataTable::getExList(['UF_LOC_CODE' => CurrentLocation::DEFAULT_LOCATION['LOCATION_CODE']], false);
        if ($arResult) {
            $this->getPrepareResult($arResult);
            $this->clearLocationList($arResult);
        }

        return $arResult;
    }

    /**
     * Очистить лишние поля массива
     * @param array $arData
     * @return void
     */
    protected function clearLocationList(array &$arData): void
    {
        $arFields = ['UF_SUBDOMAIN', 'SUBDOMAIN_ID', 'LOCATION_ID', 'LOCATION_CODE', 'LOCATION_LEFT_MARGIN', 'LOCATION_RIGHT_MARGIN', 'LOCATION_TYPE_ID'];
        foreach ($arFields as $item) {
            if (array_key_exists($item, $arData)) {
                unset($arData[$item]);
            }
        }
    }

    /**
     * @brief Подготовить массив $arData
     * @param array $arData
     * @return void
     */
    protected function getPrepareResult(array &$arData): void
    {
        if (!$arData['UF_PHONE'] && $arData['LOCATION_PATH']) {
            $arData['UF_PHONE'] = $this->getPhoneByLocation(array_column($arData['LOCATION_PATH'], 'CODE'));
        }

        #Тип местоположения
        #MAIN - основной город
        #LOCATION - текущее местоположение
        $arData['CUSTOM_TYPE'] = 'LOCATION';
        if (!empty($arData['CITY_XML_ID'])) {
            $arData['CUSTOM_TYPE'] = 'MAIN';
        } elseif (!empty($arData['SATELLITE_CITY_XML_ID'])) {
            $arData['CUSTOM_TYPE'] = 'SATELLITE';

            $arGorodaSputniki = \Stroy\Regionality\Orm\GorodaSputnikiTable::getList([
                'order' => ['ID' => 'ASC'],
                'filter' => ['=UF_XML_ID' => $arData['SATELLITE_CITY_XML_ID']],
                'select' => ['ID','UF_XML_ID','UF_XML_GOROD','GORODA_FIAS_ID' => 'GORODA.UF_FIAS_ID']
            ])->Fetch();
            if (!$arGorodaSputniki || !$arGorodaSputniki['GORODA_FIAS_ID']) {
                $arData['CUSTOM_TYPE'] = 'LOCATION';
            }
        }
    }

    /**
     * @brief Конвертировать result, map
     * @param array $arData
     * @return void
     */
    protected function getMapResult(array &$arData): void
    {
        $arResult = [
            'ID' => $arData['ID'],
            'NAME' => $arData['UF_NAME'],
            'TYPE_OF_LOCALITY' => $arData['UF_PARAMETER']['type'] ?? '',
            'LOC_CODE' => $arData['UF_LOC_CODE'],
            'FIAS_ID' => $arData['UF_FIAS_ID'],
            'ZIP_CODE' => $arData['UF_ZIP_CODE'],
            'LATITUDE' => $arData['UF_LATITUDE'],
            'LONGITUDE' => $arData['UF_LONGITUDE'],
            'PHONE' => $arData['UF_PHONE'],
            'TIMEZONE' => $arData['UF_TIMEZONE'] ?: 'Asia/Yekaterinburg',
            'SUBDOMAIN' => $arData['SUBDOMAIN_NAME'],
            'TYPE' => $arData['CUSTOM_TYPE'],
            'CITY_YANDEX_DELIVERY' => $arData['CITY_YANDEXDELIVERYAVA'],
            'CITY_XML_ID' => $arData['CITY_XML_ID'],
            'SATELLITE_CITY_XML_ID' => $arData['SATELLITE_CITY_XML_ID'],
            'DECLENSIONS' => $arData['UF_DECLENSIONS'],
            'LOCATION_PATH' => $arData['LOCATION_PATH']
        ];

        $arData = $arResult;
    }

    /**
     * Проверить наличие кеша
     * @return bool
     */
    protected function isCache(): bool
    {
        $cache = $this->getCache();

        #Сбрасываем кеш если одно из полей отсутствует
        if ($cache && !Handler::checkFields($cache, ['ID', 'NAME', 'LOC_CODE', 'FIAS_ID', 'LATITUDE', 'LONGITUDE', 'LOCATION_PATH', 'PHONE'])) {
            $cache = false;
        }

        $requestHelper = new \Stroy\Regionality\Helpers\Request();
        #Сбрасываем кеш если сменился домен
        if ($cache && !$requestHelper->isMobileRestapi() && !$requestHelper->checkSubDomain($cache['SUBDOMAIN'])) {
            $cache = false;
        }

        return (bool)$cache;
    }

    /**
     * Получить полный путь местопложения
     * @return array
     */
    public function getLocationPath(): array
    {
        $arResult = [];

        if ($this->getResult()->get('LOCATION_PATH'))
            $arResult = array_column($this->getResult()->get('LOCATION_PATH'), 'CODE');

        return $arResult;
    }

    /**
     * Рассчитать данные для основного города
     * @param string $locationCode
     * @return array
     */
    public function getCalcLocation(string $locationCode = ''): array
    {
        if (!$locationCode) {
            return [];
        }

        $arResult = \Stroy\Regionality\Orm\LocationsDataTable::getExList(['UF_LOC_CODE' => $locationCode], false);
        if (!$arResult) {
            return [];
        }

        #Если выбрали город по умолчанию
        if ($arResult['UF_LOC_CODE'] == \Stroy\Regionality\Location\CurrentLocation::DEFAULT_LOCATION['LOCATION_CODE']) {
            $arResult['UF_SUBDOMAIN'] = '';
            $arResult['SUBDOMAIN_ID'] = '';
            $arResult['SUBDOMAIN_NAME'] = '';
        } else {
            #Если домен не установлен, поиск домена у родителей location
            if (!$arResult['UF_SUBDOMAIN'] && $arResult['LOCATION_PATH']) {
                $arLocationsData = \Stroy\Regionality\Orm\LocationsDataTable::getList([
                    'order' => ['LOCATION_TYPE_ID' => 'DESC'],
                    'filter' => [
                        '=UF_LOC_CODE' => array_column($arResult['LOCATION_PATH'], 'CODE'),
                        '!UF_SUBDOMAIN' => false],
                    'select' => [
                        'ID', 'UF_NAME', 'UF_LOC_CODE', 'UF_SUBDOMAIN',
                        'LOCATION_TYPE_ID' => 'LOCATION.TYPE_ID',
                        'SUBDOMAIN_ID' => 'SUBDOMAIN.ID',
                        'SUBDOMAIN_NAME' => 'SUBDOMAIN.UF_NAME'
                    ]
                ])->fetch();

                if ($arLocationsData['SUBDOMAIN_NAME']) {
                    $arResult['UF_SUBDOMAIN'] = $arLocationsData['SUBDOMAIN_ID'];
                    $arResult['SUBDOMAIN_ID'] = $arLocationsData['SUBDOMAIN_ID'];
                    $arResult['SUBDOMAIN_NAME'] = $arLocationsData['SUBDOMAIN_NAME'];
                }
            }
        }

        if ($arResult) {
            $this->getPrepareResult($arResult);
            $this->getMapResult($arResult);
        }

        // Отключим редиректы для определённых суб.доменов
        $requestHelper = new \Stroy\Regionality\Helpers\Request();
        $subDomain = $requestHelper->getSubDomain();
        if (in_array($subDomain, ['pwa'])) {
            $obLocation = \Stroy\Regionality\Location\CurrentLocation::getInstance();
            $arResult['SUBDOMAIN'] = $obLocation->getMainCity()->getResult()->get('SUBDOMAIN');
        }

        return $arResult;
    }

    /**
     * Поиск телефона по списку LocCode местоположений
     * @param array $arLocations
     * @return string
     */
    protected function getPhoneByLocation(array $arLocations = []): string
    {
        $result = \Stroy\Regionality\Location\CurrentLocation::DEFAULT_LOCATION['LOCATION_PHONE'];
        $resLocations = [];

        if (!$arLocations) {
            return $result;
        }

        $obLocations = \Stroy\Regionality\Orm\LocationsDataTable::getlist([
            'order' => ['ID' => 'ASC'],
            'filter' => ['UF_LOC_CODE' => $arLocations],
            'select' => ['ID', 'UF_NAME', 'UF_LOC_CODE', 'UF_PHONE']
        ]);
        while ($arItem = $obLocations->fetch()) {
            if ($arItem['UF_PHONE']) {
                $resLocations[$arItem['UF_LOC_CODE']] = $arItem['UF_PHONE'];
            }
        }

        if ($resLocations) {
            foreach ($arLocations as $item) {
                if ($resLocations[$item]) {
                    $result = $resLocations[$item];
                    break;
                }
            }
        }

        return $result;
    }
}
