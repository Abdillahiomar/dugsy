<?php

use App\Models\Guardian;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component
{
    public function mount(): void
{
    if (! auth()->user()->hasRole('parent')) {
        abort(403);
    }
}
    public function with(): array
    {
        // Trouver le tuteur lié à ce compte
        $guardian = Guardian::where('user_id', auth()->id())
            ->with(['students.currentSchoolYear.schoolClass.level'])
            ->first();

        $children = $guardian?->students ?? collect();

        return compact('guardian', 'children');
    }
}; ?>

<div>
    <div style="margin-bottom:1.5rem;">
        <h1 style="font-family:'Fraunces',serif;font-size:1.5rem;font-weight:600;color:var(--ink);">
            Bonjour, {{ auth()->user()->name }}
        </h1>
        <p style="font-size:.875rem;color:var(--ink);opacity:.5;margin-top:.25rem;">
            Suivi scolaire de vos enfants
        </p>
    </div>

    @if ($children->isEmpty())
        <div style="text-align:center;padding:3rem;color:var(--ink);opacity:.4;">
            Aucun enfant associé à votre compte. Contactez l'administration.
        </div>
    @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem;">
            @foreach ($children as $student)
                @php
                    $ssy   = $student->currentSchoolYear;
                    $class = $ssy?->schoolClass;
                @endphp
                <a href="{{ route('parent_students.show', $student) }}"
                   style="display:block;border-radius:12px;border:1px solid var(--line);background:var(--paper-raised);overflow:hidden;text-decoration:none;transition:border-color .15s,box-shadow .15s;"
                   onmouseover="this.style.borderColor='rgba(42,63,126,.3)';this.style.boxShadow='0 4px 16px rgba(42,63,126,.1)'"
                   onmouseout="this.style.borderColor='var(--line)';this.style.boxShadow='none'">

                    <div style="padding:1.25rem;border-bottom:1px solid var(--line);background:var(--sidebar);display:flex;align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:.875rem;">
                            @if ($student->photo_path)
                                <img src="{{ asset('storage/'.$student->photo_path) }}"
                                     style="width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.3);">
                            @else
                                <div style="width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-family:'JetBrains Mono',monospace;font-size:14px;font-weight:700;color:#FFFFFF;">
                                    {{ strtoupper(substr($student->first_name,0,1).substr($student->last_name,0,1)) }}
                                </div>
                            @endif
                            <div>
                                <div style="font-family:'Fraunces',serif;font-size:1.1rem;font-weight:600;color:#FFFFFF;">
                                    {{ $student->fullName() }}
                                </div>
                                <div style="font-size:.8rem;color:rgba(255,255,255,.65);">
                                    {{ $student->matricule }}
                                </div>
                            </div>
                        </div>
                        <svg fill="none" stroke="rgba(255,255,255,.6)" stroke-width="2" viewBox="0 0 24 24" style="width:18px;height:18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>

                    <div style="padding:1rem 1.25rem;display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                        <div>
                            <div style="font-family:'JetBrains Mono',monospace;font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--ink);opacity:.4;margin-bottom:3px;">Classe</div>
                            <div style="font-size:.875rem;font-weight:600;color:var(--ink);">{{ $class?->name ?? '—' }}</div>
                        </div>
                        <div>
                            <div style="font-family:'JetBrains Mono',monospace;font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--ink);opacity:.4;margin-bottom:3px;">Niveau</div>
                            <div style="font-size:.875rem;font-weight:600;color:var(--ink);">{{ $class?->level?->name ?? '—' }}</div>
                        </div>
                        <div>
                            <div style="font-family:'JetBrains Mono',monospace;font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--ink);opacity:.4;margin-bottom:3px;">Statut</div>
                            <div style="font-size:.875rem;font-weight:600;color:{{ $student->status === 'active' ? '#166534' : 'var(--accent-red)' }};">
                                {{ $student->status === 'active' ? 'Inscrit' : 'Inactif' }}
                            </div>
                        </div>
                        <div>
                            <div style="font-family:'JetBrains Mono',monospace;font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--ink);opacity:.4;margin-bottom:3px;">Année</div>
                            <div style="font-size:.875rem;font-weight:600;color:var(--ink);">{{ $ssy?->academicYear?->label ?? '—' }}</div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>