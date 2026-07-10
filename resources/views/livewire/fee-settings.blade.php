<?php

use App\Models\DiscountType;
use App\Models\FeeStructure;
use App\Models\InstallmentPlan;
use App\Models\InstallmentPlanItem;
use App\Models\Level;
use App\Models\SchoolFeeConfig;
use App\Services\AcademicYearService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Attributes\On;

new class extends Component
{
    // ── Section active ──────────────────────────────────────────
    public string $activeSection = 'general';

    // ── Section 1 : Configuration générale ──────────────────────
    public int    $inscription_fee         = 0;
    public int    $reinscription_fee        = 0;
    public array  $payment_methods         = [];
    public bool   $allow_social_exemption  = false;
    public bool   $allow_discounts         = true;
    public int    $late_fee_percentage     = 0;
    public string $terms                   = '';

    // ── Section 2 : Frais par niveau ─────────────────────────────
    public array  $feeInputs = [];
    // [level_id => ['amount' => ..., 'installment_plan_id' => ...]]

    // ── Section 3 : Plans d'échéancier ───────────────────────────
    public bool   $showPlanForm       = false;
    public string $planName           = '';
    public int    $planCount          = 1;
    public array  $planItems          = [];
    // editingPlan
    public ?int   $editingPlanId      = null;
    public ?int   $confirmDeletePlanId = null;

    // ── Section 4 : Remises ──────────────────────────────────────
    public bool   $showDiscountForm    = false;
    public string $discountName        = '';
    public string $discountType        = 'percentage';
    public int    $discountValue       = 0;
    public string $discountAppliesTo   = 'tuition';
    public bool   $discountIsSocial    = false;
    public bool   $discountRequiresApp = false;
    public bool   $discountCumulative  = false;
    // editing
    public ?int   $editingDiscountId       = null;
    public ?int   $confirmDeleteDiscountId = null;

    public bool   $savedGeneral     = false;
    public bool   $savedFees        = false;

    // ────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->loadGeneralConfig();
        $this->loadFeeInputs();
    }

    private function loadGeneralConfig(): void
    {
        $year   = AcademicYearService::current();
        $config = SchoolFeeConfig::where('school_id', auth()->user()->school_id)
            ->where('academic_year_id', $year?->id)
            ->first();

        if ($config) {
            $this->inscription_fee        = $config->inscription_fee;
            $this->reinscription_fee       = $config->reinscription_fee;
            $this->payment_methods        = $config->payment_methods ?? [];
            $this->allow_social_exemption = $config->allow_social_exemption;
            $this->allow_discounts        = $config->allow_discounts;
            $this->late_fee_percentage    = $config->late_fee_percentage;
            $this->terms                  = $config->terms ?? '';
        } else {
            $this->payment_methods = ['especes'];
        }
    }

    #[On('academic-year-changed')]
    public function refresh(): void
    {
        $this->loadFeeInputs();
        $this->savedFees = false;
    }

    // Et dans loadFeeInputs(), ajoute les nouveaux champs :
    private function loadFeeInputs(): void
    {
        $year    = AcademicYearService::current();
        $levels  = Level::where('school_id', auth()->user()->school_id)->get();
        $structures = FeeStructure::where('school_id', auth()->user()->school_id)
            ->where('academic_year_id', $year?->id)
            ->get()
            ->keyBy('level_id');

        $this->feeInputs = [];

        foreach ($levels as $level) {
            $s = $structures->get($level->id);
            $this->feeInputs['level_' . $level->id] = [
                'amount'              => $s?->amount ?? 0,
                'inscription_fee'     => $s?->inscription_fee ?? 0,
                'reinscription_fee'   => $s?->reinscription_fee ?? 0,
                'installment_plan_id' => (string) ($s?->installment_plan_id ?? ''),
            ];
        }
    }


    public function togglePaymentMethod(string $method): void
    {
        if (in_array($method, $this->payment_methods)) {
            $this->payment_methods = array_values(
                array_filter($this->payment_methods, fn ($m) => $m !== $method)
            );
        } else {
            $this->payment_methods[] = $method;
        }
    }

    public function saveGeneral(): void
    {
        $this->validate([
            'inscription_fee'      => 'integer|min:0',
            'reinscription_fee'    => 'integer|min:0',
            'late_fee_percentage'  => 'integer|min:0|max:100',
        ]);

        $year = AcademicYearService::current();

        SchoolFeeConfig::updateOrCreate(
            ['school_id' => auth()->user()->school_id, 'academic_year_id' => $year?->id],
            [
                'inscription_fee'        => $this->inscription_fee,
                'reinscription_fee'      => $this->reinscription_fee,
                'payment_methods'        => $this->payment_methods,
                'allow_social_exemption' => $this->allow_social_exemption,
                'allow_discounts'        => $this->allow_discounts,
                'late_fee_percentage'    => $this->late_fee_percentage,
                'terms'                  => $this->terms,
            ]
        );

        $this->savedGeneral = true;
    }

    public function saveFees(): void
    {
        $year = AcademicYearService::current();
        if (! $year) return;

        foreach ($this->feeInputs as $key => $data) {
            if (empty($data['amount']) && empty($data['inscription_fee'])) continue;

            $levelId = str_replace('level_', '', $key);

            FeeStructure::updateOrCreate(
                [
                    'school_id'        => auth()->user()->school_id,
                    'academic_year_id' => $year->id,
                    'level_id'         => $levelId,
                ],
                [
                    'label'               => 'Frais de scolarité',
                    'amount'              => (int) $data['amount'],
                    'inscription_fee'     => (int) ($data['inscription_fee'] ?? 0),
                    'reinscription_fee'   => (int) ($data['reinscription_fee'] ?? 0),
                    'frequency'           => 'annual',
                    'installment_plan_id' => $data['installment_plan_id'] ?: null,
                ]
            );
        }

        $this->savedFees = true;
    }

    // ── Plans ────────────────────────────────────────────────────

    public function updatedPlanCount(int $value): void
    {
        $this->planItems = [];
        $equal = intdiv(100, max(1, $value));
        for ($i = 1; $i <= $value; $i++) {
            $this->planItems[] = [
                'label'      => $this->defaultLabel($i),
                'percentage' => ($i === $value) ? 100 - ($equal * ($value - 1)) : $equal,
                'due_month'  => null,
                'due_day'    => 1,
            ];
        }
    }

    private function defaultLabel(int $i): string
    {
        return match($i) {
            1 => '1ère tranche',
            2 => '2ème tranche',
            3 => '3ème tranche',
            default => $i . 'ème tranche',
        };
    }

    public function savePlan(): void
    {
        $this->validate([
            'planName'  => 'required|string|max:100',
            'planCount' => 'required|integer|min:1|max:12',
        ]);

        // Vérifier que les % font 100
        $total = collect($this->planItems)->sum('percentage');
        if ($total !== 100) {
            $this->addError('planItems', "Le total des pourcentages doit être égal à 100% (actuellement {$total}%).");
            return;
        }

        $plan = InstallmentPlan::create([
            'school_id'          => auth()->user()->school_id,
            'name'               => $this->planName,
            'installments_count' => $this->planCount,
            'is_active'          => true,
        ]);

        foreach ($this->planItems as $i => $item) {
            InstallmentPlanItem::create([
                'installment_plan_id' => $plan->id,
                'order'      => $i + 1,
                'label'      => $item['label'],
                'percentage' => $item['percentage'],
                'due_month'  => $item['due_month'] ?: null,
                'due_day'    => $item['due_day'] ?? 1,
            ]);
        }

        $this->reset('planName', 'planCount', 'planItems', 'showPlanForm');
    }

    public function setDefaultPlan(int $planId): void
    {
        InstallmentPlan::where('school_id', auth()->user()->school_id)
            ->update(['is_default' => false]);
        InstallmentPlan::where('id', $planId)->update(['is_default' => true]);
    }

    public function confirmDeletePlan(int $planId): void
    {
        $this->confirmDeletePlanId = $planId;
    }

    public function deletePlan(): void
    {
        if (! $this->confirmDeletePlanId) return;
        InstallmentPlan::where('id', $this->confirmDeletePlanId)
            ->where('school_id', auth()->user()->school_id)
            ->delete();
        $this->confirmDeletePlanId = null;
    }

    // ── Remises ──────────────────────────────────────────────────

    public function saveDiscount(): void
    {
        $this->validate([
            'discountName'  => 'required|string|max:100',
            'discountType'  => 'required|in:percentage,fixed',
            'discountValue' => 'required|integer|min:0',
        ]);

        if ($this->editingDiscountId) {
            DiscountType::where('id', $this->editingDiscountId)->update([
                'name'               => $this->discountName,
                'type'               => $this->discountType,
                'value'              => $this->discountValue,
                'applies_to'         => $this->discountAppliesTo,
                'is_social'          => $this->discountIsSocial,
                'requires_approval'  => $this->discountRequiresApp,
                'is_cumulative'      => $this->discountCumulative,
            ]);
        } else {
            DiscountType::create([
                'school_id'          => auth()->user()->school_id,
                'name'               => $this->discountName,
                'type'               => $this->discountType,
                'value'              => $this->discountValue,
                'applies_to'         => $this->discountAppliesTo,
                'is_social'          => $this->discountIsSocial,
                'requires_approval'  => $this->discountRequiresApp,
                'is_cumulative'      => $this->discountCumulative,
            ]);
        }

        $this->resetDiscountForm();
    }

    public function startEditDiscount(int $id): void
    {
        $d = DiscountType::find($id);
        if (! $d) return;

        $this->editingDiscountId    = $id;
        $this->discountName         = $d->name;
        $this->discountType         = $d->type;
        $this->discountValue        = $d->value;
        $this->discountAppliesTo    = $d->applies_to;
        $this->discountIsSocial     = $d->is_social;
        $this->discountRequiresApp  = $d->requires_approval;
        $this->discountCumulative   = $d->is_cumulative;
        $this->showDiscountForm     = true;
    }

    public function confirmDeleteDiscount(int $id): void
    {
        $this->confirmDeleteDiscountId = $id;
    }

    public function deleteDiscount(): void
    {
        if (! $this->confirmDeleteDiscountId) return;
        DiscountType::where('id', $this->confirmDeleteDiscountId)
            ->where('school_id', auth()->user()->school_id)
            ->delete();
        $this->confirmDeleteDiscountId = null;
    }

    private function resetDiscountForm(): void
    {
        $this->reset(
            'discountName', 'discountType', 'discountValue', 'discountAppliesTo',
            'discountIsSocial', 'discountRequiresApp', 'discountCumulative',
            'showDiscountForm', 'editingDiscountId'
        );
    }

    public function with(): array
    {
        $year   = AcademicYearService::current();
        $levels = Level::where('school_id', auth()->user()->school_id)
            ->orderBy('order')->get()->groupBy('cycle');

        $plans    = InstallmentPlan::where('school_id', auth()->user()->school_id)
            ->with('items')->get();

        $discounts = DiscountType::where('school_id', auth()->user()->school_id)->get();

        $months = [
            1=>'Janvier', 2=>'Février', 3=>'Mars', 4=>'Avril',
            5=>'Mai', 6=>'Juin', 7=>'Juillet', 8=>'Août',
            9=>'Septembre', 10=>'Octobre', 11=>'Novembre', 12=>'Décembre',
        ];

        return compact('year', 'levels', 'plans', 'discounts', 'months');
    }
}; ?>

