<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zamyka rejestrację sprzedawców, gdy admin przestawi przełącznik w konsoli.
 *
 * Middleware, a NIE warunek w kontrolerze: rejestracja ma dwie trasy (formularz
 * i zapis), a bramka postawiona w jednej z nich prędzej czy później rozjechałaby
 * się z drugą — i zamknięte drzwi dałoby się obejść, wysyłając POST wprost.
 *
 * Odpowiadamy 503, nie 403: to stan CHWILOWY. 403 mówi „nie wolno ci",
 * a przeglądarki i wyszukiwarki czytają 503 jako „wróć później" — o to chodzi.
 */
class EnsureRegistrationIsOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PlatformSetting::registrationOpen()) {
            return $next($request);
        }

        return response()->view('auth.registration-closed', [
            // Gdy jest komunikat o przerwie, mówimy nim — zamknięta rejestracja
            // i przerwa techniczna to zwykle ta sama sprawa, a dwa różne teksty
            // na dwóch ekranach kazałyby zgadywać, który jest aktualny.
            'notice' => PlatformSetting::maintenanceNotice(),
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
