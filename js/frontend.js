var $j = jQuery.noConflict();

$j(document).ready(function(){

	$j("#lockerBuyButton").click(function(){
		$j(this).fadeOut(function(){
			$j("#lockerForm").fadeIn();
		});
	});
	
	$j("#lockerCheckMailButton").click(function(event){
		event.preventDefault();
		$j(this).fadeOut(function(){
			$j("#lockerCheckMail").fadeIn();
		});
	});

});