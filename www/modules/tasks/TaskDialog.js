/**
 * Copyright Intermesh
 * 
 * This file is part of Group-Office. You should have received a copy of the
 * Group-Office license along with Group-Office. See the file /LICENSE.TXT
 * 
 * If you have questions write an e-mail to info@intermesh.nl
 * 
 * @copyright Copyright Intermesh
 * @version $Id: TaskDialog.js 19784 2016-01-26 13:56:16Z michaelhart86 $
 * @author Merijn Schering <mschering@intermesh.nl>
 */

GO.tasks.TaskDialog = function() {

	this.buildForm();

	var focusName = function() {
		this.nameField.focus();
	};

	this.goDialogId='task';
	
	this.remind_before = '';

	this.win = new GO.Window({
		layout : 'fit',
		modal : false,
		resizable : true,
		width : 560,
		height : 400,
		closeAction : 'hide',
		collapsible: true,
		title : GO.tasks.lang.task,
		items : this.formPanel,
		focus : focusName.createDelegate(this),
		buttons : [{
			text : GO.lang['cmdOk'],
			handler : function() {
				this.submitForm(true);

			},
			scope : this
		},/* {
			text : GO.lang['cmdApply'],
			handler : function() {
				this.submitForm();
			},
			scope : this
		}, */{
			text : GO.lang['cmdClose'],
			handler : function() {
				this.win.hide();
			},
			scope : this
		}]
	/*
 * , keys: [{ key: Ext.TaskObject.ENTER, fn: function(){ this.submitForm();
 * this.win.hide(); }, scope:this }]
 */
	});

	this.win.render(Ext.getBody());

	GO.tasks.TaskDialog.superclass.constructor.call(this);
}

