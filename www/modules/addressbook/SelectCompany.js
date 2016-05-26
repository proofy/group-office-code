/** 
 * Copyright Intermesh
 * 
 * This file is part of Group-Office. You should have received a copy of the
 * Group-Office license along with Group-Office. See the file /LICENSE.TXT
 * 
 * If you have questions write an e-mail to info@intermesh.nl
 * 
 * @copyright Copyright Intermesh
 * @version $Id: SelectCompany.js 17094 2014-03-14 13:06:30Z mschering $
 * @author Merijn Schering <mschering@intermesh.nl>
 */


GO.addressbook.SelectCompany = function(config){
	
	if(!config.valueField)
		config.valueField='id';
		
	config.displayField='name_and_name2';
	
	if(!config.tpl)
		config.tpl = '<tpl for="."><div class="x-combo-list-item">{' + config.displayField + '} ({addressbook_name})</div></tpl>';


//	Ext.apply(this, config);
	
	var customfieldId = config.customfieldId || '';

	if (typeof(config.fields)=='undefined') {
		config.fields = {
			columns:[],
			fields:['id', 'name_and_name2', 'post_address', 'post_address_no', 'post_zip', 'post_city', 'post_state', 'post_country', 'vat_no', 'crn','email','invoice_email','cf','addressbook_name']
		};
	}
		
	if(!this.fieldLabel){
		this.fieldLabel=GO.addressbook.lang.company;
	}

	if(GO.customfields)
	{
		GO.customfields.addColumns("GO\\Addressbook\\Model\\Company", config.fields);
	}

	if (typeof(config.store)=='undefined') {
		this.store = new GO.data.JsonStore({
			url: GO.url("addressbook/company/selectCompany"),
			baseParams: {				
				addressbook_id : this.addressbook_id,		
				noMultiSelectFilter:true,
				customfield_id : customfieldId
			},
			root: 'results',
			id: 'id',
			totalProperty:'total',
			fields: config.fields.fields,
			remoteSort: true
		});
		this.store.setDefaultSort('name', 'asc');		
	}
	
	config.triggerAction='all';
	config.selectOnFocus=true;

	GO.addressbook.SelectCompany.superclass.constructor.call(this,config
//	{		
//		triggerAction: 'all',
//		selectOnFocus:true,
//		pageSize: parseInt(GO.settings['max_rows_list'])
//	}
	);
	
}
Ext.extend(GO.addressbook.SelectCompany, GO.form.ComboBoxReset);

Ext.ComponentMgr.registerType('selectcompany', GO.addressbook.SelectCompany);