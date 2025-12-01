<?php

namespace TechrOption;

/**
 * Featured Post Manager
 * Adds a WooCommerce-style Star toggle to Admin Columns
 */
class Featured_Post_Manager
{

   private $post_types = [];

   /**
    * @param array $post_types List of CPT slugs (e.g. ['directory', 'event'])
    */
   public function __construct(array $post_types)
   {
      $this->post_types = $post_types;
   }

   public function init(): void
   {
      add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
      add_action('wp_ajax_toggle_featured_status', [$this, 'ajax_toggle_featured']);

      foreach ($this->post_types as $post_type) {
         // Add Column Header
         add_filter("manage_{$post_type}_posts_columns", [$this, 'add_column_header']);
         // Add Column Content
         add_action("manage_{$post_type}_posts_custom_column", [$this, 'render_column_content'], 10, 2);
         // Make Column Sortable (Optional)
         // add_filter("manage_edit-{$post_type}_sortable_columns", [$this, 'make_column_sortable']);
      }

      // Handle sorting logic
      add_action('pre_get_posts', [$this, 'handle_sorting']);
   }

   /**
    * Add the Star Column Header
    */
   public function add_column_header(array $columns): array
   {
      $new_columns = [];
      $inserted = false;

      foreach ($columns as $key => $value) {
         // Insert before 'title' or 'date'
         if (!$inserted && ($key === 'date')) {
            $new_columns['featured'] = '';
            $inserted = true;
         }
         $new_columns[$key] = $value;
      }

      return $inserted ? $new_columns : array_merge(['featured' => 'Featured'], $columns);
   }

   /**
    * Render the Star Icon
    */
   public function render_column_content(string $column, int $post_id): void
   {
      if ($column !== 'featured') return;

      $is_featured = get_post_meta($post_id, '_is_featured', true) === 'yes';

      $icon_class = $is_featured ? 'dashicons-star-filled' : 'dashicons-star-empty';
      $status_val = $is_featured ? 'yes' : 'no';
      $tooltip    = $is_featured ? 'Yes' : 'No';

      // Nonce for security
      $nonce = wp_create_nonce('featured_toggle_' . $post_id);

      echo sprintf(
         '<a href="#" class="toggle-featured-post %s" data-id="%d" data-status="%s" data-nonce="%s" title="%s"></a>',
         esc_attr($icon_class),
         esc_attr($post_id),
         esc_attr($status_val),
         esc_attr($nonce),
         esc_attr($tooltip)
      );
   }

   /**
    * Register Sortable Column
    */
   public function make_column_sortable(array $columns): array
   {
      $columns['featured'] = 'featured';
      return $columns;
   }

   /**
    * Handle the Sorting Query
    */
   public function handle_sorting($query): void
   {
      if (!is_admin() || !$query->is_main_query()) return;

      if ($query->get('orderby') === 'featured') {
         $query->set('meta_key', '_is_featured');
         $query->set('orderby', 'meta_value');
      }
   }

   /**
    * AJAX Handler to Toggle Status
    */
   public function ajax_toggle_featured(): void
   {
      // Verify inputs
      $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
      $nonce   = isset($_POST['nonce']) ? $_POST['nonce'] : '';

      if (!$post_id || !wp_verify_nonce($nonce, 'featured_toggle_' . $post_id)) {
         wp_send_json_error(['message' => 'Security check failed']);
      }

      if (!current_user_can('edit_post', $post_id)) {
         wp_send_json_error(['message' => 'Permission denied']);
      }

      // Toggle logic
      $current_status = get_post_meta($post_id, '_is_featured', true);
      $new_status     = ($current_status === 'yes') ? 'no' : 'yes';

      update_post_meta($post_id, '_is_featured', $new_status);

      wp_send_json_success(['new_status' => $new_status]);
   }

   /**
    * Add CSS and JS inline for simplicity
    */
   public function enqueue_assets(): void
   {
      $screen = get_current_screen();
      if (!$screen || !in_array($screen->post_type, $this->post_types)) return;

?>
      <style>
         .column-featured {
            width: 45px;
            text-align: center;
         }

         .toggle-featured-post {
            text-decoration: none;
            outline: none;
            box-shadow: none;
         }

         .toggle-featured-post:before {
            font-family: dashicons;
            font-size: 20px;
            display: inline-block;
            color: #ccc;
            /* Default empty star color */
         }

         .toggle-featured-post.dashicons-star-empty:before {
            content: "\f154";
         }

         .toggle-featured-post.dashicons-star-filled:before {
            content: "\f155";
            color: #f0c33c;
            /* Gold */
         }

         /* Loading Spinner */
         .toggle-featured-post.loading:before {
            content: "\f463";
            animation: spin 2s infinite linear;
            color: #0073aa;
         }

         @keyframes spin {
            0% {
               transform: rotate(0deg);
            }

            100% {
               transform: rotate(360deg);
            }
         }
      </style>

      <script>
         document.addEventListener('DOMContentLoaded', function() {
            var buttons = document.querySelectorAll('.toggle-featured-post');
            if (!buttons.length) return;

            buttons.forEach(function(btn) {
               btn.addEventListener('click', function(e) {
                  e.preventDefault();
                  if (btn.classList.contains('loading')) return; // Prevent double click

                  var post_id = btn.getAttribute('data-id') || btn.dataset.id;
                  var nonce = btn.getAttribute('data-nonce') || btn.dataset.nonce;

                  // UI Optimistic Update (Loading state)
                  btn.classList.add('loading');

                  var params = new URLSearchParams();
                  params.append('action', 'toggle_featured_status');
                  params.append('post_id', post_id);
                  params.append('nonce', nonce);

                  fetch(typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                           'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                        },
                        body: params.toString()
                     })
                     .then(function(res) {
                        return res.json();
                     })
                     .then(function(response) {
                        btn.classList.remove('loading');
                        if (response && response.success) {
                           var newStatus = response.data && response.data.new_status ? response.data.new_status : '';

                           if (newStatus === 'yes') {
                              btn.classList.remove('dashicons-star-empty');
                              btn.classList.add('dashicons-star-filled');
                              btn.setAttribute('title', 'Yes');
                           } else {
                              btn.classList.remove('dashicons-star-filled');
                              btn.classList.add('dashicons-star-empty');
                              btn.setAttribute('title', 'No');
                           }
                        } else {
                           var msg = (response && response.data && response.data.message) ? response.data.message : 'Unknown error';
                           alert('Error: ' + msg);
                        }
                     })
                     .catch(function() {
                        btn.classList.remove('loading');
                        alert('Connection error');
                     });
               });
            });
         });
      </script>
<?php
   }
}
