<?php

namespace App\Services\Finance;

use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;

class ReceiptNumberService
{
    /**
     * DOIT être appelé à l'intérieur d'une transaction.
     * Garantit une séquence continue et sans trou par école et par année civile.
     */
    public function next(int $schoolId, string $type = 'receipt', string $prefix = 'REC'): string
    {
        $year = (int) now()->format('Y');

        DocumentSequence::withoutGlobalScopes()->firstOrCreate(
            ['school_id' => $schoolId, 'type' => $type, 'year' => $year],
            ['last_number' => 0]
        );

        $seq = DocumentSequence::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('type', $type)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        $next = $seq->last_number + 1;
        $seq->update(['last_number' => $next]);

        return sprintf('%s-%d-%05d', $prefix, $year, $next);
    }
}