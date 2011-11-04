<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * This model can be used for getting posts from WordPress database
 *
 * @package   WordPress
 * @version   0.1
 * @author    Viacheslav Radionov <radionov@gmail.com>
 * @copyright (c) 2011-2012 Viacheslav Radionov
 * @link      http://slaver.info/
 */
class Model_Wordpress extends Model_Database {

    protected $config = array();

    public function __construct($config = array())
	{
        parent::__construct();

        $this->config = $config;
	}

	/**
	 * Takes a post ID or post name (slug) and returns the database record for
     * that post, includes meta- and taxonomy information for that post.
     * Analogue of http://codex.wordpress.org/Function_Reference/get_post
	 *
     * @param  mixed  $id   (ID|post_name)
     * @param  string $type (post|page|attachment)
	 * @return array
	 */
	public function get_post($id = NULL, $type = 'post')
	{
		if ($id)
		{
            $query = DB::select('posts.*', 'users.display_name', 'users.user_nicename')
                ->from('posts')
                ->join('users', 'LEFT')->on('posts.post_author', '=', 'users.ID')
                ->and_where('post_type', '=', $type)
                ->and_where('post_status', '=', 'publish')
                ->group_by('posts.ID')
                ->order_by('posts.post_date', 'DESC')
                ->limit(1);

            if (is_numeric($id))
            {
                $query->and_where('posts.ID', '=', $id);
            }
            else
            {
                $query->and_where('posts.post_name', '=', urlencode($id));
            }

            $posts = $query->execute()->as_array('ID');

		    if ( ! empty($posts))
		    {
                // Retrieves all custom fields, taxonomy and thumbnails of a particular post or page
			    $taxonomy = $this->get_post_taxonomy($posts);
			    foreach ($taxonomy as $id => $values)
			    {
				    $posts[$id]['taxonomy'] = $values;
			    }

			    $meta = $this->get_post_meta( $posts );
			    foreach ($meta as $id => $values)
			    {
				    $posts[$id]['meta'] = $values;
			    }

                foreach ($posts as $id => $values)
                {
                    if ( ! empty($values['meta']['_thumbnail_id']))
                    {
                        $thumb_posts[$id] = $values['meta']['_thumbnail_id'];
                    }
                }
                if ( ! empty($thumb_posts))
                {
                    $thumbs = $this->get_post_thumbs($thumb_posts);
                    foreach ($thumbs as $values)
                    {
                        $posts[$id]['thumb'] = unserialize($values);
                    }
                }

                return array(
				    'posts'	=> $posts,
				    'total'	=> 1,
			    );
		    }
        }
	}

