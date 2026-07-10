<?php

use App\Models\ClassSubjectTeacher;
use App\Models\SchoolClass;
use App\Models\Staff;
use App\Models\Subject;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Services\AcademicYearService;

new class extends Component
{
    use WithFileUploads;

    public Staff $staff;

    // Profil
    public string $name       = '';
    public string $email      = '';
    public string $phone      = '';
    public string $position   = '';
    public string $hired_at   = '';
    public string $matricule  = '';
    public $photo             = null;
    public string $bio        = '';

    // Mot de passe
    public string $newPassword        = '';
    public string $newPasswordConfirm = '';
    public bool   $showPassword       = false;

    public bool   $savedProfile  = false;
    public bool   $savedPassword = false;
    public ?string $passwordError = null;

    public function mount(Staff $staff): void
    {
        $this->staff      = $staff;
        $this->name       = $staff->user->name;
        $this->email      = $staff->user->email;
        $this->phone      = $staff->phone ?? '';
        $this->position   = $staff->position ?? '';
        $this->hired_at   = $staff->hired_at?->format('Y-m-d') ?? '';
        $this->matricule  = $staff->matricule ?? '';
        $this->bio        = $staff->bio ?? '';
    }

    public function saveProfile(): void
    {
        $this->validate([
            'name'      => 'required|string|max:200',
            'email'     => 'required|email|unique:users,email,'.$this->staff->user_id,
            'position'  => 'required|string|max:100',
            'hired_at'  => 'nullable|date',
            'photo'     => 'nullable|image|max:2048',
        ]);

        $this->staff->user->update([
            'name'  => $this->name,
            'email' => $this->email,
        ]);

        $data = [
            'phone'    => $this->phone ?: null,
            'position' => $this->position,
            'hired_at' => $this->hired_at ?: null,
            'bio'      => $this->bio ?: null,
        ];

        if ($this->photo) {
            if ($this->staff->photo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($this->staff->photo_path);
            }
            $data['photo_path'] = $this->photo->store('staff/photos', 'public');
            $this->photo = null;
        }

        $this->staff->update($data);
        $this->savedProfile = true;
    }

    public function savePassword(): void
    {
        $this->passwordError = null;

        if (strlen($this->newPassword) < 8) {
            $this->passwordError = 'Le mot de passe doit contenir au moins 8 caractères.';
            return;
        }

        if ($this->newPassword !== $this->newPasswordConfirm) {
            $this->passwordError = 'Les deux mots de passe ne correspondent pas.';
            return;
        }

        $this->staff->user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($this->newPassword),
        ]);

        $this->newPassword        = '';
        $this->newPasswordConfirm = '';
        $this->savedPassword      = true;
    }

    public function with(): array
    {
        $year     = AcademicYearService::current();
        $schoolId = auth()->user()->school_id;

        // Affectations matières / classes de ce membre
        $assignments = ClassSubjectTeacher::where('staff_id', $this->staff->id)
            ->with(['schoolClass.level', 'subject'])
            ->get();

        // Classes dont il est prof. principal
        $mainClasses = SchoolClass::where('school_id', $schoolId)
            ->where('main_teacher_id', $this->staff->id)
            ->with('level')
            ->get();

        // Statistiques
        $totalClasses   = $assignments->pluck('school_class_id')->unique()->count();
        $totalSubjects  = $assignments->pluck('subject_id')->unique()->count();

        return compact(
            'year', 'assignments', 'mainClasses',
            'totalClasses', 'totalSubjects'
        );
    }
}; ?>

