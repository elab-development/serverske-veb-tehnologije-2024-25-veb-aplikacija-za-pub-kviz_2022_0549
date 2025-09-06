<?php

namespace App\Http\Controllers;

use App\Http\Resources\SeasonResource;
use App\Models\Participation;
use App\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *   name="Seasons",
 *   description="Manage and view seasons"
 * )
 */
class SeasonController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/seasons",
     *   tags={"Seasons"},
     *   summary="List all seasons",
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="seasons",
     *         type="array",
     *         @OA\Items(type="object",
     *           @OA\Property(property="id", type="integer", example=1),
     *           @OA\Property(property="name", type="string", example="Season 2025"),
     *           @OA\Property(property="slug", type="string", example="season-2025"),
     *           @OA\Property(property="start_date", type="string", example="2025-01-15"),
     *           @OA\Property(property="end_date", type="string", example="2025-06-15"),
     *           @OA\Property(property="is_active", type="boolean", example=true),
     *           @OA\Property(property="description", type="string", example="Spring/Summer season.")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=404, description="No seasons found.")
     * )
     */
    public function index()
    {
        $seasons = Season::query()
            ->orderByDesc('is_active')
            ->orderBy('start_date')
            ->get();

        if ($seasons->isEmpty()) {
            return response()->json('No seasons found.', 404);
        }

        return response()->json([
            'seasons' => SeasonResource::collection($seasons),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * @OA\Post(
     *   path="/api/seasons",
     *   tags={"Seasons"},
     *   summary="Create a season (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"name"},
     *       @OA\Property(property="name", type="string", maxLength=255, example="Season 2026"),
     *       @OA\Property(property="slug", type="string", maxLength=255, example="season-2026"),
     *       @OA\Property(property="start_date", type="string", example="2026-02-01"),
     *       @OA\Property(property="end_date", type="string", example="2026-06-01"),
     *       @OA\Property(property="is_active", type="boolean", example=false),
     *       @OA\Property(property="description", type="string", example="Early-year season.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Created",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Season created successfully"),
     *       @OA\Property(property="season", type="object",
     *         @OA\Property(property="id", type="integer", example=12),
     *         @OA\Property(property="name", type="string", example="Season 2026"),
     *         @OA\Property(property="slug", type="string", example="season-2026"),
     *         @OA\Property(property="start_date", type="string", example="2026-02-01"),
     *         @OA\Property(property="end_date", type="string", example="2026-06-01"),
     *         @OA\Property(property="is_active", type="boolean", example=false),
     *         @OA\Property(property="description", type="string", example="Early-year season.")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=403, description="Only admins can create seasons"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can create seasons'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:seasons,slug',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        if (empty($validated['slug'])) {
            $base = Str::slug($validated['name']);
            $slug = $base;
            $i = 1;
            while (Season::where('slug', $slug)->exists()) {
                $slug = $base . '-' . $i++;
            }
            $validated['slug'] = $slug;
        }

        $season = Season::create($validated);

        return response()->json([
            'message' => 'Season created successfully',
            'season'  => new SeasonResource($season->loadCount('events')),
        ]);
    }

    /**
     * @OA\Get(
     *   path="/api/seasons/{id}/board",
     *   tags={"Seasons"},
     *   summary="Season TOTAL board and per-event boards",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="season", type="object",
     *         @OA\Property(property="id", type="integer", example=3),
     *         @OA\Property(property="name", type="string", example="Season 2025")
     *       ),
     *       @OA\Property(property="total_board", type="array",
     *         @OA\Items(type="object",
     *           @OA\Property(property="team", type="string", example="Quiz Masters"),
     *           @OA\Property(property="points", type="integer", example=245),
     *           @OA\Property(property="rank", type="integer", example=1)
     *         )
     *       ),
     *       @OA\Property(property="events", type="array",
     *         @OA\Items(type="object",
     *           @OA\Property(property="event", type="object",
     *             @OA\Property(property="id", type="integer", example=10),
     *             @OA\Property(property="title", type="string", example="Belgrade Pub Quiz #1"),
     *             @OA\Property(property="starts_at", type="string", example="2025-03-10T19:00:00"),
     *             @OA\Property(property="status", type="string", example="completed")
     *           ),
     *           @OA\Property(property="board", type="array",
     *             @OA\Items(type="object",
     *               @OA\Property(property="team", type="string", example="Quiz Masters"),
     *               @OA\Property(property="points", type="integer", example=87),
     *               @OA\Property(property="rank", type="integer", nullable=true, example=1)
     *             )
     *           )
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=404, description="No events / participations found.")
     * )
     */
    public function board(Season $season)
    {
        $events = $season->events()
            ->select('id', 'title', 'starts_at', 'ends_at', 'status')
            ->orderBy('starts_at')
            ->get();

        if ($events->isEmpty()) {
            return response()->json('No events in this season.', 404);
        }

        $eventIds = $events->pluck('id');

        $hasAnyParticipation = Participation::whereIn('event_id', $eventIds)->exists();
        if (!$hasAnyParticipation) {
            return response()->json('No participations found for this season.', 404);
        }

        $totals = Participation::select('user_id')
            ->selectRaw('SUM(total_points) as points')
            ->whereIn('event_id', $eventIds)
            ->groupBy('user_id')
            ->with('user:id,name')
            ->get()
            ->sortByDesc('points')
            ->values();

        $rank = 0;
        $lastPoints = null;
        $totalBoard = $totals->map(function ($row) use (&$rank, &$lastPoints) {
            if ($lastPoints !== (int) $row->points) {
                $rank++;
                $lastPoints = (int) $row->points;
            }
            return [
                'team'   => $row->user?->name,
                'points' => (int) $row->points,
                'rank'   => $rank,
            ];
        });

        $eventBoards = [];
        foreach ($events as $ev) {
            $rows = Participation::with(['user:id,name'])
                ->where('event_id', $ev->id)
                ->orderByDesc('total_points')
                ->orderBy('rank')
                ->orderBy('created_at')
                ->get(['id', 'event_id', 'user_id', 'total_points', 'rank']);

            if ($rows->isEmpty()) {
                continue;
            }

            $board = $rows->map(fn($p) => [
                'team'   => $p->user->name,
                'points' => (int) $p->total_points,
                'rank'   => $p->rank !== null ? (int) $p->rank : null,
            ]);

            $eventBoards[] = [
                'event' => [
                    'id'         => $ev->id,
                    'title'      => $ev->title,
                    'starts_at'  => $ev->starts_at,
                    'status'     => $ev->status,
                ],
                'board' => $board->values(),
            ];
        }

        return response()->json([
            'season'       => ['id' => $season->id, 'name' => $season->name],
            'total_board'  => $totalBoard,
            'events'       => $eventBoards,
        ]);
    }

    /**
     * @OA\Get(
     *   path="/api/seasons/{id}",
     *   tags={"Seasons"},
     *   summary="Get a single season",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="season", type="object",
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="name", type="string", example="Season 2025"),
     *         @OA\Property(property="slug", type="string", example="season-2025"),
     *         @OA\Property(property="start_date", type="string", example="2025-01-15"),
     *         @OA\Property(property="end_date", type="string", example="2025-06-15"),
     *         @OA\Property(property="is_active", type="boolean", example=true),
     *         @OA\Property(property="description", type="string", example="Spring/Summer season.")
     *       )
     *     )
     *   )
     * )
     */
    public function show(Season $season)
    {
        return response()->json([
            'season' => new SeasonResource($season->loadCount('events')),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Season $season)
    {
        //
    }

    /**
     * @OA\Put(
     *   path="/api/seasons/{id}",
     *   tags={"Seasons"},
     *   summary="Update a season (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(
     *     required=false,
     *     @OA\JsonContent(
     *       @OA\Property(property="name", type="string", maxLength=255, example="Season 2025 Updated"),
     *       @OA\Property(property="slug", type="string", maxLength=255, example="season-2025-updated"),
     *       @OA\Property(property="start_date", type="string", example="2025-02-01"),
     *       @OA\Property(property="end_date", type="string", example="2025-07-01"),
     *       @OA\Property(property="is_active", type="boolean", example=true),
     *       @OA\Property(property="description", type="string", example="Updated description.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Updated",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Season updated successfully"),
     *       @OA\Property(property="season", type="object",
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="name", type="string", example="Season 2025 Updated"),
     *         @OA\Property(property="slug", type="string", example="season-2025-updated"),
     *         @OA\Property(property="start_date", type="string", example="2025-02-01"),
     *         @OA\Property(property="end_date", type="string", example="2025-07-01"),
     *         @OA\Property(property="is_active", type="boolean", example=true),
     *         @OA\Property(property="description", type="string", example="Updated description.")
     *       )
     *     )
     *   ),
     *   @OA\Response(response=403, description="Only admins can update seasons"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, Season $season)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can update seasons'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:seasons,slug,' . $season->id,
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string',
        ]);

        // If slug explicitly set to empty string, regenerate from (new) name if present
        if (array_key_exists('slug', $validated) && $validated['slug'] === '') {
            unset($validated['slug']);
            if (!empty($validated['name'])) {
                $base = Str::slug($validated['name']);
                $slug = $base;
                $i = 1;
                while (Season::where('slug', $slug)->where('id', '!=', $season->id)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $validated['slug'] = $slug;
            }
        }

        $season->update($validated);

        return response()->json([
            'message' => 'Season updated successfully',
            'season'  => new SeasonResource($season->fresh()->loadCount('events')),
        ]);
    }

    /**
     * @OA\Delete(
     *   path="/api/seasons/{id}",
     *   tags={"Seasons"},
     *   summary="Delete a season (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="Deleted",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Season deleted successfully")
     *     )
     *   ),
     *   @OA\Response(response=403, description="Only admins can delete seasons")
     * )
     */
    public function destroy(Season $season)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can delete seasons'], 403);
        }

        $season->delete();

        return response()->json(['message' => 'Season deleted successfully']);
    }
}
