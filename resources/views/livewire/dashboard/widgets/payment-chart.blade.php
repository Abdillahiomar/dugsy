<div class="w-card" wire:ignore.self>
    <div class="w-header">
        <span class="w-title">Méthodes de paiement</span>
    </div>
    <div class="chart-box" style="display:flex;justify-content:center;">
        <div id="chart-payment" style="min-height:220px;width:100%;max-width:340px;"></div>
    </div>
</div>

@push('scripts')
<script>
(function(){
    const raw = @json(json_decode($chartJson, true));
    if (!raw) return;

    const fmt = v => new Intl.NumberFormat('fr-FR').format(v) + ' DJF';
    const fmtC = v => new Intl.NumberFormat('fr-FR',{notation:'compact'}).format(v) + ' DJF';

    function init() {
        if (typeof ApexCharts === 'undefined' || !document.getElementById('chart-payment')) {
            return setTimeout(init, 150);
        }
        if (window._pyChart) { window._pyChart.destroy(); window._pyChart = null; }
        window._pyChart = new ApexCharts(document.getElementById('chart-payment'), {
            chart: { type:'donut', height:220, toolbar:{show:false}, fontFamily:'Inter,sans-serif',
                     animations:{enabled:true,easing:'easeinout',speed:700} },
            series: raw.series ?? [],
            labels: raw.labels ?? [],
            colors: raw.colors ?? [],
            legend: { position:'bottom', fontFamily:'Inter,sans-serif', fontSize:'11px' },
            plotOptions: { pie:{ donut:{ size:'65%',
                labels:{ show:true, total:{ show:true, label:'Total',
                    formatter: w => fmtC(w.globals.seriesTotals.reduce((a,b)=>a+b,0))
                }}
            }}},
            dataLabels: { enabled:false },
            tooltip: { y:{ formatter: fmt } },
        });
        window._pyChart.render();
    }
    document.addEventListener('DOMContentLoaded', init);
    if (document.readyState !== 'loading') init();
    document.addEventListener('livewire:update', () => { if(window._pyChart){window._pyChart.destroy();window._pyChart=null;} setTimeout(init,100); });
})();
</script>
@endpush