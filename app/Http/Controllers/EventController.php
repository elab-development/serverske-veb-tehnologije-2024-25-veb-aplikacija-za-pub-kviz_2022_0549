<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Participation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *   name="Events",
 *   description="Manage and view events"
 * )
 */
class EventController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/events",
     *   tags={"Events"},
     *   summary="List events with search, filters, sort, pagination",
     *   @OA\Parameter(name="search", in="query", description="Search by title or location", @OA\Schema(type="string")),
     *   @OA\Parameter(name="season_id", in="query", @OA\Schema(type="integer")),
     *   @OA\Parameter(
     *     name="status",
     *     in="query",
     *     description="Filter by one or more statuses (repeat the param).",
     *     style="form",
     *     explode=true,
     *     @OA\Schema(type="array", @OA\Items(type="string", enum={"scheduled","completed","cancelled"}))
     *   ),
     *   @OA\Parameter(name="upcoming", in="query", @OA\Schema(type="boolean")),
     *   @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"starts_at","title","created_at","status"})),
     *   @OA\Parameter(name="sort_dir", in="query", @OA\Schema(type="string", enum={"asc","desc"})),
     *   @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *   @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", minimum=1)),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="events",
     *         type="array",
     *         @OA\Items(type="object",
     *           @OA\Property(property="id", type="integer", example=10),
     *           @OA\Property(property="season_id", type="integer", example=3),
     *           @OA\Property(property="title", type="string", example="Belgrade Pub Quiz #1"),
     *           @OA\Property(property="location", type="string", example="Knez Mihailova 5"),
     *           @OA\Property(property="starts_at", type="string", example="2025-03-10T19:00:00"),
     *           @OA\Property(property="ends_at", type="string", nullable=true, example="2025-03-10T21:00:00"),
     *           @OA\Property(property="status", type="string", example="scheduled"),
     *           @OA\Property(property="scores_finalized", type="boolean", example=false),
     *           @OA\Property(
     *             property="season",
     *             type="object",
     *             nullable=true,
     *             description="Present when the relation is loaded",
     *             @OA\Property(property="id", type="integer", example=3),
     *             @OA\Property(property="name", type="string", example="Season 2025"),
     *             @OA\Property(property="slug", type="string", example="season-2025"),
     *             @OA\Property(property="start_date", type="string", example="2025-01-15"),
     *             @OA\Property(property="end_date", type="string", example="2025-06-15"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="description", type="string", example="Spring/Summer season.")
     *           )
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=404, description="No events found.")
     * )
     */
    public function index(Request $request)
    {
        $query = Event::query()
            ->with('season');

        /* Search */
        if ($request->filled('search')) {
            $s = trim($request->string('search'));
            $query->where(function ($qq) use ($s) {
                $qq->where('title', 'like', "%{$s}%")
                    ->orWhere('location', 'like', "%{$s}%");
            });
        }

        /* Filters */
        if ($request->filled('season_id')) {
            $query->where('season_id', (int) $request->season_id);
        }

        if ($request->filled('status')) {
            $allowed = ['scheduled', 'completed', 'cancelled'];
            $statuses = (array) $request->status;
            $query->whereIn('status', array_values(array_intersect($statuses, $allowed)));
        }

        if ($request->boolean('upcoming')) {
            $query->upcoming();
        }

        /* Sort */
        $sortBy  = $request->string('sort_by')->toString() ?: 'starts_at';
        $sortDir = strtolower($request->string('sort_dir')->toString() ?: 'asc');

        $sortable = ['starts_at', 'title', 'created_at', 'status'];
        if (!in_array($sortBy, $sortable, true)) {
            $sortBy = 'starts_at';
        }
        if (!in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }
        $query->orderBy($sortBy, $sortDir);

        /* Pagination */
        $perPage = (int) ($request->input('per_page', 15));
        $perPage = max(1, min($perPage, 100));

        $events = $query->paginate($perPage)->appends($request->query());

        if ($events->total() === 0) {
            return response()->json('No events found.', 404);
        }

        if ($events->isEmpty()) {
            return response()->json('No events found.', 404);
        }

        return response()->json([
            'events' => EventResource::collection($events->items()),
        ]);
    }

    /**
     * @OA\Get(
     *   path="/api/events/{id}/board",
     *   tags={"Events"},
     *   summary="Leaderboard for a given event (team, points, rank)",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="board",
     *         type="array",
     *         @OA\Items(type="object",
     *           @OA\Property(property="team", type="string", example="Quiz Masters"),
     *           @OA\Property(property="points", type="integer", example=87),
     *           @OA\Property(property="rank", type="integer", nullable=true, example=1)
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=404, description="No participations found for this event.")
     * )
     */
    public function board(Event $event)
    {
        $rows = Participation::with(['user:id,name'])
            ->where('event_id', $event->id)
            ->orderByDesc('total_points')
            ->orderBy('rank')
            ->orderBy('created_at')
            ->get(['id', 'event_id', 'user_id', 'total_points', 'rank']);

        if ($rows->isEmpty()) {
            return response()->json('No participations found for this event.', 404);
        }

        $board = $rows->map(fn($p) => [
            'team'   => $p->user->name,
            'points' => (int) $p->total_points,
            'rank'   => isset($p->rank) ? (int) $p->rank : null,
        ]);

        return response()->json(['board' => $board->values()]);
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
     *   path="/api/events",
     *   tags={"Events"},
     *   summary="Create an event (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"season_id","title","starts_at"},
     *       @OA\Property(property="season_id", type="integer", example=3),
     *       @OA\Property(property="title", type="string", maxLength=255, example="Belgrade Pub Quiz #2"),
     *       @OA\Property(property="location", type="string", maxLength=255, example="Skadarlija 12"),
     *       @OA\Property(property="starts_at", type="string", example="2025-04-05T19:00:00"),
     *       @OA\Property(property="ends_at", type="string", nullable=true, example="2025-04-05T21:00:00"),
     *       @OA\Property(property="status", type="string", enum={"scheduled","completed","cancelled"}, example="scheduled"),
     *       @OA\Property(property="scores_finalized", type="boolean", example=false)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Created",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Event created successfully"),
     *       @OA\Property(property="event", type="object",
     *         @OA\Property(property="id", type="integer", example=11),
     *         @OA\Property(property="season_id", type="integer", example=3),
     *         @OA\Property(property="title", type="string", example="Belgrade Pub Quiz #2"),
     *         @OA\Property(property="location", type="string", example="Skadarlija 12"),
     *         @OA\Property(property="starts_at", type="string", example="2025-04-05T19:00:00"),
     *         @OA\Property(property="ends_at", type="string", nullable=true, example="2025-04-05T21:00:00"),
     *         @OA\Property(property="status", type="string", example="scheduled"),
     *         @OA\Property(property="scores_finalized", type="boolean", example=false)
     *       )
     *     )
     *   ),
     *   @OA\Response(response=403, description="Only admins can create events"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can create events'], 403);
        }

        $validated = $request->validate([
            'season_id' => 'required|exists:seasons,id',
            'title' => 'required|string|max:255',
            'location'  => 'nullable|string|max:255',
            'starts_at' => 'required|date',
            'ends_at'  => 'nullable|date|after_or_equal:starts_at',
            'status'  => 'sometimes|in:scheduled,completed,cancelled',
            'scores_finalized' => 'sometimes|boolean',
        ]);

        $event = Event::create($validated);

        return response()->json([
            'message' => 'Event created successfully',
            'event'   => new EventResource($event),
        ]);
    }

    /**
     * @OA\Get(
     *   path="/api/events/{id}",
     *   tags={"Events"},
     *   summary="Get a single event",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="event", type="object",
     *         @OA\Property(property="id", type="integer", example=10),
     *         @OA\Property(property="season_id", type="integer", example=3),
     *         @OA\Property(property="title", type="string", example="Belgrade Pub Quiz #1"),
     *         @OA\Property(property="location", type="string", example="Knez Mihailova 5"),
     *         @OA\Property(property="starts_at", type="string", example="2025-03-10T19:00:00"),
     *         @OA\Property(property="ends_at", type="string", nullable=true, example="2025-03-10T21:00:00"),
     *         @OA\Property(property="status", type="string", example="completed"),
     *         @OA\Property(property="scores_finalized", type="boolean", example=true),
     *         @OA\Property(
     *           property="season",
     *           type="object",
     *           nullable=true,
     *           @OA\Property(property="id", type="integer", example=3),
     *           @OA\Property(property="name", type="string", example="Season 2025"),
     *           @OA\Property(property="slug", type="string", example="season-2025"),
     *           @OA\Property(property="start_date", type="string", example="2025-01-15"),
     *           @OA\Property(property="end_date", type="string", example="2025-06-15"),
     *           @OA\Property(property="is_active", type="boolean", example=true),
     *           @OA\Property(property="description", type="string", example="Spring/Summer season.")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function show(Event $event)
    {
        $event->load(['season'])->loadCount('participations');

        return response()->json([
            'event' => new EventResource($event),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event)
    {
        //
    }

    /**
     * @OA\Put(
     *   path="/api/events/{id}",
     *   tags={"Events"},
     *   summary="Update an event (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(
     *     required=false,
     *     @OA\JsonContent(
     *       @OA\Property(property="season_id", type="integer", example=3),
     *       @OA\Property(property="title", type="string", maxLength=255, example="Belgrade Pub Quiz #1 - Updated"),
     *       @OA\Property(property="location", type="string", maxLength=255, example="Terazije 1"),
     *       @OA\Property(property="starts_at", type="string", example="2025-03-15T19:00:00"),
     *       @OA\Property(property="ends_at", type="string", nullable=true, example="2025-03-15T21:00:00"),
     *       @OA\Property(property="status", type="string", enum={"scheduled","completed","cancelled"}, example="completed"),
     *       @OA\Property(property="scores_finalized", type="boolean", example=true)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Updated",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Event updated successfully"),
     *       @OA\Property(property="event", type="object",
     *         @OA\Property(property="id", type="integer", example=10),
     *         @OA\Property(property="season_id", type="integer", example=3),
     *         @OA\Property(property="title", type="string", example="Belgrade Pub Quiz #1 - Updated"),
     *         @OA\Property(property="location", type="string", example="Terazije 1"),
     *         @OA\Property(property="starts_at", type="string", example="2025-03-15T19:00:00"),
     *         @OA\Property(property="ends_at", type="string", nullable=true, example="2025-03-15T21:00:00"),
     *         @OA\Property(property="status", type="string", example="completed"),
     *         @OA\Property(property="scores_finalized", type="boolean", example=true)
     *       )
     *     )
     *   ),
     *   @OA\Response(response=403, description="Only admins can update events"),
     *   @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, Event $event)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can update events'], 403);
        }

        $validated = $request->validate([
            'season_id' => 'sometimes|exists:seasons,id',
            'title' => 'sometimes|string|max:255',
            'location' => 'nullable|string|max:255',
            'starts_at'  => 'sometimes|date',
            'ends_at'  => 'nullable|date|after_or_equal:starts_at',
            'status' => 'sometimes|in:scheduled,completed,cancelled',
            'scores_finalized' => 'sometimes|boolean',
        ]);

        $event->update($validated);

        return response()->json([
            'message' => 'Event updated successfully',
            'event'   => new EventResource($event->fresh()->load(['season'])->loadCount('participations')),
        ]);
    }

    /**
     * @OA\Delete(
     *   path="/api/events/{id}",
     *   tags={"Events"},
     *   summary="Delete an event (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="Deleted",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Event deleted successfully")
     *     )
     *   ),
     *   @OA\Response(response=403, description="Only admins can delete events")
     * )
     */
    public function destroy(Event $event)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can delete events'], 403);
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted successfully']);
    }
}
