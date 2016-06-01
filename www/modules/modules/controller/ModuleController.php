<?php

namespace GO\Modules\Controller;

use GO;
use GO\Base\Model\Module;
use GO\Base\Controller\AbstractJsonController;
use GO\Base\Model\Acl;

//use GO\Base\Data\DbStore;
//use GO\Base\Data\ColumnModel;
//use GO\Base\Db\FindParams;
use GO\Base\Data\JsonResponse;

class ModuleController extends AbstractJsonController{
	
	protected function allowWithoutModuleAccess() {
		return array('permissionsstore');
	}
	
	protected function ignoreAclPermissions() {		
		return array('*');
	}
	
	
	protected function actionDelete($id){
		$module = Module::model()->findByPk($id);
		$module->delete();
		
		echo json_encode(array('success'=>true));
	}
	
	
	protected function actionUpdate($id) {

		
		$module = Module::install($id);		
		$module->setAttributes($_POST);		
		$module->save();
		
		echo $this->renderSubmit($module);
	}
	

	
	protected function actionStore($params){
		
		$response=new JsonResponse(array('success'=>true));
		
		$modules = GO::modules()->getAvailableModules(true);
		
		$availableModules=array();
						
		foreach($modules as $moduleClass){		
			
			$module = new $moduleClass;	
			
			$model = GO::modules()->isInstalled($module->id());
			
			
			$availableModules[$module->name()] = array(
					'id'=>$module->id(),
					'name'=>$module->name(),
					'author'=>$module->author(),
					'description'=>$module->description(),
					'icon'=>$module->icon(),
					'acl_id'=>$model ? $model->acl_id : 0,
//					'buyEnabled'=>!GO::scriptCanBeDecoded() || 
//							($module->appCenter() && (\GO\Professional\License::isTrial() || \GO\Professional\License::moduleIsRestricted($module->id())!==false)),
				
					'package'=>$module->package(),
					'enabled'=>$model && $model->enabled,
					'not_installable'=> $module->appCenter() && !GO::scriptCanBeDecoded($module->package()),
					'sort_order' => ($model && $model->sort_order)?$model->sort_order:''
			);
		}
		
		ksort($availableModules);		
		
		
//		$response['has_license']=(GO::getLicenseFile()->exists() && GO::getLicenseFile()->size()) || GO::config()->product_name!='Group-Office';
						
		$response['results']=array_values($availableModules);		
		$response['total']=count($response['results']);
		
		echo $response;
	}
	
	
	protected function actionAvailableModulesStore($params){
		
		$response=new JsonResponse(array('results','success'=>true));
		
		$modules = GO::modules()->getAvailableModules();
		
		$availableModules=array();
						
		foreach($modules as $moduleClass){		
			
			$module = new $moduleClass;//call_user_func($moduleClase();			
			$availableModules[$module->name()] = array(
					'id'=>$module->id(),
					'name'=>$module->name(),
					'description'=>$module->description(),
					'icon'=>$module->icon()
			);
		}
		
		ksort($availableModules);		
		
		$response['results']=array_values($availableModules);
		
		$response['total']=count($response['results']);
		
		echo $response;
	}
	
	
//	protected function actionInstall($params){
//		
//		$response =new JsonResponse(array('success'=>true,'results'=>array()));
//		$modules = json_decode($params['modules'], true);
//		foreach($modules as $moduleId)
//		{
//			if(!GO::modules()->$moduleId){
//				$module = new Module();
//				$module->id=$moduleId;
//
//
//				$module->moduleManager->checkDependenciesForInstallation($modules);	
//
//				if(!$module->save())
//					throw new \GO\Base\Exception\Save();
//
//				$response->data['results'][]=array_merge($module->getAttributes(), array('name'=>$module->moduleManager->name()));
//			}
//		}
//		
////		$defaultModels = \GO\Base\Model\AbstractUserDefaultModel::getAllUserDefaultModels();
////		
////		$stmt = \GO\Base\Model\User::model()->find(\GO\Base\Db\FindParams::newInstance()->ignoreAcl());		
////		while($user = $stmt->fetch()){
////			foreach($defaultModels as $model){
////				$model->getDefault($user);
////			}
////		}
//				
//		echo $response;
//	}
	
