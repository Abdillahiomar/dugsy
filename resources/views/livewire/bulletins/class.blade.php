<?php

use App\Models\Bulletin;
use App\Models\SchoolClass;
use App\Models\StudentSchoolYear;
use App\Services\AcademicYearService;
use App\Services\BulletinService;
use App\Services\GradingConfigService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component
{
    public SchoolClass $schoolClass;
    public string      $period    = '';
    public bool        $generated = false;

    // [ssy_id => appréciation]
    public array $comments  = [];

    // [ssy_id => décision d'admission]
    public array $decisions = [];

    public function mount(SchoolClass $schoolClass): void
    {
        $this->schoolClass = $schoolClass;

        // Initialiser la période depuis la config de l'école
        $schoolId     = auth()->user()->school_id;
        $config       = GradingConfigService::get($schoolId);
        $this->period = GradingConfigService::periods($config)[0];

        $this->loadData();
    }

    public function updatedPeriod(): void
    {
        $this->generated = false;
        $this->loadData();
    }

    private function loadData(): void
    {
        $year     = AcademicYearService::current();
        $schoolId = auth()->user()->school_id;
        $config   = GradingConfigService::get($schoolId);

        $ssys = StudentSchoolYear::where('school_class_id', $this->schoolClass->id)
            ->where('academic_year_id', $year?->id)
            ->get();

        foreach ($ssys as $ssy) {
            $existing = Bulletin::where('student_school_year_id', $ssy->id)
                ->where('period', $this->period)
                ->first();

            $this->comments[(string) $ssy->id]  = $existing?->general_comment ?? '';

            // Décision par défaut selon la moyenne
            if ($existing?->decision) {
                $this->decisions[(string) $ssy->id] = $existing->decision;
            } else {
                // Auto-calculer la décision par défaut selon la moyenne
                $avg = $existing?->average;
                $this->decisions[(string) $ssy->id] = $this->defaultDecision($avg, $config->passing_score);
            }
        }
    }

    private function defaultDecision(?float $avg, int $passing): string
    {
        if ($avg === null) return 'admis';
        if ($avg >= $passing * 1.5)  return 'admis_felicitations';   // ex: >= 15/20
        if ($avg >= $passing * 1.2)  return 'admis_encouragements';  // ex: >= 12/20
        if ($avg >= $passing)        return 'admis';
        if ($avg >= $passing * 0.85) return 'passage_conditionnel';  // ex: >= 8.5/20
        return 'redoublant';
    }

    public function generateAll(): void
    {
        $year     = AcademicYearService::current();
        $service  = new BulletinService();

        $ssys = StudentSchoolYear::where('school_class_id', $this->schoolClass->id)
            ->where('academic_year_id', $year?->id)
            ->get();

        foreach ($ssys as $ssy) {
            $comment  = $this->comments[(string) $ssy->id] ?? '';
            $decision = $this->decisions[(string) $ssy->id] ?? 'admis';

            $bulletin = $service->generateBulletin($ssy, $this->period, $comment);

            // Sauvegarder la décision
            $bulletin->update(['decision' => $decision]);
        }

        $this->generated = true;
    }

    public function saveDecision(string $ssyId, string $decision): void
    {
        $this->decisions[$ssyId] = $decision;

        // Sauvegarder immédiatement si le bulletin existe
        $bulletin = Bulletin::where('student_school_year_id', $ssyId)
            ->where('period', $this->period)
            ->first();

        $bulletin?->update(['decision' => $decision]);
    }

    public function with(): array
    {
        $year     = AcademicYearService::current();
        $schoolId = auth()->user()->school_id; // ← FIX : était manquant

        // Config grading de l'école
        $config  = GradingConfigService::get($schoolId);
        $periods = GradingConfigService::periods($config);

        $ssys = StudentSchoolYear::where('school_class_id', $this->schoolClass->id)
            ->where('academic_year_id', $year?->id)
            ->with('student')
            ->get()
            ->sortBy('student.last_name');

        $service = new BulletinService();

        $bulletinData = $ssys->map(function ($ssy) use ($service, $config) {
            $data     = $service->calculate($ssy, $this->period);
            $existing = Bulletin::where('student_school_year_id', $ssy->id)
                ->where('period', $this->period)
                ->first();

            return [
                'ssy'      => $ssy,
                'data'     => $data,
                'existing' => $existing,
            ];
        });

        return compact('year', 'ssys', 'bulletinData', 'periods', 'config');
    }
}; ?>

