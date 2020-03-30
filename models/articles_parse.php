<?php

namespace modules\articles\models;

use m\config;
use m\model;
use m\registry;
use modules\pages\models\pages;

class articles_parse extends model
{
    protected $_sort = ['sequence' => 'ASC'];

    public $fields = [
        'id' => 'int',
        'site' => 'int',
        'host' => 'varchar',
        'source_page' => 'varchar',
        'pages' => 'int',
        'destination_page' => 'int',
        'container_mask' => 'varchar',
        'item_mask' => 'varchar',
        'title_mask' => 'varchar',
        'description_mask' => 'varchar',
        'date_mask' => 'varchar',
        'text_mask' => 'varchar',
        'source_mask' => 'varchar',
        'source_mask2' => 'varchar',
        'images_mask' => 'varchar',
        'meta_image_mask' => 'varchar',
        'meta_title_mask' => 'varchar',
        'videos_mask' => 'varchar',
        'exclude_blocks_mask' => 'varchar',
        'last_parse' => 'timestamp',
        'period' => 'int',
        'sequence' => 'int',
        'active' => 'tinyint',
    ];

}
