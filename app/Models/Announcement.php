<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder; // Oubliez pas d'importer le Builder

class Announcement extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'school_id', 'created_by', 'title', 'content', 'audience', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'expired_at'   => 'datetime', // À ajouter si vous gérez les expirations
    ];

    /**
     * Scope pour filtrer uniquement les annonces publiées.
     */
    public function scopePublished(Builder $query): Builder
    {
        // On considère qu'une annonce est publiée si 'published_at' n'est pas nul 
        // et que la date est passée (inférieure ou égale à maintenant)
        return $query->whereNotNull('published_at')
                     ->where('published_at', '<=', now());
    }

    // app/Models/Announcement.php — remplacer
    public function author()
    {
        return $this->belongsTo(User::class, 'created_by'); // ← created_by au lieu de user_id
    }

    /**
     * Détermine le statut textuel de l'annonce.
     *
     * @return string
     */
    public function statusLabel(): string
    {
        // 1. Si la date de publication est nulle ou dans le futur -> Brouillon / Planifiée
        if (is_null($this->published_at)) {
            return 'Brouillon';
        }

        if ($this->published_at->isFuture()) {
            return 'Brouillon'; // Ou 'Planifiée' si vous préférez adapter votre CSS
        }

        // 2. Si vous avez un champ 'expired_at' ou 'ends_at' dans votre table (Optionnel)
        // Si ce n'est pas le cas, vous pouvez retirer ou adapter cette condition.
        if (isset($this->expired_at) && $this->expired_at->isPast()) {
            return 'Expirée';
        }

        // 3. Par défaut, si la date est passée -> Publiée
        return 'Publiée';
    }


    /**
     * Retourne un label lisible pour l'audience ciblée.
     *
     * @return string
     */
    public function targetLabel(): string
    {
        return match ($this->audience) {
            'all'      => 'Tout le monde',
            'students' => 'Élèves',
            'staff'    => 'Personnel / Profs',
            'parents'  => 'Parents',
            default    => ucfirst($this->audience ?? 'Non spécifié'),
        };
    }

    /**
     * Vérifie si l'annonce est un brouillon.
     *
     * @return bool
     */
    public function isDraft(): bool
    {
        // Une annonce est un brouillon si la date n'est pas définie 
        // ou si elle est planifiée pour le futur
        return is_null($this->published_at) || $this->published_at->isFuture();
    }

    /**
     * Vérifie si l'annonce a expiré.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        // Si vous avez un champ 'expired_at' en base de données :
        if (isset($this->expired_at)) {
            return $this->expired_at->isPast();
        }

        // Sinon, par défaut, une annonce n'expire pas toute seule
        return false;
    }
}
