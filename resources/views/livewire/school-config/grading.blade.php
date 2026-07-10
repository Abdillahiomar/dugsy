<?php

use App\Models\GradingPeriod;
use App\Models\SchoolGradingConfig;
use App\Services\AcademicYearService;
use App\Services\GradingConfigService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component
{
    public string $activeTab = 'scale';

    // ── Barème & Calcul ──────────────────────────────────────────
    public int    $max_score              = 20;
    public int    $passing_score         = 10;
    public int    $decimal_places        = 2;
    public string $average_method        = 'weighted_coefficient';
    public string $period_system         = 'trimester';
    public bool   $drop_lowest_grade     = false;
    public int    $min_grades_per_period = 1;

    // ── Types d'évaluation ───────────────────────────────────────
    public array  $evaluation_types   = [];
    public array  $evaluation_weights = [];
    // Pour ajouter un type personnalisé
    public string $newTypeName   = '';
    public int    $newTypeWeight = 0;

    // ── Mentions ────────────────────────────────────────────────
    public array  $mentions      = [];
    public array  $appreciations = [];

    // ── Options bulletin ────────────────────────────────────────
    public bool   $show_rank                 = true;
    public bool   $show_class_average        = true;
    public bool   $show_min_max              = false;
    public bool   $show_teacher_appreciation = true;
    public bool   $show_absences_on_bulletin = true;

    // ── Périodes de saisie ───────────────────────────────────────
    // [period => [is_open, open_from, open_until, note]]
    public array $gradingPeriods = [];

    public bool   $savedScale    = false;
    public bool   $savedTypes    = false;
    public bool   $savedMentions = false;
    public bool   $savedPeriods  = false;

    public function mount(): void
    {
        $schoolId = auth()->user()->school_id;
        $config   = GradingConfigService::get($schoolId);

        $this->max_score              = $config->max_score;
        $this->passing_score          = $config->passing_score;
        $this->decimal_places         = $config->decimal_places;
        $this->average_method         = $config->average_method;
        $this->period_system          = $config->period_system;
        $this->drop_lowest_grade      = $config->drop_lowest_grade;
        $this->min_grades_per_period  = $config->min_grades_per_period;
        $this->evaluation_types       = $config->evaluation_types ?? GradingConfigService::defaults()['evaluation_types'];
        $this->evaluation_weights     = $config->evaluation_weights ?? GradingConfigService::defaults()['evaluation_weights'];
        $this->mentions               = $config->mentions ?? GradingConfigService::defaults()['mentions'];
        $this->appreciations          = $config->appreciations ?? GradingConfigService::defaults()['appreciations'];
        $this->show_rank                 = $config->show_rank;
        $this->show_class_average        = $config->show_class_average;
        $this->show_min_max              = $config->show_min_max;
        $this->show_teacher_appreciation = $config->show_teacher_appreciation;
        $this->show_absences_on_bulletin = $config->show_absences_on_bulletin;

        $this->loadGradingPeriods();
    }

    private function loadGradingPeriods(): void
    {
        $year     = AcademicYearService::current();
        $schoolId = auth()->user()->school_id;
        $periods  = GradingConfigService::periods(GradingConfigService::get($schoolId));

        foreach ($periods as $period) {
            $rec = GradingPeriod::where('school_id', $schoolId)
                ->where('academic_year_id', $year?->id)
                ->where('period', $period)
                ->first();

            $this->gradingPeriods[$period] = [
                'is_open'    => $rec?->is_open ?? false,
                'open_from'  => $rec?->open_from?->format('Y-m-d') ?? '',
                'open_until' => $rec?->open_until?->format('Y-m-d') ?? '',
                'note'       => $rec?->note ?? '',
            ];
        }
    }

    // ── Barème ────────────────────────────────────────────────────

    public function saveScale(): void
    {
        $this->validate([
            'max_score'              => 'required|integer|min:10|max:100',
            'passing_score'          => 'required|integer|min:0',
            'decimal_places'         => 'required|integer|min:0|max:4',
            'min_grades_per_period'  => 'required|integer|min:0|max:20',
        ]);

        $this->updateConfig([
            'max_score'              => $this->max_score,
            'passing_score'          => $this->passing_score,
            'decimal_places'         => $this->decimal_places,
            'average_method'         => $this->average_method,
            'period_system'          => $this->period_system,
            'drop_lowest_grade'      => $this->drop_lowest_grade,
            'min_grades_per_period'  => $this->min_grades_per_period,
        ]);

        $this->loadGradingPeriods();
        $this->savedScale = true;
    }

    // ── Types d'évaluation ────────────────────────────────────────

    public function addType(): void
    {
        if (! $this->newTypeName) return;
        $key = strtolower(trim($this->newTypeName));
        if (! in_array($key, $this->evaluation_types)) {
            $this->evaluation_types[]     = $key;
            $this->evaluation_weights[$key] = $this->newTypeWeight;
        }
        $this->newTypeName   = '';
        $this->newTypeWeight = 0;
    }

    public function removeType(string $type): void
    {
        $this->evaluation_types   = array_values(array_filter($this->evaluation_types, fn ($t) => $t !== $type));
        unset($this->evaluation_weights[$type]);
    }

    public function saveTypes(): void
    {
        $total = array_sum($this->evaluation_weights);
        if ($total !== 100 && $total !== 0) {
            $this->addError('evaluation_weights', "Le total des poids doit être 0 (non pondéré) ou 100% (actuellement {$total}%).");
            return;
        }

        $this->updateConfig([
            'evaluation_types'   => array_values($this->evaluation_types),
            'evaluation_weights' => $this->evaluation_weights,
        ]);

        $this->savedTypes = true;
    }

    // ── Mentions ──────────────────────────────────────────────────

    public function addMention(): void
    {
        $this->mentions[] = ['label' => '', 'min' => 0];
    }

    public function removeMention(int $i): void
    {
        array_splice($this->mentions, $i, 1);
    }

    public function addAppreciation(): void
    {
        $this->appreciations[] = ['label' => '', 'min' => 0];
    }

    public function removeAppreciation(int $i): void
    {
        array_splice($this->appreciations, $i, 1);
    }

    public function saveMentions(): void
    {
        $this->updateConfig([
            'mentions'                 => $this->mentions,
            'appreciations'            => $this->appreciations,
            'show_rank'                => $this->show_rank,
            'show_class_average'       => $this->show_class_average,
            'show_min_max'             => $this->show_min_max,
            'show_teacher_appreciation'=> $this->show_teacher_appreciation,
            'show_absences_on_bulletin'=> $this->show_absences_on_bulletin,
        ]);

        $this->savedMentions = true;
    }

    // ── Périodes de saisie ────────────────────────────────────────

    public function togglePeriod(string $period): void
    {
        $this->gradingPeriods[$period]['is_open'] = ! $this->gradingPeriods[$period]['is_open'];
        $this->saveSinglePeriod($period);
    }

    public function savePeriods(): void
    {
        $year     = AcademicYearService::current();
        $schoolId = auth()->user()->school_id;

        foreach ($this->gradingPeriods as $period => $data) {
            $this->saveSinglePeriod($period, $year, $schoolId);
        }

        $this->savedPeriods = true;
    }

    private function saveSinglePeriod(string $period, $year = null, ?int $schoolId = null): void
    {
        $year     ??= AcademicYearService::current();
        $schoolId ??= auth()->user()->school_id;
        if (! $year) return;

        $data = $this->gradingPeriods[$period];
        GradingPeriod::updateOrCreate(
            ['school_id' => $schoolId, 'academic_year_id' => $year->id, 'period' => $period],
            [
                'is_open'    => $data['is_open'],
                'open_from'  => $data['open_from'] ?: null,
                'open_until' => $data['open_until'] ?: null,
                'note'       => $data['note'] ?: null,
            ]
        );
    }

    private function updateConfig(array $data): void
    {
        SchoolGradingConfig::updateOrCreate(
            ['school_id' => auth()->user()->school_id],
            $data
        );
    }

    public function with(): array
    {
        $year = AcademicYearService::current();
        return compact('year');
    }
}; ?>

