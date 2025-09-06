<?php

namespace App\Http\Controllers;

use App\Http\Resources\ParticipationResource;
use App\Models\Event;
use App\Models\Participation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *   name="Participations",
 *   description="Register, view and score participations"
 * )
 */
class ParticipationController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/participations",
     *   tags={"Participations"},
     *   summary="List participations (admin: all, team: only own)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(
     *         property="participations",
     *         type="array",
     *         @OA\Items(type="object",
     *           @OA\Property(property="id", type="integer", example=42),
     *           @OA\Property(property="event_id", type="integer", example=10),
     *           @OA\Property(property="user_id", type="integer", example=7),
     *           @OA\Property(property="total_points", type="integer", example=86),
     *           @OA\Property(property="rank", type="integer", nullable=true, example=1),
     *           @OA\Property(
     *             property="user", type="object",
     *             @OA\Property(property="id", type="integer", example=7),
     *             @OA\Property(property="name", type="string", example="Quiz Masters"),
     *             @OA\Property(property="email", type="string", example="team@example.com"),
     *             @OA\Property(property="role", type="string", example="team")
     *           ),
     *           @OA\Property(
     *             property="event", type="object",
     *             @OA\Property(property="id", type="integer", example=10),
     *             @OA\Property(property="season_id", type="integer", example=3),
     *             @OA\Property(property="title", type="string", example="Belgrade Pub Quiz #1"),
     *             @OA\Property(property="starts_at", type="string", example="2025-03-10T19:00:00"),
     *             @OA\Property(property="ends_at", type="string", nullable=true, example="2025-03-10T21:00:00"),
     *             @OA\Property(property="status", type="string", example="completed")
     *           )
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=404, description="No participations found.")
     * )
     */
    public function index()
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        $query = Participation::with([
            'user:id,name,email,role',
            'event:id,season_id,title,starts_at,ends_at,status',
        ])->orderBy('event_id')->orderBy('rank')->orderByDesc('total_points');

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $participations = $query->get();

        if ($participations->isEmpty()) {
            return response()->json('No participations found.', 404);
        }

        return response()->json([
            'participations' => ParticipationResource::collection($participations),
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
     *   path="/api/participations",
     *   tags={"Participations"},
     *   summary="Create a participation (admin or team). Points and rank start at 0.",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"event_id"},
     *       @OA\Property(property="event_id", type="integer", example=10),
     *       @OA\Property(
     *         property="user_id", type="integer", example=7,
     *         description="Optional; only admins may register another user. Teams register themselves."
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Created",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Participation created successfully"),
     *       @OA\Property(property="participation", type="object",
     *         @OA\Property(property="id", type="integer", example=55),
     *         @OA\Property(property="event_id", type="integer", example=10),
     *         @OA\Property(property="user_id", type="integer", example=7),
     *         @OA\Property(property="total_points", type="integer", example=0),
     *         @OA\Property(property="rank", type="integer", example=0),
     *         @OA\Property(
     *           property="user", type="object",
     *           @OA\Property(property="id", type="integer", example=7),
     *           @OA\Property(property="name", type="string", example="Quiz Masters"),
     *           @OA\Property(property="email", type="string", example="team@example.com"),
     *           @OA\Property(property="role", type="string", example="team")
     *         ),
     *         @OA\Property(
     *           property="event", type="object",
     *           @OA\Property(property="id", type="integer", example=10),
     *           @OA\Property(property="season_id", type="integer", example=3),
     *           @OA\Property(property="title", type="string", example="Belgrade Pub Quiz #1"),
     *           @OA\Property(property="starts_at", type="string", example="2025-03-10T19:00:00"),
     *           @OA\Property(property="ends_at", type="string", nullable=true, example="2025-03-10T21:00:00"),
     *           @OA\Property(property="status", type="string", example="scheduled")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=409, description="Team already registered for this event"),
     *   @OA\Response(response=422, description="Validation error or registration closed")
     * )
     */
    public function store(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'user_id' => 'sometimes|exists:users,id',
        ]);

        $targetUserId = $user->role === 'admin'
            ? ($validated['user_id'] ?? $user->id)
            : $user->id;

        $event = Event::findOrFail($validated['event_id']);
        if ($event->status !== 'scheduled') {
            return response()->json(['error' => 'Registration closed for this event'], 422);
        }

        $exists = Participation::where('event_id', $event->id)
            ->where('user_id', $targetUserId)
            ->exists();
        if ($exists) {
            return response()->json(['error' => 'Team already registered for this event'], 409);
        }

        $participation = Participation::create([
            'event_id' => $event->id,
            'user_id' => $targetUserId,
            'total_points' => 0,
            'rank'  => 0,
        ]);

        return response()->json([
            'message'       => 'Participation created successfully',
            'participation' => new ParticipationResource($participation->load(['user', 'event'])),
        ]);
    }

    /**
     * @OA\Get(
     *   path="/api/participations/{id}",
     *   tags={"Participations"},
     *   summary="Get a single participation (admin: any, team: only own)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="participation", type="object",
     *         @OA\Property(property="id", type="integer", example=42),
     *         @OA\Property(property="event_id", type="integer", example=10),
     *         @OA\Property(property="user_id", type="integer", example=7),
     *         @OA\Property(property="total_points", type="integer", example=86),
     *         @OA\Property(property="rank", type="integer", nullable=true, example=1),
     *         @OA\Property(
     *           property="user", type="object",
     *           @OA\Property(property="id", type="integer", example=7),
     *           @OA\Property(property="name", type="string", example="Quiz Masters"),
     *           @OA\Property(property="email", type="string", example="team@example.com"),
     *           @OA\Property(property="role", type="string", example="team")
     *         ),
     *         @OA\Property(
     *           property="event", type="object",
     *           @OA\Property(property="id", type="integer", example=10),
     *           @OA\Property(property="season_id", type="integer", example=3),
     *           @OA\Property(property="title", type="string", example="Belgrade Pub Quiz #1"),
     *           @OA\Property(property="starts_at", type="string", example="2025-03-10T19:00:00"),
     *           @OA\Property(property="ends_at", type="string", nullable=true, example="2025-03-10T21:00:00"),
     *           @OA\Property(property="status", type="string", example="completed")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function show(Participation $participation)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        if ($user->role !== 'admin' && $participation->user_id !== $user->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $participation->load(['user', 'event']);

        return response()->json([
            'participation' => new ParticipationResource($participation),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Participation $participation)
    {
        //
    }

    /**
     * @OA\Put(
     *   path="/api/participations/{id}",
     *   tags={"Participations"},
     *   summary="Update scores (admin only): total_points, rank",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\RequestBody(
     *     required=false,
     *     @OA\JsonContent(
     *       @OA\Property(property="total_points", type="integer", minimum=0, example=92),
     *       @OA\Property(property="rank", type="integer", minimum=0, example=1)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Updated",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Participation updated successfully"),
     *       @OA\Property(property="participation", type="object",
     *         @OA\Property(property="id", type="integer", example=42),
     *         @OA\Property(property="event_id", type="integer", example=10),
     *         @OA\Property(property="user_id", type="integer", example=7),
     *         @OA\Property(property="total_points", type="integer", example=92),
     *         @OA\Property(property="rank", type="integer", example=1),
     *         @OA\Property(
     *           property="user", type="object",
     *           @OA\Property(property="id", type="integer", example=7),
     *           @OA\Property(property="name", type="string", example="Quiz Masters"),
     *           @OA\Property(property="email", type="string", example="team@example.com"),
     *           @OA\Property(property="role", type="string", example="team")
     *         ),
     *         @OA\Property(
     *           property="event", type="object",
     *           @OA\Property(property="id", type="integer", example=10),
     *           @OA\Property(property="season_id", type="integer", example=3),
     *           @OA\Property(property="title", type="string", example="Belgrade Pub Quiz #1"),
     *           @OA\Property(property="starts_at", type="string", example="2025-03-10T19:00:00"),
     *           @OA\Property(property="ends_at", type="string", nullable=true, example="2025-03-10T21:00:00"),
     *           @OA\Property(property="status", type="string", example="completed")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=403, description="Only admins can update participations"),
     *   @OA\Response(response=422, description="Validation error or no editable fields provided")
     * )
     */
    public function update(Request $request, Participation $participation)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can update participations'], 403);
        }

        $validated = $request->validate([
            'total_points' => 'sometimes|integer|min:0',
            'rank' => 'sometimes|integer|min:0',
        ]);

        unset($validated['event_id'], $validated['user_id']);

        if (empty($validated)) {
            return response()->json(['error' => 'No editable fields provided'], 422);
        }

        $participation->update($validated);

        return response()->json([
            'message'       => 'Participation updated successfully',
            'participation' => new ParticipationResource($participation->fresh()->load(['user', 'event'])),
        ]);
    }

    /**
     * @OA\Delete(
     *   path="/api/participations/{id}",
     *   tags={"Participations"},
     *   summary="Delete a participation (admin: any, team: only own)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *   @OA\Response(
     *     response=200,
     *     description="Deleted",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", example="Participation deleted successfully")
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthorized"),
     *   @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(Participation $participation)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        if ($user->role !== 'admin' && $participation->user_id !== $user->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $participation->delete();

        return response()->json(['message' => 'Participation deleted successfully']);
    }
}
