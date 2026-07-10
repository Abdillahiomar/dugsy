<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'short_name', 'description', 'slogan', 'slug',
        'email', 'phone', 'phone2', 'fax', 'address', 'address2',
        'city', 'country', 'postal_code', 'website',
        'facebook', 'instagram', 'twitter',
        'school_type', 'ministry_code', 'director_name',
        'logo_path', 'banner_path', 'primary_color', 'secondary_color',
        'status', 'trial_ends_at',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────

    public function users()          { return $this->hasMany(User::class); }
    public function staff()          { return $this->hasMany(Staff::class); }
    public function subscriptions()  { return $this->hasMany(Subscription::class); }
    public function students()       { return $this->hasMany(Student::class); }
    public function guardians()      { return $this->hasMany(Guardian::class); }
    public function levels()         { return $this->hasMany(Level::class); }
    public function subjects()       { return $this->hasMany(Subject::class); }
    public function academicYears()  { return $this->hasMany(AcademicYear::class); }
    public function smtpConfig()     { return $this->hasOne(SchoolSmtpConfig::class); }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latestOfMany();
    }

    public function activeAcademicYear()
    {
        return $this->hasOne(AcademicYear::class)->where('is_active', true);
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function logoUrl(): ?string
    {
        return $this->logo_path
            ? asset('storage/' . $this->logo_path)
            : null;
    }
}
