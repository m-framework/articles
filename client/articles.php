<?php

namespace modules\articles\client;

use m\events;
use
    m\module,
    m\config,
    m\core,
    m\registry,
    m\custom_exception,
    m\i18n,
    modules\articles\models\articles_tags,
    modules\users\models\users,
    modules\pages\models\pages,
    m\view;
use modules\articles\models\articles_data;
use modules\pagination\client\pagination;
use modules\seo\models\seo;
use modules\users\models\visitors;

class articles extends module {

    public $arr = [];
    public $articles = [];
    public $articleId = '';
    public $articleTitle = '';
    public $subpage = '';

    public static $_name = '*Articles*';

//    protected $cache = true;
//    protected $cache_per_page = true;

    protected $events = [
        'article_shown' => 'init_article_visitor',
    ];

    public function _init()
    {
        $same_alias = \modules\articles\models\articles::call_static()->s([], [
            'alias' => $this->alias,
            'site' => $this->site->id,
            'language' => $this->language_id,
            'page' => $this->page->id,
            'published' => 1,
        ])->obj();

        if ($this->page->address !== '/' . $this->alias || empty($this->_route) || !empty($same_alias->id))
            $this->article(empty($same_alias->id) ? null : $same_alias);
        else
            $this->article_small();

//        if (!view::get('article_item') && !view::get('article_small') && $this->page->type == 'articles'
//            && !$this->user->is_admin())
//            throw new custom_exception(i18n::get('Page not found'), 404);

        return true;
    }

    private function article_small()
    {
        $this->css = ['/css/article-small.css'];

        $pages_tree = $this->page->get_pages_tree();
        $subpages = pages::call_static()->s(['id', 'address'], ['parent' => $this->page->id], [1000])->all('object');

        $pages_conditions = [['page' => $this->page->id]];

        if (!empty($subpages)) {
            foreach ($subpages as $subpage) {
                $pages_conditions[] = ['page' => $subpage->id, 'alias' => substr($subpage->address, 1)];
            }
        }
        $articles = '';

        $conditions = [
            'site' => $this->site->id,
            $pages_conditions,
            'language' => $this->language_id,
            'published' => 1,
        ];

//        core::out($conditions);

        $by_tag = registry::get('by_tag');

        if (!empty($by_tag)) {

            $tag_id = articles_tags::call_static()->s(['id'], ['tag' => trim(urldecode($by_tag))])->one();

            if (!empty($tag_id))
                $conditions[] = "CONCAT(',',`tags`,',') like '%," . $tag_id . ",%'";
        }

//        $by_date = registry::get('by_date');
//
//        if (!empty($by_date)) {
//
//            $conditions[] = "`date` LIKE '" . $by_date . "%'";
//        }

        if (registry::has('by_date') && strtotime(registry::get('by_date')) > 0) {
            $conditions['date'] = ['between' => ["'".registry::get('by_date') . " 00:00:00'", "'".registry::get('by_date') . " 23:59:59'"]];
        }
        else if (!empty($this->get->date) && strtotime($this->get->date) > 0) {
            $conditions['date'] = ['between' => ["'".$this->get->date . " 00:00:00'", "'".$this->get->date . " 23:59:59'"]];
        }

        $per_page = !empty($this->config->per_page) ? $this->config->per_page : 10;

        $page = registry::get('page_num');

        if (empty($page))
            $page = 1;

        $limit = (($page * $per_page) - $per_page) . ', ' . $per_page;

        $articles_items = \modules\articles\models\articles::call_static()
            ->select([], [], $conditions, [], ['sequence' => 'ASC', 'date' => 'DESC'], [$limit])
            ->all();

        if (empty($articles_items) || !isset($this->view->article_small))
            return false;

        $count = \modules\articles\models\articles::call_static()->count($conditions, [], []);

        foreach ($articles_items as $row) {

            $tags = [];

            if (!empty($row['tags'])) {
                $_tags = articles_tags::call_static()
                    ->s([], ['id' => array_filter(explode(',', trim($row['tags'])))], ['10'])
                    ->all();
            }

            if (!empty($_tags)) {
                foreach ($_tags as $tag) {
                    $tags[] = '<a href="' . $this->page->get_path() . '/by_tag/' . urlencode(trim($tag['tag'])) .
                        '">#' . $tag['tag'] . '</a>';
                }
            }

            if (!empty($row['date'])) {
                $time = strtotime($row['date']);

                $row['short_beautiful_date'] = date('Y', $time) !== date('Y') ? strftime('%e %b %Y', $time)
                    : strftime('%e %b', $time);
                $row['beautiful_date'] = date('Y', $time) !== date('Y') ? strftime('%e %b %Y %H:%M', $time)
                    : strftime('%e %b %H:%M', $time);
            }

            $row['tags_links'] = implode(' ', $tags);
            $row['text'] = htmlspecialchars_decode($row['text']);
            $row['description'] = htmlspecialchars_decode($row['description']);
            $row['image'] = empty($row['image']) ? '' : htmlspecialchars_decode(stripslashes($row['image']));
            $row['image_container'] = empty($row['image']) ? '' : '<img src="' . $row['image'] . '">';
            $row['comments'] = '';
            $row['article_link'] = '//' . $this->site->host . '/' . $this->_route . '/' . $row['alias'];
            $row['date'] = substr($row['date'], 0, 10);
            $row['source_host'] = empty($row['source']) ? '' : parse_url($row['source'], PHP_URL_HOST);
			$row['source_link'] = empty($row['source_host']) ? '' : '<span class="source-link-wrap">*Source*: <a href="' . $row['source'] . '" target="_blank">' . $row['source_host'] . '</a></span>';

			if ($this->user->has_permission($this->name, $this->page->id) && isset($this->view->edit_bar)) {
                $row['edit_bar'] = $this->view->edit_bar->prepare([
                    'model' => 'articles',
                    'id' => $row['id'],
                    'edit_link' => '/' . config::get('admin_panel_alias') . '/articles/edit/' . $row['id'],
                ]);
            }

            $row['path'] = '~language_prefix~/' . $this->clean_route . '/' . $row['alias'];

            $articles .= $this->view->article_small->prepare($row);

            unset($row);
        }

        unset($articles_items);
        unset($article_view);


        if (!empty($this->user) && $this->user->has_permission($this->name, $this->page->id) && isset($this->view->article_add_link)) {
            $articles .= $this->view->article_add_link->prepare([
                'model' => 'articles',
                'add_link' => '/' . config::get('admin_panel_alias') . '/articles/add/page/' . $this->page->id,
            ]);
        }

        if (empty($articles))
            return false;

        view::set('article_small', $articles);

        new pagination($count);

        events::call('articles_section_shown', $page);

        unset($articles);
    }

