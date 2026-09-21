<?php

namespace App\Observers;

use App\Models\Zone;
use App\Models\OrganizationalUnit;

class ZoneObserver
{
    public function created(Zone $zone): void
    {
        // On récupère le parent depuis le territoire
        $parentId = null;
        if ($zone->territory && $zone->territory->organizational_unit_id) {
            $parentId = $zone->territory->organizational_unit_id;
        }

        $unit = OrganizationalUnit::create([
            'name' => $zone->name,
            'type' => 'zone',
            'parent_id' => $parentId,
            'metadata' => ['entity_id' => $zone->id, 'entity_type' => 'zone']
        ]);
        
        $zone->updateQuietly(['organizational_unit_id' => $unit->id]);
    }

    public function updated(Zone $zone): void
    {
        if ($zone->isDirty('name') && $zone->organizational_unit_id) {
            OrganizationalUnit::where('id', $zone->organizational_unit_id)
                ->update(['name' => $zone->name]);
        }
    }

    public function deleted(Zone $zone): void
    {
        if ($zone->organizational_unit_id) {
            OrganizationalUnit::where('id', $zone->organizational_unit_id)->delete();
        }
    }
}
