<?php
require_once("DbSet.php");

class RkPostsPush {

    var $current_site_id;
    var $post_id;
    var $site_type = "cn";
    var $insert_table;
    var $postmeta_table;
    var $term_table;
    var $term_rel_table;

    public function __construct($current_site_id) {
        global $wpdb;
        $lang = get_blog_option($current_site_id, "WPLANG");
        if($lang == "zh_CN") {
            $this->site_type = "cn";
            $this->insert_table = "posts";
            $this->postmeta_table = "postmeta";
            $this->term_table = "terms";
            $this->term_rel_table = "term_relationships";
        }else {
            $this->site_type = "en";
            $this->insert_table = "2_posts";
            $this->postmeta_table = "2_postmeta";
            $this->term_table = "2_terms";
            $this->term_rel_table = "2_term_relationships";
        }
        $this->current_site_id = ceil($current_site_id);
    }

    public function setSiteType($type) {
        $arr = array("cn", "en");
        if(in_array($type, $arr)) {
            $this->site_type = $type;
        }
    }

    public function pushPostToMasterSite($post_id) {
        $post_info = get_post($post_id, ARRAY_A);

        if(count($post_info)>0 && $post_info['ID'] && $post_info['post_parent'] == "0") {
            $this->post_id = $post_info['ID'];
            return $this->savePosts($post_info);
        }
    }

    public function savePosts($post_info) {
        global $wpdb;
        $_thumbnail_master_post_id = 0;

        if($this->current_site_id == 0 || !$this->post_id || !$this->site_type)
            return;

        // get post's all postmeta
        $sql = "select * from " . $wpdb->base_prefix . $this->current_site_id. "_postmeta where post_id=".$this->post_id;
        $postmeta_arr = $wpdb->get_results($sql, ARRAY_A);

        // get main post's thumbnail
        for($i=0;$i<count($postmeta_arr);$i++) {
            if($postmeta_arr[$i]['meta_key'] == "_thumbnail_id") {
                $_thumbnail_post_id = $postmeta_arr[$i]['meta_value'];
            }

            if($postmeta_arr[$i]['meta_key'] == "_dt_fancy_header_bg_image") {
                $arr = explode(";", $postmeta_arr[$i]['meta_value']);
                $background_image_id = str_replace("i:", "", $arr[1]);
                $background_image_id = $this->pushImage($background_image_id);
                $postmeta_arr[$i]['meta_value'] = str_replace($arr[1], "i:".$background_image_id, $postmeta_arr[$i]['meta_value']);
            }
        }

        if($_thumbnail_post_id > 0) {
            $_thumbnail_master_post_id =$this->pushImage($_thumbnail_post_id);
        }

        $post_exists = false;

        // main post push
        $post_record_row = $this->getPostRecord($this->post_id);

        // instance post table obj
        $postObj = new DbSet($this->insert_table, "ID", $post_record_row['master_post_id']);
        $postObj->setVars($post_info);

        if($postObj->actived()) {
            $postObj->setVar("post_status", ":skip:");
            $postObj->setVar("post_date_gmt", ":skip:");
            $postObj->setVar("post_name", ":skip:");
        }else {
            $postObj->setVar("ID", ":skip:");
            $postObj->setVar("post_status", "pending");
        }

        $exec = $postObj->update();

        if($exec) {
            $id = $postObj->getKeyId();
            if(!$post_record_row['master_post_id']) {
                $this->insertPostRecord($this->post_id, $id);
            }

            // update term
            $site_info = pathinfo(get_blog_option($this->current_site_id, "siteurl"));
            $slug = str_replace(".suis.com.cn", "", $site_info['basename']);
            $sql = "select * from " . $wpdb->base_prefix . $this->term_table." where slug='$slug'";
            $term_row = $wpdb->get_row($sql, ARRAY_A);
            if($term_row['term_id']) {
                $term_id = $term_row['term_id'];
            }else {
                $term_id = 1;
            }
            $sql = "select * from " . $wpdb->base_prefix.$this->term_rel_table." where object_id=$id";
            $rel_row = $wpdb->get_row($sql, ARRAY_A);
            if(!$rel_row['object_id']) {
                $sql = "insert into " . $wpdb->base_prefix.$this->term_rel_table." set object_id=$id, term_taxonomy_id=$term_id";
                $wpdb->query($sql);
            }

            // update post meta
            for($i=0;$i<count($postmeta_arr);$i++) {
                $v = $postmeta_arr[$i];
                if($v['meta_key'] == "_edit_lock" || $v['_edit_last']) {
                    continue;
                }

                if($v['meta_key'] == "_thumbnail_id") {
                    $value = $_thumbnail_master_post_id;
                }else {
                    $value = addslashes($v['meta_value']);
                }

                $sql = "select meta_id from " .$wpdb->base_prefix.$this->postmeta_table." where meta_key='". addslashes($v['meta_key'])."' and post_id=$id";
                $row = $row = $wpdb->get_row($sql, ARRAY_A);
                if($row['meta_id']) {
                    $sql = "update ".$wpdb->base_prefix.$this->postmeta_table." set meta_value='".$value."' where meta_key='". addslashes($v['meta_key'])."' and post_id=$id";
                }else {
                    $sql = "insert into ".$wpdb->base_prefix.$this->postmeta_table." set meta_value='".$value."',meta_key='". addslashes($v['meta_key'])."', post_id=$id";
                }

                $wpdb->query($sql);
            }


            return true;
        }
    }

