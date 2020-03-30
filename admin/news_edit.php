<?php

namespace modules\articles\admin;

use m\module;
use m\i18n;
use m\registry;
use m\view;
use m\config;
use m\model;
use m\form;
use modules\articles\models\articles;
use modules\articles\models\news;
use modules\pages\models\pages;
use modules\users\models\users;
use modules\users\models\users_info;

class news_edit extends module {

    public function _init()
    {
        if (!isset($this->view->{$this->name . '_form'})) {
            return false;
        }
        
        $news_item = new news(!empty($this->get->news_edit) ? $this->get->news_edit : null);

        view::set('page_title', '<h1><i class="fa fa-file-text-o"></i> ' . i18n::get('To edit news item') . (!empty($news_item->id) ? ' `' . $news_item->title . '`' : '') . '</h1>');
        registry::set('title', i18n::get('To edit news item'));

        registry::set('breadcrumbs', [
            '/' . $this->conf->admin_panel_alias . '/articles' => i18n::get('Articles'),
            '/' . $this->conf->admin_panel_alias . '/articles/news' => i18n::get('News'),
        ]);

        $news_alias = config::get('news_alias') ? config::get('news_alias') : 'news';

        $page = pages::call_static()->s([], ['address' => '/' . $news_alias])->obj();

        if (empty($page->id) && !empty($this->site->news_page)) {
            $page = new pages($this->site->news_page);
        }

        if (empty($page->id)) {
            view::set('content', $this->view->div_notice->prepare([
                'text' => i18n::get('News page not found in configuration or in site options')
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

        if (empty($news_item->page)) {
            $news_item->page = $page->id;
        }
        if (empty($news_item->site)) {
            $news_item->site = $this->site->id;
        }
        if (empty($news_item->language)) {
            $news_item->language = (string)$this->language_id;
        }
        if (empty($news_item->author)) {
            $news_item->author = $this->user->profile;
        }

        $news_item->article_path = $news_item->path;

        new form(
            $news_item,
            [
//                'page' => [
//                    'field_name' => i18n::get('Page'),
//                    'related' => pages::call_static()->s(['id as value', 'name'],['id' => $pages_id],10000)->all(),
//                ],
//                'language' => [
//                    'field_name' => i18n::get('Language'),
//                    'related' => module::languages_options_arr(),
//                ],
                'title' => [
                    'type' => 'varchar',
                    'field_name' => i18n::get('Title'),
                    'required' => true,
                ],
                'alias' => [
                    'type' => 'auto_alias',
                    'field_name' => i18n::get('URL alias'),
                    'required' => true,
                    'options' => [
                        'context_field' => '[name="articles_1_title"]',
                    ],
                ],
                'description' => [
                    'type' => 'text',
                    'field_name' => i18n::get('Short description'),
                    'required' => true,
                ],
                'text' => [
                    'type' => 'text',
                    'field_name' => i18n::get('News text'),
                    'required' => true,
                ],
//                'tags' => [
//                    'type' => 'tags',
//                    'field_name' => i18n::get('Tags'),
//                ],
                'author' => [
                    'field_name' => i18n::get('Author'),
                    'related' => users_info::call_static()->s(['profile as value', "CONCAT(first_name,' ',last_name) as name"],[],10000)->all(),
                ],
                'published' => [
                    'type' => 'tinyint',
                    'field_name' => i18n::get('Published'),
                ],
                'date' => [
                    'type' => 'timestamp',
                    'field_name' => i18n::get('Shown date'),
                ],
                'date_start' => [
                    'type' => 'timestamp',
                    'field_name' => i18n::get('Show article from date'),
                ],
                'date_end' => [
                    'type' => 'timestamp',
                    'field_name' => i18n::get('Show article until date'),
                ],
                'site' => [
                    'type' => 'hidden',
                    'field_name' => '',
                ],
                'page' => [
                    'type' => 'hidden',
                    'field_name' => '',
                ],
                'language' => [
                    'type' => 'hidden',
                    'field_name' => '',
                ],
            ],
            [
                'form' => $this->view->{$this->name . '_form'},
                'varchar' => $this->view->edit_row_varchar,
                'text' => $this->view->edit_row_text,
                'timestamp' => $this->view->edit_row_timestamp,
                'tags' => $this->view->edit_row_tags,
                'tinyint' => $this->view->edit_row_tinyint,
                'related' => $this->view->edit_row_related,
                'hidden' => $this->view->edit_row_hidden,
                'auto_alias' => $this->view->edit_row_auto_alias,
                'saved' => $this->view->edit_row_saved,
                'error' => $this->view->edit_row_error,
            ]
        );

        return true;
    }
}