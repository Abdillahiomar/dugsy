<?php

use App\Models\ClassSubjectTeacher;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Subject;
use App\Services\AcademicYearService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component
{
    public SchoolClass $schoolClass;

    // Formulaire d'ajout
    public string $subject_id = '';
    public string $staff_id   = '';
    public bool   $saved      = false;

    // Edition inline d'un enseignant
    public ?int   $editingAssignmentId = null;
    public string $editStaffId         = '';

    // Suppression
    public ?int $confirmDeleteId = null;

    public function mount(SchoolClass $schoolClass): void
    {
        $this->schoolClass = $schoolClass;
    }

    public function assign(): void
    {
        $this->validate([
            'subject_id' => 'required|exists:subjects,id',
            'staff_id'   => 'required|exists:staff,id',
        ]);

        // Vérifier que la matière n'est pas déjà assignée à cette classe
        $exists = ClassSubjectTeacher::where('school_class_id', $this->schoolClass->id)
            ->where('subject_id', $this->subject_id)
            ->exists();

        if ($exists) {
            $this->addError('subject_id', 'Cette matière est déjà assignée à cette classe.');
            return;
        }

        ClassSubjectTeacher::create([
            'school_class_id' => $this->schoolClass->id,
            'subject_id'      => $this->subject_id,
            'staff_id'        => $this->staff_id,
        ]);

        $this->reset('subject_id', 'staff_id');
        $this->saved = true;
    }

    public function startEditAssignment(int $assignmentId): void
    {
        $assignment = ClassSubjectTeacher::find($assignmentId);
        if (! $assignment) return;

        $this->editingAssignmentId = $assignmentId;
        $this->editStaffId         = (string) $assignment->staff_id;
    }

    public function saveEditAssignment(): void
    {
        $this->validate([
            'editStaffId' => 'required|exists:staff,id',
        ]);

        ClassSubjectTeacher::where('id', $this->editingAssignmentId)
            ->update(['staff_id' => $this->editStaffId]);

        $this->editingAssignmentId = null;
    }

    public function confirmDelete(int $assignmentId): void
    {
        $this->confirmDeleteId = $assignmentId;
    }

    public function deleteAssignment(): void
    {
        if (! $this->confirmDeleteId) return;

        ClassSubjectTeacher::where('id', $this->confirmDeleteId)
            ->whereHas('schoolClass', fn ($q) =>
                $q->where('school_id', auth()->user()->school_id)
            )
            ->delete();

        $this->confirmDeleteId = null;
    }

    public function with(): array
    {
        // Matières déjà assignées à cette classe
        $assignments = ClassSubjectTeacher::where('school_class_id', $this->schoolClass->id)
            ->with(['subject', 'teacher.user'])
            ->get()
            ->sortBy('subject.name');

        // Matières pas encore assignées (pour le select d'ajout)
        $assignedSubjectIds = $assignments->pluck('subject_id');
        $availableSubjects  = Subject::where('school_id', auth()->user()->school_id)
            ->whereNotIn('id', $assignedSubjectIds)
            ->orderBy('name')
            ->get();

        $teachers = Staff::where('school_id', auth()->user()->school_id)
            ->with('user')
            ->get();

        $year = AcademicYearService::current();

        return compact('assignments', 'availableSubjects', 'teachers', 'year');
    }
}; ?>