<style>
    .bc { display:flex; align-items:center; gap:.5rem; font-size:.8125rem; margin-bottom:1.25rem; color:var(--ink); opacity:.5; }
    .bc a { color:inherit; text-decoration:none; }
    .bc a:hover { color:var(--sidebar-soft); opacity:1; }
    .bc svg { width:14px; height:14px; }
    .bc-cur { opacity:1; font-weight:600; color:var(--ink); }

    .page-toolbar { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
    .toolbar-left { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }

    .period-chips { display:flex; gap:.4rem; }
    .period-chip { padding:.4rem .875rem; border-radius:7px; border:1.5px solid var(--line); background:var(--paper); font-size:.8125rem; font-weight:500; cursor:pointer; transition:all .12s; color:var(--ink); }
    .period-chip.active { border-color:var(--sidebar); background:rgba(42,63,126,.07); color:var(--sidebar); font-weight:600; }

    .btn-generate { display:inline-flex; align-items:center; gap:5px; padding:.5rem 1.25rem; border-radius:8px; background:#166534; color:#FFFFFF; font-size:.875rem; font-weight:700; font-family:'Inter',sans-serif; border:none; cursor:pointer; transition:background .15s; }
    .btn-generate:hover { background:#14532d; }
    .btn-generate svg { width:15px; height:15px; }

    .toast { display:flex; align-items:center; gap:.6rem; padding:.65rem 1rem; border-radius:8px; font-size:.875rem; font-weight:500; margin-bottom:1rem; animation:slideDown .15s ease; }
    .toast-ok { background:rgba(30,120,80,.1); border:1px solid rgba(30,120,80,.2); color:#166534; }
    .toast svg { width:15px; height:15px; flex-shrink:0; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-4px);} to{opacity:1;transform:translateY(0);} }

    .bulletin-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:1.25rem; }

    /* Carte bulletin */
    .bulletin-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; }
    .bc-header { padding:.875rem 1.25rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .bc-student { display:flex; align-items:center; gap:.65rem; }
    .bc-avatar { width:32px; height:32px; border-radius:50%; background:rgba(42,63,126,.1); color:var(--sidebar-soft); font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .bc-name   { font-weight:600; font-size:.875rem; color:var(--ink); }
    .bc-matric { font-family:'JetBrains Mono',monospace; font-size:10px; color:var(--ink); opacity:.4; }
    .bc-avg    { font-family:'JetBrains Mono',monospace; font-size:1.25rem; font-weight:700; }
    .avg-good { color:#166534; }
    .avg-mid  { color:#8A6010; }
    .avg-bad  { color:var(--accent-red); }
    .avg-na   { color:var(--ink); opacity:.3; }

    /* Matières */
    .bc-subjects { padding:.75rem 1.25rem; border-bottom:1px solid var(--line); }
    .subject-mini { display:flex; align-items:center; justify-content:space-between; padding:.3rem 0; border-bottom:1px solid var(--line); font-size:.8125rem; }
    .subject-mini:last-child { border-bottom:none; }
    .subject-mini-left { display:flex; align-items:center; gap:.5rem; }
    .subj-dot  { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .subj-name { color:var(--ink); }
    .subj-avg  { font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:700; }

    /* Décision d'admission */
    .bc-decision { padding:.75rem 1.25rem; border-bottom:1px solid var(--line); }
    .decision-label { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; margin-bottom:.5rem; }
    .decision-options { display:flex; flex-wrap:wrap; gap:.35rem; }
    .decision-btn {
        padding:.3rem .65rem; border-radius:6px; font-size:.75rem; font-weight:600;
        font-family:'JetBrains Mono',monospace; border:1.5px solid transparent;
        cursor:pointer; transition:all .12s; opacity:.5; text-transform:uppercase;
        letter-spacing:.03em;
    }
    .decision-btn:hover { opacity:.9; }
    .decision-btn.active { opacity:1; }

    /* Couleurs par décision */
    .db-admis_felicitations { background:rgba(30,120,80,.1); color:#166534; border-color:rgba(30,120,80,.2); }
    .db-admis_felicitations.active { background:rgba(30,120,80,.2); border-color:#166534; }

    .db-admis_encouragements { background:rgba(42,63,126,.08); color:var(--sidebar-soft); border-color:rgba(42,63,126,.2); }
    .db-admis_encouragements.active { background:rgba(42,63,126,.15); border-color:var(--sidebar-soft); }

    .db-admis { background:rgba(99,102,241,.08); color:#3730A3; border-color:rgba(99,102,241,.2); }
    .db-admis.active { background:rgba(99,102,241,.15); border-color:#3730A3; }

    .db-passage_conditionnel { background:rgba(232,168,56,.1); color:#8A6010; border-color:rgba(232,168,56,.25); }
    .db-passage_conditionnel.active { background:rgba(232,168,56,.2); border-color:#8A6010; }

    .db-redoublant { background:rgba(224,92,58,.08); color:var(--accent-red); border-color:rgba(224,92,58,.2); }
    .db-redoublant.active { background:rgba(224,92,58,.15); border-color:var(--accent-red); }

    /* Badge décision */
    .decision-badge { display:inline-block; font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; padding:2px 7px; border-radius:4px; text-transform:uppercase; letter-spacing:.04em; }

    /* Appréciation */
    .bc-comment { padding:.75rem 1.25rem 0; }
    .comment-label { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; margin-bottom:.35rem; }
    .comment-input { width:100%; padding:.45rem .65rem; border-radius:7px; border:1px solid var(--line); background:var(--paper); font-size:.8rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; resize:none; }
    .comment-input:focus { border-color:var(--sidebar-soft); }

    /* Footer carte */
    .bc-footer { padding:.75rem 1.25rem; border-top:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; margin-top:.75rem; }
    .bc-rank      { font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--ink); opacity:.5; }
    .bc-generated { font-size:.75rem; color:#166534; }
    .btn-view { display:inline-flex; align-items:center; gap:4px; padding:.3rem .65rem; border-radius:6px; font-size:.8rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; background:rgba(42,63,126,.08); color:var(--sidebar-soft); text-decoration:none; transition:background .12s; }
    .btn-view:hover { background:rgba(42,63,126,.16); }
    .btn-view svg { width:13px; height:13px; }

    /* Légende */
    .legend { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:1.25rem; padding:.875rem 1.25rem; border-radius:10px; background:var(--paper); border:1px solid var(--line); }
    .legend-title { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; width:100%; margin-bottom:.35rem; }
    .legend-item { display:flex; align-items:center; gap:.35rem; font-size:.75rem; color:var(--ink); opacity:.7; }
    .legend-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
</style>

<div>
    <div class="bc">
        <a href="{{ route('grades.index') }}">Notes</a>
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        <span class="bc-cur">Bulletins — {{ $schoolClass->name }}</span>
    </div>

    @if ($generated)
        <div class="toast toast-ok" x-data x-init="setTimeout(() => $el.remove(), 3000)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Bulletins générés pour {{ $ssys->count() }} élèves — décisions d'admission enregistrées.
        </div>
    @endif

    {{-- Toolbar --}}
    <div class="page-toolbar">
        <div class="toolbar-left">
            <div class="period-chips">
                @foreach ($periods as $p)
                    <button type="button"
                            wire:click="$set('period','{{ $p }}')"
                            class="period-chip {{ $period === $p ? 'active' : '' }}">
                        {{ $p }}
                    </button>
                @endforeach
            </div>
            <span style="font-size:.8125rem;color:var(--ink);opacity:.45;">
                {{ $schoolClass->name }} — {{ $year?->label }}
            </span>
        </div>
        <button wire:click="generateAll" class="btn-generate">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            Générer tous les bulletins
        </button>
    </div>

    {{-- Légende décisions --}}
    <div class="legend">
        <div class="legend-title">Décisions d'admission</div>
        @foreach ([
            ['key'=>'admis_felicitations',   'color'=>'#166534',          'label'=>'Admis avec félicitations'],
            ['key'=>'admis_encouragements',  'color'=>'var(--sidebar-soft)','label'=>'Admis avec encouragements'],
            ['key'=>'admis',                 'color'=>'#3730A3',           'label'=>'Admis'],
            ['key'=>'passage_conditionnel',  'color'=>'#8A6010',           'label'=>'Passage conditionnel'],
            ['key'=>'redoublant',            'color'=>'var(--accent-red)', 'label'=>'Redoublant'],
        ] as $d)
            <div class="legend-item">
                <div class="legend-dot" style="background:{{ $d['color'] }}"></div>
                {{ $d['label'] }}
            </div>
        @endforeach
    </div>

    {{-- Grille des bulletins --}}
    <div class="bulletin-grid">
        @foreach ($bulletinData as $item)
            @php
                $avg    = $item['data']['general_average'];
                $passing = $config->passing_score;
                $maxScore = $config->max_score;
                $decimals = $config->decimal_places;
                $goodThreshold = $maxScore * 0.70;

                // Couleur moyenne selon config
                $avgCss = $avg === null
                    ? 'avg-na'
                    : ($avg >= $goodThreshold ? 'avg-good' : ($avg >= $passing ? 'avg-mid' : 'avg-bad'));

                $ssy      = $item['ssy'];
                $ssyKey   = (string) $ssy->id;
                $decision = $this->decisions[$ssyKey] ?? 'admis';

                // Labels des décisions
                $decisionLabels = [
                    'admis_felicitations'  => 'Félicitations',
                    'admis_encouragements' => 'Encouragements',
                    'admis'                => 'Admis',
                    'passage_conditionnel' => 'Conditionnel',
                    'redoublant'           => 'Redoublant',
                ];
            @endphp
            <div class="bulletin-card">

                {{-- En-tête : nom + moyenne --}}
                <div class="bc-header">
                    <div class="bc-student">
                        <div class="bc-avatar">
                            {{ strtoupper(substr($ssy->student->first_name,0,1).substr($ssy->student->last_name,0,1)) }}
                        </div>
                        <div>
                            <div class="bc-name">{{ $ssy->student->fullName() }}</div>
                            <div class="bc-matric">{{ $ssy->student->matricule }}</div>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div class="bc-avg {{ $avgCss }}">
                            {{ $avg !== null ? number_format($avg, $decimals).'/'.$maxScore : '—' }}
                        </div>
                        @if ($item['data']['rank'] && $config->show_rank)
                            <div style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--ink);opacity:.4;">
                                {{ $item['data']['rank'] }}e / {{ $item['data']['class_count'] }}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Matières --}}
                <div class="bc-subjects">
                    @forelse ($item['data']['subject_lines'] as $line)
                        @if ($line['average'] !== null)
                            @php
                                $lineAvg   = $line['average'];
                                $lineColor = $lineAvg >= $goodThreshold
                                    ? '#166534'
                                    : ($lineAvg >= $passing ? '#8A6010' : 'var(--accent-red)');
                            @endphp
                            <div class="subject-mini">
                                <div class="subject-mini-left">
                                    <div class="subj-dot" style="background:{{ $line['subject_color'] ?? 'var(--sidebar)' }}"></div>
                                    <span class="subj-name">{{ $line['subject_name'] }}</span>
                                    <span style="font-family:'JetBrains Mono',monospace;font-size:9px;color:var(--ink);opacity:.35;">({{ $line['coefficient'] }})</span>
                                </div>
                                <span class="subj-avg" style="color:{{ $lineColor }}">
                                    {{ number_format($lineAvg, $decimals) }}
                                </span>
                            </div>
                        @endif
                    @empty
                        <div style="font-size:.8rem;color:var(--ink);opacity:.4;text-align:center;padding:.5rem 0;">
                            Aucune note saisie pour ce trimestre.
                        </div>
                    @endforelse
                </div>

                {{-- Décision d'admission --}}
                <div class="bc-decision">
                    <div class="decision-label">Décision du conseil de classe</div>
                    <div class="decision-options">
                        @foreach ([
                            'admis_felicitations'  => 'Félicitations',
                            'admis_encouragements' => 'Encouragements',
                            'admis'                => 'Admis',
                            'passage_conditionnel' => 'Conditionnel',
                            'redoublant'           => 'Redoublant',
                        ] as $key => $label)
                            <button type="button"
                                    wire:click="saveDecision('{{ $ssy->id }}','{{ $key }}')"
                                    class="decision-btn db-{{ $key }} {{ $decision === $key ? 'active' : '' }}">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Appréciation générale --}}
                <div class="bc-comment">
                    <div class="comment-label">Appréciation générale</div>
                    <textarea wire:model="comments.{{ $ssy->id }}"
                              rows="2"
                              class="comment-input"
                              placeholder="{{ $avg !== null ? GradingConfigService::appreciation($avg, $config) : 'Saisir une appréciation...' }}"></textarea>
                </div>

                {{-- Footer --}}
                <div class="bc-footer">
                    <div>
                        @if ($item['existing'])
                            <span class="bc-generated">✓ Généré le {{ $item['existing']->generated_at?->format('d/m/Y') }}</span>
                        @else
                            <span style="font-size:.75rem;color:var(--ink);opacity:.35;">Non généré</span>
                        @endif
                        {{-- Badge décision si bulletin existant --}}
                        @if ($item['existing']?->decision)
                            <span class="decision-badge db-{{ $item['existing']->decision }}"
                                  style="margin-left:.5rem;">
                                {{ $decisionLabels[$item['existing']->decision] ?? $item['existing']->decision }}
                            </span>
                        @endif
                    </div>
                    @if ($item['existing'])
                        <a href="{{ route('bulletins.show', [$ssy->student, $item['existing']]) }}"
                           target="_blank" class="btn-view">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            Voir
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>