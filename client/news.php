<?php

namespace modules\articles\client;

use m\config;
use m\core;
use m\registry;
use m\view;
use modules\pages\models\pages;

class news extends articles {

    public static $_name = '*News*';
    private $subpages = [];

    protected $js = [
        '/js/date_get_parameter.js',
    ];

    public function _init()
    {
        $pages_tree = $this->page->get_pages_tree();
        $this->subpages = empty($pages_tree[$this->page->super_parent]) || empty($pages_tree[$this->page->super_parent]['sub_pages'])
            ? [] : $pages_tree[$this->page->super_parent]['sub_pages'];

        /**
         * View a news root, like /news or /articles
         */
        if ((int)$this->page->id == (int)$this->page->super_parent
            && $this->page->address == '/' . $this->alias
            && isset($this->view->news_block)
            && isset($this->view->news_block_slide)
            && isset($this->view->news_block_link)
            && !empty($this->subpages)) {

            return $this->news_root();
        }

        parent::_init();
    }

    public function news_root()
    {

        $news_blocks = '';

        foreach ($this->subpages as $news_subpage) {
            $news_blocks .= $this->build_block($news_subpage);
        }

        view::set('news_block', $news_blocks);

        $this->css[] = '/css/news_block.css';
    }

    public function build_block($page)
    {
        $options = $this->options;

        $slides_limit = isset($options->slides_limit) ? $options->slides_limit : config::get('per_page');
        $block_links_limit = isset($options->block_links_limit) ? $options->block_links_limit : config::get('per_page');

        $slides = $links = '';

        $category_path = $this->page->address . $page['address'];

        $cond = [
            'site' => $this->site->id,
            'page' => $page['id'],
            'alias' => ['not' => substr($page['address'], 1)],
            'published' => 1,
            'language' => $this->language_id,
        ];

        if (registry::has('by_date') && strtotime(registry::get('by_date')) > 0) {
            $cond['date'] = ['between' => ["'".registry::get('by_date') . " 00:00:00'", "'".registry::get('by_date') . " 23:59:59'"]];
        }

        $slides_articles = \modules\articles\models\news::call_static()
            ->s([], $cond, [$slides_limit])
            ->all();

        $links_articles = \modules\articles\models\news::call_static()
            ->s([], $cond, [$slides_limit, $block_links_limit])
            ->all();

        if (!empty($slides_articles) && is_array($slides_articles)) {
            foreach ($slides_articles as $article) {
                $slides .= $this->view->news_block_slide->prepare([
                    'title' => $article['title'],
                    'image' => $article['image'],
                    'path' => $category_path . '/' . $article['alias'],
                    'date' => strftime('%e %b', strtotime($article['date'])),
                    'source' => $article['source'],
                    'description' => $article['description'],
                ]);
            }
        }

        if (!empty($links_articles) && is_array($links_articles)) {
            foreach ($links_articles as $article) {
                $links .= $this->view->news_block_link->prepare([
                    'title' => $article['title'],
                    'path' => $category_path . '/' . $article['alias'],
                    'date' => strftime('%e %b', strtotime($article['date'])),
                ]);
            }
        }

        return empty($slides) ? '' : $this->view->news_block->prepare([
            'block_link' => $this->page->address . $page['address'],
            'block_title' => $page['name'],
            'slides' => $slides,
            'links' => $links,
            'bests' => '',
        ]);
    }
}