<?php

namespace Stroy\Regionality\Orm;

use Bitrix\Main\ORM\Data\DataManager,
    Bitrix\Main\ORM\Fields\IntegerField,
    Bitrix\Main\ORM\Fields\StringField,
    Bitrix\Main\ORM\Fields\Relations\Reference,
    Bitrix\Main\ORM\Query\Join;

/**
 * Для работы с сущностью ХБ "GorodaSputniki"
 * Class SatelliteCitiesTable
 **/
class GorodaSputnikiTable extends DataManager
{
    /**
     * Returns DB table name for entity.
     * @return string
     */
    public static function getTableName(): string
    {
        return 'b_goroda_sputniki';
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
            new StringField('UF_XML_ID', [
                'size' => 100
            ]),
            new StringField('UF_FIAS_ID', [
                'size' => 100
            ]),
            new StringField('UF_LOC_CODE', [
                'size' => 100
            ]),
            new StringField('UF_XML_GOROD', [
                'size' => 100
            ]),
            new Reference('GORODA',
                GorodaTable::class,
                Join::on('this.' . 'UF_XML_GOROD', 'ref.UF_XML_ID')
            ),
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

    /**
     * Получим данные по фильрру
     * @param array $arFilter
     * @param bool $fetchAll
     * @return array
     */
    public static function getExList(array $arFilter = [], bool $fetchAll = false): array
    {
        $arResult = [];
        $arLocationPath = [];

        $obGorodaSputniki = GorodaSputnikiTable::getlist([
            'order' => ['ID' => 'ASC'],
            'filter' => $arFilter,
            'select' => [
                '*',
                'GORODA_NAME' => 'GORODA.UF_NAME',
                'GORODA_XML_ID' => 'GORODA.UF_XML_ID',
                'GORODA_OSNOVNOYSLKAD' => 'GORODA.UF_OSNOVNOYSLKAD',
                'GORODA_FIAS_ID' => 'GORODA.UF_FIAS_ID',
                'GORODA_LOC_CODE' => 'GORODA.UF_LOC_CODE',
                'LOCATION_ID' => 'LOCATION.ID',
                'LOCATION_CODE' => 'LOCATION.CODE',
                'LOCATION_LEFT_MARGIN' => 'LOCATION.LEFT_MARGIN',
                'LOCATION_RIGHT_MARGIN' => 'LOCATION.RIGHT_MARGIN',
                'LOCATION_TYPE_ID' => 'LOCATION.TYPE_ID',
                'LOCDATA_FIAS_ID' => 'LOCDATA.UF_FIAS_ID',
                'LOCDATA_LATITUDE' => 'LOCDATA.UF_LATITUDE',
                'LOCDATA_LONGITUDE' => 'LOCDATA.UF_LONGITUDE',
                'LOCDATA_ZIP_CODE' => 'LOCDATA.UF_ZIP_CODE',
                'LOCDATA_SUBDOMAIN' => 'LOCDATA.UF_SUBDOMAIN',
                'LOCDATA_PHONE' => 'LOCDATA.UF_PHONE',
                'LOCDATA_DECLENSIONS' => 'LOCDATA.UF_DECLENSIONS'
            ],
            'limit' => $fetchAll ? '' : 1,
            'cache' => ['ttl' => 3600, 'cache_joins' => true]
        ]);
        while ($arItem = $obGorodaSputniki->fetch()) {
            if ($arItem['LOCATION_ID'] && !$arLocationPath[$arItem['LOCATION_ID']]) {
                $arLocationPath[$arItem['LOCATION_ID']] = [
                    'ID' => $arItem['LOCATION_ID'],
                    'CODE' => $arItem['LOCATION_CODE'],
                    'LEFT_MARGIN' => $arItem['LOCATION_LEFT_MARGIN'],
                    'RIGHT_MARGIN' => $arItem['LOCATION_RIGHT_MARGIN'],
                    'TYPE_ID' => $arItem['LOCATION_TYPE_ID']
                ];
            }

            if ($arItem['LOCDATA_DECLENSIONS']) {
                foreach ($arItem['LOCDATA_DECLENSIONS'] as $key => $item) {
                    unset($arItem['LOCDATA_DECLENSIONS'][$key]);
                    $item = json_decode($item, true);
                    $arItem['LOCDATA_DECLENSIONS'] += $item;
                }
            }

            $arResult[] = $arItem;
        }

        #LOCATION_PATCH
        if ($arLocationPath) {
            $obLocationPath = \Bitrix\Sale\Location\LocationTable::getPathToMultipleNodes($arLocationPath, [
                'filter' => ['NAME.LANGUAGE_ID' => LANGUAGE_ID],
                'select' => ['CODE', 'TYPE_ID', 'LNAME' => 'NAME.NAME']
            ]);
            while ($arItem = $obLocationPath->Fetch()) {
                if (!is_array($arItem['PATH']) || !$arItem['PATH'] || !$arLocationPath[$arItem['ID']]) {
                    continue;
                }

                $arItem['PATH'] = array_values($arItem['PATH']);
                $arLocationPath[$arItem['ID']]['PATH'] = $arItem['PATH'];
            }
        }

        foreach ($arResult as $key => $arItem) {
            if ($arLocationPath[$arItem['LOCATION_ID']]['PATH']) {
                $arResult[$key]['LOCATION_PATH'] = $arLocationPath[$arItem['LOCATION_ID']]['PATH'];
            }
        }

        if ($arResult && !$fetchAll) {
            $arResult = current($arResult);
        }

        return $arResult;
    }
}