	public function actionPermissionsStore($params) {
		
		
		//check access to users or groups module. Because we allow this action without
		//access to the modules module		
		if ($params['paramIdType']=='groupId'){
			if(!GO::modules()->groups)
				throw new \GO\Base\Exception\AccessDenied();
		}else{
			if(!GO::modules()->users)
				throw new \GO\Base\Exception\AccessDenied();
		}
			
		$response = new JsonResponse(array(
			'success' => true,
			'results' => array(),
			'total' => 0
		));
		$modules = array();
		$mods = GO::modules()->getAllModules();
			
		while ($module=array_shift($mods)) {
			$permissionLevel = 0;
			$usersGroupPermissionLevel = false;
			if (empty($params['id'])) {				
				$aclUsersGroup = $module->acl->hasGroup(GO::config()->group_everyone); // everybody group
				$permissionLevel=$usersGroupPermissionLevel=$aclUsersGroup ? $aclUsersGroup->level : 0;
			} else {
				if ($params['paramIdType']=='groupId') {
					//when looking at permissions from the groups module.
					$aclUsersGroup = $module->acl->hasGroup($params['id']);
					$permissionLevel=$aclUsersGroup ? $aclUsersGroup->level : 0;
				} else {
					//when looking from the users module
					$permissionLevel = Acl::getUserPermissionLevel($module->acl_id, $params['id']);					
					$usersGroupPermissionLevel= Acl::getUserPermissionLevel($module->acl_id, $params['id'], true);
				}
			}
			
			$translated = $module->moduleManager ? $module->moduleManager->name() : $module->id;
			
			// Module permissions only support read permission and manage permission:
			if (Acl::hasPermission($permissionLevel,Acl::CREATE_PERMISSION))
				$permissionLevel = Acl::MANAGE_PERMISSION;			
			
			$modules[$translated]= array(
				'id' => $module->id,
				'name' => $translated,
				'permissionLevel' => $permissionLevel,
				'disable_none' => $usersGroupPermissionLevel!==false && Acl::hasPermission($usersGroupPermissionLevel,Acl::READ_PERMISSION),
				'disable_use' => $usersGroupPermissionLevel!==false && Acl::hasPermission($usersGroupPermissionLevel, Acl::CREATE_PERMISSION)
			);
			$response['total'] += 1;
		}
		ksort($modules);

		$response['results'] = array_values($modules);
		
		echo $response;
	}
	
	
	/**
	 * Checks default models for this module for each user.
	 * 
	 * @param array $params 
	 */
	public function actionCheckDefaultModels($params) {
		
		GO::session()->closeWriting();
		

		GO::setMaxExecutionTime(120);
		
//		GO::$disableModelCache=true;
		$module = Module::model()->findByPk($params['moduleId']);

		//only do when modified
		if($module->acl->mtime>time()-120){

			$models = array();
			$modMan = $module->moduleManager;
			if ($modMan) {
				$classes = $modMan->findClasses('model');
				foreach ($classes as $class) {
					if ($class->isSubclassOf('\GO\Base\Model\AbstractUserDefaultModel')) {
						$models[] = GO::getModel($class->getName());
					}
				}
			}
	//		GO::debug(count($users));

			$module->acl->getAuthorizedUsers($module->acl_id, Acl::READ_PERMISSION, array("\\GO\\Modules\\Controller\\ModuleController","checkDefaultModelCallback"), array($models));
		}
		
//		if(class_exists("GO_Professional_LicenseCheck")){
//			$lc = new GO_Professional_LicenseCheck();
//			$lc->checkProModules(true);
//		}
		echo new JsonResponse(array('success'=>true));
	}
	
	public static function checkDefaultModelCallback($user, $models){		
		foreach ($models as $model){
			$model->getDefault($user);		
		}
	}
	
	
	public function actionUpdateModuleModel($id=false){
		
		$response = array();
		
		if($id && GO::request()->isPost()){
			
			$postData = GO::request()->post['module'];
			
			$module = Module::model()->findByPk($postData['id']);
			
			if($module){
				
				// For now only set the sort_order, other attributes can be added later.
				$module->sort_order = $postData['sort_order'];
				
				if($module->save()){
					$response['success'] = true;
				} else {
					$response['success'] = false;
					$response['feedback'] = sprintf(\GO::t('validationErrorsFound'), strtolower($module->localizedName)) . "\n\n" . implode("\n", $module->getValidationErrors()) . "\n";
					$response['validationErrors'] = $module->getValidationErrors();
				}
			}
		} else {
			$response['success'] = false;
			$response['feedback'] = 'NO MODULE FOUND';
		}
		
		echo new \GO\Base\Data\JsonResponse($response);
	}
	
	
	
//	public function actionSaveSortOrder($params){
//		$modules = json_decode($params['modules']);
//		
//		$i=0;
//		foreach($modules as $module){
//			$moduleModel = Module::model()->findByPk($module->id);
//			$moduleModel->sort_order=$i++;
//			$moduleModel->save();
//		}
//		
//		echo new JsonResponse(array('success'=>true));
//	}

}

