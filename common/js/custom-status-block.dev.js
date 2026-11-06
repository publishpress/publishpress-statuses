/**
 * PublishPress Statuses
 *
 * Copyright 2024, PublishPress
 */

/**
 * ------------------------------------------------------------------------------
 * Portions of this module were originally derived from the Edit Flow plugin
 * Author: Daniel Bachhuber, Scott Bressler, Mohammad Jangda, Automattic, and
 * others
 * Copyright (c) 2009-2019 Mohammad Jangda, Daniel Bachhuber, et al.
 * ------------------------------------------------------------------------------
 */

/**
 * Map Custom Statuses as options for SelectControl
 */

if (!jQuery.isFunction('$')) {
  var $ = window.jQuery = jQuery;
}

var ppStatuses = window.PPCustomStatuses.statuses.map(function (s) {
  var ret = {
    label: s.label,
    save_as: s.save_as,
    submit: s.submit,
    icon: s.icon,
    color: s.color,
    alternate: s.alternate,
    value: s.name
  };

  return ret;
});

var publishedStatuses = window.PPCustomStatuses.publishedStatusObjects.map(function (s) {
  return {
    label: s.label,
    save_as: s.save_as,
    submit: s.submit,
    icon: s.icon,
    color: s.color,
    value: s.name
  };
});

var ppsCaptions = window.PPCustomStatuses.captions;

var ppGetStatusLabel = function ppGetStatusLabel(slug) {
  var item = ppStatuses.find(function (s) {
    return s.value === slug;
  });

  return (item) ? item.label : '';
};

var ppGetStatusSaveAs = function ppGetStatusSaveAs(slug) {
  var item = ppStatuses.find(function (s) {
    return s.value === slug;
  });

  // @todo: API
  if (!item && ('undefined' != typeof(window.PPCustomPrivacy))) {
    var privacyStatuses = window.PPCustomPrivacy.statuses.map(function (s) {
      return {
        label: s.label,
        value: s.name
      };
    });

    item = privacyStatuses.find(function (s) {
      return s.value === slug;
    });

    var saveAsCaption = window.PPCustomStatuses.saveAsCaption;

    return (item) ? saveAsCaption.replace('%s', item.label) : '';
  } else {
    return (item) ? item.save_as : '';
  }
};

var ppGetStatusSubmit = function ppGetStatusSubmit(slug) {
  var item = ppStatuses.find(function (s) {
    return s.value === slug;
  });

  return (item) ? item.submit : '';
};

var ppGetStatusObject = function ppGetStatusObject(slug) {
  var item = ppStatuses.find(function (s) {
    return s.value === slug;
  });

  return (item) ? item : false;
};

var ppGetPublishedStatusObject = function ppGetStatusObject(slug) {
  var item = publishedStatuses.find(function (s) {
    return s.value === slug;
  });

  return (item) ? item : false;
};

/*
 * Return the text colour (near-black or white) that gives the higher contrast
 * ratio on the given status background colour (WCAG relative luminance).
 */
var ppStatusTextColor = function ppStatusTextColor(color) {
  var rgb = null, m;

  if (color) {
    color = String(color).trim();

    if ((m = color.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i))) {
      var hex = m[1];

      if (3 == hex.length) {
        hex = hex.charAt(0) + hex.charAt(0) + hex.charAt(1) + hex.charAt(1) + hex.charAt(2) + hex.charAt(2);
      }

      rgb = [parseInt(hex.substr(0, 2), 16), parseInt(hex.substr(2, 2), 16), parseInt(hex.substr(4, 2), 16)];
    } else if ((m = color.match(/^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)/i))) {
      rgb = [parseInt(m[1], 10), parseInt(m[2], 10), parseInt(m[3], 10)];
    }
  }

  if (!rgb) {
    return 'white';
  }

  var lum = rgb.map(function (c) {
    c = c / 255;
    return (c <= 0.03928) ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
  });

  lum = 0.2126 * lum[0] + 0.7152 * lum[1] + 0.0722 * lum[2];

  // #1e1e1e has a relative luminance of about 0.013
  return ((1.05 / (lum + 0.05)) >= ((lum + 0.05) / 0.063)) ? 'white' : '#1e1e1e';
};

