<?php

namespace Stroy\Regionality\Event\PropBuildList;

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Class CIBlockPropertyDirectory
 * /bitrix/modules/highloadblock/classes/general/prop_directory.php
 * /bitrix/modules/iblock/classes/general/prop*
 */
class IblockPropLocation
{
    private const USER_TYPE = 'IblockPropLocation';

    /**
     * Property type description.
     * @return array
     */
    public static function GetUserTypeDescription(): array
    {
        return [
            'PROPERTY_TYPE' => \Bitrix\Iblock\PropertyTable::TYPE_STRING,
            'USER_TYPE' => self::USER_TYPE,
            'CLASS_NAME' => self::class,
            'DESCRIPTION' => Loc::getMessage('REGIONALITY_DIRECTORY_DESCRIPTION'),
            'GetSettingsHTML' => [self::class, 'GetSettingsHTML'],
            'GetPropertyFieldHtml' => [self::class, 'GetPropertyFieldHtml'],
            'GetPropertyFieldHtmlMulty' => [self::class, 'GetPropertyFieldHtmlMulty'],
            'PrepareSettings' => [self::class, 'PrepareSettings'],
            'AddFilterFields' => [self::class, 'AddFilterFields'],
            'GetAdminListViewHTML' => [self::class, 'GetAdminListViewHTML'],
            'GetPublicViewHTML' => [self::class, 'GetPublicViewHTML'],
            'GetPublicEditHTML' => [self::class, 'GetPublicEditHTML'],
            'GetPublicEditHTMLMulty' => [self::class, 'GetPublicEditHTMLMulty'],
            'GetAdminFilterHTML' => [self::class, 'GetAdminFilterHTML'],
            'GetExtendedValue' => [self::class, 'GetExtendedValue'],
            'GetSearchContent' => [self::class, 'GetSearchContent'],
            'GetUIFilterProperty' => [self::class, 'GetUIFilterProperty'],
            'GetUIEntityEditorProperty' => [self::class, 'GetUIEntityEditorProperty'],
            'GetUIEntityEditorPropertyEditHtml' => [self::class, 'GetUIEntityEditorPropertyEditHtml'],
            'GetUIEntityEditorPropertyViewHtml' => [self::class, 'GetUIEntityEditorPropertyViewHtml'],
        ];
    }

    /**
     * html for show in edit property page.
     * @param array $arProperty Property description.
     * @param array $strHTMLControlName Control description.
     * @param array $arPropertyFields Property fields for edit form.
     * @return string
     */
    public static function GetSettingsHTML($arProperty, $strHTMLControlName, &$arPropertyFields): string
    {
        $arPropertyFields = [
            'HIDE' => ['ROW_COUNT', 'COL_COUNT', 'MULTIPLE_CNT', 'DEFAULT_VALUE', 'WITH_DESCRIPTION'],
            'SET' => ['DEFAULT_VALUE' => '']
        ];

        $selectDir = Loc::getMessage("REGIONALITY_DIRECTORY_DESCRIPTION");

        return <<<"HIBSELECT"
			<tr>
				<td>{$selectDir}:</td>
				<td>------</td>
			</tr>
		HIBSELECT;
    }

    /**
     * html for edit single value.
     * @param array $arProperty Property description.
     * @param array $value Current value.
     * @param array $strHTMLControlName Control description.
     * @return string
     */
    public static function GetPropertyFieldHtml($arProperty, $value, $strHTMLControlName): string
    {
        $value = self::normalizePropValue($value['VALUE']);

        $html = self::getEditHtml($arProperty, $value, $strHTMLControlName);

        return $html;
    }

    /**
     * html for edit multi value.
     * @param array $arProperty Property description.
     * @param array $value Current value.
     * @param array $strHTMLControlName Control description.
     * @return string
     */
    public static function GetPropertyFieldHtmlMulty($arProperty, $value, $strHTMLControlName): string
    {
        $value = self::normalizePropMultValue($value);

        $html = self::getEditHtml($arProperty, $value, $strHTMLControlName);

        return $html;
    }

    /**
     * Prepare settings for property.
     * @param array $arProperty Property description.
     * @return array
     */
    public static function PrepareSettings($arProperty): array
    {
        return $arProperty;
    }

