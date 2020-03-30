<?php

namespace modules\articles\client;

use libraries\parser\parser;
use m\config;
use m\core;
use m\functions;
use m\i18n;
use m\logs;
use m\module;
use m\multi_threads;
use m\registry;
use m\view;
use modules\articles\models\articles;
use modules\articles\models\articles_data;
use modules\articles\models\articles_parse;

class parser_news extends module
{
    private $threads = 4;

    private static $months = [
        'січня' => '01',
        'січень' => '01',
        'січ' => '01',
        'январь' => '01',
        'января' => '01',
        'лютий' => '02',
        'лютого' => '02',
        'лют' => '02',
        'февраль' => '02',
        'февраля' => '02',
        'березень' => '03',
        'березеня' => '03',
        'бер' => '03',
        'март' => '03',
        'марта' => '03',
        'квітень' => '04',
        'квітня' => '04',
        'квіт' => '04',
        'апрель' => '04',
        'апреля' => '04',
        'травень' => '05',
        'травня' => '05',
        'трав' => '05',
        'май' => '05',
        'мая' => '05',
        'червень' => '06',
        'червня' => '06',
        'черв' => '06',
        'июнь' => '06',
        'июня' => '06',
        'липень' => '07',
        'липня' => '07',
        'лип' => '07',
        'июль' => '07',
        'июля' => '07',
        'серпень' => '08',
        'серпня' => '08',
        'серп' => '08',
        'август' => '08',
        'августа' => '08',
        'вересень' => '09',
        'вересня' => '09',
        'вер' => '09',
        'сентябрь' => '09',
        'сентября' => '09',
        'жовтень' => '10',
        'жовтня' => '10',
        'жовт' => '10',
        'октябрь' => '10',
        'октября' => '10',
        'листопад' => '11',
        'листопада' => '11',
        'лист' => '11',
        'ноябрь' => '11',
        'ноября' => '11',
        'грудень' => '12',
        'грудня' => '12',
        'груд' => '12',
        'декабрь' => '12',
        'декабря' => '12',
    ];

    public function _init()
    {
        $articles_parse = articles_parse::call_static()
            ->s([], [
                'active' => 1,
                [['UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(`last_parse`) > `period`'], ['last_parse' => null]],
            ], [$this->threads])
            ->all('object');

        if (empty($articles_parse)) {
            die();
        }

        config::set('db_logs', null);

        /**
         * for test only
         */
//        articles::call_static()->truncate();
//        articles_data::call_static()->truncate();

        $parse_threads = [];

        foreach ($articles_parse as $article_parse) {

            $base_url = parse_url($article_parse->source_page, PHP_URL_SCHEME) . '://'
                . parse_url($article_parse->source_page, PHP_URL_HOST);

            $parse_threads[$article_parse->id] = new multi_threads([
                'callback' => '\modules\articles\client\parser_news::parse_source',
                'args' => [
                    'article_parse' => $article_parse,
                    'base_url' => $base_url,
                ]
            ]);

            /**
             * Update `last_parse`
             */
            articles_parse::call_static()
                ->u(['last_parse' => date('Y-m-d H:i:s')], ['id' => $article_parse->id]);

            $parse_threads[$article_parse->id]->start();
        }

        if (!empty($parse_threads)) {
            foreach ($parse_threads as $parse_thread) {
                $parse_thread->join();
            }
        }
    }

