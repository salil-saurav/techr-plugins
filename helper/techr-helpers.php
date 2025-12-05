<?php

/**
 * Techr Helper Class - Refactored
 * Handles XLSX uploads, image processing, and directory post management
 */

namespace TechrOption;

require_once __DIR__ . '/class-data-extractor.php';

class Techr_Helpers
{
   private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
   private const ALLOWED_FILE_TYPES = ['xlsx', 'xls'];
   private const POST_TYPE = 'directory';
   private const MEDIA_FOLDER = '/assets/media';

   /**
    * ACF field mappings for directory meta
    */
   private const FIELD_MAPPINGS = [
      'field_directory_video_url'       => 'video',
      'field_directory_minimum_price'   => 'minimumPrice',
      'field_directory_pricing_details' => 'pricingDetails',
      'field_directory_website'         => 'website',
      'field_directory_rating'          => 'rating',
      'field_directory_review_count'    => 'reviewCount',
      'field_directory_rank'            => 'rank',
      'field_directory_used_by'         => 'usedBy',
      'field_directory_overview'        => 'overview',
   ];

   /**
    * Read and parse uploaded XLSX file
    *
    * @param string $file_path Path to the XLSX file
    * @return array|WP_Error Parsed data or error
    */
   public function read_uploaded_xlsx($file_path)
   {
      if (!file_exists($file_path)) {
         return new \WP_Error('file_not_found', __('File not found.', 'techr'));
      }

      $reader = new Simple_XLSX_Reader($file_path);
      $data = $reader->get_data();

      if (is_wp_error($data)) {
         return $data;
      }

      if (empty($data)) {
         return new \WP_Error('empty_file', __('The uploaded file is empty.', 'techr'));
      }

      return $this->parse_xlsx_data($data);
   }

   /**
    * Parse XLSX data into associative arrays
    *
    * @param array $data Raw XLSX data
    * @return array|WP_Error Parsed rows or error
    */
   private function parse_xlsx_data($data)
   {
      $headers = array_shift($data);

      if (empty($headers)) {
         return new \WP_Error('no_headers', __('No headers found in the file.', 'techr'));
      }

      $result = [];
      foreach ($data as $row) {
         $row_assoc = $this->map_row_to_headers($headers, $row);

         if ($this->is_valid_row($row_assoc)) {
            $result[] = $row_assoc;
         }
      }

      return $result;
   }

   /**
    * Map row data to headers
    *
    * @param array $headers Column headers
    * @param array $row Row data
    * @return array Associative array
    */
   private function map_row_to_headers($headers, $row)
   {
      $row_assoc = [];
      foreach ($headers as $i => $header) {
         $row_assoc[trim($header)] = isset($row[$i]) ? trim($row[$i]) : '';
      }
      return $row_assoc;
   }

   /**
    * Check if row contains valid data
    *
    * @param array $row Row data
    * @return bool
    */
   private function is_valid_row($row)
   {
      return !empty(array_filter($row));
   }

   /**
    * Handle XLSX file upload and processing
    */
   public function handle_xlsx_upload()
   {
      $this->verify_upload_permissions();

      $file = $this->validate_uploaded_file();
      if (is_wp_error($file)) {
         wp_die($file->get_error_message(), __('Upload Error', 'techr'), ['back_link' => true]);
      }

      $data = $this->read_uploaded_xlsx($file['tmp_name']);
      if (is_wp_error($data)) {
         wp_die($data->get_error_message(), __('File Processing Error', 'techr'), ['back_link' => true]);
      }


      // echo '<pre>';
      // print_r($data);
      // echo '</pre>';
      $this->assing_terms_to_software_category($data);

      // $this->add_category_terms($data);
      // $this->assign_screenshots_gallery($data);
   }

   private function sanitize_term_ids($ids, $taxonomy)
   {
      if (!is_array($ids)) {
         $ids = explode(',', $ids);
      }

      $clean = [];

      foreach ($ids as $id) {
         $id = (int) trim($id);

         // ensure this term ID actually exists
         if ($id > 0 && term_exists($id, $taxonomy)) {
            $clean[] = $id;
         }
      }

      return $clean;
   }


