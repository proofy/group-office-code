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
 * The User model
 * 
 * @version $Id: Group.php 7607 2011-08-04 13:41:42Z mschering $
 * @copyright Copyright Intermesh
 * @author Merijn Schering <mschering@intermesh.nl>
 * @package GO.base.model
 * 
 * @property int $id
 * @property String $username
 * @property String $password
 * @property String $password_type
 * @property Boolean $enabled
 * @property String $first_name
 * @property String $middle_name
 * @property String $last_name
 * @property int $acl_id
 * @property String $time_format
 * @property String $thousands_separator
 * @property String $decimal_separator
 * @property String $currency
 * @property int $logins
 * @property int $lastlogin
 * @property int $ctime
 * @property int $max_rows_list
 * @property String $timezone
 * @property String $start_module
 * @property String $language
 * @property String $theme
 * @property int $first_weekday
 * @property String $sort_name
 * @property String $bank
 * @property String $bank_no
 * @property int $mtime
 * @property int $muser_id
 * @property Boolean $mute_sound
 * @property Boolean $mute_reminder_sound
 * @property Boolean $mute_new_mail_sound
 * @property Boolean $show_smilies
 * @property Boolean $auto_punctuation
 * @property String $list_separator
 * @property String $text_separator
 * @property int $files_folder_id
 * @property int $disk_quota The amount of diskspace the user may use in MB
 * @property int $disk_usage The diskspace used in Bytes (cache column with sum fs_files.size owned by this user)
 * @property int $mail_reminders
 * @property int $popup_reminders
 * @property int $contact_id
 * @property String $holidayset
 * 
 * @property $completeDateFormat
 * @property string $date_separator
 * @property string $date_format
 * @property string $email
 * @property \GO\Addressbook\Model\Contact $contact
 * @property string $diges
 * 
 * @method User findByPk();
 * 
 * @property Boolean $sort_email_addresses_by_time
 */


namespace GO\Base\Model;

use GO;
use GO\Base\Mail\Message;
use GO\Base\Mail\Mailer;


class User extends \GO\Base\Db\ActiveRecord {
  
	public $generatedRandomPassword = false;
	public $passwordConfirm;
	
	
	public $skip_contact_update=false;
	
	/**
	 * This variable will be set when the password is modified.
	 * 
	 * @var string 
	 */
	private $_unencryptedPassword;
	/**
	 * If this is set on a new user then it will be connected to this contact.
	 * 
	 * @var int 
	 */
	public $contact_id;
	
	/**
	 * Returns a static model of itself
	 * 
	 * @param String $className
	 * @return GO\Base\Model\User 
	 */
	public static function model($className=__CLASS__)
	{	
		return parent::model($className);
	}
	
	
	/**
	 * Create a new user 
	 * 
	 * When creating a user we also need to create a lot of default models and
	 * set permissions for this user. This function creates the user with permissions
	 * and the right models in one go.
	 * 
	 * @param array $attributes
	 * @param array $groups array of group names array('Internal','Some group');
	 * @param array $modulePermissionLevels array('calendar'=>1,'projects'=>4)
	 * @return User 
	 */
	public static function newInstance($attributes, $groups=array(), $modulePermissionLevels=array()){
		$user = new User();
		$user->setAttributes($attributes);
		$user->save();

		$user->addToGroups($groups);	
		
		foreach($modulePermissionLevels as $module=>$permissionLevel){
			GO::modules()->$module->acl->addUser($user->id, $permissionLevel);
		}
		
		$user->checkDefaultModels();
		
		return $user;
	}

	public function aclField() {
		return 'acl_id';
	}

	public function tableName() {
		return 'go_users';
	}

	public function relations() {
		return array(
			'contact' => array('type' => self::HAS_ONE, 'model' => 'GO\Addressbook\Model\Contact', 'field' => 'go_user_id'),
			'reminders' => array('type'=>self::MANY_MANY, 'model'=>'GO\Base\Model\Reminder', 'field'=>'user_id', 'linkModel' => 'GO\Base\Model\ReminderUser'),
			'groups' => array('type'=>self::MANY_MANY, 'model'=>'GO\Base\Model\Group', 'field'=>'user_id', 'linkModel' => 'GO\Base\Model\UserGroup'),
			'_workingWeek' => array('type' => self::HAS_ONE, 'model' => 'GO\Base\Model\WorkingWeek', 'field' => 'user_id')
		);
	}
	
