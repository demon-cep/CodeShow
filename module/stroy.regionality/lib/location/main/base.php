<?php

namespace Stroy\Regionality\Location\Main;

use Stroy\Regionality\Location\Fields;

/**
 * Класс базовый для местоположений
 */
class Base
{
    protected Fields $config;
    protected Fields $result;
    protected string $cacheKey = '';

    public function __construct($arParams = [])
    {
        $this->result = new Fields();
        $this->config = new Fields();

        if ($arParams) {
            $this->config->setValues($arParams);
        }
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
        return $_SESSION['STROYLANDIYA']['GEOIP'][$this->getCacheKey()];
    }

    /**
     * Сохранить в cache
     */
    public function saveCache(): void
    {
        $_SESSION['STROYLANDIYA']['GEOIP'][$this->getCacheKey()] = [];
        $_SESSION['STROYLANDIYA']['GEOIP'][$this->getCacheKey()] = $this->getResult()->getValues();
    }

    /**
     * Очистить cache
     * @return void
     */
    public function clearCache(): void
    {
        $_SESSION['STROYLANDIYA']['GEOIP'][$this->getCacheKey()] = [];
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
     * Очистить класс
     * @return void
     */
    public function destroy(): void
    {
        $this->result = new Fields();
        $this->config = new Fields();
    }
}