   public function assing_terms_to_software_category($xlsx_data)
   {
      foreach ($xlsx_data as $row) {

         $post = get_page_by_path($row['slug'], OBJECT, 'directory');

         if (!$post) {
            continue; // invalid slug
         }

         $post_id = $post->ID;

         // clean and validate all term IDs
         $deployment   = $this->sanitize_term_ids($this->parse_file_or_ids($row['deployment']), 'deployment');
         $platforms    = $this->sanitize_term_ids($this->parse_file_or_ids($row['platforms']), 'platforms');
         $browsers     = $this->sanitize_term_ids($this->parse_file_or_ids($row['browsers']), 'browsers');
         $market       = $this->sanitize_term_ids($this->parse_file_or_ids($row['market']), 'market');
         $license      = $this->sanitize_term_ids($this->parse_file_or_ids($row['license']), 'license');
         $pricingModel = $this->sanitize_term_ids($this->parse_file_or_ids($row['pricingModel']), 'pricing_model');
         $training     = $this->sanitize_term_ids($this->parse_file_or_ids($row['training']), 'training');
         $support      = $this->sanitize_term_ids($this->parse_file_or_ids($row['support']), 'support');
         $features     = $this->sanitize_term_ids($this->parse_file_or_ids($row['features']), 'features');

         // assign terms by ID only — prevents unwanted creation
         wp_set_object_terms($post_id, $deployment, 'deployment');
         wp_set_object_terms($post_id, $platforms, 'platforms');
         wp_set_object_terms($post_id, $browsers, 'browsers');
         wp_set_object_terms($post_id, $market, 'market');
         wp_set_object_terms($post_id, $license, 'license');
         wp_set_object_terms($post_id, $pricingModel, 'pricing_model');
         wp_set_object_terms($post_id, $training, 'training');
         wp_set_object_terms($post_id, $support, 'support');
         wp_set_object_terms($post_id, $features, 'features');
      }
   }


   public function add_category_terms($xlsx_data)
   {
      global $wpdb;

      foreach ($xlsx_data as $row) {

         $term_id   = isset($row['id']) ? (int) $row['id'] : 0;
         $term_slug = isset($row['slug']) ? sanitize_title($row['slug']) : '';
         $term_name = isset($row['name']) ? sanitize_text_field($row['name']) : '';
         $taxonomy  = isset($row['group']) ? sanitize_key($row['group']) : '';

         if (! $term_id || ! $term_slug || ! $term_name) {
            continue;
         }

         // 🚫 Already exists by ID?
         $exists_by_id = $wpdb->get_var(
            $wpdb->prepare("SELECT term_id FROM $wpdb->terms WHERE term_id = %d", $term_id)
         );

         if ($exists_by_id) {
            continue;
         }

         $exists_by_slug = get_term_by('slug', $term_slug, $taxonomy);

         if ($exists_by_slug) {
            continue;
         }

         // 🚀 Create term with your own custom ID
         $this->create_term_with_custom_id($term_id, $term_name, $term_slug, $taxonomy);
      }
   }

   private function create_term_with_custom_id($term_id, $name, $slug, $taxonomy)
   {
      global $wpdb;

      // 1. Insert into wp_terms (auto increment avoided by specifying ID)
      $wpdb->insert(
         $wpdb->terms,
         [
            'term_id' => $term_id,
            'name'    => $name,
            'slug'    => $slug,
         ],
         ['%d', '%s', '%s']
      );

      // 2. Insert into wp_term_taxonomy
      $wpdb->insert(
         $wpdb->term_taxonomy,
         [
            'term_id'  => $term_id,
            'taxonomy' => $taxonomy,
            'description' => '',
            'parent'   => 0,
            'count'    => 0,
         ],
         ['%d', '%s', '%s', '%d', '%d']
      );

      return $term_id;
   }


   /**
    * Verify user has upload permissions
    */
   private function verify_upload_permissions()
   {
      if (!current_user_can('edit_posts')) {
         wp_die(
            __('You do not have permission to upload files.', 'techr'),
            __('Unauthorized', 'techr'),
            ['response' => 403]
         );
      }
   }

