<?php
namespace Modules\Iappointment\Events\Handlers;

class NewAppointmentFromNewSubscription
{
    private $appointmentService;

    public function __construct()
    {
        $this->appointmentService = app('Modules\Iappointment\Services\AppointmentService');
    }

    public function handle($event)
    {
        try {
            $userDriver = config('asgard.user.config.driver');

            $model = $event->model;

            \Log::info('Appointment category: ' . $model->options->appointmentCategoryId);

            if ($model->entity === "Modules\\User\\Entities\\{$userDriver}\\User") {
                $appointment = $this->appointmentService->create($model->options->appointmentCategoryId, $model);
                \Log::info('New Appointment was created to customer: '.$appointment->customer->email.' - Category: '.$appointment->category->title);
            }
        }catch(\Exception $e){
            \Log::info($e->getMessage().' - '.$e->getFile().' - '.$e->getLine());
        }
    }
}
