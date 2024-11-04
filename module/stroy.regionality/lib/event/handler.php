<?php

namespace Stroy\Regionality\Event;

use Bitrix\Main\Localization\Loc;
use Stroy\Regionality\Handler as HD;

Loc::loadMessages(__FILE__);

class Handler
{
    /**
     * Событие 'OnPageStart' вызывается в начале выполняемой части пролога сайта, после подключения всех библиотек и отработки агентов
     **/
    public static function OnPageStartHandler(): void
    {

    }

    /**
     * Событие 'OnProlog' вызывается в начале визуальной части пролога сайта
     **/
    public static function OnPrologHandler(): void
    {
        if (HD::isAdminPage()) {
            return;
        }

        if (!$_SESSION['STROYLANDIYA']['GEOIP']['LOCATION'] || !$_SESSION['STROYLANDIYA']['GEOIP']['MAIN_CITY']) {
            #Данные сесии из Cookies
            $resCookies = \Stroy\Regionality\Helpers\Request::getUserCookies();
            if ($resCookies && is_array($resCookies['STROYLANDIYA'])) {
                $modParams = HD::getInstance()->getOptions();
                if (
                    $resCookies['STROYLANDIYA']['GEOIP']['LOCATION'] && $resCookies['STROYLANDIYA']['GEOIP']['MAIN_CITY'] &&
                    $resCookies['STROYLANDIYA']['PRICE'] && $resCookies['STROYLANDIYA']['STORE'] &&
                    $modParams['COMMON']['SAVE_COOKIES']
                ) {
                    $_SESSION['STROYLANDIYA'] = $resCookies['STROYLANDIYA'];
                } else {
                    \Stroy\Regionality\Helpers\Request::deleteUserCookies();
                }
            }
        }

        //\Stroy\Regionality\Helpers\Request::deleteUserCookies();
        //unset($_SESSION['STROYLANDIYA']);
        $obLocation = \Stroy\Regionality\Location\CurrentLocation::getInstance();
    }

    /**
     * Событие 'onSalePaySystemRestrictionsClassNamesBuildList' ограничение платёжных служб
     **/
    public static function onSalePaySystemRestrictionsClassNamesBuildListHandler()
    {
        #Регистрируем новые типы свойств
        \Bitrix\Main\Loader::includeModule('sale');
        \Bitrix\Sale\Internals\Input\Manager::register('LOCATION_MULTI_PAYSYSTEM', [
            'CLASS' => '\Stroy\Regionality\Event\PaySystem\Inputs\LocationMulti',
            'NAME' => Loc::getMessage('REGIONALITY_LOCATION_NAME')
        ]);
        \Bitrix\Sale\Internals\Input\Manager::register('LOCATION_MULTI_EXCLUDE_PAYSYSTEM', [
            'CLASS' => '\Stroy\Regionality\Event\PaySystem\Inputs\LocationMultiExclude',
            'NAME' => Loc::getMessage('REGIONALITY_EXCLUDE_LOCATION_NAME')
        ]);

        #Проверяем наличие файла, class и регистрируем ограничение платёжных служб
        $pathFile = str_ireplace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\', '/', realpath(__DIR__)));
        $pathByLocation = $pathFile . '/paysystem/restrictions/bylocation.php';
        $pathExcludeLocation = $pathFile . '/paysystem/restrictions/excludelocation.php';

        $arEvent = [];
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $pathByLocation)) {
            $arEvent['\Stroy\Regionality\Event\PaySystem\Restrictions\ByLocation'] = $pathByLocation;
        }
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $pathExcludeLocation)) {
            $arEvent['\Stroy\Regionality\Event\PaySystem\Restrictions\ExcludeLocation'] = $pathExcludeLocation;
        }

        return new \Bitrix\Main\EventResult(\Bitrix\Main\EventResult::SUCCESS, $arEvent);
    }
}
