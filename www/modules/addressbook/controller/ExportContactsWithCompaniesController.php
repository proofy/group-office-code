<?php
/**
 * @copyright Copyright Intermesh BV.
 * @author Wesley Smits <wsmits@intermesh.nl>
 *
 */

/**
 * Class to export Contacts with companies together to a .csv file. 
 */

namespace GO\Addressbook\Controller;

use GO;

class ExportContactsWithCompaniesController extends \GO\Base\Controller\AbstractExportController{
	
	/**
	 * Export the contact model to a .csv, including the company.
	 * 
	 * @param array $params
	 */
	public function export($params) {
		
		GO::$disableModelCache=true;		
		GO::setMaxExecutionTime(420);
		
		$companyMode = false;
		if(isset($params['viewState']) && $params['viewState'] == 'company') { 
			$companyMode = true;
		}
		// Load the data from the session.
		if($companyMode) { 
			$findParams = \GO::session()->values['company']['findParams'];
			$model = \GO::getModel(\GO::session()->values['company']['model']);
		} else {
			$findParams = \GO::session()->values['contact']['findParams'];
			$model = \GO::getModel(\GO::session()->values['contact']['model']);
		}
		
		$findParams->getCriteria()->recreateTemporaryTables();
		
		// Include the companies
		if($companyMode) {
			
			$findParams->joinRelation('contacts','LEFT');
		} else {
			$findParams->joinRelation('company','LEFT');
		}
						
		// Let the export handle all found records without a limit
		$findParams->limit(0); 

		// Create the statement
		$stmt = $model->find($findParams);
		
		// Create the csv file
		$csvFile = new \GO\Base\Fs\CsvFile(\GO\Base\Fs\File::stripInvalidChars('export.csv'));
		
		// Output the download headers
		\GO\Base\Util\Http::outputDownloadHeaders($csvFile, false);
				
		$csvWriter = new \GO\Base\Csv\Writer('php://output');
		
		$headerPrinted = false; 
		$attrs = array();
		$compAttrs = array();
			
		foreach($stmt as $m){
			
			
			$iterationStartUnix = time();
			
			
			$header = array();
			$record = array();
			
			
				$attrs = $m->getAttributes();
				if($companyMode) { 
					
					foreach ($m->contacts as $compAttrs) {
						$header = array();
						foreach($attrs as $attr=>$val){
							if (!$headerPrinted)
								$header[$attr] = $m->getAttributeLabel($attr);
							$record[$attr] = $m->{$attr};
						}

						foreach($compAttrs->getAttributes() as $cattr=>$cval){

								if (!$headerPrinted) {
									$header[GO::t('contacts','addressbook').$cattr] = GO::t('contacts','addressbook').':'.$compAttrs->getAttributeLabel($cattr);
								}
								$record[GO::t('contacts','addressbook').$cattr] = $compAttrs->{$cattr};
							}

						}
					
					
				} else {
					
					$compAttrs = $m->company->getAttributes();
					
					foreach($attrs as $attr=>$val){
						if (!$headerPrinted)
							$header[$attr] = $m->getAttributeLabel($attr);
						$record[$attr] = $m->{$attr};
					}
					
					
					foreach($compAttrs as $cattr=>$cval){

						if (!$headerPrinted) {
							$header[GO::t('company','addressbook').$cattr] = GO::t('company','addressbook').':'.$m->company->getAttributeLabel($cattr);
						}
						$record[GO::t('company','addressbook').$cattr] = $m->company->{$cattr};
					}
			
				}
					
			if(!$headerPrinted){
				$csvWriter->putRecord($header);
				$headerPrinted = true;
			}

			$csvWriter->putRecord($record);
		}
	}
	
	/**
	 * Return an empty array because we don't select attributes
	 * 
	 * @return array
	 */
	public function exportableAttributes() {
		return array();
	}
}