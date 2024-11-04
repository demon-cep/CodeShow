<?php

namespace Stroy\Regionality;

use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

class Handler
{
    /**@var Handler|null */
    protected static ?self $instance = null;

    protected $storage = [];

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
     * Получить имя модуля
     * @return string
     */
    public static function getModuleName(): string
    {
        return 'stroy.regionality';
    }

    /**
     * Проверка на админ страницу
     * @return bool
     */
    public static function isAdminPage(): bool
    {
        return \CSite::InDir('/bitrix/') || in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'HEAD']);
    }

    /**
     * Форматирование номера телефона
     * @param $phone - Номер телефона
     * @param $type - Тип форматирования [clear | normalize | format]
     * @return string
     */
    public static function formattingPhone($phone = '', $type = 'clear'): string
    {
        if (!$phone) {
            return '';
        }

        $strResult = $str = preg_replace("/\D+/", '', $phone);

        if ($type == 'normalize') {
            $strResult = \Bitrix\Main\UserPhoneAuthTable::normalizePhoneNumber($str, 'RU');
        } else if ($type == 'normalize2') {
            $str = \Bitrix\Main\UserPhoneAuthTable::normalizePhoneNumber($str, 'RU');
            $strResult = preg_replace("/\D+/", '', $str);
        } else if ($type == 'format') {
            if (mb_strlen($str) == 11) {
                $strСountry = $str[0];
                $strCode = mb_substr($str, 1, -6);
                $strPhone = mb_substr($str, -6);
                $strResult = $strСountry . ' (' . $strCode . ') ' . $strPhone[0] . $strPhone[1] . '-' . $strPhone[2] . $strPhone[3] . '-' . $strPhone[4] . $strPhone[5];
            }
        }

        return $strResult;
    }

    /**
     * Проверка обязательных полей Location
     * @param array $arData - Массив данных для проверки
     * @param array $arFields - Проверяемы поля
     * @return bool
     */
    public static function checkFields(array $arData = [], $arFields = []): bool
    {
        $result = true;

        foreach ($arFields as $item) {
            if (!array_key_exists($item, $arData)) {
                $result = false;
                break;
            }
        }

        return $result;
    }

    /**
     * Получить все настройки модуля
     * @return array
     * @throws ArgumentNullException
     */
    public function getOptions(): array
    {
        if ($this->storage['OPTIONS']) {
            return $this->storage['OPTIONS'];
        }

        $resOptions = [];
        $settings = Option::getForModule(self::getModuleName());

        #Общие настройки
        $resOptions['COMMON']['CATALOG_IBLOCK_ID'] = $settings['tab10_common_catalog_iblock_id'];
        $resOptions['COMMON']['SAVE_COOKIES'] = $settings['tab10_common_save_cookies'];

        #GEOIP
        $resOptions['GEOIP']['DATA_SOURCE_IP'] = $settings['tab20_geoip_data_source_ip'];

        #DADATA
        $resOptions['DADATA']['TOKEN'] = $settings['tab30_dadata_token'];
        $resOptions['DADATA']['SECRET_KEY'] = $settings['tab30_dadata_secret_key'];

        $this->storage['OPTIONS'] = $resOptions;

        return $resOptions;
    }
}
