<?php

use App\Services\AcademicYearService;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public int|null $currentYearId = null;

    public function mount(): void
    {
        $this->currentYearId = AcademicYearService::currentId();
    }

    public function switchYear(int $yearId): void
    {
        AcademicYearService::switchTo($yearId);
        $this->currentYearId = $yearId;

        // Notifie tous les composants Livewire de la page de se rafraîchir
        $this->dispatch('academic-year-changed');
    }

    public function with(): array
    {
        return [
            'years'       => AcademicYearService::allForCurrentSchool(),
            'currentYear' => AcademicYearService::current(),
        ];
    }
}; ?>

<div>
    <style>
        .year-switcher { margin-bottom: 1.5rem; }
        .year-switcher-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 9px; font-weight: 200;
            text-transform: uppercase; letter-spacing: 0.1em;
            color: white;
            padding: 0 0.75rem;
            margin-bottom: 0.4rem;
            display: block;
        }
        .year-selector {
            position: relative;
        }
        .year-current-btn {
            width: 80%;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.07);
            cursor: pointer; text-align: left;
            transition: background 0.12s;
        }
        .year-current-btn:hover { background: rgba(255,255,255,0.12); }
        .year-current-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px; font-weight: 600;
            color: #FFFFFF;
        }
        .year-active-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: #4ADE80; flex-shrink: 0;
            margin-left: 6px;
        }
        .year-chevron {
            width: 14px; height: 14px;
            color: var(--sidebar-muted);
            transition: transform 0.15s;
            flex-shrink: 0;
        }
        .year-chevron.open { transform: rotate(180deg); }

        .year-dropdown {
            position: absolute; top: calc(100% + 4px); left: 0; right: 0;
            background: #162348;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 8px;
            overflow: hidden;
            z-index: 50;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        }
        .year-option {
            display: flex; align-items: center; justify-content: space-between;
            padding: 0.6rem 0.875rem;
            cursor: pointer;
            transition: background 0.1s;
            border: none; background: none; width: 100%; text-align: left;
        }
        .year-option:hover { background: rgba(255,255,255,0.08); }
        .year-option-label {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px; font-weight: 500; color: #FFFFFF;
        }
        .year-option-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 9px; font-weight: 600;
            padding: 1px 6px; border-radius: 3px;
            background: rgba(74,222,128,0.15); color: #4ADE80;
            text-transform: uppercase;
        }
        .year-option-selected {
            font-size: 11px; color: var(--accent); font-weight: 700;
        }
        .year-separator {
            height: 1px; background: rgba(255,255,255,0.08); margin: 0;
        }
    </style>

    <div class="year-switcher">
        <span class="year-switcher-label">Année académique</span>

        <div class="year-selector"
             x-data="{ open: false }"
             x-on:click.outside="open = false">

            <button class="year-current-btn" type="button" x-on:click="open = !open">
                <div style="display:flex; align-items:center; gap:6px; min-width:0;">
                    <span class="year-current-label">{{ $currentYear?->label ?? '—' }}</span>
                    @if ($currentYear?->is_active)
                        <span class="year-active-dot"></span>
                    @endif
                </div>
                <svg class="year-chevron" :class="{ 'open': open }"
                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div class="year-dropdown" x-show="open" x-transition x-cloak>
                @forelse ($years as $year)
                    @if (!$loop->first)
                        <div class="year-separator"></div>
                    @endif
                    <button type="button"
                            class="year-option"
                            wire:click="switchYear({{ $year->id }})"
                            x-on:click="open = false">
                        <span class="year-option-label">{{ $year->label }}</span>
                        <div style="display:flex; align-items:center; gap:6px;">
                            @if ($year->is_active)
                                <span class="year-option-badge">Active</span>
                            @endif
                            @if ($year->id === $currentYearId)
                                <span class="year-option-selected">✓</span>
                            @endif
                        </div>
                    </button>
                @empty
                    <div style="padding:0.75rem; font-size:12px; color:var(--sidebar-muted); text-align:center;">
                        Aucune année
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>