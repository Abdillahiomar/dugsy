<?php

use App\Models\Bulletin;
use App\Models\Student;
use App\Models\Attendance;
use App\Services\BulletinService;
use App\Services\GradingConfigService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component
{
    public Student  $student;
    public Bulletin $bulletin;

    public string  $comment  = '';
    public bool    $saved    = false;

    public function mount(Student $student, Bulletin $bulletin): void
    {
        $this->student  = $student;
        $this->bulletin = $bulletin;
        $this->comment  = $bulletin->general_comment ?? '';
    }

    public function saveComment(): void
    {
        $this->bulletin->update(['general_comment' => $this->comment]);
        $this->saved = true;
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        // Config grading de l'école
        $config  = GradingConfigService::get($schoolId);

        $service = new BulletinService();
        $ssy     = $this->bulletin->studentSchoolYear()
            ->with(['schoolClass.level', 'academicYear', 'schoolClass.classSubjects.subject'])
            ->first();

        $data   = $service->calculate($ssy, $this->bulletin->period);
        $school = auth()->user()->school;

        // Absences pour cette période (conditionnel selon config)
        $absenceStats = null;
        if ($config->show_absences_on_bulletin) {
            $absenceStats = [
                'absent'  => Attendance::where('student_school_year_id', $ssy->id)
                    ->where('status', 'absent')->count(),
                'late'    => Attendance::where('student_school_year_id', $ssy->id)
                    ->where('status', 'late')->count(),
                'excused' => Attendance::where('student_school_year_id', $ssy->id)
                    ->where('status', 'excused')->count(),
            ];
        }

        return compact('data', 'ssy', 'school', 'config', 'absenceStats');
    }
}; ?>

