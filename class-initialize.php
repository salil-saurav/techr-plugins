<?php

use WPStarter\Optimization\HTML_Minifier;

class Initialize_Techr_Option
{
   public function __construct()
   {
      $this->load_dependencies();
      $this->init_hooks();
   }

   /**
    * Load required files
    */
   private function load_dependencies()
   {
      require_once TECHR_OPTIONS_PATH . 'includes/class-admin.php';
      require_once TECHR_OPTIONS_PATH . 'includes/class-html-minifier.php';
      // require_once TECHR_OPTIONS_PATH . 'includes/class-config-smtp.php';
      require_once TECHR_OPTIONS_PATH . 'includes/class-minify.php';
      require_once TECHR_OPTIONS_PATH . 'includes/class-optimize.php';
      require_once TECHR_OPTIONS_PATH . 'includes/class-custom-login.php';

      // require_once TECHR_OPTIONS_PATH . 'helper/load-env.php';
   }

   /**
    * Instantiate classes
    */
   private function init_hooks()
   {
      new WP_Starter_Admin();
      HTML_Minifier::init();
      // SMTP_Handler::init();
      WP_Performance_Suite::get_instance();
      new Custom_Login_Manager();
   }
}
