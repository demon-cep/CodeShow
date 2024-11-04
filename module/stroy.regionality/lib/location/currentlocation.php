<?php

namespace Stroy\Regionality\Location;

use Stroy\Regionality\Handler;

/**
 * Класс-синглтон для работы с текущим населенным пунктом
 */
class CurrentLocation
{
    /** @var Main\Location $location - Текущее местоположение пользователя */
    protected Main\Location $location;

    /** @var Main\MainCity $mainCity - Основной город у которого есть магазины [b_gorda] */
    protected Main\MainCity $mainCity;

    /** @var Main\SatelliteCity $satelliteCity - Не имеют собственных магазинов, используется для доставок. [b_goroda_sputniki] */
    protected Main\SatelliteCity $satelliteCity;

    /** @var Sale\Store $store - Склады */
    protected Sale\Store $store;

    /** @var Sale\Price $price - Цены */
    protected Sale\Price $price;

    /**@var CurrentLocation|null */
    protected static ?self $instance = null;

    public const DEFAULT_LOCATION = [
        'IP' => '145.255.22.78',
        'COUNTRY' => 'Россия',
        'COUNTRY_CODE' => 'RU',
        'REGION' => 'Оренбургская',
        'CITY' => 'Оренбург',
        'LOCATION_LATITUDE' => '51.7875092',
        'LOCATION_LONGITUDE' => '55.1018828',
        'LOCATION_ZIP' => '460000',
        'LOCATION_CODE' => '0000709964',
        'LOCATION_PHONE' => '88007003396'
    ];

    public function __construct()
    {
        $this->location = new Main\Location();
        $this->mainCity = new Main\MainCity();
        $this->satelliteCity = new Main\SatelliteCity();
        $this->store = new Sale\Store();
        $this->price = new Sale\Price();

        #Инициализация определение города по ip
        $this->location->init();
        if ($this->location->isSave()) {
            $this->location->saveCache();
        }

        #Инициализация определение основного города
        $this->mainCity->init();
        if ($this->mainCity->isSave()) {
            $this->mainCity->saveCache();

            $this->satelliteCity->clearCache();
        }

        #Инициализация определение satellite города
        $this->satelliteCity->getConfig()->set('LOCATION_CODE', $this->location->getResult()->get('LOC_CODE'));
        $this->satelliteCity->init();
        if ($this->satelliteCity->isSave()) {
            $this->satelliteCity->saveCache();
        }

        #Инициализация определение складов
        $this->store->getConfig()->set('LOCATION_CODE', $this->mainCity->getResult()->get('LOC_CODE'));
        $this->store->init();

        #Инициализация определение типов цен
        $this->price->getConfig()->set('LOCATION_CODE', $this->mainCity->getResult()->get('LOC_CODE'));
        $this->price->init();

        if ($this->isSaveCookie()) {
            $this->clearConfigAll();

            #Установим | Сохранить пользовательские данные в Cookies
            $modParams = Handler::getInstance()->getOptions();
            if ($modParams['COMMON']['SAVE_COOKIES']) {
                \Stroy\Regionality\Helpers\Request::setUserCookies();
            }
        }
    }

    /**
     * Возвращает экземпляр класса
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
     * Возвращает объект Location
     * @return Main\Location
     */
    public function getLocation(): Main\Location
    {
        return $this->location;
    }

    /**
     * Возвращает объект MainCity
     * @return Main\MainCity
     */
    public function getMainCity(): Main\MainCity
    {
        return $this->mainCity;
    }

    /**
     * Возвращает объект Location
     * @return Main\SatelliteCity
     */
    public function getSatelliteCity(): Main\SatelliteCity
    {
        return $this->satelliteCity;
    }

    /**
     * Возвращает объект Store
     * @return Sale\Store
     */
    public function getStore(): Sale\Store
    {
        return $this->store;
    }

    /**
     * Возвращает объект Price
     * @return Sale\Price
     */
    public function getPrice(): Sale\Price
    {
        return $this->price;
    }

    /**
     * Установить новый объект местопожения
     * @param array $arLocationFields
     * @return array
     */
    public function setMainCity(array $arLocationFields): array
    {
        if (!$arLocationFields || !Handler::checkFields($arLocationFields, ['ID', 'NAME', 'LOC_CODE', 'FIAS_ID', 'LATITUDE', 'LONGITUDE', 'LOCATION_PATH', 'PHONE'])) {
            return [];
        }

        $this->mainCity->reset();
        $this->mainCity->getResult()->resetValues($arLocationFields);
        $this->mainCity->saveCache();

        $this->satelliteCity->reset();
        $this->satelliteCity->getConfig()->set('LOCATION_CODE', $this->mainCity->getResult()->get('LOC_CODE'));
        $this->satelliteCity->init();
        $this->satelliteCity->saveCache();

        $this->store->reset();
        $this->store->getConfig()->set('LOCATION_CODE', $this->mainCity->getResult()->get('LOC_CODE'));
        $this->store->init();

        $this->price->reset();
        $this->price->getConfig()->set('LOCATION_CODE', $this->mainCity->getResult()->get('LOC_CODE'));
        $this->price->init();

        $this->clearConfigAll();

        #Установим | Сохранить пользовательские данные в Cookies
        $modParams = Handler::getInstance()->getOptions();
        if ($modParams['COMMON']['SAVE_COOKIES']) {
            \Stroy\Regionality\Helpers\Request::setUserCookies();
        }

        #Редиректы
        $requestHelper = new \Stroy\Regionality\Helpers\Request();
        $subDomain = $requestHelper->getSubDomain();
        $newSybDomain = $this->mainCity->getResult()->get('SUBDOMAIN');

        if ($subDomain != $newSybDomain) {
            $domain = $requestHelper->getProtocol();
            if ($newSybDomain) {
                $domain .= $newSybDomain . '.';
            }
            $domain .= $requestHelper->getDomain();

            $arResult = ['LOCAL_REDIRECT' => true, 'LOCAL_REDIRECT_URL' => $domain];
        } else {
            $arResult = ['LOCAL_RELOAD' => true];
        }

        return $arResult;
    }

    /**
     * Проверка необходимости сохранить в $_COOKIE
     * @return bool
     */
    private function isSaveCookie(): bool
    {
        $isSave = false;

        if (
            $this->location->isSave() ||
            $this->mainCity->isSave() ||
            $this->satelliteCity->isSave() ||
            $this->price->isSave() ||
            $this->store->isSave()
        ) {
            $isSave = true;
        }

        return $isSave;
    }

    /**
     * Удалить все конфиги
     * @return void
     */
    private function clearConfigAll(): void
    {
        $this->location->getConfig()->clear();
        $this->mainCity->getConfig()->clear();
        $this->satelliteCity->getConfig()->clear();
        $this->price->getConfig()->clear();
        $this->store->getConfig()->clear();
    }
}
