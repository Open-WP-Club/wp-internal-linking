<?php
/*
Plugin Name: Auto Internal Linking
Description: Automates and simplifies internal linking to boost SEO.
Version: 1.2
Author: Your Name
*/

// Don't allow direct access to the plugin file
if (!defined('ABSPATH')) {
  exit;
}

class Auto_Internal_Linking
{
  private $options;

  public function __construct()
  {
    add_action('admin_menu', array($this, 'add_plugin_page'));
    add_action('admin_init', array($this, 'page_init'));
    add_action('add_meta_boxes', array($this, 'add_meta_box'));
    add_action('save_post', array($this, 'save_meta_box_data'));
    add_filter('the_content', array($this, 'add_internal_links'));

    $this->options = get_option('auto_internal_linking_options');
  }

  public function add_plugin_page()
  {
    add_options_page(
      'Auto Internal Linking Settings',
      'Auto Internal Linking',
      'manage_options',
      'auto-internal-linking',
      array($this, 'create_admin_page')
    );
  }

  public function create_admin_page()
  {
?>
    <div class="wrap">
      <h1>Auto Internal Linking Settings</h1>
      <form method="post" action="options.php">
        <?php
        settings_fields('auto_internal_linking_option_group');
        do_settings_sections('auto-internal-linking-admin');
        submit_button();
        ?>
      </form>
    </div>
  <?php
  }

  public function page_init()
  {
    register_setting(
      'auto_internal_linking_option_group',
      'auto_internal_linking_options',
      array($this, 'sanitize')
    );

    add_settings_section(
      'auto_internal_linking_setting_section',
      'Settings',
      array($this, 'section_info'),
      'auto-internal-linking-admin'
    );

    add_settings_field(
      'excluded_words',
      'Excluded Words',
      array($this, 'excluded_words_callback'),
      'auto-internal-linking-admin',
      'auto_internal_linking_setting_section'
    );
  }

  public function sanitize($input)
  {
    $new_input = array();
    if (isset($input['excluded_words']))
      $new_input['excluded_words'] = sanitize_text_field($input['excluded_words']);

    return $new_input;
  }

  public function section_info()
  {
    print 'Enter your settings below:';
  }

  public function excluded_words_callback()
  {
    printf(
      '<textarea class="large-text" rows="5" name="auto_internal_linking_options[excluded_words]" id="excluded_words">%s</textarea>',
      isset($this->options['excluded_words']) ? esc_attr($this->options['excluded_words']) : ''
    );
    echo '<p class="description">Enter words to be excluded from linking, separated by commas.</p>';
  }

  public function add_meta_box()
  {
    add_meta_box(
      'auto_internal_linking_meta_box',
      'Auto Internal Linking',
      array($this, 'render_meta_box'),
      'post',
      'side',
      'high'
    );
  }

  public function render_meta_box($post)
  {
    $value = get_post_meta($post->ID, '_auto_internal_linking', true);
    wp_nonce_field('auto_internal_linking_meta_box', 'auto_internal_linking_meta_box_nonce');
  ?>
    <label for="auto_internal_linking_field">
      <input type="checkbox" id="auto_internal_linking_field" name="auto_internal_linking_field" value="1" <?php checked($value, '1'); ?> />
      Enable auto internal linking
    </label>
<?php
  }

  public function save_meta_box_data($post_id)
  {
    if (!isset($_POST['auto_internal_linking_meta_box_nonce'])) {
      return;
    }
    if (!wp_verify_nonce($_POST['auto_internal_linking_meta_box_nonce'], 'auto_internal_linking_meta_box')) {
      return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
      return;
    }
    if (!current_user_can('edit_post', $post_id)) {
      return;
    }

    $auto_internal_linking = isset($_POST['auto_internal_linking_field']) ? '1' : '0';
    update_post_meta($post_id, '_auto_internal_linking', $auto_internal_linking);
  }

  public function add_internal_links($content)
  {
    global $post;

    if (!is_singular('post') || get_post_meta($post->ID, '_auto_internal_linking', true) !== '1') {
      return $content;
    }

    $keywords = $this->get_keywords($content);
    $posts = $this->get_related_posts($keywords);

    $excluded_words = isset($this->options['excluded_words']) ? explode(',', $this->options['excluded_words']) : array();
    $excluded_words = array_map('trim', $excluded_words);

    foreach ($posts as $related_post) {
      if (!in_array(strtolower($related_post->post_title), array_map('strtolower', $excluded_words))) {
        $pattern = '/\b(' . preg_quote($related_post->post_title, '/') . ')\b/i';
        $replacement = '<a href="' . get_permalink($related_post->ID) . '">$1</a>';
        $content = preg_replace($pattern, $replacement, $content, 1);
      }
    }

    return $content;
  }

  private function get_keywords($content)
  {
    $stop_words = array('the', 'and', 'in', 'on', 'at', 'to', 'for', 'of', 'with');
    $words = str_word_count(strip_tags($content), 1);
    $words = array_diff($words, $stop_words);
    $keywords = array_slice(array_count_values($words), 0, 5);
    return array_keys($keywords);
  }

  private function get_related_posts($keywords)
  {
    global $post;

    $args = array(
      'post_type' => 'post',
      'post_status' => 'publish',
      'posts_per_page' => -1,
      'post__not_in' => array($post->ID),
      's' => implode(' ', $keywords),
      'orderby' => 'relevance',
      'order' => 'DESC',
    );

    $query = new WP_Query($args);
    $scored_posts = array();

    foreach ($query->posts as $related_post) {
      $score = 0;
      foreach ($keywords as $keyword) {
        if (stripos($related_post->post_title, $keyword) !== false) {
          $score += 2;
        }
        if (stripos($related_post->post_content, $keyword) !== false) {
          $score += 1;
        }
      }
      $scored_posts[$related_post->ID] = $score;
    }

    arsort($scored_posts);
    $top_posts = array_slice($scored_posts, 0, 5, true);

    $final_posts = array();
    foreach ($top_posts as $post_id => $score) {
      $final_posts[] = get_post($post_id);
    }

    return $final_posts;
  }
}

new Auto_Internal_Linking();
