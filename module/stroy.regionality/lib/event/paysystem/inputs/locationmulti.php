<?php

namespace Stroy\Regionality\Event\PaySystem\Inputs;

/**
 * Class
 * @bref /bitrix/modules/sale/lib/delivery/inputs.php
 */
class LocationMulti extends \Bitrix\Sale\Internals\Input\Base
{
    protected static $d2LClass = '\Stroy\Regionality\Orm\PaySystemlocationTable';

    public static function getViewHtml(array $input, $value = null)
    {
        $result = "";
        $class = static::$d2LClass;

        $res = $class::getConnectedLocations(
            $input["PAY_SYSTEM_ID"],
            [
                'select' => ['LNAME' => 'NAME.NAME'],
                'filter' => ['NAME.LANGUAGE_ID' => LANGUAGE_ID]
            ]
        );

        while ($loc = $res->fetch()) {
            $result .= htmlspecialcharsbx($loc["LNAME"]) . "<br>\n";
        }

        $res = $class::getConnectedGroups(
            $input["PAY_SYSTEM_ID"],
            [
                'select' => ['LNAME' => 'NAME.NAME'],
                'filter' => ['NAME.LANGUAGE_ID' => LANGUAGE_ID]
            ]
        );

        while ($loc = $res->fetch()) {
            $result .= htmlspecialcharsbx($loc["LNAME"]) . "<br>\n";
        }

        return $result;
    }

    public static function getEditHtml($name, array $input, $values = null)
    {
        global $APPLICATION;

        ob_start();
        $APPLICATION->IncludeComponent(
            "bitrix:sale.location.selector.system",
            "",
            [
                "ENTITY_PRIMARY" => $input["PAY_SYSTEM_ID"],
                "LINK_ENTITY_NAME" => mb_substr(static::$d2LClass, 0, -5),
                "INPUT_NAME" => $name
            ],
            false
        );
        $result = ob_get_contents();
        ob_end_clean();

        $result = '
            <script type="text/javascript">
                var bxInputdeliveryLocMultiStep3 = function()
                {
                    BX.loadScript("/bitrix/components/bitrix/sale.location.selector.system/templates/.default/script.js", function(){
                        let locationSelectorSystemContainer = BX("locationSelectorSystemContainer");
                        let html = ' . \CUtil::PhpToJSObject($result, false, true) . ';
                        if (locationSelectorSystemContainer && html) {
                            let processed = BX.processHTML(html, false);
                            if (processed.HTML && processed.HTML.length) {
                                locationSelectorSystemContainer.innerHTML = processed.HTML;
                            }
                            if (processed.SCRIPT && processed.SCRIPT.length) {
                                BX.ajax.processScripts(processed.SCRIPT);
                            }
                        }
                    });
                };
            
                var bxInputdeliveryLocMultiStep2 = function()
                {
                    BX.load([
                            "/bitrix/js/sale/core_ui_etc.js",
                            "/bitrix/js/sale/core_ui_autocomplete.js",
                            "/bitrix/js/sale/core_ui_itemtree.js"
                        ],
                        bxInputdeliveryLocMultiStep3
                    );
                };
            
                BX.loadScript("/bitrix/js/sale/core_ui_widget.js", bxInputdeliveryLocMultiStep2);
            </script>
            
            <link rel="stylesheet" type="text/css" href="/bitrix/panel/main/adminstyles_fixed.css">
            <link rel="stylesheet" type="text/css" href="/bitrix/panel/main/admin.css">
            <link rel="stylesheet" type="text/css" href="/bitrix/panel/main/admin-public.css">
            <link rel="stylesheet" type="text/css" href="/bitrix/components/bitrix/sale.location.selector.system/templates/.default/style.css">
            <style>.bx-core-adm-dialog .bx-core-adm-dialog-content{width: auto !important;height:auto !important;}</style>

            <div id="locationSelectorSystemContainer">' . preg_replace('/\s?<script[^>]*?>.*?<\/script>\s?/si', ' ', $result) . '</div>
        ';

        return $result;
    }

    public static function getError(array $input, $values)
    {
        return [];
    }

    public static function getValueSingle(array $input, $userValue)
    {
        return $userValue;
    }

    public static function getSettings(array $input, $reload)
    {
        return [];
    }
}
