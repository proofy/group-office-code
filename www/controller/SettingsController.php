<?php


namespace GO\Core\Controller;

use GO;


class SettingsController extends \GO\Base\Controller\AbstractController {
	
	protected function actionSubmit($params){		
		
		$this->fireEvent('beforesavesettings', array($this, $params));
			
		// Fix for branding of Group-Office (Group-Office is replaced with branding name and then the theme is also renamed.)
		if(isset(\GO::config()->product_name) && !empty($params['theme']) && \GO::config()->product_name == $params['theme']){
			 $params['theme'] = 'Group-Office';
		}
		
		if(!empty($params["dateformat"])){
			$dateparts = explode(':',$params["dateformat"]);
			$params['date_separator'] = $dateparts[0];
			$params['date_format'] = $dateparts[1];
		}
		
//		$user = \GO\Base\Model\User::model()->findByPk($params['id']);
		$user = GO::user();
		
					
		if (!empty($params["password"]) || !empty($params["passwordConfirm"])) {
			
			if(!$user->checkPassword($params['current_password']))
				throw new \GO\Base\Exception\BadPassword();
			
//			if ($params["password"] != $params["passwordConfirm"]) {
//				throw new \Exception(\GO::t('error_match_pass', 'users'));
//			}
//			if (!empty($params["passwordConfirm"])) {
//				$user->setAttribute('password', $_POST['passwordConfirm']);
//			}
		}else
		{
			unset($params['password']);
		}
		$user->setAttributes($params);
		
		\GO::$ignoreAclPermissions = true;
		$contact = $user->createContact();
		unset($params['id']);
		if($contact !== false) {
			$contact->setAttributes($params);
			$contact->save(true);
		}
		\GO::$ignoreAclPermissions = false;
		
		$response['success']=$user->save(true);
		
		if(!$response['success']){
			
			//No HTML can be used because iframe file upload is used!
			$response['feedback']=implode("\n", $user->getValidationErrors())."\n";			
			
			$response['validationErrors']=$user->getValidationErrors();
		}else
		{
			\GO::modules()->callModuleMethod('submitSettings', array(&$this, &$params, &$response, $user), false);
		}
				
		
		

//		\GO\Base\Session::setCompatibilitySessionVars();
		
		
		return $response;
	}
	
	protected function actionLoad($params){
		
		$user = \GO\Base\Model\User::model()->findByPk($params['id']);
		
		
		$response['data']=$user->getAttributes('formatted');
		unset($response['data']['password']);
		
		if($user->contact)
			$response['data']=array_merge($response['data'],$user->contact->getAttributes('formatted'));
		
		if(!empty($response['data']['date_separator'])&& !empty($response['data']['date_format'])){
			$response['data']['dateformat'] = $response['data']['date_separator'].':'.$response['data']['date_format'];
		}
		
		$response['success']=true;
		
		\GO::modules()->callModuleMethod('loadSettings', array(&$this, &$params, &$response, $user));
		
		return $response;
	}
	
}
