<?php
/**
 * Plugin Name: Rannk Posts Push
 * Description: Push the posts to the main site
 * Version: 1.0
 * Author: Rannk Deng
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License version 2, as published by the Free Software Foundation.  You may NOT assume
 * that you can use any other version of the GPL.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @copyright Copyright (c) 2011, Bill Erickson
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */
require_once ("lib/RkPostsPush.php");

//插件启用时检测数据库
register_activation_hook( __FILE__, 'posts_push_install');
function posts_push_install() {
    global $wpdb;
    $sql = "CREATE TABLE IF NOT EXISTS `".$wpdb->base_prefix."rk_posts_push_records` (
              `post_id` int(11) NOT NULL,
              `current_site_id` int(11) NOT NULL,
              `type` varchar(50) CHARACTER SET latin1 NOT NULL DEFAULT 'cn',
              `master_post_id` int(11) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $wpdb->query($sql);
}

add_action( 'add_meta_boxes', 'myplugin_add_custom_box' );
function myplugin_add_custom_box() {
    // 不显示在主站和英文站点， 英文站点默认2
    if(get_current_blog_id() < 3 ||  ! user_can(wp_get_current_user(), "publish_posts"))
        return;

    $p_obj = get_post()->to_array();
    if($p_obj['post_type'] != "post")
        return;

    add_meta_box(
        'posts push',
        "文章推送",
        'rk_posts_push_inner_custom_box',
        null, 'side','high'
    );
}

function rk_posts_push_inner_custom_box($post) {
    $value = get_post_meta( $post->ID, '_rk_posts_push_value_key', true );
    $checked = "";
    if($value) {
        $checked = "checked";
    }
    echo "<input type='checkbox' name='rk_posts_p' value='1' $checked> 勾选,点击发布后会推送此文章到主站";
}

add_action('save_post', 'rk_posts_push_save_data');

function rk_posts_push_save_data($post_id) {
    if(!$_POST['post_type'])
        return;

    if ( 'post' == $_POST['post_type'] ) {
        if ( ! current_user_can( 'publish_posts', $post_id ) ){
            return;
        }

        if($_POST['rk_posts_p']) {

            update_post_meta($post_id, '_rk_posts_push_value_key', 1);
            $rkPPObj = new RkPostsPush(get_current_blog_id());
            if($rkPPObj->pushPostToMasterSite($post_id)) {
                echo "yes";
            }else{
                echo "no";
            }

        }
    }

}