	public function getWorkingWeek(){
		$ww = $this->_workingWeek;
		if(!$ww){
			$ww = new WorkingWeek();
			$ww->user_id=$this->id;
			$ww->save();
		}
		return $ww;
	}
	
	protected function getLocalizedName() {
		return GO::t('strUser');
	}

	public function customfieldsModel() {
		return 'GO\Users\Customfields\Model\User';
	}
	
	public function hasFiles(){
		return false;
	}
	
	public function hasLinks() {
		return true;
	}
	
	public function getAttributes($outputType = 'formatted') {
		
		$attr = parent::getAttributes($outputType);
		$attr['name']=$this->getName();
		
		return $attr;
	}
	
	public function attributeLabels() {
		$labels = parent::attributeLabels();
		$labels['passwordConfirm']=GO::t("passwordConfirm");
		return $labels;
	}
	
	/**
	 * Getter function for the ACL function
	 * @return int 
	 */
	protected function getUser_id(){
		return $this->id;
	}

	public function init() {
		$this->columns['email']['regex'] = \GO\Base\Util\String::get_email_validation_regex();
		$this->columns['email']['required'] = true;

		$this->columns['password']['required'] = true;
		$this->columns['username']['required'] = true;
		$this->columns['username']['regex'] = '/^[A-Za-z0-9_\-\.\@]*$/';
		
		$this->columns['first_name']['required'] = true;

		$this->columns['last_name']['required'] = true;
		$this->columns['timezone']['required']=true;
		
		$this->columns['lastlogin']['gotype']='unixtimestamp';
		$this->columns['disk_quota']['gotype']='number';
		$this->columns['disk_quota']['decimals']=0;
		return parent::init();
	}
	
	public function getFindSearchQueryParamFields($prefixTable = 't', $withCustomFields = true) {
		$fields=array(
				"CONCAT(t.first_name,' ',t.middle_name,' ',t.last_name)", 
				$prefixTable.".email",
				$prefixTable.".username"
				);
		
		if($withCustomFields && $this->customfieldsRecord)
		{
			$fields = array_merge($fields, $this->customfieldsRecord->getFindSearchQueryParamFields('cf'));
		}
		
		return $fields;
	}

	private function _maxUsersReached() {
		return GO::config()->max_users > 0 && $this->count() >= GO::config()->max_users;
	}

        /**
	 * This method will (re)calculate the used diskspace for this user
	 * @param integer $bytes The amount of bytes to add to the users used diskspace (negative for substraction)
	 * @return User itself for chaining eg. $user->calculatedDiskUsage()->save()
	 */
	public function calculatedDiskUsage($bytes = false) {
		if (GO::modules()->isInstalled('files')) {
			if (!$bytes) { //recalculated
				$fp = \GO\Base\Db\FindParams::newInstance()->criteria(\GO\Base\Db\FindCriteria::newInstance()->addCondition('user_id', $this->id));
				$sumFilesize = Grouped::model()->load('GO\Files\Model\File', 'user_id', 'SUM(size) as total_size', $fp)->fetch();
				//GO::debug($sumFilesize->total_size);
				if ($sumFilesize)
					$this->disk_usage = $sumFilesize->total_size;
			} else {
				$this->disk_usage+=$bytes;
			}
		} else
			throw new \Exceptions('Can not calculated diskusage without the files module');
		return $this;
	}
	
	/**
	 * Get the user disk quota in bytes
	 * @return int amount of bytes the user may use
	 */
	public function getDiskQuota(){
		return $this->disk_quota*1024*1024;
	}

