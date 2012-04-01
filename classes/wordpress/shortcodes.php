<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * WordPress API for creating bbcode like tags or what WordPress calls "shortcodes".
 * You can extend this class and add custom methods for parsing shortcodes:
 *  1. Add somewhere `Wordpress_Shortcodes::instance()->add('shortcode_name');`
 *  2. Create method `public function shortcode_name()`
 *  3. Parse text `Wordpress_Shortcodes::instance()->parse($text);`
 *
 * @package   WordPress
 * @author    Viacheslav Radionov <radionov@gmail.com>
 * @copyright (c) 2011-2012 Viacheslav Radionov
 * @link      http://slaver.info/
 */
class Wordpress_Shortcodes {

    // Instances
    protected static $instance;

    /**
     * Singleton pattern
     */
    public static function instance()
    {
        if ( ! isset(self::$instance))
        {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public $tags = array();

    /**
     * Adds new shortcode:
     * Wordpress_Shortcodes::instance()->add('');
     * 
     * @param mixed $name
     */
    public function add($name)
    {
        $this->tags[] = $name;
    }

    /**
     * Parse text with shortcodes:
     * Wordpress_Shortcodes::instance()->parse($text)
     * 
     * @param mixed $text
     * @return mixed
     */
    public function parse($text)
    {
        if ( ! empty($this->tags))
        {
            $regex = $this->_regex();
            return preg_replace_callback('/'.$regex.'/s', 'self::_run', $text);
        }

        return $text;
    }

    /**
     * Creates regular expression for parsing shortcodes in text
     * 
     * @return string
     */
    protected function _regex()
    {
        $tagnames  = array_values($this->tags);
        $tagregexp = join('|', array_map('preg_quote', $tagnames));

        return '(.?)\[('.$tagregexp.')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\2\])?(.?)';
    }

    /**
     * Runs parsing and calls methods for showing the result
     * 
     * @param mixed $m
     * @return string
     */
    protected static function _run($m)
    {
        if ($m[1] == '[' && $m[6] == ']')
        {
            return substr($m[0], 1, -1);
        }

        $tag = $m[2];
        $attr = self::_atts($m[3]);

        if (isset($m[5]))
        {
            return $m[1].self::$tag($attr, $m[5], $tag).$m[6];
        }

        return $m[1].self::$tag($attr, NULL, $tag).$m[6];
    }

    /**
     * Parse the attributes of shortcode
     * 
     * @param mixed $text
     * @return string
     */
    protected static function _atts($text)
    {
        $atts = array();
        $pattern = '/(\w+)\s*=\s*"([^"]*)"(?:\s|$)|(\w+)\s*=\s*\'([^\']*)\'(?:\s|$)|(\w+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/';
        $text = preg_replace("/[\x{00a0}\x{200b}]+/u", " ", $text);

        if (preg_match_all($pattern, $text, $match, PREG_SET_ORDER))
        {
            foreach ($match as $m)
            {
                if (!empty($m[1]))
                    $atts[strtolower($m[1])] = stripcslashes($m[2]);
                elseif (!empty($m[3]))
                    $atts[strtolower($m[3])] = stripcslashes($m[4]);
                elseif (!empty($m[5]))
                    $atts[strtolower($m[5])] = stripcslashes($m[6]);
                elseif (isset($m[7]) and strlen($m[7]))
                    $atts[] = stripcslashes($m[7]);
                elseif (isset($m[8]))
                    $atts[] = stripcslashes($m[8]);
            }
        }
        else
        {
            $atts = ltrim($text);
        }

        return $atts;
    }
}