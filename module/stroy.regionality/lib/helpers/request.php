<?php

namespace Stroy\Regionality\Helpers;

use Bitrix\Main\Context;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\Server;

/**
 * Класс-хелпер для работы с request
 * Class Request
 */
class Request
{
    /** @var HttpRequest */
    protected HttpRequest $request;

    /** @var Server */
    protected Server $server;

    public function __construct()
    {
        $context = Context::getCurrent();
        $this->request = $context->getRequest();
        $this->server = $context->getServer();
    }

    public function isMobileRestapi(): bool
    {
        return strpos($this->server->get('REAL_FILE_PATH'), '/local/restapi/') !== false;
    }
    /**
     * Возвращает основной домен
     * @return string
     */
    public function getProtocol(): string
    {
        return ($this->request->isHttps()) ? 'https://' : 'http://';
    }

    /**
     * Возвращает основной домен
     * @return string
     */
    public function getDomain(): string
    {
        $domain = $this->server->get('HTTP_HOST');

        if (!$domain) {
            return '';
        }

        $arDomain = explode('.', $domain);

        if (count($arDomain) > 2) {
            $arDomain = array_slice($arDomain, -2);
        }

        $domain = implode('.', $arDomain);

        return $domain;
    }

    /**
     * Возвращает поддомен из запроса
     * @return string
     */
    public function getSubDomain(): string
    {
        $domain = $this->server->get('HTTP_HOST');

        if (!$domain) {
            return '';
        }

        $arDomain = explode('.', $domain);

        if (count($arDomain) <= 2) {
            return '';
        }

        return array_shift($arDomain) ?: '';
    }

    /**
     * Проверить субдомена
     * @param $strDomain
     * @return bool
     */
    public function checkSubDomain($strDomain = ''): bool
    {
        $result = true;

        $domain = $this->getSubDomain();

        if ($domain != $strDomain) {
            $result = false;
        }

        return $result;
    }

    /**
     * Установим | Сохранить пользовательские данные в Cookies
     * @param $arOptions
     * @return false|void
     */
    public static function setUserCookies($arOptions = ['STROYLANDIYA'])
    {
        $secure = (\COption::GetOptionString('main', 'use_secure_password_cookies', 'N') == 'Y' && \CMain::IsHTTPS());
        $spread = (\COption::GetOptionString('main', 'auth_multisite', 'N') == 'Y' ? (\Bitrix\Main\Web\Cookie::SPREAD_SITES | \Bitrix\Main\Web\Cookie::SPREAD_DOMAIN) : \Bitrix\Main\Web\Cookie::SPREAD_DOMAIN);

        foreach ($arOptions as $item) {
            if ($item == 'STROYLANDIYA') {
                $arCookie = ['NAME' => 'STROYLANDIYA', 'VALUE' => json_encode($_SESSION['STROYLANDIYA']), 'TIME' => (time() + 3600 * 24 * 30)];
            } else {
                return false;
            }

            $obCC = new \Stroy\Regionality\Helpers\CookiesCrypter();
            $resCC = $obCC->encrypt($arCookie);

            if ($resCC) {
                foreach ($resCC as $nameCC => $valueCC) {
                    $obCookie = new \Bitrix\Main\Web\Cookie($nameCC, $valueCC, $arCookie['TIME'], false);
                    $obCookie->setSecure($secure);//безопасное хранение cookie
                    $obCookie->setSpread($spread);//распространять куки на все домены
                    $obCookie->setHttpOnly(true);

                    setcookie(
                        $obCookie->getName(),
                        $obCookie->getValue(),
                        $obCookie->getExpires(),
                        $obCookie->getPath(),
                        $obCookie->getDomain(),
                        $obCookie->getSecure(),
                        $obCookie->getHttpOnly()
                    );
                }
            }
        }
    }

    /**
     * Получение пользовательские Cookies
     */
    public static function getUserCookies()
    {
        $obCC = new \Stroy\Regionality\Helpers\CookiesCrypter();
        $obRequest = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
        $arCookies = $obRequest->getCookieRawList()->getValues();
        $arCookiesNew = $arCookiesToDecrypt = [];

        if ($arCookies) {
            $cookieBitrixPrefix = \Bitrix\Main\Config\Option::get('main', 'cookie_name', 'BITRIX_SM') . '_';
            foreach ($arCookies as $nameC => $valueC) {
                if (mb_strpos($nameC, $cookieBitrixPrefix) === 0) {
                    continue;
                }

                if (is_string($valueC) && $obCC->shouldDecrypt($nameC, $valueC)) {
                    $arCookiesToDecrypt[$nameC] = $valueC;
                } else {
                    $arCookiesNew[$nameC] = $valueC;
                }
            }

            if ($arCookiesToDecrypt) {
                foreach ($arCookiesToDecrypt as $nameC => $valueC) {
                    $resDecrypt = $obCC->decrypt($nameC, $valueC, $arCookiesNew);
                    if ($resDecrypt) {
                        $arCookiesToDecrypt[$nameC] = json_decode($resDecrypt, true);
                    }
                }
            }
        }

        return $arCookiesToDecrypt;
    }

    /**
     * Удалим пользовательские Cookies
     * @param $arOptions
     */
    public static function deleteUserCookies($arOptions = ['STROYLANDIYA'])
    {
        $timestampDel = (time() - 3600);
        $obRequest = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
        $arCookies = $obRequest->getCookieRawList()->getValues();

        if ($arCookies) {
            foreach ($arCookies as $nameC => $valueC) {
                foreach ($arOptions as $item) {
                    if (mb_strpos($nameC, $item) === 0) {
                        $obCookie = new \Bitrix\Main\Web\Cookie($nameC, '', $timestampDel, false);

                        setcookie(
                            $obCookie->getName(),
                            $obCookie->getValue(),
                            $obCookie->getExpires(),
                            $obCookie->getPath(),
                            $obCookie->getDomain(),
                            $obCookie->getSecure(),
                            $obCookie->getHttpOnly()
                        );
                    }
                }
            }
        }
    }
}