	public function validate() {
		
		if($this->max_rows_list > 250)
				$this->setValidationError('max_rows_list', GO::t('maxRowslistTooHigh'));
		
		if($this->isModified('password') && isset($this->passwordConfirm) && $this->passwordConfirm!=$this->password){
			$this->setValidationError('passwordConfirm', GO::t('passwordMatchError'));
		}
		
		if($this->isModified('disk_quota') && GO::user()->getModulePermissionLevel('users') < Acl::MANAGE_PERMISSION)
			$this->setValidationError('disk_quota', 'Only managers of the "users"  module may modify disk quota');
		
		if(GO::config()->password_validate && $this->isModified('password')){
			if(!\GO\Base\Util\Validate::strongPassword($this->password)){
				$this->setValidationError('password', \GO\Base\Util\Validate::getPasswordErrorString($this->password));
			}
		}

		if ($this->isNew && $this->_maxUsersReached())				
			$this->setValidationError('form', GO::t('max_users_reached', 'users'));
			
		if (!GO::config()->allow_duplicate_email) {
			$existing = $this->findSingleByAttribute('email', $this->email);
			if (($this->isNew && $existing) || $existing && $existing->id != $this->id )
				$this->setValidationError('email', GO::t('error_email_exists', 'users'));
		}

		$existing = $this->findSingleByAttribute('username', $this->username);
		if (($this->isNew && $existing) || $existing && $existing->id != $this->id )
			$this->setValidationError('username', GO::t('error_username_exists', 'users'));

		if (empty($this->password) && $this->isNew) {
			$this->password = \GO\Base\Util\String::randomPassword();
			$this->generatedRandomPassword = true;
		}

		return parent::validate();
	}
	
	public function buildFilesPath() {
		return 'users/'.$this->username;
	}
	
	protected function beforeSave(){
		
		if($this->isNew){
			$holiday = Holiday::localeFromCountry($this->language);
			
		if($holiday !== false)
			$this->holidayset = $holiday; 
		}
		
		if(!$this->isNew && empty($this->holidayset) && ($contact = $this->createContact())){
			$holiday = Holiday::localeFromCountry($contact->country);

			if($holiday !== false)
				$this->holidayset = $holiday; 
		}
				
		if($this->isModified('password') && !empty($this->password)){
			$this->_unencryptedPassword=$this->password;
			$this->password=crypt($this->password);
			$this->password_type='crypt';
			
			$this->digest = md5($this->username.":".GO::config()->product_name.":".$this->_unencryptedPassword);
		}
		
		return parent::beforeSave();
	}	
		
	/**
	 * When the password was just modified. You can call this function to get the
	 * plain text password.
	 * 
	 * @return string 
	 */
	public function getUnencryptedPassword(){
		return isset($this->_unencryptedPassword) ? $this->_unencryptedPassword : false;
	}
	

	protected function afterSave($wasNew) {

		if($wasNew){
			$everyoneGroup = Group::model()->findByPk(GO::config()->group_everyone);		
			$everyoneGroup->addUser($this->id);			
			
			$this->acl->user_id=$this->id;
			$this->acl->save();
			
			if(!empty(GO::config()->register_user_groups)){
				$groups = explode(',',GO::config()->register_user_groups);
				foreach($groups as $groupName){
					$group = Group::model()->findSingleByAttribute('name', trim($groupName));
					if($group)
						$group->addUser($this->id);
				}
			}
			
			$this->_setVisibility();
		}		
		
		if(!$this->skip_contact_update && ($this->isNew || $this->isModified(array('first_name','middle_name','last_name','email'))))
			$this->createContact();
		
		return parent::afterSave($wasNew);
	}
	
	private function _setVisibility(){
		if(!empty(GO::config()->register_visible_user_groups)){
			$groups = explode(',',GO::config()->register_visible_user_groups);
			foreach($groups as $groupName){
				$group = Group::model()->findSingleByAttribute('name', trim($groupName));
				if($group)
					$this->acl->addGroup($group->id, Acl::MANAGE_PERMISSION);
			}
		}
	}
	
	/**
	 * Makes shure that this model's user has all the default models it should have.
	 */
	public function checkDefaultModels(){
		$oldIgnore = GO::setIgnoreAclPermissions(true);
	  $defaultModels = AbstractUserDefaultModel::getAllUserDefaultModels($this->id);	
		foreach($defaultModels as $model){
			$model->getDefault($this);
		}		
		GO::setIgnoreAclPermissions($oldIgnore);
	}
	
