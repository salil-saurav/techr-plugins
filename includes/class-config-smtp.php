<?php

if (!defined('ABSPATH')) exit;

/**
 * SMTP Handler for WordPress emails
 *
 * Configures WordPress to use custom SMTP settings stored in ACF options
 */
class SMTP_Handler
{
   /**
    * Initialize the SMTP handler
    *
    * @return void
    */
   public static function init(): void
   {
      $instance = new self();
      add_action('phpmailer_init', [$instance, 'configure_smtp']);
   }

   /**
    * Configure PHPMailer to use custom SMTP settings
    *
    * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer The PHPMailer instance
    * @return void
    */
   public function configure_smtp($phpmailer): void
   {
      if (!$this->can_configure_smtp()) {
         return;
      }

      $smtp_settings = $this->get_smtp_settings();
      $this->apply_smtp_settings($phpmailer, $smtp_settings);
   }

   /**
    * Check if SMTP can be configured
    *
    * @return bool
    */
   private function can_configure_smtp(): bool
   {
      if (!function_exists('get_field')) {
         return false;
      }

      $email = get_field('smtp_email', 'option');
      $password = get_field('smtp_password', 'option');

      return !empty($email) && !empty($password);
   }

   /**
    * Get SMTP settings from ACF options
    *
    * @return array
    */
   private function get_smtp_settings(): array
   {
      return [
         'host' => $this->get_acf_option('smtp_host'),
         'port' => $this->get_acf_option('smtp_port', 587), // Default port
         'email' => $this->get_acf_option('smtp_email'),
         'password' => $this->get_acf_option('smtp_password'),
         'sender_name' => $this->get_acf_option('smtp_sender_name', get_bloginfo('name')), // Default to site name
         'encryption' => $this->get_acf_option('smtp_encryption', 'tls'), // Default to TLS
      ];
   }

   /**
    * Helper function to get ACF option with default fallback
    *
    * @param string $field_name ACF field name
    * @param mixed $default Default value if field is empty
    * @return mixed
    */
   private function get_acf_option(string $field_name, $default = '')
   {
      $value = get_field($field_name, 'option');
      return !empty($value) ? $value : $default;
   }

   /**
    * Apply SMTP settings to PHPMailer
    *
    * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer The PHPMailer instance
    * @param array $settings SMTP settings
    * @return void
    */
   private function apply_smtp_settings($phpmailer, array $settings): void
   {
      $phpmailer->isSMTP();
      $phpmailer->Host = $settings['host'];
      $phpmailer->SMTPAuth = true;
      $phpmailer->Username = $settings['email'];
      $phpmailer->Password = $settings['password'];
      $phpmailer->SMTPSecure = $settings['encryption'];
      $phpmailer->Port = $settings['port'];
      $phpmailer->setFrom($settings['email'], $settings['sender_name']);

      // Enable debug if in development environment
      if (defined('WP_DEBUG') && WP_DEBUG) {
         $phpmailer->SMTPDebug = 1;
      }
   }
}
