{{-- resources/views/livewire/dashboard/dashboard-page.blade.php --}}

<style>
:root {
    --dsh-blue  : #1E2D5A;
    --dsh-blue2 : #2A3F7E;
    --dsh-gold  : #E8A838;
    --dsh-green : #166534;
    --dsh-red   : #E05C3A;
    --dsh-ink   : #1A1E35;
    --dsh-muted : #6B7090;
    --dsh-card  : #FFFFFF;
    --dsh-line  : #E8EAF0;
    --dsh-bg    : #F8F9FB;
    --dsh-r     : 12px;
    --dsh-shadow: 0 1px 3px rgba(30,45,90,.06),0 1px 2px rgba(30,45,90,.04);
}

/* Page */
.dsh { display:flex; flex-direction:column; gap:1.25rem; }

/* KPI grid */
.kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; }
@media(max-width:1100px){ .kpi-grid{ grid-template-columns:1fr 1fr; } }
@media(max-width:580px) { .kpi-grid{ grid-template-columns:1fr; } }

/* KPI card */
.kpi-card {
    background:var(--dsh-card); border-radius:var(--dsh-r);
    border:1px solid var(--dsh-line); box-shadow:var(--dsh-shadow);
    padding:1.25rem 1.25rem 1rem;
    position:relative; overflow:hidden; transition:box-shadow .15s;
}
.kpi-card:hover { box-shadow:0 4px 16px rgba(30,45,90,.1); }
.kpi-card::before {
    content:''; position:absolute; left:0; top:12%; bottom:12%;
    width:3px; border-radius:0 2px 2px 0;
}
.kpi-blue::before  { background:var(--dsh-blue); }
.kpi-green::before { background:#22c55e; }
.kpi-red::before   { background:var(--dsh-red); }
.kpi-amber::before { background:var(--dsh-gold); }

.kpi-icon {
    position:absolute; top:1rem; right:1rem;
    width:34px; height:34px; border-radius:9px;
    display:flex; align-items:center; justify-content:center;
}
.kpi-icon svg { width:16px; height:16px; }
.icon-blue   { background:rgba(30,45,90,.08);  color:var(--dsh-blue); }
.icon-green  { background:rgba(34,197,94,.1);  color:#166534; }
.icon-red    { background:rgba(224,92,58,.1);  color:var(--dsh-red); }
.icon-amber  { background:rgba(232,168,56,.15);color:#92400E; }

.kpi-label {
    font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:600;
    text-transform:uppercase; letter-spacing:.1em;
    color:var(--dsh-muted); margin-bottom:.5rem;
}
.kpi-value {
    font-family:'Fraunces',serif; font-size:1.875rem; font-weight:700;
    color:var(--dsh-ink); line-height:1; margin-bottom:.45rem;
    letter-spacing:-.02em;
}
.kpi-footer { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
.kpi-delta  {
    font-family:'JetBrains Mono',monospace; font-size:10.5px; font-weight:700;
    padding:1.5px 6px; border-radius:5px;
}
.kpi-delta.up   { background:rgba(22,101,52,.1);  color:#166534; }
.kpi-delta.down { background:rgba(224,92,58,.1);  color:var(--dsh-red); }
.kpi-sub { font-size:.8rem; color:var(--dsh-muted); }

/* Widget card */
.w-card {
    background:var(--dsh-card); border-radius:var(--dsh-r);
    border:1px solid var(--dsh-line); box-shadow:var(--dsh-shadow);
    overflow:hidden;
}
.w-header {
    padding:.875rem 1.25rem; border-bottom:1px solid var(--dsh-line);
    display:flex; align-items:center; justify-content:space-between;
}
.w-title { font-family:'Fraunces',serif; font-size:.9375rem; font-weight:600; color:var(--dsh-ink); }
.w-meta  { font-family:'JetBrains Mono',monospace; font-size:10px; color:var(--dsh-muted); }
.chart-box { padding:.5rem 1rem 1rem; }

/* Grilles */
.g2   { display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; }
.g3-2 { display:grid; grid-template-columns:3fr 2fr; gap:1.25rem; }
@media(max-width:900px) { .g2,.g3-2 { grid-template-columns:1fr; } }

/* Paiements */
.pay-row {
    display:flex; align-items:center; gap:.75rem;
    padding:.625rem 1.25rem; border-bottom:1px solid var(--dsh-line);
}
.pay-row:last-child { border-bottom:none; }
.pay-av {
    width:32px; height:32px; border-radius:50%;
    background:rgba(30,45,90,.08); color:var(--dsh-blue2);
    font-family:'JetBrains Mono',monospace; font-size:11px; font-weight:700;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
}
.pay-name  { font-size:.875rem; font-weight:600; color:var(--dsh-ink); line-height:1.2; }
.pay-class { font-size:.75rem; color:var(--dsh-muted); }
.pay-amt   { font-family:'JetBrains Mono',monospace; font-size:.875rem; font-weight:700; color:#166534; margin-left:auto; flex-shrink:0; }
.pay-date  { font-family:'JetBrains Mono',monospace; font-size:.7rem; color:var(--dsh-muted); flex-shrink:0; }

.m-badge {
    display:inline-flex; align-items:center;
    font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:700;
    padding:2px 7px; border-radius:4px; flex-shrink:0;
}
.m-cash     { background:rgba(30,45,90,.08);   color:var(--dsh-blue); }
.m-d_money  { background:rgba(232,168,56,.15); color:#92400E; }
.m-cac_pay  { background:rgba(34,197,94,.1);   color:#166534; }
.m-cheque   { background:rgba(139,92,246,.1);  color:#6D28D9; }
.m-virement { background:rgba(6,182,212,.1);   color:#0E7490; }

/* Débiteurs */
.dbt-row {
    display:flex; align-items:center; gap:.75rem;
    padding:.5rem 1.25rem; border-bottom:1px solid var(--dsh-line);
}
.dbt-row:last-child { border-bottom:none; }
.dbt-rank  { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:700; color:var(--dsh-muted); width:18px; flex-shrink:0; }
.dbt-name  { font-size:.875rem; font-weight:500; color:var(--dsh-ink); flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.dbt-class { font-size:.75rem; color:var(--dsh-muted); }
.dbt-amt   { font-family:'JetBrains Mono',monospace; font-size:.875rem; font-weight:700; color:var(--dsh-red); flex-shrink:0; }

/* Empty */
.dsh-empty { padding:2.5rem; text-align:center; }
.dsh-empty svg { width:36px; height:36px; margin:0 auto .75rem; opacity:.2; }
.dsh-empty-msg { font-size:.875rem; color:var(--dsh-muted); }
</style>

<div class="dsh">

    {{-- En-tête page --}}
    <div>
        <div style="font-family:'Fraunces',serif;font-size:1.5rem;font-weight:700;color:var(--dsh-ink);letter-spacing:-.02em;">
            @switch($role)
                @case('admin')      Tableau de bord     @break
                @case('comptable')  Finances            @break
                @case('enseignant') Mes classes         @break
                @case('surveillant') Présences          @break
                @case('parent')     Suivi scolaire      @break
                @default            Tableau de bord
            @endswitch
        </div>
        <div style="font-size:.875rem;color:var(--dsh-muted);margin-top:.15rem;">
            {{ auth()->user()->school?->name }} · {{ now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
        </div>
    </div>

    {{-- KPI Cards --}}
    @livewire('dashboard.widgets.stats-grid', ['role' => $role])

    {{-- Graphiques principaux --}}
    @if (in_array('revenue_chart', $widgets) && in_array('enrollment_chart', $widgets))
        <div class="g3-2">
            @livewire('dashboard.widgets.revenue-chart')
            @livewire('dashboard.widgets.enrollment-chart')
        </div>
    @elseif (in_array('revenue_chart', $widgets))
        @livewire('dashboard.widgets.revenue-chart')
    @endif

    @if (in_array('attendance_chart', $widgets) || in_array('payment_methods', $widgets))
        <div class="g2">
            @if (in_array('attendance_chart', $widgets))
                @livewire('dashboard.widgets.attendance-chart', ['role' => $role])
            @endif
            @if (in_array('payment_methods', $widgets))
                @livewire('dashboard.widgets.payment-chart')
            @endif
        </div>
    @endif

    {{-- Données tabulaires --}}
    @if (in_array('recent_payments', $widgets) || in_array('top_debtors', $widgets))
        <div class="g2">
            @if (in_array('recent_payments', $widgets))
                @livewire('dashboard.widgets.recent-payments')
            @endif
            @if (in_array('top_debtors', $widgets))
                @livewire('dashboard.widgets.top-debtors')
            @endif
        </div>
    @endif

</div>
