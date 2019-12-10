console.log( "My script is loaded" );
console.log( param.ajax_url );

/*
jQuery( document ).on( 'click', '.onoffswitch-checkbox', function() {
    var checked = jQuery(this).attr("checked");
    console.log( "Checkbox is: " + checked );
})*/
//background-color: rgb(68, 193, 52);
var refreshTimer;

function refreshLine() {
    var refreshOject    = jQuery(".refresh_line");
    refreshOject.stop( true );

    var refresh         = parseInt( refreshOject.attr('refresh') );
    var diff            = parseInt( refreshOject.attr('diff_seconds') );
    var animation_time  = (refresh*1000 - diff*1000);
    var startwidth      = refreshOject.width();
    //console.log(refresh + " " + diff);
    console.log( "Number of animations in queue: " + refreshOject.queue().length );
    
    if ( diff > (refresh + (refresh / 1) ) ) {
        //Offline
        console.log("Sensor offline in: " + diff + " seconds");
        refreshOject.css("background-color", "red");
        //clearTimeout(refreshTimer);
        //refreshTimer = setTimeout( reload, refresh*1000 );
        refreshOject.animate( {
            width: 0
        }, {
            duration: refresh*1000,
            easing: "linear",
            complete: function() {
                console.log("Time to update");
                reload();
            }
        });
    }
    else {
        console.log("Sensor: Online");
        refreshOject.animate( {
            width: 0
        }, {
            duration: animation_time,
            easing: "linear",
            complete: function() {
                console.log("Time to update");
                reload();
            },
            step: function( now, fx ) {
                var endwidth     = 0;
                var percent_left = now / startwidth;
                var time_left    = Math.round( animation_time * percent_left);
                jQuery(this).attr('animate_timeleft', time_left);
            }
        });
    }
}

jQuery( document ).on( 'ajax_loaded', function() {
    //console.log("ajax_loaded");
    refreshLine();
});
jQuery( document ).ready( function() {
    refreshLine();
});

function reload() {
    var parentObject = jQuery( ".iot_widget_device" );
    var device_id    = parentObject.attr('id');
    var pin          = parentObject.find('.PIN').html();

    jQuery.ajax({
        url: param.ajax_url,
        type: 'post',
        data: {
            'action'      : 'device_reload',
            'device_id'   : device_id,
            'pin'         : pin
        },
        success: function( response ) {
            //console.log(response.success)
            parentObject.html(response.html);
            jQuery( document ).trigger( 'ajax_loaded' );
            parentObject.removeClass('msp-selected');
        },
        error: function() {
            var refreshOject = jQuery(".refresh_line");
            refreshOject.css("background-color", "red");
            refreshOject.css("width", "100%");
            jQuery( document ).trigger( 'ajax_loaded' );
            parentObject.removeClass('msp-selected');
        }
    })

}

jQuery( document ).on( 'click', '.onoffswitch-checkbox', function() {
    clearTimeout(refreshTimer);
    var refreshOject    = jQuery(".refresh_line");
    refreshOject.stop( true );
    
    var parentObject = jQuery(this).parents(".iot_widget_device");
    var device_id    = parentObject.attr('id');
    var pin          = parentObject.find('.PIN').html();
    var refresh      = parseInt( refreshOject.attr('refresh') );
    var time_left    = parseInt( refreshOject.attr('animate_timeleft') );
    /*
    console.log( "Time left: " + time_left );
    console.log( "Refresh left: " + refresh );
    console.log( "Wait time: " + (refresh*1000 + 500 + time_left) + "ms");
    */

    var sensor_id    = jQuery(this).attr("sensor");
    var value        = jQuery(this).closest("input").attr("checked");

    if ( value == "checked" ) {
        value = 1;
    } else {
        value = 0;
    }
    //var html = parentObject.html();
    //parentObject.html("<center>...arbetar med det...</center>");
    parentObject.addClass('msp-selected');

    /*
    console.log("Device id: " + device_id);
    console.log("Clicked onoffswitch for sensor: " + sensor_id);
    console.log("Input value: " + value);
    console.log("PIN value: " + pin);
    */
    jQuery.ajax({
        url: param.ajax_url,
        type: 'post',
        data: {
            'action'      : 'sensor_change',
            'sensor_id'   : sensor_id,
            'sensor_value': value,
            'device_id'   : device_id,
            'pin'         : pin
        },
        success: function( response ) {
            console.log( "Server success: " + response.success + " (true/false)")
            parentObject.html(response.html);
            if ( response.success ) {
                //refreshTimer = setTimeout( reload, (refresh*1000 + 500 + time_left) );
                var refreshOject = jQuery(".refresh_line");
                refreshOject.animate( {
                    width: 0
                }, {
                    duration: (refresh*1000 + 500 + time_left),
                    easing: "linear",
                    complete: function() {
                        console.log("Reload to se if device is updated");
                        reload();
                    }
                });
            } else {
                jQuery( document ).trigger( 'ajax_loaded' );
                parentObject.removeClass('msp-selected');
            }
        }
    })
})


