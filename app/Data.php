<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Data extends Model
{
    protected $table = 'table_with_data';
    protected $dates = ['date_added'];

    public function getDateThreeAttribute()
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $this->date_added)->addDays(3);
    }
}
