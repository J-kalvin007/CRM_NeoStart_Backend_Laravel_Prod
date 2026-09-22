<?php

namespace App\Observers;

use App\Models\Region;
use App\Models\OrganizationalUnit;

class RegionObserver
{
    /**
     * Handle the Region "created" event.
     */
    public function created(Region $region): void
    {
        // On cherche le pays parent si on l'a (via subsidiary, pour l'instant on garde ça simple)
        // Mais par défaut, on met en racine ou sous une filiale si nécessaire.
        $unit = OrganizationalUnit::create([
            'name' => $region->name,
            'type' => 'region',
            'metadata' => ['entity_id' => $region->id, 'entity_type' => 'region']
        ]);
        
        $region->updateQuietly(['organizational_unit_id' => $unit->id]);
    }

    /**
     * Handle the Region "updated" event.
     */
    public function updated(Region $region): void
    {
        if ($region->isDirty('name') && $region->organizational_unit_id) {
            OrganizationalUnit::where('id', $region->organizational_unit_id)
                ->update(['name' => $region->name]);
        }
    }

    /**
     * Handle the Region "deleted" event.
     */
    public function deleted(Region $region): void
    {
        if ($region->organizational_unit_id) {
            OrganizationalUnit::where('id', $region->organizational_unit_id)->delete();
        }
    }
}