// Announce a message to screen readers, if wp.a11y is available
var ppcsSpeak = function ppcsSpeak(message) {
  if (message && window.wp && wp.a11y && wp.a11y.speak) {
    wp.a11y.speak(message, 'polite');
  }
};

var ppcsOrigSaveDraftWidth = 0;
var ppcsSaveDraftLinkColor = false;
var ppcsRestoreSaveFocus = false;

/*
 * Remove the cloned Save button. If it had keyboard focus, move focus to the
 * stock control that replaces it, or restore it to the clone once re-created.
 */
function PPCS_RemoveSaveButton() {
  var $clone = jQuery('span.presspermit-save-button');

  if (!$clone.length) {
    return;
  }

  var hadFocus = document.activeElement && $clone.find(document.activeElement).length;

  $clone.remove();

  if (hadFocus) {
    var $target = jQuery('button.editor-post-saved-state:visible, button.editor-post-save-draft:visible').first();

    if ($target.length) {
      $target.trigger('focus');
    } else {
      ppcsRestoreSaveFocus = true;
    }
  }
}

var ppLastPostStatus = '';
var ppQueriedStatuses = false;

var querySelectableStatuses = function(status, post_id) {
  if (typeof post_id == "undefined") {
    post_id = wp.data.select('core/editor').getCurrentPostId();
  }

  // Update status selectability
  var params = {
      action: 'pp_get_selectable_statuses',
      post_id: post_id,
      selected_status: status,
      pp_nonce: PPCustomStatuses.ppNonce
  };

  if (!ppQueriedStatuses) {
    jQuery(document).ready(function ($) {
      $('div.publishpress-extended-post-status select option').hide();

      if (objCustomStatusBlock.currentStatus) {
        $('div.publishpress-extended-post-status select option[value="' + objCustomStatusBlock.currentStatus + '"]').hide();
      }
    });

    ppQueriedStatuses = true;
  }

  jQuery.post(PPCustomStatuses.ajaxurl, params, function (retval) {
      var selectable_statuses = retval['data'];

      jQuery(document).ready(function ($) {
        $('div.publishpress-extended-post-status select option').each(function() {
          if ($(this).attr('alternate')) {
            $('div.publishpress-extended-post-status select option[value="_"]').attr('disabled', 'disabled').attr('aria-label', ppsCaptions.alternateWorkflows || '').show().insertBefore($(this).parent().find('[value="' + $(this).attr('value') + '"]'));
            return false;
          }
        });

        $('div.publishpress-extended-post-status select option').each(function() {
          if (selectable_statuses.indexOf($(this).val()) != -1 || $(this).val() == objCustomStatusBlock.currentStatus) {
           $(this).show();
          } 
        });



        $('div.publishpress-extended-post-status select option[value="publish"]').insertBefore('div.publishpress-extended-post-status select option[value="_"]');
      });

      if (typeof (retval['params']) != 'undefined') {
        if (typeof (retval['params']['nextStatus']) != 'undefined') {
          ppObjEdit.nextStatus = retval['params']['nextStatus'];
        }

        if (typeof (retval['params']['maxStatus']) != 'undefined') {
          ppObjEdit.maxStatus = retval['params']['maxStatus'];
        }
      }
  }).fail(function() {

  });
}

var tmrQuerySelectableStatuses;

var refreshSelectableStatuses = function (status) {
  if (status != ppLastPostStatus) {
    
    tmrQuerySelectableStatuses = setInterval(
      function() {
        var post_id = wp.data.select('core/editor').getCurrentPostId();

        if (post_id) {
          clearInterval(tmrQuerySelectableStatuses, post_id);
          ppLastPostStatus = status;
          querySelectableStatuses(status);
        }
      }, 100
    )
  }
}



/******************** FUNCTIONS FOR RECAPTIONING SAVE AS BUTTON **************************/

var ppLastPrepanelVisibility = false;

