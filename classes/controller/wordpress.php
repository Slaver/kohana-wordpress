<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Wordpress extends Controller {

    public $wordpress = NULL;

    public function before()
    {
        parent::before();

        $this->wordpress = Wordpress::instance();
    }

    public function action_index()
    {
        $this->wordpress->limit = 20;
        $posts = $this->wordpress->get_posts();
        var_dump($posts);
    }

    public function action_post()
    {
        $id = $this->request->param('id');

        $this->wordpress->get_posts();
        $this->wordpress->get_comments($id, NULL);
    }

    public function action_feed()
    {
        var_dump($this->wordpress->get_posts(100));
    }

    public function action_category()
    {
        var_dump($this->wordpress->get_posts(10));
    }

    public function action_tag()
    {
        var_dump($this->wordpress->get_posts(10));
    }

    public function action_static()
    {
        var_dump($this->wordpress->get_static());
    }

    public function after()
    {
        parent::after();
    }
}