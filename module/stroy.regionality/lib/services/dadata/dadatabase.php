<?php

namespace Stroy\Regionality\Services\Dadata;

use Bitrix\Main\Web\HttpClient;
use Stroy\Regionality\Handler;

/**
 * Класс формирования запроса
 * Docs https://dadata.ru/api/clean/address/
 */
class DadataBase
{
    protected static ?self $instance = null;
    protected HttpClient $httpClient;
    protected array $result = [];
    protected array $config = [];

    public function __construct()
    {
        $this->httpClient = new HttpClient();
        $modParams = Handler::getInstance()->getOptions();

        $this->config = [
            'URL_SUGGESTIONS' => 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/',
            'URL_CLEANER' => 'https://cleaner.dadata.ru/api/v1/',
            'URL' => 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/',
            'TOKEN' => $modParams['DADATA']['TOKEN'] ?: '',
            'SECRET_KEY' => $modParams['DADATA']['SECRET_KEY'] ?: ''
        ];
    }

    /**
     * Получить значения конфигурации
     * @param string $name
     * @return string | mixed
     */
    protected function getConfig(string $name)
    {
        if (isset($this->config[$name])) {
            return $this->config[$name];
        }

        return null;
    }

    /**
     * Установить значения конфигурации
     * @param string $name
     * @param mixed $value
     * @return bool
     */
    protected function setConfig(string $name, $value): bool
    {
        $oldValue = $this->getConfig($name);
        if ($oldValue != $value || ($oldValue === null && $value !== null)) {
            $this->config[$name] = $value;
            return true;
        }

        return false;
    }

    /**
     * Curl run request
     * @param $apiMethod
     * @param $httpMethod
     * @param $arParams
     * @return array
     */
    protected function call($apiMethod = null, $httpMethod = 'GET', $arParams = []): array
    {
        if (!$apiMethod) {
            return [
                'RESULT' => [
                    'status' => 'error',
                    'status_code' => '400',
                    'message' => 'Invalid api method'
                ],
                'STATUS' => '400',
                'ERRORS' => []
            ];
        }

        $url = $this->config['URL'] . $apiMethod;
        $header = $this->getHeader();
        $this->httpClient->setHeaders($header);
        $this->httpClient->query($httpMethod, $url, json_encode($arParams));

        $this->result['RESULT'] = json_decode($this->httpClient->getResult(), true);
        $this->result['STATUS'] = $this->httpClient->getStatus();
        $this->result['ERRORS'] = $this->httpClient->getError();

        return $this->result;
    }

    /**
     * Заголовок запроса
     * @return array
     */
    protected function getHeader(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Token ' . $this->config['TOKEN'],
            'X-Secret' => $this->config['SECRET_KEY']
        ];
    }

    /**
     * Очистить
     * @return void
     */
    public function destroy(): void
    {
        self::$instance = null;
        $this->httpClient = new HttpClient();
        $this->result = [];
        $this->config = [];
    }
}
