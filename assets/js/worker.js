(function($) {
    // Inside of this function, $() will work as an alias for jQuery()
    // and other libraries also using $ will not be accessible under this shortcut

    // setup autocomplete function pulling from currencies[] array
    jQuery('.select-airport').autocomplete({
        serviceUrl:admin_ajax.ajax_url,
        minChars: 2,
        type : 'POST',
        paramName: 'key',
        params: { action:'airport_live_search' },
        onSelect: function (suggestion) {
          //jQuery('#departure-from-selected').val( suggestion.value + '/' + suggestion.data );
          if (window.console) console.log(JSON.stringify(suggestion));
        }
    });

    jQuery('#raq-form').validator().on('submit', function (e) {
      if (e.isDefaultPrevented()) {
        if (window.console) console.log('handle the invalid form...');
      }else 
      {
        var trip_type= $( "input:radio[name=trip-type]:checked" ).val();
        var departure_from= $('#departure-from').val();
        var departure_date= $( "#departure-day option:selected" ).val() + ' / ' + $( "#departure-month option:selected" ).val() + ' / ' + $( "#departure-year option:selected" ).val();
        var arrival_to= $('#arrival-from').val();
        var arrival_date= $( "#arrival-day option:selected" ).val() + ' / ' + $( "#arrival-month option:selected" ).val() + ' / ' + $( "#arrival-year option:selected" ).val();
        var adults= $( "#adults option:selected" ).val();
        var childs= $( "#child option:selected" ).val();
        var infants= $( "#infant option:selected" ).val();
        var email= $('#email').val();
        var phone= $('#phone').val();

        jQuery.ajax({
            url: admin_ajax.mailchimp_api,
            data: 'ajax=true&email=' + escape(email),
            success: function(msg) {
                if(window.console) console.log( 'mailchimp says: ' + msg);
            }
        });

        jQuery.ajax({
            type : "POST",
            dataType : "text",
            url : admin_ajax.ajax_url,
            data : {
                action: "submit_form",
                trip_type: trip_type,
                departure_from: departure_from,
                departure_date: departure_date,
                arrival_to: arrival_to,
                arrival_date: arrival_date,
                adults: adults,
                childs: childs,
                infants: infants,
                email: email,
                phone: phone
            },
            success: function(response) {
                if (window.console) console.log( 'wp_mail_response: ' + response);                
                show_response(response); //response = {"success":true} or {"success":false}                
            }
        });
      }      
      return false;
    });    

})(jQuery);

function show_response (arg) {
    form =  jQuery('#raq-form');
    check = jQuery.parseJSON( arg );

    if( check.success == true ){

        if (window.console) console.log( 'Email Sent Successfully!' );

        jQuery(form).fadeOut("slow");

        jQuery(form).trigger("reset");

        jQuery('h2#success').fadeIn("slow", function(){
            setTimeout(function(){
                jQuery('h2#success').fadeOut('slow');
                jQuery(form).fadeIn("slow");
            },4000);
            
        });

    }else{

        if (window.console) console.log('There were issue while sending the mail. Please try to send it again.');

        jQuery(form).fadeOut("slow");

        jQuery('h2#error').fadeIn("slow", function(){
            setTimeout(function(){
                jQuery('h2#error').fadeOut('slow');
                jQuery(form).fadeIn("slow");
            },7000);
            
        });

    }
}