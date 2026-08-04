<?php

use App\Models\PaymentReceipt;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Services\AcademicYearService;
use Livewire\Component;

new class extends Component
{
    public Student $student;
    public ?int $yearId = null;

    public function mount(Student $student): void
    {
        $this->student = $student;
        $this->yearId  = AcademicYearService::current()?->id;
    }

    public function with(): array
    {
        $invoices = StudentInvoice::where('academic_year_id', $this->yearId)
            ->whereHas('studentSchoolYear', fn ($q) => $q->where('student_id', $this->student->id))
            ->where('status', '!=', 'cancelled')
            ->with('feeStructure')
            ->orderBy('due_at')->orderBy('id')
            ->get();

        $receipts = PaymentReceipt::where('student_id', $this->student->id)
            ->where('academic_year_id', $this->yearId)
            ->with('lines.invoice')
            ->orderByDesc('paid_at')
            ->get();

        $due  = (int) $invoices->sum('amount_due');
        $paid = (int) $invoices->sum('amount_paid');
        $left = $due - $paid;
        $rate = $due > 0 ? round($paid / $due * 100, 1) : 0.0;

        // Années précédentes non soldées : la dette qui traîne d'une année sur l'autre
        $oldDebt = (int) StudentInvoice::where('academic_year_id', '!=', $this->yearId)
            ->whereHas('studentSchoolYear', fn ($q) => $q->where('student_id', $this->student->id))
            ->where('status', '!=', 'cancelled')
            ->whereRaw('amount_paid < amount_due')
            ->selectRaw('SUM(amount_due - amount_paid) AS d')->value('d');

        $years = AcademicYearService::current();

        return compact('invoices', 'receipts', 'due', 'paid', 'left', 'rate', 'oldDebt', 'years');
    }
}; ?>

@include('partials.finance-styles')

<div>
    @if ($oldDebt > 0)
        <div class="fin-alert warn">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <div>
                Reliquat des années antérieures :
                <strong>{{ number_format($oldDebt, 0, ',', ' ') }} DJF</strong> non soldés.
            </div>
        </div>
    @endif

    <div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="kpi">
            <div class="lbl">Total dû</div>
            <div class="kpi-val">{{ number_format($due, 0, ',', ' ') }}</div>
        </div>
        <div class="kpi good">
            <div class="lbl">Payé</div>
            <div class="kpi-val">{{ number_format($paid, 0, ',', ' ') }}</div>
        </div>
        <div class="kpi bad">
            <div class="lbl">Reste</div>
            <div class="kpi-val">{{ number_format($left, 0, ',', ' ') }}</div>
        </div>
        <div class="kpi dark">
            <div class="lbl">Avancement</div>
            <div class="kpi-val">{{ number_format($rate, 1, ',', ' ') }}<span class="kpi-unit">%</span></div>
        </div>
    </div>

    <div class="fin-card">
        <div class="fin-card-header">
            <span class="fin-card-title">Échéancier {{ $years?->label }}</span>
            @can('finance.collect')
                @if ($left > 0)
                    <a href="{{ route('finances.collect', ['student' => $student->id]) }}"
                       class="btn btn-green" style="margin-left:auto;" wire:navigate>Encaisser</a>
                @endif
            @endcan
        </div>
        <div class="fin-card-body" style="padding:0;">
            <table class="fin-table">
                <thead>
                    <tr><th>Facture</th><th>Échéance</th><th>État</th><th class="num">Dû</th><th class="num">Payé</th><th class="num">Reste</th></tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $i)
                        <tr>
                            <td>
                                <div style="font-weight:600;">{{ $i->invoice_number }}</div>
                                @if ($i->feeStructure)
                                    <div class="lbl">{{ $i->feeStructure->name }}</div>
                                @endif
                            </td>
                            <td class="mono" style="opacity:.6;">{{ $i->due_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>
                                <span class="st st-{{ $i->status }}">
                                    {{ match($i->status) { 'pending'=>'À payer','partial'=>'Partiel','overdue'=>'En retard','paid'=>'Soldée',default=>$i->status } }}
                                </span>
                            </td>
                            <td class="num mono">{{ number_format($i->amount_due, 0, ',', ' ') }}</td>
                            <td class="num mono" style="color:#166534;">{{ number_format($i->amount_paid, 0, ',', ' ') }}</td>
                            <td class="num mono" style="color:{{ $i->balance() > 0 ? 'var(--accent-red)' : 'var(--ink)' }};">
                                {{ number_format($i->balance(), 0, ',', ' ') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="fin-empty">Aucune facture pour cette année.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="fin-card">
        <div class="fin-card-header"><span class="fin-card-title">Historique des règlements</span></div>
        <div class="fin-card-body" style="padding:0;">
            <table class="fin-table">
                <thead>
                    <tr><th>Reçu</th><th>Date</th><th>Mode</th><th>Affectation</th><th class="num">Montant</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($receipts as $r)
                        <tr class="{{ $r->isVoided() ? 'row-voided' : '' }}">
                            <td>
                                <span class="mono" style="color:var(--sidebar-soft);">{{ $r->receipt_number }}</span>
                                @if ($r->isVoided()) <div><span class="st st-voided">Annulé</span></div> @endif
                            </td>
                            <td class="mono" style="opacity:.6;">{{ $r->paid_at->format('d/m/Y H:i') }}</td>
                            <td style="font-size:.8125rem;">
                                {{ $r->methodLabel() }}
                                @if ($r->reference) <div class="lbl">{{ $r->reference }}</div> @endif
                            </td>
                            <td style="font-size:.75rem;opacity:.6;">
                                @foreach ($r->lines as $l)
                                    <div>{{ $l->invoice?->invoice_number }} · {{ number_format($l->amount, 0, ',', ' ') }}</div>
                                @endforeach
                            </td>
                            <td class="num mono">{{ number_format($r->amount, 0, ',', ' ') }}</td>
                            <td class="num">
                                <a href="{{ route('finances.receipt', $r) }}" target="_blank" class="btn btn-icon" title="Imprimer">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2z"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="fin-empty">Aucun règlement enregistré.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>