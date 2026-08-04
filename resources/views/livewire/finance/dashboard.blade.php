<?php

use App\Models\CashSession;
use App\Models\PaymentReceipt;
use App\Models\StudentInvoice;
use App\Services\AcademicYearService;
use Illuminate\Support\Carbon;
use Livewire\Component;

new class extends Component
{
    public string $scope = 'year';   // year | month | quarter

    /**
     * Toutes les requêtes jointes utilisent withoutGlobalScopes() + un where
     * qualifié explicite : sur des agrégats financiers, l'isolation par école
     * doit être lisible dans la requête, pas cachée dans un trait.
     */
    private function invoiceBase(int $schoolId, int $yearId)
    {
        return StudentInvoice::withoutGlobalScopes()
            ->where('student_invoices.school_id', $schoolId)
            ->where('student_invoices.academic_year_id', $yearId)
            ->where('student_invoices.status', '!=', 'cancelled');
    }

    private function receiptBase(int $schoolId, int $yearId)
    {
        return PaymentReceipt::withoutGlobalScopes()
            ->where('payment_receipts.school_id', $schoolId)
            ->where('payment_receipts.academic_year_id', $yearId)
            ->whereNull('payment_receipts.voided_at');
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;
        $year     = AcademicYearService::current();

        if (! $year) {
            return ['year' => null, 'ready' => false];
        }

        // ── KPI globaux ──────────────────────────────────────────
        $agg = $this->invoiceBase($schoolId, $year->id)
            ->selectRaw('SUM(amount_due) AS due, SUM(amount_paid) AS paid')
            ->first();

        $due  = (int) ($agg->due ?? 0);
        $paid = (int) ($agg->paid ?? 0);
        $left = $due - $paid;
        $rate = $due > 0 ? round($paid / $due * 100, 1) : 0.0;

        // ── Encaissé ce mois vs mois précédent ───────────────────
        $thisMonth = (int) $this->receiptBase($schoolId, $year->id)
            ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount');

        $lastMonth = (int) $this->receiptBase($schoolId, $year->id)
            ->whereBetween('paid_at', [
                now()->subMonthNoOverflow()->startOfMonth(),
                now()->subMonthNoOverflow()->endOfMonth(),
            ])->sum('amount');

        $delta = $lastMonth > 0 ? round(($thisMonth - $lastMonth) / $lastMonth * 100, 1) : null;

        // ── Balance âgée : où se concentre le retard ─────────────
        $aging = $this->invoiceBase($schoolId, $year->id)
            ->whereRaw('amount_paid < amount_due')
            ->selectRaw("
                SUM(CASE WHEN due_at IS NULL OR due_at >= CURDATE()
                    THEN amount_due - amount_paid ELSE 0 END) AS non_echu,
                SUM(CASE WHEN due_at < CURDATE() AND due_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    THEN amount_due - amount_paid ELSE 0 END) AS b30,
                SUM(CASE WHEN due_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND due_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                    THEN amount_due - amount_paid ELSE 0 END) AS b60,
                SUM(CASE WHEN due_at < DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                    THEN amount_due - amount_paid ELSE 0 END) AS b90
            ")->first();

        // ── Recouvrement par classe ──────────────────────────────
        $byClass = $this->invoiceBase($schoolId, $year->id)
            ->join('student_school_years AS ssy', 'ssy.id', '=', 'student_invoices.student_school_year_id')
            ->join('school_classes AS sc', 'sc.id', '=', 'ssy.school_class_id')
            ->groupBy('sc.id', 'sc.name')
            ->selectRaw('sc.id, sc.name, SUM(amount_due) AS due, SUM(amount_paid) AS paid, COUNT(DISTINCT ssy.student_id) AS nb')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'nb'   => (int) $r->nb,
                'due'  => (int) $r->due,
                'paid' => (int) $r->paid,
                'left' => (int) $r->due - (int) $r->paid,
                'rate' => $r->due > 0 ? round($r->paid / $r->due * 100, 1) : 0.0,
            ])
            ->sortBy('rate')          // classes en difficulté en haut
            ->values();

        // ── Courbe mensuelle ─────────────────────────────────────
        $raw = $this->receiptBase($schoolId, $year->id)
            ->selectRaw("DATE_FORMAT(paid_at, '%Y-%m') AS m, SUM(amount) AS total")
            ->groupBy('m')->orderBy('m')->pluck('total', 'm');

        // Série continue sur les 12 derniers mois, trous inclus
        $monthly = collect(range(11, 0))->map(function ($i) use ($raw) {
            $d = now()->subMonthsNoOverflow($i);
            return [
                'key'   => $d->format('Y-m'),
                'label' => $d->locale('fr')->isoFormat('MMM'),
                'total' => (int) ($raw[$d->format('Y-m')] ?? 0),
            ];
        });
        $monthlyMax = max(1, $monthly->max('total'));

        // ── Répartition par type de frais ────────────────────────
        // ⚠ ADAPTER 'fs.name' au nom réel de la colonne libellé de fee_structures
        
        // ── Top débiteurs ────────────────────────────────────────
        $debtors = $this->invoiceBase($schoolId, $year->id)
            ->join('student_school_years AS ssy', 'ssy.id', '=', 'student_invoices.student_school_year_id')
            ->join('students AS s', 's.id', '=', 'ssy.student_id')
            ->leftJoin('school_classes AS sc', 'sc.id', '=', 'ssy.school_class_id')
            ->groupBy('s.id', 's.first_name', 's.last_name', 's.matricule', 'sc.name')
            ->selectRaw('s.id, s.first_name, s.last_name, s.matricule, sc.name AS class_name,
                         SUM(amount_due - amount_paid) AS reste,
                         SUM(CASE WHEN status = "overdue" THEN 1 ELSE 0 END) AS nb_retard')
            ->havingRaw('reste > 0')
            ->orderByDesc('reste')
            ->limit(10)->get();

        // ── Caisses ouvertes en ce moment ────────────────────────
        $openSessions = CashSession::withoutGlobalScopes()
            ->where('school_id', $schoolId)->where('status', 'open')
            ->with('user')->get();

        // ── Écarts de caisse du mois ─────────────────────────────
        $variance = (int) CashSession::withoutGlobalScopes()
            ->where('school_id', $schoolId)->where('status', 'closed')
            ->whereBetween('closed_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('variance');

        $overdueCount = $this->invoiceBase($schoolId, $year->id)
            ->where('status', 'overdue')->count();

        return compact(
            'year', 'due', 'paid', 'left', 'rate', 'thisMonth', 'lastMonth', 'delta',
            'aging', 'byClass', 'monthly', 'monthlyMax', 'byFee', 'debtors',
            'openSessions', 'variance', 'overdueCount'
        ) + ['ready' => true];
    }
}; ?>

@include('partials.finance-styles')

<style>
    .split-2 { display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; align-items:start; }
    @media (max-width:960px) { .split-2 { grid-template-columns:1fr; } }
    .aging-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:.6rem; }
    @media (max-width:700px) { .aging-grid { grid-template-columns:repeat(2,1fr); } }
    .aging-cell { padding:.75rem .9rem; border-radius:9px; border:1px solid var(--line); background:var(--paper); }
    .aging-val { font-family:'JetBrains Mono',monospace; font-size:.95rem; font-weight:700; margin-top:3px; }
    .a0 .aging-val { color:var(--sidebar-soft); }
    .a1 .aging-val { color:#8A6010; }
    .a2 .aging-val { color:#C04020; }
    .a3 .aging-val { color:var(--accent-red); }
    .a3 { border-color:rgba(224,92,58,.3); background:rgba(224,92,58,.04); }
</style>

<div>
    @if (! ($ready ?? false))
        <div class="fin-empty">Aucune année académique active. Activez-en une pour consulter les finances.</div>
    @else

    <div class="page-head">
        <div>
            <div class="page-title">Finances</div>
            <div class="page-sub">Année {{ $year->label }} · vue de pilotage</div>
        </div>
        <div style="display:flex;gap:.6rem;">
            <a href="{{ route('finances.receivables') }}" class="btn" wire:navigate>Impayés</a>
            <a href="{{ route('finances.cashbook') }}" class="btn" wire:navigate>Journal de caisse</a>
            @can('finance.collect')
                <a href="{{ route('finances.collect') }}" class="btn btn-green" wire:navigate>
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Encaisser
                </a>
            @endcan
        </div>
    </div>

    {{-- ══ KPI ══ --}}
    <div class="kpi-grid">
        <div class="kpi">
            <div class="lbl">Attendu sur l'année</div>
            <div class="kpi-val">{{ number_format($due, 0, ',', ' ') }}<span class="kpi-unit">DJF</span></div>
        </div>
        <div class="kpi good">
            <div class="lbl">Encaissé</div>
            <div class="kpi-val">{{ number_format($paid, 0, ',', ' ') }}<span class="kpi-unit">DJF</span></div>
            <div class="kpi-foot">
                {{ number_format($thisMonth, 0, ',', ' ') }} DJF ce mois
                @if ($delta !== null)
                    · <span style="color:{{ $delta >= 0 ? '#166534' : 'var(--accent-red)' }};font-weight:600;">
                        {{ $delta >= 0 ? '+' : '' }}{{ $delta }}%
                    </span>
                @endif
            </div>
        </div>
        <div class="kpi bad">
            <div class="lbl">Reste à recouvrer</div>
            <div class="kpi-val">{{ number_format($left, 0, ',', ' ') }}<span class="kpi-unit">DJF</span></div>
            <div class="kpi-foot">{{ $overdueCount }} échéance(s) en retard</div>
        </div>
        <div class="kpi dark">
            <div class="lbl">Taux de recouvrement</div>
            <div class="kpi-val">{{ number_format($rate, 1, ',', ' ') }}<span class="kpi-unit">%</span></div>
            <div class="kpi-foot">de l'attendu {{ $year->label }}</div>
        </div>
    </div>

    {{-- ══ Alertes ══ --}}
    @if ($openSessions->isNotEmpty())
        <div class="fin-alert warn">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                {{ $openSessions->count() }} caisse(s) encore ouverte(s) :
                {{ $openSessions->map(fn($s) => $s->user->name.' (depuis le '.$s->opened_at->format('d/m à H:i').')')->join(', ') }}
            </div>
        </div>
    @endif

    @if ($variance !== 0)
        <div class="fin-alert {{ $variance < 0 ? 'err' : 'warn' }}">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <div>
                Écart de caisse cumulé ce mois :
                <strong>{{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 0, ',', ' ') }} DJF</strong>
                {{ $variance < 0 ? '(manquant)' : '(excédent)' }}
            </div>
        </div>
    @endif

    {{-- ══ Balance âgée ══ --}}
    <div class="fin-card">
        <div class="fin-card-header">
            <div class="fin-icon" style="background:rgba(224,92,58,.1);color:var(--accent-red);">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <span class="fin-card-title">Ancienneté des impayés</span>
            <span class="fin-card-sub">Plus la créance vieillit, moins elle se recouvre</span>
        </div>
        <div class="fin-card-body">
            <div class="aging-grid">
                <div class="aging-cell a0">
                    <div class="lbl">Non échu</div>
                    <div class="aging-val">{{ number_format((int)$aging->non_echu, 0, ',', ' ') }}</div>
                </div>
                <div class="aging-cell a1">
                    <div class="lbl">1 – 30 jours</div>
                    <div class="aging-val">{{ number_format((int)$aging->b30, 0, ',', ' ') }}</div>
                </div>
                <div class="aging-cell a2">
                    <div class="lbl">31 – 60 jours</div>
                    <div class="aging-val">{{ number_format((int)$aging->b60, 0, ',', ' ') }}</div>
                </div>
                <div class="aging-cell a3">
                    <div class="lbl">Plus de 60 jours</div>
                    <div class="aging-val">{{ number_format((int)$aging->b90, 0, ',', ' ') }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Courbe mensuelle ══ --}}
    <div class="fin-card">
        <div class="fin-card-header">
            <div class="fin-icon" style="background:rgba(42,63,126,.08);color:var(--sidebar-soft);">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
            </div>
            <span class="fin-card-title">Encaissements mensuels</span>
            <span class="fin-card-sub">12 derniers mois · DJF</span>
        </div>
        <div class="fin-card-body">
            <div class="chart">
                @foreach ($monthly as $m)
                    <div class="chart-col" title="{{ number_format($m['total'], 0, ',', ' ') }} DJF">
                        <div class="chart-bar" style="height:{{ max(2, round($m['total'] / $monthlyMax * 100)) }}%;">
                            @if ($m['total'] > 0)
                                <span class="chart-bar-val">{{ number_format($m['total'] / 1000, 0, ',', ' ') }}k</span>
                            @endif
                        </div>
                        <div class="chart-lbl">{{ $m['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="split-2">
        {{-- ══ Par classe ══ --}}
        <div class="fin-card">
            <div class="fin-card-header">
                <div class="fin-icon" style="background:rgba(232,168,56,.12);color:#8A6010;">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/></svg>
                </div>
                <span class="fin-card-title">Recouvrement par classe</span>
                <span class="fin-card-sub">Plus faible en premier</span>
            </div>
            <div class="fin-card-body">
                @forelse ($byClass as $c)
                    @php
                        $color = $c['rate'] >= 80 ? '#166534' : ($c['rate'] >= 50 ? '#E8A838' : '#E05C3A');
                    @endphp
                    <div class="bar-row">
                        <div>
                            <div class="bar-name">{{ $c['name'] }}</div>
                            <div class="lbl">{{ $c['nb'] }} élèves</div>
                        </div>
                        <div class="bar-track">
                            <div class="bar-fill" style="width:{{ min(100, $c['rate']) }}%;background:{{ $color }};"></div>
                            <span class="bar-pct">{{ number_format($c['rate'], 1, ',', ' ') }}%</span>
                        </div>
                        <div class="bar-amounts">
                            <div style="color:#166534;">{{ number_format($c['paid'], 0, ',', ' ') }}</div>
                            <div style="opacity:.45;">/ {{ number_format($c['due'], 0, ',', ' ') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="fin-empty">Aucune donnée pour cette année.</div>
                @endforelse
            </div>
        </div>

        {{-- ══ Par type de frais ══ --}}
        <div class="fin-card">
            <div class="fin-card-header">
                <div class="fin-icon" style="background:rgba(99,102,241,.1);color:#3730A3;">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9A9.004 9.004 0 0015 3.512V9h5.488z"/></svg>
                </div>
                <span class="fin-card-title">Par type de frais</span>
            </div>
            <div class="fin-card-body" style="padding-top:.5rem;padding-bottom:.5rem;">
                <table class="fin-table">
                    <thead>
                        <tr><th>Type</th><th class="num">Attendu</th><th class="num">Encaissé</th><th class="num">Taux</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($byFee as $f)
                            <tr>
                                <td style="font-weight:600;">{{ $f['name'] }}</td>
                                <td class="num mono">{{ number_format($f['due'], 0, ',', ' ') }}</td>
                                <td class="num mono" style="color:#166534;">{{ number_format($f['paid'], 0, ',', ' ') }}</td>
                                <td class="num mono" style="color:{{ $f['rate'] >= 80 ? '#166534' : ($f['rate'] >= 50 ? '#8A6010' : 'var(--accent-red)') }};">
                                    {{ number_format($f['rate'], 1, ',', ' ') }}%
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="fin-empty">Aucune donnée.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ══ Top débiteurs ══ --}}
    <div class="fin-card">
        <div class="fin-card-header">
            <div class="fin-icon" style="background:rgba(224,92,58,.1);color:var(--accent-red);">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <span class="fin-card-title">Principaux débiteurs</span>
            <a href="{{ route('finances.receivables') }}" class="fin-card-sub" style="text-decoration:underline;" wire:navigate>Voir tous les impayés →</a>
        </div>
        <div class="fin-card-body" style="padding-top:.5rem;padding-bottom:.5rem;">
            <table class="fin-table">
                <thead>
                    <tr><th>Élève</th><th>Classe</th><th class="num">Échéances en retard</th><th class="num">Reste dû</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($debtors as $d)
                        <tr>
                            <td>
                                <div style="font-weight:600;">{{ $d->first_name }} {{ $d->last_name }}</div>
                                <div class="lbl">{{ $d->matricule }}</div>
                            </td>
                            <td style="opacity:.65;">{{ $d->class_name ?? '—' }}</td>
                            <td class="num">
                                @if ($d->nb_retard > 0)
                                    <span class="st st-overdue">{{ $d->nb_retard }}</span>
                                @else
                                    <span style="opacity:.35;">—</span>
                                @endif
                            </td>
                            <td class="num mono" style="color:var(--accent-red);">{{ number_format((int)$d->reste, 0, ',', ' ') }} DJF</td>
                            <td class="num">
                                @can('finance.collect')
                                    <a href="{{ route('finances.collect', ['student' => $d->id]) }}" class="btn btn-icon" wire:navigate title="Encaisser">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="fin-empty">Aucun impayé. Recouvrement complet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @endif
</div>