<?php

namespace modules\articles\admin;

use m\module;
use m\view;
use m\i18n;
use modules\admin\admin\overview_data;

class tags extends module
{
    public function _init()
    {
        view::set('content', overview_data::items(
            'modules\articles\models\articles_tags',
            [
                'id' => i18n::get('Id'),
                'tag' => i18n::get('Tag'),
            ],
            [],
            $this->view->tags_overview,
            $this->view->tags_overview_item
        ));

        view::set_css($this->module_path . '/css/tags_overview.css');
    }
}
