<?php
    namespace Sitios\Bamboo\Classes\Helpers;

    class GatewayHelper {

        public static function toJSON($data, $options = 0)
        {
            if (version_compare(phpversion(), '5.4.0', '>=') === true) {
                return json_encode($data, $options | 64);
            }
            return str_replace('\\/', '/', json_encode($data, $options));
        }
    }