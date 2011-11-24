<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * WordPress API for creating bbcode like tags or what WordPress calls
 * "shortcodes." The tag and attribute parsing or regular expression code is
 * based on the Textpattern tag parser.
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

    function add($name)
    {
        $this->tags[] = $name;
    }

    function regex($name)
    {
        $tagnames = array_values($this->tags);
        $tagregexp = join('|', array_map('preg_quote', $tagnames));

        return '(.?)\[('.$tagregexp.')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\2\])?(.?)';
    }

    function parse($text)
    {
        if ( ! empty($this->tags))
        {
            $regex = $this->regex($name);
            return preg_replace_callback('/'.$regex.'/s', 'self::run', $text);
        }
        else
        {
            return $text;
        }
    }

    /**
     * $m[0] — строка до парсинга
     * $m[1] — текст до тэга
     * $m[2] — shortcode
     *
     * @param type $m
     * @return type 
     */
    public static function run($m)
    {
        if ($m[1] == '[' && $m[6] == ']')
        {
            return substr($m[0], 1, -1);
        }

        $tag = $m[2];
        $attr = self::atts($m[3]);

        if (isset($m[5]))
        {
            // enclosing tag - extra parameter
            return $m[1] . Shortcodes::$tag($attr, $m[5], $tag) . $m[6];
            //return $m[1] . call_user_func( $shortcode_tags[$tag], $attr, $m[5], $tag ) . $m[6];
            //var_dump($m[1] . $shortcode_tags, $tag, $attr, $m[5], $tag  . $m[6] );
        } else {
            // self-closing tag
            return $m[1] . Shortcodes::$tag($attr, NULL, $tag) . $m[6];
            //return $m[1] . call_user_func( $shortcode_tags[$tag], $attr, NULL,  $tag ) . $m[6];
            //var_dump( $m[1] . $shortcode_tags, $tag, $attr, NULL,  $tag . $m[6] );
        }
    }

    public static function atts($text)
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