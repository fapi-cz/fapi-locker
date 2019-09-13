var $j = jQuery.noConflict();

$j(document).ready(function(){

	$j("#lockerBuyButton").click(function(){
		$j(this).fadeOut(function(){
			$j("#lockerForm").fadeIn();
			$j("#lockerCheckMail").fadeOut();
			$j("#lockerCheckMailButton").fadeOut();
			$j(".hide").fadeOut();
			$j(".")

		});
	});
	
	$j("#lockerCheckMailButton").click(function(event){
		event.preventDefault();
		$j(this).fadeOut(function(){
			$j("#lockerCheckMail").fadeIn();
			$j(".hide").fadeIn();
		});
	});

});