    private function article(\modules\articles\models\articles $article = null)
    {
        $this->css = ['/css/article.css'];

        if (empty($article)) {
            $conditions = [
                'site' => $this->site->id,
                'language' => $this->language_id,
                'page' => $this->page->id,
                'published' => 1,
            ];

            if (!empty($this->alias)) {
                $conditions['alias'] = $this->alias;
            }

            $article = \modules\articles\models\articles::call_static()->s([], $conditions, [1], ['date'=>'DESC'])->obj();
        }

        /**
         * On each of page with type "articles" should be alighting article with same alias
         */
        if (empty($article->id) && $this->page->address !== '/404') {
            core::process_page_by_route(['404']);
        }

        registry::set('subpage_type', 'article');

        $tags = [];

        if (!empty($article->tags)) {
            $_tags = articles_tags::call_static()
                ->s([], ['id' => array_filter(explode(',', trim($article->tags)))], ['10'])
                ->all();
        }

        if (!empty($_tags)) {
            foreach ($_tags as $tag) {
                $tags[] = '<a href="' . $this->page->get_path() . '/by_tag/' . urlencode(trim($tag['tag'])) .
                    '">#' . $tag['tag'] . '</a>';
            }
        }

        $article->tags_links = implode(' ', $tags);
        $article->description = strip_tags($article->description);
        $article->image = empty($article->image) ? '' : stripslashes(htmlspecialchars_decode($article->image));
        $article->image_container = empty($article->image) ? '' : '<img src="' . $article->image . '">';
        $article->date = substr($article->date, 0, 10);
        $article->source_host = empty($article->source) ? '' : parse_url($article->source, PHP_URL_HOST);
        $article->source_link = empty($article->source_host) ? '' : '<span class="source-link-wrap">*Source*: <a href="' . $article->source . '" target="_blank">' . $article->source_host . '</a></span>';

        if ($this->user->has_permission($this->name, $this->page->id) && isset($this->view->edit_bar)) {
            $article->edit_bar = $this->view->edit_bar->prepare([
                'model' => 'articles',
                'id' => $article->id,
                'edit_link' => '/' . config::get('admin_panel_alias') . '/articles/edit/' . $article->id,
            ]);
        }

        $images = $videos = $attachments = '';

        $articles_data = articles_data::call_static()->s([], [
            'site' => $this->site->id,
            'article' => $article->id,
        ], [1000])->all();

        if (!empty($articles_data) && isset($this->view->article_attachments_wrapper)) {

            foreach ($articles_data as $articles_data_item) {

                if (!empty($articles_data_item['type']) && (int)$articles_data_item['type'] == 1
                    && isset($this->view->article_image) && $articles_data_item['data_path'] !== $article->image) {
                    $images .= $this->view->article_image->prepare([
                        'image' => $articles_data_item['data_path'],
                        'alt' => $article->title,
                    ]);
                }
                else if (!empty($articles_data_item['type']) && (int)$articles_data_item['type'] == 2 && isset($this->view->article_video)) {
                    $videos .= $this->view->article_video->prepare([
                        'src' => $articles_data_item['data_path'],
                    ]);
                }
                else if (!in_array((int)$articles_data_item['type'], [1,2]) && isset($this->view->article_attachment)) {
                    $attachments .= $this->view->article_attachment->prepare([
                        'link' => $articles_data_item['data_path'],
                        'ext' => mb_strtolower(pathinfo($articles_data_item['data_path'], PATHINFO_EXTENSION), 'UTF-8'),
                        'filename' => pathinfo($articles_data_item['data_path'], PATHINFO_BASENAME),
                    ]);
                }
            }

            $article->attachments = '';

            if (!empty($images)) {
                $article->attachments .= $this->view->article_attachments_wrapper->prepare([
                    'title' => '*Photos*',
                    'attachments' => $images,
                ]);
            }

            if (!empty($videos)) {
                $article->attachments .= $this->view->article_attachments_wrapper->prepare([
                    'title' => '*Videos*',
                    'attachments' => $videos,
                ]);
            }

            if (!empty($attachments)) {
                $article->attachments .= $this->view->article_attachments_wrapper->prepare([
                    'title' => '*Attached files*',
                    'attachments' => $attachments,
                ]);
            }
        }

        registry::set('title', $article->title);
        registry::set('description', strip_tags($article->description));

        $seo = $this->page->seo;

        if (empty($seo)) {
            $this->page->seo = new seo();
        }

        if (!empty($article->image) && (empty($this->page->seo) || empty($this->page->seo->og_image))) {
            $this->page->seo->og_image = $article->image;
            $this->page->seo->twitter_image = $article->image;
        }

        if (!empty($article->description) && (empty($this->page->seo) || empty($this->page->seo->description))) {
            $this->page->seo->description = strip_tags($article->description);
            $this->page->seo->og_description = strip_tags($article->description);
            $this->page->seo->twitter_description = strip_tags($article->description);
        }

        if (!empty($article->title) && (empty($this->page->seo) || empty($this->page->seo->title))) {
            $this->page->seo->title = strip_tags($article->title);
            $this->page->seo->og_title = strip_tags($article->title);
            $this->page->seo->twitter_title = strip_tags($article->title);
        }

        if (registry::get('breadcrumbs') && substr($this->page->address, 1) !== $article->alias) {
            registry::merge(['breadcrumbs' => ['' => $article->title]]);
        }

        events::call('article_shown', $article);

        if ($this->view->article_item)
            view::set('article_item', $this->view->article_item->prepare($article));
    }

