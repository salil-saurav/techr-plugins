<?php

/**
 * HTML, CSS, and JS Minification functionality
 *
 * @package WordPress-Starter
 * @version 1.1.0
 */

namespace WPStarter\Optimization;

if (!defined('ABSPATH')) {
   exit; // Exit if accessed directly
}

/**
 * HTML Minifier Class
 */
class HTML_Minifier
{
   private static $config = [
      'minify_html'                   => true,
      'minify_inline_css'             => true,
      'minify_inline_js'              => true,
      'remove_comments'               => true,
      'preserve_conditional_comments' => true,
      'version_assets'                => true,
   ];

   public static function init($config = [])
   {
      if (!empty($config) && is_array($config)) {
         self::$config = array_merge(self::$config, $config);
      }

      if (self::should_minify()) {
         add_action('template_redirect', [self::class, 'start_buffer'], 1);
         add_action('shutdown', [self::class, 'end_buffer'], 0);

         if (self::$config['version_assets']) {
            add_filter('style_loader_src', [self::class, 'version_assets'], 10, 2);
            add_filter('script_loader_src', [self::class, 'version_assets'], 10, 2);
         }
      }
   }

   public static function configure($config)
   {
      if (!empty($config) && is_array($config)) {
         self::$config = array_merge(self::$config, $config);
      }
   }

   public static function start_buffer()
   {
      ob_start([self::class, 'minify']);
   }

   public static function end_buffer()
   {
      if (ob_get_level() > 0) {
         ob_end_flush();
      }
   }

   public static function minify($buffer)
   {
      if (!is_string($buffer) || empty($buffer)) {
         return $buffer;
      }

      try {
         // 1. Extract Conditionals (IE)
         $conditionals = [];
         if (self::$config['preserve_conditional_comments']) {
            if (preg_match_all('/<!--\[if[^\]]*?\]>.*?<!\[endif\]-->/is', $buffer, $matches)) {
               foreach ($matches[0] as $i => $match) {
                  // Use <![ format to prevent removal by remove_comments
                  $id = '<!-- <![WPSTARTER_CONDITIONAL_' . $i . '] -->';
                  $conditionals[$id] = $match;
                  $buffer = str_replace($match, $id, $buffer);
               }
            }
         }

         // 2. Extract Scripts
         // We use <![ pattern because minify_html regex explicitly preserves comments with <![
         $scripts = [];
         $buffer = preg_replace_callback(
            '/<script\b([^>]*)>(.*?)<\/script>/is',
            function ($matches) use (&$scripts) {
               $id = '<!-- <![WPSTARTER_SCRIPT_' . count($scripts) . '] -->';
               $scripts[$id] = [
                  'attrs' => $matches[1],
                  'content' => $matches[2]
               ];
               return $id;
            },
            $buffer
         );

         // 3. Extract Styles
         $styles = [];
         $buffer = preg_replace_callback(
            '/<style\b([^>]*)>(.*?)<\/style>/is',
            function ($matches) use (&$styles) {
               $id = '<!-- <![WPSTARTER_STYLE_' . count($styles) . '] -->';
               $styles[$id] = [
                  'attrs' => $matches[1],
                  'content' => $matches[2]
               ];
               return $id;
            },
            $buffer
         );

         // 4. Minify HTML (Safe now as scripts/styles are hidden in protected comments)
         if (self::$config['minify_html']) {
            $buffer = self::minify_html($buffer);
         }

         // 5. Restore & Minify Inline CSS
         if (!empty($styles)) {
            foreach ($styles as $id => $style) {
               $content = $style['content'];
               if (self::$config['minify_inline_css']) {
                  if (strpos($content, 'calc(') === false && strpos($content, '@keyframes') === false) {
                     $content = Minifier::minify_css($content);
                  }
               }
               $buffer = str_replace($id, '<style' . $style['attrs'] . '>' . $content . '</style>', $buffer);
            }
         }

         // 6. Restore & Minify Inline JS
         if (!empty($scripts)) {
            foreach ($scripts as $id => $script) {
               $content = $script['content'];
               $attrs = $script['attrs'];
               $should_minify = self::$config['minify_inline_js'];

               // Check if external or non-JS
               if (
                  strpos($attrs, 'src=') !== false ||
                  (strpos($attrs, 'type=') !== false &&
                     !preg_match('/type=(["\'])(?:text|application)\/(?:javascript|ecmascript)\1/i', $attrs))
               ) {
                  $should_minify = false;
               }

               if ($should_minify) {
                  $content = Minifier::minify_js($content);
               }

               $buffer = str_replace($id, '<script' . $attrs . '>' . $content . '</script>', $buffer);
            }
         }

         // 7. Restore Conditionals
         if (!empty($conditionals)) {
            foreach ($conditionals as $id => $match) {
               $buffer = str_replace($id, $match, $buffer);
            }
         }

         return $buffer;
      } catch (\Exception $e) {
         if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('HTML Minifier Error: ' . $e->getMessage());
         }
         return $buffer;
      }
   }

   private static function minify_html($buffer)
   {
      $search = [
         '/\>[^\S ]+/s',     // Remove whitespace after tags
         '/[^\S ]+\</s',     // Remove whitespace before tags
         '/(\s)+/s',         // Remove multiple whitespace sequences
      ];
      $replace = ['>', '<', '\\1'];

      if (self::$config['remove_comments']) {
         // This regex specifically IGNORES comments that have <![ inside them
         // which is why we switched our placeholders to use that format.
         $buffer = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $buffer);
      }

      return preg_replace($search, $replace, $buffer);
   }

   public static function version_assets($src, $handle)
   {
      if (empty($src) || is_admin() || strpos($src, 'ver=') !== false) {
         return $src;
      }
      $version = wp_get_theme()->get('Version') ?: get_bloginfo('version');
      return add_query_arg('ver', $version, $src);
   }

   private static function should_minify()
   {
      if (is_admin() || wp_doing_ajax() || is_customize_preview()) {
         return false;
      }
      if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) {
         return false;
      }
      return apply_filters('wpstarter_should_minify', true);
   }
}

