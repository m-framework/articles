<?php

namespace modules\articles\admin;

use m\module;
use m\core;
use m\registry;
use m\i18n;
use modules\articles\models\articles;
use modules\pages\models\pages;
use modules\users\models\users;
use modules\users\models\users_info;
use modules\users\models\visitors;
use modules\users\models\visitors_history;

class articles_json extends module {

    public function _init()
    {
        $arr = [
            'data' => [[],[]],
            'data_captions' => [],
            'lines_captions' => [
                i18n::get('New articles'),
                i18n::get('News'),
            ],
            'colors' => [
                '#20780e',
                '#0bb6c3',
            ],
        ];

        registry::set('is_ajax', true);

        $last_date = articles::call_static()->s(['date'], [], [1], ['date' => 'DESC'])->one();

        $from_date = strtotime('-7 days', empty($last_date) ? time() : strtotime(substr($last_date, 0, 10)));

        if (strtotime('today') - $from_date > 604800) {
            $from_date = strtotime('-7 days');
        }

        /**
         * Get articles
         */
        $articles = articles::call_static()
            ->s(['date'], ["date>'" . date('Y-m-d 00:00:00', $from_date) . "'"], [10000], ['date' => 'DESC'])
            ->all();

        if (!empty($articles) && is_array($articles))
            foreach ($articles as $article) {
                $date = date('d.m', strtotime(substr($article['date'], 0, 10)));
                $_date = date('Y-m-d', strtotime(substr($article['date'], 0, 10)));

                if (!isset($arr['data']['0'][$_date])) {
                    $arr['data']['0'][$_date] = 1;
                    $arr['data_captions'][$_date] = $date;
                }
                else {
                    $arr['data']['0'][$_date] += 1;
                }
            }


        /**
         * Get news
         */
        $news_alias = empty($this->conf->news_alias) ? 'news' : $this->conf->news_alias;

        $page = pages::call_static()->s([], ['address' => '/' . $news_alias])->obj();

        if (empty($page->id) && !empty($this->site->news_page)) {
            $page = new pages($this->site->news_page);
        }

        if (!empty($page->id)) {
            $articles = articles::call_static()
                ->s(
                    ['date'],
                    ["date>'" . date('Y-m-d 00:00:00', $from_date) . "'", 'page' => $page->id],
                    [10000],
                    ['date' => 'DESC']
                )
                ->all();

            if (!empty($articles) && is_array($articles))
                foreach ($articles as $article) {
                    $date = date('d.m', strtotime(substr($article['date'], 0, 10)));
                    $_date = date('Y-m-d', strtotime(substr($article['date'], 0, 10)));

                    if (!isset($arr['data']['1'][$_date])) {
                        $arr['data']['1'][$_date] = 1;
//                        $arr['data_captions'][$_date] = $date;
                    }
                    else {
                        $arr['data']['1'][$_date] += 1;
                    }
                }
        }

        for ($day = $from_date; $day <= strtotime(date('Y-m-d')); $day = $day+86400) {
            $day_date = date('d.m', $day);
            $date = date('Y-m-d', $day);

            if (!isset($arr['data']['0'][$date])) {
                $arr['data']['0'][$date] = 0;
                $arr['data_captions'][$date] = $day_date;
            }

            if (!isset($arr['data']['1'][$date])) {
                $arr['data']['1'][$date] = 0;
            }
        }

        ksort($arr['data']['0']);
        ksort($arr['data']['1']);
        ksort($arr['data_captions']);

        $arr['data']['0'] = array_values($arr['data']['0']);
        $arr['data']['1'] = array_values($arr['data']['1']);
        $arr['data_captions'] = array_values($arr['data_captions']);

        core::out((object)$arr);
    }
}
