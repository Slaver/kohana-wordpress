<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * This model can be used for getting posts from WordPress database
 *
 * @package   WordPress
 * @author    Viacheslav Radionov <radionov@gmail.com>
 * @copyright (c) 2011-2012 Viacheslav Radionov
 * @link      http://slaver.info/
 */
class Model_Posts extends Model_Database {

    protected $config = array();

    public function __construct()
    {
        parent::__construct();

        $this->taxonomy = new Model_Taxonomy();
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
    public function get_post($id = NULL, $type = 'post', $status = 'publish')
    {
        if ($id)
        {
            $query = DB::select('posts.*', 'users.display_name', 'users.user_nicename')
                ->from('posts')
                ->join('users', 'LEFT')
                    ->on('posts.post_author', '=', 'users.ID')
                ->and_where('post_type', '=', $type)
                ->and_where('post_status', '=', $status)
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
                $taxonomy = $this->taxonomy->get_post_taxonomy($posts);
                foreach ($taxonomy as $id => $values)
                {
                    $posts[$id]['taxonomy'] = $values;
                }

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
                    foreach ($thumbs as $values)
                    {
                        $posts[$id]['thumb'] = $values;
                    }
                }

                $posts = $this->_convert_more_text($posts);

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
    public function get_posts($args = array(), $pagination = TRUE)
    {
        /**
         * All default WordPress arguments and two custom:
         * 'search' - search request (from GET-query)
         * 'select' - array of custom information. If you do not need it,
         *    you can set it empty for reduce the number of database requests
         * 'sticky' - number of sticky posts
         * 'taxonomy_type' - type of taxonomy
         */
        $default = array(
            'numberposts'     => 10,
            'offset'          => 0,
            'taxonomy'        => NULL,
            'taxonomy_type'   => 'category',
            'orderby'         => 'post_date',
            'order'           => 'DESC',
            'include'         => NULL,
            'exclude'         => NULL,
            'meta_key'        => NULL,
            'meta_value'      => NULL,
            'meta_compare'    => '=',
            'post_type'       => 'post',
            'post_mime_type'  => NULL,
            'post_parent'     => NULL,
            'post_status'     => 'publish',
            'date'            => NULL,
            'search'          => NULL,
            'select'          => array('taxonomy', 'meta', 'thumbs'),
            'sticky'          => 0,
        );
        extract(Arr::overwrite($default, $args));

        $query = DB::select('posts.*', 'users.display_name', 'users.user_nicename')
            ->from('posts')
            ->join('users', 'LEFT')
                ->on('posts.post_author', '=', 'users.ID')
            ->and_where('post_type', '=', $post_type)
            ->and_where('post_status', '=', 'publish');

        if ( ! empty($sticky))
        {
            $sticky = (int)$sticky;
            $sticky_array = unserialize(Wordpress_Options::instance()->get_option('sticky_posts'));
            arsort($sticky_array);

            $sticky_posts = array_slice($sticky_array, 0, $sticky);
            $sticky_order = DB::expr('FIELD('.$this->_db->table_prefix().'posts.ID, '.implode(',', $sticky_posts).')');
            $query
                ->and_where('posts.ID', 'IN', $sticky_posts)
                ->order_by($sticky_order);
        }

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
                $query->and_where('postmeta.meta_value', $meta_compare, $meta_value);
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
                $query->and_where(DB::expr('YEAR('.$this->_db->table_prefix().'posts.post_date)'), '=', $date['y']);

                if ( ! empty($date['m']))
                {
                    $query->and_where(DB::expr('MONTH('.$this->_db->table_prefix().'posts.post_date)'), '=', $date['m']);
                }
                if ( ! empty($date['d']))
                {
                    $query->and_where(DB::expr('DAYOFMONTH('.$this->_db->table_prefix().'posts.post_date)'), '=', $date['d']);
                }
            }
        }

        if ( ! empty($taxonomy))
        {
            if ($taxonomy_type === 'tag')
            {
                $taxonomy_type = 'post_tag';
            }

            $taxonomy = (array)$taxonomy;
            foreach ($taxonomy as $tax)
            {
                $taxonomy_array[] = Wordpress_Taxonomy::instance($taxonomy_type)->get_taxonomy_id($tax);

                if (Wordpress_Taxonomy::instance($taxonomy_type)->has_childs($tax))
                {
                    $taxonomy_array += Wordpress_Taxonomy::instance($taxonomy_type)->get_childs($tax);
                }
            }

            $query
                ->join('term_relationships', 'INNER')
                    ->on('posts.ID', '=', 'term_relationships.object_id')
                ->join('term_taxonomy', 'INNER')
                    ->on('term_relationships.term_taxonomy_id', '=', 'term_taxonomy.term_taxonomy_id')
                        ->where_open()
                            ->where('term_taxonomy.taxonomy', '=', $taxonomy_type)
                        ->where_close()
                ->and_where('term_taxonomy.term_id', 'IN', $taxonomy_array);
        }

        $posts = $query
            ->order_by('posts.'.$orderby, $order)
            ->limit($numberposts)
            ->offset($offset)
            ->execute()->as_array('ID');

        if ( ! empty($posts))
        {
            // Count all post by criteria for pagination
            $total_rows = count($posts);
            if ($pagination)
            {
                $total_rows = (int)$query->select(DB::expr('COUNT(*) as total_rows'))->limit(NULL)->offset(NULL)->execute()->get('total_rows');
            }

            // Retrieves all custom fields, taxonomy and thumbnails of a particular post or page
            if (in_array('taxonomy', $select))
            {
                $taxonomy = $this->taxonomy->get_post_taxonomy($posts);
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
                        $posts[$id]['thumb'] = $values;
                    }
                }
            }

