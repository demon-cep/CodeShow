<?php

namespace Stroy\Regionality\Location\Main;

use Stroy\Regionality\Handler;

/**
 * Класс для работы с текущим местоположением
 */
class Location extends Base
{
    /** @var string $cacheKey - ключ кеширования доступен для получения внутри класса */
    protected string $cacheKey = 'LOCATION';

    /**
     * Init
     * @return void
     */
    public function init(): void
    {
        if ($this->isCache()) {
            $arResult = $this->getCache();
        } else {
            $arResult = $this->getLocation();

            #Class голосует за сохранение данных
            $this->config->set('SAVE', true);
        }

        if ($arResult) {
            $this->result->resetValues($arResult);
        }
    }

    /**
     * Текущее местоположение пользователя
     * @return array
     */
    public function getLocation(): array
    {
        $ip = \Bitrix\Main\Service\GeoIp\Manager::getRealIp();
        $modParams = Handler::getInstance()->getOptions();
        $arResult = [];

        if ($modParams['GEOIP']['DATA_SOURCE_IP'] == 'DADATA') {
            $obDadata = new \Stroy\Regionality\Services\Dadata\Address();
            $obDadata->getIpLocate($ip);
            $arResult = $obDadata->getConvertResult();
            $obDadata->destroy();
        } else if($modParams['GEOIP']['DATA_SOURCE_IP'] == 'CLOUD_FLARE') {
            $obCloudFlare = new \Stroy\Regionality\Services\CloudFlare\DataBase();
            $obCloudFlare->getLocation();
            $arResult = $obCloudFlare->getConvertResult();
            $obCloudFlare->destroy();
        } else {
            $obGeoIp = new \Stroy\Regionality\Services\Geoip\GeoIpBase();
            $obGeoIp->getIp($ip);
            $arResult = $obGeoIp->getConvertResult();
            $obGeoIp->destroy();
        }

        if ($arResult) {
            if ($arResult['FIAS_ID']) {
                $arLocation = \Stroy\Regionality\Orm\LocationsDataTable::getList(['filter' => ['=UF_FIAS_ID' => $arResult['FIAS_ID']]])->Fetch();
            } elseif ($arResult['ZIP_CODE']) {
                $arLocation = \Stroy\Regionality\Orm\LocationsDataTable::getList(['filter' => ['=UF_ZIP_CODE' => $arResult['ZIP_CODE']]])->Fetch();
                if (!$arLocation) {
                    $arLocationExternal = \Bitrix\Sale\Location\ExternalTable::getList(['filter' => ['=SERVICE_ID' => '3', '=XML_ID' => $arResult['ZIP_CODE']], 'select' => ['LOCATION_CODE' => 'LOCATION.CODE']])->Fetch();
                    if ($arLocationExternal) {
                        $arLocation = \Stroy\Regionality\Orm\LocationsDataTable::getList(['filter' => ['=UF_LOC_CODE' => $arLocationExternal['LOCATION_CODE']]])->Fetch();
                    }
                }
            }

            if (!$arLocation && $arResult['LATITUDE'] && $arResult['LONGITUDE']) {
                $arLocation = $this->getNearestDot($arResult['LATITUDE'], $arResult['LONGITUDE']);
            }
        } else {
            $arResult['IP'] = \Stroy\Regionality\Location\CurrentLocation::DEFAULT_LOCATION['IP'];
            $arResult['COUNTRY'] = \Stroy\Regionality\Location\CurrentLocation::DEFAULT_LOCATION['COUNTRY'];
            $arResult['COUNTRY_CODE'] = \Stroy\Regionality\Location\CurrentLocation::DEFAULT_LOCATION['COUNTRY_CODE'];
            $arLocation = \Stroy\Regionality\Orm\LocationsDataTable::getList(['filter' => ['=UF_LOC_CODE' => \Stroy\Regionality\Location\CurrentLocation::DEFAULT_LOCATION['LOCATION_CODE']]])->Fetch();
        }

        if ($arLocation) {
            if ($arLocation['UF_NAME']) {
                $arResult['CITY'] = $arLocation['UF_NAME'];
            }
            if ($arLocation['UF_ZIP_CODE']) {
                $arResult['ZIP_CODE'] = $arLocation['UF_ZIP_CODE'];
            }
            if ($arLocation['UF_FIAS_ID']) {
                $arResult['FIAS_ID'] = $arLocation['UF_FIAS_ID'];
            }
            if ($arLocation['UF_LOC_CODE']) {
                $arResult['LOC_CODE'] = $arLocation['UF_LOC_CODE'];
            }
            if ($arLocation['UF_LATITUDE']) {
                $arResult['LATITUDE'] = $arLocation['UF_LATITUDE'];
            }
            if ($arLocation['UF_LONGITUDE']) {
                $arResult['LONGITUDE'] = $arLocation['UF_LONGITUDE'];
            }
        }

        return $arResult;
    }

    /**
     * Проверить наличие кеша
     * @return bool
     */
    protected function isCache(): bool
    {
        $cache = $this->getCache();

        #Сбрасываем кеш если одно из полей отсутствует
        if ($cache && !Handler::checkFields($cache, ['IP', 'COUNTRY', 'CITY', 'LATITUDE', 'LONGITUDE', 'FIAS_ID', 'LOC_CODE'])) {
            $cache = false;
        }

        return (bool)$cache;
    }

    /**
     * Вычисляем Ближайшую точку
     * @param $latitude - широта
     * @param $longitude - долгота
     * @return array
     */
    protected function getNearestDot($latitude, $longitude): array
    {
        $arResult = [];

        $obLocation = \Stroy\Regionality\Orm\LocationsDataTable::getList([
            'order' => ['ID' => 'ASC'],
            'filter' => ['LOCATION_TYPE_ID' => 5, '>UF_LATITUDE' => '0', '>UF_LONGITUDE' => '0'],
            'select' => ['*', 'LOCATION_TYPE_ID' => 'LOCATION.TYPE_ID']
        ]);
        if ($obLocation) {
            while ($arItem = $obLocation->Fetch()) {
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

        if ($matResult['DOTS']) {
            #Вычисляем минимальное значение, то есть ближайшую точку к нам
            $dotMinIndex = array_search(min($matResult['DOTS']), $matResult['DOTS']);

            $arResult = $matResult['ITEMS'][$dotMinIndex];
        }

        return $arResult;
    }
}
