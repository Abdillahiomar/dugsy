<?php

use App\Models\AcademicYear;
use App\Models\DiscountType;
use App\Models\FeeStructure;
use App\Models\InstallmentPlan;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Services\AcademicYearService;
use App\Services\EnrollmentService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component
{
    public Student $student;

    public string $school_class_id    = '';
    public string $installment_plan_id = '';
    public array  $discount_ids       = [];

    public bool   $done   = false;
    public ?string $error = null;

    public function mount(Student $student): void
    {
        $this->student = $student;

        // Pré-remplir avec la classe de l'année précédente si possible
        $year = AcademicYearService::current();
        if ($year) {
            $lastSsy = StudentSchoolYear::where('student_id', $student->id)
                ->whereHas('academicYear', fn ($q) => $q->where('id', '!=', $year->id))
                ->latest()
                ->with('schoolClass.level')
                ->first();

            // On cherche la classe équivalente dans la nouvelle année
            if ($lastSsy?->schoolClass) {
                $equivalentClass = SchoolClass::where('school_id', $student->school_id)
                    ->where('academic_year_id', $year->id)
                    ->where('level_id', $lastSsy->schoolClass->level_id)
                    ->first();

                $this->school_class_id = (string) ($equivalentClass?->id ?? '');
            }
        }
    }

    public function updatedSchoolClassId(): void
    {
        $this->error = null;
    }

    public function reenroll(): void
    {
        $this->validate([
            'school_class_id' => 'required|exists:school_classes,id',
        ]);

        $year = AcademicYearService::current();
        $schoolId = auth()->user()->school_id;

        $admission = \App\Models\SchoolAdmissionConfig::where('school_id', $schoolId)->first();
        if ($admission && ! $admission->is_enrollment_open) {
            $this->addError('enroll', 'Les réinscriptions sont actuellement fermées.');
            return;
        }

        if (! $year) {
            $this->error = "Aucune année académique active. Veuillez en activer une d'abord.";
            return;
        }

        // Vérifier si déjà inscrit cette année
        $alreadyEnrolled = StudentSchoolYear::where('student_id', $this->student->id)
            ->where('academic_year_id', $year->id)
            ->exists();

        if ($alreadyEnrolled) {
            $this->error = "Cet élève est déjà inscrit pour l'année {$year->label}.";
            return;
        }

        $plan = $this->installment_plan_id
            ? InstallmentPlan::find($this->installment_plan_id)
            : null;

        $service = new EnrollmentService();
        $service->enroll(
            $this->student,
            $year,
            (int) $this->school_class_id,
            $plan,
            $this->discount_ids,
            true
        );

        // Mettre le statut à jour
        $this->student->update(['status' => 'active']);
        $this->done = true;
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;
        $year     = AcademicYearService::current();

        $classes = SchoolClass::where('school_id', $schoolId)
            ->where('academic_year_id', $year?->id)
            ->with('level')
            ->get()
            ->groupBy('level.cycle')
            ->sortKeys();

        $plans = InstallmentPlan::where('school_id', $schoolId)
            ->where('is_active', true)->with('items')->get();

        $discountTypes = DiscountType::where('school_id', $schoolId)
            ->where('is_active', true)->get();

        // Historique scolarité de l'élève
        $history = StudentSchoolYear::where('student_id', $this->student->id)
            ->with(['academicYear', 'schoolClass.level'])
            ->latest()
            ->get();

        // Prévisualisation financière
        $preview = null;
        $selectedClass = $this->school_class_id
            ? SchoolClass::find($this->school_class_id)
            : null;

        $admission = \App\Models\SchoolAdmissionConfig::where('school_id', $schoolId)->first();

        $requiredDocs = \App\Models\RequiredDocument::where('school_id', $schoolId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('applies_to','all')->orWhere('applies_to','reenroll'))
            ->orderBy('order')
            ->get();

        if ($selectedClass && $year) {
            $plan = $this->installment_plan_id
                ? $plans->firstWhere('id', $this->installment_plan_id)
                : null;

            $service = new EnrollmentService();
            $preview = $service->previewInvoices(
                $schoolId,
                $selectedClass->level_id,
                $year,
                $plan,
                $this->discount_ids,
                true
            );
        }

        return compact('year', 'classes', 'plans', 'discountTypes', 'history', 'preview', 'admission', 'requiredDocs');
    }
}; ?>

