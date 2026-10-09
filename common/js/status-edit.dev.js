jQuery(document).ready(function ($) {
    $('#pp_status_all_types').on('click', function () {
        if ($('#pp_status_all_types').is(':checked')) {
            $('input.pp_status_post_types').prop('disabled', true);
            $('input.pp_status_post_types').prop('checked', false);
        } else {
            $('input.pp_status_post_types').prop('disabled', false);
        }
    });

    $('div.publishpress-admin-wrapper ul.nav-tab-wrapper li a').click(function(e) {
        $('input[type="hidden"][name="pp_tab"]').val($(this).attr('href'));
    });

    $("#addstatus #label").on("change", function(e) {
        if (!$("#addstatus #slug").val()) {
            $("#addstatus #slug").val($(this).val());
            $("#addstatus #slug").trigger("keyup");
        }
    });

    $("#editstatus #label").on("change", function(e) {
        if (!$("#editstatus #slug").val()) {
            $("#editstatus #slug").val($(this).val());
            $("#editstatus #slug").trigger("keyup");
        }
    });

    $("#addstatus #slug, #editstatus #slug").on("keyup", function(e) {
        var value, original_value;
        value = original_value = $(this).val();
        if (e.keyCode !== 9 && e.keyCode !== 37 && e.keyCode !== 38 && e.keyCode !== 39 && e.keyCode !== 40) {
            value = value.replace(/ /g, "-");
            value = value.toLowerCase();
            value = replaceDiacritics(value);
            value = transliterate(value);
            value = replaceSpecialCharacters(value);
            if (value !== original_value) {
                $(this).prop("value", value);
            }
        }
        if (typeof original_slug !== "undefined") {
            var $slugchanged = $("#slugchanged");
            if (value != original_slug) {
                $slugchanged.removeClass("hidemessage");
            } else {
                $slugchanged.addClass("hidemessage");
            }
        }
        var $slugexists = $("#slugexists");
        var $override_validation = $("#override_validation").is(":checked");
    });
    function replaceDiacritics(s) {
        var diacritics = [ /[\300-\306]/g, /[\340-\346]/g, /[\310-\313]/g, /[\350-\353]/g, /[\314-\317]/g, /[\354-\357]/g, /[\322-\330]/g, /[\362-\370]/g, /[\331-\334]/g, /[\371-\374]/g, /[\321]/g, /[\361]/g, /[\307]/g, /[\347]/g ];
        var chars = [ "A", "a", "E", "e", "I", "i", "O", "o", "U", "u", "N", "n", "C", "c" ];
        for (var i = 0; i < diacritics.length; i++) {
            s = s.replace(diacritics[i], chars[i]);
        }
        return s;
    }
    function replaceSpecialCharacters(s) {
        s = s.replace(/[^a-z0-9\s-]/gi, "-");
        return s;
    }

    var cyrillic = {
        "\u0401": "YO",
        "\u0419": "I",
        "\u0426": "TS",
        "\u0423": "U",
        "\u041a": "K",
        "\u0415": "E",
        "\u041d": "N",
        "\u0413": "G",
        "\u0428": "SH",
        "\u0429": "SCH",
        "\u0417": "Z",
        "\u0425": "H",
        "\u042a": "'",
        "\u0451": "yo",
        "\u0439": "i",
        "\u0446": "ts",
        "\u0443": "u",
        "\u043a": "k",
        "\u0435": "e",
        "\u043d": "n",
        "\u0433": "g",
        "\u0448": "sh",
        "\u0449": "sch",
        "\u0437": "z",
        "\u0445": "h",
        "\u044a": "'",
        "\u0424": "F",
        "\u042b": "I",
        "\u0412": "V",
        "\u0410": "a",
        "\u041f": "P",
        "\u0420": "R",
        "\u041e": "O",
        "\u041b": "L",
        "\u0414": "D",
        "\u0416": "ZH",
        "\u042d": "E",
        "\u0444": "f",
        "\u044b": "i",
        "\u0432": "v",
        "\u0430": "a",
        "\u043f": "p",
        "\u0440": "r",
        "\u043e": "o",
        "\u043b": "l",
        "\u0434": "d",
        "\u0436": "zh",
        "\u044d": "e",
        "\u042f": "Ya",
        "\u0427": "CH",
        "\u0421": "S",
        "\u041c": "M",
        "\u0418": "I",
        "\u0422": "T",
        "\u042c": "'",
        "\u0411": "B",
        "\u042e": "YU",
        "\u044f": "ya",
        "\u0447": "ch",
        "\u0441": "s",
        "\u043c": "m",
        "\u0438": "i",
        "\u0442": "t",
        "\u044c": "'",
        "\u0431": "b",
        "\u044e": "yu"
    };
    function transliterate(word) {
        return word.split("").map(function(char) {
            return cyrillic[char] || char;
        }).join("");
    }
});
jQuery(function ($) {
    $('.pp-statuses-role-tooltip').on('click', function (event) {
        if (!$(event.target).closest('.tooltip-text').length) {
            this.focus();
        }
    });
});

