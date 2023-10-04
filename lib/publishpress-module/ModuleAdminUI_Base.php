<?php

namespace PublishPress;

class ModuleAdminUI_Base {
    public $module;

    private static $instance = null;

    public static function instance($module = false) {
        if (is_null(self::$instance)) {
            self::$instance = new \PublishPress\ModuleAdminUI_Base($module);
        }

        return self::$instance;
    }

    private function __construct($module) {
        if ($module) {
            $this->module = $module;
        }
    }

    public static function defaultHeader() {
        return self::instance()->default_header();
    }

    public function default_header($custom_text = null)
    {
        $display_text = '';

        // If there's been a message, let's display it
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
        } elseif (isset($_REQUEST['message'])) {
            $message = sanitize_text_field($_REQUEST['message']);
        } elseif (isset($_POST['message'])) {
            $message = sanitize_text_field($_POST['message']);
        } else {
            $message = false;
        }

        if ($message && isset($this->module->messages[$message])) {
            $display_text .= '<div class="is-dismissible notice notice-info"><p>' . esc_html($this->module->messages[$message]) . '</p></div>';
        }

        if (!empty($form_errors)) {
            \PublishPress_Statuses::instance()->form_errors = $form_errors;
        }

        // If there's been an error, let's display it
        if ($error = \PublishPress_Statuses::instance()->last_error) {
            $error = sanitize_text_field($error);
        } else {
            $error = false;
        }

        if ($error && isset($this->module->messages[$error])) {
            $display_text .= '<div class="is-dismissible notice notice-error"><p>' . esc_html($this->module->messages[$error]) . '</p></div>';
        }
        ?>

        <div class="publishpress-admin publishpress-admin-wrapper wrap">
            <header>
                <div class="pp-icon">
                <img src="<?php echo PUBLISHPRESS_STATUSES_URL . 'common/assets/publishpress-logo-icon.png';?>" alt="" class="logo-header" />
                </div>

                <h1 class="wp-heading-inline"><?php echo $this->module->title; if (!empty($this->module->header_button)) echo $this->module->header_button;?></h1>

                <?php echo !empty($display_text) ? $display_text : ''; ?>
                <?php // We keep the H2 tag to keep notices tied to the header?>
                <h2>
                    <?php if ($this->module->short_description && empty($custom_text)): ?>
                        <?php echo $this->module->short_description; ?>
                    <?php endif; ?>

                    <?php if (!empty($custom_text)) : ?>
                        <?php echo $custom_text; ?>
                    <?php endif; ?>
                </h2>
                
                <?php 
                do_action('publishpress_default_header');
                ?>
            </header>
        <?php
    }

    public static function defaultFooter($plugin_wp_slug, $plugin_title, $ppcom_url, $ppcom_doc_url, $local_img_url, $args = []) {
    ?>
        <footer>

        <div class="pp-rating">
        <a href="https://wordpress.org/support/plugin/<?php echo esc_url($plugin_wp_slug);?>/reviews/#new-post" target="_blank" rel="noopener noreferrer">
        <?php printf(
                // translators: %1$s is the plugin name, %2$s is the rating stars
            esc_html__('If you like %1$s, please leave us a %2$s rating. Thank you!', 'publishpress-statuses'),
            esc_html($plugin_title),
            '<span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span>'
            );
        ?>
        </a>
        </div>

        <hr>
        <nav>
        <ul>
        <li><a href="<?php echo esc_url($ppcom_url);?>" target="_blank" rel="noopener noreferrer" title="<?php printf(esc_attr__('About %s', 'publishpress-functions'), $plugin_title);?>"><?php esc_html_e('About', 'publishpress-functions');?>
        </a></li>
        <li><a href="<?php echo esc_url($ppcom_doc_url);?>" target="_blank" rel="noopener noreferrer" title="<?php printf(esc_attr__('%s Documentation', 'publishpress-functions'), $plugin_title);?>"><?php esc_html_e('Documentation', 'publishpress-functions');?>
        </a></li>
        <li><a href="https://publishpress.com/contact" target="_blank" rel="noopener noreferrer" title="<?php esc_attr_e('Contact the PublishPress team', 'publishpress-functions');?>"><?php esc_html_e('Contact', 'publishpress-functions');?>
        </a></li>
        <li><a href="https://twitter.com/publishpresscom" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-twitter"></span>
        </a></li>
        <li><a href="https://facebook.com/publishpress" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-facebook"></span>
        </a></li>
        </ul>
        </nav>

        <div class="pp-publishpress-logo">
        <a href="//publishpress.com" target="_blank" rel="noopener noreferrer">
        <img src="<?php echo esc_url($local_img_url);?>" />
        </a>
        </div>

        </footer>
    <?php
    }
}
