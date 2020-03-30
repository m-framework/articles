<?php

namespace modules\articles\admin;

use m\module;
use m\i18n;
use m\registry;
use m\view;
use m\core;
use m\model;
use m\form;
use modules\articles\models\articles;
use modules\pages\models\pages;
use modules\users\models\users;
use modules\users\models\users_info;

class news_delete extends module {

    public function _init()
    {
        $article = new articles(!empty($this->get->news_delete) ? $this->get->news_delete : null);

        if (!empty($article->id) && !empty($this->user->profile) && $this->user->is_admin() && $article->destroy()) {
            // TODO: to set in $_SESSION a success
        }

        core::redirect('/' . $this->conf->admin_panel_alias . '/news');
    }
}