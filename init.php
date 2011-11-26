<?php defined('SYSPATH') or die('No direct script access.');

//Route::set('wordpress/auth', 'auth(/<action>(/<id>))')
//    ->defaults(array(
//        'controller' => 'auth',
//        'action'     => 'index',
//    ));
//Route::set('wordpress/feed', '(<prefix>/<category>/)feed', array('prefix' => '(category|tag)', 'category' => '.+'))
//    ->defaults(array(
//        'controller'=> 'wordpress',
//        'action'    => 'feed',
//    ));
//Route::set('wordpress/page', 'page(/<page>)', array('page' => '[0-9]+'))
//    ->defaults(array(
//        'controller'=> 'wordpress',
//        'action'    => 'index',
//    ));
//Route::set('wordpress/category', '<prefix>/<category>(/page/<page>)', array('prefix' => '(category|tag)', 'category' => '[a-zA-Z0-9_-]+', 'page' => '[0-9]+'))
//    ->defaults(array(
//        'controller'=> 'wordpress',
//        'action'    => 'category',
//    ));
//Route::set('wordpress/static', '<title>', array('title' => '(about|4u)'))
//    ->defaults(array(
//        'controller'=> 'wordpress',
//        'action'    => 'static',
//    ));
//Route::set('wordpress/default', '(<year>(/<month>(/<day>(/<title>))))(/page/<page>)', array(
//        'year'  => '[0-9]+',
//        'month' => '[0-9]+',
//        'day'   => '[0-9]+',
//        'title' => '[^\.\\n]+',
//        'page'  => '[0-9]+'))
//    ->defaults(array(
//        'controller'=> 'wordpress',
//        'action'    => 'index',
//    ));