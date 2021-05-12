<?php

namespace Modules\Iappointment\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

use Modules\Iappointment\Events\CategoryWasCreated;
use Modules\Iappointment\Events\CategoryWasDeleted;
use Modules\Iappointment\Events\CategoryWasUpdated;
use Modules\Iappointment\Events\Handlers\NewAppointmentFromNewSubscription;
use Modules\Iforms\Events\Handlers\HandleFormeable;

use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [];

    public function boot()
    {
        //Listen category was created event
        Event::listen(
            CategoryWasCreated::class,
            [HandleFormeable::class, 'handle']
        );

        //Listen category was updated event
        Event::listen(
            CategoryWasUpdated::class,
            [HandleFormeable::class, 'handle']
        );

        //Listen category was deleted event
        Event::listen(
            CategoryWasDeleted::class,
            [HandleFormeable::class, 'handle']
        );

        if(is_module_enabled('Iplan')){
            Event::listen(
                "Modules\\Iplan\\Events\\SubscriptionHasStarted",
                [NewAppointmentFromNewSubscription::class, 'handle']
            );
        }

    }
}
