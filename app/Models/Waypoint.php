<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Waypoint extends Model
{
    /** @var list<string> */
    protected $fillable = ['point_lng', 'point_lat', 'name', 'description', 'order'];

    protected static function booted(): void
    {
        static::saving(function (Waypoint $waypoint): void {
            foreach (['point_lat' => 90, 'point_lng' => 180] as $attribute => $limit) {
                $value = $waypoint->getAttributes()[$attribute] ?? null;

                if (! is_numeric($value) || ! is_finite((float) $value) || abs((float) $value) > $limit) {
                    throw new InvalidArgumentException("Invalid coordinate: {$attribute}.");
                }
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'point_lng' => 'float',
            'point_lat' => 'float',
            'order' => 'integer',
        ];
    }

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /** @return HasMany<WaypointTag, $this> */
    public function tags(): HasMany
    {
        return $this->hasMany(WaypointTag::class);
    }
}