Ext.extend(GO.tasks.TaskDialog, Ext.util.Observable, {

	
	show : function(config) {

		if (!config) {
			config = {};
		}
		
		this.showConfig=config;

		GO.dialogListeners.apply(this);

		if(!GO.tasks.categoriesStore.loaded)
			GO.tasks.categoriesStore.load();

		//tmpfiles on the server ({name:'Name',tmp_file:/tmp/name.ext} will be attached)
		this.formPanel.baseParams.tmp_files = config.tmp_files ? Ext.encode(config.tmp_files) : '';

		if(config.projectName)
			this.formPanel.baseParams.project_name=config.projectName;
		else
			delete this.formPanel.baseParams.project_name;
		
		delete this.link_config;
		this.formPanel.form.reset();

		//		this.formPanel.form.findField('remind').setValue(!GO.util.empty(GO.tasks.remind));
		//		this.formPanel.form.findField('remind_date').setDisabled(GO.util.empty(GO.tasks.remind));
		//		this.formPanel.form.findField('remind_time').setDisabled(GO.util.empty(GO.tasks.remind));

		this.tabPanel.setActiveTab(0);

		if (!config.task_id) {
			config.task_id = 0;
		}

		this.setTaskId(config.task_id);
		
		var params = {};
		if (!GO.util.empty(config.tasklist_id))
			params.tasklist_id=config.tasklist_id;
		
		if (config.link_config && config.link_config.model_name=="GO\\Projects\\Model\\Project")
			params.project_id=config.link_config.model_id;	
		
		// this.selectTaskList.container.up('div.x-form-item').setDisplayed(false);

		//		if (config.task_id > 0) {

		this.formPanel.load({
			url : GO.url('tasks/task/load'),
			params:params,
			success : function(form, action) {
				this.win.show();
				this.changeRepeat(action.result.data.freq);
				this.setValues(config.values);
					
				this.remind_before = action.result.data.remind_before;

				//	this.selectTaskList.setRemoteText(action.result.data.tasklist_name);
				this.selectTaskList.setRemoteText(action.result.remoteComboTexts.tasklist_id);

				if(this.selectProject){
					if(config.link_config && config.link_config.model_name=="GO\\Projects2\\Model\\Project"){			

						this.selectProject.setValue(config.link_config.model_id);
						this.selectProject.setRemoteText(config.link_config.text);
					}else
					{
						this.selectProject.setRemoteText(action.result.remoteComboTexts.project_id);
					}
				}
				
				if(GO.comments){	
					if(action.result.data['id'] > 0){
						if (!GO.util.empty(action.result.data['action_date'])) {
							this.commentsGrid.actionDate = action.result.data['action_date'];
						} else {
							this.commentsGrid.actionDate = false;
						}
						this.commentsGrid.setLinkId(action.result.data['id'], 'GO\\Tasks\\Model\\Task');
						this.commentsGrid.store.load();
						this.commentsGrid.setDisabled(false);
					}else {
						this.commentsGrid.setDisabled(true);
					}
				}
				
				if(action.result.data.category_id == 0)
				{
					//this.selectCategory.setRemoteText();
					this.selectCategory.setValue("");
				}else
				{
					this.selectCategory.setRemoteText(action.result.remoteComboTexts.category_id);
				}
					
				this.formPanel.form.clearInvalid();
					
			},
			failure : function(form, action) {
				GO.errorDialog.show(action.result.feedback)
			},
			scope : this

		});
		//		} else {
		//			delete this.formPanel.form.baseParams['exception_task_id'];
		//			delete this.formPanel.form.baseParams['exceptionDate'];
		//
		//			this.lastTaskListId = this.selectTaskList.getValue();
		//
		//			this.selectTaskList.setValue(this.lastTaskListId);
		//
		//			this.setWritePermission(true);
		//
		//			this.win.show();
		//			this.setValues(config.values);
		//
		//			if (GO.util.empty(config.tasklist_id)) {
		//				config.tasklist_id = GO.tasks.defaultTasklist.id;
		//				config.tasklist_name = GO.tasks.defaultTasklist.name;
		//			}
		//			this.selectTaskList.setValue(config.tasklist_id);
		//			if (config.tasklist_name) {
		//				this.selectTaskList.setRemoteText(config.tasklist_name);
		//				this.selectTaskList.container.up('div.x-form-item').setDisplayed(true);
		//			}else
		//			{
		//				this.selectTaskList.container.up('div.x-form-item').setDisplayed(false);
		//			}
		//		}

		// if the newMenuButton from another passed a linkTypeId then set this
		// value in the select link field
		if (config.link_config) {
			this.link_config = config.link_config;
			if (config.link_config.modelNameAndId) {
				this.selectLinkField.setValue(config.link_config.modelNameAndId);
				this.selectLinkField.setRemoteText(config.link_config.text);				
			}
		}
	},


	setValues : function(values) {
		if (values) {
			for (var key in values) {
				var field = this.formPanel.form.findField(key);
				if (field) {
					field.setValue(values[key]);
				}
			}
		}

	},
	setTaskId : function(task_id) {
		this.formPanel.form.baseParams['id'] = task_id;
		this.task_id = task_id;
	},

	setCurrentDate : function() {
		var formValues = {};

		var date = new Date();

		formValues['start_time'] = formValues['remind_date'] = date
		.format(GO.settings['date_format']);
		formValues['start_hour'] = date.format("H");
		formValues['start_min'] = '00';

		formValues['end_date'] = date.format(GO.settings['date_format']);
		formValues['end_hour'] = date.add(Date.HOUR, 1).format("H");
		formValues['end_min'] = '00';

		this.formPanel.form.setValues(formValues);
	},

	submitForm : function(hide) {
		this.formPanel.form.submit({
			url : GO.url('tasks/task/submit'),
			waitMsg : GO.lang['waitMsgSave'],
			success : function(form, action) {

				if (action.result.id) {
					this.setTaskId(action.result.id);
				}

				if (this.link_config && this.link_config.callback) {
					this.link_config.callback.call(this);
				}

				GO.tasks.tasksObservable.fireEvent('save', this, this.task_id);
				this.fireEvent('save', this, this.task_id);
				
				
				GO.dialog.TabbedFormDialog.prototype.refreshActiveDisplayPanels.call(this);

				if (hide) {
					this.win.hide();
				}
			},
			failure : function(form, action) {
				if (action.failureType == 'client') {
					GO.errorDialog.show(GO.lang['strErrorsInForm']);
				} else {
					GO.errorDialog.show(action.result.feedback);
				}
			},
			scope : this
		});

	},

	buildForm : function() {

		this.nameField = new Ext.form.TextField({
			name : 'name',
			allowBlank : false,
			fieldLabel : GO.lang.strSubject
		});

		this.selectLinkField = new GO.form.SelectLink();

		var checkDateInput = function(field) {

			if (field.name == 'due_time') {
				if (startDate.getValue() > dueDate.getValue()) {
					startDate.setValue(dueDate.getValue());
				}
			} else {
				if (startDate.getValue() > dueDate.getValue()) {
					dueDate.setValue(startDate.getValue());
				}
			}

			var remindDate = startDate.getValue().add(Date.DAY, -this.remind_before);
			
			formPanel.form.findField('remind_date').setValue(remindDate);

			if (this.repeatType.getValue() != '') {
				if (this.repeatEndDate.getValue() == '') {
					this.repeatForever.setValue(true);
				} else {
					var eD = dueDate.getValue();
					if (this.repeatEndDate.getValue() < eD) {
						this.repeatEndDate.setValue(eD.add(Date.DAY, 1));
					}
				}
			}
		}

		var now = new Date();

		var startDate = new Ext.form.DateField({
			name : 'start_time',
			format : GO.settings['date_format'],
			fieldLabel : GO.tasks.lang.startsAt,
			value : now.format(GO.settings.date_format),
			listeners : {
				change : {
					fn : checkDateInput,
					scope : this
				}
			}
		});

		var dueDate = new Ext.form.DateField({
			name : 'due_time',
			format : GO.settings['date_format'],
			allowBlank : false,
			fieldLabel : GO.tasks.lang.dueAt,
			value : now.format(GO.settings.date_format),
			listeners : {
				change : {
					fn : checkDateInput,
					scope : this
				}
			}
		});

		var taskStatus = new GO.tasks.SelectTaskStatus({
			flex:3,
			listeners:{
				scope:this,
				select:function(combo, record){
					if(record.data.value=='COMPLETED')
						this.formPanel.form.findField('percentage_complete').setValue(100);
				}
			}
		});

		this.selectTaskList = new GO.tasks.SelectTasklist({
			fieldLabel : GO.tasks.lang.tasklist,
			allowBlank:false
		});

		this.selectCategory = new GO.form.ComboBoxReset({
			hiddenName:'category_id',
			fieldLabel:GO.tasks.lang.category,
			valueField:'id',
			displayField:'name',			
			store: GO.tasks.categoriesStore,
			mode:'local',
			triggerAction:'all',
			emptyText:GO.tasks.lang.selectCategory,
			editable:false,
			selectOnFocus:true,
			forceSelection:true,
			pageSize: parseInt(GO.settings['max_rows_list'])
		});

		this.selectPriority = new GO.form.SelectPriority();
		
		var percentages = [];
		for(var i=0;i<101;i+=10){
			percentages.push([i,i+"%"]);
		}
		
		var descAnchor = -220;

		var propertiesPanel = new Ext.Panel({
			hideMode : 'offsets',
			title : GO.lang['strProperties'],
			defaults : {
				anchor : '-20'
			},
			labelWidth:120,
			// cls:'go-form-panel',waitMsgTarget:true,
			bodyStyle : 'padding:5px',
			layout : 'form',
			autoScroll : true,
			items : [
				this.nameField, 
				this.selectLinkField,
				startDate,
				dueDate,
				this.statusProgressField = new GO.tasks.StatusProgressField({}),
				this.selectTaskList,
				this.selectCategory,
				this.selectPriority	
			]

		});

		if(GO.moduleManager.userHasModule("projects2")){
			descAnchor-=20;
			this.selectProject = new GO.projects2.SelectProject();
			propertiesPanel.add(this.selectProject);
		} else if(GO.moduleManager.userHasModule("projects")) {
			descAnchor-=20;
			this.selectProject = new GO.projects.SelectProject();
			propertiesPanel.add(this.selectProject);
		}
		
		propertiesPanel.add({
				xtype:'textarea',
				fieldLabel:GO.lang.strDescription,
				name : 'description',
				anchor:'-20 '+descAnchor
			});
				

		// Start of recurrence tab
		this.repeatEvery = new GO.form.NumberField({
			decimals:0,
			name : 'interval',
			minValue:1,
			width : 50,
			value : '1'
		});


		this.repeatType = new Ext.form.ComboBox({
			hiddenName : 'freq',
			triggerAction : 'all',
			editable : false,
			selectOnFocus : true,
			width : 200,
			forceSelection : true,
			mode : 'local',
			value : '',
			valueField : 'value',
			displayField : 'text',
			store : new Ext.data.SimpleStore({
				fields : ['value', 'text'],
				data : [['', GO.lang.noRecurrence],
				['DAILY', GO.lang.strDays],
				['WEEKLY', GO.lang.strWeeks],
				['MONTHLY_DATE', GO.lang.monthsByDate],
				['MONTHLY', GO.lang.monthsByDay],
				['YEARLY', GO.lang.strYears]]
			}),
			hideLabel : true,
			listeners : {
				change : {
					fn : checkDateInput,
					scope : this
				}
			}
		});

		this.repeatType.on('select', function(combo, record) {
			this.changeRepeat(record.data.value);
		}, this);

		this.monthTime = new Ext.form.ComboBox({
			//hiddenName : 'month_time',
			hiddenName : 'bysetpos',
			triggerAction : 'all',
			selectOnFocus : true,
			disabled : true,
			width : 80,
			forceSelection : true,
			fieldLabel : GO.tasks.lang.atDays,
			mode : 'local',
			value : '1',
			valueField : 'value',
			displayField : 'text',
			store : new Ext.data.SimpleStore({
				fields : ['value', 'text'],
				data : [['1', GO.lang.strFirst],
				['2', GO.lang.strSecond],
				['3', GO.lang.strThird],
				['4', GO.lang.strFourth]]
			})
		});
		
		
		var days = ['SU','MO','TU','WE','TH','FR','SA'];

		this.cb = [];
		for (var day = 0; day < 7; day++) {
			this.cb[day] = new Ext.form.Checkbox({
				boxLabel : GO.lang.shortDays[day],
				name : days[day],
				disabled : true,
				checked : false,
				width : 'auto',
				hideLabel : true,
				labelSeperator : ''
			});
		}

		this.repeatEndDate = this.repeatEndDate = new Ext.form.DateField({
			name : 'until',
			width : 100,
			disabled : true,
			format : GO.settings['date_format'],
			allowBlank : true,
			fieldLabel : GO.tasks.lang.repeatUntil,
			listeners : {
				change : {
					fn : checkDateInput,
					scope : this
				}
			}
		});

		this.repeatForever = new Ext.form.Checkbox({
			boxLabel : GO.tasks.lang.repeatForever,
			name : 'repeat_forever',
			checked : true,
			disabled : true,
			width : 'auto',
			hideLabel : true,
			labelSeperator : '',
			listeners : {
				check : {
					fn : this.disableUntilField,
					scope : this
				}
			}
		});

		this.recurrencePanel = new Ext.Panel({
			title : GO.tasks.lang.recurrence,
			bodyStyle : 'padding: 5px',
			layout : 'form',
			hideMode : 'offsets',
			defaults:{
				forceLayout:true,
				border:false
			},
			items : [{
				fieldLabel : GO.tasks.lang.repeatEvery,
				xtype : 'compositefield',
				items : [this.repeatEvery,this.repeatType]
			}, {
				xtype : 'compositefield',
				fieldLabel : GO.tasks.lang.atDays,
				items : [this.monthTime,this.cb[1],this.cb[2],this.cb[3],this.cb[4],this.cb[5],this.cb[6],this.cb[0]]
			}, {
				fieldLabel : GO.tasks.lang.repeatUntil,
				xtype : 'compositefield',
				items : [this.repeatEndDate,this.repeatForever]
			}
			]
		});

		var remindDate = now.add(Date.DAY, -GO.tasks.reminderDaysBefore);
		// start other options tab
		var optionsPanel = new Ext.Panel({

			title : GO.tasks.lang.options,
			defaults : {
				anchor : '100%'
			},
			bodyStyle : 'padding:5px',
			layout : 'form',
			hideMode : 'offsets',
			autoScroll : true,
			items : [{
				xtype : 'xcheckbox',
				boxLabel : GO.tasks.lang.remindMe,
				hideLabel : true,
				name : 'remind',
				listeners : {
					'check' : function(field, checked) {
						this.formPanel.form.findField('remind_date')
						.setDisabled(!checked);
						this.formPanel.form.findField('remind_time')
						.setDisabled(!checked);
					},
					scope : this
				}
			}, {
				xtype : 'datefield',
				name : 'remind_date',
				format : GO.settings.date_format,
				value : remindDate.format(GO.settings['date_format']),
				fieldLabel : GO.lang.strDate,
				disabled : true
			}, {
				xtype : 'timefield',
				name : 'remind_time',
				format : GO.settings.time_format,
				value : GO.tasks.reminderTime,
				fieldLabel : GO.lang.strTime,
				disabled : true
			}]
		});

		var items = [propertiesPanel, this.recurrencePanel, optionsPanel];


		if(GO.customfields && GO.customfields.types["GO\\Tasks\\Model\\Task"])
		{
			for(var i=0;i<GO.customfields.types["GO\\Tasks\\Model\\Task"].panels.length;i++)
			{
				items.push(GO.customfields.types["GO\\Tasks\\Model\\Task"].panels[i]);
			}
		}
		
		if(GO.comments){
			this.commentsGrid = new GO.comments.CommentsGrid({title:GO.comments.lang.comments});
			items.push(this.commentsGrid);
		}
		
		this.tabPanel = new Ext.TabPanel({
			activeTab : 0,
			deferredRender : false,
			// layoutOnTabChange:true,
			border : false,
			anchor : '100% 100%',
			hideLabel : true,
			items : items
		});

		var formPanel = this.formPanel = new Ext.form.FormPanel({
			waitMsgTarget : true,
			url : GO.settings.modules.tasks.url + 'action.php',
			border : false,
			baseParams : {
				task : 'task'
			},
			items : this.tabPanel
		});
	},

	/*populateComboBox : function(records)
	{
		var data = [];

		for(var i=0; i<records.length; i++)
		{
			var tasklist = []
			tasklist.push(records[i].id);
			tasklist.push(records[i].data.name);

			data.push(tasklist);
		}

		this.selectCategory.store.loadData(data);
		var r = this.selectCategory.store.getAt(0);
		if(r)
			this.selectCategory.setValue(r.data.id);
	},*/

	changeRepeat : function(value) {

		var repeatForever = this.repeatForever.getValue();
		
		var form = this.formPanel.form;
		switch (value) {
			case '' :
				this.disableDays(true);
				this.monthTime.setDisabled(true);
				this.repeatForever.setDisabled(true);
				this.repeatEndDate.setDisabled(true);
				this.repeatEvery.setDisabled(true);
				break;

			case 'DAILY' :
				this.disableDays(true);
				this.monthTime.setDisabled(true);
				this.repeatForever.setDisabled(false);
				this.repeatEndDate.setDisabled(repeatForever);
				this.repeatEvery.setDisabled(false);

				break;

			case 'WEEKLY' :
				this.disableDays(false);
				this.monthTime.setDisabled(true);
				this.repeatForever.setDisabled(false);
				this.repeatEndDate.setDisabled(repeatForever);
				this.repeatEvery.setDisabled(false);

				break;

			case 'MONTHLY_DATE' :
				this.disableDays(true);
				this.monthTime.setDisabled(true);
				this.repeatForever.setDisabled(false);
				this.repeatEndDate.setDisabled(repeatForever);
				this.repeatEvery.setDisabled(false);

				break;

			case 'MONTHLY' :
				this.disableDays(false);
				this.monthTime.setDisabled(false);
				this.repeatForever.setDisabled(false);
				this.repeatEndDate.setDisabled(repeatForever);
				this.repeatEvery.setDisabled(false);
				break;

			case 'YEARLY' :
				this.disableDays(true);
				this.monthTime.setDisabled(true);
				this.repeatForever.setDisabled(false);
				this.repeatEndDate.setDisabled(repeatForever);
				this.repeatEvery.setDisabled(false);
				break;
		}
	},
	disableDays : function(disabled) {
		var days = ['SU','MO','TU','WE','TH','FR','SA'];
		for (var day = 0; day < 7; day++) {
			this.formPanel.form.findField(days[day])
			.setDisabled(disabled);
		}
	},
	disableUntilField : function() {
		if(this.repeatForever.checked)
			this.repeatEndDate.setDisabled(true);
		else
			this.repeatEndDate.setDisabled(false);
	}
});