   /**
    * Validate uploaded file
    *
    * @return array|WP_Error File data or error
    */
   private function validate_uploaded_file()
   {
      if (!isset($_FILES['xlsx_file']) || $_FILES['xlsx_file']['error'] !== UPLOAD_ERR_OK) {
         return new \WP_Error('upload_error', __('No file uploaded or upload error occurred.', 'techr'));
      }

      $file = $_FILES['xlsx_file'];

      // Validate file type
      $file_type = wp_check_filetype($file['name']);
      if (!in_array($file_type['ext'], self::ALLOWED_FILE_TYPES)) {
         return new \WP_Error(
            'invalid_type',
            __('Please upload a valid Excel file (.xlsx or .xls)', 'techr')
         );
      }

      // Validate file size
      if ($file['size'] > self::MAX_FILE_SIZE) {
         return new \WP_Error(
            'file_too_large',
            sprintf(__('File size exceeds maximum allowed size of %dMB.', 'techr'), self::MAX_FILE_SIZE / 1024 / 1024)
         );
      }

      return $file;
   }

   /**
    * Map and create directory posts with ACF fields
    *
    * @param array $data Parsed XLSX data
    * @return array Results summary
    */
   private function map_directory_meta($data)
   {
      $results = [
         'success' => 0,
         'failed'  => 0,
         'errors'  => [],
      ];

      foreach ($data as $index => $post_data) {
         $row_number = $index + 2; // Account for header row

         if (!$this->validate_required_fields($post_data)) {
            $results['failed']++;
            $results['errors'][] = sprintf(
               __('Row %d: Missing required field "title"', 'techr'),
               $row_number
            );
            continue;
         }

         $post_id = $this->create_directory_post($post_data);

         if (is_wp_error($post_id)) {
            $results['failed']++;
            $results['errors'][] = sprintf(
               __('Row %d (%s): %s', 'techr'),
               $row_number,
               $post_data['title'],
               $post_id->get_error_message()
            );
            continue;
         }

         if ($this->update_acf_fields($post_id, $post_data)) {
            $results['success']++;
         } else {
            $results['failed']++;
            $results['errors'][] = sprintf(
               __('Row %d (%s): Post created but some fields failed to update', 'techr'),
               $row_number,
               $post_data['title']
            );
         }
      }

      return $results;
   }

   /**
    * Validate required fields
    *
    * @param array $post_data Post data
    * @return bool
    */
   private function validate_required_fields($post_data)
   {
      return !empty($post_data['title']);
   }

   /**
    * Create directory post
    *
    * @param array $post_data Post data
    * @return int|WP_Error Post ID or error
    */
   private function create_directory_post($post_data)
   {
      $post_args = [
         'post_title'   => sanitize_text_field($post_data['title']),
         'post_type'    => self::POST_TYPE,
         'post_status'  => 'draft',
         'post_content' => isset($post_data['extendedDescription'])
            ? wp_kses_post($post_data['extendedDescription'])
            : '',
         'post_excerpt' => isset($post_data['summary'])
            ? sanitize_textarea_field($post_data['summary'])
            : '',
      ];

      if (!empty($post_data['slug'])) {
         $post_args['post_name'] = sanitize_title($post_data['slug']);
      }

      return wp_insert_post($post_args, true);
   }

   /**
    * Update ACF fields for a post
    *
    * @param int $post_id Post ID
    * @param array $post_data Post data
    * @return bool Success status
    */
   private function update_acf_fields($post_id, $post_data)
   {
      if (!function_exists('update_field')) {
         return false;
      }

      $success = true;
      foreach (self::FIELD_MAPPINGS as $field_key => $data_key) {
         if (isset($post_data[$data_key]) && $post_data[$data_key] !== '') {
            $field_value = $this->sanitize_field_value($data_key, $post_data[$data_key]);

            if (!update_field($field_key, $field_value, $post_id)) {
               $success = false;
            }
         }
      }

      return $success;
   }

   /**
    * Sanitize field value based on data type
    *
    * @param string $data_key Field key
    * @param mixed $value Field value
    * @return mixed Sanitized value
    */
   private function sanitize_field_value($data_key, $value)
   {
      $numeric_fields = ['rating', 'reviewCount', 'rank', 'minimumPrice'];
      $url_fields = ['video', 'website'];

      if (in_array($data_key, $numeric_fields)) {
         return floatval($value);
      } elseif (in_array($data_key, $url_fields)) {
         return esc_url_raw($value);
      }

      return sanitize_textarea_field($value);
   }

