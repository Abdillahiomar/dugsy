<?php

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\StudentSchoolYear;
use App\Services\AcademicYearService;
use App\Services\AccessService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    #[Url] public string $classFilter   = '';
    #[Url] public string $subjectFilter = '';
    #[Url] public string $statusFilter  = ''; // '' | upcoming | overdue

    // ── Formulaire création (enseignant) ──────────────────────────
    public bool   $showForm      = false;
    public string $title         = '';
    public string $description   = '';
    public string $classId       = '';
    public string $subjectId     = '';
    public string $dueDate       = '';
    public bool   $isMandatory   = true;
    public bool   $allowSubmit   = true;
    public $hwFile               = null;

    // ── Suppression ───────────────────────────────────────────────
    public ?int $confirmDeleteId = null;

    public bool $saved = false;

    public function mount(): void
    {
        $this->dueDate = now()->addDays(7)->format('Y-m-d');
    }

    public function saveHomework(): void
    {
        $this->validate([
            'title'    => 'required|string|max:200',
            'classId'  => 'required|exists:school_classes,id',
            'subjectId'=> 'required|exists:subjects,id',
            'dueDate'  => 'required|date|after:today',
            'hwFile'   => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ], [
            'dueDate.after' => 'La date de rendu doit être dans le futur.',
            'hwFile.max'    => 'Le fichier ne doit pas dépasser 10 Mo.',
        ]);

        $year     = AcademicYearService::current();
        $schoolId = auth()->user()->school_id;
        $staff    = auth()->user()->staff;

        $filePath = null;
        $fileName = null;
        if ($this->hwFile) {
            $filePath = $this->hwFile->store('homeworks/files', 'public');
            $fileName = $this->hwFile->getClientOriginalName();
        }

        Homework::create([
            'school_id'        => $schoolId,
            'academic_year_id' => $year->id,
            'school_class_id'  => $this->classId,
            'subject_id'       => $this->subjectId,
            'staff_id'         => $staff->id,
            'title'            => $this->title,
            'description'      => $this->description ?: null,
            'file_path'        => $filePath,
            'file_name'        => $fileName,
            'due_date'         => $this->dueDate,
            'is_mandatory'     => $this->isMandatory,
            'allow_submission' => $this->allowSubmit,
        ]);

        $this->reset('title','description','classId','subjectId','dueDate','isMandatory','allowSubmit','hwFile','showForm');
        $this->dueDate = now()->addDays(7)->format('Y-m-d');
        $this->saved   = true;
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function deleteHomework(): void
    {
        $hw = Homework::find($this->confirmDeleteId);
        if (! $hw) return;

        if ($hw->file_path) Storage::disk('public')->delete($hw->file_path);

        // Supprimer tous les rendus
        foreach ($hw->submissions as $sub) {
            Storage::disk('public')->delete($sub->file_path);
        }

        $hw->delete();
        $this->confirmDeleteId = null;
    }

    public function with(): array
    {
        $year      = AcademicYearService::current();
        $schoolId  = auth()->user()->school_id;
        $user      = auth()->user();
        $isTeacher = $user->hasRole('enseignant') && ! $user->hasAnyRole(['admin','directeur']);
        $isParent  = $user->hasRole('parent');

        // ── Classes selon le rôle ──────────────────────────────────
        $myClassIds = AccessService::myClassIds();
        $classes = SchoolClass::where('school_id', $schoolId)
            ->where('academic_year_id', $year?->id)
            ->when($myClassIds !== null, fn ($q) => $q->whereIn('id', $myClassIds))
            ->with('level')
            ->get();

        // ── Matières selon le rôle ────────────────────────────────
        $mySubjectIds = AccessService::mySubjectIds();
        $subjects = Subject::whereHas('classSubjects', fn ($q) =>
            $q->whereIn('school_class_id', $classes->pluck('id'))
        )
        ->when($mySubjectIds !== null, fn ($q) => $q->whereIn('id', $mySubjectIds))
        ->get();

        // ── Devoirs ────────────────────────────────────────────────
        $query = Homework::where('school_id', $schoolId)
            ->where('academic_year_id', $year?->id)
            ->with(['subject','schoolClass.level','staff.user','submissions'])
            ->orderByDesc('due_date');

        // Enseignant → ses devoirs uniquement
        if ($isTeacher && $user->staff) {
            $query->where('staff_id', $user->staff->id);
        }

        // Parent → devoirs des classes de ses enfants
        if ($isParent) {
            $guardian   = \App\Models\Guardian::where('user_id', $user->id)->first();
            $childrenIds = $guardian
                ? \App\Models\Student::whereHas('guardians', fn ($q) => $q->where('guardian_id', $guardian->id))
                    ->pluck('id')
                : collect();

            $childClassIds = StudentSchoolYear::whereIn('student_id', $childrenIds)
                ->where('academic_year_id', $year?->id)
                ->pluck('school_class_id');

            $query->whereIn('school_class_id', $childClassIds);
        }

        // Filtres
        if ($this->classFilter)   $query->where('school_class_id', $this->classFilter);
        if ($this->subjectFilter) $query->where('subject_id', $this->subjectFilter);
        if ($this->statusFilter === 'upcoming') $query->where('due_date', '>=', now());
        if ($this->statusFilter === 'overdue')  $query->where('due_date', '<', now());

        $homeworks = $query->get();

        // Pour le parent → mapper les enfants avec leurs rendus
        $myChildren = collect();
        if ($isParent) {
            $guardian = \App\Models\Guardian::where('user_id', $user->id)->first();
            if ($guardian) {
                $myChildren = StudentSchoolYear::whereHas('student', fn ($q) =>
                    $q->whereHas('guardians', fn ($q) => $q->where('guardian_id', $guardian->id))
                )->where('academic_year_id', $year?->id)
                 ->with('student')
                 ->get();
            }
        }

        return compact(
            'year', 'classes', 'subjects', 'homeworks',
            'isTeacher', 'isParent', 'myChildren'
        );
    }
}; ?>

