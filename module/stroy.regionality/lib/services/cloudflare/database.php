<?php

namespace Stroy\Regionality\Services\CloudFlare;

use \Bitrix\Main\Error;
use \Bitrix\Main\ErrorCollection;

class DataBase
{
    protected static ?self $instance = null;
    protected ErrorCollection $errorCollection;
    protected $config = [];
    protected $result = [];

    public function __construct()
    {
        $this->errorCollection = new ErrorCollection();
        $this->config = [
            'TIMEZONE' => $_SERVER['HTTP_CF_TIMEZONE'] ?: '',
            'IP' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?: '',
            'CITY' => $_SERVER['HTTP_CF_IPCITY'] ?: '',
            'CONTINENT' => $_SERVER['HTTP_CF_IPCONTINENT'] ?: '',
            'COUNTRY' => $_SERVER['HTTP_CF_IPCOUNTRY'] ?: '',
            'LATITUDE' => $_SERVER['HTTP_CF_IPLATITUDE'] ?: '',
            'LONGITUDE' => $_SERVER['HTTP_CF_IPLONGITUDE'] ?: '',
            //'POSTAL_CODE' => $_SERVER['HTTP_CF_POSTAL_CODE'] ?: '',
            'REGION' => $_SERVER['HTTP_CF_REGION'] ?: '',
            'REGION_COD' => $_SERVER['HTTP_CF_REGION_CODE'] ?: ''
        ];
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
     * @brief Получение гео-информации
     * @return array
     */
    public function getLocation(): void
    {
        $this->checkFields($this->config, ['IP', 'LATITUDE', 'LONGITUDE']);

        if ($this->isSuccess()) {
            $this->result = $this->config;
        }
    }

    /**
     * @brief Получение result
     */
    public function getResult(): array
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

        if ($this->result['IP']) {
            $arResult = [
                'IP' => $this->config['IP'],
                'COUNTRY' => $this->result['COUNTRY'],
                'COUNTRY_CODE' => $this->result['CONTINENT'],
                'CITY' => $this->result['CITY'],
                'LATITUDE' => $this->result['LATITUDE'],
                'LONGITUDE' => $this->result['LONGITUDE']
            ];

            if ($this->result['POSTAL_CODE']) {
                $arResult['ZIP_CODE'] = $this->result['POSTAL_CODE'];
            }
        }

        return $arResult;
    }

    /**
     * Проверка обязательных полей Location
     * @param array $arFields - Массив полей
     * @param array $arRequired - Проверяемые поля
     * @return void
     */
    public function checkFields($arFields, $arRequired = []): void
    {
        if ($arFields && $arRequired) {
            foreach ($arRequired as $item) {
                if (empty($arFields[$item])) {
                    $this->addErrors('error '.$item.' not found', 404);
                }
            }
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
