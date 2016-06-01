<?php
if(\GO\Base\Util\Http::isAjaxRequest()){
	echo $data['response'];
}elseif(PHP_SAPI=='cli'){
	echo "ERROR: ".trim($data['response']['feedback'])."\n\n";
	if(\GO::config()->debug)
		echo $data['response']['exception']."\n\n";
}else
{
	require("externalHeader.php");
	echo '<h1>'.\GO::t('strError').'</h1>';
	echo '<p style="color:red">'.$data['response']['feedback'].'</p>';
	if(\GO::config()->debug){
		unset($data['response']['feedback']);
		echo '<h2>Debug info:</h2>';
		echo '<pre>';
		var_dump($data['response']);
		echo '</pre>';
	}
	
	require("externalFooter.php");
}