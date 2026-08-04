<?php

use App\Models\PaymentReceipt;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Services\AcademicYearService;
use App\Services\Finance\CashSessionService;
use App\Services\Finance\PaymentService;
use Livewire\Component;

new class extends Component
{
    public string $search = '';
    public ?int   $studentId = null;

    public string $amount    = '';
    public string $method    = 'cash';
    public string $reference = '';
    public string $paidAt    = '';
    public string $note      = '';

    public string $mode   = 'auto';       // auto | manual
    public array  $manual = [];           // [invoice_id => montant]

    public ?string $error   = null;
    public ?int    $receiptId = null;

    // Ouverture de caisse
    public string $openingFloat = '0';

    public function mount(): void
    {
        $this->paidAt = now()->format('Y-m-d\TH:i');
    }

    public function openSession(): void
    {
        try {
            app(CashSessionService::class)->open(
                auth()->user()->school_id,
                auth()->id(),
                (int) $this->openingFloat
            );
            $this->error = null;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function selectStudent(int $id): void
    {
        $this->studentId = $id;
        $this->search    = '';
        $this->reset(['amount', 'manual', 'error', 'receiptId']);
        $this->mode = 'auto';
    }

    public function clearStudent(): void
    {
        $this->reset(['studentId', 'amount', 'manual', 'error', 'receiptId', 'reference', 'note']);
        $this->mode = 'auto';
    }

    /** Applique le reste dû total dans le champ montant. */
    public function fillFullBalance(): void
    {
        $this->amount = (string) $this->openInvoices()->sum(fn ($i) => $i->balance());
    }

    /** Applique le reste dû d'une seule échéance. */
    public function fillInvoice(int $invoiceId): void
    {
        $invoice = $this->openInvoices()->firstWhere('id', $invoiceId);
        if (! $invoice) return;

        $this->mode   = 'manual';
        $this->manual = [$invoiceId => $invoice->balance()];
        $this->amount = (string) $invoice->balance();
    }

    public function updatedMode(): void
    {
        $this->manual = [];
    }

    public function updatedManual(): void
    {
        // En mode manuel, le montant encaissé suit la somme des affectations
        if ($this->mode === 'manual') {
            $this->amount = (string) collect($this->manual)->sum(fn ($v) => (int) $v);
        }
    }

    public function collect(): void
    {
        $this->error = null;

        $student = Student::find($this->studentId);
        $year    = AcademicYearService::current();

        if (! $student || ! $year) {
            $this->error = 'Élève ou année académique introuvable.';
            return;
        }

        try {
            $receipt = app(PaymentService::class)->collect(
                $student,
                $year,
                (int) $this->amount,
                $this->method,
                auth()->id(),
                [
                    'reference'   => $this->reference ?: null,
                    'paid_at'     => $this->paidAt ?: now(),
                    'note'        => $this->note ?: null,
                    'allocations' => $this->mode === 'manual' ? $this->manual : null,
                ]
            );

            $this->receiptId = $receipt->id;
            $this->reset(['amount', 'manual', 'reference', 'note']);
            $this->mode = 'auto';

        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    /** Factures ouvertes de l'élève, ordonnées par échéance. */
    private function openInvoices()
    {
        if (! $this->studentId) return collect();

        $year = AcademicYearService::current();
        if (! $year) return collect();

        return StudentInvoice::where('academic_year_id', $year->id)
            ->whereHas('studentSchoolYear', fn ($q) => $q->where('student_id', $this->studentId))
            ->open()
            ->with('feeStructure')
            ->orderBy('due_at')->orderBy('id')
            ->get();
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;
        $year     = AcademicYearService::current();

        $session = app(CashSessionService::class)->currentFor($schoolId, auth()->id());

        $results = collect();
        if (strlen($this->search) >= 2) {
            $results = Student::where('school_id', $schoolId)
                ->where(function ($q) {
                    $q->where('matricule', 'like', "%{$this->search}%")
                      ->orWhere('first_name', 'like', "%{$this->search}%")
                      ->orWhere('last_name', 'like', "%{$this->search}%");
                })
                ->limit(8)->get();
        }

        $student  = $this->studentId ? Student::with('currentSchoolYear.schoolClass')->find($this->studentId) : null;
        $invoices = $this->openInvoices();

        //dd($invoices);

        $totalDue  = $invoices->sum('amount_due');
        $totalPaid = $invoices->sum('amount_paid');
        $totalLeft = $invoices->sum(fn ($i) => $i->balance());

        // Aperçu de la répartition avant validation
        $preview   = [];
        $overflow  = 0;
        $entered   = (int) $this->amount;

        if ($entered > 0 && $invoices->isNotEmpty()) {
            if ($this->mode === 'auto') {
                $rest = $entered;
                foreach ($invoices as $inv) {
                    if ($rest <= 0) break;
                    $take = min($inv->balance(), $rest);
                    if ($take > 0) { $preview[$inv->id] = $take; $rest -= $take; }
                }
                $overflow = $rest;
            } else {
                foreach ($this->manual as $id => $v) {
                    if ((int) $v > 0) $preview[(int) $id] = (int) $v;
                }
            }
        }

        $receipt = $this->receiptId
            ? PaymentReceipt::with('lines.invoice')->find($this->receiptId)
            : null;

        // Historique récent de l'élève
        $history = $student
            ? PaymentReceipt::where('student_id', $student->id)
                ->valid()->latest('paid_at')->limit(5)->get()
            : collect();

        return compact(
            'year', 'session', 'results', 'student', 'invoices',
            'totalDue', 'totalPaid', 'totalLeft', 'preview', 'overflow', 'receipt', 'history'
        );
    }
}; ?>

<style>
    .pay-grid { display:grid; grid-template-columns:1fr 340px; gap:1.5rem; align-items:start; }
    @media (max-width:960px) { .pay-grid { grid-template-columns:1fr; } }

    .card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.25rem; }
    .card:last-child { margin-bottom:0; }
    .card-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; gap:.65rem; }
    .card-header-icon { width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .card-header-icon svg { width:15px; height:15px; }
    .card-title { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); }
    .card-body { padding:1.25rem 1.5rem; }

    .form-field { display:flex; flex-direction:column; gap:.35rem; }
    .form-label { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600; text-transform:uppercase; letter-spacing:.08em; color:var(--ink); opacity:.5; }
    .form-input, .form-select-inp { padding:.5rem .75rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; width:100%; }
    .form-input:focus, .form-select-inp:focus { border-color:var(--sidebar-soft); box-shadow:0 0 0 3px rgba(42,63,126,.08); }
    .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem; }
    @media (max-width:600px) { .form-grid-2 { grid-template-columns:1fr; } }

    /* Caisse */
    .session-bar { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.65rem 1.25rem; border-radius:10px; background:rgba(30,120,80,.07); border:1px solid rgba(30,120,80,.18); margin-bottom:1.25rem; }
    .session-bar.closed { background:rgba(232,168,56,.1); border-color:rgba(232,168,56,.3); }
    .session-meta { font-size:.8125rem; color:var(--ink); }
    .session-dot { width:8px; height:8px; border-radius:50%; background:#166534; display:inline-block; margin-right:.4rem; }
    .session-bar.closed .session-dot { background:#8A6010; }

    /* Recherche */
    .search-wrap { position:relative; }
    .search-results { position:absolute; z-index:20; top:calc(100% + 4px); left:0; right:0; border:1px solid var(--line); border-radius:10px; background:var(--paper-raised); box-shadow:0 8px 24px rgba(0,0,0,.08); overflow:hidden; }
    .search-item { display:flex; align-items:center; gap:.65rem; padding:.6rem 1rem; cursor:pointer; border-bottom:1px solid var(--line); }
    .search-item:last-child { border-bottom:none; }
    .search-item:hover { background:rgba(42,63,126,.05); }
    .search-mat { font-family:'JetBrains Mono',monospace; font-size:11px; color:var(--ink); opacity:.5; }

    /* Élève sélectionné */
    .student-head { display:flex; align-items:center; gap:1rem; padding:1rem 1.5rem; background:var(--sidebar); }
    .student-avatar { width:44px; height:44px; border-radius:50%; background:var(--accent); color:var(--sidebar); font-family:'JetBrains Mono',monospace; font-size:13px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .student-name { font-family:'Fraunces',serif; font-size:1.1rem; font-weight:600; color:#FFFFFF; }
    .student-meta { font-size:.8125rem; color:rgba(255,255,255,.6); margin-top:2px; }
    .btn-clear { margin-left:auto; background:rgba(255,255,255,.12); border:none; color:#FFFFFF; border-radius:7px; padding:.35rem .75rem; font-size:.8125rem; cursor:pointer; font-family:'Inter',sans-serif; }

    /* Échéancier */
    .inv-table { width:100%; border-collapse:collapse; }
    .inv-table th { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.1em; color:var(--ink); opacity:.4; text-align:left; padding:.5rem .75rem; border-bottom:1px solid var(--line); }
    .inv-table th.num, .inv-table td.num { text-align:right; }
    .inv-table td { padding:.6rem .75rem; border-bottom:1px solid var(--line); font-size:.875rem; vertical-align:middle; }
    .inv-table tr:last-child td { border-bottom:none; }
    .inv-table tr.allocated { background:rgba(30,120,80,.05); }
    .mono { font-family:'JetBrains Mono',monospace; font-weight:700; font-size:.8125rem; }
    .inv-label { font-weight:600; color:var(--ink); }
    .inv-due { font-family:'JetBrains Mono',monospace; font-size:.7rem; opacity:.45; }
    .st { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; padding:2px 7px; border-radius:4px; white-space:nowrap; }
    .st-pending { background:rgba(42,63,126,.1); color:var(--sidebar-soft); }
    .st-partial { background:rgba(232,168,56,.15); color:#8A6010; }
    .st-overdue { background:rgba(224,92,58,.12); color:#C04020; }
    .alloc-input { width:110px; padding:.3rem .5rem; border-radius:6px; border:1px solid var(--line); background:var(--paper); font-family:'JetBrains Mono',monospace; font-size:.8125rem; text-align:right; color:var(--ink); }
    .alloc-badge { font-family:'JetBrains Mono',monospace; font-size:.8125rem; font-weight:700; color:#166534; }

    /* Totaux */
    .totals { display:grid; grid-template-columns:repeat(3,1fr); gap:.6rem; margin-bottom:1.25rem; }
    .total-box { padding:.75rem 1rem; border-radius:10px; border:1px solid var(--line); background:var(--paper); }
    .total-box.left { background:var(--sidebar); border-color:var(--sidebar); }
    .total-lbl { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.1em; opacity:.45; }
    .total-box.left .total-lbl { color:rgba(255,255,255,.6); opacity:1; }
    .total-val { font-family:'JetBrains Mono',monospace; font-size:1rem; font-weight:700; color:var(--ink); margin-top:3px; }
    .total-box.left .total-val { color:#FFFFFF; }

    /* Mode */
    .mode-toggle { display:flex; gap:.5rem; margin-bottom:1rem; }
    .mode-btn { flex:1; padding:.5rem; border-radius:8px; border:1.5px solid var(--line); background:var(--paper); font-size:.8125rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }
    .mode-btn.active { border-color:var(--sidebar); background:rgba(30,45,90,.07); color:var(--sidebar); }

    .method-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:.5rem; }
    .method-btn { padding:.5rem; border-radius:8px; border:1.5px solid var(--line); background:var(--paper); font-size:.8125rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; text-align:center; }
    .method-btn.active { border-color:var(--sidebar); background:rgba(30,45,90,.07); color:var(--sidebar); }

    .btn-link { background:none; border:none; color:var(--sidebar-soft); font-size:.75rem; cursor:pointer; font-family:'Inter',sans-serif; padding:0; text-decoration:underline; }
    .btn-cash { width:100%; display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:.65rem 1.5rem; border-radius:8px; background:#166534; color:#FFFFFF; font-size:.9rem; font-weight:700; font-family:'Inter',sans-serif; border:none; cursor:pointer; }
    .btn-cash:hover { background:#14532d; }
    .btn-cash:disabled { opacity:.4; cursor:not-allowed; }
    .btn-cash svg { width:16px; height:16px; }
    .btn-plain { display:inline-flex; align-items:center; gap:6px; padding:.5rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.875rem; color:var(--ink); cursor:pointer; text-decoration:none; font-family:'Inter',sans-serif; }

    .alert-error { display:flex; align-items:flex-start; gap:.65rem; padding:.75rem 1rem; border-radius:8px; background:rgba(224,92,58,.08); border:1px solid rgba(224,92,58,.2); color:var(--accent-red); font-size:.8125rem; margin-bottom:1rem; }
    .alert-error svg { width:16px; height:16px; flex-shrink:0; margin-top:1px; }
    .alert-warn { padding:.6rem .875rem; border-radius:8px; background:rgba(232,168,56,.1); border:1px solid rgba(232,168,56,.25); color:#8A6010; font-size:.8125rem; margin-bottom:1rem; }

    /* Reçu */
    .receipt-ok { border-radius:12px; border:1px solid rgba(30,120,80,.25); background:rgba(30,120,80,.05); padding:1.25rem 1.5rem; margin-bottom:1.25rem; }
    .receipt-num { font-family:'JetBrains Mono',monospace; font-size:1.05rem; font-weight:700; color:#166534; }
    .receipt-line { display:flex; justify-content:space-between; font-size:.8125rem; padding:2px 0; }

    .hist-row { display:flex; justify-content:space-between; align-items:center; padding:.5rem 0; border-bottom:1px solid var(--line); font-size:.8125rem; }
    .hist-row:last-child { border-bottom:none; }
    .empty { text-align:center; padding:2rem 1rem; font-size:.875rem; color:var(--ink); opacity:.45; }
</style>

<div>
    {{-- ══ Barre de caisse ══ --}}
    @if ($session)
        <div class="session-bar">
            <div class="session-meta">
                <span class="session-dot"></span>
                <strong>Caisse ouverte</strong> depuis le {{ $session->opened_at->format('d/m/Y à H:i') }}
                · Fond de caisse {{ number_format($session->opening_float, 0, ',', ' ') }} DJF
            </div>
            <a href="{{ route('finances.cashbook') }}" class="btn-plain" wire:navigate>Journal de caisse</a>
        </div>
    @else
        <div class="session-bar closed">
            <div class="session-meta">
                <span class="session-dot"></span>
                <strong>Caisse fermée.</strong> Ouvrez votre caisse pour commencer à encaisser.
            </div>
            <div style="display:flex;gap:.5rem;align-items:center;">
                <input wire:model="openingFloat" type="number" min="0" class="form-input"
                       style="width:150px;" placeholder="Fond de caisse">
                <button wire:click="openSession" class="btn-cash" style="width:auto;padding:.5rem 1.25rem;">
                    Ouvrir la caisse
                </button>
            </div>
        </div>
    @endif

    @if ($error)
        <div class="alert-error">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            {{ $error }}
        </div>
    @endif

    {{-- ══ Reçu généré ══ --}}
    @if ($receipt)
        <div class="receipt-ok">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
                <div>
                    <div class="receipt-num">{{ $receipt->receipt_number }}</div>
                    <div style="font-size:.8125rem;opacity:.6;">
                        {{ number_format($receipt->amount, 0, ',', ' ') }} DJF encaissés
                        · {{ $receipt->methodLabel() }}
                    </div>
                </div>
                <a href="{{ route('finances.receipt', $receipt) }}" target="_blank" class="btn-plain">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="width:15px;height:15px;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Imprimer le reçu
                </a>
            </div>
            @foreach ($receipt->lines as $line)
                <div class="receipt-line">
                    <span style="opacity:.65;">{{ $line->invoice?->invoice_number }}</span>
                    <span class="mono">{{ number_format($line->amount, 0, ',', ' ') }} DJF</span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="pay-grid">
        <div>
            {{-- ══ Recherche / élève ══ --}}
            @if (! $student)
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-icon" style="background:rgba(42,63,126,.08);color:var(--sidebar-soft);">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <span class="card-title">Rechercher un élève</span>
                    </div>
                    <div class="card-body">
                        <div class="search-wrap">
                            <input wire:model.live.debounce.300ms="search" type="text" class="form-input"
                                   placeholder="Matricule, nom ou prénom…" autofocus>
                            @if ($results->isNotEmpty())
                                <div class="search-results">
                                    @foreach ($results as $r)
                                        <div class="search-item" wire:click="selectStudent({{ $r->id }})">
                                            <div>
                                                <div style="font-weight:600;font-size:.875rem;">{{ $r->fullName() }}</div>
                                                <div class="search-mat">{{ $r->matricule }}</div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="student-head">
                        <div class="student-avatar">
                            {{ strtoupper(substr($student->first_name,0,1).substr($student->last_name,0,1)) }}
                        </div>
                        <div>
                            <div class="student-name">{{ $student->fullName() }}</div>
                            <div class="student-meta">
                                {{ $student->matricule }}
                                @if ($student->currentSchoolYear?->schoolClass)
                                    · {{ $student->currentSchoolYear->schoolClass->name}}
                                @endif
                                · {{ $year?->label }}
                            </div>
                        </div>
                        <button wire:click="clearStudent" class="btn-clear">Changer</button>
                    </div>

                    <div class="card-body">
                        <div class="totals">
                            <div class="total-box">
                                <div class="total-lbl">Total dû</div>
                                <div class="total-val">{{ number_format($totalDue, 0, ',', ' ') }}</div>
                            </div>
                            <div class="total-box">
                                <div class="total-lbl">Déjà payé</div>
                                <div class="total-val">{{ number_format($totalPaid, 0, ',', ' ') }}</div>
                            </div>
                            <div class="total-box left">
                                <div class="total-lbl">Reste à payer</div>
                                <div class="total-val">{{ number_format($totalLeft, 0, ',', ' ') }}</div>
                            </div>
                        </div>

                        @if ($invoices->isEmpty())
                            <div class="empty">Aucune échéance ouverte. Tout est soldé pour {{ $year?->label }}.</div>
                        @else
                            <div class="mode-toggle">
                                <button wire:click="$set('mode','auto')"   class="mode-btn {{ $mode==='auto' ? 'active' : '' }}">Répartition automatique</button>
                                <button wire:click="$set('mode','manual')" class="mode-btn {{ $mode==='manual' ? 'active' : '' }}">Répartition manuelle</button>
                            </div>

                            <table class="inv-table">
                                <thead>
                                    <tr>
                                        <th>Échéance</th>
                                        <th>État</th>
                                        <th class="num">Dû</th>
                                        <th class="num">Payé</th>
                                        <th class="num">Reste</th>
                                        <th class="num">{{ $mode === 'manual' ? 'Affecter' : 'Affectation' }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($invoices as $inv)
                                        @php $alloc = $preview[$inv->id] ?? 0; @endphp
                                        <tr class="{{ $alloc > 0 ? 'allocated' : '' }}">
                                            <td>
                                                <div class="inv-label">{{ $inv->invoice_number }}</div>
                                                @if ($inv->due_at)
                                                    <div class="inv-due">Échéance {{ $inv->due_at->format('d/m/Y') }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="st st-{{ $inv->status }}">
                                                    {{ match($inv->status) { 'pending'=>'À payer','partial'=>'Partiel','overdue'=>'En retard',default=>$inv->status } }}
                                                </span>
                                            </td>
                                            <td class="num mono">{{ number_format($inv->amount_due, 0, ',', ' ') }}</td>
                                            <td class="num mono" style="opacity:.55;">{{ number_format($inv->amount_paid, 0, ',', ' ') }}</td>
                                            <td class="num mono" style="color:var(--accent-red);">{{ number_format($inv->balance(), 0, ',', ' ') }}</td>
                                            <td class="num">
                                                @if ($mode === 'manual')
                                                    <input wire:model.live.debounce.400ms="manual.{{ $inv->id }}"
                                                           type="number" min="0" max="{{ $inv->balance() }}"
                                                           class="alloc-input" placeholder="0">
                                                @else
                                                    @if ($alloc > 0)
                                                        <span class="alloc-badge">+{{ number_format($alloc, 0, ',', ' ') }}</span>
                                                    @else
                                                        <button wire:click="fillInvoice({{ $inv->id }})" class="btn-link">solder</button>
                                                    @endif
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>

                {{-- Historique --}}
                @if ($history->isNotEmpty())
                    <div class="card">
                        <div class="card-header"><span class="card-title">Derniers encaissements</span></div>
                        <div class="card-body" style="padding-top:.5rem;padding-bottom:.5rem;">
                            @foreach ($history as $h)
                                <div class="hist-row">
                                    <div>
                                        <span class="mono" style="color:var(--sidebar-soft);">{{ $h->receipt_number }}</span>
                                        <span style="opacity:.5;"> · {{ $h->paid_at->format('d/m/Y') }} · {{ $h->methodLabel() }}</span>
                                    </div>
                                    <span class="mono">{{ number_format($h->amount, 0, ',', ' ') }} DJF</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>

        {{-- ══ Panneau d'encaissement ══ --}}
        <div>
            <div class="card" style="position:sticky;top:1.5rem;">
                <div class="card-header">
                    <div class="card-header-icon" style="background:rgba(30,120,80,.08);color:#1A6040;">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    </div>
                    <span class="card-title">Encaissement</span>
                </div>
                <div class="card-body">
                    @if (! $student)
                        <div class="empty" style="padding:1.5rem 0;">Sélectionnez d'abord un élève.</div>
                    @elseif (! $session)
                        <div class="alert-warn">Ouvrez votre caisse avant d'encaisser.</div>
                    @else
                        <div class="form-field" style="margin-bottom:.35rem;">
                            <label class="form-label">Montant reçu (DJF)</label>
                            <input wire:model.live.debounce.400ms="amount" type="number" min="0"
                                   class="form-input" style="font-family:'JetBrains Mono',monospace;font-size:1.1rem;font-weight:700;"
                                   placeholder="0" @if($mode==='manual') readonly @endif>
                        </div>
                        @if ($mode === 'auto' && $totalLeft > 0)
                            <button wire:click="fillFullBalance" class="btn-link" style="margin-bottom:1rem;">
                                Solder la totalité ({{ number_format($totalLeft, 0, ',', ' ') }} DJF)
                            </button>
                        @else
                            <div style="height:1rem;"></div>
                        @endif

                        @if ($overflow > 0)
                            <div class="alert-warn">
                                Trop-perçu de {{ number_format($overflow, 0, ',', ' ') }} DJF.
                                Le reste dû sur l'année est de {{ number_format($totalLeft, 0, ',', ' ') }} DJF.
                            </div>
                        @endif

                        <div class="form-field" style="margin-bottom:1rem;">
                            <label class="form-label">Mode de règlement</label>
                            <div class="method-grid">
                                @foreach (\App\Models\PaymentReceipt::METHODS as $key => $label)
                                    <button wire:click="$set('method','{{ $key }}')"
                                            class="method-btn {{ $method === $key ? 'active' : '' }}">{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>

                        @if ($method !== 'cash')
                            <div class="form-field" style="margin-bottom:1rem;">
                                <label class="form-label">
                                    {{ $method === 'dmoney' ? 'N° transaction D-Money' : ($method === 'cheque' ? 'N° de chèque' : 'Référence virement') }}
                                </label>
                                <input wire:model="reference" type="text" class="form-input">
                            </div>
                        @endif

                        <div class="form-field" style="margin-bottom:1rem;">
                            <label class="form-label">Date & heure</label>
                            <input wire:model="paidAt" type="datetime-local" class="form-input">
                        </div>

                        <div class="form-field" style="margin-bottom:1.25rem;">
                            <label class="form-label">Note (optionnel)</label>
                            <input wire:model="note" type="text" class="form-input" placeholder="Remis par la mère…">
                        </div>

                        <button wire:click="collect"
                                wire:loading.attr="disabled"
                                class="btn-cash"
                                @if (! $amount || (int)$amount <= 0 || $overflow > 0 || $invoices->isEmpty()) disabled @endif>
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span wire:loading.remove wire:target="collect">
                                Encaisser {{ (int)$amount > 0 ? number_format((int)$amount, 0, ',', ' ').' DJF' : '' }}
                            </span>
                            <span wire:loading wire:target="collect">Traitement…</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>