<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRolePermission
{
    /**
     * Route-name prefix -> permissions-matrix resource key. Only these
     * resources are gated — agencies, zones and the calendar are reference
     * data / read views, not part of the Roles form's permission matrix.
     */
    protected array $resources = [
        'users' => 'users',
        'roles' => 'roles',
        'companies' => 'companies',
    ];

    /**
     * apiResource action name -> permission action.
     */
    protected array $actions = [
        'index' => 'read',
        'show' => 'read',
        'store' => 'create',
        'update' => 'update',
        'destroy' => 'delete',
    ];

    protected array $resourceLabels = [
        'users' => 'les employés',
        'roles' => 'les rôles',
        'companies' => 'les compagnies',
    ];

    protected array $actionLabels = [
        'read' => 'consulter',
        'create' => 'créer',
        'update' => 'modifier',
        'delete' => 'supprimer',
    ];

    public function handle(Request $request, Closure $next)
    {
        $routeName = $request->route()?->getName();

        if (!$routeName || !str_contains($routeName, '.')) {
            return $next($request);
        }

        [$routeResource, $routeAction] = explode('.', $routeName, 2);

        if (!isset($this->resources[$routeResource]) || !isset($this->actions[$routeAction])) {
            return $next($request);
        }

        $role = $request->user()?->role;

        if (!$role) {
            return response()->json([
                'message' => 'Votre compte n\'a aucun rôle assigné et ne peut pas effectuer cette action.',
            ], 403);
        }

        $resource = $this->resources[$routeResource];
        $action = $this->actions[$routeAction];

        if (!$role->canDo($resource, $action)) {
            $actionLabel = $this->actionLabels[$action] ?? $action;
            $resourceLabel = $this->resourceLabels[$resource] ?? $resource;
            return response()->json([
                'message' => "Votre rôle n'a pas la permission de {$actionLabel} {$resourceLabel}.",
            ], 403);
        }

        return $next($request);
    }
}
