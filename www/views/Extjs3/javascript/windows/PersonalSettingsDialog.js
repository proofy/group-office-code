/** 
 * Copyright Intermesh
 * 
 * This file is part of Group-Office. You should have received a copy of the
 * Group-Office license along with Group-Office. See the file /LICENSE.TXT
 * 
 * If you have questions write an e-mail to info@intermesh.nl
 * 
 * @version $Id: PersonalSettingsDialog.js 14816 2013-05-21 08:31:20Z mschering $
 * @copyright Copyright Intermesh
 * @author Merijn Schering <mschering@intermesh.nl>
 */
 
GO.PersonalSettingsDialog = Ext.extend(GO.dialog.TabbedFormDialog , {

	initComponent : function(){
		
		Ext.apply(this, {
			goDialogId:'settings',
			title:GO.lang.settings,
			formControllerUrl: 'settings',
			width:900,
			height:550,
			enableApplyButton:false
		});
		
		GO.PersonalSettingsDialog.superclass.initComponent.call(this);	
	},
	
	buildForm : function(){
		var panels =GO.moduleManager.getAllSettingsPanels();
		
		for(var i=0;i<panels.length;i++)
			this.addPanel(panels[i]);
	},
	
	afterLoad : function(remoteModelId, config, action){
		for(var i=0;i<this._tabPanel.items.getCount();i++)
		{
			var panel = this._tabPanel.items.itemAt(i);
			if(panel.onLoadSettings)
			{
				var func = panel.onLoadSettings.createDelegate(panel, [action]);
				func.call();							 
			}
		}			
	},

	beforeSubmit : function(){
		for(var i=0;i<this._tabPanel.items.getCount();i++)
		{
			var panel = this._tabPanel.items.itemAt(i);
			if(panel.onBeforeSaveSettings)
			{
				var func = panel.onBeforeSaveSettings.createDelegate(panel, [this]);
				var result = func.call();
				if(!result)
				{
					this._tabPanel.setActiveTab(panel);
					return false;
				}
			}
		}
	},
	
	show : function (remoteModelId, config) {
		
		remoteModelId = GO.settings.user_id;
		GO.PersonalSettingsDialog.superclass.show.call(this, remoteModelId, config);	
	},
	afterSubmit : function(action) {	
		// Reload Groupoffice to use the new settings
		document.location = GO.url('');
	}
});