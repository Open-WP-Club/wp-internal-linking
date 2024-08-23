<?php
/*
Plugin Name: Auto Internal Linking
Description: Automates and simplifies internal linking to boost SEO with manual word selection.
Version: 2.0
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
    add_action('admin_menu', array($this, 'add_plugin_pages'));
    add_action('admin_init', array($this, 'page_init'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    add_filter('the_content', array($this, 'add_internal_links'));
    add_action('wp_ajax_save_link_words', array($this, 'save_link_words'));
    add_action('wp_ajax_generate_link_diagram', array($this, 'generate_link_diagram'));

    $this->options = get_option('auto_internal_linking_options');
  }

  public function add_plugin_pages()
  {
    add_menu_page(
      'Auto Internal Linking',
      'Auto Internal Linking',
      'manage_options',
      'auto-internal-linking',
      array($this, 'create_main_page'),
      'dashicons-admin-links'
    );
    add_submenu_page(
      'auto-internal-linking',
      'Word Selection',
      'Word Selection',
      'manage_options',
      'auto-internal-linking-words',
      array($this, 'create_word_selection_page')
    );
    add_submenu_page(
      'auto-internal-linking',
      'Link Diagram',
      'Link Diagram',
      'manage_options',
      'auto-internal-linking-diagram',
      array($this, 'create_link_diagram_page')
    );
  }

  public function create_main_page()
  {
?>
    <div class="wrap">
      <h1>Auto Internal Linking</h1>
      <p>Welcome to Auto Internal Linking. Use the submenus to manage word selection and view the link diagram.</p>
    </div>
  <?php
  }

  public function create_word_selection_page()
  {
    $words = $this->get_all_words();
    $linked_words = isset($this->options['linked_words']) ? $this->options['linked_words'] : array();
  ?>
    <div class="wrap">
      <h1>Word Selection for Auto Internal Linking</h1>
      <table class="wp-list-table widefat fixed striped">
        <thead>
          <tr>
            <th>Word</th>
            <th>Link</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($words as $word): ?>
            <tr>
              <td><?php echo esc_html($word); ?></td>
              <td>
                <input type="checkbox" name="link_word" value="<?php echo esc_attr($word); ?>"
                  <?php checked(in_array($word, $linked_words)); ?>>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <button id="save-link-words" class="button button-primary">Save Changes</button>
    </div>
  <?php
  }

  public function create_link_diagram_page()
  {
  ?>
    <div class="wrap">
      <h1>Internal Link Diagram</h1>
      <div id="link-diagram"></div>
      <button id="generate-diagram" class="button button-primary">Generate Diagram</button>
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
  }

  public function sanitize($input)
  {
    return $input;
  }

  public function enqueue_admin_scripts($hook)
  {
    if ($hook == 'auto-internal-linking_page_auto-internal-linking-words') {
      wp_enqueue_script('auto-internal-linking-admin', plugins_url('js/admin.js', __FILE__), array('jquery'), '1.0', true);
      wp_localize_script('auto-internal-linking-admin', 'ailAjax', array('ajaxurl' => admin_url('admin-ajax.php')));
    }
    if ($hook == 'auto-internal-linking_page_auto-internal-linking-diagram') {
      wp_enqueue_script('mermaid', 'https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js', array(), '8.13.10', true);
      wp_enqueue_script('auto-internal-linking-diagram', plugins_url('js/diagram.js', __FILE__), array('jquery', 'mermaid'), '1.0', true);
      wp_localize_script('auto-internal-linking-diagram', 'ailAjax', array('ajaxurl' => admin_url('admin-ajax.php')));
    }
  }

  public function get_all_words()
  {
    global $wpdb;
    $words = $wpdb->get_col("
            SELECT DISTINCT LOWER(word) word FROM (
                SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(REPLACE(LOWER(post_content), ',' , ' '), '.', ' '), '!', ' '), ' ', n.n), ' ', -1) word
                FROM wp_posts p
                CROSS JOIN (
                    SELECT a.N + b.N * 10 + 1 n
                    FROM (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a
                    CROSS JOIN (SELECT 0 AS N UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b
                    ORDER BY n
                ) n
                WHERE n.n <= 1 + (LENGTH(post_content) - LENGTH(REPLACE(post_content, ' ', '')))
                AND post_type = 'post' AND post_status = 'publish'
            ) words
            WHERE LENGTH(word) > 3
            ORDER BY word
        ");
    return $words;
  }

  public function save_link_words()
  {
    if (!current_user_can('manage_options')) {
      wp_die('Unauthorized user');
    }

    $linked_words = isset($_POST['linked_words']) ? $_POST['linked_words'] : array();
    $this->options['linked_words'] = $linked_words;
    update_option('auto_internal_linking_options', $this->options);

    wp_send_json_success('Words saved successfully');
  }

  public function add_internal_links($content)
  {
    if (!is_singular('post')) {
      return $content;
    }

    $linked_words = isset($this->options['linked_words']) ? $this->options['linked_words'] : array();

    foreach ($linked_words as $word) {
      $pattern = '/\b(' . preg_quote($word, '/') . ')\b/i';
      $replacement = '<a href="' . get_permalink($this->find_post_for_word($word)) . '">$1</a>';
      $content = preg_replace($pattern, $replacement, $content, 1);
    }

    return $content;
  }

  private function find_post_for_word($word)
  {
    $args = array(
      'post_type' => 'post',
      'post_status' => 'publish',
      'posts_per_page' => 1,
      's' => $word,
      'orderby' => 'relevance',
    );

    $query = new WP_Query($args);

    if ($query->have_posts()) {
      return $query->posts[0]->ID;
    }

    return null;
  }

  public function generate_link_diagram()
  {
    if (!current_user_can('manage_options')) {
      wp_die('Unauthorized user');
    }

    $linked_words = isset($this->options['linked_words']) ? $this->options['linked_words'] : array();
    $diagram = "graph TD\n";

    foreach ($linked_words as $word) {
      $post_id = $this->find_post_for_word($word);
      if ($post_id) {
        $post_title = get_the_title($post_id);
        $diagram .= "    " . sanitize_title($word) . "[\"" . esc_html($word) . "\"] --> " . sanitize_title($post_title) . "[\"" . esc_html($post_title) . "\"]\n";
      }
    }

    wp_send_json_success($diagram);
  }
}

new Auto_Internal_Linking();
