<?php

if (!defined('ABSPATH')) exit;

/**
 * Custom Code Editor
 *
 * Adds custom code (HTML, CSS, JS) editor fields to posts and pages
 * with syntax highlighting via CodeMirror
 */
class Custom_Code_Editor
{
   /**
    * Post types that support custom code
    *
    * @var array
    */
   private $supported_post_types = ['post', 'page'];

   /**
    * Code section definitions
    *
    * @var array
    */
   private $code_sections = [
      'header' => [
         ['type' => 'html', 'label' => 'HTML', 'mode' => 'text/html'],
         ['type' => 'css', 'label' => 'CSS', 'mode' => 'text/css'],
         ['type' => 'js', 'label' => 'JavaScript', 'mode' => 'text/javascript']
      ],
      'footer' => [
         ['type' => 'html', 'label' => 'HTML', 'mode' => 'text/html'],
         ['type' => 'css', 'label' => 'CSS', 'mode' => 'text/css'],
         ['type' => 'js', 'label' => 'JavaScript', 'mode' => 'text/javascript']
      ]
   ];

   /**
    * Initialize the plugin
    *
    * @return void
    */
   public static function init(): void
   {
      $instance = new self();
      $instance->setup_hooks();
   }

   /**
    * Setup hooks and actions
    *
    * @return void
    */
   public function setup_hooks(): void
   {
      // Admin assets
      add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

      // Meta boxes
      add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
      add_action('save_post', [$this, 'save_meta_boxes']);

      // Frontend output
      add_action('wp_head', [$this, 'output_header_code']);
      add_action('wp_footer', [$this, 'output_footer_code']);
   }

   /**
    * Enqueue admin assets for the editor
    *
    * @return void
    */
   public function enqueue_admin_assets(): void
   {
      global $post_type;

      // Only load on supported post types
      if (!in_array($post_type, $this->supported_post_types)) {
         return;
      }

      // Enqueue FontAwesome
      wp_enqueue_style(
         'fontawesome-css',
         'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css'
      );

      // Enqueue CodeMirror 5.65.17
      wp_enqueue_style(
         'codemirror-css',
         'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.17/codemirror.min.css'
      );

      wp_enqueue_script(
         'codemirror-js',
         'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.17/codemirror.min.js',
         [],
         null,
         true
      );

      // Enqueue modes
      $codemirror_addons = [
         ['mode/xml/xml.min.js', 'codemirror-html'],
         ['mode/css/css.min.js', 'codemirror-css-mode'],
         ['mode/javascript/javascript.min.js', 'codemirror-js-mode'],
         ['addon/edit/closebrackets.min.js', 'codemirror-closebrackets'],
         ['addon/edit/closetag.min.js', 'codemirror-closetag']
      ];

      foreach ($codemirror_addons as [$path, $handle]) {
         wp_enqueue_script(
            $handle,
            "https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.17/{$path}",
            ['codemirror-js'],
            null,
            true
         );
      }

      // jQuery UI
      wp_enqueue_script('jquery-ui-accordion');
      wp_enqueue_style(
         'jquery-ui-css',
         'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css'
      );

      // Add inline scripts and styles
      add_action('admin_footer', [$this, 'output_admin_scripts']);
   }

   /**
    * Output admin scripts and styles
    *
    * @return void
    */
   public function output_admin_scripts(): void
   {
?>
      <script>
         document.addEventListener('DOMContentLoaded', () => {
            // Initialize CodeMirror for each textarea
            document.querySelectorAll('textarea.codemirror').forEach(textarea => {
               const mode = textarea.dataset.mode;

               const cm = CodeMirror.fromTextArea(textarea, {
                  lineNumbers: true,
                  mode: mode,
                  theme: 'default',
                  extraKeys: {
                     "Ctrl-Space": "autocomplete"
                  },
                  autoCloseBrackets: true,
                  autoCloseTags: true
               });
            });

            // Accordion functionality
            const accordionHeaders = document.querySelectorAll('.accordion-header');
            accordionHeaders.forEach(header => {
               header.addEventListener('click', () => {
                  const content = header.nextElementSibling;
                  const icon = header.querySelector('.accordion-icon');

                  content.style.display = content.style.display === 'none' ? 'block' : 'none';
                  icon.classList.toggle('open');
               });
            });

            // Collapsing by default
            document.querySelectorAll('.accordion-content').forEach(content => {
               content.style.display = 'none';
            });
         });
      </script>
      <style>
         .accordion-header {
            cursor: pointer;
            padding: 10px;
            background-color: #f1f1f1;
            border: 1px solid #ccc;
            margin-bottom: 5px;
            position: relative;
         }

         .accordion-icon {
            position: absolute;
            right: 10px;
            top: 10px;
            transition: transform 0.2s;
         }

         .accordion-icon.open {
            transform: rotate(180deg);
         }

         .accordion-content .CodeMirror {
            border: 1px solid #ccc;
            margin-bottom: 5px;
         }
      </style>
   <?php
   }

   /**
    * Register meta boxes
    *
    * @return void
    */
   public function register_meta_boxes(): void
   {
      $meta_boxes = [
         [
            'id' => 'header_code_meta_box',
            'title' => 'Header Code',
            'callback' => [$this, 'render_header_meta_box']
         ],
         [
            'id' => 'footer_code_meta_box',
            'title' => 'Footer Code',
            'callback' => [$this, 'render_footer_meta_box']
         ]
      ];

      foreach ($this->supported_post_types as $post_type) {
         foreach ($meta_boxes as $box) {
            add_meta_box(
               $box['id'],
               $box['title'],
               $box['callback'],
               $post_type,
               'normal',
               'high'
            );
         }
      }
   }

