<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * This model can be used for getting WordPress options
 *
 * @package   WordPress
 * @author    Viacheslav Radionov <radionov@gmail.com>
 * @copyright (c) 2011-2012 Viacheslav Radionov
 * @link      http://slaver.info/
 */
class Model_Options extends Model_Database {

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
}
