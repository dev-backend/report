<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'this_year_orders';
    protected $dates = ['created_date', 'charged_date'];

    public function customer()
    {
        return $this->belongsTo('App\Customer');
    }
}
