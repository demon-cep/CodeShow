<?php

namespace Stroy\Regionality\Orm;

use Bitrix\Main\ORM\Data\DataManager,
    Bitrix\Main\ORM\Fields\IntegerField,
    Bitrix\Main\ORM\Fields\StringField;

/**
 * Class SubdomainsTable
 **/
class SubdomainsTable extends DataManager
{
    /**
     * Returns DB table name for entity.
     * @return string
     */
    public static function getTableName(): string
    {
        return 'stroy_regionality_subdomains';
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
                'size' => 100
            ])
        ];
    }
}
