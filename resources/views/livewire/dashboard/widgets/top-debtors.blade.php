<div class="w-card" wire:ignore.self>
    <div class="w-header">
        <span class="w-title">Top débiteurs</span>
        <span class="w-meta" style="color:var(--dsh-red);">{{ count($debtors) }} élèves</span>
    </div>
    @if (count($debtors) > 0)
        <div class="chart-box" style="padding-bottom:0;">
            <div id="chart-debtors" style="min-height:180px;"></div>
        </div>
        <div>
            @foreach ($debtors as $i => $d)
                <div class="dbt-row">
                    <span class="dbt-rank">{{ $i+1 }}</span>
                    <div style="min-width:0;flex:1;">
                        <div class="dbt-name">{{ $d['name'] }}</div>
                        <div class="dbt-class">{{ $d['class'] }}</div>
                    </div>
                    <span class="dbt-amt">{{ number_format($d['balance'],0,',',' ') }} DJF</span>
                </div>
            @endforeach
        </div>
    @else
        <div class="dsh-empty">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div class="dsh-empty-msg">Aucun impayé. ✓</div>
        </div>
    @endif
</div>

@push('scripts')
<script>
(function(){
    const raw = @json(json_decode($chartJson, true));
    if (!raw || !(raw.series?.[0]?.data?.length)) return;

    const fmt = v => new Intl.NumberFormat('fr-FR',{notation:'compact'}).format(v) + ' DJF';

    function init() {
        if (typeof ApexCharts === 'undefined' || !document.getElementById('chart-debtors')) {
            return setTimeout(init, 150);
        }
        if (window._dbtChart) { window._dbtChart.destroy(); window._dbtChart = null; }
        window._dbtChart = new ApexCharts(document.getElementById('chart-debtors'), {
            chart: { type:'bar', height:180, toolbar:{show:false}, fontFamily:'Inter,sans-serif',
                     animations:{enabled:true,easing:'easeinout',speed:700} },
            series: raw.series ?? [],
            xaxis:  { categories: raw.categories ?? [],
                       labels:{style:{fontSize:'10px',fontFamily:'JetBrains Mono,monospace',colors:'#6B7090'}} },
            yaxis:  { labels:{formatter:fmt, style:{fontSize:'10px',fontFamily:'JetBrains Mono,monospace',colors:'#6B7090'}} },
            colors: ['#E05C3A'],
            plotOptions: { bar:{borderRadius:4,horizontal:true,barHeight:'55%'} },
            grid:   { borderColor:'#E8EAF0', strokeDashArray:4 },
            tooltip:{ y:{formatter:v=>new Intl.NumberFormat('fr-FR').format(v)+' DJF'} },
            dataLabels: { enabled:false },
        });
        window._dbtChart.render();
    }
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endpush