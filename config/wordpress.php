<?php

defined('SYSPATH') OR die('No direct access allowed.');

return array(
    // Deafult capabilities
    'wp_user_level' => 0,
    'wp_capabilities' => array('subscriber' => 1),

    // Fields for update (in wp_users)
    'user_fields' => array('user_login', 'user_pass', 'user_nicename', 'user_email', 'user_url', 'display_name'),
);