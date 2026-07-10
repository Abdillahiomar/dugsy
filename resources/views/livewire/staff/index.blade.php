<?php

use App\Models\Staff;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url] public string $search   = '';
    #[Url] public string $position = '';

    public bool $showForm = false;

    // Formulaire création
    public string $first_name = '';
    public string $last_name  = '';
    public string $email      = '';
    public string $phone      = '';
    public string $pos        = '';
    public string $hired_at   = '';
    public string $matricule  = '';

    // Suppression
    public ?int $confirmDeleteId = null;

    public function updatedSearch(): void   { $this->resetPage(); }
    public function updatedPosition(): void { $this->resetPage(); }

    public function saveStaff(): void
    {
        $this->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email',
            'pos'        => 'required|string|max:100',
        ]);

        $schoolId = auth()->user()->school_id;
        $count    = Staff::where('school_id', $schoolId)->count() + 1;

        // Créer l'utilisateur
        $user = User::create([
            'school_id' => $schoolId,
            'name'      => $this->first_name . ' ' . $this->last_name,
            'email'     => $this->email,
            'password'  => \Illuminate\Support\Facades\Hash::make('password'),
            'status'    => 'active',
        ]);

        // Créer la fiche staff
        Staff::create([
            'school_id'  => $schoolId,
            'user_id'    => $user->id,
            'matricule'  => $this->matricule ?: sprintf('STF-%d-%04d', $schoolId, $count),
            'position'   => $this->pos,
            'phone'      => $this->phone ?: null,
            'hired_at'   => $this->hired_at ?: now(),
        ]);

        $this->reset('first_name','last_name','email','phone','pos','hired_at','matricule','showForm');
    }

    public function confirmDelete(int $staffId): void
    {
        $this->confirmDeleteId = $staffId;
    }

    public function deleteStaff(): void
    {
        if (! $this->confirmDeleteId) return;

        $staff = Staff::with('user')->find($this->confirmDeleteId);
        if ($staff && $staff->school_id === auth()->user()->school_id) {
            $staff->user?->delete();
            $staff->delete();
        }

        $this->confirmDeleteId = null;
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $staff = Staff::where('school_id', $schoolId)
            ->when($this->search, fn ($q) =>
                $q->whereHas('user', fn ($q) =>
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%")
                )->orWhere('matricule', 'like', "%{$this->search}%")
            )
            ->when($this->position, fn ($q) => $q->where('position', $this->position))
            ->with('user')
            ->withCount('mainClasses')
            ->paginate(15);

        $positions = Staff::where('school_id', $schoolId)
            ->whereNotNull('position')
            ->distinct()
            ->pluck('position');

        $totalStaff    = Staff::where('school_id', $schoolId)->count();
        $totalTeachers = Staff::where('school_id', $schoolId)->where('position', 'Enseignant')->count();

        return compact('staff', 'positions', 'totalStaff', 'totalTeachers');
    }
}; ?>

