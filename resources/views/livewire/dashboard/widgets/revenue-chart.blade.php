<div class="w-card" wire:ignore.self>
    <div class="w-header">
        <span class="w-title">Encaissements</span>
        <span class="w-meta" id="rev-total-lbl"></span>
    </div>
    <div class="chart-box">
        <div id="chart-revenue" style="min-height:230px;"></div>
    </div>
</div>

@push('scripts')
<script>
(function(){
    const raw = @json(json_decode($chartJson, true));
    if (!raw) return;

    const fmt = v => new Intl.NumberFormat('fr-FR').format(v) + ' DJF';
    const fmtC = v => new Intl.NumberFormat('fr-FR',{notation:'compact'}).format(v) + ' DJF';

    const lbl = document.getElementById('rev-total-lbl');
    if (lbl) lbl.textContent = fmt(raw.total ?? 0);

    function init() {
        if (typeof ApexCharts === 'undefined' || !document.getElementById('chart-revenue')) {
            return setTimeout(init, 150);
        }
        if (window._rvChart) { window._rvChart.destroy(); window._rvChart = null; }
        window._rvChart = new ApexCharts(document.getElementById('chart-revenue'), {
            chart: { type:'area', height:230, toolbar:{show:false}, fontFamily:'Inter,sans-serif',
                     animations:{enabled:true,easing:'easeinout',speed:700} },
            series: raw.series ?? [],
            xaxis:  { categories: raw.categories ?? [],
                       labels:{style:{fontSize:'11px',fontFamily:'JetBrains Mono,monospace',colors:'#6B7090'}} },
            yaxis:  { labels:{formatter:fmtC, style:{fontSize:'10px',fontFamily:'JetBrains Mono,monospace',colors:'#6B7090'}} },
            colors: ['#1E2D5A'],
            fill:   { type:'gradient', gradient:{shadeIntensity:1,opacityFrom:.2,opacityTo:.02,stops:[0,90]} },
            stroke: { curve:'smooth', width:2 },
            grid:   { borderColor:'#E8EAF0', strokeDashArray:4, xaxis:{lines:{show:false}} },
            tooltip:{ y:{formatter:fmt} },
            dataLabels: { enabled:false },
        });
        window._rvChart.render();
    }
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();

    document.addEventListener('livewire:update', () => {
        if (window._rvChart) { window._rvChart.destroy(); window._rvChart = null; }
        setTimeout(init, 100);
    });
})();
</script>
@endpush