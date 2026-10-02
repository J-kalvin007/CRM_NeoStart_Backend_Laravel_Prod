<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PerimeterService;
use App\Models\User;
use App\Models\UserScope;
use App\Models\OrganizationalUnit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserScopeController extends Controller
{
    protected PerimeterService $perimeterService;

    public function __construct(PerimeterService $perimeterService)
    {
        $this->perimeterService = $perimeterService;
    }

    /**
     * Get user's organizational scopes
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user_id ? User::findOrFail($request->user_id) : Auth::user();
        
        // Check if current user can view this user's scopes
        if ($user->id !== Auth::id() && !$this->perimeterService->canManageUsers(Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view user scopes.',
            ], 403);
        }

        $scopes = $this->perimeterService->getUserScopes($user, $request->boolean('include_expired', false));
        $primaryScope = $this->perimeterService->getPrimaryScope($user);

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'scopes' => $scopes,
                'primary_scope' => $primaryScope,
                'total_count' => $scopes->count(),
            ],
        ]);
    }

    /**
     * Assign organizational scope to user
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|uuid|exists:users,id',
            'organizational_unit_id' => 'required|uuid|exists:organizational_units,id',
            'is_primary' => 'boolean',
            'permissions' => 'array',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $currentUser = Auth::user();

        // Validate the assignment
        $validation = $this->perimeterService->validateScopeAssignment(
            $currentUser,
            $user,
            $validated['organizational_unit_id']
        );

        if (!$validation['allowed']) {
            return response()->json([
                'success' => false,
                'message' => 'Scope assignment not allowed.',
                'errors' => $validation['errors'],
            ], 403);
        }

        try {
            $scope = $this->perimeterService->assignUserToPerimeter($user, $validated['organizational_unit_id'], [
                'is_primary' => $validated['is_primary'] ?? false,
                'permissions' => $validated['permissions'] ?? [],
                'expires_at' => $validated['expires_at'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Organizational scope assigned successfully.',
                'data' => $scope->load(['organizationalUnit.levelDefinition']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign organizational scope.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user scope
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $scope = UserScope::findOrFail($id);
        $currentUser = Auth::user();

        // Check permissions
        if ($scope->user_id !== $currentUser->id && !$this->perimeterService->canManageUsers($currentUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this scope.',
            ], 403);
        }

        $validated = $request->validate([
            'is_primary' => 'boolean',
            'permissions' => 'array',
            'expires_at' => 'nullable|date|after:now',
            'is_active' => 'boolean',
        ]);

        try {
            $scope->update($validated);

            if (isset($validated['is_primary']) && $validated['is_primary']) {
                $this->perimeterService->setPrimaryScope($scope->user, $scope->organizational_unit_id);
            }

            return response()->json([
                'success' => true,
                'message' => 'User scope updated successfully.',
                'data' => $scope->fresh(['organizationalUnit.levelDefinition']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user scope.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove organizational scope from user
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $scope = UserScope::findOrFail($id);
        $currentUser = Auth::user();

        // Check permissions
        if ($scope->user_id !== $currentUser->id && !$this->perimeterService->canManageUsers($currentUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to remove this scope.',
            ], 403);
        }

        try {
            $removed = $this->perimeterService->revokeUserPerimeter(
                User::findOrFail($scope->user_id),
                $scope->organizational_unit_id
            );

            if ($removed) {
                return response()->json([
                    'success' => true,
                    'message' => 'Organizational scope removed successfully.',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to remove organizational scope.',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove organizational scope.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set primary scope for user
     */
    public function setPrimary(Request $request, string $id): JsonResponse
    {
        $scope = UserScope::findOrFail($id);
        $currentUser = Auth::user();

        // Check permissions
        if ($scope->user_id !== $currentUser->id && !$this->perimeterService->canManageUsers($currentUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to set primary scope.',
            ], 403);
        }

        try {
            $success = $this->perimeterService->setPrimaryScope(
                User::findOrFail($scope->user_id),
                $scope->organizational_unit_id
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Primary scope set successfully.',
                    'data' => $scope->fresh(['organizationalUnit.levelDefinition']),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to set primary scope.',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set primary scope.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get accessible organizational units for current user
     */
    public function getAccessibleUnits(Request $request): JsonResponse
    {
        $user = Auth::user();
        $configId = $request->query('config_id');

        $query = \App\Models\OrganizationalUnit::withinUserPerimeter($user);
        
        if ($configId) {
            $query->where('organization_config_id', $configId);
        }

        $accessibleUnits = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'units' => $accessibleUnits,
                'total_count' => $accessibleUnits->count(),
            ],
        ]);
    }

    /**
     * Get accessible users within current user's perimeter
     */
    public function getAccessibleUsers(Request $request): JsonResponse
    {
        $user = Auth::user();
        $accessibleUsers = $this->perimeterService->getAccessibleUsers($user);

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $accessibleUsers,
                'total_count' => $accessibleUsers->count(),
            ],
        ]);
    }

    /**
     * Get manageable units for current user
     */
    public function getManageableUnits(Request $request): JsonResponse
    {
        $user = Auth::user();
        $manageableUnits = $this->perimeterService->getManageableUnits($user);

        return response()->json([
            'success' => true,
            'data' => [
                'units' => $manageableUnits,
                'total_count' => $manageableUnits->count(),
                'can_manage_users' => $this->perimeterService->canManageUsers($user),
                'can_create_units' => $this->perimeterService->canCreateUnits($user),
                'can_edit_units' => $this->perimeterService->canEditUnits($user),
                'can_delete_units' => $this->perimeterService->canDeleteUnits($user),
            ],
        ]);
    }

    /**
     * Get hierarchy path for an organizational unit
     */
    public function getHierarchyPath(string $unitId): JsonResponse
    {
        $unit = OrganizationalUnit::findOrFail($unitId);
        
        // Check if user can access this unit
        if (!$this->perimeterService->canAccessUnit(Auth::user(), $unitId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to access this organizational unit.',
            ], 403);
        }

        $path = $this->perimeterService->getHierarchyPath($unitId);

        return response()->json([
            'success' => true,
            'data' => [
                'unit' => $unit,
                'path' => $path,
                'depth' => $path->count(),
            ],
        ]);
    }
}