<style>
    .page-toolbar { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
    .toolbar-left { display:flex; align-items:center; gap:.65rem; flex-wrap:wrap; }

    .search-wrap { position:relative; }
    .search-wrap svg { position:absolute; left:10px; top:50%; transform:translateY(-50%); width:15px; height:15px; color:var(--ink); opacity:.35; pointer-events:none; }
    .search-input { padding:.45rem .75rem .45rem 2.1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper-raised); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); width:220px; outline:none; }
    .search-input:focus { border-color:var(--sidebar-soft); }
    .filter-select { padding:.45rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper-raised); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; cursor:pointer; }
    .btn-primary { display:inline-flex; align-items:center; gap:5px; padding:.45rem 1rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; transition:background .15s; }
    .btn-primary:hover { background:var(--sidebar-soft); }
    .btn-primary svg { width:15px; height:15px; }

    /* KPIs */
    .kpi-row { display:grid; grid-template-columns:repeat(3,1fr); gap:.875rem; margin-bottom:1.5rem; }
    .kpi-box { padding:.875rem 1.25rem; border-radius:10px; border:1px solid var(--line); background:var(--paper-raised); }
    .kpi-label { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; margin-bottom:.35rem; }
    .kpi-value { font-family:'Fraunces',serif; font-size:2rem; font-weight:600; color:var(--ink); }

    /* Formulaire */
    .create-form { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.25rem; animation:slideDown .15s ease; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-6px);} to{opacity:1;transform:translateY(0);} }
    .cf-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .cf-title { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); }
    .cf-body { padding:1.25rem 1.5rem; display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; }
    @media(max-width:800px) { .cf-body { grid-template-columns:1fr 1fr; } }
    @media(max-width:500px) { .cf-body { grid-template-columns:1fr; } }
    .form-field { display:flex; flex-direction:column; gap:.3rem; }
    .form-label { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.5; }
    .form-input, .form-select-inp { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; transition:border-color .15s; }
    .form-input:focus, .form-select-inp:focus { border-color:var(--sidebar-soft); }
    .form-error { font-size:.75rem; color:var(--accent-red); margin-top:.2rem; }
    .cf-footer { padding:1rem 1.5rem; border-top:1px solid var(--line); display:flex; justify-content:flex-end; gap:.65rem; }
    .btn-cancel { padding:.45rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }
    .btn-save { display:inline-flex; align-items:center; gap:5px; padding:.45rem 1.25rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
    .btn-save svg { width:14px; height:14px; }

    /* Table */
    .table-wrap { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; }
    table { width:100%; border-collapse:collapse; }
    thead tr { border-bottom:1px solid var(--line); background:var(--paper); }
    thead th { text-align:left; padding:.65rem 1.25rem; font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.45; white-space:nowrap; }
    thead th:last-child { text-align:right; }
    tbody tr { border-bottom:1px solid var(--line); transition:background .1s; }
    tbody tr:last-child { border-bottom:none; }
    tbody tr:hover { background:rgba(30,45,90,.03); }
    tbody td { padding:.75rem 1.25rem; font-size:.875rem; color:var(--ink); vertical-align:middle; }
    tbody td:last-child { text-align:right; }

    .staff-cell { display:flex; align-items:center; gap:.75rem; }
    .staff-avatar { width:34px; height:34px; border-radius:50%; background:rgba(42,63,126,.1); color:var(--sidebar-soft); font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .staff-name   { font-weight:600; }
    .staff-email  { font-size:.8rem; color:var(--ink); opacity:.5; }
    .staff-matric { font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--ink); opacity:.4; }

    .position-badge { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:2px 8px; border-radius:4px; background:rgba(42,63,126,.08); color:var(--sidebar-soft); }

    .actions-cell { display:flex; align-items:center; justify-content:flex-end; gap:.4rem; }
    .btn-action { display:inline-flex; align-items:center; gap:4px; padding:.3rem .65rem; border-radius:6px; font-size:.8rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; transition:background .12s; text-decoration:none; }
    .btn-action svg { width:13px; height:13px; }
    .btn-edit-act { background:rgba(42,63,126,.08); color:var(--sidebar-soft); }
    .btn-edit-act:hover { background:rgba(42,63,126,.16); }
    .btn-del-act  { background:rgba(224,92,58,.08); color:var(--accent-red); }
    .btn-del-act:hover { background:rgba(224,92,58,.16); }

    /* Pagination */
    .pagination-bar { display:flex; align-items:center; justify-content:space-between; padding:.75rem 1.25rem; border-top:1px solid var(--line); }
    .pagination-info { font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--ink); opacity:.45; }
    .pagination-controls { display:flex; align-items:center; gap:.4rem; }
    .page-btn { display:inline-flex; align-items:center; gap:4px; padding:.35rem .75rem; border-radius:7px; font-size:.8125rem; font-weight:600; border:1px solid var(--line); background:var(--paper-raised); color:var(--ink); cursor:pointer; transition:all .12s; }
    .page-btn:hover:not(:disabled) { border-color:var(--sidebar-soft); color:var(--sidebar-soft); }
    .page-btn:disabled { opacity:.35; cursor:default; }
    .page-btn svg { width:14px; height:14px; }
    .page-current { padding:.35rem .75rem; border-radius:7px; font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600; background:var(--sidebar); color:#FFFFFF; border:1px solid var(--sidebar); }

    /* Modal */
    .modal-overlay { position:fixed; inset:0; z-index:100; background:rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; padding:1rem; }
    .modal { background:var(--paper-raised); border-radius:14px; border:1px solid var(--line); padding:1.75rem; max-width:380px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.2); }
    .modal-title { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; margin-bottom:.5rem; }
    .modal-desc  { font-size:.875rem; color:var(--ink); opacity:.6; margin-bottom:1.25rem; line-height:1.5; }
    .modal-actions { display:flex; justify-content:flex-end; gap:.65rem; }
    .btn-modal-cancel { padding:.45rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }
    .btn-modal-confirm { padding:.45rem 1rem; border-radius:8px; border:none; background:var(--accent-red); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; cursor:pointer; }

    .empty-state { padding:3.5rem 2rem; text-align:center; }
    .empty-state svg { width:44px; height:44px; margin:0 auto 1rem; opacity:.2; }
    .empty-title { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; color:var(--ink); margin-bottom:.3rem; }
    .empty-sub   { font-size:.875rem; color:var(--ink); opacity:.45; }
