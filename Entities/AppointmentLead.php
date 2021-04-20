<?php

namespace Modules\Iappointment\Entities;

use Illuminate\Database\Eloquent\Model;

class AppointmentLead extends Model
{

    protected $table = 'iappointment__appointment_leads';
    protected $fillable = [
        'appointment_id',
        'values'
    ];

    protected $casts = [
        'values'=>'array'
    ];

    public function appointment(){
        return $this->belongsTo(Appointment::class);
    }
}
