<?php

namespace Stroy\Regionality\Event\PropBuildList;

use \Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Class UserTypeLocation
 * /bitrix/modules/main/classes/general/usertypestr.php
 * /bitrix/modules/main/classes/general/usertype*
 */
class UserTypeLocation
{
    private const USER_TYPE = 'IblockPropLocation';

    /**
     * Событие 'OnIBlockPropertyBuildList' Вызывается при построении списка пользовательских свойств
     **/
    public static function GetUserTypeDescription(): array
    {
        return [
            'USER_TYPE_ID' => self::USER_TYPE,
            'CLASS_NAME' => self::class,
            'EDIT_CALLBACK' => [],
            'VIEW_CALLBACK' => [],
            'USE_FIELD_COMPONENT' => true,
            'DESCRIPTION' => Loc::getMessage('REGIONALITY_DIRECTORY_DESCRIPTION'),
            'BASE_TYPE' => \CUserTypeManager::BASE_TYPE_STRING
        ];
    }

    public static function GetPropertyFieldHtml($arUserField, $arHtmlControl): string
    {
        return '';
    }

    /**
     * This function is called when the property values are displayed
     * in the public part of the site.
     *
     * Returns html. If the class does not provide such a function,
     * then the type manager will call the component specified
     * in the property metadata or system bitrix: system.field.view
     *
     * @param array $userField An array describing the field.
     * @param array $additionalParameters Additional parameters (e.g. context).
     * @return string
     */
    public static function getPublicView(array $userField, array $additionalParameters = []): string
    {
        return '';
    }

    /**
     * This function is called when editing property values in the public part of the site.
     *
     * Returns html. If the class does not provide such a function,
     * then the type manager will call the component specified
     * in the property metadata or system bitrix: system.field.edit
     *
     * @param array $userField An array describing the field.
     * @param array|null $additionalParameters Additional parameters (e.g. context).
     * @return string HTML для вывода.
     */
    public static function getPublicEdit(array $userField, ?array $additionalParameters = []): string
    {
        return '';
    }

    /**
     * This function is called when the property settings form is displayed.
     *
     * Returns html for embedding in a 2-column table in the form usertype_edit.php
     *
     * @param bool|array $userField An array describing the field. For a new (not yet added field - false)
     * @param array|null $additionalParameters Array of advanced parameters
     * @param $varsFromForm
     * @return string HTML
     */
    public static function getSettingsHtml($userField, ?array $additionalParameters, $varsFromForm): string
    {
        return '';
    }

    /**
     * This function is called when the form for editing the property value is displayed,
     * for example, here /bitrix/admin/iblock_section_edit.php
     *
     * Returns html for embedding in a table cell in the entity editing form
     * (on the "Advanced Properties" tab).
     *
     * @param array $userField An array describing the field..
     * @param array|null $additionalParameters An array of controls from the form. Contains the elements NAME and VALUE.
     * @return string
     */
    public static function getEditFormHtml(array $userField, ?array $additionalParameters): string
    {
        if ($userField['MULTIPLE'] != 'Y') {
            $userField['VALUE'] = self::normalizePropValue($userField['VALUE']);
        }

        $html = self::getEditHtml($userField, $additionalParameters);

        return $html;
    }

    /**
     * This function is called when the property value is displayed in the list of elements.
     *
     * Returns html to embed in a table cell.
     * $AdditionalParameters elements are converted to html safe mode.
     *
     * @param array $userField An array describing the field.
     * @param array|null $additionalParameters An array of controls from the form. Contains the elements NAME and VALUE.
     * @return string HTML
     */
    public static function getAdminListViewHtml(array $userField, ?array $additionalParameters): string
    {
        $html = '';
        if ($additionalParameters['VALUE']) {
            if ($userField['MULTIPLE'] != 'Y') {
                $additionalParameters['VALUE'] = str_replace(':', ' / ', $additionalParameters['VALUE']);
            }

            $html = $additionalParameters['VALUE'];
        }

        return $html;
    }

    /**
     * This function is called when the property value is displayed in the list of items in edit mode.
     *
     * Returns html to embed in a table cell.
     * $AdditionalParameters elements are converted to html safe mode.
     * @param array $userField An array describing the field.
     * @param array|null $additionalParameters An array of controls from the form. Contains the elements NAME and VALUE.
     * @return string HTML
     */
    public static function getAdminListEditHtml(array $userField, ?array $additionalParameters): string
    {
        return '';
    }