	/**
     * This function retrieves a list of latest posts or posts matching criteria.
     * Also retrieves meta, taxonomy and thumbnails of posts
     * Analogue of http://codex.wordpress.org/Function_Reference/get_posts
     *
     * @param  array   $args
     * @return array
     */
	public function get_posts($args = array())
	{
        /**
         * All default WordPress arguments and two custom:
         * 'search' - search request (from GET-query)
         * 'select' - array of custom information. If you do not need it,
         *    you can set it empty for reduce the number of database requests
         */
        $default = array(
            'numberposts'     => 10,
            'offset'          => 0,
            'category'        => NULL,
            'orderby'         => 'post_date',
            'order'           => 'DESC',
            'include'         => NULL,
            'exclude'         => NULL,
            'meta_key'        => NULL,
            'meta_value'      => NULL,
            'post_type'       => 'post',
            'post_mime_type'  => NULL,
            'post_parent'     => NULL,
            'post_status'     => 'publish',
            'date'            => NULL,
            'search'          => NULL,
            'select'          => array('taxonomy', 'meta', 'thumbs'),
        );
        extract(Arr::overwrite($default, $args));

        $query = DB::select('posts.*', 'users.display_name', 'users.user_nicename')
            ->from('posts')
            ->join('users', 'LEFT')
                ->on('posts.post_author', '=', 'users.ID')
            ->and_where('post_type', '=', $post_type)
            ->and_where('post_status', '=', 'publish')
            ->order_by('posts.'.$orderby, $order);

        if ( ! empty($include))
        {
            $query->and_where('posts.ID', 'IN', array_values($include));
        }

        if ( ! empty($exclude))
        {
            $query->and_where('posts.ID', 'NOT IN', array_values($exclude));
        }

        if ( ! empty($post_mime_type))
        {
            $query->and_where('posts.post_mime_type', '=', $post_mime_type);
        }

        if ( ! empty($post_parent))
        {
            $query->and_where('posts.post_parent', '=', $post_parent);
        }

        if ( ! empty($meta_key))
        {
            $query
                ->join('postmeta', 'LEFT')
                    ->on('postmeta.post_id', '=', 'posts.ID')
                ->and_where('postmeta.meta_key', '=', $meta_key);

            if ( ! empty($meta_value))
            {
                $query->and_where('postmeta.meta_value', '=', $meta_value);
            }
            else
            {
                $query->and_where('postmeta.meta_value', '!=', '');
            }
        }

		if ( ! empty($search))
		{
            $search = "%".$search."%";
            $query
                ->and_where('posts.post_title', 'LIKE', $search)
                ->and_where('posts.post_content', 'LIKE', $search);
		}

        if ( ! empty($date))
		{
			if ( ! empty($date['y']))
			{
                $query->and_where(DB::expr('YEAR(wp_posts.post_date)'), '=', $date['y']);

				if ( ! empty($date['m']))
				{
                    $query->and_where(DB::expr('MONTH(wp_posts.post_date)'), '=', $date['m']);
				}
				if ( ! empty($date['d']))
				{
                    $query->and_where(DB::expr('DAYOFMONTH(wp_posts.post_date)'), '=', $date['d']);
				}
			}
		}

        if ( ! empty($category))
		{
			$category = (array)$category;
            foreach ($category as $cat)
            {
                if ( ! is_numeric($cat))
                {
                    $category_array[] = DB::select()
                        ->from('terms')
                        ->where('slug', '=', urldecode($cat))
                        ->execute()->as_array('slug', 'term_id');
                }
                else
                {
                    $category_array[] = $cat;
                }
            }

            $query
                ->join('term_relationships', 'INNER')
                ->on('posts.ID', '=', 'term_relationships.object_id')
                ->join('term_taxonomy', 'INNER')
                ->on('term_relationships.term_taxonomy_id', '=', 'term_taxonomy.term_taxonomy_id')
                ->where_open()
                    ->where('term_taxonomy.taxonomy', '=', 'category')
                    ->or_where('term_taxonomy.taxonomy', '=', 'post_tag')
                ->where_close()
                ->and_where('term_taxonomy.term_id', 'IN', $category_array);
		}

        $posts = $query
            ->limit($numberposts)
            ->offset($offset)
            ->execute()->as_array('ID');

		if ( ! empty($posts))
		{
            // Count all post by crteria for pagination
            $total_rows = (int)$query->select(DB::expr('COUNT(*) as total_rows'))->limit(NULL)->offset(NULL)->execute()->get('total_rows');

            // Retrieves all custom fields, taxonomy and thumbnails of a particular post or page
            if (in_array('taxonomy', $select))
            {
                $taxonomy = $this->get_post_taxonomy($posts);
                foreach ($taxonomy as $id => $values)
                {
                    $posts[$id]['taxonomy'] = $values;
                }
            }

            if (in_array('meta', $select) OR in_array('thumbs', $select))
            {
                $meta = $this->get_post_meta($posts);
                foreach ($meta as $id => $values)
                {
                    $posts[$id]['meta'] = $values;
                }

                foreach ($posts as $id => $values)
                {
                    if ( ! empty($values['meta']['_thumbnail_id']))
                    {
                        $thumb_posts[$id] = $values['meta']['_thumbnail_id'];
                    }
                }
                if ( ! empty($thumb_posts))
                {
                    $thumbs = $this->get_post_thumbs($thumb_posts);
                    foreach ($thumbs as $id => $values)
                    {
                        $posts[$id]['thumb'] = unserialize($values);
                    }
                }
            }

			return array(
				'posts'	=> $posts,
				'total'	=> $total_rows,
			);
		}
	}

