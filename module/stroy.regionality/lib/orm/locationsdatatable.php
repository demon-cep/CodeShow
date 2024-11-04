<?php

namespace Stroy\Regionality\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\ArrayField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Sale\Location\LocationTable;

/**
 * Class SubdomainsTable
 **/
class LocationsDataTable extends DataManager
{
    /**
     * Returns DB table name for entity.
     * @return string
     */
    public static function getTableName(): string
    {
        return 'stroy_regionality_locations_data';
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
                'size' => 50
            ]),
            new StringField('UF_LOC_CODE', [
                'size' => 50
            ]),
            new StringField('UF_FIAS_ID', [
                'size' => 200
            ]),
            new IntegerField('UF_ZIP_CODE', [
                'size' => 50
            ]),
            new FloatField('UF_LATITUDE', [
            ]),
            new FloatField('UF_LONGITUDE', [
            ]),
            new IntegerField('UF_MAIN_CITY_SUBDOMAIN', [
                'size' => 50
            ]),
            new IntegerField('UF_SUBDOMAIN', [
                'size' => 50
            ]),
            new StringField('UF_PHONE', [
                'size' => 50
            ]),
            (new ArrayField('UF_DECLENSIONS'))->configureSerializationPhp(),
            new StringField('UF_TIMEZONE', [
                'size' => 50
            ]),
            new ArrayField('UF_PARAMETER'),
            new TextField('UF_SEARCHABLE_CONTENT', [
                'required' => true
            ]),
            new Reference(
                'LOCATION',
                LocationTable::class,
                Join::on('this.' . 'UF_LOC_CODE', 'ref.CODE')
            ),
            new Reference(
                'SUBDOMAIN',
                SubdomainsTable::class,
                Join::on('this.' . 'UF_SUBDOMAIN', 'ref.ID')
            ),
            new Reference(
                'MAIN_CITY',
                GorodaTable::class,
                Join::on('this.' . 'UF_LOC_CODE', 'ref.UF_LOC_CODE')
            ),
            new Reference(
                'SATELLITE_CITY',
                GorodaSputnikiTable::class,
                Join::on('this.' . 'UF_LOC_CODE', 'ref.UF_LOC_CODE')
            ),
        ];
    }
    
    /**
     * getList - Переопределяем данные в запросе, если нет кеша, добавляем
     *
     * @param  mixed $parameters
     * @return void
     */
    public static function getList(array $parameters = [])
    {
        if (empty($parameters['cache'])) {
            $parameters['cache'] = ['ttl' => 3600 * 24, 'cache_joins' => true];
        }

        return parent::getList($parameters);
    }

    /**
     * Очистить таблицу
     * @return void
     */
    public static function truncateTable(): void
    {
        $connection = \Bitrix\Main\Application::getConnection();
        $connection->truncateTable(self::getTableName());
    }

    /**
     * Получим город по домену
     * @param string $domain
     */
    public static function getByDomain(string $domain)
    {
        $arResult = LocationsDataTable::getlist([
            'order' => ['ID' => 'ASC'],
            'filter' => [
                'UF_MAIN_CITY_SUBDOMAIN' => 1,
                '=SUBDOMAIN_NAME' => $domain
            ],
            'select' => [
                '*',
                'SUBDOMAIN_NAME' => 'SUBDOMAIN.UF_NAME',
            ],
            'cache' => ['ttl' => 3600, 'cache_joins' => true]
        ])->fetch();

        return $arResult;
    }

    /**
     * Получим данные по фильрру
     * @param array $arFilter
     * @param bool $fetchAll
     * @return array
     */
    public static function getExList(array $arFilter = [], bool $fetchAll = false): array
    {
        if (!$arFilter) {
            return [];
        }

        $arResult = [];
        $arLocationPath = [];

        $obLocations = LocationsDataTable::getlist([
            'order' => ['ID' => 'ASC'],
            'filter' => $arFilter,
            'select' => [
                '*',
                'SUBDOMAIN_ID' => 'SUBDOMAIN.ID',
                'SUBDOMAIN_NAME' => 'SUBDOMAIN.UF_NAME',
                'LOCATION_ID' => 'LOCATION.ID',
                'LOCATION_CODE' => 'LOCATION.CODE',
                'LOCATION_LEFT_MARGIN' => 'LOCATION.LEFT_MARGIN',
                'LOCATION_RIGHT_MARGIN' => 'LOCATION.RIGHT_MARGIN',
                'LOCATION_TYPE_ID' => 'LOCATION.TYPE_ID',
                'CITY_YANDEXDELIVERYAVA' => 'MAIN_CITY.UF_YANDEXDELIVERYAVA',
                'CITY_XML_ID' => 'MAIN_CITY.UF_XML_ID',
                'SATELLITE_CITY_XML_ID' => 'SATELLITE_CITY.UF_XML_ID',
            ],
            'limit' => $fetchAll ? '' : 1,
            'cache' => ['ttl' => 3600, 'cache_joins' => true]
        ]);
        while ($arItem = $obLocations->fetch()) {
            if ($arItem['LOCATION_ID'] && !$arLocationPath[$arItem['LOCATION_ID']]) {
                $arLocationPath[$arItem['LOCATION_ID']] = [
                    'ID' => $arItem['LOCATION_ID'],
                    'CODE' => $arItem['LOCATION_CODE'],
                    'LEFT_MARGIN' => $arItem['LOCATION_LEFT_MARGIN'],
                    'RIGHT_MARGIN' => $arItem['LOCATION_RIGHT_MARGIN'],
                    'TYPE_ID' => $arItem['LOCATION_TYPE_ID']
                ];
            }

            if ($arItem['UF_DECLENSIONS']) {
                foreach ($arItem['UF_DECLENSIONS'] as $key => $item) {
                    unset($arItem['UF_DECLENSIONS'][$key]);
                    $item = json_decode($item, true);
                    $arItem['UF_DECLENSIONS'] += $item;
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
