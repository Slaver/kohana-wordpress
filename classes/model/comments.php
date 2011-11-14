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
    public function get_comments($post_id = NULL, $limit = NULL)
    {
        $query = DB::select()
            ->from('comments')
            ->and_where('comment_approved', '=', 1)
            ->order_by('comment_ID', 'ASC');

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
}
