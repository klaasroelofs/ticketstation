jQuery(window).ready(function(){
	
	jQuery('.userinfo').click( function() {

		if (jQuery('.userinfo-logout').is(':hidden')) {
			jQuery('.userinfo-logout').slideDown(350);
			setTimeout(
					function() {
						toggleArrow('#arrow', 'up');		
					}, 350);
		} else {
			jQuery('.userinfo-logout').slideUp(350);
			setTimeout(
					function() {
						toggleArrow('#arrow', 'down');		
					}, 350);
		}
		
	});
	
	jQuery('#clientcount').click( function() {

		if (jQuery('#clientdetails').is(':hidden')) {
			
			if (jQuery('#multipleordersdetails').not(':hidden')) {
				jQuery('#multipleordersdetails').slideUp(500);
				setTimeout(
					function() {
						toggleArrow('#clients-multiple-orders-arrow', 'down');		
					}, 500);
			}
				
			jQuery('#clientdetails').slideDown(500);
			setTimeout(
					function() {
						toggleArrow('#clients-no-orders-arrow', 'up');		
					}, 500);
		} else {
			jQuery('#clientdetails').slideUp(500);
			setTimeout(
					function() {
						toggleArrow('#clients-no-orders-arrow', 'down');		
					}, 500);
		}
		
	});
	
	jQuery('#multipleorderscount').click( function() {

		if (jQuery('#multipleordersdetails').is(':hidden')) {
			
			if (jQuery('#clientdetails').not(':hidden')) {
				jQuery('#clientdetails').slideUp(500);
				setTimeout(
					function() {
						toggleArrow('#clients-no-orders-arrow', 'down');		
					}, 500);
			}
			
			jQuery('#multipleordersdetails').slideDown(500);
			setTimeout(
					function() {
						toggleArrow('#clients-multiple-orders-arrow', 'up');		
					}, 500);
		} else {
			jQuery('#multipleordersdetails').slideUp(500);
			setTimeout(
					function() {
						toggleArrow('#clients-multiple-orders-arrow', 'down');		
					}, 500);				
		}
		
	});
	
	function toggleArrow(elementid, newdirection) {
		if (newdirection === 'up') {
			jQuery(elementid).removeClass('bi-chevron-down');
			jQuery(elementid).addClass('bi-chevron-up');
		} else {				
			jQuery(elementid).removeClass('bi-chevron-up');
			jQuery(elementid).addClass('bi-chevron-down');
		}
	}

});
	
	
