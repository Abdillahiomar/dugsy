<?php

use App\Models\TimetableSlot;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Staff;
use App\Services\AcademicYearService;
use App\Services\AccessService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url] public string $classId = '';

    public bool   $showForm    = false;
    public ?int   $editingId   = null;
    public int    $fDay        = 0;
    public string $fStart      = '07:30';
    public string $fEnd        = '09:30';
    public string $fSubjectId  = '';
    public string $fStaffId    = '';
    public string $fRoom       = '';
    public string $fColor      = '#1E2D5A';

    public ?int $confirmDeleteId = null;
    public bool $saved = false;

    // Dimanche=0 → Jeudi=4
    public array $schoolDays = [0, 1, 2, 3, 4];

    // Créneaux horaires : 7h30→12h30 puis pause, 14h30→18h30
    // Chaque créneau dure 1h
    public array $timeRows = [
        '07:30', '08:30', '09:30', '10:30', '11:30', '12:30',
        // -- PAUSE 12:30→14:30 --
        '14:30', '15:30', '16:30', '17:30', '18:30',
    ];

    #[On('academic-year-changed')]
    public function refresh(): void
    {
        $this->classId = '';
        $this->closeForm();
    }

    public function openCreate(?int $day = null, ?string $start = null): void
    {
        // Sécurité : refuser l'ouverture du formulaire à 12:30
        if ($start === '12:30') return;

        $this->editingId  = null;
        $this->fDay       = $day ?? 0;
        $this->fStart     = $start ?? '07:30';
        $this->fEnd       = $this->nextSlot($start ?? '07:30');
        $this->fSubjectId = '';
        $this->fStaffId   = '';
        $this->fRoom      = '';
        $this->fColor     = '#1E2D5A';
        $this->showForm   = true;
    }

    public function openEdit(int $id): void
    {
        $slot = TimetableSlot::find($id);
        if (! $slot) return;

        $this->editingId  = $id;
        $this->fDay       = $slot->day_of_week;
        $this->fStart     = substr($slot->start_time, 0, 5);
        $this->fEnd       = substr($slot->end_time, 0, 5);
        $this->fSubjectId = (string) $slot->subject_id;
        $this->fStaffId   = (string) ($slot->staff_id ?? '');
        $this->fRoom      = $slot->room ?? '';
        $this->fColor     = $slot->color ?? '#1E2D5A';
        $this->showForm   = true;
    }

    public function saveSlot(): void
    {
        $this->validate([
            'classId'    => 'required|exists:school_classes,id',
            'fDay'       => 'required|integer|between:0,6',
            'fStart'     => 'required',
            'fEnd'       => 'required',
            'fSubjectId' => 'required|exists:subjects,id',
        ]);

        $year     = AcademicYearService::current();
        $schoolId = auth()->user()->school_id;

        if($this->fStart == '12:30'){
            $this->addError('fStart', 'Une séance ne peut pas commencer à 12h30 (heure de pause).');
        return;
        }

        TimetableSlot::updateOrCreate(
            $this->editingId
                ? ['id' => $this->editingId]
                : [
                    'school_class_id' => $this->classId,
                    'day_of_week'     => $this->fDay,
                    'start_time'      => $this->fStart,
                  ],
            [
                'school_id'        => $schoolId,
                'academic_year_id' => $year->id,
                'school_class_id'  => $this->classId,
                'day_of_week'      => $this->fDay,
                'start_time'       => $this->fStart,
                'end_time'         => $this->fEnd,
                'subject_id'       => $this->fSubjectId,
                'staff_id'         => $this->fStaffId ?: null,
                'room'             => $this->fRoom ?: null,
                'color'            => $this->fColor ?: null,
            ]
        );

        $this->closeForm();
        $this->saved = true;
    }

    public function confirmDelete(int $id): void { $this->confirmDeleteId = $id; }

    public function deleteSlot(): void
    {
        TimetableSlot::find($this->confirmDeleteId)?->delete();
        $this->confirmDeleteId = null;
    }

    public function closeForm(): void
    {
        $this->showForm   = false;
        $this->editingId  = null;
        $this->fSubjectId = '';
        $this->fStaffId   = '';
        $this->fRoom      = '';
    }

    private function nextSlot(string $start): string
    {
        // Fin suggérée = +1h par défaut, +2h courant
        $map = [
            '07:30' => '09:30',
            '08:30' => '09:30',
            '09:30' => '11:30',
            '10:30' => '11:30',
            '11:30' => '12:30',
            '12:30' => '14:30',
            '14:30' => '16:30',
            '15:30' => '16:30',
            '16:30' => '18:30',
            '17:30' => '18:30',
        ];
        return $map[$start] ?? '09:30';
    }

    /**
     * Calcule le rowspan d'un slot dans la grille.
     * Ex: 07:30→09:30 = 2 rangs, 07:30→10:30 = 3 rangs.
     * La pause 12:30→14:30 est transparente pour le calcul.
     */
    public function calcRowspan(string $startTime, string $endTime): int
    {
        $rows = $this->timeRows;
        $startIdx = array_search($startTime, $rows);
        $endIdx   = array_search($endTime, $rows);

        if ($startIdx === false) return 1;
        if ($endIdx === false) {
            // Fin hors grille (ex: 18:30 = fin de grille)
            return count($rows) - $startIdx;
        }

        $span = $endIdx - $startIdx;
        return max(1, $span);
    }

    public function with(): array
    {
        $year     = AcademicYearService::current();
        $schoolId = auth()->user()->school_id;
        $user     = auth()->user();

        $canManage = $user->hasAnyRole(['admin','directeur'])
            || $user->can('timetable.manage');

        $isParent   = $user->hasRole('parent');
        $myClassIds = AccessService::myClassIds();

        $classes = SchoolClass::where('school_id', $schoolId)
            ->where('academic_year_id', $year?->id)
            ->when($myClassIds !== null, fn ($q) => $q->whereIn('id', $myClassIds))
            ->with('level')
            ->orderBy('name')
            ->get();

        if ($isParent) {
            $guardian = \App\Models\Guardian::where('user_id', $user->id)->first();
            if ($guardian) {
                $childClassIds = \App\Models\StudentSchoolYear::whereHas('student', fn ($q) =>
                    $q->whereHas('guardians', fn ($q) => $q->where('guardian_id', $guardian->id))
                )->where('academic_year_id', $year?->id)
                 ->pluck('school_class_id')->toArray();

                $classes = $classes->filter(fn ($c) => in_array($c->id, $childClassIds));
                if (! $this->classId && $classes->isNotEmpty()) {
                    $this->classId = (string) $classes->first()->id;
                }
            }
        }

        // ── Grille — tableaux PHP natifs [] ──
        $grid      = [];
        $slotsRaw  = [];
        $subjects  = collect();
        $staffList = collect();

        if ($this->classId) {
            $dbSlots = TimetableSlot::where('school_class_id', $this->classId)
                ->where('school_id', $schoolId)
                ->where('academic_year_id', $year?->id)
                ->with(['subject','staff.user'])
                ->orderBy('start_time')
                ->get();

            foreach ($this->schoolDays as $day) {
                $grid[$day] = $dbSlots
                    ->filter(fn ($s) => (int) $s->day_of_week === $day)
                    ->sortBy('start_time')
                    ->values()
                    ->all();
            }

            $slotsRaw = $dbSlots->all();

            $subjects = Subject::whereHas('classSubjects', fn ($q) =>
                $q->where('school_class_id', $this->classId)
            )->orderBy('name')->get();

            $staffList = Staff::where('school_id', $schoolId)
                ->with('user')->get()->sortBy('user.name');
        }

        foreach ($this->schoolDays as $day) {
            if (! array_key_exists($day, $grid)) $grid[$day] = [];
        }

        $dayNames = TimetableSlot::$DAYS;

        return compact(
            'year', 'classes', 'grid', 'slotsRaw',
            'subjects', 'staffList', 'dayNames', 'canManage'
        );
    }
}; ?>

