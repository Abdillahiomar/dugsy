<?php

namespace App\Services\Finance;

use App\Models\AcademicYear;
use App\Models\CashSession;
use App\Models\PaymentReceipt;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\StudentPayment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentService
{
    public function __construct(private ReceiptNumberService $numbers) {}

    /**
     * Encaisse un montant global et le répartit sur les factures ouvertes.
     *
     * @param  array{reference?:string,paid_at?:string,note?:string,allocations?:array<int,int>}  $opts
     *         allocations : [student_invoice_id => montant]. Si absent → répartition FIFO par échéance.
     */
    public function collect(
        Student $student,
        AcademicYear $year,
        int $amount,
        string $method,
        int $receivedBy,
        array $opts = []
    ): PaymentReceipt {

        if ($amount <= 0) {
            throw new RuntimeException('Le montant doit être supérieur à zéro.');
        }
        if (! array_key_exists($method, PaymentReceipt::METHODS)) {
            throw new RuntimeException('Mode de règlement inconnu.');
        }

        $session = $this->requireOpenSession($student->school_id, $receivedBy);

        return DB::transaction(function () use ($student, $year, $amount, $method, $receivedBy, $opts, $session) {

            // Verrou pessimiste : empêche deux caissiers d'encaisser la même facture simultanément
            $invoices = StudentInvoice::where('school_id', $student->school_id)
                ->where('academic_year_id', $year->id)
                ->whereHas('studentSchoolYear', fn ($q) => $q->where('student_id', $student->id))
                ->open()
                ->orderBy('due_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($invoices->isEmpty()) {
                throw new RuntimeException("Aucune facture ouverte pour {$student->fullName()} sur l'année {$year->label}.");
            }

            $allocations = isset($opts['allocations']) && $opts['allocations']
                ? $this->validateManual($invoices, $opts['allocations'], $amount)
                : $this->allocateFifo($invoices, $amount);

            $receipt = PaymentReceipt::create([
                'school_id'        => $student->school_id,
                'academic_year_id' => $year->id,
                'student_id'       => $student->id,
                'cash_session_id'  => $session->id,
                'receipt_number'   => $this->numbers->next($student->school_id),
                'amount'           => $amount,
                'method'           => $method,
                'reference'        => $opts['reference'] ?? null,
                'paid_at'          => $opts['paid_at'] ?? now(),
                'received_by'      => $receivedBy,
                'note'             => $opts['note'] ?? null,
            ]);

            foreach ($allocations as $invoiceId => $allocated) {
                if ($allocated <= 0) continue;

                $invoice = $invoices->firstWhere('id', $invoiceId);

                StudentPayment::create([
                    'school_id'          => $student->school_id,
                    'payment_receipt_id' => $receipt->id,
                    'student_invoice_id' => $invoice->id,
                    'amount'             => $allocated,
                    'method'             => $method,
                    'reference'          => $opts['reference'] ?? null,
                    'paid_at'            => $receipt->paid_at,
                    'received_by'        => null,
                ]);

                $invoice->amount_paid += $allocated;
                $invoice->status = $this->resolveStatus($invoice);
                $invoice->save();
            }

            return $receipt->fresh(['lines.invoice', 'student']);
        });
    }

    /**
     * Répartition FIFO : la plus ancienne échéance d'abord, puis les suivantes,
     * y compris celles pas encore échues (choix produit : encaissement d'avance autorisé).
     */
    private function allocateFifo($invoices, int $amount): array
    {
        $out = [];
        $remaining = $amount;

        foreach ($invoices as $invoice) {
            if ($remaining <= 0) break;
            $take = min($invoice->balance(), $remaining);
            if ($take > 0) {
                $out[$invoice->id] = $take;
                $remaining -= $take;
            }
        }

        if ($remaining > 0) {
            $total = $invoices->sum(fn ($i) => $i->balance());
            throw new RuntimeException(sprintf(
                "Montant supérieur au total dû. Reste à payer sur l'année : %s DJF. Trop-perçu : %s DJF.",
                number_format($total, 0, ',', ' '),
                number_format($remaining, 0, ',', ' ')
            ));
        }

        return $out;
    }

    private function validateManual($invoices, array $manual, int $amount): array
    {
        $out = [];
        $sum = 0;

        foreach ($manual as $invoiceId => $value) {
            $value = (int) $value;
            if ($value <= 0) continue;

            $invoice = $invoices->firstWhere('id', (int) $invoiceId);
            if (! $invoice) {
                throw new RuntimeException("Facture #{$invoiceId} introuvable ou déjà soldée.");
            }
            if ($value > $invoice->balance()) {
                throw new RuntimeException(sprintf(
                    'Affectation de %s DJF sur « %s » alors que le reste dû est de %s DJF.',
                    number_format($value, 0, ',', ' '),
                    $invoice->invoice_number,
                    number_format($invoice->balance(), 0, ',', ' ')
                ));
            }
            $out[$invoice->id] = $value;
            $sum += $value;
        }

        if ($sum !== $amount) {
            throw new RuntimeException(sprintf(
                'La répartition (%s DJF) ne correspond pas au montant encaissé (%s DJF).',
                number_format($sum, 0, ',', ' '),
                number_format($amount, 0, ',', ' ')
            ));
        }

        return $out;
    }

    /** Annulation traçable. On ne supprime jamais un reçu. */
    public function void(PaymentReceipt $receipt, string $reason, int $userId): void
    {
        DB::transaction(function () use ($receipt, $reason, $userId) {
            $receipt = PaymentReceipt::lockForUpdate()->findOrFail($receipt->id);

            if ($receipt->isVoided()) {
                throw new RuntimeException('Ce reçu est déjà annulé.');
            }
            if ($receipt->cashSession && ! $receipt->cashSession->isOpen()) {
                throw new RuntimeException(
                    'La caisse de ce reçu est clôturée. Passez par une régularisation comptable.'
                );
            }

            foreach ($receipt->lines as $line) {
                $invoice = StudentInvoice::lockForUpdate()->find($line->student_invoice_id);
                if (! $invoice) continue;

                $invoice->amount_paid = max(0, $invoice->amount_paid - $line->amount);
                $invoice->status = $this->resolveStatus($invoice);
                $invoice->save();
            }

            $receipt->update([
                'voided_at'   => now(),
                'voided_by'   => $userId,
                'void_reason' => $reason,
            ]);
        });
    }

    public function resolveStatus(StudentInvoice $invoice): string
    {
        if ($invoice->amount_paid >= $invoice->amount_due) return 'paid';
        if ($invoice->amount_paid > 0) return 'partial';

        return $invoice->due_at && $invoice->due_at->isPast() ? 'overdue' : 'pending';
    }

    private function requireOpenSession(int $schoolId, int $userId): CashSession
    {
        $session = CashSession::where('school_id', $schoolId)
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        if (! $session) {
            throw new RuntimeException(
                "Aucune caisse ouverte. Ouvrez votre caisse avant d'encaisser."
            );
        }

        return $session;
    }
}