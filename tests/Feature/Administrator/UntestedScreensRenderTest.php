<?php

namespace Tests\Feature\Administrator;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ekrany konsoli admina, których dotąd nie otwierał ŻADEN test.
 *
 * Powód powstania (2026-08-24): ekran „Sprawdź skrzynkę" po rejestracji klienta
 * wywracał się błędem składni Blade przez sześć tygodni, bo jedyny test na jego
 * ścieżce sprawdzał `assertRedirect` na ten adres i nigdy go nie renderował.
 * Przegląd po tamtej awarii wykazał, że te dwa ekrany też nikt nie otwierał.
 *
 * Test jest celowo płytki — nie sprawdza treści, tylko to, że widok w ogóle się
 * kompiluje i wykonuje. Ta klasa błędów (osierocony `@endif`, literówka
 * w nazwie zmiennej) nie daje o sobie znać inaczej niż przez otwarcie strony.
 */
class UntestedScreensRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_platform_mailing_form_renders(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('administrator.mailings.create'))
            ->assertOk();
    }

    public function test_mail_template_preview_renders(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('administrator.mail.preview', 'aktywacja'))
            ->assertOk();
    }

    public function test_unknown_mail_template_is_not_found(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('administrator.mail.preview', 'nie-ma-takiego'))
            ->assertNotFound();
    }
}
