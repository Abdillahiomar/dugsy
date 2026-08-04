<style>
    /* ── Structure ── */
    .fin-card { border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); overflow:hidden; margin-bottom:1.25rem; }
    .fin-card:last-child { margin-bottom:0; }
    .fin-card-header { padding:.875rem 1.5rem; border-bottom:1px solid var(--line); display:flex; align-items:center; gap:.65rem; }
    .fin-card-title { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); }
    .fin-card-sub { font-size:.75rem; color:var(--ink); opacity:.45; margin-left:auto; font-family:'JetBrains Mono',monospace; }
    .fin-card-body { padding:1.25rem 1.5rem; }
    .fin-icon { width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .fin-icon svg { width:15px; height:15px; }

    /* ── Typographie de données ── */
    .mono { font-family:'JetBrains Mono',monospace; font-weight:700; font-size:.8125rem; }
    .lbl { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.1em; color:var(--ink); opacity:.42; }

    /* ── KPI ── */
    .kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:.75rem; margin-bottom:1.5rem; }
    @media (max-width:1000px) { .kpi-grid { grid-template-columns:repeat(2,1fr); } }
    @media (max-width:520px)  { .kpi-grid { grid-template-columns:1fr; } }
    .kpi { padding:1rem 1.15rem; border-radius:12px; border:1px solid var(--line); background:var(--paper-raised); }
    .kpi-val { font-family:'JetBrains Mono',monospace; font-size:1.35rem; font-weight:700; color:var(--ink); margin-top:.3rem; line-height:1.1; }
    .kpi-unit { font-size:.7rem; font-weight:500; opacity:.5; margin-left:3px; }
    .kpi-foot { font-size:.72rem; margin-top:.35rem; opacity:.55; }
    .kpi.dark { background:var(--sidebar); border-color:var(--sidebar); }
    .kpi.dark .lbl { color:rgba(255,255,255,.55); opacity:1; }
    .kpi.dark .kpi-val, .kpi.dark .kpi-foot { color:#FFFFFF; }
    .kpi.dark .kpi-foot { opacity:.6; }
    .kpi.good .kpi-val { color:#166534; }
    .kpi.warn .kpi-val { color:#8A6010; }
    .kpi.bad  .kpi-val { color:var(--accent-red); }

    /* ── Tables ── */
    .fin-table { width:100%; border-collapse:collapse; }
    .fin-table th { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:.1em; color:var(--ink); opacity:.42; text-align:left; padding:.55rem .85rem; border-bottom:1px solid var(--line); white-space:nowrap; }
    .fin-table td { padding:.65rem .85rem; border-bottom:1px solid var(--line); font-size:.875rem; vertical-align:middle; color:var(--ink); }
    .fin-table tr:last-child td { border-bottom:none; }
    .fin-table tbody tr:hover { background:rgba(42,63,126,.03); }
    .fin-table .num { text-align:right; }
    .fin-table th.num { text-align:right; }
    .row-voided td { opacity:.4; text-decoration:line-through; }

    /* ── Badges d'état ── */
    .st { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:600; padding:2px 7px; border-radius:4px; white-space:nowrap; display:inline-block; }
    .st-pending { background:rgba(42,63,126,.1);  color:var(--sidebar-soft); }
    .st-partial { background:rgba(232,168,56,.15); color:#8A6010; }
    .st-overdue { background:rgba(224,92,58,.12);  color:#C04020; }
    .st-paid    { background:rgba(30,120,80,.1);   color:#166534; }
    .st-voided  { background:rgba(120,120,120,.12); color:#555; }

    /* ── Barres de progression ── */
    .bar-row { display:grid; grid-template-columns:150px 1fr 140px; gap:.85rem; align-items:center; padding:.5rem 0; }
    @media (max-width:700px) { .bar-row { grid-template-columns:110px 1fr; } .bar-row .bar-amounts { grid-column:1/-1; text-align:left; } }
    .bar-name { font-size:.8125rem; font-weight:600; color:var(--ink); }
    .bar-track { height:20px; border-radius:5px; background:rgba(42,63,126,.07); overflow:hidden; position:relative; }
    .bar-fill { height:100%; border-radius:5px; transition:width .3s; }
    .bar-pct { position:absolute; right:7px; top:50%; transform:translateY(-50%); font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:700; color:var(--ink); opacity:.65; }
    .bar-amounts { text-align:right; font-family:'JetBrains Mono',monospace; font-size:.75rem; }

    /* ── Graphique mensuel ── */
    .chart { display:flex; align-items:flex-end; gap:.4rem; height:150px; padding-top:1.25rem; }
    .chart-col { flex:1; display:flex; flex-direction:column; align-items:center; gap:.35rem; height:100%; justify-content:flex-end; }
    .chart-bar { width:100%; max-width:44px; border-radius:4px 4px 0 0; background:var(--sidebar-soft); position:relative; min-height:2px; transition:background .15s; }
    .chart-col:hover .chart-bar { background:var(--sidebar); }
    .chart-bar-val { position:absolute; top:-16px; left:50%; transform:translateX(-50%); font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:700; color:var(--ink); opacity:.5; white-space:nowrap; }
    .chart-lbl { font-family:'JetBrains Mono',monospace; font-size:9px; opacity:.45; text-transform:uppercase; }

    /* ── Filtres ── */
    .filters { display:flex; flex-wrap:wrap; gap:.65rem; align-items:flex-end; margin-bottom:1.25rem; }
    .filter-field { display:flex; flex-direction:column; gap:.3rem; }
    .fin-input, .fin-select { padding:.45rem .7rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.8125rem; font-family:'Inter',sans-serif; color:var(--ink); outline:none; }
    .fin-input:focus, .fin-select:focus { border-color:var(--sidebar-soft); box-shadow:0 0 0 3px rgba(42,63,126,.08); }

    /* ── Boutons ── */
    .btn { display:inline-flex; align-items:center; gap:6px; padding:.45rem 1rem; border-radius:8px; border:1px solid var(--line); background:var(--paper); font-size:.8125rem; font-weight:500; font-family:'Inter',sans-serif; color:var(--ink); cursor:pointer; text-decoration:none; }
    .btn:hover { border-color:var(--sidebar-soft); color:var(--sidebar-soft); }
    .btn svg { width:14px; height:14px; }
    .btn-primary { background:var(--sidebar); border-color:var(--sidebar); color:#FFFFFF; }
    .btn-primary:hover { background:var(--sidebar-soft); color:#FFFFFF; }
    .btn-green { background:#166534; border-color:#166534; color:#FFFFFF; }
    .btn-green:hover { background:#14532d; color:#FFFFFF; }
    .btn-danger { background:var(--paper); border-color:rgba(224,92,58,.35); color:var(--accent-red); }
    .btn-danger:hover { background:rgba(224,92,58,.07); color:var(--accent-red); }
    .btn-icon { padding:.3rem .45rem; border-radius:6px; }
    .btn:disabled { opacity:.4; cursor:not-allowed; }

    /* ── Modal ── */
    .modal-back { position:fixed; inset:0; background:rgba(15,20,35,.45); display:flex; align-items:center; justify-content:center; z-index:60; padding:1rem; }
    .modal { width:100%; max-width:430px; border-radius:14px; background:var(--paper-raised); border:1px solid var(--line); overflow:hidden; box-shadow:0 20px 50px rgba(0,0,0,.2); }
    .modal-head { padding:1rem 1.5rem; border-bottom:1px solid var(--line); font-family:'Fraunces',serif; font-size:1.05rem; font-weight:600; color:var(--ink); }
    .modal-body { padding:1.25rem 1.5rem; }
    .modal-foot { padding:1rem 1.5rem; border-top:1px solid var(--line); display:flex; justify-content:flex-end; gap:.6rem; }

    /* ── Alertes ── */
    .fin-alert { display:flex; align-items:flex-start; gap:.65rem; padding:.7rem 1rem; border-radius:9px; font-size:.8125rem; margin-bottom:1rem; }
    .fin-alert svg { width:16px; height:16px; flex-shrink:0; margin-top:1px; }
    .fin-alert.err  { background:rgba(224,92,58,.08); border:1px solid rgba(224,92,58,.2); color:var(--accent-red); }
    .fin-alert.warn { background:rgba(232,168,56,.1); border:1px solid rgba(232,168,56,.28); color:#8A6010; }
    .fin-alert.ok   { background:rgba(30,120,80,.07); border:1px solid rgba(30,120,80,.2); color:#166534; }

    .fin-empty { text-align:center; padding:2.5rem 1rem; font-size:.875rem; color:var(--ink); opacity:.45; }
    .page-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
    .page-title { font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; color:var(--ink); }
    .page-sub { font-size:.8125rem; color:var(--ink); opacity:.5; margin-top:2px; }
</style>