    public function getPostRecord($post_id) {
        global $wpdb;

        $sql = "select * from " . $wpdb->base_prefix . "rk_posts_push_records
            where current_site_id={$this->current_site_id}
            and  post_id={$post_id}
            and type='{$this->site_type}'";

        return $wpdb->get_row($sql, ARRAY_A);
    }

    public function insertPostRecord($post_id, $master_post_id) {
        global $wpdb;
        $sql = "insert into " . $wpdb->base_prefix . "rk_posts_push_records set post_id={$post_id},
                    current_site_id={$this->current_site_id},
                    `type`='{$this->site_type}',
                    master_post_id={$master_post_id}";

        return $wpdb->query($sql);
    }

    public function pushImage($_thumbnail_post_id) {
        global $wpdb;
        if($_thumbnail_post_id > 0) {
            $sql = "select * from ". $wpdb->base_prefix . $this->current_site_id."_posts where ID=" . $_thumbnail_post_id;
            $row = $wpdb->get_row($sql, ARRAY_A);
            if($row['ID']) {
                $thumbnail_record_row = $this->getPostRecord($row['ID']);
                $thumbnail_post_obj = new DbSet($this->insert_table, "ID", $thumbnail_record_row['master_post_id']);
                if(!$thumbnail_post_obj->actived()) {

                    $thumbnail_post_obj->setVars($row);
                    $thumbnail_post_obj->setVar("post_parent", 0);
                    $thumbnail_post_obj->update();

                    // thumbnail meta
                    $sql = "select * from ". $wpdb->base_prefix . $this->current_site_id."_postmeta where post_id=".$row['ID'];
                    $thumbnail_meta_arr = $wpdb->get_results($sql, ARRAY_A);
                    for($i=0;$i<count($thumbnail_meta_arr);$i++) {
                        $v = $thumbnail_meta_arr[$i];
                        if($v['meta_key'] == "_wp_attached_file") {
                            $replaced_value = "sites/".$this->current_site_id."/".$v['meta_value'];
                            if($this->site_type == "en") {
                                $replaced_value = "../../" . $replaced_value;
                            }
                            $search_value = $v['meta_value'];
                            break;
                        }
                    }
                    for($i=0;$i<count($thumbnail_meta_arr);$i++) {
                        $v = $thumbnail_meta_arr[$i];
                        $value = $v['meta_value'];
                        if($v['meta_key'] == "_wp_attached_file") {
                            $value = $replaced_value;
                        }

                        if($v['meta_key'] == "_wp_attachment_metadata") {
                            $value = str_replace('"'.$search_value, '"'.$replaced_value, $v['meta_value']);
                        }

                        $sql = "insert into " .$wpdb->base_prefix.$this->postmeta_table." set meta_key='".addslashes($v['meta_key'])."', meta_value='".addslashes($value)."', post_id=".$thumbnail_post_obj->getKeyId();
                        $wpdb->query($sql);
                    }
                }

                if(!$thumbnail_record_row['master_post_id']) {
                    $this->insertPostRecord($row['ID'], $thumbnail_post_obj->getKeyId());
                }

                return $thumbnail_post_obj->getKeyId();
            }
        }
    }
} 