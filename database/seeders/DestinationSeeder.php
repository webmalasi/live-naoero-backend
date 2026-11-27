<?php

namespace Database\Seeders;

use App\Models\Destination;
use Illuminate\Database\Seeder;

class DestinationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $destinations = [
            [
                'name' => 'Brisbane',
                'slug' => 'brisbane',
                'country' => 'Australia',
                'flag' => '🇦🇺',
                'thumbnail' => 'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?w=800&auto=format&fit=crop&q=80',
                'banner_image' => 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=1600&auto=format&fit=crop&q=80',
                'short_description' => "Discover creative spaces, music and restaurants in Queensland's capital city",
                'quote' => "If you're after endless blue skies, golden beaches and tropical rainforests, Queensland is for you.",
                'discover_heading' => 'Discover Brisbane – Your Gateway to Queensland',
                'intro' => "Nestled on the east coast of Australia, Brisbane is the vibrant capital of Queensland, offering a perfect mix of city sophistication, natural beauty, and outdoor adventure. With a population of approximately 2.6 million, it is Australia's third-largest city and a key hub for business, culture, and tourism.",
                'banner_text' => "With an incredible restaurant scene, nearby beaches and hiking, and plenty of live music and events, there's always something to do in Brisbane. Explore today!",
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 1,
                'why_visit' => [
                    [
                        'title' => 'Stunning Weather',
                        'description' => 'Enjoy endless blue skies and a warm, sub-tropical climate year-round. Summer temperatures can reach the high 30s°C, perfect for exploring the great outdoors.',
                    ],
                    [
                        'title' => 'Proximity to Iconic Destinations',
                        'description' => 'The Sunshine Coast (north) and Gold Coast (south) are nearby, offering pristine beaches, national parks, and attractions.',
                    ],
                    [
                        'title' => 'Nature & Outdoor Adventures',
                        'description' => 'From the scenic Brisbane River to the lush rainforests of the hinterland, Queensland provides hiking, wildlife encounters, surfing, and island experiences.',
                    ],
                    [
                        'title' => 'Thriving City Experience',
                        'description' => 'Brisbane is home to bustling markets, world-class dining, arts, and cultural festivals, making it a dynamic destination for both relaxation and exploration.',
                    ],
                    [
                        'title' => 'Money & Payments',
                        'description' => 'Brisbane and the rest of Australia use the Australian Dollar (AUD). Credit cards are widely accepted, and cash can be withdrawn from ATMs and banks in the city and populated areas.',
                    ],
                    [
                        'title' => 'Tipping Culture',
                        'description' => 'Tipping is not expected in Australia, but locals sometimes tip to recognise exceptional service.',
                    ],
                    [
                        'title' => 'What to Pack?',
                        'description' => 'We recommend packing light, breathable clothing suitable for hot weather. A hat and sunscreen are essential, as UV levels are high year-round, even on cloudy days and during winter',
                    ],
                ],
                'things_to_do_intro' => 'Discover endless activities and iconic sights around Brisbane.',
                'things_to_do' => [
                    [
                        'title' => 'Southbank',
                        'description' => 'The Southbank precinct buzzes with life. From shopping to delicious morsels, you will love to wander along the river. Maybe even stop and take a dip at the Streets Beach - a man-made beach and lagoon right beside the river! Take in the cityscape as you relax in the water, or on the sand beside it. Southbank often has street performers and markets and is a great spot to relax in the late afternoon.',
                        'images' => [
                            'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=800&auto=format&fit=crop&q=80',
                            'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?w=800&auto=format&fit=crop&q=80',
                        ],
                    ],
                    [
                        'title' => 'Stunning beaches',
                        'description' => "Just a short drive from Brisbane in virtually any direction, you can find magical beaches with clear water. While you might be sharing the beach and water with other people (and maybe even some dolphins), Queensland's coastline is unrivalled in natural beauty. You can even take a hike along many of the coasts to find the perfect spot. Swim between the red and yellow flags for safety!",
                        'images' => [
                            'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&auto=format&fit=crop&q=80',
                            'https://images.unsplash.com/photo-1519046904884-53103b34b206?w=800&auto=format&fit=crop&q=80',
                        ],
                    ],
                ],
                'stays_intro' => 'Whether you\'re after a central, boutique hotel or somewhere close to shopping, Brisbane has hotels for every budget and desire.',
                'stays' => [
                    [
                        'title' => 'Gambaro Hotel',
                        'description' => 'Located on Caxton Street known for nightlife, offering sophisticated rooms, room service, restaurant, and bar scene.',
                        'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&auto=format&fit=crop&q=80',
                    ],
                    [
                        'title' => 'Quest Chermside',
                        'description' => 'Near Westfield Chermside shopping centre, providing air-conditioned apartments with fully-equipped kitchens.',
                        'image' => 'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=800&auto=format&fit=crop&q=80',
                    ],
                    [
                        'title' => 'The Park Hotel',
                        'description' => 'Opposite Roma Street Parklands with modern rooms, restaurant, and pool at competitive prices.',
                        'image' => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&auto=format&fit=crop&q=80',
                    ],
                ],
            ],
            [
                'name' => 'Naoero',
                'slug' => 'naoero',
                'country' => 'Republic of Naoero',
                'flag' => '🇳🇷',
                'thumbnail' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=800&auto=format&fit=crop&q=80',
                'banner_image' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=1600&auto=format&fit=crop&q=80',
                'short_description' => 'Naoero may be small, but it has lots to offer!',
                'quote' => 'Naoero is one of the smallest countries in the world, spanning just 21 square kilometres and only 6 kilometres at its widest.',
                'discover_heading' => 'Discover Naoero: A Hidden Gem in the Pacific',
                'intro' => 'Naoero is a compact island nation in the Central Pacific, offering natural beauty, cultural heritage and warm hospitality. Despite covering just 21 square kilometres, it offers diverse experiences for every kind of traveller.',
                'banner_text' => "With rich history, incredible fishing and a warm island welcome, there's always something to discover in Naoero. Explore today!",
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 2,
                'why_visit' => [
                    [
                        'title' => 'A Landscape Shaped by History',
                        'description' => "Naoero's rugged terrain is a striking contrast of lush jungle and jagged limestone pinnacles, remnants of the island's phosphate mining era.",
                    ],
                    [
                        'title' => 'Language & Visa Requirements',
                        'items' => [
                            ['label' => 'Languages Spoken:', 'text' => 'Naoeroan & English'],
                            ['label' => 'Visa Requirements:', 'text' => 'All visitors must apply for a visa before arriving in Naoero. Ensure your visa is approved in advance to enjoy a seamless travel experience.'],
                        ],
                    ],
                    [
                        'title' => 'Climate',
                        'description' => 'Located just 40 kilometres south of the equator, Naoero enjoys a tropical climate with warm temperatures year-round.',
                    ],
                    [
                        'title' => 'What to Pack?',
                        'checklist' => [
                            'Light, conservative clothing suitable for warm weather',
                            'Sunscreen, hats, and insect repellent for sun and bug protection',
                        ],
                    ],
                    [
                        'title' => 'Getting Around',
                        'description' => 'Naoero is one of the few countries in the world where you can walk around the entire nation in just a few hours! Early morning exploration is recommended to beat the heat.',
                    ],
                    [
                        'title' => 'Currency & Payment Methods',
                        'description' => 'Naoero uses the Australian Dollar (AUD). Cash is preferred and credit cards are rarely accepted.',
                    ],
                ],
                'things_to_do' => [
                    [
                        'title' => 'War History',
                        'description' => 'The island contains numerous WWII relics, including old war prisons and large guns from the Japanese occupation period, scattered throughout the hills and undergrowth.',
                        'images' => [
                            'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=800&auto=format&fit=crop&q=80',
                            'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&auto=format&fit=crop&q=80',
                        ],
                    ],
                    [
                        'title' => 'Deep-Sea Fishing',
                        'description' => 'With a steep coastal dropoff, Naoero offers some incredible game fishing for those willing to give it a go! Visitors can catch tuna, marlin and barracuda in waters reaching 2,000 metres deep.',
                        'images' => [
                            'https://images.unsplash.com/photo-1519046904884-53103b34b206?w=800&auto=format&fit=crop&q=80',
                            'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?w=800&auto=format&fit=crop&q=80',
                        ],
                    ],
                ],
                'stays' => [
                    [
                        'title' => 'Ewa Lodge',
                        'description' => 'Modern serviced apartments located one minute from the beach with free shuttle service. Features include air-conditioning, TV, an on-site gym, bakery and coffee shop, with a supermarket nearby.',
                        'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&auto=format&fit=crop&q=80',
                    ],
                    [
                        'title' => "Od'n Awo Hotel",
                        'description' => 'A family-owned hotel popular with backpackers, located near the airport and the major supermarket. Amenities include a Chinese restaurant, games room and casino with bar.',
                        'image' => 'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=800&auto=format&fit=crop&q=80',
                    ],
                    [
                        'title' => 'Menen Hotel',
                        'description' => '119 rooms and conference facilities with a swimming pool, tennis court and barbecue areas. Many rooms offer ocean views, with friendly island hospitality and an evening bar.',
                        'image' => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&auto=format&fit=crop&q=80',
                    ],
                ],
            ],
            [
                'name' => 'Kiritimati',
                'slug' => 'kiritimati',
                'country' => 'Kiribati',
                'flag' => '🇰🇮',
                'thumbnail' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&auto=format&fit=crop&q=80',
                'banner_image' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1600&auto=format&fit=crop&q=80',
                'short_description' => 'A captivating paradise, affectionately known as Christmas Island.',
                'quote' => "Whether you're here for fishing, adventure, or relaxation, Kiritimati offers an untouched island paradise like no other.",
                'discover_heading' => "Explore Kiritimati (Christmas Island) – The World's Largest Coral Atoll",
                'intro' => 'Nestled in the Northern Line Islands of the Pacific Ocean, Kiritimati (Christmas Island) is a breathtaking coral atoll renowned for its pristine lagoons, diverse marine life, and unique island culture. Spanning approximately 388 square kilometers, its expansive lagoon mirrors the land area, creating a stunning tropical paradise.',
                'banner_text' => 'Discover the Enchantment of Kiritimati. A captivating paradise, affectionately known as Christmas Island.',
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 3,
                'why_visit' => [
                    [
                        'title' => 'A Rich History & Natural Wonder',
                        'description' => 'Discovered by British Captain James Cook on Christmas Eve in 1777, the island remains deeply connected to its holiday namesake. Today, Kiritimati is celebrated for its unspoiled beauty, world-class fishing, and remote island charm—offering travelers an authentic and unforgettable Pacific experience.',
                    ],
                    [
                        'title' => 'Airports',
                        'checklist' => [
                            'Kiritimati Island (Cassidy International Airport) serves as the main gateway.',
                            'Fanning Island (Tabuaeran) & Teraina (Washington Island) have smaller airports.',
                        ],
                    ],
                    [
                        'title' => 'Money & Payments',
                        'checklist' => [
                            'Currency: Australian Dollar (AUD$) is the official currency, with some places accepting USD$.',
                            'Bank & ATM: ANZ Bank of Kiribati is located in Ronton (London), Kiritimati Island, with ATM access.',
                        ],
                    ],
                ],
                'things_to_do_intro' => 'Today Kiritimati Island is a popular international tourism destination for world-class salt water fly fishing. Birdwatching, diving, surfing, and lagoon tours are also popular recreational activities.',
                'things_to_do' => [
                    [
                        'title' => 'Fishing Paradise',
                        'description' => 'Discover fishing nirvana on Kiritimati Island. From bonefish to giant trevally, this Pacific haven offers endless angling opportunities. Expert guides enhance the experience, while well-equipped gear and stunning landscapes ensure memorable fishing.',
                        'images' => [
                            'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=800&auto=format&fit=crop&q=80',
                            'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&auto=format&fit=crop&q=80',
                        ],
                    ],
                    [
                        'title' => 'Birdwatching',
                        'description' => 'Kiritimati Island is a Pacific seabird sanctuary, houses 18 bird species, including the endemic Christmas Island Warbler. It shelters endangered Phoenix and White Throated Storm Petrels.',
                        'images' => [
                            'https://images.unsplash.com/photo-1519046904884-53103b34b206?w=800&auto=format&fit=crop&q=80',
                            'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?w=800&auto=format&fit=crop&q=80',
                        ],
                    ],
                ],
                'stays' => [
                    [
                        'title' => 'Lagoon View Resort',
                        'description' => 'Family-run hotel by the lagoon, white sand, shaded lounging. Address: Tabwakea Village, Republic of Kiribati.',
                        'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&auto=format&fit=crop&q=80',
                    ],
                    [
                        'title' => 'The Villages Hotel',
                        'description' => "Offers 10 air-conditioned bungalows on Discovery Beach, Tabakea Village. Overlooking the atoll's lagoon, it's perfect for fishing and scuba diving enthusiasts.",
                        'image' => 'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=800&auto=format&fit=crop&q=80',
                    ],
                ],
            ],
            [
                'name' => 'Majuro',
                'slug' => 'majuro',
                'country' => 'Marshall Islands',
                'flag' => '🇲🇭',
                'thumbnail' => 'https://images.unsplash.com/photo-1519046904884-53103b34b206?w=800&auto=format&fit=crop&q=80',
                'banner_image' => 'https://images.unsplash.com/photo-1519046904884-53103b34b206?w=1600&auto=format&fit=crop&q=80',
                'short_description' => 'Discover the capital of the Republic of Marshall Islands.',
                'quote' => 'Majuro offers visitors an authentic Pacific experience, from its breathtaking marine life and cultural traditions to its warm, welcoming community.',
                'discover_heading' => 'Majuro – The Vibrant Capital of the Marshall Islands',
                'intro' => 'Majuro, the capital of the Republic of the Marshall Islands, is a stunning atoll that serves as the political, economic, and cultural center of the nation. Located in the central Pacific Ocean, between Hawaii and the Philippines, Majuro is part of a breathtaking island chain consisting of 64 coral atolls and volcanic islands.',
                'banner_text' => 'With snorkelling opportunities and loads of war history for the avid history buff, Majuro has plenty to offer tourists. Why wait?',
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 4,
                'why_visit' => [
                    [
                        'title' => 'A Unique Island Nation',
                        'description' => 'An atoll is a ring-shaped coral or volcanic land formation that rises from the ocean, encircling a lagoon filled with crystal-clear waters. This distinctive geography makes Majuro a paradise for marine life, diving, and ocean exploration.',
                    ],
                    [
                        'title' => 'Currency & Economy',
                        'checklist' => [
                            'Official Currency: US Dollar (USD$)',
                            'ATMs and banks available in the main center.',
                        ],
                    ],
                ],
                'things_to_do' => [
                    [
                        'title' => 'Diving and Snorkelling',
                        'description' => 'Majuro features vibrant coral ecosystems and WWII wrecks. Raycrew is the local dive operator for bookings.',
                        'images' => [
                            'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=800&auto=format&fit=crop&q=80',
                            'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&auto=format&fit=crop&q=80',
                        ],
                    ],
                ],
                'stays' => [
                    [
                        'title' => 'Marshall Islands Resort',
                        'description' => 'Features tennis courts, a swimming pool, restaurant and cocktail lounge, with complimentary breakfast and air-conditioning.',
                        'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&auto=format&fit=crop&q=80',
                    ],
                ],
            ],
            [
                'name' => 'Nadi',
                'slug' => 'nadi',
                'country' => 'Fiji',
                'flag' => '🇫🇯',
                'thumbnail' => 'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?w=800&auto=format&fit=crop&q=80',
                'banner_image' => 'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?w=1600&auto=format&fit=crop&q=80',
                'short_description' => 'Welcome to Nadi – the sunny gateway to the Fiji islands.',
                'quote' => 'Welcome to Nadi - the gateway to Fiji.',
                'discover_heading' => 'Welcome to Nadi – The Gateway to Fiji',
                'intro' => 'While Nadi is not the capital of Fiji, it is often referred to as the gateway to Fiji due to the international airport there. Fiji is recognized worldwide for its sandy beaches, palm-trees, and turquoise lagoons.',
                'banner_text' => 'Welcome to Nadi - the gateway to Fiji.',
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 5,
                'things_to_do' => [
                    [
                        'title' => 'The Garden of the Sleeping Giant',
                        'description' => 'The Garden of the Sleeping Giant showcases an incredible collection of orchids, as well as beautifully landscaped gardens. Take a jungle walk through the 20 acre gardens, past ponds, flowers and more.',
                        'images' => [
                            'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=800&auto=format&fit=crop&q=80',
                            'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&auto=format&fit=crop&q=80',
                        ],
                    ],
                ],
                'stays' => [
                    [
                        'title' => 'Fiji Marriott Resort Momi Bay',
                        'description' => 'Boasting direct access to the lagoon, with three swimming pools, a swim-up bar and a lounge.',
                        'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&auto=format&fit=crop&q=80',
                    ],
                ],
            ],
            [
                'name' => 'Palau',
                'slug' => 'palau',
                'country' => 'Palau',
                'flag' => '🇵🇼',
                'thumbnail' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=800&auto=format&fit=crop&q=80',
                'banner_image' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=1600&auto=format&fit=crop&q=80',
                'short_description' => 'A pristine paradise with breathtaking Rock Islands and world-class diving.',
                'quote' => 'Palau - Dive into Tranquility',
                'discover_heading' => 'Discover Palau – A Pristine Paradise in the Pacific',
                'intro' => 'Palau is an archipelago of over 500 islands in the Western Pacific, known for its crystal-clear lagoons, world-class diving and rich cultural heritage - a dream destination for adventure seekers, nature lovers and culture enthusiasts alike.',
                'banner_text' => 'Welcome to Palau: a pristine paradise in the Western Pacific, with breathtaking islands, vibrant coral reefs and rich cultural heritage.',
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 6,
                'things_to_do' => [
                    [
                        'title' => 'Jellyfish Lake',
                        'description' => 'Swim among millions of stingless jellyfish at this one-of-a-kind marine sanctuary.',
                        'images' => [
                            'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=800&auto=format&fit=crop&q=80',
                            'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&auto=format&fit=crop&q=80',
                        ],
                    ],
                ],
                'stays' => [
                    [
                        'title' => 'Palau Royal Resort',
                        'description' => 'Luxury resort offering private beach, swimming pool, tennis courts, and world-class hospitality.',
                        'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&auto=format&fit=crop&q=80',
                    ],
                ],
            ],
            [
                'name' => 'Pohnpei',
                'slug' => 'pohnpei',
                'country' => 'Federated States of Micronesia',
                'flag' => '🇫🇲',
                'thumbnail' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&auto=format&fit=crop&q=80',
                'banner_image' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1600&auto=format&fit=crop&q=80',
                'short_description' => 'Ancient ruins, lush waterfalls, and world-class surfing waves.',
                'quote' => "With dozens of waterfalls, ancient ruins and some of the clearest water you've ever seen, Pohnpei is easy to fall in love with.",
                'discover_heading' => 'Explore Pohnpei – Jewel of Micronesia',
                'intro' => "With dozens of waterfalls, ancient ruins and some of the clearest water you've ever seen, Pohnpei is easy to fall in love with.",
                'banner_text' => "With dozens of waterfalls, ancient ruins and some of the clearest water you've ever seen, Pohnpei is easy to fall in love with.",
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 7,
                'things_to_do' => [
                    [
                        'title' => 'Nan Madol',
                        'description' => 'Nan Madol is a wonder to visit. It was the home of the old kings of Pohnpei, and the ruins are a feat of ancient architecture.',
                        'images' => [
                            'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=800&auto=format&fit=crop&q=80',
                            'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?w=800&auto=format&fit=crop&q=80',
                        ],
                    ],
                ],
                'stays' => [
                    [
                        'title' => 'Cliff Rainbow Hotel',
                        'description' => 'Overlooking Sokehs Rock with a 50-year heritage of welcoming visitors to Pohnpei.',
                        'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&auto=format&fit=crop&q=80',
                    ],
                ],
            ],
            [
                'name' => 'Tarawa',
                'slug' => 'tarawa',
                'country' => 'Kiribati',
                'flag' => '🇰🇮',
                'thumbnail' => 'https://images.unsplash.com/photo-1519046904884-53103b34b206?w=800&auto=format&fit=crop&q=80',
                'banner_image' => 'https://images.unsplash.com/photo-1519046904884-53103b34b206?w=1600&auto=format&fit=crop&q=80',
                'short_description' => 'The bustling atoll capital of Kiribati with rich WWII battle history.',
                'quote' => 'Tarawa offers an authentic Pacific lifestyle and historical landmarks across the atoll.',
                'discover_heading' => 'Discover Tarawa – Heart of Kiribati',
                'intro' => 'Tarawa is an atoll and the capital of the Republic of Kiribati, in the central Pacific Ocean. It comprises North Tarawa and South Tarawa, connected by causeways with rich history and stunning lagoon views.',
                'banner_text' => 'Explore the beauty and heritage of Tarawa with Naoero Airlines.',
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 8,
                'things_to_do' => [
                    [
                        'title' => 'Betio WWII Relics',
                        'description' => 'Explore historic coastal defence guns, bunkers and shipwrecks from the Battle of Tarawa.',
                        'images' => [
                            'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=800&auto=format&fit=crop&q=80',
                            'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&auto=format&fit=crop&q=80',
                        ],
                    ],
                ],
                'stays' => [
                    [
                        'title' => 'Otintaai Hotel',
                        'description' => 'Located on the lagoon side in Bikenibeu, South Tarawa, offering comfortable rooms and restaurant.',
                        'image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&auto=format&fit=crop&q=80',
                    ],
                ],
            ],
        ];

        foreach ($destinations as $data) {
            Destination::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
