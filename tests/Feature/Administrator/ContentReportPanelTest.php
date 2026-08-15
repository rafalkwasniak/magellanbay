<?php

namespace Tests\Feature\Administrator;

use App\Enums\ContentReportCategory;
use App\Enums\ContentReportStatus;
use App\Models\ContentReport;
use App\Models\EmailMessage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konsola admina — zgłoszenia treści bezprawnych.
 *
 * Rozstrzygnięcie jest ruchem w JEDNĄ stronę: po nim wychodzą pisma na zewnątrz,
 * więc drugie kliknięcie nie może wysłać sprzecznej decyzji.
 */
class ContentReportPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * Sklep jest współdzielony między zgłoszeniami — dwa zgłoszenia na ten sam
     * sklep to zwyczajny przypadek, a osobny sklep na każde wywołanie wywracał
     * się na unikalnym slugu.
     */
    private function report(array $override = []): ContentReport
    {
        $shop = Shop::where('slug', 'bukiety')->first() ?? Shop::factory()->create(['slug' => 'bukiety']);

        $report = new ContentReport([
            'url' => 'https://bukiety.'.config('tenancy.central_domain').'/produkt/12-bukiet',
            'category' => ContentReportCategory::Counterfeit->value,
            'justification' => 'Zdjęcie pochodzi z mojego katalogu i nie wyrażałem zgody na jego użycie.',
            'reporter_email' => 'zglaszajacy@example.com',
            'good_faith' => true,
            ...$override,
        ]);
        $report->shop()->associate($shop);
        $report->save();

        return $report;
    }

    public function test_a_seller_cannot_reach_the_reports(): void
    {
        $seller = User::factory()->create();

        $this->actingAs($seller)->get(route('administrator.reports.index'))->assertForbidden();
    }

    public function test_the_list_puts_pending_reports_first(): void
    {
        $rozpatrzone = $this->report();
        $rozpatrzone->forceFill([
            'status' => ContentReportStatus::Rejected,
            'decision_reason' => 'Zgłaszający nie wykazał praw do zdjęcia.',
            'decided_at' => now(),
        ])->save();

        $nowe = $this->report(['reporter_email' => 'drugi@example.com']);

        $content = $this->actingAs($this->admin())
            ->get(route('administrator.reports.index'))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(
            strpos($content, 'zglaszajacy@example.com'),
            strpos($content, 'drugi@example.com'),
            'Nierozpatrzone zgłoszenie musi być wyżej niż rozstrzygnięte.'
        );
        $this->assertNotFalse(strpos($content, (string) $nowe->url));
    }

    public function test_the_search_narrows_the_list_down(): void
    {
        $this->report();
        $this->report(['reporter_email' => 'inny@example.com']);

        $content = $this->actingAs($this->admin())
            ->get(route('administrator.reports.index', ['szukaj' => 'inny@']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('inny@example.com', $content);
        $this->assertStringNotContainsString('zglaszajacy@example.com', $content);
    }

    /**
     * Numer sprawy wklejony w wyszukiwarkę ma trafiać w JEDNO zgłoszenie — po to
     * jest w temacie każdego maila. Przyjmujemy formy, w których człowiek go
     * naprawdę wkleja.
     */
    public function test_a_case_number_finds_exactly_one_report(): void
    {
        $pierwsze = $this->report();
        $drugie = $this->report(['reporter_email' => 'drugi@example.com']);

        // Formy Z PREFIKSEM są jednoznaczne — mają zawęzić listę do jednej sprawy.
        foreach ([$drugie->reference(), mb_strtolower($drugie->reference()), 'zg-'.$drugie->id] as $wpisane) {
            $content = $this->actingAs($this->admin())
                ->get(route('administrator.reports.index', ['szukaj' => $wpisane]))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString($drugie->reference(), $content, 'Wpisane '.$wpisane.' nie znalazło swojej sprawy.');
            $this->assertStringNotContainsString($pierwsze->reference(), $content, 'Wpisane '.$wpisane.' złapało cudzą sprawę.');
        }

        // Sama cyfra też trafia w sprawę, ale wyłączności tu NIE wymagamy i nie
        // można wymagać: „2" jest zwykłym ciągiem, więc pasuje też do adresów
        // w rodzaju `/produkt/12-bukiet`. Prefiks jest od precyzji, goła liczba
        // od wygody.
        $content = $this->actingAs($this->admin())
            ->get(route('administrator.reports.index', ['szukaj' => (string) $drugie->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($drugie->reference(), $content);
    }

    public function test_deciding_records_the_reason_and_sends_both_letters(): void
    {
        $report = $this->report();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('administrator.reports.decide', $report), [
                'status' => ContentReportStatus::Upheld->value,
                'decision_reason' => 'Zdjęcie jest identyczne z materiałem zgłaszającego, sprzedawca nie wykazał licencji.',
            ])
            ->assertRedirect(route('administrator.reports.show', $report));

        $report->refresh();
        $this->assertSame(ContentReportStatus::Upheld, $report->status);
        $this->assertNotNull($report->decided_at);
        $this->assertSame($admin->id, $report->decided_by);

        $this->assertSame(1, EmailMessage::where('subject', 'like', '%'.$report->reference().'%rozstrzygnięcie')->count());
        $this->assertSame(1, EmailMessage::where('subject', 'like', '%'.$report->reference().'%sklepie')->count());
    }

    /**
     * Brak wyboru rozstrzygnięcia łapie Form Request, a nie `forms.js` —
     * ten sprawdza każdy przycisk radio osobno i przy grupie zapalałby błąd
     * mimo dokonanego wyboru. Gdyby ktoś wrócił `required` na te radia, ekran
     * znów odmawiałby wysyłki poprawnie wypełnionego formularza.
     */
    public function test_a_decision_without_a_choice_is_rejected(): void
    {
        $report = $this->report();

        $this->actingAs($this->admin())
            ->post(route('administrator.reports.decide', $report), [
                'decision_reason' => 'Zdjęcie jest identyczne z materiałem zgłaszającego, sprzedawca nie wykazał licencji.',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(ContentReportStatus::New, $report->refresh()->status);
        $this->assertSame(0, EmailMessage::count());
    }

    public function test_an_empty_reason_is_rejected_also_when_dismissing(): void
    {
        $report = $this->report();

        $this->actingAs($this->admin())
            ->post(route('administrator.reports.decide', $report), [
                'status' => ContentReportStatus::Rejected->value,
                'decision_reason' => 'Nie.',
            ])
            ->assertSessionHasErrors('decision_reason');

        $this->assertSame(ContentReportStatus::New, $report->refresh()->status);
        $this->assertSame(0, EmailMessage::count());
    }

    public function test_a_report_cannot_be_decided_twice(): void
    {
        $report = $this->report();
        $admin = $this->admin();
        $decyzja = [
            'status' => ContentReportStatus::Upheld->value,
            'decision_reason' => 'Zdjęcie jest identyczne z materiałem zgłaszającego, sprzedawca nie wykazał licencji.',
        ];

        $this->actingAs($admin)->post(route('administrator.reports.decide', $report), $decyzja);

        // Drugie podejście — np. odświeżony formularz — próbuje odwrócić decyzję.
        $this->actingAs($admin)->post(route('administrator.reports.decide', $report), [
            'status' => ContentReportStatus::Rejected->value,
            'decision_reason' => 'Jednak jednak nie, zmieniam zdanie po namyśle nad sprawą.',
        ])->assertSessionHas('error');

        $this->assertSame(ContentReportStatus::Upheld, $report->refresh()->status);
        $this->assertSame(1, EmailMessage::where('subject', 'like', '%rozstrzygnięcie')->count());
    }

    /**
     * Kolory plakietek biorą się z enuma, ale zadziałają tylko wtedy, gdy klasy
     * są w ZBUDOWANYM CSS — klasa spoza buildu nic nie robi po cichu i plakietka
     * wychodzi bez tła. Test czyta arkusz, bo tego nie widać po HTML-u.
     */
    public function test_the_badge_classes_exist_in_the_built_stylesheet(): void
    {
        $css = '';
        foreach (glob(public_path('build/assets/*.css')) as $file) {
            $css .= file_get_contents($file);
        }

        $this->assertNotSame('', $css, 'Brak zbudowanego arkusza — uruchom build.');

        foreach (ContentReportStatus::cases() as $case) {
            foreach ($case->badgeClasses() as $class) {
                $this->assertStringContainsString(
                    '.'.str_replace(['-', '/'], ['-', '\\/'], $class),
                    $css,
                    "Klasa {$class} nie istnieje w zbudowanym CSS — plakietka „{$case->label()}” wyjdzie bez koloru."
                );
            }
        }
    }

    public function test_the_menu_badge_counts_only_pending_reports(): void
    {
        $this->report();
        $this->report(['reporter_email' => 'drugi@example.com'])
            ->forceFill(['status' => ContentReportStatus::Upheld, 'decision_reason' => 'Uzasadnienie rozstrzygnięcia zgłoszenia.'])
            ->save();

        $this->actingAs($this->admin())
            ->get(route('administrator.dashboard'))
            ->assertOk()
            ->assertSee('Zgłoszenia');

        $this->assertSame(1, ContentReport::pending()->count());
    }
}
