<?php

namespace Stroy\Core\Loyalty;

use mysql_xdevapi\Exception;
use Stroy\Core\Api\Api1C;
use Stroy\Core\Helpers\Str;

Class ApiClient {
    protected $apiUrl;
    protected $client=null;

    public function __construct()
    {
        if (ENV == "prod") {
            $this->apiUrl = Api1C::API_URL;
        } else {
            $this->apiUrl = Api1C::API_URL_TEST;
        }
    }

    public function QueryClientRegistration($identifier) {
        $res=$this->sendRequest(
            'QueryClientRegistration',
            [
                'Identifier'=>$identifier
            ]
        );

        return $res;
    }

    /**
     * Запрос на получение авторизационных данных Loymax
     *
     * @param string $identifier
     * @return array
     * @throws \SoapFault
     */
    public function authLoymaxInfo(string $identifier): array
    {
        return $this->sendRequest('AuthLoymaxInfo',
            [
                'Identifier' => $identifier
            ]
        );
    }

    /**
     * Запрос на получение информации о бонусных картах клиентах
     *
     * @param string $identifier
     * @return array
     * @throws \SoapFault
     */
    public function cardClientInfo(string $identifier): array
    {
        return $this->sendRequest('CardClientInfo',
            [
                'Identifier' => $identifier
            ]
        );
    }

    public function CheckingClientRegistration($identifier) {
        /** @var Результат запроса $res */
        $res=null;

        //стартуем кеширование
        $cache = new \CPHPCache();
        $cache_time = 60;
        $cache_id='CheckingClientRegistration_'.$identifier;
        $cache_path='newLoyaltycard';
        if ($cache_time > 0 && $cache->InitCache($cache_time, $cache_id, $cache_path)) {
            //кеш стартанул, получаем из него данные
            $cacheRes = $cache->GetVars();
            if (is_array($cacheRes)) {
                $res=$cacheRes['res'];
            }
        }

        //если в кеше ни чего нет, то пускаем запрос
        if (is_null($res)) {
            $res=$this->sendRequest(
                'CheckingClientRegistration',
                [
                    'Identifier'=>$identifier
                ]
            );

            //сохраняем данные в кеш
            if ($cache_time > 0) {
                $cache->StartDataCache($cache_time, $cache_id, $cache_path);
                $cache->EndDataCache(array("res"=>$res));
            }
        }

        return $res;
    }
    public function QueryClientRegistrationConfirm($identifier,$code) {
        $res=$this->sendRequest(
            'QueryClientRegistrationConfirm',
            [
                'Identifier'=>$identifier,
                'ConfirmCode'=>$code
            ]
        );
        return $res;
    }

    /**
     * @param $cardNumber Номер карты
     * @param $identifier Идентификатор клиента (телефон или номер карты)
     * @return mixed
     * @throws \SoapFault
     */
    public function BlockCardClient($cardNumber,$identifier) {
        $res=$this->sendRequest(
            'BlockCardClient',
            [
                'CardNumber'=>$cardNumber,
            ]
        );
		//сбрасываем кеш метода BonusBalanceClient
		$this->cleadCardDadaCache($identifier);

        return $res;
    }

    public function BonusBalanceClient($identifier) {
        /** @var Результат запроса $res */
        $res=null;

        //стартуем кеширование
        //ВНИМАНИЕ! ТУТ КЕШ ТЕГИРОВАННЫЙ
        $cache = new \CPHPCache();
        $cache_time = 180;
        $cache_id='BonusBalanceClient_'.$identifier;
        $cache_path='/newLoyaltycard';
        if ($cache_time > 0 && $cache->InitCache($cache_time, $cache_id, $cache_path)) {
            //кеш стартанул, получаем из него данные
            $cacheRes = $cache->GetVars();
            if (is_array($cacheRes)) {
                $res = $cacheRes['res'];
            }
        } else {
            $cache->StartDataCache();
            global $CACHE_MANAGER;
            $CACHE_MANAGER->StartTagCache($cache_path);
            $CACHE_MANAGER->RegisterTag("BonusBalanceClient_".$identifier);

            //получаем данные
            $res = $this->sendRequest(
                'BonusBalanceClient',
                [
                    'Identifier'=>$identifier,
                ]
            );
            $CACHE_MANAGER->EndTagCache();
            $cache->EndDataCache(['res'=>$res]);

        }

        return $res;
    }

    public function BonusHistoryHTML($identifier) {
        /** @var Результат запроса $res */
        $res=null;

        //стартуем кеширование
        $cache = new \CPHPCache();
        $cache_time = 60;
        $cache_id='BonusHistoryHTML_'.$identifier;
        $cache_path='/newLoyaltycard';
        if ($cache_time > 0 && $cache->InitCache($cache_time, $cache_id, $cache_path)) {
            //кеш стартанул, получаем из него данные
            $cacheRes = $cache->GetVars();
            if (is_array($cacheRes)) {
                $res=$cacheRes['res'];
            }
        }

        //если в кеше ни чего нет, то пускаем запрос
        if (is_null($res)) {
            $res=$this->sendRequest(
                'BonusHistoryHTML',
                [
                    //'Identifier'=>$identifier, //юзернейм, не ошибись, правильно отправлять CardNumber, не смотря на описание АПИ
                    'CardNumber'=>$identifier
                ],
                1
            );

            //сохраняем данные в кеш
            if ($cache_time > 0) {
                $cache->StartDataCache($cache_time, $cache_id, $cache_path);
                $cache->EndDataCache(array("res"=>$res));
            }
        }


        return $res;
    }

    public function SaveClientInfo(
        $phone, $confirmCode, $family, $name,
        $patronym, $birthDate, $sex, $vk, $instagram,
        $notConsentToMailing = null
    ) {
        $res = $this->sendRequest(
            'SaveClientInfo',
            [
                'Phone' => $phone,
                'ConfirmCode' => $confirmCode,
                'Family' => Str::clean($family),
                'Name' => Str::clean($name),
                'Patronym' => Str::clean($patronym),
                'BirthDate' => $birthDate,
                'Sex' => $sex,
                'vk' => $vk,
                'instagram' => $instagram
            ]
        );

        //отдельно обновляем согласие на рассылку
        if (!is_null($notConsentToMailing)) {
            $consentMailingRes = $this->sendRequest(
                'ConsentToMailing',
                [
                    'Identifier' => $phone,
                    'NotConsentToMailing' => $notConsentToMailing,
                ]
            );
        }

        if ($res['SavedInformation']) {
            //сбрасываем кеш метода BonusBalanceClient
            $this->cleadCardDadaCache($phone);
        }

        return $res;
    }

    public function ConsentToMailing($identifier,$notConsentToMailing) {
		$res=$this->sendRequest(
			'ConsentToMailing',
			[
				'Identifier'=>$identifier,
				'NotConsentToMailing'=>$notConsentToMailing,
			]
		);
		if (empty($res['Description'])) {//пустой $res['Description'] - признак успеха операции
			//сбрасываем кеш метода BonusBalanceClient
			$this->cleadCardDadaCache($identifier);
		}
		return $res;
	}

	/**
	 * Очищает кеш данных всех карт по идентификатору
	 *
	 * @param $identifier
	 */
    protected function cleadCardDadaCache($identifier) {
		$data=$this->BonusBalanceClient($identifier);
		global $CACHE_MANAGER;
		$CACHE_MANAGER->ClearByTag("BonusBalanceClient_".$identifier);
		foreach ($data['Cards'] AS $arCard) {
			$CACHE_MANAGER->ClearByTag("BonusBalanceClient_".$arCard['Card']);
		}
	}

    /**
     * @param $method Метод АПИ
     * @param $params Параметры АПИ
     * @param $version Версия (2 - новые запросы, 1 - старые)
     * @return mixed
     * @throws \SoapFault
     * @throws \Exception
     */
    protected function sendRequest($method,$params,$version=2) {

        \Bitrix\Main\Diag\Debug::writeToFile(
            array(
                'METHOD'=>$method,
                'PARAMS'=>$params,
                'VERSION'=>$version,
                'URL'=>$_SERVER["SCRIPT_URL"],
                'REFERER'=>$_SERVER['HTTP_REFERER']

            ),
            date("d.m.Y H:i:s"),
            "bitrix/tmp/loyaltycardReqests.log"
        );

        try {
            //оформляем параметры в зависи
            if ($version==2) {
                $apiParams=['Data'=>json_encode($params)];
            } else {
                $apiParams=$params;
            }

            $apiRes=$this->getClient()->$method(
                $apiParams
            );

            $res=$apiRes->return;

            //если это "старый" метод, то убираем из JSON перенос строки и табуляции, иначен не парсится
            //не знаю на всех ли так запросах, используется только BonusHistoryHTML, но поломать парсинг это не должно
            if ($version==1) {
                $res=preg_replace('/\n*\t*/','',$res);
            }

            $res=json_decode($res,true);


            if (!$res) {
                throw new \Exception('Сервис не вернул JSON строку');
            }

            //получаем сообщение для пользователя
            if ($version==2) {
                //только для второй версии

                //в Description что-то есть только если возникла ошибка
                if (!empty($res['Description'])) {
                    //ищем текст ошибки по коду
                    $message=$this->getUserMessage($res['Description']);
                    if (!is_null($message)) {
                        //если нашли то добавляем в сообщения
                        $res['Message']=$message;
                    } else {
                        //если НЕ нашли, то в Description может содержаться и текст
                        //на самом деле нет, оставил на всякий случай, т.к. в ранних версиях АПИ в этом поле приходило просто сообщение
                        //сейчас если все в продяке, то Description пустое
                        $res['Message']=$res['Description'];
                    }
                }
            }

            return $res;
        } catch (\SoapFault $exception) {
            \Bitrix\Main\Diag\Debug::writeToFile(
                array(
                    'METHOD' => $method,
                    'PARAMS' => $params,
                    'ERROR' => $exception->getMessage(),
                ),
                'Error ' . date("d.m.Y H:i:s"),
                "bitrix/tmp/loyaltycardReqests.log"
            );

            throw $exception;
        }
    }
    protected function getClient() {
        if (is_null($this->client)) {
            $this->client=new \SoapClient(
                $this->apiUrl,
                [
                    "trace" => 0,
                    //"connection_timeout" => 60 //все равно настройки берутся из .ini default_socket_timeout на прямую
                ]
            );
        }
        return $this->client;
    }

    protected function getUserMessage($code) {
        $errorCodes=[
            "Data error"=>'Ошибка передачи данных. Повторите операцию позже, либо обратитесь в службу поддержки',
            "Balance error"=>'Ошибка запроса баланса. Повторите операцию позже, либо обратитесь в службу поддержки',
            "No phone"=>'При выдаче карты не был указан номер телефона(либо указан с ошибкой)',
            "Service not available"=>'Сервис клиентской регистрации временно недоступен. Повторите операцию позже, либо обратитесь в службу поддержки',
            "Identifier error"=>'Ошибка идентификации клиента. Повторите операцию позже, либо обратитесь в службу поддержки',
            "SMS error"=>'Ошибка отправки кода подтверждения. Повторите операцию позже, либо обратитесь в службу поддержки',
            "No client"=>'Клиент не найден. Повторите операцию позже, либо обратитесь в службу поддержки',
            "Confirm code error"=>'Неизвестная ошибка проверки кода подтверждения. Повторите операцию позже, либо обратитесь в службу поддержки',
            "Confirm code invalid"=>'Неверный код подтверждения. Проверьте код и повторите операцию, либо обратитесь в службу поддержки',
            "Card set error"=>'Ошибка привязки карты к номеру телефона. Повторите операцию позже, либо обратитесь в службу поддержки',
            "Fields error"=>'Не все поля анкеты заполнены',
            "Birth date error"=>'Неверный формат даты рождения. Проверьте дату рождения и повторите операцию, либо обратитесь в службу поддержки',
            "Unknown error"=>'Неизвестная ошибка. Повторите операцию позже, либо обратитесь в службу поддержки',
            "Block error"=>'Ошибка блокировка карты. Повторите операцию позже, либо обратитесь в службу поддержки',
            "No card"=>'Карта не найдена. Проверьте номер карты и повторите операцию еще раз',
            "Card block"=>'Карта заблокирована. Операции невозможны',
            "No card phone"=>'К номеру телефона не привязано ни одной клубной карты',
            "Card no registration"=>'Карта не зарегистрирована',
            "Phone error"=>'Для участия в программе лояльности введите номер мобильного телефона'
        ];

        return $errorCodes[$code];
    }
}