    public function _get_article()
    {
        if (empty($this->request->id))
            return false;

        $article = \modules\articles\models\articles::call_static()->obj_by_id($this->request->id);
        if (empty($article->id))
            return false;

        $this->ajax_arr = [
            'title' => htmlspecialchars_decode(@$article->title),
            'alias' => strip_tags(htmlspecialchars_decode(@$article->alias)),
            'text' => htmlspecialchars_decode(@$article->text)
        ];
        return true;
    }

    public function _save_article()
    {
        $article = \modules\articles\models\articles::call_static();

        if (empty($this->request->id)) {

            $save_arr = [
                'title' => $this->request->title,
                'alias' => strip_tags(htmlspecialchars_decode($this->request->alias)),
                'text' => $this->request->text,
                'site' => $this->site->id,
                'author' => $this->user->profile,
                'page' => $this->page->id,
                'date' => date('Y-m-d H:i:s'),
                'language' => $this->language_id
            ];

            if ($article->save($save_arr)) {

                $save_arr['id'] = $article->id;
                $save_arr['text'] = htmlspecialchars_decode(stripcslashes($save_arr['text']));

                return $this->ajax_arr = [
                    'article' => $this->view->article_item->prepare($save_arr)
                ];
            }
            else {
                return $this->ajax_arr = [
                    'error' => $article->error()
                ];
            }
        }
        else {

            $update_arr = [
                'title' => $this->request->title,
                'alias' => strip_tags(htmlspecialchars_decode($this->request->alias)),
                'text' => $this->request->text
            ];

            if ($article->u($update_arr, ['id' => $this->request->id]))
                return $this->ajax_arr = [
                    'title' => htmlspecialchars_decode($this->request->title),
                    'alias' => strip_tags(htmlspecialchars_decode($this->request->alias)),
                    'text' => stripcslashes(htmlspecialchars_decode($this->request->text))
                ];
            else
                return $this->ajax_arr = [
                    'error' => mysql_error()
                ];
        }
    }

