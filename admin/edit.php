<?php

namespace modules\articles\admin;

use m\core;
use m\module;
use m\i18n;
use m\registry;
use m\view;
use m\config;
use m\model;
use m\form;
use modules\articles\models\articles;
use modules\pages\models\pages;
use modules\users\models\users;
use modules\users\models\users_info;

class edit extends module {

    public function _init()
    {
        if (!isset($this->view->{'article_' . $this->name . '_form'})) {
            return false;
        }

        $article = new articles(!empty($this->get->edit) ? $this->get->edit : null);

        if (empty($article->id) && !empty($this->get->page) && is_numeric($this->get->page)) {
            $article->page = (string)$this->get->page;
        }

        $page = empty($article->page) ? null : new pages($article->page);

        if (!empty($article->id)) {
            view::set('page_title', '<h1><i class="fa fa-file-text-o"></i> ' . i18n::get('To edit article') . ' `' .
                $article->title . '`</h1>');

            /**
             * Why sometimes used something like i18n::get('To edit article') not only '*To edit article*' ?
             * Because i18n::get() works with current translation (loaded for admin side or for client side per module).
             * All of phrases appended to main translation array by keys and can be overwritten several times when
             *   a process will go to template translation (changing *To edit article* to phrase from array).
             * So, usage i18n::get() gives more actual translation if you are not sure that a phrase can be overwritten.
             *
             * Also that method can be usable when you don't expect to go to render of a full template
             *   (render a small view for mail function of for Ajax response, or for debug a part of content, etc.)
             */
            registry::set('title', '*To edit article*');

            if (!empty($page) && !empty($page->id)) {

                registry::set('breadcrumbs', [
                    '/' . config::get('admin_panel_alias') . '/articles' => '*Articles*',
                    '/' . config::get('admin_panel_alias') . '/articles/page/' . $page->id => '*Articles of page*' . ' "' . $page->name . '"',
                    '/' . config::get('admin_panel_alias') . '/articles/edit/' . $article->id => '*To edit article*',
                ]);
            }
        }
        else if (!empty($page) && !empty($page->id)) {
            registry::set('breadcrumbs', [
                '/' . config::get('admin_panel_alias') . '/articles' => '*Articles*',
                '/' . config::get('admin_panel_alias') . '/articles/page/' . $page->id => '*Articles of page*' . ' "' . $page->name . '"',
                '/' . config::get('admin_panel_alias') . '/articles/page/' . $page->id . '/add' => '*To add article*',
            ]);
        }


        if (empty($article->site)) {
            $article->site = $this->site->id;
            $this->post->site = $this->site->id;
        }

        if (empty($article->language)) {
            $article->language = (string)$this->language_id;
        }
        if (empty($article->author)) {
            $article->author = $this->user->profile;
        }

        $article->article_path = $article->path;

        $pages_tree = $this->page->get_pages_tree();

        if (empty($pages_tree)) {
            $this->page->prepare_page([]);
            $pages_tree = $this->page->get_pages_tree();
        }

        $pages_arr = empty($pages_tree) ? [] : pages::options_arr_recursively($pages_tree, '');

//        if (!empty($article->text)) {
//            preg_match_all('!<div class=\"code\">(.*?)<\/div>!si', $article->text, $code_arr);
//
//            if ($code_arr) {
//                //exit(print_r($code_arr));
//            }
//        }

        new form(
            $article,
            [
                'page' => [
                    'field_name' => i18n::get('Page'),
                    'related' => $pages_arr,
                    'required' => true,
                ],
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
                ],
                'image' => [
                    'type' => 'file_path',
                    'field_name' => i18n::get('Main article image'),
//                    'options' => [
//                        'multiple' => true,
//                    ],
                ],
                'text' => [
                    'type' => 'text',
                    'field_name' => i18n::get('Article text'),
                    'required' => true,
                ],
                'date' => [
                    'type' => 'timestamp',
                    'field_name' => i18n::get('Date'),
                ],
//                'tags' => [
//                    'type' => 'tags',
//                    'field_name' => i18n::get('Tags'),
//                ],
                'author' => [
                    'field_name' => i18n::get('Author'),
                    'related' => users_info::call_static()->s(['profile as value', "CONCAT(first_name,' ',last_name) as name"],[],10000)->all(),
                ],
                'disallow_comments' => [
                    'type' => 'tinyint',
                    'field_name' => i18n::get('Disallow comments'),
                ],
                'published' => [
                    'type' => 'tinyint',
                    'field_name' => i18n::get('Published'),
                ],
                'site' => [
                    'type' => 'hidden',
                    'field_name' => '',
                ],
                'language' => [
                    'type' => 'hidden',
                    'field_name' => '',
                ],
            ],
            [
                'form' => $this->view->{'article_' . $this->name . '_form'},
                'varchar' => $this->view->edit_row_varchar,
                'text' => $this->view->edit_row_text,
                'textarea' => $this->view->edit_row_textarea,
                'timestamp' => $this->view->edit_row_timestamp,
                'tags' => $this->view->edit_row_tags,
                'tinyint' => $this->view->edit_row_tinyint,
                'hidden' => $this->view->edit_row_hidden,
                'related' => $this->view->edit_row_related,
                'auto_alias' => $this->view->edit_row_auto_alias,
                'file_path' => $this->view->edit_row_file_path,
                'saved' => $this->view->edit_row_saved,
                'error' => $this->view->edit_row_error,
            ]
        );
    }
}