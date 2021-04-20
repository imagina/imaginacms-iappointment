<?php

namespace Modules\Iappointment\Entities;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use Translatable;

    protected $table = 'iappointment__categories';
    public $translatedAttributes = [
        'title',
        'slug',
        'description'
    ];
    protected $fillable = [
        'parent_id',
        'options'
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

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'category_id');
    }


}
