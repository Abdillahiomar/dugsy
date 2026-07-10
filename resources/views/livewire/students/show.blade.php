<?php

use App\Models\Attendance;
use App\Models\Bulletin;
use App\Models\Evaluation;
use App\Models\Grade;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\StudentPayment;
use App\Models\StudentSchoolYear;
use App\Services\AcademicYearService;
use Livewire\Attributes\Layout;
use Livewire\Component;

new class extends Component
{
    public Student $student;
    public string  $activeSection = 'overview';

    // ── Paiement rapide ──────────────────────────────────────────
    public ?int   $payingInvoiceId = null;
    public int    $payAmount       = 0;
    public string $payMethod       = 'especes';
    public string $payReference    = '';
    public bool   $paymentSaved    = false;

    // ── Absence ─────────────────────────────────────────────────
    public bool   $showAbsenceForm   = false;
    public string $absenceDate       = '';
    public string $absenceStatus     = 'absent';
    public string $absenceJustif     = '';
    public bool   $absenceSaved      = false;

    // Compte parent
    public bool   $parentAccountCreated  = false;
    public string $parentAccountEmail    = '';
    public string $parentAccountTempPass = '';

    // Reset mot de passe
    public bool   $showResetModal    = false;
    public ?int   $resetGuardianId   = null;
    public string $resetPassword     = '';
    public string $resetPasswordConfirm = '';
    public bool   $resetDone         = false;
    public string $resetEmail        = '';

    public function mount(Student $student): void
    {
        $this->student      = $student;
        $this->absenceDate  = now()->format('Y-m-d');
    }

    // ── Paiement ─────────────────────────────────────────────────

    public function openPayment(int $invoiceId): void
    {
        $invoice            = StudentInvoice::find($invoiceId);
        $this->payingInvoiceId = $invoiceId;
        $this->payAmount    = $invoice->balance();
        $this->payMethod    = 'especes';
        $this->payReference = '';
        $this->paymentSaved = false;
    }

    public function savePayment(): void
    {
        $this->validate([
            'payAmount'    => 'required|integer|min:1',
            'payMethod'    => 'required|string',
        ]);

        $invoice = StudentInvoice::find($this->payingInvoiceId);
        if (! $invoice) return;

        StudentPayment::create([
            'student_invoice_id' => $invoice->id,
            'amount'             => $this->payAmount,
            'method'             => $this->payMethod,
            'reference'          => $this->payReference ?: null,
            'paid_at'            => now(),
            'received_by'        => auth()->user()->staff?->id,
        ]);

        $newPaid = $invoice->amount_paid + $this->payAmount;
        $invoice->update([
            'amount_paid' => $newPaid,
            'status'      => $newPaid >= $invoice->amount_due ? 'paid' : 'partially_paid',
        ]);

        $this->payingInvoiceId = null;
        $this->paymentSaved    = true;
    }

    // ── Absence ─────────────────────────────────────────────────

    public function saveAbsence(): void
    {
        $this->validate([
            'absenceDate'   => 'required|date',
            'absenceStatus' => 'required|in:present,absent,late,excused',
        ]);

        $year = AcademicYearService::current();
        $ssy  = StudentSchoolYear::where('student_id', $this->student->id)
            ->where('academic_year_id', $year?->id)
            ->first();

        if (! $ssy) return;

        Attendance::updateOrCreate(
            [
                'student_school_year_id' => $ssy->id,
                'date'                   => $this->absenceDate,
                'session_start'          => null, // ← AJOUT obligatoire
            ],
            [
                'status'        => $this->absenceStatus,
                'justification' => $this->absenceJustif ?: null,
                'recorded_by'   => auth()->user()->staff?->id,
            ]
        );

        $this->showAbsenceForm = false;
        $this->absenceSaved    = true;
        $this->absenceDate     = now()->format('Y-m-d');
        $this->absenceStatus   = 'absent';
        $this->absenceJustif   = '';
    }

 
    // Remplacer createParentAccount() entièrement
    public function createParentAccount(int $guardianId): void
    {
        // Seuls admin et directeur peuvent faire ça
        if (! auth()->user()->hasAnyRole(['admin','directeur'])) return;

        $guardian = \App\Models\Guardian::with('user')->find($guardianId);
        if (! $guardian) return;

        // ── Cas 1 : tuteur a déjà un compte (user_id renseigné) ──────
        if ($guardian->user_id && $guardian->user) {
            $this->showResetModal  = true;
            $this->resetGuardianId = $guardianId;
            $this->resetEmail      = $guardian->user->email;
            return;
        }

        // ── Cas 2 : l'email existe déjà en base sans être lié ────────
        $schoolId = auth()->user()->school_id;
        $school   = auth()->user()->school;
        $email    = $guardian->email
            ?: 'parent.' . $guardianId . '@' . ($school->slug ?? $schoolId) . '.dj';

        $existingUser = \App\Models\User::where('email', $email)->first();
        if ($existingUser) {
            // Lier ce compte existant au tuteur
            $guardian->update(['user_id' => $existingUser->id]);
            if (! $existingUser->hasRole('parent')) {
                $existingUser->assignRole('parent');
            }
            // Proposer reset
            $this->showResetModal  = true;
            $this->resetGuardianId = $guardianId;
            $this->resetEmail      = $email;
            return;
        }

        // ── Cas 3 : créer un nouveau compte ──────────────────────────
        $tempPass = 'Parent@' . rand(1000, 9999);

        $user = \App\Models\User::create([
            'school_id' => $schoolId,
            'name'      => $guardian->fullName(),
            'email'     => $email,
            'password'  => \Illuminate\Support\Facades\Hash::make($tempPass),
            'status'    => 'active',
        ]);

        $user->assignRole('parent');
        $guardian->update(['user_id' => $user->id]);

        $this->parentAccountCreated  = true;
        $this->parentAccountEmail    = $email;
        $this->parentAccountTempPass = $tempPass;
    }

    public function resetParentPassword(): void
    {
        if (! auth()->user()->hasAnyRole(['admin','directeur'])) return;

        $this->validate([
            'resetPassword'        => 'required|string|min:8',
            'resetPasswordConfirm' => 'required|same:resetPassword',
        ], [
            'resetPasswordConfirm.same' => 'Les deux mots de passe ne correspondent pas.',
        ]);

        $guardian = \App\Models\Guardian::with('user')->find($this->resetGuardianId);
        if (! $guardian?->user) return;

        $guardian->user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($this->resetPassword),
            'status'   => 'active',
        ]);

        $this->resetDone            = true;
        $this->parentAccountEmail   = $guardian->user->email;
        $this->parentAccountTempPass = $this->resetPassword;
        $this->resetPassword        = '';
        $this->resetPasswordConfirm = '';
        $this->showResetModal       = false;

        // Afficher modal de confirmation avec les nouveaux identifiants
        $this->parentAccountCreated = true;
    }

    public function closeResetModal(): void
    {
        $this->showResetModal      = false;
        $this->resetGuardianId     = null;
        $this->resetPassword       = '';
        $this->resetPasswordConfirm = '';
        $this->resetEmail          = '';
    }

    public function with(): array
    {
        $year = AcademicYearService::current();

        $isParent = auth()->user()->hasRole('parent');

        // Inscription courante
        $currentSsy = StudentSchoolYear::where('student_id', $this->student->id)
            ->where('academic_year_id', $year?->id)
            ->with(['schoolClass.level', 'schoolClass.classSubjects.subject', 'schoolClass.classSubjects.teacher.user'])
            ->first();

        // Historique scolaire
        $history = StudentSchoolYear::where('student_id', $this->student->id)
            ->with(['academicYear', 'schoolClass.level'])
            ->orderByDesc('enrolled_at')
            ->get();

        // Tuteurs
        $guardians = $this->student->guardians()
            ->withPivot('relationship', 'is_primary_contact')
            ->get();

        // Factures + paiements
        $invoices = StudentInvoice::where('student_school_year_id', $currentSsy?->id)
            ->with('payments')
            ->orderBy('issued_at')
            ->get();

        $allInvoices = StudentInvoice::whereHas('studentSchoolYear', fn ($q) =>
            $q->where('student_id', $this->student->id)
        )->with(['payments', 'studentSchoolYear.academicYear'])
         ->orderByDesc('issued_at')
         ->get();

        $totalDue  = $allInvoices->sum('amount_due');
        $totalPaid = $allInvoices->sum('amount_paid');
        $balance   = $totalDue - $totalPaid;

        // Notes par matière
        $grades = Grade::whereHas('studentSchoolYear', fn ($q) =>
            $q->where('student_id', $this->student->id)
              ->where('academic_year_id', $year?->id)
        )->with(['evaluation.subject', 'evaluation.schoolClass'])
         ->get()
         ->groupBy('evaluation.subject.name');

        // Bulletins
        $bulletins = Bulletin::whereHas('studentSchoolYear', fn ($q) =>
            $q->where('student_id', $this->student->id)
        )->with('studentSchoolYear.academicYear')
         ->orderByDesc('generated_at')
         ->get();

        // Absences
        $attendances = Attendance::whereHas('studentSchoolYear', fn ($q) =>
            $q->where('student_id', $this->student->id)
              ->where('academic_year_id', $year?->id)
        )->orderByDesc('date')->get();

        $absenceStats = [
            'total'   => $attendances->count(),
            'absent'  => $attendances->where('status', 'absent')->count(),
            'late'    => $attendances->where('status', 'late')->count(),
            'excused' => $attendances->where('status', 'excused')->count(),
        ];

        // Dernière inscription connue (toutes années confondues)
        $lastSsy = StudentSchoolYear::where('student_id', $this->student->id)
            ->with(['academicYear', 'schoolClass.level'])
            ->latest('enrolled_at')
            ->first();

        // Récupérer la fee structure pour l'élève courant
        $feeStructure = $currentSsy
            ? \App\Models\FeeStructure::where('school_id', auth()->user()->school_id)
                ->where('academic_year_id', $year?->id)
                ->where('level_id', $currentSsy->schoolClass->level_id)
                ->first()
            : null;
        
        $studentDocs = \App\Models\StudentDocument::whereHas('studentSchoolYear', fn ($q) =>
            $q->where('student_id', $this->student->id)
            ->where('academic_year_id', $year?->id)
        )->with('requiredDocument')->get();

        $missingDocs = \App\Models\RequiredDocument::where('school_id', auth()->user()->school_id)
            ->where('is_active', true)
            ->whereNotIn('id', $studentDocs->pluck('required_document_id'))
            ->get();

        return compact(
            'year', 'currentSsy', 'lastSsy', 'history', 'guardians',
            'invoices', 'allInvoices', 'totalDue', 'totalPaid', 'balance',
            'grades', 'bulletins', 'attendances', 'absenceStats',
            'feeStructure', 'studentDocs', 'missingDocs', 'isParent'
        );
    }
}; ?>

