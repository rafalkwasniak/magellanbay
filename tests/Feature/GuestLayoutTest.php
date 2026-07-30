<?php

namespace Tests\Feature;

use App\Enums\LegalDocumentType;
use App\Models\LegalDocument;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Ekrany dla niezalogowanych muszą mieć drogę powrotu na stronę główną.
 * Rafał wyłapał, że z logowania nie dało się nigdzie wyjść — logo w layoucie
 * `guest` było zwykłym tekstem, w odróżnieniu od layoutu stron publicznych.
 */
class GuestLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_screens_have_a_clickable_logo(): void
    {
        foreach ([route('login'), route('register')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('href="'.url('/').'"', false);
        }
    }

    public function test_legal_pages_have_a_clickable_logo(): void
    {
        LegalDocument::create([
            'type' => LegalDocumentType::Terms,
            'version' => 'v1',
            'content' => 'Treść regulaminu',
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('href="'.url('/').'"', false);
    }
}
