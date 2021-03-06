/** 
 * Copyright Intermesh
 * 
 * This file is part of Group-Office. You should have received a copy of the
 * Group-Office license along with Group-Office. See the file /LICENSE.TXT
 * 
 * If you have questions write an e-mail to info@intermesh.nl
 * 
 * @version $Id: ScheduleCallDialog.js 16895 2014-02-21 15:05:23Z mschering $
 * @copyright Copyright Intermesh
 * @author Merijn Schering <mschering@intermesh.nl>
 */
 
GO.tasks.ScheduleCallDialog = Ext.extend(GO.dialog.TabbedFormDialog , {
	
	initComponent : function(){
		
		Ext.apply(this, {
			height:580,
			width:600,
			goDialogId:'task-schedule-call',
			title:GO.tasks.lang.scheduleCall,
			formControllerUrl: 'tasks/scheduleCall',
			submitAction : 'save',
			loadAction : 'load',
			enableApplyButton : false
		});
		
		GO.tasks.ScheduleCallDialog.superclass.initComponent.call(this);	
		this.setCurrentDateAndTime();
		this.formPanel.baseParams.remind_date=this.datePicker.getValue().format(GO.settings.date_format);
	},
	show : function (remoteModelId, config) {
		this.selectContact.clearLastSearch();
		GO.tasks.ScheduleCallDialog.superclass.show.call(this,remoteModelId, config);
		if(config && config.link_config)
			this.setContact(config.link_config.model_id,config.link_config.name);
		
		this.setCurrentDateAndTime();
	},
	setCurrentDateAndTime : function(){
		var now = new Date();

		var time = now.getMinutes() +10; // + 10 minutes
		now.setMinutes(time);
		this.datePicker.setValue(now);
		this.timeField.setValue(now.format(GO.settings['time_format']));
	},
	buildForm : function () {

		this.datePicker = new Ext.DatePicker({
			xtype:'this.datePicker',
			name:'remind_date',
			format: GO.settings.date_format,
			fieldLabel:GO.lang.strDate
		});

		this.datePicker.on("select", function(datePicker, DateObj){						
			this.formPanel.baseParams.remind_date=this.formPanel.baseParams.start_time=this.formPanel.baseParams.due_time=DateObj.format(GO.settings.date_format);	
		},this);
				
		this.selectTaskList = new GO.tasks.SelectTasklist({
			fieldLabel: GO.tasks.lang.tasklist, 
			anchor:'100%'
		});
		
		this.timeField = new Ext.form.TimeField({
			name:'remind_time',
			width:220,
			format: GO.settings.time_format,
			fieldLabel:GO.lang.strTime,
			anchor:'100%'
		});
			
		this.descriptionField = new Ext.form.TextArea({
			name: 'description',
			anchor: '100%',
			width:300,
			height:45,
			fieldLabel: GO.lang.strDescription
		});		

//		this.selectContact = new GO.addressbook.SelectContact ({
//			name: 'contact_name',
//			fieldLabel:GO.addressbook.lang.contact,
//			enableKeyEvents : true,
//			remoteSort: true,
//			allowBlank:false,
//			anchor: '100%',
//			tpl:'<tpl for="."><div class="x-combo-list-item">{name} ({ab_name}) <tpl if="email">({email})</tpl></div></tpl>'
//		});
		
		this.selectContact = new GO.form.ComboBoxReset({
			name: 'contact_name',
			fieldLabel:GO.addressbook.lang.contact,
			anchor: '100%',
			allowBlank:false,
			mode:'remote',
			triggerAction:'all',
			enableKeyEvents : true,
			selectOnFocus:true,
			displayField:'name',
			valueField: 'id',
			tpl:'<tpl for="."><div class="x-combo-list-item">{name} ({ab_name}) <tpl if="email">({email})</tpl></div></tpl>',
			store: new GO.data.JsonStore({
				url: GO.url('addressbook/contact/store'),
				root: 'results',
				id: 'id',
				totalProperty:'total',
				fields: ['id','name','email','ab_name','work_phone','home_phone','cellular','cellular2'],
				remoteSort: true
			})
		});
		
				
		this.contactIdField = new Ext.form.Hidden({
			name:'contact_id'
		});
		
		this.phoneNumberField = new GO.form.ComboBoxReset({
			name: 'number',
			fieldLabel:GO.tasks.lang.phoneNr,
			anchor: '100%',
			allowBlank:false,
			mode:'local',
			triggerAction:'all',
			enableKeyEvents : true,
			selectOnFocus:true,
			displayField:'label',
			valueField: 'number',
			store: new Ext.data.ArrayStore({
				storeId: 'phoneNumberFieldStore',
				fields: ['id','number','label']
			})
		});
		
		this.btnAddContact = new Ext.Button ({
			text:GO.addressbook.lang.btnAddContact,
			anchor: '50%',
			disabled:true,
			style:{
				'margin-left':'105px',
				'margin-bottom':'5px'
			},
			handler:function(){
				var attrs = {};
				var name = this.selectContact.getRawValue();
				var number = this.phoneNumberField.getRawValue();
				var field = this.savePhoneNumberField.getValue();
				
				var nameParts = {};

				if(name){
					nameParts = name.split(" ");

					if(nameParts.length > 2){
						attrs.first_name = nameParts[0];
						attrs.middle_name = nameParts[1];
						attrs.last_name = nameParts[2];
					} else if(nameParts.length > 1){
						attrs.first_name = nameParts[0];
						attrs.last_name = nameParts[1];
					} else {
						attrs.first_name = nameParts[0];
					}
				}
				
				if(!GO.util.empty(field) && number){
					attrs[field] = number;
				} else if(number){
					attrs['work_phone'] = number;
				}
			
				GO.addressbook.showContactDialog(0, {values:attrs});
				
				GO.addressbook.contactDialog.on('save',this.setContactFromDialog,this);
				GO.addressbook.contactDialog.on('hide',function(){
					GO.addressbook.contactDialog.un('save', this.setContactFromDialog);
				},this, {single:true});
			},
			scope: this
		});

		this.savePhoneNumberField = new GO.form.ComboBox({
			hiddenName: 'save_as',
			fieldLabel:GO.tasks.lang.savePhoneNr,
			disabled:true,
			anchor: '100%',
			mode:'local',
			triggerAction:'all',
			selectOnFocus:true,
			displayField:'label',
			valueField: 'id',
			store: new Ext.data.ArrayStore({
				storeId: 'savePhoneNumberFieldStore',
				fields: ['id','label','number']
			})
		});
			
		this.selectContact.on('change', function(combo, new_val, old_val ){

			var record = this.selectContact.store.getById(new_val);
			
			new_val = record ? new_val :  0;

			this.contactIdField.setValue(new_val);
			this.populatePhoneFields();
			this.btnAddContact.setDisabled(new_val!=0);

		},this);
		
		this.phoneNumberField.on('keyup', function(combo,e){
			if(e.getKey() !== 9 && e.getKey() !== 13){ // Don't do anything when the tab button is pressed
				this.savePhoneNumberField.setDisabled(false);
			}
		},this);
		
		this.phoneNumberField.on('select', function(combo,record,index){			
			this.disableSavePhoneNumberField();
		},this);
		
		this.propertiesPanel = new Ext.Panel({
			border: false,
			//			baseParams: {date: tomorrow.format(GO.settings.date_format), name: 'TEST'},			
			//cls:'go-form-panel',
			layout:'form',
			waitMsgTarget:true,			
			items:[
			{
				xtype:'fieldset',
				title: GO.tasks.lang.task,
				items:[
				{	
					items:this.datePicker,
					width:240,
					style:'margin:auto;'
				},
				{
					layout:'column',
					items:[{
							columnWidth:.5,
							items:[{
									layout:'form',
									labelWidth:76,
									items:[
										this.timeField,
										this.selectTaskList
									]
							}]
						},{
							columnWidth:.5,
							items:[{
									layout:'form',
									style:{
										'padding-left': '10px'
									},
									labelWidth:70,
									items:[
										this.descriptionField
									]
							}]
						}]
				}
			]},{
				xtype:'fieldset',
				title: GO.addressbook.lang.contact,
				items:[
					this.contactIdField,
					this.selectContact,
					this.phoneNumberField,
					this.savePhoneNumberField,
					this.btnAddContact,
				]}
			]			
		});
	
		this.addPanel(this.propertiesPanel);
	},

	populatePhoneFields : function(){

		var order = [
			'work_phone',
			'home_phone',
			'cellular',
			'cellular2'
		];
		
		var record = this.selectContact.store.getById(this.contactIdField.getValue());
	
		if(GO.util.empty(record)){
			record = {};
			record.data = {};
			
			for(var i=0; i <order.length; i++)
				record.data[order[i]] = '';
			
			this.savePhoneNumberField.setDisabled(false);
		} else {
			this.savePhoneNumberField.setDisabled(true);
		}
		
		// Select the first found attribute that is not empty
		var currentNumber = '';
		var foundNumbers = [];
		var replaceNumbers = [];
		for(var i=0; i <order.length; i++){
			currentNumber = record.data[order[i]];
			if(!GO.util.empty(currentNumber)){
				replaceNumbers.push(new Ext.data.Record({'id':order[i],'label':this.createReplaceNumberLabel(order[i],currentNumber),'number':currentNumber},order[i]));
				foundNumbers.push(new Ext.data.Record({'id':order[i],'number':currentNumber,'label':currentNumber+' '+GO.addressbook.lang['contact'+this.capitalize(order[i])]},order[i]));
			} else {
				replaceNumbers.push(new Ext.data.Record({'id':order[i],'label':this.createReplaceNumberLabel(order[i],''),'number':''},order[i]));
			}
		}
		
		// Clear both stores
		this.phoneNumberField.getStore().removeAll();
		this.savePhoneNumberField.getStore().removeAll();
		
		// Fill the store for the phoneNumberField 
		if(foundNumbers.length > 0){
			this.phoneNumberField.getStore().add(foundNumbers);
			this.phoneNumberField.selectFirst();
		}
		// Fill the store for the savePhoneNumberField
		this.savePhoneNumberField.getStore().add(replaceNumbers);
		this.savePhoneNumberField.setRawValue('');
	},
	createReplaceNumberLabel: function(field,oldvalue){
		
		var label = '';
		
		if(!GO.util.empty(oldvalue))
			label = GO.tasks.lang.overwritePhoneNumber;
		else 
			label = GO.tasks.lang.addToPhoneNumber;
		
		var fieldname = 'contact'+this.capitalize(field);

		label = label.replace("{field}",GO.addressbook.lang[fieldname]);
		label = label.replace("{number}",oldvalue);

		return label;
	},
	capitalize : function(text) {
    return text.charAt(0).toUpperCase() + text.slice(1).toLowerCase();
	},
	setContactFromDialog : function(dialog,contact_id){
		this.setContact(contact_id,this.getNameFromContactDialog(dialog));
	},
	setContact : function(contact_id){
		this.selectContact.selectContactById(contact_id,function(combo,record){

			this.contactIdField.setValue(contact_id);
			this.populatePhoneFields();
			this.btnAddContact.setDisabled(true);
			this.disableSavePhoneNumberField();
		},this);

		this.btnAddContact.setDisabled(true);
		this.disableSavePhoneNumberField();
	},
	disableSavePhoneNumberField : function(){
		this.savePhoneNumberField.setDisabled(true);
		this.savePhoneNumberField.setRawValue('');
	},
	getNameFromContactDialog : function(dialog){
		var data = dialog.formPanel.getForm().getValues();
		var name = '';
		
		if(GO.settings.sort_name == 'last_name'){
			name = data.last_name+', ';
			name += data.first_name;
			
			if(!GO.util.empty(data.middle_name)){
				name += ' '+data.middle_name;
			}
			
		} else {
			name = data.first_name+' ';
			
			if(!GO.util.empty(data.middle_name)){
				name += data.middle_name+' ';
			}
			
			name += data.last_name;
		}
				
		return name;
	}
	
});