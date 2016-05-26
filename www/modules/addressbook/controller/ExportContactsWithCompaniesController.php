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


class ExportContactsWithCompaniesController extends \GO\Base\Controller\AbstractExportController{
	
	/**
	 * Export the contact model to a .csv, including the company.
	 * 
	 * @param array $params
	 */
	public function export($params) {

		// Load the data from the session.
		$findParams = \GO::session()->values['contact']['findParams'];
		$findParams->getCriteria()->recreateTemporaryTables();
		$model = \GO::getModel(\GO::session()->values['contact']['model']);
		
		// Include the companies
		$findParams->joinRelation('company','LEFT');
						
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
		
		foreach($stmt as $m){
			
			$attrs = $m->getAttributes();
			$compAttrs = $m->company->getAttributes();

			$header = array();
			$record = array();
			foreach($attrs as $attr=>$val){
				$header[$attr] = $m->getAttributeLabel($attr);
				$record[$attr] = $m->{$attr};
			}
			
			foreach($compAttrs as $cattr=>$cval){
				$header[\GO::t('company','addressbook').$cattr] = \GO::t('company','addressbook').':'.$m->company->getAttributeLabel($cattr);
				$record[\GO::t('company','addressbook').$cattr] = $m->company->{$cattr};
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