    public function init_article_visitor($article)
    {
		if (!registry::has('visitor') || !registry::has('site'))  {
			return false;
		}
	
        visitors::set_history([
			'visitor' => registry::has('visitor') ? registry::get('visitor')->id : null,
            'user' => registry::has('user') ? registry::get('user')->profile : null,
            'site' => registry::has('site') ? registry::get('site')->id : null,
            'related_model' => 'articles',
            'related_id' => $article->id,
        ]);
    }

    public function _ajax_delete_articles()
    {
        if (!$this->user->has_permission($this->name, $this->page->id) || empty($this->post->id)
            || empty($this->post->model) || $this->post->model !== 'articles') {

            return $this->ajax_arr = ['error' => 'Not fully data'];
        }

        $item = new \modules\articles\models\articles($this->post->id);

        if ($item->destroy()) {
            return $this->ajax_arr = ['result' => 'success'];
        }
        else {
            return $this->ajax_arr = ['error' => 'Can\'t delete this article'];
        }
    }

    public function _ajax_update_articles()
    {
        if (!$this->user->has_permission($this->name, $this->page->id) || empty($this->post->id)
            || empty($this->post->model) || $this->post->model !== 'articles') {

            return $this->ajax_arr = ['error' => 'Not fully data'];
        }

        $item = new \modules\articles\models\articles($this->post->id);
        $item->import($this->post);

        if ($item->save()) {
            $this->ajax_arr = ['result' => 'success'];
        }
        else {
            $this->ajax_arr = ['error' => 'Can\'t update this article'];
        }

        return true;
    }

    public function _ajax_add_articles()
    {
        if (!$this->user->has_permission($this->name, $this->page->id) || empty($this->post->model)
            || $this->post->model !== 'articles' || !isset($this->view->article_item)) {

            return $this->ajax_arr = ['error' => 'Not fully data'];
        }

        $data = [
            'site' => $this->site->id,
            'page' => $this->page->id,
            'language' => (int)$this->language_id,
            'published' => 1,
        ];

        $last_sequence = \modules\articles\models\articles::call_static()->s(['sequence'], $data, [1])->one();

        $data = array_merge($data, [
            'alias' => time(),
            'title' => 'Lorem ipsum dolor sit amet',
            'description' => 'Lorem ipsum dolor sit amet',
            'content' => 'Lorem ipsum dolor sit amet',
            'date' => date('Y-m-d H:i:s'),
            'sequence' => empty($last_sequence) ? 1 : (int)$last_sequence + 1,
        ]);

        $item = new \modules\articles\models\articles();
        $item->import($data);
        $item->save();

        $item->edit_bar = $this->view->edit_bar->prepare([
            'model' => 'articles',
            'id' => $item->id,
            'edit_link' => '/' . config::get('admin_panel_alias') . '/articles/edit/' . $item->id,
        ]);

        $this->ajax_arr = ['item' => $this->view->article_small->prepare($item)];

        return true;
    }
}