/*
* The goal is to allow recaptioning "Save draft" to "Save as Pitch" etc.
*/
function PPCS_RecaptionButton(btnSelector, btnCaption) {
  jQuery(document).ready(function ($) {
    var node = $(btnSelector);

    var status = '';
    
    if ($('div.publishpress-extended-post-status select:visible').length && ('status' == PPCustomStatuses.statusRestProperty)) {
      status = wp.data.select('core/editor').getEditedPostAttribute('pp_status_selection');
    }

    if (!status) {
      status = wp.data.select('core/editor').getEditedPostAttribute(PPCustomStatuses.statusRestProperty);
    }

    if (!btnCaption || $('div.publishpress-extended-post-status select:visible').length) { //@todo: review now that pp_status_selection property is used
      btnCaption = ppGetStatusSaveAs(status);
    }

    if (wp.data.select('core/editor').isSavingPost()) {
      return;
    }

    if (('draft' == status) || !$(btnSelector).length) {
      PPCS_RemoveSaveButton();
      return;
    }

    if ($(btnSelector).length && btnCaption 
    && (btnCaption != $('span.presspermit-save-button button').html() || !$('span.presspermit-save-button button:visible').length || $('button.editor-post-save-draft:visible').length)
    ) {
      var hideClass = 'presspermit-save-hidden';

      if (!$('.presspermit-save-button').length
      ) {
        // Clone the stock button
        // (no id, and no aria-label, so the accessible name matches the visible caption)
        node.after('<span class="presspermit-save-button">' + node.clone().css('z-index', 0).removeClass(hideClass).removeClass('editor-post-save-draft').removeAttr('disabled').removeAttr('aria-disabled').removeAttr('id').removeAttr('aria-label').removeAttr('style').css('white-space', 'nowrap').css('position', 'relative').show().html(btnCaption).wrap('<span>').parent().html() + '</span>');

        if (ppcsRestoreSaveFocus) {
          ppcsRestoreSaveFocus = false;

          if (!document.activeElement || document.activeElement === document.body) {
            $('span.presspermit-save-button button').trigger('focus');
          }
        }

        // Force regeneration of label if prepublish panel is displayed, then hidden
        setInterval(function() {
          var prepanelVisible = $('.editor-post-publish-panel__prepublish:visible').length;

          if (ppLastPrepanelVisibility) {
            if (!prepanelVisible) {
              if ($('div.publishpress-extended-post-status select:visible').length) {
                PPCS_RemoveSaveButton();
                PPCS_RecaptionButton('.editor-post-save-draft', '');
              }
            }
          }

          ppLastPrepanelVisibility = prepanelVisible;
        }, 500);
      }

      // Hide the stock button
      node.addClass(hideClass).hide().css('z-index', -999);

	  $('span.presspermit-save-button button').removeClass('editor-post-save-draft').removeClass(hideClass).html(btnCaption);

      node.addClass(hideClass).attr('aria-disabled', true);
    }
  });
}

// Update main publish ("Publish" / "Submit Pending") button width and span caption
function PPCS_RecaptionOnDisplay(caption) {
  jQuery(document).ready(function ($) {
    if (($('button.editor-post-save-draft').filter(':visible').length || !$('.is-saving').length)
    && $('button.editor-post-save-draft').length) {  // indicates save operation (or return from Pre-Publish) is done
      PPCS_RecaptionButton('button.editor-post-save-draft', caption);
      $('span.presspermit-save-button button').removeAttr('aria-disabled');
    } else {
      var SaveRecaptionInterval = setInterval(WaitForRecaption, 100);
      var SaveRecaptionTimeout = setTimeout(function () {
          clearInterval(SaveRecaptionInterval);
      }, 20000);

      function WaitForRecaption() {
        if ($('button.editor-post-save-draft').filter(':visible').length || !$('.is-saving').length) { // indicates save operation (or return from Pre-Publish) is done
          clearInterval(SaveRecaptionInterval);
          clearTimeout(SaveRecaptionTimeout);

          PPCS_RecaptionButton('button.editor-post-save-draft', '');
        }
      }
    }
  });
}

