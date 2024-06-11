<?php

namespace Modules\Iappointment\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AssignAppointment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public $appointmentService;

    public function __construct()
    {
        $this->appointmentService = app('Modules\Iappointment\Services\AppointmentService');
    }

    public function handle()
    {
        $this->appointmentService->assign();
    }
}
