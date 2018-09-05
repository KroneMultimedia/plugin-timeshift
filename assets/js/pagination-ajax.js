jQuery(document).ready(function() {
    jQuery('#krn-timeshift').on('click', 'a.page-numbers', function(e) {
        e.preventDefault();
        var timeshift_page = jQuery(this).text();

        if(jQuery(this).hasClass('prev')) {
            var current = jQuery('#krn-timeshift .page-numbers.current');
            if(current.prev('a.page-numbers').length) {
                timeshift_page = parseInt(current.text()) - 1;
            }
        }

        if(jQuery(this).hasClass('next')) {
            var current = jQuery('#krn-timeshift .page-numbers.current');
            if(current.next('a.page-numbers').length) {
                timeshift_page = parseInt(current.text()) + 1;
            }
        }

        krn_timeshift.timeshift_page = timeshift_page;

        jQuery.get(ajaxurl, krn_timeshift, function(response) {
            jQuery('#krn-timeshift .inside').html(response);
        }, 'html');
    });
});