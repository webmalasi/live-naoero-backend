<?php

namespace Tests\Feature\Api;

use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Testimonial::create([
            'name' => 'Nick Wade',
            'location' => 'Brisbane, Australia',
            'quote' => 'Flying with Naoero Airlines was a wonderful experience.',
            'image' => 'https://images.unsplash.com/photo-test1.jpg',
            'rating' => 5,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Testimonial::create([
            'name' => 'Natasha Reyes',
            'location' => 'Majuro, Marshall Islands',
            'quote' => 'The direct island connections made our family holiday seamless and stress-free.',
            'image' => 'https://images.unsplash.com/photo-test2.jpg',
            'rating' => 5,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        Testimonial::create([
            'name' => 'Inactive Reviewer',
            'location' => 'Hidden',
            'quote' => 'This is an inactive review.',
            'rating' => 3,
            'sort_order' => 99,
            'is_active' => false,
        ]);
    }

    public function test_can_list_active_testimonials_for_homepage(): void
    {
        $response = $this->getJson('/api/testimonials');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);

        $json = $response->json();
        $this->assertEquals('Nick Wade', $json['data'][0]['name']);
        $this->assertEquals('Brisbane, Australia', $json['data'][0]['location']);
        $this->assertNotEmpty($json['data'][0]['image']);
        $this->assertEquals('Natasha Reyes', $json['data'][1]['name']);
    }

    public function test_can_filter_testimonials_by_rating(): void
    {
        $response = $this->getJson('/api/testimonials?rating=5');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);
    }

    public function test_can_get_single_testimonial_by_id(): void
    {
        $testimonial = Testimonial::where('name', 'Nick Wade')->first();

        $response = $this->getJson("/api/testimonials/{$testimonial->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $testimonial->id,
                    'name' => 'Nick Wade',
                    'rating' => 5,
                ],
            ]);
    }
}
