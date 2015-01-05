jQuery(document).ready(function($) {
	$('.fetlife-writing-content .clearfix .empc').each(function(){
		var image = $(this).find('img');
		image.addClass('aligncenter');
		var $newElement = $('<p />').append('<p>&nbsp;</p>').append(image).append('<p>&nbsp;</p>');
		$(this).parent().html($newElement);
	});
});