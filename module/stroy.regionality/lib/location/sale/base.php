<?php

namespace Stroy\Regionality\Location\Sale;

use Stroy\Regionality\Location\CurrentLocationManager;
use Stroy\Regionality\Location\Fields;

/**
 * Класс базовый для местоположений
 */
class Base
{
    protected Fields $config;
    protected Fields $result;
    protected string $cacheKey = '';
    protected array $mainCityFilter = [
        '!MAIN_CITY_OSNOVNOYSLKAD' => false,
        '!MAIN_CITY_UF_PRICE_TYPE_PREFIX' => false
    ];
    protected array $excludedLocCodes = [];

    public function __construct($arParams = [])
    {
        $this->result = new Fields();
        $this->config = new Fields();

        if ($arParams) {
            $this->config->setValues($arParams);
        }
        $this->excludedLocCodes = defined('EXCLUDED_LOCATION_STORES') && !empty(EXCLUDED_LOCATION_STORES) && is_array(EXCLUDED_LOCATION_STORES) ? EXCLUDED_LOCATION_STORES : [];
    }

    /**
     * Получить config
     * @return Fields
     */
    public function getConfig(): Fields
    {
        return $this->config;
    }

    /**
     * Получить result
     * @return Fields
     */
    public function getResult(): Fields
    {
        return $this->result;
    }

    /**
     * Проверка необходимости сохранить
     * @return bool
     */
    public function isSave(): bool
    {
        return (bool)$this->getConfig()->get('SAVE');
    }

    /**
     * Получить ключ cache
     * @return string
     */
    protected function getCacheKey(): string
    {
        return $this->cacheKey;
    }

    /**
     * Проверить наличие кеша
     * @return bool
     */
    protected function isCache(): bool
    {
        return (bool)$this->getCache();
    }

    /**
     * Получить cache
     */
    protected function getCache()
    {
        return $_SESSION['STROYLANDIYA'][$this->getCacheKey()];
    }

    /**
     * Сохранить в cache
     */
    public function saveCache(): void
    {
        $_SESSION['STROYLANDIYA'][$this->getCacheKey()] = [];
        $_SESSION['STROYLANDIYA'][$this->getCacheKey()] = $this->getResult()->getValues();
    }

    /**
     * Очистить cache
     * @return void
     */
    public function clearCache(): void
    {
        $_SESSION['STROYLANDIYA'][$this->getCacheKey()] = [];
    }

    /**
     * Сброс всех данных
     * @return void
     */
    public function reset(): void
    {
        $this->clearCache();
        $this->getResult()->clear();
        $this->getConfig()->clear();
    }

    /**
     * Вычисляем Ближайший город
     * @return array
     */
    protected function getNearestCity($latitude, $longitude): array
    {
        $arResult = [];

        $arLocationsData = CurrentLocationManager::getLocationsDataList([
            '!MAIN_CITY_OSNOVNOYSLKAD' => false,
            '!MAIN_CITY_UF_PRICE_TYPE_PREFIX' => false
        ], true, $this->excludedLocCodes);

        if ($arLocationsData) {
            foreach ($arLocationsData as $arItem) {
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
        }

        return $arResult;
    }

    /**
     * Очистить класс
     * @return void
     */
    public function destroy(): void
    {
        $this->result = new Fields();
        $this->config = new Fields();
    }
}
