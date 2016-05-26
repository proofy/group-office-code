<?php

/**
 * Copyright Intermesh
 *
 * This file is part of Group-Office. You should have received a copy of the
 * Group-Office license along with Group-Office. See the file /LICENSE.TXT
 *
 * If you have questions write an e-mail to info@intermesh.nl
 *
 * @version $Id: Folder.php 7607 2011-09-01 15:44:36Z <<USERNAME>> $
 * @copyright Copyright Intermesh
 * @author <<FIRST_NAME>> <<LAST_NAME>> <<EMAIL>>@intermesh.nl
 */

/**
 * The Folder model
 *

 * @property int $user_id
 * @property int $folder_id
 */

namespace GO\Files\Model;


class FolderNotification extends \GO\Base\Db\ActiveRecord {


	/**
	 * Returns a static model of itself
	 *
	 * @param String $className
	 * @return FolderNotification
	 */
	public static function model($className=__CLASS__) {
		return parent::model($className);
	}


	/**
	 * Returns the table name
	 */
	public function tableName() {
		return 'fs_notifications';
	}

	public function primaryKey() {
		return array('user_id', 'folder_id');
	}

	/**
	 * Get users to notify by folder id
	 *
	 * @param int $folder_id
	 *
	 * @return array
	 */
	public static function getUsersToNotify($folder_id) {
		$stmt = self::model()->findByAttribute('folder_id', $folder_id);
		$users = array();
		while ($fnRow = $stmt->fetch()) {
			//ignore user who changed file(s)
			if ($fnRow->user_id == \GO::user()->id)
				continue;
			$users[] = $fnRow->user_id;
		}
		return $users;
	}

	/**
	 *
	 * @param int|array $folders
	 * @param type $type
	 * @param type $arg1
	 * @param type $arg2
	 */
	public function storeNotification($folders, $type, $arg1, $arg2 = '') {

		if (is_numeric($folders))
			$folders = array((int)$folders);
		elseif (is_array($folders))
			$folders = array_map('intval', $folders);
		else
			return false;

		$users = array();
		foreach ($folders as $folder_id) {
			$users+= self::getUsersToNotify($folder_id);
		}

		$users = array_unique($users);

		if (count($users)) {
			foreach($users as $user_id) {
				$notification = new FolderNotificationMessage();
				$notification->type = $type;
				$notification->arg1 = $arg1;
				$notification->arg2 = $arg2;
				$notification->user_id = $user_id;
				$notification->save();
			}
		}
	}

	public function notifyUser() {

		$notifications = FolderNotificationMessage::getNotifications();
		if (empty($notifications))
			return false;

		//userCache
		$users = array();
		$messages = array();

		foreach ($notifications as $notification) {
			if (!isset($messages[$notification->type]))
				$messages[$notification->type] = array();

			if (!isset($users[$notification->modified_user_id])) {
				$user = \GO::user()->findByPk($notification->modified_user_id);
				if ($user)
					$users[$notification->modified_user_id] = $user->getName();
				else
					$users[$notification->modified_user_id] = \GO::t('deletedUser', 'files');
			}

			//switch status of notification to sent
			$notification->status = 1;
			$notification->save();

			switch ($notification->type) {
				case FolderNotificationMessage::ADD_FOLDER:
					$messages[$notification->type][] = sprintf(
						\GO::t('notifyFolderAdd', 'files'),
						$notification->arg1,
						$notification->arg2,
						$users[$notification->modified_user_id]
					);
					break;
				case FolderNotificationMessage::RENAME_FOLDER:
					$messages[$notification->type][] = sprintf(
						\GO::t('notifyFolderRename', 'files'),
						$notification->arg1,
						$notification->arg2,
						$users[$notification->modified_user_id]
					);
					break;
				case FolderNotificationMessage::MOVE_FOLDER:
					$messages[$notification->type][] = sprintf(
						\GO::t('notifyFolderMove', 'files'),
						$notification->arg1,
						$notification->arg2,
						$users[$notification->modified_user_id]
					);
					break;
				case FolderNotificationMessage::DELETE_FOLDER:
					$messages[$notification->type][] = sprintf(
						\GO::t('notifyFolderDelete', 'files'),
						$notification->arg1,
						$users[$notification->modified_user_id]
					);
					break;
				case FolderNotificationMessage::ADD_FILE:
					$messages[$notification->type][] = sprintf(
						\GO::t('notifyFileAdd', 'files'),
						$notification->arg1,
						$notification->arg2,
						$users[$notification->modified_user_id]
					);
					break;
				case FolderNotificationMessage::RENAME_FILE:
					$messages[$notification->type][] = sprintf(
						\GO::t('notifyFileRename', 'files'),
						$notification->arg1,
						$notification->arg2,
						$users[$notification->modified_user_id]
					);
					break;
				case FolderNotificationMessage::MOVE_FILE:
					$messages[$notification->type][] = sprintf(
						\GO::t('notifyFileMove', 'files'),
						$notification->arg1,
						$notification->arg2,
						$users[$notification->modified_user_id]
					);
					break;
				case FolderNotificationMessage::DELETE_FILE:
					$messages[$notification->type][] = sprintf(
						\GO::t('notifyFileDelete', 'files'),
						$notification->arg1,
						$users[$notification->modified_user_id]
					);
					break;
				case FolderNotificationMessage::UPDATE_FILE:
					$messages[$notification->type][] = sprintf(
						\GO::t('notifyFileUpdate', 'files'),
						$notification->arg1,
						$users[$notification->modified_user_id]
					);
					break;
			}
		}

		//TODO: create emailBody
		$emailBody = '';
		$types = array_keys($messages);
		foreach ($types as $type) {
			foreach ($messages[$type] as $message) {
				$emailBody.= $message . "\n";
			}
		}

		$message = new \GO\Base\Mail\Message();
		$message->setSubject(\GO::t('notificationEmailSubject', 'files'))
				->setTo(array(\GO::user()->email=>\GO::user()->name))
				->setFrom(array(\GO::config()->webmaster_email=>\GO::config()->title))
				->setBody($emailBody);
		\GO\Base\Mail\Mailer::newGoInstance()->send($message);
	}
}