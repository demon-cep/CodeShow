<?php

namespace Stroy\Regionality\Event\PropBuildList;

use \Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Class UserTypeDeclensions
 * /bitrix/modules/main/classes/general/usertypestr.php
 * /bitrix/modules/main/classes/general/usertype*
 */
class UserTypeDeclensions
{
    private const USER_TYPE = 'UserTypeDeclensions';

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
            'DESCRIPTION' => Loc::getMessage('DECLENSIONS_DIRECTORY_DESCRIPTION'),
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
        if ($userField['MULTIPLE'] == 'Y') {
            $userField['VALUE'] = self::normalizePropMultyValue($userField['VALUE']);
            $html = self::getEditMultiHtml($userField, $additionalParameters);
        } else {
            $html = self::getEditHtml($userField, $additionalParameters);
        }

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
     * @param $userfield
     * @param $value
     * @return array|false|float|mixed|string|string[]
     */
    public static function onBeforeSave($userfield, $value = '')
    {
        if ($value && $userfield['MULTIPLE'] == 'Y' && $_REQUEST[$userfield['FIELD_NAME']]) {
            $obRequest = \Bitrix\Main\HttpApplication::getInstance()->getContext()->getRequest();
            $arRequest = $obRequest->getPostList()->getValues();

            if (in_array($value, $arRequest[$userfield['FIELD_NAME']], true)) {
                $key = array_search($value, $arRequest[$userfield['FIELD_NAME']], true);
                $arRequest[$userfield['FIELD_NAME']][$key] = $key . '_' . $value;
                #Перестал работать после обновления ядра
                //$obRequest->set($userfield['FIELD_NAME'], $arRequest[$userfield['FIELD_NAME']]);
                //$obRequest->getPostList()->set($userfield['FIELD_NAME'],$arRequest[$userfield['FIELD_NAME']]);
                $obRequest->set($arRequest);
                $obRequest->getPostList()->set($arRequest);
                $value = json_encode([$key => trim($value)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return $value;
    }

    /**
     * html
     * @param $userField
     * @param $additionalParameters
     * @return string
     */
    public static function getEditMultiHtml($userField, $additionalParameters): string
    {
        $result = <<<"FORMHHTML"
            <style>
                .declension-row {margin-bottom: 10px;}
                .declension-row span {margin-left: 10px;}
            </style>
            <div class="declension-row">
                <input type="text" name="{$userField['FIELD_NAME']}[i]" value="{$userField['VALUE']['i']}">
                <span>Именительный падеж</span>
            </div>
            <div class="declension-row">
                <input type="text" name="{$userField['FIELD_NAME']}[r]" value="{$userField['VALUE']['r']}">
                <span>Родительный падеж</span>
            </div>
            <div class="declension-row">
                <input type="text" name="{$userField['FIELD_NAME']}[d]" value="{$userField['VALUE']['d']}">
                <span>Дательный падеж</span>
            </div>
            <div class="declension-row">
                <input type="text" name="{$userField['FIELD_NAME']}[v]" value="{$userField['VALUE']['v']}">
                <span>Винительный падеж</span>
            </div>
            <div class="declension-row">
                <input type="text" name="{$userField['FIELD_NAME']}[t]" value="{$userField['VALUE']['t']}">
                <span>Творительный падеж</span>
            </div>
            <div class="declension-row">
                <input type="text" name="{$userField['FIELD_NAME']}[p]" value="{$userField['VALUE']['p']}">
                <span>Предложный падеж</span>
            </div>
        FORMHHTML;

        return $result;
    }

    /**
     * html
     * @param $userField
     * @param $additionalParameters
     * @return string
     */
    public static function getEditHtml($userField, $additionalParameters): string
    {
        $value = $userField['VALUE'] ?: '';

        $result = <<<"FORMHHTML"
            <style>
                .declension-row {margin-bottom: 10px;}
                .declension-row span {margin-left: 10px;}
            </style>
            <div class="declension-row">
                <input type="text" name="{$userField['FIELD_NAME']}" value="{$value}" />
                <span>Падежи через запятую: Им,Род,Дат,Вин,Тв,Пред</span>
            </div>
        FORMHHTML;

        return $result;
    }

    /**
     * Normalize property value
     * @return string
     */
    public static function normalizePropMultyValue($value)
    {
        if (!$value || !is_array($value)) {
            return $value;
        }

        $result = [];

        foreach ($value as $key => $item) {
            $item = json_decode($item, true);
            if ($item) {
                $result += $item;
            }
        }

        return $result;
    }
}
