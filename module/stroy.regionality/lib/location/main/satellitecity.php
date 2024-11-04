<?php

namespace Stroy\Regionality\Location\Main;

use Stroy\Regionality\Handler;

/**
 * Класс для работы с городами не имеющих собственных магазинов, используется для доставок. [b_goroda_sputniki]
 */
class SatelliteCity extends Base
{
    /** @var string $cacheKey - ключ кеширования доступен для получения внутри класса */
    protected string $cacheKey = 'SATELITE_CITY';

    /**
     * Init
     * @return void
     */
    public function init(): void
    {
        if ($this->isCache()) {
            $arResult = $this->getCache();
        } else {
            if ($this->config->get('LATITUDE') && $this->config->get('LONGITUDE')) {
                $arResult = $this->getNearestDot($this->config->get('LATITUDE'), $this->config->get('LONGITUDE'));
            } else if ($this->config->get('LOCATION_CODE')) {
                $arResult = $this->getLocationByCode();
            }

            if ($arResult) {
                #Class голосует за сохранение данных
                $this->config->set('SAVE', true);
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
        $arGorodaSputniki = \Stroy\Regionality\Orm\GorodaSputnikiTable::getExList([], true);

        if ($arGorodaSputniki) {
            foreach ($arGorodaSputniki as $arItem) {
                if ($arItem['LOCDATA_LATITUDE'] && $arItem['LOCDATA_LONGITUDE']) {
                    $matResult['ITEMS'][$arItem['ID']] = $arItem;
                    #Формула вычисления длины от точки А до точки Б
                    //$matResult['DOT'][$arItem['ID']] = \Stroy\Regionality\Services\GeoDistance\Base::calculateNearestDot(
                    #Формула вычисления длины в метрах
                    $matResult['DOTS'][$arItem['ID']] = \Stroy\Regionality\Services\GeoDistance\Base::calculateDistanceMeters(
                        [$arItem['LOCDATA_LATITUDE'], $arItem['LOCDATA_LONGITUDE']],
                        [$latitude, $longitude]
                    );
                }
            }

            #Вычисляем минимальное значение, то есть ближайшую точку к нам
            $dotMinIndex = array_search(min($matResult['DOTS']), $matResult['DOTS']);

            $arResult = $matResult['ITEMS'][$dotMinIndex];

            if ($arResult) {
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
            $arResult = \Stroy\Regionality\Orm\GorodaSputnikiTable::getExList(['UF_LOC_CODE' => $this->getConfig()->get('LOCATION_CODE')], false);
            if ($arResult) {
                $this->getMapResult($arResult);
            }
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
        $arFields = ['UF_XML_ID', 'UF_FIAS_ID', 'UF_VERSION', 'LOCATION_ID', 'LOCATION_CODE', 'LOCATION_LEFT_MARGIN', 'LOCATION_RIGHT_MARGIN',
            'LOCATION_TYPE_ID', 'LOCDATA_SUBDOMAIN', 'LOCDATA_PHONE', 'LOCDATA_DECLENSIONS', 'LOCATION_PATH'];

        foreach ($arFields as $item) {
            if (array_key_exists($item, $arData)) {
                unset($arData[$item]);
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
            'XML_ID' => $arData['UF_XML_ID'],
            'XML_GOROD' => $arData['UF_XML_GOROD'],
            'LOC_CODE' => $arData['UF_LOC_CODE'],
            'FIAS_ID' => $arData['LOCDATA_FIAS_ID'],
            'ZIP_CODE' => $arData['LOCDATA_ZIP_CODE'],
            'LATITUDE' => $arData['LOCDATA_LATITUDE'],
            'LONGITUDE' => $arData['LOCDATA_LONGITUDE'],
            'GORODA_NAME' => $arData['GORODA_NAME'],
            'GORODA_OSNOVNOYSLKAD' => $arData['GORODA_OSNOVNOYSLKAD'],
            'GORODA_FIAS_ID' => $arData['GORODA_FIAS_ID'],
            'GORODA_LOC_CODE' => $arData['GORODA_LOC_CODE']
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
        if ($cache && !Handler::checkFields($cache, ['ID', 'NAME', 'LOC_CODE', 'FIAS_ID', 'LATITUDE', 'LONGITUDE'])) {
            $cache = false;
        }

        return (bool)$cache;
    }
}
