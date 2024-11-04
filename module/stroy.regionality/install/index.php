<?php

use Bitrix\Main\Application;
use Bitrix\Main\Entity\Base;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class stroy_regionality extends CModule
{
    /**
     * @var string
     */
    public $MODULE_ID = 'stroy.regionality';

    /**
     * @var string
     */
    public $MODULE_VERSION;

    /**
     * @var string
     */
    public $MODULE_VERSION_DATE;

    /**
     * @var string
     */
    public $MODULE_NAME;

    /**
     * @var string
     */
    public $MODULE_DESCRIPTION;

    /**
     * Construct object
     */
    public function __construct()
    {
        $arModuleVersion = array();
        include(__DIR__ . '/version.php');

        $this->MODULE_NAME = Loc::getMessage('REGIONALITY_MODULE_NAME_STROY_CORE');
        $this->MODULE_DESCRIPTION = Loc::getMessage('REGIONALITY_MODULE_DESCRIPTION_STROY_CORE');

        $this->PARTNER_NAME = Loc::getMessage('REGIONALITY_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('REGIONALITY_PARTNER_URI');

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
    }

    /**
     * Return path module
     *
     * @param bool $notDocumentRoot
     * @return string
     */
    public function GetPath($notDocumentRoot = false): string
    {
        if ($notDocumentRoot) {
            return str_ireplace(Application::getDocumentRoot(), '', dirname(__DIR__));
        } else {
            return dirname(__DIR__);
        }
    }

    /**
     * Install DB
     */
    function InstallDB()
    {
        Loader::includeModule($this->MODULE_ID);
        Loader::includeModule('sale');

        #Платёжная система ограничение по местоположению
        if (!Application::getConnection(\Stroy\Regionality\Orm\PaySystemlocationTable::getConnectionName())->isTableExists(Base::getInstance('\Stroy\Regionality\Orm\PaySystemlocationTable')->getDBTableName())) {
            Base::getInstance('\Stroy\Regionality\Orm\PaySystemlocationTable')->createDbTable();
        }
    }

    /**
     * UnInstall DB
     */
    function UnInstallDB()
    {
        Loader::includeModule($this->MODULE_ID);
        Loader::includeModule('sale');

        Option::delete($this->MODULE_ID);

        #Платёжная система ограничение по местоположению
        Application::getConnection(\Stroy\Regionality\Orm\PaySystemlocationTable::getConnectionName())->
            queryExecute('drop table if exists ' . Base::getInstance('\Stroy\Regionality\Orm\PaySystemlocationTable')->getDBTableName());
    }

    /**
     * Install Events
     */
    function InstallEvents()
    {
        #Выполняемой части пролога сайта, после подключения всех библиотек и отработки агентов
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'main',
            'OnPageStart',
            $this->MODULE_ID,
            '\Stroy\Regionality\Event\Handler',
            'OnPageStartHandler'
        );

        #Вызывается в начале визуальной части пролога сайта
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'main',
            'OnProlog',
            $this->MODULE_ID,
            '\Stroy\Regionality\Event\Handler',
            'OnPrologHandler'
        );

        #Вызывается при построении списка типов пользовательских свойств
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'main',
            'OnUserTypeBuildList',
            $this->MODULE_ID,
            '\Stroy\Regionality\Event\PropBuildList\UserTypeLocation',
            'GetUserTypeDescription'
        );
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'main',
            'OnUserTypeBuildList',
            $this->MODULE_ID,
            '\Stroy\Regionality\Event\PropBuildList\UserTypeDeclensions',
            'GetUserTypeDescription'
        );

        #Вызывается при построении списка пользовательских свойств
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'iblock',
            'OnIBlockPropertyBuildList',
            $this->MODULE_ID,
            '\Stroy\Regionality\Event\PropBuildList\IblockPropLocation',
            'GetUserTypeDescription'
        );

        #Вызывается при построении списка ограничения платёжных служб
        \Bitrix\Main\EventManager::getInstance()->registerEventHandler(
            'sale',
            'onSalePaySystemRestrictionsClassNamesBuildList',
            $this->MODULE_ID,
            '\Stroy\Regionality\Event\Handler',
            'onSalePaySystemRestrictionsClassNamesBuildListHandler'
        );
    }

    /**
     * UnInstall Events
     */
    function UnInstallEvents()
    {
        #Выполняемой части пролога сайта, после подключения всех библиотек и отработки агентов
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'main',
            'OnPageStart',
            $this->MODULE_ID,
            '\Stroy\Regionality\Event\Handler',
            'OnPageStartHandler'
        );

        #Вызывается в начале визуальной части пролога сайта
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'main',
            'OnProlog',
            $this->MODULE_ID,
            '\Stroy\Regionality\Event\Handler',
            'OnPrologHandler'
        );

        #Вызывается при построении списка пользовательских свойств
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'main',
            'OnUserTypeBuildList',
            $this->MODULE_ID,
            '\Stroy\Regionality\Event\PropBuildList\UserTypeLocation',
            'GetUserTypeDescription'
        );
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'main',
            'OnUserTypeBuildList',
            $this->MODULE_ID,
            '\Stroy\Regionality\Event\PropBuildList\UserTypeDeclensions',
            'GetUserTypeDescription'
        );

        #Вызывается при построении списка пользовательских свойств ИБ
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'iblock',
            'OnIBlockPropertyBuildList',
            $this->MODULE_ID,
            '\Stroy\Regionality\Event\PropBuildList\IblockPropLocation',
            'GetUserTypeDescription'
        );

        #Вызывается при построении списка ограничения платёжных служб
        \Bitrix\Main\EventManager::getInstance()->unRegisterEventHandler(
            'sale',
            'onSalePaySystemRestrictionsClassNamesBuildList',
            $this->MODULE_ID,
            '\Stroy\Regionality\Event\Handler',
            'onSalePaySystemRestrictionsClassNamesBuildListHandler'
        );
    }

    /**
     * Install modules
     */
    function DoInstall()
    {
        global $APPLICATION;

        \Bitrix\Main\ModuleManager::registerModule($this->MODULE_ID);

        $this->InstallDB();

        $this->InstallEvents();

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('REGIONALITY_INSTALL_TITLE'),
            $this->GetPath() . '/install/step.php'
        );
    }

    /**
     * Do install modules
     */
    function DoUninstall()
    {
        global $APPLICATION;

        $request = \Bitrix\Main\HttpApplication::getInstance()->getContext()->getRequest();

        if ($request['step'] < 2) {
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('REGIONALITY_UNINSTALL_TITLE'),
                $this->GetPath() . '/install/unstep1.php'
            );
        } elseif ($request['step'] == 2) {
            #Удаляем обработчики событий которые нам нужны.
            $this->UnInstallEvents();

            if ($request['savedata'] != 'Y') {
                $this->UnInstallDB();
            }

            \Bitrix\Main\ModuleManager::unRegisterModule($this->MODULE_ID);

            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('REGIONALITY_UNINSTALL_TITLE'),
                $this->GetPath() . '/install/unstep2.php'
            );
        }
    }

    /**
     * Access rights modules
     */
    function GetModuleRightList()
    {
        return array(
            'reference_id' => array('D', 'K', 'S', 'W'),
            'reference' => array(
                '[D] ' . Loc::getMessage('REGIONALITY_DENIED'),#Доступ закрыт
                '[K] ' . Loc::getMessage('REGIONALITY_READ_COMPONENT'),#Доступ к компонентам
                '[S] ' . Loc::getMessage('REGIONALITY_WRITE_SETTINGS'),#Изменение настроек модуля
                '[W] ' . Loc::getMessage('REGIONALITY_FULL')
            )#Полный доступ
        );
    }
}
