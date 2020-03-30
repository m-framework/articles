<?php

namespace modules\articles\models;

use m\core;
use m\model;
use m\registry;
use modules\pages\models\pages;

class articles extends model
{
    public $_table = 'articles';
    protected $_sort = ['sequence' => 'ASC', 'date' => 'DESC'];

    public $fields = [
        'id' => 'int',
        'site' => 'int',
        'author' => 'int',
        'page' => 'int',
        'alias' => 'varchar',
        'image' => 'varchar',
        'title' => 'varchar',
        'description' => 'text',
        'text' => 'longtext',
        'date' => 'timestamp',
        'date_start' => 'timestamp',
        'date_end' => 'timestamp',
        'language' => 'int',
        'tags' => 'varchar',
        'source' => 'varchar',
        'sequence' => 'int',
        'disallow_comments' => 'tinyint',
        'published' => 'tinyint',
    ];

    public function _before_destroy()
    {
        if (empty($this->id)) {
            return true;
        }

        $data = articles_data::call_static()
            ->s([], ['site' => registry::get('site')->id, 'article' => $this->id], [1000])
            ->all('object');

        if (empty($data)) {
            return true;
        }

        foreach ($data as $data_item) {
            $data_item->destroy();
        }

        return true;
    }

    public function _after_save()
    {
        if (empty($this->id) || empty($this->text)) { //  empty($this->title) && empty($this->description) &&
            return true;
        }

        if (!registry::has('tags') && !empty($this->site) && !empty($this->language)) {
            $tags_arr = [];

            $tags = articles_tags::call_static()
                ->s([],['site' => $this->site, [['language' => $this->language], ['language' => null]]],[10000])
                ->all();

            if (empty($tags)) {
                return true;
            }

            foreach ($tags as $tag) {
                $tags_arr[$tag['id']] = $tag['tag'];
            }

            registry::set('tags', $tags_arr);
        }

        $curr_tags = [];

        foreach ((array)registry::get('tags') as $tag_id => $tag) {
            if (
//                !(mb_stripos($this->title, $tag, null, 'UTF-8') === false)
//                || !(mb_stripos($this->description, $tag, null, 'UTF-8') === false)
//                ||
                !(stripos($this->text, $tag) === false)
            ) {
                $curr_tags[] = $tag_id;
                continue;
            }
        }

        $this->u([
            'tags' => empty($curr_tags) ? null : implode(',', $curr_tags),
            'date' => $this->date, // prevent CURRENT_TIMESTAMP
        ], ['id' => $this->id]);

        return true;
    }

    public function _before_save()
    {
        /**
         * Split 4-2 firsts of sentences from text
         */
        if (empty($this->description) && !empty($this->text)) {
            $text = strip_tags(htmlspecialchars_decode(stripslashes($this->text)));

            if (preg_match("/(.*?\..*?\..*?\..*?\.)/", $text)) {
                $text_res = preg_split("/(.*?\..*?\..*?\..*?\.)/", $text, NULL, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            } else if (preg_match("/(.*?\..*?\..*?\.)/", $text)) {
                $text_res = preg_split("/(.*?\..*?\..*?\.)/", $text, NULL, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            } else if (preg_match("/(.*?\..*?\.)/", $text)) {
                $text_res = preg_split("/(.*?\..*?\.)/", $text, NULL, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
            }
            if (!empty($text_res['0'])) {
                $this->description = $text_res['0'];
            }
        }

        return true;
    }

    public function _autoload_path()
    {
        $this->path = '';

        if (!empty($this->page)) {
            $page = new pages($this->page);
            $this->path .= $page->get_path();
        }

        if (substr($this->path, strrpos($this->path, '/') + 1) !== $this->alias) {
            $this->path .= ($this->path !== '/' ? '/' : '') . $this->alias;
        }

        return $this->path;
    }

    public function _autoload_source_link()
    {
        $this->source_link = '';

        if (empty($this->source)) {
            $this->source_link = '';
        }
        else if (strpos($this->source, 'http') === false) {
            $this->source_link = $this->source;
        }
        else {
            $this->source_link = '<a href="' . $this->source . '">' . parse_url($this->source, PHP_URL_HOST) . '</a>';
        }

        return $this->source_link;
    }

    public function _autoload_beautiful_date()
    {
        if (empty($this->date)) {
            return '';
        }
        $time = strtotime($this->date);
        $this->beautiful_date = date('Y', $time) !== date('Y') ? strftime('%e %b %Y %H:%M', $time)
            : strftime('%e %b %H:%M', $time);
        return $this->beautiful_date;
    }

    public function _autoload_short_beautiful_date()
    {
        if (empty($this->date)) {
            return '';
        }
        $time = strtotime($this->date);
        $this->short_beautiful_date = date('Y', $time) !== date('Y') ? strftime('%e %b %Y', $time)
            : strftime('%e %b', $time);
        return $this->short_beautiful_date;
    }

    public function _override_description()
    {
        $this->description = htmlspecialchars_decode($this->description);

        if (preg_match("!<pre>(.*?)<\/pre>!si", $this->description, $pre_arr)) {
            $rplsmnt = nl2br(htmlspecialchars(str_replace("<br>", "\n", htmlspecialchars_decode($pre_arr['1']))), false);
            $this->description = str_replace($pre_arr['0'], '<pre>'.$rplsmnt.'</pre>', $this->description);
        }
    }

    public function _override_text()
    {
//        exit('_override_text');
        if (preg_match("/&lt;pre.*?&gt;(.*?)&lt;\/pre&gt;/si", $this->text, $pre_arr)) {
            $rplsmnt = (htmlspecialchars(str_replace("<br>", "\n", htmlspecialchars_decode(stripslashes($pre_arr['1'])))));
            $this->text = htmlspecialchars_decode(str_replace($pre_arr['0'], '<pre>'.$rplsmnt.'</pre>', $this->text));
        }
        else {
            $this->text = htmlspecialchars_decode(stripslashes($this->text));
        }
    }

    public function _autoload_published_checked()
    {
        $this->published_checked = empty($this->published) ? '' : ' checked';
        return $this->published_checked;
    }
}
