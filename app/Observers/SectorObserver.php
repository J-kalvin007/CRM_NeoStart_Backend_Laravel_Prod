<?php

namespace App\Observers;

use App\Models\Sector;
use App\Models\OrganizationalUnit;

class SectorObserver
{
    public function created(Sector $sector): void
    {
        // On récupère le parent depuis la zone
        $parentId = null;
        if ($sector->zone && $sector->zone->organizational_unit_id) {
            $parentId = $sector->zone->organizational_unit_id;
        }

        $unit = OrganizationalUnit::create([
            'name' => $sector->name,
            'type' => 'sector',
            'parent_id' => $parentId,
            'metadata' => ['entity_id' => $sector->id, 'entity_type' => 'sector']
        ]);
        
        $sector->updateQuietly(['organizational_unit_id' => $unit->id]);
    }

    public function updated(Sector $sector): void
    {
        if ($sector->isDirty('name') && $sector->organizational_unit_id) {
            OrganizationalUnit::where('id', $sector->organizational_unit_id)
                ->update(['name' => $sector->name]);
        }
    }

    public function deleted(Sector $sector): void
    {
        if ($sector->organizational_unit_id) {
            OrganizationalUnit::where('id', $sector->organizational_unit_id)->delete();
        }
    }
}
