<?php

namespace modules\articles\admin;

use m\module;
use m\core;
use modules\articles\models\articles;

class delete extends module {

    public function _init()
    {
        $article = new articles(!empty($this->get->delete) ? $this->get->delete : null);

        if (!empty($article->id) && !empty($this->user->profile)
            && ((int)$article->author == $this->user->profile || $this->user->is_admin()) && $article->destroy()) {
            core::redirect($this->config->previous);
        }
    }
}