<?php

namespace modules\articles\models;

use m\model;

class articles_tags extends model
{
    public $_table = 'articles_tags';

    protected $fields = [
        'id' => 'int',
        'site' => 'int',
        'language' => 'int',
        'tag' => 'varchar'
    ];
}
