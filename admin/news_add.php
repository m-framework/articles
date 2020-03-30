<?php

namespace modules\articles\admin;

use m\registry;
use m\i18n;
use m\view;

class news_add extends news_edit {

    public function _init()
    {
        parent::_init();

        view::set('page_title', '<h1><i class="fa fa-file-text-o"></i> ' . i18n::get('To add news item') . '</h1>');
        registry::set('title', i18n::get('To add news item'));

        registry::set('breadcrumbs', [
            '/' . $this->conf->admin_panel_alias . '/articles' => i18n::get('Articles'),
            '/' . $this->conf->admin_panel_alias . '/articles/news' => i18n::get('News'),
        ]);
    }
}
