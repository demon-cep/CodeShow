<?php

namespace Stroy\Regionality\Event\PaySystem\Restrictions;

use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\Internals\Entity;
use Bitrix\Sale\Shipment;
use Bitrix\Sale\Services\PaySystem\Restrictions;

Loc::loadMessages(__FILE__);

/**
 * Class ByLocation
 * /bitrix/modules/sale/lib/delivery/restrictions/bylocation.php
 * /bitrix/modules/sale/lib/services/paysystem/restrictions/
 * b_sale_service_rstr таблица с ограничение платёжных систем
 */
class ExcludeLocation extends ByLocation
{
    public static $easeSort = 200;

    /**
     * Получить название
     * @return string|null
     */
    public static function getClassTitle()
    {
        return Loc::getMessage('REGIONALITY_RSTR_EX_LOCATION_NAME');
    }

    /**
     * Получить описания
     * @return string|null
     */
    public static function getClassDescription()
    {
        return Loc::getMessage('REGIONALITY_RSTR_EX_LOCATION_DESCRIPT');
    }

    /**
     * Class для хранения значения местположения
     * @return string
     */
    protected static function getD2LClass()
    {
        return '\Stroy\Regionality\Orm\PaySystemlocationExcludeTable';
    }

    /**
     * Проверка
     * Эта функция должна принимать только CODE местоположения, а не ID, являясь частью API.
     * @param string $locationCode - CODE местоположения
     * @param array $restrictionParams - Параметры ограничения
     * @param int $paySystemId - Id платёжной системы
     * @return bool
     */
    public static function check($locationCode, array $restrictionParams, $paySystemId = 0)
    {
        return !parent::check($locationCode, $restrictionParams, $paySystemId);
    }

    /**
     * Возвращать массив параметров ограничения.
     * @param $paySystemId
     * @return array|array[]
     */
    public static function getParamsStructure($paySystemId = 0)
    {
        $result = [
            'LOCATION' => [
                'TYPE' => 'LOCATION_MULTI_EXCLUDE_PAYSYSTEM'
            ]
        ];

        if ($paySystemId > 0) {
            $result['LOCATION']['PAY_SYSTEM_ID'] = $paySystemId;
        }

        return $result;
    }

    /**
     * @param Shipment $shipment
     * @param array $restrictionFields
     * @return array
     */
    public static function filterServicesArray(Shipment $shipment, array $restrictionFields)
    {
        return [];
    }
}
