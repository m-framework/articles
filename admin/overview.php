<?php

namespace modules\articles\admin;

use libraries\helper\html;
use m\core;
use m\functions;
use m\module;
use m\registry;
use m\view;
use m\i18n;
use m\config;
use modules\admin\admin\overview_data;
use modules\articles\models\articles;
use modules\pages\models\pages;

class overview extends module {

    public function _init()
    {
        config::set('per_page', 200);

        $news_alias = config::get('news_alias') ? config::get('news_alias') : 'news';

        $news_page = pages::call_static()->s([], ['address' => '/' . $news_alias])->obj();

        if (empty($news_page->id) && !empty($this->site->news_page)) {
            $news_page = new pages($this->site->news_page);
        }

        $fields = [
            'path' => i18n::get('Path'),
            'title' => i18n::get('Title'),
            'published' => i18n::get('Published'),
            'date' => i18n::get('Date'),
        ];

        $conditions = [
            'site' => (int)$this->site->id,
            'language' => $this->language_id,
        ];

        if (!empty($news_page->id)) {
            $conditions[] = "page!='" . $news_page->id . "'";
        }

        if (registry::has('page_num')) {

            $page = new pages(registry::get('page_num'));

            if (!empty($page->id)) {

                $conditions['page'] = $page->id;

                view::set('page_title', '<h1><i class="fa fa-file-text-o"></i> ' . i18n::get('Articles of page') . ' "' . $page->name . '"</h1>');
                registry::set('title', i18n::get('Articles of page') . ' "' . $page->name . '"');

                registry::set('breadcrumbs', [
                    '/' . config::get('admin_panel_alias') . '/articles' => '*Articles*',
                    '/' . config::get('admin_panel_alias') . '/articles/page/' . $page->id => i18n::get('Articles of page') . ' "' . $page->name . '"',
                ]);
            }
        }

        $pages_arr = [
            ['value' => '', 'name' => ''],
        ];

        $pages_tree = $this->page->get_pages_tree();

        if (empty($pages_tree)) {
            $this->page->prepare_page([]);
            $pages_tree = $this->page->get_pages_tree();
        }

        //core::out($pages_tree);

        $pages_arr = array_merge($pages_arr, pages::options_arr_recursively($pages_tree, ''));

        view::set('content', overview_data::items(
            'modules\articles\models\articles',
            $fields,
            $conditions,
            $this->view->overview,
            $this->view->overview_item,
            [
                'page_options' => html::arr_to_options($pages_arr, !registry::has('page_num') ? null : registry::get('page_num')),
            ]
        ));
    }


    public function _ajax_onchange_update()
    {
        if (empty($this->post->id)) {
            $this->ajax_arr['error'] = 'Empty important data';
            return false;
        }

        $article = new articles($this->post->id);

        $article->site = $this->site->id;
        $article->save((array)$this->post);

        if ($error = $article->error()) {
            $this->ajax_arr['error'] = $error;
        }

        $this->ajax_arr['result'] = 'success';

        $this->ajax_arr['db_logs'] = registry::get('db_logs');
        return true;
    }
}
