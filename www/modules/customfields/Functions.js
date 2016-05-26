GO.customfields.disableTabs = function(tabPanel, responseResult, root) {	
	if(!root)
		root='customfields';
	for (var i=0; i<tabPanel.items.items.length; i++) {
		
		var panel = tabPanel.items.items[i];
		
		if (typeof(panel.category_id)!='undefined') {

			if(!responseResult[root].disable_categories){
				tabPanel.unhideTabStripItem(panel);
				panel.enableValidation();
			}else
			{
				if(responseResult[root].enabled_categories.indexOf(panel.category_id)>-1){
					tabPanel.unhideTabStripItem(panel);
					panel.enableValidation();
				}else{	
					tabPanel.hideTabStripItem(panel);
					panel.disableValidation();
				}
			}
		}
	}
}