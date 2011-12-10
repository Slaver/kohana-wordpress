<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * This model can be used for getting posts' taxonomies from WordPress database
 *
 * @package   WordPress
 * @author    Viacheslav Radionov <radionov@gmail.com>
 * @copyright (c) 2011-2012 Viacheslav Radionov
 * @link      http://slaver.info/
 */
class Model_Taxonomy extends Model_Database {

    /**
     * Retrieve the terms of the taxonomy that are attached to the post.
     * Analogue of http://codex.wordpress.org/Function_Reference/get_the_terms
     *
     * @param  array $posts
     * @return array
     */
    public function get_post_taxonomy($posts = array())
    {
        $return = array();
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
            // @TODO nested categories
            if ($value['taxonomy'] == 'category')
            {
                $return[$value['object_id']]['category'][] = array(
                    'id'    => $value['term_id'],
                    'name'  => $value['name'],
                    'slug'  => $value['slug'],
                    'link'  => html::anchor('/category/'.$value['slug'], $value['name']),
                );
            }
            elseif ($value['taxonomy'] == 'post_tag')
            {
                $return[$value['object_id']]['tags'][] = array(
                    'id'    => $value['term_id'],
                    'name'  => $value['name'],
                    'slug'  => $value['slug'],
                    'link'  => html::anchor('/tag/'.$value['slug'], $value['name']),
                );
            }
        }

        return $return;
    }

    /**
     * Get all taxonomies by their type
     *
     * @param  string $type
     * @return array
     */
    public function get_taxonomies($type)
    {
        if ( ! empty($type))
        {
            return DB::select()
                ->from('terms')
                ->join('term_taxonomy', 'INNER')
                    ->using('term_id')
                ->where('taxonomy', '=', $type)
                ->execute()->as_array();
        }
    }

    /**
     * Get term information
     *
     * @param  string $type
     * @return array
     */
    public function get_term($id)
    {
        if ( ! empty($id))
        {
            $query = DB::select()
                ->from('terms');

            if (is_numeric($id))
            {
                $query->where('term_id', '=', $id);
            }
            else
            {
                $query->where('slug', '=', $id);
            }

            return $query
                ->join('term_taxonomy', 'INNER')
                    ->using('term_id')
                ->execute()->current();
        }
    }
}
