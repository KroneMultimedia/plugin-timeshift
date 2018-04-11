function krnTimeshift_autosave() {
$.ajax({
            type: 'POST',
            url: 'post.php?krn_autosave=1',
            data: $('form').serialize(),
            success: function (d) {
							console.log("AUTO SAVED");
            }
          });
}
jQuery(document).ready(function() {
		window.setInterval(function() {
				if(adminpage && adminpage == 'post-php') {
					krnTimeshift_autosave();
				}
		}, 10000);
});
