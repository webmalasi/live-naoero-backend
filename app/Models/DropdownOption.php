<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DropdownOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'group',
        'name',
        'value',
        'badge_color',
        'icon',
        'description',
        'sort_order',
        'is_active',
        'is_system',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (DropdownOption $option) {
            if (empty($option->value)) {
                $option->value = $option->name;
            }
            if (empty($option->badge_color)) {
                $option->badge_color = 'blue';
            }
        });
    }

    /**
     * Scope for a specific dropdown group.
     */
    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    /**
     * Scope for active options.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered options.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get all active options for a specific group as an Eloquent collection.
     */
    public static function getGroup(string $group, bool $onlyActive = true): Collection
    {
        $query = static::group($group)->ordered();
        if ($onlyActive) {
            $query->active();
        }
        return $query->get();
    }

    /**
     * Get array of plain string values for validation rules or basic dropdowns.
     */
    public static function getValues(string $group, bool $onlyActive = true): array
    {
        $options = static::getGroup($group, $onlyActive);
        if ($options->isEmpty()) {
            // Fallback default options if not yet seeded
            $meta = static::getGroupMeta();
            if (isset($meta[$group]['defaults'])) {
                return array_column($meta[$group]['defaults'], 'value');
            }
            return [];
        }
        return $options->pluck('value')->toArray();
    }

    /**
     * Get key-value associative map [value => name] for select dropdowns.
     */
    public static function getKeyValueMap(string $group, bool $onlyActive = true): array
    {
        $options = static::getGroup($group, $onlyActive);
        if ($options->isEmpty()) {
            $meta = static::getGroupMeta();
            if (isset($meta[$group]['defaults'])) {
                $map = [];
                foreach ($meta[$group]['defaults'] as $def) {
                    $map[$def['value']] = $def['name'];
                }
                return $map;
            }
            return [];
        }
        return $options->pluck('name', 'value')->toArray();
    }

    /**
     * Create or retrieve an existing option by group and value.
     */
    public static function createOrGet(string $group, string $name, ?string $value = null, array $attributes = []): static
    {
        $value = $value ?: $name;
        $option = static::where('group', $group)->where('value', $value)->first();
        if ($option) {
            return $option;
        }

        return static::create(array_merge([
            'group' => $group,
            'name' => $name,
            'value' => $value,
            'sort_order' => static::where('group', $group)->max('sort_order') + 1,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Master registry of all system dropdown groups, module mappings, icons, and default seed options.
     */
    public static function getGroupMeta(): array
    {
        return [
            'shop_categories' => [
                'title' => 'Shop Product Categories',
                'module' => 'Shop & Gift Cards',
                'icon' => '🛍️',
                'description' => 'Categories used to filter merchandise, apparel, accessories, and gift cards.',
                'defaults' => [
                    ['name' => 'Gift Card', 'value' => 'Gift Card', 'badge_color' => 'amber', 'icon' => '💳', 'sort_order' => 1],
                    ['name' => 'Merch', 'value' => 'Merch', 'badge_color' => 'blue', 'icon' => '🛍️', 'sort_order' => 2],
                    ['name' => 'Apparel', 'value' => 'Apparel', 'badge_color' => 'indigo', 'icon' => '🧥', 'sort_order' => 3],
                    ['name' => 'Drinkware', 'value' => 'Drinkware', 'badge_color' => 'emerald', 'icon' => '🍶', 'sort_order' => 4],
                    ['name' => 'Caps', 'value' => 'Caps', 'badge_color' => 'purple', 'icon' => '🧢', 'sort_order' => 5],
                    ['name' => 'Tshirts', 'value' => 'Tshirts', 'badge_color' => 'slate', 'icon' => '👕', 'sort_order' => 6],
                    ['name' => 'Toys', 'value' => 'Toys', 'badge_color' => 'rose', 'icon' => '🧸', 'sort_order' => 7],
                ]
            ],
            'shop_types' => [
                'title' => 'Shop Classification Types',
                'module' => 'Shop & Gift Cards',
                'icon' => '🏷️',
                'description' => 'Product types defining behavior (e.g. Physical Merchandise vs Digital Gift Voucher).',
                'defaults' => [
                    ['name' => 'Physical Merchandise', 'value' => 'merch', 'badge_color' => 'blue', 'icon' => '👕', 'sort_order' => 1],
                    ['name' => 'Travel Gift Card Voucher', 'value' => 'gift_card', 'badge_color' => 'amber', 'icon' => '💳', 'sort_order' => 2],
                    ['name' => 'Apparel & Clothing', 'value' => 'apparel', 'badge_color' => 'indigo', 'icon' => '🧥', 'sort_order' => 3],
                    ['name' => 'Travel Accessory', 'value' => 'accessory', 'badge_color' => 'emerald', 'icon' => '🎒', 'sort_order' => 4],
                ]
            ],
            'fleet_types' => [
                'title' => 'Aircraft Fleet Models',
                'module' => 'Fleet Management',
                'icon' => '✈️',
                'description' => 'Boeing and regional aircraft models operated in passenger or freighter service.',
                'defaults' => [
                    ['name' => 'Boeing 737-300', 'value' => 'B737-300', 'badge_color' => 'blue', 'icon' => '✈️', 'sort_order' => 1],
                    ['name' => 'Boeing 737-700', 'value' => 'B737-700', 'badge_color' => 'indigo', 'icon' => '✈️', 'sort_order' => 2],
                    ['name' => 'Boeing 737-800', 'value' => 'B737-800', 'badge_color' => 'emerald', 'icon' => '✈️', 'sort_order' => 3],
                    ['name' => 'Boeing 737-300F (Freighter)', 'value' => 'B737-300F', 'badge_color' => 'amber', 'icon' => '📦', 'sort_order' => 4],
                    ['name' => 'Boeing 737-800F (Freighter)', 'value' => 'B737-800F', 'badge_color' => 'rose', 'icon' => '📦', 'sort_order' => 5],
                ]
            ],
            'fleet_categories' => [
                'title' => 'Fleet Operational Categories',
                'module' => 'Fleet Management',
                'icon' => '🛫',
                'description' => 'Aircraft role classification (Passenger Transport vs Dedicated Air Cargo).',
                'defaults' => [
                    ['name' => 'Passenger Aircraft', 'value' => 'passenger', 'badge_color' => 'blue', 'icon' => '👥', 'sort_order' => 1],
                    ['name' => 'Dedicated Cargo Freighter', 'value' => 'freight', 'badge_color' => 'amber', 'icon' => '📦', 'sort_order' => 2],
                ]
            ],
            'blog_categories' => [
                'title' => 'Blog Article Categories',
                'module' => 'Blog Management',
                'icon' => '✍️',
                'description' => 'Categories used to classify passenger stories, travel tips, island culture, and blog articles.',
                'defaults' => [
                    ['name' => 'Pohnpei Blog', 'value' => 'Pohnpei Blog', 'badge_color' => 'blue', 'icon' => '🌴', 'sort_order' => 1],
                    ['name' => 'Temporary Change', 'value' => 'Temporary Change', 'badge_color' => 'amber', 'icon' => '⏱️', 'sort_order' => 2],
                    ['name' => 'Naoero Blog', 'value' => 'Naoero Blog', 'badge_color' => 'emerald', 'icon' => '🏝️', 'sort_order' => 3],
                    ['name' => 'Tarawa Blog', 'value' => 'Tarawa Blog', 'badge_color' => 'indigo', 'icon' => '🌊', 'sort_order' => 4],
                    ['name' => 'Pacific Islands', 'value' => 'Pacific Islands', 'badge_color' => 'purple', 'icon' => '🌺', 'sort_order' => 5],
                    ['name' => 'General', 'value' => 'General', 'badge_color' => 'slate', 'icon' => '📝', 'sort_order' => 6],
                ]
            ],
            'news_categories' => [
                'title' => 'News Hub & Media Categories',
                'module' => 'News Hub',
                'icon' => '📰',
                'description' => 'Categories used to classify press releases, corporate announcements, and event sponsorships.',
                'defaults' => [
                    ['name' => 'Media Release', 'value' => 'Media Release', 'badge_color' => 'blue', 'icon' => '📢', 'sort_order' => 1],
                    ['name' => 'Sponsored Events', 'value' => 'Sponsored Events', 'badge_color' => 'purple', 'icon' => '🎉', 'sort_order' => 2],
                    ['name' => 'Sponsored Sporting Events', 'value' => 'Sponsored Sporting Events', 'badge_color' => 'emerald', 'icon' => '🏆', 'sort_order' => 3],
                    ['name' => 'Company News', 'value' => 'Company News', 'badge_color' => 'amber', 'icon' => '🏢', 'sort_order' => 4],
                    ['name' => 'Announcements', 'value' => 'Announcements', 'badge_color' => 'rose', 'icon' => '🚨', 'sort_order' => 5],
                ]
            ],
            'post_categories' => [
                'title' => 'News & Blog Categories',
                'module' => 'Articles & News',
                'icon' => '📰',
                'description' => 'Topics for airline news, travel guides, corporate updates, and flight advisories.',
                'defaults' => [
                    ['name' => 'General News', 'value' => 'General', 'badge_color' => 'slate', 'icon' => '📢', 'sort_order' => 1],
                    ['name' => 'Aviation Insights', 'value' => 'Aviation', 'badge_color' => 'blue', 'icon' => '✈️', 'sort_order' => 2],
                    ['name' => 'Travel & Island Guides', 'value' => 'Travel', 'badge_color' => 'emerald', 'icon' => '🌴', 'sort_order' => 3],
                    ['name' => 'Corporate & Governance', 'value' => 'Corporate', 'badge_color' => 'purple', 'icon' => '🏢', 'sort_order' => 4],
                    ['name' => 'Destination Spotlights', 'value' => 'Destinations', 'badge_color' => 'amber', 'icon' => '📍', 'sort_order' => 5],
                ]
            ],
            'team_departments' => [
                'title' => 'Team & Leadership Departments',
                'module' => 'Our Team',
                'icon' => '👥',
                'description' => 'Divisions for airline Board of Directors, Executive Management, and Flight Ops.',
                'defaults' => [
                    ['name' => 'Board of Directors', 'value' => 'Board of Directors', 'badge_color' => 'purple', 'icon' => '🏛️', 'sort_order' => 1],
                    ['name' => 'Executive Leadership', 'value' => 'Executive Leadership', 'badge_color' => 'blue', 'icon' => '👔', 'sort_order' => 2],
                    ['name' => 'Flight Operations', 'value' => 'Flight Operations', 'badge_color' => 'emerald', 'icon' => '👨‍✈️', 'sort_order' => 3],
                    ['name' => 'Commercial & Sales', 'value' => 'Commercial', 'badge_color' => 'amber', 'icon' => '📈', 'sort_order' => 4],
                    ['name' => 'Ground & Airport Services', 'value' => 'Ground Services', 'badge_color' => 'slate', 'icon' => '🧳', 'sort_order' => 5],
                ]
            ],
            'career_departments' => [
                'title' => 'Career Job Departments',
                'module' => 'Careers & HR',
                'icon' => '💼',
                'description' => 'Departments posting job openings and accepting resume applications.',
                'defaults' => [
                    ['name' => 'Flight Operations (Pilots)', 'value' => 'Flight Operations', 'badge_color' => 'blue', 'icon' => '👨‍✈️', 'sort_order' => 1],
                    ['name' => 'Cabin Crew & Inflight Services', 'value' => 'Cabin Crew', 'badge_color' => 'rose', 'icon' => '🧑‍💼', 'sort_order' => 2],
                    ['name' => 'Engineering & Maintenance', 'value' => 'Engineering', 'badge_color' => 'amber', 'icon' => '🔧', 'sort_order' => 3],
                    ['name' => 'Ground Operations & Cargo', 'value' => 'Ground Operations', 'badge_color' => 'slate', 'icon' => '📦', 'sort_order' => 4],
                    ['name' => 'Corporate, IT & Administration', 'value' => 'Corporate & Administration', 'badge_color' => 'purple', 'icon' => '💻', 'sort_order' => 5],
                ]
            ],
            'career_types' => [
                'title' => 'Employment Contract Types',
                'module' => 'Careers & HR',
                'icon' => '📝',
                'description' => 'Work engagement classifications (Full-Time, Part-Time, Contract).',
                'defaults' => [
                    ['name' => 'Full-Time Permanent', 'value' => 'Full-Time', 'badge_color' => 'emerald', 'icon' => '🟢', 'sort_order' => 1],
                    ['name' => 'Part-Time', 'value' => 'Part-Time', 'badge_color' => 'blue', 'icon' => '🔵', 'sort_order' => 2],
                    ['name' => 'Fixed-Term Contract', 'value' => 'Contract', 'badge_color' => 'amber', 'icon' => '🟡', 'sort_order' => 3],
                    ['name' => 'Casual / On-Call', 'value' => 'Casual', 'badge_color' => 'purple', 'icon' => '🟣', 'sort_order' => 4],
                ]
            ],
            'career_locations' => [
                'title' => 'Job Duty Locations',
                'module' => 'Careers & HR',
                'icon' => '📍',
                'description' => 'Operating bases and hub stations where vacancies are located.',
                'defaults' => [
                    ['name' => 'Brisbane, Australia (Hub)', 'value' => 'Brisbane, Australia', 'badge_color' => 'blue', 'icon' => '🇦🇺', 'sort_order' => 1],
                    ['name' => 'Nauru International Airport (Headquarters)', 'value' => 'Nauru', 'badge_color' => 'emerald', 'icon' => '🇳🇷', 'sort_order' => 2],
                    ['name' => 'Nadi, Fiji Station', 'value' => 'Nadi, Fiji', 'badge_color' => 'purple', 'icon' => '🇫🇯', 'sort_order' => 3],
                    ['name' => 'Tarawa, Kiribati Station', 'value' => 'Tarawa, Kiribati', 'badge_color' => 'amber', 'icon' => '🇰🇮', 'sort_order' => 4],
                    ['name' => 'Majuro, Marshall Islands Station', 'value' => 'Majuro, Marshall Islands', 'badge_color' => 'rose', 'icon' => '🇲🇭', 'sort_order' => 5],
                ]
            ],
            'faq_categories' => [
                'title' => 'FAQ Knowledgebase Categories',
                'module' => 'Passenger FAQs',
                'icon' => '❓',
                'description' => 'Topic groupings for passenger questions and travel information.',
                'defaults' => [
                    ['name' => 'Baggage Allowance & Fees', 'value' => 'Baggage', 'badge_color' => 'blue', 'icon' => '🧳', 'sort_order' => 1],
                    ['name' => 'Check-in & Boarding Times', 'value' => 'Check-in', 'badge_color' => 'emerald', 'icon' => '⏱️', 'sort_order' => 2],
                    ['name' => 'Dangerous Goods & Restrictions', 'value' => 'Dangerous Goods', 'badge_color' => 'rose', 'icon' => '⚠️', 'sort_order' => 3],
                    ['name' => 'Special Medical & Child Assistance', 'value' => 'Special Assistance', 'badge_color' => 'purple', 'icon' => '♿', 'sort_order' => 4],
                    ['name' => 'Booking, Fares & Changes', 'value' => 'Bookings', 'badge_color' => 'amber', 'icon' => '🎟️', 'sort_order' => 5],
                    ['name' => 'General Travel Inquiries', 'value' => 'General', 'badge_color' => 'slate', 'icon' => 'ℹ️', 'sort_order' => 6],
                ]
            ],
            'gallery_categories' => [
                'title' => 'Media Gallery Categories',
                'module' => 'Media Gallery',
                'icon' => '📸',
                'description' => 'Photo categories (Aircraft, Landscape, Hospitality, Culture).',
                'defaults' => [
                    ['name' => 'Fleet & Aircraft Liveries', 'value' => 'aircraft', 'badge_color' => 'blue', 'icon' => '✈️', 'sort_order' => 1],
                    ['name' => 'Pacific Island Landscapes', 'value' => 'landscape', 'badge_color' => 'emerald', 'icon' => '🌴', 'sort_order' => 2],
                    ['name' => 'Inflight Hospitality & Crew', 'value' => 'hospitality', 'badge_color' => 'rose', 'icon' => '🥂', 'sort_order' => 3],
                    ['name' => 'Destination Culture & Highlights', 'value' => 'destination', 'badge_color' => 'amber', 'icon' => '🗺️', 'sort_order' => 4],
                    ['name' => 'General Gallery Media', 'value' => 'general', 'badge_color' => 'slate', 'icon' => '📷', 'sort_order' => 5],
                ]
            ],
            'agency_countries' => [
                'title' => 'Agency Directory Countries',
                'module' => 'Travel Agencies',
                'icon' => '🌍',
                'description' => 'Countries where authorized ticketing and global sales agencies operate.',
                'defaults' => [
                    ['name' => 'Nauru', 'value' => 'Naoero', 'badge_color' => 'emerald', 'icon' => '🇳🇷', 'sort_order' => 1],
                    ['name' => 'Australia', 'value' => 'Australia', 'badge_color' => 'blue', 'icon' => '🇦🇺', 'sort_order' => 2],
                    ['name' => 'Fiji', 'value' => 'Fiji', 'badge_color' => 'purple', 'icon' => '🇫🇯', 'sort_order' => 3],
                    ['name' => 'Kiribati', 'value' => 'Kiribati', 'badge_color' => 'amber', 'icon' => '🇰🇮', 'sort_order' => 4],
                    ['name' => 'Marshall Islands', 'value' => 'Marshall Islands', 'badge_color' => 'rose', 'icon' => '🇲🇭', 'sort_order' => 5],
                    ['name' => 'Federated States of Micronesia', 'value' => 'Micronesia', 'badge_color' => 'indigo', 'icon' => '🇫🇲', 'sort_order' => 6],
                    ['name' => 'Palau', 'value' => 'Palau', 'badge_color' => 'emerald', 'icon' => '🇵🇼', 'sort_order' => 7],
                ]
            ],
            'notice_types' => [
                'title' => 'Notice Bar Alert Types',
                'module' => 'Notice Bar & Alerts',
                'icon' => '📢',
                'description' => 'Banner styles and urgency levels for public flight notices.',
                'defaults' => [
                    ['name' => 'General Advisory / Info', 'value' => 'info', 'badge_color' => 'blue', 'icon' => 'ℹ️', 'sort_order' => 1],
                    ['name' => 'Weather / Operational Warning', 'value' => 'warning', 'badge_color' => 'amber', 'icon' => '⚠️', 'sort_order' => 2],
                    ['name' => 'Urgent Flight Emergency / Alert', 'value' => 'alert', 'badge_color' => 'rose', 'icon' => '🚨', 'sort_order' => 3],
                    ['name' => 'Service Resumed / Success', 'value' => 'success', 'badge_color' => 'emerald', 'icon' => '✅', 'sort_order' => 4],
                ]
            ],
            'currencies' => [
                'title' => 'System Currencies',
                'module' => 'Global Settings',
                'icon' => '💵',
                'description' => 'Supported transactional currencies for flight specials, shop merchandise, and gift cards.',
                'defaults' => [
                    ['name' => 'Australian Dollar (AUD $)', 'value' => 'AUD', 'badge_color' => 'emerald', 'icon' => '🇦🇺', 'sort_order' => 1],
                    ['name' => 'United States Dollar (USD $)', 'value' => 'USD', 'badge_color' => 'blue', 'icon' => '🇺🇸', 'sort_order' => 2],
                    ['name' => 'Fijian Dollar (FJD $)', 'value' => 'FJD', 'badge_color' => 'purple', 'icon' => '🇫🇯', 'sort_order' => 3],
                    ['name' => 'New Zealand Dollar (NZD $)', 'value' => 'NZD', 'badge_color' => 'indigo', 'icon' => '🇳🇿', 'sort_order' => 4],
                    ['name' => 'Euro (EUR €)', 'value' => 'EUR', 'badge_color' => 'blue', 'icon' => '🇪🇺', 'sort_order' => 5],
                    ['name' => 'British Pound (GBP £)', 'value' => 'GBP', 'badge_color' => 'slate', 'icon' => '🇬🇧', 'sort_order' => 6],
                    ['name' => 'Indian Rupee (INR ₹)', 'value' => 'INR', 'badge_color' => 'amber', 'icon' => '🇮🇳', 'sort_order' => 7],
                ]
            ],
            'travel_classes' => [
                'title' => 'Flight Travel Classes',
                'module' => 'Flight Operations & Inquiries',
                'icon' => '💺',
                'description' => 'Cabin seating classes (Business Class, Economy Class).',
                'defaults' => [
                    ['name' => 'Business Class', 'value' => 'business', 'badge_color' => 'amber', 'icon' => '🥂', 'sort_order' => 1],
                    ['name' => 'Economy Class', 'value' => 'economy', 'badge_color' => 'blue', 'icon' => '💺', 'sort_order' => 2],
                ]
            ],
            'feedback_categories' => [
                'title' => 'Customer Feedback Categories',
                'module' => 'Customer Care & Inquiries',
                'icon' => '💬',
                'description' => 'Passenger feedback types (Compliment, Complaint, Suggestion).',
                'defaults' => [
                    ['name' => 'Compliment / Praise', 'value' => 'compliment', 'badge_color' => 'emerald', 'icon' => '⭐', 'sort_order' => 1],
                    ['name' => 'Complaint / Issue', 'value' => 'complaint', 'badge_color' => 'rose', 'icon' => '⚠️', 'sort_order' => 2],
                    ['name' => 'Suggestion / Improvement', 'value' => 'suggestion', 'badge_color' => 'blue', 'icon' => '💡', 'sort_order' => 3],
                ]
            ],
            'charter_types' => [
                'title' => 'Charter Flight Types',
                'module' => 'Charter Services',
                'icon' => '✈️',
                'description' => 'Types of private aircraft charter bookings.',
                'defaults' => [
                    ['name' => 'Passenger Charter Flight', 'value' => 'Passenger Charter', 'badge_color' => 'blue', 'icon' => '👥', 'sort_order' => 1],
                    ['name' => 'Dedicated Cargo / Freight Charter', 'value' => 'Cargo Charter', 'badge_color' => 'amber', 'icon' => '📦', 'sort_order' => 2],
                    ['name' => 'FIFO Mining & Corporate Charter', 'value' => 'FIFO Corporate', 'badge_color' => 'indigo', 'icon' => '💼', 'sort_order' => 3],
                    ['name' => 'VIP / Government Official Delegation', 'value' => 'VIP Government', 'badge_color' => 'purple', 'icon' => '👑', 'sort_order' => 4],
                    ['name' => 'Medical Evacuation (Medevac)', 'value' => 'Medevac', 'badge_color' => 'rose', 'icon' => '🏥', 'sort_order' => 5],
                ]
            ],
            'cargo_types' => [
                'title' => 'Air Freight & Cargo Types',
                'module' => 'Cargo Operations',
                'icon' => '📦',
                'description' => 'Classifications for cargo and freight shipments.',
                'defaults' => [
                    ['name' => 'General Dry Cargo & Merchandise', 'value' => 'General Cargo', 'badge_color' => 'slate', 'icon' => '📦', 'sort_order' => 1],
                    ['name' => 'Perishable Fresh Food / Cold Chain', 'value' => 'Perishable Food', 'badge_color' => 'emerald', 'icon' => '🥩', 'sort_order' => 2],
                    ['name' => 'Dangerous Goods & Chemical Consignments', 'value' => 'Dangerous Goods', 'badge_color' => 'rose', 'icon' => '⚠️', 'sort_order' => 3],
                    ['name' => 'Live Animals & Pet Transport', 'value' => 'Live Animals', 'badge_color' => 'amber', 'icon' => '🐕', 'sort_order' => 4],
                    ['name' => 'High-Value / Secured Shipments', 'value' => 'Valuable Cargo', 'badge_color' => 'purple', 'icon' => '💎', 'sort_order' => 5],
                    ['name' => 'Oversized & Heavy Industrial Machinery', 'value' => 'Oversized Cargo', 'badge_color' => 'blue', 'icon' => '🚜', 'sort_order' => 6],
                ]
            ],
        ];
    }
}
