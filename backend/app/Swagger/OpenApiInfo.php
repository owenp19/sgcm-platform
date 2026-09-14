<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'SGCM API',
    description: 'API REST del sistema SGCM (Cámara de Comercio). Autenticación vía JWT Bearer Token.'
)]
#[OA\Server(
    url: 'http://127.0.0.1:8000/api',
    description: 'Servidor local de desarrollo'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Ingrese el token JWT obtenido en /auth/login'
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'message', type: 'string', example: 'Mensaje de error'),
    ],
    type: 'object'
)]
class OpenApiInfo
{
}