<style>
    .tabs { display:flex; background:var(--paper); border:1px solid var(--line); border-radius:10px; padding:4px; margin-bottom:1.5rem; gap:.25rem; flex-wrap:wrap; }
    .tab  { display:inline-flex; align-items:center; gap:6px; padding:.45rem 1rem; border-radius:7px; font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); border:none; cursor:pointer; background:none; opacity:.55; transition:all .12s; }
    .tab svg { width:15px; height:15px; }
    .tab:hover { opacity:.9; background:var(--paper-raised); }
    .tab.active { background:var(--sidebar); color:#FFFFFF; opacity:1; }

    .card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.25rem; }
    .card:last-child { margin-bottom:0; }
    .card-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .card-title  { font-family:'Fraunces',serif; font-size:.95rem; font-weight:600; color:var(--ink); }
    .card-body   { padding:1.25rem 1.5rem; }

    .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
    .form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; margin-bottom:1rem; }
    .form-grid-4 { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:1rem; margin-bottom:1rem; }
    @media(max-width:700px) { .form-grid-2,.form-grid-3,.form-grid-4 { grid-template-columns:1fr; } }
    .form-field { display:flex; flex-direction:column; gap:.35rem; }
    .form-field.full { grid-column:1/-1; }
    .form-label { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.5; }
    .form-input, .form-select-inp { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; transition:border-color .15s; }
    .form-input:focus,.form-select-inp:focus { border-color:var(--sidebar-soft); box-shadow:0 0 0 3px rgba(42,63,126,.08); }
    .form-hint  { font-size:.75rem; color:var(--ink); opacity:.4; margin-top:2px; }
    .form-error { font-size:.75rem; color:var(--accent-red); margin-top:.2rem; }
    .form-actions { display:flex; justify-content:flex-end; gap:.65rem; padding-top:1.25rem; border-top:1px solid var(--line); margin-top:1.25rem; }

    .btn-save { display:inline-flex; align-items:center; gap:5px; padding:.5rem 1.25rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
    .btn-save svg { width:14px; height:14px; }
    .btn-sm { display:inline-flex; align-items:center; gap:4px; padding:.35rem .75rem; border-radius:6px; font-size:.8125rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
    .btn-sm svg { width:13px; height:13px; }
    .btn-add  { background:rgba(42,63,126,.08); color:var(--sidebar-soft); }
    .btn-add:hover { background:rgba(42,63,126,.16); }
    .btn-del  { background:rgba(224,92,58,.08); color:var(--accent-red); }
    .btn-del:hover { background:rgba(224,92,58,.16); }

    /* Toggle */
    .toggle-row { display:flex; align-items:center; justify-content:space-between; padding:.875rem 0; border-bottom:1px solid var(--line); }
    .toggle-row:last-child { border-bottom:none; padding-bottom:0; }
    .toggle-label { font-size:.875rem; font-weight:500; color:var(--ink); }
    .toggle-desc  { font-size:.8rem; color:var(--ink); opacity:.5; margin-top:2px; }
    .toggle-switch { position:relative; width:40px; height:22px; cursor:pointer; flex-shrink:0; }
    .toggle-switch input { opacity:0; width:0; height:0; }
    .toggle-slider { position:absolute; inset:0; border-radius:22px; background:var(--line); transition:background .2s; }
    .toggle-slider::before { content:''; position:absolute; width:16px; height:16px; border-radius:50%; background:white; top:3px; left:3px; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
    .toggle-switch input:checked + .toggle-slider { background:var(--sidebar-soft); }
    .toggle-switch input:checked + .toggle-slider::before { transform:translateX(18px); }

    /* Method selector */
    .method-cards { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin-bottom:1rem; }
    .method-card { padding:.875rem 1rem; border-radius:8px; border:1.5px solid var(--line); background:var(--paper); cursor:pointer; transition:all .12s; }
    .method-card.selected { border-color:var(--sidebar); background:rgba(42,63,126,.05); }
    .method-card-title { font-size:.875rem; font-weight:600; color:var(--ink); margin-bottom:3px; }
    .method-card-desc  { font-size:.75rem; color:var(--ink); opacity:.5; }
    .method-check { width:14px; height:14px; border-radius:50%; border:2px solid var(--line); float:right; margin-top:2px; transition:all .12s; }
    .method-card.selected .method-check { border-color:var(--sidebar); background:var(--sidebar); }

    /* Period system */
    .period-chips { display:flex; gap:.5rem; }
    .period-chip { flex:1; padding:.55rem; border-radius:8px; border:1.5px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:500; text-align:center; cursor:pointer; transition:all .12s; color:var(--ink); }
    .period-chip.active { border-color:var(--sidebar); background:rgba(42,63,126,.07); color:var(--sidebar); font-weight:700; }

    /* Types d'évaluation */
    .type-row { display:flex; align-items:center; gap:.75rem; padding:.75rem 0; border-bottom:1px solid var(--line); }
    .type-row:last-child { border-bottom:none; }
    .type-name { font-size:.875rem; font-weight:600; color:var(--ink); text-transform:capitalize; flex:1; }
    .type-weight-wrap { display:flex; align-items:center; gap:.4rem; }
    .weight-input { width:70px; padding:.4rem .6rem; border-radius:7px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'JetBrains Mono',monospace; font-weight:600; color:var(--ink); outline:none; text-align:center; }
    .weight-input:focus { border-color:var(--sidebar-soft); }
    .weight-pct { font-family:'JetBrains Mono',monospace; font-size:13px; color:var(--ink); opacity:.4; }
    .total-bar { display:flex; align-items:center; gap:.5rem; padding:.65rem 1rem; border-radius:8px; margin-top:.75rem; font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:700; }
    .total-ok   { background:rgba(30,120,80,.08); border:1px solid rgba(30,120,80,.2); color:#166534; }
    .total-warn { background:rgba(224,92,58,.08); border:1px solid rgba(224,92,58,.2); color:var(--accent-red); }

    /* Mentions */
    .mention-row { display:grid; grid-template-columns:1fr auto auto; gap:.65rem; align-items:center; margin-bottom:.6rem; }
    .apprec-row  { display:grid; grid-template-columns:2fr auto auto; gap:.65rem; align-items:center; margin-bottom:.6rem; }

    /* Grading periods */
    .period-card { border:1px solid var(--line); border-radius:10px; overflow:hidden; margin-bottom:.875rem; }
    .period-card:last-child { margin-bottom:0; }
    .period-card-header { padding:.875rem 1.25rem; display:flex; align-items:center; justify-content:space-between; }
    .period-card.open   { border-color:rgba(30,120,80,.3); }
    .period-card.closed { border-color:var(--line); }
    .period-card.open .period-card-header   { background:rgba(30,120,80,.05); }
    .period-card.closed .period-card-header { background:var(--paper); }
    .period-name { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); }
    .period-open-badge  { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:2px 8px; border-radius:4px; background:rgba(30,120,80,.1); color:#166534; }
    .period-closed-badge{ font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:2px 8px; border-radius:4px; background:rgba(0,0,0,.05); color:var(--ink); opacity:.5; }
    .period-card-body { padding:.875rem 1.25rem; border-top:1px solid var(--line); background:var(--paper); }
    .period-dates { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin-bottom:.75rem; }
    .period-note-input { width:100%; padding:.4rem .65rem; border-radius:7px; border:1px solid var(--line); background:var(--paper-raised); font-size:.8125rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; }
    .period-note-input:focus { border-color:var(--sidebar-soft); }

    /* Toast */
    .toast { display:flex; align-items:center; gap:.6rem; padding:.65rem 1rem; border-radius:8px; font-size:.875rem; font-weight:500; margin-bottom:1rem; animation:slideDown .15s ease; }
    .toast-ok { background:rgba(30,120,80,.1); border:1px solid rgba(30,120,80,.2); color:#166534; }
    .toast svg { width:15px; height:15px; flex-shrink:0; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-4px);} to{opacity:1;transform:translateY(0);} }
</style>

<div>
    {{-- Tabs --}}
    <div class="tabs">
        @foreach ([
            ['k'=>'scale',   'l'=>'Barème & Calcul',       'i'=>'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
            ['k'=>'types',   'l'=>'Types d\'évaluation',   'i'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            ['k'=>'mentions','l'=>'Mentions & Bulletins',   'i'=>'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z'],
            ['k'=>'periods', 'l'=>'Saisie des notes',      'i'=>'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
        ] as $t)
            <button type="button" wire:click="$set('activeTab','{{ $t['k'] }}')"
                    class="tab {{ $activeTab===$t['k'] ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $t['i'] }}"/>
                </svg>
                {{ $t['l'] }}
            </button>
        @endforeach
    </div>

    @if ($savedScale || $savedTypes || $savedMentions || $savedPeriods)
        <div class="toast toast-ok" x-data x-init="setTimeout(() => $el.remove(), 3000)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Configuration d'évaluation enregistrée.
        </div>
    @endif

    {{-- ══ BARÈME & CALCUL ══ --}}
    @if ($activeTab === 'scale')

        <div class="card">
            <div class="card-header"><span class="card-title">Barème de notation</span></div>
            <div class="card-body">
                <div class="form-grid-4">
                    <div class="form-field">
                        <label class="form-label">Note maximale</label>
                        <input wire:model="max_score" type="number" min="10" max="100" class="form-input">
                        <span class="form-hint">Ex: 20 (français) ou 100 (américain)</span>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Note de passage</label>
                        <input wire:model="passing_score" type="number" min="0" class="form-input">
                        <span class="form-hint">En dessous = insuffisant</span>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Décimales</label>
                        <input wire:model="decimal_places" type="number" min="0" max="4" class="form-input">
                    </div>
                    <div class="form-field">
                        <label class="form-label">Notes min / période</label>
                        <input wire:model="min_grades_per_period" type="number" min="0" max="20" class="form-input">
                        <span class="form-hint">Par matière</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Méthode de calcul de la moyenne générale</span></div>
            <div class="card-body">
                <div class="method-cards">
                    <div class="method-card {{ $average_method === 'weighted_coefficient' ? 'selected' : '' }}"
                         wire:click="$set('average_method','weighted_coefficient')">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                            <div class="method-card-title">Pondérée par coefficient</div>
                            <div class="method-check"></div>
                        </div>
                        <div class="method-card-desc">La moyenne de chaque matière est multipliée par son coefficient. Méthode standard en France et Djibouti.</div>
                    </div>
                    <div class="method-card {{ $average_method === 'simple_average' ? 'selected' : '' }}"
                         wire:click="$set('average_method','simple_average')">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                            <div class="method-card-title">Moyenne simple</div>
                            <div class="method-check"></div>
                        </div>
                        <div class="method-card-desc">Toutes les matières ont le même poids, indépendamment du coefficient.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Système de périodes</span></div>
            <div class="card-body">
                <div class="period-chips">
                    @foreach ([['val'=>'trimester','lbl'=>'Trimestres (3)'],['val'=>'semester','lbl'=>'Semestres (2)'],['val'=>'annual','lbl'=>'Annuel (1)']] as $opt)
                        <button type="button"
                                wire:click="$set('period_system','{{ $opt['val'] }}')"
                                class="period-chip {{ $period_system === $opt['val'] ? 'active' : '' }}">
                            {{ $opt['lbl'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Options de calcul</span></div>
            <div class="card-body">
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">Supprimer la note la plus basse</div>
                        <div class="toggle-desc">La plus mauvaise note de chaque matière est écartée du calcul de la moyenne.</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" wire:model="drop_lowest_grade">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;">
            <button wire:click="saveScale" class="btn-save">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                Enregistrer
            </button>
        </div>

    @endif

    {{-- ══ TYPES D'ÉVALUATION ══ --}}
    @if ($activeTab === 'types')

        <div class="card">
            <div class="card-header">
                <span class="card-title">Types d'évaluation & Poids</span>
                <span style="font-size:.8rem;color:var(--ink);opacity:.5;">La somme des poids doit être 0 ou 100%</span>
            </div>
            <div class="card-body">
                @foreach ($evaluation_types as $i => $type)
                    <div class="type-row">
                        <div class="type-name">{{ ucfirst($type) }}</div>
                        <div class="type-weight-wrap">
                            <input wire:model="evaluation_weights.{{ $type }}"
                                   type="number" min="0" max="100"
                                   class="weight-input">
                            <span class="weight-pct">%</span>
                        </div>
                        <button wire:click="removeType('{{ $type }}')" class="btn-sm btn-del">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                @endforeach

                {{-- Total --}}
                @php $total = array_sum($evaluation_weights); @endphp
                <div class="total-bar {{ $total === 100 || $total === 0 ? 'total-ok' : 'total-warn' }}">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        @if ($total === 100 || $total === 0)
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        @else
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        @endif
                    </svg>
                    Total : {{ $total }}%
                    @if ($total !== 100 && $total !== 0)
                        <span style="font-size:11px;font-weight:400;">— ajuste pour atteindre 100%</span>
                    @endif
                </div>

                @error('evaluation_weights')
                    <div style="color:var(--accent-red);font-size:.8rem;margin-top:.5rem;">{{ $message }}</div>
                @enderror

                {{-- Ajouter un type --}}
                <div style="display:flex;gap:.65rem;align-items:flex-end;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--line);">
                    <div class="form-field" style="flex:1;margin:0;">
                        <label class="form-label">Ajouter un type</label>
                        <input wire:model="newTypeName" type="text" class="form-input" placeholder="Ex: TP, Projet, Oral...">
                    </div>
                    <div class="form-field" style="width:100px;margin:0;">
                        <label class="form-label">Poids %</label>
                        <input wire:model="newTypeWeight" type="number" min="0" max="100" class="form-input">
                    </div>
                    <button wire:click="addType" class="btn-sm btn-add" style="height:38px;">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Ajouter
                    </button>
                </div>

                <div class="form-actions">
                    <button wire:click="saveTypes" class="btn-save">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                        Enregistrer
                    </button>
                </div>
            </div>
        </div>

    @endif

    {{-- ══ MENTIONS & BULLETINS ══ --}}
    @if ($activeTab === 'mentions')

        {{-- Mentions --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">Mentions</span>
                <button wire:click="addMention" class="btn-sm btn-add">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Ajouter
                </button>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:2fr 1fr auto;gap:.5rem;margin-bottom:.5rem;">
                    <span class="form-label">Libellé</span>
                    <span class="form-label">À partir de (/ {{ $max_score }})</span>
                    <span></span>
                </div>
                @foreach ($mentions as $i => $mention)
                    <div class="mention-row">
                        <input wire:model="mentions.{{ $i }}.label" type="text" class="form-input" placeholder="Ex: Très Bien">
                        <input wire:model="mentions.{{ $i }}.min" type="number" min="0" :max="max_score" class="form-input">
                        <button wire:click="removeMention({{ $i }})" class="btn-sm btn-del">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                @endforeach
                <span class="form-hint">Les mentions sont appliquées de la plus haute à la plus basse.</span>
            </div>
        </div>

        {{-- Appréciations --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">Appréciations automatiques</span>
                <button wire:click="addAppreciation" class="btn-sm btn-add">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Ajouter
                </button>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:2fr 1fr auto;gap:.5rem;margin-bottom:.5rem;">
                    <span class="form-label">Appréciation générée automatiquement</span>
                    <span class="form-label">Moyenne min</span>
                    <span></span>
                </div>
                @foreach ($appreciations as $i => $apprec)
                    <div class="apprec-row">
                        <input wire:model="appreciations.{{ $i }}.label" type="text" class="form-input" placeholder="Ex: Excellent travail...">
                        <input wire:model="appreciations.{{ $i }}.min" type="number" min="0" class="form-input">
                        <button wire:click="removeAppreciation({{ $i }})" class="btn-sm btn-del">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Options bulletin --}}
        <div class="card">
            <div class="card-header"><span class="card-title">Options d'affichage du bulletin</span></div>
            <div class="card-body">
                @foreach ([
                    ['prop'=>'show_rank',                'label'=>'Afficher le rang de l\'élève',       'desc'=>'Ex : 3ème / 35 élèves'],
                    ['prop'=>'show_class_average',       'label'=>'Afficher la moyenne de la classe',   'desc'=>'Comparaison avec la moyenne générale de la classe'],
                    ['prop'=>'show_min_max',             'label'=>'Afficher les notes min/max',         'desc'=>'Note la plus haute et la plus basse de la classe par matière'],
                    ['prop'=>'show_teacher_appreciation','label'=>'Appréciation par matière',           'desc'=>'L\'enseignant peut saisir une appréciation individuelle'],
                    ['prop'=>'show_absences_on_bulletin','label'=>'Absences sur le bulletin',           'desc'=>'Nombre d\'absences et retards affichés en bas du bulletin'],
                ] as $opt)
                    <div class="toggle-row">
                        <div>
                            <div class="toggle-label">{{ $opt['label'] }}</div>
                            <div class="toggle-desc">{{ $opt['desc'] }}</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" wire:model="{{ $opt['prop'] }}">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;">
            <button wire:click="saveMentions" class="btn-save">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                Enregistrer
            </button>
        </div>

    @endif

    {{-- ══ SAISIE DES NOTES (PÉRIODES) ══ --}}
    @if ($activeTab === 'periods')

        <div style="font-size:.875rem;color:var(--ink);opacity:.6;margin-bottom:1.25rem;padding:.875rem 1.25rem;background:rgba(42,63,126,.04);border:1px solid rgba(42,63,126,.1);border-radius:10px;">
            Contrôlez ici les périodes pendant lesquelles les enseignants peuvent saisir les notes.
            Les directeurs et administrateurs peuvent toujours saisir, quelle que soit la configuration.
            <strong>Année : {{ $year?->label }}</strong>
        </div>

        @foreach ($gradingPeriods as $period => $data)
            @php $isOpen = $data['is_open']; @endphp
            <div class="period-card {{ $isOpen ? 'open' : 'closed' }}">
                <div class="period-card-header">
                    <div>
                        <div class="period-name">{{ $period }}</div>
                        @if ($isOpen && ($data['open_from'] || $data['open_until']))
                            <div style="font-size:.8rem;color:#166634;margin-top:2px;">
                                @if ($data['open_from']) Du {{ \Carbon\Carbon::parse($data['open_from'])->format('d/m/Y') }} @endif
                                @if ($data['open_until']) au {{ \Carbon\Carbon::parse($data['open_until'])->format('d/m/Y') }} @endif
                            </div>
                        @endif
                    </div>
                    <div style="display:flex;align-items:center;gap:.875rem;">
                        <span class="{{ $isOpen ? 'period-open-badge' : 'period-closed-badge' }}">
                            {{ $isOpen ? '🔓 Ouverte' : '🔒 Fermée' }}
                        </span>
                        <label class="toggle-switch">
                            <input type="checkbox"
                                   wire:model="gradingPeriods.{{ $period }}.is_open"
                                   wire:change="togglePeriod('{{ $period }}')">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <div class="period-card-body">
                    <div class="period-dates">
                        <div class="form-field" style="margin:0;">
                            <label class="form-label">Ouverture de la saisie</label>
                            <input wire:model="gradingPeriods.{{ $period }}.open_from"
                                   type="date" class="form-input">
                        </div>
                        <div class="form-field" style="margin:0;">
                            <label class="form-label">Clôture de la saisie</label>
                            <input wire:model="gradingPeriods.{{ $period }}.open_until"
                                   type="date" class="form-input">
                        </div>
                    </div>
                    <div class="form-field" style="margin:0;">
                        <label class="form-label">Message aux enseignants (optionnel)</label>
                        <input wire:model="gradingPeriods.{{ $period }}.note"
                               type="text" class="period-note-input"
                               placeholder="Ex: Les notes doivent être saisies avant le 15 décembre...">
                    </div>
                </div>
            </div>
        @endforeach

        <div style="display:flex;justify-content:flex-end;margin-top:1rem;">
            <button wire:click="savePeriods" class="btn-save">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                Enregistrer toutes les périodes
            </button>
        </div>

    @endif
</div>