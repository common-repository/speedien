<?php

function speedien_settings_init()
{
    // Register a new setting for "speedien" page.
    register_setting('speedien', 'speedien_options');

    // Register a new section in the "speedien" page.
    $options = get_option('speedien_options');
    
    add_settings_section(
        'speedien_section_developers',
        __('Pagespeed Optimization Settings ', 'speedien'),
        'speedien_section_developers_callback',
        'speedien'
    );

    // Register a new field in the "speedien_section_developers" section, inside the "speedien" page.
    add_settings_field(
        'speedien_field_site_id', // As of WP 4.6 this value is used only internally.
        // Use $args' label_for to populate the id inside the callback.
        __('Speedien Site ID', 'speedien'),
        'speedien_field_site_id_cb',
        'speedien',
        'speedien_section_developers',
        array(
            'label_for'            => 'speedien_field_site_id',
            'class'                => 'speedien_row',
            'speedien_custom_data' => 'custom',
        )
    );	
    // Register a new field in the "speedien_section_developers" section, inside the "speedien" page.
    add_settings_field(
        'speedien_field_api_key',
        __('Speedien API Key', 'speedien'),
        'speedien_field_api_key_cb',
        'speedien',
        'speedien_section_developers',
        array(
            'label_for'            => 'speedien_field_api_key',
            'class'                => 'speedien_row',
            'speedien_custom_data' => 'custom',
        )
    );

    add_settings_field(
        'speedien_field_jse_type',
        __('Javascript Exclusion Type', 'speedien'),
        'speedien_field_jse_type_cb',
        'speedien',
        'speedien_section_developers',
        array(
            'label_for'            => 'speedien_field_jse_type',
            'class'                => 'speedien_row',
            'speedien_custom_data' => 'custom',
        )
    );

    add_settings_field(
        'speedien_field_jslist',
        __('Javascript Exclusion List', 'speedien'),
        'speedien_field_jslist_cb',
        'speedien',
        'speedien_section_developers',
        array(
            'label_for'            => 'speedien_field_jslist',
            'class'                => 'speedien_row',
            'speedien_custom_data' => 'custom',
        )
    );

    add_settings_field(
        'speedien_field_pagelist',
        __('Exclude pages from optimization', 'speedien'),
        'speedien_field_pagelist_cb',
        'speedien',
        'speedien_section_developers',
        array(
            'label_for'            => 'speedien_field_pagelist',
            'class'                => 'speedien_row',
            'speedien_custom_data' => 'custom',
        )
    );

}

/**
 * Register our speedien_settings_init to the admin_init action hook.
 */
add_action('admin_init', 'speedien_settings_init');

function speedien_cache_init()
{
    file_put_contents(WP_CONTENT_DIR . '/advanced-cache.php', '');
}

/**
 * Custom option and settings:
 *  - callback functions
 */


/**
 * Developers section callback function.
 *
 * @param array $args  The settings array, defining title, id, callback.
 */
