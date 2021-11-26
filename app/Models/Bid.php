<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Bid extends Model
{
    protected $table = 'bid';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['user_id','product_id','bid'];
    
}
