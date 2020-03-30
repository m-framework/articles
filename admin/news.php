<?php

namespace modules\articles\admin;

use m\module;
use m\view;
use m\i18n;
use m\config;
use modules\admin\admin\overview_data;
use modules\pages\models\pages;

class news extends module {

    public function _init()
    {
        $news_alias = config::get('news_alias') ? config::get('news_alias') : 'news';

        $page = pages::call_static()->s([], ['site' => (int)$this->site->id, 'address' => '/' . $news_alias])->obj();

        if (empty($page->id) && !empty($this->site->news_page)) {
            $page = new pages($this->site->news_page);
        }

        if (empty($page->id)) {
            view::set('content', $this->view->div_notice->prepare([
                'text' => i18n::get('Parameter `news_alias` not found in configuration or in site options and page /news not found too')
            ]));
            return true;
        }

        $pages_id = [$page->id];

        $news_child = pages::call_static()->s([], ['parent' => $page->id], 1000)->all();

        if (!empty($news_child)) {
            foreach ($news_child as $child) {
                $pages_id[] = $child['id'];
            }
        }

        view::set('content', overview_data::items(
            'modules\articles\models\news',
            [
                'path' => i18n::get('Path'),
                'title' => i18n::get('Title'),
                'published' => i18n::get('Published'),
                'date_start' => i18n::get('Date start'),
                'date_end' => i18n::get('Date end'),
            ],
            ['site' => (int)$this->site->id, 'language' => $this->language_id, 'page' => $pages_id],
            $this->view->news_overview,
            $this->view->news_overview_item
        ));
    }
}
