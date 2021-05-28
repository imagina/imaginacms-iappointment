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
        if($statusId == '6'){ //COMPLETADO
          $this->inotification->to(['broadcast' => [$appointment->customer->id]])->push([
            "title" => "Appointment was completed",
            "message" => "Appointment was completed!",
            "link" => url(''),
            "frontEvent" => [
              "name" => "iappointment.appoinment.was.changed",
            ],
            "setting" => ["saveInDatabase" => 1]
          ]);
        }
        if($statusId == '4'){//ABANDONADO
          $this->inotification->to([
            'broadcast' => [$appointment->customer->id],
            'email' => [$appointment->customer->email]
            ])->push([
            "title" => trans("iappointment::appointments.title.appointmentAbandoned"),
            "message" => trans("iappointment::appointments.messages.appointmentAbandoned"),
            "buttonText" => trans("iappointment::appointments.button.retake"),
            "withButton" => true,
            "link" => url('/ipanel/#/appointment/' . $item->id),
            "setting" => ["saveInDatabase" => 1]
          ]);
        }
        }
    }

}
