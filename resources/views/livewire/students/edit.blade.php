<?php

use App\Models\Student;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use App\Models\StudentSchoolYear;
use App\Services\AcademicYearService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    public Student $student;

    // Infos personnelles
    public string $first_name  = '';
    public string $last_name   = '';
    public string $matricule   = '';
    public string $birth_date  = '';
    public string $birth_place = '';
    public string $gender      = '';
    public string $status      = '';

    // Scolarité
    public string $school_class_id = '';

    // Tuteur
    public string $guardian_id = '';

    public bool $saved = false;

    public function mount(Student $student): void
    {
        $this->student = $student;

        $this->first_name  = $student->first_name;
        $this->last_name   = $student->last_name;
        $this->matricule   = $student->matricule;
        $this->birth_date  = $student->birth_date?->format('Y-m-d') ?? '';
        $this->birth_place = $student->birth_place ?? '';
        $this->gender      = $student->gender ?? '';
        $this->status      = $student->status;

        $year = AcademicYearService::current();
        $schoolYear = $student->schoolYears()
            ->where('academic_year_id', $year?->id)
            ->first();
        $this->school_class_id = (string) ($schoolYear?->school_class_id ?? '');

        $primaryGuardian = $student->guardians()->wherePivot('is_primary_contact', true)->first();
        $this->guardian_id = (string) ($primaryGuardian?->id ?? '');
    }

    public function save(): void
    {
        $this->validate([
            'first_name'  => 'required|string|max:100',
            'last_name'   => 'required|string|max:100',
            'matricule'   => 'required|string|max:50',
            'birth_date'  => 'nullable|date',
            'birth_place' => 'nullable|string|max:100',
            'gender'      => 'nullable|in:M,F',
            'status'      => 'required|in:active,transferred,graduated,dropped',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'guardian_id'     => 'nullable|exists:guardians,id',
        ]);

        $this->student->update([
            'first_name'  => $this->first_name,
            'last_name'   => $this->last_name,
            'matricule'   => $this->matricule,
            'birth_date'  => $this->birth_date ?: null,
            'birth_place' => $this->birth_place ?: null,
            'gender'      => $this->gender ?: null,
            'status'      => $this->status,
        ]);

        // Mise à jour classe dans l'année active
        $year = AcademicYearService::current();
        if ($year && $this->school_class_id) {
            StudentSchoolYear::updateOrCreate(
                ['student_id' => $this->student->id, 'academic_year_id' => $year->id],
                ['school_class_id' => $this->school_class_id]
            );
        }

        // Mise à jour tuteur principal
        if ($this->guardian_id) {
            $this->student->guardians()->updateExistingPivot($this->guardian_id, ['is_primary_contact' => true]);
            $this->student->guardians()
                ->where('guardian_id', '!=', $this->guardian_id)
                ->updateExistingPivot($this->guardian_id, ['is_primary_contact' => false]);
        }

        $this->saved = true;
    }

    public function with(): array
    {
        $year = AcademicYearService::current();

        $classes = $year
            ? SchoolClass::where('academic_year_id', $year->id)->with('level')->get()
            : collect();

        $guardians = Guardian::orderBy('last_name')->get();

        return compact('classes', 'guardians', 'year');
    }
}; ?>

