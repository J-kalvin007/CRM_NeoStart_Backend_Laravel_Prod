<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "CRM API Documentation",
    description: "L5 Swagger OpenApi description for the CRM Backend",
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: "Main API Server"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    name: "bearerAuth",
    in: "header",
    bearerFormat: "JWT",
    scheme: "bearer"
)]
abstract class Controller
{
    //
}
