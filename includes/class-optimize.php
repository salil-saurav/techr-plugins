<?php

class WP_Performance_Suite
{
   /**
    * Singleton instance
    * @var WP_Performance_Suite|null
    */
   private static $instance = null;

   /**
    * Configuration options
    * @var array
    */
   private $config = [
      // Basic optimizations
      'remove_emoji'            => true,
      'remove_embeds'           => true,
      'remove_wp_block_library' => true,
      'disable_xmlrpc'          => true,
      'limit_revisions'         => true,
      'max_revisions'           => 3,
      'disable_heartbeat'       => false,
      'heartbeat_frequency'     => 60,
      'disable_self_pingbacks'  => true,
      'disable_rss_feeds'       => true,
      'remove_query_strings'    => true,
      'disable_comments'        => true,

      // Advanced optimizations
      'optimize_images'         => true,
      'convert_to_webp'         => true,
      'image_quality'           => 82,
      'defer_js'                => true,
      'lazy_load'               => true,
      'preload_resources'       => true,
      'disable_gutenberg'       => true,
      'dns_prefetch'            => true,
      'remove_jquery_migrate'   => true,

      // Security options
      'hide_wp_version'         => true,
      'disable_file_edit'       => true,
      'restrict_admin_access'   => false,
      // Removed DB cleanup options

      // Development tools
      'show_template_path'      => true,
      'debug_mode'              => false
   ];

   /**
    * Resources for preloading
    * @var array
    */
   private $preload_resources = [
      'fonts'     => [
         'https://fonts.googleapis.com',
         'https://fonts.gstatic.com'
      ],
      'scripts'   => [
         'https://ajax.googleapis.com',
         'https://cdnjs.cloudflare.com'
      ],
      'analytics' => [
         'https://www.google-analytics.com',
         'https://www.googletagmanager.com'
      ]
   ];

   /**
    * Scripts to defer
    * @var array
    */
   private $defer_scripts = [
      'skip'  => ['jquery', 'jquery-core', 'admin-bar', 'woocommerce', 'wc-add-to-cart'],
      'async' => ['google-analytics', 'gtag', 'gtm']
   ];

   /**
    * Private Constructor to enforce Singleton
    */
   private function __construct(array $config = [])
   {
      $this->config = array_merge($this->config, $config);
      $this->init_hooks();
   }

   /**
    * Get Singleton Instance
    */
   public static function get_instance(array $config = [])
   {
      if (self::$instance === null) {
         self::$instance = new self($config);
      }
      return self::$instance;
   }

   /**
    * Initialize all hooks
    */
   private function init_hooks()
   {
      // Debug Mode
      if ($this->config['debug_mode']) {
         if (!defined('WP_DEBUG')) define('WP_DEBUG', true);
         if (!defined('WP_DEBUG_DISPLAY')) define('WP_DEBUG_DISPLAY', true);
      }

      // Core Cleanup
      $this->remove_header_bloat();
      add_action('after_setup_theme', [$this, 'remove_api_endpoints']);

      // Features based on config
      if ($this->config['remove_emoji']) $this->remove_emoji_support();
      if ($this->config['remove_embeds']) $this->disable_embeds();

      // Assets
      add_action('wp_enqueue_scripts', [$this, 'optimize_assets'], 100);
      add_action('init', [$this, 'remove_post_type_supports'], 100);

      if ($this->config['disable_self_pingbacks']) add_action('pre_ping', [$this, 'stop_self_ping']);
      if ($this->config['disable_xmlrpc']) $this->disable_xmlrpc();
      if ($this->config['limit_revisions']) $this->limit_post_revisions();
      if ($this->config['disable_heartbeat']) $this->control_heartbeat();
      if ($this->config['disable_rss_feeds']) $this->disable_rss_feeds();

      if ($this->config['remove_query_strings']) {
         add_filter('script_loader_src', [$this, 'remove_query_strings'], 15);
         add_filter('style_loader_src', [$this, 'remove_query_strings'], 15);
      }

      if ($this->config['disable_comments']) $this->disable_comments();

      // Image Optimization
      if ($this->config['optimize_images']) {
         add_filter('jpeg_quality', [$this, 'set_image_quality']);
         add_filter('wp_editor_set_quality', [$this, 'set_image_quality']);

         if ($this->config['convert_to_webp']) {
            add_filter('wp_handle_upload', [$this, 'convert_to_webp'], 10, 2);
            add_filter('upload_mimes', [$this, 'enable_custom_mime_support']);
         }
      }

      // JS/CSS Loading
      if ($this->config['defer_js']) {
         add_filter('script_loader_tag', [$this, 'optimize_script_loading'], 10, 3);
      }

      if ($this->config['preload_resources']) {
         add_filter('style_loader_tag', [$this, 'optimize_style_loading'], 10, 4);
         add_action('wp_head', [$this, 'add_resource_hints'], 1);
      }

      if ($this->config['lazy_load']) {
         // WordPress native lazy load is usually sufficient, but we filter if needed
         add_filter('wp_lazy_loading_enabled', '__return_true');
      }

      if ($this->config['disable_gutenberg']) $this->disable_gutenberg();

      if ($this->config['remove_jquery_migrate']) {
         add_action('wp_default_scripts', [$this, 'remove_jquery_migrate']);
      }

      if ($this->config['hide_wp_version']) {
         add_filter('the_generator', '__return_empty_string');
         add_filter('style_loader_src', [$this, 'remove_wp_version_strings']);
         add_filter('script_loader_src', [$this, 'remove_wp_version_strings']);
      }

      if ($this->config['disable_file_edit'] && !defined('DISALLOW_FILE_EDIT')) {
         define('DISALLOW_FILE_EDIT', true);
      }

      if ($this->config['show_template_path']) {
         add_action('admin_bar_menu', [$this, 'show_template_path'], 100);
      }

      add_action('init', [$this, 'restrict_comments_post_access']);
      add_filter('tiny_mce_before_init', [$this, 'customize_tinymce']);
   }

