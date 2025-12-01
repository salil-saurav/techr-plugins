<?php

require_once __DIR__ . '/includes/class-admin.php';
require_once __DIR__ . '/includes/class-custom-login.php';
require_once __DIR__ . '/includes/class-featured-post-manager.php';
require_once __DIR__ . '/includes/class-html-minifier.php';
require_once __DIR__ . '/includes/class-optimize.php';
// require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-taxonomy-image-manager.php';


use TechrOption\HTML_Minifier;
use TechrOption\WP_Starter_Admin;
use TechrOption\WP_Performance_Suite;
use TechrOption\Custom_Login_Manager;
use TechrOption\Taxonomy_Image_Manager;
use TechrOption\Featured_Post_Manager;

class Initialize_Techr_Option
{
   public function __construct()
   {
      $this->init_hooks();

      // add_action('admin_menu', [$this, 'register_techr_menu']);
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
      // new Techr_Settings();
      // Taxonomy image manager

      $tax_manager = new Taxonomy_Image_Manager([
         'software-category',
         'category'
      ]);
      $tax_manager->init();

      // Array of CPT slugs you want the star on
      $featured_manager = new Featured_Post_Manager(['directory']);
      $featured_manager->init();
   }

   /**
    * Register top-level + sub menu pages
    */
   public function register_techr_menu()
   {

      // Top-level menu
      add_menu_page(
         __('Techr Options', 'techr-options'),
         __('Techr Options', 'techr-options'),
         'manage_options',
         'techr-options-dashboard',
         [$this, 'techr_dashboard_page'],
         'dashicons-performance',
         25
      );
   }

   public function techr_dashboard_page()
   {
      include_once __DIR__ . "/pages/techr-dashboard.php";
   }
}
