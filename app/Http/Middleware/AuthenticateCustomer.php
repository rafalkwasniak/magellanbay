<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wpuszcza wyłącznie zalogowanego klienta sklepu (guard `customer`). Gość leci na
 * storefrontowe logowanie (`/logowanie`) z zapamiętanym celem (redirect()->guest),
 * żeby po zalogowaniu wrócił tam, gdzie zmierzał — inaczej domyślny `auth` odesłałby
 * na logowanie sprzedawcy w centrali.
 */
class AuthenticateCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('customer')->check()) {
            return redirect()->guest('/logowanie');
        }

        return $next($request);
    }
}
