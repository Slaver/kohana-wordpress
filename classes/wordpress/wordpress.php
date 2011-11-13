<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * WordPress posts' actions library for Kohana
 *
 * @package   WordPress
 * @version   0.1
 * @author    Viacheslav Radionov <radionov@gmail.com>
 * @copyright (c) 2011-2012 Viacheslav Radionov
 * @link      http://slaver.info/
 */
class Wordpress_Wordpress {

    // Instances
    protected static $_instance;

    /**
     * Singleton pattern
     *
     * @return Wordpress
     */
    public static function instance()
    {
        if ( ! isset(Wordpress::$_instance))
        {
            // Create a new session instance
            Wordpress::$_instance = new self();
        }

        return Wordpress::$_instance;
    }

    protected $model = FALSE;
    protected $page = 1;
    protected $year = FALSE;
    protected $month = FALSE;
    protected $day = FALSE;
    protected $search = FALSE;

    // Title or ID of single post
    public $category = array();
    public $title = FALSE;
    public $limit = 10;
    public $exclude = array();

    /**
     * Constructor
     *
     * @return Wordpress
     */
    public function __construct()
    {
        $this->model = new Model_Wordpress();

        $this->year = Request::current()->param('year');
        $this->month = Request::current()->param('month');
        $this->day = Request::current()->param('day');
        $this->title = Request::current()->param('title');

        $this->prefix = Request::current()->param('prefix', 'category');
        $this->category = Request::current()->param('category');
        $this->search = Request::current()->param('q');
        $this->page = Request::current()->param('page');
    }

    /**
     * Return list of posts
     *
     * @return array
     */
    public function get_posts()
    {
        $offset = ( ! empty($this->page)) ? ($this->page * $this->limit - $this->limit) : 0;

        // Single post
        if ($this->limit === 1 OR $this->title)
        {
            $data = $this->model->get_post($this->title);
        }
        // Posts archive by date
        elseif ( ! empty($this->year))
        {
            $data = $this->model->get_posts(array(
                'numberposts' => $this->limit,
                'offset' => $offset,
                'date' => array('y' => $this->year, 'm' => $this->month, 'd' => $this->day),
            ));
        }
        // List of posts
        else
        {
            $data = $this->model->get_posts(array(
                'taxonomy' => $this->category,
                'taxonomy_type' => $this->prefix,
                'numberposts' => $this->limit,
                'offset' => $offset,
                'exclude' => $this->exclude,
                'search' => $this->search,
            ));
        }

        $permalink_structure = $this->model->get_permalink_structure();

        if ( ! empty($data['posts']))
        {
            foreach ($data['posts'] as $id => $post)
            {
                // Link
                $data['posts'][$id]['link'] = $this->get_link($permalink_structure, $post);

                // Text of post
                $content = $post['post_content'];
                if (preg_match('/<!--more(.*?)?-->/', $content, $matches))
                {
                    $data['posts'][$id]['content'] = explode($matches[0], $content, 2);
                }
                else
                {
                    $data['posts'][$id]['content'] = array($content);
                }
            }
            return $data;
        }
    }

    /**
     * Get sticky posts
     *
     * @return array
     */
    public function get_sticky($number = 5)
    {
        $data = $this->model->get_posts(array('sticky' => $number));

        $permalink_structure = $this->model->get_permalink_structure();

        if ( ! empty($data['posts']))
        {
            foreach ($data['posts'] as $id => $post)
            {
                // Link
                $data['posts'][$id]['link'] = $this->get_link($permalink_structure, $post);

                // Text of post
                $content = $post['post_content'];
                if (preg_match('/<!--more(.*?)?-->/', $content, $matches))
                {
                    $data['posts'][$id]['content'] = explode($matches[0], $content, 2);
                }
                else
                {
                    $data['posts'][$id]['content'] = array($content);
                }
            }
            return $data;
        }
    }

    /**
     * Return one static page
     *
     * @return array
     */
    public function get_static()
    {
        return $this->model->get_post($this->title, 'page');
    }

    /**
     * Get random posts
     *
     * @return array
     */
    public function get_random_posts($limit = '10', $category = 'sidebar', $excluded_posts = array())
    {
        $data = $this->model->get_posts(0, 50, $category, array(), FALSE, array(), array('thumbs'), $excluded_posts, TRUE);
        $data['permalink_structure'] = $this->model->get_permalink_structure();
        $random = array_rand($data['posts'], 1);

        if ( ! empty($data['posts']))
        {
            $post = $data['posts'][$random];
            $url = $this->get_link($data['permalink_structure'], $post);
            $result[] = array(
                'id' => $post['ID'],
                'date' => strtotime($post['post_date']),
                'title' => $post['post_title'],
                'link' => $url,
                'thumb' => $post['thumb'],
                'meta' => $post['meta'],
            );
            return array('posts' => $result);
        }
    }

