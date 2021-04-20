<?php

namespace Modules\Iappointment\Entities;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;

class AppointmentStatus extends Model
{
    use Translatable;

    protected $table = 'iappointment__appointment_statuses';
    public $translatedAttributes = [
        'title'
    ];
    protected $fillable = [
        'parent_id',
        'status'
    ];
}