    /**
	 * Retrieve the terms of the taxonomy that are attached to the post.
     * Analogue of http://codex.wordpress.org/Function_Reference/get_the_terms
	 *
	 * @param  array $posts
	 * @return array
	 */
	public function get_post_taxonomy($posts = array())
	{
		$post_id = array_keys($posts);

        $taxonomy = DB::select('t.*', 'tt.*', 'tr.object_id')
            ->from(array('terms', 't'))
            ->join(array('term_taxonomy', 'tt'), 'INNER')
                ->on('tt.term_id', '=', 't.term_id')
            ->join(array('term_relationships', 'tr'), 'INNER')
                ->on('tr.term_taxonomy_id', '=', 'tt.term_taxonomy_id')
            ->where('tr.object_id', 'IN', $post_id)
            ->execute()->as_array();

        foreach ($taxonomy as $id=>$value)
        {
            if ($value['taxonomy'] == 'category')
            {
                $return[$value['object_id']]['category'][] = array(
                    'name'  => $value['name'],
                    'slug'  => $value['slug'],
                );
            }
            elseif ($value['taxonomy'] == 'post_tag')
            {
                $return[$value['object_id']]['tags'][] = array(
                    'name'  => $value['name'],
                    'slug'  => $value['slug'],
                );
            }
        }

		return $return;
	}

	/**
	 * Returns a multidimensional array with all custom fields of a particular post or page
     * Analogue of http://codex.wordpress.org/Function_Reference/get_post_custom
	 *
	 * @param  array $posts
	 * @return array
	 */
	public function get_post_meta($posts = array())
	{
		$post_id = array_keys($posts);

        $meta = DB::select('post_id', 'meta_key', 'meta_value')
            ->from('postmeta')
            ->where('post_id', 'IN', $post_id)
            ->order_by('post_id', 'ASC')
            ->execute()->as_array();

        foreach ($meta as $id => $value)
        {
            $return[$value['post_id']][$value['meta_key']] = $value['meta_value'];
        }

		return $return;
	}

	/**
	 * Gets Post Thumbnail as set in post's or page's edit screen
	 *
	 * @param  array  $posts
	 * @return array
	 */
	public function get_post_thumbs($posts = array())
	{
        $thumbs = DB::select()
            ->from(array('posts', 'p'))
            ->join(array('postmeta', 'm'), 'LEFT')
                ->on('p.ID', '=', 'm.post_id')
            ->where('p.ID', 'IN', $posts)
            ->and_where('meta_key', '=', '_wp_attachment_metadata')
            ->execute()->as_array();

        foreach ($meta as $id => $value)
        {
            $files[$value['ID']] = $value['meta_value'];
        }
        foreach ($posts as $id => $post)
        {
            $return[$id] = $files[$post];
        }

		return $return;
	}

    /**
     * Retrieve a list of comments
     *
     * @param  numeric $post_id
     * @return array
     */
    public function get_comments($post_id = NULL, $limit = NULL)
    {
        $query = DB::select()
            ->from('comments')
            ->and_where('comment_approved', '=', 1)
            ->order_by('comment_ID', 'DESC');

        if ($post_id === NULL)
        {
            $query->join('posts', 'LEFT')->on('comments.comment_post_ID', '=', 'posts.ID');
        }
        else
        {
            $query->and_where('comment_post_ID', '=', $post_id);
        }

        return $query->limit($limit)->execute()->as_array();
    }

    /**
     * Retrieve one comments
     *
     * @param numeric $comment_id
     * @return array
     */
    public function get_comment($comment_id = FALSE)
    {        
        if ($comment_id)
        {
            return DB::select()
                ->from('comments')
                ->and_where('comment_ID', '=', $comment_id)
                ->limit(1)
                ->execute()->as_array();
        }
    }

	/**
	 * Display archive links by month
	 *
	 * @return array
	 */
	public function get_archives()
	{
        return DB::select(array(DB::expr('YEAR(post_date)'), 'year'), array(DB::expr('MONTH(post_date)'), 'month'), array(DB::expr('COUNT(ID)'), 'posts'))
            ->from('posts')
            ->and_where('post_type', '=', 'post')
            ->and_where('post_status', '=', 'publish')
            ->group_by(DB::expr('YEAR(post_date)'))
            ->group_by(DB::expr('MONTH(post_date)'))
            ->order_by('post_date', 'DESC')
            ->execute()->as_array();
	}

