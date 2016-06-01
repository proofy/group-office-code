/**
 * Copyright Intermesh
 *
 * This file is part of Group-Office. You should have received a copy of the
 * Group-Office license along with Group-Office. See the file /LICENSE.TXT
 *
 * If you have questions write an e-mail to info@intermesh.nl
 *
 * @version $Id: Overrides.js 18792 2015-02-20 10:04:17Z wsmits $
 * @copyright Copyright Intermesh
 * @author Wesley Smits <wsmits@intermesh.nl>
 */

GO.moduleManager.onModuleReady('email',function(){
	Ext.override(GO.email.AccountDialog, {	
		initComponent : GO.email.AccountDialog.prototype.initComponent.createSequence(function(){

			this.sieveGrid = new GO.sieve.SieveGrid();
			this.outOfOfficePanel = new GO.sieve.OutOfOfficePanel();

			var inPos = this.tabPanel.items.indexOf(this.filterGrid);
			this.tabPanel.insert(inPos,this.outOfOfficePanel);
			this.tabPanel.insert(inPos,this.sieveGrid);

			this.tabPanel.hideTabStripItem(this.filterGrid);
		}),
		
		sieveCheck :function(account_id){
			
			if(!GO.util.empty(account_id)){
				this.account_id = account_id;
			}

			if(this.account_id > 0)
			{
				GO.request({
					maskEl:this.getEl(),
					url: "sieve/sieve/isSupported",
					success: function(response, options, result){
						
						if(result.supported)
						{
							// Hide the 'normal' panel and show this panel
							this.tabPanel.hideTabStripItem(this.filterGrid);
							
							this.tabPanel.unhideTabStripItem(this.sieveGrid);
							this.tabPanel.unhideTabStripItem(this.outOfOfficePanel);
							this.outOfOfficePanel.disableFields(false);			
						}
						else
						{
							// Hide this panel and show the 'normal' panel
							this.tabPanel.hideTabStripItem(this.sieveGrid);
							this.tabPanel.hideTabStripItem(this.outOfOfficePanel);
							this.outOfOfficePanel.disableFields(true);
							
							this.tabPanel.unhideTabStripItem(this.filterGrid);
						}						
					},
					fail: function(response){
						alert(GO.sieve.lang.checksieveerror);						
					},
					params: {
						account_id: this.account_id
					},
					scope:this
				});
			}
		},
		setAccountId : GO.email.AccountDialog.prototype.setAccountId.createSequence(function(account_id){
			
			// Check if sieve is supported with the account settings of this account id
			this.sieveCheck(account_id);
			
			this.sieveGrid.setAccountId(account_id);
			this.outOfOfficePanel.setAccountId(account_id);
		}),
		
		show : GO.email.AccountDialog.prototype.show.createSequence(function(accountId){
			if(GO.util.empty(accountId)){
				this.tabPanel.hideTabStripItem(this.sieveGrid);
				this.tabPanel.hideTabStripItem(this.outOfOfficePanel);
				this.outOfOfficePanel.disableFields(true);

				this.tabPanel.unhideTabStripItem(this.filterGrid);
			}
		})
	});
});