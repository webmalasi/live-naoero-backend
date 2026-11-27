<?php

namespace Tests\Feature\Api;

use App\Models\Destination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestinationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed destinations
        Destination::create([
            'name' => 'Brisbane',
            'slug' => 'brisbane',
            'country' => 'Australia',
            'flag' => '🇦🇺',
            'thumbnail' => 'https://example.com/brisbane-thumb.jpg',
            'banner_image' => 'https://example.com/brisbane-banner.jpg',
            'short_description' => "Discover Queensland's capital city",
            'quote' => 'Queensland is for you.',
            'discover_heading' => 'Discover Brisbane',
            'intro' => 'Brisbane is the vibrant capital of Queensland.',
            'banner_text' => 'Explore today!',
            'is_featured' => true,
            'is_active' => true,
            'sort_order' => 1,
            'why_visit' => [
                ['title' => 'Stunning Weather', 'description' => 'Warm climate all year round.'],
            ],
            'things_to_do' => [
                ['title' => 'Southbank', 'description' => 'Walk along the river.', 'images' => []],
            ],
            'stays' => [
                ['title' => 'Gambaro Hotel', 'description' => 'Sophisticated rooms.'],
            ],
        ]);

        Destination::create([
            'name' => 'Naoero',
            'slug' => 'naoero',
            'country' => 'Republic of Naoero',
            'flag' => '🇳🇷',
            'short_description' => 'A hidden gem in the Pacific.',
            'is_featured' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        Destination::create([
            'name' => 'Draft City',
            'slug' => 'draft-city',
            'country' => 'Unknown',
            'is_featured' => false,
            'is_active' => false,
            'sort_order' => 99,
        ]);
    }

    public function test_can_list_active_destinations()
    {
        $response = $this->getJson('/api/destinations');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2,
            ])
            ->assertJsonFragment(['name' => 'Brisbane', 'slug' => 'brisbane'])
            ->assertJsonFragment(['name' => 'Naoero', 'slug' => 'naoero'])
            ->assertJsonMissing(['name' => 'Draft City']);
    }

    public function test_can_filter_featured_destinations_for_home_explore_section()
    {
        $response = $this->getJson('/api/destinations?featured=1');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);

        $featuredResponse = $this->getJson('/api/destinations/featured');
        $featuredResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);
    }

    public function test_can_get_single_destination_detail_by_slug()
    {
        $response = $this->getJson('/api/destinations/brisbane');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Brisbane',
                    'slug' => 'brisbane',
                    'country' => 'Australia',
                    'discover_heading' => 'Discover Brisbane',
                    'intro' => 'Brisbane is the vibrant capital of Queensland.',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'slug',
                    'name',
                    'country',
                    'flag',
                    'image',
                    'thumbnail',
                    'banner_image',
                    'short_description',
                    'quote',
                    'discoverHeading',
                    'intro',
                    'banner',
                    'whyVisit',
                    'thingsToDo',
                    'stays',
                    'otherDestinations',
                ],
            ]);
    }

    public function test_api_formats_nested_images_with_full_urls()
    {
        $dest = Destination::create([
            'name' => 'Kiritimati Island',
            'slug' => 'kiritimati-island',
            'country' => 'Kiribati',
            'is_featured' => true,
            'is_active' => true,
            'why_visit' => [
                ['title' => 'Birdwatching', 'description' => 'Sanctuary of birds', 'image' => 'destinations/why_visit/bird.jpg'],
            ],
            'things_to_do' => [
                [
                    'title' => 'Fly Fishing',
                    'description' => 'Bonefish angling.',
                    'images' => [
                        'destinations/activities/fish1.jpg',
                        'https://images.unsplash.com/photo-fish.jpg',
                    ],
                ],
            ],
            'stays' => [
                ['title' => 'Lagoon View Resort', 'description' => 'By the beach.', 'image' => 'destinations/stays/hotel.jpg'],
            ],
        ]);

        $response = $this->getJson('/api/destinations/kiritimati-island');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertStringContainsString('destinations/why_visit/bird.jpg', $data['whyVisit'][0]['image']);
        $this->assertStringStartsWith('http', $data['whyVisit'][0]['image']);

        $this->assertStringContainsString('destinations/activities/fish1.jpg', $data['thingsToDo'][0]['images'][0]);
        $this->assertStringStartsWith('http', $data['thingsToDo'][0]['images'][0]);
        $this->assertEquals('https://images.unsplash.com/photo-fish.jpg', $data['thingsToDo'][0]['images'][1]);

        $this->assertStringContainsString('destinations/stays/hotel.jpg', $data['stays'][0]['image']);
        $this->assertStringStartsWith('http', $data['stays'][0]['image']);
    }

    public function test_returns_404_for_non_existent_destination()
    {
        $response = $this->getJson('/api/destinations/non-existent-city');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Destination not found.',
            ]);
    }
}