   /* -------------------------------------------------------------------------- */
   /* Core Cleanups                                */
   /* -------------------------------------------------------------------------- */

   private function remove_header_bloat()
   {
      remove_action('wp_head', 'wp_shortlink_wp_head', 10);
      remove_action('template_redirect', 'wp_shortlink_header', 11);
      remove_action('wp_head', 'rsd_link');
      remove_action('wp_head', 'wlwmanifest_link');
      remove_action('wp_head', 'wp_generator');
      remove_action('wp_head', 'feed_links', 2);
      remove_action('wp_head', 'feed_links_extra', 3);
      remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
      remove_action('wp_head', 'wp_oembed_add_host_js');
      remove_action('rest_api_init', 'wp_oembed_register_route');
      remove_action('wp_head', 'wp_resource_hints', 2);
      remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);
      remove_action('wp_head', 'rel_canonical');
      remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
   }

   public function remove_api_endpoints()
   {
      remove_action('wp_head', 'rest_output_link_wp_head', 10);
      remove_action('template_redirect', 'rest_output_link_header', 11);

      if ($this->config['restrict_admin_access']) {
         add_filter('rest_authentication_errors', function ($result) {
            if (!empty($result)) return $result;
            if (!is_user_logged_in()) {
               return new WP_Error('rest_not_logged_in', 'Restricted Access', ['status' => 401]);
            }
            return $result;
         });
      }
   }

   private function remove_emoji_support()
   {
      remove_action('wp_head', 'print_emoji_detection_script', 7);
      remove_action('admin_print_scripts', 'print_emoji_detection_script');
      remove_action('wp_print_styles', 'print_emoji_styles');
      remove_action('admin_print_styles', 'print_emoji_styles');
      remove_filter('the_content_feed', 'wp_staticize_emoji');
      remove_filter('comment_text_rss', 'wp_staticize_emoji');
      remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

      add_filter('tiny_mce_plugins', function ($plugins) {
         return is_array($plugins) ? array_diff($plugins, ['wpemoji']) : [];
      });

      add_filter('emoji_svg_url', '__return_false');
   }

   /* -------------------------------------------------------------------------- */
   /* Asset Logic                                 */
   /* -------------------------------------------------------------------------- */

   public function optimize_assets()
   {
      // Block Library / Gutenberg styles
      if ($this->config['remove_wp_block_library']) {
         wp_dequeue_style('wp-block-library');
         wp_dequeue_style('wp-block-library-theme');
         wp_dequeue_style('wc-blocks-style');
         wp_dequeue_style('global-styles');
      }

      // Generic cleanup for default themes
      if (!is_admin() && !is_customize_preview()) {
         $handles = [
            'twenty-twenty-one-style',
            'twenty-twenty-one-print-style',
            'twenty-twenty-two-style',
            'twenty-twenty-two-print-style',
            'twenty-twenty-three-style',
            'twenty-twenty-three-print-style',
            'twenty-twenty-four-style',
            'twenty-twenty-four-print-style',
         ];

         foreach ($handles as $handle) {
            if (wp_style_is($handle, 'enqueued')) {
               wp_dequeue_style($handle);
               wp_deregister_style($handle);
            }
         }
      }
   }

   public function remove_query_strings($src)
   {
      if (strpos($src, '?ver=')) {
         $src = remove_query_arg('ver', $src);
      }
      return $src;
   }

   public function optimize_script_loading($tag, $handle, $src)
   {
      if (is_admin() || in_array($handle, $this->defer_scripts['skip'])) {
         return $tag;
      }

      if (in_array($handle, $this->defer_scripts['async']) || strpos($handle, 'analytics') !== false) {
         return str_replace(' src', ' async src', $tag);
      }

      if (strpos($tag, 'async') === false && strpos($tag, 'defer') === false) {
         return str_replace(' src', ' defer src', $tag);
      }

      return $tag;
   }

   public function optimize_style_loading($tag, $handle, $href, $media)
   {
      if (strpos($handle, 'critical') !== false || strpos($handle, 'above-fold') !== false) {
         return str_replace("rel='stylesheet'", "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", $tag);
      }
      return $tag;
   }

   public function add_resource_hints()
   {
      $domains = [];
      foreach ($this->preload_resources as $urls) {
         $domains = array_merge($domains, $urls);
      }

      foreach (array_unique($domains) as $domain) {
         echo '<link rel="dns-prefetch" href="' . esc_url($domain) . '" />' . "\n";
         echo '<link rel="preconnect" href="' . esc_url($domain) . '" crossorigin />' . "\n";
      }
   }

   public function remove_jquery_migrate($scripts)
   {
      if (!is_admin() && isset($scripts->registered['jquery'])) {
         $script = $scripts->registered['jquery'];
         if ($script->deps) {
            $script->deps = array_diff($script->deps, ['jquery-migrate']);
         }
      }
   }

   public function remove_wp_version_strings($src)
   {
      if (strpos($src, 'ver=' . get_bloginfo('version'))) {
         $src = remove_query_arg('ver', $src);
      }
      return $src;
   }

   /* -------------------------------------------------------------------------- */
   /* Image Optimization                               */
   /* -------------------------------------------------------------------------- */

   public function set_image_quality()
   {
      return $this->config['image_quality'];
   }

   public function enable_custom_mime_support($mimes)
   {
      $mimes['webp'] = 'image/webp';
      $mimes['svg']  = 'image/svg+xml';
      return $mimes;
   }

   public function convert_to_webp($upload)
   {
      // Validation checks
      if (
         !isset($upload['type']) || strpos($upload['type'], 'image/') !== 0 ||
         $upload['type'] === 'image/webp' || $upload['type'] === 'image/svg+xml'
      ) {
         return $upload;
      }

      $file_path = $upload['file'];

      // Ensure file exists before processing
      if (!file_exists($file_path)) {
         return $upload;
      }

      $file_info = wp_check_filetype($file_path);
      $allowed_types = ['image/jpeg', 'image/png'];

      if (in_array($file_info['type'], $allowed_types)) {
         $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
         $success = false;

         try {
            // Try GD First
            if (function_exists('imagewebp') && function_exists('imagecreatefromjpeg')) {
               $image = null;
               if ($file_info['type'] === 'image/jpeg') {
                  $image = @imagecreatefromjpeg($file_path);
               } elseif ($file_info['type'] === 'image/png') {
                  $image = @imagecreatefrompng($file_path);
                  if ($image) {
                     imagepalettetotruecolor($image);
                     imagealphablending($image, true);
                     imagesavealpha($image, true);
                  }
               }

               if ($image) {
                  $success = imagewebp($image, $webp_path, $this->config['image_quality']);
                  imagedestroy($image);
               }
            }

            // Fallback to Imagick if GD failed or wasn't available
            if (!$success && class_exists('Imagick')) {
               $imagick = new Imagick($file_path);
               $imagick->setImageFormat('webp');
               $imagick->setImageCompressionQuality($this->config['image_quality']);
               $imagick->setOption('webp:lossless', 'false');
               $success = $imagick->writeImage($webp_path);
               $imagick->clear();
               $imagick->destroy();
            }

            if ($success && file_exists($webp_path)) {
               // Remove original if you want to save space, otherwise keep it for fallback
               @unlink($file_path);

               $upload['type'] = 'image/webp';
               $upload['file'] = $webp_path;
               $upload['url']  = str_replace(basename($upload['url']), basename($webp_path), $upload['url']);
            }
         } catch (Exception $e) {
            // Log error but return original upload so user workflow isn't broken
            error_log('WP Performance Suite WebP Error: ' . $e->getMessage());
            return $upload;
         }
      }

      return $upload;
   }

   /* -------------------------------------------------------------------------- */
   /* Security / System                                */
   /* -------------------------------------------------------------------------- */

   private function disable_xmlrpc()
   {
      add_filter('xmlrpc_enabled', '__return_false');
      add_filter('xmlrpc_methods', function ($methods) {
         unset($methods['pingback.ping']);
         unset($methods['pingback.extensions.getPingbacks']);
         return $methods;
      });

      add_filter('wp_headers', function ($headers) {
         unset($headers['X-Pingback']);
         return $headers;
      });
   }

   private function limit_post_revisions()
   {
      if (!defined('WP_POST_REVISIONS')) {
         define('WP_POST_REVISIONS', $this->config['max_revisions']);
      }
   }

   private function control_heartbeat()
   {
      add_action('init', function () {
         // Disable heartbeat unless in post edit screen
         global $pagenow;
         if ($pagenow !== 'post.php' && $pagenow !== 'post-new.php') {
            wp_deregister_script('heartbeat');
         }
      }, 1);

      add_filter('heartbeat_settings', function ($settings) {
         $settings['interval'] = $this->config['heartbeat_frequency'];
         return $settings;
      });
   }

   private function disable_rss_feeds()
   {
      $actions = ['do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2', 'do_feed_atom', 'do_feed_rss2_comments', 'do_feed_atom_comments'];
      foreach ($actions as $action) {
         add_action($action, function () {
            wp_die(
               '<p>' . __('RSS feeds are disabled for better performance.', 'wp-perf-suite') . '</p>',
               '',
               ['response' => 410]
            );
         }, 1);
      }
   }

   private function disable_comments()
   {
      add_action('admin_init', function () {
         // Remove from post types
         foreach (get_post_types() as $post_type) {
            if (post_type_supports($post_type, 'comments')) {
               remove_post_type_support($post_type, 'comments');
               remove_post_type_support($post_type, 'trackbacks');
            }
         }
      });

      add_action('admin_menu', function () {
         remove_menu_page('edit-comments.php');
      });

      add_action('wp_before_admin_bar_render', function () {
         global $wp_admin_bar;
         $wp_admin_bar->remove_menu('comments');
      });

      add_filter('comments_open', '__return_false', 20, 2);
      add_filter('pings_open', '__return_false', 20, 2);
      add_filter('comments_array', '__return_empty_array', 10, 2);

      add_action('wp_print_scripts', function () {
         wp_dequeue_script('comment-reply');
      });
   }

   public function restrict_comments_post_access()
   {
      if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'wp-comments-post.php') !== false) {
         wp_die('Comments are closed.', '', ['response' => 403]);
      }
   }

   public function remove_post_type_supports()
   {
      remove_post_type_support('page', 'trackbacks');
      remove_post_type_support('post', 'trackbacks');
      remove_post_type_support('page', 'excerpt');
   }

   public function stop_self_ping(&$links)
   {
      $home = get_option('home');
      foreach ($links as $l => $link) {
         if (0 === strpos($link, $home)) {
            unset($links[$l]);
         }
      }
   }

   /* -------------------------------------------------------------------------- */
   /* Gutenberg & UI                                   */
   /* -------------------------------------------------------------------------- */

   private function disable_gutenberg()
   {
      add_filter('use_block_editor_for_post', '__return_false', 10);
      add_filter('use_block_editor_for_post_type', '__return_false', 10);
      add_filter('gutenberg_use_widgets_block_editor', '__return_false');
      add_filter('use_widgets_block_editor', '__return_false');
   }

   private function disable_embeds()
   {
      add_action('wp_print_scripts', function () {
         wp_dequeue_script('wp-embed');
      });

      add_filter('tiny_mce_plugins', function ($plugins) {
         return is_array($plugins) ? array_diff($plugins, ['wpembed']) : [];
      });

      remove_action('rest_api_init', 'wp_oembed_register_route');
      add_filter('embed_oembed_discover', '__return_false');
      remove_action('wp_head', 'wp_oembed_add_host_js');
   }

   public function customize_tinymce($settings)
   {
      $settings['wpautop'] = false;
      if (isset($settings['toolbar1'])) {
         $settings['toolbar1'] = str_replace(',wp_more', '', $settings['toolbar1']);
      }

      // Only load if exists to prevent 404
      $css_path = get_template_directory_uri() . '/assets/css/editor-style.css';
      $settings['content_css'] = $css_path;

      return $settings;
   }

   public function show_template_path($admin_bar)
   {
      if (!is_admin_bar_showing() || !current_user_can('manage_options') || is_admin()) {
         return;
      }

      global $template;
      $template_path = $template ? basename($template) : 'Unknown';

      $admin_bar->add_node([
         'id'    => 'template-path',
         'title' => 'Template: ' . $template_path,
         'top'   => true
      ]);
   }
}

// Initialization usage
// add_action('plugins_loaded', function () {
//    WP_Performance_Suite::get_instance();
// });
