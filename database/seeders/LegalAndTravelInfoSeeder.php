<?php

namespace Database\Seeders;

use App\Models\CheckInTime;
use App\Models\DangerousGoodsItem;
use App\Models\LegalDocument;
use App\Models\TravelInfoCard;
use Illuminate\Database\Seeder;

class LegalAndTravelInfoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Agency Resources are managed in admin, each with its PDF (the old
        // sample entries here pointed at files that never existed).

        // =========================================================================
        // 2. CHECK-IN TIMES TIMETABLE (9 Pacific Airport Ports)
        // =========================================================================
        $checkInPorts = [
            [
                'port' => 'Brisbane (BNE)',
                'airport_code' => 'BNE',
                'opens' => '3 hours prior to departure',
                'closes' => '60 minutes prior to departure',
                'is_highlighted' => false,
                'notes' => 'International Terminal Level 4, Check-in Desks 1-8',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'port' => 'Nauru (INU)',
                'airport_code' => 'INU',
                'opens' => '3 hours prior to departure',
                'closes' => '45 minutes prior to departure',
                'is_highlighted' => true,
                'notes' => 'Nauru International Airport Departures Concourse',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'port' => 'Tarawa (TRW)',
                'airport_code' => 'TRW',
                'opens' => '3 hours prior to departure',
                'closes' => '90 minutes prior to departure',
                'is_highlighted' => true,
                'notes' => 'Bonriki International Airport. Special effective timetable from 6 Nov 2025.',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'port' => 'Majuro (MAJ)',
                'airport_code' => 'MAJ',
                'opens' => '3 hours prior to departure',
                'closes' => '90 minutes prior to departure',
                'is_highlighted' => true,
                'notes' => 'Amata Kabua International Airport. Early arrival strongly recommended.',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'port' => 'Pohnpei (PNI)',
                'airport_code' => 'PNI',
                'opens' => '3 hours prior to departure',
                'closes' => '90 minutes prior to departure',
                'is_highlighted' => true,
                'notes' => 'Pohnpei International Airport. Check-in closes strictly 90 mins before departure.',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'port' => 'Honiara (HIR)',
                'airport_code' => 'HIR',
                'opens' => '3 hours prior to departure',
                'closes' => '60 minutes prior to departure',
                'is_highlighted' => false,
                'notes' => 'Henderson International Airport International Terminal',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'port' => 'Nadi (NAN)',
                'airport_code' => 'NAN',
                'opens' => '3 hours prior to departure',
                'closes' => '60 minutes prior to departure',
                'is_highlighted' => false,
                'notes' => 'Nadi International Airport Departures Hall B',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'port' => 'Palau / Koror (ROR)',
                'airport_code' => 'ROR',
                'opens' => '3 hours prior to departure',
                'closes' => '60 minutes prior to departure',
                'is_highlighted' => false,
                'notes' => 'Palau International Airport (Babeldaob)',
                'sort_order' => 8,
                'is_active' => true,
            ],
            [
                'port' => 'Melbourne (MEL)',
                'airport_code' => 'MEL',
                'opens' => '3 hours prior to departure',
                'closes' => '60 minutes prior to departure',
                'is_highlighted' => false,
                'notes' => 'Tullamarine Terminal 2 (International Departures)',
                'sort_order' => 9,
                'is_active' => true,
            ],
        ];

        foreach ($checkInPorts as $port) {
            CheckInTime::updateOrCreate(['airport_code' => $port['airport_code']], $port);
        }

        // =========================================================================
        // 3. DANGEROUS GOODS & PROHIBITED ITEMS
        // =========================================================================
        $dgItems = [
            // Quick reference
            [
                'item_name' => 'Lithium Ion Batteries (under 100Wh)',
                'carry_on' => 'Yes (In equipment & spares)',
                'checked' => 'In equipment only (NO spares)',
                'approval_required' => 'No',
                'category' => 'quick_reference',
                'icon' => 'BatteryCharging',
                'details' => 'Phones, laptops, tablets, cameras. Spare batteries forbidden in checked luggage.',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'item_name' => 'Lithium Ion Batteries (100Wh - 160Wh)',
                'carry_on' => 'Yes (Max 2 spare batteries)',
                'checked' => 'In equipment only',
                'approval_required' => 'Airline Approval Required',
                'category' => 'quick_reference',
                'icon' => 'Battery',
                'details' => 'Large video equipment, drone batteries, power tools. Must declare before check-in.',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'item_name' => 'Power Banks (< 100Wh)',
                'carry_on' => 'Yes (Must stay in cabin)',
                'checked' => 'Forbidden in Checked Bag',
                'approval_required' => 'No',
                'category' => 'quick_reference',
                'icon' => 'Zap',
                'details' => 'Power banks are considered spare batteries and must always travel in cabin baggage.',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'item_name' => 'E-Cigarettes & Vapes',
                'carry_on' => 'Yes (On person or cabin bag)',
                'checked' => 'Forbidden in Checked Bag',
                'approval_required' => 'No',
                'category' => 'quick_reference',
                'icon' => 'Wind',
                'details' => 'Recharging of e-cigarettes on board is strictly prohibited.',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'item_name' => 'Aerosols (Toiletry / Medicinal)',
                'carry_on' => 'Yes (Max 100ml per container)',
                'checked' => 'Yes (Max 500ml / 2L total)',
                'approval_required' => 'No',
                'category' => 'quick_reference',
                'icon' => 'SprayCan',
                'details' => 'Caps must be fitted to prevent accidental release of contents.',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'item_name' => 'Safety Matches / Small Lighter',
                'carry_on' => 'Yes (1 book on person)',
                'checked' => 'Forbidden in Checked Bag',
                'approval_required' => 'No',
                'category' => 'quick_reference',
                'icon' => 'Flame',
                'details' => 'Must be carried on your person, not inside cabin baggage.',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'item_name' => 'Mercury Barometer or Thermometer',
                'carry_on' => 'Yes (Representative only)',
                'checked' => 'Forbidden',
                'approval_required' => 'Airline Approval Required',
                'category' => 'quick_reference',
                'icon' => 'Thermometer',
                'details' => 'Only for government weather bureau representatives in approved leak-proof packaging.',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'item_name' => 'Ammunition (Division 1.4S)',
                'carry_on' => 'Forbidden',
                'checked' => 'Yes (Max 5kg gross weight)',
                'approval_required' => 'Airline Approval Required',
                'category' => 'quick_reference',
                'icon' => 'Shield',
                'details' => 'Securely boxed in quantities not exceeding 5kg for personal sporting use only.',
                'sort_order' => 8,
                'is_active' => true,
            ],

            // Conditional Acceptance Items
            [
                'item_name' => 'Smart Luggage with Lithium Batteries',
                'carry_on' => 'Yes (if within cabin limits)',
                'checked' => 'Only if battery removed',
                'approval_required' => 'No',
                'category' => 'conditional',
                'icon' => 'Luggage',
                'details' => 'If power bank / battery is removable, it must be removed and kept in the cabin. If non-removable, maximum 0.3g lithium metal or 2.7Wh.',
                'sort_order' => 9,
                'is_active' => true,
            ],
            [
                'item_name' => 'Dry Ice (Solid Carbon Dioxide)',
                'carry_on' => 'Yes (Max 2.5kg per person)',
                'checked' => 'Yes (Max 2.5kg per person)',
                'approval_required' => 'Airline Approval Required',
                'category' => 'conditional',
                'icon' => 'Snowflake',
                'details' => 'Up to 2.5 kg per person when used to pack perishables not subject to Dangerous Goods Regulations, package must allow release of carbon dioxide gas.',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'item_name' => 'Electric Mobility Aids & Wheelchairs',
                'carry_on' => 'Forbidden',
                'checked' => 'Yes (With Airline Clearance)',
                'approval_required' => 'Airline Approval Required',
                'category' => 'conditional',
                'icon' => 'Accessibility',
                'details' => 'Non-spillable wet, dry, or lithium batteries. Contact Reservations at least 48 hours prior to departure with manufacturer battery specifications.',
                'sort_order' => 11,
                'is_active' => true,
            ],
            [
                'item_name' => 'Camping Stoves & Fuel Containers',
                'carry_on' => 'Forbidden',
                'checked' => 'Yes (Completely Drained & Cleaned)',
                'approval_required' => 'Airline Approval Required',
                'category' => 'conditional',
                'icon' => 'Compass',
                'details' => 'Liquid fuel camping stoves must have fuel tank thoroughly drained, left open to evaporate for 6 hours, and wrapped in absorbent material.',
                'sort_order' => 12,
                'is_active' => true,
            ],
        ];

        foreach ($dgItems as $item) {
            DangerousGoodsItem::updateOrCreate(['item_name' => $item['item_name']], $item);
        }

        // =========================================================================
        // 4. TRAVEL INFORMATION CARDS (Helpful Information on /travel-info)
        // =========================================================================
        $cards = [
            [
                'title' => 'Before You Fly',
                'slug' => 'before-you-fly',
                'description' => 'Prepare for your journey with essential travel checklists, passport validity rules, visa requirements, and airport procedures.',
                'icon' => 'Send',
                'route_url' => '/travel-info/before-you-fly',
                'has_link' => true,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Baggage Allowances',
                'slug' => 'baggage-allowances',
                'description' => 'Cabin baggage allowances, checked luggage weight limits, sporting goods, oversized items, and excess baggage fee schedules.',
                'icon' => 'Briefcase',
                'route_url' => '/travel-info/baggage',
                'has_link' => true,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Airport Check-In Times',
                'slug' => 'check-in-times',
                'description' => 'Desk opening and closing times across Brisbane, Nauru, Kiribati, Marshall Islands, Micronesia, Solomon Islands, Fiji, and Palau.',
                'icon' => 'Clock',
                'route_url' => '/travel-info/before-you-fly/check-in-times',
                'has_link' => true,
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'title' => 'Dangerous Goods Guide',
                'slug' => 'dangerous-goods',
                'description' => 'Important passenger safety regulations for spare lithium batteries, power banks, liquids, vapes, and restricted hazardous items.',
                'icon' => 'AlertTriangle',
                'route_url' => '/travel-info/dangerous-goods',
                'has_link' => true,
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'title' => 'Conditions of Carriage',
                'slug' => 'conditions-of-carriage',
                'description' => 'Official contractual terms, passenger travel rights, baggage liability limits, flight retiming policies, and carriage contract clauses.',
                'icon' => 'FileText',
                'route_url' => '/travel-info/conditions-of-carriage',
                'has_link' => true,
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'title' => 'Special Assistance & Medical',
                'slug' => 'mobility-assistance',
                'description' => 'Wheelchair assistance, medical oxygen clearance, pregnancy travel guidelines, and support for travelers with special needs.',
                'icon' => 'Heart',
                'route_url' => '/travel-info/before-you-fly/mobility-assistance',
                'has_link' => true,
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'title' => 'Travelling with Children & Infants',
                'slug' => 'travelling-with-children',
                'description' => 'Guidelines for strollers, car seats, infant baggage, bassinet availability, and family seating options on Pacific routes.',
                'icon' => 'Users',
                'route_url' => '/travel-info/before-you-fly/travelling-with-children',
                'has_link' => true,
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'title' => 'Unaccompanied Minors',
                'slug' => 'unaccompanied-minors',
                'description' => 'Dedicated staff supervision, check-in formalities, guardian drop-off, and travel guidelines for young solo flyers.',
                'icon' => 'UserCheck',
                'route_url' => '/travel-info/before-you-fly/unaccompanied-minors',
                'has_link' => true,
                'sort_order' => 8,
                'is_active' => true,
            ],
            [
                'title' => 'Flying While Pregnant',
                'slug' => 'flying-while-pregnant',
                'description' => 'Medical clearances, travel gestation limits, and health guidelines for expectant mothers flying with Naoero Airlines.',
                'icon' => 'HeartHandshake',
                'route_url' => '/travel-info/before-you-fly/flying-while-pregnant',
                'has_link' => true,
                'sort_order' => 9,
                'is_active' => true,
            ],
            [
                'title' => 'Flight Timetables & PDF Schedules',
                'slug' => 'flight-schedule',
                'description' => 'Download current seasonal timetables, flight numbers, aircraft equipment, departure days, and route maps.',
                'icon' => 'Calendar',
                'route_url' => '/travel-info/flight-schedule',
                'has_link' => true,
                'sort_order' => 10,
                'is_active' => true,
            ],
        ];

        foreach ($cards as $card) {
            TravelInfoCard::updateOrCreate(['slug' => $card['slug']], $card);
        }

        // =========================================================================
        // 5. LEGAL DOCUMENTS (Privacy Policy, Terms of Use, Conditions of Carriage)
        // FULL EXTENSIVE TEXT FOR BOTH SECTIONS ARRAY AND WYSIWYG CONTENT
        // =========================================================================

        // 5.1 PRIVACY POLICY (Full 18 Sections)
        $privacySections = [
            [
                'heading' => '1. Introduction',
                'content' => 'Who we are Naoero Air Corporation trading as Naoero Airlines (" Naoero Airlines ", "we", "us" or "our"). The importance of privacy Naoero Airlines understands the importance of privacy to all individuals. We are committed to protecting your personal information in accordance with applicable privacy legislation. We limit our collection and use of your personal data to the information that is reasonably necessary to conduct our business and provide you with the highest quality service. This Privacy Policy This Privacy Policy is designed to inform you about the type of information we collect from you, how we will use it and the rights you have in relation to it. This Privacy Policy applies to personal information about you that is reasonably necessary for us to collect, use and otherwise process, including when you travel with us, use our other services and websites, make enquiries, enter competitions or promotions, contact our service agents or Naoero Airlines Reservations, and book to use our services through third parties (such as travel agents and other airlines). Please ensure that you read this Privacy Policy in full before submitting your personal information to us. Additional Terms and Conditions or policies may apply if you elect to take additional services from us, such as entering a competition or promotion.',
            ],
            [
                'heading' => '2. What is \'Personal Information\'?',
                'content' => 'Personal information means data relating to you from which you can be reasonably identified, such as your name and contact details, your travel arrangements and purchase history. It may also include information about how you use our websites and mobile applications. We recognise that your personal information is valuable, and we will process it in accordance with applicable laws.',
            ],
            [
                'heading' => '3. Information you provide to us and how we use it?',
                'content' => '3.1. What personal information you provide to us When you use our services, you will need to provide us with your personal details and/or the details of the individual(s) who will be travelling with you. Some examples of the type of personal information we may collect from you in the process of conducting our business and providing services include: a) Contact information – your name, address, phone number, email address and social media handle; b) Identity information – your government identification number such as your passport number, date of birth or image; c) Financial information – your credit or debit card details and expiry date; d) Our website and mobile apps – your browser type, web pages visited, your geo-location, your mobile telephone number or your IP address and details of how you use the website or app and any third party sites you access from them; e) CCTV Images – images captured in our airport lounges, airport offices and premises; and/or f) Health and dietary information - dietary requirements and health information, including request for specific assistance, in connection with the use of our products and services. 3.2. How we collect personal information We may collect personal information from you when you interact with us directly, for example when you: a) complete and manage a booking you have made with us or a service you have requested from us; b) travel with us and update your travel arrangements; and/or c) use our website and apps, complete online registrations, enter promotions and engage in other online interactions. You can choose not to provide us with any of your personal information however we may then be unable to respond to your enquiry, engage in further communications with you and/or provide you with the requested services. When we collect personal information, we do so because it is reasonably necessary to: (a) use and disclose your personal information to communicate with you and/or on your behalf; (b) facilitate and/or perform a contract with you; (c) protect the interests of you and other persons; and/or (d) comply with applicable laws, regulations and the requirements of Government authorities. We may also collect personal information from you indirectly, such as from Government agencies like customs and immigration, border security, partner airlines, others who interact with us on your behalf or from third party service providers such as websites, apps or social media platforms. 3.3. How we use your personal information a) If you are travelling with us - we may use your personal information to fulfil your travel arrangements and deliver services you have requested, manage the travelling process, facilitate flight connections, send status updates and other communications to you, keep track of you at the airport and keep you safe when you fly with us as well as personalise the way we provide and market benefits, products and services to you. b) If you make an enquiry about our company, services or products - we may use your personal information to respond to your query or comply with a legal obligation. c) If you are an existing customer, supplier, or partner of ours – we may use your personal information to fulfil an obligation or perform an agreement with you, respond to any query or comply with a legal obligation. d) If you make an enquiry about working for us – we may use your personal information to consider and respond to your enquiry, comply with a legal obligation or retain your personal information in relation to future work opportunities. e) If we do not know you and/or your role at your business – we may use your personal information to confirm your identity and the business that you work for, as well as your role at that business. We do this because it is reasonably necessary to make sure that your enquiry is genuine and is not spam or being made for fraudulent reasons. We may also do this because we may be subject to legal obligations which require us to confirm the details that you provide to us before entering a relationship with you. f) If you opt-in or otherwise consent to receive marketing communications – we may use your personal information (other than sensitive information) to contact you to share information about relevant products, services, news, promotion and events in accordance with paragraph 4. When you provide your contact details for this reason, you will be consenting to us contacting you for these purposes, including for future promotions we may offer.',
            ],
            [
                'heading' => '4. Marketing Communications',
                'content' => '4.1. When you opt-in to receive marketing communications from us, you are giving your consent for your personal information to be used by us to contact you by email, direct mail, telephone, SMS, MMS, social media, apps and via other forms of communications for the purpose of direct marketing, which includes: a) delivering personalised website, app or social media experiences such as sharing information with you about products and services provided by us and our industry partners (such as third party hire car companies, travel insurance providers or hotels); b) sending you press releases, newsletters and information about industry news and events; and/or c) notifying you about promotions and competitions held by us and our industry partners (Marketing Communications). 4.2. If you decide that you do not want to receive Marketing Communications from us, you may withdraw your consent at any time to receive Marketing Communications, or you can unsubscribe by selecting the opt-out or unsubscribe link contained in the relevant Marketing Communication (where applicable) or amend your communication preferences by contacting us at privacy@naoeroairlines.com.au or using the details in paragraph 18. We aim to action requests to stop marketing communications within 10 working days of receiving such requests. 4.3. Please note that if you opt-out of Marketing Communications, you will still receive service communications (as described in paragraph 3.3 above) which are necessary, for example, to confirm your booking or to provide you with an update on its status.',
            ],
            [
                'heading' => '5. Special categories of personal information',
                'content' => '(sensitive information) 5.1. Under privacy laws, certain categories of personal information, such as information about race, ethnicity, religion, health, sexuality or biometric information are special categories of data requiring additional protection (sensitive information). For example, you may have requested services (such as a special meal) which is not \'sensitive data\' or \'sensitive information\' itself but may imply or suggest your religion, health or other information. Other examples would be information required by government authorities to demonstrate fitness to fly (eg: COVID-19 testing requirements) or for public health reasons. Generally, we try to limit the circumstances in which we collect and process sensitive information. 5.2. We may collect and use sensitive information where the collection or use of such information is required by law or you have: a) requested specific medical assistance, such as the provision of wheelchair assistance or oxygen; b) sought clearance from us to fly with a medical condition or because you are more than 28 weeks pregnant; and/or c) chosen to provide such information to us or it has been passed onto us by a third party such as the travel agent through whom you made your booking. 5.3. Sensitive information will only be collected by us if you have explicitly consented, unless otherwise stated above, and in accordance with applicable laws, including the Australian Privacy Principles.',
            ],
            [
                'heading' => '6. Information received from third parties',
                'content' => '6.1. We may receive personal information about you from third parties, such as companies contracted by us to provide services to you, companies involved in your travel plans, airlines involved in your prior or onward journey, airport operators, customs and immigration authorities and companies that participate in our partner loyalty schemes and other customer programmes. 6.2. These third parties receive your personal information in accordance with their own privacy policies and procedures and, where applicable, may function as separate controllers under the European Data Protection Legislation (refer to section 15 of this Privacy Policy for more information). 6.3. We recommend you consult their individual privacy policies for further information. 6.4. We are not responsible for the privacy practices of third parties or for the content, products, or services provided by them.',
            ],
            [
                'heading' => '7. Information we automatically collect about you',
                'content' => '7.1. When you visit our website or mobile apps, our browser or other applications automatically collect information about your interaction with us such as your IP address, traffic data, clickstream information, time stamp, location data, web logs, other communication data and the resources that you access. 7.2. We automatically collect data about your use of our website or apps using various technologies such as cookies as explained below. This information allows us to: a) ensure content from our website and apps is presented in the most effective manner for you and your device and to make improvements to our website; b) recognise you when you return to our website or app and to record your use; c) keep our website and apps secure; d) administer and maintain our website and apps by conducting testing and solving errors; and/or e) analyse your and other users\' use of the website and apps to create anonymised and aggregated research or statistics from time to time. Once your personal information has been anonymised and aggregated with that of other users, we will no longer be able to identify you or anyone else from the combined information. 7.3. We use your personal information in this way because it is reasonably necessary for us to determine how you may be viewing and using our website and any apps so that we can operate them effectively and improve them.',
            ],
            [
                'heading' => '8. Cookies',
                'content' => '8.1. Our websites and apps may use cookies, which are small data files containing an identifier (a string of letters and numbers) that is sent by a server to a browser and is stored by the browser. The identifier is then sent back to the server each time the browser requests a page from the server. 8.2. We use cookies to enhance your use of our websites, apps and/or services and to monitor your activity during your visit(s) to our websites and apps and to help us make our website more useful for you. By continuing to use our websites and apps you consent to our use of cookies as explained in this Privacy Policy. 8.3. Cookies do not typically contain any information that personally identifies a user, but personal information that we store about you may be linked to the information stored in and obtained from cookies. 8.4. Some cookies are deleted when you close your browser. These are known as session cookies. Others remain on your device until they expire, or you delete them. These are known as persistent cookies and enable us to remember things about you as a returning visitor. If you do not want cookies to be stored, then you may turn off cookies individually or select the appropriate options on your browser or device to delete some or all cookies. Please note that if you block some or all cookies (including essential cookies) you may not be able to use or access all or parts of our websites. 8.5. If you would like to disable cookies, you can set your web browser or device to reject cookies. However, if you disable the cookie function, you may not be able to access or receive all the information contained on our websites or apps. The method of altering your cookie settings will depend on the type of browser you use.',
            ],
            [
                'heading' => '9. How long do we keep personal information?',
                'content' => '9.1. We will keep your information for as long as we need it for the purpose(s) for which it was collected. For example, where you book a flight with us, we will keep information related to your booking for a period that allows us to fulfil the travel arrangements, handle or respond to any complaints or queries, continue to improve your experience with us, ensure that you receive any loyalty rewards which are due to you and to allow us to comply with any legal obligations. 9.2. We regularly review the information we hold and delete it securely, or in some cases anonymise it, when there is no longer a legal, business or customer need for it to be retained. We may retain a record of the existence of our relationship with you, to the extent that and for so long as we are required to do so by law.',
            ],
            [
                'heading' => '10. Who do we share your personal information with?',
                'content' => '10.1. We do not sell personal information to any third parties. In certain circumstances, we may be required to share your personal information with third parties, including: a) other airlines and service providers where, for instance, part of your travel itinerary involves a flight operated by a different airline or includes car hire or a hotel booking; b) credit and charge card companies, credit reference agencies and anti-fraud screening service providers to process payments and (where necessary) to carry out fraud-screening; c) to confirm your identity or check your personal information (including by searching for your name and that of your business in publicly available and private databases); d) service providers we are using to provide services that involve data processing, for example, to carry out marketing initiatives or run customer surveys on our behalf; e) our business partners if you have expressed an interest in a product or service of such business partner. For example, if you have joined an affiliated loyalty program so that we can administer the benefits of the loyalty program to you; f) government and law enforcement agencies such as customs and immigration authorities, in response to a valid, legal requirement or request; g) any member of our group of companies (this means our subsidiaries, our ultimate holding company and all its subsidiaries) in so far as reasonably necessary for the purposes set out in this policy; h) analytics and search engine providers that assist us on the improvement and optimisation of our websites; i) any of our employees, officers, insurers, professional advisers, lawyers, tax or business advisors, agents, suppliers or subcontractors insofar as it is reasonably necessary for the purposes set out in this policy; j) a purchaser or successor entity in the event of a sale or other corporate transaction involving some or all of our business or as needed to effect the sale or transfer of business assets, or as needed for external audit, compliance or corporate governance related matters; and/or k) a third party to whom we have a duty to disclose or share your information: i. to enforce or apply our conditions of carriage and other agreements with you; ii. as required by applicable law, court orders, government entities or legal process (including subpoenas); iii. as necessary to protect the property, safety and rights of others. By providing us with your personal information, you consent to the disclosure of your personal information to third parties as set out in paragraph 10.1. In the event you do not wish for us to disclose your personal information to third parties pursuant to paragraph 10.1, please contact us using the details in paragraph 18.',
            ],
            [
                'heading' => '11. International Data Transfers',
                'content' => '11.1. We may transfer, store and process your personal information in servers located in countries worldwide, including in offshore cloud storage provided by third party suppliers. The nature of our business means it is often necessary for us to send your personal information outside the country in which you are located to fulfil your travel arrangements. This occurs because our business and the third parties we share your personal information with as described in this Privacy Policy have operations in countries across the world. By providing us with your Personal Information, you consent to this storage and transfer, as is reasonably necessary in conducting our business and the supply of our services to you. 11.2. By providing us with your personal information, you consent to the disclosure of your personal information offshore as set out in paragraph 11.1. 11.3. We are located in Australia and Naoero, which are not countries that have been deemed to offer adequate data protection by the European Commission. If you do provide your personal information to us, please note that you are doing so on the basis that you consent to the transfer of your personal information outside the European Economic Area (EEA). The potential consequence of this is that there is a risk that your personal information will not be protected in a manner that complies with European Data Protection Legislation. You can withdraw your consent to such international transfers by contacting us in accordance with paragraph 18 of this Privacy Policy, but it will mean that we will not be able to contact you about the services we can or have agreed to provide to you going forward. Please see how we handle your personal information under European Data Protection Legislation in paragraph 15.',
            ],
            [
                'heading' => '12. Your Rights',
                'content' => '12.1. With respect to the personal information that we collect about you, you have the right to: a) request access to that personal information; b) receive a copy of the personal information that you have provided to us in a commonly used format so that you can share it with others; c) where personal information is inaccurate or incomplete, ask for personal information to be rectified or completed; d) request the transfer of your personal information to another party; e) ask that your personal information be erased; f) object to us processing your personal information by asking for the processing of that personal information to be restricted or stopped. For example, by requesting that we do not use your personal information for marketing purposes; and/or g) make a complaint about the way we process your personal information under any applicable laws. 12.2. Please contact us using the details listed in paragraph 18 to exercise these rights or to ask us for additional information. When you contact us to exercise the above rights, we may ask for further information from you, to confirm your identity, as we take the protection of your personal information seriously. In the event you email us, we will endeavour to respond to requests, complaints or queries as soon as reasonably practicable. 12.3. Please note that despite paragraph 12.1, we are not required to give you access to your Personal Information to the extent that: a) we reasonably believe that giving access would pose a serious threat to the life, health or safety of any individual, or to public health or public safety; or b) giving access would have an unreasonable impact on the privacy of other individuals; or c) the request for access is frivolous or vexatious; or d) the information relates to existing or anticipated legal proceedings between us and you, and would not be accessible by the process of discovery in those proceedings; or e) giving access would reveal our intentions in relation to negotiations with you in such a way as to prejudice those negotiations; or f) giving access would be unlawful; or g) denying access is required or authorised by or under applicable laws or a court/tribunal order; or h) both of the following apply: i. we have reason to suspect that unlawful activity, or misconduct of a serious nature, which relates to our functions or activities has been, is being or may be engaged in; ii. giving access would be likely to prejudice the taking of appropriate action in relation to the matter; or i) giving access would be likely to prejudice one or more enforcement related activities conducted by, or on behalf of, an enforcement body; or j) giving access would reveal evaluative information generated by us in connection with a commercially sensitive decision-making process.',
            ],
            [
                'heading' => '13. Security of your personal information',
                'content' => '13.1. We will take reasonable technical and organisational precautions to prevent the loss, misuse, disclosure or alteration of your personal information. 13.2. We will use a range of physical, electronic and network security controls to take reasonable steps to protect the information we hold about you. 13.3. All electronic financial transactions entered into through our website will be protected by encryption technology. 13.4. You acknowledge that the transmission of information over the internet is inherently unsecure, and we cannot guarantee the security of data sent in this manner. 13.5. You are responsible for keeping your booking information secure. To help you keep your information secure, we recommend that you: a) keep your booking reference confidential - when you make a booking, you will be given a booking reference on the email confirmation or ticket. You should always keep your booking reference confidential. Giving your booking reference to others may allow them to access your booking details through our system. If you are travelling with others and would not like your individual booking details to be accessible by them, you may prefer each person to make separate bookings. b) be aware of and protect yourself against Internet scams, fraud and \'phishing\' - there is an Internet fraud practice known as \'phishing\' (the illegal gathering of personal information by deception). 13.6. If we become aware of a breach of security of your Personal Information, we will provide written notice to your postal address, contact you by telephone or contact you electronically within the period required by applicable laws.',
            ],
            [
                'heading' => '14. USA TSA Secure Flight Program - Privacy Notice',
                'content' => '14.1. This paragraph is relevant for flights to and from the United States. 14.2. The United States Transportation Security Administration (TSA) may require you to provide your full name, date of birth, and gender for the purpose of watch list screening, under the authority of Title 49 U.S.C. section 114, the Intelligence Reform and Terrorism Prevention Act of 2004 and Title 49 C.F.R parts 1540 and 1560. You may also provide your Redress Number, if available. 14.3. Failure to provide your full name, date of birth, and gender may result in denial of transport or denial of authority to enter the boarding area. TSA may share the information you provide with law enforcement or intelligence agencies or others under its published system of records notice. For more information on TSA privacy policies, or to review the system of records notice and the privacy impact assessment, please see the TSA Web site at www.tsa.gov .',
            ],
            [
                'heading' => '15. European Data Protection Legislation',
                'content' => '15.1. For the purposes of this Privacy Policy, "European Data Protection Legislation" means all applicable legislation relating to data protection in the European Economic Area, including the EU General Data Protection Regulation 2016/679 (GDPR) and all legislation implementing, made under, pursuant to, replacing or superseding the GDPR. Where this Privacy Policy uses terms defined in the GDPR, the definitions set out in the GDPR will apply. 15.2. Where the European Data Protection Legislation applies to personal information collected about you, Naoero Airlines is the "controller" of your personal information. If you have made a flight booking with us but one or more flights are to be provided by another airline or airlines, then that other airline will also be considered a "controller". Any provider of services such as a hotel or car rental company will also separately be a "controller". Please be careful to review the privacy policies of each individual service provider, which you can access from them directly. 15.3. This paragraph 15 applies if you are based in the European Economic Area (EEA), either as a citizen or resident of the EEA, during your communications with us (outside of when you are in the EEA for travel purposes) and sets out the additional information Naoero Airlines is required to provide to you under European law. 15.4. Personal Data Whilst we have used the term "personal information" throughout our Privacy Policy in line with Australian terminology, EU legislation uses the term "personal data" and therefore we have used that term in this paragraph 15 only. 15.5. How we use personal data We collect and use personal data in the ways we have set out in this Privacy Policy. We recommend you review this section as well as the remainder of our Privacy Policy to understand the nature of the personal data we may collect and the circumstances in which it is collected and used. "Controllers" can only process personal data in one of the following circumstances: a) with consent; b) where there is a contractual obligation; c) to meet a legal obligation; d) where processing is necessary for the performance of a task carried out in the public interest; e) to protect the vital interests of an individual; f) for an organisation\'s legitimate interests (except if these are overridden by the interests or fundamental rights of individuals). In addition, the GDPR establishes additional conditions for the processing of sensitive data. We rely on the following GDPR lawful bases to process your personal data: Purposes of the data processing Basis of Use Providing our travel services Performance of a contract, such as providing you with a flight to complete your travel bookings. Legitimate interests, to allow us to perform our obligations and provide services. Consent, where you have provided us with sensitive personal data. Marketing and conducting consumer research Legitimate interests, in marketing our services to you. Consent, which you may withdraw at any time by contacting us and telling us so. Providing customer support Performance of a contract Meeting our legal obligations Legitimate interests, such as enabling us to communicate with you. Meeting our legal and regulatory obligations and for health, security and safety purposes Meeting our legal obligations Legitimate interests, such as complying with the requirements of law enforcement agencies and regulatory authorities or ensuring the health and safety of our passengers and staff. Sensitive Personal Data collection: meeting our legal obligations, vital interests in the case of an emergency, and public interest regarding public health and safety. To prevent fraudulent activity Legitimate interests, such as identifying and responding to possible fraudulent transactions. To ensure our website functions as well as possible Legitimate interests, such as identifying for you the appropriate content and services on our website. 15.6. Protecting your personal data We use several controls to protect your personal data such as encryption and multi factor authentication. We may at times have to transfer your personal data to a third party in another country who does not meet the requirements of the GDPR or other EU legislation relating to data protection. If that occurs, we use appropriate safeguards to protect your personal data such as including standard contractual clauses in our agreements with third parties, using procedures or other controls. 15.7. How long we keep your personal data How long we keep your personal data depends on our business needs and legal requirements. We keep the data for as long as is necessary to meet our regulatory obligations, even after you have flown with us or otherwise ceased using our services. We may also retain your personal data for other permitted purposes, such as contacting you. 15.8. Marketing We may use your personal data for marketing purposes however you have the right to ask us not to. We will tell you if we share your personal data with third parties for marketing purposes. You can contact us at privacy@naoeroairlines.com.au at any time to withdraw your consent if you have previously opted in to sharing your personal data. 15.9. Other rights for EU citizens and residents You have rights to access, correct and withdraw consent in relation to your personal data. If you are an EU resident or citizen you may also have additional rights under EU law such as asking us to: a) provide details as to how we process your data; b) restrict our use of your personal data; c) delete your personal data; d) stop using your personal data for marketing purposes, legitimate or public interests; and/or e) share your personal data to a third party. We may be able to refuse your request on legal grounds however, if that occurs, we must explain in writing to you why that is the case. 15.10. Complaints If you wish to make a complaint about how we handle your personal data, please contact us at privacy@naoeroairlines.com.au . We take all complaints seriously and will do our best to resolve any issues within 30 days of your complaint being lodged with us. If you are still not satisfied with our actions, you may contact your EU member state data protection authority at edpb.europa.eu .',
            ],
            [
                'heading' => '16. CCTV Images',
                'content' => '16.1. We, or our third party providers, may operate CCTV cameras at airport terminals, buildings and facilities. 16.2. Your personal information may be collected through our CCTV cameras if you are reasonably identifiable from the images captured by these cameras. 16.3. Where we use CCTV, we do so because it is reasonably necessary to: a) monitor access to our terminals, buildings, and workplaces; b) identify and respond to security incidents or threats; c) investigate incidents or accidents occurring on our premises; d) assist airport authorities, security and management personnel, regulatory bodies and law enforcement agencies; and/or e) meet our legal and regulatory requirements. 16.4. We may disclose personal information captured by our CCTV cameras to our related companies, airport authorities, security, management, law enforcement agencies, our insurers, and those third parties who provide us with our CCTV equipment.',
            ],
            [
                'heading' => '17. Amendments to this Privacy Policy',
                'content' => '17.1. We may update this policy from time to time to reflect any changes in our practices, services and legal obligations by publishing a new version on our website. 17.2. Your continued use of our services, websites and apps following the effective date of a change to this Privacy Policy signifies your acceptance and agreement to the terms of the revised Privacy Policy. We encourage you to check this page periodically to ensure you have the most up to date privacy statement and terms.',
            ],
            [
                'heading' => '18. Ownership and Contact details',
                'content' => '18.1. This website is owned and operated by Naoero Air Corporation trading as Naoero Airlines. 18.2. If you have any questions on our Privacy Policy or any concerns about the ways in which we protect your personal information, please contact us by email at privacy@naoeroairlines.com.au or by telephone on 1300 369 044 (#2) inside Australia or +61 7 3229 6455 outside Australia.',
            ],
        ];

        $privacyHtml = '<h2>
              1. Introduction
            </h2>

            <h3>Who we are</h3>
            <p>
              Naoero Air Corporation trading as Naoero Airlines ("
              <strong>
                Naoero Airlines
              </strong>
              ", "we", "us" or "our").
            </p>

            <h3>
              The importance of privacy
            </h3>
            <p>
              Naoero Airlines understands the importance of privacy to all
              individuals. We are committed to protecting your personal
              information in accordance with applicable privacy
              legislation. We limit our collection and use of your personal
              data to the information that is reasonably necessary to
              conduct our business and provide you with the highest quality
              service.
            </p>

            <h3>
              This Privacy Policy
            </h3>
            <p>
              This Privacy Policy is designed to inform you about the type
              of information we collect from you, how we will use it and
              the rights you have in relation to it. This Privacy Policy
              applies to personal information about you that is reasonably
              necessary for us to collect, use and otherwise process,
              including when you travel with us, use our other services and
              websites, make enquiries, enter competitions or promotions,
              contact our service agents or Naoero Airlines Reservations,
              and book to use our services through third parties (such as
              travel agents and other airlines).
            </p>
            <p>
              Please ensure that you read this Privacy Policy in full before
              submitting your personal information to us.
            </p>
            <p>
              Additional Terms and Conditions or policies may apply if you
              elect to take additional services from us, such as entering a
              competition or promotion.
            </p>

<h2>
              2. What is \'Personal Information\'?
            </h2>
            <p>
              Personal information means data relating to you from which
              you can be reasonably identified, such as your name and
              contact details, your travel arrangements and purchase
              history. It may also include information about how you use
              our websites and mobile applications. We recognise that your
              personal information is valuable, and we will process it in
              accordance with applicable laws.
            </p>

<h2>
              3. Information you provide to us and how we use it?
            </h2>

            <h3>
              3.1. What personal information you provide to us
            </h3>
            <p>
              When you use our services, you will need to provide us with
              your personal details and/or the details of the
              individual(s) who will be travelling with you. Some examples
              of the type of personal information we may collect from you
              in the process of conducting our business and providing
              services include:
            </p>
            <ul>
              <li>
                a) Contact information – your name, address, phone number,
                email address and social media handle;
              </li>
              <li>
                b) Identity information – your government identification
                number such as your passport number, date of birth or
                image;
              </li>
              <li>
                c) Financial information – your credit or debit card
                details and expiry date;
              </li>
              <li>
                d) Our website and mobile apps – your browser type, web
                pages visited, your geo-location, your mobile telephone
                number or your IP address and details of how you use the
                website or app and any third party sites you access from
                them;
              </li>
              <li>
                e) CCTV Images – images captured in our airport lounges,
                airport offices and premises; and/or
              </li>
              <li>
                f) Health and dietary information - dietary requirements
                and health information, including request for specific
                assistance, in connection with the use of our products and
                services.
              </li>
            </ul>

            <h3>
              3.2. How we collect personal information
            </h3>
            <p>
              We may collect personal information from you when you
              interact with us directly, for example when you:
            </p>
            <ul>
              <li>
                a) complete and manage a booking you have made with us or a
                service you have requested from us;
              </li>
              <li>b) travel with us and update your travel arrangements; and/or</li>
              <li>
                c) use our website and apps, complete online registrations,
                enter promotions and engage in other online interactions.
              </li>
            </ul>
            <p>
              You can choose not to provide us with any of your personal
              information however we may then be unable to respond to your
              enquiry, engage in further communications with you and/or
              provide you with the requested services.
            </p>
            <p>
              When we collect personal information, we do so because it is
              reasonably necessary to:
            </p>
            <ul>
              <li>
                (a) use and disclose your personal information to
                communicate with you and/or on your behalf;
              </li>
              <li>(b) facilitate and/or perform a contract with you;</li>
              <li>(c) protect the interests of you and other persons; and/or</li>
              <li>
                (d) comply with applicable laws, regulations and the
                requirements of Government authorities.
              </li>
            </ul>
            <p>
              We may also collect personal information from you
              indirectly, such as from Government agencies like customs
              and immigration, border security, partner airlines, others
              who interact with us on your behalf or from third party
              service providers such as websites, apps or social media
              platforms.
            </p>

            <h3>
              3.3. How we use your personal information
            </h3>
            <ul>
              <li>
                a) If you are travelling with us - we may use your personal
                information to fulfil your travel arrangements and deliver
                services you have requested, manage the travelling
                process, facilitate flight connections, send status
                updates and other communications to you, keep track of you
                at the airport and keep you safe when you fly with us as
                well as personalise the way we provide and market
                benefits, products and services to you.
              </li>
              <li>
                b) If you make an enquiry about our company, services or
                products - we may use your personal information to respond
                to your query or comply with a legal obligation.
              </li>
              <li>
                c) If you are an existing customer, supplier, or partner of
                ours – we may use your personal information to fulfil an
                obligation or perform an agreement with you, respond to
                any query or comply with a legal obligation.
              </li>
              <li>
                d) If you make an enquiry about working for us – we may
                use your personal information to consider and respond to
                your enquiry, comply with a legal obligation or retain
                your personal information in relation to future work
                opportunities.
              </li>
              <li>
                e) If we do not know you and/or your role at your business
                – we may use your personal information to confirm your
                identity and the business that you work for, as well as
                your role at that business. We do this because it is
                reasonably necessary to make sure that your enquiry is
                genuine and is not spam or being made for fraudulent
                reasons. We may also do this because we may be subject to
                legal obligations which require us to confirm the details
                that you provide to us before entering a relationship with
                you.
              </li>
              <li>
                f) If you opt-in or otherwise consent to receive marketing
                communications – we may use your personal information
                (other than sensitive information) to contact you to share
                information about relevant products, services, news,
                promotion and events in accordance with paragraph 4. When
                you provide your contact details for this reason, you will
                be consenting to us contacting you for these purposes,
                including for future promotions we may offer.
              </li>
            </ul>