/**
 * Asset Minifier Class
 */
class Minifier
{
   private static $config = [
      'backup_original' => true,
      'overwrite_existing' => false,
      'css_aggressive' => false,
      'js_aggressive' => false,
   ];

   public static function configure($config)
   {
      if (!empty($config) && is_array($config)) {
         self::$config = array_merge(self::$config, $config);
      }
   }

   public static function minify_js($js)
   {
      if (empty($js)) {
         return '';
      }

      try {
         // FIX: Explicitly remove WordPress CDATA wrappers
         // This prevents them from causing syntax errors when everything is single-lined
         $js = str_replace(['/* <![CDATA[ */', '/* ]]> */', '// <![CDATA[', '// ]]>'], '', $js);

         // Preserve important multi-line comments
         $important_comments = [];
         preg_match_all('/\/\*![\s\S]*?\*\//m', $js, $matches);

         if (!empty($matches[0])) {
            foreach ($matches[0] as $i => $comment) {
               $placeholder = "/* IMPORTANT_COMMENT_{$i} */";
               $important_comments[$placeholder] = $comment;
               $js = str_replace($comment, $placeholder, $js);
            }
         }

         // Remove single line comments
         $js = preg_replace('/\/\/.*$/m', '', $js);

         // Remove multi-line comments
         $js = preg_replace('/\/\*(?!.*?\*\/).*?\*\//s', '', $js);

         // Remove whitespace
         $js = preg_replace('/\s+/', ' ', $js);

         if (self::$config['js_aggressive']) {
            $js = preg_replace('/\s*([=+\-*\/%<>!&|:;,()])\s*/', '$1', $js);
            $js = preg_replace('/;+\}/', '}', $js);
         }

         foreach ($important_comments as $placeholder => $comment) {
            $js = str_replace($placeholder, $comment, $js);
         }

         return trim($js);
      } catch (\Exception $e) {
         return $js; // Return original if minification fails
      }
   }
   // ... (rest of Minifier class: minify_css, process_assets, etc. remains the same) ...
   public static function minify_css($css)
   {
      if (empty($css)) return '';
      try {
         $important_comments = [];
         preg_match_all('/\/\*![\s\S]*?\*\//m', $css, $matches);
         if (!empty($matches[0])) {
            foreach ($matches[0] as $i => $comment) {
               $placeholder = "/* IMPORTANT_COMMENT_{$i} */";
               $important_comments[$placeholder] = $comment;
               $css = str_replace($comment, $placeholder, $css);
            }
         }
         $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
         $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
         $css = preg_replace('/\s+/', ' ', $css);
         $css = preg_replace('/\s*([\{\}:;,])\s*/', '$1', $css);
         $css = str_replace(';}', '}', $css);
         if (self::$config['css_aggressive']) {
            $css = preg_replace('/#([a-f0-9])\1([a-f0-9])\2([a-f0-9])\3/i', '#$1$2$3', $css);
            $css = preg_replace('/(?<!\d)0(?:em|ex|px|pt|pc|in|cm|mm|%)/i', '0', $css);
            $css = preg_replace('/0\.([0-9]+)/', '.$1', $css);
         }
         foreach ($important_comments as $placeholder => $comment) {
            $css = str_replace($placeholder, $comment, $css);
         }
         return trim($css);
      } catch (\Exception $e) {
         return $css;
      }
   }

