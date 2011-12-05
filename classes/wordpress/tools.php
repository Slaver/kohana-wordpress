<?php defined('SYSPATH') OR die('No direct access allowed.');

class Wordpress_Tools {

    public static function get_post_thumbnail($post = array(), $size = 'post-thumbnail', $alt = NULL)
    {
        if ( ! empty($post['thumb']['attach']))
        {
            // Images path root
            $image_root = Wordpress_Options::instance()->get_option('upload_path');

            // Default image (full)
            if ($size === 'full')
            {
                $image = Arr::path($post, 'thumb.attach.file');
                if ($image !== NULL)
                {
                    $image = Text::reduce_slashes($image_root.'/'.$image);

                    return html::image($image, array(
                        'width'  => Arr::path($post, 'thumb.attach.width'),
                        'height' => Arr::path($post, 'thumb.attach.height'),
                        'alt'    => ( ! empty($alt)) ? $alt : Arr::path($post, 'thumb.content.post_title'),
                    ));
                }
            }

            // Selected image
            $image_url = explode('/', $post['thumb']['attach']['file']);
            $file_path = $image_root.'/'.str_replace(end($image_url), '', $post['thumb']['attach']['file']);

            $image = Arr::path($post, 'thumb.attach.sizes.'.$size.'.file');
            if ($image !== NULL)
            {
                $image = Text::reduce_slashes($file_path.$image);

                return html::image($image, array(
                    'width'  => Arr::path($post, 'thumb.attach.sizes.'.$size.'.width'),
                    'height' => Arr::path($post, 'thumb.attach.sizes.'.$size.'.height'),
                    'alt'    => ( ! empty($alt)) ? $alt : Arr::path($post, 'thumb.content.post_title'),
                ));
            }
        }
    }

}