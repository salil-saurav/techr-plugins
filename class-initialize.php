<?php

require_once TECHR_OPTIONS_PATH . 'includes/class-admin.php';
require_once TECHR_OPTIONS_PATH . 'includes/class-code-meta.php';
require_once TECHR_OPTIONS_PATH . 'includes/class-config-smtp.php';
require_once TECHR_OPTIONS_PATH . 'includes/class-minify.php';
require_once TECHR_OPTIONS_PATH . 'includes/class-optimize.php';
// require_once TECHR_OPTIONS_FILE . 'helper/load-env.php';


class Initialize_Techr_Option
{

   public function __construct()
   {
      // Admin init
      new WP_Starter_Admin();
      Custom_Code_Editor::init();
      SMTP_Handler::init();
      WP_Performance_Suite::get_instance();

      // load_env_file(ABSPATH . "/.env");
   }
}

wp_die("checking");
