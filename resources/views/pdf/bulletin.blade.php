<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size:10px; color:#1A1E35; background:#FFFFFF; }

        .header { background:#1E2D5A; padding:14px 20px; display:flex; }
        .header-left { flex:1; }
        .header-school { font-size:14px; font-weight:bold; color:#FFFFFF; margin-bottom:3px; }
        .header-sub { font-size:9px; color:rgba(255,255,255,.65); }
        .header-logo { width:44px; height:44px; border-radius:6px; }
        .header-logo-ph { width:44px; height:44px; border-radius:6px; background:rgba(255,255,255,.15); text-align:center; line-height:44px; font-size:18px; font-weight:bold; color:#FFFFFF; }

        .title-bar { background:#F5F3EE; border-bottom:2px solid #E8A838; padding:8px 20px; display:flex; justify-content:space-between; align-items:center; }
        .title-text { font-size:12px; font-weight:bold; color:#1A1E35; letter-spacing:.05em; }
        .period-badge { background:rgba(232,168,56,.2); color:#8A6010; font-size:9px; font-weight:bold; padding:3px 9px; border-radius:4px; }

        .student-bar { display:flex; padding:10px 20px; background:#F8F7F2; border-bottom:1px solid #E0DBD0; }
        .student-info { flex:1; }
        .info-label { font-size:8px; text-transform:uppercase; letter-spacing:.06em; color:#888; margin-bottom:2px; }
        .info-value { font-size:10px; font-weight:bold; color:#1A1E35; }

        .grades-section { padding:14px 20px; }
        table { width:100%; border-collapse:collapse; margin-bottom:12px; }
        thead th { background:#1E2D5A; color:#FFFFFF; padding:6px 8px; font-size:9px; text-transform:uppercase; letter-spacing:.04em; font-weight:bold; text-align:left; }
        thead th:not(:first-child) { text-align:center; }
        tbody td { padding:6px 8px; border-bottom:1px solid #E8E4DC; font-size:9.5px; vertical-align:middle; }
        tbody td:not(:first-child) { text-align:center; }
        tbody tr:nth-child(even) td { background:#FAFAF7; }
        .tr-general td { background:#1E2D5A !important; color:#FFFFFF; font-weight:bold; }

        .subj-name { display:flex; align-items:center; gap:5px; }
        .subj-dot { width:7px; height:7px; border-radius:50%; display:inline-block; }
        .score-good { color:#166534; font-weight:bold; }
        .score-mid  { color:#8A6010; font-weight:bold; }
        .score-bad  { color:#C1432B; font-weight:bold; }
        .score-na   { color:#CCCCCC; }
        .coeff { background:rgba(42,63,126,.1); color:#1E2D5A; font-size:9px; padding:1px 5px; border-radius:3px; font-weight:bold; }
        .note-chip { display:inline-block; background:#F0EDE6; font-size:9px; padding:1px 4px; border-radius:2px; margin:1px; }

        .footer-section { display:flex; padding:10px 20px 14px; border-top:1px solid #E0DBD0; gap:20px; }
        .footer-block { flex:1; }
        .footer-label { font-size:8px; text-transform:uppercase; letter-spacing:.06em; color:#888; margin-bottom:5px; }
        .footer-text { font-size:9.5px; color:#1A1E35; font-style:italic; line-height:1.5; }
        .sign-box { border:1px dashed #DDD; border-radius:4px; height:55px; margin-top:5px; }

        .stats-bar { display:flex; justify-content:center; gap:30px; padding:8px 20px; background:rgba(232,168,56,.08); border-top:1px solid rgba(232,168,56,.2); border-bottom:1px solid rgba(232,168,56,.2); }
        .stat-item { text-align:center; }
        .stat-val { font-size:13px; font-weight:bold; color:#1E2D5A; }
        .stat-lbl { font-size:8px; color:#888; text-transform:uppercase; letter-spacing:.04em; }

        .page-footer { position:fixed; bottom:0; left:0; right:0; padding:6px 20px; background:#F5F3EE; border-top:1px solid #E0DBD0; display:flex; justify-content:space-between; font-size:8px; color:#AAA; }

        /* Absences (conditionnel selon config) */
        .absences-bar { display:flex; gap:20px; padding:6px 20px; background:#F8F7F2; border-top:1px solid #E0DBD0; font-size:9px; color:#666; }
    </style>
</head>
<body>

    {{-- ① $config est passé par BulletinController::pdf() --}}
    @php
        $passing  = $config->passing_score;
        $maxScore = $config->max_score;
        $decimals = $config->decimal_places;
        // Seuil "bien" = 70% du barème (ex: 14/20, 70/100)
        $goodThreshold = $maxScore * 0.70;
    @endphp

    <div class="page-footer">
        <span>{{ $school->name }}</span>
        <span>Bulletin généré le {{ now()->format('d/m/Y') }}</span>
        <span>Confidentiel</span>
    </div>

    {{-- En-tête école --}}
    <div class="header">
        <div class="header-left">
            <div class="header-school">{{ $school->name }}</div>
            <div class="header-sub">
                {{ $school->school_type }}
                @if ($school->address) · {{ $school->address }} @endif
                @if ($school->city) · {{ $school->city }} @endif
            </div>
            @if ($school->phone)
                <div class="header-sub">Tél : {{ $school->phone }}</div>
            @endif
            @if ($school->email)
                <div class="header-sub">{{ $school->email }}</div>
            @endif
        </div>
        @if ($school->logo_path)
            <img src="{{ public_path('storage/'.$school->logo_path) }}" class="header-logo">
        @else
            <div class="header-logo-ph">{{ strtoupper(substr($school->name,0,1)) }}</div>
        @endif
    </div>

    {{-- Titre --}}
    <div class="title-bar">
        <span class="title-text">BULLETIN DE NOTES</span>
        <span class="period-badge">{{ $bulletin->period }} — {{ $ssy->academicYear->label }}</span>
    </div>

    {{-- Infos élève --}}
    <div class="student-bar">
        <div class="student-info">
            <div class="info-label">Nom & Prénom</div>
            <div class="info-value">{{ $student->fullName() }}</div>
        </div>
        <div class="student-info">
            <div class="info-label">Matricule</div>
            <div class="info-value">{{ $student->matricule }}</div>
        </div>
        <div class="student-info">
            <div class="info-label">Classe</div>
            <div class="info-value">{{ $ssy->schoolClass->name }}</div>
        </div>
        <div class="student-info">
            <div class="info-label">Niveau</div>
            <div class="info-value">{{ $ssy->schoolClass->level?->name }}</div>
        </div>
        <div class="student-info">
            <div class="info-label">Date de naissance</div>
            <div class="info-value">{{ $student->birth_date?->format('d/m/Y') ?? '—' }}</div>
        </div>
    </div>

    {{-- Stats rapides --}}
    @if ($data['general_average'] !== null)
        <div class="stats-bar">
            <div class="stat-item">
                {{-- ② Barème dynamique : /20 devient /100 si l'école utilise /100 --}}
                <div class="stat-val">{{ number_format($data['general_average'], $decimals) }}/{{ $maxScore }}</div>
                <div class="stat-lbl">Moyenne générale</div>
            </div>
            <div class="stat-item">
                <div class="stat-val">{{ $data['mention'] }}</div>
                <div class="stat-lbl">Mention</div>
            </div>
            {{-- ③ Rang conditionnel selon $config->show_rank --}}
            @if ($config->show_rank && $data['rank'])
                <div class="stat-item">
                    <div class="stat-val">{{ $data['rank'] }}e / {{ $data['class_count'] }}</div>
                    <div class="stat-lbl">Rang dans la classe</div>
                </div>
            @endif
            <div class="stat-item">
                <div class="stat-val">{{ collect($data['subject_lines'])->where('average','!=',null)->count() }}</div>
                <div class="stat-lbl">Matières évaluées</div>
            </div>
        </div>
    @endif

    {{-- Tableau des matières --}}
    <div class="grades-section">
        <table>
            <thead>
                <tr>
                    <th style="width:22%;">Matière</th>
                    <th style="width:7%;">Coeff</th>
                    <th style="width:20%;">Notes obtenues</th>
                    <th style="width:10%;">Moyenne</th>
                    <th style="width:12%;">Mention</th>
                    <th style="width:18%;">Appréciation</th>
                    <th style="width:11%;">Enseignant</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['subject_lines'] as $line)
                    @php
                        $avg = $line['average'];
                       
                        $css = $avg === null
                            ? 'score-na'
                            : ($avg >= $goodThreshold
                                ? 'score-good'
                                : ($avg >= $passing ? 'score-mid' : 'score-bad'));
                    @endphp
                    <tr>
                        <td>
                            <div class="subj-name">
                                <span class="subj-dot" style="background:{{ $line['subject_color'] ?? '#1E2D5A' }}"></span>
                                {{ $line['subject_name'] }}
                            </div>
                        </td>
                        <td><span class="coeff">{{ $line['coefficient'] }}</span></td>
                        <td>
                            @foreach ($line['grades'] as $g)
                                <span class="note-chip">{{ $g['score'] }}</span>
                            @endforeach
                            @if (empty($line['grades'])) <span class="score-na">—</span> @endif
                        </td>
                        {{-- ④ Décimales et barème dynamiques --}}
                        <td><span class="{{ $css }}">{{ $avg !== null ? number_format($avg, $decimals) : '—' }}</span></td>
                        <td style="font-size:9px;">{{ $line['mention'] }}</td>
                        <td style="font-size:8.5px;color:#555;">{{ $line['appreciation'] }}</td>
                        <td style="font-size:8.5px;color:#666;">{{ $line['teacher'] }}</td>
                    </tr>
                @endforeach

                @if ($data['general_average'] !== null)
                    <tr class="tr-general">
                        <td colspan="3" style="font-size:10px;">MOYENNE GÉNÉRALE PONDÉRÉE</td>
                        {{-- ② Barème dynamique dans la ligne total --}}
                        <td style="font-size:12px;">{{ number_format($data['general_average'], $decimals) }}/{{ $maxScore }}</td>
                        <td colspan="3" style="font-size:9px;">
                            {{ $data['mention'] }}
                            {{-- ③ Rang conditionnel dans la ligne total --}}
                            @if ($config->show_rank && $data['rank'])
                                · Rang {{ $data['rank'] }}e / {{ $data['class_count'] }}
                            @endif
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>

        {{-- ⑤ Absences conditionnelles selon $config->show_absences_on_bulletin --}}
        @if ($config->show_absences_on_bulletin && isset($absenceStats))
            <div class="absences-bar">
                <span><strong>Absences :</strong> {{ $absenceStats['absent'] ?? 0 }}</span>
                <span><strong>Retards :</strong> {{ $absenceStats['late'] ?? 0 }}</span>
                <span><strong>Justifiées :</strong> {{ $absenceStats['excused'] ?? 0 }}</span>
            </div>
        @endif

        {{-- Pied du bulletin --}}
        <div class="footer-section">
            <div class="footer-block" style="flex:2">
                <div class="footer-label">Appréciation générale du conseil de classe</div>
                <div class="footer-text">
                    {{ $bulletin->general_comment
                        ?: ($data['general_average'] !== null
                            ? \App\Services\GradingConfigService::appreciation($data['general_average'], $config)
                            : '—') }}
                </div>
            </div>
            <div class="footer-block">
                <div class="footer-label">Signature & Cachet du Directeur</div>
                <div class="sign-box"></div>
            </div>
            <div class="footer-block">
                <div class="footer-label">Signature du Parent / Tuteur</div>
                <div class="sign-box"></div>
            </div>
        </div>
    </div>

</body>
</html>