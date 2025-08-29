<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ParticipationController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\TriviaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [LoginController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

Route::get('/seasons', [SeasonController::class, 'index']);
Route::get('/seasons/{season}/board', [SeasonController::class, 'board']);
Route::get('/seasons/{season}', [SeasonController::class, 'show']);

Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event}/board', [EventController::class, 'board']);
Route::get('/events/{event}', [EventController::class, 'show']);


Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('/logout', [LoginController::class, 'logout']);

    Route::post('/seasons', [SeasonController::class, 'store']);
    Route::put('/seasons/{season}', [SeasonController::class, 'update']);
    Route::delete('/seasons/{season}', [SeasonController::class, 'destroy']);

    Route::resource('events', EventController::class)->except(['index', 'show']);

    Route::get('/trivia', [TriviaController::class, 'index']);

    Route::resource('participations', ParticipationController::class);
});
