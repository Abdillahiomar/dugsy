<?php

use App\Models\Level;
use App\Models\RequiredDocument;
use App\Models\SchoolAdmissionConfig;
use App\Services\AcademicYearService;
use Livewire\Attributes\Layout;
use Livewire\Component;


new class extends Component
{
    public string $activeTab = 'policy';

    // ── Politique d'admission ────────────────────────────────────
    public ?int   $min_age_years             = null;
    public ?int   $max_age_years             = null;
    public bool   $requires_entrance_test    = false;
    public string $entrance_test_description = '';
    public bool   $enforce_capacity          = true;
    public bool   $priority_siblings         = false;
    public bool   $priority_staff_children   = false;
    public string $admission_conditions      = '';
    public string $enrollment_open_from      = '';
    public string $enrollment_open_until     = '';
    public bool   $is_enrollment_open        = true;

    // ── Pièces à fournir ─────────────────────────────────────────
    public bool   $showDocForm    = false;
    public ?int   $editingDocId   = null;
    public string $docName        = '';
    public string $docDescription = '';
    public bool   $docMandatory   = true;
    public string $docAppliesTo   = 'all';
    public array  $docLevels      = [];
    public int    $docOrder       = 0;

    public ?int   $confirmDeleteDocId = null;

    public bool $savedPolicy = false;
    public bool $savedDoc    = false;

    public function mount(): void
    {
        $schoolId = auth()->user()->school_id;
        $config   = SchoolAdmissionConfig::where('school_id', $schoolId)->first();

        if ($config) {
            $this->min_age_years             = $config->min_age_years;
            $this->max_age_years             = $config->max_age_years;
            $this->requires_entrance_test    = $config->requires_entrance_test;
            $this->entrance_test_description = $config->entrance_test_description ?? '';
            $this->enforce_capacity          = $config->enforce_capacity;
            $this->priority_siblings         = $config->priority_siblings;
            $this->priority_staff_children   = $config->priority_staff_children;
            $this->admission_conditions      = $config->admission_conditions ?? '';
            $this->enrollment_open_from      = $config->enrollment_open_from?->format('Y-m-d') ?? '';
            $this->enrollment_open_until     = $config->enrollment_open_until?->format('Y-m-d') ?? '';
            $this->is_enrollment_open        = $config->is_enrollment_open;
        }
    }

    public function savePolicy(): void
    {
        $this->validate([
            'min_age_years'  => 'nullable|integer|min:2|max:25',
            'max_age_years'  => 'nullable|integer|min:2|max:25',
            'enrollment_open_from'  => 'nullable|date',
            'enrollment_open_until' => 'nullable|date|after_or_equal:enrollment_open_from',
        ]);

        SchoolAdmissionConfig::updateOrCreate(
            ['school_id' => auth()->user()->school_id],
            [
                'min_age_years'             => $this->min_age_years,
                'max_age_years'             => $this->max_age_years,
                'requires_entrance_test'    => $this->requires_entrance_test,
                'entrance_test_description' => $this->entrance_test_description ?: null,
                'enforce_capacity'          => $this->enforce_capacity,
                'priority_siblings'         => $this->priority_siblings,
                'priority_staff_children'   => $this->priority_staff_children,
                'admission_conditions'      => $this->admission_conditions ?: null,
                'enrollment_open_from'      => $this->enrollment_open_from ?: null,
                'enrollment_open_until'     => $this->enrollment_open_until ?: null,
                'is_enrollment_open'        => $this->is_enrollment_open,
            ]
        );

        $this->savedPolicy = true;
    }

    // ── Documents ────────────────────────────────────────────────

    public function openCreateDoc(): void
    {
        $this->resetDocForm();
        $this->showDocForm  = true;
        $this->editingDocId = null;
    }

    public function openEditDoc(int $id): void
    {
        $doc = RequiredDocument::find($id);
        if (! $doc) return;

        $this->editingDocId   = $id;
        $this->docName        = $doc->name;
        $this->docDescription = $doc->description ?? '';
        $this->docMandatory   = $doc->is_mandatory;
        $this->docAppliesTo   = $doc->applies_to;
        $this->docLevels      = $doc->applies_to_levels ?? [];
        $this->docOrder       = $doc->order;
        $this->showDocForm    = true;
    }

    public function saveDoc(): void
    {
        $this->validate([
            'docName' => 'required|string|max:200',
        ]);

        $data = [
            'school_id'         => auth()->user()->school_id,
            'name'              => $this->docName,
            'description'       => $this->docDescription ?: null,
            'is_mandatory'      => $this->docMandatory,
            'applies_to'        => $this->docAppliesTo,
            'applies_to_levels' => empty($this->docLevels) ? null : $this->docLevels,
            'order'             => $this->docOrder,
        ];

        if ($this->editingDocId) {
            RequiredDocument::where('id', $this->editingDocId)->update($data);
        } else {
            RequiredDocument::create($data);
        }

        $this->resetDocForm();
        $this->savedDoc = true;
    }

    public function toggleDoc(int $id): void
    {
        $doc = RequiredDocument::find($id);
        $doc?->update(['is_active' => ! $doc->is_active]);
    }

    public function confirmDeleteDoc(int $id): void
    {
        $this->confirmDeleteDocId = $id;
    }

    public function deleteDoc(): void
    {
        if (! $this->confirmDeleteDocId) return;
        RequiredDocument::where('id', $this->confirmDeleteDocId)
            ->where('school_id', auth()->user()->school_id)
            ->delete();
        $this->confirmDeleteDocId = null;
    }

    private function resetDocForm(): void
    {
        $this->docName = $this->docDescription = '';
        $this->docMandatory  = true;
        $this->docAppliesTo  = 'all';
        $this->docLevels     = [];
        $this->docOrder      = 0;
        $this->showDocForm   = false;
        $this->editingDocId  = null;
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $documents = RequiredDocument::where('school_id', $schoolId)
            ->orderBy('order')
            ->get();

        $levels = Level::where('school_id', $schoolId)
            ->orderBy('order')
            ->get()
            ->groupBy('cycle');

        return compact('documents', 'levels');
    }
}; ?>

