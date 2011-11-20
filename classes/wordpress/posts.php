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
            Wordpress::$_instance = new self();
        }

        return Wordpress::$_instance;
    }

    protected $posts = FALSE;
    protected $comments = FALSE;

    protected $year = FALSE;
    protected $month = FALSE;
    protected $day = FALSE;

    public $id = FALSE;
    public $numberposts = 10;
    public $exclude = array();

    public $taxonomy_type = NULL;
    public $taxonomy = array();

    protected $search = FALSE;
    protected $page = 1;

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
        $this->id = Request::current()->param('id');

        $this->taxonomy_type = Request::current()->param('taxonomy_type');
        $this->taxonomy = Request::current()->param('taxonomy');
        $this->search = Request::current()->param('q');
        $this->page = Request::current()->param('page');
    }

    /**
     * Return list of posts
     *
     * @return array
     */
    public function get_posts($args = array())
    {
        $offset = ( ! empty($this->page)) ? ($this->page * $this->numberposts - $this->numberposts) : 0;

        $default = array(
            'numberposts'   => $this->numberposts,
            'offset'        => $offset,
            'date'          => array('y' => $this->year, 'm' => $this->month, 'd' => $this->day),
            'taxonomy'      => $this->taxonomy,
            'taxonomy_type' => $this->taxonomy_type,
            'exclude'       => $this->exclude,
            'search'        => $this->search,
            'id'            => $this->id,
            'page'          => $this->page,
            'post_type'     => 'post'
        );
        $args = Arr::overwrite($default, $args);

        return $this->posts->get_posts($args);
    }

    /**
     * Return one post by ID
     *
     * @return array
     */
    public function get_post($id)
    {
        return $this->posts->get_post($this->id);
    }

    /**
     * Return one static page
     *
     * @return array
     */
    public function get_page()
    {
        return $this->posts->get_post($this->id, 'page');
    }

    /**
     * Get sticky posts
     *
     * @return array
     */
    public function get_sticky($number = 5)
    {
        return $this->posts->get_posts(array('sticky' => $number, 'exclude' => $this->exclude));
    }

    /**
     * Get popular posts
     *
     * @return array
     */
    public function get_popular($number = 5, $period = '-1 month')
    {
        $time_period = array('from' => strtotime($period));

        return $this->posts->get_popular_posts($number, $time_period);
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
    public function add_comment($input, $user)
    {
        return $this->comments->add_comment($input, $user);
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