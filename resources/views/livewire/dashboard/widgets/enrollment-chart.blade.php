<div class="w-card" wire:ignore.self>
    <div class="w-header">
        <span class="w-title">Inscriptions & Niveaux</span>
    </div>
    <div class="chart-box">
        <div id="chart-enroll" style="min-height:130px;"></div>
        <div id="chart-levels" style="min-height:130px;margin-top:.5rem;"></div>
    </div>
</div>

@push('scripts')
<script>
(function(){
    const raw = @json(json_decode($chartJson, true));
    if (!raw) return;

    function init() {
        if (typeof ApexCharts === 'undefined') return setTimeout(init, 150);

        const elEnroll = document.getElementById('chart-enroll');
        const elLevels = document.getElementById('chart-levels');

        if (elEnroll && !window._enrChart) {
            window._enrChart = new ApexCharts(elEnroll, {
                chart: { type:'line', height:130, toolbar:{show:false}, sparkline:{enabled:false},
                         fontFamily:'Inter,sans-serif', animations:{enabled:true,speed:700} },
                series: raw.line?.series ?? [],
                xaxis:  { categories: raw.line?.categories ?? [],
                           labels:{style:{fontSize:'10px',fontFamily:'JetBrains Mono,monospace',colors:'#6B7090'}} },
                yaxis:  { labels:{style:{fontSize:'10px',fontFamily:'JetBrains Mono,monospace',colors:'#6B7090'}} },
                colors: ['#E8A838'],
                stroke: { curve:'smooth', width:2.5 },
                markers:{ size:3, hover:{size:5} },
                grid:   { borderColor:'#E8EAF0', strokeDashArray:4 },
                dataLabels: { enabled:false },
            });
            window._enrChart.render();
        }

        if (elLevels && !window._lvlChart) {
            window._lvlChart = new ApexCharts(elLevels, {
                chart: { type:'bar', height:130, toolbar:{show:false},
                         fontFamily:'Inter,sans-serif', animations:{enabled:true,speed:700} },
                series: [{ name:'Élèves', data: raw.donut?.series ?? [] }],
                xaxis:  { categories: raw.donut?.labels ?? [],
                           labels:{style:{fontSize:'10px',fontFamily:'JetBrains Mono,monospace',colors:'#6B7090'}} },
                yaxis:  { labels:{style:{fontSize:'10px',fontFamily:'JetBrains Mono,monospace',colors:'#6B7090'}} },
                colors: raw.donut?.colors ?? ['#1E2D5A'],
                plotOptions: { bar:{borderRadius:4,horizontal:true,barHeight:'60%'} },
                grid:   { borderColor:'#E8EAF0', strokeDashArray:4 },
                legend: { show:false },
                dataLabels: { enabled:false },
            });
            window._lvlChart.render();
        }
    }
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
})();
</script>
@endpush