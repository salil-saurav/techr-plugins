<?php
namespace TechrOption;

/**
 * Class WP_Starter_Admin
 * Handles all admin-related functionality
 */
class WP_Starter_Admin
{
   /**
    * Initialize the admin features
    */
   public function __construct()
   {
      $this->init_hooks();
   }

   /**
    * Register all hooks
    */
   private function init_hooks()
   {
      add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
      add_filter('admin_footer_text', [$this, 'customize_footer_text']);
      remove_action('welcome_panel', 'wp_welcome_panel');

      add_action('after_setup_theme', [$this, 'register_menu']);
      add_action('widgets_init', [$this, 'register_widgets']);
   }

   /**
    * Enqueue admin styles and fonts
    */
   public function enqueue_admin_assets()
   {
      $assets = [
         'wp-starter-admin-style' => [
            'url' => TECHR_OPTIONS_URL . 'assets/css/admin.css',
            'deps' => [],
         ],
         'techr-admin-css' => [
            'url' => TECHR_OPTIONS_URL . 'assets/css/main.css',
            'deps' => [],
         ],
      ];

      foreach ($assets as $handle => $asset) {
         wp_register_style($handle, $asset['url'], $asset['deps'], null);
         wp_enqueue_style($handle);
      }
   }

   /**
    * Customize admin footer text
    */
   public function customize_footer_text()
   {
      return sprintf(
         'Thank you for creating with <a href="%s">%s</a>',
         home_url(),
         get_bloginfo('name')
      );
   }

   public function register_menu()
   {
      register_nav_menu('primary', __('Primary Menu'));
   }

   public  function register_widgets()
   {
      register_sidebar(array(
         'name' => __('Sidebar'),
         'id' => 'sidebar-1',
         'description' => __('Add widgets to this sidebar'),
         'before_widget' => '<div id="%1$s" class="widget %2$s">',
         'after_widget' => '</div>',
         'before_title' => '<h2 class="widget-title">',
         'after_title' => '</h2>',
      ));
   }
}
