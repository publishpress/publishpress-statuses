/**
 * Block Editor Modifications to support Statuses workflow
 */
jQuery(document).ready(function ($) {

    var __ = wp.i18n.__;
    var ppCurrentStatus = '';
    var ppLastStatus = false;
    var ppEditorDisposed = false;
    var ppRefreshTimeout = null;
    var ppEditorObserver;
    var ppEditorUnsubscribe;
    var ppWaitIntervals = [];
    var ppWaitTimeouts = [];

    // Temporary React/save waits are bounded and have one owner per purpose.
    function PP_StartWait(callback) {
        var interval = setInterval(function () {
            if (callback()) {
                stop();
            }
        }, 100);
        var timeout = setTimeout(stop, 20000);
        ppWaitIntervals.push(interval);
        ppWaitTimeouts.push(timeout);
        function stop() {
            clearInterval(interval);
            clearTimeout(timeout);
            ppWaitIntervals = ppWaitIntervals.filter(function (id) { return id !== interval; });
            ppWaitTimeouts = ppWaitTimeouts.filter(function (id) { return id !== timeout; });
        }
        return stop;
    }
    var ppStopRecaptionWait = null;
    var ppStopRestoreWait = null;
    var ppStopPrepublishWait = null;

    ppObjEdit.publishCaptionCurrent = ppObjEdit.publish;


    /******************** FUNCTIONS FOR RECAPTIONING PUBLISH AND PRE-PUBLISH BUTTONS **************************/

    /*
     * The goal is to allow recaptioning "Publish..." to "Workflow...",  "Submit for Review" to "Submit as Pitch" etc.
     */
    function PP_RecaptionButton(btnName, btnSelector, btnCaption) {
        if (ppObjEdit.disableRecaption || wp.data.select('core/editor').isSavingPost()) {
            return;
        }

        var node = $(btnSelector);

        var ppClass;
        var hideClass;
        
        if ('button.editor-post-publish-button' == btnSelector) {
            ppClass = 'presspermit-editor-button';
        } else {
            ppClass = 'presspermit-editor-toggle';
        }

        if ($(btnSelector).length && btnCaption && (btnCaption != $('span.' + ppClass + ' button').html() || !$('span.' + ppClass + ':visible').length)) {
            if ($(btnSelector).html() == ppObjEdit.submitRevisionCaption) {
                return;
            }

            $('span.presspermit-editor-toggle').remove();

            if (!$('div.editor-post-publish-panel__prepublish').length) {
                $('span.presspermit-editor-button').remove();
            }

            if ((ppClass == 'presspermit-editor-button') && $('div.editor-post-publish-panel__prepublish').length && $('span.' + ppClass + ' button').length) {
                $('span.' + ppClass + ' button').html(btnCaption).show();
            } else {
                $('.presspermit-editor-hidden').not($(btnSelector)).show();

                if ('button.editor-post-publish-button' == btnSelector) {
                    hideClass = 'presspermit-editor-hidden presspermit-editor-button-hidden';
                } else {
                    hideClass = 'presspermit-editor-hidden presspermit-editor-toggle-hidden';
                }

                // Hide the stock button
                node.addClass(hideClass).hide().css('z-index', -999);

                // Clone the stock button
                node.after('<span class="' + ppClass + '">' + node.clone().css('z-index', 0).removeClass(hideClass).removeClass('editor-post-publish-button').removeAttr('aria-disabled').css('position', 'relative').css('background-color', 'var(--wp-admin-theme-color)').show().html(btnCaption).wrap('<span>').parent().html() + '</span>');
        
                // If the stock button is not the pre-publish toggle, really hide it (re-add hide class; set background color, position and aria-disabled properties)
                if ((typeof ppObjEdit['isGutenbergLegacy'] != undefined) && ppObjEdit.isGutenbergLegacy) {
                	node.not('.editor-post-publish-panel__toggle').addClass(hideClass).css('background-color', 'inherit').css('position', 'fixed').attr('aria-disabled', true);
            	} else {
	                if ('button.editor-post-publish-button' == btnSelector) {
	                    if ($('.presspermit-save-button:visible').length || $('div.editor-post-publish-panel__content:visible').length 
	                    || ($('.presspermit-editor-button button:visible').length && $('.publishpress-extended-post-status select:visible').length)) {
	                        node.not('.editor-post-publish-panel__toggle').addClass(hideClass).css('background-color', 'inherit').css('position', 'fixed').attr('aria-disabled', true);
	                    }
	                }
	            }
            }
        }

        PP_InitializeStatuses();
    }

    // Update main publish ("Publish" / "Submit Pending") button width and span caption
    function PP_SetPublishButtonCaption(caption, waitForSaveDraftButton) {
        if ((ppObjEdit.publishCaptionCurrent == ppObjEdit.saveDraftCaption) || wp.data.select('core/editor').isSavingPost()) {
            return;
        }

        if (caption == '' && (typeof ppObjEdit['publishCaptionCurrent'] != undefined)) {
            caption = ppObjEdit.publishCaptionCurrent;
        } else {
            ppObjEdit.publishCaptionCurrent = caption;
        }

        if (typeof waitForSaveDraftButton == 'undefined') {
            waitForSaveDraftButton = false;
        }

        if (ppStopRecaptionWait) {
            ppStopRecaptionWait();
            ppStopRecaptionWait = null;
        }

        if ((!waitForSaveDraftButton 
        || ($('button.editor-post-save-draft').filter(':visible').length || !$('.is-saving').length)) 
        && $('button.editor-post-publish-button').length) {  // indicates save operation (or return from Pre-Publish) is done
            PP_RecaptionButton('publish', 'button.editor-post-publish-button', caption);
            $('span.presspermit-editor-button button').removeAttr('aria-disabled');

        } else {
            ppStopRecaptionWait = PP_StartWait(WaitForRecaption);

            function WaitForRecaption() {
                if (!waitForSaveDraftButton || $('button.editor-post-save-draft').filter(':visible').length || !$('.is-saving').length) { // indicates save operation (or return from Pre-Publish) is done

                    // will set Pre-pub button instead when applicable
                    PP_RecaptionButton('publish', 'button.editor-post-publish-button', caption);

                    $('.publishpress-extended-post-status-note').hide();

                    $('span.presspermit-editor-button button').removeAttr('aria-disabled');
                    return true;
                }
                return false;
            }
        }
    }
    /*****************************************************************************************************************/


    /************* RECAPTION PRE-PUBLISH AND PUBLISH BUTTONS ****************/
    
    // Initialization operations to perform once React loads the relevant elements
    var PP_InitializeBlockEditorModifications = function (forceRefresh) {
        if (ppObjEdit.hidePending) {
            var wp_i8n_helper = window["wp"]["i18n"];

            if (typeof wp_i8n_helper != 'undefined') {
                var pendingCaption = (0,wp_i8n_helper.__)('Pending review');
                
                if (pendingCaption) {
                    $('input.components-checkbox-control__input').closest('div.components-base-control__field').find('label:contains("")').closest('div.components-panel__row').hide();
                }
            }
        }

        if (
        (typeof forceRefresh != "undefined" && forceRefresh) 
        || (
        	($('button.editor-post-publish-button').length || $('button.editor-post-publish-panel__toggle').length) 
        	&& (
        		$('button.editor-post-save-draft').length
                || $('button.editor-post-saved-state.is-saved').length
                || ((typeof window.PPCustomStatuses != 'undefined')
                    && window.PPCustomStatuses.publishedStatuses.indexOf(wp.data.select('core/editor').getEditedPostAttribute('status')) !== -1)
        		|| (
	        		$('div.publishpress-extended-post-status select option[value="_pending"]').length 
	        		&& ('pending' == $('div.publishpress-extended-post-status select').val() || '_pending' == $('div.publishpress-extended-post-status select').val())
	        	)
	        )
        )
        || ((typeof window.PPCustomStatuses != 'undefined') && (typeof window.PPCustomStatuses['isRevision'] != 'undefined') && (window.PPCustomStatuses.isRevision))
        ) {
            if ($('button.editor-post-publish-panel__toggle').length) {
                if (typeof ppObjEdit.prePublish != 'undefined' && ppObjEdit.prePublish) { // && ($('button.editor-post-publish-panel__toggle').html() != __('Schedule…'))) {
                    let status = wp.data.select('core/editor').getEditedPostAttribute('status');

                    if (-1 == window.PPCustomStatuses.publishedStatuses.indexOf(status)) {
                        PP_RecaptionButton('prePublish', 'button.editor-post-publish-panel__toggle', ppObjEdit.prePublish);
                    }
                }

            } else {
                PP_SetPublishButtonCaption(ppObjEdit.publish, false);
            }
        }

        if (ppObjEdit.lockStatus) {
            $('.editor-change-status__options input').prop('disabled', true);
            $('.publishpress-extended-post-privacy select').prop('disabled', true);
        }
    }
    // Bind once; React may recreate the toggle after a modal closes.
    $(document).on('click', 'button.editor-post-publish-panel__toggle,span.pp-recaption-prepublish-button', function () {
        PP_SetPublishButtonCaption('', false);
    });

    function PP_ClearBusyButtons() {
        if ($('span.presspermit-editor-button button.is-busy').length) {
            let saving = wp.data.select('core/editor').isSavingPost();

            if (!saving) {
                $('span.presspermit-editor-button button.is-busy').removeClass('is-busy');
            }
        }
    }

    var ppLastPublishCaption = '';

    function PP_RefreshWorkflow() {
			if (ppObjEdit.moveParentUI) {
	            $('div.editor-post-panel__row-label').each(function (i, e) {
	                if ($(e).html() == ppObjEdit.parentLabel) {
	                    $(e).closest('div.editor-post-panel__row').insertAfter(
	                        $('div.editor-post-panel__row-label:contains(' + ppObjEdit.publishLabel + ')').closest('div.editor-post-panel__row').next()
	                    ); 
	                }
	            });
        	}
        	
            if ($('div.editor-post-publish-panel__header-cancel-button').length) {
                PP_SetPublishButtonCaption(ppObjEdit.publish, false);
            }

            if (ppObjEdit.workflowSequence && !wp.data.select('core/editor').isSavingPost()) {
                let status = wp.data.select('core/editor').getEditedPostAttribute('status');

                if (ppObjEdit.publish != ppLastPublishCaption || status !== ppLastStatus) {
                    if (-1 !== PPCustomStatuses.publishedStatuses.indexOf(status)) {
                        ppObjEdit.publish = ppObjEdit.update;
                        ppObjEdit.saveAs = '';
                    } else {
                        if (status == ppObjEdit.maxStatus) {
                            ppObjEdit.publish = ppObjEdit.update;
                            ppObjEdit.saveAs = ppObjEdit.update;
                        } else {
                            if ($('button.editor-post-publish-panel__toggle').length) {
                                if (typeof ppObjEdit.prePublish != 'undefined' && ppObjEdit.prePublish && ($('button.editor-post-publish-panel__toggle').html() != ppObjEdit.scheduleCaption)) {
                                    
                                    var pendingStatusArr = new Array('pending', '_pending');
                                    
                                    if (pendingStatusArr.indexOf(status) != -1) {
                                        PP_SetPublishButtonCaption(ppObjEdit.publish, false);
                                    } else {
                                        PP_RecaptionButton('prePublish', 'button.editor-post-publish-panel__toggle', ppObjEdit.prePublish);
                                    }
                                }
                            } else {
                                PP_SetPublishButtonCaption(ppObjEdit.publish, false);
                            }
                        }
                    }

                    ppLastStatus = status;
                    ppLastPublishCaption = ppObjEdit.publish;

                    if (ppObjEdit.publishCaptionCurrent != ppObjEdit.publish) {
                        setTimeout(function () {
                            PP_InitializeBlockEditorModifications(true);
                        }, 100);
                    }

                    ppObjEdit.publishCaptionCurrent = ppObjEdit.publish;
                }
            }
    }

    var PP_InitializeStatuses = function () {
        if ($('div.publishpress-extended-post-status select').length) {

            // Users without the publish capability get an alternate 'pending' option item 
            // to allow a "Save as Pending Review" button which does not trigger automatic workflow status progression.
            if ($('div.publishpress-extended-post-status select option[value="_pending"]').length) {
                if ($('div.publishpress-extended-post-status select').val() == 'pending') {
                    $('div.publishpress-extended-post-status select').val('_pending');
                }

                // Blank option for Safari, which cannot hide it
				$('div.publishpress-extended-post-status select > option[value="pending"]').html('').hide();


            }

            ppCurrentStatus = $('div.publishpress-extended-post-status select').val();
        }
    }
    $(document).on('click', 'div.publishpress-extended-post-status select option[value="pending"]', function () {
        $('div.publishpress-extended-post-status select').val('_pending');
    });

    // The save handler is delegated once, rather than added on each dropdown click.
    $(document).on('click', 'button.editor-post-save-draft', function () {
        ppObjEdit.publishCaptionCurrent = ppObjEdit.publish;
    });

    $(document).on('change', 'div.publishpress-extended-post-status select', function () {
        // Reduce visible re-sizing of new label. It will be re-shown after width property is updated.
        $('#ppcs_save_draft_label').hide();
    });

    // Fallback safeguard against redundant visible Pending options
    $(document).on('click', 'div.publishpress-extended-post-status select', function() {
        if ($('div.publishpress-extended-post-status select option[value="_pending"]').length && $('div.publishpress-extended-post-status select option[value="pending"]').length) {
            $('div.publishpress-extended-post-status select option[value="pending"]').hide();
        }
    });

    $(document).on('click', 'span.presspermit-editor-button button', function() {
        if (!wp.data.select('core/editor').isSavingPost() && !$('span.presspermit-editor-button button').attr('aria-disabled')) {
            $(this).parent().prev('button.editor-post-publish-button').trigger('click').hide();
        }
    });

    $(document).on('click', 'span.presspermit-editor-toggle button', function() {
        if (!wp.data.select('core/editor').isSavingPost() && !$('span.presspermit-editor-toggle button').attr('aria-disabled')) {
            $(this).parent().prev('button.editor-post-publish-panel__toggle').trigger('click').hide();
        }
    });

    var ppcsDisablePostUpdate = function ppDisablePostUpdate() {
    jQuery(document).ready(function ($) {
        $('span.presspermit-editor-toggle button').attr('aria-disabled', true);
        $('span.presspermit-editor-button button').attr('aria-disabled', true);
        $('div.publishpress-extended-post-status select').attr('disabled', true);
    });
    }
    
    var ppcsEnablePostUpdate = function ppEnablePostUpdate() {
    jQuery(document).ready(function ($) {
        if (ppStopRestoreWait) {
            ppStopRestoreWait();
        }
        ppStopRestoreWait = PP_StartWait(function() {
        if ($('span.presspermit-editor-toggle button:visible').length && $('span.presspermit-editor-toggle button').parent().prev('button').attr('aria-disabled') == 'false'
        || ($('span.presspermit-editor-button button:visible').length && $('span.presspermit-editor-button button').parent().prev('button').attr('aria-disabled') == 'false')
        ) {
            $('span.presspermit-editor-toggle button').removeAttr('aria-disabled');
            $('span.presspermit-editor-button button').removeAttr('aria-disabled');
            return true;
        }
        return false;
        });
    
        $('div.publishpress-extended-post-status select').removeAttr('disabled');
    });
    }

    let ppPostSavingDone = function() {
        $('div.publishpress-extended-post-status select').removeAttr('locked');

        ppcsEnablePostUpdate();

        let status = wp.data.select('core/editor').getEditedPostAttribute(PPCustomStatuses.statusRestProperty);

        var redirectProp = 'redirectURL' + status;
        if (typeof ppObjEdit[redirectProp] != undefined) {
            $(location).attr("href", ppObjEdit[redirectProp]);
        } else {
            ppCurrentStatus = status;
        }

        ppLastStatus = false;

        setTimeout(function() {
            ppLastStatus = false;
            PP_RecaptionButton('prePublish', 'button.editor-post-publish-panel__toggle', ppObjEdit.prePublish);
            PP_SetPublishButtonCaption(ppObjEdit.publish, false);

            setTimeout(function() {
            PPCS_RecaptionOnDisplay('');
            }, 500);

            ppEnablePostUpdate();
        }, 500);

        querySelectableStatuses(status);

        ppLoggedPostSave = false;
    }

    var ppDisablePostUpdate = function ppDisablePostUpdate() {
        $('span.presspermit-editor-button button').attr('aria-disabled', true);
        $('div.publishpress-extended-post-status select').attr('disabled', true);
    }

    var ppEnablePostUpdate = function ppEnablePostUpdate() {
        $('div.publishpress-extended-post-status select').removeAttr('disabled');
    }

    var ppLoggedPostSave = false;

    let ppPostSaveCheck = function() {
        let saving = wp.data.select('core/editor').isSavingPost();

        if (saving) {
            if (wp.data.select('core/editor').isAutosavingPost()) {
                return;
            }

            if (!ppLoggedPostSave) {
                ppLoggedPostSave = true;

                ppCurrentStatus = wp.data.select('core/editor').getEditedPostAttribute('status');

                //$('div.publishpress-extended-post-status select').parent().hide();
                $('div.publishpress-extended-post-status select').attr('locked', true);

                ppDisablePostUpdate();
                $('span.presspermit-editor-toggle button').attr('aria-disabled', true);
            }
        } else if (ppLoggedPostSave) {
            ppPostSavingDone();
        }
    }

    /***** Redirect back to edit.php if user won't be able to futher edit after changing post status *******/
    $(document).on('click', 'button.editor-post-publish-button:not(.presspermit-editor-hidden),button.editor-post-save-draft', function () {
        ppPostSaveCheck();
    });

    // If Publish button is clicked, current post status will be set to [user's next/max status progression]
    // So set Publish button caption to "Save As %s" to show that no further progression is needed / offered.
    $(document).on('click', 'button.editor-post-publish-button', function () {
        if ($('div.editor-post-publish-panel__prepublish').length || $('button.editor-post-publish-panel__toggle').length) {
            $('span.presspermit-editor-button button').remove();
            
            var RvyRecaptionPrepub = function () {
                if ($('button.editor-post-publish-panel__toggle').not('[aria-disabled="true"]').length) {


                    PP_RecaptionButton('prePublish', 'button.editor-post-publish-panel__toggle', ppObjEdit.prePublish);
                    return true;
                } else {
                    if ($('button.editor-post-publish-panel__toggle').length) {
                        if (!$('span.presspermit-editor-toggle').length) {
                            PP_RecaptionButton('prePublish', 'button.editor-post-publish-panel__toggle', ppObjEdit.prePublish);
                        }
                    } else {
                        if (!$('span.presspermit-editor-button button').length) {
                            PP_SetPublishButtonCaption(ppObjEdit.publish, false);
                            $('span.presspermit-editor-button button').attr('aria-disabled', 'true');
                        }
                    }
                }
            }
            if (ppStopPrepublishWait) {
                ppStopPrepublishWait();
            }
            ppStopPrepublishWait = PP_StartWait(RvyRecaptionPrepub);
        } else {
            PP_SetPublishButtonCaption(ppObjEdit.saveAs, true);
            $('span.presspermit-editor-button button').attr('aria-disabled', 'true');
        }

        // Wait for Save Draft button to reappear; this will have no effect on Publish Button if Pre-Publish is enabled (but will update ppObjEdit property for next button refresh)
        setTimeout(function () {
            PP_SetPublishButtonCaption(ppObjEdit.saveAs, true);
        }, 100);
    });

    $(document).on('click', 'fieldset.editor-change-status__options div.components-radio-control__option input[value="publish"]', function() {
        $('span.presspermit-editor-toggle').hide();
        $('.presspermit-editor-hidden').show().css('z-index', 0);
    });

    $(document).on('click', 'div.editor-post-publish-panel__header-cancel-button button', function() {
        setTimeout(function () {
            $('button.editor-post-publish-panel__toggle').removeClass('presspermit-editor-hidden').css('z-index', 1);
            PP_RecaptionButton('prePublish', 'button.editor-post-publish-panel__toggle', ppObjEdit.prePublish);

            PPCS_RecaptionOnDisplay('');
        }, 100);
    });

    $(document).on('change', '.e1mv6sxx2', function() {
        if ($(this).val() === 'draft') {
            setTimeout(function() {
                if ($('span.presspermit-save-button').length) {
                    $('span.presspermit-save-button button').css(
                        {
                            'display': 'flex',
                            'z-index': '999'
                        }
                    ).attr('aria-disabled', 'false');
                } else {
                    $('.editor-header__settings .editor-post-save-draft').css(
                        {
                            'display': 'flex',
                            'z-index': '999'
                        }
                    ).attr('aria-disabled', 'false');
                }
            }, 100);
        }
    });

    $(document).on('click', 'button.editor-post-save-draft', function () {
        $('span.presspermit-editor-button button').attr('aria-disabled', 'true');

        // Wait for Save Draft button; this will have no effect on Publish Button if Pre-Publish is enabled 
        // (but will clear disabled attribute on current button and update ppObjEdit property with current button caption)
        setTimeout(function () {
            PP_SetPublishButtonCaption(ppObjEdit.publish, true);
        }, 50);
    });



    $(document).on('click', 'div.editor-post-publish-panel__header button.components-icon-button', function() {
        setTimeout(function () {
            PP_InitializeBlockEditorModifications();
        }, 100);
    });

    // React UI changes and editor-state transitions replace permanent DOM polling.
    var ppModalWasOpen = false;
    function PP_RefreshEditorUI() {
        ppRefreshTimeout = null;
        if (ppEditorDisposed) {
            return;
        }
        // Ignore our own caption/visibility mutations to avoid a refresh feedback loop.
        ppEditorObserver.disconnect();
        try {
            var modalIsOpen = $('div.components-modal__header').length > 0;
            if (ppModalWasOpen && !modalIsOpen) {
                $('span.presspermit-editor-button').remove();
                $('span.presspermit-editor-toggle').remove();
                $('.presspermit-editor-hidden').show();
                PP_RecaptionButton('prePublish', 'button.editor-post-publish-panel__toggle', ppObjEdit.prePublish);
                PP_SetPublishButtonCaption(ppObjEdit.publish, true);
            }
            ppModalWasOpen = modalIsOpen;
            PP_InitializeBlockEditorModifications();
            PP_InitializeStatuses();
            PP_ClearBusyButtons();
            PP_RefreshWorkflow();
        } finally {
            if (!ppEditorDisposed) {
                ppEditorObserver.observe(document.body, {childList: true, subtree: true});
            }
        }
    }
    function PP_QueueEditorRefresh() {
        if (!ppEditorDisposed && ppRefreshTimeout === null) {
            ppRefreshTimeout = setTimeout(PP_RefreshEditorUI, 100);
        }
    }
    ppEditorObserver = new MutationObserver(PP_QueueEditorRefresh);
    ppEditorObserver.observe(document.body, {childList: true, subtree: true});

    var ppLastEditorState = '';
    ppEditorUnsubscribe = wp.data.subscribe(function () {
        var editor = wp.data.select('core/editor');
        var state = [editor.getEditedPostAttribute('status'), editor.isSavingPost(), editor.isAutosavingPost()].join('|');
        if (state !== ppLastEditorState) {
            ppLastEditorState = state;
            ppPostSaveCheck();
            PP_QueueEditorRefresh();
        }
    });
    PP_QueueEditorRefresh();

    $(window).on('pagehide.ppStatusesBlockEditor', function () {
        ppEditorDisposed = true;
        ppEditorObserver.disconnect();
        ppEditorUnsubscribe();
        clearTimeout(ppRefreshTimeout);
        ppWaitIntervals.forEach(clearInterval);
        ppWaitTimeouts.forEach(clearTimeout);
        ppWaitIntervals = [];
        ppWaitTimeouts = [];
    });
});