<style>
    /* ── Breadcrumb ── */
    .bc { display:flex; align-items:center; gap:.5rem; font-size:.8125rem; margin-bottom:1.25rem; color:var(--ink); opacity:.5; }
    .bc a { color:inherit; text-decoration:none; }
    .bc a:hover { color:var(--sidebar-soft); opacity:1; }
    .bc svg { width:14px; height:14px; }
    .bc-cur { opacity:1; font-weight:600; color:var(--ink); }

    /* ── En-tête élève ── */
    .student-header {
        border-radius:12px; overflow:hidden;
        border:1px solid var(--line); margin-bottom:1.5rem;
    }
    .student-header-bg {
        height:80px; background:linear-gradient(135deg, var(--sidebar) 0%, var(--sidebar-soft) 100%);
        position:relative;
    }
    .student-avatar-wrap {
        position:absolute; bottom:-28px; left:1.5rem;
        width:56px; height:56px; border-radius:50%;
        border:3px solid var(--paper-raised); background:var(--accent);
        color:var(--sidebar); font-family:'JetBrains Mono',monospace;
        font-size:16px; font-weight:700;
        display:flex; align-items:center; justify-content:center;
    }
    .student-header-body { padding:2.25rem 1.5rem 1.25rem; background:var(--paper-raised); display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; gap:1rem; }
    .student-header-name { font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; color:var(--ink); }
    .student-header-meta { display:flex; align-items:center; gap:1rem; flex-wrap:wrap; margin-top:.35rem; }
    .meta-chip { font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600; padding:3px 9px; border-radius:5px; }
    .meta-chip-matric { background:rgba(42,63,126,.08); color:var(--sidebar-soft); }
    .meta-chip-active { background:rgba(30,120,80,.1); color:#166534; }
    .meta-chip-inactive { background:rgba(224,92,58,.1); color:var(--accent-red); }
    .meta-text { font-size:.8125rem; color:var(--ink); opacity:.55; }
    .student-header-actions { display:flex; gap:.65rem; flex-wrap:wrap; }

    /* ── Section nav ── */
    .sec-nav { display:flex; gap:.25rem; background:var(--paper); border:1px solid var(--line); border-radius:10px; padding:4px; margin-bottom:1.5rem; flex-wrap:wrap; }
    .sec-btn { display:inline-flex; align-items:center; gap:5px; padding:.4rem .875rem; border-radius:7px; font-size:.8125rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); border:none; cursor:pointer; background:none; transition:all .12s; opacity:.55; }
    .sec-btn svg { width:14px; height:14px; }
    .sec-btn:hover { opacity:.9; background:var(--paper-raised); }
    .sec-btn.active { background:var(--sidebar); color:#FFFFFF; opacity:1; }

    /* ── Layout 2 col ── */
    .detail-grid { display:grid; grid-template-columns:1fr 280px; gap:1.25rem; align-items:start; }
    @media (max-width:900px) { .detail-grid { grid-template-columns:1fr; } }

    /* ── Cards ── */
    .card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.25rem; }
    .card:last-child { margin-bottom:0; }
    .card-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
    .card-title { font-family:'Fraunces',serif; font-size:.95rem; font-weight:600; color:var(--ink); }
    .card-meta { font-family:'JetBrains Mono',monospace; font-size:10px; color:var(--ink); opacity:.4; }
    .card-body { padding:1.25rem 1.5rem; }

    /* ── Infos perso ── */
    .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:.75rem 1.5rem; }
    @media(max-width:600px) { .info-grid { grid-template-columns:1fr; } }
    .info-item { }
    .info-label { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; margin-bottom:3px; }
    .info-value { font-size:.875rem; font-weight:500; color:var(--ink); }

    /* ── Tuteur ── */
    .guardian-row { display:flex; align-items:center; gap:.75rem; padding:.75rem 0; border-bottom:1px solid var(--line); }
    .guardian-row:last-child { border-bottom:none; padding-bottom:0; }
    .guardian-avatar { width:32px; height:32px; border-radius:50%; background:rgba(42,63,126,.1); color:var(--sidebar-soft); font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:600; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .guardian-name { font-size:.875rem; font-weight:600; color:var(--ink); }
    .guardian-detail { font-size:.8rem; color:var(--ink); opacity:.5; margin-top:1px; }
    .guardian-badge { margin-left:auto; font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; padding:2px 7px; border-radius:4px; background:rgba(42,63,126,.08); color:var(--sidebar-soft); flex-shrink:0; }

    /* ── Matières ── */
    .subject-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:.6rem; }
    .subject-card { padding:.7rem .875rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); }
    .subject-dot { width:10px; height:10px; border-radius:50%; margin-bottom:.4rem; }
    .subject-name-sm { font-size:.8125rem; font-weight:600; color:var(--ink); }
    .subject-teacher { font-size:.75rem; color:var(--ink); opacity:.45; margin-top:2px; }
    .subject-coeff { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:700; color:var(--sidebar-soft); margin-top:2px; }

    /* ── Historique ── */
    .history-timeline { position:relative; padding-left:1.25rem; }
    .history-timeline::before { content:''; position:absolute; left:5px; top:6px; bottom:6px; width:2px; background:var(--line); border-radius:1px; }
    .history-item { position:relative; padding:0 0 1rem .875rem; }
    .history-item:last-child { padding-bottom:0; }
    .history-dot { position:absolute; left:-1.25rem; top:4px; width:10px; height:10px; border-radius:50%; border:2px solid var(--paper-raised); flex-shrink:0; }
    .history-year { font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:700; color:var(--sidebar-soft); }
    .history-class-name { font-size:.875rem; font-weight:600; color:var(--ink); }
    .history-level { font-size:.8rem; color:var(--ink); opacity:.5; }

    /* ── Finances ── */
    .finance-kpis { display:grid; grid-template-columns:repeat(3,1fr); gap:.75rem; margin-bottom:1.25rem; }
    .kpi-box { padding:.875rem 1rem; border-radius:10px; border:1px solid var(--line); background:var(--paper); text-align:center; }
    .kpi-label { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; margin-bottom:.35rem; }
    .kpi-value { font-family:'JetBrains Mono',monospace; font-size:1.1rem; font-weight:700; }
    .kpi-due  { color:var(--ink); }
    .kpi-paid { color:#166534; }
    .kpi-bal  { color:var(--accent-red); }
    .kpi-bal.ok { color:#166534; }

    table { width:100%; border-collapse:collapse; }
    thead tr { border-bottom:1px solid var(--line); background:var(--paper); }
    thead th { text-align:left; padding:.6rem 1rem; font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.45; white-space:nowrap; }
    thead th:last-child { text-align:right; }
    tbody tr { border-bottom:1px solid var(--line); transition:background .1s; }
    tbody tr:last-child { border-bottom:none; }
    tbody tr:hover { background:rgba(30,45,90,.03); }
    tbody td { padding:.7rem 1rem; font-size:.8125rem; color:var(--ink); vertical-align:middle; }
    tbody td:last-child { text-align:right; }

    .inv-label { font-weight:600; }
    .inv-year { font-size:.75rem; color:var(--ink); opacity:.45; font-family:'JetBrains Mono',monospace; margin-top:1px; }

    .badge { display:inline-block; font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:2px 8px; border-radius:4px; text-transform:uppercase; letter-spacing:.05em; white-space:nowrap; }
    .b-paid     { background:rgba(30,120,80,.1); color:#166534; }
    .b-partial  { background:rgba(232,168,56,.15); color:#8A6010; }
    .b-unpaid   { background:rgba(224,92,58,.1); color:var(--accent-red); }
    .b-overdue  { background:rgba(139,0,0,.1); color:#7F1D1D; }

    .amount-mono { font-family:'JetBrains Mono',monospace; font-size:.8125rem; }
    .amount-paid { color:#166534; font-weight:600; }
    .amount-bal  { color:var(--accent-red); font-weight:600; }

    .btn-pay { display:inline-flex; align-items:center; gap:4px; padding:.3rem .65rem; border-radius:6px; font-size:.8rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; background:rgba(30,120,80,.1); color:#166534; transition:background .12s; }
    .btn-pay:hover { background:rgba(30,120,80,.2); }
    .btn-pay svg { width:13px; height:13px; }

    /* Paiements détail */
    .payments-sub { padding:.5rem 1rem 0; }
    .payment-row { display:flex; align-items:center; justify-content:space-between; padding:.4rem 0; border-bottom:1px solid var(--line); font-size:.8rem; }
    .payment-row:last-child { border-bottom:none; }
    .payment-method { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:1px 6px; border-radius:3px; background:rgba(42,63,126,.08); color:var(--sidebar-soft); }

    /* ── Notes ── */
    .subject-grades { margin-bottom:1.5rem; border:1px solid var(--line); border-radius:10px; overflow:hidden; }
    .sg-header { padding:.75rem 1rem; background:var(--paper); border-bottom:1px solid var(--line); display:flex; align-items:center; gap:.65rem; }
    .sg-color { width:12px; height:12px; border-radius:50%; flex-shrink:0; }
    .sg-name { font-size:.875rem; font-weight:600; color:var(--ink); flex:1; }
    .sg-coeff { font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--ink); opacity:.45; }
    .sg-avg { font-family:'JetBrains Mono',monospace; font-size:14px; font-weight:700; }
    .avg-good { color:#166534; }
    .avg-mid  { color:#8A6010; }
    .avg-bad  { color:var(--accent-red); }
    .grade-chips { display:flex; flex-wrap:wrap; gap:.4rem; padding:.75rem 1rem; }
    .grade-chip { display:flex; flex-direction:column; align-items:center; padding:.4rem .65rem; border-radius:7px; border:1px solid var(--line); background:var(--paper); min-width:60px; }
    .gc-score { font-family:'JetBrains Mono',monospace; font-size:14px; font-weight:700; color:var(--ink); }
    .gc-label { font-size:.7rem; color:var(--ink); opacity:.45; margin-top:2px; text-align:center; }
    .gc-date  { font-size:.65rem; color:var(--ink); opacity:.35; margin-top:1px; font-family:'JetBrains Mono',monospace; }

    .empty-grades { padding:2rem; text-align:center; font-size:.875rem; color:var(--ink); opacity:.4; }

    /* ── Bulletins ── */
    .bulletin-row { display:flex; align-items:center; justify-content:space-between; padding:.875rem 1rem; border-bottom:1px solid var(--line); gap:1rem; }
    .bulletin-row:last-child { border-bottom:none; }
    .bulletin-period { font-weight:600; font-size:.875rem; color:var(--ink); }
    .bulletin-year { font-size:.75rem; color:var(--ink); opacity:.45; font-family:'JetBrains Mono',monospace; margin-top:1px; }
    .bulletin-avg { font-family:'JetBrains Mono',monospace; font-size:1.1rem; font-weight:700; }
    .bulletin-rank { font-size:.8rem; color:var(--ink); opacity:.5; }
    .btn-dl { display:inline-flex; align-items:center; gap:4px; padding:.3rem .65rem; border-radius:6px; font-size:.8rem; font-weight:600; border:1px solid var(--line); background:var(--paper); color:var(--ink); cursor:pointer; text-decoration:none; }
    .btn-dl svg { width:13px; height:13px; }

    /* ── Absences ── */
    .absence-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:.6rem; margin-bottom:1.25rem; }
    .abs-stat { padding:.65rem; border-radius:8px; border:1px solid var(--line); text-align:center; }
    .abs-num { font-family:'JetBrains Mono',monospace; font-size:1.2rem; font-weight:700; }
    .abs-lbl { font-size:.75rem; color:var(--ink); opacity:.5; margin-top:2px; }
    .abs-stat.ab .abs-num { color:var(--accent-red); }
    .abs-stat.lt .abs-num { color:#8A6010; }
    .abs-stat.ex .abs-num { color:#166534; }

    .abs-row { display:flex; align-items:center; justify-content:space-between; padding:.65rem 0; border-bottom:1px solid var(--line); }
    .abs-row:last-child { border-bottom:none; }
    .abs-date { font-family:'JetBrains Mono',monospace; font-size:.8125rem; font-weight:600; color:var(--ink); }
    .abs-status { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; padding:2px 8px; border-radius:4px; text-transform:uppercase; }
    .as-absent  { background:rgba(224,92,58,.1); color:var(--accent-red); }
    .as-late    { background:rgba(232,168,56,.15); color:#8A6010; }
    .as-excused { background:rgba(30,120,80,.1); color:#166534; }
    .as-present { background:rgba(42,63,126,.08); color:var(--sidebar-soft); }
    .abs-justif { font-size:.75rem; color:var(--ink); opacity:.5; margin-top:1px; }

    /* Formulaire absence */
    .absence-form { border-radius:10px; border:1px solid var(--line); background:var(--paper); padding:1rem; margin-bottom:1rem; animation:slideDown .15s ease; }
    @keyframes slideDown { from{opacity:0;transform:translateY(-4px);} to{opacity:1;transform:translateY(0);} }
    .form-row-inline { display:grid; grid-template-columns:1fr 1fr 1fr; gap:.75rem; margin-bottom:.75rem; }
    @media(max-width:600px) { .form-row-inline { grid-template-columns:1fr; } }
    .form-field-sm { display:flex; flex-direction:column; gap:.3rem; }
    .form-label-sm { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.5; }
    .form-input-sm, .form-select-sm { padding:.4rem .65rem; border-radius:7px; border:1px solid var(--line); background:var(--paper-raised); font-size:.8125rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; }
    .form-input-sm:focus, .form-select-sm:focus { border-color:var(--sidebar-soft); }
    .form-actions-sm { display:flex; justify-content:flex-end; gap:.5rem; }
    .btn-sm-save { padding:.35rem .875rem; border-radius:6px; background:var(--sidebar); color:#FFFFFF; font-size:.8125rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
    .btn-sm-cancel { padding:.35rem .75rem; border-radius:6px; background:var(--paper-raised); color:var(--ink); font-size:.8125rem; font-family:'Inter',sans-serif; border:1px solid var(--line); cursor:pointer; }

    /* Modal paiement */
    .modal-overlay { position:fixed; inset:0; z-index:100; background:rgba(0,0,0,.4); display:flex; align-items:center; justify-content:center; padding:1rem; }
    .modal { background:var(--paper-raised); border-radius:14px; border:1px solid var(--line); padding:1.75rem; max-width:400px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.2); }
    .modal-title { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; margin-bottom:.25rem; }
    .modal-sub { font-size:.8125rem; color:var(--ink); opacity:.5; margin-bottom:1.25rem; }
    .modal-field { display:flex; flex-direction:column; gap:.3rem; margin-bottom:.875rem; }
    .modal-label { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.5; }
    .modal-input, .modal-select { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; }
    .modal-input:focus, .modal-select:focus { border-color:var(--sidebar-soft); }
    .modal-actions { display:flex; justify-content:flex-end; gap:.65rem; padding-top:1rem; border-top:1px solid var(--line); margin-top:1rem; }
    .btn-modal-cancel { padding:.45rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }
    .btn-modal-pay { padding:.45rem 1.1rem; border-radius:8px; background:#166534; color:#FFFFFF; font-size:.875rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; }

    /* Toast */
    .toast { display:flex; align-items:center; gap:.6rem; padding:.65rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:.875rem; font-weight:500; animation:slideDown .15s ease; }
    .toast svg { width:15px; height:15px; flex-shrink:0; }
    .toast-ok { background:rgba(30,120,80,.1); border:1px solid rgba(30,120,80,.2); color:#166534; }

    /* Sticky sidebar */
    .sticky-sidebar { position:sticky; top:1.5rem; }
    .side-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1rem; }
    .side-card-header { padding:.75rem 1rem; border-bottom:1px solid var(--line); font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.4; }
    .side-card-body { padding:.875rem 1rem; }
    .side-row { display:flex; justify-content:space-between; align-items:flex-start; gap:.5rem; padding:.3rem 0; }
    .side-label { font-size:.8rem; color:var(--ink); opacity:.55; }
    .side-value { font-size:.8rem; font-weight:600; color:var(--ink); text-align:right; }

    .empty-section { padding:3rem; text-align:center; font-size:.875rem; color:var(--ink); opacity:.35; }

    /* Boutons header */
    .btn-outline { display:inline-flex; align-items:center; gap:5px; padding:.4rem .875rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.8125rem; font-weight:600; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; text-decoration:none; transition:border-color .12s; }
    .btn-outline:hover { border-color:var(--sidebar-soft); color:var(--sidebar-soft); }
    .btn-outline svg { width:14px; height:14px; }
    .btn-primary-sm { display:inline-flex; align-items:center; gap:5px; padding:.4rem .875rem; border-radius:8px; background:var(--sidebar); color:#FFFFFF; font-size:.8125rem; font-weight:600; font-family:'Inter',sans-serif; border:none; cursor:pointer; text-decoration:none; transition:background .12s; }
    .btn-primary-sm:hover { background:var(--sidebar-soft); }
    .btn-primary-sm svg { width:14px; height:14px; }
</style>

<div>

    {{-- Breadcrumb --}}
    <div class="bc">
        <a href="{{ route('students.index') }}">Elèves</a>
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        <span class="bc-cur">{{ $student->fullName() }}</span>
    </div>

    {{-- Toast --}}
    @if ($paymentSaved || $absenceSaved)
        <div class="toast toast-ok" x-data x-init="setTimeout(() => $el.remove(), 3000)">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $paymentSaved ? 'Paiement enregistré avec succès.' : 'Présence enregistrée.' }}
        </div>
    @endif

    {{-- En-tête élève --}}
    <div class="student-header">
        <div class="student-header-bg">
            @if ($student->photo_path)
                <div class="student-avatar-wrap" style="background:transparent; border:none;">
                    <img src="{{ asset('storage/'.$student->photo_path) }}" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                </div>
            @else
                <div class="student-avatar-wrap">
                    {{ strtoupper(substr($student->first_name,0,1).substr($student->last_name,0,1)) }}
                </div>
            @endif
        </div>
        <div class="student-header-body">
            <div>
                <div class="student-header-name">{{ $student->fullName() }}</div>
                <div class="student-header-meta">
                    <span class="meta-chip meta-chip-matric">{{ $student->matricule }}</span>

                    {{-- Badge basé sur l'inscription dans l'année SÉLECTIONNÉE --}}
                    @if ($currentSsy)
                        <span class="meta-chip meta-chip-active">
                            Inscrit {{ $year?->label }}
                        </span>
                        <span class="meta-text">
                            {{ $currentSsy->schoolClass->name }} — {{ $currentSsy->schoolClass->level?->name }}
                        </span>
                    @else
                        <span class="meta-chip meta-chip-inactive">
                            Non inscrit {{ $year?->label }}
                        </span>
                        @if ($lastSsy)
                            <span class="meta-text">
                                Dernière inscription : {{ $lastSsy->schoolClass->name }} ({{ $lastSsy->academicYear->label }})
                            </span>
                        @endif
                    @endif
                </div>
            </div>
            <div class="student-header-actions">
                @if (! $isParent)
                <a href="{{ route('students.edit', $student) }}" class="btn-outline">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Modifier
                </a>
                @endif

                {{-- Bouton contextuel selon l'état d'inscription --}}
                @if ($currentSsy && ! $isParent)
                    
                    {{-- Inscrit cette année → proposer réinscription pour l'année suivante --}}
                    <a href="{{ route('students.reenroll', $student) }}" class="btn-primary-sm">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Réinscrire pour une autre année
                    </a>
                    
                @elseif ($lastSsy)
                    {{-- A déjà été inscrit mais pas cette année → réinscription sur l'année sélectionnée --}}
                    <a href="{{ route('students.reenroll', $student) }}" class="btn-primary-sm">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Réinscrire pour {{ $year?->label }}
                    </a>
                @else
                    {{-- Jamais inscrit → nouvelle inscription --}}
                    <a href="{{ route('students.enroll') }}" class="btn-primary-sm">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Inscrire {{ $year?->label }}
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Navigation sections --}}
    <nav class="sec-nav">
        @foreach ([
            ['k'=>'overview',  'l'=>'Vue d\'ensemble', 'i'=>'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            ['k'=>'academic',  'l'=>'Académique',       'i'=>'M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z'],
            ['k'=>'finance',   'l'=>'Finances',         'i'=>'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
            ['k'=>'grades',    'l'=>'Notes',            'i'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01'],
            ['k'=>'bulletins', 'l'=>'Bulletins',        'i'=>'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
            ['k'=>'absences',  'l'=>'Absences',         'i'=>'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ] as $s)
            <button wire:click="$set('activeSection','{{ $s['k'] }}')"
                    class="sec-btn {{ $activeSection === $s['k'] ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $s['i'] }}"/>
                </svg>
                {{ $s['l'] }}
            </button>
        @endforeach
    </nav>

    {{-- ═══════════════════════════════════════ --}}
    {{-- OVERVIEW --}}
    {{-- ═══════════════════════════════════════ --}}
    @if ($activeSection === 'overview')
        <div class="detail-grid">
            <div>
                {{-- Informations personnelles --}}
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Informations personnelles</span>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Prénom</div>
                                <div class="info-value">{{ $student->first_name }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Nom</div>
                                <div class="info-value">{{ $student->last_name }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Genre</div>
                                <div class="info-value">{{ $student->gender === 'M' ? 'Masculin' : ($student->gender === 'F' ? 'Féminin' : '—') }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Date de naissance</div>
                                <div class="info-value">{{ $student->birth_date?->format('d/m/Y') ?? '—' }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Lieu de naissance</div>
                                <div class="info-value">{{ $student->birth_place ?? '—' }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Inscrit le</div>
                                <div class="info-value">{{ $student->created_at->format('d/m/Y') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tuteurs --}}
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Tuteurs</span>
                    </div>
                    <div class="card-body" style="padding-top:.5rem;padding-bottom:.5rem;">
                        @forelse ($guardians as $guardian)
                            <div class="guardian-row">
                                <div class="guardian-avatar">
                                    {{ strtoupper(substr($guardian->first_name,0,1).substr($guardian->last_name,0,1)) }}
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div class="guardian-name">{{ $guardian->fullName() }}</div>
                                    <div class="guardian-detail">
                                        {{ $guardian->pivot->relationship ? ucfirst($guardian->pivot->relationship) : '' }}
                                        @if ($guardian->phone) · {{ $guardian->phone }} @endif
                                        @if ($guardian->email) · {{ $guardian->email }} @endif
                                    </div>
                                </div>
                                @if (! $isParent && auth()->user()->hasAnyRole(['admin','directeur']))
                                    @if ($guardian->user_id)
                                        <div style="display:flex;align-items:center;gap:.5rem;">
                                            <span style="color:#166534;font-size:.8rem;">✓ Compte actif</span>
                                            <button wire:click="createParentAccount({{ $guardian->id }})"
                                                    style="font-size:.75rem;padding:.2rem .5rem;border-radius:5px;background:rgba(232,168,56,.1);color:#8A6010;border:1px solid rgba(232,168,56,.2);cursor:pointer;">
                                                Réinitialiser
                                            </button>
                                        </div>
                                    @else
                                        <button wire:click="createParentAccount({{ $guardian->id }})"
                                                style="font-size:.8rem;padding:.3rem .65rem;border-radius:6px;background:rgba(42,63,126,.08);color:var(--sidebar-soft);border:none;cursor:pointer;">
                                            Créer compte parent
                                        </button>
                                    @endif
                                @endif
                            </div>
                        @empty
                            <div style="font-size:.875rem;color:var(--ink);opacity:.4;padding:.75rem 0;text-align:center;">Aucun tuteur enregistré.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="sticky-sidebar">
                {{-- Résumé financier --}}
                <div class="side-card">
                    <div class="side-card-header">Finances {{ $year?->label }}</div>
                    
                    @if ($feeStructure)
                    <div class="side-card-body">
                        <div class="side-row">
                            <span class="side-label">Scolarité par an</span>
                            <span class="side-value">{{ number_format($feeStructure->amount,0,',',' ') }} DJF</span>
                        </div>
                        <div class="side-row">
                            <span class="side-label">Inscription</span>
                            <span class="side-value">{{ number_format($feeStructure->inscription_fee,0,',',' ') }} DJF</span>
                        </div>
                        </div>
                    @endif
                    <div class="side-card-body">
                        <div class="side-row">
                            <span class="side-label">Total dû</span>
                            <span class="side-value">{{ number_format($totalDue,0,',',' ') }} DJF</span>
                        </div>
                        <div class="side-row">
                            <span class="side-label">Payé</span>
                            <span class="side-value" style="color:#166534;">{{ number_format($totalPaid,0,',',' ') }} DJF</span>
                        </div>
                        <div class="side-row" style="border-top:1px solid var(--line);margin-top:.5rem;padding-top:.5rem;">
                            <span class="side-label">Reste à payer</span>
                            <span class="side-value" style="color:{{ $balance > 0 ? 'var(--accent-red)' : '#166634' }};">{{ number_format(max(0,$balance),0,',',' ') }} DJF</span>
                        </div>
                    </div>
                </div>
                {{-- Résumé présences --}}
                <div class="side-card">
                    <div class="side-card-header">Présences {{ $year?->label }}</div>
                    <div class="side-card-body">
                        <div class="side-row">
                            <span class="side-label">Total jours</span>
                            <span class="side-value">{{ $absenceStats['total'] }}</span>
                        </div>
                        <div class="side-row">
                            <span class="side-label">Absences</span>
                            <span class="side-value" style="color:var(--accent-red);">{{ $absenceStats['absent'] }}</span>
                        </div>
                        <div class="side-row">
                            <span class="side-label">Retards</span>
                            <span class="side-value" style="color:#8A6010;">{{ $absenceStats['late'] }}</span>
                        </div>
                        <div class="side-row">
                            <span class="side-label">Justifiées</span>
                            <span class="side-value" style="color:#166634;">{{ $absenceStats['excused'] }}</span>
                        </div>
                    </div>
                </div>
                {{-- Infos classe --}}
                @if ($currentSsy)
                    <div class="side-card">
                        <div class="side-card-header">Classe actuelle</div>
                        <div class="side-card-body">
                            <div class="side-row">
                                <span class="side-label">Classe</span>
                                <span class="side-value">{{ $currentSsy->schoolClass->name }}</span>
                            </div>
                            <div class="side-row">
                                <span class="side-label">Niveau</span>
                                <span class="side-value">{{ $currentSsy->schoolClass->level?->name }}</span>
                            </div>
                            <div class="side-row">
                                <span class="side-label">Cycle</span>
                                <span class="side-value">{{ $currentSsy->schoolClass->level?->cycle }}</span>
                            </div>
                            <div class="side-row">
                                <span class="side-label">Inscrit le</span>
                                <span class="side-value">{{ $currentSsy->enrolled_at->format('d/m/Y') }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════ --}}
    {{-- ACADÉMIQUE --}}
    {{-- ═══════════════════════════════════════ --}}
    @if ($activeSection === 'academic')
        <div class="detail-grid">
            <div>
                {{-- Matières & enseignants --}}
                @if ($currentSsy)
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">Matières — {{ $currentSsy->schoolClass->name }}</span>
                            <span class="card-meta">{{ $currentSsy->schoolClass->classSubjects->count() }} matières</span>
                        </div>
                        <div class="card-body">
                            @if ($currentSsy->schoolClass->classSubjects->isEmpty())
                                <div class="empty-section">Aucune matière assignée à cette classe.</div>
                            @else
                                <div class="subject-grid">
                                    @foreach ($currentSsy->schoolClass->classSubjects as $cs)
                                        <div class="subject-card">
                                            <div class="subject-dot" style="background:{{ $cs->subject->color ?? 'var(--sidebar)' }}"></div>
                                            <div class="subject-name-sm">{{ $cs->subject->name }}</div>
                                            <div class="subject-teacher">{{ $cs->teacher?->user?->name ?? '—' }}</div>
                                            <div class="subject-coeff">Coeff {{ $cs->subject->coefficient }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Historique scolaire --}}
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">Historique scolaire</span>
                    </div>
                    <div class="card-body">
                        @if ($history->isEmpty())
                            <div class="empty-section">Aucun historique.</div>
                        @else
                            <div class="history-timeline">
                                @foreach ($history as $h)
                                    @php
                                        $dotColor = match($h->status) {
                                            'enrolled'    => 'var(--sidebar)',
                                            'transferred' => 'var(--accent-red)',
                                            'repeated'    => '#8A6010',
                                            default       => 'var(--line)',
                                        };
                                    @endphp
                                    <div class="history-item">
                                        <div class="history-dot" style="background:{{ $dotColor }}"></div>
                                        <div class="history-year">{{ $h->academicYear->label }}</div>
                                        <div class="history-class-name">{{ $h->schoolClass->name }}</div>
                                        <div class="history-level">{{ $h->schoolClass->level?->name }} — {{ $h->schoolClass->level?->cycle }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="sticky-sidebar">
                @if ($currentSsy)
                    <div class="side-card">
                        <div class="side-card-header">Année en cours</div>
                        <div class="side-card-body">
                            <div class="side-row"><span class="side-label">Année</span><span class="side-value">{{ $year?->label }}</span></div>
                            <div class="side-row"><span class="side-label">Classe</span><span class="side-value">{{ $currentSsy->schoolClass->name }}</span></div>
                            <div class="side-row"><span class="side-label">Effectif</span><span class="side-value">{{ $currentSsy->schoolClass->studentSchoolYears()->count() }} élèves</span></div>
                            @if ($currentSsy->schoolClass->mainTeacher)
                                <div class="side-row"><span class="side-label">Prof. principal</span><span class="side-value">{{ $currentSsy->schoolClass->mainTeacher?->user?->name }}</span></div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="side-card">
                        <div class="side-card-body">
                            <p style="font-size:.8125rem;color:var(--ink);opacity:.5;text-align:center;margin-bottom:.75rem;">
                                Pas inscrit pour {{ $year?->label }}.
                            </p>
                            @if ($lastSsy)
                                <p style="font-size:.75rem;color:var(--ink);opacity:.4;text-align:center;margin-bottom:.75rem;">
                                    Dernière inscription : {{ $lastSsy->schoolClass->name }} ({{ $lastSsy->academicYear->label }})
                                </p>
                                <a href="{{ route('students.reenroll', $student) }}"
                                   style="display:flex;align-items:center;justify-content:center;gap:5px;padding:.45rem .875rem;border-radius:8px;background:var(--sidebar);color:#FFFFFF;font-size:.8125rem;font-weight:600;text-decoration:none;">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    Réinscrire pour {{ $year?->label }}
                                </a>
                            @else
                                <a href="{{ route('students.enroll') }}"
                                   style="display:flex;align-items:center;justify-content:center;gap:5px;padding:.45rem .875rem;border-radius:8px;background:var(--sidebar);color:#FFFFFF;font-size:.8125rem;font-weight:600;text-decoration:none;">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    Inscrire pour {{ $year?->label }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════ --}}
    {{-- FINANCES --}}
    {{-- ═══════════════════════════════════════ --}}
    @if ($activeSection === 'finance')

        {{-- KPIs --}}
        <div class="finance-kpis">
            <div class="kpi-box">
                <div class="kpi-label">Total dû</div>
                <div class="kpi-value kpi-due">{{ number_format($totalDue,0,',',' ') }}</div>
                <div style="font-size:.7rem;color:var(--ink);opacity:.4;margin-top:2px;">DJF</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Total payé</div>
                <div class="kpi-value kpi-paid">{{ number_format($totalPaid,0,',',' ') }}</div>
                <div style="font-size:.7rem;color:var(--ink);opacity:.4;margin-top:2px;">DJF</div>
            </div>
            <div class="kpi-box">
                <div class="kpi-label">Reste à payer</div>
                <div class="kpi-value kpi-bal {{ $balance <= 0 ? 'ok' : '' }}">{{ number_format(max(0,$balance),0,',',' ') }}</div>
                <div style="font-size:.7rem;color:var(--ink);opacity:.4;margin-top:2px;">DJF</div>
            </div>
        </div>

        {{-- Factures --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">Factures</span>
                <span class="card-meta">{{ $allInvoices->count() }} au total</span>
            </div>
            @if ($allInvoices->isEmpty())
                <div class="empty-section">Aucune facture générée.</div>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Année</th>
                            <th>Montant dû</th>
                            <th>Payé</th>
                            <th>Reste</th>
                            <th>Echéance</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($allInvoices as $invoice)
                            @php
                                $bal = $invoice->balance();
                                $badgeCss = match($invoice->status) {
                                    'paid'           => 'b-paid',
                                    'partially_paid' => 'b-partial',
                                    'overdue'        => 'b-overdue',
                                    default          => 'b-unpaid',
                                };
                                $badgeLbl = match($invoice->status) {
                                    'paid'           => 'Soldée',
                                    'partially_paid' => 'Partiel',
                                    'overdue'        => 'En retard',
                                    default          => 'Impayée',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <div class="inv-label">{{ $invoice->label ?? $invoice->invoice_number }}</div>
                                    <div class="inv-year">{{ $invoice->invoice_number }}</div>
                                </td>
                                <td class="inv-year">{{ $invoice->studentSchoolYear->academicYear->label }}</td>
                                <td class="amount-mono">{{ number_format($invoice->amount_due,0,',',' ') }}</td>
                                <td class="amount-mono amount-paid">{{ number_format($invoice->amount_paid,0,',',' ') }}</td>
                                <td class="amount-mono {{ $bal > 0 ? 'amount-bal' : '' }}">{{ number_format(max(0,$bal),0,',',' ') }}</td>
                                <td class="inv-year">{{ $invoice->due_at?->format('d/m/Y') ?? '—' }}</td>
                                <td><span class="badge {{ $badgeCss }}">{{ $badgeLbl }}</span></td>
                                <td>
                                    @if ($invoice->status !== 'paid')
                                    @if (! $isParent)
                                        <button wire:click="openPayment({{ $invoice->id }})" class="btn-pay">
                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Encaisser
                                        </button>

                                    @endif
                                    @endif
                                </td>
                            </tr>
                            {{-- Paiements reçus --}}
                            @if ($invoice->payments->isNotEmpty())
                                <tr>
                                    <td colspan="8" style="padding:0;background:rgba(30,45,90,.02);">
                                        <div class="payments-sub">
                                            @foreach ($invoice->payments as $p)
                                                <div class="payment-row">
                                                    <div>
                                                        <span class="payment-method">{{ $p->method }}</span>
                                                        @if ($p->reference) <span style="font-size:.75rem;color:var(--ink);opacity:.5;margin-left:.35rem;">{{ $p->reference }}</span> @endif
                                                    </div>
                                                    <div style="font-size:.8rem;color:var(--ink);opacity:.5;">{{ $p->paid_at->format('d/m/Y') }}</div>
                                                    <div class="amount-mono amount-paid">+{{ number_format($p->amount,0,',',' ') }} DJF</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    {{-- ═══════════════════════════════════════ --}}
    {{-- NOTES --}}
    {{-- ═══════════════════════════════════════ --}}
    @if ($activeSection === 'grades')
        @if ($grades->isEmpty())
            <div class="empty-section" style="border:1.5px dashed var(--line);border-radius:12px;">Aucune note enregistrée pour {{ $year?->label }}.</div>
        @else
            @foreach ($grades as $subjectName => $subjectGrades)
                @php
                    $subject  = $subjectGrades->first()->evaluation->subject;
                    $avg      = $subjectGrades->avg('score');
                    $avgCss   = $avg >= 14 ? 'avg-good' : ($avg >= 10 ? 'avg-mid' : 'avg-bad');
                @endphp
                <div class="subject-grades">
                    <div class="sg-header">
                        <div class="sg-color" style="background:{{ $subject?->color ?? 'var(--sidebar)' }}"></div>
                        <span class="sg-name">{{ $subjectName }}</span>
                        <span class="sg-coeff">Coeff {{ $subject?->coefficient }}</span>
                        <span class="sg-avg {{ $avgCss }}">{{ number_format($avg,2) }}/20</span>
                    </div>
                    <div class="grade-chips">
                        @foreach ($subjectGrades as $grade)
                            @php
                                $score    = $grade->score;
                                $chipColor = $score >= 14 ? '#166534' : ($score >= 10 ? '#8A6010' : 'var(--accent-red)');
                            @endphp
                            <div class="grade-chip">
                                <div class="gc-score" style="color:{{ $chipColor }}">{{ $score }}</div>
                                <div class="gc-label">{{ $grade->evaluation?->type }}</div>
                                <div class="gc-label">{{ $grade->evaluation?->period }}</div>
                                <div class="gc-date">{{ $grade->evaluation?->date?->format('d/m') }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    @endif

    {{-- ═══════════════════════════════════════ --}}
    {{-- BULLETINS --}}
    {{-- ═══════════════════════════════════════ --}}
    @if ($activeSection === 'bulletins')
        <div class="card">
            <div class="card-header">
                <span class="card-title">Bulletins de notes</span>
            </div>
            @if ($bulletins->isEmpty())
                <div class="empty-section">Aucun bulletin généré.</div>
            @else
                @foreach ($bulletins as $bulletin)
                    @php
                        $avgCss = !$bulletin->average ? '' : ($bulletin->average >= 14 ? 'avg-good' : ($bulletin->average >= 10 ? 'avg-mid' : 'avg-bad'));
                    @endphp
                    <div class="bulletin-row">
                        <div>
                            <div class="bulletin-period">{{ $bulletin->period }}</div>
                            <div class="bulletin-year">{{ $bulletin->studentSchoolYear->academicYear->label }}</div>
                        </div>
                        <div style="text-align:center;">
                            <div class="bulletin-avg {{ $avgCss }}">
                                {{ $bulletin->average ? number_format($bulletin->average,2).'/20' : '—' }}
                            </div>
                            @if ($bulletin->rank)
                                <div class="bulletin-rank">{{ $bulletin->rank }}ème / classe</div>
                            @endif
                        </div>
                        @if ($bulletin->general_comment)
                            <div style="font-size:.8125rem;color:var(--ink);opacity:.6;font-style:italic;flex:1;max-width:240px;">
                                "{{ Str::limit($bulletin->general_comment, 80) }}"
                            </div>
                        @else
                            <div></div>
                        @endif
                        @if ($bulletin->pdf_path)
                            {{-- Remplacer l'ancien bloc pdf --}}
                            <div style="display:flex;gap:.4rem;">
                                <a href="{{ route('bulletins.show', [$student, $bulletin]) }}"
                                class="btn-dl">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    Voir
                                </a>
                                <a href="{{ route('bulletins.pdf', [$student, $bulletin]) }}"
                                target="_blank" class="btn-dl">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                    PDF
                                </a>
                            </div>
                        @else
                            <span style="font-size:.75rem;color:var(--ink);opacity:.35;">Pas de PDF</span>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>
    @endif

    {{-- ═══════════════════════════════════════ --}}
    {{-- ABSENCES --}}
    {{-- ═══════════════════════════════════════ --}}
    @if ($activeSection === 'absences')

        {{-- Stats --}}
        <div class="absence-stats">
            <div class="abs-stat ab">
                <div class="abs-num">{{ $absenceStats['absent'] }}</div>
                <div class="abs-lbl">Absences</div>
            </div>
            <div class="abs-stat lt">
                <div class="abs-num">{{ $absenceStats['late'] }}</div>
                <div class="abs-lbl">Retards</div>
            </div>
            <div class="abs-stat ex">
                <div class="abs-num">{{ $absenceStats['excused'] }}</div>
                <div class="abs-lbl">Justifiées</div>
            </div>
        </div>

        {{-- Formulaire ajout absence --}}
        <div style="display:flex;justify-content:flex-end;margin-bottom:.75rem;">
            <button wire:click="$toggle('showAbsenceForm')" class="btn-primary-sm">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Enregistrer une présence
            </button>
        </div>

        @if ($showAbsenceForm)
            <div class="absence-form">
                <div class="form-row-inline">
                    <div class="form-field-sm">
                        <label class="form-label-sm">Date</label>
                        <input wire:model="absenceDate" type="date" class="form-input-sm">
                    </div>
                    <div class="form-field-sm">
                        <label class="form-label-sm">Statut</label>
                        <select wire:model="absenceStatus" class="form-select-sm">
                            <option value="present">Présent</option>
                            <option value="absent">Absent</option>
                            <option value="late">En retard</option>
                            <option value="excused">Absence justifiée</option>
                        </select>
                    </div>
                    <div class="form-field-sm">
                        <label class="form-label-sm">Justificatif</label>
                        <input wire:model="absenceJustif" type="text" class="form-input-sm" placeholder="Certificat médical...">
                    </div>
                </div>
                <div class="form-actions-sm">
                    <button wire:click="$set('showAbsenceForm',false)" class="btn-sm-cancel">Annuler</button>
                    <button wire:click="saveAbsence" class="btn-sm-save">Enregistrer</button>
                </div>
            </div>
        @endif

        {{-- Liste des absences --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">Relevé de présences — {{ $year?->label }}</span>
                <span class="card-meta">{{ $attendances->count() }} entrées</span>
            </div>
            <div class="card-body" style="padding-top:.5rem;padding-bottom:.5rem;">
                @forelse ($attendances as $att)
                    @php
                        $statusCss = match($att->status) {
                            'absent'  => 'as-absent',
                            'late'    => 'as-late',
                            'excused' => 'as-excused',
                            default   => 'as-present',
                        };
                        $statusLbl = match($att->status) {
                            'absent'  => 'Absent',
                            'late'    => 'Retard',
                            'excused' => 'Justifiée',
                            default   => 'Présent',
                        };
                    @endphp
                    <div class="abs-row">
                        <div>
                            <div class="abs-date">{{ $att->date->format('d/m/Y') }}</div>
                            @if ($att->justification)
                                <div class="abs-justif">{{ $att->justification }}</div>
                            @endif
                        </div>
                        <span class="abs-status {{ $statusCss }}">{{ $statusLbl }}</span>
                    </div>
                @empty
                    <div class="empty-section">Aucune entrée de présence pour cette année.</div>
                @endforelse
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════ --}}
    {{-- MODAL PAIEMENT --}}
    {{-- ═══════════════════════════════════════ --}}
    @if ($payingInvoiceId)
        @php $inv = $allInvoices->firstWhere('id', $payingInvoiceId); @endphp
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Encaisser un paiement</div>
                <div class="modal-sub">{{ $inv?->label ?? $inv?->invoice_number }} · Reste : {{ number_format($inv?->balance() ?? 0, 0, ',', ' ') }} DJF</div>

                <div class="modal-field">
                    <label class="modal-label">Montant encaissé (DJF)</label>
                    <input wire:model="payAmount" type="number" min="1" class="modal-input">
                    @error('payAmount') <span style="font-size:.75rem;color:var(--accent-red);margin-top:.2rem;">{{ $message }}</span> @enderror
                </div>
                <div class="modal-field">
                    <label class="modal-label">Méthode de paiement</label>
                    <select wire:model="payMethod" class="modal-select">
                        <option value="especes">Espèces</option>
                        <option value="d-money">D-Money</option>
                        <option value="virement">Virement</option>
                        <option value="cheque">Chèque</option>
                        <option value="carte">Carte</option>
                    </select>
                </div>
                <div class="modal-field">
                    <label class="modal-label">Référence / N° reçu</label>
                    <input wire:model="payReference" type="text" class="modal-input" placeholder="Optionnel">
                </div>
                <div class="modal-actions">
                    <button wire:click="$set('payingInvoiceId',null)" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="savePayment" class="btn-modal-pay">Confirmer</button>
                </div>
            </div>
        </div>
    @endif


    


    {{-- ── Modal reset mot de passe parent ── --}}
    @if ($showResetModal)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">Compte parent existant</div>
                <div class="modal-sub">
                    Ce tuteur a déjà un compte associé à
                    <strong style="font-family:'JetBrains Mono',monospace;color:var(--sidebar-soft);">{{ $resetEmail }}</strong>.
                    Voulez-vous réinitialiser son mot de passe ?
                </div>

                <div class="modal-field">
                    <label class="modal-label">Nouveau mot de passe</label>
                    <div style="position:relative;">
                        <input wire:model="resetPassword"
                            type="password"
                            class="modal-input"
                            placeholder="8 caractères minimum">
                    </div>
                    @error('resetPassword')
                        <span style="font-size:.75rem;color:var(--accent-red);margin-top:.2rem;">{{ $message }}</span>
                    @enderror
                </div>

                <div class="modal-field">
                    <label class="modal-label">Confirmer le mot de passe</label>
                    <input wire:model="resetPasswordConfirm"
                        type="password"
                        class="modal-input"
                        placeholder="Répéter le mot de passe">
                    @error('resetPasswordConfirm')
                        <span style="font-size:.75rem;color:var(--accent-red);margin-top:.2rem;">{{ $message }}</span>
                    @enderror
                </div>

                <div class="modal-actions">
                    <button wire:click="closeResetModal" class="btn-modal-cancel">Annuler</button>
                    <button wire:click="resetParentPassword"
                            style="padding:.45rem 1.1rem;border-radius:8px;background:#8A6010;color:#FFFFFF;font-size:.875rem;font-weight:600;font-family:'Inter',sans-serif;border:none;cursor:pointer;">
                        Réinitialiser le mot de passe
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Modal confirmation compte créé / réinitialisé ── --}}
    @if ($parentAccountCreated)
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-title">
                    {{ $resetDone ? '✓ Mot de passe réinitialisé' : '✓ Compte parent créé' }}
                </div>
                <div class="modal-sub">Communiquez ces identifiants au tuteur.</div>

                <div style="background:var(--paper);border:1px solid var(--line);border-radius:8px;padding:1rem;margin-bottom:1.25rem;">
                    <div style="margin-bottom:.75rem;">
                        <div style="font-family:'JetBrains Mono',monospace;font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:var(--ink);opacity:.4;margin-bottom:4px;">Email de connexion</div>
                        <div style="font-family:'JetBrains Mono',monospace;font-size:.9rem;font-weight:600;color:var(--sidebar-soft);">
                            {{ $parentAccountEmail }}
                        </div>
                    </div>
                    <div>
                        <div style="font-family:'JetBrains Mono',monospace;font-size:9px;text-transform:uppercase;letter-spacing:.08em;color:var(--ink);opacity:.4;margin-bottom:4px;">
                            {{ $resetDone ? 'Nouveau mot de passe' : 'Mot de passe temporaire' }}
                        </div>
                        <div style="font-family:'JetBrains Mono',monospace;font-size:1.1rem;font-weight:700;color:var(--accent-red);letter-spacing:.05em;">
                            {{ $parentAccountTempPass }}
                        </div>
                    </div>
                </div>

                <p style="font-size:.8rem;color:var(--ink);opacity:.5;margin-bottom:1.25rem;">
                    ⚠ Notez ces informations — elles ne seront plus affichées.
                    @if (! $resetDone) Le tuteur est invité à changer ce mot de passe. @endif
                </p>

                <div style="display:flex;justify-content:flex-end;">
                    <button wire:click="$set('parentAccountCreated',false);$set('resetDone',false)"
                            class="btn-modal-pay">
                        Compris, fermer
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>