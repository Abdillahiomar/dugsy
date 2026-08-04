<div class="w-card" wire:ignore.self>
    <div class="w-header">
        <span class="w-title">Présences — 7 derniers jours</span>
    </div>
    <div class="chart-box">
        <div id="chart-attendance" style="min-height:220px;"></div>
    </div>
</div>

@push('scripts')
<script>
(function(){
    const raw = @json(json_decode($chartJson, true));
    if (!raw) return;

    function init() {
        if (typeof ApexCharts === 'undefined' || !document.getElementById('chart-attendance')) {
            return setTimeout(init, 150);
        }
        if (window._attChart) { window._attChart.destroy(); window._attChart = null; }
        window._attChart = new ApexCharts(document.getElementById('chart-attendance'), {
            chart: { type:'bar', height:220, toolbar:{show:false}, fontFamily:'Inter,sans-serif',
                     animations:{enabled:true,easing:'easeinout',speed:700} },
            series: raw.series ?? [],
            xaxis:  { categories: raw.categories ?? [],
                       labels:{style:{fontSize:'11px',fontFamily:'JetBrains Mono,monospace',colors:'#6B7090'}} },
            yaxis:  { labels:{style:{fontSize:'10px',fontFamily:'JetBrains Mono,monospace',colors:'#6B7090'}} },
            colors: ['#1E2D5A','#E05C3A'],
            plotOptions: { bar:{borderRadius:4,columnWidth:'55%',borderRadiusApplication:'end'} },
            grid:   { borderColor:'#E8EAF0', strokeDashArray:4 },
            legend: { position:'bottom', fontFamily:'Inter,sans-serif', fontSize:'12px' },
            dataLabels: { enabled:false },
        });
        window._attChart.render();
    }
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
    document.addEventListener('livewire:update', () => { if(window._attChart){window._attChart.destroy();window._attChart=null;} setTimeout(init,100); });
})();
</script>
@endpush