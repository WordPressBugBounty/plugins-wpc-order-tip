(function($) {
  'use strict';

  $(function() {
    // ready
    init_sortable();
    init_custom();
    init_roles();
    $('.wpcot_color_picker').wpColorPicker();
  });

  $(document).on('click touch', '.wpcot-tip-header', function(e) {
    if (($(e.target).closest('.wpcot-tip-duplicate').length === 0) &&
        ($(e.target).closest('.wpcot-remove-tip').length === 0)) {
      $(this).closest('.wpcot-tip').toggleClass('active');
    }
  });

  $(document).on('click touch', '.wpcot-remove-tip', function() {
    var r = confirm('Do you want to remove this tip? This action cannot undo.');
    if (r == true) {
      let $this = $(this);
      $this.closest('.wpcot-tip').remove();
    }
  });

  $(document).on('click', '.wpcot-remove-value', function() {
    $(this).closest('.wpcot-value').remove();
  });

  $(document).on('click', '.wpcot-add-value', function() {
    let $this = $(this), count = $this.data('count'), key = $this.data('key'),
        value_html = `<div class="wpcot-value">
    <span class="wpcot-label-wrapper"><input type="text" placeholder="label" name="wpcot_tips[${key}][values][${count}][label]"></span>
    <span class="wpcot-value-wrapper hint--top" aria-label="${wpcot_vars.hint_value}"><input type="text" placeholder="value" name="wpcot_tips[${key}][values][${count}][value]"></span>
    <span class="wpcot-remove-value hint--top" aria-label="${wpcot_vars.hint_remove}">&times;</span></div>`;
    $this.closest('.wpcot-tip').find('.wpcot-values').append(value_html);
    init_sortable();
    $this.data('count', count + 1);
  });

  $(document).on('click', '.wpcot-add-tip', function() {
    let $this = $(this);
    $this.prop('disabled', true);
    $.post(ajaxurl, {
      action: 'wpcot_add_tip',
    }, function(response) {
      $('.wpcot-tips-wrapper .wpcot-tips').append(response);
      init_roles();
      $this.prop('disabled', false);
    });
  });

  $(document).
      on('keyup change keypress', '.wpcot-tip-name', function() {
        // sync label
        let $this = $(this), value = $this.val();
        $this.closest('.wpcot-tip').find('.wpcot-tip-label').text(value);
      });

  $(document).on('change', '.wpcot-custom', function() {
    init_custom();
  });

  function init_sortable() {
    $('.wpcot-tips').sortable({
      handle: '.wpcot-tip-move',
    });
  }

  function init_custom() {
    $('.wpcot-custom').each(function() {
      if ($(this).val() === 'yes') {
        $(this).closest('.wpcot-tip').find('.wpcot-show-if-custom').show();
      } else {
        $(this).closest('.wpcot-tip').find('.wpcot-show-if-custom').hide();
      }
    });
  }

  function init_roles() {
    $('.wpcot_roles_select').selectWoo();
  }
})(jQuery);
