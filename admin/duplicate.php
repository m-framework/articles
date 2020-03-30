<?php

namespace modules\articles\admin;

use m\module;
use m\i18n;
use m\core;
use modules\articles\models\articles;

class duplicate extends module {

    public function _init()
    {
        $article = new articles(!empty($this->get->duplicate) ? $this->get->duplicate : null);

        if (!empty($article->id) && $this->user->is_admin()) {

            $new_article = new articles();
            $new_article->import(array_merge(get_object_vars($article), [
                'id' => null,
                'published' => null,
                'author' => $this->user->profile,
                'language' => (string)$this->language_id,
                'title' => $article->title . ' - ' . i18n::get('a copy') . date(' Y-m-d H:i'),
            ]));
            $new_article->save();

            if (!$new_article->error()) {

                $alias = (int)$article->page == 5 ? 'news_edit' : 'edit';

                core::redirect('/' . $this->conf->admin_panel_alias . '/articles/' . $alias . '/' . $new_article->id);
            }

            core::redirect('/' . $this->conf->admin_panel_alias . '/articles');
        }
    }
}