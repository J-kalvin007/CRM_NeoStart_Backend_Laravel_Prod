<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HierarchyConfigService;
use App\Models\OrganizationConfig;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class HierarchyConfigController extends Controller
{
    protected HierarchyConfigService $hierarchyService;

    public function __construct(HierarchyConfigService $hierarchyService)
    {
        $this->hierarchyService = $hierarchyService;
    }

    /**
     * Get all organization configurations
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OrganizationConfig::class);

        $configs = $this->hierarchyService->getActiveConfigurations();

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    /**
     * Create a new organization configuration
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', OrganizationConfig::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:organization_configs,name',
            'code' => 'nullable|string|max:50|regex:/^[a-z0-9_-]+$/i|unique:organization_configs,code',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'settings' => 'array',
            'levels' => 'required|array|min:1',
            'levels.*.level_order' => 'required|integer|min:1',
            'levels.*.level_code' => 'required|string|max:50',
            'levels.*.level_name' => 'required|string|max:255',
            'levels.*.level_description' => 'nullable|string|max:1000',
            'levels.*.level_icon' => 'nullable|string|max:50',
            'levels.*.level_color' => 'nullable|string|max:7',
            'levels.*.can_have_users' => 'boolean',
            'levels.*.can_have_managers' => 'boolean',
            'levels.*.is_active' => 'boolean',
            'levels.*.metadata' => 'array',
        ]);

        try {
            $config = $this->hierarchyService->createOrganizationConfig($validated);

            return response()->json([
                'success' => true,
                'message' => 'Organization configuration created successfully.',
                'data' => $config,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create organization configuration.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific organization configuration
     */
    public function show(string $id): JsonResponse
    {
        $config = $this->hierarchyService->getConfigurationWithStats($id);

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Organization configuration not found.',
            ], 404);
        }

        $this->authorize('view', $config);

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Update an organization configuration
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $config = OrganizationConfig::findOrFail($id);
        $this->authorize('update', $config);

        $validated = $request->validate([
            'name' => "string|max:255|unique:organization_configs,name,{$id}",
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'settings' => 'array',
            'levels' => 'array',
            'levels.*.id' => 'nullable|uuid|exists:hierarchy_level_definitions,id',
            'levels.*.level_order' => 'required|integer|min:1',
            'levels.*.level_code' => 'required|string|max:50',
            'levels.*.level_name' => 'required|string|max:255',
            'levels.*.level_description' => 'nullable|string|max:1000',
            'levels.*.level_icon' => 'nullable|string|max:50',
            'levels.*.level_color' => 'nullable|string|max:7',
            'levels.*.can_have_users' => 'boolean',
            'levels.*.can_have_managers' => 'boolean',
            'levels.*.is_active' => 'boolean',
            'levels.*.metadata' => 'array',
        ]);

        try {
            $updatedConfig = $this->hierarchyService->updateOrganizationConfig($config, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Organization configuration updated successfully.',
                'data' => $updatedConfig,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update organization configuration.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an organization configuration
     */
    public function destroy(string $id): JsonResponse
    {
        $config = OrganizationConfig::findOrFail($id);
        $this->authorize('delete', $config);

        try {
            $this->hierarchyService->deleteOrganizationConfig($config);

            return response()->json([
                'success' => true,
                'message' => 'Organization configuration deactivated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete organization configuration.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clone an organization configuration
     */
    public function clone(Request $request, string $id): JsonResponse
    {
        $sourceConfig = OrganizationConfig::findOrFail($id);
        $this->authorize('create', OrganizationConfig::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:organization_configs,name',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            $clonedConfig = $this->hierarchyService->cloneConfiguration(
                $sourceConfig,
                $validated['name'],
                $validated['description'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Organization configuration cloned successfully.',
                'data' => $clonedConfig,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clone organization configuration.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get hierarchy tree for a configuration
     */
    public function getTree(string $id): JsonResponse
    {
        $config = OrganizationConfig::findOrFail($id);
        $this->authorize('view', $config);

        try {
            $tree = $this->hierarchyService->getHierarchyTree($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'config' => $config->load('hierarchyLevels'),
                    'tree' => $tree,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get hierarchy tree.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}