jQuery(function ($) {
    if (typeof ppStatusesCapabilities === 'undefined') {
        return;
    }
    var config = ppStatusesCapabilities;
    var returnUrl = new URL(window.location.href);
    returnUrl.searchParams.set('pp_tab', 'roles');

    // Thickbox stops click bubbling. Observe the trigger during capture so
    // popup activation is bound before the iframe's default controls run.
    document.addEventListener('click', function (event) {
        if (!$(event.target).closest('.pp-statuses-role-tooltip a.thickbox').length) {
            return;
        }
        // Thickbox creates its iframe synchronously during this click.
        window.setTimeout(function () {
            var frame = document.getElementById('TB_iframeContent');
            if (!frame) {
                return;
            }
            $(frame).on('load.ppStatusesCapabilities', function () {
                var doc = frame.contentDocument;
                if (!doc) {
                    return;
                }
                // Capture before WordPress's default install/activate handlers.
                doc.addEventListener('click', function (event) {
                    var button = $(event.target).closest('#plugin_install_from_iframe, #plugin_activate_from_iframe, a[href*="action=activate"]')[0];
                    if (!button) {
                        return;
                    }
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    if ($(button).data('pp-statuses-busy')) {
                        return;
                    }
                    $(button).data('pp-statuses-busy', true).attr('aria-disabled', 'true');
                    $(doc).find('.pp-statuses-plugin-error').remove();

                    function fail(response) {
                        var data = response && response.responseJSON ? response.responseJSON.data : response && response.data;
                        var message = data && (data.message || data.errorMessage);
                        $('<div class="notice notice-error pp-statuses-plugin-error"><p></p></div>')
                            .find('p').text(message || config.error).end().insertBefore(button);
                        $(button).data('pp-statuses-busy', false).removeAttr('aria-disabled');
                    }
                    function activate(plugin) {
                        if (!config.canActivate) {
                            window.location.assign(returnUrl.href);
                            return;
                        }
                        $.post(config.ajaxUrl, {action: 'pp_statuses_activate_capabilities', nonce: config.nonce, plugin: plugin, returnUrl: returnUrl.href})
                            .done(function (response) {
                                if (response.success) {
                                    window.location.assign(returnUrl.href);
                                } else {
                                    fail(response);
                                }
                            }).fail(fail);
                    }
                    if (config.plugin) {
                        activate(config.plugin);
                    } else {
                        $.post(config.ajaxUrl, {action: 'install-plugin', slug: 'capability-manager-enhanced', _ajax_nonce: config.installNonce})
                            .done(function (response) {
                                if (response.success && response.data && response.data.plugin) {
                                    config.plugin = response.data.plugin;
                                    activate(config.plugin);
                                } else {
                                    fail(response);
                                }
                            }).fail(fail);
                    }
                }, true);
            });
        }, 0);
    }, true);
});
