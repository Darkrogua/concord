<?php namespace Zen\Chub\Models;

use Model;

class BaseRecord extends Model
{
    public $table = 'records';

    public $timestamps = false;

    protected $guarded = [];
}