	/**
	 * Display popular posts for any period (by comments)
     * 
     * @param  string $limit
     * @param  array  $time_period
     * @return array
	 */
	public function get_popular_posts($limit = 10, $time_period = array())
	{
        $query = DB::select()
            ->from('posts')
            ->and_where('post_type', '=', 'post')
            ->and_where('post_status', '=', 'publish')
            ->order_by('comment_count', 'DESC');

        $time_from = isset($time_period['from']) ? date('Y-m-d H:i:s', $time_period['from']) : FALSE;
		$time_to = isset($time_period['to']) ? date('Y-m-d H:i:s', $time_period['to']) : FALSE;

		if($time_from && $time_to)
		{
            $query->and_where('post_date', 'BETWEEN', array($time_from, $time_to));
		}
		else if($time_from)
		{
            $query->and_where('post_date', '>', $time_from);
		}
		else if($time_to)
		{
            $query->and_where('post_date', '<', $time_to);
		}

        return $query->limit($limit)->execute()->as_array('ID');
	}

    /**
     * Add new comment
     *
     * @param  string $message
     * @param  array  $user
     * @param  string $post_id
     * @param  string $status
     * @return boolean
     */
    public function add_comment($message, $user, $post_id, $status = 1)
    {
        if ( ! empty($user) && ! empty($message) && $post_id)
        {
            $comment = array(
                'comment_post_ID'   => $post_id,
                'comment_author'    => $user['display_name'],
                'comment_author_email' => $user['user_email'],
                'comment_date'      => date('Y-m-d H:i:s'),
                'comment_date_gmt'  => gmdate('Y-m-d H:i:s'),
                'comment_content'   => $message,
                'comment_approved'  => $status,
                'comment_agent'     => Arr::get($_SERVER, 'HTTP_USER_AGENT'),
                'comment_author_IP' => Arr::get($_SERVER, 'SERVER_ADDR'),
            );

            if (DB::insert('comments', array_keys($comment))->values(array_values($comment))->execute())
            {
                return DB::update('posts')
                    ->set(array('comment_count' => DB::expr('comment_count + 1')))
                    ->where('ID', '=', $post_id)
                    ->execute();
            }
        }
    }

    /**
     * Get one comment
     *
     * @param  numeric $comment_id
     * @param  array   $input
     * @return boolean
     */
    public function update_comment($comment_id = FALSE, $input)
    {
        if ($comment_id !== FALSE)
        {
            return DB::update('comments')
                ->set(array('comment_content' => $input['message']))
                ->where('comment_ID', '=', $comment_id)
                ->execute();
        }
    }

    /**
     * Delete comment
     *
     * @param  numeric $comment_id
     * @return boolean
     */
    public function delete_comment($comment_id = FALSE)
    {
        if ($comment_id !== FALSE)
        {
            $post_id = DB::select('comment_post_ID')
                ->from('comments')
                ->and_where('comment_ID', '=', $comment_id)
                ->limit(1)
                ->execute()
                ->get('comment_post_ID');

            DB::delete('comments')->and_where('comment_ID', '=', $comment_id);
            DB::delete('commentmeta')->and_where('comment_ID', '=', $comment_id);

            return DB::update('posts')
                ->set(array('comment_count' => DB::expr('comment_count - 1')))
                ->where('ID', '=', $post_id)
                ->execute();
        }
    }

    /**
     * Get all WordPress config options
     *
     * @return boolean
     */
    public function get_options()
    {
        return DB::select()
            ->from('options')
            ->where('autoload', '=', 'yes')
            ->execute()->as_array('option_name', 'option_value');
    }

	/**
	 * Get WordPress permalink structure
	 *
	 * @return string
	 */
	public function get_permalink_structure()
	{
        $options = $this->get_options();

        return Arr::get($options, 'permalink_structure');
	}
}