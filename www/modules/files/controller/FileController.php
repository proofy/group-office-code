<?php


namespace GO\Files\Controller;


class FileController extends \GO\Base\Controller\AbstractModelController {

	protected $model = 'GO\Files\Model\File';
	
	protected function allowGuests() {
		return array('download'); //permissions will be checked manually in that action
	}
    
	/**
	 * Will calculate the used diskspace per user
	 * If no ID is passed diskspace will be recalculated for all user
	 * @param integer $id id of the user to recalculate used space for
	 */
	protected function actionRecalculateDiskUsage($id=false) {
		
		\GO::session()->closeWriting();
						
		if(!empty($id)) {
			$user = \GO\Base\Model\User::model()->findByPk($id);
			if(!empty($user) && $user->calculatedDiskUsage()->save())
				echo $user->getName() . ' uses ' . $user->disk_usage. "<br>\n";
		} else {
			$users = \GO\Base\Model\User::model()->find();
			foreach($users as $user) {
				if($user->calculatedDiskUsage()->save())
					echo $user->getName() . ' uses ' . $user->disk_usage. "<br>\n";
			}
		}
	}
	
	protected function actionDisplay($params) {
		
		//custom fields send path as ID.
		if(!empty($params['id']) && !is_numeric($params['id'])){
			$file = \GO\Files\Model\File::model()->findByPath($params['id']);
			$params['id']=$file->id;
		}
		
		return parent::actionDisplay($params);
	}

	
	protected function afterDisplay(&$response, &$model, &$params) {

		$response['data']['path'] = $model->path;
		$response['data']['size'] = \GO\Base\Util\Number::formatSize($model->fsFile->size());
		$response['data']['extension'] = strtolower($model->fsFile->extension());
		$response['data']['type'] = \GO::t($response['data']['extension'], 'base', 'filetypes');
		
		$response['data']['locked_user_name']=$model->lockedByUser ? $model->lockedByUser->name : '';
		$response['data']['locked']=$model->isLocked();
		$response['data']['unlock_allowed']=$model->unlockAllowed();
		

		if (!empty($model->random_code) && time() < $model->expire_time) {
			$response['data']['expire_time'] = \GO\Base\Util\Date::get_timestamp(\GO\Base\Util\Date::date_add($model->expire_time, -1),false);
			$response['data']['download_link'] = $model->emailDownloadURL;
		} else {
			$response['data']['expire_time'] = "";
			$response['data']['download_link'] = "";
		}
		
		$response['data']['url']=\GO::url('files/file/download',array('id'=>$model->id), false, true);

		if ($model->fsFile->isImage())
			$response['data']['thumbnail_url'] = $model->thumbURL;
		else
			$response['data']['thumbnail_url'] = "";
		
		$response['data']['handler']='startjs:function(){'.$model->getDefaultHandler()->getHandler($model).'}:endjs';
		
		try{
			if(\GO::modules()->filesearch){
				$filesearch = \GO\Filesearch\Model\Filesearch::model()->findByPk($model->id);
//				if(!$filesearch){
//					$filesearch = \GO\Filesearch\Model\Filesearch::model()->createFromFile($model);
//				}
				if($filesearch){
					$response['data']=array_merge($filesearch->getAttributes('formatted'), $response['data']);
				

					if (!empty($params['query_params'])) {
						$qp = json_decode($params['query_params'], true);
						if (isset($qp['content_all'])){

							$c = new \GO\Filesearch\Controller\Filesearch();

							$response['data']['text'] = $c->highlightSearchParams($qp, $response['data']['text']);
						}
					}
				}else
				{
					$response['data']['text'] = \GO::t('notIndexedYet','filesearch');
				}
			}
		}
		catch(\Exception $e){
			\GO::debug((string) $e);
			
			$response['data']['text'] = "Index out of date. Please rebuild it using the admin tools.";
		}

		return parent::afterDisplay($response, $model, $params);
	}