<style>
    /* Breadcrumb */
    .breadcrumb { display:flex; align-items:center; gap:.5rem; font-size:.8125rem; margin-bottom:1.5rem; color:var(--ink); opacity:.5; }
    .breadcrumb a { color:inherit; text-decoration:none; }
    .breadcrumb a:hover { color:var(--sidebar-soft); opacity:1; }
    .breadcrumb svg { width:14px; height:14px; }
    .breadcrumb-current { opacity:1; font-weight:600; color:var(--ink); }

    /* Layout */
    .reenroll-grid { display:grid; grid-template-columns:1fr 320px; gap:1.5rem; align-items:start; }
    @media (max-width:900px) { .reenroll-grid { grid-template-columns:1fr; } }

    /* Cards */
    .card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.25rem; }
    .card:last-child { margin-bottom:0; }
    .card-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; gap:.65rem; }
    .card-header-icon { width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .card-header-icon svg { width:15px; height:15px; }
    .card-title { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); }
    .card-body { padding:1.25rem 1.5rem; }

    /* Profil élève */
    .student-profile { display:flex; align-items:center; gap:1rem; padding:1rem 1.5rem; background:var(--sidebar); }
    .student-avatar { width:48px; height:48px; border-radius:50%; background:var(--accent); color:var(--sidebar); font-family:'JetBrains Mono',monospace; font-size:14px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .student-name { font-family:'Fraunces',serif; font-size:1.15rem; font-weight:600; color:#FFFFFF; }
    .student-meta { font-size:.8125rem; color:rgba(255,255,255,.6); margin-top:2px; }

    /* Formulaire */
    .form-field { display:flex; flex-direction:column; gap:.35rem; margin-bottom:1rem; }
    .form-field:last-of-type { margin-bottom:0; }
    .form-label { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.5; }
    .form-select-inp { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; transition:border-color .15s, box-shadow .15s; }
    .form-select-inp:focus { border-color:var(--sidebar-soft); box-shadow:0 0 0 3px rgba(42,63,126,.08); }
    .form-error { font-size:.75rem; color:var(--accent-red); margin-top:.2rem; }

    /* Classes groupées */
    .classes-by-cycle { display:flex; flex-direction:column; gap:.75rem; }
    .cycle-group { }
    .cycle-group-label { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; margin-bottom:.4rem; }
    .class-options { display:grid; grid-template-columns:repeat(auto-fill, minmax(120px, 1fr)); gap:.4rem; }
    .class-option {
        padding:.5rem .75rem; border-radius:7px; border:1.5px solid var(--line); background:var(--paper);
        font-size:.8125rem; font-weight:500; text-align:center; cursor:pointer;
        transition:all .12s; color:var(--ink);
    }
    .class-option.selected { border-color:var(--sidebar); background:rgba(42,63,126,.08); color:var(--sidebar); }
    .class-option.full     { opacity:.5; cursor:not-allowed; }
    .class-capacity { font-size:.7rem; opacity:.5; margin-top:1px; }

    /* Plan selector */
    .plan-options { display:flex; flex-direction:column; gap:.5rem; }
    .plan-option { display:flex; align-items:center; gap:.75rem; padding:.65rem 1rem; border-radius:8px; border:1.5px solid var(--line); background:var(--paper); cursor:pointer; transition:all .12s; }
    .plan-option.selected { border-color:var(--sidebar); background:rgba(42,63,126,.05); }
    .plan-radio { width:14px; height:14px; border-radius:50%; border:2px solid var(--line); flex-shrink:0; transition:all .12s; display:flex; align-items:center; justify-content:center; }
    .plan-option.selected .plan-radio { border-color:var(--sidebar); }
    .plan-option.selected .plan-radio::after { content:''; width:6px; height:6px; border-radius:50%; background:var(--sidebar); }
    .plan-name-text { font-size:.875rem; font-weight:600; color:var(--ink); }
    .plan-pcts { font-size:.75rem; color:var(--ink); opacity:.5; }

    /* Remises */
    .discount-options { display:flex; flex-direction:column; gap:.5rem; }
    .discount-item { display:flex; align-items:center; justify-content:space-between; padding:.65rem 1rem; border-radius:8px; border:1.5px solid var(--line); background:var(--paper); cursor:pointer; transition:all .12s; }
    .discount-item.selected { border-color:var(--sidebar-soft); background:rgba(42,63,126,.05); }
    .discount-item-left { display:flex; align-items:center; gap:.65rem; }
    .discount-check { width:16px; height:16px; border-radius:4px; border:1.5px solid var(--line); background:var(--paper-raised); appearance:none; cursor:pointer; flex-shrink:0; transition:all .15s; position:relative; }
    .discount-check:checked { background:var(--sidebar); border-color:var(--sidebar); }
    .discount-check:checked::after { content:''; position:absolute; top:2px; left:4.5px; width:4px; height:7px; border:2px solid #FFFFFF; border-top:none; border-left:none; transform:rotate(45deg); }
    .discount-name-text { font-size:.875rem; font-weight:600; color:var(--ink); }
    .chip { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; padding:1px 6px; border-radius:3px; text-transform:uppercase; }
    .chip-social { background:rgba(99,102,241,.1); color:#3730A3; }
    .chip-approval { background:rgba(232,168,56,.12); color:#8A6010; }
    .discount-val { font-family:'JetBrains Mono',monospace; font-size:13px; font-weight:700; color:var(--sidebar-soft); }

    /* Historique */
    .history-row { display:flex; align-items:center; justify-content:space-between; padding:.65rem 0; border-bottom:1px solid var(--line); font-size:.875rem; }
    .history-row:last-child { border-bottom:none; }
    .history-year { font-weight:600; color:var(--ink); }
    .history-class { color:var(--ink); opacity:.6; }
    .history-status { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:2px 7px; border-radius:4px; }
    .hs-enrolled   { background:rgba(42,63,126,.1); color:var(--sidebar-soft); }
    .hs-repeated   { background:rgba(232,168,56,.15); color:#8A6010; }
    .hs-transferred{ background:rgba(224,92,58,.12); color:#C04020; }

    /* Prévisualisation factures */
    .invoice-preview { display:flex; flex-direction:column; gap:.4rem; }
    .invoice-row { display:flex; align-items:center; justify-content:space-between; padding:.55rem .875rem; border-radius:7px; border:1px solid var(--line); background:var(--paper); gap:.5rem; }
    .invoice-row.type-registration { border-color:rgba(232,168,56,.3); background:rgba(232,168,56,.04); }
    .invoice-row-label { font-size:.8125rem; font-weight:500; color:var(--ink); }
    .invoice-row-due { font-size:.7rem; color:var(--ink); opacity:.4; font-family:'JetBrains Mono',monospace; }
    .invoice-row-amount { font-family:'JetBrains Mono',monospace; font-size:.875rem; font-weight:700; color:var(--sidebar-soft); flex-shrink:0; }
    .invoice-total { display:flex; justify-content:space-between; align-items:center; padding:.65rem .875rem; border-radius:8px; background:var(--sidebar); margin-top:.25rem; }
    .invoice-total-label { font-size:.875rem; font-weight:600; color:rgba(255,255,255,.8); }
    .invoice-total-amount { font-family:'JetBrains Mono',monospace; font-size:.9rem; font-weight:700; color:#FFFFFF; }
    .discount-line { font-size:.75rem; color:#166534; text-align:right; margin-top:.2rem; }

    /* Bouton confirmer */
    .btn-confirm { display:inline-flex; align-items:center; gap:6px; padding:.55rem 1.5rem; border-radius:8px; background:#166534; color:#FFFFFF; font-size:.875rem; font-weight:700; font-family:'Inter',sans-serif; border:none; cursor:pointer; transition:background .15s; }
    .btn-confirm:hover { background:#14532d; }
    .btn-confirm svg { width:16px; height:16px; }
    .btn-cancel { display:inline-flex; align-items:center; gap:6px; padding:.5rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; text-decoration:none; }

    /* Error & success */
    .alert-error { display:flex; align-items:center; gap:.65rem; padding:.75rem 1rem; border-radius:8px; background:rgba(224,92,58,.08); border:1px solid rgba(224,92,58,.2); color:var(--accent-red); font-size:.875rem; margin-bottom:1rem; }
    .alert-error svg { width:16px; height:16px; flex-shrink:0; }
    .success-screen { text-align:center; padding:3rem 2rem; }
    .success-icon { width:64px; height:64px; margin:0 auto 1.25rem; background:rgba(30,120,80,.1); border-radius:50%; display:flex; align-items:center; justify-content:center; }
    .success-icon svg { width:32px; height:32px; color:#166534; }
    .success-title { font-family:'Fraunces',serif; font-size:1.4rem; font-weight:600; color:var(--ink); margin-bottom:.5rem; }
    .success-sub { font-size:.875rem; color:var(--ink); opacity:.6; margin-bottom:1.5rem; }
    .success-actions { display:flex; justify-content:center; gap:.75rem; flex-wrap:wrap; }
</style>

<div>
    {{-- Breadcrumb --}}
    <div class="breadcrumb">
        <a href="{{ route('students.index') }}">Elèves</a>
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        <a href="{{ route('students.edit', $student) }}">{{ $student->fullName() }}</a>
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        <span class="breadcrumb-current">Réinscription {{ $year?->label }}</span>
    </div>

    @if ($done)
        <div class="success-screen">
            <div class="success-icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="success-title">Réinscription confirmée !</div>
            <div class="success-sub">{{ $student->fullName() }} est réinscrit pour l'année {{ $year?->label }}. Les factures ont été générées.</div>
            <div class="success-actions">
                <a href="{{ route('students.index') }}" class="btn-cancel">Retour à la liste</a>
                <a href="{{ route('students.show', $student) }}" class="btn-confirm" style="background:var(--sidebar);">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Fiche de l'élève
                </a>
            </div>
        </div>
    @else

        {{-- Profil élève --}}
        <div class="card" style="margin-bottom:1.5rem;">
            <div class="student-profile">
                <div class="student-avatar">
                    {{ strtoupper(substr($student->first_name,0,1).substr($student->last_name,0,1)) }}
                </div>
                <div>
                    <div class="student-name">{{ $student->fullName() }}</div>
                    <div class="student-meta">
                        {{ $student->matricule }} · {{ $student->gender === 'M' ? 'Masculin' : 'Féminin' }}
                        @if ($student->birth_date) · Né(e) le {{ $student->birth_date->format('d/m/Y') }} @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($error)
            <div class="alert-error">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                {{ $error }}
            </div>
        @endif

        <div class="reenroll-grid">

            {{-- Formulaire --}}
            <div>
                {{-- Classe --}}
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon" style="background:rgba(232,168,56,.12);color:#8A6010;">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/></svg>
                        </div>
                        <span class="card-title">Classe — {{ $year?->label }}</span>
                    </div>
                    <div class="card-body">
                        <div class="classes-by-cycle">
                            @forelse ($classes as $cycle => $cycleClasses)
                                <div class="cycle-group">
                                    <div class="cycle-group-label">{{ $cycle }}</div>
                                    <div class="class-options">
                                        @foreach ($cycleClasses as $class)
                                            @php
                                                $enrolled  = $class->studentSchoolYears()->count();
                                                $isFull    = $class->capacity && $enrolled >= $class->capacity;
                                            @endphp
                                            <div wire:click="{{ $isFull ? '' : '$set(\'school_class_id\', \''.$class->id.'\')' }}"
                                                 class="class-option {{ $school_class_id == $class->id ? 'selected' : '' }} {{ $isFull ? 'full' : '' }}">
                                                <div>{{ $class->name }}</div>
                                                @if ($class->capacity)
                                                    <div class="class-capacity">{{ $enrolled }}/{{ $class->capacity }}</div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @empty
                                <div style="font-size:.875rem;color:var(--ink);opacity:.45;text-align:center;padding:1rem 0;">
                                    Aucune classe disponible pour l'année {{ $year?->label }}.
                                </div>
                            @endforelse
                        </div>
                        @error('school_class_id') <div style="color:var(--accent-red);font-size:.75rem;margin-top:.75rem;">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- Plan d'échéancier --}}
                @if ($plans->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <div class="card-header-icon" style="background:rgba(42,63,126,.08);color:var(--sidebar-soft);">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <span class="card-title">Plan d'échéancier</span>
                        </div>
                        <div class="card-body">
                            <div class="plan-options">
                                <label class="plan-option {{ !$installment_plan_id ? 'selected' : '' }}" wire:click="$set('installment_plan_id', '')">
                                    <div class="plan-radio"></div>
                                    <div>
                                        <div class="plan-name-text">Plan par défaut du niveau</div>
                                        <div class="plan-pcts">Selon la configuration des frais</div>
                                    </div>
                                </label>
                                @foreach ($plans as $plan)
                                    <label class="plan-option {{ $installment_plan_id == $plan->id ? 'selected' : '' }}"
                                           wire:click="$set('installment_plan_id', '{{ $plan->id }}')">
                                        <div class="plan-radio"></div>
                                        <div>
                                            <div class="plan-name-text">{{ $plan->name }}</div>
                                            <div class="plan-pcts">{{ $plan->items->map(fn($i) => $i->percentage.'%')->join(' · ') }}</div>
                                        </div>
                                        @if ($plan->is_default)
                                            <span style="margin-left:auto;font-family:'JetBrains Mono',monospace;font-size:10px;font-weight:600;padding:2px 7px;border-radius:4px;background:rgba(232,168,56,.15);color:#8A6010;">Défaut</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Remises --}}
                @if ($discountTypes->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <div class="card-header-icon" style="background:rgba(99,102,241,.1);color:#3730A3;">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M17 17h.01M5.5 5.5l13 13M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <span class="card-title">Remises & Exonérations</span>
                        </div>
                        <div class="card-body">
                            <div class="discount-options">
                                @foreach ($discountTypes as $d)
                                    <label class="discount-item {{ in_array($d->id, $discount_ids) ? 'selected' : '' }}">
                                        <div class="discount-item-left">
                                            <input type="checkbox" wire:model.live="discount_ids" value="{{ $d->id }}" class="discount-check">
                                            <div>
                                                <div class="discount-name-text">{{ $d->name }}</div>
                                                <div style="display:flex;gap:.3rem;margin-top:2px;flex-wrap:wrap;">
                                                    @if ($d->is_social) <span class="chip chip-social">Sociale</span> @endif
                                                    @if ($d->requires_approval) <span class="chip chip-approval">Approbation</span> @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="discount-val">{{ $d->formatted_value }}</div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Boutons --}}
                <div style="display:flex;align-items:center;justify-content:space-between;margin-top:1rem;">
                    <a href="{{ route('students.edit', $student) }}" class="btn-cancel">Annuler</a>
                    <button wire:click="reenroll" class="btn-confirm">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Confirmer la réinscription
                    </button>
                </div>
            </div>

            {{-- Colonne droite --}}
            <div>
                {{-- Factures prévisionnelles --}}
                @if ($preview)
                    <div class="card" style="margin-bottom:1.25rem;">
                        <div class="card-header">
                            <span class="card-title">Factures à générer</span>
                        </div>
                        <div class="card-body">
                            <div style="font-family:'JetBrains Mono',monospace;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:var(--ink);opacity:.4;margin-bottom:.75rem;">
                                {{ $preview['plan_name'] }}
                            </div>
                            <div class="invoice-preview">
                                @foreach ($preview['invoices'] as $inv)
                                    <div class="invoice-row {{ $inv['type'] === 'registration' ? 'type-registration' : '' }}">
                                        <div>
                                            <div class="invoice-row-label">{{ $inv['label'] }}</div>
                                            @if ($inv['due_at'] !== '—')
                                                <div class="invoice-row-due">{{ $inv['due_at'] }}</div>
                                            @endif
                                        </div>
                                        <div class="invoice-row-amount">{{ number_format($inv['amount'], 0, ',', ' ') }} DJF</div>
                                    </div>
                                @endforeach
                                <div class="invoice-total">
                                    <span class="invoice-total-label">Total</span>
                                    <span class="invoice-total-amount">{{ number_format($preview['total'], 0, ',', ' ') }} DJF</span>
                                </div>
                                @if ($preview['total_discount'] > 0)
                                    <div class="discount-line">Remise : -{{ number_format($preview['total_discount'], 0, ',', ' ') }} DJF</div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Historique scolaire --}}
                @if ($history->isNotEmpty())
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Historique scolaire</span>
                        </div>
                        <div class="card-body" style="padding-top:.75rem;padding-bottom:.75rem;">
                            @foreach ($history as $h)
                                @php
                                    $statusCss = match($h->status) { 'enrolled' => 'hs-enrolled', 'transferred' => 'hs-transferred', 'repeated' => 'hs-repeated', default => 'hs-enrolled' };
                                    $statusLabel = match($h->status) { 'enrolled' => 'Inscrit', 'transferred' => 'Transféré', 'repeated' => 'Redoublant', default => $h->status };
                                @endphp
                                <div class="history-row">
                                    <div>
                                        <div class="history-year">{{ $h->academicYear->label }}</div>
                                        <div class="history-class">{{ $h->schoolClass->name }} — {{ $h->schoolClass->level?->name }}</div>
                                    </div>
                                    <span class="history-status {{ $statusCss }}">{{ $statusLabel }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
