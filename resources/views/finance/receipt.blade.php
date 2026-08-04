<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $receipt->receipt_number }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        @page { size: A5; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family:'Inter',sans-serif; color:#1A1A1A; margin:0; padding:0; font-size:12px; background:#F5F5F3; }
        .sheet { width:148mm; min-height:200mm; margin:0 auto; background:#FFFFFF; padding:12mm 11mm; position:relative; }
        @media print {
            body { background:#FFFFFF; }
            .sheet { width:auto; min-height:auto; margin:0; padding:0; page-break-after:always; }
            .no-print { display:none !important; }
        }

        .head { display:flex; align-items:flex-start; gap:10px; border-bottom:2px solid #1E2D5A; padding-bottom:8px; margin-bottom:12px; }
        .logo { width:44px; height:44px; object-fit:contain; }
        .logo-ph { width:44px; height:44px; border-radius:8px; background:#1E2D5A; color:#FFF; display:flex; align-items:center; justify-content:center; font-family:'JetBrains Mono',monospace; font-size:18px; font-weight:700; }
        .school-name { font-family:'Fraunces',serif; font-size:16px; font-weight:600; color:#1E2D5A; }
        .school-meta { font-size:9.5px; color:#666; margin-top:1px; line-height:1.4; }
        .doc-type { margin-left:auto; text-align:right; }
        .doc-label { font-family:'JetBrains Mono',monospace; font-size:9px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:#888; }
        .doc-num { font-family:'JetBrains Mono',monospace; font-size:15px; font-weight:700; color:#1E2D5A; }

        .meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px 14px; margin-bottom:12px; }
        .meta-lbl { font-family:'JetBrains Mono',monospace; font-size:8px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#999; }
        .meta-val { font-size:12px; font-weight:600; margin-top:1px; }

        table.lines { width:100%; border-collapse:collapse; margin-bottom:10px; }
        table.lines th { font-family:'JetBrains Mono',monospace; font-size:8px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:#999; text-align:left; padding:5px 6px; border-bottom:1px solid #DDD; }
        table.lines th.num, table.lines td.num { text-align:right; }
        table.lines td { padding:6px; border-bottom:1px solid #EEE; font-size:11.5px; }
        table.lines .mono { font-family:'JetBrains Mono',monospace; font-weight:700; }

        .total-box { background:#1E2D5A; color:#FFF; padding:9px 12px; border-radius:6px; display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
        .total-lbl { font-size:11px; opacity:.75; }
        .total-val { font-family:'JetBrains Mono',monospace; font-size:16px; font-weight:700; }
        .in-words { font-size:10.5px; font-style:italic; color:#555; padding:6px 0 12px; border-bottom:1px dashed #DDD; margin-bottom:12px; }

        .balance { display:flex; justify-content:space-between; font-size:11px; padding:3px 0; }
        .balance strong { font-family:'JetBrains Mono',monospace; }

        .sign { display:flex; justify-content:space-between; margin-top:22px; gap:20px; }
        .sign-box { flex:1; }
        .sign-lbl { font-size:9.5px; color:#888; margin-bottom:26px; }
        .sign-line { border-top:1px solid #BBB; padding-top:3px; font-size:10px; }

        .foot { position:absolute; bottom:12mm; left:11mm; right:11mm; font-size:8.5px; color:#AAA; text-align:center; border-top:1px solid #EEE; padding-top:5px; }

        .voided { position:absolute; top:45%; left:50%; transform:translate(-50%,-50%) rotate(-22deg); font-family:'Fraunces',serif; font-size:52px; font-weight:600; color:rgba(224,92,58,.16); letter-spacing:.08em; pointer-events:none; }

        .toolbar { max-width:148mm; margin:14px auto; display:flex; gap:8px; justify-content:flex-end; }
        .tb-btn { padding:7px 16px; border-radius:8px; border:none; background:#1E2D5A; color:#FFF; font-family:'Inter',sans-serif; font-size:13px; font-weight:600; cursor:pointer; }
        .tb-btn.alt { background:#FFF; color:#1E2D5A; border:1px solid #DDD; }
    </style>
</head>
<body>

<div class="toolbar no-print">
    <button onclick="window.print()" class="tb-btn">Imprimer</button>
    <button onclick="window.close()" class="tb-btn alt">Fermer</button>
</div>

@php
    // Deux exemplaires : souche école + exemplaire parent
    $copies = ['Exemplaire école', 'Exemplaire parent'];

    $totalDue  = $receipt->lines->sum(fn ($l) => $l->invoice?->amount_due ?? 0);
    $totalLeft = $receipt->lines->sum(fn ($l) => $l->invoice?->balance() ?? 0);

    try {
        $words = ucfirst(\Illuminate\Support\Number::spell($receipt->amount, locale: 'fr'));
    } catch (\Throwable $e) {
        $words = null;   // extension intl absente
    }
@endphp

@foreach ($copies as $copy)
<div class="sheet">
    @if ($receipt->isVoided())
        <div class="voided">ANNULÉ</div>
    @endif

    <div class="head">
        @if ($school?->logo_path)
            <img src="{{ public_path('storage/'.$school->logo_path) }}" class="logo" alt="">
        @else
            <div class="logo-ph">{{ strtoupper(substr($school?->name ?? 'D', 0, 1)) }}</div>
        @endif
        <div>
            <div class="school-name">{{ $school?->name }}</div>
            <div class="school-meta">
                {{ $school?->address }}@if($school?->phone) · Tél. {{ $school->phone }}@endif<br>
                {{ $school?->email }}
            </div>
        </div>
        <div class="doc-type">
            <div class="doc-label">Reçu de paiement</div>
            <div class="doc-num">{{ $receipt->receipt_number }}</div>
            <div class="school-meta">{{ $copy }}</div>
        </div>
    </div>

    <div class="meta-grid">
        <div>
            <div class="meta-lbl">Élève</div>
            <div class="meta-val">{{ $receipt->student?->fullName() }}</div>
            <div class="school-meta">
                {{ $receipt->student?->matricule }}
                @if ($receipt->student?->currentSchoolYear?->schoolClass)
                    · {{ $receipt->student->currentSchoolYear->schoolClass->name }}
                @endif
            </div>
        </div>
        <div>
            <div class="meta-lbl">Date de règlement</div>
            <div class="meta-val">{{ $receipt->paid_at->format('d/m/Y à H:i') }}</div>
            <div class="school-meta">Année {{ $receipt->academicYear?->label }}</div>
        </div>
        <div>
            <div class="meta-lbl">Mode de règlement</div>
            <div class="meta-val">{{ $receipt->methodLabel() }}</div>
            @if ($receipt->reference)
                <div class="school-meta">Réf. {{ $receipt->reference }}</div>
            @endif
        </div>
        <div>
            <div class="meta-lbl">Encaissé par</div>
            <div class="meta-val">{{ $receipt->receivedBy?->name }}</div>
        </div>
    </div>

    <table class="lines">
        <thead>
            <tr>
                <th>Désignation</th>
                <th>Échéance</th>
                <th class="num">Montant réglé</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($receipt->lines as $l)
                <tr>
                    <td>
                        {{ $l->invoice?->invoice_number }}
                        @if ($l->invoice?->feeStructure)
                            <div style="font-size:9.5px;color:#888;">{{ $l->invoice->feeStructure->name }}</div>
                        @endif
                    </td>
                    <td style="font-size:10.5px;color:#777;">{{ $l->invoice?->due_at?->format('d/m/Y') ?? '—' }}</td>
                    <td class="num mono">{{ number_format($l->amount, 0, ',', ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total-box">
        <span class="total-lbl">Total encaissé</span>
        <span class="total-val">{{ number_format($receipt->amount, 0, ',', ' ') }} DJF</span>
    </div>

    @if ($words)
        <div class="in-words">Arrêté la présente quittance à la somme de {{ $words }} francs Djibouti.</div>
    @endif

    <div class="balance">
        <span style="color:#777;">Reste dû après ce règlement (échéances concernées)</span>
        <strong style="color:{{ $totalLeft > 0 ? '#C04020' : '#166534' }};">
            {{ number_format($totalLeft, 0, ',', ' ') }} DJF
        </strong>
    </div>

    @if ($receipt->note)
        <div style="font-size:10px;color:#777;margin-top:6px;">Note : {{ $receipt->note }}</div>
    @endif

    @if ($receipt->isVoided())
        <div style="margin-top:10px;padding:7px 10px;border:1px solid rgba(224,92,58,.3);border-radius:6px;font-size:10px;color:#C04020;">
            Reçu annulé le {{ $receipt->voided_at->format('d/m/Y à H:i') }} par {{ $receipt->voidedBy?->name }}.
            Motif : {{ $receipt->void_reason }}
        </div>
    @endif

    <div class="sign">
        <div class="sign-box">
            <div class="sign-lbl">Le caissier</div>
            <div class="sign-line">{{ $receipt->receivedBy?->name }}</div>
        </div>
        <div class="sign-box">
            <div class="sign-lbl">Le parent / tuteur</div>
            <div class="sign-line">Nom et signature</div>
        </div>
    </div>

    <div class="foot">
        Reçu généré par Dugsi le {{ now()->format('d/m/Y à H:i') }} · Document à conserver
    </div>
</div>
@endforeach

</body>
</html>