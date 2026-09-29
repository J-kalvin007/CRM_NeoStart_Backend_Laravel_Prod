<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HierarchyConfigService;
use App\Models\OrganizationalUnit;
use App\Models\HierarchyLevelDefinition;
use App\Models\OrganizationConfig;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class HierarchyController extends Controller
{
    protected HierarchyConfigService $hierarchyService;

    public function __construct(HierarchyConfigService $hierarchyService)
    {
        $this->hierarchyService = $hierarchyService;
    }

    /**
     * Get the active organization configuration
     */
    public function getConfig(): JsonResponse
    {
        try {
            $config = $this->hierarchyService->getActiveConfig();
            
            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active organization configuration found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'config' => $config->load('levelDefinitions'),
                    'levels' => $this->hierarchyService->getHierarchyLevels(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all hierarchy levels
     */
    public function getLevels(): JsonResponse
    {
        try {
            $levels = $this->hierarchyService->getHierarchyLevels();
            
            return response()->json([
                'success' => true,
                'data' => $levels
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving hierarchy levels: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get hierarchy tree structure
     */
    public function getTree(Request $request): JsonResponse
    {
        try {
            $parentId = $request->query('parent_id');
            $tree = $this->hierarchyService->getHierarchyTree($parentId);
            
            return response()->json([
                'success' => true,
                'data' => $tree
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving hierarchy tree: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get organizational unit details
     */
    public function getUnit(int $id): JsonResponse
    {
        try {
            $unit = OrganizationalUnit::with([
                'levelDefinition',
                'organizationConfig',
                'parent.levelDefinition',
                'children.levelDefinition',
                'userScopes.user'
            ])->findOrFail($id);

            $path = $this->hierarchyService->getUnitPath($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'unit' => $unit,
                    'path' => $path,
                    'descendants_count' => $unit->getDescendants()->count(),
                    'users_count' => $unit->userScopes()->where('is_active', true)->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving organizational unit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new organizational unit
     */
    public function createUnit(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'nullable|string|max:50|unique:organizational_units,code',
                'description' => 'nullable|string|max:1000',
                'parent_id' => 'nullable|exists:organizational_units,id',
                'level_code' => 'required|string|exists:hierarchy_level_definitions,level_code',
                'is_active' => 'boolean',
                'display_order' => 'integer|min:0'
            ]);

            $unit = $this->hierarchyService->createOrganizationalUnit($validated);

            return response()->json([
                'success' => true,
                'message' => 'Organizational unit created successfully',
                'data' => $unit->load(['levelDefinition', 'parent'])
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
                'message' => 'Error creating organizational unit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an organizational unit
     */
    public function updateUnit(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'code' => 'sometimes|nullable|string|max:50|unique:organizational_units,code,' . $id,
                'description' => 'sometimes|nullable|string|max:1000',
                'parent_id' => 'sometimes|nullable|exists:organizational_units,id',
                'is_active' => 'sometimes|boolean',
                'display_order' => 'sometimes|integer|min:0'
            ]);

            $unit = OrganizationalUnit::findOrFail($id);

            // Validate parent-child relationship if parent is being changed
            if (isset($validated['parent_id']) && $validated['parent_id']) {
                $parent = OrganizationalUnit::findOrFail($validated['parent_id']);
                if ($parent->level_order >= $unit->level_order) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Parent must be at a higher hierarchy level'
                    ], 422);
                }

                // Check for circular references
                if ($unit->getDescendants()->contains('id', $validated['parent_id'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot set a descendant as parent (circular reference)'
                    ], 422);
                }
            }

            $unit->update($validated);

            // Clear cache
            $this->hierarchyService->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Organizational unit updated successfully',
                'data' => $unit->fresh(['levelDefinition', 'parent', 'children'])
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
                'message' => 'Error updating organizational unit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an organizational unit
     */
    public function deleteUnit(int $id): JsonResponse
    {
        try {
            $unit = OrganizationalUnit::findOrFail($id);

            // Check if unit has children
            if ($unit->children()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete unit with child units. Please reassign or delete children first.'
                ], 422);
            }

            // Check if unit has active user scopes
            if ($unit->userScopes()->where('is_active', true)->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete unit with active user assignments. Please remove user assignments first.'
                ], 422);
            }

            $unit->delete();

            // Clear cache
            $this->hierarchyService->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'Organizational unit deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting organizational unit: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update organization configuration
     */
    public function updateConfig(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|nullable|string|max:1000',
                'max_hierarchy_depth' => 'sometimes|integer|min:1|max:20',
                'allow_cross_level_assignment' => 'sometimes|boolean',
                'strict_perimeter_isolation' => 'sometimes|boolean',
            ]);

            $config = $this->hierarchyService->updateConfig($validated);

            return response()->json([
                'success' => true,
                'message' => 'Configuration updated successfully',
                'data' => $config
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
                'message' => 'Error updating configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a new hierarchy level
     */
    public function addLevel(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'level_name' => 'required|string|max:100',
                'level_code' => 'required|string|max:50|unique:hierarchy_level_definitions,level_code',
                'level_order' => 'required|integer|min:1',
                'description' => 'nullable|string|max:500',
                'is_mandatory' => 'boolean',
                'allows_multiple_parents' => 'boolean',
            ]);

            $level = $this->hierarchyService->addHierarchyLevel($validated);

            return response()->json([
                'success' => true,
                'message' => 'Hierarchy level added successfully',
                'data' => $level
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
                'message' => 'Error adding hierarchy level: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update role permissions for hierarchy levels
     */
    public function updateRolePermissions(Request $request, string $roleName): JsonResponse
    {
        try {
            $validated = $request->validate([
                'permissions' => 'required|array',
                'permissions.*.level_id' => 'sometimes|exists:hierarchy_level_definitions,id',
                'permissions.*.min_level' => 'sometimes|integer|min:1',
                'permissions.*.max_level' => 'sometimes|integer|min:1',
                'permissions.*.can_access' => 'boolean',
                'permissions.*.can_create' => 'boolean',
                'permissions.*.can_edit' => 'boolean',
                'permissions.*.can_delete' => 'boolean',
            ]);

            $this->hierarchyService->updateRolePermissions($roleName, $validated['permissions']);

            return response()->json([
                'success' => true,
                'message' => 'Role permissions updated successfully'
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
                'message' => 'Error updating role permissions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get role permissions for hierarchy levels
     */
    public function getRolePermissions(string $roleName): JsonResponse
    {
        try {
            $accessibleLevels = $this->hierarchyService->getAccessibleLevelsForRole($roleName);

            return response()->json([
                'success' => true,
                'data' => [
                    'role_name' => $roleName,
                    'accessible_levels' => $accessibleLevels
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving role permissions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate hierarchy integrity
     */
    public function validateIntegrity(): JsonResponse
    {
        try {
            $issues = $this->hierarchyService->validateHierarchyIntegrity();

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
                'message' => 'Error validating hierarchy integrity: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export hierarchy configuration
     */
    public function exportConfig(): JsonResponse
    {
        try {
            $exportData = $this->hierarchyService->exportConfiguration();

            return response()->json([
                'success' => true,
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import hierarchy configuration
     */
    public function importConfig(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'config_data' => 'required|array',
                'config_data.organization_config' => 'required|array',
                'config_data.hierarchy_levels' => 'required|array',
                'config_data.role_permissions' => 'sometimes|array',
            ]);

            $this->hierarchyService->importConfiguration($validated['config_data']);

            return response()->json([
                'success' => true,
                'message' => 'Configuration imported successfully'
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
                'message' => 'Error importing configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search organizational units
     */
    public function searchUnits(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'query' => 'required|string|min:2',
                'level_code' => 'sometimes|string',
                'parent_id' => 'sometimes|integer',
                'is_active' => 'sometimes|boolean',
                'limit' => 'sometimes|integer|min:1|max:100'
            ]);

            $query = OrganizationalUnit::with(['levelDefinition', 'parent'])
                ->where(function ($q) use ($validated) {
                    $q->where('name', 'like', '%' . $validated['query'] . '%')
                      ->orWhere('code', 'like', '%' . $validated['query'] . '%');
                });

            if (isset($validated['level_code'])) {
                $query->where('level_code', $validated['level_code']);
            }

            if (isset($validated['parent_id'])) {
                $query->where('parent_id', $validated['parent_id']);
            }

            if (isset($validated['is_active'])) {
                $query->where('is_active', $validated['is_active']);
            }

            $limit = $validated['limit'] ?? 20;
            $results = $query->limit($limit)->get();

            return response()->json([
                'success' => true,
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
                'message' => 'Error searching organizational units: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get units where user can create new units
     */
    public function getCreatableUnits(): JsonResponse
    {
        try {
            $user = auth()->user();
            $levels = $this->hierarchyService->getHierarchyLevels();
            $creatableUnits = [];

            foreach ($levels as $level) {
                if ($user->can('create_organizational_units') || 
                    $this->hierarchyService->roleCanAccessLevel($user->roles->first()->name ?? '', $level->level_code)) {
                    $creatableUnits[] = $level;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $creatableUnits
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving creatable units: ' . $e->getMessage()
            ], 500);
        }
    }
}