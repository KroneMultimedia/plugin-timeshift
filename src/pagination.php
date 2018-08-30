<?php

namespace KMM\Timeshift;

// WordPress-Stuff
header('Content-Type: application/json');
define('WP_USE_THEMES', false);
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

if (! isset($_GET['post'])) {
    return;
}
$prod_post = get_post($_GET['post']);

$i18n = 'kmm-timeshift';
load_plugin_textdomain(
    $i18n,
    false,
    dirname(plugin_basename(__FILE__)) . '/languages'
);
$core = new Core($i18n);

// pagination
$pagination = $core->get_paginated_links($prod_post, $_GET['timeshift_page']);
echo $pagination;

// load the next rows & render
$start = ($_GET['timeshift_page'] - 1) * $core->timeshift_posts_per_page;
$rows = $core->get_next_rows($prod_post, $start);
$timeshift_table = $core->render_metabox_table($prod_post, $rows);
echo $timeshift_table;