function speedien_section_developers_callback($args)
{

    $options = get_option('speedien_options');
    
    if(empty($options['speedien_field_api_key']) || empty($options['speedien_field_site_id']))
    {
?>

<p id="<?php echo esc_attr($args['id']); ?>">Please visit <a href="https://my.speedien.com/signup" target="_blank">https://my.speedien.com/signup</a> to generate a new API key and Site ID.</p>
<?php
    }
}

    function speedien_field_api_key_cb($args)
    {
        // Get the value of the setting we've registered with register_setting()
        $options = get_option('speedien_options');
        if (isset($options[$args['label_for']])) {
            $api_key = esc_attr($options[$args['label_for']]);
        } else {
            $api_key = '';
        }

    ?>

<input type="password" name="speedien_options[<?php echo esc_attr($args['label_for']); ?>]"
    id="<?php echo esc_attr($args['label_for']); ?>" value="<?php echo $api_key; ?>" />

<?php
    }

    function speedien_field_site_id_cb($args)
    {
        // Get the value of the setting we've registered with register_setting()
        $options = get_option('speedien_options');
        if (isset($options[$args['label_for']])) {
            $api_key = esc_attr($options[$args['label_for']]);
        } else {
            $api_key = '';
        }

?>

<input type="password" name="speedien_options[<?php echo esc_attr($args['label_for']); ?>]"
    id="<?php echo esc_attr($args['label_for']); ?>" value="<?php echo $api_key; ?>" />

<?php
    }

    function speedien_field_pagelist_cb($args)
    {
        // Get the value of the setting we've registered with register_setting()
        $options = get_option('speedien_options');
        if (isset($options[$args['label_for']])) {
            $page_exclusions = esc_attr($options[$args['label_for']]);
        } else {
            $page_exclusions = '';
        }

?>

<textarea name="speedien_options[<?php echo esc_attr($args['label_for']); ?>]"
    id="<?php echo esc_attr($args['label_for']); ?>" rows="4" cols="40"><?php echo $page_exclusions; ?></textarea>
    <br><span>Enter the slugs of the pages that you wish to exclude from optimization. (Example: /contact)</span>
<?php
    }

    function speedien_field_jse_type_cb($args)
    {
        // Get the value of the setting we've registered with register_setting()
        $options = get_option('speedien_options');
        if (isset($options[$args['label_for']])) {
            $jse_type = esc_attr($options[$args['label_for']]);
        } else {
            $jse_type = '1';
        }

?>
<input type="radio" name="speedien_options[<?php echo esc_attr($args['label_for']); ?>]" value="1" <?php checked(1, $jse_type, true); ?>>Exclude all Javascript except for the following list.<br /><br />
<input type="radio" name="speedien_options[<?php echo esc_attr($args['label_for']); ?>]" value="2" <?php checked(2, $jse_type, true); ?>>Exclude only Javascript matching the following list.


<?php
    }

    function speedien_field_jslist_cb($args)
    {
        // Get the value of the setting we've registered with register_setting()
        $options = get_option('speedien_options');
        if (isset($options[$args['label_for']])) {
            $js_exclusions = esc_attr($options[$args['label_for']]);
        } else {
            $js_exclusions = '';
        }

?>

<textarea name="speedien_options[<?php echo esc_attr($args['label_for']); ?>]"
    id="<?php echo esc_attr($args['label_for']); ?>" rows="4" cols="40"><?php echo $js_exclusions; ?></textarea>
    <br><span>Enter the keyword from javascript code or url. (Example: gtm.js)</span>
<?php
    }

    
    /**
     * Add the top level menu page.
     */
    function speedien_options_page()
    {
        add_menu_page(
            'Pagespeed Optimization Settings',
            'Pagespeed ',
            'manage_options',
            'speedien',
            'speedien_options_page_html'
        );
    }


    /**
     * Register our speedien_options_page to the admin_menu action hook.
     */
    add_action('admin_menu', 'speedien_options_page');


    /**
     * Top level menu callback function
     */
    function speedien_options_page_html()
    {
        // check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // add error/update messages

        // check if the user have submitted the settings
        // WordPress will add the "settings-updated" $_GET parameter to the url
        if (isset($_GET['settings-updated'])) {
            // add settings saved message with the class of "updated"
            add_settings_error('speedien_messages', 'speedien_message', __('Settings Saved', 'speedien'), 'updated');
        }

        // show error/update messages

        settings_errors('speedien_messages');

        $options = get_option('speedien_options');

?>
<div class="wrap">

    <form action="options.php" method="post" class="speedien-form-bg">
        <?php
            // output security fields for the registered setting "speedien"
            settings_fields('speedien');
            // output setting sections and their fields
            // (sections are registered for "speedien", each field is registered to a specific section)
            do_settings_sections('speedien');
            // output save settings button
            submit_button('Save Settings');
            ?>
    </form>
    <style>
    .speedien-form-bg {
        background: #fff;
        padding: 10px 25px;
    }

    .speedien-field {
        margin: 10px 0px;
    }
    </style>

</div>

<?php
    }

    function speedien_section_preload_callback( $arg ) { ?>
        <input type="hidden" name="action" value="speedien_preload_content">
        <?php
    }

    function speedien_preload_content_handler() {
        update_option('speedien_preload_status','In Progress');
        
        $preloadurls = array();
        $pages = get_pages(array('number' => 100));
        foreach($pages as $page)
        {
            $preloadurls[] = get_permalink($page->ID);
        }
        $posts = get_posts(array('number' => 50));
        foreach($posts as $post)
        {
            $preloadurls[] = get_permalink($post->ID);
        }

        $options = get_option('speedien_options');
        $data = array('api_key'=>$options['speedien_field_api_key'], 'site_id' => $options['speedien_field_site_id'], 'urls' => $preloadurls);

        $response = wp_remote_post(SPEEDIEN_API_URL . '/preload', array('body' => $data, 'timeout' => 10));
        wp_redirect(admin_url('admin.php?page=speedien'));
    }
    add_action( 'admin_post_speedien_preload_content', 'speedien_preload_content_handler' );