    /**
     * Add values in filter.
     * @param array $arProperty
     * @param array $strHTMLControlName
     * @param array &$arFilter
     * @param bool &$filtered
     * @return void
     */
    public static function AddFilterFields($arProperty, $strHTMLControlName, &$arFilter, &$filtered): void
    {
    }

    /**
     * Returns admin list view html.
     * @param array $arProperty Property description.
     * @param array $value Current value.
     * @param array $strHTMLControlName Control description.
     * @return string
     */
    public static function GetAdminListViewHTML($arProperty, $value, $strHTMLControlName): string
    {
        $value = self::normalizePropValue($value['VALUE']);
        $pref = (($arProperty['MULTIPLE'] == 'Y') ? '' : ' / ');
        $value = $value ? implode($pref, $value) : '';

        return $value;
    }

    /**
     * Return public list view html (module list).
     * @param array $arProperty Property description.
     * @param array $value Current value.
     * @param array $strHTMLControlName Control description.
     * @return string
     */
    public static function GetPublicViewHTML($arProperty, $value, $strHTMLControlName): string
    {
        return '';
    }

    /**
     * Return html for public edit value.
     * @param array $property Property description.
     * @param array $value Current value.
     * @param array $control Control description.
     * @return string
     */
    public static function GetPublicEditHTML($property, $value, $control): string
    {
        return '';
    }

    /**
     * Return html for public edit multi values.
     * @param array $property Property description.
     * @param array $value Current value.
     * @param array $control Control description.
     * @return string
     */
    public static function GetPublicEditHTMLMulty($property, $value, $control): string
    {
        return '';
    }

    /**
     * Return admin filter html.
     * @param array $arProperty Property description.
     * @param array $strHTMLControlName Control description.
     * @return string
     */
    public static function GetAdminFilterHTML($arProperty, $strHTMLControlName): string
    {
        return '';
    }

    /**
     * Returns data for smart filter.
     * @param array $arProperty Property description.
     * @param array $value Current value.
     * @return false
     */
    public static function GetExtendedValue($arProperty, $value)
    {
        return false;
    }

    /**
     * Return property value for search.
     * @param array $arProperty Property description.
     * @param array $value Current value.
     * @param array $strHTMLControlName Control description.
     * @return string
     */
    public static function GetSearchContent($arProperty, $value, $strHTMLControlName): string
    {
        return '';
    }

    /**
     * @param array $property
     * @param array $strHTMLControlName
     * @param array &$field
     * @return void
     */
    public static function GetUIFilterProperty($property, $strHTMLControlName, &$field)
    {
        unset($field['value']);
        $field['type'] = 'string';
    }

    public static function GetUIEntityEditorProperty($settings, $value): ?array
    {
        return [];
    }

    public static function GetUIEntityEditorPropertyEditHtml(array $params = []): string
    {
        return '';
    }

    public static function GetUIEntityEditorPropertyViewHtml(array $params = []): string
    {
        return '';
    }

    /**
     * html
     * @param $arProperty
     * @param $value
     * @param $strHTMLControlName
     * @return string
     */
    private static function getEditHtml($arProperty, $value, $strHTMLControlName): string
    {
        global $APPLICATION;

        ob_start();
        $APPLICATION->IncludeComponent(
            "stroyregionality:sale.location.selector.system",
            "",
            [
                "INPUT_NAME" => 'PROP[' . $arProperty['ID'] . ']',
                "PROP_ID" => $arProperty['ID'],
                "PROP_CODE" => $arProperty['CODE'],
                "PROP_MULTIPLE" => $arProperty['MULTIPLE'],
                "PROP_VALUE" => $value
            ]
        );

        return ob_get_clean();
    }

    /**
     * Normalize property value
     * @param $value
     * @return string
     */
    private static function normalizePropValue($value)
    {
        if (!$value || !is_string($value)) {
            return $value;
        }

        $result = $value ? explode(':', $value) : [];

        return $result;
    }

    /**
     * Normalize property multiple value
     * @param $value
     * @return string
     */
    private static function normalizePropMultValue($value)
    {
        if ($value['VALUE'] || !is_array($value)) {
            return $value;
        }

        $result = [];
        if ($value) {
            foreach ($value as $item) {
                if ($item['VALUE']) {
                    $result = array_merge($result, explode(':', $item['VALUE']));
                }
            }

            if ($result) {
                $result = array_diff($result, ['']);
                $result = array_unique($result);
            }
        }

        return $result;
    }
}