<style>
    .page-toolbar { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap; }
    .toolbar-left { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; }

    .sel { padding:.5rem .875rem; border-radius:8px; border:1.5px solid var(--line); background:var(--paper-raised); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; cursor:pointer; }
    .sel:focus { border-color:var(--sidebar-soft); }

    .btn-primary { display:inline-flex; align-items:center; gap:5px; padding:.5rem 1.1rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; transition:background .15s; }
    .btn-primary:hover { background:var(--sidebar-soft); }
    .btn-primary svg { width:14px; height:14px; }

    .form-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.5rem; animation:slideDown .15s ease; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
    .form-card-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .form-card-title  { font-family:'Fraunces',serif; font-size:.95rem; font-weight:600; color:var(--ink); }
    .form-card-body   { padding:1.25rem 1.5rem; display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; }
    @media(max-width:800px) { .form-card-body { grid-template-columns:1fr 1fr; } }
    .form-field { display:flex; flex-direction:column; gap:.35rem; }
    .form-label { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.5; }
    .form-input,.form-select-inp { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; transition:border-color .15s; }
    .form-input:focus,.form-select-inp:focus { border-color:var(--sidebar-soft); box-shadow:0 0 0 3px rgba(42,63,126,.08); }
    .form-error { font-size:.75rem; color:var(--accent-red); margin-top:.2rem; }
    .form-actions { display:flex; justify-content:flex-end; gap:.65rem; padding:1rem 1.5rem; border-top:1px solid var(--line); }
    .btn-save   { display:inline-flex; align-items:center; gap:5px; padding:.5rem 1.25rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
    .btn-cancel { padding:.5rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }

    /* ── Tableau EDT ── */
    .timetable-wrap { overflow-x:auto; }
    .timetable { width:100%; border-collapse:collapse; table-layout:fixed; min-width:700px; }

    .timetable thead th {
        padding:.75rem .5rem;
        font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600;
        text-transform:uppercase; letter-spacing:.06em;
        color:var(--ink); opacity:.5; text-align:center;
        border-bottom:2px solid var(--sidebar); background:var(--paper);
    }
    .timetable thead th:first-child { width:78px; }
    .timetable thead th.today { background:rgba(42,63,126,.07); color:var(--sidebar); opacity:1; border-bottom-color:var(--accent); }

    .timetable tbody tr { border-bottom:1px solid var(--line); }

    /* Cellule heure */
    .timetable tbody td.time-cell {
        background:var(--paper); text-align:right; padding:.5rem .75rem;
        border-right:2px solid var(--line); vertical-align:top;
        white-space:nowrap;
    }

    /* Ligne pause — non cliquable */
    .timetable tr.pause-row td .cell-add {
        display: none !important;
    }

    .timetable tr.pause-row {
        pointer-events: none;
        user-select: none;
    }
    .time-label { font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600; color:var(--ink); opacity:.4; }

    /* Cellule contenu */
    .timetable tbody td.day-cell {
        vertical-align:top; padding:.35rem; border-right:1px solid var(--line);
    }
    .timetable tbody td.day-cell:last-child { border-right:none; }

    /* Ligne pause */
    .timetable tr.pause-row td {
        background:repeating-linear-gradient(
            45deg,
            rgba(30,45,90,.025),
            rgba(30,45,90,.025) 4px,
            transparent 4px,
            transparent 10px
        );
        border-bottom:1px dashed rgba(30,45,90,.15);
        padding:.4rem .75rem;
    }
    .pause-label {
        font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600;
        color:var(--ink); opacity:.35; text-align:right;
    }
    .pause-text {
        font-size:11px; color:var(--ink); opacity:.25;
        font-family:'JetBrains Mono',monospace; text-align:center;
    }

    /* Carte créneau — avec hauteur dynamique via rowspan */
    .slot-card {
        border-radius:8px; padding:.55rem .7rem;
        position:relative; overflow:hidden; cursor:pointer;
        transition:opacity .12s, transform .12s;
        height:100%; min-height:50px;
    }
    .slot-card:hover { opacity:.88; transform:translateY(-1px); }
    .slot-subject { font-size:.8125rem; font-weight:600; color:var(--ink); line-height:1.2; margin-bottom:2px; }
    .slot-teacher { font-size:.75rem; color:var(--ink); opacity:.55; }
    .slot-time    { font-family:'JetBrains Mono',monospace; font-size:10px; color:var(--ink); opacity:.45; margin-top:4px; }
    .slot-room    { font-size:.7rem; font-family:'JetBrains Mono',monospace; color:var(--ink); opacity:.4; margin-top:2px; }
    .slot-actions { position:absolute; top:4px; right:4px; display:none; gap:3px; }
    .slot-card:hover .slot-actions { display:flex; }
    .slot-btn { width:20px; height:20px; border-radius:4px; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; }
    .slot-btn svg { width:11px; height:11px; }
    .slot-btn-edit { background:rgba(255,255,255,.8); color:var(--ink); }
    .slot-btn-del  { background:rgba(224,92,58,.15); color:var(--accent-red); }

    /* Cellule vide cliquable */
    .cell-add { display:flex; align-items:center; justify-content:center; height:100%; min-height:50px; cursor:pointer; border-radius:6px; border:1.5px dashed transparent; transition:all .12s; }
    .cell-add:hover { border-color:rgba(42,63,126,.3); background:rgba(42,63,126,.04); }
    .cell-add svg { width:16px; height:16px; color:var(--ink); opacity:.2; }
    .cell-add:hover svg { opacity:.5; color:var(--sidebar-soft); }

    /* Toast */
    .toast { display:flex; align-items:center; gap:.6rem; padding:.65rem 1rem; border-radius:8px; font-size:.875rem; font-weight:500; margin-bottom:1rem; animation:slideDown .15s ease; }
    .toast-ok { background:rgba(30,120,80,.1); border:1px solid rgba(30,120,80,.2); color:#166534; }
    .toast svg { width:15px; height:15px; flex-shrink:0; }

    /* Modal */
    .modal-overlay { position:fixed; inset:0; z-index:100; background:rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; padding:1rem; }
    .modal { background:var(--paper-raised); border-radius:14px; border:1px solid var(--line); padding:1.75rem; max-width:380px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.2); }
    .modal-title { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; margin-bottom:.5rem; }
    .modal-desc  { font-size:.875rem; color:var(--ink); opacity:.6; margin-bottom:1.25rem; }
    .modal-actions { display:flex; justify-content:flex-end; gap:.65rem; }
    .btn-modal-cancel  { padding:.45rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; cursor:pointer; font-family:'Inter',sans-serif; color:var(--ink); }
    .btn-modal-confirm { padding:.45rem 1rem; border-radius:8px; border:none; background:var(--accent-red); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; cursor:pointer; }

    /* Empty */
    .empty { padding:4rem 2rem; text-align:center; }
    .empty svg { width:44px; height:44px; margin:0 auto 1rem; opacity:.2; }
    .empty-title { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; color:var(--ink); }
    .empty-sub   { font-size:.875rem; color:var(--ink); opacity:.45; margin-top:.35rem; }

    /* Légende */
    .legend { display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:1.25rem; }
    .legend-item { display:flex; align-items:center; gap:.4rem; font-size:.8125rem; color:var(--ink); opacity:.65; }
    .legend-dot  { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
</style>

<div>
    {{-- Toolbar --}}
    <div class="page-toolbar">
        <div class="toolbar-left">
            <select wire:model.live="classId" class="sel">
                <option value="">— Sélectionner une classe —</option>
                @foreach ($classes->groupBy(fn($c) => $c->level?->cycle) as $cycle => $cycleClasses)
                    <optgroup label="{{ $cycle ?? 'Autre' }}">
                        @foreach ($cycleClasses as $class)
                            <option value="{{ $class->id }}">{{ $class->name }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            @if ($classId)
                <span style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--ink);opacity:.4;">
                    {{ $year?->label }}
                </span>
            @endif
        </div>
        @if ($canManage && $classId)
            <button wire:click="openCreate" class="btn-primary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Ajouter un créneau
            </button>
        @endif
    </div>

    @if ($saved)
        <div class="toast toast-ok" x-data x-init="setTimeout(() => $el.remove(), 3000)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Créneau enregistré.
        </div>
    @endif

    <div class="form-field">
        <label class="form-label">Heure début *</label>
        <select wire:model="fStart" class="form-select-inp">
            @foreach ($timeRows as $t)
                {{-- Masquer 12:30 comme option de début --}}
                @if ($t !== '12:30')
                    <option value="{{ $t }}">{{ $t }}</option>
                @endif
            @endforeach
        </select>
        @error('fStart') <span class="form-error">{{ $message }}</span> @enderror
    </div>

    {{-- Formulaire --}}
    @if ($showForm && $canManage)
        <div class="form-card">
            <div class="form-card-header">
                <span class="form-card-title">{{ $editingId ? 'Modifier le créneau' : 'Nouveau créneau' }}</span>
                <button wire:click="closeForm" style="background:none;border:none;cursor:pointer;opacity:.4;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="form-card-body">
                <div class="form-field">
                    <label class="form-label">Jour *</label>
                    <select wire:model="fDay" class="form-select-inp">
                        @foreach ($dayNames as $num => $name)
                            @if (in_array($num, $schoolDays))
                                <option value="{{ $num }}">{{ $name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label">Heure début *</label>
                    <select wire:model="fStart" class="form-select-inp">
                        @foreach ($timeRows as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label">Heure fin *</label>
                    <select wire:model="fEnd" class="form-select-inp">
                        @foreach ($timeRows as $t)
                            @if ($t > $fStart)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endif
                        @endforeach
                        {{-- Fin possible hors grille --}}
                        @if ($fStart < '14:30')
                            <option value="12:30">12:30</option>
                        @endif
                        <option value="18:30">18:30</option>
                    </select>
                    @error('fEnd') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-field">
                    <label class="form-label">Matière *</label>
                    <select wire:model="fSubjectId" class="form-select-inp">
                        <option value="">— Sélectionner —</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                        @endforeach
                    </select>
                    @error('fSubjectId') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-field">
                    <label class="form-label">Enseignant</label>
                    <select wire:model="fStaffId" class="form-select-inp">
                        <option value="">— Optionnel —</option>
                        @foreach ($staffList as $s)
                            <option value="{{ $s->id }}">{{ $s->user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label">Salle</label>
                    <input wire:model="fRoom" type="text" class="form-input" placeholder="Ex: Salle 3">
                </div>
                <div class="form-field">
                    <label class="form-label">Couleur</label>
                    <input wire:model="fColor" type="color" class="form-input" style="height:38px;padding:.2rem;">
                </div>
            </div>
            <div class="form-actions">
                <button wire:click="closeForm" class="btn-cancel">Annuler</button>
                <button wire:click="saveSlot" class="btn-save">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Enregistrer
                </button>
            </div>
        </div>
    @endif

    {{-- Contenu --}}
    @if (! $classId)
        <div class="empty">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <div class="empty-title">Sélectionne une classe</div>
            <div class="empty-sub">L'emploi du temps s'affichera ici.</div>
        </div>
    @else

        {{-- Légende --}}
        @php $slotsCollection = collect($slotsRaw); @endphp
        @if ($slotsCollection->isNotEmpty())
            <div class="legend">
                @foreach ($slotsCollection->unique('subject_id') as $slot)
                    <div class="legend-item">
                        <div class="legend-dot" style="background:{{ $slot->effectiveColor() }}"></div>
                        {{ $slot->subject?->name }}
                    </div>
                @endforeach
            </div>
        @endif

        <div class="timetable-wrap">
            <table class="timetable">
                <thead>
                    <tr>
                        <th style="width:78px;">Heure</th>
                        @php $todayDow = (int) now()->dayOfWeek; @endphp
                        @foreach ($schoolDays as $day)
                            <th class="{{ $day === $todayDow ? 'today' : '' }}">
                                {{ $dayNames[$day] }}
                                @if ($day === $todayDow)
                                    <div style="font-size:9px;font-weight:400;opacity:.6;margin-top:1px;">Aujourd'hui</div>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @php
                        /*
                         * $timeRows = ['07:30','08:30',...,'12:30','14:30',...,'18:30']
                         *
                         * $covered[$day][$timeIdx] = true
                         *   → cette cellule est déjà couverte par un rowspan d'une ligne précédente,
                         *     ne pas rendre de <td> pour elle.
                         */
                        $covered = [];
                        foreach ($schoolDays as $d) $covered[$d] = [];
                    @endphp

                    @foreach ($timeRows as $rowIdx => $time)
                        <tr>
                            {{-- Cellule heure --}}
                            <td class="time-cell">
                                <span class="time-label">{{ $time }}</span>
                            </td>

                            @foreach ($schoolDays as $day)
                                @php
                                    // Cette cellule est couverte par un rowspan au-dessus → on saute
                                    if (! empty($covered[$day][$rowIdx])) continue;

                                    // Slot qui commence exactement à cette heure pour ce jour
                                    $dayArr    = $grid[$day] ?? [];
                                    $slotHere  = null;
                                    foreach ($dayArr as $s) {
                                        if (substr($s->start_time, 0, 5) === $time) {
                                            $slotHere = $s;
                                            break;
                                        }
                                    }

                                    $rowspan = 1;
                                    if ($slotHere) {
                                        $rowspan = $this->calcRowspan(
                                            substr($slotHere->start_time, 0, 5),
                                            substr($slotHere->end_time,   0, 5)
                                        );
                                        // Marquer les lignes suivantes comme couvertes
                                        for ($r = 1; $r < $rowspan; $r++) {
                                            $covered[$day][$rowIdx + $r] = true;
                                        }
                                    }
                                @endphp

                                <td class="day-cell"
                                    @if ($rowspan > 1) rowspan="{{ $rowspan }}" @endif
                                    style="height:{{ $rowspan * 60 }}px;">

                                    @if ($slotHere)
                                        @php
                                            $bg      = $slotHere->effectiveColor();
                                            $r       = hexdec(substr($bg, 1, 2));
                                            $g       = hexdec(substr($bg, 3, 2));
                                            $b       = hexdec(substr($bg, 5, 2));
                                            $bgLight = "rgba({$r},{$g},{$b},.1)";
                                        @endphp
                                        <div class="slot-card"
                                             style="background:{{ $bgLight }};border-left:3px solid {{ $bg }};">

                                            @if ($canManage)
                                                <div class="slot-actions">
                                                    <button class="slot-btn slot-btn-edit"
                                                            wire:click="openEdit({{ $slotHere->id }})">
                                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                                    </button>
                                                    <button class="slot-btn slot-btn-del"
                                                            wire:click="confirmDelete({{ $slotHere->id }})">
                                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16"/></svg>
                                                    </button>
                                                </div>
                                            @endif

                                            <div class="slot-subject">{{ $slotHere->subject?->name }}</div>
                                            @if ($slotHere->staff)
                                                <div class="slot-teacher">{{ $slotHere->staff->user->name }}</div>
                                            @endif
                                            <div class="slot-time">
                                                {{ substr($slotHere->start_time, 0, 5) }} – {{ substr($slotHere->end_time, 0, 5) }}
                                                @if ($rowspan > 1)
                                                    <span style="font-size:9px;opacity:.55;">· {{ $rowspan }}h</span>
                                                @endif
                                            </div>
                                            @if ($slotHere->room)
                                                <div class="slot-room">📍 {{ $slotHere->room }}</div>
                                            @endif
                                        </div>

                                    {{-- Cellule vide cliquable — sauf si c'est 12:30 --}}
                                    @elseif ($canManage && $time !== '12:30')
                                        <div class="cell-add"
                                            wire:click="openCreate({{ $day }}, '{{ $time }}')">
                                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                            </svg>
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>

                        {{-- Ligne pause après 12:30 --}}
                        @if ($time === '12:30')
                            <tr class="pause-row">
                                <td class="time-cell">
                                    <span class="pause-label">12:30<br>↓<br>14:30</span>
                                </td>
                                @foreach ($schoolDays as $day)
                                    <td><div class="pause-text">— Pause —</div></td>
                                @endforeach
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($confirmDeleteId)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Supprimer ce créneau ?</div>
                <div class="modal-desc">Le créneau sera définitivement supprimé de l'emploi du temps.</div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeleteId', null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deleteSlot" class="btn-modal-confirm">Supprimer</button>
                </div>
            </div>
        </div>
    @endif
</div>