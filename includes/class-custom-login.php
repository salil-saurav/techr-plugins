<?php

if (!defined('ABSPATH')) exit;

/**
 * Custom Login Page Customization
 *
 * @package WordPress
 */

class Custom_Login_Manager
{
   /**
    * Initialize the login customizations
    */
   public function __construct()
   {
      $this->init_hooks();
   }

   /**
    * Initialize WordPress hooks
    */
   private function init_hooks()
   {

      // Actions

      add_action('login_enqueue_scripts', [$this, 'customize_login_styles'], 10, 1);
      // add_action('init', [$this, 'custom_login_rewrite_rule']);
      // add_action('init', [$this, 'redirect_wp_admin_to_404']);
      // add_action('wp_logout', [$this, 'custom_logout_redirect']);



      // Filters
      add_filter('login_headerurl', [$this, 'customize_logo_url']);
      add_filter('login_headertext', [$this, 'customize_logo_title']);
      add_filter('login_errors', [$this, 'customize_error_messages']);
      // add_filter('login_url', [$this, 'custom_login_url'], 10, 3);
   }

   /**
    * Custom login page styles
    */
   public function customize_login_styles()
   {
?>
      <style>
         @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap');
         @import url('https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0');

         :root {
            --login-primary-color: #8700ff;
         }

         body.login {
            margin: 0;
            padding: 0;
            font-family: "Poppins", sans-serif;
            background: #f0f0f0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
         }

         /* Glass card wrapper */
         #login {
            width: 380px !important;
            padding: 0 !important;
         }

         .login form {
            background: rgba(255, 255, 255, 0.18) !important;
            backdrop-filter: blur(12px) saturate(180%) !important;
            -webkit-backdrop-filter: blur(12px) saturate(180%);
            border-radius: 20px !important;
            padding: 40px !important;
            border: 1px solid var(--login-primary-color) !important.;
            /* box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25); */
            animation: fade-up 0.8s ease-out forwards !important;
            transform: translateY(10px);
            opacity: 0;

         }

         .login .button.wp-hide-pw .dashicons {
            color: var(--login-primary-color) !important;
         }

         @keyframes fade-up {
            from {
               opacity: 0;
               transform: translateY(25px);
            }

            to {
               opacity: 1;
               transform: translateY(0);
            }
         }

         /* Logo */
         .login h1 a {
            background-image: url(<?php echo esc_url(TECHR_OPTIONS_URL . "assets/images/logo.png"); ?>) !important;
            background-size: contain !important;
            width: 200px !important;
            height: 80px !important;
            margin-bottom: 10px !important;
         }

         /* Input Fields */
         .login form .input,
         .login input[type=text] {
            font-size: 14px !important;
            border-radius: 10px !important;
            padding: 12px 40px !important;
            border: 1px solid #fff !important;
            background: rgba(255, 255, 255, 0.25) !important;
            box-shadow: inset 0 0 20px rgba(255, 255, 255, 0.05);
            color: #fff !important;
         }

         .login form .input:focus {
            border-color: var(--login-primary-color) !important;
            box-shadow: 0 0 8px rgba(255, 255, 255, 0.6) !important;
         }

         /* Input Icons */
         .input-icon {
            position: relative;
         }

         .input-icon span.material-symbols-outlined {
            position: absolute;
            top: 11px;
            left: 12px;
            font-size: 20px;
            color: var(--login-primary-color);
            opacity: 0.85;
         }

         /* Hide Labels */
         label:not([for="rememberme"]) {
            display: none !important;
         }

         /* Submit Button */
         .wp-core-ui .button-primary {
            width: 100%;
            font-size: 15px !important;
            padding: 10px !important;
            border-radius: 50px !important;
            background: #ffffff !important;
            color: var(--login-primary-color) !important;
            border: none !important;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
         }

         .wp-core-ui .button-primary:hover {
            background: var(--login-primary-color) !important;
            color: #fff !important;
         }

         #login form p.submit {
            margin-top: 50px !important;
         }

         /* Remember me + Lost password */
         .login #nav,
         .login #backtoblog {
            text-align: center !important;
         }

         #nav a,
         #backtoblog a {
            color: #000 !important;
            font-weight: 400 !important;
         }

         #nav a:hover,
         #backtoblog a:hover {
            color: var(--login-primary-color) !important;
         }

         /* Errors (modern toast style) */
         #login_error,
         .message {
            background: rgba(255, 0, 0, 0.5) !important;
            backdrop-filter: blur(4px);
            color: #fff !important;
            border-left: 4px solid #ff6b6b !important;
            border-radius: 8px !important;
         }
      </style>

      <script>
         document.addEventListener("DOMContentLoaded", () => {
            const user = document.getElementById("user_login");
            const pass = document.getElementById("user_pass");

            user.setAttribute("placeholder", "Username or Email");
            pass.setAttribute("placeholder", "Password");

            // Wrap inputs with icon wrappers
            [user, pass].forEach((input, index) => {
               const wrapper = document.createElement("div");
               wrapper.classList.add("input-icon");

               const icon = document.createElement("span");
               icon.className = "material-symbols-outlined";
               icon.textContent = index === 0 ? "account_circle" : "lock";

               input.parentNode.insertBefore(wrapper, input);
               wrapper.appendChild(icon);
               wrapper.appendChild(input);
            });
         });
      </script>
<?php
   }


   /**
    * Customize login logo URL
    */
   public function customize_logo_url(): string
   {
      return home_url();
   }

   /**
    * Customize login logo title
    */
   public function customize_logo_title(): string
   {
      return get_bloginfo('name');
   }

   /**
    * Customize login error messages
    */
   public function customize_error_messages(): string
   {
      return 'Invalid credentials. Please try again.';
   }


   public function custom_login_rewrite_rule()
   {
      add_rewrite_rule('^login$', 'wp-login.php', 'top');
   }

   // public function custom_login_url($login_url)
   // {
   //     return home_url('/login/');
   // }

   public function redirect_wp_login()
   {
      if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false || strpos($_SERVER['REQUEST_URI'], 'login')) {
         wp_redirect(home_url('/wp-login.php'));
         exit;
      }
   }

   public function redirect_wp_admin_to_404()
   {
      if (is_admin() && !defined('DOING_AJAX') && !current_user_can('manage_options')) {
         global $wp_query;
         $wp_query->set_404();
         status_header(404);
         get_template_part('404');
         exit;
      }
   }

   public function custom_logout_redirect()
   {
      wp_redirect(home_url('/login'));
      exit;
   }
}
