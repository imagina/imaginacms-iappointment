<?php
namespace Modules\Iappointment\Events\Handlers;

use Modules\Iappointment\Services\AppointmentService;
use Modules\Ichat\Services\ConversationService;

class AppointmentStatusHandler
{
    private $appointmentService;


    public function __construct(AppointmentService $appointmentService, ConversationService $conversationService)
    {
        $this->appointmentService = $appointmentService;
      $this->conversationService = $conversationService;
    }

    public function handle($event)
    {
      $appointment = $event->model;
      $data = $event->data;
        try {
          //create Appointment status history
          $data = ['notify' => '1', 'status_id' => $data["status_id"], 'assigned_to' => $data["assigned_to"] ?? null, 'comment' => $data["status_comment"] ?? ""];
          $appointment->statusHistory()->create($data);
          
          //Generating notification about the status id
          
          switch($data["status_id"]){
  
            case 1: //Pending
              $this->appointmentService->assign($appointment->category_id);
              break;
  
            case 2: // In Progress Pre
              break;
  
            case 3: //In Progress Conversation
              
              $this->inotification->to(['broadcast' => [$appointment->assigned_to]])->push([
                "title" => "Appointment was completed",
                "message" => "Appointment was completed!",
                "link" => url(''),
                "frontEvent" => [
                  "name" => "iappointment.appoinment.was.changed",
                ],
                "setting" => ["saveInDatabase" => 0]
              ]);
              
              break;
  
            case 4: // Abandoned
  
              $appointment->assigned_to = null;
              $appointment->save();
  
              $this->inotification->to([
                'broadcast' => [$appointment->customer->id],
                'email' => [$appointment->customer->email]
              ])->push([
                "title" => trans("iappointment::appointments.title.appointmentAbandoned"),
                "message" => trans("iappointment::appointments.messages.appointmentAbandoned",['name'=>$appointment->customer->present()->fullName]),
                "buttonText" => trans("iappointment::appointments.button.retake"),
                "withButton" => true,
                "frontEvent" => [
                  "name" => "iappointment.appoinment.was.changed",
                ],
                "link" => url('/ipanel/#/appointments/customer' . $appointment->id),
                "setting" => ["saveInDatabase" => 0]
              ]);
              
              break;
  
  
            case 5: // Expired
              break;
  
            case 6: // Completed
              
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
              
              break;
  
  
  
  
          }
          
          
        }catch(\Exception $e){
            \Log::info($e->getMessage().' - '.$e->getFile().' - '.$e->getLine());
        }
    }
}
