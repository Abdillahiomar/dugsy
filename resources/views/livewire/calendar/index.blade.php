<?php

use App\Models\SchoolEvent;
use App\Services\AcademicYearService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component
{
    public int $year  = 0;
    public int $month = 0;

    // Formulaire
    public bool   $showForm      = false;
    public ?int   $editingId     = null;
    public string $fTitle        = '';
    public string $fType         = 'other';
    public string $fStartDate    = '';
    public string $fEndDate      = '';
    public bool   $fIsAllDay     = true;
    public string $fStartTime    = '08:00';
    public string $fEndTime      = '10:00';
    public string $fDescription  = '';
    public string $fColor        = '#1E2D5A';

    // Détail
    public ?int $viewingId = null;

    public ?int $confirmDeleteId = null;
    public bool $saved = false;

    public function mount(): void
    {
        $now         = now();
        $this->year  = $now->year;
        $this->month = $now->month;
    }

    public function prevMonth(): void
    {
        $d = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->year  = $d->year;
        $this->month = $d->month;
    }

    public function nextMonth(): void
    {
        $d = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->year  = $d->year;
        $this->month = $d->month;
    }

    public function goToday(): void
    {
        $this->year  = now()->year;
        $this->month = now()->month;
    }

    public function openCreate(?string $date = null): void
    {
        $this->editingId    = null;
        $this->fTitle       = '';
        $this->fType        = 'other';
        $this->fStartDate   = $date ?? now()->format('Y-m-d');
        $this->fEndDate     = $date ?? now()->format('Y-m-d');
        $this->fIsAllDay    = true;
        $this->fStartTime   = '08:00';
        $this->fEndTime     = '10:00';
        $this->fDescription = '';
        $this->fColor       = '#1E2D5A';
        $this->showForm     = true;
        $this->viewingId    = null;
    }

    public function openEdit(int $id): void
    {
        $event = SchoolEvent::find($id);
        if (! $event) return;

        $this->editingId    = $id;
        $this->fTitle       = $event->title;
        $this->fType        = $event->type;
        $this->fStartDate   = $event->start_date->format('Y-m-d');
        $this->fEndDate     = $event->end_date->format('Y-m-d');
        $this->fIsAllDay    = $event->is_all_day;
        $this->fStartTime   = $event->start_time ?? '08:00';
        $this->fEndTime     = $event->end_time ?? '10:00';
        $this->fDescription = $event->description ?? '';
        $this->fColor       = $event->color;
        $this->showForm     = true;
        $this->viewingId    = null;
    }

    public function viewEvent(int $id): void
    {
        $this->viewingId = $id;
    }

    public function saveEvent(): void
    {
        $this->validate([
            'fTitle'     => 'required|string|max:200',
            'fType'      => 'required|string',
            'fStartDate' => 'required|date',
            'fEndDate'   => 'required|date|after_or_equal:fStartDate',
            'fColor'     => 'required|string',
        ]);

        $data = [
            'school_id'   => auth()->user()->school_id,
            'user_id'     => auth()->id(),
            'title'       => $this->fTitle,
            'type'        => $this->fType,
            'start_date'  => $this->fStartDate,
            'end_date'    => $this->fEndDate,
            'is_all_day'  => $this->fIsAllDay,
            'start_time'  => $this->fIsAllDay ? null : $this->fStartTime,
            'end_time'    => $this->fIsAllDay ? null : $this->fEndTime,
            'description' => $this->fDescription ?: null,
            'color'       => $this->fColor,
        ];

        if ($this->editingId) {
            SchoolEvent::where('id', $this->editingId)->update($data);
        } else {
            SchoolEvent::create($data);
        }

        $this->showForm = false;
        $this->editingId = null;
        $this->saved = true;
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmDeleteId = $id;
        $this->viewingId = null;
    }

    public function deleteEvent(): void
    {
        SchoolEvent::find($this->confirmDeleteId)?->delete();
        $this->confirmDeleteId = null;
    }

    public function canManage(): bool
    {
        return auth()->user()->hasAnyRole(['admin','directeur'])
            || auth()->user()->can('events.manage');
    }

    public function with(): array
    {
        $schoolId  = auth()->user()->school_id;
        $canManage = $this->canManage();

        // Construire le calendrier du mois
        // Semaine commence DIMANCHE (Carbon: startOfWeek(Carbon::SUNDAY))
        $firstDay = Carbon::create($this->year, $this->month, 1);
        $lastDay  = $firstDay->copy()->endOfMonth();

        // Début de la grille = dimanche avant ou égal au 1er
        $gridStart = $firstDay->copy()->startOfWeek(Carbon::SUNDAY);
        // Fin de la grille = samedi après ou égal au dernier jour
        $gridEnd   = $lastDay->copy()->endOfWeek(Carbon::SUNDAY);

        // Générer toutes les semaines
        $weeks = [];
        $current = $gridStart->copy();
        while ($current->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = $current->copy();
                $current->addDay();
            }
            $weeks[] = $week;
        }

        // Charger les événements du mois (+ débordements)
        $events = SchoolEvent::where('school_id', $schoolId)
            ->where('start_date', '<=', $gridEnd->format('Y-m-d'))
            ->where('end_date',   '>=', $gridStart->format('Y-m-d'))
            ->orderBy('start_date')
            ->get();

        // Événement en cours de visualisation
        $viewingEvent = $this->viewingId
            ? SchoolEvent::find($this->viewingId)
            : null;

        // Événements du mois pour la liste latérale
        $monthEvents = $events->filter(fn ($e) =>
            $e->start_date->month === $this->month ||
            $e->end_date->month === $this->month
        )->sortBy('start_date')->values();

        $today      = now()->startOfDay();
        $monthLabel = $firstDay->locale('fr')->isoFormat('MMMM YYYY');

        $eventTypes = SchoolEvent::$TYPES;

        // Noms des jours (commence dimanche)
        $dayNames = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];

        return compact(
            'weeks', 'events', 'monthEvents', 'viewingEvent',
            'today', 'monthLabel', 'dayNames',
            'eventTypes', 'canManage'
        );
    }
}; ?>

