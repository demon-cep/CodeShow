<?php

namespace Stroy\Regionality\Orm;

use Bitrix\Main\ORM\Data\DataManager,
    Bitrix\Main\ORM\Fields\IntegerField,
    Bitrix\Main\ORM\Fields\StringField,
    Bitrix\Main\ORM\Fields\Relations\Reference,
    Bitrix\Main\ORM\Query\Join,
    Bitrix\Main\ORM\Fields\Validators\LengthValidator;

/**
 * Для работы с сущностью ХБ "Goroda"
 * Class MainCitiesTable
 **/
class GorodaTable extends DataManager
{
    /**
     * Returns DB table name for entity.
     * @return string
     */
    public static function getTableName(): string
    {
        return 'b_goroda';
    }

    /**
     * Returns entity map definition.
     * @return array
     */
    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new StringField('UF_NAME', [
                'size' => 200
            ]),
            new StringField('UF_FIAS_ID', [
                'size' => 100
            ]),
            new StringField('UF_XML_ID', [
                'size' => 100
            ]),
            new StringField('UF_VERSION', [
                'size' => 100
            ]),
            new StringField('UF_DOMEN', [
                'validation' => function() {
                    return [
                        new LengthValidator(null, 100),
                    ];
                },
            ]),
            new StringField('UF_OSNOVNOYSLKAD', [
                'size' => 100
            ]),
            new StringField('UF_LOC_CODE', [
                'size' => 100
            ]),
            new StringField('UF_PRICE_TYPE_PREFIX', [
                'size' => 100
            ]),
            new StringField('UF_IS_IN_CITY_POPUP', [
                'size' => 100
            ]),
            new IntegerField('UF_SORT_IN_CITY_POPUP'),
            new StringField('UF_PRICE_TYPE_PREFIX', [
                'size' => 100
            ]),
            new StringField('UF_TSENADOSTAVKI', [
                'size' => 100
            ]),
            new StringField('UF_YANDEXDELIVERYAVA', [
                'size' => 100
            ]),
            new Reference('LOCATION',
                '\Bitrix\Sale\Location\LocationTable',
                Join::on('this.' . 'UF_LOC_CODE', 'ref.CODE')
            ),
            new Reference('LOCDATA',
                LocationsDataTable::class,
                Join::on('this.' . 'UF_LOC_CODE', 'ref.UF_LOC_CODE')
            ),
        ];
    }
}
