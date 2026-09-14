<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProcedureType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProcedureController extends Controller
{
    private function serialize(ProcedureType $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'description' => $p->description,
            'requirements' => $p->requirements ?? [],
            'baseCost' => $p->base_cost,
            'estimatedDays' => $p->estimated_days,
            'isActive' => (bool) $p->is_active,
            'createdAt' => $p->created_at?->toISOString(),
        ];
    }

    #[OA\Get(
        path: '/procedures',
        summary: 'Obtener todos los trámites activos',
        tags: ['Procedures'],
        responses: [
            new OA\Response(response: 200, description: 'Lista de trámites'),
        ]
    )]
    public function getAll(): JsonResponse
    {
        $rows = ProcedureType::where('is_active', true)->orderBy('name')->get();

        return response()->json([
            'procedures' => $rows->map(fn ($p) => $this->serialize($p)),
        ]);
    }

    #[OA\Get(
        path: '/procedures/{slug}',
        summary: 'Obtener un trámite por slug o ID',
        tags: ['Procedures'],
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true,
                schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detalle del trámite'),
            new OA\Response(response: 404, description: 'Trámite no encontrado'),
        ]
    )]
    public function getBySlug(string $slug): JsonResponse
    {
        $procedure = ctype_digit($slug)
            ? ProcedureType::find((int) $slug)
            : ProcedureType::where('slug', $slug)->first();

        if (! $procedure) {
            return response()->json(['error' => 'Trámite no encontrado'], 404);
        }

        return response()->json(['procedure' => $this->serialize($procedure)]);
    }

    #[OA\Post(
        path: '/procedures',
        summary: 'Crear un nuevo tipo de trámite',
        tags: ['Procedures'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'slug'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'requirements', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'baseCost', type: 'number'),
                    new OA\Property(property: 'estimatedDays', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Trámite creado exitosamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 409, description: 'El slug ya está registrado'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'unique:procedure_types,slug'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'array'],
            'baseCost' => ['nullable', 'numeric'],
            'estimatedDays' => ['nullable', 'integer'],
        ]);

        $procedure = ProcedureType::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? '',
            'requirements' => $data['requirements'] ?? [],
            'base_cost' => $data['baseCost'] ?? 0,
            'estimated_days' => $data['estimatedDays'] ?? 0,
        ]);

        AuditLog::record($request->user()->id, 'create_procedure_type', 'procedure_type', $procedure->id, [
            'name' => $data['name'],
            'slug' => $data['slug'],
        ]);

        return response()->json(['message' => 'Trámite creado exitosamente', 'id' => $procedure->id], 201);
    }

    #[OA\Put(
        path: '/procedures/{id}',
        summary: 'Actualizar un tipo de trámite',
        tags: ['Procedures'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'requirements', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'baseCost', type: 'number'),
                    new OA\Property(property: 'estimatedDays', type: 'integer'),
                    new OA\Property(property: 'isActive', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Trámite actualizado exitosamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permisos para actualizar'),
            new OA\Response(response: 404, description: 'Trámite no encontrado'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'array'],
            'baseCost' => ['nullable', 'numeric'],
            'estimatedDays' => ['nullable', 'integer'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        $procedure = ProcedureType::findOrFail($id);

        $update = array_filter([
            'name' => $data['name'] ?? null,
            'slug' => $data['slug'] ?? null,
            'description' => array_key_exists('description', $data) ? ($data['description'] ?? '') : null,
            'requirements' => array_key_exists('requirements', $data) ? ($data['requirements'] ?? []) : null,
            'base_cost' => $data['baseCost'] ?? null,
            'estimated_days' => $data['estimatedDays'] ?? null,
            'is_active' => array_key_exists('isActive', $data) ? (int) $data['isActive'] : null,
        ], fn ($v) => ! is_null($v));

        $procedure->update($update);

        AuditLog::record($request->user()->id, 'update_procedure_type', 'procedure_type', $id, $request->all());

        return response()->json(['message' => 'Trámite actualizado exitosamente']);
    }

    #[OA\Delete(
        path: '/procedures/{id}',
        summary: 'Desactivar un tipo de trámite',
        tags: ['Procedures'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Trámite desactivado exitosamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 404, description: 'Trámite no encontrado'),
        ]
    )]
    public function remove(Request $request, int $id): JsonResponse
    {
        ProcedureType::where('id', $id)->update(['is_active' => false]);

        AuditLog::record($request->user()->id, 'deactivate_procedure_type', 'procedure_type', $id);

        return response()->json(['message' => 'Trámite desactivado exitosamente']);
    }
}