jQuery(document).ready(function ($) {
  $(document).on('click', 'span.presspermit-save-button button', function() {
    if (!wp.data.select('core/editor').isSavingPost() && !$('span.presspermit-save-button button').attr('aria-disabled')) {
      $(this).parent().prev('button.editor-post-save-draft').trigger('click');
    }
  });

  $(document).on('click', '.editor-change-status__options input', function() {
    $('.editor-post-publish-button button').removeAttr('aria-disabled').css('z-index', '999');
  });

  $(document).on('click', '.editor-change-status__options .components-radio-control__option input[value="draft"]', function() {
    $('div.publishpress-extended-post-status select').val('draft');
  });

  setInterval(function() {
    if ($('editor-change-status__content:visible'.length)) {
      if ($('div.publishpress-extended-post-status select:visible').length) {
        $('.editor-change-status__options input[value="draft"],.editor-change-status__options input[value="pending"]').prop('disabled', 'disabled').parent().hide();
      } else {
        $('.editor-change-status__options input[value="draft"],.editor-change-status__options input[value="pending"]').removeProp('disabled').parent().show();
      }
    }
  }, 500);
});
/*****************************************************************************************************************/

var ppLastExtendedStatusVisible = false;

/**
 * Hack :(
 *
 * @see https://github.com/WordPress/gutenberg/issues/3144
 *
 * @param status
 */
var sideEffectL10nManipulation = function sideEffectL10nManipulation(status) {
  jQuery(document).ready(function ($) {
    if ('future' == status ) {
      return;
    }

    if (wp.data.select('core/editor').isSavingPost() || $('span.editor-post-saved-state:visible').length) {
      $('div.presspermit-save-button-wrap').hide();
      PPCS_RemoveSaveButton();
      return;
    } else {
      setTimeout(function() {
        $('div.presspermit-save-button-wrap').show();
        PPCS_RecaptionButton('.editor-post-save-draft', '');
      }, 500);
    }

    if ('undefined' == typeof(sideEffectL10nManipulation.statusRefeshDone)) {
      refreshSelectableStatuses(status);
      sideEffectL10nManipulation.statusRefeshDone = true;
    }

    var node = document.querySelector('.editor-post-save-draft');

    if (node) {
      var saveAsLabel = ppGetStatusSaveAs(status);

      if (saveAsLabel && (-1 == PPCustomStatuses.publishedStatuses.indexOf(status))) {
        PPCS_RecaptionOnDisplay(saveAsLabel);

        if ((-1 == PPCustomStatuses.publishedStatuses.indexOf(status))) {
          $('div.publishpress-extended-post-status div.components-base-control').show();
        }
      }
    }

    if (-1 == PPCustomStatuses.publishedStatuses.indexOf(status)) {
      $('div.publishpress-extended-post-status').show();
      $('div.publishpress-extended-post-status-scheduled').hide();
    } else {
      if (('private' != status) && (-1 != PPCustomStatuses.publishedStatuses.indexOf(status))) {
        $('.editor-post-publish-panel__toggle').hide();
      }

      $('div.publishpress-extended-post-status').hide();
    }

    var extendedStatusVisible = $('div.extended-post-status select:visible').length;

    // Prevent previous status selection from being applied to next post update after published / private status is selected
    if (!extendedStatusVisible && ppLastExtendedStatusVisible) {
      dispatch('core/editor').editPost({
        pp_status_selection: ''
      });
    }

    ppLastExtendedStatusVisible = extendedStatusVisible;
  });
};

/**
 * Hack :(
 * We need an interval because the DOM element is removed by autosave and rendered back after finishing.
 *
 * @see https://github.com/WordPress/gutenberg/issues/3144
 */

jQuery(document).ready(function ($) {
  setInterval(function () {
    var status = wp.data.select('core/editor').getEditedPostAttribute(PPCustomStatuses.statusRestProperty);    
    var isPublished = (-1 != PPCustomStatuses.publishedStatuses.indexOf(status));

    var updateDisabled = ($('span.presspermit-editor-button button').length && $('span.presspermit-editor-button button').attr('aria-disabled'))
    || ($('span.presspermit-editor-toggle button').length && $('span.presspermit-editor-toggle button').attr('aria-disabled'));

    $('div.publishpress-extended-post-status div.components-base-control').toggle(!isPublished);
    $('div.publishpress-extended-post-status div.publishpress-extended-post-status-published').toggle(isPublished && ('future' != status) && !updateDisabled);
    $('div.publishpress-extended-post-status div.publishpress-extended-post-status-scheduled').toggle(('future' == status) && !updateDisabled);

    sideEffectL10nManipulation(status);
    
  }, 250);
});


