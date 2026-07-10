<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\DiscountType;
use App\Models\FeeStructure;
use App\Models\InstallmentPlan;
use App\Models\SchoolClass;
use App\Models\SchoolFeeConfig;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\StudentSchoolYear;
use Carbon\Carbon;

class EnrollmentService
{
    /**
     * Inscrit un élève et génère ses factures.
     */
    public function enroll(
        Student          $student,
        AcademicYear     $year,
        int              $schoolClassId,
        ?InstallmentPlan $plan,
        array            $discountIds = [],
        bool             $isReinscription = false
    ): StudentSchoolYear {
        $ssy = StudentSchoolYear::create([
            'student_id'       => $student->id,
            'academic_year_id' => $year->id,
            'school_class_id'  => $schoolClassId,
            'enrolled_at'      => now(),
            'status'           => 'enrolled',
        ]);

        // Charger explicitement après create() — les relations ne sont pas chargées automatiquement
        $ssy->load(['schoolClass.level']);

        $this->generateInvoices(
            $ssy,
            $student->school_id,
            $year,
            $schoolClassId,
            $plan,
            $discountIds,
            $isReinscription
        );

        return $ssy;
    }

    /**
     * Génère les factures pour une inscription.
     * Paramètres passés explicitement pour éviter tout problème de lazy load / Global Scope.
     */
    public function generateInvoices(
        StudentSchoolYear $ssy,
        int               $schoolId,
        AcademicYear      $year,
        int               $schoolClassId,
        ?InstallmentPlan  $plan,
        array             $discountIds = [],
        bool              $isReinscription = false
    ): void {
        // 1. Charger la classe (sans Global Scope pour éviter les conflits)
        $class = SchoolClass::withoutGlobalScopes()
            ->with('level')
            ->find($schoolClassId);

        if (! $class) return;

        // 2. Config générale de l'école pour cette année
        $config = SchoolFeeConfig::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $year->id)
            ->first();

