<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PerimeterService;
use App\Models\User;
use App\Models\UserScope;
use App\Models\OrganizationalUnit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class PerimeterController extends Controller
{
    protected PerimeterService $perimeterService;

    public function __construct(PerimeterService $perimeterService)
    {
        $this->perimeterService = $perimeterService;
    }

    /**
     * Get current user's accessible organizational units
     */
    public function getMyAccessibleUnits(): JsonResponse
    {
        try {
            $user = auth()->user();
            $units = $this->perimeterService->getUserAccessibleUnits($user);

            return response()->json([
                'success' => true,
                'data' => $units
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving accessible units: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current user's direct scopes
     */
    public function getMyScopes(): JsonResponse
    {
        try {
            $user = auth()->user();
            $scopes = $this->perimeterService->getUserScopes($user);

            return response()->json([
                'success' => true,
                'data' => $scopes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user scopes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's accessible hierarchy levels
     */
    public function getMyAccessibleLevels(): JsonResponse
    {
        try {
            $user = auth()->user();
            $levels = $this->perimeterService->getUserAccessibleLevels($user);

            return response()->json([
                'success' => true,
                'data' => $levels
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving accessible levels: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get users within a specific perimeter
     */
    public function getUsersInPerimeter(Request $request, int $organizationalUnitId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'include_descendants' => 'boolean'
            ]);

            $includeDescendants = $validated['include_descendants'] ?? true;
            $users = $this->perimeterService->getUsersInPerimeter($organizationalUnitId, $includeDescendants);

            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving users in perimeter: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign user to organizational unit perimeter
     */
    public function assignUserToPerimeter(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'organizational_unit_id' => 'required|exists:organizational_units,id',
                'expires_at' => 'nullable|date|after:now',
                'access_type' => 'nullable|string|in:full,read_only,limited',
                'notes' => 'nullable|string|max:500'
            ]);

            $user = User::findOrFail($validated['user_id']);
            $userScope = $this->perimeterService->assignUserToPerimeter(
                $user,
                $validated['organizational_unit_id'],
                array_filter([
                    'expires_at' => $validated['expires_at'] ?? null,
                    'access_type' => $validated['access_type'] ?? 'full',
                    'notes' => $validated['notes'] ?? null
                ])
            );

            return response()->json([
                'success' => true,
                'message' => 'User assigned to perimeter successfully',
                'data' => $userScope
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error assigning user to perimeter: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk assign users to perimeter
     */
    public function bulkAssignUsersToPerimeter(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_ids' => 'required|array|min:1',
                'user_ids.*' => 'exists:users,id',
                'organizational_unit_id' => 'required|exists:organizational_units,id',
                'expires_at' => 'nullable|date|after:now',
                'access_type' => 'nullable|string|in:full,read_only,limited',
                'notes' => 'nullable|string|max:500'
            ]);

            $options = array_filter([
                'expires_at' => $validated['expires_at'] ?? null,
                'access_type' => $validated['access_type'] ?? 'full',
                'notes' => $validated['notes'] ?? null
            ]);

            $results = $this->perimeterService->bulkAssignUsersToPerimeter(
                $validated['user_ids'],
                $validated['organizational_unit_id'],
                $options
            );

            return response()->json([
                'success' => true,
                'message' => 'Bulk assignment completed',
                'data' => $results
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error in bulk assignment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revoke user access to perimeter
     */
    public function revokeUserPerimeter(Request $request, int $userId, int $organizationalUnitId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'nullable|string|max:500'
            ]);

            $user = User::findOrFail($userId);
            $revoked = $this->perimeterService->revokeUserPerimeter(
                $user,
                $organizationalUnitId,
                $validated['reason'] ?? null
            );

            if ($revoked) {
                return response()->json([
                    'success' => true,
                    'message' => 'User access revoked successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not have active access to this perimeter'
                ], 404);
            }

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error revoking user perimeter: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user scope settings
     */
    public function updateUserScope(Request $request, int $userId, int $organizationalUnitId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'expires_at' => 'nullable|date|after:now',
                'access_type' => 'nullable|string|in:full,read_only,limited',
                'notes' => 'nullable|string|max:500'
            ]);

            $user = User::findOrFail($userId);
            $userScope = $this->perimeterService->updateUserScope(
                $user,
                $organizationalUnitId,
                $validated
            );

            return response()->json([
                'success' => true,
                'message' => 'User scope updated successfully',
                'data' => $userScope
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating user scope: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user perimeter assignment history
     */
    public function getUserPerimeterHistory(int $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);
            $history = $this->perimeterService->getUserPerimeterHistory($user);

            return response()->json([
                'success' => true,
                'data' => $history
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving user perimeter history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get users with expiring access
     */
    public function getUsersWithExpiringAccess(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'days_ahead' => 'nullable|integer|min:1|max:365'
            ]);

            $daysAhead = $validated['days_ahead'] ?? 7;
            $users = $this->perimeterService->getUsersWithExpiringAccess($daysAhead);

            return response()->json([
                'success' => true,
                'data' => $users
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving users with expiring access: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extend user scope expiration
     */
    public function extendUserScopeExpiration(Request $request, int $userId, int $organizationalUnitId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'new_expiration_date' => 'required|date|after:now'
            ]);

            $user = User::findOrFail($userId);
            $newExpirationDate = Carbon::parse($validated['new_expiration_date']);
            
            $userScope = $this->perimeterService->extendUserScopeExpiration(
                $user,
                $organizationalUnitId,
                $newExpirationDate
            );

            return response()->json([
                'success' => true,
                'message' => 'User scope expiration extended successfully',
                'data' => $userScope
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error extending user scope expiration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user can access specific unit
     */
    public function canUserAccessUnit(int $userId, int $organizationalUnitId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);
            $canAccess = $this->perimeterService->canUserAccessUnit($user, $organizationalUnitId);

            return response()->json([
                'success' => true,
                'data' => [
                    'can_access' => $canAccess,
                    'user_id' => $userId,
                    'organizational_unit_id' => $organizationalUnitId
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error checking user access: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get perimeter statistics
     */
    public function getPerimeterStatistics(): JsonResponse
    {
        try {
            $statistics = $this->perimeterService->getPerimeterStatistics();

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving perimeter statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate user perimeter assignments
     */
    public function validateUserPerimeters(int $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);
            $issues = $this->perimeterService->validateUserPerimeters($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_valid' => empty($issues),
                    'issues_count' => count($issues),
                    'issues' => $issues
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error validating user perimeters: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search users for perimeter assignment
     */
    public function searchUsersForAssignment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'query' => 'required|string|min:2',
                'exclude_assigned' => 'sometimes|boolean',
                'organizational_unit_id' => 'sometimes|exists:organizational_units,id',
                'role' => 'sometimes|string',
                'limit' => 'sometimes|integer|min:1|max:100'
            ]);

            $query = User::with(['roles', 'userScopes.organizationalUnit'])
                ->where(function ($q) use ($validated) {
                    $q->where('name', 'like', '%' . $validated['query'] . '%')
                      ->orWhere('email', 'like', '%' . $validated['query'] . '%');
                });

            if (isset($validated['role'])) {
                $query->whereHas('roles', function ($q) use ($validated) {
                    $q->where('name', $validated['role']);
                });
            }

            if (isset($validated['exclude_assigned'], $validated['organizational_unit_id']) 
                && $validated['exclude_assigned']) {
                $query->whereDoesntHave('userScopes', function ($q) use ($validated) {
                    $q->where('organizational_unit_id', $validated['organizational_unit_id'])
                      ->where('is_active', true)
                      ->where(function ($subQ) {
                          $subQ->whereNull('expires_at')
                               ->orWhere('expires_at', '>', now());
                      });
                });
            }

            $limit = $validated['limit'] ?? 20;
            $users = $query->limit($limit)->get();

            return response()->json([
                'success' => true,
                'data' => $users
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error searching users: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user scope details
     */
    public function getUserScope(int $userId, int $organizationalUnitId): JsonResponse
    {
        try {
            $userScope = UserScope::with([
                'user',
                'organizationalUnit.levelDefinition',
                'assignedBy',
                'revokedBy'
            ])
            ->where('user_id', $userId)
            ->where('organizational_unit_id', $organizationalUnitId)
            ->where('is_active', true)
            ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $userScope
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User scope not found or inactive'
            ], 404);
        }
    }

    /**
     * Clear perimeter cache
     */
    public function clearCache(): JsonResponse
    {
        try {
            $this->perimeterService->clearAllCache();

            return response()->json([
                'success' => true,
                'message' => 'Perimeter cache cleared successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error clearing cache: ' . $e->getMessage()
            ], 500);
        }
    }
}