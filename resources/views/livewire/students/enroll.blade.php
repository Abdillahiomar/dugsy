<?php

use App\Models\AcademicYear;
use App\Models\DiscountType;
use App\Models\Guardian;
use App\Models\InstallmentPlan;
use App\Models\Level;
use App\Models\RequiredDocument;
use App\Models\SchoolAdmissionConfig;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentSchoolYear;
use App\Services\AcademicYearService;
use App\Services\EnrollmentService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public int  $step       = 1;
    public int  $totalSteps = 5;

    // ── Étape 1 : Informations personnelles ──────────────────────
    public string $first_name  = '';
    public string $last_name   = '';
    public string $birth_date  = '';
    public string $birth_place = '';
    public string $gender      = '';
    public $photo              = null;

    // ── Étape 2 : Tuteur ─────────────────────────────────────────
    public string $guardian_mode   = 'existing';
    public string $guardian_id     = '';
    public string $g_first_name    = '';
    public string $g_last_name     = '';
    public string $g_phone         = '';
    public string $g_email         = '';
    public string $g_profession    = '';
    public string $g_relationship  = 'pere';

    // ── Étape 3 : Académique ─────────────────────────────────────
    public string $level_id        = '';
    public string $school_class_id = '';

    // ── Étape 4 : Documents ──────────────────────────────────────
    // [required_document_id => file upload]
    public array $docFiles = [];

    // ── Étape 5 : Financier ───────────────────────────────────────
    public string $installment_plan_id = '';
    public array  $discount_ids        = [];

    // Résultat
    public bool   $done         = false;
    public ?int   $newStudentId = null;
    public ?int   $newSsyId     = null;

    public function nextStep(): void
    {
        $this->validateStep($this->step);
        $this->step++;
    }

    public function prevStep(): void
    {
        if ($this->step > 1) $this->step--;
    }

    public function goToStep(int $s): void
    {
        if ($s < $this->step) $this->step = $s;
    }

    private function validateStep(int $step): void
    {
        match ($step) {
            1 => $this->validate([
                'first_name' => 'required|string|max:100',
                'last_name'  => 'required|string|max:100',
                'gender'     => 'required|in:M,F',
                'birth_date' => 'nullable|date',
                'photo'      => 'nullable|image|max:2048',
            ]),
            2 => $this->guardian_mode === 'new'
                ? $this->validate([
                    'g_last_name'  => 'required|string|max:100',
                    'g_first_name' => 'required|string|max:100',
                    'g_phone'      => 'required|string|max:30',
                ])
                : null,
            3 => $this->validate([
                'level_id'        => 'required|exists:levels,id',
                'school_class_id' => 'required|exists:school_classes,id',
            ]),
            default => null,
        };
    }

    public function updatedLevelId(): void
    {
        $this->school_class_id = '';
    }

    public function enroll(): void
    {
        $year     = AcademicYearService::current();
        $schoolId = auth()->user()->school_id;

        // 1. Vérifier l'admission ouverte
        $admission = SchoolAdmissionConfig::where('school_id', $schoolId)->first();
        if ($admission && ! $admission->is_enrollment_open) {
            $this->addError('enroll', 'Les inscriptions sont actuellement fermées.');
            return;
        }

        // 2. Créer l'élève
        $count     = Student::where('school_id', $schoolId)->count() + 1;
        $matricule = sprintf('ELV-%d-%04d-%s', $schoolId, $count, now()->format('Y'));

        $photoPath = $this->photo
            ? $this->photo->store('students/photos', 'public')
            : null;

        $student = Student::create([
            'school_id'  => $schoolId,
            'matricule'  => $matricule,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'birth_date' => $this->birth_date ?: null,
            'birth_place'=> $this->birth_place ?: null,
            'gender'     => $this->gender,
            'photo_path' => $photoPath,
            'status'     => 'active',
        ]);

        // 3. Tuteur
        $guardianId = $this->guardian_id ?: null;
        if ($this->guardian_mode === 'new') {
            $guardian = \App\Models\Guardian::create([
                'school_id'  => $schoolId,
                'first_name' => $this->g_first_name,
                'last_name'  => $this->g_last_name,
                'phone'      => $this->g_phone,
                'email'      => $this->g_email ?: null,
                'profession' => $this->g_profession ?: null,
            ]);
            $guardianId = $guardian->id;
        }
        if ($guardianId) {
            $student->guardians()->attach($guardianId, [
                'relationship'       => $this->g_relationship,
                'is_primary_contact' => true,
            ]);
        }

        // 4. Inscrire + générer factures
        $plan    = $this->installment_plan_id
            ? InstallmentPlan::find($this->installment_plan_id)
            : null;

        $service = new EnrollmentService();
        $ssy     = $service->enroll($student, $year, (int) $this->school_class_id, $plan, $this->discount_ids, false);

        // 5. Sauvegarder les documents
        foreach ($this->docFiles as $docId => $file) {
            if (! $file) continue;
            $path = $file->store('students/documents', 'public');
            StudentDocument::create([
                'student_school_year_id' => $ssy->id,
                'required_document_id'   => $docId,
                'file_path'              => $path,
                'original_name'          => $file->getClientOriginalName(),
                'status'                 => 'provided',
                'provided_at'            => now(),
            ]);
        }

        // Documents manquants → créer en status "pending"
        $allRequired = RequiredDocument::where('school_id', $schoolId)
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('applies_to','all')->orWhere('applies_to','new'))
            ->get();

        foreach ($allRequired as $doc) {
            if (! isset($this->docFiles[$doc->id]) || ! $this->docFiles[$doc->id]) {
                StudentDocument::firstOrCreate([
                    'student_school_year_id' => $ssy->id,
                    'required_document_id'   => $doc->id,
                ], ['status' => 'pending']);
            }
        }

        $this->newStudentId = $student->id;
        $this->newSsyId     = $ssy->id;
        $this->done         = true;
    }

    public function with(): array
    {
        $schoolId  = auth()->user()->school_id;
        $year      = AcademicYearService::current();

        $levels = Level::where('school_id', $schoolId)->orderBy('order')->get();

        $classes = $this->level_id
            ? SchoolClass::where('school_id', $schoolId)
                ->where('academic_year_id', $year?->id)
                ->where('level_id', $this->level_id)
                ->get()
            : collect();

        $guardians = Guardian::where('school_id', $schoolId)->orderBy('last_name')->get();

        $plans = InstallmentPlan::where('school_id', $schoolId)
            ->where('is_active', true)->with('items')->get();

        $discountTypes = DiscountType::where('school_id', $schoolId)
            ->where('is_active', true)->get();

        // Documents requis pour l'inscription (step 4)
        $requiredDocs = collect();
        if ($this->level_id) {
            $requiredDocs = RequiredDocument::where('school_id', $schoolId)
                ->where('is_active', true)
                ->where(fn ($q) => $q->where('applies_to','all')->orWhere('applies_to','new'))
                ->where(function ($q) {
                    $q->whereNull('applies_to_levels')
                      ->orWhereJsonContains('applies_to_levels', (int) $this->level_id);
                })
                ->orderBy('order')
                ->get();
        }

        // Preview financière
        $preview = null;
        if ($this->level_id && $year) {
            $plan = $this->installment_plan_id
                ? $plans->firstWhere('id', (int) $this->installment_plan_id)
                : null;
            $service = new EnrollmentService();
            $preview = $service->previewInvoices($schoolId, (int) $this->level_id, $year, $plan, $this->discount_ids, false);
        }

        $admission = SchoolAdmissionConfig::where('school_id', $schoolId)->first();

        return compact('year','levels','classes','guardians','plans','discountTypes','requiredDocs','preview','admission');
    }
}; ?>

