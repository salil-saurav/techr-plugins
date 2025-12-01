<?php

/**
 * Techr Dashboard page
 */
?>

<div class="thr_wrapper">
   <h1>Techr Options</h1>

   <form method="post" action="options.php">
      <?php
      settings_fields('techr_settings_group');
      do_settings_sections('techr-options-dashboard');
      submit_button(__('Save Settings', 'techr-options'));
      ?>
   </form>
</div>
