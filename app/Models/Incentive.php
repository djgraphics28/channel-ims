<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incentive extends Model
{
    protected $guarded = [];

    /**
     * Get all of the agents for the Incentive
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function agents(): HasMany
    {
        return $this->hasMany(IncentiveAgent::class, 'incentive_id', 'id');
    }
}
