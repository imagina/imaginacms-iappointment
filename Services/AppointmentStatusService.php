<?php


namespace Modules\Iappointment\Services;

class AppointmentStatusService
{
    public $appointment;
    public $conversationService;

    public function __construct()
    {
        $this->appointment = app('Modules\Iappointment\Repositories\AppointmentRepository');
        $this->conversationService = app('Modules\Ichat\Services\ConversationService');
    }

    public function setStatus($appointmentId, $statusId, $assignedTo = null, $comment = null){

      $appointment =  $this->appointment->getItem($appointmentId);

      if($appointment->status_id != $statusId){
          $appointment->update(['status_id' => $statusId, 'assigned_to' => $assignedTo]);
          $data = ['notify'=> '1','status_id' => $statusId, 'assigned_to' => $assignedTo, 'comment' => $comment];
          $appointment->statusHistories()->create($data);
        if($statusId == '6'){ //COMPLETADO
          $conversation = $appointment->conversation;
          $conversationData = [
              'status' => 2
          ];
          $this->conversationService->update($conversation->id, $conversationData);
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
            "link" => url('/ipanel/#/appointment/' . $appointment->id),
            "setting" => ["saveInDatabase" => 1]
          ]);
        }
        }
    }

}
