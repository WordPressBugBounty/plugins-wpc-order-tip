'use strict';

(function($) {
  $(function() {
    $('#wpcot-reports-from, #wpcot-reports-to').datepicker({
      dateFormat: 'yy-mm-dd',
    });
  });

  $('body').on('click', '#wpcot-reports-filter', function(e) {
    e.preventDefault();

    var $name = $('#wpcot-reports-names'),
        $from = $('#wpcot-reports-from'),
        $to = $('#wpcot-reports-to'),
        $status = $('#wpcot-reports-status'),
        $error = $('#wpcot-reports-error'),
        $result = $('#wpcot-reports-result'),
        error = wpcot_validate_dates();

    if (!error) {
      $error.empty();

      $('#wpcot-reports').block({
        message: '', overlayCSS: {
          backgroundColor: 'rgb(255,255,255)',
        },
      });

      $.ajax({
        type: 'POST', url: ajaxurl, dataType: 'json', data: ({
          action: 'wpcot_display_reports',
          names: $name.val(),
          from: $from.val(),
          to: $to.val(),
          status: $status.val(),
        }), success: function(response) {
          $result.empty();

          if (response.status == 'error') {
            $.each(response.errors, function(i, err) {
              $error.append('<p>' + err + '</p>');
            });
          } else {
            $result.html(response.result);
          }

          $('#wpcot-reports').unblock();
        },
      });
    }
  });
})(jQuery);

function wpcot_validate_dates() {
  var $from = jQuery('#wpcot-reports-from'),
      $to = jQuery('#wpcot-reports-to'), error = 0;

  if (!$from.val()) {
    $from.css('border', '1px solid red').focus();
    error = 1;
    return error;
  } else {
    $from.css('border', '1px solid #7e8993');
    error = 0;
  }

  if (!$to.val()) {
    $to.css('border', '1px solid red').focus();
    error = 1;
    return error;
  } else {
    $to.css('border', '1px solid #7e8993');
    error = 0;
  }

  return error;
}
