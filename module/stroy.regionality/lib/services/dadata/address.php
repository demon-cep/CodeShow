<?php

namespace Stroy\Regionality\Services\Dadata;

use Stroy\Regionality\Location\CurrentLocationManager;

/**
 * Класс для работы с адресами Dadata
 */
class Address extends DadataBase
{
    /**
     * @bref Возвращает экземпляр класса
     * @return $this
     */
    public static function getInstance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Поиск города
     * @param $arParams ['query'] - город | ['count'] - количество результатов
     * @return array
     */
    public function getSuggest($arParams = []): array
    {
        if ($this->getConfig('URL') != $this->getConfig('URL_SUGGESTIONS')) {
            $this->setConfig('URL', $this->getConfig('URL_SUGGESTIONS'));
        }

        $arParamsDefault = [
            'query' => '',
            'count' => '5',
            'from_bound' => [
                'value' => 'city'
            ],
            'to_bound' => [
                'value' => 'settlement'
            ]
        ];

        $arParams = array_merge($arParamsDefault, (array)$arParams);

        return $this->call('suggest/address', 'POST', $arParams);
    }

    /**
     * Поиск по fias_id
     * @param $arParams ['query'] - fias_id | ['count'] - количество результатов
     * @return array
     */
    public function getFindById($arParams = []): array
    {
        if ($this->getConfig('URL') != $this->getConfig('URL_SUGGESTIONS')) {
            $this->setConfig('URL', $this->getConfig('URL_SUGGESTIONS'));
        }

        $arParamsDefault = [
            'query' => '',
            'count' => '1'
        ];

        $arParams = array_merge($arParamsDefault, (array)$arParams);

        return $this->call('findById/address', 'POST', $arParams);
    }

    /**
     * Поис по координатам
     * @param $arParams ['lat'] - lat | ['lon'] | [count] - количество результатов
     * @return array
     */
    public function getGeoLocate($arParams = []): array
    {
        if ($this->getConfig('URL') != $this->getConfig('URL_SUGGESTIONS')) {
            $this->setConfig('URL', $this->getConfig('URL_SUGGESTIONS'));
        }

        $arParamsDefault = [
            'lat' => '',
            'lon' => '',
            'count' => 1
        ];

        $arParams = array_merge($arParamsDefault, (array)$arParams);

        return $this->call('geolocate/address', 'POST', $arParams);
    }

    /**
     * Поиск по IP
     * @param $ip
     * @return array
     */
    public function getIpLocate($ip = ''): array
    {
        if ($this->getConfig('URL') != $this->getConfig('URL_SUGGESTIONS')) {
            $this->setConfig('URL', $this->getConfig('URL_SUGGESTIONS'));
        }

        $arParams['ip'] = $ip;
        $this->config['PARAMS']['IP'] = $ip;

        return $this->call('iplocate/address', 'POST', $arParams);
    }

    /**
     * Поиск города clean
     * @param $arParams ['query'] - город | ['count'] - количество результатов
     * @return array
     */
    public function getCleanAddress($arParams = []): array
    {
        if ($this->getConfig('URL') != $this->getConfig('URL_CLEANER')) {
            $this->setConfig('URL', $this->getConfig('URL_CLEANER'));
        }

        return $this->call('clean/address', 'POST', $arParams);
    }

    /**
     * Конвертировать result
     * @return array
     */
    public function getConvertResult(): array
    {
        $arResult = [];

        if ($this->result['STATUS'] == '200' && $this->result['RESULT']['location']) {
            $arResult = [
                'IP' => $this->config['PARAMS']['IP'],
                'COUNTRY' => $this->result['RESULT']['location']['data']['country'],
                'COUNTRY_CODE' => $this->result['RESULT']['location']['data']['country_iso_code'],
                'CITY' => $this->result['RESULT']['location']['data']['city'],
                'LATITUDE' => $this->result['RESULT']['location']['data']['geo_lat'],
                'LONGITUDE' => $this->result['RESULT']['location']['data']['geo_lon']
            ];

            if ($this->result['RESULT']['location']['data']['postal_code']) {
                $arResult['ZIP_CODE'] = $this->result['RESULT']['location']['data']['postal_code'];
            }

            if ($this->result['RESULT']['location']['data']['city_fias_id'] || $this->result['RESULT']['location']['data']['region_fias_id']) {
                $arResult['FIAS_ID'] = $this->result['RESULT']['location']['data']['city_fias_id'] ?: $this->result['RESULT']['location']['data']['region_fias_id'];
            }
        }

        return $arResult;
    }
}
