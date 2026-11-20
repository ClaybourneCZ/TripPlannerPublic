<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    /** @var list<string> */
    protected $fillable = ['name', 'notes', 'distance', 'duration'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'distance' => 'integer',
            'duration' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Waypoint, $this> */
    public function waypoints(): HasMany
    {
        return $this->hasMany(Waypoint::class)->orderBy('order');
    }

    /** @return HasMany<TripTag, $this> */
    public function tags(): HasMany
    {
        return $this->hasMany(TripTag::class);
    }
}
