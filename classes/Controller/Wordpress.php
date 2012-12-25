<?php defined('SYSPATH') OR die('No direct access allowed.');

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
        $this->wordpress->get_posts();
    }

    public function action_post()
    {
        $id = $this->request->param('id');

        $this->wordpress->get_posts();
        $this->wordpress->get_comments($id, NULL);
    }

    public function action_feed()
    {
        $this->wordpress->get_posts(100);
    }

    public function action_category()
    {
        $this->wordpress->get_posts(10);
    }

    public function action_tag()
    {
        $this->wordpress->get_posts(10);
    }

    public function action_static()
    {
        $this->wordpress->get_static();
    }

    public function after()
    {
        parent::after();
    }
}