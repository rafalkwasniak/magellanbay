<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ThrottlesLogins;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    use ThrottlesLogins;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'adres e-mail',
            'password' => 'hasło',
        ];
    }

    /**
     * Próba uwierzytelnienia na podstawie danych z żądania.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            $this->hitRateLimiter();

            throw ValidationException::withMessages([
                'email' => 'Nieprawidłowy adres e-mail lub hasło.',
            ]);
        }

        $this->clearRateLimiter();
    }

    /**
     * Klucz throttlingu: e-mail + IP.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