var ppLastWorkflowAction = '';

var lastWorkflowStatusNext = '';
var lastWorkflowStatusMax = '';
var lastSelectedStatus = '';

jQuery(document).ready(function ($) {
setInterval(function () {
  $('div.editor-post-publish-panel__prepublish > div:not([class])').hide();
  $('div.editor-post-publish-panel__prepublish > p:not([class])').hide();

  var currentStatus = wp.data.select('core/editor').getCurrentPostAttribute(PPCustomStatuses.statusRestProperty);
  var currentStatusPublished = -1 !== PPCustomStatuses.publishedStatuses.indexOf(currentStatus);
  var showPublishSuggestions = currentStatusPublished;

  var selectedStatus = '';
  
  if ('status' == PPCustomStatuses.statusRestProperty) {
    selectedStatus = wp.data.select('core/editor').getEditedPostAttribute('pp_status_selection');
  }

  if (!selectedStatus) {
    selectedStatus = wp.data.select('core/editor').getEditedPostAttribute(PPCustomStatuses.statusRestProperty);
  }

  var currentWorkflowSelection = wp.data.select('core/editor').getEditedPostAttribute('pp_workflow_action');

  if ('publish' == ppObjEdit.maxStatus || 'future' == ppObjEdit.maxStatus) {
    let postDate = new Date(wp.data.select('core/editor').getEditedPostAttribute('date'));
    let currentDate = new Date();

    if (postDate.getTime() - ((currentDate.getTimezoneOffset() / 60 + parseInt(ppObjEdit.timezoneOffset)) * 3600000) > currentDate.getTime()
    ) {
      ppObjEdit.maxStatus = 'future';
    } else {
      ppObjEdit.maxStatus = 'publish';
    }
  }

  if (currentWorkflowSelection == 'specified') {
    ppObjEdit.publish = ppGetStatusSubmit(selectedStatus);
  }

  if (currentWorkflowSelection == 'current') {
    ppObjEdit.publish = ppObjEdit.update;
  }

  if (currentWorkflowSelection == 'next') {
    if ('publish' == ppObjEdit.nextStatus || 'future' == ppObjEdit.nextStatus) {
      showPublishSuggestions = true;

      ppObjEdit.publish = ('future' == ppObjEdit.nextStatus) ? ppsCaptions.schedule : ppsCaptions.publish;
    } else {
      ppObjEdit.publish = ppGetStatusSubmit(ppObjEdit.nextStatus);
    }
  }

  if (currentWorkflowSelection == 'max') {
    if ('publish' == ppObjEdit.maxStatus || 'future' == ppObjEdit.maxStatus) {
      showPublishSuggestions = true;

      ppObjEdit.publish = ('future' == ppObjEdit.maxStatus) ? ppsCaptions.schedule : ppsCaptions.publish;
    } else {
      ppObjEdit.publish = ppGetStatusSubmit(ppObjEdit.maxStatus);
    }
  }

  ppObjEdit.publishCaptionCurrent = ppObjEdit.publish;

  if ($('div.editor-post-publish-panel__prepublish').length) {
    $('#ppcs_save_draft_label').hide();

    if (!$('div.editor-post-publish-panel__prepublish div:first').hasClass('pp-statuses-workflow')) {
      $('div.pp-statuses-workflow').prependTo('div.editor-post-publish-panel__prepublish');
    }

    // This is called for each workflow action caption (current, next and max)
    var appendWorkflowCaption = function appendWorkflowCaption(buttonWorkflowAction, buttonStatus) {
      if ('auto-draft' == buttonStatus) {
        buttonStatus = 'draft';
      }

      var statusCaption = ppGetStatusLabel(buttonStatus);
      var statusObj = ppGetStatusObject(buttonStatus);

      var statusSpanClass = 'pp-status-color pp-status-color-title';

      if (!statusObj && (-1 !== PPCustomStatuses.publishedStatuses.indexOf(buttonStatus)) ) {
        statusObj = ppGetPublishedStatusObject(buttonStatus);
        
        if (statusObj) {
          statusCaption = statusObj.label;
        }

        statusSpanClass += ' pp-published-status';
      }

      if (statusObj && statusObj.color) {
        statusCaption = '<span class="' + statusSpanClass + ' pp-status-' + buttonStatus + '" style="background:' + statusObj.color + '; color:' + ppStatusTextColor(statusObj.color) + '">' + statusCaption + '</span>';
      }

      if (statusObj && statusObj.icon) {
        statusCaption = '<span class="dashicons ' + statusObj.icon + '"></span> ' + statusCaption + '</span>';
      }
      
      if (statusCaption) {
        $('div.pp-statuses-workflow input[type="radio"][value="' + buttonWorkflowAction + '"]').next('label').parent().after(
          '<div class="pp-editor-prepublish-' + buttonWorkflowAction + '-status pp-editor-workflow-caption pp-status-' + buttonStatus + '">' + statusCaption + '</div>'
        );
      }
    }

    $('input[name="pp_statuses_workflow_selection"][value="current"]').closest('div.components-radio-control__option').toggle(-1 == PPCustomStatuses.publishedStatuses.indexOf(selectedStatus));
    $('input[name="pp_statuses_workflow_selection"][value="specified"]').closest('div.components-radio-control__option').toggle((currentStatus != selectedStatus) && (-1 == ['publish', 'future'].indexOf(selectedStatus)));
    $('input[name="pp_statuses_workflow_selection"][value="next"]').closest('div.components-radio-control__option').toggle(!currentStatusPublished && (ppObjEdit.nextStatus != currentStatus) && (ppObjEdit.nextStatus != ppObjEdit.maxStatus));
    $('input[name="pp_statuses_workflow_selection"][value="max"]').closest('div.components-radio-control__option').toggle(!currentStatusPublished && (ppObjEdit.maxStatus != currentStatus));

    if (!$('input[name="pp_statuses_workflow_selection"][value="next"]').closest('div.components-radio-control__option:visible').length) {
      $('div.pp-statuses-workflow div.pp-editor-prepublish-next-status').hide();
    }

    // default to current status if checked option is hidden
    if ((currentStatusPublished || (!$('input[name="pp_statuses_workflow_selection"]:visible:checked').length 
    && (!ppObjEdit.defaultBySequence || $('input[name="pp_statuses_workflow_selection"]:visible:checked').val() != 'next')))
    && (-1 == PPCustomStatuses.publishedStatuses.indexOf(selectedStatus))
    ) {
      $('input[name="pp_statuses_workflow_selection"][value="current"]').trigger('click');

      wp.data.dispatch('core/editor').editPost({
        pp_workflow_action: 'current'
      });
    } else {
      if (!$('input[name="pp_statuses_workflow_selection"]:visible:checked').length 
      && (ppObjEdit.nextStatus == ppObjEdit.maxStatus) 
      && $('input[name="pp_statuses_workflow_selection"][value="max"]:visible').length
      && (-1 == PPCustomStatuses.publishedStatuses.indexOf(selectedStatus))) {
        $('input[name="pp_statuses_workflow_selection"][value="max"]').trigger('click');

        wp.data.dispatch('core/editor').editPost({
          pp_workflow_action: 'max'
        });

      } else {
          if ((currentStatus != selectedStatus) && (selectedStatus != lastSelectedStatus) && (-1 == ['publish', 'future'].indexOf(selectedStatus))) {
          $('input[name="pp_statuses_workflow_selection"][value="specified"]').trigger('click');
    
          wp.data.dispatch('core/editor').editPost({
            pp_workflow_action: 'specified'
          });
        }
      }
    }

    // Provide confirmation that workflow progression is being specified
    wp.data.dispatch('core/editor').editPost({
      pp_statuses_selecting_workflow: true
    });

    lastSelectedStatus = selectedStatus;

    if ((lastWorkflowStatusNext != ppObjEdit.nextStatus) || (lastWorkflowStatusMax != ppObjEdit.maxStatus)) {
      $('div.pp-statuses-workflow div.pp-editor-workflow-caption').remove(); 
    }

    lastWorkflowStatusNext = ppObjEdit.nextStatus;
    lastWorkflowStatusMax = ppObjEdit.maxStatus;

    if ((currentStatus != selectedStatus) && (-1 == ['publish', 'future'].indexOf(selectedStatus)) && !$('div.pp-statuses-workflow div.pp-editor-prepublish-specified-status').length) {
      appendWorkflowCaption('specified', selectedStatus);
    }

    if (!$('div.pp-statuses-workflow div.pp-editor-prepublish-current-status').length) {
      appendWorkflowCaption('current', currentStatus);
    }

    if (!currentStatusPublished) {
      if (!$('div.pp-statuses-workflow div.pp-editor-prepublish-next-status').length && (ppObjEdit.nextStatus != currentStatus)) {
        appendWorkflowCaption('next', ppObjEdit.nextStatus);
      }

      if (!$('div.pp-statuses-workflow div.pp-editor-prepublish-max-status').length && (ppObjEdit.maxStatus != currentStatus)) {
        appendWorkflowCaption('max', ppObjEdit.maxStatus);
      }
    }

    switch (ppObjEdit.maxStatus) {
      case 'publish':
        $('div.pp-statuses-workflow div.components-radio-control__option:last label').html(ppsCaptions.publish);
        break;

      case 'future':
        $('div.pp-statuses-workflow div.components-radio-control__option:last label').html(ppsCaptions.schedule);
        break;

      default:
        $('div.pp-statuses-workflow div.components-radio-control__option:last label').html(ppsCaptions.advanceMax);
        break;
    }

    $('div.editor-post-publish-panel__prepublish > div.components-panel__body').not('.pp-statuses-show-workflow-prepublish,.pp-statuses-workflow,.components-site-card').toggle(showPublishSuggestions);
  }
}, 200);

  $(document).on('change', 'div.publishpress-extended-post-status select', function(e) {
    wp.data.dispatch('core/editor').editPost({
      pp_status_selection: $('div.publishpress-extended-post-status select').val()
    });

    // The Save button is recaptioned after a delay; announce its new caption
    setTimeout(function() {
      var caption = $('span.presspermit-save-button button:visible').first().text();

      if (caption && ppsCaptions.saveButtonCaption) {
        ppcsSpeak(ppsCaptions.saveButtonCaption.replace('%s', caption));
      }
    }, 1000);
  });

  $(document).on('change', 'input[name="pp_statuses_workflow_selection"]', function(e) {
    // Only announce user selections, not the automatic default selection
    if (!e.originalEvent || !e.originalEvent.isTrusted) {
      return;
    }

    // The Publish button in the pre-publish panel is recaptioned after a delay; announce its new caption
    setTimeout(function() {
      var caption = $('div.editor-post-publish-panel__header-publish-button button:visible, span.presspermit-editor-button button:visible').last().text();

      if (caption && ppsCaptions.publishButtonCaption) {
        ppcsSpeak(ppsCaptions.publishButtonCaption.replace('%s', caption));
      }
    }, 1000);
  });
});