    /**
     * This function is called when the filter is displayed on the list page.
     *
     * Returns html to embed in a table cell.
     * $additionalParameters elements are html safe.
     *
     * @param array $userField An array describing the field.
     * @param array|null $additionalParameters An array of controls from the form. Contains the elements NAME and VALUE.
     * @return string
     */
    public static function getFilterHtml(array $userField, ?array $additionalParameters): string
    {
        return '';
    }

    /**
     * This function is called when new properties are added. We only support mysql data types.
     *
     * This function is called to construct the SQL column creation query
     * to store non-multiple property values.
     * Values of multiple properties are not stored in rows, but in columns
     * (as in info blocks) and the type of such a field in the database is always text
     *
     * @return string
     */
    public static function getDbColumnType(): string
    {
        return 'text';
    }

    /**
     * @param null|array $userField
     * @param array $additionalParameters
     * @return array
     */
    public static function getFilterData(?array $userField, array $additionalParameters): array
    {
        return [
            'id' => $additionalParameters['ID'],
            'name' => $additionalParameters['NAME'],
            'filterable' => ''
        ];
    }

    /**
     * This function is called before saving the property metadata to the database.
     *
     * It should 'clear' the array with the settings of the instance of the property type.
     * In order to accidentally / intentionally no one wrote down any garbage there.
     *
     * @param array $userField An array describing the field. Warning! this description of the field has not yet been saved to the database!
     * @return array An array that will later be serialized and stored in the database.
     */
    public static function prepareSettings(array $userField): array
    {
        if (!$userField['SETTINGS']) {
            return $userField;
        }

        $size = (int)$userField['SETTINGS']['SIZE'];
        $rows = (int)$userField['SETTINGS']['ROWS'];
        $min = (int)$userField['SETTINGS']['MIN_LENGTH'];
        $max = (int)$userField['SETTINGS']['MAX_LENGTH'];

        $regExp = '';
        if (!empty($userField['SETTINGS']['REGEXP']) && @preg_match($userField['SETTINGS']['REGEXP'], null) !== false) {
            $regExp = $userField['SETTINGS']['REGEXP'];
        }

        return [
            'SIZE' => ($size <= 1 ? 20 : ($size > 255 ? 225 : $size)),
            'ROWS' => ($rows <= 1 ? 1 : ($rows > 50 ? 50 : $rows)),
            'REGEXP' => $regExp,
            'MIN_LENGTH' => $min,
            'MAX_LENGTH' => $max,
            'DEFAULT_VALUE' => $userField['SETTINGS']['DEFAULT_VALUE'],
        ];
    }

    /**
     * This function is validator.
     * Called from the CheckFields method of the $ USER_FIELD_MANAGER object,
     * which can be called from the Add / Update methods of the property owner entity.
     * @param array $userField
     * @param string|array $value
     * @return array
     */
    public static function checkFields(array $userField, $value): array
    {
        return [];
    }

    /**
     * This function should return a representation of the field value for the search.
     * It is called from the OnSearchIndex method of the object $ USER_FIELD_MANAGER,
     * which is also called the update function of the entity search index.
     * For multiple values, the VALUE field is an array.
     * @param array $userField
     * @return string|null
     */
    public static function onSearchIndex(array $userField): ?string
    {
        return '';
    }

    /**
     * html
     * @param $userField
     * @param $additionalParameters
     * @return string
     */
    public static function getEditHtml($userField, $additionalParameters): string
    {
        global $APPLICATION;

        ob_start();
        $APPLICATION->IncludeComponent(
            "stroyregionality:sale.location.selector.system",
            "",
            [
                "INPUT_NAME" => $userField['FIELD_NAME'],
                "PROP_ID" => $userField['ID'],
                "PROP_CODE" => $userField['FIELD_NAME'],
                "PROP_MULTIPLE" => $userField['MULTIPLE'],
                "PROP_VALUE" => $userField['VALUE']
            ]
        );

        return ob_get_clean();
    }

    /**
     * Normalize property value
     * @param $value
     * @return string
     */
    public static function normalizePropValue($value)
    {
        if (!$value || !is_string($value)) {
            return $value;
        }

        $result = $value ? explode(':', $value) : [];

        return $result;
    }
}
