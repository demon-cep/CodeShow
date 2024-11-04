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
class ByLocation extends \Bitrix\Sale\Services\Base\Restriction
{
    public static $easeSort = 200;

    /**
     * Получить название
     * @return string|null
     */
    public static function getClassTitle()
    {
        return Loc::getMessage('REGIONALITY_RSTR_BY_LOCATION_NAME');
    }

    /**
     * Получить описания
     * @return string|null
     */
    public static function getClassDescription()
    {
        return Loc::getMessage('REGIONALITY_RSTR_BY_LOCATION_DESCRIPT');
    }

    /**
     * Class для хранения значения местположения
     * @return string
     */
    protected static function getD2LClass()
    {
        return '\Stroy\Regionality\Orm\PaySystemlocationTable';
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
        if (intval($paySystemId) <= 0) {
            return true;
        }

        if ($locationCode == '') {
            return false;
        }

        try {
            $class = static::getD2LClass();
            return $class::checkConnectionExists(
                intval($paySystemId),
                $locationCode,
                [
                    'LOCATION_LINK_TYPE' => 'AUTO'
                ]
            );
        } catch (\Bitrix\Sale\Location\Tree\NodeNotFoundException $e) {
            return false;
        }
    }

    /**
     * Метод подготовит необходимые данные для проверки ограничения, далее эти данные передаются в метод check.
     * @param Entity $entity
     * @return string|null
     */
    protected static function extractParams(Entity $entity)
    {
        $obLocation = \Stroy\Regionality\Location\CurrentLocation::getInstance();
        $locationCode = $obLocation->getMainCity()->getResult()->get('LOC_CODE');

        return $locationCode;
    }

    protected static function prepareParamsForSaving(array $params = [], $paySystemId = 0)
    {
        $class = static::getD2LClass();
        if ($paySystemId > 0) {
            $arLocation = [];

            if (!!\CSaleLocation::isLocationProEnabled()) {
                if ($params["LOCATION"][$class::DB_LOCATION_FLAG] <> '') {
                    $LOCATION1 = explode(':', $params["LOCATION"][$class::DB_LOCATION_FLAG]);
                }

                if ($params["LOCATION"][$class::DB_GROUP_FLAG] <> '') {
                    $LOCATION2 = explode(':', $params["LOCATION"][$class::DB_GROUP_FLAG]);
                }
            }

            if (isset($LOCATION1) && is_array($LOCATION1) && count($LOCATION1) > 0) {
                $arLocation[$class::DB_LOCATION_FLAG] = [];
                $locationCount = count($LOCATION1);

                for ($i = 0; $i < $locationCount; $i++) {
                    if ($LOCATION1[$i] <> '') {
                        $arLocation[$class::DB_LOCATION_FLAG][] = $LOCATION1[$i];
                    }
                }
            }

            if (isset($LOCATION2) && is_array($LOCATION2) && count($LOCATION2) > 0) {
                $arLocation[$class::DB_GROUP_FLAG] = [];
                $locationCount = count($LOCATION2);

                for ($i = 0; $i < $locationCount; $i++) {
                    if ($LOCATION2[$i] <> '') {
                        $arLocation[$class::DB_GROUP_FLAG][] = $LOCATION2[$i];
                    }
                }
            }

            $class::resetMultipleForOwner($paySystemId, $arLocation);
        }

        return [];
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
                'TYPE' => 'LOCATION_MULTI_PAYSYSTEM'
            ]
        ];

        if ($paySystemId > 0) {
            $result['LOCATION']['PAY_SYSTEM_ID'] = $paySystemId;
        }

        return $result;
    }

    public static function save(array $fields, $restrictionId = 0)
    {
        $fields["PARAMS"] = self::prepareParamsForSaving($fields["PARAMS"], $fields["SERVICE_ID"]);
        return parent::save($fields, $restrictionId);
    }

    public static function delete($restrictionId, $paySystemId = 0)
    {
        $class = static::getD2LClass();
        $class::resetMultipleForOwner($paySystemId);
        return parent::delete($restrictionId);
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

    /**
     * @param array $restrictionFields
     * @param $leftMargin
     * @param $rightMargin
     * @return array
     */
    protected static function getLocationsCompat(array $restrictionFields, $leftMargin, $rightMargin)
    {
        return [];
    }
}