</style>

<div>

    {{-- KPIs --}}
    <div class="kpi-row">
        <div class="kpi-box">
            <div class="kpi-label">Total personnel</div>
            <div class="kpi-value">{{ $totalStaff }}</div>
        </div>
        <div class="kpi-box">
            <div class="kpi-label">Enseignants</div>
            <div class="kpi-value">{{ $totalTeachers }}</div>
        </div>
        <div class="kpi-box">
            <div class="kpi-label">Autres postes</div>
            <div class="kpi-value">{{ $totalStaff - $totalTeachers }}</div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="page-toolbar">
        <div class="toolbar-left">
            <div class="search-wrap">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 111 11a6 6 0 0116 0z"/></svg>
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Nom, email, matricule..." class="search-input">
            </div>
            <select wire:model.live="position" class="filter-select">
                <option value="">Tous les postes</option>
                @foreach ($positions as $p)
                    <option value="{{ $p }}">{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <button wire:click="$toggle('showForm')" class="btn-primary">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Nouveau membre
        </button>
    </div>

    {{-- Formulaire création --}}
    @if ($showForm)
        <div class="create-form">
            <div class="cf-header">
                <span class="cf-title">Nouveau membre du personnel</span>
                <button wire:click="$set('showForm',false)" style="background:none;border:none;cursor:pointer;opacity:.4;">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="cf-body">
                <div class="form-field">
                    <label class="form-label">Prénom *</label>
                    <input wire:model="first_name" type="text" class="form-input" placeholder="Ahmed">
                    @error('first_name') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-field">
                    <label class="form-label">Nom *</label>
                    <input wire:model="last_name" type="text" class="form-input" placeholder="Dirieh">
                    @error('last_name') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-field">
                    <label class="form-label">Email *</label>
                    <input wire:model="email" type="email" class="form-input" placeholder="ahmed@ecole.dj">
                    @error('email') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-field">
                    <label class="form-label">Poste *</label>
                    <select wire:model="pos" class="form-select-inp">
                        <option value="">— Sélectionner —</option>
                        <option value="Enseignant">Enseignant</option>
                        <option value="Administrateur">Administrateur</option>
                        <option value="Comptable">Comptable</option>
                        <option value="Surveillant">Surveillant</option>
                        <option value="Documentaliste">Documentaliste</option>
                        <option value="Agent de sécurité">Agent de sécurité</option>
                        <option value="Personnel d'entretien">Personnel d'entretien</option>
                        <option value="Autre">Autre</option>
                    </select>
                    @error('pos') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                <div class="form-field">
                    <label class="form-label">Téléphone</label>
                    <input wire:model="phone" type="tel" class="form-input" placeholder="77 00 00 00">
                </div>
                <div class="form-field">
                    <label class="form-label">Date d'embauche</label>
                    <input wire:model="hired_at" type="date" class="form-input">
                </div>
                <div class="form-field">
                    <label class="form-label">Matricule</label>
                    <input wire:model="matricule" type="text" class="form-input" placeholder="Auto-généré si vide">
                </div>
            </div>
            <div class="cf-footer">
                <p style="font-size:.8rem;color:var(--ink);opacity:.5;flex:1;">
                    Mot de passe par défaut : <code>password</code> — à changer à la première connexion.
                </p>
                <button wire:click="$set('showForm',false)" class="btn-cancel">Annuler</button>
                <button wire:click="saveStaff" class="btn-save">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Créer
                </button>
            </div>
        </div>
    @endif

    {{-- Table --}}
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Membre</th>
                    <th>Matricule</th>
                    <th>Poste</th>
                    <th>Téléphone</th>
                    <th>Embauché le</th>
                    <th>Classes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($staff as $member)
                    <tr>
                        <td>
                            <div class="staff-cell">
                                <div class="staff-avatar">
                                    {{ strtoupper(substr($member->user->name ?? '?',0,2)) }}
                                </div>
                                <div>
                                    <div class="staff-name">{{ $member->user->name }}</div>
                                    <div class="staff-email">{{ $member->user->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="staff-matric">{{ $member->matricule ?? '—' }}</span></td>
                        <td><span class="position-badge">{{ $member->position ?? '—' }}</span></td>
                        <td style="font-size:.875rem;">{{ $member->phone ?? '—' }}</td>
                        <td style="font-family:'JetBrains Mono',monospace;font-size:11px;opacity:.6;">
                            {{ $member->hired_at?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td style="text-align:center;font-family:'JetBrains Mono',monospace;font-weight:600;">
                            {{ $member->main_classes_count }}
                        </td>
                        <td>
                            <div class="actions-cell">
                                <a href="{{ route('staff.edit', $member) }}" class="btn-action btn-edit-act">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    Modifier
                                </a>
                                <button wire:click="confirmDelete({{ $member->id }})" class="btn-action btn-del-act">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16"/></svg>
                                    Supprimer
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <div class="empty-title">Aucun membre du personnel</div>
                                <div class="empty-sub">Crée ton premier membre du personnel.</div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pagination --}}
        @if ($staff->hasPages())
            <div class="pagination-bar">
                <span class="pagination-info">{{ $staff->firstItem() }}–{{ $staff->lastItem() }} sur {{ $staff->total() }}</span>
                <div class="pagination-controls">
                    @if ($staff->onFirstPage())
                        <button class="page-btn" disabled>
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            Précédent
                        </button>
                    @else
                        <button wire:click="previousPage" class="page-btn">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            Précédent
                        </button>
                    @endif
                    <span class="page-current">{{ $staff->currentPage() }} / {{ $staff->lastPage() }}</span>
                    @if ($staff->hasMorePages())
                        <button wire:click="nextPage" class="page-btn">
                            Suivant
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    @else
                        <button class="page-btn" disabled>
                            Suivant
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Modal suppression --}}
    @if ($confirmDeleteId)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Supprimer ce membre ?</div>
                <div class="modal-desc">Son compte utilisateur sera également supprimé. Les classes dont il est prof. principal seront mises à jour. Cette action est irréversible.</div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeleteId',null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deleteStaff" class="btn-modal-confirm">Oui, supprimer</button>
                </div>
            </div>
        </div>
    @endif

</div>
