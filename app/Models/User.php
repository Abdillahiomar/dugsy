<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles,HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'email',
        'password',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * L'école (tenant) à laquelle cet utilisateur appartient.
     */
    public function school()
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Fiche staff associée, si l'utilisateur est un membre du personnel
     * (enseignant, admin, comptable...).
     */
    public function staff()
    {
        return $this->hasOne(Staff::class);
    }

    /**
     * Notifications internes de l'application (table custom "notifications",
     * différent du système de notification natif Laravel).
     */
    public function appNotifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications()
    {
        return $this->appNotifications()->whereNull('read_at');
    }

    /**
     * Limite les requêtes à l'école de l'utilisateur actuellement connecté.
     * A utiliser explicitement dans les controllers, ex:
     * User::forCurrentSchool()->get()
     *
     * On ne met pas de Global Scope automatique ici car User sert à
     * l'authentification (Auth::user()) : un scope automatique créerait
     * une dépendance circulaire au moment du login.
     */
    public function scopeForCurrentSchool($query)
    {
        return $query->where('school_id', auth()->user()?->school_id);
    }

    public function isSuperAdmin(): bool
    {
        return is_null($this->school_id);
    }

    /**
     * Initiales de l'utilisateur (ex: "Abdillahi Omar" -> "AO"),
     * utilisées par les vues du starter kit Laravel (navbar, avatar...).
     */
    public function initials(): string
    {
        return collect(explode(' ', $this->name))
            ->map(fn (string $part) => \Illuminate\Support\Str::substr($part, 0, 1))
            ->join('');
    }
}