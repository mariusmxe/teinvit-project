<?php

namespace SW_WAPF_PRO\Includes\Classes {

    class Cache
    {
        protected static $cache = [];
        protected static $clones = [];
		protected static $files = [];
		protected static $linked_products = [];

        public static function add_clone( $key, $data ) {
        	self::$clones[$key] = $data;
        }

        public static function get_clone( $key ) {

                    	if( ! isset( self::$clones[ $key ] ) ) {
                return false;
            }

        	return self::$clones[$key];

                    }

        public static function set_files( $files ) {
        	self::$files = $files;
        }

        public static function get_files(): array {
        	return self::$files;
        }

        public static function add_linked_products( $data = [], $field_id = null, $clone_idx = 0 ) {
            self::$linked_products[ $field_id . '_' . $clone_idx ] = $data;
        }

                public static function remove_linked_products( ) {
            self::$linked_products = [];
        }

        public static function get_linked_products( $field_id = null, $clone_idx = 0 ) {

            if( empty( $field_id ) ) {
                return self::$linked_products;
            }

                        $key = $field_id . '_' . $clone_idx;

            return self::$linked_products[ $key ] ?? [];

                    }

        public static function set( $key, $item ) {
            self::$cache[ $key ] = $item;
        }

        public static function get( $key, $default = false ) {

            if( ! isset( self::$cache[ $key ] ) ) {
                return $default;
            }

            return self::$cache[ $key ];
        }

        public static function clear() {
            self::$cache = [];
        }

    }
}
