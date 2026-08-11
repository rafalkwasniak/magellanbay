<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrator\PlatformSettingsRequest;
use App\Models\PlatformSetting;
use App\Support\PlatformHealth;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;

/**
 * Konsola admina — ustawienia platformy.
 *
 * Dwie rzeczy, celowo na jednym ekranie: STAN (czy coś się pali) i PRZEŁĄCZNIKI
 * (czym można to ugasić bez wgrywania kodu). Rozdzielenie ich znaczyłoby, że w
 * środku awarii trzeba wiedzieć, dokąd iść.
 *
 * Świadomie NIE ma tu edycji cennika, progów ani danych firmy — te żyją w
 * `config/` (FOUNDATION sek. 5) i zmieniają się raz na kilka lat. Przeniesienie
 * ich do bazy dałoby drugie źródło prawdy obok pliku.
 */
class SettingsController extends Controller
{
    public function index(): Renderable
    {
        return view('administrator.settings.index', [
            'integrations' => PlatformHealth::integrations(),
            'queue' => PlatformHealth::queue(),
            'runtime' => PlatformHealth::runtime(),
            // NIE 'errors': ta nazwa jest zarezerwowana dla worka bledow walidacji
            // Laravela ($errors->getBag()). Nadpisanie jej wywraca kazdy @error w widoku.
            'logErrors' => PlatformHealth::recentErrors(),
            'registrationOpen' => PlatformSetting::registrationOpen(),
            'maintenanceNotice' => PlatformSetting::maintenanceNotice(),
        ]);
    }

    public function update(PlatformSettingsRequest $request): RedirectResponse
    {
        // Checkbox niezaznaczony w ogóle nie przychodzi w żądaniu — stąd `boolean()`,
        // a nie odczyt wartości: brak klucza musi znaczyć „zamknięte", nie „bez zmian".
        PlatformSetting::put(
            PlatformSetting::REGISTRATION_OPEN,
            $request->boolean('registration_open') ? '1' : '0'
        );

        PlatformSetting::put(
            PlatformSetting::MAINTENANCE_NOTICE,
            $request->string('maintenance_notice')->toString() ?: null
        );

        return redirect()
            ->route('administrator.settings.index')
            ->with('success', 'Zapisano ustawienia platformy.');
    }
}
