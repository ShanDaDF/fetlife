jQuery(document).ready(function($) {
	$(".fetlife-refresh-menu a").click(function(e) {
		nonce = getURLParameter(jQuery(this).attr("href"), 'nonce');

		$('.fetlife-refresh-menu').removeClass('fetlife-refresh-fail');
		$('.fetlife-refresh-menu').removeClass('fetlife-refresh-success');
		$('.fetlife-refresh-menu').addClass('fetlife-refresh-ongoing');
		$('.fetlife-refresh-menu').addClass('fetlife-refresh-menu-processed');

		$.ajax({
			type : "post",
			dataType : "json",
			url : Fetlife.ajaxurl,
			data : {action: "refresh_fetlife", nonce: nonce},
			success: function(response) {
				if(response) {
					$('.fetlife-refresh-menu').addClass('fetlife-refresh-success');
					$('.fetlife-refresh-menu').removeClass('fetlife-refresh-fail');
					$('.fetlife-refresh-menu').removeClass('fetlife-refresh-ongoing');
				} else {
					$('.fetlife-refresh-menu').addClass('fetlife-refresh-fail');
					$('.fetlife-refresh-menu').removeClass('fetlife-refresh-success');
					$('.fetlife-refresh-menu').removeClass('fetlife-refresh-ongoing');
				}
			}
		});
		e.preventDefault();
	});

	function getURLParameter(url, name) {
   		return (RegExp(name + '=' + '(.+?)(&|$)').exec(url)||[,null])[1];
	}
});