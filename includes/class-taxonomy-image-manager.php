<?php

namespace TechrOption;

class Taxonomy_Image_Manager
{

   private $taxonomies = [];

   /**
    * @param array $taxonomies List of taxonomy slugs (e.g., ['software-categories', 'brands'])
    */
   public function __construct(array $taxonomies)
   {
      $this->taxonomies = $taxonomies;
   }

   public function init(): void
   {
      add_action('admin_enqueue_scripts', [$this, 'enqueue_media_scripts']);

      foreach ($this->taxonomies as $taxonomy) {
         // Add Field
         add_action("{$taxonomy}_add_form_fields", [$this, 'add_image_field']);
         // Edit Field
         add_action("{$taxonomy}_edit_form_fields", [$this, 'edit_image_field']);
         // Save Data
         add_action("created_{$taxonomy}", [$this, 'save_image_data']);
         add_action("edited_{$taxonomy}", [$this, 'save_image_data']);
         // Admin Column (Optional: Shows image in the list)
         add_filter("manage_edit-{$taxonomy}_columns", [$this, 'add_column_header']);
         add_filter("manage_{$taxonomy}_custom_column", [$this, 'add_column_content'], 10, 3);
      }
   }

   /**
    * Enqueue WordPress Media Uploader and Custom JS
    */
   public function enqueue_media_scripts(): void
   {
      // Only load on edit-tags.php or term.php screens
      $screen = get_current_screen();
      if (!$screen || !in_array($screen->base, ['edit-tags', 'term'])) {
         return;
      }

      if (!in_array($screen->taxonomy, $this->taxonomies)) {
         return;
      }

      wp_enqueue_media();

      // Inline JS for handling the media uploader
      wp_add_inline_script('common', $this->get_js_logic());
   }

   /**
    * UI for "Add New Term" screen
    */
   public function add_image_field(): void
   {
?>
      <div class="form-field term-group">
         <label><?php _e('Thumbnail', 'text-domain'); ?></label>
         <div class="tax-image-wrapper">
            <input type="hidden" name="taxonomy_image_id" class="tax-image-id" value="">
            <div class="image-preview-wrapper" style="margin-bottom: 10px;">
               <img class="tax-image-preview" src="" style="display:none; max-width: 150px; border: 1px solid #ddd; padding: 5px;">
            </div>
            <button type="button" class="button button-secondary upload-tax-image"><?php _e('Upload/Add Image', 'text-domain'); ?></button>
            <button type="button" class="button button-link remove-tax-image" style="display:none; color: #a00;"><?php _e('Remove', 'text-domain'); ?></button>
         </div>
      </div>
   <?php
   }

   /**
    * UI for "Edit Term" screen
    */
   public function edit_image_field($term): void
   {
      $image_id = get_term_meta($term->term_id, 'taxonomy_image_id', true);
      $image_url = $image_id ? wp_get_attachment_thumb_url($image_id) : '';
      $display = $image_id ? 'block' : 'none';
   ?>
      <tr class="form-field term-group-wrap">
         <th scope="row"><label><?php _e('Thumbnail', 'text-domain'); ?></label></th>
         <td>
            <div class="tax-image-wrapper">
               <input type="hidden" name="taxonomy_image_id" class="tax-image-id" value="<?php echo esc_attr($image_id); ?>">
               <div class="image-preview-wrapper" style="margin-bottom: 10px;">
                  <img class="tax-image-preview" src="<?php echo esc_url($image_url); ?>" style="display:<?php echo $display; ?>; max-width: 150px; border: 1px solid #ddd; padding: 5px;">
               </div>
               <button type="button" class="button button-secondary upload-tax-image"><?php _e('Upload/Add Image', 'text-domain'); ?></button>
               <button type="button" class="button button-link remove-tax-image" style="display:<?php echo $display; ?>; color: #a00;"><?php _e('Remove', 'text-domain'); ?></button>
            </div>
         </td>
      </tr>
<?php
   }

   /**
    * Save the image ID
    */
   public function save_image_data($term_id): void
   {
      if (isset($_POST['taxonomy_image_id'])) {
         $image_id = absint($_POST['taxonomy_image_id']);
         if ($image_id) {
            update_term_meta($term_id, 'taxonomy_image_id', $image_id);
         } else {
            delete_term_meta($term_id, 'taxonomy_image_id');
         }
      }
   }

   /**
    * Add column to Admin List
    */
   public function add_column_header($columns)
   {
      $new_columns = [];
      // Insert 'thumb' column before 'name' if possible
      foreach ($columns as $key => $value) {
         if ($key === 'name') {
            $new_columns['thumb'] = __('Thumbnail', 'text-domain');
         }
         $new_columns[$key] = $value;
      }
      return $new_columns;
   }

   /**
    * Render column content
    */
   public function add_column_content($content, $column_name, $term_id)
   {
      if ($column_name === 'thumb') {
         $image_id = get_term_meta($term_id, 'taxonomy_image_id', true);
         if ($image_id) {
            $img = wp_get_attachment_image_src($image_id, [50, 50]);
            if ($img) {
               return '<img src="' . esc_url($img[0]) . '" style="width:50px;height:50px;object-fit:cover;border-radius:4px;" />';
            }
         }
         return '<span style="color:#ccc;">—</span>';
      }
      return $content;
   }

   /**
    * Javascript logic (Inlined to avoid extra HTTP requests for one file)
    */
   private function get_js_logic(): string
   {
      return "
         jQuery(document).ready(function($){
            var mediaUploader;

            // Upload Image
            $(document).on('click', '.upload-tax-image', function(e) {
               e.preventDefault();
               var wrapper = $(this).closest('.tax-image-wrapper');

               if (mediaUploader) {
                  mediaUploader.open();
                  return;
               }

               mediaUploader = wp.media.frames.file_frame = wp.media({
                  title: 'Choose Taxonomy Image',
                  button: { text: 'Choose Image' },
                  multiple: false
               });

               mediaUploader.on('select', function() {
                  var attachment = mediaUploader.state().get('selection').first().toJSON();
                  wrapper.find('.tax-image-id').val(attachment.id);
                  wrapper.find('.tax-image-preview').attr('src', attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url).show();
                  wrapper.find('.remove-tax-image').show();
               });

               mediaUploader.open();
            });

            // Remove Image
            $(document).on('click', '.remove-tax-image', function(e) {
               e.preventDefault();
               var wrapper = $(this).closest('.tax-image-wrapper');
               wrapper.find('.tax-image-id').val('');
               wrapper.find('.tax-image-preview').hide().attr('src', '');
               $(this).hide();
            });
         });
            ";
   }
}
