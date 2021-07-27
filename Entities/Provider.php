<?php

namespace Modules\Iappointment\Entities;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    use Translatable;

    protected $table = 'iappointment__providers';
    public $translatedAttributes = [];
    protected $fillable = [];
}
