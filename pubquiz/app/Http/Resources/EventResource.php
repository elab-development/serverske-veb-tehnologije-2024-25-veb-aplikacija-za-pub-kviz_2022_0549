<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'  => $this->id,
            'season_id' => $this->season_id,
            'title'   => $this->title,
            'location' => $this->location,
            'starts_at'  => $this->starts_at,
            'ends_at' => $this->ends_at,
            'status'   => $this->status,
            'scores_finalized' => (bool) $this->scores_finalized,
            'season' => SeasonResource::make($this->whenLoaded('season')),
        ];
    }
}