   public static function process_assets($assets_dir, $options = [])
   {
      $options = array_merge(['js_dir' => '/js', 'css_dir' => '/css', 'recursive' => false, 'file_mask' => '*'], $options);
      $stats = ['js_processed' => 0, 'css_processed' => 0, 'js_size_before' => 0, 'js_size_after' => 0, 'css_size_before' => 0, 'css_size_after' => 0, 'errors' => []];

      // Helper to process files
      $process_file = function ($file, $type) use (&$stats) {
         try {
            if (strpos($file, '.min.') !== false || strpos($file, '.map.') !== false) return;
            $code = file_get_contents($file);
            $stats["{$type}_size_before"] += strlen($code);
            $minified = ($type === 'js') ? self::minify_js($code) : self::minify_css($code);
            $stats["{$type}_size_after"] += strlen($minified);
            $min_file = str_replace(".{$type}", ".min.{$type}", $file);
            if (!file_exists($min_file) || self::$config['overwrite_existing']) {
               file_put_contents($min_file, $minified);
               $stats["{$type}_processed"]++;
            }
         } catch (\Exception $e) {
            $stats['errors'][] = "Error processing {$file}: " . $e->getMessage();
         }
      };

      // Find and process files (Simplified for brevity)
      $glob_pat = $options['file_mask'] . '.';
      $js_files = $options['recursive'] ? self::recursive_glob($assets_dir . $options['js_dir'], $glob_pat . 'js') : glob($assets_dir . $options['js_dir'] . '/' . $glob_pat . 'js');
      if ($js_files) foreach ($js_files as $f) $process_file($f, 'js');

      $css_files = $options['recursive'] ? self::recursive_glob($assets_dir . $options['css_dir'], $glob_pat . 'css') : glob($assets_dir . $options['css_dir'] . '/' . $glob_pat . 'css');
      if ($css_files) foreach ($css_files as $f) $process_file($f, 'css');

      return $stats;
   }

   private static function recursive_glob($base_dir, $pattern, $flags = 0)
   {
      $files = glob(rtrim($base_dir, '/') . '/' . $pattern, $flags);
      foreach (glob(rtrim($base_dir, '/') . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
         $files = array_merge($files, self::recursive_glob($dir, $pattern, $flags));
      }
      return $files;
   }
}

class Asset_Optimizer
{
   public static function init()
   {
      HTML_Minifier::init();
      add_action('after_setup_theme', [self::class, 'maybe_process_assets']);
   }
   public static function maybe_process_assets()
   {
      if ((defined('WP_DEBUG') && WP_DEBUG) || (isset($_GET['optimize_assets']) && current_user_can('manage_options'))) {
         $theme_dir = get_template_directory();
         $assets_dir = apply_filters('wpstarter_assets_dir', $theme_dir . '/assets');
         self::process_theme_assets($assets_dir);
      }
   }
   public static function process_theme_assets($assets_dir, $options = [])
   {
      $options = array_merge(['recursive' => true, 'file_mask' => '*'], $options);
      return Minifier::process_assets($assets_dir, $options);
   }
   public static function register_admin_menu()
   {
      add_management_page('Asset Optimization', 'Asset Optimizer', 'manage_options', 'wpstarter-asset-optimizer', [self::class, 'admin_page']);
   }
   public static function admin_page()
   {
      echo '<div class="wrap"><h1>Asset Optimizer</h1>';
      if (isset($_POST['process_assets']) && check_admin_referer('wpstarter_optimize_assets')) {
         $stats = self::process_theme_assets(apply_filters('wpstarter_assets_dir', get_template_directory() . '/assets'));
         echo '<div class="notice notice-success"><p>' . sprintf('Processed %d JS, %d CSS', $stats['js_processed'], $stats['css_processed']) . '</p></div>';
      }
      echo '<form method="post">';
      wp_nonce_field('wpstarter_optimize_assets');
      echo '<p><button type="submit" name="process_assets" class="button button-primary">Process Theme Assets</button></p></form></div>';
   }
}
// add_action('admin_menu', ['WPStarter\Optimization\Asset_Optimizer', 'register_admin_menu']);
// Asset_Optimizer::init();
