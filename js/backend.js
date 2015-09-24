jQuery(document).ready(function(){
	jQuery("select#active").change(function(){
		if (jQuery(this).val() == '1'){
			jQuery("div#activeLocker").fadeIn();
		}else{
			jQuery("div#activeLocker").fadeOut();
		}
	}); 
});