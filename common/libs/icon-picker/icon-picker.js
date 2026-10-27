/**
 * Dashicons Picker
 *
 * Based on: https://github.com/bradvin/dashicons-picker/
 * 
 * License: GPL Version 2
 * 
 */

(function ($) {
    'use strict';
    /**
     *
     * @returns {void}
     */
    $.fn.dashiconsPicker = function () {

        /**
         * Dashicons, in CSS order
         *
         * @type Array
         */
        var icons = [
            'menu',
            'admin-site',
            'dashboard',
            'admin-media',
            'admin-page',
            'admin-comments',
            'admin-appearance',
            'admin-plugins',
            'admin-users',
            'admin-tools',
            'admin-settings',
            'admin-network',
            'admin-generic',
            'admin-home',
            'admin-collapse',
            'filter',
            'admin-customizer',
            'admin-multisite',
            'admin-links',
            'format-links',
            'admin-post',
            'format-standard',
            'format-image',
            'format-gallery',
            'format-audio',
            'format-video',
            'format-chat',
            'format-status',
            'format-aside',
            'format-quote',
            'welcome-write-blog',
            'welcome-edit-page',
            'welcome-add-page',
            'welcome-view-site',
            'welcome-widgets-menus',
            'welcome-comments',
            'welcome-learn-more',
            'image-crop',
            'image-rotate',
            'image-rotate-left',
            'image-rotate-right',
            'image-flip-vertical',
            'image-flip-horizontal',
            'image-filter',
            'undo',
            'redo',
            'editor-bold',
            'editor-italic',
            'editor-ul',
            'editor-ol',
            'editor-quote',
            'editor-alignleft',
            'editor-aligncenter',
            'editor-alignright',
            'editor-insertmore',
            'editor-spellcheck',
            'editor-distractionfree',
            'editor-expand',
            'editor-contract',
            'editor-kitchensink',
            'editor-underline',
            'editor-justify',
            'editor-textcolor',
            'editor-paste-word',
            'editor-paste-text',
            'editor-removeformatting',
            'editor-video',
            'editor-customchar',
            'editor-outdent',
            'editor-indent',
            'editor-help',
            'editor-strikethrough',
            'editor-unlink',
            'editor-rtl',
            'editor-break',
            'editor-code',
            'editor-paragraph',
            'editor-table',
            'align-left',
            'align-right',
            'align-center',
            'align-none',
            'lock',
            'unlock',
            'calendar',
            'calendar-alt',
            'visibility',
            'hidden',
            'post-status',
            'edit',
            'post-trash',
            'trash',
            'sticky',
            'external',
            'arrow-up',
            'arrow-down',
            'arrow-left',
            'arrow-right',
            'arrow-up-alt',
            'arrow-down-alt',
            'arrow-left-alt',
            'arrow-right-alt',
            'arrow-up-alt2',
            'arrow-down-alt2',
            'arrow-left-alt2',
            'arrow-right-alt2',
            'leftright',
            'sort',
            'randomize',
            'list-view',
            'excerpt-view',
            'grid-view',
            'hammer',
            'art',
            'migrate',
            'performance',
            'universal-access',
            'universal-access-alt',
            'tickets',
            'nametag',
            'clipboard',
            'heart',
            'megaphone',
            'schedule',
            'wordpress',
            'wordpress-alt',
            'pressthis',
            'update',
            'screenoptions',
            'cart',
            'feedback',
            'cloud',
            'translation',
            'tag',
            'category',
            'archive',
            'tagcloud',
            'text',
            'media-archive',
            'media-audio',
            'media-code',
            'media-default',
            'media-document',
            'media-interactive',
            'media-spreadsheet',
            'media-text',
            'media-video',
            'playlist-audio',
            'playlist-video',
            'controls-play',
            'controls-pause',
            'controls-forward',
            'controls-skipforward',
            'controls-back',
            'controls-skipback',
            'controls-repeat',
            'controls-volumeon',
            'controls-volumeoff',
            'yes',
            'no',
            'no-alt',
            'plus',
            'plus-alt',
            'plus-alt2',
            'minus',
            'dismiss',
            'marker',
            'star-filled',
            'star-half',
            'star-empty',
            'flag',
            'info',
            'warning',
            'share',
            'share1',
            'share-alt',
            'share-alt2',
            'twitter',
            'rss',
            'email',
            'email-alt',
            'facebook',
            'facebook-alt',
            'networking',
            'googleplus',
            'location',
            'location-alt',
            'camera',
            'images-alt',
            'images-alt2',
            'video-alt',
            'video-alt2',
            'video-alt3',
            'vault',
            'shield',
            'shield-alt',
            'sos',
            'search',
            'slides',
            'analytics',
            'chart-pie',
            'chart-bar',
            'chart-line',
            'chart-area',
            'groups',
            'businessman',
            'id',
            'id-alt',
            'products',
            'awards',
            'forms',
            'testimonial',
            'portfolio',
            'book',
            'book-alt',
            'download',
            'upload',
            'backup',
            'clock',
            'lightbulb',
            'microphone',
            'desktop',
            'tablet',
            'smartphone',
            'phone',
            'smiley',
            'index-card',
            'carrot',
            'building',
            'store',
            'album',
            'palmtree',
            'tickets-alt',
            'money',
            'thumbs-up',
            'thumbs-down',
            'layout',
            'align-pull-left',
            'align-pull-right',
            'block-default',
            'cloud-saved',
            'cloud-upload',
            'columns',
            'cover-image',
            'embed-audio',
            'embed-generic',
            'embed-photo',
            'embed-post',
            'embed-video',
            'exit',
            'html',
            'info-outline',
            'insert-after',
            'insert-before',
            'insert',
            'remove',
            'shortcode',
            'table-col-after',
            'table-col-before',
            'table-col-delete',
            'table-row-after',
            'table-row-before',
            'table-row-delete',
            'saved',
            'amazon',
            'google',
            'linkedin',
            'pinterest',
            'podio',
            'reddit',
            'spotify',
            'twitch',
            'whatsapp',
            'xing',
            'youtube',
            'database-add',
            'database-export',
            'database-import',
            'database-remove',
            'database-view',
            'database',
            'bell',
            'airplane',
            'car',
            'calculator',
            'ames',
            'printer',
            'beer',
            'coffee',
            'drumstick',
            'food',
            'bank',
            'hourglass',
            'money-alt',
            'open-folder',
            'pdf',
            'pets',
            'privacy',
            'superhero',
            'superhero-alt',
            'edit-page',
            'fullscreen-alt',
            'fullscreen-exit-alt',
            '',
            '',
            ''
        ];

        var l10n = $.extend({
            dialogLabel: 'Select an icon',
            searchLabel: 'Search icons',
            searchPlaceholder: 'Search',
            previous: 'Previous icons',
            next: 'Next icons',
            current: 'Current icon: %s'
        }, (typeof window.ppIconPickerL10n === 'object') ? window.ppIconPickerL10n : {});

        // Convert an icon slug such as "arrow-left-alt2" into readable words ("Arrow left alt 2").
        var iconToWords = function (slug) {
            var words = String(slug).replace(/^dashicons-/, '').replace(/[-_]+/g, ' ').replace(/([a-z])(\d)/gi, '$1 $2');
            return words.charAt(0).toUpperCase() + words.slice(1);
        };

        return this.each(function () {

            var button = $(this),
                offsetTop,
                offsetLeft;

            button.attr('aria-expanded', 'false');

            button.on('click.dashiconsPicker', function (e) {
                e.preventDefault();

                if ($('.dashicon-picker-container').length) {
                    removePopup(true);
                    return;
                }

                offsetTop = $(e.currentTarget).offset().top;
                offsetLeft = $(e.currentTarget).offset().left;
                createPopup(button);
            });

            function createPopup(button) {

                var target = $(button.data('target')),
                    preview = $(button.data('preview')),
                    currentLabel = $(button.data('current')),
                    currentIcon = String(target.val() || '').replace(/^dashicons-/, ''),
                    popup = $('<div class="dashicon-picker-container" role="dialog">' +
                        '<div class="dashicon-picker-control"></div>' +
                        '<ul class="dashicon-picker-list"></ul>' +
                        '</div>').css({
                        'top': offsetTop,
                        'left': offsetLeft
                    }),
                    list = popup.find('.dashicon-picker-list');

                popup.attr('aria-label', l10n.dialogLabel);

                for (var i in icons) {
                    if (icons.hasOwnProperty(i) && icons[i]) {
                        var item = $('<li></li>').attr('data-icon', icons[i]),
                            iconButton = $('<button type="button"></button>')
                                .attr('data-icon', icons[i])
                                .attr('aria-label', iconToWords(icons[i]))
                                .attr('aria-pressed', (icons[i] === currentIcon) ? 'true' : 'false')
                                .append($('<span class="dashicons" aria-hidden="true"></span>').addClass('dashicons-' + icons[i]));

                        list.append(item.append(iconButton));
                    }
                }

                $('button', list).on('click', function (e) {
                    e.preventDefault();
                    var icon = $(this).attr('data-icon');
                    target.val('dashicons-' + icon);
                    preview
                        .prop('class', 'dashicons')
                        .addClass('dashicons-' + icon);

                    if (currentLabel.length) {
                        currentLabel.text(l10n.current.replace('%s', iconToWords(icon)));
                    }

                    removePopup(true);
                });

                var control = popup.find('.dashicon-picker-control');

                control.append(
                    $('<button type="button" class="dashicon-picker-nav" data-direction="back"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button>').attr('aria-label', l10n.previous),
                    $('<input type="search" class="" />').attr({'aria-label': l10n.searchLabel, 'placeholder': l10n.searchPlaceholder}),
                    $('<button type="button" class="dashicon-picker-nav" data-direction="forward"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>').attr('aria-label', l10n.next)
                );

                $('button', control).on('click', function (e) {
                    e.preventDefault();
                    var count = $('li', list).length;

                    if ($(this).data('direction') === 'back') {
                        $('li:gt(' + (count - 26) + ')', list).prependTo(list);
                    } else {
                        $('li:lt(25)', list).appendTo(list);
                    }
                });

                popup.appendTo('body').show();
                button.attr('aria-expanded', 'true');

                $('input', control).on('keyup search', function (e) {
                    var search = $(this).val();
                    if (search === '') {
                        $('li', list).show();
                    } else {
                        $('li', list).each(function () {
                            var icon = String($(this).data('icon'));
                            if (icon.toLowerCase().indexOf(search.toLowerCase()) !== -1
                            || iconToWords(icon).toLowerCase().indexOf(search.toLowerCase()) !== -1
                            ) {
                                $(this).show();
                            } else {
                                $(this).hide();
                            }
                        });
                    }
                });

                // Keyboard: Escape closes and returns focus to the trigger; Tab cycles within the popup.
                popup.on('keydown', function (e) {
                    if (e.key === 'Escape' || e.keyCode === 27) {
                        e.preventDefault();
                        e.stopPropagation();
                        removePopup(true);
                        return;
                    }

                    if (e.key === 'Tab' || e.keyCode === 9) {
                        var focusable = popup.find('button, input').filter(':visible');
                        if (!focusable.length) {
                            return;
                        }

                        var first = focusable.first()[0],
                            last = focusable.last()[0];

                        if (e.shiftKey && document.activeElement === first) {
                            e.preventDefault();
                            last.focus();
                        } else if (!e.shiftKey && document.activeElement === last) {
                            e.preventDefault();
                            first.focus();
                        }
                    }
                });

                $(document).on('mouseup.dashicons-picker', function (e) {
                    if (!popup.is(e.target) && popup.has(e.target).length === 0
                    && !button.is(e.target) && button.has(e.target).length === 0
                    ) {
                        removePopup(false);
                    }
                });

                // Move focus into the popup.
                $('input', control).trigger('focus');
            }

            function removePopup(returnFocus) {
                $('.dashicon-picker-container').remove();
                $(document).off('.dashicons-picker');
                button.attr('aria-expanded', 'false');

                if (returnFocus) {
                    button.trigger('focus');
                }
            }
        });
    };

    $(function () {
        $('.dashicons-picker').dashiconsPicker();
    });

}(jQuery));