<h2>
              4. Marketing Communications
            </h2>
            <p>
              4.1. When you opt-in to receive marketing communications from
              us, you are giving your consent for your personal
              information to be used by us to contact you by email, direct
              mail, telephone, SMS, MMS, social media, apps and via other
              forms of communications for the purpose of direct marketing,
              which includes:
            </p>
            <ul>
              <li>
                a) delivering personalised website, app or social media
                experiences such as sharing information with you about
                products and services provided by us and our industry
                partners (such as third party hire car companies, travel
                insurance providers or hotels);
              </li>
              <li>
                b) sending you press releases, newsletters and information
                about industry news and events; and/or
              </li>
              <li>
                c) notifying you about promotions and competitions held by
                us and our industry partners (Marketing Communications).
              </li>
            </ul>
            <p>
              4.2. If you decide that you do not want to receive Marketing
              Communications from us, you may withdraw your consent at any
              time to receive Marketing Communications, or you can
              unsubscribe by selecting the opt-out or unsubscribe link
              contained in the relevant Marketing Communication (where
              applicable) or amend your communication preferences by
              contacting us at 
              <a href="mailto:privacy@naoeroairlines.com.au" target="_blank">
                privacy@naoeroairlines.com.au
              </a> 
              or using the details in paragraph 18. We aim to action
              requests to stop marketing communications within 10 working
              days of receiving such requests.
            </p>
            <p>
              4.3. Please note that if you opt-out of Marketing
              Communications, you will still receive service communications
              (as described in paragraph 3.3 above) which are necessary,
              for example, to confirm your booking or to provide you with
              an update on its status.
            </p>

<h2>
              5. Special categories of personal information (sensitive
              information)
            </h2>
            <p>
              5.1. Under privacy laws, certain categories of personal
              information, such as information about race, ethnicity,
              religion, health, sexuality or biometric information are
              special categories of data requiring additional protection
              (sensitive information). For example, you may have requested
              services (such as a special meal) which is not \'sensitive
              data\' or \'sensitive information\' itself but may imply or
              suggest your religion, health or other information. Other
              examples would be information required by government
              authorities to demonstrate fitness to fly (eg: COVID-19
              testing requirements) or for public health reasons.
              Generally, we try to limit the circumstances in which we
              collect and process sensitive information.
            </p>
            <p>
              5.2. We may collect and use sensitive information where the
              collection or use of such information is required by law or
              you have:
            </p>
            <ul>
              <li>
                a) requested specific medical assistance, such as the
                provision of wheelchair assistance or oxygen;
              </li>
              <li>
                b) sought clearance from us to fly with a medical condition
                or because you are more than 28 weeks pregnant; and/or
              </li>
              <li>
                c) chosen to provide such information to us or it has been
                passed onto us by a third party such as the travel agent
                through whom you made your booking.
              </li>
            </ul>
            <p>
              5.3. Sensitive information will only be collected by us if
              you have explicitly consented, unless otherwise stated above,
              and in accordance with applicable laws, including the
              Australian Privacy Principles.
            </p>

<h2>
              6. Information received from third parties
            </h2>
            <p>
              6.1. We may receive personal information about you from
              third parties, such as companies contracted by us to provide
              services to you, companies involved in your travel plans,
              airlines involved in your prior or onward journey, airport
              operators, customs and immigration authorities and companies
              that participate in our partner loyalty schemes and other
              customer programmes.
            </p>
            <p>
              6.2. These third parties receive your personal information
              in accordance with their own privacy policies and
              procedures and, where applicable, may function as separate
              controllers under the European Data Protection Legislation
              (refer to section 15 of this Privacy Policy for more
              information).
            </p>
            <p>
              6.3. We recommend you consult their individual privacy
              policies for further information.
            </p>
            <p>
              6.4. We are not responsible for the privacy practices of
              third parties or for the content, products, or services
              provided by them.
            </p>

<h2>
              7. Information we automatically collect about you
            </h2>
            <p>
              7.1. When you visit our website or mobile apps, our browser
              or other applications automatically collect information
              about your interaction with us such as your IP address,
              traffic data, clickstream information, time stamp, location
              data, web logs, other communication data and the resources
              that you access.
            </p>
            <p>
              7.2. We automatically collect data about your use of our
              website or apps using various technologies such as cookies
              as explained below. This information allows us to:
            </p>
            <ul>
              <li>
                a) ensure content from our website and apps is presented in
                the most effective manner for you and your device and to
                make improvements to our website;
              </li>
              <li>
                b) recognise you when you return to our website or app and
                to record your use;
              </li>
              <li>c) keep our website and apps secure;</li>
              <li>
                d) administer and maintain our website and apps by
                conducting testing and solving errors; and/or
              </li>
              <li>
                e) analyse your and other users\' use of the website and
                apps to create anonymised and aggregated research or
                statistics from time to time. Once your personal
                information has been anonymised and aggregated with that
                of other users, we will no longer be able to identify you
                or anyone else from the combined information.
              </li>
            </ul>
            <p>
              7.3. We use your personal information in this way because it
              is reasonably necessary for us to determine how you may be
              viewing and using our website and any apps so that we can
              operate them effectively and improve them.
            </p>

<h2>
              8. Cookies
            </h2>
            <p>
              8.1. Our websites and apps may use cookies, which are small
              data files containing an identifier (a string of letters and
              numbers) that is sent by a server to a browser and is stored
              by the browser. The identifier is then sent back to the
              server each time the browser requests a page from the
              server.
            </p>
            <p>
              8.2. We use cookies to enhance your use of our websites,
              apps and/or services and to monitor your activity during
              your visit(s) to our websites and apps and to help us make
              our website more useful for you. By continuing to use our
              websites and apps you consent to our use of cookies as
              explained in this Privacy Policy.
            </p>
            <p>
              8.3. Cookies do not typically contain any information that
              personally identifies a user, but personal information that
              we store about you may be linked to the information stored
              in and obtained from cookies.
            </p>
            <p>
              8.4. Some cookies are deleted when you close your browser.
              These are known as session cookies. Others remain on your
              device until they expire, or you delete them. These are
              known as persistent cookies and enable us to remember things
              about you as a returning visitor. If you do not want cookies
              to be stored, then you may turn off cookies individually or
              select the appropriate options on your browser or device to
              delete some or all cookies. Please note that if you block
              some or all cookies (including essential cookies) you may
              not be able to use or access all or parts of our websites.
            </p>
            <p>
              8.5. If you would like to disable cookies, you can set your
              web browser or device to reject cookies. However, if you
              disable the cookie function, you may not be able to access
              or receive all the information contained on our websites or
              apps. The method of altering your cookie settings will
              depend on the type of browser you use.
            </p>

<h2>
              9. How long do we keep personal information?
            </h2>
            <p>
              9.1. We will keep your information for as long as we need it
              for the purpose(s) for which it was collected. For example,
              where you book a flight with us, we will keep information
              related to your booking for a period that allows us to
              fulfil the travel arrangements, handle or respond to any
              complaints or queries, continue to improve your experience
              with us, ensure that you receive any loyalty rewards which
              are due to you and to allow us to comply with any legal
              obligations.
            </p>
            <p>
              9.2. We regularly review the information we hold and delete
              it securely, or in some cases anonymise it, when there is no
              longer a legal, business or customer need for it to be
              retained. We may retain a record of the existence of our
              relationship with you, to the extent that and for so long as
              we are required to do so by law.
            </p>

<h2>
              10. Who do we share your personal information with?
            </h2>
            <p>
              10.1. We do not sell personal information to any third
              parties.
            </p>
            <p>
              In certain circumstances, we may be required to share your
              personal information with third parties, including:
            </p>
            <ul>
              <li>
                a) other airlines and service providers where, for
                instance, part of your travel itinerary involves a flight
                operated by a different airline or includes car hire or a
                hotel booking;
              </li>
              <li>
                b) credit and charge card companies, credit reference
                agencies and anti-fraud screening service providers to
                process payments and (where necessary) to carry out
                fraud-screening;
              </li>
              <li>
                c) to confirm your identity or check your personal
                information (including by searching for your name and that
                of your business in publicly available and private
                databases);
              </li>
              <li>
                d) service providers we are using to provide services that
                involve data processing, for example, to carry out
                marketing initiatives or run customer surveys on our
                behalf;
              </li>
              <li>
                e) our business partners if you have expressed an interest
                in a product or service of such business partner. For
                example, if you have joined an affiliated loyalty program
                so that we can administer the benefits of the loyalty
                program to you;
              </li>
              <li>
                f) government and law enforcement agencies such as customs
                and immigration authorities, in response to a valid, legal
                requirement or request;
              </li>
              <li>
                g) any member of our group of companies (this means our
                subsidiaries, our ultimate holding company and all its
                subsidiaries) in so far as reasonably necessary for the
                purposes set out in this policy;
              </li>
              <li>
                h) analytics and search engine providers that assist us on
                the improvement and optimisation of our websites;
              </li>
              <li>
                i) any of our employees, officers, insurers, professional
                advisers, lawyers, tax or business advisors, agents,
                suppliers or subcontractors insofar as it is reasonably
                necessary for the purposes set out in this policy;
              </li>
              <li>
                j) a purchaser or successor entity in the event of a sale
                or other corporate transaction involving some or all of our
                business or as needed to effect the sale or transfer of
                business assets, or as needed for external audit,
                compliance or corporate governance related matters;
                and/or
              </li>
              <li>
                k) a third party to whom we have a duty to disclose or
                share your information:
                <ul>
                  <li>
                    i. to enforce or apply our conditions of carriage and
                    other agreements with you;
                  </li>
                  <li>
                    ii. as required by applicable law, court orders,
                    government entities or legal process (including
                    subpoenas);
                  </li>
                  <li>
                    iii. as necessary to protect the property, safety and
                    rights of others.
                  </li>
                </ul>
              </li>
            </ul>
            <p>
              By providing us with your personal information, you consent
              to the disclosure of your personal information to third
              parties as set out in paragraph 10.1. In the event you do
              not wish for us to disclose your personal information to
              third parties pursuant to paragraph 10.1, please contact us
              using the details in paragraph 18.
            </p>

<h2>
              11. International Data Transfers
            </h2>
            <p>
              11.1. We may transfer, store and process your personal
              information in servers located in countries worldwide,
              including in offshore cloud storage provided by third party
              suppliers. The nature of our business means it is often
              necessary for us to send your personal information outside
              the country in which you are located to fulfil your travel
              arrangements. This occurs because our business and the
              third parties we share your personal information with as
              described in this Privacy Policy have operations in
              countries across the world. By providing us with your
              Personal Information, you consent to this storage and
              transfer, as is reasonably necessary in conducting our
              business and the supply of our services to you.
            </p>
            <p>
              11.2. By providing us with your personal information, you
              consent to the disclosure of your personal information
              offshore as set out in paragraph 11.1.
            </p>
            <p>
              11.3. We are located in Australia and Naoero, which are not
              countries that have been deemed to offer adequate data
              protection by the European Commission. If you do provide
              your personal information to us, please note that you are
              doing so on the basis that you consent to the transfer of
              your personal information outside the European Economic
              Area (EEA). The potential consequence of this is that there
              is a risk that your personal information will not be
              protected in a manner that complies with European Data
              Protection Legislation. You can withdraw your consent to
              such international transfers by contacting us in accordance
              with paragraph 18 of this Privacy Policy, but it will mean
              that we will not be able to contact you about the services
              we can or have agreed to provide to you going forward.
              Please see how we handle your personal information under
              European Data Protection Legislation in paragraph 15.
            </p>

