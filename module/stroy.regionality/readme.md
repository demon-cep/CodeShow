====== Методы по работе с местоположением ======
1) Получить текущее местоположение объект CurrentLocation::getInstance
$obLocation = \Stroy\Regionality\Location\CurrentLocation::getInstance();

#1.1 Объект местоположение пользователя определённого по Ip
$obLocation->getLocation();
#1.1.1 Название местоположения
$obLocation->getLocation()->getResult()->get('CITY');
#1.1.2 Название местоположения code bitrix
$obLocation->getLocation()->getResult()->get('LOC_CODE');
#1.1.3 Получить все доступные данные
$obLocation->getLocation()->getResult()->getValues();

#1.2 Объект основного города
$obLocation->getMainCity();
#1.2.1 Название местоположения
$obLocation->getMainCity()->getResult()->get('NAME');
#1.2.2 Название местоположения code bitrix
$obLocation->getMainCity()->getResult()->get('LOC_CODE');
#1.2.3 Fias id города
$obLocation->getMainCity()->getResult()->get('FIAS_ID');
#1.2.4 Получить все доступные данные
$obLocation->getMainCity()->getResult()->getValues();

#1.3 Объект города сателлита
$obLocation->getSatelliteCity();
#1.3.1 Название местоположения
$obLocation->getSatelliteCity()->getResult()->get('NAME');
#1.3.2 Название местоположения code bitrix
$obLocation->getSatelliteCity()->getResult()->get('LOC_CODE');
#1.3.3 Fias id города
$obLocation->getSatelliteCity()->getResult()->get('FIAS_ID');
#1.3.4 Получить все доступные данные
$obLocation->getSatelliteCity()->getResult()->getValues();

2) Получить текущее местоположение данные из сессии == CurrentLocation::getInstance
$arStroyLandiya = \Bitrix\Main\Application::getInstance()->getSession()->get('STROYLANDIYA');

3) Сбросить все данные очистить кеш
\Stroy\Regionality\Helpers\Request::deleteUserCookies();
unset($_SESSION['STROYLANDIYA']);

4) Изменить текущие местоположение с перерасчётом и сохранением в кеш[session][cookies]
$arParams - текущее положение пользователя

$arParams = [
    'IP' => '37.112.153.32',
    'ZIP_CODE' => '450000',
    'COUNTRY' => 'Россия',
    'COUNTRY_CODE' => 'RU',
    'REGION' => 'Республика Башкортостан',
    'CITY' => 'Уфа',
    'LONGITUDE' => '55.95873',
    'LATITUDE' => '54.73515'
];
$obLocation = \Stroy\Regionality\Location\CurrentLocation::getInstance();
$obLocation->setMainCity($arParams);
