<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * WordPress posts' actions library for Kohana
 *
 * @package   WordPress
 * @author    Viacheslav Radionov <radionov@gmail.com>
 * @copyright (c) 2011-2012 Viacheslav Radionov
 * @link      http://slaver.info/
 */
class Wordpress_Posts {

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

    /**
     * Models
     */
    protected $posts = FALSE;
    protected $comments = FALSE;

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
        $this->posts = new Model_Posts();
        $this->comments = new Model_Comments();

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
            $data = $this->posts->get_post($this->title);
        }
        // Posts archive by date
        elseif ( ! empty($this->year))
        {
            $data = $this->posts->get_posts(array(
                'numberposts' => $this->limit,
                'offset'      => $offset,
                'date'        => array('y' => $this->year, 'm' => $this->month, 'd' => $this->day),
            ));
        }
        // List of posts
        else
        {
            $data = $this->posts->get_posts(array(
                'taxonomy'      => $this->category,
                'taxonomy_type' => $this->prefix,
                'numberposts'   => $this->limit,
                'offset'        => $offset,
                'exclude'       => $this->exclude,
                'search'        => $this->search,
            ));
        }

        if ( ! empty($data['posts']))
        {
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
        $data = $this->posts->get_posts(array('sticky' => $number, 'exclude' => $this->exclude));

        if ( ! empty($data['posts']))
        {
            return $data;
        }
    }

    /**
     * Get popular posts
     *
     * @return array
     */
    public function get_popular($number = 5, $period = '-1 month')
    {
        $time_period = array('from' => strtotime($period));
        $data = $this->posts->get_popular_posts($number, $time_period);

        if ( ! empty($data))
        {
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
        return $this->posts->get_post($this->title, 'page');
    }

    /**
     * Список постов по месяцам
     *
     * @return array
     */
    public function get_archives()
    {
        return $this->posts->get_archives();
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
        return $this->comments->get_comments($post_id, $limit);
    }

    /**
     * Get one comment
     *
     * @param  numeric $comment_id
     * @return array
     */
    public function get_comment($comment_id)
    {
        return $this->comments->get_comment($comment_id);
    }

    /**
     * Change comment
     *
     * @param numeric $comment_id
     */
    public function update_comment($comment_id, $input)
    {
        return $this->comments->update_comment($comment_id, $input);
    }

    /**
     * Delete comment
     *
     * @param numeric $comment_id
     */
    public function delete_comment($comment_id)
    {
        return $this->comments->delete_comment($comment_id);
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
        return $this->comments->add_comment($message, $user, $post_id, $status);
    }

    /**
     * Get last comments
     *
     * @param  numeric $number
     * @return array 
     */
    public function get_last_comments($number = 5)
    {
        $comments = $this->comments->get_comments(FALSE, $number);
        if ( ! empty($comments))
        {
            $str = $this->posts->get_permalink_structure();
            foreach ($comments as $k => $comment)
            {
                $data[$k] = $comment;
                $data[$k]['link'] = $this->get_link($str, $comment) . '#comment_' . $comment['comment_ID'];
            }
            return $data;
        }
    }

}