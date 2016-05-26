#!/usr/bin/php
<?php
require('/etc/groupoffice/config.php');
require($config['root_path'] . 'GO.php');

\GO::setIgnoreAclPermissions();
\GO::session()->setCurrentUser(1);

try {

	if (\GO::modules()->isInstalled("email")) {

		$domains = empty(\GO::config()->serverclient_domains) ? array() : explode(',', \GO::config()->serverclient_domains);

		if(!\GO\Email\Model\Account::model()->count()){

			foreach ($domains as $domain) {
				if (!empty($domain)) {
					$accountModel = new \GO\Email\Model\Account();
					$accountModel->mbroot = \GO::config()->serverclient_mbroot;
					$accountModel->use_ssl = \GO::config()->serverclient_use_ssl;
					$accountModel->novalidate_cert = \GO::config()->serverclient_novalidate_cert;
					$accountModel->type = \GO::config()->serverclient_type;
					$accountModel->host = \GO::config()->serverclient_host;
					$accountModel->port = \GO::config()->serverclient_port;

					$accountModel->username = 'admin@' . $domain;
					$accountModel->password = 'admin';
//					$accountModel->name=\GO::user()->name;

					$accountModel->smtp_host = \GO::config()->serverclient_smtp_host;
					$accountModel->smtp_port = \GO::config()->serverclient_smtp_port;
					$accountModel->smtp_encryption = \GO::config()->serverclient_smtp_encryption;
					$accountModel->smtp_username = \GO::config()->serverclient_smtp_username;
					$accountModel->smtp_password = \GO::config()->serverclient_smtp_password;
					$accountModel->save();
					$accountModel->addAlias($accountModel->username, \GO::user()->name);			
				}
			}
			
			
			if(file_exists($config['file_storage_path'].'key.txt'))
				system('chown www-data:www-data '.$config['file_storage_path'].'key.txt');
		}

	}
} catch (Exception $e) {
	echo 'ERROR: ' . $e->getMessage();
}

