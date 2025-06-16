<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncentiveAgent extends Model
{
    protected $guarded = [];

    /**
     * Get the Incentive that owns the IncentiveAgent
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function incentive(): BelongsTo
    {
        return $this->belongsTo(Incentive::class, 'incentive_id', 'id');
    }

    /**
     * Get the employee that owns the IncentiveAgent
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'agent_id', 'id');
    }
}
