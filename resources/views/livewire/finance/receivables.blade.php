<?php

use App\Models\SchoolClass;
use App\Models\StudentInvoice;
use App\Services\AcademicYearService;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public string $search = '';
    public string $classId = '';
    public string $bucket = '';        // '' | '30' | '60' | '90'
    public string $sort = 'anciennete'; // anciennete | reste | nom
    public array  $selected = [];

    private function rowsQuery()
    {
        $schoolId = auth()->user()->school_id;
        $year     = AcademicYearService::current();

        $q = StudentInvoice::withoutGlobalScopes()
            ->where('student_invoices.school_id', $schoolId)
            ->where('student_invoices.academic_year_id', $year?->id)
            ->where('student_invoices.status', '!=', 'cancelled')
            ->whereRaw('student_invoices.amount_paid < student_invoices.amount_due')
            ->whereNotNull('student_invoices.due_at')
            ->whereDate('student_invoices.due_at', '<', today())
            ->join('student_school_years AS ssy', 'ssy.id', '=', 'student_invoices.student_school_year_id')
            ->join('students AS s', 's.id', '=', 'ssy.student_id')
            ->leftJoin('school_classes AS sc', 'sc.id', '=', 'ssy.school_class_id')
            ->when($this->classId, fn ($q) => $q->where('sc.id', $this->classId))
            ->when($this->search, function ($q) {
                $q->where(function ($w) {
                    $w->where('s.matricule', 'like', "%{$this->search}%")
                      ->orWhere('s.first_name', 'like', "%{$this->search}%")
                      ->orWhere('s.last_name', 'like', "%{$this->search}%");
                });
            })
            ->groupBy('s.id', 's.first_name', 's.last_name', 's.matricule', 'sc.id', 'sc.name')
            ->selectRaw('
                s.id AS student_id, s.first_name, s.last_name, s.matricule,
                sc.name AS class_name,
                COUNT(*) AS nb_echeances,
                SUM(student_invoices.amount_due - student_invoices.amount_paid) AS reste,
                MIN(student_invoices.due_at) AS plus_ancienne,
                DATEDIFF(CURDATE(), MIN(student_invoices.due_at)) AS anciennete
            ')
            ->havingRaw('reste > 0');

        // Tranche d'ancienneté
        $q = match ($this->bucket) {
            '30'    => $q->havingRaw('anciennete BETWEEN 1 AND 30'),
            '60'    => $q->havingRaw('anciennete BETWEEN 31 AND 60'),
            '90'    => $q->havingRaw('anciennete > 60'),
            default => $q,
        };

        return match ($this->sort) {
            'reste' => $q->orderByDesc('reste'),
            'nom'   => $q->orderBy('s.last_name')->orderBy('s.first_name'),
            default => $q->orderByDesc('anciennete'),
        };
    }

    public function toggleAll(array $ids): void
    {
        $this->selected = count($this->selected) === count($ids) ? [] : $ids;
    }

    public function export()
    {
        $this->authorize('finance.export');

        $rows = $this->rowsQuery()->get();
        if ($this->selected) {
            $rows = $rows->whereIn('student_id', $this->selected);
        }

        $phones = $this->guardianPhones($rows->pluck('student_id')->all());

        return response()->streamDownload(function () use ($rows, $phones) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Matricule', 'Élève', 'Classe', 'Tuteur', 'Téléphone', 'Échéances', 'Reste DJF', 'Ancienneté (j)'], ';');
            foreach ($rows as $r) {
                $g = $phones[$r->student_id] ?? null;
                fputcsv($out, [
                    $r->matricule,
                    $r->first_name . ' ' . $r->last_name,
                    $r->class_name,
                    $g ? $g->first_name . ' ' . $g->last_name : '',
                    $g->phone ?? '',
                    $r->nb_echeances,
                    (int) $r->reste,
                    (int) $r->anciennete,
                ], ';');
            }
            fclose($out);
        }, 'impayes-' . today()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Tuteur principal par élève — requête séparée pour éviter la duplication des lignes agrégées. */
    private function guardianPhones(array $studentIds)
    {
        if (! $studentIds) return collect();

        return DB::table('student_guardian AS sg')
            ->join('guardians AS g', 'g.id', '=', 'sg.guardian_id')
            ->whereIn('sg.student_id', $studentIds)
            ->orderByDesc('sg.is_primary_contact')
            ->select('sg.student_id', 'g.first_name', 'g.last_name', 'g.phone')
            ->get()
            ->unique('student_id')
            ->keyBy('student_id');
    }

    public function with(): array
    {
        $schoolId = auth()->user()->school_id;
        $year     = AcademicYearService::current();

        $rows   = $this->rowsQuery()->get();
        $phones = $this->guardianPhones($rows->pluck('student_id')->all());

        $classes = SchoolClass::where('school_id', $schoolId)
            ->where('academic_year_id', $year?->id)
            ->orderBy('name')->get(['id', 'name']);

        $totalReste = (int) $rows->sum('reste');
        $allIds     = $rows->pluck('student_id')->all();

        return compact('year', 'rows', 'phones', 'classes', 'totalReste', 'allIds');
    }
}; ?>

@include('partials.finance-styles')

<style>
    .age-pill { font-family:'JetBrains Mono',monospace; font-size:10px; font-weight:700; padding:3px 8px; border-radius:5px; }
    .age-1 { background:rgba(232,168,56,.15); color:#8A6010; }
    .age-2 { background:rgba(224,92,58,.12); color:#C04020; }
    .age-3 { background:var(--accent-red); color:#FFFFFF; }
    .tel { font-family:'JetBrains Mono',monospace; font-size:.8125rem; color:var(--sidebar-soft); text-decoration:none; }
    .tel:hover { text-decoration:underline; }
</style>

<div>
    <div class="page-head">
        <div>
            <div class="page-title">Impayés</div>
            <div class="page-sub">
                {{ $rows->count() }} élève(s) ·
                <strong style="color:var(--accent-red);">{{ number_format($totalReste, 0, ',', ' ') }} DJF</strong> à recouvrer
            </div>
        </div>
        <div style="display:flex;gap:.6rem;">
            @can('finance.export')
                <button wire:click="export" class="btn">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Exporter {{ $selected ? '('.count($selected).')' : 'tout' }}
                </button>
            @endcan
        </div>
    </div>

    <div class="filters">
        <div class="filter-field" style="flex:1;min-width:200px;">
            <label class="lbl">Rechercher</label>
            <input wire:model.live.debounce.300ms="search" type="text" class="fin-input" placeholder="Nom ou matricule…">
        </div>
        <div class="filter-field">
            <label class="lbl">Classe</label>
            <select wire:model.live="classId" class="fin-select">
                <option value="">Toutes</option>
                @foreach ($classes as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field">
            <label class="lbl">Ancienneté</label>
            <select wire:model.live="bucket" class="fin-select">
                <option value="">Toutes</option>
                <option value="30">1 – 30 jours</option>
                <option value="60">31 – 60 jours</option>
                <option value="90">Plus de 60 jours</option>
            </select>
        </div>
        <div class="filter-field">
            <label class="lbl">Trier par</label>
            <select wire:model.live="sort" class="fin-select">
                <option value="anciennete">Ancienneté</option>
                <option value="reste">Montant dû</option>
                <option value="nom">Nom</option>
            </select>
        </div>
    </div>

    <div class="fin-card">
        <div class="fin-card-body" style="padding:0;">
            <table class="fin-table">
                <thead>
                    <tr>
                        <th style="width:36px;">
                            <input type="checkbox" wire:click="toggleAll({{ json_encode($allIds) }})"
                                   @checked(count($selected) && count($selected) === count($allIds))>
                        </th>
                        <th>Élève</th><th>Classe</th><th>Tuteur</th>
                        <th class="num">Échéances</th><th class="num">Reste dû</th>
                        <th class="num">Ancienneté</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        @php
                            $g   = $phones[$r->student_id] ?? null;
                            $age = (int) $r->anciennete;
                            $cls = $age > 60 ? 'age-3' : ($age > 30 ? 'age-2' : 'age-1');
                        @endphp
                        <tr>
                            <td><input type="checkbox" wire:model.live="selected" value="{{ $r->student_id }}"></td>
                            <td>
                                <div style="font-weight:600;">{{ $r->first_name }} {{ $r->last_name }}</div>
                                <div class="lbl">{{ $r->matricule }}</div>
                            </td>
                            <td style="opacity:.65;">{{ $r->class_name ?? '—' }}</td>
                            <td>
                                @if ($g)
                                    <div style="font-size:.8125rem;">{{ $g->first_name }} {{ $g->last_name }}</div>
                                    <a href="tel:{{ $g->phone }}" class="tel">{{ $g->phone }}</a>
                                @else
                                    <span style="opacity:.35;font-size:.8125rem;">Aucun tuteur</span>
                                @endif
                            </td>
                            <td class="num"><span class="st st-overdue">{{ $r->nb_echeances }}</span></td>
                            <td class="num mono" style="color:var(--accent-red);font-size:.875rem;">
                                {{ number_format((int)$r->reste, 0, ',', ' ') }}
                            </td>
                            <td class="num">
                                <span class="age-pill {{ $cls }}">{{ $age }} j</span>
                                <div class="lbl" style="margin-top:2px;">
                                    depuis {{ \Carbon\Carbon::parse($r->plus_ancienne)->format('d/m/Y') }}
                                </div>
                            </td>
                            <td class="num">
                                @can('finance.collect')
                                    <a href="{{ route('finances.collect', ['student' => $r->student_id]) }}"
                                       class="btn btn-icon btn-green" wire:navigate title="Encaisser">
                                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="fin-empty">Aucun impayé échu. Tout est à jour.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>