<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * This model can be used for manage comments from WordPress database
 *
 * @package   WordPress
 * @author    Viacheslav Radionov <radionov@gmail.com>
 * @copyright (c) 2011-2012 Viacheslav Radionov
 * @link      http://slaver.info/
 */
class Model_Comments extends Model_Database {

    /**
     * Retrieve a list of comments
     *
     * @param  numeric $post_id
     * @return array
     */
    public function get_comments($post_id = NULL, $limit = NULL, $order = 'ASC')
    {
        $query = DB::select()
            ->from('comments')
            ->and_where('comment_approved', 'IN', array('0', '1'))
            ->order_by('comment_ID', $order);

        if ($post_id === NULL)
        {
            $query->join('posts', 'LEFT')->on('comments.comment_post_ID', '=', 'posts.ID');
        }
        else
        {
            $query->and_where('comment_post_ID', '=', $post_id);
        }

        $comments = $query->limit($limit)->execute()->as_array('comment_ID');
        $posters = array_unique(Arr::pluck($comments, 'user_id'));

        if (in_array('0', $posters))
        {
            $posters = array_diff($posters, array('0'));
        }

        if ($posters)
        {
            $users = DB::select()->from('users')
                ->where('ID', 'IN', $posters)
                ->execute()->as_array('ID');

            $usermeta = DB::select()->from('usermeta')
                ->where('user_id', 'IN', $posters)
                ->execute()->as_array();
            foreach ($usermeta as $meta)
            {
                $users[$meta['user_id']][$meta['meta_key']] = $meta['meta_value'];
            }

            foreach ($comments as $comment)
            {
                $comments[$comment['comment_ID']]['user'] = Arr::get($users, $comment['user_id']);
            }
        }

        return $comments;
    }

    /**
     * Retrieve one comments
     *
     * @param numeric $comment_id
     * @return array
     */
    public function get_comment($comment_id = FALSE)
    {
        if ($comment_id) {
            $comment = DB::select()
                ->from('comments')
                ->and_where('comment_ID', '=', $comment_id)
                ->limit(1)
                ->execute()->current();

            if ($comment['user_id'] > 0) {
                $comment['user'] = DB::select()->from('users')
                    ->where('ID', '=', $comment['user_id'])
                    ->execute()->as_array('ID');
                $usermeta = DB::select()->from('usermeta')
                    ->where('user_id', '=', $comment['user_id'])
                    ->execute()->as_array();
                foreach ($usermeta as $meta) {
                    $comment['user'][$meta['meta_key']] = $meta['meta_value'];
                }
            }

            return $comment;
        }
    }

    /**
     * Add new comment
     *
     * @param   string  $message
     * @param   array   $user
     * @return  boolean
     */
    public function add_comment($input, $user)
    {
        if ( ! empty($user) && ! empty($input))
        {
            $post_id = $input['comment_post_id'];

            $comment = array(
                'comment_post_ID'   => $post_id,
                'comment_author'    => Arr::get($user, 'display_name'),
                'comment_author_email' => Arr::get($user, 'user_email'),
                'comment_author_url'=> Arr::get($input, 'comment_author_url', ''),
                'user_id'           => Arr::get($user, 'ID', 0),
                'comment_date'      => date('Y-m-d H:i:s'),
                'comment_date_gmt'  => gmdate('Y-m-d H:i:s'),
                'comment_content'   => $input['comment'],
                'comment_parent'    => Arr::get($input, 'comment_parent', 0),
                'comment_approved'  => Arr::get($input, 'comment_approved', 0),
                'comment_agent'     => Arr::get($_SERVER, 'HTTP_USER_AGENT'),
                'comment_author_IP' => Request::$client_ip,
            );

            list($comment_id, ) = DB::insert('comments', array_keys($comment))->values(array_values($comment))->execute();

            $referer = NULL;
            if ( ! empty($_COOKIE['__utmz']))
            {
                $refer = explode('utmcsr=', $_COOKIE['__utmz']);
                $full  = explode('utmcct=', $_COOKIE['__utmz']);
                if (count($refer) > 1) $refer = explode('|', $refer[1]);

                if ( ! empty($full[1]) && $full[1] !== '/')
                {
                    $referer = addslashes($refer[0].$full[1]);
                }
                else
                {
                    $referer = addslashes($refer[0]);
                }
            }
            $comment_meta = array(
                'user_ip'      => Request::$client_ip,
                'user_host'    => @gethostbyaddr(Request::$client_ip),
                'user_referer' => $referer,
                'user_browser' => Arr::get($_SERVER, 'HTTP_USER_AGENT'),
            );
            DB::insert('commentmeta', array('comment_id', 'meta_key', 'meta_value'))->values(array($comment_id, 'user_info', serialize($comment_meta)))->execute();

            if ($comment_id && ! empty($input['comment_approved']) && $input['comment_approved'] !== 'spam')
            {
                DB::update('posts')
                    ->set(array('comment_count' => DB::expr('comment_count + 1')))
                    ->where('ID', '=', $post_id)
                    ->execute();
            }
            return $comment_id;
        }
    }

    /**
     * Update one comment
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
                ->set(array('comment_content' => $input['comment_content']))
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

            DB::update('comments')
                ->set(array('comment_approved' => 'trash'))
                ->where('comment_ID', '=', $comment_id)
                ->execute();

            return DB::update('posts')
                ->set(array('comment_count' => DB::expr('comment_count - 1')))
                ->where('ID', '=', $post_id)
                ->execute();
        }
    }

    /**
     * Check first comment
     *
     * @param  numeric $comment_id
     * @return boolean
     */
    public function check_comment($user)
    {
        if ( ! empty($user))
        {
            return DB::select()
                ->from('comments')
                ->and_where('comment_author_email', '=', Arr::get($user, 'user_email'))
                ->and_where('comment_approved', '=', '1')
                ->limit(1)
                ->execute()
                ->current();
        }
    }

    /**
     * Update one comment
     *
     * @param  numeric $comment_id
     * @param  array   $input
     * @return boolean
     */
    public function approve_comment($comment_id = FALSE, $approve = TRUE)
    {
        if ($comment_id !== FALSE)
        {
            return DB::update('comments')
                ->set(array('comment_approved' => $approve))
                ->where('comment_ID', '=', $comment_id)
                ->execute();
        }
    }
}