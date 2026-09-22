<?php

namespace App\Observers;

use App\Models\Territory;
use App\Models\OrganizationalUnit;

class TerritoryObserver
{
    public function created(Territory $territory): void
    {
        // On récupère le parent depuis la région
        $parentId = null;
        if ($territory->region && $territory->region->organizational_unit_id) {
            $parentId = $territory->region->organizational_unit_id;
        }

        $unit = OrganizationalUnit::create([
            'name' => $territory->name,
            'type' => 'territory',
            'parent_id' => $parentId,
            'metadata' => ['entity_id' => $territory->id, 'entity_type' => 'territory']
        ]);
        
        $territory->updateQuietly(['organizational_unit_id' => $unit->id]);
    }

    public function updated(Territory $territory): void
    {
        if ($territory->isDirty('name') && $territory->organizational_unit_id) {
            OrganizationalUnit::where('id', $territory->organizational_unit_id)
                ->update(['name' => $territory->name]);
        }
    }

    public function deleted(Territory $territory): void
    {
        if ($territory->organizational_unit_id) {
            OrganizationalUnit::where('id', $territory->organizational_unit_id)->delete();
        }
    }
}
