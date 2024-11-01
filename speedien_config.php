<?php

function speedien_load_config() {
    if(defined('SPEEDIEN_API_KEY') && defined('SPEEDIEN_SITE_ID'))
    {
        $options = get_option('speedien_options');
        $options['speedien_field_api_key'] = SPEEDIEN_API_KEY;
        $options['speedien_field_site_id'] = SPEEDIEN_SITE_ID;
        update_option('speedien_options', $options);
    }
}

speedien_load_config();