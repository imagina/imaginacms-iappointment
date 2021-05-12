<?php
namespace Modules\Iappointment\Events\Handlers;

class NewAppointmentFromNewSubscription
{
    private $appointment;

    public function __construct()
    {
        $this->appointment = app('Modules\Iappointment\Repositories\AppointmentRepository');
    }

    public function handle($event)
    {
        try {
            $userDriver = config('asgard.user.config.driver');

            $model = $event->model;

            \Log::info('Appointment category: ' . $model->options->appointmentCategoryId);

            if ($model->entity === "Modules\\User\\Entities\\{$userDriver}\\User") {
                $appointmentData = [
                    'description' => '--',
                    'customer_id' => $model->entity_id,
                    'status_id' => 1,
                    'category_id' => $model->options->appointmentCategoryId
                ];
                $appointment = $this->appointment->create($appointmentData);
                \Log::info('New Appointment was created to customer: '.$appointment->customer->email.' - Category: '.$appointment->category->title);
            }
        }catch(\Exception $e){
            \Log::info($e->getMessage().' - '.$e->getFile().' - '.$e->getLine());
        }
    }
}
