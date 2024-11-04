<?php

use \Bitrix\Main\Loader;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Config\Option;
use Stroy\Regionality\Handler;

$moduleId = Handler::getModuleName();
#Используется в подключаемом файле /bitrix/modules/main/admin/group_rights.php
$module_id = $moduleId;
$modParams = Handler::getInstance()->getOptions();

Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/modules/main/options.php');
Loc::loadMessages(__FILE__);

if (!Loader::includeModule('iblock')) {
    return;
}

global $APPLICATION;
if ($APPLICATION->GetGroupRight($moduleId) < 'S') {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

$request = \Bitrix\Main\HttpApplication::getInstance()->getContext()->getRequest();

#Инфоблоки сайта
$arIBlock = [Loc::getMessage('REGIONALITY_SELECT_DEFAULT')];
$rsIBlock = CIBlock::GetList(['SORT' => 'ASC', 'ID' => 'ASC'], ['ACTIVE' => 'Y']);
while ($arr = $rsIBlock->Fetch()) {
    $arIBlock[$arr['ID']] = '[' . $arr['ID'] . '] ' . $arr['NAME'];
    $arIBlockId[] = $arr['ID'];
}

$arGeoIp = [
    'LOCAL' => Loc::getMessage('REGIONALITY_TAB20_DATA_SOURCE_LOCAL'),
    'DADATA' => Loc::getMessage('REGIONALITY_TAB20_DATA_SOURCE_DADATA'),
    'CLOUD_FLARE' => Loc::getMessage('REGIONALITY_TAB20_DATA_CLOUD_FLARE')
];

#Получаем данные по IP
$ip = \Bitrix\Main\Service\GeoIp\Manager::getRealIp();
$resDadata = $resCloudFlare = $resGeoip = [];
if ($ip) {
    $obDadata = new \Stroy\Regionality\Services\Dadata\Address();
    $obDadata->getIpLocate($ip);
    $resDadata = $obDadata->getConvertResult();
    $obDadata->destroy();

    $obCloudFlare = new \Stroy\Regionality\Services\CloudFlare\DataBase();
    $obCloudFlare->getLocation();
    $resCloudFlare = $obCloudFlare->getConvertResult();
    $obCloudFlare->destroy();

    $obGeoIp = new \Stroy\Regionality\Services\Geoip\GeoIpBase();
    $obGeoIp->getIp($ip);
    $resGeoip = $obGeoIp->getConvertResult();
    $obGeoIp->destroy();
}

$arGeoipSoursDemoHtml = '';
if ($resDadata) {
    $arGeoipSoursDemoHtml .= '<div>dadata.ru</div>';
    $arGeoipSoursDemoHtml .= '<pre>'.print_r($resDadata, true).'</pre>';
}
if ($resCloudFlare) {
    $arGeoipSoursDemoHtml .= '<div>CloudFlare</div>';
    $arGeoipSoursDemoHtml .= '<pre>'.print_r($resCloudFlare, true).'</pre>';
}
if ($resGeoip) {
    $arGeoipSoursDemoHtml .= '<div>Локальная база</div>';
    $arGeoipSoursDemoHtml .= '<pre>'.print_r($resGeoip, true).'</pre>';
}

#Описание опций
$aTabs = [
    [
        'DIV' => 'edit10',
        'TAB' => Loc::getMessage('REGIONALITY_TAB10'),
        'TITLE' => Loc::getMessage('REGIONALITY_TAB10_TITLE'),
        'OPTIONS' => [
            Loc::getMessage('REGIONALITY_TAB10_TITLE_HEADER_1'),
            ['tab10_common_catalog_iblock_id', Loc::getMessage('REGIONALITY_TAB10_COMMON_CATALOG_IBLOCK_ID'), $arIBlockId[0], ['selectbox', $arIBlock], '', ' *1.1'],

            Loc::getMessage('REGIONALITY_TAB10_TITLE_HEADER_2'),
            ['tab10_common_save_cookies', Loc::getMessage('REGIONALITY_TAB10_COMMON_SAVE_COOKIES'), '', ['checkbox'], '', ' *2.1'],

            ['note' => Loc::getMessage('REGIONALITY_TAB10_NOTE')],
        ]
    ],
    [
        'DIV' => 'edit20',
        'TAB' => Loc::getMessage('REGIONALITY_TAB20'),
        'TITLE' => Loc::getMessage('REGIONALITY_TAB20_TITLE'),
        'OPTIONS' => [
            Loc::getMessage('REGIONALITY_TAB20_TITLE_HEADER_1'),
            ['tab20_geoip_data_source_ip', Loc::getMessage('REGIONALITY_TAB20_DATA_SOURCE_IP'), 'LOCAL', ['selectbox', $arGeoIp], '', ' *1.1'],
            ['', Loc::getMessage('REGIONALITY_TAB20_DATA_SOURCE_DEMO'), $arGeoipSoursDemoHtml, ['statichtml'], '', ' *1.2'],
            ['note' => Loc::getMessage('REGIONALITY_TAB20_NOTE')],
        ]
    ],
    [
        'DIV' => 'edit30',
        'TAB' => Loc::getMessage('REGIONALITY_TAB30'),
        'TITLE' => Loc::getMessage('REGIONALITY_TAB30_TITLE'),
        'OPTIONS' => [
            Loc::getMessage('REGIONALITY_TAB30_TITLE_HEADER_1'),
            ['tab30_dadata_token', Loc::getMessage('REGIONALITY_TAB30_DADATA_TOKEN'), '', ['text', 60], '', ' *1.1'],
            ['tab30_dadata_secret_key', Loc::getMessage('REGIONALITY_TAB30_DADATA_SECRET_KEY'), '', ['text', 60], '', ' *1.2'],

            ['note' => Loc::getMessage('REGIONALITY_TAB30_NOTE')],
        ]
    ],
    [
        'DIV' => 'edit99',
        'TAB' => Loc::getMessage('MAIN_TAB_RIGHTS'),
        'TITLE' => Loc::getMessage('MAIN_TAB_TITLE_RIGHTS')
    ],
];

#Сохранение
if ($request->isPost() && $request['Update'] && check_bitrix_sessid()) {
    $arSites = ['reference_id' => [], 'reference' => []];
    $rsSites = CSite::GetList($by = 'sort', $order = 'asc', ['ACTIVE' => 'Y']);
    while ($arSite = $rsSites->GetNext()) {
        $arSites['reference_id'][] = $arSite['ID'];
        $arSites['reference'][] = '[' . $arSite['ID'] . '] ' . $arSite['NAME'];
    }
    if ($GROUPS && count($GROUPS) > 0) {
        COption::RemoveOption($moduleId, 'GROUP_DEFAULT_RIGHT');
        $APPLICATION->DelGroupRight($moduleId, [], false);
        foreach ($arSites['reference_id'] as $site_id_tmp) {
            $APPLICATION->DelGroupRight($moduleId, [], $site_id_tmp);
        }

        foreach ($GROUPS as $i => $group_id) {
            if ($group_id == '') {
                continue;
            }

            if (!array_key_exists($i, $RIGHTS) || $RIGHTS[$i] == '') {
                continue;
            }

            if (intval($group_id) == 0) {
                COption::SetOptionString(
                    $moduleId,
                    'GROUP_DEFAULT_RIGHT',
                    $RIGHTS[$i],
                    'Right for groups by default for site ' . $SITES[$i],
                    $SITES[$i]
                );
            } else {
                if (!in_array($RIGHTS[$i], $arRightsUseSites) || $SITES[$i] == '') {
                    $APPLICATION->SetGroupRight($moduleId, $group_id, $RIGHTS[$i], false);
                } else {
                    $APPLICATION->SetGroupRight($moduleId, $group_id, $RIGHTS[$i], $SITES[$i]);
                }
            }
        }
    }

    foreach ($aTabs as $aTab) {
        foreach ($aTab['OPTIONS'] as $arOption) {
            if (!is_array($arOption) || !$arOption[0]) {
                continue;
            }

            if ($arOption['note']) {
                continue;
            }

            $optionName = $arOption[0];

            $optionValue = $request->getPost($optionName);

            Option::set($moduleId, $optionName, is_array($optionValue) ? implode(',', $optionValue) : $optionValue);
        }
    }

    LocalRedirect($APPLICATION->GetCurPage() . '?lang=' . LANGUAGE_ID . '&mid=' . $moduleId . '&mid_menu=1');
}

$tabControl = new CAdminTabControl('tabControl', $aTabs);

$tabControl->Begin(); ?>
<form method='post'
      action='<?= $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($request['mid']) ?>&amp;lang=<?= $request['lang'] ?>'
      name='impulsit_salefood_settings'>
    <?php
    foreach ($aTabs as $aTab):
        if ($aTab['OPTIONS']):
            $tabControl->BeginNextTab();

            __AdmSettingsDrawList($moduleId, $aTab['OPTIONS']);
        endif;
    endforeach;

    $tabControl->BeginNextTab();
    require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/admin/group_rights.php');
    $tabControl->Buttons();
    ?>
    <input type="submit" name="Update" value="<?= GetMessage('MAIN_SAVE') ?>"/>
    <input type="reset" name="reset" value="<?= GetMessage('MAIN_RESET') ?>"/>
    <?= bitrix_sessid_post() ?>
</form>
<?php
$tabControl->End();

if (class_exists('Bitrix\Main\UI\Extension')) {
    Bitrix\Main\UI\Extension::load("ui.hint");
    echo '<script>BX.ready(function() {BX.UI.Hint.init(BX("adm-workarea")); });</script>';
    echo '<style>.simple-hint {display: none !important;}</style>';
} ?>