    /**
     *
     * @param $args
     * @return bool
     */
    public static function parse_source($args)
    {
        if (empty($args) || empty($args['article_parse']) || empty($args['base_url'])) {
            return false;
        }

        $links = [];

        for ($p = 1; $p <= (int)$args['article_parse']->pages; $p++) {

            $list_url = str_replace('{n}', $p, $args['article_parse']->source_page);

//            $list_page = file_get_contents($list_url);
            $list_page = parser::curl($list_url, [
                'use_agent' => 1,
                'use_proxy' => 1,
//                'verbose' => 1,
//                'use_header' => 1,
                'use_cookie' => 1,
            ]);

            $list_page = mb_convert_encoding($list_page, 'UTF-8');

            $news_items_links = [];
            preg_match_all('!' . $args['article_parse']->item_mask . '!si', $list_page, $news_items_links);

            if (empty($news_items_links) || empty($news_items_links['1']) || !is_array($news_items_links['1'])) {
                continue;
            }

            foreach ($news_items_links['1'] as $news_item_link) {

                $news_item_link = stripslashes($news_item_link);

                // Removing a hash-tag from URL
                if (!(strrpos($news_item_link, '#') === false)) {
                    $news_item_link = substr($news_item_link, 0, strrpos($news_item_link, '#'));
                }

                if (strpos($news_item_link, 'http') === false || strpos($news_item_link, $args['article_parse']->host) === false) {
                    $news_item_link = $args['base_url'] . $news_item_link;
                }

                $article = articles::call_static()
                    ->s([], ['site' => 1, 'source' => $news_item_link])
                    ->obj();

                if (empty($article) || empty($article->id)) {

                    $article = new articles();
                    $article->save([
                        'site' => registry::get('site')->id,
                        'source' => $news_item_link,
                    ]);
                }

                $links[$article->id] = $news_item_link;
            }
        }

        if (empty($links)) {
            return false;
        }

        $links_chunks = array_chunk($links, parser::$threads, true);

        foreach ($links_chunks as $chunk => $links_chunk) {

//            logs::set('Send parser::multi_threads chunk ' . $chunk . ', ' . count($liks_chunk) . ' of links from '
//                . count($links) . '. Project: ' . $args['project']->id . ', system:' . $args['system']['id']);

            parser::multi_threads([
                'urls' => $links_chunk,
                'callback' => '\modules\articles\client\parser_news::parse_callback',
                'use_agent' => 1,
                'use_proxy' => 1,
                'verbose' => 1,
                'use_header' => 1,
                'use_cookie' => 1,
                'callback_options' => [
                    'article_parse' => $args['article_parse'],
                    'base_url' => $args['base_url'],
                ],
            ]);

            sleep(rand(15, 30));
        }
    }

