<?php


namespace GO\Postfixadmin\Controller;


class DomainController extends \GO\Base\Controller\AbstractModelController {

	protected $model = 'GO\Postfixadmin\Model\Domain';
	
	protected function remoteComboFields() {
		return array('user_id'=>'$model->user->name');
	}
	
	
	protected function getStoreParams($params) {
		return \GO\Base\Db\FindParams::newInstance()->permissionLevel(\GO\Base\Model\Acl::WRITE_PERMISSION);
	}
	
	public function formatStoreRecord($record, $model, $store) {
		$record['user_name']=$model->user ? $model->user->name : 'unknown';
		
		$domainInfo = \GO\Postfixadmin\Model\Mailbox::model()->find(
			\GO\Base\Db\FindParams::newInstance()
				->single()
				->select('COUNT(*) AS mailbox_count, SUM(`usage`) AS `usage`, SUM(`quota`) AS `quota`')
				->criteria(
					\GO\Base\Db\FindCriteria::newInstance()
						->addCondition('domain_id', $model->id)
				)
		);
		$domainInfo2 = \GO\Postfixadmin\Model\Alias::model()->find(
			\GO\Base\Db\FindParams::newInstance()
				->single()
				->select('COUNT(*) AS alias_count')
				->criteria(
					\GO\Base\Db\FindCriteria::newInstance()
						->addCondition('domain_id', $model->id)
				)
		);
		$record['usage'] = \GO\Base\Util\Number::formatSize( $domainInfo->usage * 1024 );
		$record['quota'] = \GO\Base\Util\Number::formatSize( $model->total_quota * 1024 );
		$record['used_quota'] = \GO\Base\Util\Number::formatSize( $domainInfo->quota * 1024 );
		$record['mailbox_count'] = $domainInfo->mailbox_count.' / '.$model->max_mailboxes;
		$record['alias_count'] = $domainInfo2->alias_count.' / '.$model->max_aliases;
		return $record;
	}
	
	protected function beforeSubmit(&$response, &$model, &$params) {
		
		if(isset($params['total_quota'])){
			$model->total_quota=  \GO\Base\Util\Number::unlocalize($params['total_quota'])*1024;
			unset($params['total_quota']);
		}
		
		if(isset($params['default_quota'])){
			$model->default_quota=  \GO\Base\Util\Number::unlocalize($params['default_quota'])*1024;
			unset($params['default_quota']);
		}
		
		return parent::beforeSubmit($response, $model, $params);
	}
	
	protected function afterLoad(&$response, &$model, &$params) {
		
		$response['data']['default_quota'] = \GO\Base\Util\Number::localize($model->default_quota/1024);
		$response['data']['total_quota'] = \GO\Base\Util\Number::localize($model->total_quota/1024);
		return $response;
	}
	
	
	protected function actionGetUsage($params){
		$domains = json_decode($params['domains']);
						
		$response['success']=true;
		
		$record = \GO\Postfixadmin\Model\Mailbox::model()->find(
			\GO\Base\Db\FindParams::newInstance()
				->single()
				->select('SUM(`usage`) AS `usage`')
				->joinModel(array(
	 			'model'=>'GO\Postfixadmin\Model\Domain',
	 			'localField'=>'domain_id',
	 			'tableAlias'=>'d'	
				))
				->criteria(
					\GO\Base\Db\FindCriteria::newInstance()
						->addInCondition('domain', $domains,'d')
				)
		);
		
		$response['usage']=$record->usage;
		
		return $response;		
	}
	
}

