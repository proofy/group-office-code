<?php


namespace GO\Site\Widget\Contactform;


class ContactForm extends \GO\Base\Model {
	/**
	 * @var string email from input
	 */
	public $email;
	
	/**
	 * @var string name input 
	 */
	public $name;
	
	/**
	 * @var string message input
	 */
	public $message;
	
	/**
	 * @var string email to input
	 */
	public $receipt;
	
	/**
	 * Returns the validation rules of the model.
	 * @return array validation rules
	 */
	public function validate()
	{
		if(empty($this->name))
			$this->setValidationError('name', sprintf(\GO::t('attributeRequired'),'name'));
		if(empty($this->email))
			$this->setValidationError('email', sprintf(\GO::t('attributeRequired'),'email'));
		if(empty($this->message))
			$this->setValidationError('message', sprintf(\GO::t('attributeRequired'),'message'));
		if(!\GO\Base\Util\Validate::email($this->email))
			$this->setValidationError('email', \GO::t('invalidEmailError'));
			
		return parent::validate();
	}
	
	/**
	 * send an email to webmaster_email in config
	 * @return boolean true when successfull
	 */
	public function send(){
		
		if(!$this->validate())
			return false;
		$message = \GO\Base\Mail\Message::newInstance();
		$message->setSubject("Groupoffice contact form");
		$message->setBody($this->message);
		$message->addFrom($this->email, $this->name);
		$message->addTo($this->receipt);
		return \GO\Base\Mail\Mailer::newGoInstance()->send($message);
	}
}