	protected function afterLoad(&$response, &$model, &$params) {

		$response['data']['path'] = $model->path;
		$response['data']['size'] = \GO\Base\Util\Number::formatSize($model->fsFile->size());
		$response['data']['extension'] = strtolower($model->fsFile->extension());
		$response['data']['type'] = \GO::t($response['data']['extension'], 'base', 'filetypes');
		
		$response['data']['name']=$model->fsFile->nameWithoutExtension();
		
		if (\GO::modules()->customfields)
			$response['customfields'] = \GO\Customfields\Controller\CategoryController::getEnabledCategoryData("GO\Files\Model\File", $model->folder_id);
		
		
		$fh = \GO\Files\Model\FileHandler::model()->findByPk(
						array('extension'=>$model->extension, 'user_id'=>\GO::user()->id));
		if($fh){
			$fileHandler = new $fh->cls;
			
			$response['data']['handlerCls']=$fh->cls;
			$response['data']['handlerName']=$fileHandler->getName();
		}else
		{
			$response['data']['handlerCls']="";
			$response['data']['handlerName']="";
		}
		

		return parent::afterLoad($response, $model, $params);
	}
	
	protected function beforeSubmit(&$response, &$model, &$params) {
		
		if(isset($params['name']))		
			$params['name'].='.'.$model->fsFile->extension();		
		
		if(isset($params['lock'])){
			//GOTA sends lock parameter It does not know the user ID.
			$model->locked_user_id=empty($params['lock']) ? 0 : \GO::user()->id;
		}
		
		
		$fh = \GO\Files\Model\FileHandler::model()->findByPk(
						array('extension'=>strtolower($model->extension), 'user_id'=>\GO::user()->id));
		
		if(!$fh)
			$fh = new \GO\Files\Model\FileHandler();
		
		$fh->extension=strtolower($model->extension);
		
		if(isset($params['handlerCls']))
			$fh->cls=$params['handlerCls'];
		
		if(empty($params['handlerCls']))
			$fh->delete();
		else
			$fh->save();
		
		return parent::beforeSubmit($response, $model, $params);
	}
	
	protected function actionHandlers($params){
		if(!empty($params['path'])){
			$folder = \GO\Files\Model\Folder::model()->findByPath(dirname($params['path']));
			$file = $folder->hasFile(\GO\Base\Fs\File::utf8Basename($params['path']));
		}else
		{
			$file = \GO\Files\Model\File::model()->findByPk($params['id'], false, true);
		}

		if(empty($params['all'])){
			$fileHandlers = array($file->getDefaultHandler());
		}else
		{
			$fileHandlers = $file->getHandlers();
		}
//	var_dump($fileHandlers);
		
		$store = new \GO\Base\Data\ArrayStore();
		
		foreach($fileHandlers as $fileHandler){	
			$store->addRecord(array(
					'name'=>$fileHandler->getName(),
					'handler'=>$fileHandler->getHandler($file),
					'iconCls'=>$fileHandler->getIconCls(),
					'cls'=>  get_class($fileHandler),
					'extension'=>$file->extension
			));	
		}	
		
		return $store->getData();		
	}
	
	protected function actionSaveHandler($params){
//		\GO::config()->save_setting('fh_'.$, $value)
		
		$fh = \GO\Files\Model\FileHandler::model()->findByPk(
						array('extension'=>strtolower($params['extension']), 'user_id'=>\GO::user()->id));
		
		if(!$fh)
			$fh = new \GO\Files\Model\FileHandler();
		
		$fh->extension=strtolower($params['extension']);
		$fh->cls=$params['cls'];
		return array('success'=>empty($params['cls']) ? $fh->delete() : $fh->save());
	}
	

	protected function actionDownload($params) {
		\GO::session()->closeWriting();
		
		if(isset($params['path'])){
			$folder = \GO\Files\Model\Folder::model()->findByPath(dirname($params['path']));
			$file = $folder->hasFile(\GO\Base\Fs\File::utf8Basename($params['path']));
		}else
		{
			$file = \GO\Files\Model\File::model()->findByPk($params['id'], false, true);
		}
		
		if(!$file)
			throw new \GO\Base\Exception\NotFound();
		
		if(!empty($params['random_code'])){
			if($file->random_code!=$params['random_code'])
				throw new \GO\Base\Exception\NotFound();
			
			if(time()>$file->expire_time)
				throw new \Exception(\GO::t('downloadLinkExpired', 'files'));				
		}else
		{
			if(!\GO::user())
				\GO\Base\Util\Http::basicAuth();
				
			if(!$file->checkPermissionLevel(\GO\Base\Model\Acl::READ_PERMISSION))
				throw new \GO\Base\Exception\AccessDenied();
		}

		
		// Show the file inside the browser or give it as a download
		$inline = true; // Defaults to show inside the browser
		if(isset($params['inline']) && $params['inline'] == "false")
			$inline = false;

		\GO\Base\Util\Http::outputDownloadHeaders($file->fsFile, $inline, !empty($params['cache']));
		$file->fsFile->output();
	}

