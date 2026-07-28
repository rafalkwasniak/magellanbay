<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Sklep wyczerpał tygodniowy limit zadań AI. Niesie datę odnowienia, bo sam
 * komunikat „limit wyczerpany" zostawia sprzedawcę bez odpowiedzi na jedyne
 * pytanie, jakie w tej chwili ma: kiedy znów będzie mógł kliknąć.
 */
class AiQuotaExceededException extends RuntimeException
{
    public function __construct(
        public readonly int $limit,
        public readonly \Carbon\CarbonInterface $resetsAt,
    ) {
        parent::__construct('Wyczerpano tygodniowy limit zadań AI ('.$limit.').');
    }
}