   /**
    * Assign screenshots gallery to posts
    *
    * @param array $data Parsed data
    */
   private function assign_screenshots_gallery($data)
   {
      $acf_field_key = 'field_directory_screenshots';

      foreach ($data as $item) {
         if (empty($item['screenshots']) || empty($item['slug'])) {
            continue;
         }

         $post = get_page_by_path($item['slug'], OBJECT, self::POST_TYPE);
         if (!$post) {
            continue;
         }

         $gallery_ids = $this->upload_gallery_images($item['screenshots'], $post->ID);

         if (!empty($gallery_ids) && function_exists('update_field')) {
            update_field($acf_field_key, $gallery_ids, $post->ID);
         }
      }
   }

   /**
    * Upload multiple gallery images
    *
    * @param string $screenshots_string Pipe-separated filenames
    * @param int $post_id Post ID
    * @return array Attachment IDs
    */
   private function upload_gallery_images($screenshots_string, $post_id)
   {
      if (empty($screenshots_string)) {
         return [];
      }

      $files = $this->parse_file_or_ids($screenshots_string);
      $source_folder = get_stylesheet_directory() . self::MEDIA_FOLDER;
      $attachment_ids = [];

      foreach ($files as $filename) {
         $att_id = $this->upload_image_to_acf(
            sanitize_file_name($filename),
            $source_folder,
            $post_id,
            false
         );

         if ($att_id) {
            $attachment_ids[] = $att_id;
         }
      }

      return $attachment_ids;
   }

   /**
    * Parse screenshot filenames from pipe-separated string
    *
    * @param string $string Pipe-separated filenames
    * @return array Filenames
    */
   private function parse_file_or_ids($string)
   {
      return array_filter(array_map('trim', explode('|', $string)));
   }

   /**
    * Upload image and optionally update ACF field
    *
    * @param string $filename Image filename
    * @param string $source_folder Source folder path
    * @param int $post_id Post ID
    * @param string|bool $acf_field_key ACF field key or false
    * @return int|bool Attachment ID or false on failure
    */
   public function upload_image_to_acf($filename, $source_folder, $post_id, $acf_field_key = false)
   {
      $full_source_path = $this->get_full_image_path($source_folder, $filename);

      if (!file_exists($full_source_path)) {
         error_log("Import Error: Image not found at " . $full_source_path);
         return false;
      }

      $upload = $this->upload_file_to_wordpress($full_source_path, $filename);
      if (!$upload) {
         return false;
      }

      $attach_id = $this->create_attachment($upload['file'], $post_id);
      if (!$attach_id) {
         return false;
      }

      $this->generate_image_metadata($attach_id, $upload['file']);

      if ($acf_field_key && function_exists('update_field')) {
         update_field($acf_field_key, $attach_id, $post_id);
      }

      return $attach_id;
   }

   /**
    * Get full image path
    *
    * @param string $source_folder Source folder
    * @param string $filename Filename
    * @return string Full path
    */
   private function get_full_image_path($source_folder, $filename)
   {
      return rtrim($source_folder, '/') . '/' . $filename;
   }

   /**
    * Upload file to WordPress uploads directory
    *
    * @param string $full_source_path Full source path
    * @param string $filename Filename
    * @return array|bool Upload result or false
    */
   private function upload_file_to_wordpress($full_source_path, $filename)
   {
      $file_content = file_get_contents($full_source_path);
      $upload = wp_upload_bits($filename, null, $file_content);

      if (!empty($upload['error'])) {
         error_log("Import Error: WP Upload Failed - " . $upload['error']);
         return false;
      }

      return $upload;
   }

   /**
    * Create attachment in WordPress
    *
    * @param string $file_path File path
    * @param int $post_id Post ID
    * @return int|bool Attachment ID or false
    */
   private function create_attachment($file_path, $post_id)
   {
      $file_name = basename($file_path);
      $file_type = wp_check_filetype($file_name, null);

      $attachment_data = [
         'post_mime_type' => $file_type['type'],
         'post_title'     => sanitize_file_name($file_name),
         'post_content'   => '',
         'post_status'    => 'inherit'
      ];

      return wp_insert_attachment($attachment_data, $file_path, $post_id);
   }

   /**
    * Generate image metadata and thumbnails
    *
    * @param int $attach_id Attachment ID
    * @param string $file_path File path
    */
   private function generate_image_metadata($attach_id, $file_path)
   {
      require_once(ABSPATH . 'wp-admin/includes/image.php');
      $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
      wp_update_attachment_metadata($attach_id, $attach_data);
   }
}
