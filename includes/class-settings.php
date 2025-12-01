<?php

/**
 * Includes/class-settings.php
 */

class Techr_Settings
{
   private $option_key = 'techr_settings';

   /**
    * Default config (copy from your optimization class)
    */
   private $defaults = [
      // Basic optimizations
      'remove_emoji'            => false,
      'remove_embeds'           => false,
      'remove_wp_block_library' => true,
      'disable_xmlrpc'          => true,
      'limit_revisions'         => true,
      'max_revisions'           => 3,
      'disable_heartbeat'       => false,
      'heartbeat_frequency'     => 60,
      'disable_self_pingbacks'  => true,
      'disable_rss_feeds'       => true,
      'remove_query_strings'    => true,
      'disable_comments'        => false,

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

      // Development tools
      'show_template_path'      => true,
      'debug_mode'              => false,
   ];

   public function __construct()
   {
      add_action('admin_init', [$this, 'register_settings']);
   }

   /**
    * Register option + dynamic fields
    */
   public function register_settings()
   {
      register_setting(
         'techr_settings_group',
         $this->option_key,
         [
            'type'              => 'array',
            // 'sanitize_callback' => [$this, 'sanitize'],
            'default'           => $this->defaults,
         ]
      );

      add_settings_section(
         'techr_optimization_section',
         __('Optimization Settings', 'techr-options'),
         [$this, 'section_cb'],
         'techr-options-dashboard'
      );

      // Add a field for each default key
      foreach ($this->defaults as $key => $default_value) {
         $label = $this->label_from_key($key);
         add_settings_field(
            $key,
            $label,
            [$this, 'render_field'],
            'techr-options-dashboard',
            'techr_optimization_section',
            ['key' => $key, 'default' => $default_value]
         );
      }
   }

   public function section_cb()
   {
      echo '<p>' . esc_html__('Manage optimization and performance toggles for your site.', 'techr-options') . '</p>';
   }

   /**
    * Render a field based on the default type
    */
   public function render_field($args)
   {
      $key     = $args['key'];
      $default = $args['default'];

      $options = get_option($this->option_key, []);
      $value   = isset($options[$key]) ? $options[$key] : $default;

      // Boolean -> checkbox (switch)
      if (is_bool($default)) {
         $checked = checked(1, (int) $value, false);
         echo '<label class="techr-switch">';
         echo '<input type="checkbox" name="' . esc_attr($this->option_key) . '[' . esc_attr($key) . ']" value="1" ' . $checked . '>';
         echo '<span class="techr-slider" aria-hidden="true"></span>';
         echo '</label>';
         // Small description for some keys
         $this->maybe_output_description($key);
         return;
      }

      // Integer -> number input
      if (is_int($default)) {
         $min = 0;
         $step = 1;
         // provide meaningful min/step for heartbeat frequency
         if ($key === 'heartbeat_frequency') {
            $min = 1;
            $step = 1;
         }
         printf(
            '<input type="number" name="%1$s[%2$s]" value="%3$s" min="%4$d" step="%5$d" class="small-text">',
            esc_attr($this->option_key),
            esc_attr($key),
            esc_attr((int) $value),
            intval($min),
            intval($step)
         );
         $this->maybe_output_description($key);
         return;
      }

      // Fallback -> text input
      printf(
         '<input type="text" name="%1$s[%2$s]" value="%3$s" class="regular-text">',
         esc_attr($this->option_key),
         esc_attr($key),
         esc_attr($value)
      );
      $this->maybe_output_description($key);
   }

   /**
    * Sanitize the whole options array
    */
   public function sanitize($input)
   {
      $sanitized = [];

      // If input isn't an array, return defaults
      if (!is_array($input)) {
         return $this->defaults;
      }

      foreach ($this->defaults as $key => $default) {

         // 1. Handle Checkboxes (Booleans)
         if (is_bool($default)) {
            // If the checkbox was checked, the key exists and equals 1.
            // If the checkbox was unchecked, the key is MISSING entirely.
            // We must force it to 0 (false) if missing.
            $sanitized[$key] = isset($input[$key]) ? 1 : 0;
            continue;
         }

         // 2. Handle Text/Numbers
         // If a text/number field is missing, we CAN revert to default.
         if (!isset($input[$key])) {
            $sanitized[$key] = $default;
            continue;
         }

         $raw = $input[$key];

         if (is_int($default)) {
            $val = intval($raw);

            // key-specific clamping
            if ($key === 'max_revisions') {
               $val = max(0, min(50, $val));
            }
            if ($key === 'heartbeat_frequency') {
               $val = max(1, min(3600, $val));
            }
            if ($key === 'image_quality') {
               $val = max(1, min(100, $val));
            }

            $sanitized[$key] = $val;
         } else {
            // general string clean
            $sanitized[$key] = sanitize_text_field($raw);
         }
      }

      return $sanitized;
   }
   /**
    * Helper: convert snake_case key to nice label
    */
   private function label_from_key($key)
   {
      // special labels mapping
      $map = [
         'max_revisions'       => 'Max Revisions',
         'heartbeat_frequency' => 'Heartbeat Frequency (seconds)',
         'image_quality'       => 'Image Quality (1-100)',
         'remove_wp_block_library' => 'Remove WP Block Library',
         'disable_xmlrpc'      => 'Disable XML-RPC',
      ];

      if (isset($map[$key])) {
         return $map[$key];
      }

      // default: turn snake_case into Title Case
      $label = str_replace('_', ' ', $key);
      return ucwords($label);
   }

   /**
    * Optional small descriptions
    */
   private function maybe_output_description($key)
   {
      $descriptions = [
         'limit_revisions' => 'Keep post revisions limited to reduce DB size.',
         'max_revisions' => 'Number of revisions to keep per post (0 = disable revisions).',
         'disable_heartbeat' => 'Disable WP Heartbeat API to reduce admin-ajax calls.',
         'optimize_images' => 'Resize/serve optimized image files on upload.',
         'convert_to_webp' => 'Generate WebP versions (server must support WebP).',
         'remove_query_strings' => 'Remove ?ver= query strings from assets URLs.',
         'restrict_admin_access' => 'Restrict admin access to specific IPs (plugin hook required).',
      ];

      if (isset($descriptions[$key])) {
         echo '<p class="description">' . esc_html($descriptions[$key]) . '</p>';
      }
   }

   /**
    * Public helper: get merged config (use this in your Optimization class)
    * returns defaults merged with saved options; boolean values cast to (bool)
    */
   public function get_config()
   {
      $saved = get_option($this->option_key, []);
      if (!is_array($saved)) {
         $saved = [];
      }
      $merged = array_merge($this->defaults, $saved);

      // force boolean types where appropriate
      foreach ($this->defaults as $k => $v) {
         if (is_bool($v)) {
            $merged[$k] = !empty($merged[$k]) ? true : false;
         } elseif (is_int($v)) {
            $merged[$k] = intval($merged[$k]);
         }
      }
      return $merged;
   }
}
