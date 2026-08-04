<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolEvent extends Model
{
    protected $fillable = [
        'school_id', 'user_id',
        'title', 'description', 'type',
        'start_date', 'end_date',
        'is_all_day', 'start_time', 'end_time',
        'color',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_all_day' => 'boolean',
    ];

    public static array $TYPES = [
        'exam'     => ['label' => 'Examen',        'color' => '#E05C3A'],
        'holiday'  => ['label' => 'Congé',          'color' => '#22c55e'],
        'meeting'  => ['label' => 'Réunion',        'color' => '#2A3F7E'],
        'activity' => ['label' => 'Activité',       'color' => '#E8A838'],
        'other'    => ['label' => 'Autre',           'color' => '#6B7090'],
    ];

    public function school() { return $this->belongsTo(School::class); }
    public function author() { return $this->belongsTo(User::class, 'user_id'); }

    public function typeLabel(): string
    {
        return self::$TYPES[$this->type]['label'] ?? ucfirst($this->type);
    }

    public function typeColor(): string
    {
        return self::$TYPES[$this->type]['color'] ?? $this->color;
    }

    public function spansDay(\Carbon\Carbon $day): bool
    {
        return $day->between($this->start_date, $this->end_date);
    }

    public function durationDays(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }
}