<?php

namespace Modules\Iappointment\Entities;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Modules\Media\Support\Traits\MediaRelation;

class Appointment extends Model
{
    use Translatable, MediaRelation;

    protected $table = 'iappointment__appointments';
    public $translatedAttributes = [
        'description'
    ];
    protected $fillable = [
        'category_id',
        'status_id',
        'customer_id',
        'assigned_to',
        'options',
    ];

    protected $casts = [
        'options' => 'array'
    ];

    public function getOptionsAttribute($value)
    {
        return json_decode($value);
    }

    public function setOptionsAttribute($value)
    {
        $this->attributes['options'] = json_encode($value);
    }

    public function category(){
        return $this->belongsTo(Category::class);
    }

    public function status(){
        return $this->belongsTo(AppointmentStatus::class,'status_id');
    }

    public function customer()
    {
        $driver = config('asgard.user.config.driver');
        return $this->belongsTo("Modules\\User\\Entities\\{$driver}\\User", 'customer_id');
    }

    public function assigned()
    {
        $driver = config('asgard.user.config.driver');
        return $this->belongsTo("Modules\\User\\Entities\\{$driver}\\User", 'assigned_to');
    }

}
