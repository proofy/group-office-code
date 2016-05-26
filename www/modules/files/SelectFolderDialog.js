GO.files.SelectFolderDialog = Ext.extend(GO.Window, {
	
	initComponent : function(){
		
		if(!this.scope)
			this.scope=this;
		
		this.layout='fit';
		this.title=GO.files.lang.selectFolder;
		this.height=500;
		this.width=500;
		this.modal=true;
		this.border=false;
		this.collapsible=true;
		this.maximizable=true;
		this.closeAction='hide';
			
		this.buttons=[
			{
				text: GO.lang.cmdOk,				        						
				handler: function(){
					var sm = this.foldersTree.getSelectionModel();
					var selectedFolderNode = sm.getSelectedNode();
					if(!selectedFolderNode)
						alert('Fout');
					this.handler.call(this.scope, this, selectedFolderNode.attributes.path);
					this.hide();
				}, 
				scope: this 
			},{
				text: GO.lang.cmdClose,				        						
				handler: function(){
					this.hide();
				},
				scope:this
			}				
		];
		
		this.foldersTree = new GO.files.TreePanel({
			border:false,
			loadDelayed:true,
			hideActionButtons:true,
			treeCollapsed:false,
			scope: this,
			selModel: new Ext.tree.DefaultSelectionModel()
		});
		
		this.items=[this.foldersTree];
		
		GO.files.SelectFolderDialog.superclass.initComponent.call(this);
	}
	
});