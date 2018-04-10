<?php

namespace KMM\Timeshift;

class Core
{
    private $plugin_dir;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->plugin_dir = plugin_dir_url(__FILE__) . '../';
        $this->add_filters();
    }
    public function add_filters() {

    }

}
