<?php

/**
 * Handle file upload in WordPress admin
 */

?>

<div class="thr_wrapper">
   <h1>Techr Options</h1>

   <form method="post" action="options.php">
      <?php
      /*
      settings_fields('techr_settings_group');
      do_settings_sections('techr-options-dashboard');
      submit_button(__('Save Settings', 'techr-options'));
      */
      ?>
   </form>

   <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload_xlsx">
      <input type="file" name="xlsx_file" accept=".xlsx">
      <button type="submit">Upload and Read XLSX</button>
   </form>
</div>
