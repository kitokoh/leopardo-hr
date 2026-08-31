<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Data;

/**
 * Villes principales du référentiel TravelAgency — seed tenant-scoped (TRAVEL-202).
 *
 * Liste initiale orientée Afrique (marché interurbain de la verticale) + hubs
 * internationaux fréquents. Les tenants complètent leur référentiel via l'API
 * (`/travel/cities`) — le seed est idempotent et ne réécrit jamais les
 * modifications apportées par le tenant (insertOrIgnore).
 *
 * Format : [iso2, nom, région, latitude, longitude].
 */
final class Cities
{
    /**
     * @return array<int, array{iso2: string, name: string, region: string|null, latitude: float|null, longitude: float|null}>
     */
    public static function all(): array
    {
        $raw = [
            // Cameroun
            ['CM', 'Douala', 'Littoral', 4.0511, 9.7679],
            ['CM', 'Yaoundé', 'Centre', 3.8480, 11.5021],
            ['CM', 'Garoua', 'Nord', 9.3014, 13.3979],
            ['CM', 'Bamenda', 'Nord-Ouest', 5.9631, 10.1591],
            ['CM', 'Bafoussam', 'Ouest', 5.4781, 10.4176],
            ['CM', 'Ngaoundéré', 'Adamaoua', 7.3203, 13.5841],
            ['CM', 'Maroua', 'Extrême-Nord', 10.5910, 14.3159],
            ['CM', 'Bertoua', 'Est', 4.5773, 13.6846],
            ['CM', 'Kribi', 'Sud', 2.9373, 9.9097],
            ['CM', 'Limbé', 'Sud-Ouest', 4.0231, 9.2149],
            // Côte d'Ivoire
            ['CI', 'Abidjan', 'Abidjan', 5.3600, -4.0083],
            ['CI', 'Bouaké', 'Vallée du Bandama', 7.6900, -5.0333],
            ['CI', 'Yamoussoukro', 'Lacs', 6.8167, -5.2833],
            ['CI', 'San-Pédro', 'Bas-Sassandra', 4.7500, -6.6333],
            // Sénégal
            ['SN', 'Dakar', 'Dakar', 14.7167, -17.4677],
            ['SN', 'Thiès', 'Thiès', 14.7833, -16.9167],
            ['SN', 'Saint-Louis', 'Saint-Louis', 16.0179, -16.4896],
            ['SN', 'Ziguinchor', 'Ziguinchor', 12.5833, -16.2719],
            // Mali
            ['ML', 'Bamako', 'Bamako', 12.6392, -8.0029],
            ['ML', 'Sikasso', 'Sikasso', 11.3167, -5.6667],
            ['ML', 'Mopti', 'Mopti', 14.4874, -4.1922],
            // Burkina Faso
            ['BF', 'Ouagadougou', 'Centre', 12.3714, -1.5197],
            ['BF', 'Bobo-Dioulasso', 'Hauts-Bassins', 11.1833, -4.2833],
            // Togo
            ['TG', 'Lomé', 'Maritime', 6.1319, 1.2228],
            ['TG', 'Sokodé', 'Centrale', 8.9833, 1.1333],
            // Bénin
            ['BJ', 'Cotonou', 'Littoral', 6.3703, 2.3912],
            ['BJ', 'Porto-Novo', 'Ouémé', 6.4965, 2.6036],
            ['BJ', 'Parakou', 'Borgou', 9.3500, 2.6167],
            // Niger
            ['NE', 'Niamey', 'Niamey', 13.5137, 2.1098],
            ['NE', 'Zinder', 'Zinder', 13.8053, 8.9883],
            // Tchad
            ['TD', "N'Djaména", 'Ville de Ndjamena', 12.1348, 15.0557],
            ['TD', 'Moundou', 'Logone Occidental', 8.5667, 16.0833],
            // Gabon
            ['GA', 'Libreville', 'Estuaire', 0.4162, 9.4673],
            ['GA', 'Port-Gentil', 'Ogooué-Maritime', -0.7193, 8.7815],
            // Congo
            ['CG', 'Brazzaville', 'Brazzaville', -4.2634, 15.2429],
            ['CG', 'Pointe-Noire', 'Pointe-Noire', -4.7761, 11.8635],
            // RD Congo
            ['CD', 'Kinshasa', 'Kinshasa', -4.4419, 15.2663],
            ['CD', 'Lubumbashi', 'Haut-Katanga', -11.6873, 27.4895],
            ['CD', 'Goma', 'Nord-Kivu', -1.6741, 29.2238],
            // Nigéria
            ['NG', 'Lagos', 'Lagos', 6.5244, 3.3792],
            ['NG', 'Abuja', 'FCT', 9.0765, 7.3986],
            ['NG', 'Kano', 'Kano', 12.0022, 8.5920],
            ['NG', 'Enugu', 'Enugu', 6.4477, 7.5486],
            ['NG', 'Port Harcourt', 'Rivers', 4.8156, 7.0498],
            // Ghana
            ['GH', 'Accra', 'Greater Accra', 5.6037, -0.1870],
            ['GH', 'Kumasi', 'Ashanti', 6.6885, -1.6244],
            ['GH', 'Tamale', 'Northern', 9.4075, -0.8533],
            // Centrafrique
            ['CF', 'Bangui', 'Bangui', 4.3947, 18.5582],
            // Guinée équatoriale
            ['GQ', 'Malabo', 'Bioko Norte', 3.7500, 8.7833],
            // Rwanda / Burundi
            ['RW', 'Kigali', 'Kigali', -1.9441, 30.0619],
            ['BI', 'Bujumbura', 'Bujumbura Mairie', -3.3614, 29.3599],
            // Maghreb
            ['DZ', 'Alger', 'Alger', 36.7538, 3.0588],
            ['DZ', 'Oran', 'Oran', 35.6989, -0.6333],
            ['MA', 'Casablanca', 'Casablanca-Settat', 33.5731, -7.5898],
            ['MA', 'Rabat', 'Rabat-Salé-Kénitra', 34.0209, -6.8416],
            ['TN', 'Tunis', 'Tunis', 36.8065, 10.1815],
            // International
            ['FR', 'Paris', 'Île-de-France', 48.8566, 2.3522],
            ['FR', 'Lyon', 'Auvergne-Rhône-Alpes', 45.7640, 4.8357],
            ['GB', 'Londres', 'Angleterre', 51.5074, -0.1278],
            ['BE', 'Bruxelles', 'Bruxelles-Capitale', 50.8503, 4.3517],
            ['TR', 'Istanbul', 'Marmara', 41.0082, 28.9784],
            ['AE', 'Dubaï', 'Dubaï', 25.2048, 55.2708],
            ['EG', 'Le Caire', 'Le Caire', 30.0444, 31.2357],
            ['ZA', 'Johannesburg', 'Gauteng', -26.2041, 28.0473],
            ['KE', 'Nairobi', 'Nairobi', -1.2921, 36.8219],
            ['ET', 'Addis-Abeba', 'Addis-Abeba', 9.0054, 38.7636],
        ];

        $cities = [];
        foreach ($raw as [$iso2, $name, $region, $lat, $lng]) {
            $cities[] = [
                'iso2' => $iso2,
                'name' => $name,
                'region' => $region,
                'latitude' => $lat,
                'longitude' => $lng,
            ];
        }

        return $cities;
    }
}