/**
 * Custom status component
 * @param object props
 */
var PPCustomPostStatusInfo = function PPCustomPostStatusInfo(_ref) {
  var onUpdate = _ref.onUpdate,
      status = _ref[PPCustomStatuses.statusRestProperty];

  var statusOptions = ppStatuses.slice();

  // Visual separator before the alternate workflow statuses; screen readers get a name instead of underscores
  var divider = {value: "_", label: "_____________", disabled: "disabled", 'aria-label': ppsCaptions.alternateWorkflows || '', save_as: "", submit: "", color: "", icon: ""};
  statusOptions.push(divider);

  var publishStatusObj = ppGetPublishedStatusObject('publish');
  var futureStatusObj = ppGetPublishedStatusObject('future');

  var publishIcon = (publishStatusObj) ? publishStatusObj.icon : '';
  var futureIcon = (futureStatusObj) ? futureStatusObj.icon : '';

  var __ = wp.i18n.__;

  return React.createElement(
    wp.editPost.PluginPostStatusInfo, 
    
    {
    className: "publishpress-extended-post-status publishpress-extended-post-status-".concat(status)
    }, 

    React.createElement(wp.components.SelectControl, {
      label: ppsCaptions.postStatus,
      hideLabelFromVision: true,
      value: status,
      options: statusOptions,
      onChange: onUpdate
    })
  );
};