	protected function beforeDelete() {
		if($this->id==1){
			throw new \Exception(GO::t('deletePrimaryAdmin','users'));
		}elseif($this->id==GO::user()->id){
			throw new \Exception(GO::t('deleteYourself','users'));			
		}else
		{
			return parent::beforeDelete();
		}
	}
	
	protected function afterDelete() {
		
		
		//delete all acl records
		$stmt = AclUsersGroups::model()->find(array(
				"by"=>array(array('user_id',$this->id))
		));
		
		while($r = $stmt->fetch())
			$r->delete();
		
		$defaultModels = AbstractUserDefaultModel::getAllUserDefaultModels();
	
		foreach($defaultModels as $model){
			$model->deleteByAttribute('user_id',$this->id);
		}
//		deprecated. It's inefficient and can be done with listeners
//		GO::modules()->callModuleMethod('deleteUser', array(&$this));

		return parent::afterDelete();
	}
		
	

	/**
	 *
	 * @return String Full formatted name of the user
	 */
	public function getName($sort=false) {
		
		if(!$sort){
			if(GO::user()){
				$sort = GO::user()->sort_name;
			}else
			{
				$sort = 'first_name';
			}
		}
		
		return \GO\Base\Util\String::format_name($this->last_name, $this->first_name, $this->middle_name,$sort);
	}
	
	/**
	 *
	 * @return String Short name of the user 
	 * Example: Foo Bar will output FB
	 */
	public function getShortName() {
		
		if(!empty($this->first_name))
			$short = substr($this->first_name,0,1);  
		
		if(!empty($this->last_name))
			$short .= substr($this->last_name,0,1);  
		
		return strtoupper($short);
	}

	/**
	 * Returns an array of user group id's
	 * 
	 * @return Array 
	 */
	public static function getGroupIds($userId) {
		$user = GO::user();
		if ($user && $userId == $user->id) {
			if (!isset(GO::session()->values['user_groups'])) {
				GO::session()->values['user_groups'] = array();

				$stmt= UserGroup::model()->find(
								\GO\Base\Db\FindParams::newInstance()
								->select('t.group_id')
								->criteria(\GO\Base\Db\FindCriteria::newInstance()
												->addCondition("user_id", $userId))
								);
				while ($r = $stmt->fetch()) {
					GO::session()->values['user_groups'][] = $r->group_id;
				}
			}
		
			return GO::session()->values['user_groups'];
		} else {
			$ids = array();
			$stmt= UserGroup::model()->find(
								\GO\Base\Db\FindParams::newInstance()
								->select('t.group_id')
								->debugSql()
								->criteria(\GO\Base\Db\FindCriteria::newInstance()
												->addCondition("user_id", $userId))
								);
			
			while ($r = $stmt->fetch()) {
				$ids[] = $r->group_id;
			}
			return $ids;
		}
	}
	
	/**
	 * Get the default group ID's for a new user.
	 * 
	 * @return array
	 */
	public static function getDefaultGroupIds(){
		$groupIds=array();
		if(!empty(GO::config()->register_user_groups)){
			$groups = explode(',',GO::config()->register_user_groups);
			foreach($groups as $groupName){
				$group = GO\Base\Model\Group::model()->findSingleByAttribute('name', trim($groupName));
				$groupIds[]=$group->id;
			}
		}

		if(!in_array(GO::config()->group_everyone, $groupIds))
		{
			$groupIds[]=GO::config()->group_everyone;
		}
		
		return $groupIds;
	}
	
	
	/**
	 * Get the default group ID's for a new user.
	 * 
	 * @return array
	 */
	public static function getDefaultVisibleGroupIds(){
		$groupIds=array();
		if(!empty(GO::config()->register_visible_user_groups)){
			$groups = explode(',',GO::config()->register_visible_user_groups);
			foreach($groups as $groupName){
				$group = GO\Base\Model\Group::model()->findSingleByAttribute('name', trim($groupName));
				$groupIds[]=$group->id;
			}
		}
		
		return $groupIds;
	}
	
	
	
	
	/**
	 * Check if the user is member of the admin group
	 * 
	 * @return boolean 
	 */
	public function isAdmin() {
		return in_array(GO::config()->group_root, User::getGroupIds($this->id));
	}

	
	/**
	 * Get the user's permission level for a given module.
	 * 
	 * @param string $moduleId
	 * @return int 
	 */
	public function getModulePermissionLevel($moduleId) {
		if (GO::modules()->$moduleId)
			return GO::modules()->$moduleId->permissionLevel;
		else
			return false;
	}
	
