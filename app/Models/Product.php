<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'category_id',
        'code',
        'name',
        'description',
        'unit_id',
        'stock',
        'buying_price',
        'selling_price',
        'created_by',
        'updated_by'
    ];
}
