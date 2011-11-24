<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * WordPress Formatting API.
 * Handles many functions for formatting output
 *
 * @package   WordPress
 * @author    Viacheslav Radionov <radionov@gmail.com>
 * @copyright (c) 2011-2012 Viacheslav Radionov
 * @link      http://slaver.info/
 */
class Wordpress_Format {

    public static function prepare_text($content)
    {
        $content = preg_replace('/<a href=\"(.*?)\">(.*?)<\/a>/', "\\2", $content);
        $content = preg_replace('/<p>(.*?)<\/p>/', "\\2", $content);

        return $content;
    }

    /**
     * Accepts matches array from preg_replace_callback in wpautop() or a string.
     *
     * Ensures that the contents of a <<pre>>...<</pre>> HTML block are not
     * converted into paragraphs or line-breaks.
     *
     * @param array|string $matches The array or string
     * @return string The pre block without paragraph/line-break conversion.
     */
    public static function clean_pre($matches)
    {
        if (is_array($matches))
        {
            $text = $matches[1] . $matches[2] . "</pre>";
        }
        else
        {
            $text = $matches;
        }

        $text = str_replace('<br />', '', $text);
        $text = str_replace('<p>', "\n", $text);
        $text = str_replace('</p>', '', $text);

        return $text;
    }

    /**
     * Replaces double line-breaks with paragraph elements.
     *
     * A group of regex replaces used to identify text formatted with newlines and
     * replace double line-breaks with HTML paragraph tags. The remaining
     * line-breaks after conversion become <<br />> tags, unless $br is set to '0'
     * or 'false'.
     *
     * @param string $str The text which has to be formatted.
     * @param int|bool $br Optional. If set, this will convert all remaining line-breaks after paragraphing. Default true.
     * @return string Text which has been converted into correct paragraph tags.
     */
    public static function auto_p($str, $br = TRUE)
    {
        if (($str = trim($str)) === '')
			return '';

        $str = $str . "\n"; // just to make things a little easier, pad the end
        $str = preg_replace('|<br />\s*<br />|', "\n\n", $str);
        // Space things out a little
        $allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|option|form|map|area|blockquote|address|math|style|input|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
        $str = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $str);
        $str = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $str);
        $str = str_replace(array("\r\n", "\r"), "\n", $str); // cross-platform newlines
        if (strpos($str, '<object') !== FALSE)
        {
            $str = preg_replace('|\s*<param([^>]*)>\s*|', "<param$1>", $str); // no pee inside object/embed
            $str = preg_replace('|\s*</embed>\s*|', '</embed>', $str);
        }
        $str = preg_replace("/\n\n+/", "\n\n", $str); // take care of duplicates
        // make paragraphs, including one at the end
        $pees = preg_split('/\n\s*\n/', $str, -1, PREG_SPLIT_NO_EMPTY);
        $str = '';
        foreach ($pees as $tinkle)
        {
            $str .= '<p>'.trim($tinkle, "\n")."</p>\n";
        }
        $str = preg_replace('|<p>\s*</p>|', '', $str); // under certain strange conditions it could create a P of entirely whitespace
        $str = preg_replace('!<p>([^<]+)</(div|address|form)>!', "<p>$1</p></$2>", $str);
        $str = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $str); // don't pee all over a tag
        $str = preg_replace("|<p>(<li.+?)</p>|", "$1", $str); // problem with nested lists
        $str = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $str);
        $str = str_replace('</blockquote></p>', '</p></blockquote>', $str);
        $str = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $str);
        $str = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $str);

        if ($br === TRUE)
        {
            $str = preg_replace_callback('/<(script|style).*?<\/\\1>/s', 'self::_autop_newline_preservation_helper', $str);
            $str = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $str); // optionally make line breaks
            $str = str_replace('<WPPreserveNewline />', "\n", $str);
        }
        $str = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $str);
        $str = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $str);
        if (strpos($str, '<pre') !== FALSE)
        {
            $str = preg_replace_callback('!(<pre[^>]*>)(.*?)</pre>!is', 'clean_pre', $str);
        }
        $str = preg_replace("|\n</p>$|", '</p>', $str);

        return $str;
    }

    /**
     * Newline preservation help function for wpautop
     *
     * @since 3.1.0
     * @access private
     * @param array $matches preg_replace_callback matches array
     * @returns string
     */
    private static function _autop_newline_preservation_helper($matches)
    {
        return str_replace("\n", "<WPPreserveNewline />", $matches[0]);
    }
}