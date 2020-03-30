<?php

namespace modules\articles\models;

use m\config;
use m\model;
use m\registry;
use modules\pages\models\pages;

class articles_data extends model
{
    protected $_sort = ['sequence' => 'ASC'];

    public $fields = [
        'id' => 'int',
        'site' => 'int',
        'article' => 'int',
        'type' => 'int',
        'data_path' => 'varchar',
        'title' => 'varchar',
        'date' => 'timestamp',
        'sequence' => 'int',
    ];

    public function _autoload_path()
    {
        $this->path = '';

        /**
         * TODO: download photo to local storage from outside servers
         */

        if (empty($this->photo)) {
            return $this->path;
        }

        $this->path = config::get('data_path') . $this->site . '/' .
            str_replace('-', '/', substr($this->date, 0, 10)) . '/' . $this->photo;

        if (!is_file(config::get('root_path') . $this->path)) {
            $this->path = '';
        }

        return $this->path;
    }

    public function _before_destroy()
    {
        if (is_file(config::get('root_path') . $this->path)) {
            unlink(config::get('root_path') . $this->path);
        }

        return true;
    }
}
