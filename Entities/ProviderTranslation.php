<?php

namespace Modules\Iappointment\Entities;

use Illuminate\Database\Eloquent\Model;

class ProviderTranslation extends Model
{
    public $timestamps = false;
    protected $fillable = [];
    protected $table = 'iappointment__provider_translations';
}