	/**
	 *
	 * @param type $params 
	 * @todo
	 */
	protected function actionCreateDownloadLink($params){
		
		$response=array();
		
		$file = \GO\Files\Model\File::model()->findByPk($params['id']);
		
		$url = $file->getEmailDownloadURL(true,\GO\Base\Util\Date::date_add($params['expire_time'],1),$params['delete_when_expired']);
		
		$response['url']=$url;
		$response['success']=true;
		
		return $response;
		
	}	
	
	/**
	 * This action will generate multiple Email Download link and return a JSON
	 * response with the generated links in the email subject
	 * @param array $params
	 * - string ids: json encode file ids to mail
	 * - timestamp expire_time: chosen email link expire time 
	 * - int template_id: id of used template
	 * - int alias_id: id of alias to mail from
	 * - string content_type : html | plain  
	 * @return string Json response
	 */
	protected function actionEmailDownloadLink($params){

		$files = \GO\Files\Model\File::model()->findByAttribute('id', json_decode($params['ids']));
		
		$html=$params['content_type']=='html';
		$bodyindex = $html ? 'htmlbody' : 'plainbody';
		$lb = $html ? '<br />' : "\n";
		$text = $html ? \GO::t('clickHereToDownload', "files") : \GO::t('copyPasteToDownload', "files");

		$linktext = $html ? "<ul>" : $lb;
		
		foreach($files as $file) {
			$url = $file->getEmailDownloadURL($html,\GO\Base\Util\Date::date_add($params['expire_time'],1),$params['delete_when_expired']);
			$linktext .= $html ?  '<li><a href="'.$url.'">'.$file->name.'</a></li>'.$lb : $url.$lb;
		}
		$linktext .= $html ? "</ul>" : "\n";
		$text .= ' ('.\GO::t('possibleUntil','files').' '.\GO\Base\Util\Date::get_timestamp(\GO\Base\Util\Date::date_add($file->expire_time,-1), false).')'.$lb;
		$text .= $linktext;
		
		if($params['template_id'] && ($template = \GO\Addressbook\Model\Template::model()->findByPk($params['template_id']))){
			$message = \GO\Email\Model\SavedMessage::model()->createFromMimeData($template->content);
	
			$response['data']=$message->toOutputArray($html, true);
			
			if(strpos($response['data'][$bodyindex],'{body}')){
				$response['data'][$bodyindex] = \GO\Addressbook\Model\Template::model()->replaceUserTags($response['data'][$bodyindex], true);
				
				\GO\Addressbook\Model\Template::model()->htmlSpecialChars=false;
				$response['data'][$bodyindex] = \GO\Addressbook\Model\Template::model()->replaceCustomTags($response['data'][$bodyindex], array('body'=>$text));			
			}else{
				$response['data'][$bodyindex] = \GO\Addressbook\Model\Template::model()->replaceUserTags($response['data'][$bodyindex], false);
				$response['data'][$bodyindex] = $text.$response['data'][$bodyindex];
			}
				
			
		}else
		{
			$response['data'][$bodyindex]=$text;	
		}
				
		$response['data']['subject'] = \GO::t('downloadLink','files'); //.' '.$file->name;
		$response['success']=true;
		
		return $response;
	}
	
	
	public function actionRecent($params){
		
		$start = !empty($params['start']) ? $params['start'] : 0;
		$limit = !empty($params['limit']) ? $params['limit'] : 20;
		
		$store = \GO\Base\Data\Store::newInstance(\GO\Files\Model\File::model());

		$store->getColumnModel()->formatColumn('path', '$model->path', array(), array('first_name', 'last_name'));
		$store->getColumnModel()->formatColumn('weekday', '$fullDays[date("w", $model->mtime)]." ".\GO\Base\Util\Date::get_timestamp($model->mtime, false);', array('fullDays'=>\GO::t('full_days')),array('first_name', 'last_name'));
		
		$store->setStatement(\GO\Files\Model\File::model()->findRecent($start,$limit));

		$response = $store->getData();
		
		$store->setStatement(\GO\Files\Model\File::model()->findRecent());
		$response['total'] = $store->getTotal();
		
		return $response;
	}
}

