<?php

namespace App\Services\Finance;

use App\Models\CashSession;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CashSessionService
{
    public function open(int $schoolId, int $userId, int $openingFloat = 0): CashSession
    {
        $existing = CashSession::where('school_id', $schoolId)
            ->where('user_id', $userId)->where('status', 'open')->first();

        if ($existing) {
            throw new RuntimeException(
                'Une caisse est déjà ouverte depuis le ' . $existing->opened_at->format('d/m/Y H:i') . '.'
            );
        }

        return CashSession::create([
            'school_id'     => $schoolId,
            'user_id'       => $userId,
            'opened_at'     => now(),
            'opening_float' => $openingFloat,
            'status'        => 'open',
        ]);
    }

    public function close(CashSession $session, int $countedCash, ?string $notes = null): CashSession
    {
        return DB::transaction(function () use ($session, $countedCash, $notes) {
            $session = CashSession::lockForUpdate()->findOrFail($session->id);

            if (! $session->isOpen()) {
                throw new RuntimeException('Cette caisse est déjà clôturée.');
            }

            $expected = $session->expectedCash();

            $session->update([
                'closed_at'     => now(),
                'expected_cash' => $expected,
                'counted_cash'  => $countedCash,
                'variance'      => $countedCash - $expected,   // négatif = manquant
                'status'        => 'closed',
                'notes'         => $notes,
            ]);

            return $session;
        });
    }

    public function currentFor(int $schoolId, int $userId): ?CashSession
    {
        return CashSession::where('school_id', $schoolId)
            ->where('user_id', $userId)->where('status', 'open')
            ->latest('opened_at')->first();
    }
}