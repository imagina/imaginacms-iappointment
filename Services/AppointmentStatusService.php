<?php


namespace Modules\Iappointment\Services;


class AppointmentStatusService
{
    public $appointment;

    public function __construct()
    {
        $this->appointment = app('Modules\Iappointment\Repositories\AppointmentRepository');
    }

    public function setStatus($appointmentId, $statusId, $assignedTo = null, $comment = null){
      $appointment =  $this->appointment->getItem($appointmentId);

      if($appointment->status_id != $statusId){
          $appointment->update(['status_id' => $statusId, 'assigned_to' => $assignedTo]);
          $data = ['notify'=> '1','status_id' => $statusId, 'assigned_to' => $assignedTo, 'comment' => $comment];
          $appointment->statusHistories()->create($data);
        }
        
    }

}
