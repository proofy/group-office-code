/**
 * Copyright Intermesh
 *
 * This file is part of Group-Office. You should have received a copy of the
 * Group-Office license along with Group-Office. See the file /LICENSE.TXT
 *
 * If you have questions write an e-mail to info@intermesh.nl
 *
 * @version $Id: Portlet.js 16920 2014-02-26 14:44:19Z mschering $
 * @copyright Copyright Intermesh
 * @author Merijn Schering <mschering@intermesh.nl>
 */

GO.calendar.SummaryGroupPanel = function(config)
{
	if(!config)
	{
		config = {};
	}
	
	config.store = new Ext.data.GroupingStore({
		reader: new Ext.data.JsonReader({
			totalProperty: "count",
			root: "results",
			id: "id",
			fields: [
			'id',
			'event_id',
			'name',
			'time',
			'start_time',
			'end_time',
			'description',
			'location',
			'private',
			'repeats',
			'day',
			'calendar_name'
			]
		}),
		baseParams: {
			task:'summary',
			'user_id' : GO.settings.user_id,
			'portlet' : true
		},
		proxy: new Ext.data.HttpProxy({
			url: GO.url("calendar/portlet/portletGrid")
		}),
		groupField:'day',
		sortInfo: {
			field: 'id',
			direction: 'ASC'
		},
		remoteGroup:true,
		remoteSort:true
	});

	config.store.on('load', function(){
		//do layout on Startpage
		this.ownerCt.ownerCt.ownerCt.doLayout();
	}, this);

	config.paging=false,			
	config.autoExpandColumn='summary-calendar-name-heading';


	config.columns=[
	{
		header:GO.lang.strDay,
		dataIndex: 'day'
	},
	{
		header:GO.lang.strTime,
		dataIndex: 'time',
		width:100,
		align:'right',
		groupable:false
	},
	{
		id:'summary-calendar-name-heading',
		header:GO.lang.strName,
		dataIndex: 'name',
		renderer:function(value, p, record){
			p.attr = 'ext:qtip="'+Ext.util.Format.htmlEncode(GO.calendar.formatQtip(record.data))+'"';
			return value;
		},
		groupable:false
	},{
		header:GO.calendar.lang.calendar,
		dataIndex: 'calendar_name',
		width:140
	}];
		
	config.view=  new Ext.grid.GroupingView({
		scrollOffset: 2,
		hideGroupedColumn:true,
		groupTextTpl: '{text} ({[values.rs.length]} {[values.rs.length > 1 ? "'+GO.lang.items+'" : "'+GO.lang.item+'"]})',
		emptyText: GO.calendar.lang.noAppointmentsToDisplay,
		showGroupName:false
	});
	config.sm=new Ext.grid.RowSelectionModel();
	config.loadMask=true;
	config.autoHeight=true;
	
	GO.calendar.SummaryGroupPanel.superclass.constructor.call(this, config);

//with auto expand column this works better otherwise you'll get a big scrollbar
/*this.store.on('load', function(){
		this.addClass('go-grid3-hide-headers');
	}, this, {single:true})*/
	
};


Ext.extend(GO.calendar.SummaryGroupPanel, Ext.grid.GridPanel, {
	
		
	afterRender : function()
	{
		GO.calendar.SummaryGroupPanel.superclass.afterRender.call(this);

		GO.dialogListeners.add('event',{
			save:function(){
				this.store.reload()
			},
			scope:this
		});		

		this.on("rowdblclick", function(grid, rowClicked, e){

			var record = grid.store.getAt(rowClicked);

			if(record.data.contact_id)
			{
				GO.linkHandlers["GO\\Addressbook\\Model\\Contact"].call(this, record.data.contact_id);
			}else
			{				
				GO.calendar.showEventDialog({
					event_id: record.data.event_id
				});
			}
		}, this);
		
		Ext.TaskMgr.start({
			run: function(){this.store.load();},
			scope:this,
			interval:900000
		});  
	}
	
});

GO.mainLayout.onReady(function(){
	
	if(GO.summary)
	{
		var calGrid = new GO.calendar.SummaryGroupPanel({
			//state causes it to load: id: 'summary-calendar-grid'
		});
		
		GO.summary.portlets['portlet-calendar']=new GO.summary.Portlet({
			id: 'portlet-calendar',
			//iconCls: 'go-module-icon-calendar',
			title: GO.calendar.lang.appointments,
			layout:'fit',
			tools: [{
				id: 'gear',
				handler: function(){
					if(!this.selectCalendarWin)
					{
						this.selectCalendarWin = new GO.base.model.multiselect.dialog({
							url:'calendar/portlet',
							columns:[{ header: GO.lang['strName'], dataIndex: 'name', sortable: true }],
							fields:['id','name'],
							title:GO.calendar.lang.visibleCalendars,
							model_id:GO.settings.user_id,
							listeners:{
								hide:function(){
									calGrid.store.reload();
								},
								scope:this
							}
						});
					}
					this.selectCalendarWin.show();
					
//					if(!this.manageCalsWindow)
//					{
//						this.manageCalsWindow = new Ext.Window({
//							layout:'fit',
//							items:this.PortletSettings =  new GO.calendar.PortletSettings(),
//							width:700,
//							height:400,
//							title:GO.calendar.lang.visibleCalendars,
//							closeAction:'hide',
//							buttons:[{
//								text: GO.lang.cmdSave,
//								handler: function(){
//									var params={
//										'task' : 'save_portlet'
//									};
//									if(this.PortletSettings.store.loaded){
//										params['calendars']=Ext.encode(this.PortletSettings.getGridData());
//									}
//									Ext.Ajax.request({
//										url: GO.settings.modules.calendar.url+'action.php',
//										params: params,
//										callback: function(options, success, response){
//											if(!success)
//											{
//												Ext.MessageBox.alert(GO.lang['strError'], GO.lang['strRequestError']);
//											}else
//											{
//												//var responseParams = Ext.decode(response.responseText);
//												this.PortletSettings.store.reload();
//												this.manageCalsWindow.hide();
//
//												calGrid.store.reload();
//											}
//										},
//										scope:this
//									});
//								},
//								scope: this
//							}],
//							listeners:{
//								show: function(){
//									if(!this.PortletSettings.store.loaded)
//									{
//										this.PortletSettings.store.load();
//									}
//								},
//								scope:this
//							}
//						});
//					}
//					this.manageCalsWindow.show();
				}
			},{
				id:'close',
				handler: function(e, target, panel){
					panel.removePortlet();
				}
			}],
			items: calGrid,
			autoHeight:true
			
		});
	}
});