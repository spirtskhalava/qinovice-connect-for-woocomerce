jQuery(document).ready(function($) {
		
    $("#doaction, #doaction2").click(function (event) {
        var actionselected = $(this).attr("id").substr(2);
        var pdf = $('select[name="' + actionselected + '"]').val();
        if ( pdf == "quote" || pdf == "invoice") {
			event.preventDefault();
			var checked = [];
			$('tbody th.check-column input[type="checkbox"]:checked').each(
			    function() {
			        checked.push($(this).val());
			    }
			);
			
			var order_ids=checked.join('x');
			url = wcqc_ajax.ajaxurl+'?action=generate_wcqc&request_type='+pdf+'&order_ids='+order_ids+'&_wpnonce='+wcqc_ajax.nonce;
			window.open(url,'_blank');
        }
    });
});

