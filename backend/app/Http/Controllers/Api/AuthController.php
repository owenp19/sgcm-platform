<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendMailJob;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    private function jwtTtl(): int
    {
        // Equivalente al '7d' del backend Node (en minutos)
        return (int) config('jwt.ttl', 10080);
    }

    private function rolesOf(User $user): array
    {
        $roles = $user->roles->pluck('name')->all();

        return $roles ?: ['ciudadano'];
    }

    #[OA\Post(
        path: '/auth/register',
        summary: 'Registrar un nuevo usuario ciudadano',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['firstName', 'lastName', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'firstName', type: 'string', example: 'Owen'),
                    new OA\Property(property: 'lastName', type: 'string', example: 'Pérez'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 6),
                    new OA\Property(property: 'documentType', type: 'string', example: 'CC'),
                    new OA\Property(property: 'documentNumber', type: 'string', example: '1020304050'),
                    new OA\Property(property: 'phone', type: 'string', example: '3001234567'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Usuario registrado'),
            new OA\Response(response: 409, description: 'El correo ya está registrado',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'firstName' => ['required', 'string', 'min:2', 'max:100'],
            'lastName' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'documentType' => ['nullable', 'string', 'max:10'],
            'documentNumber' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        if (User::where('email', $data['email'])->exists()) {
            return response()->json(['error' => 'El correo ya está registrado'], 409);
        }

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'first_name' => $data['firstName'],
                'last_name' => $data['lastName'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'document_type' => $data['documentType'] ?? null,
                'document_number' => $data['documentNumber'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);

            $role = \App\Models\Role::where('name', 'ciudadano')->first();
            if ($role) {
                $user->roles()->attach($role->id);
            }

            return $user;
        });

        $token = JWTAuth::claims(['email' => $user->email])->fromUser($user);

        AuditLog::record($user->id, 'register', 'user', $user->id, ['email' => $user->email]);

        return response()->json([
            'message' => 'Usuario registrado exitosamente',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'email' => $user->email,
                'phone' => '',
                'documentType' => '',
                'documentNumber' => '',
                'photoUrl' => '',
                'roles' => ['ciudadano'],
            ],
        ], 201);
    }

    #[OA\Post(
        path: '/auth/login',
        summary: 'Iniciar sesión y obtener token JWT',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'owen@sgcm.app'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'owen12345'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login exitoso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'user', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Credenciales inválidas',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::with('roles')->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['error' => 'Correo o contraseña incorrectos'], 401);
        }

        $token = JWTAuth::claims(['email' => $user->email])->fromUser($user);

        AuditLog::record($user->id, 'login', 'user', $user->id, ['email' => $user->email]);

        return response()->json([
            'message' => 'Inicio de sesión exitoso',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'documentType' => $user->document_type ?? '',
                'documentNumber' => $user->document_number ?? '',
                'photoUrl' => $user->photo_url ?? '',
                'roles' => $this->rolesOf($user),
            ],
        ]);
    }

    #[OA\Get(
        path: '/auth/profile',
        summary: 'Obtener el perfil del usuario autenticado',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Perfil del usuario'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function getProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load('roles');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'email' => $user->email,
                'documentType' => $user->document_type,
                'documentNumber' => $user->document_number,
                'phone' => $user->phone ?? '',
                'photoUrl' => $user->photo_url ?? '',
                'roles' => $this->rolesOf($user),
                'createdAt' => $user->created_at?->toISOString(),
            ],
        ]);
    }

    #[OA\Put(
        path: '/auth/profile',
        summary: 'Actualizar el perfil del usuario autenticado',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['firstName', 'lastName'],
                properties: [
                    new OA\Property(property: 'firstName', type: 'string', minLength: 2, maxLength: 100),
                    new OA\Property(property: 'lastName', type: 'string', minLength: 2, maxLength: 100),
                    new OA\Property(property: 'documentType', type: 'string', nullable: true, maxLength: 10),
                    new OA\Property(property: 'documentNumber', type: 'string', nullable: true, maxLength: 50),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, maxLength: 30),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Perfil actualizado exitosamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'firstName' => ['required', 'string', 'min:2', 'max:100'],
            'lastName' => ['required', 'string', 'min:2', 'max:100'],
            'documentType' => ['nullable', 'string', 'max:10'],
            'documentNumber' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update([
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'document_type' => $data['documentType'] ?? null,
            'document_number' => $data['documentNumber'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        return response()->json([
            'message' => 'Perfil actualizado exitosamente',
            'user' => [
                'id' => $user->id,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'email' => $user->email,
                'documentType' => $user->document_type,
                'documentNumber' => $user->document_number,
                'phone' => $user->phone ?? '',
                'photoUrl' => $user->photo_url ?? '',
            ],
        ]);
    }

    #[OA\Put(
        path: '/auth/photo',
        summary: 'Actualizar la foto de perfil del usuario autenticado',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['photoUrl'],
                properties: [
                    new OA\Property(property: 'photoUrl', type: 'string', format: 'uri'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Foto actualizada exitosamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function uploadPhoto(Request $request): JsonResponse
    {
        $data = $request->validate([
            'photoUrl' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->update(['photo_url' => $data['photoUrl']]);

        return response()->json([
            'message' => 'Foto actualizada exitosamente',
            'photoUrl' => $data['photoUrl'],
        ]);
    }

    #[OA\Post(
        path: '/auth/forgot-password',
        summary: 'Solicitar recuperación de contraseña',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Correo de recuperación enviado si el usuario existe'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Si el correo existe, recibirás un enlace de recuperación']);
        }

        $resetToken = Str::uuid()->toString();

        $user->forceFill([
            'reset_token' => $resetToken,
            'reset_token_expires' => now()->addHour(),
        ])->save();

        $frontend = rtrim((string) config('services.frontend_url', 'http://localhost:8100'), '/');
        $resetUrl = $frontend.'/reset-password?token='.$resetToken;

        \Illuminate\Support\Facades\Log::info("[Password Reset] Link para {$user->email}: {$resetUrl}");

        if (config('mail.mailers.smtp.host') && config('mail.from.address')) {
            // Envío asíncrono: no bloquea la respuesta HTTP del usuario.
            SendMailJob::dispatch(
                $user->email,
                'Recuperación de contraseña - SGCM',
                '<p>Has solicitado recuperar tu contraseña.</p>'
                .'<p>Haz clic en el siguiente enlace para restablecerla:</p>'
                ."<p><a href=\"{$resetUrl}\">{$resetUrl}</a></p>"
                .'<p>Este enlace expira en 1 hora.</p>'
                .'<p>Si no solicitaste este cambio, ignora este mensaje.</p>'
            );
        }

        return response()->json(['message' => 'Si el correo existe, recibirás un enlace de recuperación']);
    }

    #[OA\Post(
        path: '/auth/reset-password',
        summary: 'Restablecer la contraseña con el token recibido',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'password'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', description: 'Token de recuperación'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 6),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Contraseña restablecida exitosamente'),
            new OA\Response(response: 400, description: 'Token inválido o expirado'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::where('reset_token', $data['token'])
            ->where('reset_token_expires', '>', now())
            ->first();

        if (! $user) {
            return response()->json(['error' => 'Token inválido o expirado'], 400);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'reset_token' => null,
            'reset_token_expires' => null,
        ])->save();

        return response()->json(['message' => 'Contraseña restablecida exitosamente']);
    }

    #[OA\Put(
        path: '/auth/change-password',
        summary: 'Cambiar la contraseña del usuario autenticado',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['currentPassword', 'newPassword'],
                properties: [
                    new OA\Property(property: 'currentPassword', type: 'string', format: 'password'),
                    new OA\Property(property: 'newPassword', type: 'string', format: 'password', minLength: 6, description: 'Debe ser distinta de la actual'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Contraseña actualizada exitosamente'),
            new OA\Response(response: 401, description: 'No autenticado o contraseña actual incorrecta'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:6', 'different:currentPassword'],
        ]);

        if (! Hash::check($data['currentPassword'], $user->password)) {
            return response()->json(['error' => 'La contraseña actual es incorrecta'], 401);
        }

        $user->update(['password' => Hash::make($data['newPassword'])]);

        AuditLog::record($user->id, 'change_password', 'user', $user->id);

        return response()->json(['message' => 'Contraseña actualizada exitosamente']);
    }

    #[OA\Put(
        path: '/auth/change-email',
        summary: 'Cambiar el correo del usuario autenticado',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['newEmail', 'currentPassword'],
                properties: [
                    new OA\Property(property: 'newEmail', type: 'string', format: 'email'),
                    new OA\Property(property: 'currentPassword', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Correo actualizado exitosamente'),
            new OA\Response(response: 400, description: 'El nuevo correo es igual al actual'),
            new OA\Response(response: 401, description: 'No autenticado o contraseña incorrecta'),
            new OA\Response(response: 409, description: 'El correo ya está registrado en otra cuenta'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function changeEmail(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'newEmail' => ['required', 'email'],
            'currentPassword' => ['required', 'string'],
        ]);

        if (! Hash::check($data['currentPassword'], $user->password)) {
            return response()->json(['error' => 'La contraseña es incorrecta'], 401);
        }

        if ($user->email === $data['newEmail']) {
            return response()->json(['error' => 'El nuevo correo es igual al actual'], 400);
        }

        if (User::where('email', $data['newEmail'])->exists()) {
            return response()->json(['error' => 'El correo ya está registrado en otra cuenta'], 409);
        }

        $oldEmail = $user->email;
        $user->update(['email' => $data['newEmail']]);

        AuditLog::record($user->id, 'change_email', 'user', $user->id, [
            'oldEmail' => $oldEmail,
            'newEmail' => $data['newEmail'],
        ]);

        return response()->json([
            'message' => 'Correo actualizado exitosamente',
            'email' => $data['newEmail'],
        ]);
    }

    #[OA\Get(
        path: '/auth/users',
        summary: 'Listar usuarios con paginación',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', description: 'Número de página', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit', in: 'query', description: 'Cantidad de usuarios por página', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de usuarios con paginación'),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function listUsers(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', '1'));
        $limit = max(1, (int) $request->query('limit', '20'));

        $total = User::count();
        $users = User::with('roles')
            ->orderByDesc('created_at')
            ->forPage($page, $limit)
            ->get();

        return response()->json([
            'users' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'firstName' => $u->first_name,
                'lastName' => $u->last_name,
                'email' => $u->email,
                'documentType' => $u->document_type,
                'documentNumber' => $u->document_number,
                'phone' => $u->phone,
                'photoUrl' => $u->photo_url,
                'roles' => $u->roles->isNotEmpty() ? $u->roles->pluck('name')->all() : ['ciudadano'],
                'createdAt' => $u->created_at?->toISOString(),
            ]),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    #[OA\Put(
        path: '/auth/users/{userId}/roles',
        summary: 'Actualizar los roles de un usuario',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', description: 'ID del usuario', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['roles'],
                properties: [
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Roles actualizados exitosamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 404, description: 'Usuario no encontrado'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function updateUserRoles(Request $request, int $userId): JsonResponse
    {
        $data = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string'],
        ]);

        $user = User::findOrFail($userId);

        DB::transaction(function () use ($user, $data) {
            $user->roles()->detach();

            // Consulta única (evita N+1) de los roles solicitados que sí existen.
            $existing = \App\Models\Role::whereIn('name', $data['roles'])->pluck('id');
            if ($existing->isNotEmpty()) {
                $user->roles()->attach($existing->all());
            }
        });

        AuditLog::record($request->user()->id, 'update_roles', 'user', $userId, ['roles' => $data['roles']]);

        return response()->json(['message' => 'Roles actualizados exitosamente']);
    }

    #[OA\Delete(
        path: '/auth/users/{userId}',
        summary: 'Eliminar un usuario',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', description: 'ID del usuario', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Usuario eliminado exitosamente'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 404, description: 'Usuario no encontrado'),
            new OA\Response(response: 422, description: 'No se puede eliminar la propia cuenta o al último administrador'),
        ]
    )]
    public function deleteUser(Request $request, int $userId): JsonResponse
    {
        $authUser = $request->user();

        if ($authUser->id === $userId) {
            return response()->json(['error' => 'No puedes eliminar tu propia cuenta'], 422);
        }

        $user = User::findOrFail($userId);

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $isTargetAdmin = DB::table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $adminRoleId)
            ->exists();

        if ($isTargetAdmin) {
            $adminCount = DB::table('users')
                ->whereExists(function ($query) use ($adminRoleId) {
                    $query->select(DB::raw(1))
                        ->from('user_roles')
                        ->whereColumn('user_roles.user_id', 'users.id')
                        ->where('user_roles.role_id', $adminRoleId);
                })
                ->count();

            if ($adminCount <= 1) {
                return response()->json(['error' => 'No se puede eliminar el último administrador del sistema'], 422);
            }
        }

        DB::transaction(function () use ($user) {
            $user->roles()->detach();
            $user->delete();
        });

        AuditLog::record($authUser->id, 'delete_user', 'user', $userId);

        return response()->json(['message' => 'Usuario eliminado exitosamente']);
    }

    #[OA\Get(
        path: '/auth/biometric-preference',
        summary: 'Obtener la preferencia biométrica del usuario autenticado',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Preferencia biométrica del usuario',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'biometricEnabled', type: 'boolean'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function getBiometricPreference(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $enabled = $user->preference()->value('biometric_enabled') ?? false;

        return response()->json(['biometricEnabled' => (bool) $enabled]);
    }

    #[OA\Put(
        path: '/auth/biometric-preference',
        summary: 'Activar o desactivar la preferencia biométrica del usuario autenticado',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['enabled'],
                properties: [
                    new OA\Property(property: 'enabled', type: 'boolean', description: 'True para activar el acceso biométrico'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Preferencia guardada'),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 422, description: 'Datos de validación incorrectos'),
        ]
    )]
    public function setBiometricPreference(Request $request): JsonResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        /** @var User $user */
        $user = $request->user();

        UserPreference::updateOrCreate(
            ['user_id' => $user->id],
            ['biometric_enabled' => $data['enabled'], 'updated_at' => now()]
        );

        AuditLog::record($user->id, 'update_biometric_pref', 'user', $user->id, ['enabled' => $data['enabled']]);

        return response()->json([
            'message' => 'Preferencia guardada',
            'biometricEnabled' => $data['enabled'],
        ]);
    }
}
