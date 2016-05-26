<?php
/*
 * Copyright Intermesh
 * 
 * This file is part of Group-Office. You should have received a copy of the
 * Group-Office license along with Group-Office. See the file /LICENSE.TXT
 * 
 * If you have questions write an e-mail to info@intermesh.nl
 *
 */

/**
 * This class is used to parse and write RFC822 compliant recipient lists
 * 
 * @package GO.modules.files
 * @version $Id: RFC822.class.inc 7536 2011-05-31 08:37:36Z mschering $
 * @author Merijn Schering <mschering@intermesh.nl>
 * @copyright Copyright Intermesh BV.
 */


namespace GO\Files;


class FilesModule extends \GO\Base\Module{	
	
	
	public static function initListeners() {
		\GO\Base\Model\User::model()->addListener('save', "GO\Files\FilesModule", "saveUser");
		\GO\Base\Model\User::model()->addListener('delete', "GO\Files\FilesModule", "deleteUser");
	}
	

	public function checkDatabase(&$response) {
		
		//create user home folders
		$stmt = \GO\Base\Model\User::model()->find(array('ignoreAcl'=>true));
		
		while($user = $stmt->fetch()){
			$folder = Model\Folder::model()->findHomeFolder($user);
			//$folder->syncFilesystem();
			
			//$folder = Model\Folder::model()->findByPath('users/'.$user->username, true);
			
			//In some cases the acl id of the home folder was copied from the user. We will correct that here.
			if(!$folder->acl || $folder->acl_id==$user->acl_id){
				$folder->setNewAcl($user->id);
				$folder->user_id=$user->id;
				$folder->visible=0;
				$folder->readonly=1;
				$folder->save();
			}
			//$folder->syncFilesystem();		
			
		}
		
		$folder = Model\Folder::model()->findByPath("log", true);
		if(!$folder->acl || $folder->acl_id==\GO::modules()->files->acl_id){
			$folder->setNewAcl();
			$folder->readonly=1;
			$folder->save();
		}
		
		parent::checkDatabase($response);
	}
	
	public static function saveUser($user, $wasNew) {
		//throw new \Exception($user->getOldAttributeValue('username'));
		if($wasNew){
			$folder = Model\Folder::model()->findHomeFolder($user);			
		}elseif($user->isModified('username')){
			$folder = Model\Folder::model()->findByPath('users/'.$user->getOldAttributeValue('username'));
			if($folder)
			{
				$folder->name=$user->username;
				$folder->systemSave=true;
				//throw new \Exception($folder->path);
				$folder->save();				
			}
		}
	}
	
	public static function deleteUser($user) {
		$folder = Model\Folder::model()->findByPath('users/'.$user->username, true);
		if($folder)
			$folder->delete(true);
	}
	
	public function autoInstall() {
		return true;
	}
	
	private static $fileHandlers;
	/**
	 * 
	 * @return Filehandler\FilehandlerInterface
	 */
	public static function getAllFileHandlers(){
		if(!isset(self::$fileHandlers)){
			
			self::$fileHandlers = \GO::cache()->get('files-file-handlers');
		
			
			if(!self::$fileHandlers){

				$modules = \GO::modules()->getAllModules();

				self::$fileHandlers=array();
				foreach($modules as $module){
					self::$fileHandlers = array_merge(self::$fileHandlers, $module->moduleManager->findClasses('filehandler'));
				}
				\GO::cache()->set('files-file-handlers', self::$fileHandlers);
			}
		}
		return self::$fileHandlers;
	}
	
	public function install() {
		parent::install();
		
		$template = new \GO\files\Model\Template();
		$template->name=\GO::t('wordtextdoc','files');
		$template->content = file_get_contents(\GO::modules()->files->path.'install/templates/empty.docx');
		$template->extension='docx';
		$template->save();	
		$template->acl->addGroup(\GO::config()->group_internal, \GO\Base\Model\Acl::READ_PERMISSION);
		
		
		$template = new \GO\files\Model\Template();
		$template->name=\GO::t('ootextdoc','files');
		$template->content = file_get_contents(\GO::modules()->files->path.'install/templates/empty.odt');
		$template->extension='odt';
		$template->save();	
		$template->acl->addGroup(\GO::config()->group_internal, \GO\Base\Model\Acl::READ_PERMISSION);
	}
	
}