<style>
    .bc { display:flex; align-items:center; gap:.5rem; font-size:.8125rem; margin-bottom:1.25rem; color:var(--ink); opacity:.5; }
    .bc a { color:inherit; text-decoration:none; } .bc a:hover { color:var(--sidebar-soft); opacity:1; }
    .bc svg { width:14px; height:14px; }
    .bc-cur { opacity:1; font-weight:600; color:var(--ink); }

    .page-grid { display:grid; grid-template-columns:1fr 280px; gap:1.5rem; align-items:start; }
    @media(max-width:900px) { .page-grid { grid-template-columns:1fr; } }

    /* Card */
    .card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.25rem; }
    .card:last-child { margin-bottom:0; }
    .card-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; gap:.65rem; }
    .card-header-icon { width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .card-header-icon svg { width:15px; height:15px; }
    .card-title { font-family:'Fraunces',serif; font-size:.95rem; font-weight:600; color:var(--ink); }
    .card-body { padding:1.25rem 1.5rem; }

    /* Formulaire */
    .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
    .form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; margin-bottom:1rem; }
    @media(max-width:700px) { .form-grid-2,.form-grid-3 { grid-template-columns:1fr; } }
    .form-field { display:flex; flex-direction:column; gap:.35rem; }
    .form-field.full { grid-column:1/-1; }
    .form-label { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.5; }
    .form-input, .form-select-inp { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; transition:border-color .15s; }
    .form-input:focus, .form-select-inp:focus { border-color:var(--sidebar-soft); }
    .form-textarea { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; resize:vertical; min-height:70px; }
    .form-textarea:focus { border-color:var(--sidebar-soft); }
    .form-error { font-size:.75rem; color:var(--accent-red); margin-top:.2rem; }
    .form-actions { display:flex; justify-content:flex-end; gap:.65rem; padding-top:1.25rem; border-top:1px solid var(--line); margin-top:1.25rem; }

    .btn-save { display:inline-flex; align-items:center; gap:5px; padding:.5rem 1.25rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
    .btn-save svg { width:14px; height:14px; }

    /* Toast */
    .toast { display:flex; align-items:center; gap:.6rem; padding:.65rem 1rem; border-radius:8px; font-size:.875rem; font-weight:500; margin-bottom:1rem; animation:slideDown .15s ease; }
    .toast-ok { background:rgba(30,120,80,.1); border:1px solid rgba(30,120,80,.2); color:#166534; }
    .toast-err { background:rgba(224,92,58,.08); border:1px solid rgba(224,92,58,.2); color:var(--accent-red); }
    .toast svg { width:15px; height:15px; flex-shrink:0; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-4px);} to{opacity:1;transform:translateY(0);} }

    /* Photo */
    .photo-wrap { display:flex; align-items:center; gap:1rem; margin-bottom:1rem; }
    .photo-circle { width:64px; height:64px; border-radius:50%; overflow:hidden; border:2px solid var(--line); flex-shrink:0; display:flex; align-items:center; justify-content:center; background:rgba(42,63,126,.1); color:var(--sidebar-soft); font-family:'JetBrains Mono',monospace; font-size:18px; font-weight:700; }
    .photo-circle img { width:100%; height:100%; object-fit:cover; }
    .photo-upload-btn { display:inline-flex; align-items:center; gap:4px; padding:.4rem .8rem; border-radius:7px; border:1px solid var(--line); background:var(--paper); font-size:.8125rem; font-family:'Inter',sans-serif; cursor:pointer; position:relative; color:var(--ink); }
    .photo-upload-btn input { position:absolute; inset:0; opacity:0; cursor:pointer; }

    /* Password */
    .pw-wrap { position:relative; }
    .pw-toggle { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--ink); opacity:.4; }
    .pw-toggle:hover { opacity:.8; }
    .pw-toggle svg { width:15px; height:15px; }

    /* Affectations */
    .assign-row { display:flex; align-items:center; gap:.75rem; padding:.65rem 0; border-bottom:1px solid var(--line); }
    .assign-row:last-child { border-bottom:none; }
    .assign-subj-dot { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
    .assign-subj { font-weight:600; font-size:.875rem; }
    .assign-class { font-size:.8rem; color:var(--ink); opacity:.5; margin-top:1px; }
    .assign-badge { margin-left:auto; font-family:'JetBrains Mono',monospace; font-size:10px; padding:2px 7px; border-radius:4px; background:rgba(42,63,126,.08); color:var(--sidebar-soft); white-space:nowrap; }

    /* Sidebar */
    .side-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1rem; }
    .side-card:last-child { margin-bottom:0; }
    .side-header { padding:.75rem 1rem; border-bottom:1px solid var(--line); font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; }
    .side-body { padding:.875rem 1rem; }
    .side-row { display:flex; justify-content:space-between; align-items:center; padding:.4rem 0; border-bottom:1px solid var(--line); font-size:.8125rem; }
    .side-row:last-child { border-bottom:none; }
    .side-label { color:var(--ink); opacity:.55; }
    .side-value { font-weight:600; color:var(--ink); }

    /* Avatar large sidebar */
    .profile-avatar-large { width:80px; height:80px; border-radius:50%; margin:0 auto 1rem; display:flex; align-items:center; justify-content:center; background:rgba(42,63,126,.1); color:var(--sidebar-soft); font-family:'JetBrains Mono',monospace; font-size:24px; font-weight:700; overflow:hidden; border:3px solid var(--line); }
    .profile-avatar-large img { width:100%; height:100%; object-fit:cover; }
    .profile-name-side { text-align:center; font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); margin-bottom:.25rem; }
    .profile-pos-side { text-align:center; font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600; padding:2px 9px; border-radius:5px; background:rgba(42,63,126,.08); color:var(--sidebar-soft); display:inline-block; margin:0 auto .75rem; }
    .profile-meta-center { display:flex; flex-direction:column; align-items:center; }
