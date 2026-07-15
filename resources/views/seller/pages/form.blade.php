<x-layouts.panel :title="$page->exists ? 'Edytuj stronę' : 'Nowa strona'">
    <x-slot:heading>{{ $page->exists ? 'Edytuj stronę' : 'Nowa strona' }}</x-slot:heading>

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="lg:col-span-8">
            <form method="POST"
                action="{{ $page->exists ? route('seller.pages.update', $page) : route('seller.pages.store') }}"
                class="space-y-6" novalidate data-validate>
                @csrf

                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Treść strony</h2>
                    <p class="mt-1 text-sm text-stone-500">Tytuł i treść widoczne dla klientów w menu i stopce sklepu.</p>

                    <div class="mt-6 space-y-5">
                        <div>
                            <label for="title" class="block text-sm font-medium text-stone-700">Tytuł</label>
                            @if ($page->is_system)
                                {{-- Regulamin: tytuł jest stały (strona systemowa). --}}
                                <input id="title" type="text" value="{{ $page->title }}" disabled
                                    class="mt-1.5 block w-full cursor-not-allowed rounded-2xl border border-stone-200 bg-stone-100 px-4 py-3 text-sm text-stone-500 shadow-sm">
                                <p class="mt-1.5 text-xs text-stone-400">Tytuł strony systemowej jest stały — możesz zmienić tylko treść i kolejność.</p>
                            @else
                                <input id="title" name="title" type="text" required
                                    value="{{ old('title', $page->title) }}"
                                    data-msg-required="Podaj tytuł strony."
                                    class="mt-1.5 block w-full rounded-2xl border border-stone-200 bg-white/80 px-4 py-3 text-sm shadow-sm transition focus:border-amber-500 focus:outline-none focus:ring-4 focus:ring-amber-500/15">
                                @error('title')
                                    <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-stone-700">Treść</label>
                            <x-rich-editor name="content" :value="old('content', $page->content)" ai-field="page_content" :max="config('pages.content_max')">Napisz treść strony — np. zasady dostawy, zwrotów albo słowo o sklepie.</x-rich-editor>
                            @error('content')
                                <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                    <h2 class="font-semibold text-stone-900">Widoczność</h2>
                    @if ($page->is_system)
                        <p class="mt-3 text-sm text-stone-500">Regulamin jest zawsze widoczny w sklepie — nie można go ukryć.</p>
                    @else
                        <div class="mt-4 space-y-4">
                            <label class="flex items-start gap-3 text-sm text-stone-600">
                                <input type="checkbox" name="published" value="1" class="mt-0.5 shrink-0"
                                    @checked(old('published', $page->exists ? $page->published : true))>
                                <span>
                                    <span class="font-medium text-stone-800">Opublikowana</span> — widoczna w menu i stopce sklepu. Odznacz, aby ukryć stronę w przygotowaniu.
                                </span>
                            </label>
                            <label class="flex items-start gap-3 text-sm text-stone-600">
                                <input type="checkbox" name="show_on_homepage" value="1" class="mt-0.5 shrink-0"
                                    @checked(old('show_on_homepage', $page->show_on_homepage))>
                                <span>
                                    <span class="font-medium text-stone-800">Wyróżnij na stronie głównej</span> — pokaż zajawkę tej strony pod ofertą, obok innych wyróżnionych treści.
                                    @isset($homepage)
                                        @php($slotsFull = $homepage['count'] >= $homepage['limit'] && ! old('show_on_homepage', $page->show_on_homepage))
                                        <span class="mt-1 block text-xs {{ $slotsFull ? 'text-rose-600' : 'text-stone-400' }}">
                                            Zajęte {{ $homepage['count'] }} z {{ $homepage['limit'] }} miejsc na stronie głównej.@if ($slotsFull) Odznacz inną stronę, aby zwolnić miejsce.@endif
                                        </span>
                                    @endisset
                                </span>
                            </label>
                            @error('show_on_homepage')
                                <p class="text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-between gap-3">
                    <a href="{{ route('seller.pages.index') }}" class="text-sm font-medium text-stone-500 transition hover:text-stone-800">← Wróć do listy</a>
                    <button type="submit"
                        class="rounded-2xl bg-gradient-to-br from-amber-500 to-rose-500 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-rose-500/20 transition hover:brightness-105 focus:outline-none focus:ring-4 focus:ring-amber-500/25">
                        {{ $page->exists ? 'Zapisz zmiany' : 'Dodaj stronę' }}
                    </button>
                </div>
            </form>
        </div>

        <aside class="lg:col-span-4 space-y-6">
            <div class="rounded-3xl border border-white/60 bg-white/70 p-6 backdrop-blur">
                <h2 class="font-semibold text-stone-900">Wskazówki</h2>
                <ul class="mt-4 space-y-3 text-sm text-stone-500">
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">✍️</span>
                        <span>Pisz prosto i konkretnie — klient szuka odpowiedzi, nie eseju.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">✨</span>
                        <span>Napisz szkic i użyj <span class="font-medium text-stone-700">Popraw przez AI</span> — poprawi styl i interpunkcję.</span>
                    </li>
                    <li class="flex gap-3">
                        <span class="mt-0.5 shrink-0 text-amber-500">🔗</span>
                        <span>Adres strony powstaje z tytułu automatycznie — nie musisz się nim zajmować.</span>
                    </li>
                    {{-- Regulaminu nie da się wyróżnić, więc nie kuśmy go tą wskazówką. --}}
                    @unless ($page->is_system)
                        <li class="flex gap-3">
                            <span class="mt-0.5 shrink-0 text-amber-500">⭐</span>
                            <span>Stronę, która opowiada o Tobie — wywiad, spotkanie autorskie, słowo o sobie — <span class="font-medium text-stone-700">wyróżnij na stronie głównej</span>. Zajawka stanie pod ofertą.</span>
                        </li>
                    @endunless
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.panel>
