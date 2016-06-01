<?php

namespace GO\Calendar\Controller;

use Exception;
use GO\Base\Controller\AbstractController;
use GO\Base\Exception\NotFound;
use GO\Base\Util\Number;
use GO\Calendar\Model\Event;


class AttendanceController extends AbstractController{
	protected function actionLoad($params){
		
		$event = Event::model()->findByPk($params['id']);
		if(!$event)
			throw new NotFound();
		
		$participant=$event->getParticipantOfCalendar();
		if(!$participant)
			throw new Exception("The participant of this event is missing");
		
		$organizer = $event->getOrganizer();
		if(!$organizer)
			throw new Exception("The organizer of this event is missing");
		
		$response = array("success"=>true, 'data'=>array(
				'notify_organizer'=>true,
				'status'=>$participant->status, 
				'organizer'=>$organizer->name,
				'info'=>$event->toHtml(),
				'reminder'=>$event->reminder
			));		
		
		// Translate the reminder back to the 2 params needed for the form
		$response = EventController::reminderSecondsToForm($response);
		
		return $response;
	}
	
	protected function actionSubmit($params){
		$response = array("success"=>true);
		
		$event = Event::model()->findByPk($params['id']);
		if(!$event)
			throw new NotFound();
		
		// A reminder is set and given
		if((isset($params['reminder_value']) && !empty($params['reminder_value'])) && 
			 (isset($params['reminder_multiplier']) && !empty($params['reminder_multiplier']))){
			
			// Add the reminder to the event. 
			$event->reminder = Number::unlocalize ($params['reminder_value']) * $params['reminder_multiplier'];
			$event->save();
		} else {
			// Remove the reminders for this event when the reminder post values are empty
			$event->reminder = 0;
			$event->deleteReminders();
			$event->save();
		}
		
		if(!empty($params['exception_date'])){
			$event = $event->createExceptionEvent($params['exception_date']);
		}
		
		$participant=$event->getParticipantOfCalendar();
		if($params['status']!=$participant->status){
			$participant->status=$params['status'];
			$participant->save();
		
			if(!empty($params['notify_organizer']))
				$event->replyToOrganizer();
		}

		return $response;
	}
}