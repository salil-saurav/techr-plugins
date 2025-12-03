<?php

/**
 * Techr Helper class
 */

namespace TechrOption;

class Techer_Helper
{
   public function upload_image_to_acf($filename, $source_folder, $post_id, $acf_field_key)
   {
      // 1. Sanitize the filename and build full path
      $filename = sanitize_file_name($filename);

      // Ensure folder has trailing slash
      $source_folder = rtrim($source_folder, '/') . '/';
      $full_source_path = $source_folder . $filename;

      // 2. Check if file exists in the source folder
      if (!file_exists($full_source_path)) {
         error_log("Import Error: Image not found at " . $full_source_path);
         return false;
      }

      // 3. Read file content
      $file_content = file_get_contents($full_source_path);

      // 4. Upload file to WordPress Uploads Directory
      // wp_upload_bits handles the year/month folder structure automatically
      $upload = wp_upload_bits($filename, null, $file_content);

      if (!empty($upload['error'])) {
         error_log("Import Error: WP Upload Failed - " . $upload['error']);
         return false;
      }

      // 5. Prepare Attachment Data
      $file_path = $upload['file'];
      $file_name = basename($file_path);
      $file_type = wp_check_filetype($file_name, null);

      $attachment_data = array(
         'post_mime_type' => $file_type['type'],
         'post_title'     => sanitize_file_name($file_name),
         'post_content'   => '',
         'post_status'    => 'inherit'
      );

      // 6. Insert Attachment into Database
      $attach_id = wp_insert_attachment($attachment_data, $file_path, $post_id);

      // 7. Generate Image Sizes (Thumbnails, Medium, Large)
      // This is required for the image to show up correctly in WP Admin
      require_once(ABSPATH . 'wp-admin/includes/image.php');
      $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
      wp_update_attachment_metadata($attach_id, $attach_data);

      // 8. Update ACF Field
      // Check if ACF function exists to prevent fatal errors
      if (function_exists('update_field')) {
         // For Image fields, ACF expects the Attachment ID
         update_field($acf_field_key, $attach_id, $post_id);
      }

      return $attach_id;
   }
}