</style>

<div>
    <div class="bc">
        <a href="{{ route('staff.index') }}">Personnel</a>
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        <span class="bc-cur">{{ $staff->user->name }}</span>
    </div>

    @if ($savedProfile)
        <div class="toast toast-ok" x-data x-init="setTimeout(() => $el.remove(), 3000)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Profil enregistré.
        </div>
    @endif

    @if ($savedPassword)
        <div class="toast toast-ok" x-data x-init="setTimeout(() => $el.remove(), 3000)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Mot de passe mis à jour.
        </div>
    @endif

    @if ($passwordError)
        <div class="toast toast-err" x-data x-init="setTimeout(() => $el.remove(), 5000)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            {{ $passwordError }}
        </div>
    @endif

    <div class="page-grid">
        <div>

            {{-- Profil --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-header-icon" style="background:rgba(42,63,126,.08);color:var(--sidebar-soft);">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <span class="card-title">Informations personnelles</span>
                </div>
                <div class="card-body">
                    {{-- Photo --}}
                    <div class="photo-wrap">
                        <div class="photo-circle">
                            @if ($photo)
                                <img src="{{ $photo->temporaryUrl() }}" alt="">
                            @elseif ($staff->photo_path)
                                <img src="{{ asset('storage/'.$staff->photo_path) }}" alt="">
                            @else
                                {{ strtoupper(substr($staff->user->name,0,2)) }}
                            @endif
                        </div>
                        <label class="photo-upload-btn">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Changer la photo
                            <input wire:model="photo" type="file" accept="image/*">
                        </label>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-field">
                            <label class="form-label">Nom complet *</label>
                            <input wire:model="name" type="text" class="form-input" placeholder="Ahmed Dirieh">
                            @error('name') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field">
                            <label class="form-label">Email *</label>
                            <input wire:model="email" type="email" class="form-input">
                            @error('email') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="form-grid-3">
                        <div class="form-field">
                            <label class="form-label">Téléphone</label>
                            <input wire:model="phone" type="tel" class="form-input" placeholder="77 00 00 00">
                        </div>
                        <div class="form-field">
                            <label class="form-label">Poste *</label>
                            <select wire:model="position" class="form-select-inp">
                                <option value="">— Sélectionner —</option>
                                @foreach (['Enseignant','Administrateur','Comptable','Surveillant','Documentaliste','Agent de sécurité','Personnel d\'entretien','Autre'] as $p)
                                    <option value="{{ $p }}" @selected($position===$p)>{{ $p }}</option>
                                @endforeach
                            </select>
                            @error('position') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field">
                            <label class="form-label">Matricule</label>
                            <input wire:model="matricule" type="text" class="form-input">
                        </div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label class="form-label">Date d'embauche</label>
                            <input wire:model="hired_at" type="date" class="form-input">
                        </div>
                    </div>
                    <div class="form-field" style="margin-top:.25rem;">
                        <label class="form-label">Biographie / Notes</label>
                        <textarea wire:model="bio" class="form-textarea" placeholder="Spécialités, expérience, notes internes..."></textarea>
                    </div>

                    <div class="form-actions">
                        <button wire:click="saveProfile" class="btn-save">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                            Enregistrer
                        </button>
                    </div>
                </div>
            </div>

            {{-- Affectations --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-header-icon" style="background:rgba(232,168,56,.12);color:#8A6010;">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                    </div>
                    <span class="card-title">Matières enseignées — {{ $year?->label }}</span>
                </div>
                <div class="card-body" style="padding-top:.5rem;padding-bottom:.5rem;">
                    @forelse ($assignments as $assign)
                        <div class="assign-row">
                            <div class="assign-subj-dot" style="background:{{ $assign->subject->color ?? 'var(--sidebar)' }}"></div>
                            <div>
                                <div class="assign-subj">{{ $assign->subject->name }}</div>
                                <div class="assign-class">{{ $assign->schoolClass->name }} — {{ $assign->schoolClass->level?->name }}</div>
                            </div>
                            <span class="assign-badge">Coeff {{ $assign->subject->coefficient }}</span>
                        </div>
                    @empty
                        <p style="font-size:.875rem;color:var(--ink);opacity:.4;text-align:center;padding:.75rem 0;">Aucune affectation pour cette année.</p>
                    @endforelse
                </div>
            </div>

            {{-- Mot de passe --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-header-icon" style="background:rgba(224,92,58,.1);color:var(--accent-red);">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <span class="card-title">Changer le mot de passe</span>
                </div>
                <div class="card-body">
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label class="form-label">Nouveau mot de passe</label>
                            <div class="pw-wrap">
                                <input wire:model="newPassword"
                                       type="{{ $showPassword ? 'text' : 'password' }}"
                                       class="form-input"
                                       placeholder="8 caractères minimum"
                                       style="padding-right:2.5rem;">
                                <button type="button" wire:click="$toggle('showPassword')" class="pw-toggle">
                                    @if ($showPassword)
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                    @else
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    @endif
                                </button>
                            </div>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Confirmer le mot de passe</label>
                            <input wire:model="newPasswordConfirm"
                                   type="{{ $showPassword ? 'text' : 'password' }}"
                                   class="form-input"
                                   placeholder="Répéter le mot de passe">
                        </div>
                    </div>
                    <div class="form-actions" style="margin-top:.25rem;">
                        <button wire:click="savePassword" class="btn-save" style="background:var(--accent-red);">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Mettre à jour
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div style="position:sticky;top:1.5rem;">

            {{-- Profil résumé --}}
            <div class="side-card">
                <div class="side-body">
                    <div class="profile-meta-center">
                        <div class="profile-avatar-large">
                            @if ($staff->photo_path)
                                <img src="{{ asset('storage/'.$staff->photo_path) }}" alt="">
                            @else
                                {{ strtoupper(substr($staff->user->name,0,2)) }}
                            @endif
                        </div>
                        <div class="profile-name-side">{{ $staff->user->name }}</div>
                        <div class="profile-pos-side">{{ $staff->position ?? 'Aucun poste' }}</div>
                    </div>
                    <div class="side-row">
                        <span class="side-label">Matricule</span>
                        <span class="side-value" style="font-family:'JetBrains Mono',monospace;font-size:.8rem;">{{ $staff->matricule ?? '—' }}</span>
                    </div>
                    <div class="side-row">
                        <span class="side-label">Embauché le</span>
                        <span class="side-value">{{ $staff->hired_at?->format('d/m/Y') ?? '—' }}</span>
                    </div>
                    <div class="side-row">
                        <span class="side-label">Matières</span>
                        <span class="side-value">{{ $totalSubjects }}</span>
                    </div>
                    <div class="side-row">
                        <span class="side-label">Classes</span>
                        <span class="side-value">{{ $totalClasses }}</span>
                    </div>
                </div>
            </div>

            {{-- Classes principales --}}
            @if ($mainClasses->isNotEmpty())
                <div class="side-card">
                    <div class="side-header">Prof. principal de</div>
                    <div class="side-body" style="padding-top:.5rem;padding-bottom:.5rem;">
                        @foreach ($mainClasses as $class)
                            <div class="side-row">
                                <span class="side-label">{{ $class->name }}</span>
                                <span class="side-value" style="font-size:.75rem;opacity:.6;">{{ $class->level?->name }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Raccourcis --}}
            <div class="side-card">
                <div class="side-body">
                    <a href="{{ route('staff.index') }}"
                       style="display:flex;align-items:center;gap:5px;font-size:.8125rem;font-weight:600;color:var(--sidebar-soft);text-decoration:none;">
                        ← Retour à la liste du personnel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
