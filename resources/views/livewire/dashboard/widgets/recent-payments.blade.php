<div class="w-card">
    <div class="w-header">
        <span class="w-title">Derniers paiements</span>
        <span class="w-meta">{{ count($payments) }} transactions</span>
    </div>
    @forelse ($payments as $p)
        @php
            $parts    = explode(' ', $p['name'], 2);
            $initials = strtoupper(substr($parts[0],0,1).(isset($parts[1]) ? substr($parts[1],0,1) : ''));
            $method   = $p['method'];
            $labels   = ['cash'=>'Espèces','d-money'=>'D-Money','cac_pay'=>'CAC Pay','cheque'=>'Chèque','virement'=>'Virement'];
        @endphp
        <div class="pay-row">
            <div class="pay-av">{{ $initials }}</div>
            <div style="min-width:0;flex:1;">
                <div class="pay-name">{{ $p['name'] }}</div>
                <div class="pay-class">{{ $p['class'] }}</div>
            </div>
            <span class="m-badge m-{{ $method }}">{{ $labels[$method] ?? ucfirst($method) }}</span>
            <div class="pay-amt">{{ number_format($p['amount'],0,',',' ') }} DJF</div>
            <div class="pay-date">{{ $p['date'] }}</div>
        </div>
    @empty
        <div class="dsh-empty">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <div class="dsh-empty-msg">Aucun paiement récent.</div>
        </div>
    @endforelse
</div>