<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SchoolSmtpConfig extends Model
{
    protected $table = 'school_smtp_configs';

    protected $fillable = [
        'school_id', 'host', 'port', 'encryption',
        'username', 'password', 'from_name', 'from_email',
        'reply_to_email', 'is_verified', 'last_tested_at', 'is_active',
    ];

    protected $casts = [
        'is_verified'    => 'boolean',
        'is_active'      => 'boolean',
        'last_tested_at' => 'datetime',
    ];

    // Chiffrement automatique du mot de passe
    public function setPasswordAttribute(?string $value): void
    {
        $this->attributes['password'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getPasswordAttribute(?string $value): ?string
    {
        if (! $value) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return null;
        }
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }
}