<style>
    /* ── Layout ── */
    .cal-layout { display:grid; grid-template-columns:1fr 280px; gap:1.5rem; align-items:start; }
    @media(max-width:900px) { .cal-layout { grid-template-columns:1fr; } }

    /* ── Header navigation ── */
    .cal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem; flex-wrap:wrap; gap:.75rem; }
    .cal-nav    { display:flex; align-items:center; gap:.5rem; }
    .cal-title  { font-family:'Fraunces',serif; font-size:1.375rem; font-weight:600; color:var(--ink); letter-spacing:-.02em; text-transform:capitalize; min-width:180px; text-align:center; }
    .btn-nav    { width:32px; height:32px; border-radius:8px; border:1px solid var(--line); background:var(--paper-raised); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all .12s; }
    .btn-nav:hover { border-color:var(--sidebar-soft); }
    .btn-nav svg { width:15px; height:15px; }
    .btn-today  { padding:.35rem .875rem; border-radius:8px; border:1px solid var(--line); background:var(--paper-raised); font-size:.8125rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; transition:all .12s; }
    .btn-today:hover { border-color:var(--sidebar-soft); color:var(--sidebar-soft); }

    .btn-new-event { display:inline-flex; align-items:center; gap:5px; padding:.5rem 1.1rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; transition:background .15s; }
    .btn-new-event:hover { background:var(--sidebar-soft); }
    .btn-new-event svg { width:14px; height:14px; }

    /* ── Grille calendrier ── */
    .cal-grid { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; }

    .cal-day-headers { display:grid; grid-template-columns:repeat(7,1fr); border-bottom:2px solid var(--sidebar); }
    .cal-day-header { padding:.6rem; text-align:center; font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--ink); opacity:.45; }
    .cal-day-header:first-child { color:var(--accent-red); opacity:.7; } /* Dimanche */
    .cal-day-header:last-child  { color:var(--sidebar-soft); opacity:.7; } /* Samedi */

    .cal-weeks { display:flex; flex-direction:column; }
    .cal-week  { display:grid; grid-template-columns:repeat(7,1fr); border-bottom:1px solid var(--line); }
    .cal-week:last-child { border-bottom:none; }

    /* Cellule jour */
    .cal-cell {
        min-height:100px;
        padding:.5rem .4rem;
        border-right:1px solid var(--line);
        position:relative;
        cursor: default;
        transition:background .1s;
    }
    .cal-cell:last-child { border-right:none; }
    .cal-cell:first-child .cal-cell-num { color:var(--accent-red); } /* Dimanche */
    .cal-cell:last-child  .cal-cell-num { color:var(--sidebar-soft); } /* Samedi */
    .cal-cell.other-month { background:rgba(0,0,0,.015); }
    .cal-cell.other-month .cal-cell-num { opacity:.3; }
    .cal-cell.today { background:rgba(42,63,126,.04); }

    .cal-cell-num {
        font-family:'JetBrains Mono',monospace;
        font-size:12px;
        font-weight:600;
        color:var(--ink);
        margin-bottom:.35rem;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:24px;
        height:24px;
        border-radius:50%;
    }

    .cal-cell.today .cal-cell-num {
        background:var(--sidebar);
        color:white;
    }

    .cal-cell-add {
        position:absolute;
        top:4px; right:4px;
        width:20px; height:20px;
        border-radius:5px;
        border:none;
        background:none;
        cursor:pointer;
        display:none;
        align-items:center;
        justify-content:center;
        color:var(--ink);
        opacity:.3;
    }
    .cal-cell:hover .cal-cell-add { display:flex; opacity:.6; }
    .cal-cell-add:hover { background:rgba(42,63,126,.1); opacity:1; color:var(--sidebar-soft); }
    .cal-cell-add svg { width:12px; height:12px; }

    /* Événements dans la cellule */
    .cal-event {
        display:block;
        border-radius:4px;
        padding:2px 6px;
        margin-bottom:2px;
        font-size:11px;
        font-weight:600;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
        cursor:pointer;
        transition:opacity .1s;
        line-height:1.4;
    }
    .cal-event:hover { opacity:.8; }
    .cal-event-dot {
        display:inline-block;
        width:6px; height:6px;
        border-radius:50%;
        margin-right:3px;
        vertical-align:middle;
    }
    .cal-more { font-size:10px; color:var(--ink); opacity:.45; padding:1px 4px; cursor:pointer; }
    .cal-more:hover { opacity:.7; }

    /* ── Formulaire slide-in ── */
    .form-panel { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.25rem; animation:slideDown .15s ease; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
    .form-panel-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .form-panel-title  { font-family:'Fraunces',serif; font-size:.95rem; font-weight:600; color:var(--ink); }
    .form-panel-body   { padding:1.25rem 1.5rem; display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .form-field { display:flex; flex-direction:column; gap:.35rem; }
    .form-field.full { grid-column:1/-1; }
    .form-label { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.5; }
    .form-input,.form-select-inp { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; transition:border-color .15s; }
    .form-input:focus,.form-select-inp:focus { border-color:var(--sidebar-soft); box-shadow:0 0 0 3px rgba(42,63,126,.08); }
    .form-textarea { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; resize:vertical; min-height:64px; }
    .form-actions { display:flex; justify-content:flex-end; gap:.65rem; padding:1rem 1.5rem; border-top:1px solid var(--line); }
    .btn-save   { display:inline-flex; align-items:center; gap:5px; padding:.5rem 1.25rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
    .btn-cancel { padding:.5rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }

    /* Toggle all-day */
    .toggle-row { display:flex; align-items:center; gap:.75rem; }
    .toggle-switch { position:relative; width:36px; height:20px; cursor:pointer; flex-shrink:0; }
    .toggle-switch input { opacity:0; width:0; height:0; }
    .toggle-slider { position:absolute; inset:0; border-radius:20px; background:var(--line); transition:background .2s; }
    .toggle-slider::before { content:''; position:absolute; width:14px; height:14px; border-radius:50%; background:white; top:3px; left:3px; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
    .toggle-switch input:checked + .toggle-slider { background:var(--sidebar-soft); }
    .toggle-switch input:checked + .toggle-slider::before { transform:translateX(16px); }
    .toggle-label { font-size:.875rem; font-weight:500; color:var(--ink); }

    /* ── Sidebar événements ── */
    .side-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1rem; }
    .side-card-header { padding:.75rem 1.25rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .side-card-title { font-family:'Fraunces',serif; font-size:.9375rem; font-weight:600; color:var(--ink); }

    .event-list-item { display:flex; align-items:flex-start; gap:.65rem; padding:.75rem 1.25rem; border-bottom:1px solid var(--line); cursor:pointer; transition:background .1s; }
    .event-list-item:last-child { border-bottom:none; }
    .event-list-item:hover { background:rgba(30,45,90,.025); }
    .event-list-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; margin-top:3px; }
    .event-list-title { font-size:.875rem; font-weight:600; color:var(--ink); margin-bottom:1px; }
    .event-list-date  { font-family:'JetBrains Mono',monospace; font-size:10px; color:var(--ink); opacity:.45; }
    .event-list-type  { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; padding:1px 6px; border-radius:3px; background:rgba(42,63,126,.08); color:var(--sidebar-soft); margin-top:3px; display:inline-block; }

    /* Détail événement */
    .event-detail { padding:1.25rem; }
    .event-detail-title { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; color:var(--ink); margin-bottom:.75rem; }
    .event-detail-row { display:flex; align-items:flex-start; gap:.65rem; font-size:.875rem; color:var(--ink); margin-bottom:.5rem; }
    .event-detail-row svg { width:15px; height:15px; color:var(--sidebar-soft); opacity:.7; flex-shrink:0; margin-top:1px; }
    .event-detail-desc { font-size:.875rem; color:var(--ink); opacity:.65; line-height:1.6; margin-top:.75rem; padding-top:.75rem; border-top:1px solid var(--line); }
    .event-detail-actions { display:flex; gap:.5rem; margin-top:1rem; padding-top:.75rem; border-top:1px solid var(--line); }
    .btn-detail-edit { display:inline-flex; align-items:center; gap:4px; padding:.35rem .75rem; border-radius:6px; font-size:.8rem; font-weight:600; font-family:'Inter',sans-serif; background:rgba(42,63,126,.08); color:var(--sidebar-soft); border:none; cursor:pointer; }
    .btn-detail-del  { display:inline-flex; align-items:center; gap:4px; padding:.35rem .75rem; border-radius:6px; font-size:.8rem; font-weight:600; font-family:'Inter',sans-serif; background:rgba(224,92,58,.08); color:var(--accent-red); border:none; cursor:pointer; }

    /* Légende types */
    .type-legend { display:flex; flex-wrap:wrap; gap:.5rem; padding:.875rem 1.25rem; border-top:1px solid var(--line); }
    .type-chip { display:inline-flex; align-items:center; gap:.35rem; font-size:.75rem; color:var(--ink); opacity:.7; }
    .type-dot  { width:8px; height:8px; border-radius:50%; }

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
</style>

<div>

    @if ($saved)
        <div class="toast toast-ok" x-data x-init="setTimeout(() => $el.remove(), 3000)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Événement enregistré.
        </div>
    @endif

    {{-- ── Header navigation ── --}}
    <div class="cal-header">
        <div class="cal-nav">
            <button wire:click="prevMonth" class="btn-nav">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <div class="cal-title">{{ $monthLabel }}</div>
            <button wire:click="nextMonth" class="btn-nav">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
            <button wire:click="goToday" class="btn-today">Aujourd'hui</button>
        </div>

        @if ($canManage)
            <button wire:click="openCreate" class="btn-new-event">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Nouvel événement
            </button>
        @endif
    </div>

    {{-- ── Formulaire ── --}}
    @if ($showForm && $canManage)
        <div class="form-panel">
            <div class="form-panel-header">
                <span class="form-panel-title">{{ $editingId ? "Modifier l'événement" : 'Nouvel événement' }}</span>
                <button wire:click="$set('showForm',false)" style="background:none;border:none;cursor:pointer;opacity:.4;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="form-panel-body">
                <div class="form-field full">
                    <label class="form-label">Titre *</label>
                    <input wire:model="fTitle" type="text" class="form-input"
                           placeholder="Ex: Réunion parents-professeurs">
                    @error('fTitle') <span style="font-size:.75rem;color:var(--accent-red);">{{ $message }}</span> @enderror
                </div>

                <div class="form-field">
                    <label class="form-label">Type *</label>
                    <select wire:model="fType" class="form-select-inp">
                        @foreach ($eventTypes as $key => $info)
                            <option value="{{ $key }}">{{ $info['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-field">
                    <label class="form-label">Couleur</label>
                    <input wire:model="fColor" type="color" class="form-input" style="height:38px;padding:.2rem;">
                </div>

                <div class="form-field">
                    <label class="form-label">Date de début *</label>
                    <input wire:model="fStartDate" type="date" class="form-input">
                    @error('fStartDate') <span style="font-size:.75rem;color:var(--accent-red);">{{ $message }}</span> @enderror
                </div>

                <div class="form-field">
                    <label class="form-label">Date de fin *</label>
                    <input wire:model="fEndDate" type="date" class="form-input">
                    @error('fEndDate') <span style="font-size:.75rem;color:var(--accent-red);">{{ $message }}</span> @enderror
                </div>

                <div class="form-field full">
                    <div class="toggle-row">
                        <label class="toggle-switch">
                            <input type="checkbox" wire:model.live="fIsAllDay">
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="toggle-label">Journée entière</span>
                    </div>
                </div>

                @if (! $fIsAllDay)
                    <div class="form-field">
                        <label class="form-label">Heure début</label>
                        <input wire:model="fStartTime" type="time" class="form-input">
                    </div>
                    <div class="form-field">
                        <label class="form-label">Heure fin</label>
                        <input wire:model="fEndTime" type="time" class="form-input">
                    </div>
                @endif

                <div class="form-field full">
                    <label class="form-label">Description</label>
                    <textarea wire:model="fDescription" class="form-textarea"
                              placeholder="Détails, lieu, informations complémentaires..."></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button wire:click="$set('showForm',false)" class="btn-cancel">Annuler</button>
                <button wire:click="saveEvent" class="btn-save">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Enregistrer
                </button>
            </div>
        </div>
    @endif

    <div class="cal-layout">

        {{-- ── Grille calendrier ── --}}
        <div>
            <div class="cal-grid">

                {{-- En-têtes jours — Dim en premier ── --}}
                <div class="cal-day-headers">
                    @foreach ($dayNames as $dn)
                        <div class="cal-day-header">{{ $dn }}</div>
                    @endforeach
                </div>

                {{-- Semaines ── --}}
                <div class="cal-weeks">
                    @foreach ($weeks as $week)
                        <div class="cal-week">
                            @foreach ($week as $day)
                                @php
                                    $isToday      = $day->isSameDay($today);
                                    $isOtherMonth = $day->month !== $month;
                                    $dateStr      = $day->format('Y-m-d');

                                    // Événements de ce jour (max 3 affichés)
                                    $dayEvents = $events->filter(fn ($e) => $e->spansDay($day));
                                    $visible   = $dayEvents->take(3);
                                    $overflow  = $dayEvents->count() - 3;
                                @endphp
                                <div class="cal-cell {{ $isOtherMonth ? 'other-month' : '' }} {{ $isToday ? 'today' : '' }}">

                                    <span class="cal-cell-num">{{ $day->day }}</span>

                                    @if ($canManage)
                                        <button class="cal-cell-add"
                                                wire:click="openCreate('{{ $dateStr }}')">
                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                        </button>
                                    @endif

                                    @foreach ($visible as $evt)
                                        @php
                                            $r   = hexdec(substr($evt->color,1,2));
                                            $g   = hexdec(substr($evt->color,3,2));
                                            $b   = hexdec(substr($evt->color,5,2));
                                            $bg  = "rgba({$r},{$g},{$b},.14)";
                                            $fg  = $evt->color;
                                        @endphp
                                        <div class="cal-event"
                                             style="background:{{ $bg }};color:{{ $fg }};"
                                             wire:click="viewEvent({{ $evt->id }})"
                                             title="{{ $evt->title }}">
                                            <span class="cal-event-dot" style="background:{{ $fg }}"></span>
                                            {{ Str::limit($evt->title, 18) }}
                                        </div>
                                    @endforeach

                                    @if ($overflow > 0)
                                        <div class="cal-more">+{{ $overflow }} autre{{ $overflow > 1 ? 's' : '' }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Sidebar ── --}}
        <div style="position:sticky;top:1.5rem;">

            {{-- Détail d'un événement cliqué --}}
            @if ($viewingEvent)
                <div class="side-card" style="border-color:{{ $viewingEvent->color }};">
                    <div class="side-card-header" style="border-bottom-color:{{ $viewingEvent->color }};">
                        <div style="display:flex;align-items:center;gap:.5rem;">
                            <div style="width:10px;height:10px;border-radius:50%;background:{{ $viewingEvent->color }};flex-shrink:0;"></div>
                            <span class="side-card-title">{{ $viewingEvent->typeLabel() }}</span>
                        </div>
                        <button wire:click="$set('viewingId',null)" style="background:none;border:none;cursor:pointer;opacity:.4;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="event-detail">
                        <div class="event-detail-title">{{ $viewingEvent->title }}</div>
                        <div class="event-detail-row">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span>
                                {{ $viewingEvent->start_date->locale('fr')->isoFormat('D MMM YYYY') }}
                                @if (! $viewingEvent->start_date->isSameDay($viewingEvent->end_date))
                                    → {{ $viewingEvent->end_date->locale('fr')->isoFormat('D MMM YYYY') }}
                                    ({{ $viewingEvent->durationDays() }} jours)
                                @endif
                            </span>
                        </div>
                        @if (! $viewingEvent->is_all_day && $viewingEvent->start_time)
                            <div class="event-detail-row">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>{{ substr($viewingEvent->start_time,0,5) }} – {{ substr($viewingEvent->end_time,0,5) }}</span>
                            </div>
                        @else
                            <div class="event-detail-row">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>Journée entière</span>
                            </div>
                        @endif
                        @if ($viewingEvent->description)
                            <div class="event-detail-desc">{{ $viewingEvent->description }}</div>
                        @endif
                        @if ($canManage)
                            <div class="event-detail-actions">
                                <button wire:click="openEdit({{ $viewingEvent->id }})" class="btn-detail-edit">
                                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    Modifier
                                </button>
                                <button wire:click="confirmDelete({{ $viewingEvent->id }})" class="btn-detail-del">
                                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16"/></svg>
                                    Supprimer
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Liste des événements du mois --}}
            <div class="side-card">
                <div class="side-card-header">
                    <span class="side-card-title">Événements du mois</span>
                    <span style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--ink);opacity:.4;">{{ $monthEvents->count() }}</span>
                </div>

                @forelse ($monthEvents as $evt)
                    <div class="event-list-item" wire:click="viewEvent({{ $evt->id }})">
                        <div class="event-list-dot" style="background:{{ $evt->color }}"></div>
                        <div style="min-width:0;">
                            <div class="event-list-title">{{ $evt->title }}</div>
                            <div class="event-list-date">
                                {{ $evt->start_date->locale('fr')->isoFormat('D MMM') }}
                                @if (! $evt->start_date->isSameDay($evt->end_date))
                                    → {{ $evt->end_date->locale('fr')->isoFormat('D MMM') }}
                                @endif
                            </div>
                            <span class="event-list-type">{{ $evt->typeLabel() }}</span>
                        </div>
                    </div>
                @empty
                    <div style="padding:2rem;text-align:center;font-size:.875rem;color:var(--ink);opacity:.4;">
                        Aucun événement ce mois-ci.
                    </div>
                @endforelse

                {{-- Légende types --}}
                <div class="type-legend">
                    @foreach ($eventTypes as $key => $info)
                        <div class="type-chip">
                            <div class="type-dot" style="background:{{ $info['color'] }}"></div>
                            {{ $info['label'] }}
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Modal suppression --}}
    @if ($confirmDeleteId)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Supprimer cet événement ?</div>
                <div class="modal-desc">L'événement sera définitivement supprimé du calendrier.</div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeleteId',null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deleteEvent" class="btn-modal-confirm">Supprimer</button>
                </div>
            </div>
        </div>
    @endif

</div>