<style>
    .bc { display:flex; align-items:center; gap:.5rem; font-size:.8125rem; margin-bottom:1.25rem; color:var(--ink); opacity:.5; }
    .bc a { color:inherit; text-decoration:none; }
    .bc a:hover { color:var(--sidebar-soft); opacity:1; }
    .bc svg { width:14px; height:14px; }
    .bc-cur { opacity:1; font-weight:600; color:var(--ink); }

    .bulletin-layout { display:grid; grid-template-columns:1fr 260px; gap:1.5rem; align-items:start; }
    @media(max-width:900px) { .bulletin-layout { grid-template-columns:1fr; } }

    /* ── Feuille de bulletin ── */
    .bulletin-sheet { border-radius:12px; border:1px solid var(--line); background:#FFFFFF; overflow:hidden; font-family:'Inter',sans-serif; }

    .sheet-header { padding:1.25rem 1.75rem; background:var(--sidebar); display:flex; align-items:center; justify-content:space-between; }
    .sheet-school-name { font-family:'Fraunces',serif; font-size:1.2rem; font-weight:600; color:#FFFFFF; }
    .sheet-school-sub  { font-size:.8rem; color:rgba(255,255,255,.6); margin-top:2px; }
    .sheet-logo { width:48px; height:48px; border-radius:8px; object-fit:contain; }
    .sheet-logo-ph { width:48px; height:48px; border-radius:8px; background:rgba(255,255,255,.15); display:flex; align-items:center; justify-content:center; font-family:'Fraunces',serif; font-size:1.25rem; font-weight:700; color:#FFFFFF; }

    .sheet-title-bar { display:flex; align-items:center; justify-content:space-between; padding:.875rem 1.75rem; border-bottom:2px solid var(--accent); background:rgba(42,63,126,.03); }
    .sheet-title  { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); }
    .sheet-period { font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600; padding:3px 10px; border-radius:5px; background:rgba(232,168,56,.15); color:#8A6010; }

    .sheet-student-bar { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; padding:1rem 1.75rem; border-bottom:1px solid var(--line); background:var(--paper); }
    .sheet-info-label { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; margin-bottom:3px; }
    .sheet-info-value { font-size:.875rem; font-weight:600; color:var(--ink); }

    /* Tableau matières */
    .sheet-body { padding:1.25rem 1.75rem; }
    .grades-table { width:100%; border-collapse:collapse; margin-bottom:1.25rem; }
    .grades-table th { text-align:left; padding:.55rem .75rem; background:var(--paper); border-bottom:2px solid var(--sidebar); font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--ink); opacity:.6; }
    .grades-table th:not(:first-child) { text-align:center; }
    .grades-table td { padding:.6rem .75rem; border-bottom:1px solid var(--line); font-size:.8125rem; vertical-align:middle; }
    .grades-table td:not(:first-child) { text-align:center; }
    .grades-table tr:last-child td { border-bottom:none; }
    .grades-table tr:hover td { background:rgba(30,45,90,.02); }

    .subj-name-cell { display:flex; align-items:center; gap:.5rem; }
    .subj-dot-sm { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .grade-score  { font-family:'JetBrains Mono',monospace; font-weight:700; font-size:13px; }
    .score-good   { color:#166534; }
    .score-mid    { color:#8A6010; }
    .score-bad    { color:var(--accent-red); }
    .score-na     { color:var(--ink); opacity:.25; }
    .coeff-badge  { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:1px 6px; border-radius:3px; background:rgba(42,63,126,.08); color:var(--sidebar-soft); }
    .mention-sm   { font-size:.75rem; color:var(--ink); opacity:.55; }
    .teacher-sm   { font-size:.75rem; color:var(--ink); opacity:.5; }

    .general-row td { background:var(--sidebar) !important; color:#FFFFFF !important; font-weight:600; }
    .general-row .grade-score { color:#FFFFFF; }

    /* Décision d'admission */
    .decision-section { padding:.875rem 1.75rem; border-top:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; gap:1rem; }
    .decision-block { }
    .decision-section-label { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; margin-bottom:.4rem; }
    .decision-badge { display:inline-flex; align-items:center; gap:.4rem; font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:700; padding:4px 12px; border-radius:6px; letter-spacing:.04em; text-transform:uppercase; }
    .decision-badge svg { width:13px; height:13px; }
    .db-admis_felicitations   { background:rgba(30,120,80,.12); color:#166534; border:1px solid rgba(30,120,80,.25); }
    .db-admis_encouragements  { background:rgba(42,63,126,.1);  color:var(--sidebar-soft); border:1px solid rgba(42,63,126,.2); }
    .db-admis                 { background:rgba(99,102,241,.1); color:#3730A3; border:1px solid rgba(99,102,241,.2); }
    .db-passage_conditionnel  { background:rgba(232,168,56,.12);color:#8A6010; border:1px solid rgba(232,168,56,.25); }
    .db-redoublant            { background:rgba(224,92,58,.1);  color:var(--accent-red); border:1px solid rgba(224,92,58,.2); }

    /* Absences */
    .absences-bar { display:flex; gap:1.5rem; padding:.75rem 1.75rem; background:rgba(42,63,126,.03); border-top:1px solid var(--line); }
    .abs-item { display:flex; align-items:center; gap:.4rem; font-size:.8125rem; }
    .abs-dot  { width:8px; height:8px; border-radius:50%; }
    .abs-label { color:var(--ink); opacity:.55; }
    .abs-val   { font-family:'JetBrains Mono',monospace; font-weight:700; }

    /* Footer signatures */
    .sheet-footer { padding:1rem 1.75rem; border-top:1px solid var(--line); display:grid; grid-template-columns:2fr 1fr 1fr; gap:1rem; }
    .footer-block { }
    .footer-label { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; margin-bottom:.35rem; }
    .footer-value { font-size:.875rem; font-weight:500; color:var(--ink); font-style:italic; }
    .footer-sign  { border:1px dashed var(--line); border-radius:4px; height:55px; margin-top:.35rem; }

    /* Sidebar */
    .side-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1rem; }
    .side-card:last-child { margin-bottom:0; }
    .side-card-header { padding:.75rem 1rem; border-bottom:1px solid var(--line); font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; }
    .side-card-body   { padding:.875rem 1rem; }
    .side-row { display:flex; justify-content:space-between; align-items:center; padding:.35rem 0; border-bottom:1px solid var(--line); font-size:.8125rem; }
    .side-row:last-child { border-bottom:none; }
    .side-label { color:var(--ink); opacity:.55; }
    .side-value { font-weight:600; color:var(--ink); font-family:'JetBrains Mono',monospace; font-size:.8125rem; }

    .btn-pdf  { display:flex; align-items:center; justify-content:center; gap:6px; width:100%; padding:.6rem; border-radius:9px; background:var(--accent-red); color:#FFFFFF; font-size:.875rem; font-weight:700; font-family:'Inter',sans-serif; border:none; cursor:pointer; text-decoration:none; transition:opacity .15s; margin-bottom:.65rem; }
    .btn-pdf:hover { opacity:.9; }
    .btn-pdf svg { width:16px; height:16px; }
    .btn-back { display:flex; align-items:center; justify-content:center; gap:5px; width:100%; padding:.5rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; text-decoration:none; }

    .comment-area { width:100%; padding:.5rem .65rem; border-radius:7px; border:1px solid var(--line); background:var(--paper); font-size:.8125rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; resize:vertical; min-height:80px; }
    .comment-area:focus { border-color:var(--sidebar-soft); }
    .btn-save-comment { width:100%; margin-top:.5rem; padding:.4rem; border-radius:7px; background:var(--sidebar); color:#FFFFFF; font-size:.8125rem; font-weight:600; border:none; cursor:pointer; }

    .toast { display:flex; align-items:center; gap:.6rem; padding:.65rem 1rem; border-radius:8px; font-size:.875rem; font-weight:500; margin-bottom:1rem; animation:slideDown .15s ease; }
    .toast-ok { background:rgba(30,120,80,.1); border:1px solid rgba(30,120,80,.2); color:#166534; }
    .toast svg { width:15px; height:15px; flex-shrink:0; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-4px);} to{opacity:1;transform:translateY(0);} }
</style>

<div>
    <div class="bc">
        <a href="{{ route('students.show', $student) }}">{{ $student->fullName() }}</a>
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        <span class="bc-cur">Bulletin — {{ $bulletin->period }}</span>
    </div>

    @if ($saved)
        <div class="toast toast-ok" x-data x-init="setTimeout(() => $el.remove(), 3000)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Appréciation enregistrée.
        </div>
    @endif

    {{-- Variables de config --}}
    @php
        $passing       = $config->passing_score;
        $maxScore      = $config->max_score;
        $decimals      = $config->decimal_places;
        $goodThreshold = $maxScore * 0.70;

        $decisionLabels = [
            'admis_felicitations'  => 'Admis avec félicitations',
            'admis_encouragements' => 'Admis avec encouragements',
            'admis'                => 'Admis',
            'passage_conditionnel' => 'Passage conditionnel',
            'redoublant'           => 'Redoublant',
        ];
        $decisionIcons = [
            'admis_felicitations'  => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'admis_encouragements' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
            'admis'                => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            'passage_conditionnel' => 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
            'redoublant'           => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        ];
        $decision = $bulletin->decision ?? 'admis';
    @endphp

    <div class="bulletin-layout">

        {{-- Feuille bulletin --}}
        <div class="bulletin-sheet">

            {{-- En-tête école --}}
            <div class="sheet-header">
                <div>
                    <div class="sheet-school-name">{{ $school->name }}</div>
                    <div class="sheet-school-sub">
                        {{ $school->school_type }}
                        @if ($school->city) · {{ $school->city }} @endif
                        @if ($school->ministry_code) · {{ $school->ministry_code }} @endif
                    </div>
                    @if ($school->phone)
                        <div class="sheet-school-sub">Tél : {{ $school->phone }}</div>
                    @endif
                </div>
                @if ($school->logo_path)
                    <img src="{{ asset('storage/'.$school->logo_path) }}" class="sheet-logo" alt="Logo">
                @else
                    <div class="sheet-logo-ph">{{ strtoupper(substr($school->name,0,1)) }}</div>
                @endif
            </div>

            {{-- Titre --}}
            <div class="sheet-title-bar">
                <span class="sheet-title">BULLETIN DE NOTES</span>
                <span class="sheet-period">{{ $bulletin->period }} · {{ $ssy->academicYear->label }}</span>
            </div>

            {{-- Infos élève --}}
            <div class="sheet-student-bar">
                <div class="sheet-info-item">
                    <div class="sheet-info-label">Nom & Prénom</div>
                    <div class="sheet-info-value">{{ $student->fullName() }}</div>
                </div>
                <div class="sheet-info-item">
                    <div class="sheet-info-label">Matricule</div>
                    <div class="sheet-info-value">{{ $student->matricule }}</div>
                </div>
                <div class="sheet-info-item">
                    <div class="sheet-info-label">Classe</div>
                    <div class="sheet-info-value">{{ $ssy->schoolClass->name }}</div>
                </div>
                <div class="sheet-info-item">
                    <div class="sheet-info-label">Année scolaire</div>
                    <div class="sheet-info-value">{{ $ssy->academicYear->label }}</div>
                </div>
            </div>

            {{-- Tableau des matières --}}
            <div class="sheet-body">
                <table class="grades-table">
                    <thead>
                        <tr>
                            <th style="width:28%;">Matière</th>
                            <th>Coeff</th>
                            <th>Notes</th>
                            <th>Moyenne</th>
                            <th>Mention</th>
                            @if ($config->show_teacher_appreciation)
                                <th>Appréciation</th>
                            @endif
                            <th>Enseignant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['subject_lines'] as $line)
                            @php
                                $avg    = $line['average'];
                                $avgCss = $avg === null
                                    ? 'score-na'
                                    : ($avg >= $goodThreshold ? 'score-good'
                                        : ($avg >= $passing ? 'score-mid' : 'score-bad'));
                            @endphp
                            <tr>
                                <td>
                                    <div class="subj-name-cell">
                                        <div class="subj-dot-sm" style="background:{{ $line['subject_color'] ?? 'var(--sidebar)' }}"></div>
                                        {{ $line['subject_name'] }}
                                    </div>
                                </td>
                                <td><span class="coeff-badge">{{ $line['coefficient'] }}</span></td>
                                <td>
                                    <div style="display:flex;gap:.25rem;flex-wrap:wrap;justify-content:center;">
                                        @foreach ($line['grades'] as $g)
                                            <span style="font-family:'JetBrains Mono',monospace;font-size:11px;padding:1px 5px;border-radius:3px;background:rgba(0,0,0,.05);">
                                                {{ $g['score'] }}
                                            </span>
                                        @endforeach
                                        @if (empty($line['grades']))
                                            <span class="score-na">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="grade-score {{ $avgCss }}">
                                        {{ $avg !== null ? number_format($avg, $decimals) : '—' }}
                                    </span>
                                </td>
                                <td><span class="mention-sm">{{ $line['mention'] }}</span></td>
                                @if ($config->show_teacher_appreciation)
                                    <td><span style="font-size:.75rem;color:var(--ink);opacity:.6;">{{ $line['appreciation'] }}</span></td>
                                @endif
                                <td><span class="teacher-sm">{{ $line['teacher'] }}</span></td>
                            </tr>
                        @endforeach

                        {{-- Ligne moyenne générale --}}
                        @if ($data['general_average'] !== null)
                            <tr class="general-row">
                                <td colspan="3" style="font-weight:700;font-size:.875rem;">
                                    MOYENNE GÉNÉRALE
                                </td>
                                <td>
                                    <span class="grade-score" style="font-size:1rem;">
                                        {{ number_format($data['general_average'], $decimals) }}/{{ $maxScore }}
                                    </span>
                                </td>
                                <td colspan="{{ $config->show_teacher_appreciation ? 3 : 2 }}" style="font-size:.875rem;opacity:.8;">
                                    {{ $data['mention'] }}
                                    {{-- Rang conditionnel --}}
                                    @if ($config->show_rank && $data['rank'])
                                        · Rang {{ $data['rank'] }}e / {{ $data['class_count'] }}
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            {{-- Décision d'admission --}}
            @if ($bulletin->decision)
                <div class="decision-section">
                    <div class="decision-block">
                        <div class="decision-section-label">Décision du conseil de classe</div>
                        <span class="decision-badge db-{{ $decision }}">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $decisionIcons[$decision] ?? $decisionIcons['admis'] }}"/>
                            </svg>
                            {{ $decisionLabels[$decision] ?? ucfirst($decision) }}
                        </span>
                    </div>
                    @if ($config->show_class_average && $data['general_average'] !== null)
                        <div style="text-align:center;">
                            <div style="font-family:'JetBrains Mono',monospace;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--ink);opacity:.4;margin-bottom:3px;">Moyenne générale</div>
                            <div style="font-family:'JetBrains Mono',monospace;font-size:1.5rem;font-weight:700;color:{{ $data['general_average'] >= $passing ? '#166534' : 'var(--accent-red)' }}">
                                {{ number_format($data['general_average'], $decimals) }}/{{ $maxScore }}
                            </div>
                            <div style="font-size:.8rem;color:var(--ink);opacity:.5;">{{ $data['mention'] }}</div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Absences (conditionnel selon config) --}}
            @if ($config->show_absences_on_bulletin && $absenceStats)
                <div class="absences-bar">
                    <div class="abs-item">
                        <div class="abs-dot" style="background:var(--accent-red);"></div>
                        <span class="abs-label">Absences :</span>
                        <span class="abs-val" style="color:var(--accent-red);">{{ $absenceStats['absent'] }}</span>
                    </div>
                    <div class="abs-item">
                        <div class="abs-dot" style="background:#8A6010;"></div>
                        <span class="abs-label">Retards :</span>
                        <span class="abs-val" style="color:#8A6010;">{{ $absenceStats['late'] }}</span>
                    </div>
                    <div class="abs-item">
                        <div class="abs-dot" style="background:#166534;"></div>
                        <span class="abs-label">Justifiées :</span>
                        <span class="abs-val" style="color:#166534;">{{ $absenceStats['excused'] }}</span>
                    </div>
                </div>
            @endif

            {{-- Footer : appréciation + signatures --}}
            <div class="sheet-footer">
                <div class="footer-block">
                    <div class="footer-label">Appréciation générale du conseil de classe</div>
                    <div class="footer-value">
                        {{ $bulletin->general_comment
                            ?: ($data['general_average'] !== null
                                ? GradingConfigService::appreciation($data['general_average'], $config)
                                : '—') }}
                    </div>
                </div>
                <div class="footer-block">
                    <div class="footer-label">Signature du Directeur</div>
                    <div class="footer-sign"></div>
                </div>
                <div class="footer-block">
                    <div class="footer-label">Signature du Parent</div>
                    <div class="footer-sign"></div>
                </div>
            </div>

        </div>

        {{-- Sidebar --}}
        <div style="position:sticky;top:1.5rem;">

            <a href="{{ route('bulletins.pdf', [$student, $bulletin]) }}"
               target="_blank" class="btn-pdf">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Télécharger PDF
            </a>

            <a href="{{ route('students.show', $student) }}" class="btn-back" style="margin-bottom:1rem;">
                ← Retour à la fiche élève
            </a>

            {{-- Résumé --}}
            <div class="side-card">
                <div class="side-card-header">Résumé</div>
                <div class="side-card-body">
                    <div class="side-row">
                        <span class="side-label">Période</span>
                        <span class="side-value">{{ $bulletin->period }}</span>
                    </div>
                    <div class="side-row">
                        <span class="side-label">Moyenne</span>
                        <span class="side-value" style="color:{{ ($data['general_average'] ?? 0) >= $passing ? '#166534' : 'var(--accent-red)' }};">
                            {{ $data['general_average'] !== null
                                ? number_format($data['general_average'], $decimals).'/'.$maxScore
                                : '—' }}
                        </span>
                    </div>
                    <div class="side-row">
                        <span class="side-label">Mention</span>
                        <span class="side-value">{{ $data['mention'] }}</span>
                    </div>
                    @if ($config->show_rank && $data['rank'])
                        <div class="side-row">
                            <span class="side-label">Rang</span>
                            <span class="side-value">{{ $data['rank'] }}e / {{ $data['class_count'] }}</span>
                        </div>
                    @endif
                    <div class="side-row">
                        <span class="side-label">Matières évaluées</span>
                        <span class="side-value">{{ collect($data['subject_lines'])->whereNotNull('average')->count() }}</span>
                    </div>
                    {{-- Décision --}}
                    @if ($bulletin->decision)
                        <div class="side-row" style="padding-top:.6rem;margin-top:.25rem;border-top:1px solid var(--line);border-bottom:none;">
                            <span class="side-label">Décision</span>
                            <span class="decision-badge db-{{ $decision }}" style="font-size:9px;padding:2px 8px;">
                                {{ $decisionLabels[$decision] ?? $decision }}
                            </span>
                        </div>
                    @endif
                    <div class="side-row">
                        <span class="side-label">Généré le</span>
                        <span class="side-value">{{ $bulletin->generated_at?->format('d/m/Y') }}</span>
                    </div>
                </div>
            </div>

            {{-- Appréciation éditable --}}
            @can('bulletins.generate')
                <div class="side-card">
                    <div class="side-card-header">Appréciation</div>
                    <div class="side-card-body">
                        <textarea wire:model="comment" class="comment-area"
                                  placeholder="Saisir une appréciation personnalisée..."></textarea>
                        <button wire:click="saveComment" class="btn-save-comment">Enregistrer</button>
                    </div>
                </div>
            @endcan

        </div>
    </div>
</div>