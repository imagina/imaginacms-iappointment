<?php

namespace Modules\Iappointment\Services;

class AppointmentService
{
    public $appointment;
    public $category;

    public function __construct()
    {
        $this->appointment = app('Modules\Iappointment\Repositories\AppointmentRepository');
        $this->category = app('Modules\Iappointment\Repositories\CategoryRepository');
    }

    /**
     * @param Category $category
     */
    function create($categoryId, $model = false){

        $user = auth()->user();

        $category = $this->category->getItem($categoryId);

        $appointmentData = [
            'description' => '--',
            'customer_id' => $model ? $model->entity_id : $user->id,
            'status_id' => 1,
            'category_id' => $category->id
        ];
        $appointment = $this->appointment->create($appointmentData);
        return $appointment;
    }
}
