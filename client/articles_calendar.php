<?php

namespace modules\articles\client;

use m\config;
use m\core;
use m\module;
use m\registry;
use m\view;
use modules\pages\models\pages;

class articles_calendar extends module {

    public static $_name = '*Articles calendar*';

    protected $css = [
        '/css/articles_calendar.css',
    ];

    public function _init()
    {
        if (isset($this->view->{$this->name})) {
            view::set($this->name, $this->view->{$this->name}->prepare([
                'current_date' => registry::has('by_date') ? registry::get('by_date') : null,
                'min_year' => date('Y', strtotime('-1 year')),
                'max_year' => date('Y'),
                'max_date' => date('Y-m-d'),
                'disabled_alert' => '*Date can\'t be in future*',
                'page_address' => $this->page->path,
            ]));
        }
    }
}