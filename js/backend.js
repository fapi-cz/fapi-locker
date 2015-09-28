jQuery(document).ready(function(){
	jQuery("select#active").change(function(){
		if (jQuery(this).val() == '1'){
			jQuery("div#activeLocker").fadeIn();
		}else{
			jQuery("div#activeLocker").fadeOut();
		}
	}); 
	

jQuery('#lockerOptionsButtonBackground,#lockerOptionsButtonColor').colpick({
	layout:'hex',
	submit:0,
	colorScheme:'dark',
	onChange:function(hsb,hex,rgb,el,bySetColor) {
		if(!bySetColor) jQuery(el).val(hex);
	}
}).keyup(function(){
	jQuery(this).colpickSetColor(this.value);
}).click(function(){
	jQuery(this).colpickSetColor(this.value);
});;

	removeInvoices();
	
	function removeInvoices(){
		jQuery(".removeInvoice").click(function(event){
			event.preventDefault();
			jQuery(this).parent().fadeOut(function(){
				jQuery(this).remove();
			});
		});
	}
	
	jQuery("#addInvoice").click(function(event){
		event.preventDefault();
		jQuery( "#invoice" ).clone().attr("id","").appendTo( "#invoiceSelects" ).wrapAll("<div>").after("&nbsp;<a class=\"removeInvoice\" href=\"#\">X</a> <div class=\"clear\"></div>");
		removeInvoices();
	});
	
});