<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'status',
        'default_view_id',
    ];

    public function defaultView(): BelongsTo
    {
        return $this->belongsTo(View::class, 'default_view_id');
    }

    /**
     * ID del perfil cuyo nombre es Mozo (único perfil restringido para anular/cerrar pedido en salones y POS).
     */
    public static function mozoProfileId(): ?int
    {
        $id = self::query()
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereRaw('LOWER(TRIM(name)) = ?', ['mozo'])
                    ->orWhereRaw('LOWER(TRIM(name)) = ?', ['mozo.']);
            })
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    public static function userHasMozoProfile(?int $profileId): bool
    {
        if ($profileId === null || $profileId < 1) {
            return false;
        }
        $mozoId = self::mozoProfileId();

        return $mozoId !== null && (int) $profileId === (int) $mozoId;
    }
}
