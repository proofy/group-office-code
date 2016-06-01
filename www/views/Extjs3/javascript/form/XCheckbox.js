/**
 * Ext.ux.form.XCheckbox - checkbox with configurable submit values
 *
 * @author  Ing. Jozef Sakalos
 * @version $Id: XCheckbox.js 19784 2016-01-26 13:56:16Z michaelhart86 $
 * @date    10. February 2008
 *
 *
 * @license Ext.ux.form.XCheckbox is licensed under the terms of
 * the Open Source LGPL 3.0 license.  Commercial use is permitted to the extent
 * that the code/component(s) do NOT become part of another Open Source or Commercially
 * licensed development library or toolkit without explicit permission.
 *
 * License details: http://www.gnu.org/licenses/lgpl.html
 */

/**
  * @class Ext.ux.XCheckbox
  * @extends Ext.form.Checkbox
  */
Ext.ns('Ext.ux.form');
Ext.ux.form.XCheckbox = Ext.extend(Ext.form.Checkbox, {
	submitOffValue:'0'
	,
	submitOnValue:'1'

	,
	onRender:function(ct) {

		this.inputValue = this.submitOnValue;

		// call parent
		Ext.ux.form.XCheckbox.superclass.onRender.apply(this, arguments);

		// create hidden field that is submitted if checkbox is not checked
		this.hiddenField = this.wrap.insertFirst({
			tag:'input',
			type:'hidden'
		});

		// update value of hidden field
		this.updateHidden();

	}

	/**
     * Calls parent and updates hiddenField
     * @private
     */
	,
	setValue:function(val) {
		Ext.ux.form.XCheckbox.superclass.setValue.apply(this, arguments);
		this.updateHidden();
	},
	
//	/**
//		* Returns the checked state of the checkbox.
//		* @return {String} '1' if checked, else '0'
//		*/
//	 getValue : function(){
//		 if(this.rendered){
//				 return this.el.dom.checked ? this.submitOnValue : this.submitOffValue;
//		 }
//		 return this.checked ? this.submitOnValue : this.submitOffValue;
//	 },
//
//	 /**
//		* Returns the checked state of the checkbox.
//		* @returns {Boolean} true if checked, else false
//		*/
//	 getValueAsBoolean : function(){
//		 if(this.rendered){
//				 return this.el.dom.checked ? true : false;
//		 }
//		 return this.checked ? true : false;
//	 },

	/**
	 * Updates hiddenField
	 * @private
	 */
	updateHidden:function() {
		if(this.hiddenField) {
			this.hiddenField.dom.value = this.checked ? this.submitOnValue : this.submitOffValue;
			this.hiddenField.dom.name = this.checked ? '' : this.el.dom.name;
		}
	},
	setBoxLabel: function(boxLabel){
		this.boxLabel = boxLabel;
		if(this.rendered){
			this.wrap.child('.x-form-cb-label').update(boxLabel);
		}
	}

}); 
Ext.reg('xcheckbox', Ext.ux.form.XCheckbox);
