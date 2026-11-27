<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountryStateCitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();
        try {
            $locations = [
                // 1. Nauru (Home Base)
                [
                    'country' => [
                        'name' => 'Nauru',
                        'iso2' => 'NR',
                        'iso3' => 'NRU',
                        'phone_code' => '+674',
                        'currency' => 'AUD',
                        'currency_symbol' => '$',
                        'currency_name' => 'Australian Dollar',
                        'capital' => 'Yaren',
                        'region' => 'Oceania',
                        'subregion' => 'Micronesia',
                        'flag_emoji' => '🇳🇷',
                        'latitude' => -0.5228,
                        'longitude' => 166.9315,
                        'sort_order' => 1,
                    ],
                    'states' => [
                        [
                            'name' => 'Yaren District',
                            'state_code' => 'YAR',
                            'type' => 'District',
                            'sort_order' => 1,
                            'cities' => [
                                ['name' => 'Yaren (Airport INU)', 'postal_code' => 'NR-YAR', 'iata_code' => 'INU', 'latitude' => -0.5472, 'longitude' => 166.9194, 'sort_order' => 1],
                                ['name' => 'Parliament Precinct', 'postal_code' => 'NR-YAR', 'iata_code' => null, 'latitude' => -0.5460, 'longitude' => 166.9200, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'Aiwo District',
                            'state_code' => 'AIW',
                            'type' => 'District',
                            'sort_order' => 2,
                            'cities' => [
                                ['name' => 'Aiwo Harbour & Port', 'postal_code' => 'NR-AIW', 'iata_code' => null, 'latitude' => -0.5317, 'longitude' => 166.9111, 'sort_order' => 1],
                                ['name' => 'Civic Centre', 'postal_code' => 'NR-AIW', 'iata_code' => null, 'latitude' => -0.5300, 'longitude' => 166.9130, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'Meneng District',
                            'state_code' => 'MEN',
                            'type' => 'District',
                            'sort_order' => 3,
                            'cities' => [
                                ['name' => 'Menen Hotel Precinct', 'postal_code' => 'NR-MEN', 'iata_code' => null, 'latitude' => -0.5433, 'longitude' => 166.9483, 'sort_order' => 1],
                                ['name' => 'Ibwenape', 'postal_code' => 'NR-MEN', 'iata_code' => null, 'latitude' => -0.5400, 'longitude' => 166.9450, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'Anibare District',
                            'state_code' => 'ANI',
                            'type' => 'District',
                            'sort_order' => 4,
                            'cities' => [
                                ['name' => 'Anibare Bay & Boat Harbour', 'postal_code' => 'NR-ANI', 'iata_code' => null, 'latitude' => -0.5342, 'longitude' => 166.9536, 'sort_order' => 1],
                            ]
                        ],
                        [
                            'name' => 'Boe District',
                            'state_code' => 'BOE',
                            'type' => 'District',
                            'sort_order' => 5,
                            'cities' => [
                                ['name' => 'Boe Town', 'postal_code' => 'NR-BOE', 'iata_code' => null, 'latitude' => -0.5417, 'longitude' => 166.9167, 'sort_order' => 1],
                            ]
                        ],
                        [
                            'name' => 'Denigomodu District',
                            'state_code' => 'DEN',
                            'type' => 'District',
                            'sort_order' => 6,
                            'cities' => [
                                ['name' => 'Denig Settlement', 'postal_code' => 'NR-DEN', 'iata_code' => null, 'latitude' => -0.5250, 'longitude' => 166.9167, 'sort_order' => 1],
                            ]
                        ],
                        [
                            'name' => 'Buada District',
                            'state_code' => 'BUA',
                            'type' => 'District',
                            'sort_order' => 7,
                            'cities' => [
                                ['name' => 'Buada Lagoon', 'postal_code' => 'NR-BUA', 'iata_code' => null, 'latitude' => -0.5333, 'longitude' => 166.9250, 'sort_order' => 1],
                            ]
                        ],
                    ]
                ],

                // 2. Australia
                [
                    'country' => [
                        'name' => 'Australia',
                        'iso2' => 'AU',
                        'iso3' => 'AUS',
                        'phone_code' => '+61',
                        'currency' => 'AUD',
                        'currency_symbol' => '$',
                        'currency_name' => 'Australian Dollar',
                        'capital' => 'Canberra',
                        'region' => 'Oceania',
                        'subregion' => 'Australia and New Zealand',
                        'flag_emoji' => '🇦🇺',
                        'latitude' => -25.2744,
                        'longitude' => 133.7751,
                        'sort_order' => 2,
                    ],
                    'states' => [
                        [
                            'name' => 'Queensland',
                            'state_code' => 'QLD',
                            'type' => 'State',
                            'sort_order' => 1,
                            'cities' => [
                                ['name' => 'Brisbane', 'postal_code' => '4000', 'iata_code' => 'BNE', 'latitude' => -27.4698, 'longitude' => 153.0251, 'sort_order' => 1],
                                ['name' => 'Gold Coast', 'postal_code' => '4217', 'iata_code' => 'OOL', 'latitude' => -28.0167, 'longitude' => 153.4000, 'sort_order' => 2],
                                ['name' => 'Cairns', 'postal_code' => '4870', 'iata_code' => 'CNS', 'latitude' => -16.9186, 'longitude' => 145.7781, 'sort_order' => 3],
                                ['name' => 'Townsville', 'postal_code' => '4810', 'iata_code' => 'TSV', 'latitude' => -19.2590, 'longitude' => 146.8169, 'sort_order' => 4],
                                ['name' => 'Sunshine Coast', 'postal_code' => '4551', 'iata_code' => 'MCY', 'latitude' => -26.6500, 'longitude' => 153.0667, 'sort_order' => 5],
                                ['name' => 'Mackay', 'postal_code' => '4740', 'iata_code' => 'MKY', 'latitude' => -21.1411, 'longitude' => 149.1861, 'sort_order' => 6],
                            ]
                        ],
                        [
                            'name' => 'New South Wales',
                            'state_code' => 'NSW',
                            'type' => 'State',
                            'sort_order' => 2,
                            'cities' => [
                                ['name' => 'Sydney', 'postal_code' => '2000', 'iata_code' => 'SYD', 'latitude' => -33.8688, 'longitude' => 151.2093, 'sort_order' => 1],
                                ['name' => 'Newcastle', 'postal_code' => '2300', 'iata_code' => 'NTL', 'latitude' => -32.9283, 'longitude' => 151.7817, 'sort_order' => 2],
                                ['name' => 'Wollongong', 'postal_code' => '2500', 'iata_code' => 'WOL', 'latitude' => -34.4278, 'longitude' => 150.8931, 'sort_order' => 3],
                                ['name' => 'Byron Bay', 'postal_code' => '2481', 'iata_code' => null, 'latitude' => -28.6474, 'longitude' => 153.6120, 'sort_order' => 4],
                            ]
                        ],
                        [
                            'name' => 'Victoria',
                            'state_code' => 'VIC',
                            'type' => 'State',
                            'sort_order' => 3,
                            'cities' => [
                                ['name' => 'Melbourne', 'postal_code' => '3000', 'iata_code' => 'MEL', 'latitude' => -37.8136, 'longitude' => 144.9631, 'sort_order' => 1],
                                ['name' => 'Geelong', 'postal_code' => '3220', 'iata_code' => 'GEX', 'latitude' => -38.1499, 'longitude' => 144.3617, 'sort_order' => 2],
                                ['name' => 'Ballarat', 'postal_code' => '3350', 'iata_code' => null, 'latitude' => -37.5622, 'longitude' => 143.8503, 'sort_order' => 3],
                            ]
                        ],
                        [
                            'name' => 'Western Australia',
                            'state_code' => 'WA',
                            'type' => 'State',
                            'sort_order' => 4,
                            'cities' => [
                                ['name' => 'Perth', 'postal_code' => '6000', 'iata_code' => 'PER', 'latitude' => -31.9505, 'longitude' => 115.8605, 'sort_order' => 1],
                                ['name' => 'Fremantle', 'postal_code' => '6160', 'iata_code' => null, 'latitude' => -32.0569, 'longitude' => 115.7439, 'sort_order' => 2],
                                ['name' => 'Broome', 'postal_code' => '6725', 'iata_code' => 'BME', 'latitude' => -17.9614, 'longitude' => 122.2359, 'sort_order' => 3],
                            ]
                        ],
                        [
                            'name' => 'South Australia',
                            'state_code' => 'SA',
                            'type' => 'State',
                            'sort_order' => 5,
                            'cities' => [
                                ['name' => 'Adelaide', 'postal_code' => '5000', 'iata_code' => 'ADL', 'latitude' => -34.9285, 'longitude' => 138.6007, 'sort_order' => 1],
                            ]
                        ],
                        [
                            'name' => 'Australian Capital Territory',
                            'state_code' => 'ACT',
                            'type' => 'Territory',
                            'sort_order' => 6,
                            'cities' => [
                                ['name' => 'Canberra', 'postal_code' => '2600', 'iata_code' => 'CBR', 'latitude' => -35.2809, 'longitude' => 149.1300, 'sort_order' => 1],
                            ]
                        ],
                    ]
                ],

                // 3. Fiji
                [
                    'country' => [
                        'name' => 'Fiji',
                        'iso2' => 'FJ',
                        'iso3' => 'FJI',
                        'phone_code' => '+679',
                        'currency' => 'FJD',
                        'currency_symbol' => 'FJ$',
                        'currency_name' => 'Fijian Dollar',
                        'capital' => 'Suva',
                        'region' => 'Oceania',
                        'subregion' => 'Melanesia',
                        'flag_emoji' => '🇫🇯',
                        'latitude' => -17.7134,
                        'longitude' => 178.0650,
                        'sort_order' => 3,
                    ],
                    'states' => [
                        [
                            'name' => 'Western Division',
                            'state_code' => 'WST',
                            'type' => 'Division',
                            'sort_order' => 1,
                            'cities' => [
                                ['name' => 'Nadi (Airport NAN)', 'postal_code' => 'FJ-WST', 'iata_code' => 'NAN', 'latitude' => -17.7765, 'longitude' => 177.4356, 'sort_order' => 1],
                                ['name' => 'Lautoka', 'postal_code' => 'FJ-WST', 'iata_code' => null, 'latitude' => -17.6242, 'longitude' => 177.4528, 'sort_order' => 2],
                                ['name' => 'Denarau Island', 'postal_code' => 'FJ-WST', 'iata_code' => null, 'latitude' => -17.7719, 'longitude' => 177.3753, 'sort_order' => 3],
                            ]
                        ],
                        [
                            'name' => 'Central Division',
                            'state_code' => 'CNT',
                            'type' => 'Division',
                            'sort_order' => 2,
                            'cities' => [
                                ['name' => 'Suva (Capital SUV)', 'postal_code' => 'FJ-CNT', 'iata_code' => 'SUV', 'latitude' => -18.1416, 'longitude' => 178.4419, 'sort_order' => 1],
                                ['name' => 'Nausori', 'postal_code' => 'FJ-CNT', 'iata_code' => 'SUV', 'latitude' => -18.0244, 'longitude' => 178.5456, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'Northern Division',
                            'state_code' => 'NTH',
                            'type' => 'Division',
                            'sort_order' => 3,
                            'cities' => [
                                ['name' => 'Labasa', 'postal_code' => 'FJ-NTH', 'iata_code' => 'LBS', 'latitude' => -16.4332, 'longitude' => 179.3645, 'sort_order' => 1],
                                ['name' => 'Savusavu', 'postal_code' => 'FJ-NTH', 'iata_code' => 'SVU', 'latitude' => -16.7794, 'longitude' => 179.3378, 'sort_order' => 2],
                            ]
                        ],
                    ]
                ],

                // 4. Kiribati
                [
                    'country' => [
                        'name' => 'Kiribati',
                        'iso2' => 'KI',
                        'iso3' => 'KIR',
                        'phone_code' => '+686',
                        'currency' => 'AUD',
                        'currency_symbol' => '$',
                        'currency_name' => 'Australian Dollar',
                        'capital' => 'South Tarawa',
                        'region' => 'Oceania',
                        'subregion' => 'Micronesia',
                        'flag_emoji' => '🇰🇮',
                        'latitude' => 1.8709,
                        'longitude' => -157.3630,
                        'sort_order' => 4,
                    ],
                    'states' => [
                        [
                            'name' => 'Gilbert Islands',
                            'state_code' => 'GIL',
                            'type' => 'Island Group',
                            'sort_order' => 1,
                            'cities' => [
                                ['name' => 'Tarawa (Bonriki TRW)', 'postal_code' => 'KI-TRW', 'iata_code' => 'TRW', 'latitude' => 1.3817, 'longitude' => 173.1472, 'sort_order' => 1],
                                ['name' => 'Betio', 'postal_code' => 'KI-TRW', 'iata_code' => null, 'latitude' => 1.3575, 'longitude' => 172.9300, 'sort_order' => 2],
                                ['name' => 'Bairiki', 'postal_code' => 'KI-TRW', 'iata_code' => null, 'latitude' => 1.3283, 'longitude' => 172.9772, 'sort_order' => 3],
                            ]
                        ],
                        [
                            'name' => 'Line Islands',
                            'state_code' => 'LIN',
                            'type' => 'Island Group',
                            'sort_order' => 2,
                            'cities' => [
                                ['name' => 'Kiritimati / Christmas Island', 'postal_code' => 'KI-CXI', 'iata_code' => 'CXI', 'latitude' => 1.8722, 'longitude' => -157.3631, 'sort_order' => 1],
                            ]
                        ],
                    ]
                ],

                // 5. Marshall Islands
                [
                    'country' => [
                        'name' => 'Marshall Islands',
                        'iso2' => 'MH',
                        'iso3' => 'MHL',
                        'phone_code' => '+692',
                        'currency' => 'USD',
                        'currency_symbol' => '$',
                        'currency_name' => 'United States Dollar',
                        'capital' => 'Majuro',
                        'region' => 'Oceania',
                        'subregion' => 'Micronesia',
                        'flag_emoji' => '🇲🇭',
                        'latitude' => 7.1315,
                        'longitude' => 171.1845,
                        'sort_order' => 5,
                    ],
                    'states' => [
                        [
                            'name' => 'Ratak Chain',
                            'state_code' => 'RAT',
                            'type' => 'Chain',
                            'sort_order' => 1,
                            'cities' => [
                                ['name' => 'Majuro Atoll (MAJ)', 'postal_code' => '96960', 'iata_code' => 'MAJ', 'latitude' => 7.0897, 'longitude' => 171.3803, 'sort_order' => 1],
                                ['name' => 'Laura Village', 'postal_code' => '96960', 'iata_code' => null, 'latitude' => 7.1350, 'longitude' => 171.0475, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'Ralik Chain',
                            'state_code' => 'RAL',
                            'type' => 'Chain',
                            'sort_order' => 2,
                            'cities' => [
                                ['name' => 'Kwajalein Atoll (KWA)', 'postal_code' => '96970', 'iata_code' => 'KWA', 'latitude' => 8.7167, 'longitude' => 167.7333, 'sort_order' => 1],
                                ['name' => 'Ebeye Island', 'postal_code' => '96970', 'iata_code' => null, 'latitude' => 8.7833, 'longitude' => 167.7333, 'sort_order' => 2],
                            ]
                        ],
                    ]
                ],

                // 6. Federated States of Micronesia
                [
                    'country' => [
                        'name' => 'Micronesia (FSM)',
                        'iso2' => 'FM',
                        'iso3' => 'FSM',
                        'phone_code' => '+691',
                        'currency' => 'USD',
                        'currency_symbol' => '$',
                        'currency_name' => 'United States Dollar',
                        'capital' => 'Palikir',
                        'region' => 'Oceania',
                        'subregion' => 'Micronesia',
                        'flag_emoji' => '🇫🇲',
                        'latitude' => 6.8874,
                        'longitude' => 158.2150,
                        'sort_order' => 6,
                    ],
                    'states' => [
                        [
                            'name' => 'Pohnpei',
                            'state_code' => 'PNI',
                            'type' => 'State',
                            'sort_order' => 1,
                            'cities' => [
                                ['name' => 'Pohnpei (Kolonia PNI)', 'postal_code' => '96941', 'iata_code' => 'PNI', 'latitude' => 6.9644, 'longitude' => 158.2089, 'sort_order' => 1],
                                ['name' => 'Palikir (Capital)', 'postal_code' => '96941', 'iata_code' => null, 'latitude' => 6.9178, 'longitude' => 158.1611, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'Chuuk',
                            'state_code' => 'TRK',
                            'type' => 'State',
                            'sort_order' => 2,
                            'cities' => [
                                ['name' => 'Weno (Chuuk TKK)', 'postal_code' => '96942', 'iata_code' => 'TKK', 'latitude' => 7.4464, 'longitude' => 151.8464, 'sort_order' => 1],
                            ]
                        ],
                        [
                            'name' => 'Kosrae',
                            'state_code' => 'KSA',
                            'type' => 'State',
                            'sort_order' => 3,
                            'cities' => [
                                ['name' => 'Kosrae (Tofol KSA)', 'postal_code' => '96944', 'iata_code' => 'KSA', 'latitude' => 5.3283, 'longitude' => 162.9808, 'sort_order' => 1],
                            ]
                        ],
                        [
                            'name' => 'Yap',
                            'state_code' => 'YAP',
                            'type' => 'State',
                            'sort_order' => 4,
                            'cities' => [
                                ['name' => 'Colonia (Yap YAP)', 'postal_code' => '96943', 'iata_code' => 'YAP', 'latitude' => 9.5167, 'longitude' => 138.1333, 'sort_order' => 1],
                            ]
                        ],
                    ]
                ],

                // 7. Solomon Islands
                [
                    'country' => [
                        'name' => 'Solomon Islands',
                        'iso2' => 'SB',
                        'iso3' => 'SLB',
                        'phone_code' => '+677',
                        'currency' => 'SBD',
                        'currency_symbol' => 'SI$',
                        'currency_name' => 'Solomon Islands Dollar',
                        'capital' => 'Honiara',
                        'region' => 'Oceania',
                        'subregion' => 'Melanesia',
                        'flag_emoji' => '🇸🇧',
                        'latitude' => -9.6457,
                        'longitude' => 160.1562,
                        'sort_order' => 7,
                    ],
                    'states' => [
                        [
                            'name' => 'Guadalcanal',
                            'state_code' => 'GUA',
                            'type' => 'Province',
                            'sort_order' => 1,
                            'cities' => [
                                ['name' => 'Honiara (Henderson HIR)', 'postal_code' => 'SB-GUA', 'iata_code' => 'HIR', 'latitude' => -9.4286, 'longitude' => 160.0547, 'sort_order' => 1],
                                ['name' => 'Point Cruz', 'postal_code' => 'SB-GUA', 'iata_code' => null, 'latitude' => -9.4300, 'longitude' => 159.9500, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'Western Province',
                            'state_code' => 'WST',
                            'type' => 'Province',
                            'sort_order' => 2,
                            'cities' => [
                                ['name' => 'Gizo', 'postal_code' => 'SB-WST', 'iata_code' => 'GZO', 'latitude' => -8.1030, 'longitude' => 156.8419, 'sort_order' => 1],
                                ['name' => 'Munda', 'postal_code' => 'SB-WST', 'iata_code' => 'MUA', 'latitude' => -8.3283, 'longitude' => 157.2608, 'sort_order' => 2],
                            ]
                        ],
                    ]
                ],

                // 8. Palau
                [
                    'country' => [
                        'name' => 'Palau',
                        'iso2' => 'PW',
                        'iso3' => 'PLW',
                        'phone_code' => '+680',
                        'currency' => 'USD',
                        'currency_symbol' => '$',
                        'currency_name' => 'United States Dollar',
                        'capital' => 'Ngerulmud',
                        'region' => 'Oceania',
                        'subregion' => 'Micronesia',
                        'flag_emoji' => '🇵🇼',
                        'latitude' => 7.5150,
                        'longitude' => 134.5825,
                        'sort_order' => 8,
                    ],
                    'states' => [
                        [
                            'name' => 'Koror State',
                            'state_code' => 'KOR',
                            'type' => 'State',
                            'sort_order' => 1,
                            'cities' => [
                                ['name' => 'Koror City (ROR)', 'postal_code' => '96940', 'iata_code' => 'ROR', 'latitude' => 7.3431, 'longitude' => 134.4789, 'sort_order' => 1],
                                ['name' => 'Malakal Island', 'postal_code' => '96940', 'iata_code' => null, 'latitude' => 7.3300, 'longitude' => 134.4500, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'Airai State',
                            'state_code' => 'AIR',
                            'type' => 'State',
                            'sort_order' => 2,
                            'cities' => [
                                ['name' => 'Airai (Airport)', 'postal_code' => '96940', 'iata_code' => 'ROR', 'latitude' => 7.3672, 'longitude' => 134.5442, 'sort_order' => 1],
                            ]
                        ],
                    ]
                ],

                // 9. Tuvalu
                [
                    'country' => [
                        'name' => 'Tuvalu',
                        'iso2' => 'TV',
                        'iso3' => 'TUV',
                        'phone_code' => '+688',
                        'currency' => 'AUD',
                        'currency_symbol' => '$',
                        'currency_name' => 'Australian Dollar',
                        'capital' => 'Funafuti',
                        'region' => 'Oceania',
                        'subregion' => 'Polynesia',
                        'flag_emoji' => '🇹🇻',
                        'latitude' => -7.1095,
                        'longitude' => 177.6493,
                        'sort_order' => 9,
                    ],
                    'states' => [
                        [
                            'name' => 'Funafuti Atoll',
                            'state_code' => 'FUN',
                            'type' => 'Atoll',
                            'sort_order' => 1,
                            'cities' => [
                                ['name' => 'Funafuti / Fongafale (FUN)', 'postal_code' => 'TV-FUN', 'iata_code' => 'FUN', 'latitude' => -8.5211, 'longitude' => 179.1962, 'sort_order' => 1],
                                ['name' => 'Vaiaku Village', 'postal_code' => 'TV-FUN', 'iata_code' => null, 'latitude' => -8.5200, 'longitude' => 179.2000, 'sort_order' => 2],
                            ]
                        ],
                    ]
                ],

                // 10. Vanuatu
                [
                    'country' => [
                        'name' => 'Vanuatu',
                        'iso2' => 'VU',
                        'iso3' => 'VUT',
                        'phone_code' => '+678',
                        'currency' => 'VUV',
                        'currency_symbol' => 'VT',
                        'currency_name' => 'Vanuatu Vatu',
                        'capital' => 'Port Vila',
                        'region' => 'Oceania',
                        'subregion' => 'Melanesia',
                        'flag_emoji' => '🇻🇺',
                        'latitude' => -15.3767,
                        'longitude' => 166.9592,
                        'sort_order' => 10,
                    ],
                    'states' => [
                        [
                            'name' => 'Shefa Province',
                            'state_code' => 'SHE',
                            'type' => 'Province',
                            'sort_order' => 1,
                            'cities' => [
                                ['name' => 'Port Vila (Bauerfield VLI)', 'postal_code' => 'VU-SHE', 'iata_code' => 'VLI', 'latitude' => -17.7333, 'longitude' => 168.3222, 'sort_order' => 1],
                            ]
                        ],
                        [
                            'name' => 'Sanma Province',
                            'state_code' => 'SAN',
                            'type' => 'Province',
                            'sort_order' => 2,
                            'cities' => [
                                ['name' => 'Luganville (Santo SON)', 'postal_code' => 'VU-SAN', 'iata_code' => 'SON', 'latitude' => -15.5167, 'longitude' => 167.1667, 'sort_order' => 1],
                            ]
                        ],
                    ]
                ],

                // 11. New Zealand
                [
                    'country' => [
                        'name' => 'New Zealand',
                        'iso2' => 'NZ',
                        'iso3' => 'NZL',
                        'phone_code' => '+64',
                        'currency' => 'NZD',
                        'currency_symbol' => 'NZ$',
                        'currency_name' => 'New Zealand Dollar',
                        'capital' => 'Wellington',
                        'region' => 'Oceania',
                        'subregion' => 'Australia and New Zealand',
                        'flag_emoji' => '🇳🇿',
                        'latitude' => -40.9006,
                        'longitude' => 174.8860,
                        'sort_order' => 11,
                    ],
                    'states' => [
                        [
                            'name' => 'Auckland Region',
                            'state_code' => 'AUK',
                            'type' => 'Region',
                            'sort_order' => 1,
                            'cities' => [
                                ['name' => 'Auckland', 'postal_code' => '1010', 'iata_code' => 'AKL', 'latitude' => -36.8485, 'longitude' => 174.7633, 'sort_order' => 1],
                            ]
                        ],
                        [
                            'name' => 'Wellington Region',
                            'state_code' => 'WGN',
                            'type' => 'Region',
                            'sort_order' => 2,
                            'cities' => [
                                ['name' => 'Wellington', 'postal_code' => '6011', 'iata_code' => 'WLG', 'latitude' => -41.2865, 'longitude' => 174.7762, 'sort_order' => 1],
                            ]
                        ],
                        [
                            'name' => 'Canterbury',
                            'state_code' => 'CAN',
                            'type' => 'Region',
                            'sort_order' => 3,
                            'cities' => [
                                ['name' => 'Christchurch', 'postal_code' => '8011', 'iata_code' => 'CHC', 'latitude' => -43.5321, 'longitude' => 172.6362, 'sort_order' => 1],
                            ]
                        ],
                    ]
                ],

                // 12. India
                [
                    'country' => [
                        'name' => 'India',
                        'iso2' => 'IN',
                        'iso3' => 'IND',
                        'phone_code' => '+91',
                        'currency' => 'INR',
                        'currency_symbol' => '₹',
                        'currency_name' => 'Indian Rupee',
                        'capital' => 'New Delhi',
                        'region' => 'Asia',
                        'subregion' => 'Southern Asia',
                        'flag_emoji' => '🇮🇳',
                        'latitude' => 20.5937,
                        'longitude' => 78.9629,
                        'sort_order' => 12,
                    ],
                    'states' => [
                        [
                            'name' => 'Maharashtra',
                            'state_code' => 'MH',
                            'type' => 'State',
                            'sort_order' => 1,
                            'cities' => [
                                ['name' => 'Mumbai', 'postal_code' => '400001', 'iata_code' => 'BOM', 'latitude' => 19.0760, 'longitude' => 72.8777, 'sort_order' => 1],
                                ['name' => 'Pune', 'postal_code' => '411001', 'iata_code' => 'PNQ', 'latitude' => 18.5204, 'longitude' => 73.8567, 'sort_order' => 2],
                                ['name' => 'Nagpur', 'postal_code' => '440001', 'iata_code' => 'NAG', 'latitude' => 21.1458, 'longitude' => 79.0882, 'sort_order' => 3],
                            ]
                        ],
                        [
                            'name' => 'Delhi (NCT)',
                            'state_code' => 'DL',
                            'type' => 'Union Territory',
                            'sort_order' => 2,
                            'cities' => [
                                ['name' => 'New Delhi', 'postal_code' => '110001', 'iata_code' => 'DEL', 'latitude' => 28.6139, 'longitude' => 77.2090, 'sort_order' => 1],
                                ['name' => 'South Delhi', 'postal_code' => '110049', 'iata_code' => null, 'latitude' => 28.5400, 'longitude' => 77.2200, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'Karnataka',
                            'state_code' => 'KA',
                            'type' => 'State',
                            'sort_order' => 3,
                            'cities' => [
                                ['name' => 'Bengaluru', 'postal_code' => '560001', 'iata_code' => 'BLR', 'latitude' => 12.9716, 'longitude' => 77.5946, 'sort_order' => 1],
                                ['name' => 'Mysuru', 'postal_code' => '570001', 'iata_code' => 'MYQ', 'latitude' => 12.2958, 'longitude' => 76.6394, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'Tamil Nadu',
                            'state_code' => 'TN',
                            'type' => 'State',
                            'sort_order' => 4,
                            'cities' => [
                                ['name' => 'Chennai', 'postal_code' => '600001', 'iata_code' => 'MAA', 'latitude' => 13.0827, 'longitude' => 80.2707, 'sort_order' => 1],
                                ['name' => 'Coimbatore', 'postal_code' => '641001', 'iata_code' => 'CJB', 'latitude' => 11.0168, 'longitude' => 76.9558, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'Gujarat',
                            'state_code' => 'GJ',
                            'type' => 'State',
                            'sort_order' => 5,
                            'cities' => [
                                ['name' => 'Ahmedabad', 'postal_code' => '380001', 'iata_code' => 'AMD', 'latitude' => 23.0225, 'longitude' => 72.5714, 'sort_order' => 1],
                                ['name' => 'Surat', 'postal_code' => '395001', 'iata_code' => 'STV', 'latitude' => 21.1702, 'longitude' => 72.8311, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'Uttar Pradesh',
                            'state_code' => 'UP',
                            'type' => 'State',
                            'sort_order' => 6,
                            'cities' => [
                                ['name' => 'Lucknow', 'postal_code' => '226001', 'iata_code' => 'LKO', 'latitude' => 26.8467, 'longitude' => 80.9462, 'sort_order' => 1],
                                ['name' => 'Noida', 'postal_code' => '201301', 'iata_code' => null, 'latitude' => 28.5355, 'longitude' => 77.3910, 'sort_order' => 2],
                            ]
                        ],
                    ]
                ],

                // 13. United States
                [
                    'country' => [
                        'name' => 'United States',
                        'iso2' => 'US',
                        'iso3' => 'USA',
                        'phone_code' => '+1',
                        'currency' => 'USD',
                        'currency_symbol' => '$',
                        'currency_name' => 'United States Dollar',
                        'capital' => 'Washington, D.C.',
                        'region' => 'Americas',
                        'subregion' => 'Northern America',
                        'flag_emoji' => '🇺🇸',
                        'latitude' => 37.0902,
                        'longitude' => -95.7129,
                        'sort_order' => 13,
                    ],
                    'states' => [
                        [
                            'name' => 'California',
                            'state_code' => 'CA',
                            'type' => 'State',
                            'sort_order' => 1,
                            'cities' => [
                                ['name' => 'Los Angeles', 'postal_code' => '90001', 'iata_code' => 'LAX', 'latitude' => 34.0522, 'longitude' => -118.2437, 'sort_order' => 1],
                                ['name' => 'San Francisco', 'postal_code' => '94101', 'iata_code' => 'SFO', 'latitude' => 37.7749, 'longitude' => -122.4194, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'Hawaii',
                            'state_code' => 'HI',
                            'type' => 'State',
                            'sort_order' => 2,
                            'cities' => [
                                ['name' => 'Honolulu', 'postal_code' => '96801', 'iata_code' => 'HNL', 'latitude' => 21.3069, 'longitude' => -157.8583, 'sort_order' => 1],
                                ['name' => 'Kahului', 'postal_code' => '96732', 'iata_code' => 'OGG', 'latitude' => 20.8893, 'longitude' => -156.4729, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'Guam (US Territory)',
                            'state_code' => 'GU',
                            'type' => 'Territory',
                            'sort_order' => 3,
                            'cities' => [
                                ['name' => 'Tamuning / Hagatna (GUM)', 'postal_code' => '96913', 'iata_code' => 'GUM', 'latitude' => 13.5042, 'longitude' => 144.7972, 'sort_order' => 1],
                                ['name' => 'Tumon Bay', 'postal_code' => '96913', 'iata_code' => null, 'latitude' => 13.5139, 'longitude' => 144.8056, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'New York',
                            'state_code' => 'NY',
                            'type' => 'State',
                            'sort_order' => 4,
                            'cities' => [
                                ['name' => 'New York City', 'postal_code' => '10001', 'iata_code' => 'JFK', 'latitude' => 40.7128, 'longitude' => -74.0060, 'sort_order' => 1],
                            ]
                        ],
                    ]
                ],

                // 14. Singapore
                [
                    'country' => [
                        'name' => 'Singapore',
                        'iso2' => 'SG',
                        'iso3' => 'SGP',
                        'phone_code' => '+65',
                        'currency' => 'SGD',
                        'currency_symbol' => 'S$',
                        'currency_name' => 'Singapore Dollar',
                        'capital' => 'Singapore',
                        'region' => 'Asia',
                        'subregion' => 'South-Eastern Asia',
                        'flag_emoji' => '🇸🇬',
                        'latitude' => 1.3521,
                        'longitude' => 103.8198,
                        'sort_order' => 14,
                    ],
                    'states' => [
                        [
                            'name' => 'Central Region',
                            'state_code' => 'SGP-CR',
                            'type' => 'Region',
                            'sort_order' => 1,
                            'cities' => [
                                ['name' => 'Singapore (Changi SIN)', 'postal_code' => '018989', 'iata_code' => 'SIN', 'latitude' => 1.3644, 'longitude' => 103.9915, 'sort_order' => 1],
                                ['name' => 'Marina Bay', 'postal_code' => '018956', 'iata_code' => null, 'latitude' => 1.2847, 'longitude' => 103.8610, 'sort_order' => 2],
                            ]
                        ],
                    ]
                ],

                // 15. Japan
                [
                    'country' => [
                        'name' => 'Japan',
                        'iso2' => 'JP',
                        'iso3' => 'JPN',
                        'phone_code' => '+81',
                        'currency' => 'JPY',
                        'currency_symbol' => '¥',
                        'currency_name' => 'Japanese Yen',
                        'capital' => 'Tokyo',
                        'region' => 'Asia',
                        'subregion' => 'Eastern Asia',
                        'flag_emoji' => '🇯🇵',
                        'latitude' => 36.2048,
                        'longitude' => 138.2529,
                        'sort_order' => 15,
                    ],
                    'states' => [
                        [
                            'name' => 'Tokyo Prefecture',
                            'state_code' => '13',
                            'type' => 'Prefecture',
                            'sort_order' => 1,
                            'cities' => [
                                ['name' => 'Tokyo (Narita / Haneda)', 'postal_code' => '100-0001', 'iata_code' => 'HND', 'latitude' => 35.6762, 'longitude' => 139.6503, 'sort_order' => 1],
                            ]
                        ],
                        [
                            'name' => 'Osaka Prefecture',
                            'state_code' => '27',
                            'type' => 'Prefecture',
                            'sort_order' => 2,
                            'cities' => [
                                ['name' => 'Osaka (KIX)', 'postal_code' => '530-0001', 'iata_code' => 'KIX', 'latitude' => 34.6937, 'longitude' => 135.5023, 'sort_order' => 1],
                            ]
                        ],
                    ]
                ],

                // 16. Philippines
                [
                    'country' => [
                        'name' => 'Philippines',
                        'iso2' => 'PH',
                        'iso3' => 'PHL',
                        'phone_code' => '+63',
                        'currency' => 'PHP',
                        'currency_symbol' => '₱',
                        'currency_name' => 'Philippine Peso',
                        'capital' => 'Manila',
                        'region' => 'Asia',
                        'subregion' => 'South-Eastern Asia',
                        'flag_emoji' => '🇵🇭',
                        'latitude' => 12.8797,
                        'longitude' => 121.7740,
                        'sort_order' => 16,
                    ],
                    'states' => [
                        [
                            'name' => 'National Capital Region',
                            'state_code' => 'NCR',
                            'type' => 'Region',
                            'sort_order' => 1,
                            'cities' => [
                                ['name' => 'Manila (Ninoy Aquino MNL)', 'postal_code' => '1000', 'iata_code' => 'MNL', 'latitude' => 14.5995, 'longitude' => 120.9842, 'sort_order' => 1],
                                ['name' => 'Makati City', 'postal_code' => '1200', 'iata_code' => null, 'latitude' => 14.5547, 'longitude' => 121.0244, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'Central Visayas',
                            'state_code' => '07',
                            'type' => 'Region',
                            'sort_order' => 2,
                            'cities' => [
                                ['name' => 'Cebu City (CEB)', 'postal_code' => '6000', 'iata_code' => 'CEB', 'latitude' => 10.3157, 'longitude' => 123.8854, 'sort_order' => 1],
                            ]
                        ],
                    ]
                ],
            ];

            foreach ($locations as $item) {
                $countryData = $item['country'];
                $country = Country::updateOrCreate(
                    ['iso2' => $countryData['iso2']],
                    $countryData
                );

                if (!empty($item['states'])) {
                    foreach ($item['states'] as $stateData) {
                        $cities = $stateData['cities'] ?? [];
                        unset($stateData['cities']);
                        $stateData['country_id'] = $country->id;

                        $state = State::updateOrCreate(
                            [
                                'country_id' => $country->id,
                                'name' => $stateData['name']
                            ],
                            $stateData
                        );

                        if (!empty($cities)) {
                            foreach ($cities as $cityData) {
                                $cityData['country_id'] = $country->id;
                                $cityData['state_id'] = $state->id;

                                City::updateOrCreate(
                                    [
                                        'country_id' => $country->id,
                                        'state_id' => $state->id,
                                        'name' => $cityData['name']
                                    ],
                                    $cityData
                                );
                            }
                        }
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
