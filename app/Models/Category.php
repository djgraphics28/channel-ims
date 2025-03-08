<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{

    //make a slug based on the name using Laravel's Str::slug helper
    protected $fillable = ['name', 'slug', 'description', 'is_active'];

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug'] = \Illuminate\Support\Str::slug($value);
    }
}