	private $_completeDateFormat;
	
	protected function getCompleteDateFormat(){
		if(!isset($this->_completeDateFormat))
			$this->_completeDateFormat=$this->date_format[0].$this->date_separator.$this->date_format[1].$this->date_separator.$this->date_format[2];
		return $this->_completeDateFormat;
	}
	
	
	/**
	 * Check if the password is correct for this user.
	 * 
	 * @param string $password
	 * @return boolean 
	 */
	public function checkPassword($password){

		if ($this->password_type == 'crypt') {
			if (crypt($password, $this->password) != $this->password) {
				return false;
			}
		} else {
			//pwhash is not set yet. We're going to use the old md5 hashed password
			if (md5($password) != $this->password) {
				return false;
			} else {				
				$this->password=$password;
				$oldIgnore=GO::setIgnoreAclPermissions(true);
				$this->save();				
				GO::setIgnoreAclPermissions($oldIgnore);
			}
		}
		
		$digest = md5($this->username.":".GO::config()->product_name.":".$password);
		if($digest != $this->digest)
		{
			$this->digest=$digest;
			$this->save(true);
		}
		
		return true;
	}	
	
	public function defaultAttributes() {
		$attr = parent::defaultAttributes();
		
		$attr['language']=GO::config()->language;
		$attr['date_format']=GO::config()->default_date_format;
		$attr['date_separator']=GO::config()->default_date_separator;
		$attr['theme']=GO::config()->theme;
		$attr['timezone']=GO::config()->default_timezone;
		$attr['first_weekday']=GO::config()->default_first_weekday;
		$attr['currency']=GO::config()->default_currency;
		$attr['decimal_separator']=GO::config()->default_decimal_separator;
		$attr['thousands_separator']=GO::config()->default_thousands_separator;
		$attr['time_format']=GO::config()->default_time_format;
		$attr['sort_name']=GO::config()->default_sort_name;
		$attr['max_rows_list']=GO::config()->default_max_rows_list;
		$attr['disk_quota']=GO::config()->default_diskquota;
		
		
		return $attr;
	}
	
	/**
	 * Get the contact model of this user. All the user profiles are stored in the
	 * addressbook.
	 * 
	 * @return \GO\Addressbook\Model\Contact 
	 */
	public function createContact(){
		if (GO::modules()->isInstalled("addressbook")) {
			
			if(!empty($this->contact_id)){
				//this is for old databases
				$contact = \GO\Addressbook\Model\Contact::model()->findByPk($this->contact_id);
				if($contact){
					$contact->go_user_id=$this->id;
					$contact->first_name = $this->first_name;
					$contact->middle_name = $this->middle_name;
					$contact->last_name = $this->last_name;
					$contact->email = $this->email;
					
					if($contact->isModified())
						$contact->save(true);
					
					return $contact;
				}
			}
			
			$contact = $this->contact();
			if (!$contact) {
				$contact = new \GO\Addressbook\Model\Contact();
				$addressbook = \GO\Addressbook\Model\Addressbook::model()->getUsersAddressbook();
				$contact->go_user_id = $this->id;
				$contact->addressbook_id = $addressbook->id;				
			}			
			
			$contact->first_name = $this->first_name;
			$contact->middle_name = $this->middle_name;
			$contact->last_name = $this->last_name;
			$contact->email = $this->email;

			if($contact->isNew || $contact->isModified()){
				$contact->skip_user_update=true;
				$contact->save(true);
			}
			
			return $contact;
		}else
		{
			return false;
		}
	}

	protected function remoteComboFields() {
		return array(
				'user_id' => '$model->name'
		);
	}
	