        // 3. Frais de scolarité du niveau (AVANT $registrationFee car on s'en sert)
        $feeStructure = FeeStructure::withoutGlobalScopes()
            ->with('installmentPlan.items')
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $year->id)
            ->where('level_id', $class->level_id)
            ->first();

        // 4. Frais d'inscription/réinscription
        // Priorité : frais du niveau > frais global de l'école > 0
        $registrationFee = $isReinscription
            ? (int) ($feeStructure?->reinscription_fee
                ?: $config?->reinscription_fee
                ?: 0)
            : (int) ($feeStructure?->inscription_fee
                ?: $config?->inscription_fee
                ?: 0);

        // 5. Montant scolarité
        $tuitionAmount = (int) ($feeStructure?->amount ?? 0);

        // 6. Appliquer les remises
        if (! empty($discountIds)) {
            $discounts       = DiscountType::whereIn('id', $discountIds)->get();
            $tuitionAmount   = (int) $this->applyDiscounts($tuitionAmount, $discounts, 'tuition');
            $registrationFee = (int) $this->applyDiscounts($registrationFee, $discounts, 'inscription');
        }

        // ── Facture d'inscription / réinscription ─────────────────────────────
        if ($registrationFee > 0) {
            StudentInvoice::create([
                'student_school_year_id' => $ssy->id,
                'fee_structure_id'       => null,
                'invoice_number'         => $this->generateInvoiceNumber($schoolId, 'INS'),
                'amount_due'             => $registrationFee,
                'amount_paid'            => 0,
                'issued_at'              => now(),
                'due_at'                 => now()->addDays(15),
                'status'                 => 'unpaid',
                'label'                  => $isReinscription
                    ? 'Frais de réinscription'
                    : 'Frais d\'inscription',
            ]);
        }

        // ── Factures de scolarité ─────────────────────────────────────────────
        if ($tuitionAmount <= 0) return;

        // Résoudre le plan : paramètre > niveau > plan par défaut global > facture unique
        $usedPlan = $plan
            ?? $feeStructure?->installmentPlan
            ?? InstallmentPlan::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('is_default', true)
                ->with('items')
                ->first();

        if ($usedPlan && $usedPlan->items->isNotEmpty()) {
            foreach ($usedPlan->items as $item) {
                $amount  = (int) round($tuitionAmount * $item->percentage / 100);
                $dueDate = $this->resolveDueDate($item->due_month, $item->due_day, $year);

                StudentInvoice::create([
                    'student_school_year_id' => $ssy->id,
                    'fee_structure_id'       => $feeStructure?->id,
                    'invoice_number'         => $this->generateInvoiceNumber($schoolId, 'SCO'),
                    'amount_due'             => $amount,
                    'amount_paid'            => 0,
                    'issued_at'              => now(),
                    'due_at'                 => $dueDate,
                    'status'                 => 'unpaid',
                    'label'                  => $item->label,
                ]);
            }
        } else {
            // Aucun plan configuré → une seule facture pour la totalité
            StudentInvoice::create([
                'student_school_year_id' => $ssy->id,
                'fee_structure_id'       => $feeStructure?->id,
                'invoice_number'         => $this->generateInvoiceNumber($schoolId, 'SCO'),
                'amount_due'             => $tuitionAmount,
                'amount_paid'            => 0,
                'issued_at'              => now(),
                'due_at'                 => $year->starts_at->copy()->addMonth(),
                'status'                 => 'unpaid',
                'label'                  => 'Frais de scolarité ' . $year->label,
            ]);
        }
    }

    /**
     * Prévisualise les factures sans les créer en base.
     */
    public function previewInvoices(
        int              $schoolId,
        int              $levelId,
        AcademicYear     $year,
        ?InstallmentPlan $plan,
        array            $discountIds = [],
        bool             $isReinscription = false
    ): array {
        // 1. Config générale
        $config = SchoolFeeConfig::withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $year->id)
            ->first();

        // 2. Frais du niveau (AVANT $registrationFee)
        $feeStructure = FeeStructure::withoutGlobalScopes()
            ->with('installmentPlan.items')
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $year->id)
            ->where('level_id', $levelId)
            ->first();

        // 3. Frais d'inscription/réinscription
        // Priorité : frais du niveau > frais global de l'école > 0
        $registrationFee = $isReinscription
            ? (int) ($feeStructure?->reinscription_fee
                ?: $config?->reinscription_fee
                ?: 0)
            : (int) ($feeStructure?->inscription_fee
                ?: $config?->inscription_fee
                ?: 0);

        // 4. Montant scolarité
        $tuitionAmount = (int) ($feeStructure?->amount ?? 0);

        // 5. Appliquer les remises
        $discounts = ! empty($discountIds)
            ? DiscountType::whereIn('id', $discountIds)->get()
            : collect();

        $tuitionDiscounted      = (int) $this->applyDiscounts($tuitionAmount, $discounts, 'tuition');
        $registrationDiscounted = (int) $this->applyDiscounts($registrationFee, $discounts, 'inscription');

        // 6. Construire la prévisualisation
        $preview = [];

        if ($registrationDiscounted > 0) {
            $preview[] = [
                'label'  => $isReinscription
                    ? 'Frais de réinscription'
                    : 'Frais d\'inscription',
                'amount' => $registrationDiscounted,
                'due_at' => now()->addDays(15)->format('d/m/Y'),
                'type'   => 'registration',
            ];
        }

        $usedPlan = $plan
            ?? $feeStructure?->installmentPlan
            ?? InstallmentPlan::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('is_default', true)
                ->with('items')
                ->first();

        if ($tuitionDiscounted > 0) {
            if ($usedPlan && $usedPlan->items->isNotEmpty()) {
                foreach ($usedPlan->items as $item) {
                    $amount  = (int) round($tuitionDiscounted * $item->percentage / 100);
                    $dueDate = $this->resolveDueDate($item->due_month, $item->due_day, $year);
                    $preview[] = [
                        'label'  => $item->label,
                        'amount' => $amount,
                        'due_at' => $dueDate?->format('d/m/Y') ?? '—',
                        'type'   => 'tuition',
                        'pct'    => $item->percentage,
                    ];
                }
            } else {
                $preview[] = [
                    'label'  => 'Frais de scolarité ' . $year->label,
                    'amount' => $tuitionDiscounted,
                    'due_at' => $year->starts_at->copy()->addMonth()->format('d/m/Y'),
                    'type'   => 'tuition',
                    'pct'    => 100,
                ];
            }
        }

        return [
            'invoices'                => $preview,
            'total'                   => collect($preview)->sum('amount'),
            'tuition_original'        => $tuitionAmount,
            'tuition_discounted'      => $tuitionDiscounted,
            'registration_original'   => $registrationFee,
            'registration_discounted' => $registrationDiscounted,
            'total_discount'          => ($tuitionAmount - $tuitionDiscounted)
                                       + ($registrationFee - $registrationDiscounted),
            'plan_name'               => $usedPlan?->name ?? 'Paiement intégral',
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function applyDiscounts(int $amount, $discounts, string $appliesTo): float
    {
        if ($discounts->isEmpty() || $amount === 0) return $amount;

        $relevant = $discounts->filter(
            fn ($d) => $d->applies_to === $appliesTo || $d->applies_to === 'both'
        );

        if ($relevant->isEmpty()) return $amount;

        $nonCumulative = $relevant->where('is_cumulative', false);
        $cumulative    = $relevant->where('is_cumulative', true);
        $totalDiscount = 0;

        if ($nonCumulative->isNotEmpty()) {
            $totalDiscount += $nonCumulative
                ->map(fn ($d) => $this->discountAmount($amount, $d))
                ->max();
        }

        foreach ($cumulative as $d) {
            $totalDiscount += $this->discountAmount($amount, $d);
        }

        return max(0, $amount - $totalDiscount);
    }

    private function discountAmount(int $baseAmount, DiscountType $d): float
    {
        return $d->type === 'percentage'
            ? ($baseAmount * $d->value / 100)
            : min($d->value, $baseAmount);
    }

    private function resolveDueDate(?int $month, int $day, AcademicYear $year): ?Carbon
    {
        if (! $month) return null;

        $startMonth = $year->starts_at->month;
        $yearStart  = $year->starts_at->year;
        $yearEnd    = $year->ends_at->year;
        $targetYear = ($month >= $startMonth) ? $yearStart : $yearEnd;

        try {
            return Carbon::createFromDate($targetYear, $month, min($day, 28));
        } catch (\Exception) {
            return null;
        }
    }

    private function generateInvoiceNumber(int $schoolId, string $prefix = 'SCO'): string
    {
        $year  = now()->format('Y');
        $count = StudentInvoice::withoutGlobalScopes()
            ->whereHas('studentSchoolYear', fn ($q) =>
                $q->withoutGlobalScopes()
                  ->whereHas('student', fn ($q) =>
                      $q->withoutGlobalScopes()->where('school_id', $schoolId)
                  )
            )->count() + 1;

        return sprintf('%s-%d-%s-%05d', $prefix, $schoolId, $year, $count);
    }
}