<h2>
              12. Your Rights
            </h2>
            <p>
              12.1. With respect to the personal information that we
              collect about you, you have the right to:
            </p>
            <ul>
              <li>a) request access to that personal information;</li>
              <li>
                b) receive a copy of the personal information that you
                have provided to us in a commonly used format so that you
                can share it with others;
              </li>
              <li>
                c) where personal information is inaccurate or incomplete,
                ask for personal information to be rectified or completed;
              </li>
              <li>
                d) request the transfer of your personal information to
                another party;
              </li>
              <li>e) ask that your personal information be erased;</li>
              <li>
                f) object to us processing your personal information by
                asking for the processing of that personal information to
                be restricted or stopped. For example, by requesting that
                we do not use your personal information for marketing
                purposes; and/or
              </li>
              <li>
                g) make a complaint about the way we process your personal
                information under any applicable laws.
              </li>
            </ul>
            <p>
              12.2. Please contact us using the details listed in
              paragraph 18 to exercise these rights or to ask us for
              additional information. When you contact us to exercise the
              above rights, we may ask for further information from you,
              to confirm your identity, as we take the protection of your
              personal information seriously. In the event you email us,
              we will endeavour to respond to requests, complaints or
              queries as soon as reasonably practicable.
            </p>
            <p>
              12.3. Please note that despite paragraph 12.1, we are not
              required to give you access to your Personal Information to
              the extent that:
            </p>
            <ul>
              <li>
                a) we reasonably believe that giving access would pose a
                serious threat to the life, health or safety of any
                individual, or to public health or public safety; or
              </li>
              <li>
                b) giving access would have an unreasonable impact on the
                privacy of other individuals; or
              </li>
              <li>c) the request for access is frivolous or vexatious; or</li>
              <li>
                d) the information relates to existing or anticipated
                legal proceedings between us and you, and would not be
                accessible by the process of discovery in those
                proceedings; or
              </li>
              <li>
                e) giving access would reveal our intentions in relation
                to negotiations with you in such a way as to prejudice
                those negotiations; or
              </li>
              <li>f) giving access would be unlawful; or</li>
              <li>
                g) denying access is required or authorised by or under
                applicable laws or a court/tribunal order; or
              </li>
              <li>
                h) both of the following apply:
                <ul>
                  <li>
                    i. we have reason to suspect that unlawful activity, or
                    misconduct of a serious nature, which relates to our
                    functions or activities has been, is being or may be
                    engaged in;
                  </li>
                  <li>
                    ii. giving access would be likely to prejudice the
                    taking of appropriate action in relation to the
                    matter; or
                  </li>
                </ul>
              </li>
              <li>
                i) giving access would be likely to prejudice one or more
                enforcement related activities conducted by, or on behalf
                of, an enforcement body; or
              </li>
              <li>
                j) giving access would reveal evaluative information
                generated by us in connection with a commercially
                sensitive decision-making process.
              </li>
            </ul>

<h2>
              13. Security of your personal information
            </h2>
            <p>
              13.1. We will take reasonable technical and organisational
              precautions to prevent the loss, misuse, disclosure or
              alteration of your personal information.
            </p>
            <p>
              13.2. We will use a range of physical, electronic and
              network security controls to take reasonable steps to
              protect the information we hold about you.
            </p>
            <p>
              13.3. All electronic financial transactions entered into
              through our website will be protected by encryption
              technology.
            </p>
            <p>
              13.4. You acknowledge that the transmission of information
              over the internet is inherently unsecure, and we cannot
              guarantee the security of data sent in this manner.
            </p>
            <p>
              13.5. You are responsible for keeping your booking
              information secure. To help you keep your information
              secure, we recommend that you:
            </p>
            <ul>
              <li>
                a) keep your booking reference confidential - when you
                make a booking, you will be given a booking reference on
                the email confirmation or ticket. You should always keep
                your booking reference confidential. Giving your booking
                reference to others may allow them to access your booking
                details through our system. If you are travelling with
                others and would not like your individual booking details
                to be accessible by them, you may prefer each person to
                make separate bookings.
              </li>
              <li>
                b) be aware of and protect yourself against Internet
                scams, fraud and \'phishing\' - there is an Internet fraud
                practice known as \'phishing\' (the illegal gathering of
                personal information by deception).
              </li>
            </ul>
            <p>
              13.6. If we become aware of a breach of security of your
              Personal Information, we will provide written notice to your
              postal address, contact you by telephone or contact you
              electronically within the period required by applicable
              laws.
            </p>

<h2>
              14. USA TSA Secure Flight Program - Privacy Notice
            </h2>
            <p>
              14.1. This paragraph is relevant for flights to and from the
              United States.
            </p>
            <p>
              14.2. The United States Transportation Security
              Administration (TSA) may require you to provide your full
              name, date of birth, and gender for the purpose of watch
              list screening, under the authority of Title 49 U.S.C.
              section 114, the Intelligence Reform and Terrorism
              Prevention Act of 2004 and Title 49 C.F.R parts 1540 and
              1560. You may also provide your Redress Number, if
              available.
            </p>
            <p>
              14.3. Failure to provide your full name, date of birth, and
              gender may result in denial of transport or denial of
              authority to enter the boarding area. TSA may share the
              information you provide with law enforcement or intelligence
              agencies or others under its published system of records
              notice. For more information on TSA privacy policies, or to
              review the system of records notice and the privacy impact
              assessment, please see the TSA Web site at 
              <a href="https://www.tsa.gov" target="_blank">
                www.tsa.gov
              </a>
              .
            </p>

<h2>
              15. European Data Protection Legislation
            </h2>
            <p>
              15.1. For the purposes of this Privacy Policy, "European Data
              Protection Legislation" means all applicable legislation
              relating to data protection in the European Economic Area,
              including the EU General Data Protection Regulation
              2016/679 (GDPR) and all legislation implementing, made
              under, pursuant to, replacing or superseding the GDPR. Where
              this Privacy Policy uses terms defined in the GDPR, the
              definitions set out in the GDPR will apply.
            </p>
            <p>
              15.2. Where the European Data Protection Legislation applies
              to personal information collected about you, Naoero Airlines
              is the "controller" of your personal information. If you
              have made a flight booking with us but one or more flights
              are to be provided by another airline or airlines, then that
              other airline will also be considered a "controller". Any
              provider of services such as a hotel or car rental company
              will also separately be a "controller". Please be careful
              to review the privacy policies of each individual service
              provider, which you can access from them directly.
            </p>
            <p>
              15.3. This paragraph 15 applies if you are based in the
              European Economic Area (EEA), either as a citizen or
              resident of the EEA, during your communications with us
              (outside of when you are in the EEA for travel purposes) and
              sets out the additional information Naoero Airlines is
              required to provide to you under European law.
            </p>

            <h3>
              15.4. Personal Data
            </h3>
            <p>
              Whilst we have used the term "personal information"
              throughout our Privacy Policy in line with Australian
              terminology, EU legislation uses the term "personal data"
              and therefore we have used that term in this paragraph 15
              only.
            </p>

            <h3>
              15.5. How we use personal data
            </h3>
            <p>
              We collect and use personal data in the ways we have set out
              in this Privacy Policy. We recommend you review this
              section as well as the remainder of our Privacy Policy to
              understand the nature of the personal data we may collect
              and the circumstances in which it is collected and used.
            </p>
            <p>
              "Controllers" can only process personal data in one of the
              following circumstances:
            </p>
            <ul>
              <li>a) with consent;</li>
              <li>b) where there is a contractual obligation;</li>
              <li>c) to meet a legal obligation;</li>
              <li>
                d) where processing is necessary for the performance of a
                task carried out in the public interest;
              </li>
              <li>e) to protect the vital interests of an individual;</li>
              <li>
                f) for an organisation\'s legitimate interests (except if
                these are overridden by the interests or fundamental
                rights of individuals).
              </li>
            </ul>
            <p>
              In addition, the GDPR establishes additional conditions for
              the processing of sensitive data. We rely on the following
              GDPR lawful bases to process your personal data:
            </p>

            
              <table class="table-auto w-full border border-slate-200">
                <th class="border border-slate-200 p-2 font-bold">
                  <tr>
                    <th class="border border-slate-200 p-2 font-bold">
                      Purposes of the data processing
                    </th>
                    <th class="border border-slate-200 p-2 font-bold">Basis of Use</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="border border-slate-200 p-2">
                      Providing our travel services
                    </td>
                    <td class="border border-slate-200 p-2">
                      Performance of a contract, such as providing you with
                      a flight to complete your travel bookings.
                      <br />
                      <br />
                      Legitimate interests, to allow us to perform our
                      obligations and provide services.
                      <br />
                      <br />
                      Consent, where you have provided us with sensitive
                      personal data.
                    </td>
                  </tr>
                  <tr>
                    <td class="border border-slate-200 p-2">
                      Marketing and conducting consumer research
                    </td>
                    <td class="border border-slate-200 p-2">
                      Legitimate interests, in marketing our services to
                      you.
                      <br />
                      <br />
                      Consent, which you may withdraw at any time by
                      contacting us and telling us so.
                    </td>
                  </tr>
                  <tr>
                    <td class="border border-slate-200 p-2">
                      Providing customer support
                    </td>
                    <td class="border border-slate-200 p-2">
                      Performance of a contract
                      <br />
                      <br />
                      Meeting our legal obligations
                      <br />
                      <br />
                      Legitimate interests, such as enabling us to
                      communicate with you.
                    </td>
                  </tr>
                  <tr>
                    <td class="border border-slate-200 p-2">
                      Meeting our legal and regulatory obligations and for
                      health, security and safety purposes
                    </td>
                    <td class="border border-slate-200 p-2">
                      Meeting our legal obligations
                      <br />
                      <br />
                      Legitimate interests, such as complying with the
                      requirements of law enforcement agencies and
                      regulatory authorities or ensuring the health and
                      safety of our passengers and staff.
                      <br />
                      <br />
                      Sensitive Personal Data collection: meeting our
                      legal obligations, vital interests in the case of an
                      emergency, and public interest regarding public
                      health and safety.
                    </td>
                  </tr>
                  <tr>
                    <td class="border border-slate-200 p-2">
                      To prevent fraudulent activity
                    </td>
                    <td class="border border-slate-200 p-2">
                      Legitimate interests, such as identifying and
                      responding to possible fraudulent transactions.
                    </td>
                  </tr>
                  <tr>
                    <td class="border border-slate-200 p-2">
                      To ensure our website functions as well as possible
                    </td>
                    <td class="border border-slate-200 p-2">
                      Legitimate interests, such as identifying for you the
                      appropriate content and services on our website.
                    </td>
                  </tr>
                </tbody>
              </table>
            

            <h3>
              15.6. Protecting your personal data
            </h3>
            <p>
              We use several controls to protect your personal data such
              as encryption and multi factor authentication. We may at
              times have to transfer your personal data to a third party
              in another country who does not meet the requirements of
              the GDPR or other EU legislation relating to data
              protection. If that occurs, we use appropriate safeguards to
              protect your personal data such as including standard
              contractual clauses in our agreements with third parties,
              using procedures or other controls.
            </p>

            <h3>
              15.7. How long we keep your personal data
            </h3>
            <p>
              How long we keep your personal data depends on our business
              needs and legal requirements. We keep the data for as long
              as is necessary to meet our regulatory obligations, even
              after you have flown with us or otherwise ceased using our
              services. We may also retain your personal data for other
              permitted purposes, such as contacting you.
            </p>

            <h3>
              15.8. Marketing
            </h3>
            <p>
              We may use your personal data for marketing purposes
              however you have the right to ask us not to. We will tell
              you if we share your personal data with third parties for
              marketing purposes. You can contact us at 
              <a href="mailto:privacy@naoeroairlines.com.au" target="_blank">
                privacy@naoeroairlines.com.au
              </a> 
              at any time to withdraw your consent if you have previously
              opted in to sharing your personal data.
            </p>

            <h3>
              15.9. Other rights for EU citizens and residents
            </h3>
            <p>
              You have rights to access, correct and withdraw consent in
              relation to your personal data. If you are an EU resident
              or citizen you may also have additional rights under EU law
              such as asking us to:
            </p>
            <ul>
              <li>a) provide details as to how we process your data;</li>
              <li>b) restrict our use of your personal data;</li>
              <li>c) delete your personal data;</li>
              <li>
                d) stop using your personal data for marketing purposes,
                legitimate or public interests; and/or
              </li>
              <li>e) share your personal data to a third party.</li>
            </ul>
            <p>
              We may be able to refuse your request on legal grounds
              however, if that occurs, we must explain in writing to you
              why that is the case.
            </p>

            <h3>
              15.10. Complaints
            </h3>
            <p>
              If you wish to make a complaint about how we handle your
              personal data, please contact us at 
              <a href="mailto:privacy@naoeroairlines.com.au" target="_blank">
                privacy@naoeroairlines.com.au
              </a>
              . We take all complaints seriously and will do our best to
              resolve any issues within 30 days of your complaint being
              lodged with us.
            </p>
            <p>
              If you are still not satisfied with our actions, you may
              contact your EU member state data protection authority at 
              <a href="https://www.edpb.europa.eu/edpb_en" target="_blank">
                edpb.europa.eu
              </a>
              .
            </p>

<h2>
              16. CCTV Images
            </h2>
            <p>
              16.1. We, or our third party providers, may operate CCTV
              cameras at airport terminals, buildings and facilities.
            </p>
            <p>
              16.2. Your personal information may be collected through our
              CCTV cameras if you are reasonably identifiable from the
              images captured by these cameras.
            </p>
            <p>
              16.3. Where we use CCTV, we do so because it is reasonably
              necessary to:
            </p>
            <ul>
              <li>
                a) monitor access to our terminals, buildings, and
                workplaces;
              </li>
              <li>
                b) identify and respond to security incidents or threats;
              </li>
              <li>
                c) investigate incidents or accidents occurring on our
                premises;
              </li>
              <li>
                d) assist airport authorities, security and management
                personnel, regulatory bodies and law enforcement agencies;
                and/or
              </li>
              <li>e) meet our legal and regulatory requirements.</li>
            </ul>
            <p>
              16.4. We may disclose personal information captured by our
              CCTV cameras to our related companies, airport authorities,
              security, management, law enforcement agencies, our
              insurers, and those third parties who provide us with our
              CCTV equipment.
            </p>

<h2>
              17. Amendments to this Privacy Policy
            </h2>
            <p>
              17.1. We may update this policy from time to time to reflect
              any changes in our practices, services and legal obligations
              by publishing a new version on our website.
            </p>
            <p>
              17.2. Your continued use of our services, websites and apps
              following the effective date of a change to this Privacy
              Policy signifies your acceptance and agreement to the terms
              of the revised Privacy Policy. We encourage you to check
              this page periodically to ensure you have the most up to
              date privacy statement and terms.
            </p>