<style>
    /* ── Nav sections ── */
    .section-nav {
        display: flex; gap: 0.25rem;
        background: var(--paper); border: 1px solid var(--line);
        border-radius: 10px; padding: 4px;
        margin-bottom: 1.5rem; flex-wrap: wrap;
    }
    .section-nav-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 0.45rem 1rem; border-radius: 7px;
        font-size: 0.875rem; font-weight: 500; font-family: 'Inter', sans-serif;
        color: var(--ink); border: none; cursor: pointer; background: none;
        transition: background 0.12s, color 0.12s;
        opacity: 0.55;
    }
    .section-nav-btn svg { width: 15px; height: 15px; }
    .section-nav-btn:hover { opacity: 0.9; background: var(--paper-raised); }
    .section-nav-btn.active {
        background: var(--sidebar); color: #FFFFFF; opacity: 1;
    }

    /* ── Cards ── */
    .card {
        border-radius: 12px; border: 1px solid var(--line);
        background: var(--paper-raised); overflow: hidden;
        margin-bottom: 1.25rem;
    }
    .card:last-child { margin-bottom: 0; }
    .card-header {
        padding: 0.875rem 1.5rem; border-bottom: 1px solid var(--line);
        display: flex; align-items: center; justify-content: space-between;
    }
    .card-title {
        font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600; color: var(--ink);
    }
    .card-body { padding: 1.25rem 1.5rem; }

    /* ── Formulaire ── */
    .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    @media (max-width: 700px) { .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; } }
    .form-field { display: flex; flex-direction: column; gap: 0.35rem; }
    .form-field.full { grid-column: 1 / -1; }
    .form-label {
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink); opacity: 0.5;
    }
    .form-input, .form-select-inp, .form-textarea {
        padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--line);
        background: var(--paper); font-size: 0.875rem; font-family: 'Inter', sans-serif;
        color: var(--ink); outline: none; width: 100%;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .form-input:focus, .form-select-inp:focus, .form-textarea:focus {
        border-color: var(--sidebar-soft); box-shadow: 0 0 0 3px rgba(42,63,126,0.08);
    }
    .form-textarea { resize: vertical; min-height: 80px; }
    .form-error { font-size: 0.75rem; color: var(--accent-red); margin-top: 0.2rem; }

    /* Méthodes de paiement */
    .payment-methods-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .method-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 0.4rem 0.875rem; border-radius: 8px;
        font-size: 0.8125rem; font-weight: 500; font-family: 'Inter', sans-serif;
        border: 1.5px solid var(--line); background: var(--paper);
        color: var(--ink); cursor: pointer; transition: all 0.12s;
        user-select: none;
    }
    .method-btn.active {
        border-color: var(--sidebar-soft); background: rgba(42,63,126,0.08); color: var(--sidebar-soft);
    }
    .method-btn svg { width: 14px; height: 14px; }

    /* Toggle */
    .toggle-row {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.875rem 0; border-bottom: 1px solid var(--line);
    }
    .toggle-row:last-child { border-bottom: none; padding-bottom: 0; }
    .toggle-label-wrap { max-width: 70%; }
    .toggle-label { font-size: 0.875rem; font-weight: 500; color: var(--ink); }
    .toggle-desc  { font-size: 0.8rem; color: var(--ink); opacity: 0.5; margin-top: 2px; }
    .toggle-switch {
        position: relative; width: 40px; height: 22px; cursor: pointer;
        flex-shrink: 0;
    }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-slider {
        position: absolute; inset: 0; border-radius: 22px;
        background: var(--line); transition: background 0.2s;
    }
    .toggle-slider::before {
        content: ''; position: absolute;
        width: 16px; height: 16px; border-radius: 50%;
        background: white; top: 3px; left: 3px;
        transition: transform 0.2s;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .toggle-switch input:checked + .toggle-slider { background: var(--sidebar-soft); }
    .toggle-switch input:checked + .toggle-slider::before { transform: translateX(18px); }

    /* Actions */
    .form-actions {
        display: flex; align-items: center; justify-content: flex-end;
        gap: 0.65rem; padding-top: 1.25rem; border-top: 1px solid var(--line); margin-top: 1.25rem;
    }
    .btn-save {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 0.5rem 1.25rem; border-radius: 8px;
        background: var(--sidebar); color: #FFFFFF;
        font-size: 0.875rem; font-weight: 600; font-family: 'Inter', sans-serif;
        border: none; cursor: pointer; transition: background 0.15s;
    }
    .btn-save:hover { background: var(--sidebar-soft); }
    .btn-save svg { width: 15px; height: 15px; }
    .btn-cancel {
        padding: 0.5rem 1rem; border-radius: 8px; border: 1px solid var(--line);
        background: var(--paper); font-size: 0.875rem; font-weight: 500;
        font-family: 'Inter', sans-serif; color: var(--ink); cursor: pointer;
    }
    .btn-secondary {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 0.45rem 1rem; border-radius: 8px;
        border: 1px solid var(--line); background: var(--paper);
        font-size: 0.875rem; font-weight: 600; font-family: 'Inter', sans-serif;
        color: var(--ink); cursor: pointer; transition: border-color 0.15s;
    }
    .btn-secondary:hover { border-color: var(--sidebar-soft); color: var(--sidebar-soft); }
    .btn-secondary svg { width: 15px; height: 15px; }

    /* Toast */
    .toast-success {
        display: flex; align-items: center; gap: 0.65rem;
        padding: 0.65rem 1rem; border-radius: 8px;
        background: rgba(30,120,80,0.1); border: 1px solid rgba(30,120,80,0.2);
        color: #1A6040; font-size: 0.875rem; font-weight: 500;
        margin-bottom: 1rem; animation: slideDown 0.15s ease;
    }
    .toast-success svg { width: 16px; height: 16px; flex-shrink: 0; }
    @keyframes slideDown { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:translateY(0); } }

    /* Table frais par niveau */
    .fee-table { width: 100%; border-collapse: collapse; }
    .fee-table thead tr { border-bottom: 1px solid var(--line); }
    .fee-table thead th {
        text-align: left; padding: 0.6rem 1rem;
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink); opacity: 0.45;
    }
    .fee-table tbody tr { border-bottom: 1px solid var(--line); }
    .fee-table tbody tr:last-child { border-bottom: none; }
    .fee-table tbody td { padding: 0.65rem 1rem; vertical-align: middle; }
    .fee-table-cycle {
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.06em;
        padding: 0.5rem 1rem; background: var(--paper);
        border-bottom: 1px solid var(--line);
        color: var(--ink); opacity: 0.5;
    }
    .level-name { font-weight: 600; font-size: 0.875rem; }
    .fee-input-wrap { display: flex; align-items: center; gap: 6px; }
    .fee-currency { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--ink); opacity: 0.5; }
    .fee-input-sm {
        padding: 0.4rem 0.65rem; border-radius: 7px; border: 1px solid var(--line);
        background: var(--paper); font-size: 0.875rem; font-family: 'JetBrains Mono', monospace;
        color: var(--ink); outline: none; width: 130px;
        transition: border-color 0.15s;
    }
    .fee-input-sm:focus { border-color: var(--sidebar-soft); }
    .plan-select-sm {
        padding: 0.4rem 0.65rem; border-radius: 7px; border: 1px solid var(--line);
        background: var(--paper); font-size: 0.8125rem; font-family: 'Inter', sans-serif;
        color: var(--ink); outline: none; min-width: 160px;
    }

    /* Plans cards */
    .plans-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem;
    }
    .plan-card {
        border-radius: 10px; border: 1px solid var(--line);
        background: var(--paper); overflow: hidden;
    }
    .plan-card.is-default { border-color: var(--accent); }
    .plan-card-header {
        padding: 0.875rem 1.1rem; border-bottom: 1px solid var(--line);
        display: flex; align-items: center; justify-content: space-between;
    }
    .plan-name { font-weight: 600; font-size: 0.9rem; }
    .plan-badge {
        font-family: 'JetBrains Mono', monospace; font-size: 9px; font-weight: 600;
        padding: 2px 7px; border-radius: 4px; text-transform: uppercase;
    }
    .plan-badge-default { background: rgba(232,168,56,0.15); color: #8A6010; }
    .plan-badge-count   { background: rgba(42,63,126,0.08); color: var(--sidebar-soft); }

    .plan-items { padding: 0.75rem 1.1rem; }
    .plan-item-row {
        display: flex; align-items: center; justify-content: space-between;
        padding: 0.35rem 0; border-bottom: 1px solid var(--line); font-size: 0.8125rem;
    }
    .plan-item-row:last-child { border-bottom: none; }
    .plan-item-label { color: var(--ink); opacity: 0.7; }
    .plan-item-pct {
        font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 700;
        color: var(--sidebar-soft);
    }
    .plan-item-month { font-size: 0.75rem; color: var(--ink); opacity: 0.45; margin-top: 1px; }
    .plan-card-footer {
        padding: 0.65rem 1.1rem; border-top: 1px solid var(--line);
        display: flex; align-items: center; gap: 0.35rem; justify-content: flex-end;
    }
    .btn-xs {
        display: inline-flex; align-items: center; gap: 3px;
        padding: 0.25rem 0.6rem; border-radius: 5px;
        font-size: 0.75rem; font-weight: 600; font-family: 'Inter', sans-serif;
        border: none; cursor: pointer; transition: background 0.12s;
    }
    .btn-xs svg { width: 12px; height: 12px; }
    .btn-xs-default { background: rgba(232,168,56,0.12); color: #8A6010; }
    .btn-xs-default:hover { background: rgba(232,168,56,0.22); }
    .btn-xs-del  { background: rgba(224,92,58,0.08); color: var(--accent-red); }
    .btn-xs-del:hover { background: rgba(224,92,58,0.16); }

    /* Tranche items form */
    .installment-items-form { margin-top: 1rem; }
    .installment-item-row {
        display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 0.65rem;
        align-items: end; margin-bottom: 0.65rem;
        padding: 0.875rem; background: var(--paper); border-radius: 8px;
        border: 1px solid var(--line);
    }
    @media (max-width: 700px) { .installment-item-row { grid-template-columns: 1fr 1fr; } }
    .installment-item-title {
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 700;
        text-transform: uppercase; color: var(--sidebar-soft); letter-spacing: 0.06em;
        grid-column: 1 / -1; margin-bottom: 0.25rem;
    }
    .pct-total {
        display: flex; align-items: center; gap: 0.5rem;
        font-family: 'JetBrains Mono', monospace; font-size: 12px;
        margin-top: 0.5rem;
    }
    .pct-total.ok   { color: #166534; }
    .pct-total.warn { color: var(--accent-red); }

    /* Remises table */
    .discount-table { width: 100%; border-collapse: collapse; }
    .discount-table thead tr { border-bottom: 1px solid var(--line); background: var(--paper); }
    .discount-table thead th {
        text-align: left; padding: 0.6rem 1rem;
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink); opacity: 0.45;
    }
    .discount-table thead th:last-child { text-align: right; }
    .discount-table tbody tr { border-bottom: 1px solid var(--line); transition: background 0.1s; }
    .discount-table tbody tr:last-child { border-bottom: none; }
    .discount-table tbody tr:hover { background: rgba(30,45,90,0.03); }
    .discount-table tbody td { padding: 0.875rem 1rem; font-size: 0.875rem; color: var(--ink); vertical-align: middle; }
    .discount-table tbody td:last-child { text-align: right; }

    .discount-name { font-weight: 600; }
    .discount-chips { display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; margin-top: 3px; }
    .chip {
        font-family: 'JetBrains Mono', monospace; font-size: 9px; font-weight: 600;
        padding: 1px 6px; border-radius: 3px; text-transform: uppercase;
    }
    .chip-social  { background: rgba(99,102,241,0.1); color: #3730A3; }
    .chip-approval{ background: rgba(232,168,56,0.12); color: #8A6010; }
    .chip-cumul   { background: rgba(74,222,128,0.1); color: #166534; }

    .value-badge {
        font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 700;
        color: var(--sidebar-soft);
    }
    .applies-badge {
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        padding: 2px 8px; border-radius: 4px;
        background: rgba(0,0,0,0.05); color: var(--ink); opacity: 0.6;
        text-transform: uppercase;
    }

    .actions-cell { display: flex; align-items: center; justify-content: flex-end; gap: 0.4rem; }
    .btn-action {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 0.3rem 0.65rem; border-radius: 6px;
        font-size: 0.8rem; font-weight: 600; font-family: 'Inter', sans-serif;
        border: none; cursor: pointer; transition: background 0.12s;
    }
    .btn-action svg { width: 13px; height: 13px; }
    .btn-edit-act { background: rgba(42,63,126,0.08); color: var(--sidebar-soft); }
    .btn-edit-act:hover { background: rgba(42,63,126,0.16); }
    .btn-del-act  { background: rgba(224,92,58,0.08); color: var(--accent-red); }
    .btn-del-act:hover { background: rgba(224,92,58,0.16); }

    /* Modal */
    .modal-overlay {
        position: fixed; inset: 0; z-index: 100; background: rgba(0,0,0,0.4);
        display: flex; align-items: center; justify-content: center; padding: 1rem;
    }
    .modal {
        background: var(--paper-raised); border-radius: 14px; border: 1px solid var(--line);
        padding: 1.75rem; max-width: 380px; width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    }
    .modal-title { font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; }
    .modal-desc  { font-size: 0.875rem; color: var(--ink); opacity: 0.6; margin-bottom: 1.25rem; line-height: 1.5; }
    .modal-actions { display: flex; justify-content: flex-end; gap: 0.65rem; }
    .btn-modal-cancel {
        padding: 0.45rem 1rem; border-radius: 8px; border: 1px solid var(--line);
        background: var(--paper); font-size: 0.875rem; font-weight: 500;
        font-family: 'Inter', sans-serif; color: var(--ink); cursor: pointer;
    }
    .btn-modal-confirm {
        padding: 0.45rem 1rem; border-radius: 8px; border: none;
        background: var(--accent-red); color: #FFFFFF;
        font-size: 0.875rem; font-weight: 600; font-family: 'Inter', sans-serif; cursor: pointer;
    }

    /* Empty */
    .empty-inline {
        padding: 2rem; text-align: center; font-size: 0.875rem;
        color: var(--ink); opacity: 0.4; border: 1.5px dashed var(--line); border-radius: 10px;
    }
</style>

<div>
    {{-- Navigation sections --}}
    <nav class="section-nav">
        <button wire:click="$set('activeSection', 'general')"
                class="section-nav-btn {{ $activeSection === 'general' ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Général
        </button>
        <button wire:click="$set('activeSection', 'fees')"
                class="section-nav-btn {{ $activeSection === 'fees' ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            Frais par niveau
        </button>
        <button wire:click="$set('activeSection', 'plans')"
                class="section-nav-btn {{ $activeSection === 'plans' ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Echéanciers
        </button>
        <button wire:click="$set('activeSection', 'discounts')"
                class="section-nav-btn {{ $activeSection === 'discounts' ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M17 17h.01M5.5 5.5l13 13M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Remises & Exonérations
        </button>
    </nav>

    {{-- ══════════════════════════════════════════════ --}}
    {{-- SECTION 1 : GÉNÉRAL --}}
    {{-- ══════════════════════════════════════════════ --}}
    @if ($activeSection === 'general')

        @if ($savedGeneral)
            <div class="toast-success" x-data x-init="setTimeout(() => $el.remove(), 3000)">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Configuration générale enregistrée — {{ $year?->label }}.
            </div>
        @endif

        {{-- Frais fixes --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">Frais fixes</span>
                <span style="font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--ink); opacity:0.4;">{{ $year?->label }}</span>
            </div>
            <div class="card-body">
                <div class="form-grid-2">
                    <div class="form-field">
                        <label class="form-label">Frais d'inscription (nouvelle inscription)</label>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <input wire:model="inscription_fee" type="number" min="0" class="form-input">
                            <span style="font-family:'JetBrains Mono',monospace; font-size:12px; opacity:0.5; white-space:nowrap;">DJF</span>
                        </div>
                        @error('inscription_fee') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-field">
                        <label class="form-label">Frais de réinscription (chaque année)</label>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <input wire:model="reinscription_fee" type="number" min="0" class="form-input">
                            <span style="font-family:'JetBrains Mono',monospace; font-size:12px; opacity:0.5; white-space:nowrap;">DJF</span>
                        </div>
                        @error('reinscription_fee') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="form-field" style="max-width:200px;">
                    <label class="form-label">Pénalité de retard (% / mois)</label>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <input wire:model="late_fee_percentage" type="number" min="0" max="100" class="form-input">
                        <span style="font-family:'JetBrains Mono',monospace; font-size:12px; opacity:0.5;">%</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Méthodes de paiement --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">Méthodes de paiement acceptées</span>
            </div>
            <div class="card-body">
                <div class="payment-methods-grid">
                    @php
                        $methods = [
                            'especes'  => ['label' => 'Espèces',  'icon' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
                            'd-money'  => ['label' => 'D-Money',  'icon' => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z'],
                            'virement' => ['label' => 'Virement', 'icon' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4'],
                            'cheque'   => ['label' => 'Chèque',   'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                            'carte'    => ['label' => 'Carte',    'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z'],
                        ];
                    @endphp
                    @foreach ($methods as $key => $method)
                        <button type="button"
                                wire:click="togglePaymentMethod('{{ $key }}')"
                                class="method-btn {{ in_array($key, $payment_methods) ? 'active' : '' }}">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $method['icon'] }}"/>
                            </svg>
                            {{ $method['label'] }}
                            @if (in_array($key, $payment_methods))
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Options --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">Options</span>
            </div>
            <div class="card-body">
                <div class="toggle-row">
                    <div class="toggle-label-wrap">
                        <div class="toggle-label">Exonération sociale</div>
                        <div class="toggle-desc">Permet d'accorder des exonérations totales ou partielles aux familles défavorisées avec justificatif.</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" wire:model="allow_social_exemption">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="toggle-row">
                    <div class="toggle-label-wrap">
                        <div class="toggle-label">Remises et réductions</div>
                        <div class="toggle-desc">Active la possibilité d'appliquer des remises (fratrie, personnel, bourses...) sur les frais de scolarité.</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" wire:model="allow_discounts">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Conditions générales --}}
        <div class="card">
            <div class="card-header"><span class="card-title">Conditions générales de paiement</span></div>
            <div class="card-body">
                <div class="form-field">
                    <label class="form-label">Texte affiché sur les factures</label>
                    <textarea wire:model="terms" class="form-textarea"
                              placeholder="Ex: Tout paiement doit être effectué dans les 15 jours suivant la date d'émission de la facture..."></textarea>
                </div>
            </div>
        </div>

        <div class="form-actions" style="border-top:none; padding-top:0;">
            <button wire:click="saveGeneral" class="btn-save">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                Enregistrer la configuration
            </button>
        </div>

    @endif

    {{-- ══════════════════════════════════════════════ --}}
    {{-- SECTION 2 : FRAIS PAR NIVEAU --}}
    {{-- ══════════════════════════════════════════════ --}}
    @if ($activeSection === 'fees')

        @if ($savedFees)
            <div class="toast-success" x-data x-init="setTimeout(() => $el.remove(), 3000)">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Frais par niveau enregistrés.
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <span class="card-title">Frais de scolarité par niveau</span>
                <span style="font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--ink); opacity:0.4;">{{ $year?->label }}</span>
            </div>
            {{-- Remplace le <table class="fee-table"> dans la section fees --}}
            <table class="fee-table">
                <thead>
                    <tr>
                        <th>Niveau</th>
                        <th>Scolarité annuelle</th>
                        <th>Frais d'inscription</th>
                        <th>Frais de réinscription</th>
                        <th>Plan d'échéancier</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($levels as $cycle => $cycleLevels)
                        <tr>
                            <td colspan="5" class="fee-table-cycle">{{ $cycle }}</td>
                        </tr>
                        @foreach ($cycleLevels as $level)
                            <tr>
                                <td><span class="level-name">{{ $level->name }}</span></td>
                                <td>
                                    <div class="fee-input-wrap">
                                        <input wire:model="feeInputs.level_{{ $level->id }}.amount"
                                            type="number" min="0" class="fee-input-sm" placeholder="0">
                                        <span class="fee-currency">DJF</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fee-input-wrap">
                                        <input wire:model="feeInputs.level_{{ $level->id }}.inscription_fee"
                                            type="number" min="0" class="fee-input-sm" placeholder="0">
                                        <span class="fee-currency">DJF</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="fee-input-wrap">
                                        <input wire:model="feeInputs.level_{{ $level->id }}.reinscription_fee"
                                            type="number" min="0" class="fee-input-sm" placeholder="0">
                                        <span class="fee-currency">DJF</span>
                                    </div>
                                </td>
                                <td>
                                    <select wire:model="feeInputs.level_{{ $level->id }}.installment_plan_id"
                                            class="plan-select-sm">
                                        <option value="">Aucun plan</option>
                                        @foreach ($plans as $plan)
                                            <option value="{{ $plan->id }}">
                                                {{ $plan->name }}
                                                @if ($plan->is_default) (défaut) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
            <div style="padding:1rem 1.5rem; border-top:1px solid var(--line); display:flex; justify-content:flex-end;">
                <button wire:click="saveFees" class="btn-save">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                    </svg>
                    Enregistrer les frais
                </button>
            </div>
        </div>

    @endif

    {{-- ══════════════════════════════════════════════ --}}
    {{-- SECTION 3 : ECHÉANCIERS --}}
    {{-- ══════════════════════════════════════════════ --}}
    @if ($activeSection === 'plans')

        <div style="display:flex; justify-content:flex-end; margin-bottom:1rem;">
            <button wire:click="$toggle('showPlanForm')" class="btn-secondary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Nouveau plan
            </button>
        </div>

        {{-- Formulaire création plan --}}
        @if ($showPlanForm)
            <div class="card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <span class="card-title">Nouveau plan d'échéancier</span>
                    <button wire:click="$set('showPlanForm', false)"
                            style="background:none;border:none;cursor:pointer;opacity:0.4;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="card-body">
                    <div class="form-grid-2" style="margin-bottom:1rem;">
                        <div class="form-field">
                            <label class="form-label">Nom du plan</label>
                            <input wire:model="planName" type="text" class="form-input"
                                   placeholder="Ex: 3 tranches égales">
                            @error('planName') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field">
                            <label class="form-label">Nombre de tranches</label>
                            <select wire:model.live="planCount" class="form-select-inp">
                                @for ($i = 1; $i <= 12; $i++)
                                    <option value="{{ $i }}">{{ $i }} tranche{{ $i > 1 ? 's' : '' }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>

                    @if (!empty($planItems))
                        <div class="installment-items-form">
                            @foreach ($planItems as $i => $item)
                                <div class="installment-item-row">
                                    <div class="installment-item-title">Tranche {{ $i + 1 }}</div>
                                    <div class="form-field">
                                        <label class="form-label">Libellé</label>
                                        <input wire:model="planItems.{{ $i }}.label" type="text" class="form-input" placeholder="1ère tranche">
                                    </div>
                                    <div class="form-field">
                                        <label class="form-label">Pourcentage %</label>
                                        <input wire:model="planItems.{{ $i }}.percentage" type="number" min="1" max="100" class="form-input">
                                    </div>
                                    <div class="form-field">
                                        <label class="form-label">Mois d'échéance</label>
                                        <select wire:model="planItems.{{ $i }}.due_month" class="form-select-inp">
                                            <option value="">Sans date fixe</option>
                                            @foreach ($months as $num => $name)
                                                <option value="{{ $num }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-field">
                                        <label class="form-label">Jour</label>
                                        <input wire:model="planItems.{{ $i }}.due_day" type="number" min="1" max="28" class="form-input" placeholder="1">
                                    </div>
                                </div>
                            @endforeach

                            @php $total = collect($planItems)->sum('percentage'); @endphp
                            <div class="pct-total {{ $total === 100 ? 'ok' : 'warn' }}">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    @if ($total === 100)
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    @else
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                    @endif
                                </svg>
                                Total : {{ $total }}% / 100%
                                @if ($total !== 100) <span style="font-size:11px;">— ajuste les % pour atteindre exactement 100%</span> @endif
                            </div>
                            @error('planItems') <div style="color:var(--accent-red); font-size:0.75rem; margin-top:0.5rem;">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    <div class="form-actions">
                        <button wire:click="$set('showPlanForm', false)" class="btn-cancel">Annuler</button>
                        <button wire:click="savePlan" class="btn-save">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Créer le plan
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Liste des plans --}}
        @if ($plans->isEmpty())
            <div class="empty-inline">Aucun plan d'échéancier créé. Crée ton premier plan ci-dessus.</div>
        @else
            <div class="plans-grid">
                @foreach ($plans as $plan)
                    <div class="plan-card {{ $plan->is_default ? 'is-default' : '' }}">
                        <div class="plan-card-header">
                            <span class="plan-name">{{ $plan->name }}</span>
                            <div style="display:flex; gap:0.35rem; align-items:center;">
                                @if ($plan->is_default)
                                    <span class="plan-badge plan-badge-default">Défaut</span>
                                @endif
                                <span class="plan-badge plan-badge-count">{{ $plan->installments_count }} tr.</span>
                            </div>
                        </div>
                        <div class="plan-items">
                            @foreach ($plan->items as $item)
                                <div class="plan-item-row">
                                    <div>
                                        <div class="plan-item-label">{{ $item->label }}</div>
                                        @if ($item->due_month)
                                            <div class="plan-item-month">
                                                Echéance : {{ $months[$item->due_month] ?? '' }} {{ $item->due_day > 1 ? "(jour {$item->due_day})" : '' }}
                                            </div>
                                        @endif
                                    </div>
                                    <span class="plan-item-pct">{{ $item->percentage }}%</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="plan-card-footer">
                            @if (! $plan->is_default)
                                <button wire:click="setDefaultPlan({{ $plan->id }})" class="btn-xs btn-xs-default">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                    </svg>
                                    Définir par défaut
                                </button>
                            @endif
                            <button wire:click="confirmDeletePlan({{ $plan->id }})" class="btn-xs btn-xs-del">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16"/>
                                </svg>
                                Supprimer
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    @endif

    {{-- ══════════════════════════════════════════════ --}}
    {{-- SECTION 4 : REMISES & EXONÉRATIONS --}}
    {{-- ══════════════════════════════════════════════ --}}
    @if ($activeSection === 'discounts')

        <div style="display:flex; justify-content:flex-end; margin-bottom:1rem;">
            <button wire:click="$toggle('showDiscountForm')" class="btn-secondary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Nouvelle remise
            </button>
        </div>

        {{-- Formulaire remise --}}
        @if ($showDiscountForm)
            <div class="card" style="margin-bottom:1.25rem;">
                <div class="card-header">
                    <span class="card-title">{{ $editingDiscountId ? 'Modifier la remise' : 'Nouvelle remise' }}</span>
                    <button wire:click="resetDiscountForm"
                            style="background:none;border:none;cursor:pointer;opacity:0.4;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="card-body">
                    <div class="form-grid-3">
                        <div class="form-field" style="grid-column: 1 / 3;">
                            <label class="form-label">Nom de la remise</label>
                            <input wire:model="discountName" type="text" class="form-input"
                                   placeholder="Ex: Exonération sociale, Remise fratrie...">
                            @error('discountName') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field">
                            <label class="form-label">S'applique sur</label>
                            <select wire:model="discountAppliesTo" class="form-select-inp">
                                <option value="tuition">Scolarité uniquement</option>
                                <option value="inscription">Inscription uniquement</option>
                                <option value="both">Scolarité + Inscription</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid-3">
                        <div class="form-field">
                            <label class="form-label">Type de remise</label>
                            <select wire:model.live="discountType" class="form-select-inp">
                                <option value="percentage">Pourcentage (%)</option>
                                <option value="fixed">Montant fixe (DJF)</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Valeur {{ $discountType === 'percentage' ? '(%)' : '(DJF)' }}</label>
                            <input wire:model="discountValue" type="number" min="0" class="form-input">
                            @error('discountValue') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:0;">
                        <div class="toggle-row">
                            <div class="toggle-label-wrap">
                                <div class="toggle-label">Exonération sociale</div>
                                <div class="toggle-desc">Nécessite un justificatif (certificat d'indigence, attestation sociale...).</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" wire:model="discountIsSocial">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="toggle-row">
                            <div class="toggle-label-wrap">
                                <div class="toggle-label">Nécessite une approbation</div>
                                <div class="toggle-desc">L'admin doit valider avant que la remise soit appliquée sur la facture.</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" wire:model="discountRequiresApp">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="toggle-row">
                            <div class="toggle-label-wrap">
                                <div class="toggle-label">Cumulable avec d'autres remises</div>
                                <div class="toggle-desc">Si désactivé, seule la remise la plus avantageuse sera appliquée.</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" wire:model="discountCumulative">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button wire:click="resetDiscountForm" class="btn-cancel">Annuler</button>
                        <button wire:click="saveDiscount" class="btn-save">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ $editingDiscountId ? 'Enregistrer' : 'Créer' }}
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Table des remises --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">Remises & Exonérations configurées</span>
                <span style="font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--ink); opacity:0.4;">{{ $discounts->count() }} type{{ $discounts->count() > 1 ? 's' : '' }}</span>
            </div>
            @if ($discounts->isEmpty())
                <div style="padding:2.5rem; text-align:center; font-size:0.875rem; color:var(--ink); opacity:0.4;">
                    Aucune remise configurée. Crée ton premier type de remise.
                </div>
            @else
                <table class="discount-table">
                    <thead>
                        <tr>
                            <th>Remise</th>
                            <th>Valeur</th>
                            <th>Applicable sur</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($discounts as $discount)
                            <tr>
                                <td>
                                    <div class="discount-name">{{ $discount->name }}</div>
                                    <div class="discount-chips">
                                        @if ($discount->is_social)
                                            <span class="chip chip-social">Sociale</span>
                                        @endif
                                        @if ($discount->requires_approval)
                                            <span class="chip chip-approval">Approbation requise</span>
                                        @endif
                                        @if ($discount->is_cumulative)
                                            <span class="chip chip-cumul">Cumulable</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="value-badge">{{ $discount->formatted_value }}</span>
                                </td>
                                <td>
                                    <span class="applies-badge">
                                        {{ match($discount->applies_to) {
                                            'tuition'     => 'Scolarité',
                                            'inscription' => 'Inscription',
                                            'both'        => 'Les deux',
                                            default       => $discount->applies_to,
                                        } }}
                                    </span>
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <button wire:click="startEditDiscount({{ $discount->id }})" class="btn-action btn-edit-act">
                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            Modifier
                                        </button>
                                        <button wire:click="confirmDeleteDiscount({{ $discount->id }})" class="btn-action btn-del-act">
                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16"/>
                                            </svg>
                                            Supprimer
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    @endif

    {{-- Modals suppression plan --}}
    @if ($confirmDeletePlanId)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Supprimer ce plan ?</div>
                <div class="modal-desc">Ce plan sera retiré de tous les niveaux auxquels il est associé. Les factures déjà générées ne seront pas affectées.</div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeletePlanId', null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deletePlan" class="btn-modal-confirm">Supprimer</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modals suppression remise --}}
    @if ($confirmDeleteDiscountId)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Supprimer cette remise ?</div>
                <div class="modal-desc">Ce type de remise ne sera plus disponible lors des inscriptions. Les remises déjà appliquées sur des factures existantes ne seront pas affectées.</div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeleteDiscountId', null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deleteDiscount" class="btn-modal-confirm">Supprimer</button>
                </div>
            </div>
        </div>
    @endif

</div>
