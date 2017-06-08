<?php

class RkPostsPush {

    var $fields = array();
    var $current_site_id;
    var $post_id;
    var $site_type = "cn";
    var $insert_table;

    public function __construct($current_site_id) {
        global $wpdb;
        $this->getPostTableStructure();
        $lang = get_blog_option($current_site_id, "WPLANG");
        if($lang == "zh_CN") {
            $this->site_type = "cn";
            $this->insert_table = $wpdb->base_prefix . "posts";
        }else {
            $this->site_type = "en";
            $this->insert_table = $wpdb->base_prefix . "2_posts";
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
            $this->setVars($post_info);
            $this->post_id = $post_info['ID'];
            return $this->savePosts();
        }
    }

    public function savePosts() {
        global $wpdb;
        if($this->current_site_id == 0 || !$this->post_id || !$this->site_type)
            return;

        $post_exists = false;

        $sql = "select * from " . $wpdb->base_prefix . "rk_posts_push_records
            where current_site_id={$this->current_site_id}
            and  post_id={$this->post_id}
            and type='{$this->site_type}'";

        $row = $wpdb->get_row($sql, ARRAY_A);

        if($row['master_post_id']) {
            $this->fields['ID'] = $row['master_post_id'];
            $this->fields['post_status'] = ":skip:";
            $this->fields['post_date_gmt'] = ":skip:";
            $this->fields['post_name'] = ":skip:";
            $post_exists = true;
        }else {
            $this->fields['ID'] = ":skip:";
            $this->fields['post_status'] = "pending";
        }

        if($post_exists) {
            $sql = "update {$this->insert_table} set " . $this->getSqlSet() . " where ID=" . $this->fields['ID'];
        }else {
            $sql = "insert into {$this->insert_table} set {$this->getSqlSet()}";
        }

        if($wpdb->query($sql)) {
            $id = $wpdb->insert_id;
            if(!$post_exists) {
                $sql = "insert into " . $wpdb->base_prefix . "rk_posts_push_records set post_id={$this->post_id},
                    current_site_id={$this->current_site_id},
                    `type`='{$this->site_type}',
                    master_post_id={$id}";

                return $wpdb->query($sql);
            }

            return true;
        }
    }

    public function setVars($post_info) {
        if(count($post_info) > 0) {
            foreach($this->fields as $k => $v) {
                if($k == "ID")
                    continue;

                $this->fields[$k] = $post_info[$k];
            }
        }
    }

    private function getSqlSet() {
        $sql = "";
        if(count($this->fields) > 0) {
            foreach($this->fields as $k => $v) {
                if($v == ":skip:")
                    continue;

                $sql .= "`$k`='$v',";
            }
            if($sql)
                $sql = substr($sql, 0, -1);
        }

        return $sql;
    }

    private function getPostTableStructure() {
        global $wpdb;
        $sql = "show COLUMNS from wp_posts";
        $columns = $wpdb->get_results($sql, ARRAY_A);
        foreach($columns as $v) {
            $this->fields[$v['Field']] = "";
        }
    }
} 