<?php

namespace Modules\Iappointment\Entities;

use Illuminate\Database\Eloquent\Model;

class CategoryForm extends Model
{

    protected $table = 'iappointment__category_forms';
    protected $fillable = [
        'category_id',
        'form_id'
    ];
}
