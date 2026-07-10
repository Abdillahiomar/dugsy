<?php

use App\Models\Guardian;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Services\AcademicYearService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public Homework $homework;

    // ── Dépôt de rendu (parent) ───────────────────────────────────
    public ?int $submittingForSsyId = null; // ssy_id de l'enfant
    public $submissionFile = null;
    public bool $submitSaved = false;
    public string $submitError = '';

    // ── Correction (enseignant / admin) ──────────────────────────
    public ?int   $gradingSubmissionId = null;
    public string $gradeValue          = '';
    public string $teacherComment      = '';
    public bool   $gradeSaved          = false;

    // ── Suppression rendu ─────────────────────────────────────────
    public ?int $confirmDeleteSubId = null;

    public function mount(Homework $homework): void
    {
        $this->homework = $homework;
    }

    // ── Rendu par le parent ───────────────────────────────────────

    public function openSubmit(int $ssyId): void
    {
        $this->submittingForSsyId = $ssyId;
        $this->submissionFile     = null;
        $this->submitError        = '';
    }

    public function submitHomework(): void
    {
        $this->submitError = '';
        $this->validate([
            'submissionFile' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ], [
            'submissionFile.required' => 'Veuillez joindre un fichier.',
            'submissionFile.max'      => 'Le fichier ne doit pas dépasser 10 Mo.',
        ]);

        if (! $this->homework->allow_submission) {
            $this->submitError = 'Le rendu en ligne n\'est pas activé pour ce devoir.';
            return;
        }

        // Supprimer l'ancien rendu si existant
        $existing = HomeworkSubmission::where('homework_id', $this->homework->id)
            ->where('student_school_year_id', $this->submittingForSsyId)
            ->first();

        if ($existing) {
            Storage::disk('public')->delete($existing->file_path);
            $existing->delete();
        }

        $path = $this->submissionFile->store('homeworks/submissions', 'public');

        $isLate = now()->gt($this->homework->due_date);

        HomeworkSubmission::create([
            'homework_id'            => $this->homework->id,
            'student_school_year_id' => $this->submittingForSsyId,
            'file_path'              => $path,
            'file_name'              => $this->submissionFile->getClientOriginalName(),
            'file_size'              => $this->formatFileSize($this->submissionFile->getSize()),
            'submitted_by'           => auth()->id(),
            'submitted_at'           => now(),
            'status'                 => $isLate ? 'late' : 'submitted',
        ]);

        $this->submittingForSsyId = null;
        $this->submissionFile     = null;
        $this->submitSaved        = true;
    }

    public function cancelSubmit(): void
    {
        $this->submittingForSsyId = null;
        $this->submissionFile     = null;
        $this->submitError        = '';
    }

    // ── Correction enseignant ─────────────────────────────────────

    public function openGrade(int $submissionId): void
    {
        $sub = HomeworkSubmission::find($submissionId);
        if (! $sub) return;

        $this->gradingSubmissionId = $submissionId;
        $this->gradeValue          = $sub->grade ?? '';
        $this->teacherComment      = $sub->teacher_comment ?? '';
    }

    public function saveGrade(): void
    {
        $this->validate([
            'gradeValue' => 'nullable|numeric|min:0|max:' . $this->homework->subject->coefficient * 5,
        ]);

        HomeworkSubmission::where('id', $this->gradingSubmissionId)->update([
            'grade'          => $this->gradeValue ?: null,
            'teacher_comment'=> $this->teacherComment ?: null,
            'status'         => 'graded',
            'graded_at'      => now(),
        ]);

        $this->gradingSubmissionId = null;
        $this->gradeSaved          = true;
    }

    // ── Suppression rendu ─────────────────────────────────────────

    public function confirmDeleteSub(int $id): void
    {
        $this->confirmDeleteSubId = $id;
    }

    public function deleteSub(): void
    {
        $sub = HomeworkSubmission::find($this->confirmDeleteSubId);
        if ($sub) {
            Storage::disk('public')->delete($sub->file_path);
            $sub->delete();
        }
        $this->confirmDeleteSubId = null;
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' Mo';
        if ($bytes >= 1024)    return round($bytes / 1024, 0) . ' Ko';
        return $bytes . ' o';
    }

    public function with(): array
    {
        $user      = auth()->user();
        $isTeacher = $user->hasRole('enseignant') && ! $user->hasAnyRole(['admin','directeur']);
        $isParent  = $user->hasRole('parent');
        $year      = AcademicYearService::current();

        // Élèves de la classe avec leurs rendus
        $students = StudentSchoolYear::where('school_class_id', $this->homework->school_class_id)
            ->where('academic_year_id', $year?->id)
            ->with(['student','homeworkSubmissions' => fn ($q) =>
                $q->where('homework_id', $this->homework->id)
                  ->with('submittedBy')
            ])
            ->get()
            ->sortBy('student.last_name');

        // Pour le parent → uniquement ses enfants dans cette classe
        $myChildren = collect();
        if ($isParent) {
            $guardian = Guardian::where('user_id', $user->id)->first();
            if ($guardian) {
                $childIds = Student::whereHas('guardians', fn ($q) =>
                    $q->where('guardian_id', $guardian->id)
                )->pluck('id');

                $myChildren = $students->filter(fn ($ssy) =>
                    $childIds->contains($ssy->student_id)
                );
                // Parent ne voit que ses enfants dans la liste
                $students = $myChildren;
            }
        }

        $submittedCount  = HomeworkSubmission::where('homework_id', $this->homework->id)->count();
        $totalStudents   = StudentSchoolYear::where('school_class_id', $this->homework->school_class_id)
            ->where('academic_year_id', $year?->id)->count();

        return compact(
            'students', 'myChildren',
            'isTeacher', 'isParent',
            'submittedCount', 'totalStudents'
        );
    }
}; ?>