<style>
    /* Stepper */
    .stepper { display:flex; align-items:center; margin-bottom:2rem; gap:0; }
    .step-item { display:flex; align-items:center; flex:1; }
    .step-item:last-child { flex:0; }
    .step-col { display:flex; flex-direction:column; align-items:center; }
    .step-circle { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:700; flex-shrink:0; cursor:pointer; transition:all .2s; border:2px solid var(--line); background:var(--paper-raised); color:var(--ink); opacity:.4; }
    .step-circle.done   { background:var(--sidebar); border-color:var(--sidebar); color:#FFFFFF; opacity:1; }
    .step-circle.active { background:var(--accent); border-color:var(--accent); color:var(--sidebar); opacity:1; }
    .step-circle svg { width:14px; height:14px; }
    .step-line { flex:1; height:2px; background:var(--line); margin:0 6px; }
    .step-line.done { background:var(--sidebar); }
    .step-label { font-size:11px; font-weight:500; color:var(--ink); opacity:.4; margin-top:4px; white-space:nowrap; text-align:center; }
    .step-label.active { opacity:1; color:var(--sidebar-soft); }
    .step-label.done   { opacity:.7; }

    /* Layout */
    .enroll-grid { display:grid; grid-template-columns:1fr 320px; gap:1.5rem; align-items:start; }
    @media(max-width:900px) { .enroll-grid { grid-template-columns:1fr; } }

    /* Card */
    .card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.25rem; }
    .card:last-child { margin-bottom:0; }
    .card-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; gap:.65rem; }
    .card-header-icon { width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .card-header-icon svg { width:15px; height:15px; }
    .card-title { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); }
    .card-body { padding:1.25rem 1.5rem; }

    /* Formulaire */
    .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
    .form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem; margin-bottom:1rem; }
    @media(max-width:600px) { .form-grid-2,.form-grid-3 { grid-template-columns:1fr; } }
    .form-field { display:flex; flex-direction:column; gap:.35rem; }
    .form-label { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.5; }
    .form-input, .form-select-inp { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; transition:border-color .15s; }
    .form-input:focus, .form-select-inp:focus { border-color:var(--sidebar-soft); box-shadow:0 0 0 3px rgba(42,63,126,.08); }
    .form-error { font-size:.75rem; color:var(--accent-red); margin-top:.2rem; }
    .form-hint  { font-size:.75rem; color:var(--ink); opacity:.4; margin-top:2px; }

    /* Genre */
    .radio-group { display:flex; gap:.5rem; }
    .radio-btn { flex:1; padding:.5rem; border-radius:7px; border:1.5px solid var(--line); background:var(--paper); font-size:.8125rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; text-align:center; transition:all .12s; }
    .radio-btn.m-active { border-color:var(--sidebar); background:rgba(30,45,90,.07); color:var(--sidebar); }
    .radio-btn.f-active { border-color:#B0307A; background:rgba(176,48,122,.07); color:#B0307A; }

    /* Mode tuteur */
    .mode-toggle { display:flex; gap:.5rem; margin-bottom:1rem; }
    .mode-btn { flex:1; padding:.55rem; border-radius:8px; border:1.5px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; text-align:center; transition:all .12s; }
    .mode-btn.active { border-color:var(--sidebar); background:rgba(30,45,90,.07); color:var(--sidebar); }

    /* Documents */
    .doc-list { display:flex; flex-direction:column; gap:.875rem; }
    .doc-item { border:1px solid var(--line); border-radius:10px; overflow:hidden; }
    .doc-item-header { padding:.75rem 1rem; background:var(--paper); display:flex; align-items:center; gap:.65rem; }
    .doc-icon { width:32px; height:32px; border-radius:7px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .doc-icon svg { width:16px; height:16px; }
    .doc-item-name { font-weight:600; font-size:.875rem; color:var(--ink); }
    .doc-item-desc { font-size:.8rem; color:var(--ink); opacity:.5; }
    .badge-required { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; padding:1px 6px; border-radius:3px; background:rgba(224,92,58,.1); color:var(--accent-red); margin-left:auto; flex-shrink:0; }
    .badge-optional { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; padding:1px 6px; border-radius:3px; background:rgba(42,63,126,.08); color:var(--sidebar-soft); margin-left:auto; flex-shrink:0; }
    .doc-item-body { padding:.75rem 1rem; border-top:1px solid var(--line); }
    .upload-row { display:flex; align-items:center; gap:.75rem; }
    .upload-label { display:inline-flex; align-items:center; gap:5px; padding:.4rem .875rem; border-radius:7px; border:1px dashed var(--line); background:var(--paper); font-size:.8125rem; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; position:relative; overflow:hidden; transition:all .12s; }
    .upload-label:hover { border-color:var(--sidebar-soft); color:var(--sidebar-soft); }
    .upload-label input { position:absolute; inset:0; opacity:0; cursor:pointer; }
    .upload-label svg { width:14px; height:14px; }
    .file-preview { display:inline-flex; align-items:center; gap:5px; padding:.35rem .75rem; border-radius:6px; background:rgba(30,120,80,.08); color:#166534; font-size:.8125rem; font-weight:600; }
    .file-preview svg { width:13px; height:13px; }

    /* Plans */
    .plan-cards { display:flex; flex-direction:column; gap:.6rem; }
    .plan-option { display:flex; align-items:center; gap:.75rem; padding:.65rem 1rem; border-radius:8px; border:1.5px solid var(--line); background:var(--paper); cursor:pointer; transition:all .12s; }
    .plan-option.selected { border-color:var(--sidebar); background:rgba(42,63,126,.05); }
    .plan-radio { width:14px; height:14px; border-radius:50%; border:2px solid var(--line); flex-shrink:0; transition:all .12s; display:flex; align-items:center; justify-content:center; }
    .plan-option.selected .plan-radio { border-color:var(--sidebar); }
    .plan-option.selected .plan-radio::after { content:''; width:6px; height:6px; border-radius:50%; background:var(--sidebar); }

    /* Remises */
    .discount-list { display:flex; flex-direction:column; gap:.5rem; }
    .discount-item { display:flex; align-items:center; justify-content:space-between; padding:.6rem 1rem; border-radius:8px; border:1.5px solid var(--line); background:var(--paper); cursor:pointer; transition:all .12s; }
    .discount-item.selected { border-color:var(--sidebar-soft); background:rgba(42,63,126,.05); }
    .discount-check { width:15px; height:15px; border-radius:3px; border:1.5px solid var(--line); appearance:none; cursor:pointer; position:relative; transition:all .12s; flex-shrink:0; }
    .discount-check:checked { background:var(--sidebar); border-color:var(--sidebar); }
    .discount-check:checked::after { content:''; position:absolute; top:1.5px; left:3.5px; width:4px; height:7px; border:2px solid #FFF; border-top:none; border-left:none; transform:rotate(45deg); }

    /* Navigation */
    .step-nav { display:flex; align-items:center; justify-content:space-between; margin-top:1.5rem; padding-top:1.25rem; border-top:1px solid var(--line); }
    .btn-prev { display:inline-flex; align-items:center; gap:6px; padding:.5rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }
    .btn-next { display:inline-flex; align-items:center; gap:6px; padding:.5rem 1.25rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; transition:background .15s; }
    .btn-next:hover { background:var(--sidebar-soft); }
    .btn-confirm { display:inline-flex; align-items:center; gap:6px; padding:.5rem 1.5rem; border-radius:8px; background:#166534; color:#FFFFFF; font-size:.875rem; font-weight:700; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
    .btn-confirm:hover { background:#14532d; }
    .btn-next svg, .btn-confirm svg, .btn-prev svg { width:15px; height:15px; }

    /* Photo */
    .photo-wrap { display:flex; align-items:center; gap:1rem; margin-bottom:1.25rem; }
    .photo-circle { width:64px; height:64px; border-radius:50%; overflow:hidden; border:2px solid var(--line); flex-shrink:0; display:flex; align-items:center; justify-content:center; background:rgba(42,63,126,.08); color:var(--sidebar-soft); font-family:'JetBrains Mono',monospace; font-size:18px; font-weight:700; }
    .photo-circle img { width:100%; height:100%; object-fit:cover; }
    .photo-upload-btn { display:inline-flex; align-items:center; gap:4px; padding:.4rem .8rem; border-radius:7px; border:1px solid var(--line); background:var(--paper); font-size:.8125rem; cursor:pointer; position:relative; }
    .photo-upload-btn input { position:absolute; inset:0; opacity:0; cursor:pointer; }

    /* Recap sidebar */
    .recap-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; position:sticky; top:1.5rem; }
    .recap-header { padding:.875rem 1.25rem; border-bottom:1px solid var(--line); display:flex; align-items:center; gap:.5rem; }
    .recap-title { font-family:'Fraunces',serif; font-size:.9rem; font-weight:600; color:var(--ink); }
    .recap-body { padding:1rem 1.25rem; }
    .recap-section { margin-bottom:1rem; padding-bottom:1rem; border-bottom:1px solid var(--line); }
    .recap-section:last-child { margin-bottom:0; padding-bottom:0; border-bottom:none; }
    .recap-section-title { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.1em; color:var(--ink); opacity:.4; margin-bottom:.5rem; }
    .recap-row { display:flex; justify-content:space-between; align-items:flex-start; gap:.5rem; font-size:.8125rem; padding:2px 0; }
    .recap-label { color:var(--ink); opacity:.6; }
    .recap-value { font-weight:600; color:var(--ink); text-align:right; }

    /* Factures */
    .invoice-row { display:flex; align-items:center; justify-content:space-between; padding:.55rem .875rem; border-radius:7px; border:1px solid var(--line); background:var(--paper); gap:.5rem; margin-bottom:.4rem; }
    .invoice-row.type-registration { border-color:rgba(232,168,56,.3); background:rgba(232,168,56,.04); }
    .invoice-row-label { font-size:.8125rem; font-weight:500; }
    .invoice-row-due   { font-size:.7rem; color:var(--ink); opacity:.4; font-family:'JetBrains Mono',monospace; }
    .invoice-row-amount { font-family:'JetBrains Mono',monospace; font-size:.875rem; font-weight:700; color:var(--sidebar-soft); flex-shrink:0; }
    .invoice-total { display:flex; justify-content:space-between; align-items:center; padding:.65rem .875rem; border-radius:8px; background:var(--sidebar); margin-top:.25rem; }
    .invoice-total-label  { font-size:.875rem; font-weight:600; color:rgba(255,255,255,.8); }
    .invoice-total-amount { font-family:'JetBrains Mono',monospace; font-size:.9rem; font-weight:700; color:#FFFFFF; }

    /* Admission banner */
    .admission-closed { display:flex; align-items:center; gap:.75rem; padding:1.25rem; border-radius:10px; background:rgba(224,92,58,.08); border:1px solid rgba(224,92,58,.2); color:var(--accent-red); margin-bottom:1.5rem; font-weight:600; }

    /* Success */
    .success-screen { text-align:center; padding:3rem 2rem; }
    .success-icon { width:64px; height:64px; margin:0 auto 1.25rem; background:rgba(30,120,80,.1); border-radius:50%; display:flex; align-items:center; justify-content:center; }
    .success-icon svg { width:32px; height:32px; color:#166634; }
    .success-title { font-family:'Fraunces',serif; font-size:1.4rem; font-weight:600; color:var(--ink); margin-bottom:.5rem; }
    .success-sub { font-size:.875rem; color:var(--ink); opacity:.6; margin-bottom:1.5rem; }
    .success-actions { display:flex; justify-content:center; gap:.75rem; flex-wrap:wrap; }
</style>

<div>

    @if ($done)
        <div class="success-screen">
            <div class="success-icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="success-title">Inscription enregistrée !</div>
            <div class="success-sub">L'élève a été inscrit avec succès. Les factures ont été générées.</div>
            <div class="success-actions">
                <a href="{{ route('students.index') }}" class="btn-prev">← Liste des élèves</a>
                @if ($newStudentId)
                    <a href="{{ route('students.show', $newStudentId) }}" class="btn-next">
                        Fiche de l'élève
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @endif
                <button wire:click="$set('done',false);$set('step',1)" class="btn-prev">+ Inscrire un autre</button>
            </div>
        </div>

    @else

        {{-- Vérification admission ouverte --}}
        @if ($admission && ! $admission->is_enrollment_open)
            <div class="admission-closed">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:20px;height:20px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Les inscriptions sont actuellement fermées.
                @if ($admission->enrollment_open_from)
                    Elles ouvriront le {{ $admission->enrollment_open_from->format('d/m/Y') }}.
                @endif
            </div>
        @endif

        {{-- Stepper --}}
        <div class="stepper">
            @php
                $stepLabels = ['Personnel','Tuteur','Académique','Documents','Financier'];
            @endphp
            @foreach ($stepLabels as $i => $label)
                @php $sNum = $i + 1; @endphp
                <div class="step-item">
                    <div class="step-col">
                        <div wire:click="goToStep({{ $sNum }})"
                             class="step-circle {{ $sNum < $step ? 'done' : ($sNum === $step ? 'active' : '') }}">
                            @if ($sNum < $step)
                                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            @else
                                {{ $sNum }}
                            @endif
                        </div>
                        <div class="step-label {{ $sNum === $step ? 'active' : ($sNum < $step ? 'done' : '') }}">{{ $label }}</div>
                    </div>
                    @if ($sNum < count($stepLabels))
                        <div class="step-line {{ $sNum < $step ? 'done' : '' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="enroll-grid">
            <div>

                {{-- ═══ ÉTAPE 1 : Informations personnelles ═══ --}}
                @if ($step === 1)
                    <div class="card">
                        <div class="card-header">
                            <div class="card-header-icon" style="background:rgba(42,63,126,.08);color:var(--sidebar-soft);">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <span class="card-title">Informations personnelles</span>
                        </div>
                        <div class="card-body">
                            <div class="photo-wrap">
                                <div class="photo-circle">
                                    @if ($photo)
                                        <img src="{{ $photo->temporaryUrl() }}" alt="">
                                    @else
                                        {{ strtoupper(substr($first_name ?: '?', 0, 1)) }}
                                    @endif
                                </div>
                                <label class="photo-upload-btn">
                                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    Photo
                                    <input wire:model="photo" type="file" accept="image/*">
                                </label>
                            </div>
                            <div class="form-grid-2">
                                <div class="form-field">
                                    <label class="form-label">Prénom *</label>
                                    <input wire:model.live="first_name" type="text" class="form-input" placeholder="Ayan">
                                    @error('first_name') <span class="form-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-field">
                                    <label class="form-label">Nom *</label>
                                    <input wire:model.live="last_name" type="text" class="form-input" placeholder="Dirieh">
                                    @error('last_name') <span class="form-error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="form-grid-2">
                                <div class="form-field">
                                    <label class="form-label">Genre *</label>
                                    <div class="radio-group">
                                        <button type="button" wire:click="$set('gender','M')" class="radio-btn {{ $gender==='M' ? 'm-active' : '' }}">Masculin</button>
                                        <button type="button" wire:click="$set('gender','F')" class="radio-btn {{ $gender==='F' ? 'f-active' : '' }}">Féminin</button>
                                    </div>
                                    @error('gender') <span class="form-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-field">
                                    <label class="form-label">Date de naissance</label>
                                    <input wire:model="birth_date" type="date" class="form-input">
                                </div>
                            </div>
                            <div class="form-field">
                                <label class="form-label">Lieu de naissance</label>
                                <input wire:model="birth_place" type="text" class="form-input" placeholder="Djibouti">
                            </div>
                            <div class="step-nav">
                                <span></span>
                                <button wire:click="nextStep" class="btn-next">Suivant <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ═══ ÉTAPE 2 : Tuteur ═══ --}}
                @if ($step === 2)
                    <div class="card">
                        <div class="card-header">
                            <div class="card-header-icon" style="background:rgba(30,120,80,.08);color:#1A6040;">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <span class="card-title">Tuteur / Parent</span>
                        </div>
                        <div class="card-body">
                            <div class="mode-toggle">
                                <button type="button" wire:click="$set('guardian_mode','existing')" class="mode-btn {{ $guardian_mode==='existing' ? 'active' : '' }}">Tuteur existant</button>
                                <button type="button" wire:click="$set('guardian_mode','new')"      class="mode-btn {{ $guardian_mode==='new'      ? 'active' : '' }}">Nouveau tuteur</button>
                            </div>
                            @if ($guardian_mode === 'existing')
                                <div class="form-field" style="margin-bottom:1rem;">
                                    <label class="form-label">Tuteur principal</label>
                                    <select wire:model="guardian_id" class="form-select-inp">
                                        <option value="">— Aucun (optionnel) —</option>
                                        @foreach ($guardians as $g)
                                            <option value="{{ $g->id }}">{{ $g->fullName() }} — {{ $g->phone }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if ($guardian_id)
                                    <div class="form-field">
                                        <label class="form-label">Lien de parenté</label>
                                        <select wire:model="g_relationship" class="form-select-inp">
                                            <option value="pere">Père</option>
                                            <option value="mere">Mère</option>
                                            <option value="tuteur">Tuteur légal</option>
                                            <option value="autre">Autre</option>
                                        </select>
                                    </div>
                                @endif
                            @else
                                <div class="form-grid-2">
                                    <div class="form-field">
                                        <label class="form-label">Prénom *</label>
                                        <input wire:model="g_first_name" type="text" class="form-input">
                                        @error('g_first_name') <span class="form-error">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="form-field">
                                        <label class="form-label">Nom *</label>
                                        <input wire:model="g_last_name" type="text" class="form-input">
                                        @error('g_last_name') <span class="form-error">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="form-grid-2">
                                    <div class="form-field">
                                        <label class="form-label">Téléphone *</label>
                                        <input wire:model="g_phone" type="tel" class="form-input" placeholder="77 00 00 00">
                                        @error('g_phone') <span class="form-error">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="form-field">
                                        <label class="form-label">Email</label>
                                        <input wire:model="g_email" type="email" class="form-input">
                                    </div>
                                </div>
                                <div class="form-grid-2">
                                    <div class="form-field">
                                        <label class="form-label">Profession</label>
                                        <input wire:model="g_profession" type="text" class="form-input">
                                    </div>
                                    <div class="form-field">
                                        <label class="form-label">Lien de parenté</label>
                                        <select wire:model="g_relationship" class="form-select-inp">
                                            <option value="pere">Père</option>
                                            <option value="mere">Mère</option>
                                            <option value="tuteur">Tuteur légal</option>
                                            <option value="autre">Autre</option>
                                        </select>
                                    </div>
                                </div>
                            @endif
                            <div class="step-nav">
                                <button wire:click="prevStep" class="btn-prev"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>Précédent</button>
                                <button wire:click="nextStep" class="btn-next">Suivant <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ═══ ÉTAPE 3 : Académique ═══ --}}
                @if ($step === 3)
                    <div class="card">
                        <div class="card-header">
                            <div class="card-header-icon" style="background:rgba(232,168,56,.12);color:#8A6010;">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/></svg>
                            </div>
                            <span class="card-title">Informations académiques — {{ $year?->label }}</span>
                        </div>
                        <div class="card-body">
                            <div class="form-grid-2">
                                <div class="form-field">
                                    <label class="form-label">Niveau *</label>
                                    <select wire:model.live="level_id" class="form-select-inp">
                                        <option value="">— Sélectionner un niveau —</option>
                                        @foreach ($levels->groupBy('cycle') as $cycle => $cycleLevels)
                                            <optgroup label="{{ $cycle }}">
                                                @foreach ($cycleLevels as $level)
                                                    <option value="{{ $level->id }}">{{ $level->name }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                    @error('level_id') <span class="form-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="form-field">
                                    <label class="form-label">Classe *</label>
                                    <select wire:model="school_class_id" class="form-select-inp" @if(!$level_id) disabled @endif>
                                        <option value="">— Sélectionner —</option>
                                        @foreach ($classes as $class)
                                            <option value="{{ $class->id }}">{{ $class->name }} @if($class->capacity) ({{ $class->studentSchoolYears()->count() }}/{{ $class->capacity }}) @endif</option>
                                        @endforeach
                                    </select>
                                    @error('school_class_id') <span class="form-error">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="step-nav">
                                <button wire:click="prevStep" class="btn-prev"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>Précédent</button>
                                <button wire:click="nextStep" class="btn-next">Suivant <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ═══ ÉTAPE 4 : Documents ═══ --}}
                @if ($step === 4)
                    <div class="card">
                        <div class="card-header">
                            <div class="card-header-icon" style="background:rgba(99,102,241,.1);color:#3730A3;">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            </div>
                            <span class="card-title">Pièces à fournir</span>
                        </div>
                        <div class="card-body">
                            @if ($requiredDocs->isEmpty())
                                <div style="text-align:center;padding:1.5rem;font-size:.875rem;color:var(--ink);opacity:.45;">
                                    Aucune pièce requise configurée pour ce niveau.
                                    <a href="{{ route('school-config.admission') }}" style="color:var(--sidebar-soft);">Configurer →</a>
                                </div>
                            @else
                                <div class="doc-list">
                                    @foreach ($requiredDocs as $doc)
                                        <div class="doc-item">
                                            <div class="doc-item-header">
                                                <div class="doc-icon" style="background:rgba(42,63,126,.08);color:var(--sidebar-soft);">
                                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                                </div>
                                                <div>
                                                    <div class="doc-item-name">{{ $doc->name }}</div>
                                                    @if ($doc->description)
                                                        <div class="doc-item-desc">{{ $doc->description }}</div>
                                                    @endif
                                                </div>
                                                <span class="{{ $doc->is_mandatory ? 'badge-required' : 'badge-optional' }}">
                                                    {{ $doc->is_mandatory ? 'Obligatoire' : 'Optionnel' }}
                                                </span>
                                            </div>
                                            <div class="doc-item-body">
                                                <div class="upload-row">
                                                    <label class="upload-label">
                                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                                        Joindre le document
                                                        <input wire:model="docFiles.{{ $doc->id }}"
                                                               type="file"
                                                               accept=".pdf,.jpg,.jpeg,.png,.webp">
                                                    </label>
                                                    @if (isset($this->docFiles[$doc->id]) && $this->docFiles[$doc->id])
                                                        <span class="file-preview">
                                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                            Document joint
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @php
                                    $mandatoryDocs  = $requiredDocs->where('is_mandatory', true);
                                    $uploadedMandatory = $mandatoryDocs->filter(fn($d) => isset($this->docFiles[$d->id]) && $this->docFiles[$d->id])->count();
                                @endphp
                                @if ($mandatoryDocs->isNotEmpty())
                                    <div style="margin-top:1rem;font-size:.8125rem;color:var(--ink);opacity:.5;">
                                        {{ $uploadedMandatory }} / {{ $mandatoryDocs->count() }} documents obligatoires joints.
                                    </div>
                                @endif
                            @endif

                            <div class="step-nav">
                                <button wire:click="prevStep" class="btn-prev"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>Précédent</button>
                                <button wire:click="nextStep" class="btn-next">Suivant <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ═══ ÉTAPE 5 : Financier ═══ --}}
                @if ($step === 5)
                    <div class="card">
                        <div class="card-header">
                            <div class="card-header-icon" style="background:rgba(42,63,126,.08);color:var(--sidebar-soft);">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            </div>
                            <span class="card-title">Plan de paiement & Remises</span>
                        </div>
                        <div class="card-body">
                            @if ($plans->isNotEmpty())
                                <div class="form-field" style="margin-bottom:1.25rem;">
                                    <label class="form-label">Plan d'échéancier</label>
                                    <div class="plan-cards">
                                        <label class="plan-option {{ !$installment_plan_id ? 'selected' : '' }}">
                                            <input type="radio" wire:model.live="installment_plan_id" value="" style="display:none;">
                                            <div class="plan-radio"></div>
                                            <div>
                                                <div style="font-size:.875rem;font-weight:600;">Plan par défaut du niveau</div>
                                                <div style="font-size:.75rem;color:var(--ink);opacity:.5;">Selon la configuration des frais</div>
                                            </div>
                                        </label>
                                        @foreach ($plans as $plan)
                                            <label class="plan-option {{ $installment_plan_id == $plan->id ? 'selected' : '' }}">
                                                <input type="radio" wire:model.live="installment_plan_id" value="{{ $plan->id }}" style="display:none;">
                                                <div class="plan-radio"></div>
                                                <div>
                                                    <div style="font-size:.875rem;font-weight:600;">{{ $plan->name }}</div>
                                                    <div style="font-size:.75rem;color:var(--ink);opacity:.5;">{{ $plan->items->map(fn($i)=>$i->percentage.'%')->join(' · ') }}</div>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($discountTypes->isNotEmpty())
                                <div class="form-field">
                                    <label class="form-label">Remises & Exonérations</label>
                                    <div class="discount-list">
                                        @foreach ($discountTypes as $d)
                                            <label class="discount-item {{ in_array($d->id, $discount_ids) ? 'selected' : '' }}">
                                                <div style="display:flex;align-items:center;gap:.65rem;">
                                                    <input type="checkbox" wire:model.live="discount_ids" value="{{ $d->id }}" class="discount-check">
                                                    <div>
                                                        <div style="font-size:.875rem;font-weight:600;">{{ $d->name }}</div>
                                                        <div style="font-size:.75rem;color:var(--ink);opacity:.5;">
                                                            @if($d->is_social) Sociale · @endif
                                                            {{ match($d->applies_to) { 'tuition'=>'Scolarité','inscription'=>'Inscription',default=>'Tout' } }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <span style="font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700;color:var(--sidebar-soft);">{{ $d->formatted_value }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="step-nav">
                                <button wire:click="prevStep" class="btn-prev"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>Précédent</button>
                                <button wire:click="enroll" class="btn-confirm">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Confirmer l'inscription
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

            </div>

            {{-- Récapitulatif sidebar --}}
            <div>
                <div class="recap-card">
                    <div class="recap-header">
                        <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="width:16px;height:16px;opacity:.5;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span class="recap-title">Récapitulatif</span>
                    </div>
                    <div class="recap-body">
                        @if ($first_name || $last_name)
                            <div class="recap-section">
                                <div class="recap-section-title">Élève</div>
                                <div class="recap-row"><span class="recap-label">Nom</span><span class="recap-value">{{ $first_name }} {{ $last_name }}</span></div>
                                @if ($gender) <div class="recap-row"><span class="recap-label">Genre</span><span class="recap-value">{{ $gender==='M'?'Masculin':'Féminin' }}</span></div> @endif
                                @if ($birth_date) <div class="recap-row"><span class="recap-label">Naissance</span><span class="recap-value">{{ \Carbon\Carbon::parse($birth_date)->format('d/m/Y') }}</span></div> @endif
                            </div>
                        @endif

                        @if ($level_id)
                            <div class="recap-section">
                                <div class="recap-section-title">Académique — {{ $year?->label }}</div>
                                <div class="recap-row"><span class="recap-label">Niveau</span><span class="recap-value">{{ $levels->firstWhere('id',$level_id)?->name ?? '—' }}</span></div>
                                @if ($school_class_id)
                                    <div class="recap-row"><span class="recap-label">Classe</span><span class="recap-value">{{ $classes->firstWhere('id',$school_class_id)?->name ?? '—' }}</span></div>
                                @endif
                            </div>
                        @endif

                        @if ($step >= 4 && $requiredDocs->isNotEmpty())
                            <div class="recap-section">
                                <div class="recap-section-title">Documents</div>
                                @foreach ($requiredDocs as $doc)
                                    <div class="recap-row">
                                        <span class="recap-label">{{ $doc->name }}</span>
                                        <span class="recap-value" style="font-size:.75rem;color:{{ (isset($this->docFiles[$doc->id]) && $this->docFiles[$doc->id]) ? '#166634' : ($doc->is_mandatory ? 'var(--accent-red)' : 'var(--ink)') }}">
                                            {{ (isset($this->docFiles[$doc->id]) && $this->docFiles[$doc->id]) ? '✓ Joint' : ($doc->is_mandatory ? 'Manquant' : 'Optionnel') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($preview && count($preview['invoices']) > 0)
                            <div class="recap-section">
                                <div class="recap-section-title">Factures · {{ $preview['plan_name'] }}</div>
                                @foreach ($preview['invoices'] as $inv)
                                    <div class="invoice-row {{ $inv['type']==='registration' ? 'type-registration' : '' }}">
                                        <div>
                                            <div class="invoice-row-label">{{ $inv['label'] }}</div>
                                            @if ($inv['due_at'] !== '—') <div class="invoice-row-due">{{ $inv['due_at'] }}</div> @endif
                                        </div>
                                        <div class="invoice-row-amount">{{ number_format($inv['amount'],0,',',' ') }} DJF</div>
                                    </div>
                                @endforeach
                                <div class="invoice-total">
                                    <span class="invoice-total-label">Total</span>
                                    <span class="invoice-total-amount">{{ number_format($preview['total'],0,',',' ') }} DJF</span>
                                </div>
                                @if ($preview['total_discount'] > 0)
                                    <div style="font-size:.75rem;color:#166634;text-align:right;margin-top:.25rem;">
                                        Remise : -{{ number_format($preview['total_discount'],0,',',' ') }} DJF
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>