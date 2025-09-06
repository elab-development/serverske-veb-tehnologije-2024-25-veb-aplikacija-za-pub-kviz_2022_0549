<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="Your API",
 *     version="1.0.0",
 *     description="API documentation for Pub Quiz application."
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local server"
 * )
 */
class ApiDoc extends Controller {}