            $posts = $this->_convert_more_text($posts);

            return array(
                'posts'	=> $posts,
                'total'	=> $total_rows,
            );
        }
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

        if ($time_from && $time_to)
        {
            $query->and_where('post_date', 'BETWEEN', array($time_from, $time_to));
        }
        else if ($time_from)
        {
            $query->and_where('post_date', '>', $time_from);
        }
        else if ($time_to)
        {
            $query->and_where('post_date', '<', $time_to);
        }

        $posts = $query->limit($limit)->execute()->as_array('ID');

        // Retrieves all custom fields, taxonomy and thumbnails of a particular post or page
        $taxonomy = $this->taxonomy->get_post_taxonomy($posts);
        foreach ($taxonomy as $id => $values)
        {
            $posts[$id]['taxonomy'] = $values;
        }

        $posts = $this->_convert_more_text($posts);

        return $posts;
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
        $return = array();
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

        foreach ($thumbs as $id => $value)
        {
            $files[$value['ID']]['attach'] = @unserialize($value['meta_value']);
            $files[$value['ID']]['content'] = array(
                'post_content' => $value['post_content'],
                'post_title' => $value['post_title'],
            );
        }
        foreach ($posts as $id => $post)
        {
            $return[$id] = $files[$post];
        }

        return $return;
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
     * Get WordPress permalink structure
     *
     * @return string
     */
    public function _get_permalink_structure()
    {
        return Wordpress_Options::instance()->get_option('permalink_structure', '/%category%/%post_id%');
    }

    /**
     * Retrieve full permalink for current post or post ID.
     *
     * @param  string  $permalink_structure
     * @param  array   $post_data
     * @return string
     */
    public function _get_permalink($post_data)
    {
        $permalink_structure = $this->_get_permalink_structure();

        $date = strtotime($post_data['post_date']);
        $url = $permalink_structure;
        $url = str_replace("%year%", date('Y', $date), $url);
        $url = str_replace("%monthnum%", date('m', $date), $url);
        $url = str_replace("%day%", date('d', $date), $url);
        $url = str_replace("%hour%", date('H', $date), $url);
        $url = str_replace("%minute%", date('i', $date), $url);
        $url = str_replace("%second%", date('s', $date), $url);
        $url = str_replace("%postname%", $post_data['post_name'], $url);
        $url = str_replace("%post_id%", $post_data['ID'], $url);

        if ( ! empty($post_data['post_type']) && ! in_array($post_data['post_type'], array('post', 'page')))
        {
            $url = str_replace("%category%", $post_data['post_type'], $url);
        }
        else if ( ! empty($post_data['taxonomy']['category'][0]['slug']))
        {
            if (Wordpress_Taxonomy::instance('category')->has_parent($post_data['taxonomy']['category'][0]['id']))
            {
                $parent_category = Wordpress_Taxonomy::instance('category')->get_parent($post_data['taxonomy']['category'][0]['id']);
                $url = str_replace("%category%", $parent_category['data'][1].'/'.$post_data['taxonomy']['category'][0]['slug'], $url);
            }
            else
            {
                $url = str_replace("%category%", $post_data['taxonomy']['category'][0]['slug'], $url);
            }
        }

        return $url;
    }

    /**
     * Get extended entry info (<!--more--> and links).
     *
     * There should not be any space after the second dash and before the word
     * 'more'. There can be text or space(s) after the word 'more', but won't be
     * referenced.
     *
     * The returned array has 'main' and 'extended' keys. Main has the text before
     * the <code><!--more--></code>. The 'extended' key has the content after the
     * <code><!--more--></code> comment.
     *
     * @param  string $posts Post content.
     * @return array Post before ('main') and after ('extended').
     */
    public function _convert_more_text($posts)
    {
        $single = (count($posts) == 1);
        foreach ($posts as $id => $post)
        {
            // Create link
            $posts[$id]['link'] = $this->_get_permalink($post);

            // Text of post
            $content = $post['post_content'];

            if (preg_match('/<!--more(.*?)?-->/', $content, $matches))
            {
                $parts = explode($matches[0], $content, 2);
                $parts = Arr::map('UTF8::trim', $parts);
                if ( ! $single)
                {
                    $parts[0] = Wordpress_Format::prepare_text($parts[0]);
                }
                $posts[$id]['content'] = $parts;
            }
            else
            {
                $posts[$id]['content'] = array($content);
            }
        }
        return $posts;
    }
}