    /**
     * Get related posts
     *
     * @param  numeric $limit
     * @param  array $tags
     * @return array
     */
    public function get_related_posts($limit = 5, $tags = array(), $excluded_posts = array())
    {
        if ( ! empty($tags))
        {
            $data = $this->model->get_posts(0, $limit, $tags, array(), FALSE, FALSE, array('thumbs'), $excluded_posts);

            foreach ($data['posts'] as $post)
            {
                $url = $this->get_link($data['permalink_structure'], $post);

                $result[] = array(
                    'id' => $post['ID'],
                    'date' => strtotime($post['post_date']),
                    'title' => $post['post_title'],
                    'link' => $url,
                    'content' => $post['post_content'],
                    'thumb' => $post['thumb'],
                );
            }

            return array(
                'posts' => $result,
                'pages' => array(
                    'limit' => $limit,
                    'rows' => $data['rows'],
                    'current' => $this->page,
                ),
            );
        }
    }

    /**
     * Список постов по месяцам
     *
     * @return array
     */
    public function get_archives()
    {
        return $this->model->get_archives();
    }

    /**
     * Return link of post by permalink structure
     */
    private function get_link($permalink_structure, $post_data)
    {
        $str = $permalink_structure == '' ? '/%year%/%monthnum%/%day%/%postname%' : $permalink_structure;

        $date = strtotime($post_data['post_date']);
        $url = $str;
        $url = str_replace("%year%", date('Y', $date), $url);
        $url = str_replace("%monthnum%", date('m', $date), $url);
        $url = str_replace("%day%", date('d', $date), $url);
        $url = str_replace("%hour%", date('H', $date), $url);
        $url = str_replace("%minute%", date('i', $date), $url);
        $url = str_replace("%second%", date('s', $date), $url);
        $url = str_replace("%postname%", $post_data['post_name'], $url);
        $url = str_replace("%post_id%", $post_data['ID'], $url);
        $url = str_replace("%category%", $post_data['taxonomy']['category'][0]['slug'], $url);

        return $url;
    }

    /**
     * List of posts' comments
     *
     * @param  numeric $post_id
     * @param  numeric $limit
     * @return array
     */
    public function get_comments($post_id = NULL, $limit = 10)
    {
        return $this->model->get_comments($post_id, $limit);
    }

    /**
     * Get one comment
     *
     * @param  numeric $comment_id
     * @return array
     */
    public function get_comment($comment_id)
    {
        return $this->model->get_comment($comment_id);
    }

    /**
     * Change comment
     *
     * @param numeric $comment_id
     */
    public function update_comment($comment_id, $input)
    {
        return $this->model->update_comment($comment_id, $input);
    }

    /**
     * Delete comment
     *
     * @param numeric $comment_id
     */
    public function delete_comment($comment_id)
    {
        return $this->model->delete_comment($comment_id);
    }

    /**
     * Add new comment
     * 
     * @param  string $message
     * @param  array  $user
     * @return boolean
     */
    public function add_comment($message, $user, $post_id, $status = 1)
    {
        return $this->model->add_comment($message, $user, $post_id, $status);
    }

    /**
     * Get popular posts
     *
     * @param  string $limit
     * @param  array  $time_period
     * @return array
     */
    public function get_popular_posts($limit = 40, $time_period = array())
    {
        return $this->model->get_popular_posts($limit, $time_period);
    }

    /**
     * Get last comments
     *
     * @param  numeric $number
     * @return array 
     */
    public function get_last_comments($number = 5)
    {
        $comments = $this->model->get_comments(FALSE, $number);
        if ( ! empty($comments))
        {
            $str = $this->model->get_permalink_structure();
            foreach ($comments as $k => $comment)
            {
                $data[$k] = $comment;
                $data[$k]['link'] = $this->get_link($str, $comment) . '#comment_' . $comment['comment_ID'];
            }
            return $data;
        }
    }

    /**
     * List of options
     *
     * @return array
     */
    public function get_options()
    {
        $options = $this->model->get_options();
        foreach ($options as $option)
        {
            $result[$option['option_name']] = $option['option_value'];
        }
        return $result;
    }

}
