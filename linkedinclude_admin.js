jQuery(document).ready(function() {
	jQuery(".li_display").on("change",function(){
		var item = jQuery(this);
		var liid = item.data("liid"), lish = item.prop("checked");
		
		jQuery.post(ajax_object.ajaxurl, {
			action: 'showhide',
			liid: liid,
			lish: lish
		}, function(data) {
			console.log(data);
			if(data<0) return;
			if(lish<1) { jQuery(".item_"+liid).addClass("unchecked"); }
			else { jQuery(".item_"+liid).removeClass("unchecked"); }

		});
		return;
		
	});


});