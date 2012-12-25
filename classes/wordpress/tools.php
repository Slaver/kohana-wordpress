<?php defined('SYSPATH') OR die('No direct access allowed.');

class Wordpress_Tools {

    /**
     * Get or generate thumbnail for post
     * 
     * @param mixed $post
     * @param mixed $size
     * @param mixed $alt
     * @param mixed $select
     * @param mixed $grayscale
     * @param mixed $local
     * @return string
     */
    public static function get_post_thumbnail($post = array(), $size = 'post-thumbnail', $html = TRUE, $alt = NULL, $select = 0, $grayscale = TRUE, $local = TRUE)
    {
        if ($size === 'post-thumbnail')
        {
            $size = 'thumbnail';
        }

        // Images sizes
        switch ($size)
        {
            case 'large':
                $sizes = array(Wordpress_Options::instance()->get_option('large_size_w'), Wordpress_Options::instance()->get_option('large_size_h'));
                break;
            case 'medium':
                $sizes = array(Wordpress_Options::instance()->get_option('medium_size_w'), Wordpress_Options::instance()->get_option('medium_size_h'));
                break;
            default:
                $sizes = array(Wordpress_Options::instance()->get_option('thumbnail_size_w'), Wordpress_Options::instance()->get_option('thumbnail_size_h'));
                break;
        }

        // Images path root
        $image_root = Wordpress_Options::instance()->get_option('upload_path');

        if ( ! empty($post['thumb']['attach']))
        {
            // Default image (full)
            if ($size === 'full')
            {
                $image = Arr::path($post, 'thumb.attach.file');
                if ($image !== NULL)
                {
                    $image = Text::reduce_slashes($image_root.'/'.$image);

                    return ($html) ? html::image($image, array(
                        'width'  => Arr::path($post, 'thumb.attach.width'),
                        'height' => Arr::path($post, 'thumb.attach.height'),
                        'alt'    => ( ! empty($alt)) ? $alt : Arr::path($post, 'thumb.content.post_title'),
                    ), 'http') : URL::site($image, 'http');
                }
            }

            // Selected image
            $image_url = explode('/', $post['thumb']['attach']['file']);
            $file_path = $image_root.'/'.str_replace(end($image_url), '', $post['thumb']['attach']['file']);
            $image_by_size = Arr::path($post, 'thumb.attach.sizes.'.$size.'.file');

            if ($image_by_size !== NULL)
            {
                $image = Text::reduce_slashes($file_path.$image_by_size);

                return ($html) ? html::image($image, array(
                    'width'  => Arr::path($post, 'thumb.attach.sizes.'.$size.'.width'),
                    'height' => Arr::path($post, 'thumb.attach.sizes.'.$size.'.height'),
                    'alt'    => ( ! empty($alt)) ? $alt : Arr::path($post, 'thumb.content.post_title'),
                ), 'http') : URL::site($image, 'http');
            }
        }
        else if ( ! empty($post['post_content']))
        {
            // Grep text for images
            $pattern = "/\<\s*img.*src\s*=\s*[\"\']?([^\"\'\ >]*)[\"\']?.*\/\>/i";
            if ($local)
            {
                $home = addcslashes(Wordpress_Options::instance()->get_option('siteurl'), '.-/');
                //$home = 'http:\/\/ultra\-music\.com\/';
                $pattern = "/\<\s*img.*src\s*=\s*[\"\']?(?:$home|\/)([^\"\'\ >]*)[\"\']?.*\/\>/i";
            }

            // Count images in text
            preg_match_all($pattern, $post['post_content'], $images);
            $count = count($images[1]);

            if ($count > 0)
            {
                $select = (isset($select) && is_numeric($select)) ? $select : rand(0, $count);
                $image_url  = ltrim($images[1][$select], '/');
                $image_path = realpath($image_url);

                if ( ! empty($image_url))
                {
                    // Create thumbnail
                    if ($size !== 'full')
                    {
                        $tmp_dir  = $image_root.'/cache';
                        $tmp_path = realpath($tmp_dir);
                        $tmp_name = substr(md5($image_url.'-'.$sizes[0].'-'.$sizes[1]), 0, 7).'.jpg';
                        $tmp_file = $tmp_path.'/'.$tmp_name;

                        if ( ! is_file($tmp_file))
                        {
                            if ( ! $tmp_path)
                            {
                                mkdir($image_root.'/cache', 0755);
                            }
                            if ($local && $image_path)
                            {
                                $gen_image = Image::factory($image_path)->thumbnail($sizes[0], $sizes[1]);

                                if ($grayscale)
                                {
                                    $gen_image->grayscale();
                                }

                                $gen_image->save($tmp_file, 70);
                            }
                        }

                        $image = $tmp_dir.'/'.$tmp_name;

                        if (strpos($image, '://') === FALSE) {
                            // Add the base URL
                            $image = URL::site($image, 'http');
                        }

                        return ($html) ? html::image($image, array(
                            'width'  => $sizes[0],
                            'height' => $sizes[1],
                            'alt'    => ( ! empty($alt)) ? $alt : NULL,
                        ), 'http') : URL::site($image, 'http');
                    }
                }
            }
        }
    }

    public static function resize($file, $width, $height, $path = FALSE, $name = FALSE) {
        if ( ! empty($file)) {
            $home = Wordpress_Options::instance()->get_option('siteurl').'/';
            $file_path = realpath(str_replace($home, '', $file));

            if ( ! empty($file_path)) {

                $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

                if ( ! $name) {
                    $name = md5($file.'-'.$width.'-'.$height).'.'.$ext;
                } else {
                    $name = $name.'.'.$ext;
                }

                if ( ! $path) {
                    // Images path root
                    $root = Wordpress_Options::instance()->get_option('upload_path');
                    $tmp_dir  = $root.'/cache/';
                    $tmp_path = realpath($tmp_dir);
                    $dir  = $tmp_dir.'/'.substr($name, 0, 2).'/'.substr($name, 2, 4).'/';
                    $path = $tmp_path.'/'.substr($name, 0, 2).'/'.substr($name, 2, 4).'/';
                } else {
                    $tmp_path = realpath($path);
                    $dir  = $path.'/';
                    $path = $tmp_path.'/';
                }

                if ( ! is_file($path.'/'.$name)) {
                    if ( ! is_dir($path)) {
                        mkdir($path, 0755, TRUE);
                    }

                    Image::factory($file_path)
                        ->thumbnail($width, $height)
                        ->save($path.'/'.$name, 80);
                }

                return Text::reduce_slashes($dir.$name);
            }

            return $file;
        }
    }

}