var ppPlugin = wp.compose.compose(wp.data.withSelect(function (select) {
  var setStatus = '';
  var ret = new Object();
  
  if ('status' == PPCustomStatuses.statusRestProperty) {
  	setStatus = select('core/editor').getEditedPostAttribute('pp_status_selection');
  }

  if (!setStatus) {
      setStatus = select('core/editor').getEditedPostAttribute(PPCustomStatuses.statusRestProperty);
  }
  	
  ret[PPCustomStatuses.statusRestProperty] = setStatus;

  return ret;
}), wp.data.withDispatch(function (dispatch) {
  return {
    onUpdate: function onUpdate(status) {
	  if ('status' == PPCustomStatuses.statusRestProperty) {
	      dispatch('core/editor').editPost({
	        pp_status_selection: status
	      });
	  } else {
		var ret = new Object();
	    ret[PPCustomStatuses.statusRestProperty] = status;
	
	    dispatch('core/editor').editPost(ret);
	  }

      refreshSelectableStatuses(status);
      sideEffectL10nManipulation(status);
    }
  };
}))(PPCustomPostStatusInfo);
wp.plugins.registerPlugin('publishpress-custom-status-block', {
  icon: 'admin-site',
  render: ppPlugin
});

if (!ppObjEdit.workflowDisabled) {
var PPWorkflowAction = function PPWorkflowAction(_ref) {
  var onUpdate = _ref.onUpdate,
    wfa = _ref.ppWorkflowAction;

  if (_ref.pp_workflow_action === null) {
    var pp_workflow_action = '';
  } else {
    var pp_workflow_action = _ref.pp_workflow_action;
  }

  var currentStatus = wp.data.select('core/editor').getEditedPostAttribute(PPCustomStatuses.statusRestProperty);

  if (!pp_workflow_action) {
    if ((currentStatus != ppObjEdit.nextStatus) && ppObjEdit.defaultBySequence) {
      pp_workflow_action = 'next';
    } else {
      if (currentStatus != ppObjEdit.maxStatus) {
        pp_workflow_action = 'max';
      }
    }

    if (!pp_workflow_action) {
      pp_workflow_action = 'update';
    }
  }

  var maxStatusCaption;
  
  switch (ppObjEdit.maxStatus) {
    case 'publish':
      maxStatusCaption = ppsCaptions.publish;
      break;

    case 'future':
      maxStatusCaption = ppsCaptions.schedule;
      break;

    default:
      maxStatusCaption = ppsCaptions.advanceMax;
  }

  var radioOptions = [
    { label: ppsCaptions.setSelected, value: 'specified' },
    { label: ppsCaptions.keepCurrent, value: 'current' },
    { label: ppsCaptions.advanceNext, value: 'next' },
    { label: maxStatusCaption, value: 'max' }
  ];

  return React.createElement(wp.editPost.PluginPrePublishPanel, {
    className: "pp-statuses-workflow publishpress-statuses-workflow-".concat(pp_workflow_action)
  }, React.createElement("h3", null, ppsCaptions.publicationWorkflow), React.createElement(wp.components.RadioControl, {
    // The visible h3 supplies the same text, so the group label is for assistive technology only
    label: ppsCaptions.publicationWorkflow,
    hideLabelFromVision: true,
    name: "pp_statuses_workflow_selection",
    options: radioOptions,
    selected: pp_workflow_action,
    onChange: onUpdate
  }) );
};

var pluginWorkflow = wp.compose.compose(wp.data.withSelect(function (select) {
  return {
    pp_workflow_action: select('core/editor').getEditedPostAttribute('pp_workflow_action')
  };
}), wp.data.withDispatch(function (dispatch) {
  return {
    onUpdate: function onUpdate(pp_workflow_action) {
      dispatch('core/editor').editPost({
        pp_workflow_action: pp_workflow_action
      });
    }
  };
}))(PPWorkflowAction);
wp.plugins.registerPlugin('publishpress-statuses-workflow-action', {
  icon: 'admin-site',
  render: pluginWorkflow
});
}
