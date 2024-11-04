<?php

namespace Stroy\Regionality\Orm;

/**
 * Class PaySystemlocationTable
 **/
class PaySystemlocationTable extends \Bitrix\Sale\Location\Connector
{
    /**
     * Returns DB table name for entity.
     * @return string
     */
    public static function getTableName(): string
    {
        return 'stroy_regionality_paysystem_location';
    }

    public static function getLinkField(): string
    {
        return 'PAY_SYSTEM_ID';
    }

    public static function getLocationLinkField(): string
    {
        return 'LOCATION_CODE';
    }

    public static function getTargetEntityName(): string
    {
        return 'Bitrix\Sale\Internals\PaySystemActionTable';
    }

    public static function getMap(): array
    {
        return [
            'PAY_SYSTEM_ID' => [
                'data_type' => 'integer',
                'required' => true,
                'primary' => true
            ],
            'LOCATION_CODE' => [
                'data_type' => 'string',
                'required' => true,
                'primary' => true
            ],
            'LOCATION_TYPE' => [
                'data_type' => 'string',
                'default_value' => static::DB_LOCATION_FLAG,
                'required' => true,
                'primary' => true
            ],
            // virtual
            'LOCATION' => [
                'data_type' => '\Bitrix\Sale\Location\Location',
                'reference' => [
                    '=this.LOCATION_CODE' => 'ref.CODE',
                    '=this.LOCATION_TYPE' => ['?', static::DB_LOCATION_FLAG]
                ]
            ]
        ];
    }
}
