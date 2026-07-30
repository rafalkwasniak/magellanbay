<?php

namespace App\Console\Commands;

use App\Services\SubscriptionLifecycle;
use Illuminate\Console\Command;

/**
 * Codzienny przegląd abonamentów: przypomnienia przed terminem oraz zamek po
 * karencji. Cała logika (i idempotencja) siedzi w `SubscriptionLifecycle` —
 * komenda jest tylko wejściem z crona, żeby dała się też odpalić z ręki.
 */
class CheckSubscriptions extends Command
{
    protected $signature = 'subscriptions:check';

    protected $description = 'Wysyła przypomnienia o kończącym się abonamencie i gasi funkcje po karencji';

    public function handle(SubscriptionLifecycle $lifecycle): int
    {
        $result = $lifecycle->run();

        $this->info("Przypomnienia: {$result['reminders']}, wygaszone sklepy: {$result['locked']}.");

        return self::SUCCESS;
    }
}