<style>
    /* Breadcrumb */
    .breadcrumb {
        display: flex; align-items: center; gap: 0.5rem;
        font-size: 0.8125rem; margin-bottom: 1.5rem;
        color: var(--ink); opacity: 0.5;
    }
    .breadcrumb a { color: inherit; text-decoration: none; }
    .breadcrumb a:hover { color: var(--sidebar-soft); opacity: 1; }
    .breadcrumb svg { width: 14px; height: 14px; }
    .breadcrumb-current { opacity: 1; font-weight: 600; color: var(--ink); }

    /* Layout 2 colonnes */
    .page-grid {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 1.5rem;
        align-items: start;
    }
    @media (max-width: 900px) { .page-grid { grid-template-columns: 1fr; } }

    /* Card */
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
    .card-meta {
        font-family: 'JetBrains Mono', monospace; font-size: 10px;
        color: var(--ink); opacity: 0.4;
    }
    .card-body { padding: 1.25rem 1.5rem; }

    /* Info classe */
    .class-info-card {
        border-radius: 12px; border: 1px solid var(--line);
        background: var(--sidebar); overflow: hidden;
        margin-bottom: 1.25rem;
    }
    .class-info-body { padding: 1.25rem 1.5rem; }
    .class-info-cycle {
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.1em;
        color: var(--accent); margin-bottom: 4px;
    }
    .class-info-name {
        font-family: 'Fraunces', serif; font-size: 1.6rem; font-weight: 600;
        color: #FFFFFF; line-height: 1.1; margin-bottom: 0.5rem;
    }
    .class-info-level {
        font-size: 0.875rem; color: rgba(255,255,255,0.6);
    }
    .class-info-stats {
        display: grid; grid-template-columns: 1fr 1fr;
        gap: 0.75rem; margin-top: 1rem;
        padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);
    }
    .class-stat {
        text-align: center;
    }
    .class-stat-value {
        font-family: 'JetBrains Mono', monospace; font-size: 1.25rem; font-weight: 700;
        color: #FFFFFF;
    }
    .class-stat-label {
        font-size: 0.75rem; color: rgba(255,255,255,0.5); margin-top: 2px;
    }

    /* Formulaire ajout matière */
    .assign-form { display: flex; flex-direction: column; gap: 0.875rem; }
    .form-field { display: flex; flex-direction: column; gap: 0.35rem; }
    .form-label {
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink); opacity: 0.5;
    }
    .form-select-inp {
        padding: 0.5rem 0.75rem; border-radius: 8px; border: 1px solid var(--line);
        background: var(--paper); font-size: 0.875rem; font-family: 'Inter', sans-serif;
        color: var(--ink); outline: none; width: 100%;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .form-select-inp:focus {
        border-color: var(--sidebar-soft); box-shadow: 0 0 0 3px rgba(42,63,126,0.08);
    }
    .form-error { font-size: 0.75rem; color: var(--accent-red); margin-top: 0.2rem; }

    .btn-assign {
        display: flex; align-items: center; justify-content: center; gap: 6px;
        width: 100%; padding: 0.55rem; border-radius: 8px;
        background: var(--sidebar); color: #FFFFFF;
        font-size: 0.875rem; font-weight: 600; font-family: 'Inter', sans-serif;
        border: none; cursor: pointer; transition: background 0.15s; margin-top: 0.25rem;
    }
    .btn-assign:hover { background: var(--sidebar-soft); }
    .btn-assign svg { width: 15px; height: 15px; }
    .btn-assign:disabled { opacity: 0.4; cursor: default; }

    /* Toast */
    .toast-success {
        display: flex; align-items: center; gap: 0.65rem;
        padding: 0.65rem 1rem; border-radius: 8px;
        background: rgba(30,120,80,0.1); border: 1px solid rgba(30,120,80,0.2);
        color: #1A6040; font-size: 0.8125rem; font-weight: 500;
        margin-bottom: 0.875rem;
        animation: slideDown 0.15s ease;
    }
    .toast-success svg { width: 16px; height: 16px; flex-shrink: 0; }
    @keyframes slideDown { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:translateY(0); } }

    /* Table des affectations */
    table { width: 100%; border-collapse: collapse; }
    thead tr { border-bottom: 1px solid var(--line); background: var(--paper); }
    thead th {
        text-align: left; padding: 0.65rem 1.25rem;
        font-family: 'JetBrains Mono', monospace; font-size: 10px;
        font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em;
        color: var(--ink); opacity: 0.45; white-space: nowrap;
    }
    thead th:last-child { text-align: right; }
    tbody tr { border-bottom: 1px solid var(--line); transition: background 0.1s; }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background: rgba(30,45,90,0.03); }
    tbody td { padding: 0.875rem 1.25rem; font-size: 0.875rem; color: var(--ink); vertical-align: middle; }
    tbody td:last-child { text-align: right; }

    /* Matière cell */
    .subject-cell { display: flex; align-items: center; gap: 0.75rem; }
    .subject-color-dot {
        width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
    }
    .subject-name-text { font-weight: 600; }
    .subject-code-text {
        font-family: 'JetBrains Mono', monospace; font-size: 11px;
        color: var(--ink); opacity: 0.4;
    }
    .coeff-badge {
        display: inline-block;
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 700;
        padding: 2px 7px; border-radius: 4px;
        background: rgba(42,63,126,0.08); color: var(--sidebar-soft);
    }

    /* Enseignant cell */
    .teacher-cell { display: flex; align-items: center; gap: 0.65rem; }
    .teacher-avatar {
        width: 28px; height: 28px; border-radius: 50%;
        background: rgba(42,63,126,0.1); color: var(--sidebar-soft);
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .teacher-name { font-weight: 500; }
    .teacher-pos  { font-size: 0.75rem; color: var(--ink); opacity: 0.45; }

    /* Edit inline dans la ligne */
    .inline-edit-select {
        padding: 0.35rem 0.65rem; border-radius: 6px; border: 1.5px solid var(--sidebar-soft);
        background: var(--paper); font-size: 0.8125rem; font-family: 'Inter', sans-serif;
        color: var(--ink); outline: none; min-width: 160px;
    }
    .inline-edit-actions { display: flex; align-items: center; gap: 0.35rem; margin-top: 0.35rem; }
    .btn-inline-save {
        padding: 0.25rem 0.65rem; border-radius: 5px;
        background: var(--sidebar); color: #FFFFFF;
        font-size: 0.75rem; font-weight: 600; font-family: 'Inter', sans-serif;
        border: none; cursor: pointer;
    }
    .btn-inline-cancel {
        padding: 0.25rem 0.65rem; border-radius: 5px;
        background: var(--paper); color: var(--ink);
        font-size: 0.75rem; font-weight: 500; font-family: 'Inter', sans-serif;
        border: 1px solid var(--line); cursor: pointer;
    }

    /* Actions */
    .actions-cell { display: flex; align-items: center; justify-content: flex-end; gap: 0.4rem; }
    .btn-action {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 0.3rem 0.65rem; border-radius: 6px;
        font-size: 0.8rem; font-weight: 600; font-family: 'Inter', sans-serif;
        border: none; cursor: pointer; transition: background 0.12s; white-space: nowrap;
    }
    .btn-action svg { width: 13px; height: 13px; }
    .btn-edit-act   { background: rgba(42,63,126,0.08); color: var(--sidebar-soft); }
    .btn-edit-act:hover { background: rgba(42,63,126,0.16); }
    .btn-del-act    { background: rgba(224,92,58,0.08); color: var(--accent-red); }
    .btn-del-act:hover  { background: rgba(224,92,58,0.16); }

    /* Empty */
    .empty-assignments {
        padding: 3rem 1.5rem; text-align: center;
        border-top: 1px solid var(--line);
    }
    .empty-assignments svg { width: 40px; height: 40px; margin: 0 auto 0.75rem; opacity: 0.2; }
    .empty-assignments-title { font-family: 'Fraunces', serif; font-size: 1rem; font-weight: 600; color: var(--ink); margin-bottom: 0.3rem; }
    .empty-assignments-sub   { font-size: 0.8125rem; color: var(--ink); opacity: 0.45; }

    /* Modal suppression */
    .modal-overlay {
        position: fixed; inset: 0; z-index: 100;
        background: rgba(0,0,0,0.4);
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
</style>

<div>

    {{-- Breadcrumb --}}
    <div class="breadcrumb">
        <a href="{{ route('classes.index') }}">Classes</a>
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="breadcrumb-current">{{ $schoolClass->name }} — Matières & Enseignants</span>
    </div>

    <div class="page-grid">

        {{-- Colonne gauche : table des affectations --}}
        <div>
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Matières affectées</span>
                    <span class="card-meta">{{ $assignments->count() }} matière{{ $assignments->count() > 1 ? 's' : '' }}</span>
                </div>

                @if ($saved)
                    <div style="padding: 0.75rem 1.5rem; border-bottom: 1px solid var(--line);">
                        <div class="toast-success" x-data x-init="setTimeout(() => $el.remove(), 3000)">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Matière assignée avec succès.
                        </div>
                    </div>
                @endif

                @if ($assignments->isEmpty())
                    <div class="empty-assignments">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        <div class="empty-assignments-title">Aucune matière assignée</div>
                        <div class="empty-assignments-sub">Utilise le formulaire pour assigner des matières et leurs enseignants.</div>
                    </div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Matière</th>
                                <th>Coeff</th>
                                <th>Enseignant</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assignments as $assignment)
                                <tr>
                                    <td>
                                        <div class="subject-cell">
                                            <div class="subject-color-dot"
                                                 style="background: {{ $assignment->subject->color ?? 'var(--sidebar)' }}">
                                            </div>
                                            <div>
                                                <div class="subject-name-text">{{ $assignment->subject->name }}</div>
                                                @if ($assignment->subject->code)
                                                    <div class="subject-code-text">{{ $assignment->subject->code }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="coeff-badge">{{ $assignment->subject->coefficient }}</span>
                                    </td>
                                    <td>
                                        @if ($editingAssignmentId === $assignment->id)
                                            <div>
                                                <select wire:model="editStaffId" class="inline-edit-select">
                                                    <option value="">— Sélectionner —</option>
                                                    @foreach ($teachers as $teacher)
                                                        <option value="{{ $teacher->id }}">{{ $teacher->user->name }}</option>
                                                    @endforeach
                                                </select>
                                                <div class="inline-edit-actions">
                                                    <button wire:click="saveEditAssignment" class="btn-inline-save">Enregistrer</button>
                                                    <button wire:click="$set('editingAssignmentId', null)" class="btn-inline-cancel">Annuler</button>
                                                </div>
                                            </div>
                                        @else
                                            <div class="teacher-cell">
                                                <div class="teacher-avatar">
                                                    {{ strtoupper(substr($assignment->teacher->user->name ?? '?', 0, 2)) }}
                                                </div>
                                                <div>
                                                    <div class="teacher-name">{{ $assignment->teacher->user->name ?? '—' }}</div>
                                                    <div class="teacher-pos">{{ $assignment->teacher->position ?? '' }}</div>
                                                </div>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($editingAssignmentId !== $assignment->id)
                                            <div class="actions-cell">
                                                <button wire:click="startEditAssignment({{ $assignment->id }})"
                                                        class="btn-action btn-edit-act">
                                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                    </svg>
                                                    Changer
                                                </button>
                                                <button wire:click="confirmDelete({{ $assignment->id }})"
                                                        class="btn-action btn-del-act">
                                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16"/>
                                                    </svg>
                                                    Retirer
                                                </button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- Colonne droite --}}
        <div>

            {{-- Infos de la classe --}}
            <div class="class-info-card">
                <div class="class-info-body">
                    <div class="class-info-cycle">
                        {{ $schoolClass->level?->cycle ?? 'Classe' }}
                        — {{ $year?->label }}
                    </div>
                    <div class="class-info-name">{{ $schoolClass->name }}</div>
                    <div class="class-info-level">{{ $schoolClass->level?->name }}</div>

                    <div class="class-info-stats">
                        <div class="class-stat">
                            <div class="class-stat-value">{{ $assignments->count() }}</div>
                            <div class="class-stat-label">matières</div>
                        </div>
                        <div class="class-stat">
                            <div class="class-stat-value">
                                {{ $schoolClass->studentSchoolYears()->count() }}
                            </div>
                            <div class="class-stat-label">élèves</div>
                        </div>
                        <div class="class-stat">
                            <div class="class-stat-value">
                                {{ $assignments->pluck('staff_id')->unique()->count() }}
                            </div>
                            <div class="class-stat-label">enseignants</div>
                        </div>
                        <div class="class-stat">
                            <div class="class-stat-value">
                                {{ $assignments->sum('subject.coefficient') }}
                            </div>
                            <div class="class-stat-label">total coeffs</div>
                        </div>
                    </div>
                </div>
            </div>

           {{-- Formulaire d'ajout --}}
            <div class="card">
                <div class="card-header">
                    <span class="card-title">Assigner une matière</span>
                </div>
                <div class="card-body">
                    @if ($availableSubjects->isEmpty())
                        <p style="font-size:0.875rem; color:var(--ink); opacity:0.5;
                                text-align:center; padding:0.5rem 0;">
                            Toutes les matières sont déjà assignées.
                        </p>
                    @else
                        <div class="assign-form" x-data>
                            <div class="form-field">
                                <label class="form-label">Matière</label>
                                <select wire:model.live="subject_id" class="form-select-inp">
                                    <option value="">— Sélectionner —</option>
                                    @foreach ($availableSubjects as $subject)
                                        <option value="{{ $subject->id }}">
                                            {{ $subject->name }}
                                            @if ($subject->code) ({{ $subject->code }}) @endif
                                            — coeff {{ $subject->coefficient }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('subject_id')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-field">
                                <label class="form-label">Enseignant</label>
                                <select wire:model.live="staff_id" class="form-select-inp">
                                    <option value="">— Sélectionner —</option>
                                    @foreach ($teachers as $teacher)
                                        <option value="{{ $teacher->id }}">
                                            {{ $teacher->user->name }}
                                            @if ($teacher->position) — {{ $teacher->position }} @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('staff_id')
                                    <span class="form-error">{{ $message }}</span>
                                @enderror
                            </div>

                            {{-- Bouton géré par Alpine pour éviter le deadlock wire:model différé --}}
                            <button
                                wire:click="assign"
                                class="btn-assign"
                                x-bind:disabled="!$wire.subject_id || !$wire.staff_id"
                                x-bind:style="(!$wire.subject_id || !$wire.staff_id)
                                    ? 'opacity:0.4; cursor:not-allowed;'
                                    : 'opacity:1; cursor:pointer;'">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                </svg>
                                Assigner
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Lien retour --}}
            <a href="{{ route('classes.index') }}"
               style="display:flex; align-items:center; gap:6px; font-size:0.8125rem; font-weight:500; color:var(--ink); opacity:0.5; text-decoration:none; padding: 0.5rem 0;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Retour aux classes
            </a>
        </div>

    </div>

    {{-- Modal suppression --}}
    @if ($confirmDeleteId)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Retirer cette matière ?</div>
                <div class="modal-desc">
                    L'enseignant ne sera plus assigné à cette matière pour cette classe. Les notes déjà saisies ne seront pas supprimées.
                </div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeleteId', null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deleteAssignment" class="btn-modal-confirm">Oui, retirer</button>
                </div>
            </div>
        </div>
    @endif

</div>