jQuery(document).ready(function($) {
	$('.fetlife-writing-content .clearfix .empc').each(function(){
		var image = $(this).find('img');
		var url = image.attr('src-fetlife');
		var fetlife_picture_url = $(this).find('.mbn a').attr('href');

		$.ajax({
			type : "post",
			dataType : "text",
			url : Fetlife.ajaxurl,
			data : {action: "fetlife_save_picture", url: url, fetlife_picture_url: fetlife_picture_url},
		}).done(function(new_url) {
			image.attr('src', new_url);
			image.removeAttr('src-fetlife');
		});;

		image.addClass('aligncenter');
		var $newElement = $('<p />').append('<p>&nbsp;</p>').append(image).append('<p>&nbsp;</p>');
		$(this).parent().html($newElement);
	});
});