<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaypointTag extends Model
{
    /** @var list<string> */
    protected $fillable = ['name'];

    /** @return BelongsTo<Waypoint, $this> */
    public function waypoint(): BelongsTo
    {
        return $this->belongsTo(Waypoint::class);
    }
}
