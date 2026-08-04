<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bulletins — {{ $schoolClass->name }} — {{ $period }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #1A1E35;
            background: #FFFFFF;
        }

        /* ── Page break ── */
        .bulletin-page {
            width: 100%;
            padding: 10mm 12mm;
            page-break-after: always;
        }
        .bulletin-page:last-child {
            page-break-after: avoid;
        }

        /* ── En-tête école ── */
        .school-header {
            width: 100%;
            background: #1E2D5A;
            color: #FFFFFF;
            padding: 9px 12px;
            border-radius: 5px 5px 0 0;
            margin-bottom: 0;
        }

        /* layout logo + texte côte à côte via table */
        .school-header-table { width: 100%; border-collapse: collapse; }
        .school-header-logo  { width: 44px; vertical-align: middle; }
        .school-header-text  { vertical-align: middle; padding-left: 10px; }
        .school-header-right { width: 44px; vertical-align: middle; text-align: right; }

        .school-name { font-size: 13px; font-weight: bold; }
        .school-sub  { font-size: 8.5px; color: rgba(255,255,255,.65); margin-top: 2px; }

        .logo-img {
            width: 40px;
            height: 40px;
            border-radius: 5px;
            object-fit: contain;
        }
        .logo-ph {
            width: 40px;
            height: 40px;
            border-radius: 5px;
            background: rgba(255,255,255,.15);
            text-align: center;
            line-height: 40px;
            font-size: 18px;
            font-weight: bold;
            color: #FFFFFF;
        }

        /* ── Bande titre ── */
        .title-band {
            width: 100%;
            border-bottom: 2px solid #E8A838;
            background: rgba(30,45,90,.03);
            padding: 6px 12px;
        }
        .title-band-table { width: 100%; border-collapse: collapse; }
        .bulletin-title {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #1E2D5A;
        }
        .period-badge {
            font-size: 9px;
            font-weight: bold;
            padding: 2px 8px;
            border-radius: 4px;
            background: rgba(232,168,56,.18);
            color: #7A5A00;
        }

        /* ── Infos élève ── */
        .student-bar {
            width: 100%;
            border-collapse: collapse;
            background: #F5F3EE;
            border-bottom: 1px solid #E0DBD0;
        }
        .student-bar td {
            padding: 6px 12px;
            width: 25%;
            vertical-align: top;
        }
        .info-label {
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #6B7090;
            margin-bottom: 2px;
        }
        .info-value {
            font-size: 10.5px;
            font-weight: bold;
            color: #1A1E35;
        }

        /* ── Tableau des matières — toutes colonnes dans la largeur A4 ── */
        .grades-table {
            width: 100%;
            border-collapse: collapse;
        }

        .grades-table th {
            background: #F5F3EE;
            border-bottom: 2px solid #1E2D5A;
            border-top: none;
            padding: 5px 6px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6B7090;
            text-align: left;
        }
        .grades-table th.center { text-align: center; }

        .grades-table td {
            padding: 4.5px 6px;
            border-bottom: 1px solid #F0EDE8;
            font-size: 9.5px;
            vertical-align: middle;
        }
        .grades-table td.center { text-align: center; }

        .grades-table tbody tr:nth-child(even) td { background: rgba(245,243,238,.6); }
        .grades-table tr:last-child td { border-bottom: none; }

        /* Ligne totale */
        .row-general td {
            background: #1E2D5A !important;
            color: #FFFFFF !important;
            font-weight: bold;
            font-size: 10px;
        }

        /* Couleurs notes */
        .score-good { color: #166534; font-weight: bold; }
        .score-mid  { color: #7A5A00; font-weight: bold; }
        .score-bad  { color: #C04020; font-weight: bold; }
        .score-na   { color: #AAAAAA; }

        .coeff-badge {
            display: inline-block;
            font-size: 8px;
            font-weight: bold;
            padding: 1px 5px;
            border-radius: 3px;
            background: rgba(42,63,126,.1);
            color: #2A3F7E;
        }

        .subj-dot {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            margin-right: 4px;
            vertical-align: middle;
        }

        /* ── Décision + absences ── */
        .decision-section {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #E0DBD0;
            background: rgba(30,45,90,.02);
        }
        .decision-section td { padding: 7px 12px; vertical-align: middle; }

        .decision-label-sm {
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #6B7090;
            margin-bottom: 3px;
        }
        .decision-badge {
            display: inline-block;
            font-size: 9px;
            font-weight: bold;
            padding: 3px 10px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .db-admis_felicitations   { background: #D1FAE5; color: #065F46; }
        .db-admis_encouragements  { background: #DBEAFE; color: #1E40AF; }
        .db-admis                 { background: #EDE9FE; color: #3730A3; }
        .db-passage_conditionnel  { background: #FEF3C7; color: #92400E; }
        .db-redoublant            { background: #FEE2E2; color: #991B1B; }

        .avg-big {
            font-size: 16px;
            font-weight: bold;
        }

        /* Absences */
        .absences-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #E0DBD0;
            background: rgba(30,45,90,.02);
        }
        .absences-table td { padding: 5px 12px; }

        .abs-dot {
            display: inline-block;
            width: 7px; height: 7px;
            border-radius: 50%;
            margin-right: 3px;
            vertical-align: middle;
        }

        /* Footer signatures */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #E0DBD0;
        }
        .footer-table td { padding: 8px 12px; vertical-align: top; }

        .footer-label-sm {
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #6B7090;
            margin-bottom: 4px;
        }
        .footer-sign {
            height: 38px;
            border: 1px dashed #C8C4BC;
            border-radius: 3px;
        }
        .footer-value {
            font-size: 9.5px;
            color: #1A1E35;
            font-style: italic;
        }

        @page { margin: 0; size: A4 portrait; }
    </style>
</head>
<body>

@foreach ($bulletins as $item)
@php
    $ssy      = $item['ssy'];
    $student  = $item['student'];
    $bulletin = $item['bulletin'];
    $data     = $item['data'];
    $abs      = $item['absenceStats'];

    $passing       = $config->passing_score;
    $maxScore      = $config->max_score;
    $decimals      = $config->decimal_places;
    $goodThreshold = $maxScore * 0.70;
    $decision      = $bulletin->decision ?? 'admis';

    $decisionLabels = [
        'admis_felicitations'  => 'Admis avec félicitations',
        'admis_encouragements' => 'Admis avec encouragements',
        'admis'                => 'Admis',
        'passage_conditionnel' => 'Passage conditionnel',
        'redoublant'           => 'Redoublant',
    ];
@endphp

<div class="bulletin-page">

    {{-- ── En-tête école ── --}}
    <div class="school-header">
        <table class="school-header-table">
            <tr>
                {{-- Logo à gauche --}}
                <td class="school-header-logo">
                    @if ($school->logo_path)
                        <img class="logo-img"
                             src="{{ public_path('storage/'.$school->logo_path) }}"
                             alt="Logo">
                    @else
                        <div class="logo-ph">{{ strtoupper(substr($school->name,0,1)) }}</div>
                    @endif
                </td>

                {{-- Nom + infos au centre --}}
                <td class="school-header-text">
                    <div class="school-name">{{ $school->name }}</div>
                    <div class="school-sub">
                        {{ $school->school_type ?? '' }}
                        @if ($school->city) · {{ $school->city }} @endif
                        @if ($school->ministry_code) · {{ $school->ministry_code }} @endif
                    </div>
                    @if ($school->phone)
                        <div class="school-sub">Tél : {{ $school->phone }}</div>
                    @endif
                </td>

                {{-- Espace droite --}}
                <td class="school-header-right"></td>
            </tr>
        </table>
    </div>

    {{-- ── Bande titre ── --}}
    <div class="title-band">
        <table class="title-band-table">
            <tr>
                <td><span class="bulletin-title">Bulletin de Notes</span></td>
                <td style="text-align:right;">
                    <span class="period-badge">{{ $bulletin->period }} · {{ $ssy->academicYear->label }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Infos élève ── --}}
    <table class="student-bar">
        <tr>
            <td>
                <div class="info-label">Nom &amp; Prénom</div>
                <div class="info-value">{{ $student->fullName() }}</div>
            </td>
            <td>
                <div class="info-label">Matricule</div>
                <div class="info-value">{{ $student->matricule }}</div>
            </td>
            <td>
                <div class="info-label">Classe</div>
                <div class="info-value">{{ $ssy->schoolClass->name }}</div>
            </td>
            <td>
                <div class="info-label">Année scolaire</div>
                <div class="info-value">{{ $ssy->academicYear->label }}</div>
            </td>
        </tr>
    </table>

    {{-- ── Tableau des matières ── --}}
    <table class="grades-table">
        <thead>
            <tr>
                {{-- Largeurs calibrées pour tenir dans 186mm (A4 - marges) --}}
                <th style="width:26%">Matière</th>
                <th style="width:9%"  class="center">Coeff</th>
                <th style="width:18%">Notes</th>
                <th style="width:13%" class="center">Moyenne</th>
                <th style="width:13%" class="center">Mention</th>
                @if ($config->show_teacher_appreciation)
                    <th style="width:11%">Appréciation</th>
                    <th style="width:10%">Enseignant</th>
                @else
                    <th style="width:11%">Enseignant</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($data['subject_lines'] as $line)
                @php
                    $avg    = $line['average'];
                    $avgCss = $avg === null ? 'score-na'
                        : ($avg >= $goodThreshold ? 'score-good'
                            : ($avg >= $passing ? 'score-mid' : 'score-bad'));
                @endphp
                <tr>
                    <td>
                        <span class="subj-dot" style="background:{{ $line['subject_color'] ?? '#1E2D5A' }}"></span>
                        {{ $line['subject_name'] }}
                    </td>
                    <td class="center">
                        <span class="coeff-badge">{{ $line['coefficient'] }}</span>
                    </td>
                    <td>
                        @if (! empty($line['grades']))
                            {{ collect($line['grades'])->pluck('score')->implode(' · ') }}
                        @else
                            <span class="score-na">—</span>
                        @endif
                    </td>
                    <td class="center">
                        <span class="{{ $avgCss }}">
                            {{ $avg !== null ? number_format($avg, $decimals) : '—' }}
                        </span>
                    </td>
                    <td class="center" style="font-size:8.5px;color:#6B7090;">
                        {{ $line['mention'] }}
                    </td>
                    @if ($config->show_teacher_appreciation)
                        <td style="font-size:8px;color:#6B7090;">{{ $line['appreciation'] }}</td>
                    @endif
                    <td style="font-size:8px;color:#6B7090;">
                        {{-- Tronquer le nom enseignant pour éviter débordement --}}
                        {{ \Illuminate\Support\Str::limit($line['teacher'] ?? '', 18) }}
                    </td>
                </tr>
            @endforeach

            {{-- Ligne moyenne générale --}}
            @if ($data['general_average'] !== null)
                <tr class="row-general">
                    <td colspan="2">MOYENNE GÉNÉRALE</td>
                    <td colspan="2" class="center">
                        {{ number_format($data['general_average'], $decimals) }}/{{ $maxScore }}
                    </td>
                    <td colspan="{{ $config->show_teacher_appreciation ? 3 : 2 }}">
                        {{ $data['mention'] }}
                        @if ($config->show_rank && $data['rank'])
                            · Rang {{ $data['rank'] }}e / {{ $data['class_count'] }}
                        @endif
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    {{-- ── Décision d'admission ── --}}
    @if ($bulletin->decision)
        <table class="decision-section">
            <tr>
                <td style="width:60%;">
                    <div class="decision-label-sm">Décision du conseil de classe</div>
                    <span class="decision-badge db-{{ $decision }}">
                        {{ $decisionLabels[$decision] ?? ucfirst($decision) }}
                    </span>
                </td>
                @if ($data['general_average'] !== null)
                    <td style="width:40%;text-align:right;">
                        <div class="decision-label-sm">Moyenne générale</div>
                        <span class="avg-big" style="color:{{ $data['general_average'] >= $passing ? '#166534' : '#C04020' }}">
                            {{ number_format($data['general_average'], $decimals) }}/{{ $maxScore }}
                        </span>
                    </td>
                @endif
            </tr>
        </table>
    @endif

    {{-- ── Absences ── --}}
    @if ($config->show_absences_on_bulletin && $abs)
        <table class="absences-table">
            <tr>
                <td>
                    <span class="abs-dot" style="background:#C04020;"></span>
                    Absences : <strong style="color:#C04020;">{{ $abs['absent'] }}</strong>
                    &nbsp;&nbsp;
                    <span class="abs-dot" style="background:#7A5A00;"></span>
                    Retards : <strong style="color:#7A5A00;">{{ $abs['late'] }}</strong>
                    &nbsp;&nbsp;
                    <span class="abs-dot" style="background:#166534;"></span>
                    Justifiées : <strong style="color:#166534;">{{ $abs['excused'] }}</strong>
                </td>
            </tr>
        </table>
    @endif

    {{-- ── Footer : appréciation + signatures ── --}}
    <table class="footer-table">
        <tr>
            <td style="width:42%;">
                <div class="footer-label-sm">Appréciation générale</div>
                <div class="footer-value">{{ $bulletin->general_comment ?: '—' }}</div>
            </td>
            <td style="width:31%;">
                <div class="footer-label-sm">Signature du Directeur</div>
                <div class="footer-sign"></div>
            </td>
            <td style="width:27%;">
                <div class="footer-label-sm">Signature du Parent</div>
                <div class="footer-sign"></div>
            </td>
        </tr>
    </table>

</div>
@endforeach

</body>
</html>