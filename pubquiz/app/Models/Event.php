<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'season_id',
        'title',
        'location',
        'starts_at',
        'ends_at',
        'status',
        'scores_finalized',
    ];

    public function season()
    {
        return $this->belongsTo(Season::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'event_participations')
            ->withPivot(['total_points', 'rank'])
            ->withTimestamps();
    }

    public function participations()
    {
        return $this->hasMany(Participation::class);
    }

    public function scopeUpcoming($q)
    {
        return $q->where('status', 'scheduled')->where('starts_at', '>=', now());
    }
}
