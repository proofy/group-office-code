/**
 * Copyright Intermesh
 *
 * This file is part of Group-Office. You should have received a copy of the
 * Group-Office license along with Group-Office. See the file /LICENSE.TXT
 *
 * If you have questions write an e-mail to info@intermesh.nl
 *
 * @version $Id: FolderPropertiesDialog.js 16919 2014-02-26 14:12:07Z mschering $
 * @copyright Copyright Intermesh
 * @author Merijn Schering <mschering@intermesh.nl>
 */
 
GO.files.FolderPropertiesDialog = function(config){
	
	if(!config)
		config={};

	this.goDialogId='folder';

	this.propertiesPanel = new Ext.Panel({
		layout:'form',
		title:GO.lang['strProperties'],
		cls:'go-form-panel',
		waitMsgTarget:true,
		defaultType: 'textfield',
		labelWidth:100, 
		border:false,   
		items: [
		{
			fieldLabel: GO.lang['strName'],
			name: 'name',
			anchor: '100%',
			validator:function(v){
				return !v.match(/[&\/:\*\?"<>|\\]/);
			}
		},{
			xtype: 'plainfield',
			fieldLabel: GO.lang.strLocation,
			name: 'path'
		},
		{
			xtype: 'plainfield',
			fieldLabel: "URL",
			name: 'url'
		},
		new GO.form.HtmlComponent({
			html:'<hr />'
		}),
		{
			xtype: 'plainfield',
			fieldLabel: GO.lang.strCtime,
			name: 'ctime'
		},
		{
			xtype: 'plainfield',
			fieldLabel: GO.lang.strMtime,
			name: 'mtime'
		},
		{
			xtype: 'plainfield',
			fieldLabel: GO.lang.createdBy,
			name: 'username'
		},
		{
			xtype: 'plainfield',
			fieldLabel: GO.lang.mUser,
			name: 'musername'
		},
		{
			xtype: 'htmlcomponent',
			html:'<hr />'
		},
		{
			xtype:'xcheckbox',
			boxLabel: GO.files.lang.activateSharing,
			name: 'share',
			checked: false,
			hideLabel:true
		},
		this.notifyCheckBox = new Ext.ux.form.XCheckbox({
			boxLabel: GO.files.lang.notifyChanges,
			name: 'notify',
			checked: false,
			hideLabel:true
		}),
		this.applyStateCheckbox = new Ext.ux.form.XCheckbox({
			boxLabel: GO.files.lang.applyState,
			name: 'apply_state',
			checked: false,
			hideLabel:true
		})
		]
	});

	this.readPermissionsTab = new GO.grid.PermissionsPanel({
							
		});
	
	this.commentsPanel = new Ext.Panel({
		layout:'form',
		labelWidth: 70,
		title: GO.files.lang.comments,
		border:false,
		items: new Ext.form.TextArea({
			name: 'comment',
			fieldLabel: '',
			hideLabel: true,
			anchor:'100% 100%'
		})
		
	});
	
	this.tabPanel =new Ext.TabPanel({
		activeTab: 0,
		enableTabScroll:true,
		deferredRender:false,
		border:false,
		anchor:'100% 100%',
		hideLabel:true,
		items:[this.propertiesPanel, this.commentsPanel, this.readPermissionsTab]
	});
	
	if(GO.customfields){
		this.disableCategoriesPanel = new GO.customfields.DisableCategoriesPanel();
		this.tabPanel.add(this.disableCategoriesPanel);
		
		
		if(GO.customfields && GO.customfields.types["GO\\Files\\Model\\Folder"])
		{
			for(var i=0;i<GO.customfields.types["GO\\Files\\Model\\Folder"].panels.length;i++)
			{
				this.tabPanel.add(GO.customfields.types["GO\\Files\\Model\\Folder"].panels[i]);
			}
		}
	}

//	if(GO.workflow)
//	{
//		this.workflowPanel = new GO.workflow.FolderPropertiesPanel();
//		this.tabPanel.insert(2,this.workflowPanel);
//	}
		
	this.formPanel = new Ext.form.FormPanel(
	{
		waitMsgTarget:true,
		border:false,
		defaultType: 'textfield',
		items:this.tabPanel,
		baseParams:{
			notifyRecursive:false
		}
	});
	GO.files.FolderPropertiesDialog.superclass.constructor.call(this,{
		title:GO.lang['strProperties'],
		layout:'fit',
		width:600,
		height:600,
		closeAction:'hide',
		items:this.formPanel,
		buttons:[
		{
			text:GO.lang['cmdOk'],
			handler: function(){
				this.save(true)
				},
			scope: this
		},
		{
			text:GO.lang['cmdApply'],
			handler: function(){
				this.save(false)
				},
			scope: this
		},
			
		{
			text:GO.lang['cmdClose'],
			handler: function(){
				this.hide()
				},
			scope: this
		}
		]		
	});

	this.addEvents({
		'rename' : true,
		'onNotifyChecked' : true
	});
}

Ext.extend(GO.files.FolderPropertiesDialog, GO.Window, {
	parent_id : 0,
	show : function(folder_id)
	{
		//this.folder_id = folder_id;
		
		this.setFolderId(folder_id);
		
		this.notifyCheckBox.removeListener('check',this.onNotifyChecked,this);
		
		this.formPanel.baseParams.notifyRecursive=false;
		
		if(!this.rendered)
			this.render(Ext.getBody());
		
		this.formPanel.form.load({
			url: GO.url('files/folder/load'),
			params: {
				id: folder_id
			},			
			success: function(form, action) {

				var shareField = this.formPanel.form.findField('share');
				shareField.setValue(action.result.data.acl_id>0);
				
				this.parent_id=action.result.data.parent_id;
								
				this.readPermissionsTab.setAcl(action.result.data.acl_id);
				
				this.setPermission(action.result.data.is_someones_home_dir, action.result.data.permission_level, action.result.data.readonly);

				this.tabPanel.setActiveTab(0);
				if(GO.customfields)
					this.disableCategoriesPanel.setModel(folder_id,"GO\\Files\\model\\File");
				
				this.notifyCheckBox.addListener('check',this.onNotifyChecked,this);
				
				GO.dialog.TabbedFormDialog.prototype.setRemoteComboTexts.call(this, action);
				
				GO.files.FolderPropertiesDialog.superclass.show.call(this);
			},
			failure: function(form, action) {
				Ext.MessageBox.alert(GO.lang['strError'], action.result.feedback);
			},
			scope: this
		});		
	},
	
	setFolderId : function(id){
		this.folder_id=id;
	},
	
	onNotifyChecked : function(checkbox,checked) {
		Ext.Msg.show({
			title: checked  ? GO.files.lang.notifyRecursiveTitle :  GO.files.lang.removeNotifyRecursiveTitle,
			msg: GO.files.lang.notifyRecursiveQuestion,
			buttons: Ext.Msg.YESNO,
			fn: function (btn){
				this.formPanel.baseParams['notifyRecursive'] = btn=='yes';
			},
			scope: this
		});
	},
	
	setPermission : function(is_someones_home_dir, permission_level, readonly)
	{
		//readonly flag is set for project, contact, company etc. folders.
			
		var form = this.formPanel.form;
		form.findField('name').setDisabled(is_someones_home_dir || readonly || permission_level<GO.permissionLevels.write);
		form.findField('share').setDisabled(is_someones_home_dir || readonly || permission_level<GO.permissionLevels.manage);
		form.findField('apply_state').setDisabled(permission_level<GO.permissionLevels.manage && !GO.settings.has_admin_permission);
		if(!this.readPermissionsTab.disabled)
			this.readPermissionsTab.setDisabled(!is_someones_home_dir && readonly);
	},
	
	save : function(hide)
	{
		this.formPanel.form.submit({
						
			url: GO.url('files/folder/submit'),
			params: {
				id: this.folder_id
			},
			waitMsg:GO.lang['waitMsgSave'],
			success:function(form, action){

				if(typeof(action.result.acl_id) != 'undefined')
				{
					this.readPermissionsTab.setAcl(action.result.acl_id);
				}
				
				if(action.result.new_path)
				{
					this.formPanel.form.findField('path').setValue(action.result.new_path);
					this.fireEvent('rename', this, this.parent_id);				
				}
				this.fireEvent('save', this, this.folder_id, this.parent_id);
				
				GO.dialog.TabbedFormDialog.prototype.refreshActiveDisplayPanels.call(this);
				
				if(hide)
				{
					this.hide();
				}				
				
			},
	
			failure: function(form, action) {
				var error = '';
				if(action.failureType=='client')
				{
					error = GO.lang['strErrorsInForm'];
				}else
				{
					error = action.result.feedback;
				}
				
				Ext.MessageBox.alert(GO.lang['strError'], error);
			},
			scope:this
			
		});
			
	}	
});