    /**
     *
     *
     * @param $id
     * @param $header
     * @param $data
     * @param array $options :
     *     - (object) article_parse
     *     - (string) base_url
     * @return bool
     */
    public static function parse_callback($id, $header, $data, array $options = null)
    {
        if (empty($data)) {
            return false;
        }

        stream_context_set_default([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $article_parse = $options['article_parse'];

        if (!(strpos($data, 'content="text/html; charset=windows-1251"') === false) || mb_detect_encoding($data, 'windows-1251')) {
            $data = mb_convert_encoding($data, 'UTF-8', 'Windows-1251');
        }

        $container = '';

        if (!empty($article_parse->container_mask)) {
            preg_match('!' . $article_parse->container_mask . '!si', $data, $container_arr);

            if (!empty($container_arr) && !empty($container_arr['1'])) {
                $container = $container_arr['1'];
            }
        }

        preg_match('!' . $article_parse->title_mask . '!si', empty($container) ? $data : $container, $title_arr);

        preg_match('!' . $article_parse->description_mask . '!si', $data, $description_arr);
        preg_match('!' . $article_parse->date_mask . '!si', $data, $date_arr);

        preg_match('!' . $article_parse->text_mask . '!si', empty($container) ? $data : $container, $text_arr);

        if (!empty($article_parse->source_mask)) {
            preg_match('!' . $article_parse->source_mask . '!si', empty($container) ? $data : $container, $source_arr);
        }
        if (!empty($article_parse->meta_image_mask)) {
            preg_match('!' . $article_parse->meta_image_mask . '!si', $data, $meta_image_mask_arr);
        }
        if (!empty($article_parse->meta_title_mask)) {
            preg_match('!' . $article_parse->meta_title_mask . '!si', $data, $meta_title_arr);
        }
        if (!empty($article_parse->source_mask2)) {
            preg_match('!' . $article_parse->source_mask2 . '!si', empty($container) ? $data : $container, $source_arr2);
        }
        if (!empty($article_parse->images_mask)) {
            preg_match_all('/' . $article_parse->images_mask . '/i', empty($container) ? $data : $container, $images_arr);
        }
        if (!empty($article_parse->videos_mask)) {
            preg_match_all('/' . $article_parse->videos_mask . '/i', empty($container) ? $data : $container, $videos_arr);
        }

        $text = empty($text_arr['1']) ? '' : $text_arr['1'];

        if (!empty($text) && !empty($article_parse->exclude_blocks_mask)) {
            $text = preg_replace("!" . $article_parse->exclude_blocks_mask . "!si", "\n", $text);
        }

        $text = preg_replace('!<script.*?>.*?<\/script>!si', '', $text);

        $text = preg_replace('!([\n]{3,})!si', '', $text);

        $text = str_replace(
            ["  ","\r\n\r\n","\n\n","&nbsp;"],
            [' ',"\n","\n", ' '],
            trim(htmlspecialchars_decode(strip_tags($text, '<a><p>')))
        );

//        $text = nl2br($text);
        $text = trim($text);

//        $article = articles::call_static()
//            ->s([], ['site' => 1, 'source' => $options['news_item_link']])
//            ->obj();

        $article = new articles($id);

        $news_item_link = $article->source;

//        $date = date('Y-m-d H:i:s');
        $date = null;
        if (!empty($date_arr['1'])) {

            if (is_array($date_arr['1'])) {
                $date_arr['1'] = $date_arr['1']['0'];
            }

            $date_arr['1'] = mb_strtolower($date_arr['1'], 'UTF-8');
            $date_arr['1'] = mb_strtolower($date_arr['1'], 'UTF-8');
            $date_arr['1'] = trim(strip_tags(htmlspecialchars_decode(html_entity_decode($date_arr['1']))));

            $date_arr['1'] = str_ireplace(array_keys(static::$months), array_values(static::$months), $date_arr['1']);
            $date_arr['1'] = trim(preg_replace("/[\s]+/i", ' ', $date_arr['1']));

            logs::set($id . '. Tried to parse date: "' . $date_arr['1'] . '" from page ' . $news_item_link);

            // urkainer date template
            if (preg_match("![0-9]{2} [0-9]{2} [0-9]{4} [0-9]{2}\:[0-9]{2}!si", $date_arr['1'])) {
                $date_time = strtotime(preg_replace("!([0-9]{2}) ([0-9]{2}) ([0-9]{4}) ([0-9]{2}\:[0-9]{2})!si", '$3-$2-$1 $4:00', $date_arr['1']));
            }
            // urkainer date template
            else if (preg_match("![0-9]{1} [0-9]{2} [0-9]{4} [0-9]{2}\:[0-9]{2}!si", $date_arr['1'])) {
                $date_time = strtotime(preg_replace("!([0-9]{1}) ([0-9]{2}) ([0-9]{4}) ([0-9]{2}\:[0-9]{2})!si", '$3-$2-0$1 $4:00', $date_arr['1']));
            }
            // uezd date template
            else if (preg_match("![0-9]{2}[\s]+[0-9]{2},[\s]+[0-9]{4}!si", $date_arr['1'])) {
                $date_time = strtotime(preg_replace("!([0-9]{2})[\s]+([0-9]{2}),[\s]+([0-9]{4})!si", '$3-$1-$2 00:00:00', $date_arr['1']));
            }
            // uezd date template
            else if (preg_match("![0-9]{2}[\s]+[0-9]{1},[\s]+[0-9]{4}!si", $date_arr['1'])) {
                $date_time = strtotime(preg_replace("!([0-9]{2})[\s]+([0-9]{1}),[\s]+([0-9]{4})!si", '$3-$1-0$2 00:00:00', $date_arr['1']));
            }
            // mynizhyn.com date template
            else if (preg_match("![0-9]{2}\.[0-9]{2}\.[0-9]{4} [0-9]{2}\:[0-9]{2}!si", $date_arr['1'])) {
                $date_time = strtotime(preg_replace("!([0-9]{2})\.([0-9]{2})\.([0-9]{4}) ([0-9]{2}\:[0-9]{2})!si", '$1.$2.$3 $4:00', $date_arr['1']));
            }
            // mynizhyn.com date template
            else if (preg_match("![0-9]{1}\.[0-9]{2}\.[0-9]{4} [0-9]{2}\:[0-9]{2}!si", $date_arr['1'])) {
                $date_time = strtotime(preg_replace("!([0-9]{1})\.([0-9]{2})\.([0-9]{4}) ([0-9]{2}\:[0-9]{2})!si", '0$1.$2.$3 $4:00', $date_arr['1']));
            }
            // mon.gov.ua date template
            else if (preg_match("![0-9]{2} [0-9]{2} [0-9]{4} року о [0-9]{2}\:[0-9]{2}!si", $date_arr['1'])) {
                $date_time = strtotime(preg_replace("!([0-9]{2}) ([0-9]{2}) ([0-9]{4}) року о ([0-9]{2}\:[0-9]{2})!si", '$1.$2.$3 $4:00', $date_arr['1']));
            }
            else {
                $date_time = strtotime($date_arr['1']);
            }

            if (!empty($date_time)) {
                $date = date('Y-m-d H:i:s', $date_time);
            }
        }

        if ($date === null) {
            logs::set($id . '. Empty date:' . $news_item_link);
            $article->destroy();
            return false;
        }

        if (empty($title_arr['1'])) {

            if (!empty($meta_title_arr) && !empty($meta_title_arr['1'])) {
                $title_arr['1'] = $meta_title_arr['1'];
            }
            else {
                logs::set('Empty title: ' . $news_item_link . ' container length: ' . strlen($container));
                $article->destroy();
                return false;
            }
        }

        $title = '';
        if (!empty($title_arr['1'])) {
            $title_arr['1'] = preg_replace("/[\s]{2,}/i", ' ', $title_arr['1']);
            $title = trim(htmlspecialchars_decode(strip_tags($title_arr['1'])));
        }

        $description = '';
        if (!empty($description_arr['1'])) {
            $description_arr['1'] = preg_replace("/[\s]+/i", ' ', $description_arr['1']);
            $description = trim(htmlspecialchars_decode(strip_tags($description_arr['1'])));
        }

        if (!empty($source_arr) && !empty($source_arr['1'])) {
            $source_arr['1'] = is_array($source_arr['1']) && !empty($source_arr['1']['0']) ? $source_arr['1']['0'] : $source_arr['1'];
            $news_item_link = $source_arr['1'];
        }

        if (!empty($source_arr2) && !empty($source_arr2['1'])) {
            $source_arr2['1'] = is_array($source_arr2['1']) && !empty($source_arr2['1']['0']) ? $source_arr2['1']['0'] : $source_arr2['1'];
            $news_item_link = $source_arr2['1'];
        }

        if (empty($images_arr['1'])) {


            if (!empty($meta_image_mask_arr) && !empty($meta_image_mask_arr['1'])) {

                $meta_image_mask_arr['1'] = is_array($meta_image_mask_arr['1']) && !empty($meta_image_mask_arr['1']['0']) ? $meta_image_mask_arr['1']['0'] : $meta_image_mask_arr['1'];

                $images_arr['1'] = [$meta_image_mask_arr['1']];
            }
            else {
                logs::set('Empty images: ' . $news_item_link);
                $article->destroy();
                return false;
            }
        }

        if (empty($description) && !empty($title)) {
            $description = $title;
        }
//        if (empty($description)) {
//            logs::set('Empty description: ' . $news_item_link);
//            $article->destroy();
//            return false;
//        }

        if (empty($text) && empty($description) && empty($images_arr['1']) && empty($videos_arr['1'])) {
            logs::set('Empty text: ' . $news_item_link);
            $article->destroy();
            return false;
        }

//        core::out([
//            'date' => $date,
//            'title' => $title,
//            'description' => $description,
//            'text' => $text,
//            'images' => empty($images_arr) && empty($images_arr['1']) ? [] : $images_arr['1'],
//            'videos' => empty($videos_arr) && empty($videos_arr['1']) ? [] : $videos_arr['1'],
//            'source' => $news_item_link,
//        ]);

//        exit(print_r($article));

        $link = '<a href="' . $news_item_link . '" class="full-text-link">' . i18n::get('Read full text article by this link') . '</a>';
        /**
         * TSN.ua allows to post less then 1500 characters of article with source-link not less then in first paragraph
         * https://tsn.ua/rules
         */
        if (!(strpos($news_item_link, 'tsn.ua') === false)) {
            if (empty($text)) {
                $article->destroy();
                return false;
            }
            $text = mb_substr($text, 0, 1500, 'UTF-8') . ' ...<br><br>' . $link;
        }

        /**
         * Unian.ua allows to post only first paragraph
         * https://www.unian.ua/ (footer)
         */
        if (!(strpos($news_item_link, 'unian.ua') === false)) {
            if (empty($text)) {
//                $article->destroy();
//                return false;
            }
            preg_match('!(<p>.*?<\/p>.*?<p>.*?<\/p>)!si', $text, $uniain_paragraphs);
            if (!empty($uniain_paragraphs) && !empty($uniain_paragraphs['1'])) {
                $text = '<p>' . strip_tags($uniain_paragraphs['1']) . '</p>...<br><br>' . $link;
            }
        }




        $alias = mb_strtolower(transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $title), 'UTF-8');
        $alias = trim(preg_replace("/[^a-z0-9]+/", '-', $alias), '-');

        $similar_article_id = articles::call_static()
            ->s(['id'], [
                [['alias' => $alias], ['source' => $news_item_link]],
                'page' => $article_parse->destination_page,
                'language' => 169,
                'published' => 1,
            ])
            ->one();

        if (!empty($similar_article_id) && (int)$similar_article_id !== (int)$id) {
            logs::set('Similar article with same source already parsed: ' . $news_item_link);
            return false;
        }

        $article->save([
            'site' => 1,
            'page' => $article_parse->destination_page,
            'alias' => $alias,
//            'image' => null,
            'title' => $title,
            'description' => $description,
            'text' => $text,
            'date' => $date,
            'language' => 169,
//            'published' => 1,
            'source' => $news_item_link,
        ]);

        if (empty($article->id)) {
            logs::set($id . '. Error from DB: ' . $article->error());
            return false;
        }

        $data_path = config::get('data_path') . registry::get('site')->id . date('/Y/m/d/', strtotime($date));

        $data = articles_data::call_static()
            ->s([], ['site' => 1, 'article' => $id])
            ->all('object');

        $articles_data_arr = [];

        if (!empty($data)) {
            foreach ($data as $article_data) {

                if (!is_file(config::get('root_path') . $article_data->data_path)) {
                    $article_data->destroy();
                    continue;
                }

                $articles_data_arr[] = $article_data->data_path;
            }
        }

        /**
         * Save parsed photos
         *
         * todo: move to separated static class
         */
        if (!empty($images_arr['1']) && is_array($images_arr['1'])) {

            foreach ($images_arr['1'] as $img_n => $image_path) {

                $basename = pathinfo($image_path, PATHINFO_BASENAME);
                $ext = pathinfo($image_path, PATHINFO_EXTENSION);

                $new_image_name = md5($id . $basename) . '.' . $ext;

                $new_image_path = $data_path . $new_image_name;

                if (in_array($new_image_path, $articles_data_arr) && is_file(config::get('root_path') . $new_image_path)) {

                    /**
                     * TODO: move to separate method
                     */
                    if (empty($article->image) && !empty($date) && !empty($article->title)) {
                        articles::call_static()
                            ->u(['image' => $new_image_path, 'date' => $date, 'published' => 1], ['id' => $id]);
                        $article->image = $new_image_path;
                        //logs::set('Tried to save an image ' . $new_image_path . ' for article ' . $id . ' via UPDATE query');
                    }

                    //logs::set('This image \'' . $new_image_path . '\' already parsed for this article: ' . $id);
                    continue;
                }

//                logs::set('Trying download image \'' . $image_path . '\' for article ' . $id);

                $image_path = trim($image_path);

                if (strpos($image_path, 'http') === false) {
                    $image_path = $options['base_url'] . $image_path;
                }

//                $image = file_get_contents($image_path);
                $image = parser::curl($image_path, [
                    'use_agent' => 1,
                    'use_proxy' => 1,
                    'use_cookie' => 1,
                ]);

                /**
                 * Second try via cUrl
                 */
                if (empty($image)) {

                    $headers = get_headers($image_path, 1);

                    if (!empty($headers['0']) && (trim($headers['0']) == 'HTTP/1.1 200 OK' || !(strpos($headers['0'], '200 OK') === false))) {

                        //logs::set('Trying again download image \'' . $image_path . '\' for article ' . $id);

                        $image = parser::curl($image_path, [
                            'use_agent' => 1,
                            'use_proxy' => 1,
                            'use_cookie' => 1,
                        ]);
                    }
                }

                /**
                 * Third try via file_get_contents()
                 */
                if (empty($image)) {

                    //

                    $headers = get_headers($image_path, 1);

                    if (!empty($headers['0']) && (trim($headers['0']) == 'HTTP/1.1 200 OK' || !(strpos($headers['0'], '200 OK') === false))) {

                        //logs::set('Trying download via file_get_contents(\'' . $image_path . '\') for article ' . $id);

                        $image = file_get_contents($image_path);
                    }
                }

                if (empty($image)) {
                    logs::set('Can\'t parse image: ' . $image_path);
                    continue;
                }

                if(!is_dir(config::get('root_path') . $data_path)) {
                    mkdir(config::get('root_path') . $data_path, 0755, true);
                }

                file_put_contents(config::get('root_path') . $new_image_path, $image);

                $mime = mime_content_type(config::get('root_path') . $new_image_path);

                if (strpos($mime, 'image/') === false) {
                    unlink(config::get('root_path') . $new_image_path);
                    logs::set('Downloaded file isn\'t valid image: ' . $new_image_path);
                    continue;
                }
                else {
//                    logs::set('Image for article ' . $id . ' successfully downloaded : ' . $new_image_path);
                }

                articles_data::call_static()->save([
                    'site' => 1,
                    'article' => $id,
                    'type' => 1,
                    'data_path' => $new_image_path,
                ]);

                /**
                 * TODO: move to separate method
                 */
                if (((int)$img_n == 0 || empty($article->image) && !empty($article->title))
                    && is_file(config::get('root_path') . $new_image_path) && !empty($date)) {

                    articles::call_static()
                        ->u(['image' => $new_image_path, 'date' => $date, 'published' => 1], ['id' => $id]);
                    //logs::set('Tried to save an image ' . $new_image_path . ' for article ' . $id . ' via UPDATE query');

                    $article->image = $new_image_path;
                }
            }
        }

        if (!empty($article->image) && !empty($article->title) && !empty($article->alias)
            && empty($article->published) && !empty($date)) {
            articles::call_static()->u(['date' => $date, 'published' => 1], ['id' => $id]);
        }
//        else if (empty($article->title) && empty($article->alias) && empty($article->text)) {
//            articles::call_static()->d(['id' => $id]);
//        }

        /**
         * Save parsed videos
         *
         * todo: move to separated static class
         */
        if (!empty($videos_arr['1']) && is_array($videos_arr['1'])) {
            foreach ($videos_arr['1'] as $video_path) {

                $ext = pathinfo($video_path, PATHINFO_EXTENSION);

                if (!(strpos($video_path, 'http') === false) && in_array($video_path, $articles_data_arr)) {
                    logs::set('This video link \'' . $video_path . '\' already saved for this article: ' . $id);
                    continue;
                }

                if (!empty($ext) && mb_strtolower($ext, 'UTF-8') == 'mp4') {

                    if (strpos($video_path, 'http') === false) {
                        $video_path = $options['base_url'] . $video_path;
                    }

                    $video = parser::curl($video_path, [
                        'use_agent' => 1,
                        'use_proxy' => 1,
                        'use_cookie' => 1,
                    ]);

                    $new_video_name = md5(microtime()) . '.' . $ext;

                    if(!is_dir(config::get('root_path') . $data_path)) {
                        mkdir(config::get('root_path') . $data_path, 0755, true);
                    }

                    file_put_contents(config::get('root_path') . $data_path . $new_video_name, $video);

                    $video_path = $data_path . $new_video_name;
                }

                articles_data::call_static()->save([
                    'site' => 1,
                    'article' => $id,
                    'type' => 2,
                    'data_path' => $video_path,
                ]);
            }
        }

        return true;
    }
}

