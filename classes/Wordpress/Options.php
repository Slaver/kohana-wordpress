<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * WordPress options actions library for Kohana
 *
 * @package   WordPress
 * @author    Viacheslav Radionov <radionov@gmail.com>
 * @copyright (c) 2011-2012 Viacheslav Radionov
 * @link      http://slaver.info/
 */
class Wordpress_Options {

    // Instance
    protected static $instance;

    /**
     * Singleton pattern
     */
    public static function instance()
    {
        if ( ! isset(self::$instance))
        {
            self::$instance = new self();
        }

        return self::$instance;
    }

    protected $model;
    protected $options;

    /**
     * Loads all taxonomies and tree actions
     *
     * @return  void
     */
    public function __construct()
    {
        $this->model = new Model_Options;
        $this->options = $this->model->get_options();

        if ( ! $this->options)
        {
            throw new Kohana_Exception('Taxonomy is not exists');
        }
    }

    /**
     * Get all options
     * 
     * @param  mixed $id
     * @return boolean
     */
    public function get_options()
    {
        return $this->options;
    }

    /**
     * Get one option
     * 
     * @param  mixed $id
     * @return array
     */
    public function get_option($id, $default = NULL)
    {
        return Arr::get($this->options, $id, $default);
    }
}
