<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $guarded = [];

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => collect([$this->first_name, $this->last_name])
            ->filter()
            ->implode(' '));
    }
}
