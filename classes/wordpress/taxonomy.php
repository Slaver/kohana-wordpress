<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * WordPress taxonomy actions library for Kohana
 * Uses Tree class
 *
 * @package   WordPress
 * @author    Viacheslav Radionov <radionov@gmail.com>
 * @copyright (c) 2011-2012 Viacheslav Radionov
 * @link      http://slaver.info/
 */
class Wordpress_Taxonomy {

    // Instances
    protected static $instances = array();

    /**
     * Singleton pattern
     */
    public static function instance($name = 'category')
    {
        if ( ! isset(self::$instances[$name]))
        {
            self::$instances[$name] = new self($name);
        }

        return self::$instances[$name];
    }

    protected $model;
    protected $taxonomy;
    protected $tree;

    /**
     * Loads all taxonomies and tree actions
     *
     * @return  void
     */
    public function __construct($name)
    {
        $this->model = new Model_Taxonomy;
        $this->taxonomy = $this->model->get_taxonomies($name);

        if ( ! empty($this->taxonomy))
        {
            $this->tree = new Tree;
            foreach ($this->taxonomy as $cat)
            {
                $this->tree->add_item($cat['term_id'], $cat['parent'], array($cat['name'], $cat['slug']));
            }
            return $this->tree;
        }
        else
        {
            throw new Kohana_Exception('Taxonomy is not exists');
        }
    }

    /**
     * List of taxonomies
     */
    public function get_all($parent = 0, $tree = TRUE)
    {
        return $this->tree->build($parent, $tree);
    }

    /**
     * Check if taxonomy has childs
     * 
     * @param  mixed $id
     * @return boolean
     */
    public function has_childs($id)
    {
        if ( ! is_numeric($id))
        {
            $id = $this->_get_taxonomy_by_slug($id);
        }

        return $this->tree->has_childs($id);
    }

    /**
     * Returns taxonomy childs if them exists
     * 
     * @param  mixed $id
     * @return array
     */
    public function get_childs($id)
    {
        if ( ! is_numeric($id))
        {
            $id = $this->_get_taxonomy_by_slug($id);
        }

        return array_keys($this->tree->get_childs($id));
    }

    /**
     * Check if taxonomy has parent
     * 
     * @param  mixed $id
     * @return boolean
     */
    public function has_parent($id)
    {
        if ( ! is_numeric($id))
        {
            $id = $this->_get_taxonomy_by_slug($id);
        }

        return $this->tree->has_parent($id);
    }

    /**
     * Returns taxonomy parent if them exists
     * 
     * @param  mixed $id
     * @return array
     */
    public function get_parent($id)
    {
        if ( ! is_numeric($id))
        {
            $id = $this->_get_taxonomy_by_slug($id);
        }

        return $this->tree->get_parent($id);
    }

    /**
     * Returns taxonomy ID
     * 
     * @param  mixed $id
     * @return numeric
     */
    public function get_taxonomy_id($id)
    {
        if ( ! is_numeric($id))
        {
            $id = $this->_get_taxonomy_by_slug($id);
        }

        return $id;
    }

    /**
     * Get texonomy data by their slug
     * 
     * @param mixed $slug
     * @return mixed
     */
    protected function _get_taxonomy_by_slug($slug)
    {
        if ( ! empty($this->taxonomy))
        {
            foreach ($this->taxonomy as $taxonomy)
            {
                if ($taxonomy['slug'] === $slug)
                {
                    return $taxonomy['term_id'];
                }
            }
        }
    }
}
