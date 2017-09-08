<?php

/**
 * organize table fields class
 */

class DbSet {
    var $table;
    var $fields;
    var $key_name;
    var $id;
    var $actived = false;

    public function __construct($table, $key_name, $id=""){
        global $wpdb;
        $this->table = $wpdb->base_prefix.$table;
        $this->key_name = $key_name;
        $this->getPostTableStructure();
        $this->id = ceil($id);

        if($this->id > 0) {
            $sql = "select * from ".$this->table." where $key_name=".$this->id;
            $row = $wpdb->get_row($sql, ARRAY_A);
            $this->setVars($row);
            $this->fields[$key_name] = $this->id;
            $this->actived = true;
        }
    }

    private function getPostTableStructure() {
        global $wpdb;
        $sql = "show COLUMNS from " . $this->table;
        $columns = $wpdb->get_results($sql, ARRAY_A);
        foreach($columns as $v) {
            $this->fields[$v['Field']] = "";
        }
    }

    public function setVars($post_info) {
        if(count($post_info) > 0) {
            foreach($this->fields as $k => $v) {
                if($k == $this->key_name)
                    continue;

                $this->fields[$k] = $post_info[$k];
            }
        }
    }

    public function setVar($key, $value) {
        $this->fields[$key] = $value;
    }

    public function getVar($key) {
        return $this->fields[$key];
    }

    public function getSqlSet() {
        $sql = "";
        if(count($this->fields) > 0) {
            foreach($this->fields as $k => $v) {
                if($v == ":skip:")
                    continue;

                $sql .= "`$k`='".addslashes($v)."',";
            }
            if($sql)
                $sql = substr($sql, 0, -1);
        }

        return $sql;
    }

    public function getKeyId() {
        return $this->id;
    }

    public function update() {
        global $wpdb;
        if($this->actived()) {
            $sql = "update {$this->table} set " . $this->getSqlSet() . " where {$this->key_name}=" . $this->id;
        }else {
            $sql = "insert into {$this->table} set {$this->getSqlSet()}";
        }

        if($wpdb->query($sql)) {
            if(!$this->actived()) {
                $this->actived = true;
                $this->id = $wpdb->insert_id;
            }
            return true;
        }else {
            return false;
        }
    }

    public function actived() {
        return $this->actived;
    }
} 