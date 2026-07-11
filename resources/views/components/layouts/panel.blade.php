<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ($title ?? 'Panel') . ' · ' . config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-stone-100 text-stone-800 antialiased">
    <x-toasts />

    @php
        $user = auth()->user();
        $isAdmin = $user?->isAdmin();
        $area = $isAdmin ? 'Administrator' : 'Sprzedawca';
        $nav = $isAdmin ? [
            ['label' => 'Pulpit', 'route' => 'administrator.dashboard', 'icon' => '🏠'],
            ['label' => 'Sklepy', 'route' => null, 'icon' => '🛍️'],
            ['label' => 'Sprzedawcy', 'route' => null, 'icon' => '👥'],
            ['label' => 'Zamówienia', 'route' => null, 'icon' => '📦'],
            ['label' => 'Pakiety', 'route' => null, 'icon' => '✨'],
            ['label' => 'Ustawienia', 'route' => null, 'icon' => '⚙️'],
        ] : [
            ['label' => 'Pulpit', 'route' => 'seller.dashboard', 'icon' => '🏠'],
            ['label' => 'Mój sklep', 'route' => 'seller.shop.edit', 'icon' => '🛍️'],
            ['label' => 'Produkty', 'route' => 'seller.products.index', 'active' => 'seller.products.*', 'icon' => '🏷️'],
            ['label' => 'Zamówienia', 'route' => 'seller.orders.index', 'active' => 'seller.orders.*', 'icon' => '📦', 'badge' => (int) ($user->shop?->unseen_orders_count ?? 0)],
            ['label' => 'Wygląd', 'route' => 'seller.appearance.edit', 'icon' => '🎨'],
            ['label' => 'Ustawienia', 'route' => 'seller.settings.edit', 'icon' => '⚙️'],
            ['label' => 'Integracje', 'route' => 'seller.integrations.edit', 'icon' => '🔌'],
        ];
        $initials = strtoupper(mb_substr($user->name ?? '?', 0, 1) . mb_substr($user->surname ?? '', 0, 1));
        $avatar = $user?->avatar_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar_path) : null;
    @endphp

    <div class="relative min-h-full overflow-hidden">
        <div class="pointer-events-none absolute -left-40 top-0 h-96 w-96 rounded-full bg-amber-300 opacity-25 blur-3xl"></div>
        <div class="pointer-events-none absolute right-0 top-1/2 h-96 w-96 rounded-full bg-rose-300 opacity-20 blur-3xl"></div>

        <div class="relative flex min-h-full">
            {{-- Sidebar --}}
            <aside class="hidden w-64 shrink-0 flex-col lg:flex">
                {{-- Nagłówek marki o wysokości h-16 — zrównuje logo z nagłówkiem strony,
                     a treść poniżej z głównym boxem po prawej. --}}
                <div class="flex h-16 shrink-0 items-center gap-2 px-4">
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-rose-500 text-white">◐</span>
                    <span class="text-lg font-semibold tracking-tight text-stone-900">{{ config('app.name') }}</span>
                    <span class="ml-auto rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-medium text-stone-500">{{ $area }}</span>
                </div>

                <div class="px-4 pb-4 pt-6">
                <div class="flex items-center gap-3 rounded-2xl bg-white/70 p-3 backdrop-blur">
                    <a href="{{ route('profile.edit') }}" class="flex min-w-0 flex-1 items-center gap-3" title="Mój profil">
                        @if ($avatar)
                            <img src="{{ $avatar }}" alt="Awatar" class="h-9 w-9 shrink-0 rounded-full object-cover">
                        @else
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-rose-500 text-sm font-semibold text-white">{{ $initials }}</span>
                        @endif
                        <div class="min-w-0 text-sm">
                            <p class="truncate font-medium text-stone-900">{{ $user->name }} {{ $user->surname }}</p>
                            <p class="text-stone-500">{{ $area }}</p>
                        </div>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                        @csrf
                        <button type="submit" title="Wyloguj"
                            class="rounded-lg px-2 py-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-700">⎋</button>
                    </form>
                </div>

                <nav class="mt-6 space-y-1 text-sm">
                    <x-panel-nav-items :items="$nav" />
                </nav>
                </div>
            </aside>

            {{-- Treść --}}
            <div class="flex-1">
                <header class="flex h-16 items-center justify-between px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" data-mobile-nav-open aria-label="Otwórz menu"
                            class="-ml-2 shrink-0 rounded-xl p-2 text-stone-600 transition hover:bg-white/70 lg:hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-6 w-6">
                                <path d="M3 6h18M3 12h18M3 18h18" />
                            </svg>
                        </button>
                        <h1 class="truncate text-lg font-semibold tracking-tight text-stone-900">{{ $heading ?? 'Dzień dobry, ' . $user->name }}</h1>
                    </div>
                    <div class="flex items-center gap-3">
                        {{ $actions ?? '' }}
                    </div>
                </header>

                <main class="p-6">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </div>

    {{-- Nawigacja mobilna: wysuwany panel (drawer). Widoczny tylko poniżej lg;
         sterowany przez resources/js/mobile-nav.js (zero zależności). --}}
    <div data-mobile-nav class="fixed inset-0 z-40 hidden lg:hidden">
        <div data-mobile-nav-backdrop class="absolute inset-0 bg-stone-900/30 backdrop-blur-sm"></div>
        <aside data-mobile-nav-panel
            class="absolute left-0 top-0 flex h-full w-72 max-w-[85%] -translate-x-full flex-col bg-stone-50 shadow-xl transition-transform duration-300 ease-out">
            <div class="flex h-16 shrink-0 items-center gap-2 px-4">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-rose-500 text-white">◐</span>
                <span class="text-lg font-semibold tracking-tight text-stone-900">{{ config('app.name') }}</span>
                <span class="ml-auto rounded-full bg-white/70 px-2 py-0.5 text-[11px] font-medium text-stone-500">{{ $area }}</span>
                <button type="button" data-mobile-nav-close aria-label="Zamknij menu"
                    class="rounded-lg p-1.5 text-stone-400 transition hover:bg-stone-200 hover:text-stone-700">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="h-5 w-5">
                        <path d="M18 6 6 18M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="px-4 pb-4 pt-4">
                <div class="flex items-center gap-3 rounded-2xl bg-white/70 p-3">
                    <a href="{{ route('profile.edit') }}" class="flex min-w-0 flex-1 items-center gap-3">
                        @if ($avatar)
                            <img src="{{ $avatar }}" alt="Awatar" class="h-9 w-9 shrink-0 rounded-full object-cover">
                        @else
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-rose-500 text-sm font-semibold text-white">{{ $initials }}</span>
                        @endif
                        <div class="min-w-0 text-sm">
                            <p class="truncate font-medium text-stone-900">{{ $user->name }} {{ $user->surname }}</p>
                            <p class="text-stone-500">{{ $area }}</p>
                        </div>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                        @csrf
                        <button type="submit" title="Wyloguj"
                            class="rounded-lg px-2 py-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-700">⎋</button>
                    </form>
                </div>

                <nav class="mt-6 space-y-1 text-sm">
                    <x-panel-nav-items :items="$nav" />
                </nav>
            </div>
        </aside>
    </div>

    @livewireScripts
</body>
</html>
