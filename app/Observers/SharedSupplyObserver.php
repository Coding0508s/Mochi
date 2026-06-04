<?php

namespace App\Observers;

use App\Models\SharedSupply;
use App\Support\SharedSupplyCalendarSync;

class SharedSupplyObserver
{
    public function saved(SharedSupply $sharedSupply): void
    {
        app(SharedSupplyCalendarSync::class)->sync($sharedSupply);
    }

    public function deleting(SharedSupply $sharedSupply): void
    {
        $sharedSupply->vehicleUsageLog()->delete();
    }

    public function deleted(SharedSupply $sharedSupply): void
    {
        app(SharedSupplyCalendarSync::class)->delete($sharedSupply);
    }
}