<style>
    .page-toolbar { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
    .toolbar-left  { display:flex; align-items:center; gap:.65rem; flex-wrap:wrap; }
    .filter-select { padding:.45rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper-raised); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; cursor:pointer; }
    .btn-primary { display:inline-flex; align-items:center; gap:5px; padding:.45rem 1rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; transition:background .15s; }
    .btn-primary:hover { background:var(--sidebar-soft); }
    .btn-primary svg { width:14px; height:14px; }

    /* Form card */
    .form-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.5rem; animation:slideDown .15s ease; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-6px);} to{opacity:1;transform:translateY(0);} }
    .form-card-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .form-card-title  { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); }
    .form-card-body   { padding:1.25rem 1.5rem; }
    .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
    .form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; margin-bottom:1rem; }
    @media(max-width:700px) { .form-grid-2,.form-grid-3 { grid-template-columns:1fr; } }
    .form-field { display:flex; flex-direction:column; gap:.35rem; }
    .form-field.full { grid-column:1/-1; }
    .form-label { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.5; }
    .form-input,.form-select-inp { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; transition:border-color .15s; }
    .form-input:focus,.form-select-inp:focus { border-color:var(--sidebar-soft); box-shadow:0 0 0 3px rgba(42,63,126,.08); }
    .form-textarea { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; resize:vertical; min-height:80px; }
    .form-textarea:focus { border-color:var(--sidebar-soft); }
    .form-error { font-size:.75rem; color:var(--accent-red); margin-top:.2rem; }
    .form-actions { display:flex; justify-content:flex-end; gap:.65rem; padding-top:1rem; border-top:1px solid var(--line); margin-top:1rem; }
    .btn-save { display:inline-flex; align-items:center; gap:5px; padding:.5rem 1.25rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
    .btn-cancel { padding:.5rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }

    /* Toggle */
    .toggle-row { display:flex; align-items:center; justify-content:space-between; padding:.75rem 0; border-bottom:1px solid var(--line); }
    .toggle-row:last-child { border-bottom:none; }
    .toggle-label { font-size:.875rem; font-weight:500; color:var(--ink); }
    .toggle-desc  { font-size:.8rem; color:var(--ink); opacity:.5; margin-top:2px; }
    .toggle-switch { position:relative; width:40px; height:22px; cursor:pointer; flex-shrink:0; }
    .toggle-switch input { opacity:0; width:0; height:0; }
    .toggle-slider { position:absolute; inset:0; border-radius:22px; background:var(--line); transition:background .2s; }
    .toggle-slider::before { content:''; position:absolute; width:16px; height:16px; border-radius:50%; background:white; top:3px; left:3px; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
    .toggle-switch input:checked + .toggle-slider { background:var(--sidebar-soft); }
    .toggle-switch input:checked + .toggle-slider::before { transform:translateX(18px); }

    /* Upload */
    .upload-zone { display:flex; align-items:center; gap:.75rem; padding:.75rem 1rem; border-radius:8px; border:1.5px dashed var(--line); background:var(--paper); cursor:pointer; position:relative; transition:all .12s; }
    .upload-zone:hover { border-color:var(--sidebar-soft); background:rgba(42,63,126,.02); }
    .upload-zone input { position:absolute; inset:0; opacity:0; cursor:pointer; }
    .upload-zone svg { width:20px; height:20px; color:var(--sidebar-soft); opacity:.5; flex-shrink:0; }
    .upload-text { font-size:.875rem; color:var(--ink); opacity:.6; }
    .upload-hint { font-size:.75rem; color:var(--ink); opacity:.4; margin-top:2px; }
    .file-attached { display:inline-flex; align-items:center; gap:.5rem; padding:.4rem .875rem; border-radius:6px; background:rgba(42,63,126,.08); color:var(--sidebar-soft); font-size:.8125rem; font-weight:600; margin-top:.5rem; }
    .file-attached svg { width:14px; height:14px; }

    /* Grille devoirs */
    .hw-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:1.25rem; }

    /* Carte devoir */
    .hw-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; display:flex; flex-direction:column; transition:border-color .15s,box-shadow .15s; }
    .hw-card:hover { border-color:rgba(42,63,126,.25); box-shadow:0 4px 16px rgba(42,63,126,.07); }

    .hw-card-header { padding:1rem 1.25rem; border-bottom:1px solid var(--line); }
    .hw-meta-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:.5rem; }
    .hw-subject-chip { display:inline-flex; align-items:center; gap:.4rem; font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:2px 8px; border-radius:5px; }
    .hw-status-chip { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:2px 8px; border-radius:5px; }
    .hw-status-upcoming { background:rgba(30,120,80,.1); color:#166534; }
    .hw-status-urgent   { background:rgba(224,92,58,.1); color:var(--accent-red); }
    .hw-status-overdue  { background:rgba(0,0,0,.06); color:var(--ink); opacity:.5; }
    .hw-title { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); margin-bottom:.35rem; }
    .hw-class { font-size:.8rem; color:var(--ink); opacity:.5; }

    .hw-card-body { padding:.875rem 1.25rem; flex:1; }
    .hw-desc { font-size:.875rem; color:var(--ink); opacity:.6; line-height:1.55; margin-bottom:.75rem; }
    .hw-info-row { display:flex; align-items:center; gap:.5rem; font-size:.8125rem; color:var(--ink); margin-bottom:.35rem; }
    .hw-info-row:last-child { margin-bottom:0; }
    .hw-info-row svg { width:14px; height:14px; color:var(--sidebar-soft); opacity:.55; flex-shrink:0; }
    .hw-mandatory { display:inline-flex; align-items:center; gap:.3rem; font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; padding:1px 6px; border-radius:3px; }
    .hw-mandatory.yes { background:rgba(224,92,58,.1); color:var(--accent-red); }
    .hw-mandatory.no  { background:rgba(42,63,126,.07); color:var(--sidebar-soft); }

    .hw-card-footer { padding:.75rem 1.25rem; border-top:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; gap:.5rem; }
    .hw-submissions { font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--ink); opacity:.45; }

    .btn-view { display:inline-flex; align-items:center; gap:4px; padding:.35rem .75rem; border-radius:6px; background:rgba(42,63,126,.08); color:var(--sidebar-soft); font-size:.8rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; text-decoration:none; transition:background .12s; }
    .btn-view:hover { background:rgba(42,63,126,.16); }
    .btn-view svg { width:13px; height:13px; }
    .btn-dl { display:inline-flex; align-items:center; gap:4px; padding:.35rem .75rem; border-radius:6px; background:rgba(30,120,80,.08); color:#166534; font-size:.8rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; text-decoration:none; transition:background .12s; }
    .btn-dl:hover { background:rgba(30,120,80,.15); }
    .btn-dl svg { width:13px; height:13px; }
    .btn-del { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:6px; background:rgba(224,92,58,.08); color:var(--accent-red); border:none; cursor:pointer; }
    .btn-del:hover { background:rgba(224,92,58,.16); }
    .btn-del svg { width:13px; height:13px; }

    /* Toast */
    .toast { display:flex; align-items:center; gap:.6rem; padding:.65rem 1rem; border-radius:8px; font-size:.875rem; font-weight:500; margin-bottom:1rem; animation:slideDown .15s ease; }
    .toast-ok { background:rgba(30,120,80,.1); border:1px solid rgba(30,120,80,.2); color:#166534; }
    .toast svg { width:15px; height:15px; flex-shrink:0; }

    /* Empty */
    .empty { padding:4rem 2rem; text-align:center; }
    .empty svg { width:44px; height:44px; margin:0 auto 1rem; opacity:.2; }
    .empty-title { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; color:var(--ink); }
    .empty-sub   { font-size:.875rem; color:var(--ink); opacity:.45; margin-top:.35rem; }

    /* Modal */
    .modal-overlay { position:fixed; inset:0; z-index:100; background:rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; padding:1rem; }
    .modal { background:var(--paper-raised); border-radius:14px; border:1px solid var(--line); padding:1.75rem; max-width:380px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.2); }
    .modal-title { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; margin-bottom:.5rem; }
    .modal-desc  { font-size:.875rem; color:var(--ink); opacity:.6; margin-bottom:1.25rem; line-height:1.5; }
    .modal-actions { display:flex; justify-content:flex-end; gap:.65rem; }
    .btn-modal-cancel  { padding:.45rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; cursor:pointer; font-family:'Inter',sans-serif; color:var(--ink); }
    .btn-modal-confirm { padding:.45rem 1rem; border-radius:8px; background:var(--accent-red); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; }

    /* Status chips */
    .chips { display:flex; gap:.4rem; }
    .chip { padding:.35rem .875rem; border-radius:7px; border:1.5px solid var(--line); background:var(--paper); font-size:.8125rem; font-weight:500; cursor:pointer; transition:all .12s; color:var(--ink); }
    .chip.active { border-color:var(--sidebar); background:rgba(42,63,126,.07); color:var(--sidebar); font-weight:600; }
</style>

<div>

    {{-- Toolbar --}}
    <div class="page-toolbar">
        <div class="toolbar-left">
            <select wire:model.live="classFilter" class="filter-select">
                <option value="">Toutes les classes</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="subjectFilter" class="filter-select">
                <option value="">Toutes les matières</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                @endforeach
            </select>
            <div class="chips">
                @foreach ([['v'=>'','l'=>'Tous'],['v'=>'upcoming','l'=>'En cours'],['v'=>'overdue','l'=>'Terminés']] as $opt)
                    <button type="button"
                            wire:click="$set('statusFilter','{{ $opt['v'] }}')"
                            class="chip {{ $statusFilter === $opt['v'] ? 'active' : '' }}">
                        {{ $opt['l'] }}
                    </button>
                @endforeach
            </div>
        </div>

        @if ($isTeacher || auth()->user()->hasAnyRole(['admin','directeur']))
            <button wire:click="$toggle('showForm')" class="btn-primary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Nouveau devoir
            </button>
        @endif
    </div>

    @if ($saved)
        <div class="toast toast-ok" x-data x-init="setTimeout(() => $el.remove(), 3000)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Devoir publié avec succès.
        </div>
    @endif

    {{-- Formulaire création --}}
    @if ($showForm)
        <div class="form-card">
            <div class="form-card-header">
                <span class="form-card-title">Nouveau devoir à la maison</span>
                <button wire:click="$set('showForm',false)" style="background:none;border:none;cursor:pointer;opacity:.4;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="form-card-body">
                <div class="form-grid-2">
                    <div class="form-field full">
                        <label class="form-label">Titre du devoir *</label>
                        <input wire:model="title" type="text" class="form-input" placeholder="Ex: Exercices sur les équations du 2e degré">
                        @error('title') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="form-grid-3">
                    <div class="form-field">
                        <label class="form-label">Classe *</label>
                        <select wire:model="classId" class="form-select-inp">
                            <option value="">— Sélectionner —</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}">{{ $class->name }}</option>
                            @endforeach
                        </select>
                        @error('classId') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-field">
                        <label class="form-label">Matière *</label>
                        <select wire:model="subjectId" class="form-select-inp">
                            <option value="">— Sélectionner —</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                            @endforeach
                        </select>
                        @error('subjectId') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-field">
                        <label class="form-label">Date limite de rendu *</label>
                        <input wire:model="dueDate" type="date" class="form-input">
                        @error('dueDate') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="form-field" style="margin-bottom:1rem;">
                    <label class="form-label">Description / Consignes</label>
                    <textarea wire:model="description" class="form-textarea"
                              placeholder="Consignes, chapitres concernés, barème..."></textarea>
                </div>

                {{-- Upload fichier --}}
                <div class="form-field" style="margin-bottom:1.25rem;">
                    <label class="form-label">Document du devoir (PDF, Word, Image)</label>
                    <label class="upload-zone">
                        <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/></svg>
                        <div>
                            @if ($hwFile)
                                <div class="upload-text" style="color:var(--sidebar-soft);">{{ $hwFile->getClientOriginalName() }}</div>
                            @else
                                <div class="upload-text">Cliquer pour joindre un fichier</div>
                                <div class="upload-hint">PDF, DOC, DOCX, JPG — max 10 Mo</div>
                            @endif
                        </div>
                        <input wire:model="hwFile" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    </label>
                    @error('hwFile') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                {{-- Options --}}
                <div style="border:1px solid var(--line);border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;">
                    <div class="toggle-row">
                        <div>
                            <div class="toggle-label">Devoir obligatoire</div>
                            <div class="toggle-desc">Si désactivé, le devoir est optionnel.</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" wire:model="isMandatory">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="toggle-row">
                        <div>
                            <div class="toggle-label">Permettre le rendu en ligne</div>
                            <div class="toggle-desc">Les parents peuvent déposer le devoir corrigé.</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" wire:model="allowSubmit">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button wire:click="$set('showForm',false)" class="btn-cancel">Annuler</button>
                    <button wire:click="saveHomework" class="btn-save">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        Publier le devoir
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Grille des devoirs --}}
    @if ($homeworks->isEmpty())
        <div class="empty">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
            <div class="empty-title">Aucun devoir</div>
            <div class="empty-sub">
                @if ($isTeacher) Publie ton premier devoir pour tes classes.
                @elseif ($isParent) Aucun devoir publié pour le moment.
                @else Aucun devoir pour les filtres sélectionnés. @endif
            </div>
        </div>
    @else
        <div class="hw-grid">
            @foreach ($homeworks as $hw)
                @php
                    $statusLabel = $hw->statusLabel();
                    $statusCss   = match($statusLabel) {
                        'Terminé' => 'hw-status-overdue',
                        'Urgent'  => 'hw-status-urgent',
                        default   => 'hw-status-upcoming',
                    };
                    $submittedCount = $hw->submissions->count();
                    $isOwner = auth()->user()->staff?->id === $hw->staff_id
                               || auth()->user()->hasAnyRole(['admin','directeur']);
                @endphp
                <div class="hw-card">
                    <div class="hw-card-header">
                        <div class="hw-meta-top">
                            <span class="hw-subject-chip"
                                  style="background:{{ $hw->subject->color ? $hw->subject->color.'22' : 'rgba(42,63,126,.08)' }};color:{{ $hw->subject->color ?? 'var(--sidebar-soft)' }};">
                                {{ $hw->subject->name }}
                            </span>
                            <span class="hw-status-chip {{ $statusCss }}">{{ $statusLabel }}</span>
                        </div>
                        <div class="hw-title">{{ $hw->title }}</div>
                        <div class="hw-class">
                            {{ $hw->schoolClass->name }} — {{ $hw->schoolClass->level?->name }}
                        </div>
                    </div>

                    <div class="hw-card-body">
                        @if ($hw->description)
                            <div class="hw-desc">{{ Str::limit($hw->description, 100) }}</div>
                        @endif

                        <div class="hw-info-row">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span>À rendre le <strong>{{ $hw->due_date->locale('fr')->isoFormat('dddd D MMMM') }}</strong></span>
                        </div>

                        <div class="hw-info-row">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span>{{ $hw->staff->user->name }}</span>
                            <span class="hw-mandatory {{ $hw->is_mandatory ? 'yes' : 'no' }}">
                                {{ $hw->is_mandatory ? 'Obligatoire' : 'Optionnel' }}
                            </span>
                        </div>

                        @if ($hw->allow_submission)
                            <div class="hw-info-row" style="color:#166534;opacity:.8;">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                <span>Rendu en ligne activé</span>
                            </div>
                        @endif
                    </div>

                    <div class="hw-card-footer">
                        @if (! $isParent)
                            <span class="hw-submissions">
                                {{ $submittedCount }} rendu{{ $submittedCount > 1 ? 's' : '' }}
                            </span>
                        @else
                            <span></span>
                        @endif

                        <div style="display:flex;gap:.4rem;align-items:center;">
                            @if ($hw->file_path)
                                <a href="{{ $hw->fileUrl() }}" target="_blank" class="btn-dl">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    Télécharger
                                </a>
                            @endif
                            <a href="{{ route('homeworks.show', $hw) }}" class="btn-view">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Voir
                            </a>
                            @if ($isOwner)
                                <button wire:click="confirmDelete({{ $hw->id }})" class="btn-del">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16"/></svg>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Modal suppression --}}
    @if ($confirmDeleteId)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Supprimer ce devoir ?</div>
                <div class="modal-desc">Tous les rendus des élèves seront également supprimés. Cette action est irréversible.</div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeleteId',null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deleteHomework" class="btn-modal-confirm">Supprimer</button>
                </div>
            </div>
        </div>
    @endif
</div>
