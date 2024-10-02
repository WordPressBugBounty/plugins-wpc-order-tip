(function($) {
  'use strict';

  $(function() {
    // ready
    $('.wpcot-tip-value.active-default').each(function() {
      var $this = $(this);
      var key = $this.closest('.wpcot-tip').data('key');

      if (!$this.hasClass('active') &&
          (get_cookie('wpcot_tip_' + wpcot_vars.user_id + '_' + key) == '')) {
        $this.trigger('click');
      }
    });
  });

  $('body').on('click touch', '.wpcot-tip-value', function(e) {
    e.preventDefault();
    apply_tip($(this));
  });

  $('body').on('click touch', '.wpcot-tip-value-custom', function(e) {
    e.preventDefault();

    $(this).closest('.wpcot-tip').find('.wpcot-tip-custom').slideToggle();
  });

  $('body').on('click touch', '.wpcot-tip-custom-add', function(e) {
    e.preventDefault();

    var $this = $(this);
    var $tip = $this.closest('.wpcot-tip');
    var value = $tip.find('.wpcot-tip-custom-value').val();
    var key = $tip.data('key');
    var name = $tip.data('name');

    if (value) {
      $this.closest('.wpcot-tip-custom-form').addClass('disabled');
      $tip.find('.wpcot-tip-value-custom').addClass('loading');

      // mark as selected
      set_cookie('wpcot_tip_' + wpcot_vars.user_id + '_' + key, value, 1);

      $.ajax({
        type: 'POST',
        url: wpcot_vars.wc_ajax_url.toString().
            replace('%%endpoint%%', 'wpcot_apply_tip'),
        dataType: 'json',
        data: ({
          action: 'wpcot_apply_tip',
          key: key,
          name: name,
          value: value,
          security: wpcot_vars.a_nonce,
        }),
        success: function(response) {
          $this.closest('.wpcot-tip-custom-form').removeClass('disabled');
          $tip.find('.wpcot-tip-value').removeClass('active');
          $tip.find('.wpcot-tip-value-custom').
              removeClass('loading').
              addClass('active');

          $('body').trigger('update_checkout');

          if ($('button[name="update_cart"]').length) {
            $('button[name="update_cart"]').
                attr('aria-disabled', false).
                removeAttr('disabled').
                trigger('click');
          }
        },
      });
    } else {
      $tip.find('.wpcot-tip-custom-value').focus();
    }
  });

  function apply_tip($value) {
    var value = $value.data('value');
    var key = $value.closest('.wpcot-tip').data('key');
    var name = $value.closest('.wpcot-tip').data('name');

    // mark as selected
    set_cookie('wpcot_tip_' + wpcot_vars.user_id + '_' + key, value, 1);

    $value.addClass('loading');

    if (($value.hasClass('active') && (wpcot_vars.click_again === 'yes')) ||
        $value.hasClass('wpcot-tip-value-none')) {
      // remove tip
      $.ajax({
        type: 'POST',
        url: wpcot_vars.wc_ajax_url.toString().
            replace('%%endpoint%%', 'wpcot_remove_tip'),
        dataType: 'json',
        data: ({
          action: 'wpcot_remove_tip', key: key, security: wpcot_vars.r_nonce,
        }),
        success: function(response) {
          $value.closest('.wpcot-tip').
              find('.wpcot-tip-value').
              removeClass('active loading');

          if ($value.hasClass('wpcot-tip-value-none')) {
            $value.addClass('active');
          }

          $('body').trigger('update_checkout');

          if ($('button[name="update_cart"]').length) {
            $('button[name="update_cart"]').
                attr('aria-disabled', false).
                removeAttr('disabled').
                trigger('click');
          }
        },
      });
    } else {
      // apply tip
      $.ajax({
        type: 'POST',
        url: wpcot_vars.wc_ajax_url.toString().
            replace('%%endpoint%%', 'wpcot_apply_tip'),
        dataType: 'json',
        data: ({
          action: 'wpcot_apply_tip',
          key: key,
          name: name,
          value: value,
          security: wpcot_vars.a_nonce,
        }),
        success: function(response) {
          $value.closest('.wpcot-tip').
              find('.wpcot-tip-value').
              removeClass('active');
          $value.removeClass('loading').addClass('active');

          $('body').trigger('update_checkout');

          if ($('button[name="update_cart"]').length) {
            $('button[name="update_cart"]').
                attr('aria-disabled', false).
                removeAttr('disabled').
                trigger('click');
          }
        },
      });
    }
  }

  function set_cookie(cname, cvalue, exdays) {
    var d = new Date();

    d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));

    var expires = 'expires=' + d.toUTCString();

    document.cookie = cname + '=' + cvalue + '; ' + expires + '; path=/';
  }

  function get_cookie(cname) {
    var name = cname + '=';
    var ca = document.cookie.split(';');

    for (var i = 0; i < ca.length; i++) {
      var c = ca[i];

      while (c.charAt(0) == ' ') {
        c = c.substring(1);
      }

      if (c.indexOf(name) == 0) {
        return decodeURIComponent(c.substring(name.length, c.length));
      }
    }

    return '';
  }
})(jQuery);