<style>
    .tabs { display:flex; background:var(--paper); border:1px solid var(--line); border-radius:10px; padding:4px; margin-bottom:1.5rem; gap:.25rem; }
    .tab  { display:inline-flex; align-items:center; gap:6px; padding:.45rem 1.1rem; border-radius:7px; font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); border:none; cursor:pointer; background:none; opacity:.55; transition:all .12s; }
    .tab svg { width:15px; height:15px; }
    .tab:hover { opacity:.9; background:var(--paper-raised); }
    .tab.active { background:var(--sidebar); color:#FFFFFF; opacity:1; }

    .card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.25rem; }
    .card:last-child { margin-bottom:0; }
    .card-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .card-title { font-family:'Fraunces',serif; font-size:.95rem; font-weight:600; color:var(--ink); }
    .card-body  { padding:1.25rem 1.5rem; }

    .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
    .form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; margin-bottom:1rem; }
    @media(max-width:700px) { .form-grid-2,.form-grid-3 { grid-template-columns:1fr; } }
    .form-field  { display:flex; flex-direction:column; gap:.35rem; }
    .form-field.full { grid-column:1/-1; }
    .form-label  { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.5; }
    .form-input, .form-select-inp  { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; transition:border-color .15s; }
    .form-input:focus,.form-select-inp:focus { border-color:var(--sidebar-soft); box-shadow:0 0 0 3px rgba(42,63,126,.08); }
    .form-textarea { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; resize:vertical; min-height:80px; }
    .form-textarea:focus { border-color:var(--sidebar-soft); }
    .form-error  { font-size:.75rem; color:var(--accent-red); margin-top:.2rem; }
    .form-hint   { font-size:.75rem; color:var(--ink); opacity:.4; margin-top:2px; }
    .form-actions { display:flex; justify-content:flex-end; gap:.65rem; padding-top:1.25rem; border-top:1px solid var(--line); margin-top:1.25rem; }

    .toggle-row  { display:flex; align-items:center; justify-content:space-between; padding:.875rem 0; border-bottom:1px solid var(--line); }
    .toggle-row:last-child { border-bottom:none; padding-bottom:0; }
    .toggle-label { font-size:.875rem; font-weight:500; color:var(--ink); }
    .toggle-desc  { font-size:.8rem; color:var(--ink); opacity:.5; margin-top:2px; }
    .toggle-switch { position:relative; width:40px; height:22px; cursor:pointer; flex-shrink:0; }
    .toggle-switch input { opacity:0; width:0; height:0; }
    .toggle-slider { position:absolute; inset:0; border-radius:22px; background:var(--line); transition:background .2s; }
    .toggle-slider::before { content:''; position:absolute; width:16px; height:16px; border-radius:50%; background:white; top:3px; left:3px; transition:transform .2s; box-shadow:0 1px 3px rgba(0,0,0,.2); }
    .toggle-switch input:checked + .toggle-slider { background:var(--sidebar-soft); }
    .toggle-switch input:checked + .toggle-slider::before { transform:translateX(18px); }

    .btn-save { display:inline-flex; align-items:center; gap:5px; padding:.5rem 1.25rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; transition:background .15s; }
    .btn-save:hover { background:var(--sidebar-soft); }
    .btn-save svg { width:14px; height:14px; }
    .btn-cancel { padding:.5rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }
    .btn-secondary { display:inline-flex; align-items:center; gap:5px; padding:.45rem .875rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }
    .btn-secondary:hover { border-color:var(--sidebar-soft); color:var(--sidebar-soft); }
    .btn-secondary svg { width:14px; height:14px; }

    /* Documents table */
    table { width:100%; border-collapse:collapse; }
    thead tr { background:var(--paper); border-bottom:1px solid var(--line); }
    thead th { padding:.6rem 1rem; text-align:left; font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.45; }
    thead th:last-child { text-align:right; }
    tbody tr { border-bottom:1px solid var(--line); transition:background .1s; }
    tbody tr:last-child { border-bottom:none; }
    tbody tr:hover { background:rgba(30,45,90,.02); }
    tbody td { padding:.75rem 1rem; font-size:.875rem; color:var(--ink); vertical-align:middle; }
    tbody td:last-child { text-align:right; }

    .doc-name { font-weight:600; }
    .doc-desc { font-size:.8rem; color:var(--ink); opacity:.5; margin-top:1px; }
    .badge { display:inline-block; font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:2px 8px; border-radius:4px; text-transform:uppercase; }
    .badge-required  { background:rgba(224,92,58,.1); color:var(--accent-red); }
    .badge-optional  { background:rgba(42,63,126,.08); color:var(--sidebar-soft); }
    .badge-inactive  { background:rgba(0,0,0,.05); color:var(--ink); opacity:.4; }
    .badge-all       { background:rgba(30,120,80,.08); color:#166534; }
    .badge-new       { background:rgba(232,168,56,.12); color:#8A6010; }
    .badge-reenroll  { background:rgba(99,102,241,.1); color:#3730A3; }

    .actions-cell { display:flex; align-items:center; justify-content:flex-end; gap:.4rem; }
    .btn-action { display:inline-flex; align-items:center; gap:4px; padding:.3rem .65rem; border-radius:6px; font-size:.8rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; transition:background .12s; }
    .btn-action svg { width:13px; height:13px; }
    .btn-edit-act { background:rgba(42,63,126,.08); color:var(--sidebar-soft); }
    .btn-edit-act:hover { background:rgba(42,63,126,.16); }
    .btn-del-act  { background:rgba(224,92,58,.08); color:var(--accent-red); }
    .btn-del-act:hover { background:rgba(224,92,58,.16); }

    /* Levels checkboxes */
    .level-checks { display:flex; flex-wrap:wrap; gap:.5rem; }
    .level-check-row { display:flex; align-items:center; gap:.35rem; }
    .level-checkbox { width:14px; height:14px; border-radius:3px; border:1.5px solid var(--line); appearance:none; cursor:pointer; position:relative; transition:all .12s; }
    .level-checkbox:checked { background:var(--sidebar); border-color:var(--sidebar); }
    .level-checkbox:checked::after { content:''; position:absolute; top:1px; left:3.5px; width:4px; height:7px; border:2px solid #FFF; border-top:none; border-left:none; transform:rotate(45deg); }
    .level-check-label { font-size:.8rem; color:var(--ink); cursor:pointer; }
    .level-group-label { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; color:var(--ink); opacity:.4; margin-bottom:.3rem; }

    /* Enrollment status banner */
    .enrollment-banner { display:flex; align-items:center; gap:.75rem; padding:.875rem 1.25rem; border-radius:10px; margin-bottom:1.25rem; }
    .eb-open   { background:rgba(30,120,80,.08); border:1px solid rgba(30,120,80,.2); }
    .eb-closed { background:rgba(224,92,58,.08); border:1px solid rgba(224,92,58,.2); }
    .eb-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
    .eb-open .eb-dot   { background:#22c55e; }
    .eb-closed .eb-dot { background:var(--accent-red); }
    .eb-text { font-size:.875rem; font-weight:500; }
    .eb-open .eb-text   { color:#166534; }
    .eb-closed .eb-text { color:var(--accent-red); }

    /* Form card */
    .form-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.25rem; animation:slideDown .15s ease; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-6px);} to{opacity:1;transform:translateY(0);} }
    .form-card-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .form-card-title { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); }
    .form-card-body  { padding:1.25rem 1.5rem; }

    /* Toast */
    .toast { display:flex; align-items:center; gap:.6rem; padding:.65rem 1rem; border-radius:8px; font-size:.875rem; font-weight:500; margin-bottom:1rem; animation:slideDown .15s ease; }
    .toast-ok { background:rgba(30,120,80,.1); border:1px solid rgba(30,120,80,.2); color:#166534; }
    .toast svg { width:15px; height:15px; flex-shrink:0; }

    /* Modal */
    .modal-overlay { position:fixed; inset:0; z-index:100; background:rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; padding:1rem; }
    .modal { background:var(--paper-raised); border-radius:14px; border:1px solid var(--line); padding:1.75rem; max-width:380px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.2); }
    .modal-title { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; margin-bottom:.5rem; }
    .modal-desc  { font-size:.875rem; color:var(--ink); opacity:.6; margin-bottom:1.25rem; line-height:1.5; }
    .modal-actions { display:flex; justify-content:flex-end; gap:.65rem; }
    .btn-modal-cancel  { padding:.45rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }
    .btn-modal-confirm { padding:.45rem 1rem; border-radius:8px; border:none; background:var(--accent-red); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; cursor:pointer; }
</style>

<div>
    {{-- Tabs --}}
    <div class="tabs">
        <button type="button" wire:click="$set('activeTab','policy')"
                class="tab {{ $activeTab==='policy' ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            Politique d'admission
        </button>
        <button type="button" wire:click="$set('activeTab','documents')"
                class="tab {{ $activeTab==='documents' ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
            Pièces à fournir
            <span style="font-family:'JetBrains Mono',monospace;font-size:10px;background:rgba(42,63,126,.1);color:var(--sidebar-soft);padding:1px 6px;border-radius:4px;">{{ $documents->count() }}</span>
        </button>
    </div>

    @if ($savedPolicy || $savedDoc)
        <div class="toast toast-ok" x-data x-init="setTimeout(() => $el.remove(), 3000)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $savedPolicy ? 'Politique d\'admission enregistrée.' : 'Document enregistré.' }}
        </div>
    @endif

    {{-- ══ POLITIQUE D'ADMISSION ══ --}}
    @if ($activeTab === 'policy')

        {{-- Statut ouverture --}}
        <div class="enrollment-banner {{ $is_enrollment_open ? 'eb-open' : 'eb-closed' }}">
            <div class="eb-dot"></div>
            <div>
                <div class="eb-text">
                    Les inscriptions sont {{ $is_enrollment_open ? 'ouvertes' : 'fermées' }}.
                </div>
                @if ($enrollment_open_from || $enrollment_open_until)
                    <div style="font-size:.8rem;color:var(--ink);opacity:.5;margin-top:2px;">
                        @if ($enrollment_open_from) Du {{ \Carbon\Carbon::parse($enrollment_open_from)->format('d/m/Y') }} @endif
                        @if ($enrollment_open_until) au {{ \Carbon\Carbon::parse($enrollment_open_until)->format('d/m/Y') }} @endif
                    </div>
                @endif
            </div>
            <label class="toggle-switch" style="margin-left:auto;">
                <input type="checkbox" wire:model.live="is_enrollment_open">
                <span class="toggle-slider"></span>
            </label>
        </div>

        {{-- Période d'inscription --}}
        <div class="card">
            <div class="card-header"><span class="card-title">Période d'inscription</span></div>
            <div class="card-body">
                <div class="form-grid-2">
                    <div class="form-field">
                        <label class="form-label">Ouverture des inscriptions</label>
                        <input wire:model="enrollment_open_from" type="date" class="form-input">
                        <span class="form-hint">Laisser vide = pas de restriction de date</span>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Clôture des inscriptions</label>
                        <input wire:model="enrollment_open_until" type="date" class="form-input">
                        @error('enrollment_open_until') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Critères d'âge --}}
        <div class="card">
            <div class="card-header"><span class="card-title">Critères d'âge</span></div>
            <div class="card-body">
                <div class="form-grid-2">
                    <div class="form-field">
                        <label class="form-label">Âge minimum (années)</label>
                        <input wire:model="min_age_years" type="number" min="2" max="25" class="form-input" placeholder="Ex: 3">
                        <span class="form-hint">Laisser vide = pas de restriction</span>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Âge maximum (années)</label>
                        <input wire:model="max_age_years" type="number" min="2" max="25" class="form-input" placeholder="Ex: 18">
                    </div>
                </div>
            </div>
        </div>

        {{-- Options --}}
        <div class="card">
            <div class="card-header"><span class="card-title">Règles d'admission</span></div>
            <div class="card-body">
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">Respecter la capacité maximale des classes</div>
                        <div class="toggle-desc">Bloquer l'inscription si la classe est complète.</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" wire:model="enforce_capacity">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">Priorité fratrie</div>
                        <div class="toggle-desc">Les enfants ayant un frère/sœur déjà inscrit sont prioritaires.</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" wire:model="priority_siblings">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">Priorité enfants du personnel</div>
                        <div class="toggle-desc">Les enfants des membres du personnel sont prioritaires.</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" wire:model="priority_staff_children">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                <div class="toggle-row">
                    <div>
                        <div class="toggle-label">Test d'entrée requis</div>
                        <div class="toggle-desc">Un examen d'admission est obligatoire avant l'inscription.</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" wire:model="requires_entrance_test">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
                @if ($requires_entrance_test)
                    <div class="form-field" style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--line);">
                        <label class="form-label">Description du test d'entrée</label>
                        <textarea wire:model="entrance_test_description" class="form-textarea"
                                  placeholder="Matières évaluées, modalités, durée..."></textarea>
                    </div>
                @endif
            </div>
        </div>

        {{-- Conditions générales --}}
        <div class="card">
            <div class="card-header"><span class="card-title">Conditions d'admission</span></div>
            <div class="card-body">
                <div class="form-field">
                    <label class="form-label">Texte affiché lors de l'inscription</label>
                    <textarea wire:model="admission_conditions" class="form-textarea" rows="5"
                              placeholder="Ex: L'inscription est définitive après paiement des frais d'inscription. Tout dossier incomplet sera rejeté..."></textarea>
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;">
            <button wire:click="savePolicy" class="btn-save">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                Enregistrer la politique
            </button>
        </div>

    @endif

    {{-- ══ PIÈCES À FOURNIR ══ --}}
    @if ($activeTab === 'documents')

        <div style="display:flex;justify-content:flex-end;margin-bottom:1rem;">
            <button wire:click="openCreateDoc" class="btn-secondary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Nouveau document requis
            </button>
        </div>

        {{-- Formulaire document --}}
        @if ($showDocForm)
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-title">{{ $editingDocId ? 'Modifier le document' : 'Nouveau document requis' }}</span>
                    <button wire:click="$set('showDocForm',false)" style="background:none;border:none;cursor:pointer;opacity:.4;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="form-card-body">
                    <div class="form-grid-2" style="margin-bottom:1rem;">
                        <div class="form-field">
                            <label class="form-label">Nom du document *</label>
                            <input wire:model="docName" type="text" class="form-input"
                                   placeholder="Ex: Acte de naissance, Certificat médical...">
                            @error('docName') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field">
                            <label class="form-label">Obligatoire / Optionnel</label>
                            <div style="display:flex;gap:.5rem;margin-top:.35rem;">
                                <button type="button"
                                        wire:click="$set('docMandatory',true)"
                                        style="flex:1;padding:.45rem;border-radius:7px;border:1.5px solid {{ $docMandatory ? 'var(--accent-red)' : 'var(--line)' }};background:{{ $docMandatory ? 'rgba(224,92,58,.08)' : 'var(--paper)' }};color:{{ $docMandatory ? 'var(--accent-red)' : 'var(--ink)' }};font-size:.8125rem;font-weight:600;cursor:pointer;">
                                    Obligatoire
                                </button>
                                <button type="button"
                                        wire:click="$set('docMandatory',false)"
                                        style="flex:1;padding:.45rem;border-radius:7px;border:1.5px solid {{ !$docMandatory ? 'var(--sidebar-soft)' : 'var(--line)' }};background:{{ !$docMandatory ? 'rgba(42,63,126,.08)' : 'var(--paper)' }};color:{{ !$docMandatory ? 'var(--sidebar-soft)' : 'var(--ink)' }};font-size:.8125rem;font-weight:600;cursor:pointer;">
                                    Optionnel
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid-3" style="margin-bottom:1rem;">
                        <div class="form-field">
                            <label class="form-label">S'applique à</label>
                            <select wire:model="docAppliesTo" class="form-select-inp">
                                <option value="all">Tous (inscription + réinscription)</option>
                                <option value="new">Nouvelle inscription uniquement</option>
                                <option value="reenroll">Réinscription uniquement</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Ordre d'affichage</label>
                            <input wire:model="docOrder" type="number" min="0" class="form-input" placeholder="0">
                        </div>
                    </div>

                    <div class="form-field" style="margin-bottom:1rem;">
                        <label class="form-label">Description / Précisions</label>
                        <textarea wire:model="docDescription" class="form-textarea" rows="2"
                                  placeholder="Ex: Document original + photocopie, moins de 3 mois..."></textarea>
                    </div>

                    {{-- Niveaux concernés --}}
                    <div class="form-field">
                        <label class="form-label">Niveaux concernés (vide = tous)</label>
                        @foreach ($levels as $cycle => $cycleLevels)
                            <div style="margin-bottom:.5rem;">
                                <div class="level-group-label">{{ $cycle }}</div>
                                <div class="level-checks">
                                    @foreach ($cycleLevels as $level)
                                        <label class="level-check-row">
                                            <input type="checkbox"
                                                   wire:model="docLevels"
                                                   value="{{ $level->id }}"
                                                   class="level-checkbox">
                                            <span class="level-check-label">{{ $level->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="form-actions">
                        <button wire:click="$set('showDocForm',false)" class="btn-cancel">Annuler</button>
                        <button wire:click="saveDoc" class="btn-save">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            {{ $editingDocId ? 'Enregistrer' : 'Créer' }}
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Liste des documents --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">Documents requis configurés</span>
                <span style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--ink);opacity:.4;">{{ $documents->count() }} documents</span>
            </div>
            @if ($documents->isEmpty())
                <div style="padding:3rem;text-align:center;font-size:.875rem;color:var(--ink);opacity:.4;">
                    Aucun document configuré. Ajoutez les pièces requises lors de l'inscription.
                </div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Document</th>
                            <th>Type</th>
                            <th>S'applique à</th>
                            <th>Niveaux</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $doc)
                            <tr>
                                <td style="font-family:'JetBrains Mono',monospace;font-size:11px;opacity:.4;">{{ $doc->order }}</td>
                                <td>
                                    <div class="doc-name">{{ $doc->name }}</div>
                                    @if ($doc->description)
                                        <div class="doc-desc">{{ $doc->description }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $doc->is_mandatory ? 'badge-required' : 'badge-optional' }}">
                                        {{ $doc->is_mandatory ? 'Obligatoire' : 'Optionnel' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-{{ $doc->applies_to }}">
                                        {{ match($doc->applies_to) { 'all'=>'Tous','new'=>'Inscription','reenroll'=>'Réinscription',default=>'Tous' } }}
                                    </span>
                                </td>
                                <td style="font-size:.8rem;color:var(--ink);opacity:.5;">
                                    {{ empty($doc->applies_to_levels) ? 'Tous les niveaux' : count($doc->applies_to_levels).' niveau(x)' }}
                                </td>
                                <td>
                                    <span class="badge {{ $doc->is_active ? 'badge-all' : 'badge-inactive' }}">
                                        {{ $doc->is_active ? 'Actif' : 'Inactif' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <button wire:click="toggleDoc({{ $doc->id }})" class="btn-action btn-edit-act" title="{{ $doc->is_active ? 'Désactiver' : 'Activer' }}">
                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $doc->is_active ? 'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21' : 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z' }}"/></svg>
                                        </button>
                                        <button wire:click="openEditDoc({{ $doc->id }})" class="btn-action btn-edit-act">
                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button wire:click="confirmDeleteDoc({{ $doc->id }})" class="btn-action btn-del-act">
                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16"/></svg>
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

    {{-- Modal suppression --}}
    @if ($confirmDeleteDocId)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Supprimer ce document ?</div>
                <div class="modal-desc">Ce document ne sera plus demandé lors des inscriptions. Les documents déjà fournis par les élèves ne seront pas supprimés.</div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeleteDocId',null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deleteDoc" class="btn-modal-confirm">Supprimer</button>
                </div>
            </div>
        </div>
    @endif
</div>