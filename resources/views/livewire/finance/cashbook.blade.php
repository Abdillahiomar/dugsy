<?php

use App\Models\CashSession;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Services\Finance\CashSessionService;
use App\Services\Finance\PaymentService;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $from = '';
    public string $to = '';
    public string $filterMethod = '';
    public string $filterUser = '';
    public bool   $showVoided = false;

    // Clôture de caisse
    public bool   $closing = false;
    public string $countedCash = '';
    public string $closingNotes = '';

    // Annulation
    public ?int   $voidingId = null;
    public string $voidReason = '';

    public ?string $error = null;
    public ?string $success = null;

    public function mount(): void
    {
        $this->from = today()->format('Y-m-d');
        $this->to   = today()->format('Y-m-d');
    }

    public function updated($property): void
    {
        if (in_array($property, ['from', 'to', 'filterMethod', 'filterUser', 'showVoided'])) {
            $this->resetPage();
        }
    }

    public function setRange(string $preset): void
    {
        [$this->from, $this->to] = match ($preset) {
            'today'     => [today()->format('Y-m-d'), today()->format('Y-m-d')],
            'yesterday' => [today()->subDay()->format('Y-m-d'), today()->subDay()->format('Y-m-d')],
            'week'      => [today()->startOfWeek()->format('Y-m-d'), today()->format('Y-m-d')],
            'month'     => [today()->startOfMonth()->format('Y-m-d'), today()->format('Y-m-d')],
            default     => [$this->from, $this->to],
        };
        $this->resetPage();
    }

    // ── Clôture ──────────────────────────────────────────────────
    public function openCloseModal(): void
    {
        $this->reset(['countedCash', 'closingNotes', 'error']);
        $this->closing = true;
    }

    public function closeSession(): void
    {
        $this->authorize('finance.close');
        $this->error = null;

        $session = app(CashSessionService::class)
            ->currentFor(auth()->user()->school_id, auth()->id());

        if (! $session) {
            $this->error = 'Aucune caisse ouverte à votre nom.';
            return;
        }

        $counted  = (int) $this->countedCash;
        $variance = $counted - $session->expectedCash();

        // Un écart non expliqué ne doit jamais être enregistré en silence
        if ($variance !== 0 && strlen(trim($this->closingNotes)) < 10) {
            $this->error = "Écart de " . number_format($variance, 0, ',', ' ')
                . " DJF constaté. Une explication d'au moins 10 caractères est obligatoire.";
            return;
        }

        try {
            app(CashSessionService::class)->close($session, $counted, $this->closingNotes ?: null);
            $this->closing = false;
            $this->success = 'Caisse clôturée.'
                . ($variance !== 0 ? ' Écart enregistré : ' . number_format($variance, 0, ',', ' ') . ' DJF.' : '');
            $this->reset(['countedCash', 'closingNotes']);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    // ── Annulation ───────────────────────────────────────────────
    public function askVoid(int $id): void
    {
        $this->authorize('finance.void');
        $this->voidingId = $id;
        $this->reset(['voidReason', 'error']);
    }

    public function confirmVoid(): void
    {
        $this->authorize('finance.void');
        $this->error = null;

        if (strlen(trim($this->voidReason)) < 10) {
            $this->error = "Le motif d'annulation doit faire au moins 10 caractères.";
            return;
        }

        try {
            $receipt = PaymentReceipt::findOrFail($this->voidingId);
            app(PaymentService::class)->void($receipt, trim($this->voidReason), auth()->id());
            $this->success = "Reçu {$receipt->receipt_number} annulé.";
            $this->voidingId = null;
            $this->voidReason = '';
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    // ── Export ───────────────────────────────────────────────────
    public function export()
    {
        $this->authorize('finance.export');

        $rows = $this->baseQuery()->with(['student', 'receivedBy'])->orderBy('paid_at')->get();
        $name = "journal-caisse-{$this->from}_{$this->to}.csv";

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));   // BOM UTF-8 pour Excel
            fputcsv($out, ['Reçu', 'Date', 'Heure', 'Élève', 'Matricule', 'Mode', 'Référence', 'Montant DJF', 'Caissier', 'Statut'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->receipt_number,
                    $r->paid_at->format('d/m/Y'),
                    $r->paid_at->format('H:i'),
                    $r->student?->fullName(),
                    $r->student?->matricule,
                    $r->methodLabel(),
                    $r->reference,
                    $r->amount,
                    $r->receivedBy?->name,
                    $r->isVoided() ? 'ANNULÉ' : 'Valide',
                ], ';');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function baseQuery()
    {
        return PaymentReceipt::withoutGlobalScopes()
            ->where('payment_receipts.school_id', auth()->user()->school_id)
            ->whereBetween('paid_at', [$this->from . ' 00:00:00', $this->to . ' 23:59:59'])
            ->when($this->filterMethod, fn ($q) => $q->where('method', $this->filterMethod))
            ->when($this->filterUser, fn ($q) => $q->where('received_by', $this->filterUser))
            ->when(! $this->showVoided, fn ($q) => $q->whereNull('voided_at'));
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;

        $receipts = $this->baseQuery()
            ->with(['student', 'receivedBy', 'voidedBy', 'lines.invoice'])
            ->orderByDesc('paid_at')->orderByDesc('id')
            ->paginate(30);

        // Totaux par mode — toujours hors reçus annulés
        $byMethod = PaymentReceipt::withoutGlobalScopes()
            ->where('payment_receipts.school_id', $schoolId)
            ->whereBetween('paid_at', [$this->from . ' 00:00:00', $this->to . ' 23:59:59'])
            ->whereNull('voided_at')
            ->when($this->filterUser, fn ($q) => $q->where('received_by', $this->filterUser))
            ->selectRaw('method, COUNT(*) AS n, SUM(amount) AS total')
            ->groupBy('method')->get()->keyBy('method');

        $grandTotal = (int) $byMethod->sum('total');
        $countTotal = (int) $byMethod->sum('n');

        $voidedTotal = (int) PaymentReceipt::withoutGlobalScopes()
            ->where('payment_receipts.school_id', $schoolId)
            ->whereBetween('paid_at', [$this->from . ' 00:00:00', $this->to . ' 23:59:59'])
            ->whereNotNull('voided_at')->sum('amount');

        $session       = app(CashSessionService::class)->currentFor($schoolId, auth()->id());
        $expectedCash  = $session?->expectedCash() ?? 0;
        $liveVariance  = $this->countedCash !== '' ? ((int) $this->countedCash) - $expectedCash : null;

        $recentSessions = CashSession::withoutGlobalScopes()
            ->where('school_id', $schoolId)->where('status', 'closed')
            ->with('user')->latest('closed_at')->limit(5)->get();

        $cashiers = User::where('school_id', $schoolId)
            ->orderBy('name')->get(['id', 'name']);

        $voidingReceipt = $this->voidingId ? PaymentReceipt::find($this->voidingId) : null;

        return compact(
            'receipts', 'byMethod', 'grandTotal', 'countTotal', 'voidedTotal',
            'session', 'expectedCash', 'liveVariance', 'recentSessions', 'cashiers', 'voidingReceipt'
        );
    }
}; ?>

@include('partials.finance-styles')

<style>
    .method-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:.6rem; margin-bottom:1.5rem; }
    @media (max-width:900px) { .method-grid { grid-template-columns:repeat(2,1fr); } }
    .m-box { padding:.8rem 1rem; border-radius:10px; border:1px solid var(--line); background:var(--paper-raised); }
    .m-val { font-family:'JetBrains Mono',monospace; font-size:1rem; font-weight:700; color:var(--ink); margin-top:3px; }
    .m-n { font-size:.7rem; opacity:.45; margin-top:1px; }
    .m-box.total { background:var(--sidebar); border-color:var(--sidebar); }
    .m-box.total .lbl { color:rgba(255,255,255,.55); opacity:1; }
    .m-box.total .m-val, .m-box.total .m-n { color:#FFFFFF; }
    .m-box.total .m-n { opacity:.6; }

    .session-panel { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.9rem 1.25rem; border-radius:11px; background:rgba(30,120,80,.06); border:1px solid rgba(30,120,80,.18); margin-bottom:1.25rem; flex-wrap:wrap; }
    .session-panel.none { background:rgba(232,168,56,.08); border-color:rgba(232,168,56,.25); }
    .session-stats { display:flex; gap:1.75rem; flex-wrap:wrap; }
    .preset-btns { display:flex; gap:.35rem; }
    .preset-btn { padding:.35rem .7rem; border-radius:7px; border:1px solid var(--line); background:var(--paper); font-size:.75rem; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; }
    .preset-btn:hover { border-color:var(--sidebar-soft); color:var(--sidebar-soft); }
    .var-line { font-family:'JetBrains Mono',monospace; font-size:1.1rem; font-weight:700; }
</style>

<div>
    <div class="page-head">
        <div>
            <div class="page-title">Journal de caisse</div>
            <div class="page-sub">Encaissements du {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</div>
        </div>
        <div style="display:flex;gap:.6rem;">
            @can('finance.export')
                <button wire:click="export" class="btn">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export CSV
                </button>
            @endcan
            @can('finance.collect')
                <a href="{{ route('finances.collect') }}" class="btn btn-green" wire:navigate>Encaisser</a>
            @endcan
        </div>
    </div>

    @if ($success)
        <div class="fin-alert ok">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ $success }}
        </div>
    @endif
    @if ($error && ! $closing && ! $voidingId)
        <div class="fin-alert err">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            {{ $error }}
        </div>
    @endif

    {{-- ══ Ma caisse ══ --}}
    @if ($session)
        <div class="session-panel">
            <div class="session-stats">
                <div>
                    <div class="lbl">Caisse ouverte</div>
                    <div style="font-size:.875rem;font-weight:600;margin-top:2px;">{{ $session->opened_at->format('d/m/Y à H:i') }}</div>
                </div>
                <div>
                    <div class="lbl">Fond de caisse</div>
                    <div class="mono" style="margin-top:2px;">{{ number_format($session->opening_float, 0, ',', ' ') }} DJF</div>
                </div>
                <div>
                    <div class="lbl">Espèces théoriques</div>
                    <div class="mono" style="margin-top:2px;color:#166534;">{{ number_format($expectedCash, 0, ',', ' ') }} DJF</div>
                </div>
            </div>
            @can('finance.close')
                <button wire:click="openCloseModal" class="btn btn-primary">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Clôturer la caisse
                </button>
            @endcan
        </div>
    @else
        <div class="session-panel none">
            <div style="font-size:.875rem;">Vous n'avez pas de caisse ouverte.</div>
            @can('finance.collect')
                <a href="{{ route('finances.collect') }}" class="btn" wire:navigate>Ouvrir depuis le guichet</a>
            @endcan
        </div>
    @endif

    {{-- ══ Totaux par mode ══ --}}
    <div class="method-grid">
        @foreach (\App\Models\PaymentReceipt::METHODS as $key => $label)
            @php $row = $byMethod[$key] ?? null; @endphp
            <div class="m-box">
                <div class="lbl">{{ $label }}</div>
                <div class="m-val">{{ number_format((int)($row->total ?? 0), 0, ',', ' ') }}</div>
                <div class="m-n">{{ (int)($row->n ?? 0) }} reçu(s)</div>
            </div>
        @endforeach
        <div class="m-box total">
            <div class="lbl">Total période</div>
            <div class="m-val">{{ number_format($grandTotal, 0, ',', ' ') }}</div>
            <div class="m-n">
                {{ $countTotal }} reçu(s)
                @if ($voidedTotal > 0) · {{ number_format($voidedTotal, 0, ',', ' ') }} annulés @endif
            </div>
        </div>
    </div>

    {{-- ══ Filtres ══ --}}
    <div class="filters">
        <div class="preset-btns">
            <button wire:click="setRange('today')" class="preset-btn">Aujourd'hui</button>
            <button wire:click="setRange('yesterday')" class="preset-btn">Hier</button>
            <button wire:click="setRange('week')" class="preset-btn">Cette semaine</button>
            <button wire:click="setRange('month')" class="preset-btn">Ce mois</button>
        </div>
        <div class="filter-field">
            <label class="lbl">Du</label>
            <input wire:model.live="from" type="date" class="fin-input">
        </div>
        <div class="filter-field">
            <label class="lbl">Au</label>
            <input wire:model.live="to" type="date" class="fin-input">
        </div>
        <div class="filter-field">
            <label class="lbl">Mode</label>
            <select wire:model.live="filterMethod" class="fin-select">
                <option value="">Tous</option>
                @foreach (\App\Models\PaymentReceipt::METHODS as $k => $l)
                    <option value="{{ $k }}">{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field">
            <label class="lbl">Caissier</label>
            <select wire:model.live="filterUser" class="fin-select">
                <option value="">Tous</option>
                @foreach ($cashiers as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <label style="display:flex;align-items:center;gap:.4rem;font-size:.8125rem;cursor:pointer;padding-bottom:.45rem;">
            <input type="checkbox" wire:model.live="showVoided">
            Afficher les annulés
        </label>
    </div>

    {{-- ══ Table des reçus ══ --}}
    <div class="fin-card">
        <div class="fin-card-body" style="padding:0;">
            <table class="fin-table">
                <thead>
                    <tr>
                        <th>Reçu</th><th>Heure</th><th>Élève</th><th>Affectation</th>
                        <th>Mode</th><th class="num">Montant</th><th>Caissier</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($receipts as $r)
                        <tr class="{{ $r->isVoided() ? 'row-voided' : '' }}">
                            <td>
                                <span class="mono" style="color:var(--sidebar-soft);">{{ $r->receipt_number }}</span>
                                @if ($r->isVoided())
                                    <div><span class="st st-voided">Annulé</span></div>
                                @endif
                            </td>
                            <td class="mono" style="opacity:.6;">{{ $r->paid_at->format('H:i') }}</td>
                            <td>
                                <div style="font-weight:600;">{{ $r->student?->fullName() }}</div>
                                <div class="lbl">{{ $r->student?->matricule }}</div>
                            </td>
                            <td style="font-size:.75rem;opacity:.6;">
                                @foreach ($r->lines->take(2) as $l)
                                    <div>{{ $l->invoice?->invoice_number }} · {{ number_format($l->amount, 0, ',', ' ') }}</div>
                                @endforeach
                                @if ($r->lines->count() > 2)
                                    <div>+ {{ $r->lines->count() - 2 }} autre(s)</div>
                                @endif
                            </td>
                            <td>
                                <span style="font-size:.8125rem;">{{ $r->methodLabel() }}</span>
                                @if ($r->reference)
                                    <div class="lbl">{{ $r->reference }}</div>
                                @endif
                            </td>
                            <td class="num mono">{{ number_format($r->amount, 0, ',', ' ') }}</td>
                            <td style="font-size:.8125rem;opacity:.65;">{{ $r->receivedBy?->name }}</td>
                            <td class="num" style="white-space:nowrap;">
                                <a href="{{ route('finances.receipt', $r) }}" target="_blank" class="btn btn-icon" title="Imprimer">
                                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                </a>
                                @can('finance.void')
                                    @if (! $r->isVoided())
                                        <button wire:click="askVoid({{ $r->id }})" class="btn btn-icon btn-danger" title="Annuler">
                                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                        @if ($r->isVoided() && $r->void_reason)
                            <tr>
                                <td colspan="8" style="padding:.35rem .85rem .7rem;border-bottom:1px solid var(--line);">
                                    <span class="lbl">Motif</span>
                                    <span style="font-size:.75rem;opacity:.6;margin-left:.4rem;">
                                        {{ $r->void_reason }} — {{ $r->voidedBy?->name }}, {{ $r->voided_at->format('d/m/Y H:i') }}
                                    </span>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="8" class="fin-empty">Aucun encaissement sur cette période.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($receipts->hasPages())
        <div style="margin-top:1rem;">{{ $receipts->links() }}</div>
    @endif

    {{-- ══ Dernières clôtures ══ --}}
    @if ($recentSessions->isNotEmpty())
        <div class="fin-card" style="margin-top:1.25rem;">
            <div class="fin-card-header"><span class="fin-card-title">Dernières clôtures</span></div>
            <div class="fin-card-body" style="padding:0;">
                <table class="fin-table">
                    <thead>
                        <tr><th>Caissier</th><th>Période</th><th class="num">Théorique</th><th class="num">Compté</th><th class="num">Écart</th><th>Note</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($recentSessions as $s)
                            <tr>
                                <td style="font-weight:600;">{{ $s->user?->name }}</td>
                                <td style="font-size:.8125rem;opacity:.6;">
                                    {{ $s->opened_at->format('d/m H:i') }} → {{ $s->closed_at?->format('d/m H:i') }}
                                </td>
                                <td class="num mono">{{ number_format((int)$s->expected_cash, 0, ',', ' ') }}</td>
                                <td class="num mono">{{ number_format((int)$s->counted_cash, 0, ',', ' ') }}</td>
                                <td class="num mono" style="color:{{ $s->variance == 0 ? '#166534' : ($s->variance < 0 ? 'var(--accent-red)' : '#8A6010') }};">
                                    {{ $s->variance > 0 ? '+' : '' }}{{ number_format((int)$s->variance, 0, ',', ' ') }}
                                </td>
                                <td style="font-size:.75rem;opacity:.55;">{{ $s->notes }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ══ Modal clôture ══ --}}
    @if ($closing && $session)
        <div class="modal-back" wire:click.self="$set('closing', false)">
            <div class="modal">
                <div class="modal-head">Clôture de caisse</div>
                <div class="modal-body">
                    <div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:.875rem;">
                        <span style="opacity:.6;">Fond de caisse</span>
                        <span class="mono">{{ number_format($session->opening_float, 0, ',', ' ') }} DJF</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:.4rem 0;font-size:.875rem;border-bottom:1px solid var(--line);margin-bottom:.85rem;">
                        <span style="opacity:.6;">Espèces encaissées</span>
                        <span class="mono">{{ number_format($expectedCash - $session->opening_float, 0, ',', ' ') }} DJF</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:.4rem 0 1rem;font-size:.9rem;font-weight:600;">
                        <span>Théorique en caisse</span>
                        <span class="mono" style="font-size:1rem;">{{ number_format($expectedCash, 0, ',', ' ') }} DJF</span>
                    </div>

                    <div class="filter-field" style="margin-bottom:1rem;">
                        <label class="lbl">Espèces comptées physiquement</label>
                        <input wire:model.live.debounce.400ms="countedCash" type="number" min="0"
                               class="fin-input" style="font-family:'JetBrains Mono',monospace;font-size:1.05rem;font-weight:700;"
                               placeholder="0" autofocus>
                    </div>

                    @if ($liveVariance !== null)
                        <div class="fin-alert {{ $liveVariance === 0 ? 'ok' : ($liveVariance < 0 ? 'err' : 'warn') }}" style="margin-bottom:1rem;">
                            <div>
                                <div class="lbl">Écart</div>
                                <div class="var-line">
                                    {{ $liveVariance > 0 ? '+' : '' }}{{ number_format($liveVariance, 0, ',', ' ') }} DJF
                                </div>
                                <div style="font-size:.75rem;margin-top:2px;">
                                    {{ $liveVariance === 0 ? 'Caisse juste.' : ($liveVariance < 0 ? 'Manquant en caisse.' : 'Excédent en caisse.') }}
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="filter-field">
                        <label class="lbl">
                            Observation {{ ($liveVariance ?? 0) !== 0 ? '(obligatoire)' : '(optionnel)' }}
                        </label>
                        <textarea wire:model="closingNotes" rows="2" class="fin-input"
                                  placeholder="Explication de l'écart, incident…"></textarea>
                    </div>

                    @if ($error)
                        <div class="fin-alert err" style="margin-top:1rem;margin-bottom:0;">{{ $error }}</div>
                    @endif
                </div>
                <div class="modal-foot">
                    <button wire:click="$set('closing', false)" class="btn">Annuler</button>
                    <button wire:click="closeSession" class="btn btn-primary"
                            @if ($countedCash === '') disabled @endif>Confirmer la clôture</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ══ Modal annulation ══ --}}
    @if ($voidingId && $voidingReceipt)
        <div class="modal-back" wire:click.self="$set('voidingId', null)">
            <div class="modal">
                <div class="modal-head" style="color:var(--accent-red);">Annuler le reçu {{ $voidingReceipt->receipt_number }}</div>
                <div class="modal-body">
                    <div class="fin-alert warn">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        <div>
                            {{ number_format($voidingReceipt->amount, 0, ',', ' ') }} DJF seront retirés du solde de
                            {{ $voidingReceipt->student?->fullName() }}. Le reçu reste conservé et visible, marqué comme annulé.
                        </div>
                    </div>
                    <div class="filter-field">
                        <label class="lbl">Motif de l'annulation (10 caractères minimum)</label>
                        <textarea wire:model.live="voidReason" rows="3" class="fin-input"
                                  placeholder="Erreur de saisie du montant, chèque sans provision…" autofocus></textarea>
                    </div>
                    @if ($error)
                        <div class="fin-alert err" style="margin-top:1rem;margin-bottom:0;">{{ $error }}</div>
                    @endif
                </div>
                <div class="modal-foot">
                    <button wire:click="$set('voidingId', null)" class="btn">Renoncer</button>
                    <button wire:click="confirmVoid" class="btn btn-danger"
                            style="background:var(--accent-red);color:#FFF;border-color:var(--accent-red);"
                            @if (strlen(trim($voidReason)) < 10) disabled @endif>
                        Confirmer l'annulation
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>