<h2>
              18. Ownership and Contact details
            </h2>
            <p>
              18.1. This website is owned and operated by Naoero Air
              Corporation trading as Naoero Airlines.
            </p>
            <p>
              18.2. If you have any questions on our Privacy Policy or any
              concerns about the ways in which we protect your personal
              information, please contact us by email at 
              <a href="mailto:privacy@naoeroairlines.com.au" target="_blank">
                privacy@naoeroairlines.com.au
              </a> 
              or by telephone on 1300 369 044 (#2) inside Australia or 
              <a href="tel:+61732296455" target="_blank">
                +61 7 3229 6455
              </a> 
              outside Australia.
            </p>';

        LegalDocument::updateOrCreate(
            ['slug' => 'privacy-policy'],
            [
                'title' => 'Privacy Policy',
                'heading' => 'Privacy Policy',
                'subheading' => 'Please take the time to read and understand our Privacy Policy.',
                'badge_text' => 'About Us > Privacy Policy',
                'banner_image' => '/assets/privacy/banner.jpg',
                'contact_email' => 'privacy@naoeroairlines.com.au',
                'summary' => 'Naoero Air Corporation trading as Naoero Airlines is committed to protecting your personal information in accordance with applicable privacy legislation.',
                'sections' => $privacySections,
                'content' => $privacyHtml,
                'last_reviewed_at' => '2026-08-18',
                'next_review_at' => '2027-01-01',
                'is_active' => true,
            ]
        );

        // 5.2 TERMS OF USE (Full 4 Sections)
        $termsSections = [
            [
                'heading' => '1. Accuracy & Liability Disclaimer',
                'content' => 'Naoero Airlines makes every effort to ensure the accuracy of the information provided. However, we do not accept liability for any errors, omissions, or inaccuracies on this website or on any third-party websites linked from our pages.

Naoero Airlines shall not be held responsible for any loss or damages arising from:
• Errors or omissions in website content
• Use of data or reliance on information provided
• Disruptions, technical issues, or system failures affecting website access

This includes, but is not limited to, loss of revenue, data loss, or damage to computer systems, even in cases of negligence.',
            ],
            [
                'heading' => '2. Website Security & User Responsibility',
                'content' => 'Naoero Airlines does not guarantee uninterrupted access to this website and is not responsible for any interference, malware, or damage that may occur to your computer systems while using our website or any external links. Users are responsible for taking necessary precautions to protect their devices from viruses, malware, or other potential threats.',
            ],
            [
                'heading' => '3. Flight Schedules & Fares',
                'content' => 'All schedules, fares, and travel-related information are subject to change without notice. Naoero Airlines assumes no liability for any damages, inconvenience, or loss resulting from reliance on published schedules or fare information. All bookings remain subject to our Passenger Conditions of Carriage.',
            ],
            [
                'heading' => '4. Intellectual Property Rights',
                'content' => 'The Naoero Airlines logo, branding, photographic assets, and website content are the exclusive property of Naoero Airlines. Any trademarks, logos, or materials belonging to participating organisations remain the property of their respective owners. Unauthorised reproduction, scraping, or distribution of any content from this website is strictly prohibited.',
            ],
        ];

        $termsHtml = '<h2>1. Accuracy & Liability Disclaimer</h2>
<p>Naoero Airlines makes every effort to ensure the accuracy of the information provided. However, we do not accept liability for any errors, omissions, or inaccuracies on this website or on any third-party websites linked from our pages.</p>
<p>Naoero Airlines shall not be held responsible for any loss or damages arising from:</p>
<ul>
<li>Errors or omissions in website content</li>
<li>Use of data or reliance on information provided</li>
<li>Disruptions, technical issues, or system failures affecting website access</li>
</ul>
<p>This includes, but is not limited to, loss of revenue, data loss, or damage to computer systems, even in cases of negligence.</p>
<h2>2. Website Security & User Responsibility</h2>
<p>Naoero Airlines does not guarantee uninterrupted access to this website and is not responsible for any interference, malware, or damage that may occur to your computer systems while using our website or any external links. Users are responsible for taking necessary precautions to protect their devices from viruses, malware, or other potential threats.</p>
<h2>3. Flight Schedules & Fares</h2>
<p>All schedules, fares, and travel-related information are subject to change without notice. Naoero Airlines assumes no liability for any damages, inconvenience, or loss resulting from reliance on published schedules or fare information.</p>
<h2>4. Intellectual Property Rights</h2>
<p>The Naoero Airlines logo, branding, and website content are the exclusive property of Naoero Airlines. Any trademarks, logos, or materials belonging to participating organisations remain the property of their respective owners. Unauthorised use, reproduction, or distribution of any content from this website is strictly prohibited.</p>
<p>By using this website, you acknowledge and accept these terms. If you do not agree with any part of these terms, please refrain from using this website.</p>
<p>For further inquiries, please contact us at <a href="mailto:privacy@naoeroairlines.com.au" target="_blank">privacy@naoeroairlines.com.au</a>.</p>';

        LegalDocument::updateOrCreate(
            ['slug' => 'terms-of-use'],
            [
                'title' => 'Terms of Use',
                'heading' => 'Terms of Use',
                'subheading' => 'The information presented in this web site is provided in good faith and is for information only.',
                'badge_text' => 'About > Terms of Use',
                'banner_image' => '/assets/career/carrerBannerimage.jpg',
                'contact_email' => 'privacy@naoeroairlines.com.au',
                'summary' => 'Terms governing the access, browsing, and use of the Naoero Airlines official website and reservation portals.',
                'sections' => $termsSections,
                'content' => $termsHtml,
                'last_reviewed_at' => '2026-08-18',
                'next_review_at' => '2027-01-01',
                'is_active' => true,
            ]
        );

        // 5.3 CONDITIONS OF CARRIAGE (Full 19 Sections + Important Notices)
        $carriageSections = [
            [
                'heading' => 'Section 1. What These Conditions Do',
                'content' => '1.1 These Conditions deal with very important issues, including: How and when these Conditions apply. Fares and tariffs. Passengers who need special assistance. Important information for your health and safety. Refusal of carriage. Schedules and disruptions. Our liability to you (including limits of liability). 1.2 Please read these Conditions carefully and visit www.naoeroairlines.com.au or contact the Naoero Airlines Reservations (1300 369 044 (then # 2) within Australia, 5577000 within Naoero or +61 7 3229 6455 if calling from outside Australia) if you have any questions.',
            ],
            [
                'heading' => 'Section 2. How And When These Conditions Apply',
                'content' => '2.1 General These Conditions apply whenever you travel on a Naoero Airlines flight or flight segment, the \'ON\' Airline Designator Code is shown as the carrier on your Ticket or in any case where we have a legal liability to you in relation to your flight. 2.2 Basis of carriage Your travel with Naoero Airlines is, without exception, subject to: the Conditions of Contract and Other Important Notices; these Conditions; any applicable Conventions; any applicable Laws; any applicable Tariffs filed by us with regulatory bodies; the Fare Rules; the Policies; and any written or oral directions given to you by Naoero Airlines Staff and/or Authorised Agents. 2.3 Interaction with Laws, Tariffs, Conventions 1. These Conditions: apply to the extent permitted by any applicable Laws, and if any provision of these Conditions is void, illegal, invalid or unenforceable in any jurisdiction, these Conditions will be read down to the extent necessary to ensure it is not void, illegal, invalid or unenforceable in that jurisdiction; and prevail unless inconsistent with any applicable Laws, Conventions or Tariffs, in which case the Laws, Conventions or Tariffs will apply to the extent of the inconsistency. 2. In respect of any goods or services we may provide, certain statutory guarantees or warranties may apply for the benefit of consumers. The nature and application of these guarantees or warranties will depend on the relevant jurisdiction. 3. In Australia, you have certain rights under the Australian Consumer Law. The Australian Consumer Law provides consumer guarantees that services must be: fit for a particular purpose; rendered with due care and skill; and supplied within a reasonable time. Where the Australian Consumer Law applies and we fail to provide services to you in accordance with these consumer guarantees, then you may have a right to seek a remedy from us in accordance with the Australian Consumer Law. 4. Nothing in these Conditions excludes or restricts the application of relevant Laws, including the Australian Consumer Law, Civil Aviation (Carrier\'s Liability) Act 1959 (Cth) or any Conventions. In respect of goods or services acquired for business purposes and not for personal, domestic or household use, to the extent permitted by the Australian Consumer Law our liability for a breach of the guarantee or warranty will be limited to either supplying the goods or services again or paying the cost of having them supplied again. 5. Where the carriage of cargo is for the purposes of a business, trade, profession or occupation carried on or engaged in by the person for whom the goods are transported, the guarantees relating to the supply of services in the Australian Consumer Law do not apply. 2.4 Variation and waiver No Naoero Airlines Staff, Authorised Agent or other person is authorised to vary these Conditions. However, Naoero Airlines Staff and/or Authorised Agents may in certain circumstances waive Fare Rules or amounts payable. A waiver on one occasion does not constitute a waiver on any other occasion. 2.5 Bookings on behalf of another carrier and other transportation or services At times you may also travel on services which we sell but which are operated by other carriers under those carriers\' flight numbers and Airline Designator Codes. If we issue a Ticket for you as agent of another carrier, or we issue a ticket for check baggage on the flight of another carrier, we do so only as agent for that carrier. The conditions of carriage of the other carrier will apply for flights sold on behalf of the other carrier. Please go to the operating carrier\'s website for further information about your rights in these circumstances. These Conditions do not apply to any non-air transportation or other services Naoero Airlines arranges for you (for example, bus transfers and hotel accommodation). Where Naoero Airlines arranges these services, it does so as your agent and your contract is with the provider of the relevant transportation or services. If you require further information about the terms of the contract, please contact the relevant provider. 2.6 Charter Operations These Conditions apply to charter or hire operations where we are the operator of the flight unless you are advised otherwise in writing by or on behalf of the charterer prior to boarding the aircraft.',
            ],
            [
                'heading' => 'Section 3. Getting Ready To Fly',
                'content' => '3.1 Passports, visas and authorisations It is your responsibility to comply with all laws, regulations and orders of your chosen destination. For more information, please ask an Authorised Agent or consult the government which issued your passport. Please consider the following: make sure you have a valid passport with at least 6 months validity remaining before it expires. In addition, if your passport expires in 12 months or less, you may want to check whether this is sufficient for your chosen destination and time away; check with the government which issued your passport and the consulates of your chosen destination to see whether you need a visa, other travel documents, or information such as vaccination records; and find out about any dangers to your health and safety at your chosen destination. The above list is given as a guide only. It remains your responsibility to ensure you have all required authorisations and documentation for your travel. Any assistance Naoero Airlines provides you in this area does not release you from your obligations. 3.2 Finding out about where you are going It is a good idea to check relevant government websites to find out about any dangers and risks to your health and safety at your chosen destinations. You can do this by checking with government agencies like the Australian Department of Foreign Affairs and Trade (smartraveller.gov.au). 3.3 Your health The health and safety of all of Passengers is paramount. That is why it is important for you to be aware of health and safety issues which are relevant to you and to tell us about any health issues which affect your safety or the health and safety of others. If you are in any doubt, please consult your doctor and talk to an Authorised Agent and/or the Naoero Airlines Reservations. 1. Risks inherent to being seated for extended periods of time By its very nature, medium to long-haul air travel involves extended periods of sitting. This may be a risk factor for some people forming blood clots in limbs, known as Deep Vein Thrombosis (DVT). You should discuss with your doctor whether you are at risk of suffering DVT and what preventative measures you should take. 2. Medical Clearance If you have a medical condition that falls within our Medical Services Policy, we may require you to provide a medical certificate signed by a qualified medical practitioner. If you do not provide a medical certificate satisfactory to us, we may refuse to carry you on our flights. The Medical Services Policy can be accessed by contacting the Naoero Airlines Reservations. 3. Public Health or Medical Emergency If a public health emergency is announced by any government or the World Health Organisation or a medical emergency arises, we may be required to: cancel, divert or delay any flight; screen passengers; require valid evidence of vaccinations or inoculations (including against COVID-19) and/or negative test results (including against COVID-19); require health documentation to be presented; offload or refuse to carry passenger(s) and their Baggage if they do not provide valid health documentation (including evidence of COVID-19 vaccination); isolate passenger(s) on board the aircraft and limit the inflight services; notify appropriate authorities such as the police, health authorities or airport security; and perform any other action we consider necessary to provide a safe environment for passengers, staff, contractors and passengers. 3.4 Travel insurance It can be expensive if something happens to you when you are overseas. Naoero Airlines strongly recommends that you purchase travel insurance for your journey, including cover for any losses you might incur if for any reason you are unable to travel with us on your planned travel date.',
            ],
            [
                'heading' => 'Section 4. Code Share Services',
                'content' => 'We have arrangements with other carriers known as Code Share Services. This means that you may have made a reservation with us, and you may travel on another carrier\'s aircraft and vice versa. If such arrangements apply to your flight, we will advise you of the carrier operating the aircraft at the time you make a Reservation. The conditions of carriage of the carrier whose flight number appears on your Ticket will apply to your flight. It is your responsibility to read and comply with those conditions of carriage.',
            ],
            [
                'heading' => 'Section 5. Reservations And Seat Allocation',
                'content' => '5.1 What is a Reservation? A Reservation is a booking on a Naoero Airlines flight and is confirmed only when recorded and paid for and accepted by us. An open-dated ticket is not a Reservation. Certain fares may have conditions which limit or exclude your right to change or cancel Reservations. Please check the Fare Rules for details. 5.2 How to make a Reservation You can make a Reservation by booking with us, an Authorised Agent or online at www.naoeroairlines.com.au 5.3 What happens next? Once you have a Reservation, you must pay the Fare for the Reservation either at the time of booking or, where you have been provided with a Ticketing Time Limit, within that Ticketing Time Limit. Make sure you clearly understand any Ticketing Time Limit when it is advised to you. If you do not pay for your Reservation within the Ticketing Time Limit, we may cancel your Reservation. If you pay for your Reservation at the time of booking or within the Ticketing Time Limit you will be issued with a Ticket by Naoero Airlines or your Authorised Agent. 5.4 If you do not use a Reservation Depending on the Fare Rules which apply to your Reservation, if you do not use the Reservation, you may forfeit your Fare. 5.5 Seat allocation Some Fare classes and cabin classes may allow you to request a preferred seat and/or cabin area. You may also have requested to be placed in a certain seat and/or cabin area during the booking process. We will endeavour to accommodate your seat request. However, we do not guarantee you any particular seat, even if you have nominated a seating preference. We may change your seat at any time, even after you have boarded the aircraft. This may be necessary for safety or operational reasons. 5.6 Passengers in Economy Class Occupying Two Seats This clause applies to economy class travel only. Upon request, or if determined necessary by us, and given availability, you will be permitted to the exclusive use of two seats subject to the payment of two applicable fares for the points between which the two seats will be used. A Ticket will be issued for each seat and the normal Checked Baggage Allowances will apply in connection with each such Ticket presented to us. The carry-on allowance is limited to the allowance for one individual. 5.7 Reconfirmation of Reservation You may be required to reconfirm onward or return Reservations with us or any other carriers involved in your journey in accordance with the time limits specified in the relevant Ticket. You are responsible for checking and complying with all reconfirmation requirements. 5.8 Cancellation of Onward Reservations If you do not use a Reservation and fail to advise us, we may cancel or request cancellation of any onward or return Reservations. 5.9 Collection of personal information When you make a Reservation, we must collect certain personal information in order to process your Reservation and comply with our legal obligations. Our privacy policy applies to all of the information we collect. A copy of the privacy policy is available at www.naoeroairlines.com.au or contact the Naoero Airlines Reservations for more information.',
            ],
            [
                'heading' => 'Section 6. Tickets, Fares And Tariffs',
                'content' => '6.1 Issuing a Ticket If you have a Reservation and you pay for it at the time of booking or within the Ticketing Time Limit either Naoero Airlines or an Authorised Agent will issue you with a Ticket. A Ticket may be an Electronic Ticket. Other airlines may be authorised to issue Electronic Tickets for Naoero Airlines. 6.2 Importance of Ticket Your Ticket is your primary evidence of your contract with Naoero Airlines and is a valuable document. You should keep it safe. If your Ticket was issued by us, an Authorised Agent or another airline, it remains the property of Naoero Airlines. 6.3 Lost Tickets If you lose your Electronic Ticket, you can print another copy or ask us to send you a replacement Electronic Ticket by email. 6.4 Rules about Tickets Tickets are not transferable (except for Fare types where name changes are permitted) and you must not sell or transfer them to anyone else. Any name changes MUST occur by calling the Naoero Airlines Reservations and paying the applicable name change fee. We will not honour a Ticket which has been sold or transferred to a person other than the person to whom the Ticket was issued. You will not be entitled to be carried on a flight unless you provide a Paper Ticket or Electronic Ticket issued in your Name and positive identification. 6.5 Ticket validity and extension of validity Unless your Ticket, Itinerary, Fare Rules or applicable Tariffs state otherwise, the following rules apply to the validity of your Ticket: a Ticket for International Travel is valid for one year from the date of commencement of travel, or if the Ticket is not used, one year from the date of the issue of the Ticket. Naoero Airlines may extend the validity of your Ticket if your travel is disrupted by an event beyond our control. 6.6 Flight Coupon Sequence Naoero Airlines will honour Flight Coupons only in the sequence from the place of departure as shown on the Ticket. The Ticket may not be valid, and we may not honour your Ticket if the first Flight Coupon for travel has not been used and you commence your journey at any Stopover or Agreed Stopping Place. Each Flight Coupon will be accepted for carriage in the class of Service specified on the Flight Coupon on the date and flight for which accommodation has been reserved. When Flight Coupons are issued without a Reservation being specified, space will be reserved on application subject to the conditions of the relevant fare and the availability of space on the flight applied for. 6.7 Changes Any changes to your Ticket or Itinerary must be made by Naoero Airlines or an Authorised Agent and in accordance with the Fare Rules. 6.8 Fares Naoero Airlines sets various Fares for the various cabin classes available on its services. The Fares are set in our absolute discretion and provide the inclusions and exclusions set out in detail in the Fare Rules. In general, Fares cover the transport of you and your Baggage (subject to Naoero Airlines\' Conditions of Contract and Other Important Notices) from the origin specified in your Ticket to the destination specified in your Ticket. The Fares do not cover your transport to or from the airport or between airports and/or other places unless provided by us without additional charge. 6.9 Which Fare applies? Naoero Airlines will calculate your exact Fare when you pay for your Reservation. We may increase fees and charges as relevant under the Fare Rules from time to time. Such changes will not affect existing bookings. However, if you change your existing booking, including your fare type, new Fare Rules and fees may apply to these changes. You will be told about the applicable Fare Rules at the time of the change to your booking. 6.10 Fare Rules Naoero Airlines has various Fare Rules which apply to its Fares, and these rules influence both the price and the flexibility of the relevant Fares. A copy of the Fare Rules is available at www.naoeroairlines.com.au or contact your Authorised Agent or the Naoero Airlines Reservations for further information. Subject to your right to a refund under applicable Laws, including the Australian Consumer Law, some Fare Rules relate to non-refundable or very restricted Fares and you should carefully consider these rules, your needs and your insurance cover before you pay for your Reservation. Should the Fare Rules applicable to a particular Fare not suit your travel requirements we encourage you to upgrade to a more suitable product (for example, full economy Fare). 6.11 Taxes and charges You are responsible for applicable taxes or charges imposed by governments, other authorities or by the operator of an airport that are in effect on the date of travel. Some applicable taxes and charges may be applied to your Ticket at time of Ticket issuance. Some airport departure charges or visa fees, which may change from time to time and may be levied directly at an international airport. Such amounts may not be marked on your Naoero Airlines Ticket and will need to be paid by you to the relevant third party prior to departure. 6.12 Currency Fares and charges are payable in any currency acceptable to Naoero Airlines. When payment is made in a currency other than the currency in which the fare is published, such payment will be made at the rate of exchange set by us at the relevant time.',
            ],
            [
                'heading' => 'Section 7. Refunds',
                'content' => '7.1 Under consumer law This Paragraph 7 is subject to Subparagraph clause 2.3. Where consumer guarantees apply under applicable consumer laws including the Australian Consumer Law, and we fail to provide services to you in accordance with those consumer guarantees, then you may have a right to seek a remedy in accordance with the relevant consumer law in addition to the remedies set out in this Paragraph 7. Please see Subparagraph 2.3 or contact us for more detail. 7.2 According to Fare Rules Depending on the Fare Rules and the applicable law, including the Australian Consumer Law, you might be entitled to a refund on part or all of your Ticket. If you are entitled to a refund we will refund the Fare you paid for any unused, refund-eligible part of your Ticket, together with any taxes, levies and surcharges which applied to those parts of your Ticket, subject to the Refund Rules listed in Subparagraph 7.3. The amount of the refund will be calculated as follows: if no portion of the Ticket has been used, an amount equal to the Fare paid (including any applicable taxes, levies and surcharges), less any applicable service charges or cancellation fees as set out in the Fare Rules; and if a portion of the Ticket has been used, the refund will be an amount equal to the difference between the Fare paid and the applicable Fare for travel between the points for which the Ticket has been used (considered on a one-way basis where appropriate, and including any applicable taxes, levies and surcharges) less any applicable service charges or cancellation fees as set out in the Fare Rules. 7.3 Refund Rules The following rules apply to all refunds: If the Ticket is a Paper Ticket, we will only provide a refund on surrender of the Paper Ticket (including all unused Flight sectors) to us; any refund will be paid to the person who paid for the Ticket, unless that person has authorised us in writing to pay the refund to someone else. Naoero Airlines will need to formally identify the other person receiving the refund before any payment will be made. This may include the other person providing suitable photographic identification documents such as passport or motor vehicle driver’s licence details as reasonably requested by Naoero Airlines; we reserve the right to provide the refund in the currency of the original booking or in Australian Dollars at our discretion; the refund provided under Subparagraph 7.3 will be in full and final satisfaction of any claim for refund by the person who paid for the Ticket and the person named on the Ticket. We will refund any taxes and charges which you have paid on parts of your Ticket which you have not used if those taxes and charges are refundable and provided that we have not already provided the taxes and charges to the authority which levied them; and Naoero Airlines reserves the right to charge you a reasonable administration fee as set out in the Fare Rules. If the administration fee exceeds the amount of the refundable taxes and charges, we will not pay you a refund. 7.4 Refusing refund Irrespective of clauses 7.2 and 7.3, and subject to any applicable laws, we may refuse to give you a refund if: you apply for a refund after the expiry of the validity period of the Ticket (see Subparagraph 6.5); your Ticket has been held by government officials as evidence of an offence against the laws of any country; or you change your mind or miss your travel due to no fault of us.',
            ],
            [
                'heading' => 'Section 8. Passengers Who Require Special Assistance Or Special Meals',
                'content' => '8.1 Special assistance If you require special assistance (for example, due to illness, incapacitation, limited mobility, or travelling as an unaccompanied minor) we will not refuse to carry you provided that: you have fully informed us of any special requirements at the time of making a Reservation; you have complied with any reasonable requests we may have (including complying with our Policies); and we and the operator of any relevant Code Share Services have agreed to provide the special assistance. Please visit the Naoero Airlines Website www.naoeroairlines.com.au or contact the Naoero Airlines Reservations for more information. 8.2 Unaccompanied minors In addition to the requirements set out in Subparagraphs 8.1 and 10.2, Naoero Airlines will only carry unaccompanied minors in accordance with its Unaccompanied Minor Policy, which in summary requires: children under 5 years old will not be carried as an unaccompanied minor; children between the ages of 5 and 11 inclusive (i.e. before their 12th birthday) who are booked to travel on a Naoero Airlines flight, without the supervision of their parent, legal guardian or other person nominated by the child\'s parent or guardian who is 16 years or older, be booked as an \'Unaccompanied Minor\' for that flight; the Unaccompanied Minor to hold his or her own valid passport and all country specific travel documents, including any visas or authorisation letters; that the parent/guardian of the Unaccompanied Minor provide a completed and signed Unaccompanied Minor Form, which must include the name and contact details of the person/s who will deliver the child to our check-in staff, and, the name and contact details of the person/s who will collect the child from our staff; the Unaccompanied Minor Form should be submitted to the Naoero Airlines Reservations team (reservations@naoeroairlines.com.au) at least 72 hours before the time of departure; and the person/s delivering and collecting the Unaccompanied Minor to produce a valid piece of photo identification and signed acceptance or delivery forms for each child travelling. There are other limitations, restrictions and requirements that apply to the carriage of Unaccompanied Minors set out in our Unaccompanied Minor Policy. It is your responsibility to read and comply with the Policy in full. Naoero Airlines reserves the right not to carry the Unaccompanied Minor if the requirements set out above and contained in our Unaccompanied Minor Policy are not complied with. The complete Unaccompanied Minor Policy is available at Unaccompanied Minors . 8.3 Pregnancy We do not represent that air travel is safe at any particular point during pregnancy, including during the time periods noted below. You must seek advice from your own medical practitioner prior to carriage as to whether it is safe to fly. If you are pregnant, the following precautions apply: International travel is not permitted after the 36th week for routine pregnancies or the 32nd week for routine multiple pregnancies e.g., twins. After 28 weeks, you need to carry a certificate or letter from a registered medical practitioner or registered midwife confirming: the estimated date of delivery. whether it is a single or multiple pregnancy. that the pregnancy is a routine pregnancy and that there are no complications with the pregnancy. Medical clearance is only required if you are having complications with your pregnancy or if you wish to travel past the 36th (or 32nd for multiple pregnancies) week. Some countries place limitations on the entry of non-national pregnant women. Check with the relevant embassy or consulate before you fly to confirm any further limitations. 8.4 Special meals If you request a special meal when you make your Reservation, we will try to ensure it is available on your selected flight. However, we do not guarantee that we will be able to supply your special meal and we will not be liable to you for any loss, expense or other damage should we be unable to provide your requested special meal for any reason.',
            ],
            [
                'heading' => 'Section 9. At The Airport',
                'content' => '9.1 Check-in You must arrive at the airport early enough to allow you to complete check-in procedures before the check-in deadline. You must bring with you a valid passport, your Paper Ticket or Electronic Ticket and all necessary travel documentation for your Destination and any Stopovers. You must present all of these documents to Naoero Airlines Staff if you are asked to do so. You must keep any material we give you at check-in (such as a boarding pass and a baggage check document) until completion of your travel with us. 9.2 Check-in deadline You will be advised of the check-in deadline for your flights in your Itinerary and/or by the Authorised Agent. As a general rule, check-in commences three hours prior to departure and closes one hour before the scheduled departure time of the aircraft. As flight times may change and different airports may have different check-in deadlines, you must make sure you are aware of the check-in deadline for all of the flights shown on your Ticket. 9.3 Arriving at the boarding gate You must complete all departure formalities (such as clearing Immigration) in time to arrive at the boarding gate no later than the time specified to you at check-in and displayed on the flight information displays at the airport. 9.4 If you are late or you do not comply with check-in rules Subject to the Australian Consumer Law, if you are late or do not comply with the check-in rules described in this Paragraph 9 through no fault of ours, Naoero Airlines reserves the right to cancel your Reservation, refuse to carry you and/or to charge you a reasonable service fee to re-book your flight. 9.5 Presentation of travel documents If we ask you to do so, you must present travel documents for the destinations and any Stopovers listed on your Ticket and permit us to make copies of them for security and procedural requirements and/or as required by any applicable Laws or we may refuse to carry you or your baggage in accordance with subparagraph 10.2.',
            ],
            [
                'heading' => 'Section 10. Refusal Of Carriage',
                'content' => '10.1 General We are not a common carrier and Naoero Airlines strives to ensure an enjoyable, safe and secure travel experience for all of its Passengers. We may take all steps Naoero Airlines believes necessary to ensure the safety and security of our Passengers including refusing carriage to you and other Passengers in some circumstances. In addition, Naoero Airlines may refuse carriage due to the practice of overbooking, see Subparagraph 10.4. 10.2 Refusal of carriage due to conduct Naoero Airlines may refuse to carry you or your Baggage if we in our absolute discretion determine that: refusal of carriage is necessary to ensure the safety of you or others; carrying you on the flight may materially affect the comfort of any person on the flight; refusal of carriage is necessary to comply with any applicable Laws (including any laws of countries you are being flown from, to or over); you have failed to comply with our check-in requirements, including minimum check-in times; you failed to arrive at the boarding gate by the time listed on your boarding pass or shown on the flight information screens at the airport; you require special assistance (including in relation to unaccompanied minors) but have not contacted us to make prior arrangements, have provided us with inaccurate or incomplete information in relation to your requirements or have otherwise failed to comply with these Conditions and any applicable policies and requirements (Naoero Airlines will exercise this right only to the extent permitted by any applicable Laws); you require but have not provided a satisfactory medical certificate in accordance with Naoero Airlines\' Medical Clearance Guidelines; you have refused to submit to a security check for you and/or your Baggage; you have failed to comply with our reasonable directions (including the directions of Naoero Airlines Crew and Naoero Airlines Staff or Agents); you have failed to comply with any applicable Laws, rules, regulations, or these Conditions; you have not paid the Fare and/or any applicable taxes or charges, your Ticket has been reported lost or stolen, has been dealt with by you in a way which contravenes these Conditions (e.g. transferred) or has otherwise been acquired unlawfully; you do not have, or do not appear to have, valid travel documents or health documents for your entire journey (including, if requested by Naoero Airlines, valid evidence of COVID-19 vaccination status and/or negative COVID-19 test results) for your country of origin or destination or you destroy your travel documents enroute, or you refuse to give, show or allow us to copy your travel documents; you cannot prove you are the person listed on your Ticket; you are (or appear to be) drunk or otherwise under the influence of drugs or alcohol; you are (or are suspected to be) unlawfully in possession of drugs or illicit drugs; your mental or physical state is threatening or a danger to Naoero Airlines\' staff or passengers; you have used threatening, abusive or insulting words or actions towards Naoero Airlines Staff, other Passengers or other persons at the airport; you have committed any offence in relation to your carriage including an offence during the check-in process or onboard the aircraft; you have tampered in any way with the aircraft or ground equipment, made a threat, or in any way put the safety of the aircraft in danger; you have failed to comply with these Conditions (including that you have done any of the things listed in Subparagraph 12.3); and/or you have done any of the things listed in Subparagraphs 10.2 (8) to (20) (inclusive) on a previous flight (whether that flight was operated by Naoero Airlines or another carrier) and Naoero Airlines has reason to believe that you may do the same thing again. 10.3 If you are refused carriage due to your conduct If you are refused carriage as a result of one or more of the matters referred to in Subparagraph 10.2, we will advise you with a notice refusing carriage. The advice may specify that you are refused carriage only for your ticketed flight, or it may specify a period of time during which we will not carry you. You must not travel or attempt to travel with us during this specified period. We reserve the right to cancel any Ticket you attempt to use to travel on Naoero Airlines when the notice is in force and subject to your rights under applicable Laws including the Australian Consumer Law, refuse to provide you with a refund. 10.4 Refusal of carriage due to overbooking Like most airlines, Naoero Airlines on occasion overbooks flights in order to account for passengers who do not arrive for their ticketed flight. If you are refused carriage because your flight has been overbooked and you hold a valid Ticket and have complied with these Conditions, then we will offer you a seat on the next available Naoero Airlines flight. If this is not acceptable to you, you may be entitled to compensation in accordance with any applicable Laws including the Australian Consumer Law.',
            ],
            [
                'heading' => 'Section 11. Schedules And Disruptions',
                'content' => '11.1 General principles Naoero Airlines will try to ensure that you and your Baggage depart and arrive as closely as possible to our scheduled departure and arrival times, as in force on your date of travel. However, we do not guarantee that we will be able to carry you and your Baggage in accordance with the flight times or schedules and they do not form part of your contract with us. Schedules may change without notice for a range of reasons including but not limited to bad weather, air traffic control delays, strikes, technical disruptions and late inbound aircraft. 11.2 Schedules and changes When you make a Reservation, Naoero Airlines or an Authorised Agent will tell you the scheduled time of your flights. We may need to change the scheduled departure time of your flight. If we change the time of your flight, we will make reasonable attempts to contact you or your Authorised Agent, using the contact details you have provided us. We do not guarantee that we will be able to contact you, and so you should check prior to your flight to make sure your flight times have not changed. 11.3 Liability for schedule changes Except as set out in Subparagraphs 11.5 and 11.6, and unless any applicable Laws or Conventions provide otherwise, Naoero Airlines will not be liable to you for any losses that you incur as a result of schedule changes. Unless provided for in these Conditions of Carriage, a Convention or any applicable law, we will not be responsible for paying any costs or expenses you may incur as a result of the changed time or cancellation. 11.4 Disrupted flights Flights may be delayed or cancelled or may originate from and be diverted to airports other than those scheduled, due to various factors. What Naoero Airlines will do for you if your flight is disrupted will depend on whether or not the disruption is due to Events Beyond our Control (see Subparagraphs 11.5 and 11.6). 11.5 Disruptions NOT due to Events Beyond our Control Naoero Airlines will use reasonable endeavours to ensure that you arrive at your scheduled Destination or Stopover as soon as possible. Subject to any remedies under applicable Laws such as the Australian Consumer Law or the appropriate Conventions, if your flight (on Naoero Airlines) is cancelled, fails to stop at your scheduled Destination or Stopover, or causes you to miss a connecting flight which is on your Ticket and on which your Reservation is confirmed, we will: carry you on our next scheduled service on which space is available; or if the relief in Subparagraph 11.5.1 is not available within a reasonable period of time (having regard to Naoero Airlines\' published schedule), re-route you to the scheduled Destination or Stopover shown on your Ticket, using the services of other carriers (or a combination of Naoero Airlines and other carrier services) or by other means of other transportation; or if neither the relief in Subparagraph 11.5.1 or 11.5.2 is available, or if Naoero Airlines in its absolute discretion decides that Subparagraph 11.5.1 or 11.5.2 are unsuitable in the circumstances, we will provide a refund to you in accordance with Paragraph 7. The remedies described in this Subparagraph 11.5 are your only remedies in relation to the matters described in Paragraph 11 and Naoero Airlines will have no further liability to you unless any applicable Laws or Conventions expressly specify otherwise. 11.6 Disruptions due to Events Beyond our Control Where we have had to make a change to your flight due to Events beyond our Control, we will: use reasonable endeavours to rebook you on the next available flight on our services at no additional cost to you; or if we are unable to rebook you on services acceptable to you, provide you with a refund or flight credit where the purpose of your trip is void, subject to the Fare Rules for your flight. You may also have rights or remedies under the Australian Consumer Law. We will not be responsible for paying any costs or expenses you may incur as a result of the delay or cancellation caused by the Events Beyond our Control, unless otherwise required by applicable Conventions, Tariffs or Laws.',
            ],
            [
                'heading' => 'Section 12. During Your Flight',
                'content' => '12.1 Safety is paramount Naoero Airlines\' first priority is the safety of its Passengers. Naoero Airlines will take all reasonable steps to ensure its Passengers\' safety at all times. You have an important role to play in the safety of your flight, both in relation to the things you should do, and the things you should never do. This Paragraph 12 explains these obligations and the steps Naoero Airlines may take to ensure safety. 12.2 Your obligations Whenever you travel with Naoero Airlines, you must: 1. Respect Naoero Airlines Crew and obey their directions Naoero Airlines Crew have been extensively trained to ensure you have a safe and comfortable flight. At all times you must listen carefully to and promptly obey any directions given to you by Naoero Airlines Crew. This includes any direction to provide your travel documents to Naoero Airlines Crew. 2. Safety briefing Listen carefully to the pre-flight safety briefing, read the safety card provided to you, identify your nearest exits and familiarise yourself with the location and operation of safety equipment. 3. Seatbelt and infant restraints Wear your seatbelt whenever you are seated – even when you are sleeping. Make sure you know how to operate infant and child restraints and use them only as directed by Naoero Airlines Crew. 4. Remain seated Remain in your seat whenever the \'Fasten Seatbelt\' sign is illuminated and return to your seat if Naoero Airlines Crew tell you to do so. 5. Stow Carry-on Baggage Stow your Carry-on Baggage as directed by Naoero Airlines Crew. 6. Use electronic devices only as directed Use electronic devices (including but not limited to personal music players, laptop computers and game devices), only when Naoero Airlines Crew tell you it is safe to do so. Do not use transmitting devices (including but not limited to radios and remote-controlled toys), while onboard. You are required to switch off your mobile phone or switch it to \'flight mode\' while onboard the aircraft. If you do not comply with these directions Naoero Airlines crew may take your electronic device from you and keep it until the end of the flight. 7. Behave responsibly and respect fellow passengers You will be sharing your flight with others, so please consider and respect their privacy, peace and personal space. Please make sure you behave in a way which does not disturb or cause offence to your fellow passengers. 8. Consume alcohol responsibly Naoero Airlines is serious about the responsible service of alcohol. We ask that you drink in moderation and ensure that you drink plenty of water to keep hydrated. In order to help us serve alcohol responsibly you must only consume alcohol which we supply to you on the flight and no alcohol that you bring onboard with you. We may refuse to serve you alcohol where we consider you to be impaired by the effects of alcohol. 12.3 What you must never do Whenever you travel with Naoero Airlines, you must never: 1. Smoke or use Electronic Cigarettes and Vapes All Naoero Airlines services are non-smoking, and most laws prohibit smoking onboard aircraft or in and around airport terminals. Electronic cigarettes, vapes and all other smoking devices are not allowed to be used or charged at any time. 2. Behave in offensive manner You must not behave in a manner which would be considered by a reasonable person to be offensive, or in a manner which might cause discomfort, distress, offence or injury to another person. 3. Disobey directions Naoero Airlines Crew will at times ask you to do things (such as returning to your seat when the aircraft encounters turbulence) which are important for your safety and the safety of others. You must never disobey these directions. 4. Use illicit drugs You must not use illicit drugs onboard Naoero Airlines aircraft or use prescription drugs which you are not medically required to take. 5. Endanger safety of aircraft, Crew or others You must never do anything which may endanger the safety of the aircraft on which you are travelling, the safety of Naoero Airlines Crew and/or your fellow passengers. 6. Tamper with or damage aircraft or onboard equipment You must never tamper, interfere with or damage any part of the aircraft or the equipment carried onboard. 7. What Naoero Airlines may do to ensure safety Naoero Airlines reserves the right to take all steps which are reasonably necessary to ensure the safety of a flight and Naoero Airlines Crew and Passengers. If in the opinion of our Crew you have not complied with your obligations in Paragraph 12 and in particular, if you have done any of the things listed in Subparagraph 12.3 we may take steps which may include one or more of the following: move you to another seat, or another part of the aircraft; restrain you; take any such other action as is reasonably necessary to ensure the safety of the flight, its Passengers and Naoero Airlines Crew, including the reasonable use of force; remove you from the flight; divert the flight and offload you from it at your cost; report you to the relevant authorities; refuse carriage for the remaining journeys on your Ticket; and/or serve you with a notice of refusal of carriage, see Subparagraph 10.3. If we divert the aircraft to an unscheduled destination as a result of your failure to comply with these Conditions (including your conduct as described in Subparagraph 12.3) you must pay us the reasonable costs of the diversion.',
            ],
            [
                'heading' => 'Section 13. After Your Flight',
                'content' => '13.1 Baggage collection Make sure you collect your Checked Baggage from the collection point advised to you by Naoero Airlines Staff when you arrive at your Destination. 13.2 Lost Baggage If you cannot find your Baggage please notify Naoero Airlines Staff and provide them with your Ticket and any baggage identification tag which was issued to you at check-in. 13.3 Wrong bag If you collect the wrong bag, you must return it to the airport where you collected it as soon as you discover the error. 13.4 If you do not collect your baggage If you do not collect your Baggage within 30 days after your flight, Naoero Airlines will, acting reasonably, attempt to contact you with the details provided at the time of your Reservation. If we are unable to reach you, or you fail to collect the Baggage within the agreed period, we may destroy it without further notifying you and without paying you any compensation, unless required by applicable Laws. 13.5 Leaving bags behind Naoero Airlines is not responsible or liable to you for any loss you suffer as a result of leaving behind any Checked Baggage or Carry-on Baggage onboard or at the airport.',
            ],
            [
                'heading' => 'Section 14. Successive Carriers',
                'content' => 'If your Ticket is for flights operated by Naoero Airlines and other carriers, the carriage may be regarded as a single operation under any applicable Laws or Conventions.',
            ],
            [
                'heading' => 'Section 15. Arrival Formalities And Administrative Requirements',
                'content' => '15.1 Visas and entry documents You are responsible for ensuring that you obtain and present at your Destination and any Stopover all required travel documents (see Paragraph 3). Naoero Airlines is not responsible or liable to you if you have failed to obtain or lost these documents. 15.2 Entry rules and requirements You must also comply with all entry requirements, rules and regulations of your Destination or any Stopover. This includes any immigration, customs and security requirements. Naoero Airlines is not responsible or liable to you if you do not comply with these requirements, rules and regulations. 15.3 If you are refused entry or deported If you are refused permission to enter a country during your journey with Naoero Airlines or are deported, then regardless of the reason for the refusal of entry or deportation: we will, if required by a governmental authority, transport you from the country that has refused you entry or deported you; you must pay for any costs associated with the refusal of entry or deportation, including any fines and detention and repatriation costs; we will not provide you with a refund for your Ticket where you were denied entry and may offset any unused part of your Ticket to offset any repatriation costs or fines incurred by Naoero Airlines as a result of your refusal of entry or deportation; and you must reimburse us for any fines, penalties, losses, costs, expenses or damage which Naoero Airlines incurs as a result of you being denied entry or deported.',
            ],
            [
                'heading' => 'Section 16. Baggage',
                'content' => '16.1 Baggage allowance Naoero Airlines will provide you with a free Baggage allowance as part of your Ticket. Your actual allowance will depend on your cabin class of travel and the applicable Fare Rules. This allowance relates both to Checked Baggage and Carry-on Baggage. The basic free Baggage allowances are set out in Naoero Airlines\' Conditions of Contract and Other Important Notices and on our website. We may change the Baggage allowances from time-to-time. However, any change will not impact existing bookings and will only apply to future bookings made after the effective date of the change. 16.2 Excess Baggage and oversized Baggage If you wish to take Baggage with you which is bigger or heavier than your free Baggage allowance, it will be considered excess and/or oversized Baggage and you will be charged extra for it. In addition, if your Baggage exceeds certain size and weight limitations, it must be sent as freight, or we may refuse to carry it. For more information visit Naoero Airlines\' Website www.naoeroairlines.com.au or contact Naoero Airlines\' Reservations. 16.3 Carrying your Baggage and right to refuse 1. Carriage on same flight While Naoero Airlines will use its best endeavours to carry your baggage on the same flight as you, we may for operational reasons (such as aircraft take-off weight restrictions) carry your baggage on the next available flight. 2. Right to refuse carriage Naoero Airlines may in its absolute discretion refuse to carry your Baggage (or refuse to continue to carry your Baggage if a problem is detected after you first check in): if it does not meet the requirements in this Paragraph 16 or the requirements set out in these Conditions or the Baggage Policy of Naoero Airlines; if it is not securely packed in suitable materials; if it may cause discomfort or inconvenience to other Passengers (for example, because of any odour it emits); or for safety or operational reasons. 16.4 Not acceptable as Baggage You must never include in your Baggage: items which may endanger the aircraft or persons, or property onboard the aircraft, such as those specified in the International Civil Aviation Organisation (ICAO) Technical Instructions for the Safe Transport of Dangerous Goods by Air, the IATA Dangerous Goods Regulations and in our regulations (further information is available from Naoero Airlines on request); items which are prohibited by any applicable Laws (including any laws of countries you are being flown from, to or over); items which Naoero Airlines in its absolute discretion refuses to take; and other items prohibited in Paragraph 16. 16.5 Right to search In addition to any searches required by any applicable Laws, for reasons of safety and security, we reserve the right to search your Baggage at any time to ensure that it meets Naoero Airlines\' baggage requirements including any requirements set out in the Policies. Naoero Airlines may refuse to carry you or your Baggage if you refuse to consent to a search. Your Checked Baggage may be searched by us, government or airport officers or their representatives in your absence if you are not available, for the purpose of determining whether you are in possession of, or whether your Baggage contains any item listed in Subparagraph 16.4. Except as otherwise provided by a Convention or other applicable Laws, if a search, screening or x-ray causes damage to you or your Baggage, we will not be liable for the damage unless it was caused by our fraud, negligence or wilful misconduct. 16.6 Rules for Checked Baggage You must comply with the following rules in relation to Checked Baggage: 1. Not acceptable as Checked Baggage Naoero Airlines does not accept the following items as Checked Baggage and to the extent permitted by applicable Laws, does not accept liability for their loss or damage unless the items are identified to us and we have, in our absolute discretion, given our written acceptance for carriage and accepted liability in writing for their loss or damage: cash, credit cards, deeds, passports and other travel documents, securities, business documents or other valuable documents; jewellery, antiques, precious metals or similar valuable items; any item of a delicate, fragile or brittle nature e.g. glassware, sports equipment, musical instruments; any item of a perishable nature e.g. seafood; medicines; buckets; polystyrene eskys and we will only accept IATA approved polystyrene boxes as cargo; mattresses of any kind and these must be presented at the Cargo terminal; TV\'s exceeding 43 inches; large oversize eskys measuring greater than 1,500 i.e. eskys measure more than of 1,500mm when adding height, width and length. These need to be presented as cargo; electrical or electronic components e.g. computers, cameras; and any item that has insufficient packaging to withstand the normal circumstances and effects of carriage by air. 2. Checked Baggage receipt You must keep any receipt which Naoero Airlines or a carrier of a Code Share Service gives you for your Checked Baggage. This is an important document which will help you to claim your Baggage at your destination and to claim any applicable compensation if your Baggage is lost or damaged. 3. Identification of Baggage You should ensure that all of your Checked Baggage is marked with your name and address or some other form of unique identification which will allow confirmation that the Baggage belongs to you. 4. Sporting goods There is no free sporting equipment baggage allowance and all sporting equipment is added to your baggage allowances. Contact Naoero Airlines Reservations for more information if you are planning to check in sporting goods. 16.7 Rules for Carry-on Baggage You must comply with the following rules in relation to Carry-on Baggage: 1. Dimension limits Naoero Airlines\' Conditions of Contract and Other Important Notices set out the allowances for Carry-on Baggage, including dimension and weight limits. If your Carry-on Baggage exceeds these limits, or if we decide that your Carry-on Baggage cannot be safely carried in the cabin of the aircraft, we may carry your Baggage as Checked Baggage, or may refuse to carry it at all if it is an item to which Subparagraph 16.4 applies. 2. Special items Naoero Airlines may accept larger items (such as musical instruments) which would normally not be suitable as Carry-on Baggage if you make prior arrangements with us. Naoero Airlines reserves the right to charge a reasonable additional fee for the carriage of the item as Carry-on Baggage. 16.8 Rules for carriage of animals Naoero Airlines may carry animals if you make prior arrangements with us in either the aircraft hold or the aircraft cabin depending on the type of animal. Naoero Airlines reserves the right to refuse to carry your animal and to set requirements in relation to crates, feeding, health and vaccinations. For more information please visit Naoero Airlines\' Website or Reservations. Naoero Airlines will carry recognised assistance animals in the cabin of the aircraft, in accordance with Naoero Airlines\' Policies. 16.9 Carriage of firearms We may, but are not obliged to, agree to carry firearms and ammunition for hunting or sporting purposes as Checked Baggage. If we do, these must be packed in accordance with all applicable national and international laws and regulations. Our approval may be withheld at our sole discretion. Applications for the carriage of firearms and ammunition for hunting or sporting purposes must be submitted at least 7 working days prior to your anticipated departure date.',
            ],
            [
                'heading' => 'Section 17. Liability For Damage',
                'content' => '17.1 Determination of liability The liability of Naoero Airlines and each carrier involved in your journey will be determined by these Conditions, any applicable Laws and Conventions and each carrier\'s conditions of carriage. Nothing in these Conditions limits or excludes our liability to you that we cannot limit or exclude under applicable Laws (including the Australian Consumer Law) or the Conventions. The following is a brief outline but you should obtain professional advice in the event that you believe that you may have a claim as a result of any injury, loss or damage. The limits of liability fixed by law are subject to periodic review and variation and you may be entitled to any higher limit applicable at the time of carriage. 17.2 Liability rules Unless this Paragraph 17 says otherwise, international carriage, as defined in any applicable Conventions, will be governed by the liability rules of those applicable Conventions. Where a Convention does not apply, these Conditions will govern Naoero Airlines\' liability. If your flight has a destination or stop in a country other than the one from which you depart the Montreal Convention (as amended) or its predecessor, the Warsaw Convention (as amended), may apply to your flight. These Conventions may govern the flight and, in most cases: impose liability and create rights of action in defined circumstances during international carriage by air; limit the liability of airlines in the case of your death or injury, loss of or damage to Baggage, cargo and delay; impose conditions in relation to notice of damage and the time to bring legal proceedings; and specify where proceedings may be brought and may exclude claims under other laws. 17.3 Death or Injury of Passengers In the event of death or other bodily injury suffered by a Passenger as a result of an accident covered by any applicable Conventions: 1. If the Montreal Convention (as amended) applies, Naoero Airlines: will be liable for any damages up to 128,821 SDRs (about A$264,000) in respect of death or bodily injury caused by an accident onboard an aircraft or during embarking or disembarking from an aircraft. will not be liable for damages to the extent that it exceeds 128,821 SDRs (about A$264,000) for each passenger if: (i) such damage was not due to the negligence or other wrongful act or omission of Naoero Airlines or its servants and agents; or (ii) such damage was solely due to the negligence or other wrongful act or omission of a third party. damages for death or bodily injury may be reduced or even exonerated if Naoero Airlines proves that the damage was caused or contributed to by the negligence or other wrongful act or omission of the person claiming compensation. 2. If the Warsaw Convention (as amended) applies similar provisions to those relating to the Montreal Convention will prevail but where there are financial limits of liability those limits will be different and you should obtain advice on the level of liability that may apply at the time of loss or damage. However, any liability we may have for damage will be reduced in accordance with applicable laws by any negligence on your part that causes or contributes to the damage. 3. Naoero Airlines reserves all other defences and limitations available under any applicable Conventions to such claims including, but not limited to, the exoneration defence of Article 21 of the Warsaw Convention (as amended) and Article 20 of the Montreal Convention (as amended) except that Naoero Airlines shall not invoke Articles 20 and 22(1) of the Warsaw Convention (as amended) and Articles 19 and 22 of the Montreal Convention (as amended) in a manner inconsistent with these Conditions. 4. Where your flight is wholly within Australia and is not International Travel it is subject to the provisions of the Civil Aviation (Carriers\' Liability) Act 1959 (as amended) or complementary State legislation and our liability for your injury or death resulting from an accident is limited to A$925,000. 5. Naoero Airlines reserves all rights of recourse against any third parties, including without limitation, rights of contribution and indemnity. 6. Notwithstanding this Subparagraph 17.3 and the above Subparagraphs, any claims made by public social insurance or similar bodies (except with respect to any such bodies of the United States) shall be subject to the liability limit and defenses under any applicable Conventions. 7. Notwithstanding this Subparagraph 17.3 and the above Subparagraphs, in respect of travel to and from the United States, the following shall apply: Naoero Airlines shall avail itself of the limitation provided in the Convention for the Unification of Certain Rules Relating to International Carriage by Air signed at Warsaw 12 October 1929, or provided in the said Convention as amended by the Protocol signed at The Hague 28 September 1955. However, in accordance with Article 22(1) of said Convention, or said Convention as amended by said Protocol, Naoero Airlines agrees that, as to all international transportation by Naoero Airlines as defined in the said Convention or said Convention as amended by said Protocol, which, according to the contract of Carriage, includes a point in the United States of America as a point of origin, point of destination, or agreed stopping place: (1) the limit of liability for each passenger for death, wounding, or other bodily injury shall be the sum of US $75,000 inclusive of legal fees and costs, except that, in case of a claim brought in a State where provision is made for separate award of legal fees and costs, the limit shall be the sum of US $58,000 exclusive of legal fees and costs; and (2) Naoero Airlines shall not, with respect to any claim arising out of the death, wounding, or other bodily injury of a passenger, avail itself of any defense under Article 20(1) of said Convention or said Convention as amended by said Protocol. Nothing herein shall be deemed to affect the rights and liabilities of Naoero Airlines with regard to any claim brought by, on behalf of, or in respect of any person who has willfully caused damage which resulted in death, wounding, or other bodily injury of a passenger. 17.4 Physical condition To the extent permitted by Laws, Naoero Airlines is not responsible for any pre-existing illness, injury or disability, including death, attributable to your physical condition or the aggravation of such condition. 17.5 Baggage 1. Naoero Airlines will be liable only for destruction, loss, damage or delay occurring during carriage ticketed on Naoero Airlines\' Airline Designator Code. If we issue a ticket or check baggage on the flight of another carrier, we only do so as agent for that carrier as outlined in Subparagraph 2.5. With respect to Checked Baggage you may also have a right of action against the first or last carrier. 2. Naoero Airlines is not responsible for small or non-significant damage to checked baggage. This includes: scratches, dents and minor cuts; damage to or loss of straps, hooks or belts; broken wheels or feet; zips or locks; damage to retractable or fixed baggage handles; damage resulting from over packing; and/or for US flights only, Naoero Airlines is not responsible for fair wear and tear including scratches, dents, minor cuts or damage caused from over packing. 3. Naoero Airlines is not liable for any damage to your Carry-on Baggage unless the damage was caused by the fault of Naoero Airlines. 4. You are responsible for any damage caused by your Baggage to other persons or property, including Naoero Airlines\' property; and you agree to indemnify Naoero Airlines for any such damage unless such damage is caused by our fraud, negligence or wilful misconduct. 5. Except to the extent required by any applicable Laws, Naoero Airlines is not liable for damage or delay to items which you are asked not to include in your Checked Baggage as per Subparagraph 16.6.1. 6. Where your travel is international carriage as defined in any applicable Conventions and those Conventions apply, Naoero Airlines\' liability for loss of, damage to, or delay in the carriage of your Checked Baggage is limited by the applicable Conventions except where you prove that the damage resulted from an act or failure to act either done with the intention to cause damage or recklessly and with knowledge that damage would probably result. Subject to the foregoing exception, Naoero Airlines\' liability for loss of, damage to, or delay in the carriage of Baggage is limited by any applicable Conventions as follows: If the Montreal Convention (as amended) applies, our liability is limited to 1,288 SDRs (about A$2,640). (i) in the case of Checked Baggage, Naoero Airlines will not be liable to the extent the damage resulted from an inherent defect, quality or vice of the Checked Baggage; (ii) in the case of Carry-on Baggage, Naoero Airlines will only be liable if Naoero Airlines was at fault. If the Warsaw Convention (as amended) applies liability for loss, delay or damage is limited to 250 francs per kilogram for Checked Baggage or 5,000 francs for Carry-on Baggage (unless Article 25 of the Warsaw Convention applies, in which case these limits do not apply). The limitations referred in Subparagraphs 17.5.5(a) and (b) do not apply if the Passenger declared a higher value in advance and paid additional charges. Only in that instance shall Naoero Airlines\' liability be extended to the higher declared value. 7. If there has been contributory negligence on your part with respect to any destruction, loss, damage or delay of Baggage, our liability may be eliminated or reduced in accordance with applicable Laws. 8. If you hold a Baggage receipt and receive your Checked Baggage without making a complaint, it will be reasonable evidence that the Checked Baggage was delivered in good condition, unless proven otherwise. In the case of damage, the person entitled to delivery must advise the carrier immediately after the discovery of the damage, and, at the latest, within seven days from the date of receipt in the case of Checked Baggage and fourteen days from the date of receipt in the case of cargo. In the case of delay, the complaint must be made at the latest within twenty-one days from the date on which the Checked Baggage or cargo have been placed at his or her disposal. 9. In case of carriage not subject to any applicable Conventions, Naoero Airlines\' liability for Checked and Carry-on Baggage shall be limited pursuant to the Civil Aviation (Carriers\' Liability) Act 1959 (Cth) or complementary Australian State legislation and our liability for loss or damage is limited to A$3,000 per Passenger for Checked Baggage and A$300 per Passenger for Carry-on Baggage. 17.6 Passenger Delay 1. Subject to applicable Laws, Naoero Airlines will not be liable for loss or damage occasioned by delay in the carriage of Passengers by air if it and its servants took reasonable measures that could reasonably be required to avoid the damage, or that it was impossible for them to take such measures. 2. Airport, air traffic, control, security and other facilities or personnel, whether public or private, who are not under the control and direction of Naoero Airlines are not agents or servants of Naoero Airlines and Naoero Airlines is not liable to the extent that the delay is caused by these types of facilities or personnel. 3. In the case of delay to your flight: where the Montreal Convention (as amended) applies, we will be liable for damage except when we took all measures that could reasonably be required to avoid the damage or it was impossible for us to take such measures. Our liability under the Montreal Convention (as amended) is limited to 5,346 SDRs (about $11,000); and where the Warsaw Convention (as amended) applies, we will be liable for damage except when we can prove that we took all necessary measures to avoid the damage or that it was impossible for us to take such measures. 17.7 Total liability limit Subject to applicable Laws, in no case will Naoero Airlines\' liability exceed the actual amount of damages suffered by the Passenger. All claims shall be limited to proven direct compensatory damages. Naoero Airlines will not in any circumstances be liable for exemplary, aggravated, consequential, indirect or special loss or damages. 17.8 Reduction for contribution to damage Any liability of Naoero Airlines for damage may be reduced by any negligence on your part which causes or contributes to the damage in accordance with applicable Laws. 17.9 Damage arising by law Naoero Airlines will not be liable for any damage directly or indirectly arising out of its compliance with any applicable Laws, failure of a Passenger to comply with the same, or any Events Beyond our Control. 17.10 Application to others These Conditions including the documents referred to in these Conditions (such as the Conditions of Contract and Other Important Notices) apply to our Authorised Agents, Naoero Airlines Staff and representatives to the same extent as they apply to Naoero Airlines. The total amount recoverable from us and from such Authorised Agents, Naoero Airlines Staff and representatives shall not exceed the amount of Naoero Airlines liability, if any. 17.11 No waiver Except as otherwise expressly provided in these Conditions, nothing contained in these Conditions shall waive any defence or exclusions or limitation of liability under any applicable Conventions or Laws. With respect to third parties, we reserve all rights of recourse against any other person, including without limitation, rights of contribution and indemnity.',
            ],
            [
                'heading' => 'Section 18. Time Limitation On Claims And Actions',
                'content' => '18.1 Notice of Claims In the case of loss or damage to Checked Baggage, you must notify us in writing within 7 days and in the case of delay, within 21 days of delivery. In respect of loss of or damage to Carry-on Baggage you must notify the Airline in writing within 7 days from the date the carriage ended. 18.2 Limitation of Actions Unless otherwise required by law or your right to claim for Damages has expired earlier as provided elsewhere in these Conditions of Carriage, you will have no right to claim for Damages if court proceedings are not brought within two (2) years from: the date of arrival at the destination; the date on which the aircraft ought to have arrived; or the date on which the carriage stopped. The method of calculating the period of limitation shall be determined by the law of the court seized of the case.',
            ],
            [
                'heading' => 'Section 19. Terms Used In These Conditions',
                'content' => 'The singular includes the plural and vice versa. The titles of Paragraphs and the marginal headings are inserted for convenient reference only and are not to be used in ascertaining the meaning of any of the provisions of these Conditions. Terms Meaning Agreed Stopping Places Those places, except the place of departure and the place of destination, set forth in the Ticket or shown in Naoero Airlines\' timetables as scheduled stopping places on the passenger\'s route.", ], [ \'Airline Designator Code\', "The two letters or number code issued by IATA to each airline to identify that airline and its flights. For example, Naoero Airlines\' Designator Code is \'ON\'.", ], [ \'Australian Consumer Law\', \'Schedule 2 to the Australian Competition and Consumer Act 2010 (Cth).\', ], [ \'Authorised Agent\', \'A duly licensed passenger sales agent who is authorised by Naoero Airlines to represent Naoero Airlines and sell Naoero Airlines Services.\', ], [ \'Baggage\', \'The items and objects which you bring with you when you travel on Naoero Airlines and includes Checked Baggage and Carry-on Baggage.\', ], [ \'Baggage Allowance\', \'The allowance for your Baggage, as set out in the Fare Rules and administered in accordance with Paragraph 16 and the Policies.\', ], [ \'Carry-on Baggage\', \'Baggage which Naoero Airlines lets you take onboard the aircraft with you.\', ], [ \'Checked Baggage\', \'That part of your baggage including any excess baggage which we have taken into our custody and for which we have issued a baggage identification tag or baggage check or both.\', ], [ \'Code Share Services\', "A flight where the \'ON\' Airline Designator Code in the flight number is not the Airline Designator Code of the Carrier/Airline operating the aircraft.", ], [\'Conditions\', \'These Conditions of Carriage. Conditions of Contract and Other Important Notices The additional terms and conditions on which the contract for your carriage has been made and which are contained in or delivered with your Ticket, Itinerary, the booking page of the Naoero Airlines Website, or your boarding pass and which incorporate these Conditions of Carriage by reference.", ], [ \'Conventions\', "Whichever of the following instruments (or the following instruments as amended) that may be applicable to your travel with Naoero Airlines: the Montreal Convention (1999); the Convention for the Unification of Certain Rules Relating to International Carriage by Air, signed at Warsaw, 12 October 1929 (the Warsaw Convention); the Warsaw Convention as amended at The Hague on 28 September 1955; the Warsaw Convention as amended at The Hague and by Additional Protocol No. 1, 2 or 4 of Montreal (1975); the Guadalajara Supplementary Convention (1961); and any other applicable protocols or conventions and any enabling legislation.", ], [ \'Destination\', \'The last destination shown on your Itinerary. When not capitalised, destination refers to a generic point of arrival.\', ], [\'Domestic Travel\', \'Travel entirely within one country. Electronic Coupon An electronic flight coupon or other value document in electronic record form held in our database.\', ], [ \'Electronic Ticket\', \'A Ticket issued electronically (which may be comprised of Electronic Coupons) by Naoero Airlines or an Authorised Agent. Your electronic ticket may be combined with an Itinerary.\', ], [ \'Events Beyond our Control\', \'Events such as severe or inclement weather, strikes, security alerts or other events that may affect our ability to provide air transport as planned.\', ], [ \'Fare\', \'The applicable fare set by Naoero Airlines for travel contemplated in a Reservation (at the time of payment for the Reservation), along with applicable fees and surcharges, and any taxes imposed by governments on the travel to which the Reservation relates.\', ], [ \'Fare Rules\', "The rules set by Naoero Airlines in relation to its various fares, as set out in Naoero Airlines\' Website, to which you agree when you purchase a flight on Naoero Airlines\' Website, or as explained to you by an Authorised Agent.", ], [ \'Flight Coupon\', "That portion of the Ticket that bears the notation \'good for passage\', or in the case of an Electronic Ticket, the Electronic Coupon, and indicates the particular places between which you are entitled to be carried.", ], [\'IATA\', \'The International Air Transport Association. International Travel Travel from one country to another.\', ], [ \'Itinerary\', \'A document provided to you by Naoero Airlines or an Authorised Agent which details your flights with Naoero Airlines and the destination of these flights. Your Itinerary may be combined with an Electronic Ticket.\', ], [ \'Laws\', \'Any laws of Australia and any other nation which apply to your travel with Naoero Airlines.\', ], [ \'Naoero Airlines\', \'Naoero Air Corporation (trading as Naoero Airlines).\', ], [ \'Naoero Airlines Crew\', \'The flight crew (pilots) and cabin crew of flights operated by Naoero Airlines.\', ], [ \'Naoero Airlines Staff\', \'Employees, contractors and agents of Naoero Airlines, and includes Naoero Airlines Crew.\', ], [ \'Naoero Airlines Services\', \'The services described in Paragraph 2.1.\', ], [ \'Paper Ticket\', \'A valid ticket issued in physical form by Naoero Airlines or an Authorised Agent.\', ], [ \'Passenger\', \'A person who has purchased a Ticket and who travels with Naoero Airlines.\', ], [ \'Policies\', "Naoero Airlines\' policies in relation to various aspects of travel, including Baggage, Passengers requiring special assistance and Passengers requiring medical clearance to travel. Naoero Airlines\' Policies are available on Naoero Airlines\' Website or by contacting Naoero Airlines\' Passenger Contact Centre.", ], [ \'Reservation\', \'A booking on a Naoero Airlines flight and is confirmed only when recorded and paid for and accepted by Naoero Airlines.\', ], [ \'SDR\', \'A Special Drawing Right, a composite unit of currency that is the official unit of exchange of the International Monetary Fund.\', ], [ \'Stopover\', \'A deliberate interruption of a journey by the passenger, at a point between the place of departure and the place of destination, which has been agreed to in advance by Naoero Airlines.\', ], [ \'Tariff\', \'The published Fares, charges and/or related conditions of carriage of an airline.\', ], [ \'Ticket\', \'A document which validly entitles your travel on Naoero Airlines Services and includes an Electronic Ticket and a Paper Ticket.\', ], [ \'Ticketing Time Limit\', \'The time limit set by Naoero Airlines within which you must pay the Fare for a Reservation.\', ], [\'us, we, our\', \'Naoero Airlines.',
            ],
        ];

        $carriageHtml = '<p>These Conditions of Carriage (\'the Conditions\') together with the documents referred to in Subparagraph 2.2 form a contract between you and Naoero Airlines in relation to your travel on Naoero Airlines Services.</p>

<p><a href="https://www.nauruair.com/library/files/nauruairlines-conditionsofcarriage-01nov24-documentnumberna-exo-py-001.pdf" target="_blank">Click here to download the official Conditions of Carriage (PDF)</a></p>

<h2>These Conditions Of Carriage Contain Important Terms Of Which You Should Be Aware</h2>

<h3>Privacy</h3>

<p>Clause 5.9 says that we need to collect your personal information so that we can provide services to you. It also says we will collect and handle your personal information in accordance with our <a href="/privacy-policy" target="_blank">Privacy Policy</a>.</p>

<h3>Check-In Rules</h3>

<p>Clause 9.4 provides that you may have your Reservation cancelled or re-allocated, be refused carriage or charged a reasonable service fee with no liability to Naoero Airlines if you do not comply with our check-in rules.</p>

<h3>Refusal Of Carriage</h3>

<p>Clause 10.2 sets out the circumstances in which we can refuse you carriage on our flights and limitations on our liability to you when this occurs.</p>

<h3>Schedule Changes & Flight Cancellation</h3>

<p>Clause 11.3 sets out limitations on our liability to you in the event of schedule changes and/or flight cancellations.</p>

<h3>Refusal Of Entry At Destination</h3>

<p>Clause 15.3 sets out limitations on our liability to you in the event you are refused entry into your destination port.</p>

<h3>Liability For Death, Personal Injury Or Baggage</h3>

<ul><li>Clause 16.6.1 and 17.5.4 sets out limitations on our liability to you in the event of loss or damage of important or valuable items taken as Checked Baggage or Carry-on Baggage.</li><li>Clause 16.3.2 sets out our right to refuse carriage of your Baggage, and clause 13.4 sets the limitations on our liability to you in the event that you do not collect your Baggage.</li><li>Clause 17 sets out limitations on our liability to you in the event you suffer illness, injury or death on our flights or in the event we delay, damage or lose your Baggage.</li><li>Clause 17.5.8 sets out the timeframes you need to comply with when making a claim for lost or damaged Baggage.</li></ul>

<h2>Section 1. What These Conditions Do</h2>
<h3>1.1</h3>
            <p>These Conditions deal with very important issues, including:</p>
            <ol>
<li>How and when these Conditions apply.</li>
<li>Fares and tariffs.</li>
<li>Passengers who need special assistance.</li>
<li>Important information for your health and safety.</li>
<li>Refusal of carriage.</li>
<li>Schedules and disruptions.</li>
<li>Our liability to you (including limits of liability).</li>
</ol>
          
          <h3>1.2</h3>
            <p>
              Please read these Conditions carefully and visit
              www.naoeroairlines.com.au or contact the Naoero Airlines
              Reservations (1300 369 044 (then # 2) within Australia,
              5577000 within Naoero or +61 7 3229 6455 if calling from
              outside Australia) if you have any questions.
            </p>

<h2>Section 2. How And When These Conditions Apply</h2>
<h3>2.1 General</h3>
            <p>
              These Conditions apply whenever you travel on a Naoero
              Airlines flight or flight segment, the \'ON\' Airline
              Designator Code is shown as the carrier on your Ticket or in
              any case where we have a legal liability to you in relation
              to your flight.
            </p>
          
          <h3>2.2 Basis of carriage</h3>
            <p>
              Your travel with Naoero Airlines is, without exception,
              subject to:
            </p>
            <ol>
<li>the Conditions of Contract and Other Important Notices;</li>
<li>these Conditions;</li>
<li>any applicable Conventions;</li>
<li>any applicable Laws;</li>
<li>any applicable Tariffs filed by us with regulatory bodies;</li>
<li>the Fare Rules;</li>
<li>the Policies; and</li>
<li>any written or oral directions given to you by Naoero Airlines Staff and/or Authorised Agents.</li>
</ol>
          
          <h3>2.3 Interaction with Laws, Tariffs, Conventions</h3>
            <p>1. These Conditions:</p>
            <ol style="list-style-type: lower-alpha;">
<li>apply to the extent permitted by any applicable Laws, and if any provision of these Conditions is void, illegal, invalid or unenforceable in any jurisdiction, these Conditions will be read down to the extent necessary to ensure it is not void, illegal, invalid or unenforceable in that jurisdiction; and</li>
<li>prevail unless inconsistent with any applicable Laws, Conventions or Tariffs, in which case the Laws, Conventions or Tariffs will apply to the extent of the inconsistency.</li>
</ol>
            <p>
              2. In respect of any goods or services we may provide,
              certain statutory guarantees or warranties may apply for the
              benefit of consumers. The nature and application of these
              guarantees or warranties will depend on the relevant
              jurisdiction.
            </p>
            <p>
              3. In Australia, you have certain rights under the
              Australian Consumer Law. The Australian Consumer Law
              provides consumer guarantees that services must be:
            </p>
            <ol style="list-style-type: lower-alpha;">
<li>fit for a particular purpose;</li>
<li>rendered with due care and skill; and</li>
<li>supplied within a reasonable time.</li>
</ol>
            <p>
              Where the Australian Consumer Law applies and we fail to
              provide services to you in accordance with these consumer
              guarantees, then you may have a right to seek a remedy from
              us in accordance with the Australian Consumer Law.
            </p>
            <p>
              4. Nothing in these Conditions excludes or restricts the
              application of relevant Laws, including the Australian
              Consumer Law, Civil Aviation (Carrier\'s Liability) Act 1959
              (Cth) or any Conventions.
            </p>
            <p>
              In respect of goods or services acquired for business
              purposes and not for personal, domestic or household use, to
              the extent permitted by the Australian Consumer Law our
              liability for a breach of the guarantee or warranty will be
              limited to either supplying the goods or services again or
              paying the cost of having them supplied again.
            </p>
            <p>
              5. Where the carriage of cargo is for the purposes of a
              business, trade, profession or occupation carried on or
              engaged in by the person for whom the goods are transported,
              the guarantees relating to the supply of services in the
              Australian Consumer Law do not apply.
            </p>
          
          <h3>2.4 Variation and waiver</h3>
            <p>
              No Naoero Airlines Staff, Authorised Agent or other person is
              authorised to vary these Conditions. However, Naoero Airlines
              Staff and/or Authorised Agents may in certain circumstances
              waive Fare Rules or amounts payable. A waiver on one
              occasion does not constitute a waiver on any other occasion.
            </p>
          
          <h3>2.5 Bookings on behalf of another carrier and other transportation or services</h3>
            <p>
              At times you may also travel on services which we sell but
              which are operated by other carriers under those carriers\'
              flight numbers and Airline Designator Codes. If we issue a
              Ticket for you as agent of another carrier, or we issue a
              ticket for check baggage on the flight of another carrier, we
              do so only as agent for that carrier. The conditions of
              carriage of the other carrier will apply for flights sold on
              behalf of the other carrier. Please go to the operating
              carrier\'s website for further information about your rights
              in these circumstances.
            </p>
            <p>
              These Conditions do not apply to any non-air transportation
              or other services Naoero Airlines arranges for you (for
              example, bus transfers and hotel accommodation). Where
              Naoero Airlines arranges these services, it does so as your
              agent and your contract is with the provider of the
              relevant transportation or services. If you require further
              information about the terms of the contract, please contact
              the relevant provider.
            </p>
          
          <h3>2.6 Charter Operations</h3>
            <p>
              These Conditions apply to charter or hire operations where
              we are the operator of the flight unless you are advised
              otherwise in writing by or on behalf of the charterer prior
              to boarding the aircraft.
            </p>

<h2>Section 3. Getting Ready To Fly</h2>
<h3>3.1 Passports, visas and authorisations</h3>
            <p>
              It is your responsibility to comply with all laws,
              regulations and orders of your chosen destination. For more
              information, please ask an Authorised Agent or consult the
              government which issued your passport. Please consider the
              following:
            </p>
            <ol>
<li>make sure you have a valid passport with at least 6 months validity remaining before it expires. In addition, if your passport expires in 12 months or less, you may want to check whether this is sufficient for your chosen destination and time away;</li>
<li>check with the government which issued your passport and the consulates of your chosen destination to see whether you need a visa, other travel documents, or information such as vaccination records; and</li>
<li>find out about any dangers to your health and safety at your chosen destination.</li>
</ol>
            <p>
              The above list is given as a guide only. It remains your
              responsibility to ensure you have all required
              authorisations and documentation for your travel. Any
              assistance Naoero Airlines provides you in this area does
              not release you from your obligations.
            </p>
          
          <h3>3.2 Finding out about where you are going</h3>
            <p>
              It is a good idea to check relevant government websites to
              find out about any dangers and risks to your health and
              safety at your chosen destinations. You can do this by
              checking with government agencies like the Australian
              Department of Foreign Affairs and Trade
              (smartraveller.gov.au).
            </p>
          
          <h3>3.3 Your health</h3>
            <p>
              The health and safety of all of Passengers is paramount.
              That is why it is important for you to be aware of health
              and safety issues which are relevant to you and to tell us
              about any health issues which affect your safety or the
              health and safety of others. If you are in any doubt,
              please consult your doctor and talk to an Authorised Agent
              and/or the Naoero Airlines Reservations.
            </p>
            <h3>1. Risks inherent to being seated for extended periods of time</h3>
              <p>
                By its very nature, medium to long-haul air travel
                involves extended periods of sitting. This may be a risk
                factor for some people forming blood clots in limbs,
                known as Deep Vein Thrombosis (DVT). You should discuss
                with your doctor whether you are at risk of suffering DVT
                and what preventative measures you should take.
              </p>
            
            <h3>2. Medical Clearance</h3>
              <p>
                If you have a medical condition that falls within our
                Medical Services Policy, we may require you to provide a
                medical certificate signed by a qualified medical
                practitioner. If you do not provide a medical certificate
                satisfactory to us, we may refuse to carry you on our
                flights. The Medical Services Policy can be accessed by
                contacting the Naoero Airlines Reservations.
              </p>
            
            <h3>3. Public Health or Medical Emergency</h3>
              <p>
                If a public health emergency is announced by any
                government or the World Health Organisation or a medical
                emergency arises, we may be required to:
              </p>
              <ol style="list-style-type: lower-alpha;">
<li>cancel, divert or delay any flight;</li>
<li>screen passengers;</li>
<li>require valid evidence of vaccinations or inoculations (including against COVID-19) and/or negative test results (including against COVID-19);</li>
<li>require health documentation to be presented;</li>
<li>offload or refuse to carry passenger(s) and their Baggage if they do not provide valid health documentation (including evidence of COVID-19 vaccination);</li>
<li>isolate passenger(s) on board the aircraft and limit the inflight services;</li>
<li>notify appropriate authorities such as the police, health authorities or airport security; and</li>
<li>perform any other action we consider necessary to provide a safe environment for passengers, staff, contractors and passengers.</li>
</ol>
            
          
          <h3>3.4 Travel insurance</h3>
            <p>
              It can be expensive if something happens to you when you
              are overseas. Naoero Airlines strongly recommends that you
              purchase travel insurance for your journey, including cover
              for any losses you might incur if for any reason you are
              unable to travel with us on your planned travel date.
            </p>

<h2>Section 4. Code Share Services</h2>
<p>
            We have arrangements with other carriers known as Code Share
            Services. This means that you may have made a reservation with
            us, and you may travel on another carrier\'s aircraft and vice
            versa.
          </p>
          <p>
            If such arrangements apply to your flight, we will advise you
            of the carrier operating the aircraft at the time you make a
            Reservation. The conditions of carriage of the carrier whose
            flight number appears on your Ticket will apply to your
            flight. It is your responsibility to read and comply with
            those conditions of carriage.
          </p>

<h2>Section 5. Reservations And Seat Allocation</h2>
<h3>5.1 What is a Reservation?</h3>
            <p>
              A Reservation is a booking on a Naoero Airlines flight and
              is confirmed only when recorded and paid for and accepted by
              us. An open-dated ticket is not a Reservation. Certain
              fares may have conditions which limit or exclude your right
              to change or cancel Reservations. Please check the Fare
              Rules for details.
            </p>
          
          <h3>5.2 How to make a Reservation</h3>
            <p>
              You can make a Reservation by booking with us, an
              Authorised Agent or online at www.naoeroairlines.com.au
            </p>
          
          <h3>5.3 What happens next?</h3>
            <p>
              Once you have a Reservation, you must pay the Fare for the
              Reservation either at the time of booking or, where you
              have been provided with a Ticketing Time Limit, within that
              Ticketing Time Limit. Make sure you clearly understand any
              Ticketing Time Limit when it is advised to you. If you do
              not pay for your Reservation within the Ticketing Time
              Limit, we may cancel your Reservation. If you pay for your
              Reservation at the time of booking or within the Ticketing
              Time Limit you will be issued with a Ticket by Naoero
              Airlines or your Authorised Agent.
            </p>
          
          <h3>5.4 If you do not use a Reservation</h3>
            <p>
              Depending on the Fare Rules which apply to your Reservation,
              if you do not use the Reservation, you may forfeit your
              Fare.
            </p>
          
          <h3>5.5 Seat allocation</h3>
            <p>
              Some Fare classes and cabin classes may allow you to request
              a preferred seat and/or cabin area. You may also have
              requested to be placed in a certain seat and/or cabin area
              during the booking process. We will endeavour to accommodate
              your seat request. However, we do not guarantee you any
              particular seat, even if you have nominated a seating
              preference. We may change your seat at any time, even after
              you have boarded the aircraft. This may be necessary for
              safety or operational reasons.
            </p>
          
          <h3>5.6 Passengers in Economy Class Occupying Two Seats</h3>
            <p>
              This clause applies to economy class travel only. Upon
              request, or if determined necessary by us, and given
              availability, you will be permitted to the exclusive use of
              two seats subject to the payment of two applicable fares for
              the points between which the two seats will be used. A
              Ticket will be issued for each seat and the normal Checked
              Baggage Allowances will apply in connection with each such
              Ticket presented to us. The carry-on allowance is limited
              to the allowance for one individual.
            </p>
          
          <h3>5.7 Reconfirmation of Reservation</h3>
            <p>
              You may be required to reconfirm onward or return
              Reservations with us or any other carriers involved in your
              journey in accordance with the time limits specified in the
              relevant Ticket. You are responsible for checking and
              complying with all reconfirmation requirements.
            </p>
          
          <h3>5.8 Cancellation of Onward Reservations</h3>
            <p>
              If you do not use a Reservation and fail to advise us, we
              may cancel or request cancellation of any onward or return
              Reservations.
            </p>
          
          <h3>5.9 Collection of personal information</h3>
            <p>
              When you make a Reservation, we must collect certain
              personal information in order to process your Reservation
              and comply with our legal obligations. Our privacy policy
              applies to all of the information we collect. A copy of the
              privacy policy is available at www.naoeroairlines.com.au or
              contact the Naoero Airlines Reservations for more
              information.
            </p>

<h2>Section 6. Tickets, Fares And Tariffs</h2>
<h3>6.1 Issuing a Ticket</h3>
            <p>
              If you have a Reservation and you pay for it at the time of
              booking or within the Ticketing Time Limit either Naoero
              Airlines or an Authorised Agent will issue you with a
              Ticket. A Ticket may be an Electronic Ticket. Other
              airlines may be authorised to issue Electronic Tickets for
              Naoero Airlines.
            </p>
          
          <h3>6.2 Importance of Ticket</h3>
            <p>
              Your Ticket is your primary evidence of your contract with
              Naoero Airlines and is a valuable document. You should keep
              it safe. If your Ticket was issued by us, an Authorised
              Agent or another airline, it remains the property of
              Naoero Airlines.
            </p>
          
          <h3>6.3 Lost Tickets</h3>
            <p>
              If you lose your Electronic Ticket, you can print another
              copy or ask us to send you a replacement Electronic Ticket
              by email.
            </p>
          
          <h3>6.4 Rules about Tickets</h3>
            <p>
              Tickets are not transferable (except for Fare types where
              name changes are permitted) and you must not sell or
              transfer them to anyone else. Any name changes MUST occur
              by calling the Naoero Airlines Reservations and paying the
              applicable name change fee. We will not honour a Ticket
              which has been sold or transferred to a person other than
              the person to whom the Ticket was issued. You will not be
              entitled to be carried on a flight unless you provide a
              Paper Ticket or Electronic Ticket issued in your Name and
              positive identification.
            </p>
          
          <h3>6.5 Ticket validity and extension of validity</h3>
            <p>
              Unless your Ticket, Itinerary, Fare Rules or applicable
              Tariffs state otherwise, the following rules apply to the
              validity of your Ticket:
            </p>
            <ol>
<li>a Ticket for International Travel is valid for one year from the date of commencement of travel, or if the Ticket is not used, one year from the date of the issue of the Ticket.</li>
<li>Naoero Airlines may extend the validity of your Ticket if your travel is disrupted by an event beyond our control.</li>
</ol>
          
          <h3>6.6 Flight Coupon Sequence</h3>
            <p>
              Naoero Airlines will honour Flight Coupons only in the
              sequence from the place of departure as shown on the
              Ticket. The Ticket may not be valid, and we may not honour
              your Ticket if the first Flight Coupon for travel has not
              been used and you commence your journey at any Stopover or
              Agreed Stopping Place. Each Flight Coupon will be accepted
              for carriage in the class of Service specified on the
              Flight Coupon on the date and flight for which
              accommodation has been reserved. When Flight Coupons are
              issued without a Reservation being specified, space will be
              reserved on application subject to the conditions of the
              relevant fare and the availability of space on the flight
              applied for.
            </p>
          
          <h3>6.7 Changes</h3>
            <p>
              Any changes to your Ticket or Itinerary must be made by
              Naoero Airlines or an Authorised Agent and in accordance
              with the Fare Rules.
            </p>
          
          <h3>6.8 Fares</h3>
            <p>
              Naoero Airlines sets various Fares for the various cabin
              classes available on its services. The Fares are set in our
              absolute discretion and provide the inclusions and
              exclusions set out in detail in the Fare Rules. In general,
              Fares cover the transport of you and your Baggage (subject
              to Naoero Airlines\' Conditions of Contract and Other
              Important Notices) from the origin specified in your Ticket
              to the destination specified in your Ticket. The Fares do
              not cover your transport to or from the airport or between
              airports and/or other places unless provided by us without
              additional charge.
            </p>
          
          <h3>6.9 Which Fare applies?</h3>
            <p>
              Naoero Airlines will calculate your exact Fare when you pay
              for your Reservation. We may increase fees and charges as
              relevant under the Fare Rules from time to time. Such
              changes will not affect existing bookings. However, if you
              change your existing booking, including your fare type, new
              Fare Rules and fees may apply to these changes. You will be
              told about the applicable Fare Rules at the time of the
              change to your booking.
            </p>
          
          <h3>6.10 Fare Rules</h3>
            <p>
              Naoero Airlines has various Fare Rules which apply to its
              Fares, and these rules influence both the price and the
              flexibility of the relevant Fares. A copy of the Fare Rules
              is available at www.naoeroairlines.com.au or contact your
              Authorised Agent or the Naoero Airlines Reservations for
              further information.
            </p>
            <p>
              Subject to your right to a refund under applicable Laws,
              including the Australian Consumer Law, some Fare Rules
              relate to non-refundable or very restricted Fares and you
              should carefully consider these rules, your needs and your
              insurance cover before you pay for your Reservation. Should
              the Fare Rules applicable to a particular Fare not suit
              your travel requirements we encourage you to upgrade to a
              more suitable product (for example, full economy Fare).
            </p>
          
          <h3>6.11 Taxes and charges</h3>
            <p>
              You are responsible for applicable taxes or charges imposed
              by governments, other authorities or by the operator of an
              airport that are in effect on the date of travel. Some
              applicable taxes and charges may be applied to your Ticket
              at time of Ticket issuance. Some airport departure charges
              or visa fees, which may change from time to time and may be
              levied directly at an international airport. Such amounts
              may not be marked on your Naoero Airlines Ticket and will
              need to be paid by you to the relevant third party prior to
              departure.
            </p>
          
          <h3>6.12 Currency</h3>
            <p>
              Fares and charges are payable in any currency acceptable to
              Naoero Airlines. When payment is made in a currency other
              than the currency in which the fare is published, such
              payment will be made at the rate of exchange set by us at
              the relevant time.
            </p>

<h2>Section 7. Refunds</h2>
<h3>7.1 Under consumer law</h3>
            <p>
              This Paragraph 7 is subject to Subparagraph clause 2.3.
              Where consumer guarantees apply under applicable consumer
              laws including the Australian Consumer Law, and we fail to
              provide services to you in accordance with those consumer
              guarantees, then you may have a right to seek a remedy in
              accordance with the relevant consumer law in addition to
              the remedies set out in this Paragraph 7. Please see
              Subparagraph 2.3 or contact us for more detail.
            </p>
          
          <h3>7.2 According to Fare Rules</h3>
            <p>
              Depending on the Fare Rules and the applicable law,
              including the Australian Consumer Law, you might be
              entitled to a refund on part or all of your Ticket. If you
              are entitled to a refund we will refund the Fare you paid
              for any unused, refund-eligible part of your Ticket,
              together with any taxes, levies and surcharges which
              applied to those parts of your Ticket, subject to the
              Refund Rules listed in Subparagraph 7.3. The amount of the
              refund will be calculated as follows:
            </p>
            <ol>
<li>if no portion of the Ticket has been used, an amount equal to the Fare paid (including any applicable taxes, levies and surcharges), less any applicable service charges or cancellation fees as set out in the Fare Rules; and</li>
<li>if a portion of the Ticket has been used, the refund will be an amount equal to the difference between the Fare paid and the applicable Fare for travel between the points for which the Ticket has been used (considered on a one-way basis where appropriate, and including any applicable taxes, levies and surcharges) less any applicable service charges or cancellation fees as set out in the Fare Rules.</li>
</ol>
          
          <h3>7.3 Refund Rules</h3>
            <p>The following rules apply to all refunds:</p>
            <ol>
<li>If the Ticket is a Paper Ticket, we will only provide a refund on surrender of the Paper Ticket (including all unused Flight sectors) to us;</li>
<li>any refund will be paid to the person who paid for the Ticket, unless that person has authorised us in writing to pay the refund to someone else. Naoero Airlines will need to formally identify the other person receiving the refund before any payment will be made. This may include the other person providing suitable photographic identification documents such as passport or motor vehicle driver’s licence details as reasonably requested by Naoero Airlines;</li>
<li>we reserve the right to provide the refund in the currency of the original booking or in Australian Dollars at our discretion;</li>
<li>the refund provided under Subparagraph 7.3 will be in full and final satisfaction of any claim for refund by the person who paid for the Ticket and the person named on the Ticket. We will refund any taxes and charges which you have paid on parts of your Ticket which you have not used if those taxes and charges are refundable and provided that we have not already provided the taxes and charges to the authority which levied them; and</li>
<li>Naoero Airlines reserves the right to charge you a reasonable administration fee as set out in the Fare Rules. If the administration fee exceeds the amount of the refundable taxes and charges, we will not pay you a refund.</li>
</ol>
          
          <h3>7.4 Refusing refund</h3>
            <p>
              Irrespective of clauses 7.2 and 7.3, and subject to any
              applicable laws, we may refuse to give you a refund if:
            </p>
            <ol>
<li>you apply for a refund after the expiry of the validity period of the Ticket (see Subparagraph 6.5);</li>
<li>your Ticket has been held by government officials as evidence of an offence against the laws of any country; or</li>
<li>you change your mind or miss your travel due to no fault of us.</li>
</ol>

<h2>Section 8. Passengers Who Require Special Assistance Or Special Meals</h2>
<h3>8.1 Special assistance</h3>
            <p>
              If you require special assistance (for example, due to
              illness, incapacitation, limited mobility, or travelling as
              an unaccompanied minor) we will not refuse to carry you
              provided that:
            </p>
            <ol>
<li>you have fully informed us of any special requirements at the time of making a Reservation;</li>
<li>you have complied with any reasonable requests we may have (including complying with our Policies); and</li>
<li>we and the operator of any relevant Code Share Services have agreed to provide the special assistance. Please visit the Naoero Airlines Website www.naoeroairlines.com.au or contact the Naoero Airlines Reservations for more information.</li>
</ol>
          
          <h3>8.2 Unaccompanied minors</h3>
            <p>
              In addition to the requirements set out in Subparagraphs 8.1
              and 10.2, Naoero Airlines will only carry unaccompanied
              minors in accordance with its Unaccompanied Minor Policy,
              which in summary requires:
            </p>
            <ol>
<li>children under 5 years old will not be carried as an unaccompanied minor;</li>
<li>children between the ages of 5 and 11 inclusive (i.e. before their 12th birthday) who are booked to travel on a Naoero Airlines flight, without the supervision of their parent, legal guardian or other person nominated by the child\'s parent or guardian who is 16 years or older, be booked as an \'Unaccompanied Minor\' for that flight;</li>
<li>the Unaccompanied Minor to hold his or her own valid passport and all country specific travel documents, including any visas or authorisation letters;</li>
<li>that the parent/guardian of the Unaccompanied Minor provide a completed and signed Unaccompanied Minor Form, which must include the name and contact details of the person/s who will deliver the child to our check-in staff, and, the name and contact details of the person/s who will collect the child from our staff;</li>
<li>the Unaccompanied Minor Form should be submitted to the Naoero Airlines Reservations team (reservations@naoeroairlines.com.au) at least 72 hours before the time of departure; and</li>
<li>the person/s delivering and collecting the Unaccompanied Minor to produce a valid piece of photo identification and signed acceptance or delivery forms for each child travelling.</li>
</ol>
            <p>
              There are other limitations, restrictions and requirements
              that apply to the carriage of Unaccompanied Minors set out
              in our Unaccompanied Minor Policy. It is your responsibility
              to read and comply with the Policy in full. Naoero Airlines
              reserves the right not to carry the Unaccompanied Minor if
              the requirements set out above and contained in our
              Unaccompanied Minor Policy are not complied with.
            </p>
            <p>
              The complete Unaccompanied Minor Policy is available at 
              <a href="/travel-info/before-you-fly/unaccompanied-minors" target="_blank">
                Unaccompanied Minors
              </a>
              .
            </p>
          
          <h3>8.3 Pregnancy</h3>
            <p>
              We do not represent that air travel is safe at any
              particular point during pregnancy, including during the
              time periods noted below. You must seek advice from your
              own medical practitioner prior to carriage as to whether it
              is safe to fly. If you are pregnant, the following
              precautions apply:
            </p>
            <p>
              International travel is not permitted after the 36th week
              for routine pregnancies or the 32nd week for routine
              multiple pregnancies e.g., twins. After 28 weeks, you need
              to carry a certificate or letter from a registered medical
              practitioner or registered midwife confirming:
            </p>
            <ul>
              <li>the estimated date of delivery.</li>
              <li>whether it is a single or multiple pregnancy.</li>
              <li>
                that the pregnancy is a routine pregnancy and that there
                are no complications with the pregnancy.
              </li>
            </ul>
            <p>
              Medical clearance is only required if you are having
              complications with your pregnancy or if you wish to travel
              past the 36th (or 32nd for multiple pregnancies) week. Some
              countries place limitations on the entry of non-national
              pregnant women. Check with the relevant embassy or consulate
              before you fly to confirm any further limitations.
            </p>
          
          <h3>8.4 Special meals</h3>
            <p>
              If you request a special meal when you make your
              Reservation, we will try to ensure it is available on your
              selected flight. However, we do not guarantee that we will
              be able to supply your special meal and we will not be
              liable to you for any loss, expense or other damage should
              we be unable to provide your requested special meal for any
              reason.
            </p>

<h2>Section 9. At The Airport</h2>
<h3>9.1 Check-in</h3>
            <p>
              You must arrive at the airport early enough to allow you to
              complete check-in procedures before the check-in deadline.
              You must bring with you a valid passport, your Paper Ticket
              or Electronic Ticket and all necessary travel documentation
              for your Destination and any Stopovers. You must present
              all of these documents to Naoero Airlines Staff if you are
              asked to do so. You must keep any material we give you at
              check-in (such as a boarding pass and a baggage check
              document) until completion of your travel with us.
            </p>
          
          <h3>9.2 Check-in deadline</h3>
            <p>
              You will be advised of the check-in deadline for your
              flights in your Itinerary and/or by the Authorised Agent.
              As a general rule, check-in commences three hours prior to
              departure and closes one hour before the scheduled
              departure time of the aircraft. As flight times may change
              and different airports may have different check-in
              deadlines, you must make sure you are aware of the check-in
              deadline for all of the flights shown on your Ticket.
            </p>
          
          <h3>9.3 Arriving at the boarding gate</h3>
            <p>
              You must complete all departure formalities (such as
              clearing Immigration) in time to arrive at the boarding
              gate no later than the time specified to you at check-in
              and displayed on the flight information displays at the
              airport.
            </p>
          
          <h3>9.4 If you are late or you do not comply with check-in rules</h3>
            <p>
              Subject to the Australian Consumer Law, if you are late or
              do not comply with the check-in rules described in this
              Paragraph 9 through no fault of ours, Naoero Airlines
              reserves the right to cancel your Reservation, refuse to
              carry you and/or to charge you a reasonable service fee to
              re-book your flight.
            </p>
          
          <h3>9.5 Presentation of travel documents</h3>
            <p>
              If we ask you to do so, you must present travel documents
              for the destinations and any Stopovers listed on your
              Ticket and permit us to make copies of them for security
              and procedural requirements and/or as required by any
              applicable Laws or we may refuse to carry you or your
              baggage in accordance with subparagraph 10.2.
            </p>

<h2>Section 10. Refusal Of Carriage</h2>
<h3>10.1 General</h3>
            <p>
              We are not a common carrier and Naoero Airlines strives to
              ensure an enjoyable, safe and secure travel experience for
              all of its Passengers. We may take all steps Naoero
              Airlines believes necessary to ensure the safety and
              security of our Passengers including refusing carriage to
              you and other Passengers in some circumstances. In
              addition, Naoero Airlines may refuse carriage due to the
              practice of overbooking, see Subparagraph 10.4.
            </p>
          
          <h3>10.2 Refusal of carriage due to conduct</h3>
            <p>
              Naoero Airlines may refuse to carry you or your Baggage if
              we in our absolute discretion determine that:
            </p>
            <ol>
<li>refusal of carriage is necessary to ensure the safety of you or others;</li>
<li>carrying you on the flight may materially affect the comfort of any person on the flight;</li>
<li>refusal of carriage is necessary to comply with any applicable Laws (including any laws of countries you are being flown from, to or over);</li>
<li>you have failed to comply with our check-in requirements, including minimum check-in times;</li>
<li>you failed to arrive at the boarding gate by the time listed on your boarding pass or shown on the flight information screens at the airport;</li>
<li>you require special assistance (including in relation to unaccompanied minors) but have not contacted us to make prior arrangements, have provided us with inaccurate or incomplete information in relation to your requirements or have otherwise failed to comply with these Conditions and any applicable policies and requirements (Naoero Airlines will exercise this right only to the extent permitted by any applicable Laws);</li>
<li>you require but have not provided a satisfactory medical certificate in accordance with Naoero Airlines\' Medical Clearance Guidelines;</li>
<li>you have refused to submit to a security check for you and/or your Baggage;</li>
<li>you have failed to comply with our reasonable directions (including the directions of Naoero Airlines Crew and Naoero Airlines Staff or Agents);</li>
<li>you have failed to comply with any applicable Laws, rules, regulations, or these Conditions;</li>
<li>you have not paid the Fare and/or any applicable taxes or charges, your Ticket has been reported lost or stolen, has been dealt with by you in a way which contravenes these Conditions (e.g. transferred) or has otherwise been acquired unlawfully;</li>
<li>you do not have, or do not appear to have, valid travel documents or health documents for your entire journey (including, if requested by Naoero Airlines, valid evidence of COVID-19 vaccination status and/or negative COVID-19 test results) for your country of origin or destination or you destroy your travel documents enroute, or you refuse to give, show or allow us to copy your travel documents;</li>
<li>you cannot prove you are the person listed on your Ticket;</li>
<li>you are (or appear to be) drunk or otherwise under the influence of drugs or alcohol;</li>
<li>you are (or are suspected to be) unlawfully in possession of drugs or illicit drugs;</li>
<li>your mental or physical state is threatening or a danger to Naoero Airlines\' staff or passengers;</li>
<li>you have used threatening, abusive or insulting words or actions towards Naoero Airlines Staff, other Passengers or other persons at the airport;</li>
<li>you have committed any offence in relation to your carriage including an offence during the check-in process or onboard the aircraft;</li>
<li>you have tampered in any way with the aircraft or ground equipment, made a threat, or in any way put the safety of the aircraft in danger;</li>
<li>you have failed to comply with these Conditions (including that you have done any of the things listed in Subparagraph 12.3); and/or</li>
<li>you have done any of the things listed in Subparagraphs 10.2 (8) to (20) (inclusive) on a previous flight (whether that flight was operated by Naoero Airlines or another carrier) and Naoero Airlines has reason to believe that you may do the same thing again.</li>
</ol>
          
          <h3>10.3 If you are refused carriage due to your conduct</h3>
            <p>
              If you are refused carriage as a result of one or more of
              the matters referred to in Subparagraph 10.2, we will
              advise you with a notice refusing carriage. The advice may
              specify that you are refused carriage only for your
              ticketed flight, or it may specify a period of time during
              which we will not carry you. You must not travel or attempt
              to travel with us during this specified period. We reserve
              the right to cancel any Ticket you attempt to use to travel
              on Naoero Airlines when the notice is in force and subject
              to your rights under applicable Laws including the
              Australian Consumer Law, refuse to provide you with a
              refund.
            </p>
          
          <h3>10.4 Refusal of carriage due to overbooking</h3>
            <p>
              Like most airlines, Naoero Airlines on occasion overbooks
              flights in order to account for passengers who do not
              arrive for their ticketed flight. If you are refused
              carriage because your flight has been overbooked and you
              hold a valid Ticket and have complied with these
              Conditions, then we will offer you a seat on the next
              available Naoero Airlines flight. If this is not acceptable
              to you, you may be entitled to compensation in accordance
              with any applicable Laws including the Australian Consumer
              Law.
            </p>

<h2>Section 11. Schedules And Disruptions</h2>
<h3>11.1 General principles</h3>
            <p>
              Naoero Airlines will try to ensure that you and your
              Baggage depart and arrive as closely as possible to our
              scheduled departure and arrival times, as in force on your
              date of travel. However, we do not guarantee that we will
              be able to carry you and your Baggage in accordance with
              the flight times or schedules and they do not form part of
              your contract with us. Schedules may change without notice
              for a range of reasons including but not limited to bad
              weather, air traffic control delays, strikes, technical
              disruptions and late inbound aircraft.
            </p>
          
          <h3>11.2 Schedules and changes</h3>
            <p>
              When you make a Reservation, Naoero Airlines or an
              Authorised Agent will tell you the scheduled time of your
              flights. We may need to change the scheduled departure time
              of your flight. If we change the time of your flight, we
              will make reasonable attempts to contact you or your
              Authorised Agent, using the contact details you have
              provided us. We do not guarantee that we will be able to
              contact you, and so you should check prior to your flight
              to make sure your flight times have not changed.
            </p>
          
          <h3>11.3 Liability for schedule changes</h3>
            <p>
              Except as set out in Subparagraphs 11.5 and 11.6, and
              unless any applicable Laws or Conventions provide
              otherwise, Naoero Airlines will not be liable to you for
              any losses that you incur as a result of schedule changes.
              Unless provided for in these Conditions of Carriage, a
              Convention or any applicable law, we will not be
              responsible for paying any costs or expenses you may incur
              as a result of the changed time or cancellation.
            </p>
          
          <h3>11.4 Disrupted flights</h3>
            <p>
              Flights may be delayed or cancelled or may originate from
              and be diverted to airports other than those scheduled, due
              to various factors. What Naoero Airlines will do for you if
              your flight is disrupted will depend on whether or not the
              disruption is due to Events Beyond our Control (see
              Subparagraphs 11.5 and 11.6).
            </p>
          
          <h3>11.5 Disruptions NOT due to Events Beyond our Control</h3>
            <p>
              Naoero Airlines will use reasonable endeavours to ensure
              that you arrive at your scheduled Destination or Stopover as
              soon as possible. Subject to any remedies under applicable
              Laws such as the Australian Consumer Law or the appropriate
              Conventions, if your flight (on Naoero Airlines) is
              cancelled, fails to stop at your scheduled Destination or
              Stopover, or causes you to miss a connecting flight which
              is on your Ticket and on which your Reservation is
              confirmed, we will:
            </p>
            <ol>
<li>carry you on our next scheduled service on which space is available; or</li>
<li>if the relief in Subparagraph 11.5.1 is not available within a reasonable period of time (having regard to Naoero Airlines\' published schedule), re-route you to the scheduled Destination or Stopover shown on your Ticket, using the services of other carriers (or a combination of Naoero Airlines and other carrier services) or by other means of other transportation; or</li>
<li>if neither the relief in Subparagraph 11.5.1 or 11.5.2 is available, or if Naoero Airlines in its absolute discretion decides that Subparagraph 11.5.1 or 11.5.2 are unsuitable in the circumstances, we will provide a refund to you in accordance with Paragraph 7.</li>
</ol>
            <p>
              The remedies described in this Subparagraph 11.5 are your
              only remedies in relation to the matters described in
              Paragraph 11 and Naoero Airlines will have no further
              liability to you unless any applicable Laws or Conventions
              expressly specify otherwise.
            </p>
          
          <h3>11.6 Disruptions due to Events Beyond our Control</h3>
            <p>
              Where we have had to make a change to your flight due to
              Events beyond our Control, we will:
            </p>
            <ol>
<li>use reasonable endeavours to rebook you on the next available flight on our services at no additional cost to you; or</li>
<li>if we are unable to rebook you on services acceptable to you, provide you with a refund or flight credit where the purpose of your trip is void, subject to the Fare Rules for your flight.</li>
</ol>
            <p>
              You may also have rights or remedies under the Australian
              Consumer Law. We will not be responsible for paying any
              costs or expenses you may incur as a result of the delay or
              cancellation caused by the Events Beyond our Control, unless
              otherwise required by applicable Conventions, Tariffs or
              Laws.
            </p>

<h2>Section 12. During Your Flight</h2>
<h3>12.1 Safety is paramount</h3>
            <p>
              Naoero Airlines\' first priority is the safety of its
              Passengers. Naoero Airlines will take all reasonable steps
              to ensure its Passengers\' safety at all times. You have an
              important role to play in the safety of your flight, both
              in relation to the things you should do, and the things you
              should never do. This Paragraph 12 explains these
              obligations and the steps Naoero Airlines may take to
              ensure safety.
            </p>
          
          <h3>12.2 Your obligations</h3>
            <p>Whenever you travel with Naoero Airlines, you must:</p>
            <h3>1. Respect Naoero Airlines Crew and obey their directions</h3>
              <p>
                Naoero Airlines Crew have been extensively trained to
                ensure you have a safe and comfortable flight. At all
                times you must listen carefully to and promptly obey any
                directions given to you by Naoero Airlines Crew. This
                includes any direction to provide your travel documents
                to Naoero Airlines Crew.
              </p>
            
            <h3>2. Safety briefing</h3>
              <p>
                Listen carefully to the pre-flight safety briefing, read
                the safety card provided to you, identify your nearest
                exits and familiarise yourself with the location and
                operation of safety equipment.
              </p>
            
            <h3>3. Seatbelt and infant restraints</h3>
              <p>
                Wear your seatbelt whenever you are seated – even when you
                are sleeping. Make sure you know how to operate infant and
                child restraints and use them only as directed by Naoero
                Airlines Crew.
              </p>
            
            <h3>4. Remain seated</h3>
              <p>
                Remain in your seat whenever the \'Fasten Seatbelt\' sign is
                illuminated and return to your seat if Naoero Airlines
                Crew tell you to do so.
              </p>
            
            <h3>5. Stow Carry-on Baggage</h3>
              <p>
                Stow your Carry-on Baggage as directed by Naoero Airlines
                Crew.
              </p>
            
            <h3>6. Use electronic devices only as directed</h3>
              <p>
                Use electronic devices (including but not limited to
                personal music players, laptop computers and game
                devices), only when Naoero Airlines Crew tell you it is
                safe to do so. Do not use transmitting devices (including
                but not limited to radios and remote-controlled toys),
                while onboard. You are required to switch off your mobile
                phone or switch it to \'flight mode\' while onboard the
                aircraft. If you do not comply with these directions
                Naoero Airlines crew may take your electronic device from
                you and keep it until the end of the flight.
              </p>
            
            <h3>7. Behave responsibly and respect fellow passengers</h3>
              <p>
                You will be sharing your flight with others, so please
                consider and respect their privacy, peace and personal
                space. Please make sure you behave in a way which does
                not disturb or cause offence to your fellow passengers.
              </p>
            
            <h3>8. Consume alcohol responsibly</h3>
              <p>
                Naoero Airlines is serious about the responsible service
                of alcohol. We ask that you drink in moderation and
                ensure that you drink plenty of water to keep hydrated.
                In order to help us serve alcohol responsibly you must
                only consume alcohol which we supply to you on the
                flight and no alcohol that you bring onboard with you.
                We may refuse to serve you alcohol where we consider you
                to be impaired by the effects of alcohol.
              </p>
            
          
          <h3>12.3 What you must never do</h3>
            <p>Whenever you travel with Naoero Airlines, you must never:</p>
            <h3>1. Smoke or use Electronic Cigarettes and Vapes</h3>
              <p>
                All Naoero Airlines services are non-smoking, and most
                laws prohibit smoking onboard aircraft or in and around
                airport terminals. Electronic cigarettes, vapes and all
                other smoking devices are not allowed to be used or
                charged at any time.
              </p>
            
            <h3>2. Behave in offensive manner</h3>
              <p>
                You must not behave in a manner which would be considered
                by a reasonable person to be offensive, or in a manner
                which might cause discomfort, distress, offence or injury
                to another person.
              </p>
            
            <h3>3. Disobey directions</h3>
              <p>
                Naoero Airlines Crew will at times ask you to do things
                (such as returning to your seat when the aircraft
                encounters turbulence) which are important for your
                safety and the safety of others. You must never disobey
                these directions.
              </p>
            
            <h3>4. Use illicit drugs</h3>
              <p>
                You must not use illicit drugs onboard Naoero Airlines
                aircraft or use prescription drugs which you are not
                medically required to take.
              </p>
            
            <h3>5. Endanger safety of aircraft, Crew or others</h3>
              <p>
                You must never do anything which may endanger the safety
                of the aircraft on which you are travelling, the safety
                of Naoero Airlines Crew and/or your fellow passengers.
              </p>
            
            <h3>6. Tamper with or damage aircraft or onboard equipment</h3>
              <p>
                You must never tamper, interfere with or damage any part
                of the aircraft or the equipment carried onboard.
              </p>
            
            <h3>7. What Naoero Airlines may do to ensure safety</h3>
              <p>
                Naoero Airlines reserves the right to take all steps
                which are reasonably necessary to ensure the safety of a
                flight and Naoero Airlines Crew and Passengers. If in the
                opinion of our Crew you have not complied with your
                obligations in Paragraph 12 and in particular, if you
                have done any of the things listed in Subparagraph 12.3
                we may take steps which may include one or more of the
                following:
              </p>
              <ol style="list-style-type: lower-alpha;">
<li>move you to another seat, or another part of the aircraft;</li>
<li>restrain you;</li>
<li>take any such other action as is reasonably necessary to ensure the safety of the flight, its Passengers and Naoero Airlines Crew, including the reasonable use of force;</li>
<li>remove you from the flight;</li>
<li>divert the flight and offload you from it at your cost;</li>
<li>report you to the relevant authorities;</li>
<li>refuse carriage for the remaining journeys on your Ticket; and/or</li>
<li>serve you with a notice of refusal of carriage, see Subparagraph 10.3.</li>
</ol>
              <p>
                If we divert the aircraft to an unscheduled destination
                as a result of your failure to comply with these
                Conditions (including your conduct as described in
                Subparagraph 12.3) you must pay us the reasonable costs
                of the diversion.
              </p>

<h2>Section 13. After Your Flight</h2>
<h3>13.1 Baggage collection</h3>
            <p>
              Make sure you collect your Checked Baggage from the
              collection point advised to you by Naoero Airlines Staff
              when you arrive at your Destination.
            </p>
          
          <h3>13.2 Lost Baggage</h3>
            <p>
              If you cannot find your Baggage please notify Naoero
              Airlines Staff and provide them with your Ticket and any
              baggage identification tag which was issued to you at
              check-in.
            </p>
          
          <h3>13.3 Wrong bag</h3>
            <p>
              If you collect the wrong bag, you must return it to the
              airport where you collected it as soon as you discover the
              error.
            </p>
          
          <h3>13.4 If you do not collect your baggage</h3>
            <p>
              If you do not collect your Baggage within 30 days after
              your flight, Naoero Airlines will, acting reasonably,
              attempt to contact you with the details provided at the
              time of your Reservation. If we are unable to reach you, or
              you fail to collect the Baggage within the agreed period,
              we may destroy it without further notifying you and without
              paying you any compensation, unless required by applicable
              Laws.
            </p>
          
          <h3>13.5 Leaving bags behind</h3>
            <p>
              Naoero Airlines is not responsible or liable to you for any
              loss you suffer as a result of leaving behind any Checked
              Baggage or Carry-on Baggage onboard or at the airport.
            </p>

<h2>Section 14. Successive Carriers</h2>
<p>
            If your Ticket is for flights operated by Naoero Airlines and
            other carriers, the carriage may be regarded as a single
            operation under any applicable Laws or Conventions.
          </p>

<h2>Section 15. Arrival Formalities And Administrative Requirements</h2>
<h3>15.1 Visas and entry documents</h3>
            <p>
              You are responsible for ensuring that you obtain and
              present at your Destination and any Stopover all required
              travel documents (see Paragraph 3). Naoero Airlines is not
              responsible or liable to you if you have failed to obtain
              or lost these documents.
            </p>
          
          <h3>15.2 Entry rules and requirements</h3>
            <p>
              You must also comply with all entry requirements, rules and
              regulations of your Destination or any Stopover. This
              includes any immigration, customs and security
              requirements. Naoero Airlines is not responsible or liable
              to you if you do not comply with these requirements, rules
              and regulations.
            </p>
          
          <h3>15.3 If you are refused entry or deported</h3>
            <p>
              If you are refused permission to enter a country during
              your journey with Naoero Airlines or are deported, then
              regardless of the reason for the refusal of entry or
              deportation:
            </p>
            <ol>
<li>we will, if required by a governmental authority, transport you from the country that has refused you entry or deported you;</li>
<li>you must pay for any costs associated with the refusal of entry or deportation, including any fines and detention and repatriation costs;</li>
<li>we will not provide you with a refund for your Ticket where you were denied entry and may offset any unused part of your Ticket to offset any repatriation costs or fines incurred by Naoero Airlines as a result of your refusal of entry or deportation; and</li>
<li>you must reimburse us for any fines, penalties, losses, costs, expenses or damage which Naoero Airlines incurs as a result of you being denied entry or deported.</li>
</ol>

<h2>Section 16. Baggage</h2>
<h3>16.1 Baggage allowance</h3>
            <p>
              Naoero Airlines will provide you with a free Baggage
              allowance as part of your Ticket. Your actual allowance
              will depend on your cabin class of travel and the
              applicable Fare Rules. This allowance relates both to
              Checked Baggage and Carry-on Baggage. The basic free
              Baggage allowances are set out in Naoero Airlines\'
              Conditions of Contract and Other Important Notices and on
              our website. We may change the Baggage allowances from
              time-to-time. However, any change will not impact existing
              bookings and will only apply to future bookings made after
              the effective date of the change.
            </p>
          
          <h3>16.2 Excess Baggage and oversized Baggage</h3>
            <p>
              If you wish to take Baggage with you which is bigger or
              heavier than your free Baggage allowance, it will be
              considered excess and/or oversized Baggage and you will be
              charged extra for it. In addition, if your Baggage exceeds
              certain size and weight limitations, it must be sent as
              freight, or we may refuse to carry it. For more information
              visit Naoero Airlines\' Website www.naoeroairlines.com.au or
              contact Naoero Airlines\' Reservations.
            </p>
          
          <h3>16.3 Carrying your Baggage and right to refuse</h3>
            <h3>1. Carriage on same flight</h3>
              <p>
                While Naoero Airlines will use its best endeavours to
                carry your baggage on the same flight as you, we may for
                operational reasons (such as aircraft take-off weight
                restrictions) carry your baggage on the next available
                flight.
              </p>
            
            <h3>2. Right to refuse carriage</h3>
              <p>
                Naoero Airlines may in its absolute discretion refuse to
                carry your Baggage (or refuse to continue to carry your
                Baggage if a problem is detected after you first check
                in):
              </p>
              <ol style="list-style-type: lower-alpha;">
<li>if it does not meet the requirements in this Paragraph 16 or the requirements set out in these Conditions or the Baggage Policy of Naoero Airlines;</li>
<li>if it is not securely packed in suitable materials;</li>
<li>if it may cause discomfort or inconvenience to other Passengers (for example, because of any odour it emits); or</li>
<li>for safety or operational reasons.</li>
</ol>
            
          
          <h3>16.4 Not acceptable as Baggage</h3>
            <p>You must never include in your Baggage:</p>
            <ol>
<li>items which may endanger the aircraft or persons, or property onboard the aircraft, such as those specified in the International Civil Aviation Organisation (ICAO) Technical Instructions for the Safe Transport of Dangerous Goods by Air, the IATA Dangerous Goods Regulations and in our regulations (further information is available from Naoero Airlines on request);</li>
<li>items which are prohibited by any applicable Laws (including any laws of countries you are being flown from, to or over);</li>
<li>items which Naoero Airlines in its absolute discretion refuses to take; and</li>
<li>other items prohibited in Paragraph 16.</li>
</ol>
          
          <h3>16.5 Right to search</h3>
            <p>
              In addition to any searches required by any applicable
              Laws, for reasons of safety and security, we reserve the
              right to search your Baggage at any time to ensure that it
              meets Naoero Airlines\' baggage requirements including any
              requirements set out in the Policies. Naoero Airlines may
              refuse to carry you or your Baggage if you refuse to
              consent to a search. Your Checked Baggage may be searched
              by us, government or airport officers or their
              representatives in your absence if you are not available,
              for the purpose of determining whether you are in
              possession of, or whether your Baggage contains any item
              listed in Subparagraph 16.4.
            </p>
            <p>
              Except as otherwise provided by a Convention or other
              applicable Laws, if a search, screening or x-ray causes
              damage to you or your Baggage, we will not be liable for
              the damage unless it was caused by our fraud, negligence or
              wilful misconduct.
            </p>
          
          <h3>16.6 Rules for Checked Baggage</h3>
            <p>
              You must comply with the following rules in relation to
              Checked Baggage:
            </p>
            <h3>1. Not acceptable as Checked Baggage</h3>
              <p>
                Naoero Airlines does not accept the following items as
                Checked Baggage and to the extent permitted by applicable
                Laws, does not accept liability for their loss or damage
                unless the items are identified to us and we have, in our
                absolute discretion, given our written acceptance for
                carriage and accepted liability in writing for their loss
                or damage:
              </p>
              <ol style="list-style-type: lower-alpha;">
<li>cash, credit cards, deeds, passports and other travel documents, securities, business documents or other valuable documents;</li>
<li>jewellery, antiques, precious metals or similar valuable items;</li>
<li>any item of a delicate, fragile or brittle nature e.g. glassware, sports equipment, musical instruments;</li>
<li>any item of a perishable nature e.g. seafood;</li>
<li>medicines;</li>
<li>buckets;</li>
<li>polystyrene eskys and we will only accept IATA approved polystyrene boxes as cargo;</li>
<li>mattresses of any kind and these must be presented at the Cargo terminal;</li>
<li>TV\'s exceeding 43 inches;</li>
<li>large oversize eskys measuring greater than 1,500 i.e. eskys measure more than of 1,500mm when adding height, width and length. These need to be presented as cargo;</li>
<li>electrical or electronic components e.g. computers, cameras; and</li>
<li>any item that has insufficient packaging to withstand the normal circumstances and effects of carriage by air.</li>
</ol>
            
            <h3>2. Checked Baggage receipt</h3>
              <p>
                You must keep any receipt which Naoero Airlines or a
                carrier of a Code Share Service gives you for your
                Checked Baggage. This is an important document which will
                help you to claim your Baggage at your destination and to
                claim any applicable compensation if your Baggage is lost
                or damaged.
              </p>
            
            <h3>3. Identification of Baggage</h3>
              <p>
                You should ensure that all of your Checked Baggage is
                marked with your name and address or some other form of
                unique identification which will allow confirmation that
                the Baggage belongs to you.
              </p>
            
            <h3>4. Sporting goods</h3>
              <p>
                There is no free sporting equipment baggage allowance and
                all sporting equipment is added to your baggage
                allowances. Contact Naoero Airlines Reservations for more
                information if you are planning to check in sporting
                goods.
              </p>
            
          
          <h3>16.7 Rules for Carry-on Baggage</h3>
            <p>
              You must comply with the following rules in relation to
              Carry-on Baggage:
            </p>
            <h3>1. Dimension limits</h3>
              <p>
                Naoero Airlines\' Conditions of Contract and Other
                Important Notices set out the allowances for Carry-on
                Baggage, including dimension and weight limits. If your
                Carry-on Baggage exceeds these limits, or if we decide
                that your Carry-on Baggage cannot be safely carried in
                the cabin of the aircraft, we may carry your Baggage as
                Checked Baggage, or may refuse to carry it at all if it
                is an item to which Subparagraph 16.4 applies.
              </p>
            
            <h3>2. Special items</h3>
              <p>
                Naoero Airlines may accept larger items (such as musical
                instruments) which would normally not be suitable as
                Carry-on Baggage if you make prior arrangements with us.
                Naoero Airlines reserves the right to charge a reasonable
                additional fee for the carriage of the item as Carry-on
                Baggage.
              </p>
            
          
          <h3>16.8 Rules for carriage of animals</h3>
            <p>
              Naoero Airlines may carry animals if you make prior
              arrangements with us in either the aircraft hold or the
              aircraft cabin depending on the type of animal. Naoero
              Airlines reserves the right to refuse to carry your animal
              and to set requirements in relation to crates, feeding,
              health and vaccinations. For more information please visit
              Naoero Airlines\' Website or Reservations. Naoero Airlines
              will carry recognised assistance animals in the cabin of
              the aircraft, in accordance with Naoero Airlines\' Policies.
            </p>
          
          <h3>16.9 Carriage of firearms</h3>
            <p>
              We may, but are not obliged to, agree to carry firearms and
              ammunition for hunting or sporting purposes as Checked
              Baggage. If we do, these must be packed in accordance with
              all applicable national and international laws and
              regulations.
            </p>
            <p>
              Our approval may be withheld at our sole discretion.
              Applications for the carriage of firearms and ammunition
              for hunting or sporting purposes must be submitted at least
              7 working days prior to your anticipated departure date.
            </p>

<h2>Section 17. Liability For Damage</h2>
<h3>17.1 Determination of liability</h3>
            <p>
              The liability of Naoero Airlines and each carrier involved
              in your journey will be determined by these Conditions, any
              applicable Laws and Conventions and each carrier\'s
              conditions of carriage. Nothing in these Conditions limits
              or excludes our liability to you that we cannot limit or
              exclude under applicable Laws (including the Australian
              Consumer Law) or the Conventions.
            </p>
            <p>
              The following is a brief outline but you should obtain
              professional advice in the event that you believe that you
              may have a claim as a result of any injury, loss or damage.
              The limits of liability fixed by law are subject to
              periodic review and variation and you may be entitled to
              any higher limit applicable at the time of carriage.
            </p>
          
          <h3>17.2 Liability rules</h3>
            <p>
              Unless this Paragraph 17 says otherwise, international
              carriage, as defined in any applicable Conventions, will be
              governed by the liability rules of those applicable
              Conventions. Where a Convention does not apply, these
              Conditions will govern Naoero Airlines\' liability. If your
              flight has a destination or stop in a country other than
              the one from which you depart the Montreal Convention (as
              amended) or its predecessor, the Warsaw Convention (as
              amended), may apply to your flight. These Conventions may
              govern the flight and, in most cases:
            </p>
            <ol>
<li>impose liability and create rights of action in defined circumstances during international carriage by air;</li>
<li>limit the liability of airlines in the case of your death or injury, loss of or damage to Baggage, cargo and delay;</li>
<li>impose conditions in relation to notice of damage and the time to bring legal proceedings; and</li>
<li>specify where proceedings may be brought and may exclude claims under other laws.</li>
</ol>
          
          <h3>17.3 Death or Injury of Passengers</h3>
            <p>
              In the event of death or other bodily injury suffered by a
              Passenger as a result of an accident covered by any
              applicable Conventions:
            </p>
            <p>1. If the Montreal Convention (as amended) applies, Naoero Airlines:</p>
            <ol style="list-style-type: lower-alpha;">
<li>will be liable for any damages up to 128,821 SDRs (about A$264,000) in respect of death or bodily injury caused by an accident onboard an aircraft or during embarking or disembarking from an aircraft.</li>
<li>will not be liable for damages to the extent that it exceeds 128,821 SDRs (about A$264,000) for each passenger if: (i) such damage was not due to the negligence or other wrongful act or omission of Naoero Airlines or its servants and agents; or (ii) such damage was solely due to the negligence or other wrongful act or omission of a third party.</li>
<li>damages for death or bodily injury may be reduced or even exonerated if Naoero Airlines proves that the damage was caused or contributed to by the negligence or other wrongful act or omission of the person claiming compensation.</li>
</ol>
            <p>
              2. If the Warsaw Convention (as amended) applies similar
              provisions to those relating to the Montreal Convention
              will prevail but where there are financial limits of
              liability those limits will be different and you should
              obtain advice on the level of liability that may apply at
              the time of loss or damage. However, any liability we may
              have for damage will be reduced in accordance with
              applicable laws by any negligence on your part that causes
              or contributes to the damage.
            </p>
            <p>
              3. Naoero Airlines reserves all other defences and
              limitations available under any applicable Conventions to
              such claims including, but not limited to, the exoneration
              defence of Article 21 of the Warsaw Convention (as amended)
              and Article 20 of the Montreal Convention (as amended)
              except that Naoero Airlines shall not invoke Articles 20
              and 22(1) of the Warsaw Convention (as amended) and
              Articles 19 and 22 of the Montreal Convention (as amended)
              in a manner inconsistent with these Conditions.
            </p>
            <p>
              4. Where your flight is wholly within Australia and is not
              International Travel it is subject to the provisions of the
              Civil Aviation (Carriers\' Liability) Act 1959 (as amended)
              or complementary State legislation and our liability for
              your injury or death resulting from an accident is limited
              to A$925,000.
            </p>
            <p>
              5. Naoero Airlines reserves all rights of recourse against
              any third parties, including without limitation, rights of
              contribution and indemnity.
            </p>
            <p>
              6. Notwithstanding this Subparagraph 17.3 and the above
              Subparagraphs, any claims made by public social insurance
              or similar bodies (except with respect to any such bodies
              of the United States) shall be subject to the liability
              limit and defenses under any applicable Conventions.
            </p>
            <p>
              7. Notwithstanding this Subparagraph 17.3 and the above
              Subparagraphs, in respect of travel to and from the United
              States, the following shall apply:
            </p>
            <p>
              Naoero Airlines shall avail itself of the limitation
              provided in the Convention for the Unification of Certain
              Rules Relating to International Carriage by Air signed at
              Warsaw 12 October 1929, or provided in the said Convention
              as amended by the Protocol signed at The Hague 28 September
              1955. However, in accordance with Article 22(1) of said
              Convention, or said Convention as amended by said Protocol,
              Naoero Airlines agrees that, as to all international
              transportation by Naoero Airlines as defined in the said
              Convention or said Convention as amended by said Protocol,
              which, according to the contract of Carriage, includes a
              point in the United States of America as a point of
              origin, point of destination, or agreed stopping place:
            </p>
            <p>
              (1) the limit of liability for each passenger for death,
              wounding, or other bodily injury shall be the sum of US
              $75,000 inclusive of legal fees and costs, except that, in
              case of a claim brought in a State where provision is made
              for separate award of legal fees and costs, the limit shall
              be the sum of US $58,000 exclusive of legal fees and costs;
              and
            </p>
            <p>
              (2) Naoero Airlines shall not, with respect to any claim
              arising out of the death, wounding, or other bodily injury
              of a passenger, avail itself of any defense under Article
              20(1) of said Convention or said Convention as amended by
              said Protocol.
            </p>
            <p>
              Nothing herein shall be deemed to affect the rights and
              liabilities of Naoero Airlines with regard to any claim
              brought by, on behalf of, or in respect of any person who
              has willfully caused damage which resulted in death,
              wounding, or other bodily injury of a passenger.
            </p>
          
          <h3>17.4 Physical condition</h3>
            <p>
              To the extent permitted by Laws, Naoero Airlines is not
              responsible for any pre-existing illness, injury or
              disability, including death, attributable to your physical
              condition or the aggravation of such condition.
            </p>
          
          <h3>17.5 Baggage</h3>
            <p>
              1. Naoero Airlines will be liable only for destruction,
              loss, damage or delay occurring during carriage ticketed on
              Naoero Airlines\' Airline Designator Code. If we issue a
              ticket or check baggage on the flight of another carrier,
              we only do so as agent for that carrier as outlined in
              Subparagraph 2.5. With respect to Checked Baggage you may
              also have a right of action against the first or last
              carrier.
            </p>
            <p>
              2. Naoero Airlines is not responsible for small or
              non-significant damage to checked baggage. This includes:
            </p>
            <ol style="list-style-type: lower-alpha;">
<li>scratches, dents and minor cuts;</li>
<li>damage to or loss of straps, hooks or belts;</li>
<li>broken wheels or feet;</li>
<li>zips or locks;</li>
<li>damage to retractable or fixed baggage handles;</li>
<li>damage resulting from over packing; and/or</li>
<li>for US flights only, Naoero Airlines is not responsible for fair wear and tear including scratches, dents, minor cuts or damage caused from over packing.</li>
</ol>
            <p>
              3. Naoero Airlines is not liable for any damage to your
              Carry-on Baggage unless the damage was caused by the fault
              of Naoero Airlines.
            </p>
            <p>
              4. You are responsible for any damage caused by your
              Baggage to other persons or property, including Naoero
              Airlines\' property; and you agree to indemnify Naoero
              Airlines for any such damage unless such damage is caused
              by our fraud, negligence or wilful misconduct.
            </p>
            <p>
              5. Except to the extent required by any applicable Laws,
              Naoero Airlines is not liable for damage or delay to items
              which you are asked not to include in your Checked Baggage
              as per Subparagraph 16.6.1.
            </p>
            <p>
              6. Where your travel is international carriage as defined
              in any applicable Conventions and those Conventions apply,
              Naoero Airlines\' liability for loss of, damage to, or delay
              in the carriage of your Checked Baggage is limited by the
              applicable Conventions except where you prove that the
              damage resulted from an act or failure to act either done
              with the intention to cause damage or recklessly and with
              knowledge that damage would probably result. Subject to
              the foregoing exception, Naoero Airlines\' liability for
              loss of, damage to, or delay in the carriage of Baggage is
              limited by any applicable Conventions as follows:
            </p>
            <ol style="list-style-type: lower-alpha;">
<li>If the Montreal Convention (as amended) applies, our liability is limited to 1,288 SDRs (about A$2,640). (i) in the case of Checked Baggage, Naoero Airlines will not be liable to the extent the damage resulted from an inherent defect, quality or vice of the Checked Baggage; (ii) in the case of Carry-on Baggage, Naoero Airlines will only be liable if Naoero Airlines was at fault.</li>
<li>If the Warsaw Convention (as amended) applies liability for loss, delay or damage is limited to 250 francs per kilogram for Checked Baggage or 5,000 francs for Carry-on Baggage (unless Article 25 of the Warsaw Convention applies, in which case these limits do not apply).</li>
<li>The limitations referred in Subparagraphs 17.5.5(a) and (b) do not apply if the Passenger declared a higher value in advance and paid additional charges. Only in that instance shall Naoero Airlines\' liability be extended to the higher declared value.</li>
</ol>
            <p>
              7. If there has been contributory negligence on your part
              with respect to any destruction, loss, damage or delay of
              Baggage, our liability may be eliminated or reduced in
              accordance with applicable Laws.
            </p>
            <p>
              8. If you hold a Baggage receipt and receive your Checked
              Baggage without making a complaint, it will be reasonable
              evidence that the Checked Baggage was delivered in good
              condition, unless proven otherwise. In the case of damage,
              the person entitled to delivery must advise the carrier
              immediately after the discovery of the damage, and, at the
              latest, within seven days from the date of receipt in the
              case of Checked Baggage and fourteen days from the date of
              receipt in the case of cargo. In the case of delay, the
              complaint must be made at the latest within twenty-one days
              from the date on which the Checked Baggage or cargo have
              been placed at his or her disposal.
            </p>
            <p>
              9. In case of carriage not subject to any applicable
              Conventions, Naoero Airlines\' liability for Checked and
              Carry-on Baggage shall be limited pursuant to the Civil
              Aviation (Carriers\' Liability) Act 1959 (Cth) or
              complementary Australian State legislation and our
              liability for loss or damage is limited to A$3,000 per
              Passenger for Checked Baggage and A$300 per Passenger for
              Carry-on Baggage.
            </p>
          
          <h3>17.6 Passenger Delay</h3>
            <p>
              1. Subject to applicable Laws, Naoero Airlines will not be
              liable for loss or damage occasioned by delay in the
              carriage of Passengers by air if it and its servants took
              reasonable measures that could reasonably be required to
              avoid the damage, or that it was impossible for them to
              take such measures.
            </p>
            <p>
              2. Airport, air traffic, control, security and other
              facilities or personnel, whether public or private, who are
              not under the control and direction of Naoero Airlines are
              not agents or servants of Naoero Airlines and Naoero
              Airlines is not liable to the extent that the delay is
              caused by these types of facilities or personnel.
            </p>
            <p>3. In the case of delay to your flight:</p>
            <ol style="list-style-type: lower-alpha;">
<li>where the Montreal Convention (as amended) applies, we will be liable for damage except when we took all measures that could reasonably be required to avoid the damage or it was impossible for us to take such measures. Our liability under the Montreal Convention (as amended) is limited to 5,346 SDRs (about $11,000); and</li>
<li>where the Warsaw Convention (as amended) applies, we will be liable for damage except when we can prove that we took all necessary measures to avoid the damage or that it was impossible for us to take such measures.</li>
</ol>
          
          <h3>17.7 Total liability limit</h3>
            <p>
              Subject to applicable Laws, in no case will Naoero
              Airlines\' liability exceed the actual amount of damages
              suffered by the Passenger. All claims shall be limited to
              proven direct compensatory damages. Naoero Airlines will
              not in any circumstances be liable for exemplary,
              aggravated, consequential, indirect or special loss or
              damages.
            </p>
          
          <h3>17.8 Reduction for contribution to damage</h3>
            <p>
              Any liability of Naoero Airlines for damage may be reduced
              by any negligence on your part which causes or contributes
              to the damage in accordance with applicable Laws.
            </p>
          
          <h3>17.9 Damage arising by law</h3>
            <p>
              Naoero Airlines will not be liable for any damage directly
              or indirectly arising out of its compliance with any
              applicable Laws, failure of a Passenger to comply with the
              same, or any Events Beyond our Control.
            </p>
          
          <h3>17.10 Application to others</h3>
            <p>
              These Conditions including the documents referred to in
              these Conditions (such as the Conditions of Contract and
              Other Important Notices) apply to our Authorised Agents,
              Naoero Airlines Staff and representatives to the same
              extent as they apply to Naoero Airlines. The total amount
              recoverable from us and from such Authorised Agents, Naoero
              Airlines Staff and representatives shall not exceed the
              amount of Naoero Airlines liability, if any.
            </p>
          
          <h3>17.11 No waiver</h3>
            <p>
              Except as otherwise expressly provided in these Conditions,
              nothing contained in these Conditions shall waive any
              defence or exclusions or limitation of liability under any
              applicable Conventions or Laws. With respect to third
              parties, we reserve all rights of recourse against any
              other person, including without limitation, rights of
              contribution and indemnity.
            </p>

<h2>Section 18. Time Limitation On Claims And Actions</h2>
<h3>18.1 Notice of Claims</h3>
            <p>
              In the case of loss or damage to Checked Baggage, you must
              notify us in writing within 7 days and in the case of
              delay, within 21 days of delivery. In respect of loss of or
              damage to Carry-on Baggage you must notify the Airline in
              writing within 7 days from the date the carriage ended.
            </p>
          
          <h3>18.2 Limitation of Actions</h3>
            <p>
              Unless otherwise required by law or your right to claim for
              Damages has expired earlier as provided elsewhere in these
              Conditions of Carriage, you will have no right to claim for
              Damages if court proceedings are not brought within two (2)
              years from:
            </p>
            <ol>
<li>the date of arrival at the destination;</li>
<li>the date on which the aircraft ought to have arrived; or</li>
<li>the date on which the carriage stopped.</li>
</ol>
            <p>
              The method of calculating the period of limitation shall be
              determined by the law of the court seized of the case.
            </p>

<h2>Section 19. Terms Used In These Conditions</h2>
<p>The singular includes the plural and vice versa. The titles of Paragraphs and the marginal headings are inserted for convenient reference only and are not to be used in ascertaining the meaning of any of the provisions of these Conditions.</p>
<table class="table-auto w-full border border-slate-200">
<thead><tr><th class="border border-slate-200 p-2 font-bold">Terms</th><th class="border border-slate-200 p-2 font-bold">Meaning</th></tr></thead>
<tbody>
<tr><td class="border border-slate-200 p-2 font-semibold">Agreed Stopping Places</td><td class="border border-slate-200 p-2">Those places, except the place of departure and the place of destination, set forth in the Ticket or shown in Naoero Airlines\' timetables as scheduled stopping places on the passenger\'s route.",
                  ],
                  [
                    \'Airline Designator Code\',
                    "The two letters or number code issued by IATA to each airline to identify that airline and its flights. For example, Naoero Airlines\' Designator Code is \'ON\'.",
                  ],
                  [
                    \'Australian Consumer Law\',
                    \'Schedule 2 to the Australian Competition and Consumer Act 2010 (Cth).\',
                  ],
                  [
                    \'Authorised Agent\',
                    \'A duly licensed passenger sales agent who is authorised by Naoero Airlines to represent Naoero Airlines and sell Naoero Airlines Services.\',
                  ],
                  [
                    \'Baggage\',
                    \'The items and objects which you bring with you when you travel on Naoero Airlines and includes Checked Baggage and Carry-on Baggage.\',
                  ],
                  [
                    \'Baggage Allowance\',
                    \'The allowance for your Baggage, as set out in the Fare Rules and administered in accordance with Paragraph 16 and the Policies.\',
                  ],
                  [
                    \'Carry-on Baggage\',
                    \'Baggage which Naoero Airlines lets you take onboard the aircraft with you.\',
                  ],
                  [
                    \'Checked Baggage\',
                    \'That part of your baggage including any excess baggage which we have taken into our custody and for which we have issued a baggage identification tag or baggage check or both.\',
                  ],
                  [
                    \'Code Share Services\',
                    "A flight where the \'ON\' Airline Designator Code in the flight number is not the Airline Designator Code of the Carrier/Airline operating the aircraft.",
                  ],
                  [\'Conditions\', \'These Conditions of Carriage.</td></tr>
<tr><td class="border border-slate-200 p-2 font-semibold">Conditions of Contract and Other Important Notices</td><td class="border border-slate-200 p-2">The additional terms and conditions on which the contract for your carriage has been made and which are contained in or delivered with your Ticket, Itinerary, the booking page of the Naoero Airlines Website, or your boarding pass and which incorporate these Conditions of Carriage by reference.",
                  ],
                  [
                    \'Conventions\',
                    "Whichever of the following instruments (or the following instruments as amended) that may be applicable to your travel with Naoero Airlines: the Montreal Convention (1999); the Convention for the Unification of Certain Rules Relating to International Carriage by Air, signed at Warsaw, 12 October 1929 (the Warsaw Convention); the Warsaw Convention as amended at The Hague on 28 September 1955; the Warsaw Convention as amended at The Hague and by Additional Protocol No. 1, 2 or 4 of Montreal (1975); the Guadalajara Supplementary Convention (1961); and any other applicable protocols or conventions and any enabling legislation.",
                  ],
                  [
                    \'Destination\',
                    \'The last destination shown on your Itinerary. When not capitalised, destination refers to a generic point of arrival.\',
                  ],
                  [\'Domestic Travel\', \'Travel entirely within one country.</td></tr>
<tr><td class="border border-slate-200 p-2 font-semibold">Electronic Coupon</td><td class="border border-slate-200 p-2">An electronic flight coupon or other value document in electronic record form held in our database.\',
                  ],
                  [
                    \'Electronic Ticket\',
                    \'A Ticket issued electronically (which may be comprised of Electronic Coupons) by Naoero Airlines or an Authorised Agent. Your electronic ticket may be combined with an Itinerary.\',
                  ],
                  [
                    \'Events Beyond our Control\',
                    \'Events such as severe or inclement weather, strikes, security alerts or other events that may affect our ability to provide air transport as planned.\',
                  ],
                  [
                    \'Fare\',
                    \'The applicable fare set by Naoero Airlines for travel contemplated in a Reservation (at the time of payment for the Reservation), along with applicable fees and surcharges, and any taxes imposed by governments on the travel to which the Reservation relates.\',
                  ],
                  [
                    \'Fare Rules\',
                    "The rules set by Naoero Airlines in relation to its various fares, as set out in Naoero Airlines\' Website, to which you agree when you purchase a flight on Naoero Airlines\' Website, or as explained to you by an Authorised Agent.",
                  ],
                  [
                    \'Flight Coupon\',
                    "That portion of the Ticket that bears the notation \'good for passage\', or in the case of an Electronic Ticket, the Electronic Coupon, and indicates the particular places between which you are entitled to be carried.",
                  ],
                  [\'IATA\', \'The International Air Transport Association.</td></tr>
<tr><td class="border border-slate-200 p-2 font-semibold">International Travel</td><td class="border border-slate-200 p-2">Travel from one country to another.\',
                  ],
                  [
                    \'Itinerary\',
                    \'A document provided to you by Naoero Airlines or an Authorised Agent which details your flights with Naoero Airlines and the destination of these flights. Your Itinerary may be combined with an Electronic Ticket.\',
                  ],
                  [
                    \'Laws\',
                    \'Any laws of Australia and any other nation which apply to your travel with Naoero Airlines.\',
                  ],
                  [
                    \'Naoero Airlines\',
                    \'Naoero Air Corporation (trading as Naoero Airlines).\',
                  ],
                  [
                    \'Naoero Airlines Crew\',
                    \'The flight crew (pilots) and cabin crew of flights operated by Naoero Airlines.\',
                  ],
                  [
                    \'Naoero Airlines Staff\',
                    \'Employees, contractors and agents of Naoero Airlines, and includes Naoero Airlines Crew.\',
                  ],
                  [
                    \'Naoero Airlines Services\',
                    \'The services described in Paragraph 2.1.\',
                  ],
                  [
                    \'Paper Ticket\',
                    \'A valid ticket issued in physical form by Naoero Airlines or an Authorised Agent.\',
                  ],
                  [
                    \'Passenger\',
                    \'A person who has purchased a Ticket and who travels with Naoero Airlines.\',
                  ],
                  [
                    \'Policies\',
                    "Naoero Airlines\' policies in relation to various aspects of travel, including Baggage, Passengers requiring special assistance and Passengers requiring medical clearance to travel. Naoero Airlines\' Policies are available on Naoero Airlines\' Website or by contacting Naoero Airlines\' Passenger Contact Centre.",
                  ],
                  [
                    \'Reservation\',
                    \'A booking on a Naoero Airlines flight and is confirmed only when recorded and paid for and accepted by Naoero Airlines.\',
                  ],
                  [
                    \'SDR\',
                    \'A Special Drawing Right, a composite unit of currency that is the official unit of exchange of the International Monetary Fund.\',
                  ],
                  [
                    \'Stopover\',
                    \'A deliberate interruption of a journey by the passenger, at a point between the place of departure and the place of destination, which has been agreed to in advance by Naoero Airlines.\',
                  ],
                  [
                    \'Tariff\',
                    \'The published Fares, charges and/or related conditions of carriage of an airline.\',
                  ],
                  [
                    \'Ticket\',
                    \'A document which validly entitles your travel on Naoero Airlines Services and includes an Electronic Ticket and a Paper Ticket.\',
                  ],
                  [
                    \'Ticketing Time Limit\',
                    \'The time limit set by Naoero Airlines within which you must pay the Fare for a Reservation.\',
                  ],
                  [\'us, we, our\', \'Naoero Airlines.</td></tr>
</tbody></table>';

        LegalDocument::updateOrCreate(
            ['slug' => 'conditions-of-carriage'],
            [
                'title' => 'Conditions of Carriage',
                'heading' => 'Conditions Of Carriage',
                'subheading' => 'Please take a moment to read our conditions of carriage prior to booking',
                'badge_text' => 'About Us > Travel Info > Condition Of Carriage',
                'banner_image' => '/assets/home/banner.png',
                'pdf_url' => 'https://www.nauruair.com/library/files/nauruairlines-conditionsofcarriage-01nov24-documentnumberna-exo-py-001.pdf',
                'contact_email' => 'reservations@naoeroairlines.com.au',
                'summary' => 'These Conditions of Carriage form a binding contract between you and Naoero Airlines in relation to passenger and baggage transport on scheduled and charter flight services.',
                'sections' => $carriageSections,
                'content' => $carriageHtml,
                'last_reviewed_at' => '2026-08-18',
                'next_review_at' => '2027-01-01',
                'is_active' => true,
            ]
        );
    }
}
