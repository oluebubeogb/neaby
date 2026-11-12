<?php

namespace App\Support;

/**
 * Approximate centres of Nigerian cities (lat, lng, state). Used offline — no paid
 * geocoder — to (a) rank stores that only have a city by real distance and (b)
 * reverse-fill city/state from a GPS fix (nearest centre within ~45 km).
 */
class NigeriaCities
{
    /** @var array<string, array{0: float, 1: float, 2: string}> */
    public const CITIES = [
        'Lagos' => [6.5244, 3.3792, 'Lagos'], 'Ikeja' => [6.6018, 3.3515, 'Lagos'], 'Lekki' => [6.4698, 3.5852, 'Lagos'],
        'Ikorodu' => [6.6194, 3.5105, 'Lagos'], 'Badagry' => [6.4316, 2.8876, 'Lagos'], 'Epe' => [6.5841, 3.9834, 'Lagos'],
        'Abuja' => [9.0765, 7.3986, 'FCT – Abuja'], 'Suleja' => [9.1806, 7.1794, 'Niger'], 'Minna' => [9.6139, 6.5569, 'Niger'],
        'Kano' => [12.0022, 8.5920, 'Kano'], 'Kaduna' => [10.5105, 7.4165, 'Kaduna'], 'Zaria' => [11.0855, 7.7199, 'Kaduna'],
        'Ibadan' => [7.3775, 3.9470, 'Oyo'], 'Ogbomosho' => [8.1335, 4.2407, 'Oyo'], 'Oyo' => [7.8526, 3.9312, 'Oyo'],
        'Port Harcourt' => [4.8156, 7.0498, 'Rivers'], 'Bonny' => [4.4517, 7.1700, 'Rivers'],
        'Benin City' => [6.3350, 5.6037, 'Edo'], 'Ekpoma' => [6.7430, 6.1390, 'Edo'], 'Auchi' => [7.0676, 6.2636, 'Edo'],
        'Enugu' => [6.4584, 7.5464, 'Enugu'], 'Nsukka' => [6.8567, 7.3958, 'Enugu'],
        'Onitsha' => [6.1413, 6.8029, 'Anambra'], 'Awka' => [6.2104, 7.0741, 'Anambra'], 'Nnewi' => [6.0177, 6.9170, 'Anambra'],
        'Aba' => [5.1066, 7.3667, 'Abia'], 'Umuahia' => [5.5250, 7.4922, 'Abia'], 'Ohafia' => [5.6146, 7.8115, 'Abia'],
        'Owerri' => [5.4840, 7.0351, 'Imo'], 'Okigwe' => [5.8295, 7.3496, 'Imo'], 'Orlu' => [5.7957, 7.0354, 'Imo'],
        'Uyo' => [5.0377, 7.9128, 'Akwa Ibom'], 'Ikot Ekpene' => [5.1794, 7.7146, 'Akwa Ibom'], 'Eket' => [4.6423, 7.9244, 'Akwa Ibom'],
        'Calabar' => [4.9757, 8.3417, 'Cross River'], 'Ogoja' => [6.6584, 8.7992, 'Cross River'],
        'Asaba' => [6.1980, 6.7319, 'Delta'], 'Warri' => [5.5544, 5.7932, 'Delta'], 'Sapele' => [5.8941, 5.6767, 'Delta'], 'Ughelli' => [5.4895, 5.9926, 'Delta'],
        'Abakaliki' => [6.3249, 8.1137, 'Ebonyi'], 'Yenagoa' => [4.9267, 6.2676, 'Bayelsa'],
        'Jos' => [9.8965, 8.8583, 'Plateau'], 'Ilorin' => [8.4966, 4.5426, 'Kwara'],
        'Abeokuta' => [7.1475, 3.3619, 'Ogun'], 'Ota' => [6.6926, 3.2312, 'Ogun'], 'Sagamu' => [6.8322, 3.6319, 'Ogun'], 'Ijebu-Ode' => [6.8194, 3.9173, 'Ogun'],
        'Akure' => [7.2571, 5.2058, 'Ondo'], 'Ondo' => [7.0932, 4.8353, 'Ondo'], 'Owo' => [7.1962, 5.5868, 'Ondo'],
        'Osogbo' => [7.7827, 4.5418, 'Osun'], 'Ile-Ife' => [7.4824, 4.5603, 'Osun'], 'Ilesa' => [7.6278, 4.7416, 'Osun'],
        'Ado-Ekiti' => [7.6211, 5.2215, 'Ekiti'], 'Lokoja' => [7.8023, 6.7333, 'Kogi'], 'Makurdi' => [7.7322, 8.5391, 'Benue'],
        'Lafia' => [8.4939, 8.5153, 'Nasarawa'], 'Keffi' => [8.8486, 7.8736, 'Nasarawa'],
        'Sokoto' => [13.0059, 5.2476, 'Sokoto'], 'Katsina' => [12.9908, 7.6018, 'Katsina'], 'Bauchi' => [10.3158, 9.8442, 'Bauchi'],
        'Gombe' => [10.2897, 11.1673, 'Gombe'], 'Maiduguri' => [11.8311, 13.1510, 'Borno'], 'Yola' => [9.2035, 12.4954, 'Adamawa'],
        'Jalingo' => [8.8937, 11.3596, 'Taraba'], 'Damaturu' => [11.7470, 11.9608, 'Yobe'], 'Dutse' => [11.7562, 9.3389, 'Jigawa'],
        'Birnin Kebbi' => [12.4539, 4.1975, 'Kebbi'], 'Gusau' => [12.1704, 6.6641, 'Zamfara'],
    ];

    /** Loose lookup ("port harcourt", "Benin", "PH") → [lat, lng, state] or null. */
    public static function find(?string $city): ?array
    {
        $k = self::key($city);
        if ($k === '') {
            return null;
        }
        static $index = null;
        $index ??= collect(self::CITIES)->mapWithKeys(fn ($v, $name) => [self::key($name) => $v])->all()
            + ['ph' => self::CITIES['Port Harcourt'], 'benin' => self::CITIES['Benin City'], 'fct' => self::CITIES['Abuja'], 'ife' => self::CITIES['Ile-Ife'], 'ado ekiti' => self::CITIES['Ado-Ekiti']];

        return $index[$k] ?? null;
    }

    /** Nearest known city to a point, if within $maxKm. @return array{city: string, state: string, km: float}|null */
    public static function nearest(float $lat, float $lng, float $maxKm = 45): ?array
    {
        $best = null;
        foreach (self::CITIES as $name => [$clat, $clng, $state]) {
            $km = \App\Services\Geo\Proximity::km($lat, $lng, $clat, $clng);
            if ($km <= $maxKm && (! $best || $km < $best['km'])) {
                $best = ['city' => $name, 'state' => $state, 'km' => $km];
            }
        }

        return $best;
    }

    public static function key(?string $city): string
    {
        $k = mb_strtolower(trim((string) $city));
        $k = preg_replace('/\b(city|town|metropolis)\b/u', '', $k);

        return trim(preg_replace('/[\s\-]+/u', ' ', $k));
    }
}
