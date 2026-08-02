<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use App\Http\Requests\Auth\PasswordResetRequest;
use App\Models\User;
use App\Services\ActivationMailer;
use App\Services\PasswordResetMailer;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Odzyskiwanie hasła do konta w centrali (sprzedawca, administrator).
 *
 * ZASADA NADRZĘDNA: formularz odpowiada TAK SAMO niezależnie od tego, czy konto
 * o podanym adresie istnieje. Inaczej stałby się wyszukiwarką kont — ktoś mógłby
 * sprawdzać listę adresów i dowiadywać się, kto sprzedaje na Kramio. Dlatego
 * komunikat jest zawsze ten sam, a różnicę widać wyłącznie w skrzynce odbiorcy.
 *
 * Konto ZAŁOŻONE, ALE NIEAKTYWOWANE dostaje mail AKTYWACYJNY zamiast resetu —
 * takie konto nie ma jeszcze hasła, więc „ustaw nowe" byłoby myleniem. Z zewnątrz
 * jest to nierozróżnialne, bo komunikat na ekranie się nie zmienia.
 */
class PasswordResetController extends Controller
{
    public function create(): Renderable
    {
        return view('auth.password-request');
    }

    public function store(
        PasswordResetLinkRequest $request,
        PasswordResetMailer $reset,
        ActivationMailer $activation,
    ): RedirectResponse {
        $user = User::where('email', $request->string('email'))->first();

        if ($user !== null) {
            $user->isActivated()
                ? $reset->send($user)
                : $activation->send($user);
        }

        return back()->with('status', 'Jeśli konto o tym adresie istnieje, wysłaliśmy na nie wiadomość z dalszymi krokami. Sprawdź skrzynkę.');
    }

    public function edit(Request $request, string $token): Renderable
    {
        return view('auth.password-reset', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(PasswordResetRequest $request): RedirectResponse
    {
        $status = Password::broker('users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->string('password')),
                    // Nowy token „zapamiętaj mnie" unieważnia ciasteczka na
                    // urządzeniach, na których ktoś został zalogowany starym
                    // hasłem — o to właśnie chodzi, gdy hasło mogło wyciec.
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => 'Link wygasł lub jest nieprawidłowy. Poproś o nowy.',
            ]);
        }

        return redirect()->route('login')->with('status', 'Hasło zostało zmienione — możesz się zalogować.');
    }
}
