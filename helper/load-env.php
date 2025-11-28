<?php

if (!defined('ABSPATH')) {
   exit;
}

class TOEnvLoader
{
   public static function load_env()
   {
      $env_path = plugin_dir_path(__FILE__) . '../../.env';

      if (!file_exists($env_path)) {
         return;
      }

      $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

      foreach ($lines as $line) {
         if (strpos(trim($line), '#') === 0) {
            continue;
         }

         list($name, $value) = array_map('trim', explode('=', $line, 2));

         if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
         }
      }
   }
}

TOEnvLoader::load_env();
