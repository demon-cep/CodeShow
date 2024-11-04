<?php

namespace Stroy\Regionality\Orm;

/**
 * Class PaySystemlocationExcludeTable
 **/
class PaySystemlocationExcludeTable extends PaySystemlocationTable
{
    const DB_LOCATION_FLAG = 'LE';
    const DB_GROUP_FLAG = 'GE';

    public static function getFilePath(): string
    {
        return __FILE__;
    }
}