   /**
    * Render a code section for the meta box
    *
    * @param string $type Type of code (header_html, footer_css, etc.)
    * @param string $label Label to display
    * @param string $mode CodeMirror mode
    * @param string $value Current value
    * @return void
    */
   private function render_code_section($type, $label, $mode, $value): void
   {
   ?>
      <div class="accordion-header"><?php echo esc_html($label); ?> <i class="fa fa-chevron-down accordion-icon"></i></div>
      <div class="accordion-content">
         <textarea name="<?php echo esc_attr($type); ?>" class="codemirror" data-mode="<?php echo esc_attr($mode); ?>" style="width: 100%; height: 100px;"><?php echo esc_textarea($value); ?></textarea>
      </div>
<?php
   }

   /**
    * Render header code meta box
    *
    * @param WP_Post $post Current post object
    * @return void
    */
   public function render_header_meta_box($post): void
   {
      wp_nonce_field('custom_code_editor_nonce', 'custom_code_editor_nonce');

      foreach ($this->code_sections['header'] as $section) {
         $meta_key = "_header_{$section['type']}";
         $field_name = "header_{$section['type']}";
         $value = get_post_meta($post->ID, $meta_key, true);

         $this->render_code_section(
            $field_name,
            $section['label'],
            $section['mode'],
            $value
         );
      }
   }

   /**
    * Render footer code meta box
    *
    * @param WP_Post $post Current post object
    * @return void
    */
   public function render_footer_meta_box($post): void
   {
      foreach ($this->code_sections['footer'] as $section) {
         $meta_key = "_footer_{$section['type']}";
         $field_name = "footer_{$section['type']}";
         $value = get_post_meta($post->ID, $meta_key, true);

         $this->render_code_section(
            $field_name,
            $section['label'],
            $section['mode'],
            $value
         );
      }
   }

   /**
    * Save meta box data
    *
    * @param int $post_id Post ID
    * @return void
    */
   public function save_meta_boxes($post_id): void
   {
      // Verify nonce and autosave
      if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
         return;
      }

      if (
         !isset($_POST['custom_code_editor_nonce']) ||
         !wp_verify_nonce($_POST['custom_code_editor_nonce'], 'custom_code_editor_nonce')
      ) {
         return;
      }

      if (!current_user_can('edit_post', $post_id)) {
         return;
      }

      // Save each field
      foreach ($this->code_sections as $location => $sections) {
         foreach ($sections as $section) {
            $field_name = "{$location}_{$section['type']}";
            $meta_key = "_{$field_name}";

            if (isset($_POST[$field_name])) {
               $value = $this->sanitize_code($_POST[$field_name], $section['type']);
               update_post_meta($post_id, $meta_key, $value);
            }
         }
      }
   }

   /**
    * Sanitize code based on type
    *
    * @param string $code The code to sanitize
    * @param string $type The type of code (html, css, js)
    * @return string Sanitized code
    */
   private function sanitize_code($code, $type): string
   {
      if ($type === 'html') {
         // Allow specific HTML tags and attributes
         return wp_kses($code, $this->get_allowed_html_tags());
      } elseif ($type === 'css') {
         // Basic sanitization for CSS - strip potentially harmful content
         $code = preg_replace('/@import\s+url/i', '/* @import url */', $code);
         return sanitize_textarea_field($code);
      } elseif ($type === 'js') {
         // Basic sanitization for JavaScript
         return sanitize_textarea_field($code);
      }

      return sanitize_textarea_field($code);
   }

   /**
    * Get allowed HTML tags for wp_kses
    *
    * @return array Allowed HTML tags and attributes
    */
   private function get_allowed_html_tags(): array
   {
      // Get extended allowed HTML tags
      $allowed_html = wp_kses_allowed_html('post');

      // Add script and style tags for the HTML editor
      $allowed_html['script'] = ['type' => true];
      $allowed_html['style'] = ['type' => true];

      return $allowed_html;
   }

   /**
    * Output custom header code
    *
    * @return void
    */
   public function output_header_code(): void
   {
      if (!is_single() && !is_page()) {
         return;
      }

      global $post;

      if (!$post || !in_array($post->post_type, $this->supported_post_types)) {
         return;
      }

      foreach ($this->code_sections['header'] as $section) {
         $meta_key = "_header_{$section['type']}";
         $code = get_post_meta($post->ID, $meta_key, true);

         if (!empty($code)) {
            $this->output_code($code, $section['type']);
         }
      }
   }

   /**
    * Output custom footer code
    *
    * @return void
    */
   public function output_footer_code(): void
   {
      if (!is_single() && !is_page()) {
         return;
      }

      global $post;

      if (!$post || !in_array($post->post_type, $this->supported_post_types)) {
         return;
      }

      foreach ($this->code_sections['footer'] as $section) {
         $meta_key = "_footer_{$section['type']}";
         $code = get_post_meta($post->ID, $meta_key, true);

         if (!empty($code)) {
            $this->output_code($code, $section['type']);
         }
      }
   }

   /**
    * Output code with appropriate wrapping
    *
    * @param string $code The code to output
    * @param string $type The type of code (html, css, js)
    * @return void
    */
   private function output_code($code, $type): void
   {
      switch ($type) {
         case 'html':
            echo $code;
            break;

         case 'css':
            printf('<style>%s</style>', $code);
            break;

         case 'js':
            printf('<script>%s</script>', $code);
            break;
      }
   }
}

// Initialize the plugin
add_action('init', ['Custom_Code_Editor', 'init']);
