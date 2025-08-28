<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *   name="Trivia",
 *   description="Fetch public trivia questions (Open Trivia DB)"
 * )
 */
class TriviaController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/trivia",
     *   tags={"Trivia"},
     *   summary="Fetch questions from Open Trivia DB (admin only)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="amount", in="query",
     *     description="Number of questions (1–50). Default 10.",
     *     @OA\Schema(type="integer", minimum=1, maximum=50), example=10
     *   ),
     *   @OA\Parameter(
     *     name="difficulty", in="query",
     *     @OA\Schema(type="string", enum={"easy","medium","hard"}), example="medium"
     *   ),
     *   @OA\Parameter(
     *     name="type", in="query",
     *     @OA\Schema(type="string", enum={"multiple","boolean"}), example="multiple"
     *   ),
     *   @OA\Parameter(
     *     name="category", in="query",
     *     description="OpenTDB numeric category id",
     *     @OA\Schema(type="integer", minimum=1), example=9
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="OK",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="source", type="string", example="Open Trivia DB"),
     *       @OA\Property(
     *         property="params", type="object",
     *         @OA\Property(property="amount", type="integer", example=10),
     *         @OA\Property(property="difficulty", type="string", nullable=true, example="medium"),
     *         @OA\Property(property="type", type="string", nullable=true, example="multiple"),
     *         @OA\Property(property="category", type="integer", nullable=true, example=9)
     *       ),
     *       @OA\Property(property="count", type="integer", example=10),
     *       @OA\Property(
     *         property="questions",
     *         type="array",
     *         @OA\Items(type="object",
     *           @OA\Property(property="uid", type="string", format="uuid", example="2b4a1af0-7a4f-4e2a-8c2b-9a7f6b3b2f1d"),
     *           @OA\Property(property="category", type="string", example="General Knowledge"),
     *           @OA\Property(property="type", type="string", example="multiple"),
     *           @OA\Property(property="difficulty", type="string", example="medium"),
     *           @OA\Property(property="question", type="string", example="What is the capital of Serbia?"),
     *           @OA\Property(property="answers", type="array", @OA\Items(type="string"), example={"Belgrade","Novi Sad","Niš","Kragujevac"}),
     *           @OA\Property(property="correct", type="string", example="Belgrade")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=403, description="Only admins can fetch questions"),
     *   @OA\Response(response=404, description="No questions found from the public API."),
     *   @OA\Response(response=502, description="Public questions API not reachable")
     * )
     */
    public function index(Request $request)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['error' => 'Only admins can fetch questions'], 403);
        }

        $validated = $request->validate([
            'amount'     => 'sometimes|integer|min:1|max:50',
            'difficulty' => 'sometimes|in:easy,medium,hard',
            'type'       => 'sometimes|in:multiple,boolean',
            'category'   => 'sometimes|integer|min:1',
        ]);

        $params = [
            'amount' => (int) ($validated['amount'] ?? 10),
        ];
        if (isset($validated['difficulty'])) $params['difficulty'] = $validated['difficulty'];
        if (isset($validated['type']))       $params['type']       = $validated['type'];
        if (isset($validated['category']))   $params['category']   = $validated['category'];

        $res = Http::timeout(10)->get('https://opentdb.com/api.php', $params);

        if (!$res->ok()) {
            return response()->json(['error' => 'Public questions API not reachable'], 502);
        }

        $json = $res->json();
        if (($json['response_code'] ?? 1) !== 0 || empty($json['results'])) {
            return response()->json('No questions found from the public API.', 404);
        }

        $decode = fn($s) => html_entity_decode($s ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $questions = collect($json['results'])->map(function ($q) use ($decode) {
            $answers = collect(array_merge([$q['correct_answer'] ?? ''], $q['incorrect_answers'] ?? []))
                ->map($decode)
                ->shuffle()
                ->values()
                ->all();

            return [
                'uid'        => (string) Str::uuid(),
                'category'   => $decode($q['category'] ?? null),
                'type'       => $q['type'] ?? null,
                'difficulty' => $q['difficulty'] ?? null,
                'question'   => $decode($q['question'] ?? null),
                'answers'    => $answers,
                'correct'    => $decode($q['correct_answer'] ?? null),
            ];
        })->values();

        return response()->json([
            'source'    => 'Open Trivia DB',
            'params'    => $params,
            'count'     => $questions->count(),
            'questions' => $questions,
        ]);
    }
}
