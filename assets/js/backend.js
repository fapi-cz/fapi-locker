jQuery(function($)
{
	$("input#active").click(function()
	{
		$("div#activeLocker")['fade' + ($(this).prop('checked') ? 'In' : 'Out')]();
	});

	$("input#showOrderForm").click(function()
	{
		$("div#activeShowOrderForm")['fade' + ($(this).prop('checked') ? 'In' : 'Out')]();
	});

	$( document ).ready(function() {
		$("div#activeShowOrderForm")['fade' + ($("input#showOrderForm").prop('checked') ? 'In' : 'Out')]();
	});


	$('#lockerOptionsButtonBackground,#lockerOptionsButtonColor')
		.colpick(
		{
			layout:'hex',
			submit:0,
			colorScheme:'dark',
			onChange:function(hsb,hex,rgb,el,bySetColor)
			{
				if(!bySetColor) $(el).val(hex);
			}
		})
		.keyup(function()
		{
			$(this).colpickSetColor(this.value);
		})
		.click(function()
		{
			$(this).colpickSetColor(this.value);
		});

	removeInvoices();

	function removeInvoices()
	{
		$(".removeInvoice").click(function(event)
		{
			event.preventDefault();

			$(this).parent().fadeOut(function()
			{
				$(this).remove();
			});
		});
	}
	
	$("#addInvoice").click(function(event)
	{
		event.preventDefault();
		$( "#invoice" ).clone().attr("id","").appendTo( "#invoiceSelects" ).wrapAll("<div>").after('&nbsp;<a class="removeInvoice" href="#">X</a> <div class="clear"></div>');
		removeInvoices();
	});
	
});
