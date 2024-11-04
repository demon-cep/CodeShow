<?php

namespace Stroy\Regionality\Services\Geoip;

use \Bitrix\Main\Error;
use \Bitrix\Main\ErrorCollection;

class GeoIpBase
{
    protected static ?self $instance = null;
    protected ErrorCollection $errorCollection;
    protected $config = [];
    protected $result = [];

    public function __construct()
    {
        $this->errorCollection = new ErrorCollection();
        $this->config['IP'] = \Stroy\Regionality\Location\CurrentLocation::DEFAULT_LOCATION['IP'];
        $this->config['PATH_ROOT'] = dirname(__FILE__);
        $this->config['PATH_DATA'] = dirname(__FILE__) . '/data/IP2LOCATIONIPv46.BIN';
    }

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
     * @brief Получение гео-информации по IP
     * @param $ip
     * @return array
     */
    public function getIp($ip): array
    {
        if ($ip) {
            $this->config['IP'] = $ip;
        }

        $this->result = $this->getDataLocal();

        return $this->result;
    }

    /**
     * @brief Получение result
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @brief Конвертировать result
     * @return array
     */
    public function getConvertResult(): array
    {
        $arResult = [];

        if ($this->result['ipAddress']) {
            $arResult = [
                'IP' => $this->config['IP'],
                'COUNTRY' => $this->result['countryName'],
                'COUNTRY_CODE' => $this->result['countryCode'],
                'CITY' => $this->result['cityName'],
                'LATITUDE' => $this->result['latitude'],
                'LONGITUDE' => $this->result['longitude']
            ];

            if ($this->result['zipCode']) {
                $arResult['ZIP_CODE'] = $this->result['zipCode'];
            }
        }

        return $arResult;
    }

    /**
     * @brief Запрос гео-информации по IP
     * @return array
     */
    protected function getDataLocal(): array
    {
        $arResult = [];

        $this->getDataPath();

        if ($this->isSuccess()) {
            try {
                //['ipNumber|1', 'ipVersion|2', 'ipAddress|3', 'latitude|4', 'longitude|5', 'countryName|6', 'countryCode|9', 'timeZone|10', 'zipCode|1002', 'cityName|1003', 'regionName|1004'];
                $arDbFields = ['1', '2', '3', '4', '5', '6', '9', '10', '1002', '1003', '1004'];
                $db = new Database($this->config['PATH_DATA'], Database::FILE_IO);
                $arResult = $db->lookup($this->config['IP'], $arDbFields);
            } catch (\Exception $e) {
                $this->addErrors($e->getMessage(), 404);
            }
        }

        return $arResult;
    }

    /**
     * @brief Определение путей к файлам
     * @return void
     */
    protected function getDataPath(): void
    {
        if (!file_exists($this->config['PATH_DATA'])) {
            $this->addErrors('file not found' . $CIDRFile, 404);
        }
    }

    /**
     * @brief Проверка на ошибки
     * @return bool
     **/
    public function isSuccess(): bool
    {
        return $this->errorCollection->isEmpty();
    }

    /**
     * @brief Добавление ошибок
     * @param $errors
     * @param $code
     * @return void
     */
    protected function addErrors($errors, $code): void
    {
        if (is_array($errors)) {
            foreach ($errors as $error) {
                $this->errorCollection->setError(new Error($error, $code));
            }
        } else if (is_string($errors)) {
            $this->errorCollection->setError(new Error($errors, $code));
        }
    }

    /**
     * @brief Очистить
     * @return void
     */
    public function destroy()
    {
        self::$instance = null;
        $this->errorCollection = new ErrorCollection();
        $this->config = [];
        $this->result = [];
    }
}
