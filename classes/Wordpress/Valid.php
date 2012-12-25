<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * WordPress valid functions for Kohana
 *
 * @package   WordPress
 * @author    Viacheslav Radionov <radionov@gmail.com>
 * @copyright (c) 2011-2012 Viacheslav Radionov
 * @link      http://slaver.info/
 */
class Wordpress_Valid extends Kohana_Valid {

    /**
     * Check if user already exists in database
     * It uses for comments from unregistered users
     * 
     * @param   mixed   $value  username|user_email
     * @return  boolean
     */
    public static function is_user_exist($value)
    {
        return ! Wordpress_Auth::instance()->is_user_exist($value);
    }

    /**
     * Check if IP is banned for comments
     * 
     * @param   mixed   $value SERVER_ADDR
     * @return  boolean
     */
    public static function check_is_banned($value)
    {
        $blacklist_keys = Wordpress_Options::instance()->get_option('blacklist_keys');
        if ( ! empty($blacklist_keys))
        {
            $banned_ip = explode("\n", $blacklist_keys);
            foreach ((array)$banned_ip as $ip)
            {
                $ip = trim($ip);
                if (empty($ip))
                {
                    continue;
                }
                $ip = preg_quote($ip, '#');
                $pattern = "#$ip#i";

                if (preg_match($pattern, $value))
                {
                    return FALSE;
                }
            }
        }
        return TRUE;
    }

}