<style>
    /* ── Breadcrumb ── */
    .breadcrumb {
        display: flex; align-items: center; gap: 0.5rem;
        font-size: 0.8125rem; margin-bottom: 1.5rem;
        color: var(--ink); opacity: 0.5;
    }
    .breadcrumb a { color: inherit; text-decoration: none; }
    .breadcrumb a:hover { opacity: 1; color: var(--sidebar-soft); }
    .breadcrumb svg { width: 14px; height: 14px; }
    .breadcrumb-current { opacity: 1; font-weight: 600; color: var(--ink); }

    /* ── Layout 2 colonnes ── */
    .edit-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
        align-items: start;
    }
    @media (max-width: 900px) { .edit-grid { grid-template-columns: 1fr; } }

    /* ── Cards ── */
    .card {
        border-radius: 12px;
        border: 1px solid var(--line);
        background: var(--paper-raised);
        overflow: hidden;
        margin-bottom: 1.25rem;
    }
    .card:last-child { margin-bottom: 0; }
    .card-header {
        padding: 0.875rem 1.5rem;
        border-bottom: 1px solid var(--line);
        display: flex; align-items: center; gap: 0.6rem;
    }
    .card-header-icon {
        width: 28px; height: 28px; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .card-header-icon svg { width: 15px; height: 15px; }
    .card-title {
        font-family: 'Fraunces', serif;
        font-size: 1rem; font-weight: 600; color: var(--ink);
    }
    .card-body { padding: 1.25rem 1.5rem; }

    /* ── Formulaire ── */
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .form-row.single { grid-template-columns: 1fr; }
    .form-row.triple { grid-template-columns: 1fr 1fr 1fr; }
    @media (max-width: 600px) { .form-row, .form-row.triple { grid-template-columns: 1fr; } }

    .form-field { display: flex; flex-direction: column; gap: 0.35rem; }
    .form-label {
        font-family: 'JetBrains Mono', monospace;
        font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.08em;
        color: var(--ink); opacity: 0.5;
    }
    .form-input, .form-select {
        padding: 0.5rem 0.75rem;
        border-radius: 8px; border: 1px solid var(--line);
        background: var(--paper);
        font-size: 0.875rem; font-family: 'Inter', sans-serif;
        color: var(--ink); outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
        width: 100%;
    }
    .form-input:focus, .form-select:focus {
        border-color: var(--sidebar-soft);
        box-shadow: 0 0 0 3px rgba(42,63,126,0.08);
    }
    .form-error {
        font-size: 0.75rem; color: var(--accent-red);
        margin-top: 0.2rem;
    }

    /* Radio genre */
    .radio-group { display: flex; gap: 0.5rem; }
    .radio-btn {
        flex: 1; padding: 0.45rem 0.5rem;
        border-radius: 7px; border: 1.5px solid var(--line);
        background: var(--paper);
        font-size: 0.8125rem; font-weight: 500; font-family: 'Inter', sans-serif;
        color: var(--ink); cursor: pointer; text-align: center;
        transition: border-color 0.12s, background 0.12s, color 0.12s;
        appearance: none;
    }
    .radio-btn.selected-m {
        border-color: var(--sidebar); background: rgba(30,45,90,0.07); color: var(--sidebar);
    }
    .radio-btn.selected-f {
        border-color: #B0307A; background: rgba(176,48,122,0.07); color: #B0307A;
    }

    /* ── Profil card (colonne droite) ── */
    .profile-card {
        border-radius: 12px; border: 1px solid var(--line);
        background: var(--paper-raised); overflow: hidden;
        margin-bottom: 1.25rem;
    }
    .profile-avatar-wrap {
        padding: 2rem 1.5rem 1.25rem;
        display: flex; flex-direction: column; align-items: center;
        border-bottom: 1px solid var(--line);
        background: var(--paper);
    }
    .profile-avatar {
        width: 72px; height: 72px; border-radius: 50%;
        background: rgba(42,63,126,0.1); color: var(--sidebar-soft);
        font-family: 'JetBrains Mono', monospace;
        font-size: 22px; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 0.75rem;
    }
    .profile-name {
        font-family: 'Fraunces', serif;
        font-size: 1.1rem; font-weight: 600; color: var(--ink);
        text-align: center;
    }
    .profile-matric {
        font-family: 'JetBrains Mono', monospace;
        font-size: 11px; color: var(--ink); opacity: 0.4;
        margin-top: 2px; text-align: center;
    }
    .profile-meta { padding: 1rem 1.5rem; }
    .meta-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid var(--line);
        font-size: 0.8125rem;
    }
    .meta-row:last-child { border-bottom: none; }
    .meta-label { color: var(--ink); opacity: 0.45; font-size: 0.75rem; }
    .meta-value { font-weight: 500; }

    /* Badges statut */
    .badge {
        display: inline-block;
        font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600;
        padding: 2px 8px; border-radius: 4px;
        text-transform: uppercase; letter-spacing: 0.05em;
    }
    .badge-active      { background: rgba(42,63,126,0.1);  color: var(--sidebar-soft); }
    .badge-transferred { background: rgba(232,168,56,0.15); color: #8A6010; }
    .badge-dropped     { background: rgba(224,92,58,0.12);  color: #C04020; }
    .badge-graduated   { background: rgba(30,120,80,0.12);  color: #1A6040; }

    /* ── Boutons ── */
    .form-actions {
        display: flex; align-items: center; justify-content: flex-end;
        gap: 0.65rem; padding-top: 1rem;
        border-top: 1px solid var(--line); margin-top: 1.25rem;
    }
    .btn-cancel {
        padding: 0.5rem 1.1rem; border-radius: 8px;
        border: 1px solid var(--line); background: var(--paper);
        font-size: 0.875rem; font-weight: 500; font-family: 'Inter', sans-serif;
        color: var(--ink); cursor: pointer; text-decoration: none;
        transition: border-color 0.15s;
    }
    .btn-cancel:hover { border-color: var(--ink); }
    .btn-save {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 0.5rem 1.25rem; border-radius: 8px;
        background: var(--sidebar); color: #FFFFFF;
        font-size: 0.875rem; font-weight: 600; font-family: 'Inter', sans-serif;
        border: none; cursor: pointer;
        transition: background 0.15s;
    }
    .btn-save:hover { background: var(--sidebar-soft); }
    .btn-save svg { width: 15px; height: 15px; }

    /* ── Toast succès ── */
    .toast-success {
        display: flex; align-items: center; gap: 0.65rem;
        padding: 0.75rem 1.1rem; border-radius: 10px;
        background: rgba(30,120,80,0.1); border: 1px solid rgba(30,120,80,0.2);
        color: #1A6040; font-size: 0.875rem; font-weight: 500;
        margin-bottom: 1.25rem;
        animation: slideDown 0.2s ease;
    }
    .toast-success svg { width: 18px; height: 18px; flex-shrink: 0; }
    @keyframes slideDown { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }

    /* Danger zone */
    .danger-zone {
        border-radius: 12px;
        border: 1px solid rgba(224,92,58,0.25);
        background: rgba(224,92,58,0.04);
        overflow: hidden;
    }
    .danger-header {
        padding: 0.875rem 1.5rem;
        border-bottom: 1px solid rgba(224,92,58,0.15);
        font-family: 'JetBrains Mono', monospace;
        font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.08em;
        color: var(--accent-red);
    }
    .danger-body { padding: 1rem 1.5rem; }
    .danger-desc { font-size: 0.8125rem; color: var(--ink); opacity: 0.6; margin-bottom: 0.875rem; }
    .btn-danger {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 0.45rem 1rem; border-radius: 8px;
        border: 1px solid rgba(224,92,58,0.4);
        background: transparent; color: var(--accent-red);
        font-size: 0.8125rem; font-weight: 600; font-family: 'Inter', sans-serif;
        cursor: pointer; transition: background 0.15s;
    }
    .btn-danger:hover { background: rgba(224,92,58,0.08); }
    .btn-danger svg { width: 14px; height: 14px; }
</style>

<div>

    {{-- Breadcrumb --}}
    <div class="breadcrumb">
        <a href="{{ route('students.index') }}">Elèves</a>
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="breadcrumb-current">{{ $student->fullName() }}</span>
    </div>

    {{-- Toast succès --}}
    @if ($saved)
        <div class="toast-success" x-data x-init="setTimeout(() => $el.remove(), 3500)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Modifications enregistrées avec succès.
        </div>
    @endif

    <div class="edit-grid">

        {{-- Colonne gauche : formulaires --}}
        <div>

            {{-- Informations personnelles --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-header-icon" style="background:rgba(42,63,126,0.1); color:var(--sidebar-soft);">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <span class="card-title">Informations personnelles</span>
                </div>
                <div class="card-body">

                    <div class="form-row">
                        <div class="form-field">
                            <label class="form-label">Prénom</label>
                            <input wire:model="first_name" type="text" class="form-input" placeholder="Prénom">
                            @error('first_name') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field">
                            <label class="form-label">Nom</label>
                            <input wire:model="last_name" type="text" class="form-input" placeholder="Nom de famille">
                            @error('last_name') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field">
                            <label class="form-label">Matricule</label>
                            <input wire:model="matricule" type="text" class="form-input" placeholder="ELV-001">
                            @error('matricule') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field">
                            <label class="form-label">Genre</label>
                            <div class="radio-group">
                                <button type="button"
                                    wire:click="$set('gender', 'M')"
                                    class="radio-btn {{ $gender === 'M' ? 'selected-m' : '' }}">
                                    Masculin
                                </button>
                                <button type="button"
                                    wire:click="$set('gender', 'F')"
                                    class="radio-btn {{ $gender === 'F' ? 'selected-f' : '' }}">
                                    Féminin
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field">
                            <label class="form-label">Date de naissance</label>
                            <input wire:model="birth_date" type="date" class="form-input">
                            @error('birth_date') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field">
                            <label class="form-label">Lieu de naissance</label>
                            <input wire:model="birth_place" type="text" class="form-input" placeholder="Djibouti">
                            @error('birth_place') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                </div>
            </div>

            {{-- Scolarité --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-header-icon" style="background:rgba(232,168,56,0.12); color:#8A6010;">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                        </svg>
                    </div>
                    <span class="card-title">Scolarité</span>
                </div>
                <div class="card-body">

                    <div class="form-row">
                        <div class="form-field">
                            <label class="form-label">Classe (année active)</label>
                            <select wire:model="school_class_id" class="form-select">
                                <option value="">— Sélectionner une classe —</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Statut</label>
                            <select wire:model="status" class="form-select">
                                <option value="active">Inscrit</option>
                                <option value="transferred">Transféré</option>
                                <option value="graduated">Diplômé</option>
                                <option value="dropped">Abandonné</option>
                            </select>
                            @error('status') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="form-row single">
                        <div class="form-field">
                            <label class="form-label">Tuteur principal</label>
                            <select wire:model="guardian_id" class="form-select">
                                <option value="">— Sélectionner un tuteur —</option>
                                @foreach ($guardians as $guardian)
                                    <option value="{{ $guardian->id }}">{{ $guardian->fullName() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('students.index') }}" class="btn-cancel">Annuler</a>
                        <button wire:click="save" class="btn-save">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                            </svg>
                            Enregistrer
                        </button>
                    </div>

                </div>
            </div>

        </div>

        {{-- Colonne droite : profil + danger zone --}}
        <div>

            {{-- Carte profil --}}
            <div class="profile-card">
                <div class="profile-avatar-wrap">
                    <div class="profile-avatar">
                        {{ strtoupper(substr($student->first_name,0,1).substr($student->last_name,0,1)) }}
                    </div>
                    <div class="profile-name">{{ $first_name }} {{ $last_name }}</div>
                    <div class="profile-matric">{{ $student->matricule }}</div>
                </div>
                <div class="profile-meta">
                    <div class="meta-row">
                        <span class="meta-label">Statut</span>
                        @php
                            $badgeClass = match($status) {
                                'active'      => 'badge-active',
                                'transferred' => 'badge-transferred',
                                'graduated'   => 'badge-graduated',
                                'dropped'     => 'badge-dropped',
                                default       => 'badge-active',
                            };
                            $statusLabel = match($status) {
                                'active'      => 'Inscrit',
                                'transferred' => 'Transféré',
                                'graduated'   => 'Diplômé',
                                'dropped'     => 'Abandonné',
                                default       => $status,
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Genre</span>
                        <span class="meta-value">{{ $gender === 'M' ? 'Masculin' : ($gender === 'F' ? 'Féminin' : '—') }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Naissance</span>
                        <span class="meta-value">{{ $birth_date ? \Carbon\Carbon::parse($birth_date)->format('d/m/Y') : '—' }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Inscrit le</span>
                        <span class="meta-value">{{ $student->created_at->format('d/m/Y') }}</span>
                    </div>
                </div>
            </div>

            {{-- Danger zone --}}
            <div class="danger-zone">
                <div class="danger-header">Zone de danger</div>
                <div class="danger-body">
                    <p class="danger-desc">La suppression d'un élève est irréversible et effacera toutes ses données (notes, présences, bulletins).</p>
                    <button class="btn-danger">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Supprimer cet élève
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>