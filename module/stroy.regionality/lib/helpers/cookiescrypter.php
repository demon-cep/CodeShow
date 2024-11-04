<?php

namespace Stroy\Regionality\Helpers;

use Bitrix\Main\Config,
    Bitrix\Main\Security\Cipher,
    Bitrix\Main\Security\SecurityException,
    Bitrix\Main\SystemException;

/**
 * Модифицированный класс шифрония Bitrix\Main\Web\CookiesCrypter
 */
class CookiesCrypter
{
    const COOKIE_MAX_SIZE = 4096;
    const SIGN_PREFIX = '-crpth-';

    protected ?string $cipherKey = null;
    protected $cipher;

    public function __construct()
    {
    }

    /**
     * Инициализация Security\Cipher
     **/
    protected function buildCipher()
    {
        if ($this->cipher) {
            return $this;
        }

        $configuration = Config\Configuration::getInstance();
        $configCrypto = $configuration->get('crypto');
        if (!$configCrypto['crypto_key']) {
            $configuration->add('crypto', ['crypto_key' => 'd839ee196d156ac95c69102ada26b125']);
            $configuration->saveConfiguration();
        }

        $this->cipher = new Cipher();
        $this->cipherKey = (($configuration->get('crypto')['crypto_key']) ?: null);

        if (!$this->cipherKey) {
            throw new SystemException('There is no crypto[crypto_key] in .settings.php. Generate it.');
        }

        return $this;
    }

    /**
     * Инициализация шифрония
     **/
    public function encrypt($cookie)
    {
        if (is_array(!$cookie)) {
            return false;
        }

        $result = [];
        $encryptedValue = $this->encryptValue($cookie['VALUE']);
        $result = $this->packCookie($cookie, $encryptedValue);

        return $result;
    }

    /**
     * Инициализация дешифрония
     **/
    public function decrypt($name, $value, $cookies)
    {
        if (!$name || !$value || is_array(!$cookies)) {
            return false;
        }

        if (!$this->shouldDecrypt($name, $value)) {
            return $value;
        }

        try {
            return $this->unpackCookie($value, $cookies);
        } catch (SecurityException $e) {
            //just skip cookies which we can't decrypt.
        }

        return '';
    }

    /**
     * Разбить на пакеты данных
     **/
    protected function packCookie($cookie, $encryptedValue)
    {
        $length = strlen($encryptedValue);
        $maxContentLength = static::COOKIE_MAX_SIZE - strlen($cookie['NAME']);

        $i = 0;
        $parts = ($length / $maxContentLength);
        $pack = [];
        do {
            $startPosition = $i * $maxContentLength;
            $pack[$cookie['NAME'] . '_' . $i] = substr($encryptedValue, $startPosition, $maxContentLength);
            $i++;
        } while ($parts > $i);

        $pack[$cookie['NAME']] = $this->prependSign(implode(',', array_keys($pack)));

        return $pack;
    }

    /**
     * Собрать пакеты данных в один результат
     **/
    protected function unpackCookie($mainCookie, $cookies)
    {
        $mainCookie = $this->removeSign($mainCookie);
        $packedNames = array_flip(array_filter(explode(',', $mainCookie)));
        $parts = [];

        foreach ($cookies as $name => $value) {
            if (!isset($packedNames[$name])) {
                continue;
            }

            $parts[$packedNames[$name]] = $value;
            if (count($parts) === count($packedNames)) {
                break;
            }
        }
        ksort($parts);
        $encryptedValue = implode('', $parts);

        return $this->decryptValue($encryptedValue);
    }

    /**
     * Зашифровать значение
     **/
    protected function encryptValue($value)
    {
        $this->buildCipher();
        if (function_exists('gzencode')) {
            $value = gzencode($value);
        }

        return $this->encodeUrlSafeB64($this->cipher->encrypt($value, $this->getCipherKey()));
    }

    /**
     * Разшашифровать значение
     **/
    protected function decryptValue($value)
    {
        $this->buildCipher();

        $value = $this->cipher->decrypt($this->decodeUrlSafeB64($value), $this->getCipherKey());
        if (function_exists('gzdecode')) {
            $value = gzdecode($value);
        }

        return $value;
    }

    private function decodeUrlSafeB64($input)
    {
        $padLength = 4 - strlen($input) % 4;
        $input .= str_repeat('=', $padLength);

        return base64_decode(strtr($input, '-_', '+/'));
    }

    private function encodeUrlSafeB64($input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    public function shouldDecrypt($cookieName, $cookieValue)
    {
        return strpos($cookieValue, self::SIGN_PREFIX) === 0;
    }

    protected function prependSign($value)
    {
        return self::SIGN_PREFIX . $value;
    }

    protected function removeSign($value)
    {
        return substr($value, strlen(self::SIGN_PREFIX));
    }

    public function getCipherKey()
    {
        return $this->cipherKey;
    }
}
