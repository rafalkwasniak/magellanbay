<?php

namespace App\Http\Controllers;

use App\Enums\ConsentChannel;
use App\Http\Requests\ProfileRequest;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Edycja własnego profilu zalogowanego użytkownika (sprzedawca/admin): dane z
 * tabeli users, awatar i zmiana hasła. E-mail celowo nieedytowalny (zmiana
 * adresu logowania to osobny, późniejszy temat).
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Renderable
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->fill($request->safe()->only(['name', 'surname', 'email', 'phone']));

        // Hasło zmieniamy tylko gdy podano nowe (cast 'hashed' zahashuje przy zapisie).
        if ($request->filled('password')) {
            $user->password = (string) $request->input('password');
        }

        if ($request->boolean('remove_avatar') && $user->avatar_path !== null) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path !== null) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $request->file('avatar')->store('users/'.$user->id, 'public');
        }

        $user->save();

        // Zgoda marketingowa: zapisujemy TYLKO przy faktycznej zmianie stanu.
        // Inaczej każde „Zapisz" w profilu przestemplowywałoby `granted_at` i
        // `ip_address` na dzisiejsze — i przepadałby dowód, KIEDY zgoda naprawdę
        // padła (RODO art. 7 każe wykazać właśnie to). Ta sama pułapka, którą
        // pilnujemy przy zgodach klientów sklepu.
        $wants = $request->boolean('marketing');

        if ($wants !== $user->hasMarketingConsent()) {
            $user->setMarketingConsent(ConsentChannel::Email, $wants, $request->ip());
        }

        return redirect()->route('profile.edit')->with('success', 'Zapisano dane profilu.');
    }
}
