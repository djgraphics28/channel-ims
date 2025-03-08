<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name',
        'document_id',
        'email',
        'phone',
        'address',
        'birth_date',
        'amount_purchased',
        'last_purchased_date',
        'date_of_registration'
    ];
}
