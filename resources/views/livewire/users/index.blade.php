<?php

use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

new class extends Component
{
    use WithPagination;

    // ── Navigation ───────────────────────────────────────────────
    public string $activeTab = 'users'; // users | roles

    // ── Filtres ───────────────────────────────────────────────────
    #[Url] public string $search   = '';
    #[Url] public string $roleFilter = '';

    public function updatedSearch(): void    { $this->resetPage(); }
    public function updatedRoleFilter(): void { $this->resetPage(); }

    // ── Formulaire utilisateur ────────────────────────────────────
    public bool    $showUserForm   = false;
    public ?int    $editingUserId  = null;
    public string  $uName         = '';
    public string  $uEmail        = '';
    public string  $uPassword     = '';
    public string  $uRole         = '';
    public string  $uStatus       = 'active';
    public bool    $showPw        = false;

    // ── Attribution de rôle rapide ────────────────────────────────
    public ?int    $roleAssignUserId  = null;
    public string  $roleAssignValue   = '';

    // ── Suppression ───────────────────────────────────────────────
    public ?int    $confirmDeleteId   = null;

    // ── Gestion des rôles ─────────────────────────────────────────
    public bool    $showRoleForm      = false;
    public ?int    $editingRoleId     = null;
    public string  $roleName         = '';
    public string  $roleLabel        = '';
    public array   $rolePerms        = [];

    public ?int    $confirmDeleteRoleId = null;

    public bool    $savedUser  = false;
    public bool    $savedRole  = false;


    // ── Nouvelle permission ───────────────────────────────────────
public bool   $showPermForm  = false;
public string $permName      = '';   // ex: reports.view
public string $permLabel     = '';   // ex: Voir les rapports
public string $permModule    = '';   // ex: reports
public bool   $permSaved     = false;

public ?int   $confirmDeletePermId = null;

    // ── Utilisateurs ─────────────────────────────────────────────

    public function openCreateUser(): void
    {
        $this->resetUserForm();
        $this->showUserForm  = true;
        $this->editingUserId = null;
    }

    public function openEditUser(int $id): void
    {
        $user = User::with('roles')->find($id);
        if (! $user) return;

        $this->editingUserId = $id;
        $this->uName         = $user->name;
        $this->uEmail        = $user->email;
        $this->uPassword     = '';
        $this->uRole         = $user->roles->first()?->name ?? '';
        $this->uStatus       = $user->status ?? 'active';
        $this->showUserForm  = true;
    }

    public function saveUser(): void
    {
        $rules = [
            'uName'   => 'required|string|max:200',
            'uEmail'  => 'required|email|unique:users,email' . ($this->editingUserId ? ",{$this->editingUserId}" : ''),
            'uRole'   => 'required|string|exists:roles,name',
            'uStatus' => 'required|in:active,inactive,suspended',
        ];

        if (! $this->editingUserId) {
            $rules['uPassword'] = 'required|string|min:8';
        }

        $this->validate($rules);

        if ($this->editingUserId) {
            $user = User::find($this->editingUserId);
            $user->update([
                'name'   => $this->uName,
                'email'  => $this->uEmail,
                'status' => $this->uStatus,
            ]);
            if ($this->uPassword) {
                $user->update(['password' => Hash::make($this->uPassword)]);
            }
        } else {
            $user = User::create([
                'school_id' => auth()->user()->school_id,
                'name'      => $this->uName,
                'email'     => $this->uEmail,
                'password'  => Hash::make($this->uPassword),
                'status'    => $this->uStatus,
            ]);
        }

        $user->syncRoles([$this->uRole]);
        $this->resetUserForm();
        $this->savedUser = true;
    }

    public function quickAssignRole(): void
    {
        if (! $this->roleAssignUserId || ! $this->roleAssignValue) return;

        $user = User::find($this->roleAssignUserId);
        $user?->syncRoles([$this->roleAssignValue]);

        $this->roleAssignUserId = null;
        $this->roleAssignValue  = '';
        $this->savedUser        = true;
    }

    public function toggleStatus(int $id): void
    {
        $user = User::find($id);
        if (! $user) return;

        $user->update([
            'status' => $user->status === 'active' ? 'inactive' : 'active'
        ]);
    }

    public function confirmDeleteUser(int $id): void
    {
        $this->confirmDeleteId = $id;
    }

    public function deleteUser(): void
    {
        if (! $this->confirmDeleteId) return;

        $user = User::find($this->confirmDeleteId);
        if ($user && $user->school_id === auth()->user()->school_id
            && $user->id !== auth()->id()) {
            $user->delete();
        }

        $this->confirmDeleteId = null;
    }

    private function resetUserForm(): void
    {
        $this->uName = $this->uEmail = $this->uPassword = $this->uRole = '';
        $this->uStatus       = 'active';
        $this->showUserForm  = false;
        $this->editingUserId = null;
    }

    // ── Rôles ────────────────────────────────────────────────────

    public function openCreateRole(): void
    {
        $this->resetRoleForm();
        $this->showRoleForm  = true;
        $this->editingRoleId = null;
    }

    public function openEditRole(int $id): void
    {
        $role = Role::with('permissions')->find($id);
        if (! $role) return;

        $this->editingRoleId = $id;
        $this->roleName      = $role->name;
        $this->roleLabel     = $role->label ?? '';
        $this->rolePerms     = $role->permissions->pluck('name')->toArray();
        $this->showRoleForm  = true;
    }

    public function saveRole(): void
    {
        $this->validate([
            'roleName'  => 'required|string|max:100|alpha_dash',
            'roleLabel' => 'nullable|string|max:200',
        ]);

        if ($this->editingRoleId) {
            $role = Role::find($this->editingRoleId);
            $role->update(['label' => $this->roleLabel]);
            // Le nom du rôle n'est pas modifiable s'il est utilisé
        } else {
            $this->validate([
                'roleName' => 'unique:roles,name',
            ]);
            $role = Role::create([
                'name'       => $this->roleName,
                'guard_name' => 'web',
                'label'      => $this->roleLabel ?: null,
            ]);
        }

        $role->syncPermissions($this->rolePerms);
        $this->resetRoleForm();
        $this->savedRole = true;
    }

    public function confirmDeleteRole(int $id): void
    {
        $this->confirmDeleteRoleId = $id;
    }

    public function deleteRole(): void
    {
        if (! $this->confirmDeleteRoleId) return;

        $role = Role::find($this->confirmDeleteRoleId);
        // Protéger les rôles système
        $protected = ['admin', 'directeur', 'comptable', 'enseignant', 'surveillant', 'parent'];
        if ($role && ! in_array($role->name, $protected)) {
            $role->delete();
        }

        $this->confirmDeleteRoleId = null;
    }


    public function savePermission(): void
    {
        $this->validate([
            'permName'  => 'required|string|regex:/^[a-z_]+\.[a-z_]+$/|unique:permissions,name',
            'permLabel' => 'required|string|max:200',
        ], [
            'permName.regex'  => 'Format requis : module.action — ex: reports.view',
            'permName.unique' => 'Cette permission existe déjà.',
        ]);

        \Spatie\Permission\Models\Permission::create([
            'name'       => $this->permName,
            'label'      => $this->permLabel,
            'guard_name' => 'web',
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->permName      = '';
        $this->permLabel     = '';
        $this->permModule    = '';
        $this->showPermForm  = false;
        $this->permSaved     = true;
    }

    public function confirmDeletePerm(int $id): void
    {
        $this->confirmDeletePermId = $id;
    }

    public function deletePerm(): void
    {
        if (! $this->confirmDeletePermId) return;

        $perm = \Spatie\Permission\Models\Permission::find($this->confirmDeletePermId);

        // Protéger les permissions système
        $protected = [
            'students.list','students.view','students.create','students.edit',
            'students.delete','students.enroll','classes.view','classes.manage',
            'grades.view','grades.enter','grades.manage','bulletins.view',
            'bulletins.generate','absences.view','absences.manage',
            'finance.view','finance.collect','finance.manage',
            'school.settings','fees.manage','users.view','users.manage',
        ];

        if ($perm && ! in_array($perm->name, $protected)) {
            $perm->delete();
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }

        $this->confirmDeletePermId = null;
    }

    private function resetRoleForm(): void
    {
        $this->roleName  = $this->roleLabel = '';
        $this->rolePerms = [];
        $this->showRoleForm  = false;
        $this->editingRoleId = null;
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $users = User::where('school_id', $schoolId)
            ->when($this->search, fn ($q) =>
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%")
            )
            ->when($this->roleFilter, fn ($q) =>
                $q->whereHas('roles', fn ($q) => $q->where('name', $this->roleFilter))
            )
            ->with(['roles', 'staff'])
            ->latest()
            ->paginate(15);

        $roles       = Role::withCount('users')->get();
        $permissions = \Spatie\Permission\Models\Permission::all()
            ->groupBy(fn ($p) => explode('.', $p->name)[0]);

        $totalUsers  = User::where('school_id', $schoolId)->count();
        $activeUsers = User::where('school_id', $schoolId)->where('status','active')->count();

        return compact(
            'users', 'roles', 'permissions',
            'totalUsers', 'activeUsers'
        );
    }
}; ?>

<style>
    /* ── Tabs ── */
    .main-tabs { display:flex; background:var(--paper); border:1px solid var(--line); border-radius:10px; padding:4px; margin-bottom:1.5rem; gap:.25rem; }
    .main-tab { display:inline-flex; align-items:center; gap:6px; padding:.5rem 1.25rem; border-radius:7px; font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); border:none; cursor:pointer; background:none; opacity:.55; transition:all .12s; }
    .main-tab svg { width:15px; height:15px; }
    .main-tab:hover { opacity:.9; background:var(--paper-raised); }
    .main-tab.active { background:var(--sidebar); color:#FFFFFF; opacity:1; }

    /* ── KPIs ── */
    .kpi-row { display:grid; grid-template-columns:repeat(4,1fr); gap:.875rem; margin-bottom:1.5rem; }
    @media(max-width:700px) { .kpi-row { grid-template-columns:1fr 1fr; } }
    .kpi-box { padding:.875rem 1.25rem; border-radius:10px; border:1px solid var(--line); background:var(--paper-raised); }
    .kpi-label { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; margin-bottom:.35rem; }
    .kpi-value { font-family:'Fraunces',serif; font-size:2rem; font-weight:600; color:var(--ink); }
    .kpi-sub { font-size:.8rem; color:var(--ink); opacity:.45; margin-top:2px; }

    /* ── Toolbar ── */
    .page-toolbar { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; flex-wrap:wrap; }
    .toolbar-left { display:flex; align-items:center; gap:.65rem; flex-wrap:wrap; }
    .search-wrap { position:relative; }
    .search-wrap svg { position:absolute; left:10px; top:50%; transform:translateY(-50%); width:15px; height:15px; color:var(--ink); opacity:.35; pointer-events:none; }
    .search-input { padding:.45rem .75rem .45rem 2.1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper-raised); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); width:220px; outline:none; }
    .search-input:focus { border-color:var(--sidebar-soft); }
    .filter-select { padding:.45rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper-raised); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; cursor:pointer; }
    .btn-primary { display:inline-flex; align-items:center; gap:5px; padding:.45rem 1rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; transition:background .15s; }
    .btn-primary:hover { background:var(--sidebar-soft); }
    .btn-primary svg { width:14px; height:14px; }

    /* ── Form card ── */
    .form-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.25rem; animation:slideDown .15s ease; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-6px);} to{opacity:1;transform:translateY(0);} }
    .form-card-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .form-card-title { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); }
    .form-card-body { padding:1.25rem 1.5rem; }
    .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
    .form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; margin-bottom:1rem; }
    @media(max-width:700px) { .form-grid-2,.form-grid-3 { grid-template-columns:1fr; } }
    .form-field { display:flex; flex-direction:column; gap:.35rem; }
    .form-field.full { grid-column:1/-1; }
    .form-label { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.5; }
    .form-input, .form-select-inp { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; transition:border-color .15s; }
    .form-input:focus, .form-select-inp:focus { border-color:var(--sidebar-soft); box-shadow:0 0 0 3px rgba(42,63,126,.08); }
    .form-error { font-size:.75rem; color:var(--accent-red); margin-top:.2rem; }
    .form-actions { display:flex; justify-content:flex-end; gap:.65rem; padding-top:1rem; border-top:1px solid var(--line); margin-top:1rem; }
    .btn-save { display:inline-flex; align-items:center; gap:5px; padding:.5rem 1.25rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
    .btn-save svg { width:14px; height:14px; }
    .btn-cancel-sm { padding:.5rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }

    /* ── Password toggle ── */
    .pw-wrap { position:relative; }
    .pw-toggle { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--ink); opacity:.4; }
    .pw-toggle:hover { opacity:.8; }
    .pw-toggle svg { width:15px; height:15px; }

    /* ── Table ── */
    .table-wrap { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; }
    table { width:100%; border-collapse:collapse; }
    thead tr { border-bottom:1px solid var(--line); background:var(--paper); }
    thead th { text-align:left; padding:.65rem 1.25rem; font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.45; white-space:nowrap; }
    thead th:last-child { text-align:right; }
    tbody tr { border-bottom:1px solid var(--line); transition:background .1s; }
    tbody tr:last-child { border-bottom:none; }
    tbody tr:hover { background:rgba(30,45,90,.02); }
    tbody td { padding:.75rem 1.25rem; font-size:.875rem; color:var(--ink); vertical-align:middle; }
    tbody td:last-child { text-align:right; }

    .user-cell { display:flex; align-items:center; gap:.75rem; }
    .user-avatar { width:34px; height:34px; border-radius:50%; background:rgba(42,63,126,.1); color:var(--sidebar-soft); font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .user-name  { font-weight:600; }
    .user-email { font-size:.8rem; color:var(--ink); opacity:.5; }
    .user-you   { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; padding:1px 5px; border-radius:3px; background:rgba(232,168,56,.15); color:#8A6010; margin-left:.35rem; }

    .status-dot { display:inline-flex; align-items:center; gap:.35rem; font-size:.875rem; }
    .dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .dot-active   { background:#22c55e; }
    .dot-inactive { background:var(--line); }
    .dot-suspended{ background:var(--accent-red); }

    /* Rôle badge inline avec select */
    .role-cell { display:flex; align-items:center; gap:.5rem; }
    .role-select-inline { padding:.25rem .5rem; border-radius:5px; border:1px solid var(--line); background:var(--paper); font-size:.8125rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; }
    .role-select-inline:focus { border-color:var(--sidebar-soft); }
    .role-badge { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:2px 8px; border-radius:4px; text-transform:uppercase; letter-spacing:.04em; cursor:pointer; }
    .role-admin      { background:rgba(30,45,90,.1); color:var(--sidebar-soft); }
    .role-directeur  { background:rgba(42,63,126,.08); color:var(--sidebar-soft); }
    .role-comptable  { background:rgba(30,120,80,.1); color:#166534; }
    .role-enseignant { background:rgba(232,168,56,.15); color:#8A6010; }
    .role-surveillant{ background:rgba(99,102,241,.1); color:#3730A3; }
    .role-parent     { background:rgba(224,92,58,.08); color:#C04020; }
    .role-none       { background:rgba(0,0,0,.05); color:var(--ink); opacity:.5; }

    .actions-cell { display:flex; align-items:center; justify-content:flex-end; gap:.4rem; }
    .btn-action { display:inline-flex; align-items:center; gap:4px; padding:.3rem .65rem; border-radius:6px; font-size:.8rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; transition:background .12s; }
    .btn-action svg { width:13px; height:13px; }
    .btn-edit-act { background:rgba(42,63,126,.08); color:var(--sidebar-soft); }
    .btn-edit-act:hover { background:rgba(42,63,126,.16); }
    .btn-del-act  { background:rgba(224,92,58,.08); color:var(--accent-red); }
    .btn-del-act:hover  { background:rgba(224,92,58,.16); }
    .btn-toggle-act { background:rgba(30,120,80,.08); color:#166534; font-size:.75rem; }
    .btn-toggle-act:hover { background:rgba(30,120,80,.15); }

    /* Pagination */
    .pagination-bar { display:flex; align-items:center; justify-content:space-between; padding:.75rem 1.25rem; border-top:1px solid var(--line); }
    .pagination-info { font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--ink); opacity:.45; }
    .pagination-controls { display:flex; align-items:center; gap:.4rem; }
    .page-btn { display:inline-flex; align-items:center; gap:4px; padding:.35rem .75rem; border-radius:7px; font-size:.8125rem; font-weight:600; border:1px solid var(--line); background:var(--paper-raised); color:var(--ink); cursor:pointer; transition:all .12s; }
    .page-btn:hover:not(:disabled) { border-color:var(--sidebar-soft); color:var(--sidebar-soft); }
    .page-btn:disabled { opacity:.35; cursor:default; }
    .page-btn svg { width:14px; height:14px; }
    .page-current { padding:.35rem .75rem; border-radius:7px; font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600; background:var(--sidebar); color:#FFFFFF; border:1px solid var(--sidebar); }

    /* ── Roles section ── */
    .roles-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:1.25rem; }
    .role-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; }
    .role-card-header { padding:.875rem 1.25rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .role-name-big { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); }
    .role-users-count { font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--ink); opacity:.45; }
    .role-card-body { padding:.875rem 1.25rem; }
    .perms-group { margin-bottom:.875rem; }
    .perms-group:last-child { margin-bottom:0; }
    .perms-group-label { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; margin-bottom:.4rem; }
    .perms-list { display:flex; flex-wrap:wrap; gap:.3rem; }
    .perm-chip { font-family:'JetBrains Mono',monospace; font-size:9.5px; font-weight:600; padding:2px 7px; border-radius:4px; background:rgba(42,63,126,.07); color:var(--sidebar-soft); }
    .perm-chip.missing { background:rgba(0,0,0,.04); color:var(--ink); opacity:.3; }
    .role-card-footer { padding:.65rem 1.25rem; border-top:1px solid var(--line); display:flex; justify-content:flex-end; gap:.4rem; }
    .role-protected { font-size:.75rem; color:var(--ink); opacity:.35; font-style:italic; margin-right:auto; align-self:center; }

    /* Permissions checkboxes dans le form */
    .perm-section { border:1px solid var(--line); border-radius:8px; overflow:hidden; margin-bottom:.75rem; }
    .perm-section:last-child { margin-bottom:0; }
    .perm-section-header { padding:.6rem 1rem; background:var(--paper); border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .perm-section-title { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:var(--ink); opacity:.5; }
    .perm-section-body { padding:.75rem 1rem; display:flex; flex-wrap:wrap; gap:.5rem; }
    .perm-check-row { display:flex; align-items:center; gap:.4rem; }
    .perm-checkbox { width:14px; height:14px; border-radius:3px; border:1.5px solid var(--line); background:var(--paper-raised); appearance:none; cursor:pointer; position:relative; transition:all .12s; }
    .perm-checkbox:checked { background:var(--sidebar); border-color:var(--sidebar); }
    .perm-checkbox:checked::after { content:''; position:absolute; top:1px; left:3.5px; width:4px; height:7px; border:2px solid #FFF; border-top:none; border-left:none; transform:rotate(45deg); }
    .perm-check-label { font-size:.8rem; color:var(--ink); cursor:pointer; }

    /* Toggle all perms */
    .btn-toggle-all { font-size:.75rem; font-weight:600; color:var(--sidebar-soft); background:none; border:none; cursor:pointer; padding:0; }

    /* Toast */
    .toast { display:flex; align-items:center; gap:.6rem; padding:.65rem 1rem; border-radius:8px; font-size:.875rem; font-weight:500; margin-bottom:1rem; animation:slideDown .15s ease; }
    .toast-ok { background:rgba(30,120,80,.1); border:1px solid rgba(30,120,80,.2); color:#166534; }
    .toast svg { width:15px; height:15px; flex-shrink:0; }

    /* Modal */
    .modal-overlay { position:fixed; inset:0; z-index:100; background:rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; padding:1rem; }
    .modal { background:var(--paper-raised); border-radius:14px; border:1px solid var(--line); padding:1.75rem; max-width:420px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.2); }
    .modal-title { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; margin-bottom:.5rem; }
    .modal-desc  { font-size:.875rem; color:var(--ink); opacity:.6; margin-bottom:1.25rem; line-height:1.5; }
    .modal-actions { display:flex; justify-content:flex-end; gap:.65rem; }
    .btn-modal-cancel  { padding:.45rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }
    .btn-modal-confirm { padding:.45rem 1rem; border-radius:8px; border:none; background:var(--accent-red); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; cursor:pointer; }

    /* Modules labels */
    .module-labels { 'students'=>'Élèves','classes'=>'Classes','subjects'=>'Matières','grades'=>'Notes','bulletins'=>'Bulletins','absences'=>'Absences','finance'=>'Finances','staff'=>'Personnel','academic_years'=>'Années','school'=>'École','fees'=>'Frais','users'=>'Utilisateurs','announcements'=>'Annonces' }
</style>

<div>

    {{-- Navigation tabs --}}
    <div class="main-tabs">
        <button type="button" wire:click="$set('activeTab','users')"
                class="main-tab {{ $activeTab==='users' ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            Utilisateurs
        </button>
        <button type="button" wire:click="$set('activeTab','roles')"
                class="main-tab {{ $activeTab==='roles' ? 'active' : '' }}">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            Rôles & Permissions
        </button>
    </div>

    {{-- Toast --}}
    @if ($savedUser || $savedRole || $permSaved)
        <div class="toast toast-ok" x-data x-init="setTimeout(() => $el.remove(), 3000)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $savedUser ? 'Utilisateur enregistré.' : ($savedRole ? 'Rôle enregistré.' : 'Permission créée.') }}
        </div>
    @endif

    {{-- ══════════════════════════════════════════════ --}}
    {{-- TAB UTILISATEURS --}}
    {{-- ══════════════════════════════════════════════ --}}
    @if ($activeTab === 'users')

        {{-- KPIs --}}
        <div class="kpi-row">
            <div class="kpi-box">
                <div class="kpi-label">Total utilisateurs</div>
                <div class="kpi-value">{{ $totalUsers }}</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Actifs</div>
                <div class="kpi-value" style="color:#166534;">{{ $activeUsers }}</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Inactifs</div>
                <div class="kpi-value" style="color:var(--ink);opacity:.4;">{{ $totalUsers - $activeUsers }}</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Rôles définis</div>
                <div class="kpi-value">{{ $roles->count() }}</div>
            </div>
        </div>

        {{-- Toolbar --}}
        <div class="page-toolbar">
            <div class="toolbar-left">
                <div class="search-wrap">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 111 11a6 6 0 0116 0z"/></svg>
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Nom ou email..." class="search-input">
                </div>
                <select wire:model.live="roleFilter" class="filter-select">
                    <option value="">Tous les rôles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->name }}">{{ $role->label ?? ucfirst($role->name) }}</option>
                    @endforeach
                </select>
            </div>
            <button wire:click="openCreateUser" class="btn-primary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Nouvel utilisateur
            </button>
        </div>

        {{-- Formulaire utilisateur --}}
        @if ($showUserForm)
            <div class="form-card">
                <div class="form-card-header">
                    <span class="form-card-title">{{ $editingUserId ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur' }}</span>
                    <button wire:click="$set('showUserForm',false)" style="background:none;border:none;cursor:pointer;opacity:.4;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="form-card-body">
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label class="form-label">Nom complet *</label>
                            <input wire:model="uName" type="text" class="form-input" placeholder="Ahmed Dirieh">
                            @error('uName') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field">
                            <label class="form-label">Email *</label>
                            <input wire:model="uEmail" type="email" class="form-input" placeholder="ahmed@ecole.dj">
                            @error('uEmail') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="form-grid-3">
                        <div class="form-field">
                            <label class="form-label">Rôle *</label>
                            <select wire:model="uRole" class="form-select-inp">
                                <option value="">— Sélectionner —</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->name }}">
                                        {{ $role->label ?? ucfirst($role->name) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('uRole') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field">
                            <label class="form-label">Statut</label>
                            <select wire:model="uStatus" class="form-select-inp">
                                <option value="active">Actif</option>
                                <option value="inactive">Inactif</option>
                                <option value="suspended">Suspendu</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="form-label">Mot de passe{{ $editingUserId ? ' (laisser vide = inchangé)' : ' *' }}</label>
                            <div class="pw-wrap">
                                <input wire:model="uPassword"
                                       type="{{ $showPw ? 'text' : 'password' }}"
                                       class="form-input"
                                       placeholder="{{ $editingUserId ? 'Laisser vide' : '8 caractères min.' }}"
                                       style="padding-right:2.5rem;">
                                <button type="button" wire:click="$toggle('showPw')" class="pw-toggle">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            @error('uPassword') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="form-actions">
                        <button wire:click="$set('showUserForm',false)" class="btn-cancel-sm">Annuler</button>
                        <button wire:click="saveUser" class="btn-save">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            {{ $editingUserId ? 'Enregistrer' : 'Créer' }}
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Table utilisateurs --}}
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Créé le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        @php
                            $userRole    = $user->roles->first();
                            $roleCss     = 'role-'.($userRole?->name ?? 'none');
                            $isMe        = $user->id === auth()->id();
                            $statusColor = match($user->status ?? 'active') {
                                'active'    => 'dot-active',
                                'inactive'  => 'dot-inactive',
                                'suspended' => 'dot-suspended',
                                default     => 'dot-inactive',
                            };
                        @endphp
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar">{{ strtoupper(substr($user->name,0,2)) }}</div>
                                    <div>
                                        <div class="user-name">
                                            {{ $user->name }}
                                            @if ($isMe) <span class="user-you">Vous</span> @endif
                                        </div>
                                        <div class="user-email">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="role-cell" x-data="{ editing: false }">
                                    <span @click="editing = true"
                                          x-show="!editing"
                                          class="role-badge {{ $roleCss }}">
                                        {{ $userRole ? ($userRole->label ?? ucfirst($userRole->name)) : 'Aucun rôle' }}
                                    </span>
                                    {{-- Sélecteur inline --}}
                                    <div x-show="editing" style="display:flex;align-items:center;gap:.4rem;" @click.outside="editing = false">
                                        <select wire:model="roleAssignValue"
                                                x-on:change="$wire.roleAssignUserId = {{ $user->id }}"
                                                class="role-select-inline">
                                            <option value="">— Choisir —</option>
                                            @foreach ($roles as $role)
                                                <option value="{{ $role->name }}" @selected($userRole?->name === $role->name)>
                                                    {{ $role->label ?? ucfirst($role->name) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button wire:click="quickAssignRole" class="btn-action btn-edit-act" style="padding:.25rem .5rem;">✓</button>
                                        <button @click="editing = false" class="btn-action btn-del-act" style="padding:.25rem .5rem;">✕</button>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="status-dot">
                                    <span class="dot {{ $statusColor }}"></span>
                                    {{ match($user->status ?? 'active') { 'active'=>'Actif','inactive'=>'Inactif','suspended'=>'Suspendu',default=>'Actif' } }}
                                </span>
                            </td>
                            <td style="font-family:'JetBrains Mono',monospace;font-size:11px;opacity:.5;">
                                {{ $user->created_at->format('d/m/Y') }}
                            </td>
                            <td>
                                <div class="actions-cell">
                                    @if (! $isMe)
                                        <button wire:click="toggleStatus({{ $user->id }})" class="btn-action btn-toggle-act">
                                            {{ ($user->status ?? 'active') === 'active' ? 'Désactiver' : 'Activer' }}
                                        </button>
                                    @endif
                                    <button wire:click="openEditUser({{ $user->id }})" class="btn-action btn-edit-act">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        Modifier
                                    </button>
                                    @if (! $isMe)
                                        <button wire:click="confirmDeleteUser({{ $user->id }})" class="btn-action btn-del-act">
                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16"/></svg>
                                            Supprimer
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align:center;padding:3rem;font-size:.875rem;color:var(--ink);opacity:.4;">
                                Aucun utilisateur trouvé.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($users->hasPages())
                <div class="pagination-bar">
                    <span class="pagination-info">{{ $users->firstItem() }}–{{ $users->lastItem() }} sur {{ $users->total() }}</span>
                    <div class="pagination-controls">
                        @if ($users->onFirstPage())
                            <button class="page-btn" disabled><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>Précédent</button>
                        @else
                            <button wire:click="previousPage" class="page-btn"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>Précédent</button>
                        @endif
                        <span class="page-current">{{ $users->currentPage() }} / {{ $users->lastPage() }}</span>
                        @if ($users->hasMorePages())
                            <button wire:click="nextPage" class="page-btn">Suivant<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
                        @else
                            <button class="page-btn" disabled>Suivant<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
                        @endif
                    </div>
                </div>
            @endif
        </div>

    @endif

    

    @php
        $moduleLabels = [ /* ... existant ... */ ];
    @endphp

    

    {{-- ══════════════════════════════════════════════ --}}
    {{-- TAB RÔLES & PERMISSIONS --}}
    {{-- ══════════════════════════════════════════════ --}}
    @if ($activeTab === 'roles')

        @php
            $moduleLabels = [
                'students'=>'Élèves','classes'=>'Classes','subjects'=>'Matières',
                'grades'=>'Notes','bulletins'=>'Bulletins','absences'=>'Absences',
                'finance'=>'Finances','staff'=>'Personnel','academic_years'=>'Années académiques',
                'school'=>'Configuration école','fees'=>'Frais','users'=>'Utilisateurs',
                'announcements'=>'Annonces',
            ];
        @endphp

        <div class="page-toolbar">
            <div></div>
            <button wire:click="openCreateRole" class="btn-primary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Nouveau rôle
            </button>
        </div>

        {{-- Formulaire rôle --}}
        @if ($showRoleForm)
            <div class="form-card" style="margin-bottom:1.5rem;">
                <div class="form-card-header">
                    <span class="form-card-title">{{ $editingRoleId ? 'Modifier le rôle' : 'Nouveau rôle' }}</span>
                    <button wire:click="$set('showRoleForm',false)" style="background:none;border:none;cursor:pointer;opacity:.4;">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="form-card-body">
                    <div class="form-grid-2" style="margin-bottom:1.25rem;">
                        <div class="form-field">
                            <label class="form-label">Identifiant technique *</label>
                            <input wire:model="roleName" type="text" class="form-input"
                                   placeholder="ex: responsable_financier"
                                   @if ($editingRoleId) readonly style="opacity:.6;" @endif>
                            <span style="font-size:.75rem;color:var(--ink);opacity:.4;">Lettres, chiffres, tirets — non modifiable après création.</span>
                            @error('roleName') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field">
                            <label class="form-label">Libellé affiché</label>
                            <input wire:model="roleLabel" type="text" class="form-input" placeholder="Responsable financier">
                        </div>
                    </div>

                    {{-- Permissions par module --}}
                    <div class="form-label" style="margin-bottom:.75rem;">Permissions</div>
                    @foreach ($permissions as $module => $modulePerms)
                        <div class="perm-section">
                            <div class="perm-section-header">
                                <span class="perm-section-title">{{ $moduleLabels[$module] ?? ucfirst($module) }}</span>
                                <button type="button" class="btn-toggle-all"
                                        wire:click="$set('rolePerms', array_merge(array_diff($rolePerms, $modulePerms->pluck('name')->toArray()), count(array_intersect($rolePerms, $modulePerms->pluck('name')->toArray())) === $modulePerms->count() ? [] : $modulePerms->pluck('name')->toArray()))">
                                    {{ count(array_intersect($rolePerms, $modulePerms->pluck('name')->toArray())) === $modulePerms->count() ? 'Tout décocher' : 'Tout cocher' }}
                                </button>
                            </div>
                            <div class="perm-section-body">
                                @foreach ($modulePerms as $perm)
                                    <label class="perm-check-row">
                                        <input type="checkbox"
                                               wire:model="rolePerms"
                                               value="{{ $perm->name }}"
                                               class="perm-checkbox">
                                        <span class="perm-check-label">{{ $perm->label ?? $perm->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="form-actions">
                        <button wire:click="$set('showRoleForm',false)" class="btn-cancel-sm">Annuler</button>
                        <button wire:click="saveRole" class="btn-save">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            {{ $editingRoleId ? 'Enregistrer' : 'Créer le rôle' }}
                        </button>
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Section permissions ── --}}
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header">
            <span class="card-title">Permissions</span>
            <button wire:click="$toggle('showPermForm')" class="btn-secondary">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Nouvelle permission
            </button>
        </div>

        {{-- Formulaire création permission --}}
        @if ($showPermForm)
            <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--line);background:var(--paper);animation:slideDown .15s ease;">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem;">
                    <div class="form-field">
                        <label class="form-label">Identifiant technique *</label>
                        <input wire:model="permName"
                               type="text"
                               class="form-input"
                               placeholder="ex: reports.view">
                        <span style="font-size:.75rem;color:var(--ink);opacity:.4;margin-top:2px;">
                            Format : module.action
                        </span>
                        @error('permName') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="form-field">
                        <label class="form-label">Libellé affiché *</label>
                        <input wire:model="permLabel"
                               type="text"
                               class="form-input"
                               placeholder="ex: Voir les rapports">
                        @error('permLabel') <span class="form-error">{{ $message }}</span> @enderror
                    </div>
                    <div style="display:flex;align-items:flex-end;gap:.65rem;">
                        <button wire:click="$set('showPermForm',false)" class="btn-cancel-sm">Annuler</button>
                        <button wire:click="savePermission" class="btn-save">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Créer
                        </button>
                    </div>
                </div>
                <div style="font-size:.8rem;color:var(--ink);opacity:.45;padding:.65rem .875rem;background:rgba(42,63,126,.04);border-radius:7px;border:1px solid rgba(42,63,126,.1);">
                    💡 La permission sera automatiquement disponible dans le formulaire d'édition des rôles.
                    Pour l'attribuer, modifie ensuite le rôle concerné.
                </div>
            </div>
        @endif

        {{-- Liste des permissions groupées par module --}}
        <div style="padding:1rem 1.5rem;">
            @foreach ($permissions as $module => $modulePerms)
                <div style="margin-bottom:1rem;">
                    <div style="font-family:'JetBrains Mono',monospace;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--ink);opacity:.4;margin-bottom:.4rem;">
                        {{ $moduleLabels[$module] ?? ucfirst($module) }}
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:.4rem;">
                        @foreach ($modulePerms as $perm)
                            @php
                                $isProtected = \Illuminate\Support\Str::startsWith($perm->name, [
                                    'students.','classes.','grades.','bulletins.',
                                    'absences.','finance.','school.','fees.',
                                    'users.','staff.','academic_years.',
                                ]);
                                $rolesWithPerm = $perm->roles->count();
                            @endphp
                            <div style="display:inline-flex;align-items:center;gap:.4rem;padding:3px 8px 3px 10px;border-radius:6px;background:var(--paper);border:1px solid var(--line);">
                                <span style="font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:600;color:var(--sidebar-soft);">
                                    {{ $perm->name }}
                                </span>
                                @if ($perm->label && $perm->label !== $perm->name)
                                    <span style="font-size:.75rem;color:var(--ink);opacity:.45;">
                                        — {{ $perm->label }}
                                    </span>
                                @endif
                                <span style="font-family:'JetBrains Mono',monospace;font-size:9px;padding:1px 5px;border-radius:3px;background:rgba(42,63,126,.07);color:var(--sidebar-soft);">
                                    {{ $rolesWithPerm }} rôle{{ $rolesWithPerm > 1 ? 's' : '' }}
                                </span>
                                @if (! $isProtected)
                                    <button wire:click="confirmDeletePerm({{ $perm->id }})"
                                            style="width:16px;height:16px;border-radius:3px;background:none;border:none;cursor:pointer;color:var(--accent-red);opacity:.4;padding:0;display:flex;align-items:center;justify-content:center;"
                                            onmouseover="this.style.opacity='1'"
                                            onmouseout="this.style.opacity='.4'">
                                        <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Suite normale : grille des rôles --}}
    <div class="page-toolbar">

    </div>

        {{-- Grille des rôles --}}
        <div class="roles-grid">
            @foreach ($roles as $role)
                @php
                    $isProtected = in_array($role->name, ['admin','directeur','comptable','enseignant','surveillant','parent']);
                    $rolePermsGrouped = $role->permissions->groupBy(fn ($p) => explode('.', $p->name)[0]);
                @endphp
                <div class="role-card">
                    <div class="role-card-header">
                        <div>
                            <div class="role-name-big">{{ $role->label ?? ucfirst($role->name) }}</div>
                            <div style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--ink);opacity:.4;">{{ $role->name }}</div>
                        </div>
                        <div style="text-align:right;">
                            <div class="role-users-count">{{ $role->users_count }} utilisateur{{ $role->users_count > 1 ? 's' : '' }}</div>
                            <div style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--sidebar-soft);opacity:.7;">{{ $role->permissions->count() }} permissions</div>
                        </div>
                    </div>
                    <div class="role-card-body">
                        @foreach ($rolePermsGrouped as $module => $modulePerms)
                            <div class="perms-group">
                                <div class="perms-group-label">{{ $moduleLabels[$module] ?? ucfirst($module) }}</div>
                                <div class="perms-list">
                                    @foreach ($modulePerms as $p)
                                        <span class="perm-chip">{{ explode('.', $p->name)[1] ?? $p->name }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="role-card-footer">
                        @if ($isProtected)
                            <span class="role-protected">Rôle système</span>
                        @endif
                        <button wire:click="openEditRole({{ $role->id }})" class="btn-action btn-edit-act">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Modifier
                        </button>
                        @if (! $isProtected)
                            <button wire:click="confirmDeleteRole({{ $role->id }})" class="btn-action btn-del-act">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M4 7h16"/></svg>
                                Supprimer
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

    @endif

    {{-- ── Modals ─────────────────────────────────────────────── --}}

    @if ($confirmDeleteId)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Supprimer cet utilisateur ?</div>
                <div class="modal-desc">Son compte sera supprimé définitivement. Cette action est irréversible.</div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeleteId',null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deleteUser" class="btn-modal-confirm">Supprimer</button>
                </div>
            </div>
        </div>
    @endif

    @if ($confirmDeleteRoleId)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Supprimer ce rôle ?</div>
                <div class="modal-desc">Les utilisateurs ayant ce rôle se retrouveront sans rôle assigné.</div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeleteRoleId',null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deleteRole" class="btn-modal-confirm">Supprimer</button>
                </div>
            </div>
        </div>
    @endif

    @if ($confirmDeletePermId)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Supprimer cette permission ?</div>
                <div class="modal-desc">
                    Elle sera retirée de tous les rôles qui la possèdent.
                    Les permissions système ne peuvent pas être supprimées.
                </div>
                <div class="modal-actions">
                    <button wire:click="$set('confirmDeletePermId',null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="deletePerm" class="btn-modal-confirm">Supprimer</button>
                </div>
            </div>
        </div>
    @endif

</div>
