<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PerimeterService;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationConfig;
use App\Models\HierarchyLevelDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DynamicOrganizationalUnitController extends Controller
{
    protected PerimeterService $perimeterService;

    public function __construct(PerimeterService $perimeterService)
    {
        $this->perimeterService = $perimeterService;
    }

    /**
     * Get organizational units accessible by current user
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $configId = $request->query('config_id');
        $parentId = $request->query('parent_id');
        $levelCode = $request->query('level_code');
        $withChildren = $request->boolean('with_children', false);
        $withStats = $request->boolean('with_stats', false);

        $query = OrganizationalUnit::withinUserPerimeter($user);

        if ($configId) {
            $query = $query->where('organization_config_id', $configId);
        }

        if ($parentId) {
            $query = $query->where('parent_id', $parentId);
        }

        if ($levelCode) {
            $query = $query->where('level_code', $levelCode);
        }

        if ($withChildren) {
            $query = $query->with(['children' => function ($childQuery) {
                $childQuery->where('is_active', true)
                          ->orderBy('display_order')
                          ->orderBy('name');
            }]);
        }

        if ($withStats) {
            $query = $query->withCount(['users', 'allChildren']);
        }

        $units = $query->get();

        return response()->json([
            'success' => true,
            'data' => [
                'units' => $units,
                'total_count' => $units->count(),
                'filters' => [
                    'config_id' => $configId,
                    'parent_id' => $parentId,
                    'level_code' => $levelCode,
                ],
            ],
        ]);
    }

    /**
     * Create a new organizational unit
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$this->perimeterService->canCreateUnits($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to create organizational units.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'level_code' => 'nullable|string',
            'organization_config_id' => 'nullable|uuid|exists:organization_configs,id',
            'level_definition_id' => 'nullable|uuid|exists:hierarchy_level_definitions,id',
            'parent_id' => 'nullable|uuid|exists:organizational_units,id',
            'manager_id' => 'nullable|uuid|exists:users,id',
            'display_order' => 'integer|min:0',
            'metadata' => 'array',
        ]);

        $configId = $validated['organization_config_id'] ?? null;
        if (!$configId) {
            $defaultConfig = OrganizationConfig::where('code', 'crm_default')->first() ?? OrganizationConfig::first();
            if (!$defaultConfig) {
                return response()->json(['success' => false, 'message' => 'No active organization configuration found.'], 404);
            }
            $configId = $defaultConfig->id;
            $validated['organization_config_id'] = $configId;
        }

        $levelDefId = $validated['level_definition_id'] ?? null;
        if (!$levelDefId && isset($validated['level_code'])) {
            $levelDef = HierarchyLevelDefinition::where('organization_config_id', $configId)
                ->where('level_code', $validated['level_code'])->first();
            if (!$levelDef) {
                return response()->json(['success' => false, 'message' => 'Invalid level_code for the configuration.'], 404);
            }
            $levelDefId = $levelDef->id;
            $validated['level_definition_id'] = $levelDefId;
        }

        if (!$levelDefId) {
             return response()->json(['success' => false, 'message' => 'level_definition_id or level_code is required.'], 422);
        }

        // Validate hierarchy level relationship
        $levelDefinition = HierarchyLevelDefinition::findOrFail($validated['level_definition_id']);
        if ($levelDefinition->organization_config_id !== $validated['organization_config_id']) {
            return response()->json([
                'success' => false,
                'message' => 'Level definition does not belong to the specified organization configuration.',
            ], 422);
        }

        // Validate parent relationship
        if (isset($validated['parent_id'])) {
            $parent = OrganizationalUnit::findOrFail($validated['parent_id']);
            
            if ($parent->organization_config_id !== $validated['organization_config_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent unit must belong to the same organization configuration.',
                ], 422);
            }

            if ($parent->level_order >= $levelDefinition->level_order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Child unit level must be greater than parent level.',
                ], 422);
            }

            // Check if user can access parent unit
            if (!$this->perimeterService->canAccessUnit($user, $validated['parent_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to create units under this parent.',
                ], 403);
            }
        }

        try {
            $unit = DB::transaction(function () use ($validated, $levelDefinition, $request) {
                return OrganizationalUnit::create([
                    'organization_config_id' => $validated['organization_config_id'],
                    'level_definition_id' => $validated['level_definition_id'],
                    'parent_id' => $validated['parent_id'] ?? null,
                    'manager_id' => $validated['manager_id'] ?? null,
                    'name' => $validated['name'],
                    'code' => $validated['code'] ?? null,
                    'level_code' => $levelDefinition->level_code,
                    'level_order' => $levelDefinition->level_order,
                    'display_order' => $validated['display_order'] ?? 0,
                    'metadata' => $request->has('currency') ? array_merge($validated['metadata'] ?? [], ['currency' => $request->input('currency')]) : ($validated['metadata'] ?? null),
                    'is_active' => true,
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Organizational unit created successfully.',
                'data' => $unit->load(['levelDefinition', 'parent', 'manager']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create organizational unit.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific organizational unit
     */
    public function show(string $id): JsonResponse
    {
        $unit = OrganizationalUnit::with([
            'levelDefinition',
            'organizationConfig',
            'parent',
            'manager',
            'children' => function ($query) {
                $query->where('is_active', true)
                      ->orderBy('display_order')
                      ->orderBy('name');
            }
        ])->findOrFail($id);

        // Check if user can access this unit
        if (!$this->perimeterService->canAccessUnit(Auth::user(), $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to access this organizational unit.',
            ], 403);
        }

        // Add statistics
        $unit->loadCount(['users', 'allChildren']);
        
        // Add hierarchy path
        $hierarchyPath = $this->perimeterService->getHierarchyPath($id);

        return response()->json([
            'success' => true,
            'data' => [
                'unit' => $unit,
                'hierarchy_path' => $hierarchyPath,
                'stats' => [
                    'users_count' => $unit->users_count,
                    'children_count' => $unit->all_children_count,
                    'depth' => $unit->getDepth(),
                    'can_be_deleted' => $unit->canBeDeletedSafely(),
                ],
            ],
        ]);
    }

    /**
     * Update an organizational unit
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $unit = OrganizationalUnit::findOrFail($id);
        $user = Auth::user();

        // Check permissions
        if (!$this->perimeterService->canEditUnits($user) || 
            !$this->perimeterService->canAccessUnit($user, $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this organizational unit.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'string|max:255',
            'manager_id' => 'nullable|uuid|exists:users,id',
            'display_order' => 'integer|min:0',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ]);

        try {
            $unit->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Organizational unit updated successfully.',
                'data' => $unit->fresh(['levelDefinition', 'parent', 'manager']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update organizational unit.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an organizational unit
     */
    public function destroy(string $id): JsonResponse
    {
        $unit = OrganizationalUnit::findOrFail($id);
        $user = Auth::user();

        // Check permissions
        if (!$this->perimeterService->canDeleteUnits($user) || 
            !$this->perimeterService->canAccessUnit($user, $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this organizational unit.',
            ], 403);
        }

        // Check if unit can be safely deleted
        if (!$unit->canBeDeletedSafely()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete organizational unit that has active users or children.',
            ], 422);
        }

        try {
            $unit->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Organizational unit deactivated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete organizational unit.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get organization configurations available to user
     */
    public function getConfigurations(): JsonResponse
    {
        $user = Auth::user();

        // Global admins can see all configurations
        if ($user->can('scope:global') || $user->hasAnyRole(['superadmin', 'admin'])) {
            $configs = OrganizationConfig::with('hierarchyLevels')->active()->get();
        } else {
            // Get configs from user's organizational units
            $userScopes = $this->perimeterService->getUserScopes($user);
            $configIds = $userScopes->pluck('organizationalUnit.organization_config_id')->unique()->filter();
            
            $configs = OrganizationConfig::with('hierarchyLevels')
                ->whereIn('id', $configIds)
                ->active()
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    /**
     * Get hierarchy levels for a configuration
     */
    public function getLevels(string $configId): JsonResponse
    {
        $config = OrganizationConfig::findOrFail($configId);
        
        $levels = HierarchyLevelDefinition::where('organization_config_id', $configId)
            ->active()
            ->orderBy('level_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'config' => $config,
                'levels' => $levels,
            ],
        ]);
    }

    /**
     * Move organizational unit to new parent
     */
    public function move(Request $request, string $id): JsonResponse
    {
        $unit = OrganizationalUnit::findOrFail($id);
        $user = Auth::user();

        // Check permissions
        if (!$this->perimeterService->canEditUnits($user) || 
            !$this->perimeterService->canAccessUnit($user, $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to move this organizational unit.',
            ], 403);
        }

        $validated = $request->validate([
            'parent_id' => 'nullable|uuid|exists:organizational_units,id',
        ]);

        if ($validated['parent_id']) {
            $newParent = OrganizationalUnit::findOrFail($validated['parent_id']);
            
            // Validate move constraints
            if ($newParent->organization_config_id !== $unit->organization_config_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot move unit to parent with different organization configuration.',
                ], 422);
            }

            if ($newParent->level_order >= $unit->level_order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot move unit to parent at same or lower level.',
                ], 422);
            }

            if ($unit->isAncestorOf($newParent)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot move unit to its own descendant.',
                ], 422);
            }

            // Check access to new parent
            if (!$this->perimeterService->canAccessUnit($user, $validated['parent_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to move unit under this parent.',
                ], 403);
            }
        }

        try {
            $unit->update(['parent_id' => $validated['parent_id']]);

            return response()->json([
                'success' => true,
                'message' => 'Organizational unit moved successfully.',
                'data' => $unit->fresh(['levelDefinition', 'parent']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to move organizational unit.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}