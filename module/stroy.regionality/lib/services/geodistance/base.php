<?php

namespace Stroy\Regionality\Services\GeoDistance;

/**
 * Класс для определения ближайшей точки, местоположение
 */
class Base
{
    /**
     * Формула вычисления длины от точки А до точки Б
     * @param $x
     * @param $y
     * @return float
     */
    public static function calculateNearestDot($x, $y): float
    {
        //$mat_result = sqrt(pow((x2-x1),2)+pow((y2-y1),2));
        return sqrt(pow(((int)$x[0] - (int)$y[0]), 2) + pow(((int)$x[1] - (int)$y[1]), 2));
    }

    /**
     * Формула вычисления длины в метрах
     * @param $x
     * @param $y
     * @return float
     */
    public static function calculateDistanceMeters($x, $y): float
    {
        $arrival = [];
        $arrival = [];

        $arrival['latitude'] = $x[0];
        $arrival['longitude'] = $x[1];

        $departure['latitude'] = $y[0];
        $departure['longitude'] = $y[1];

        $rad_per_deg = pi() / 180.0;// PI / 180
        $rkm = 6371.0;// Earth radius in kilometers
        $rm = $rkm * 1000.0;// Radius in meters
        $dlat_rad = ($arrival['latitude'] - $departure['latitude']) * $rad_per_deg;// Delta, converted to rad
        $dlon_rad = ($arrival['longitude'] - $departure['longitude']) * $rad_per_deg;

        $lat1_rad = $departure['latitude'] * $rad_per_deg;
        $lat2_rad = $arrival['latitude'] * $rad_per_deg;

        $sinDlat = sin($dlat_rad / 2);
        $sinDlon = sin($dlon_rad / 2);
        $a = $sinDlat * $sinDlat + cos($lat1_rad) * cos($lat2_rad) * $sinDlon * $sinDlon;
        $c = 2.0 * atan2(sqrt($a), sqrt(1 - $a));

        return $rm * $c;
    }
}
