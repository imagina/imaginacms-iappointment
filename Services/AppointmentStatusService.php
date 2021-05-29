<?php


namespace Modules\Iappointment\Services;

class AppointmentStatusService
{
  public $appointment;
  public $conversationService;
  public $inotification;
  public $appointmentService;
  
  public function __construct()
  {
    $this->appointment = app('Modules\Iappointment\Repositories\AppointmentRepository');
    $this->conversationService = app('Modules\Ichat\Services\ConversationService');
    $this->inotification = app('Modules\Notification\Services\Inotification');
    $this->appointmentService = app('Modules\Iappointment\Services\AppointmentService');
  }
  
  public function setStatus($appointmentId, $statusId, $assignedTo = null, $comment = null)
  {
    
    $appointment = $this->appointment->getItem($appointmentId);
    

      $appointment->status_id = $statusId;
  
      if(!empty($assignedTo)){
        $appointment->assigned_to = $assignedTo;
      }
      $appointment->save();

  
    
    $data = ['notify' => '1', 'status_id' => $statusId, 'assigned_to' => $assignedTo, 'comment' => $comment];
    $appointment->statusHistory()->create($data);
    if ($statusId == 6) { //COMPLETADO
      
      $conversation = $appointment->conversation;
      $conversationData = [
        'status' => 2
      ];
      $this->conversationService->update($conversation->id, $conversationData);
      $this->inotification->to(['broadcast' => [$appointment->customer_id]])->push([
        "title" => "Appointment was completed",
        "message" => "Appointment was completed!",
        "link" => url(''),
        "frontEvent" => [
          "name" => "iappointment.appoinment.was.changed",
        ],
        "setting" => ["saveInDatabase" => 0]
      ]);
    }
  
    if ($statusId == 3) { //In progress conversation
      
      $this->inotification->to(['broadcast' => [$appointment->assigned_to]])->push([
        "title" => "Appointment was completed",
        "message" => "Appointment was completed!",
        "link" => url(''),
        "frontEvent" => [
          "name" => "iappointment.appoinment.was.changed",
        ],
        "setting" => ["saveInDatabase" => 0]
      ]);
    }
    
    if ($statusId == 1) { //Pendiente
      $this->appointmentService->assign($appointment->category_id);
    }
    
    if ($statusId == 4) {//ABANDONADO
  
      $appointment->assigned_to = null;
      $appointment->save();
      
      $this->inotification->to([
        'broadcast' => [$appointment->customer->id],
        'email' => [$appointment->customer->email]
      ])->push([
        "title" => trans("iappointment::appointments.title.appointmentAbandoned"),
        "message" => trans("iappointment::appointments.messages.appointmentAbandoned"),
        "buttonText" => trans("iappointment::appointments.button.retake"),
        "withButton" => true,
        "frontEvent" => [
          "name" => "iappointment.appoinment.was.changed",
        ],
        "link" => url('/ipanel/#/appointments/customer' . $appointment->id),
        "setting" => ["saveInDatabase" => 0]
      ]);
    }
    
  }
}