<style>
    .bc { display:flex; align-items:center; gap:.5rem; font-size:.8125rem; margin-bottom:1.25rem; color:var(--ink); opacity:.5; }
    .bc a { color:inherit; text-decoration:none; } .bc a:hover { color:var(--sidebar-soft); opacity:1; }
    .bc svg { width:14px; height:14px; }
    .bc-cur { opacity:1; font-weight:600; color:var(--ink); }

    .hw-layout { display:grid; grid-template-columns:1fr 300px; gap:1.5rem; align-items:start; }
    @media(max-width:900px) { .hw-layout { grid-template-columns:1fr; } }

    /* Card */
    .card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.25rem; }
    .card:last-child { margin-bottom:0; }
    .card-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .card-title  { font-family:'Fraunces',serif; font-size:.95rem; font-weight:600; color:var(--ink); }
    .card-body   { padding:1.25rem 1.5rem; }

    /* En-tête devoir */
    .hw-header { border-radius:12px; overflow:hidden; border:1px solid var(--line); margin-bottom:1.25rem; }
    .hw-header-top { padding:1.25rem 1.5rem; background:var(--sidebar); }
    .hw-header-subject { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:rgba(255,255,255,.55); margin-bottom:.35rem; }
    .hw-header-title { font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; color:#FFFFFF; margin-bottom:.5rem; }
    .hw-header-meta { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }
    .hw-meta-chip { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:3px 9px; border-radius:5px; }
    .hw-chip-mandatory  { background:rgba(224,92,58,.2); color:#FFB09E; }
    .hw-chip-optional   { background:rgba(255,255,255,.1); color:rgba(255,255,255,.7); }
    .hw-chip-date       { background:rgba(232,168,56,.2); color:#FFD285; }
    .hw-chip-teacher    { background:rgba(255,255,255,.1); color:rgba(255,255,255,.65); }

    .hw-header-body { padding:1.25rem 1.5rem; background:var(--paper-raised); }
    .hw-desc { font-size:.9375rem; color:var(--ink); opacity:.65; line-height:1.65; }

    /* Info row */
    .info-row { display:flex; align-items:center; gap:.65rem; font-size:.875rem; color:var(--ink); padding:.5rem 0; border-bottom:1px solid var(--line); }
    .info-row:last-child { border-bottom:none; }
    .info-row svg { width:16px; height:16px; color:var(--sidebar-soft); opacity:.6; flex-shrink:0; }
    .info-label { opacity:.55; min-width:120px; }
    .info-value { font-weight:600; }

    /* Télécharger devoir */
    .hw-download { display:flex; align-items:center; justify-content:space-between; padding:1rem 1.25rem; border-radius:10px; background:rgba(42,63,126,.04); border:1px solid rgba(42,63,126,.1); margin-bottom:1.25rem; }
    .hw-dl-info { display:flex; align-items:center; gap:.75rem; }
    .hw-dl-icon { width:36px; height:36px; border-radius:8px; background:var(--sidebar); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .hw-dl-icon svg { width:18px; height:18px; color:#FFFFFF; }
    .hw-dl-name { font-weight:600; font-size:.875rem; color:var(--ink); }
    .hw-dl-hint { font-size:.75rem; color:var(--ink); opacity:.4; margin-top:1px; }
    .btn-dl-big { display:inline-flex; align-items:center; gap:6px; padding:.5rem 1.25rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; text-decoration:none; transition:background .15s; }
    .btn-dl-big:hover { background:var(--sidebar-soft); }
    .btn-dl-big svg { width:15px; height:15px; }

    /* Rendus */
    .submission-row { display:flex; align-items:flex-start; gap:.875rem; padding:.875rem 0; border-bottom:1px solid var(--line); }
    .submission-row:last-child { border-bottom:none; }
    .student-avatar { width:34px; height:34px; border-radius:50%; background:rgba(42,63,126,.1); color:var(--sidebar-soft); font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .student-name { font-weight:600; font-size:.875rem; color:var(--ink); }
    .student-matric { font-family:'JetBrains Mono',monospace; font-size:10px; color:var(--ink); opacity:.4; }

    /* Statut rendu */
    .sub-status { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:2px 8px; border-radius:4px; text-transform:uppercase; white-space:nowrap; }
    .sub-submitted { background:rgba(30,120,80,.1); color:#166534; }
    .sub-late      { background:rgba(232,168,56,.12); color:#8A6010; }
    .sub-graded    { background:rgba(42,63,126,.1); color:var(--sidebar-soft); }
    .sub-missing   { background:rgba(0,0,0,.05); color:var(--ink); opacity:.4; }

    /* Fichier rendu */
    .sub-file { display:inline-flex; align-items:center; gap:.4rem; padding:.3rem .65rem; border-radius:6px; background:var(--paper); border:1px solid var(--line); font-size:.8rem; color:var(--ink); text-decoration:none; margin-top:.35rem; }
    .sub-file:hover { border-color:var(--sidebar-soft); color:var(--sidebar-soft); }
    .sub-file svg { width:13px; height:13px; }
    .sub-meta { font-size:.75rem; color:var(--ink); opacity:.4; margin-top:2px; }
    .sub-grade { font-family:'JetBrains Mono',monospace; font-size:1rem; font-weight:700; color:var(--sidebar-soft); }
    .sub-comment { font-size:.8125rem; color:var(--ink); opacity:.6; font-style:italic; margin-top:.25rem; }

    /* Boutons action */
    .btn-action { display:inline-flex; align-items:center; gap:4px; padding:.3rem .65rem; border-radius:6px; font-size:.8rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; transition:background .12s; }
    .btn-action svg { width:13px; height:13px; }
    .btn-grade   { background:rgba(42,63,126,.08); color:var(--sidebar-soft); }
    .btn-grade:hover { background:rgba(42,63,126,.16); }
    .btn-upload  { background:rgba(30,120,80,.08); color:#166534; }
    .btn-upload:hover { background:rgba(30,120,80,.16); }
    .btn-del-sub { background:rgba(224,92,58,.08); color:var(--accent-red); }
    .btn-del-sub:hover { background:rgba(224,92,58,.16); }

    /* Upload zone inline */
    .inline-upload { margin-top:.65rem; padding:.875rem 1rem; border-radius:8px; background:var(--paper); border:1.5px dashed var(--line); animation:fadeIn .15s ease; }
    @keyframes fadeIn { from{opacity:0;} to{opacity:1;} }
    .upload-label { display:flex; align-items:center; gap:.65rem; cursor:pointer; position:relative; }
    .upload-label input { position:absolute; inset:0; opacity:0; cursor:pointer; }
    .upload-label svg { width:18px; height:18px; color:var(--sidebar-soft); opacity:.55; flex-shrink:0; }
    .upload-label-text { font-size:.875rem; color:var(--ink); opacity:.6; }
    .upload-label-hint { font-size:.75rem; color:var(--ink); opacity:.35; }
    .upload-actions { display:flex; align-items:center; gap:.5rem; margin-top:.65rem; }
    .btn-submit { display:inline-flex; align-items:center; gap:5px; padding:.4rem .875rem; border-radius:7px; background:#166534; color:#FFFFFF; font-size:.8125rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
    .btn-cancel-sm { padding:.4rem .75rem; border-radius:7px; border:1px solid var(--line); background:var(--paper-raised); font-size:.8125rem; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }
    .file-selected { display:inline-flex; align-items:center; gap:.4rem; padding:.25rem .65rem; border-radius:5px; background:rgba(30,120,80,.08); color:#166534; font-size:.8rem; font-weight:600; }

    /* Grading modal */
    .grade-panel { margin-top:.65rem; padding:.875rem 1rem; border-radius:8px; background:var(--paper); border:1px solid rgba(42,63,126,.15); animation:fadeIn .15s ease; }
    .grade-panel-title { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; margin-bottom:.75rem; }
    .grade-row { display:flex; align-items:center; gap:.75rem; margin-bottom:.65rem; }
    .grade-input { width:80px; padding:.4rem .6rem; border-radius:7px; border:1px solid var(--line); background:var(--paper-raised); font-family:'JetBrains Mono',monospace; font-size:14px; font-weight:700; text-align:center; color:var(--sidebar-soft); outline:none; }
    .grade-input:focus { border-color:var(--sidebar-soft); }
    .comment-input { width:100%; padding:.4rem .65rem; border-radius:7px; border:1px solid var(--line); background:var(--paper-raised); font-size:.8125rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; resize:none; min-height:56px; }

    /* Sidebar */
    .side-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1rem; }
    .side-card:last-child { margin-bottom:0; }
    .side-card-header { padding:.75rem 1rem; border-bottom:1px solid var(--line); font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; }
    .side-card-body   { padding:.875rem 1rem; }
    .side-row { display:flex; justify-content:space-between; align-items:center; padding:.35rem 0; border-bottom:1px solid var(--line); font-size:.8125rem; }
    .side-row:last-child { border-bottom:none; }
    .side-label { color:var(--ink); opacity:.55; }
    .side-value { font-weight:600; color:var(--ink); }

    /* Progress bar */
    .progress-wrap { margin-top:.75rem; }
    .progress-bar  { height:6px; border-radius:3px; background:var(--line); overflow:hidden; }
    .progress-fill { height:100%; border-radius:3px; background:var(--sidebar); transition:width .3s; }
    .progress-label { display:flex; justify-content:space-between; font-family:'JetBrains Mono',monospace; font-size:10px; color:var(--ink); opacity:.45; margin-top:.25rem; }

    /* Toast */
    .toast { display:flex; align-items:center; gap:.6rem; padding:.65rem 1rem; border-radius:8px; font-size:.875rem; font-weight:500; margin-bottom:1rem; animation:slideDown .15s ease; }
    .toast-ok { background:rgba(30,120,80,.1); border:1px solid rgba(30,120,80,.2); color:#166534; }
    .toast svg { width:15px; height:15px; flex-shrink:0; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-4px);} to{opacity:1;transform:translateY(0);} }

    /* Modal */
    .modal-overlay { position:fixed; inset:0; z-index:100; background:rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; padding:1rem; }
    .modal { background:var(--paper-raised); border-radius:14px; border:1px solid var(--line); padding:1.75rem; max-width:380px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.2); }
    .modal-title { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; margin-bottom:.5rem; }
    .modal-desc  { font-size:.875rem; color:var(--ink); opacity:.6; margin-bottom:1.25rem; line-height:1.5; }
    .modal-actions { display:flex; justify-content:flex-end; gap:.65rem; }
    .btn-modal-cancel  { padding:.45rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; cursor:pointer; font-family:'Inter',sans-serif; color:var(--ink); }
    .btn-modal-confirm { padding:.45rem 1rem; border-radius:8px; background:var(--accent-red); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
</style>

<div>
    <div class="bc">
        <a href="{{ route('homeworks.index') }}">Devoirs</a>
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        <span class="bc-cur">{{ Str::limit($homework->title, 40) }}</span>
    </div>

    @if ($submitSaved || $gradeSaved)
        <div class="toast toast-ok" x-data x-init="setTimeout(() => $el.remove(), 3000)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $submitSaved ? 'Devoir déposé avec succès.' : 'Note enregistrée.' }}
        </div>
    @endif

    <div class="hw-layout">
        <div>

            {{-- En-tête du devoir --}}
            <div class="hw-header">
                <div class="hw-header-top">
                    <div class="hw-header-subject">{{ $homework->subject->name }} · {{ $homework->schoolClass->name }}</div>
                    <div class="hw-header-title">{{ $homework->title }}</div>
                    <div class="hw-header-meta">
                        <span class="hw-meta-chip hw-chip-date">
                            🗓 À rendre le {{ $homework->due_date->locale('fr')->isoFormat('D MMMM YYYY') }}
                        </span>
                        <span class="hw-meta-chip {{ $homework->is_mandatory ? 'hw-chip-mandatory' : 'hw-chip-optional' }}">
                            {{ $homework->is_mandatory ? 'Obligatoire' : 'Optionnel' }}
                        </span>
                        <span class="hw-meta-chip hw-chip-teacher">
                            {{ $homework->staff->user->name }}
                        </span>
                        @if ($homework->isOverdue())
                            <span class="hw-meta-chip" style="background:rgba(0,0,0,.2);color:rgba(255,255,255,.5);">
                                Terminé
                            </span>
                        @endif
                    </div>
                </div>
                @if ($homework->description)
                    <div class="hw-header-body">
                        <div class="hw-desc">{{ $homework->description }}</div>
                    </div>
                @endif
            </div>

            {{-- Document à télécharger --}}
            @if ($homework->file_path)
                <div class="hw-download">
                    <div class="hw-dl-info">
                        <div class="hw-dl-icon">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <div class="hw-dl-name">{{ $homework->file_name ?? 'Document du devoir' }}</div>
                            <div class="hw-dl-hint">Cliquez pour télécharger et consulter le devoir</div>
                        </div>
                    </div>
                    <a href="{{ $homework->fileUrl() }}" target="_blank" class="btn-dl-big">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Télécharger
                    </a>
                </div>
            @endif

            {{-- Liste des élèves + rendus --}}
            <div class="card">
                <div class="card-header">
                    <span class="card-title">
                        @if ($isParent) Rendu de votre enfant
                        @else Rendus des élèves
                        @endif
                    </span>
                    @if (! $isParent)
                        <span style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--ink);opacity:.4;">
                            {{ $submittedCount }} / {{ $totalStudents }} rendus
                        </span>
                    @endif
                </div>

                <div class="card-body" style="padding-top:.5rem;padding-bottom:.5rem;">
                    @forelse ($students as $ssy)
                        @php
                            $submission = $ssy->homeworkSubmissions->first();
                            $isMyChild  = $isParent; // si parent, c'est forcément son enfant
                        @endphp

                        <div class="submission-row">
                            {{-- Avatar + nom --}}
                            <div class="student-avatar">
                                {{ strtoupper(substr($ssy->student->first_name,0,1).substr($ssy->student->last_name,0,1)) }}
                            </div>

                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;align-items:center;gap:.65rem;flex-wrap:wrap;margin-bottom:.35rem;">
                                    <div>
                                        <div class="student-name">{{ $ssy->student->fullName() }}</div>
                                        <div class="student-matric">{{ $ssy->student->matricule }}</div>
                                    </div>

                                    {{-- Statut --}}
                                    @if ($submission)
                                        <span class="sub-status sub-{{ $submission->status }}">
                                            {{ match($submission->status) {
                                                'submitted' => 'Rendu',
                                                'late'      => 'Rendu en retard',
                                                'graded'    => 'Corrigé',
                                                default     => $submission->status,
                                            } }}
                                        </span>
                                        @if ($submission->grade !== null)
                                            <span class="sub-grade">{{ $submission->grade }}/20</span>
                                        @endif
                                    @else
                                        <span class="sub-status sub-missing">Non rendu</span>
                                    @endif
                                </div>

                                {{-- Fichier déposé --}}
                                @if ($submission)
                                    <a href="{{ $submission->fileUrl() }}" target="_blank" class="sub-file">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        {{ $submission->file_name }}
                                        @if ($submission->file_size) <span style="opacity:.5;">· {{ $submission->file_size }}</span> @endif
                                    </a>
                                    <div class="sub-meta">
                                        Déposé le {{ $submission->submitted_at->locale('fr')->isoFormat('D MMM à HH:mm') }}
                                        · par {{ $submission->submittedBy?->name }}
                                    </div>
                                    @if ($submission->teacher_comment)
                                        <div class="sub-comment">💬 {{ $submission->teacher_comment }}</div>
                                    @endif
                                @endif

                                {{-- Zone upload pour le parent --}}
                                @if (($isParent || auth()->user()->hasAnyRole(['admin','directeur'])) && $homework->allow_submission)
                                    @if ($submittingForSsyId === $ssy->id)
                                        <div class="inline-upload">
                                            <label class="upload-label">
                                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                                <div>
                                                    @if ($submissionFile)
                                                        <div class="file-selected">
                                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4"/></svg>
                                                            {{ $submissionFile->getClientOriginalName() }}
                                                        </div>
                                                    @else
                                                        <div class="upload-label-text">Sélectionner le devoir corrigé</div>
                                                        <div class="upload-label-hint">PDF, Word, Image — max 10 Mo</div>
                                                    @endif
                                                </div>
                                                <input wire:model="submissionFile" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                            </label>

                                            @if ($submitError)
                                                <div style="font-size:.8rem;color:var(--accent-red);margin-top:.4rem;">{{ $submitError }}</div>
                                            @endif
                                            @error('submissionFile')
                                                <div style="font-size:.8rem;color:var(--accent-red);margin-top:.4rem;">{{ $message }}</div>
                                            @enderror

                                            <div class="upload-actions">
                                                <button wire:click="cancelSubmit" class="btn-cancel-sm">Annuler</button>
                                                <button wire:click="submitHomework" class="btn-submit">
                                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                                    Déposer
                                                </button>
                                            </div>
                                        </div>
                                    @else
                                        <div style="margin-top:.5rem;">
                                            <button wire:click="openSubmit({{ $ssy->id }})" class="btn-action btn-upload">
                                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                                {{ $submission ? 'Remplacer le rendu' : 'Déposer le devoir' }}
                                            </button>
                                        </div>
                                    @endif
                                @endif

                                {{-- Zone correction pour l'enseignant --}}
                                @if (! $isParent && $submission)
                                    @if ($gradingSubmissionId === $submission->id)
                                        <div class="grade-panel">
                                            <div class="grade-panel-title">Correction</div>
                                            <div class="grade-row">
                                                <input wire:model="gradeValue"
                                                       type="number" min="0" max="20" step="0.5"
                                                       class="grade-input"
                                                       placeholder="—">
                                                <span style="font-size:.8rem;color:var(--ink);opacity:.5;">/20</span>
                                            </div>
                                            <textarea wire:model="teacherComment"
                                                      class="comment-input"
                                                      placeholder="Commentaire pour les parents..."
                                                      rows="2"></textarea>
                                            <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:.65rem;">
                                                <button wire:click="$set('gradingSubmissionId',null)" class="btn-cancel-sm">Annuler</button>
                                                <button wire:click="saveGrade" class="btn-submit">Enregistrer</button>
                                            </div>
                                        </div>
                                    @else
                                        <div style="margin-top:.5rem;display:flex;gap:.4rem;">
                                            <button wire:click="openGrade({{ $submission->id }})" class="btn-action btn-grade">
                                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                {{ $submission->grade !== null ? 'Modifier la note' : 'Corriger' }}
                                            </button>
                                            @if (auth()->user()->hasAnyRole(['admin','directeur']))
                                                <button wire:click="confirmDeleteSub({{ $submission->id }})" class="btn-action btn-del-sub">
                                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16"/></svg>
                                                    Supprimer
                                                </button>
                                            @endif
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @empty
                        <div style="padding:2rem;text-align:center;font-size:.875rem;color:var(--ink);opacity:.4;">
                            Aucun élève dans cette classe.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div style="position:sticky;top:1.5rem;">

            {{-- Statistiques rendus --}}
            @if (! $isParent)
                <div class="side-card">
                    <div class="side-card-header">Progression</div>
                    <div class="side-card-body">
                        <div class="side-row">
                            <span class="side-label">Rendus</span>
                            <span class="side-value">{{ $submittedCount }} / {{ $totalStudents }}</span>
                        </div>
                        <div class="side-row">
                            <span class="side-label">Corrigés</span>
                            <span class="side-value">
                                {{ \App\Models\HomeworkSubmission::where('homework_id',$homework->id)->where('status','graded')->count() }}
                            </span>
                        </div>
                        <div class="side-row">
                            <span class="side-label">En retard</span>
                            <span class="side-value" style="color:#8A6010;">
                                {{ \App\Models\HomeworkSubmission::where('homework_id',$homework->id)->where('status','late')->count() }}
                            </span>
                        </div>
                        @php $pct = $totalStudents > 0 ? round(($submittedCount / $totalStudents) * 100) : 0; @endphp
                        <div class="progress-wrap">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width:{{ $pct }}%"></div>
                            </div>
                            <div class="progress-label">
                                <span>0</span>
                                <span>{{ $pct }}%</span>
                                <span>{{ $totalStudents }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Infos devoir --}}
            <div class="side-card">
                <div class="side-card-header">Détails</div>
                <div class="side-card-body">
                    <div class="side-row">
                        <span class="side-label">Classe</span>
                        <span class="side-value">{{ $homework->schoolClass->name }}</span>
                    </div>
                    <div class="side-row">
                        <span class="side-label">Matière</span>
                        <span class="side-value">{{ $homework->subject->name }}</span>
                    </div>
                    <div class="side-row">
                        <span class="side-label">Enseignant</span>
                        <span class="side-value">{{ $homework->staff->user->name }}</span>
                    </div>
                    <div class="side-row">
                        <span class="side-label">Date limite</span>
                        <span class="side-value" style="color:{{ $homework->isOverdue() ? 'var(--ink)' : ($homework->due_date->diffInDays(now()) <= 2 ? 'var(--accent-red)' : '#166534') }};">
                            {{ $homework->due_date->locale('fr')->isoFormat('D MMM YYYY') }}
                        </span>
                    </div>
                    <div class="side-row">
                        <span class="side-label">Type</span>
                        <span class="side-value">{{ $homework->is_mandatory ? 'Obligatoire' : 'Optionnel' }}</span>
                    </div>
                    <div class="side-row">
                        <span class="side-label">Rendu en ligne</span>
                        <span class="side-value">{{ $homework->allow_submission ? 'Activé' : 'Désactivé' }}</span>
                    </div>
                    <div class="side-row">
                        <span class="side-label">Publié le</span>
                        <span class="side-value">{{ $homework->created_at->locale('fr')->isoFormat('D MMM') }}</span>
                    </div>
                </div>
            </div>

            <a href="{{ route('homeworks.index') }}"
               style="display:flex;align-items:center;justify-content:center;gap:5px;padding:.5rem;border-radius:8px;border:1px solid var(--line);background:var(--paper-raised);font-size:.875rem;font-weight:500;color:var(--ink);text-decoration:none;font-family:'Inter',sans-serif;">
                ← Retour aux devoirs
            </a>
        </div>
    </div>

    {{-- Modal suppression rendu --}}
    @if ($confirmDeleteSubId)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Supprimer ce rendu ?</div>
                <div class="modal-desc">Le fichier déposé sera définitivement supprimé.</div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeleteSubId',null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deleteSub" class="btn-modal-confirm">Supprimer</button>
                </div>
            </div>
        </div>
    @endif
</div>