	/**
	 * Add the user to user groups.
	 * 
	 * @param string[] $groupNames
	 * @param boolean $autoCreate 
	 */
	public function addToGroups(array $groupNames, $autoCreate=false){		
		foreach($groupNames as $groupName){
			$group = Group::model()->findSingleByAttribute('name', $groupName);
			
			if(!$group && $autoCreate){
				$group = new Group();
				$group->name = $groupName;
				$group->save();
			}
			
			if($group)
				$group->addUser($this->id);
		}
	}
	
	/**
	 *
	 * @param boolean $internal Use go to reset the password(internal) or use a website/webpage to reset the password
	 */
	public function sendResetPasswordMail($siteTitle=false,$url=false,$fromName=false,$fromEmail=false){
		$message = \GO\Base\Mail\Message::newInstance();
		$message->setSubject(GO::t('lost_password_subject','base','lostpassword'));
		
		if(!$siteTitle)
			$siteTitle=GO::config()->title;
		
		if(!$url){
			$url=GO::url("auth/resetPassword", array("email"=>$this->email, "usertoken"=>$this->getSecurityToken()),false);
//			$url = GO::config()->full_url."index.php".$url;		
		}else{
			$url=\GO\Base\Util\Http::addParamsToUrl($url, array("email"=>$this->email, "usertoken"=>$this->getSecurityToken()),false);
		}
		//$url="<a href='".$url."'>".$url."</a>";
		
		if(!$fromName)
			$fromName = GO::config()->title;
		
		if(!$fromEmail){
			$fromEmail = GO::config()->webmaster_email;
		}

		$emailBody = GO::t('lost_password_body','base','lostpassword');
		$emailBody = sprintf($emailBody,$this->contact->salutation, $siteTitle, $this->username, $url);
		
		$message->setBody($emailBody);
		$message->addFrom($fromEmail,$fromName);
		$message->addTo($this->email,$this->getName());

		\GO\Base\Mail\Mailer::newGoInstance()->send($message);
	}
	
	/**
	 * Send an email to the newly registrated user when he just created an account.
	 * The mail should contain a welcome message and a username and password
	 * @param string $view path to a template for the email. If the view is not set or
	 * not found the default email body will be loaded from groupoffice
	 * @param string $title title of email
	 * @param array $_data this array will be explode to the view. if the view template
	 * is not found it will be ignored
	 * @return boolean true when email was send
	 */
	public function sendRegistrationMail($view=null, $title=null, $_data=array(),$message=false) {
		
		$this->password=$this->_unencryptedPassword; //to non-crypted email password
		
		if(!empty($view) && is_readable($view.'.php')) {
			$model = $this;
			if(!empty($_data))
				extract($_data, EXTR_PREFIX_SAME, 'data');
			ob_start();
			ob_implicit_flush(false);

			require($view.'.php');

			$emailBody = ob_get_clean();
			$type= 'text/html';
		} else { //fallback to register_email_body when no view
			$emailBody = GO::config()->get_setting('register_email_body') ?: GO::t('register_email_body', 'users');
			foreach ($this->getAttributes() as $key => $value) {
				if(is_string($value))
					$emailBody = str_replace('{' . $key . '}', $value, $emailBody);
			}
			$emailBody = str_replace('{url}', GO::config()->full_url, $emailBody);
			$emailBody = str_replace('{title}', GO::config()->title, $emailBody);
			$type= null;
		}
		if(!$title)
			$title=GO::config()->get_setting('register_email_subject') ?: GO::t('register_email_subject', 'users');

		if(empty($title) || empty($emailBody))
			return false;
		if(!$message) {
			$message = new Message();
			$message->addFrom(GO::config()->webmaster_email,GO::config()->title);
		}
		$message->setSubject($title)
			->setBody($emailBody, $type)
			->addTo($this->email,$this->getName());

		return Mailer::newGoInstance()->send($message);
	}
	
	/**
	 * Get a security hash that can be used for verification. For example with 
	 * reset password function. The token will change when the user's password or
	 * email address changes and when the user logs in.
	 * 
	 * @return string 
	 */
	public function getSecurityToken(){
		return md5($this->password.$this->email.$this->ctime.$this->lastlogin);
	}
	
	
	protected function getCacheAttributes() {
		return array(
				'name' => $this->name
		);
	}
}

