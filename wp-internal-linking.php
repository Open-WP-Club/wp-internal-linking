<?php
/*
Plugin Name: Auto Internal Linking
Description: Automates and simplifies internal linking to boost SEO with word usage analysis and link diagram.
Version: 3.1
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
        add_action('wp_ajax_generate_link_diagram', array($this, 'generate_link_diagram'));
        add_action('wp_ajax_analyze_all_posts', array($this, 'analyze_all_posts'));

        $this->options = get_option('auto_internal_linking_options');
    }

    public function add_plugin_pages()
    {
        add_submenu_page(
            'tools.php',
            'Auto Internal Linking',
            'Auto Internal Linking',
            'manage_options',
            'auto-internal-linking',
            array($this, 'create_main_page')
        );
    }

    public function create_main_page()
    {
?>
        <div class="wrap">
            <h1>Auto Internal Linking</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=auto-internal-linking" class="nav-tab <?php echo ($_GET['page'] === 'auto-internal-linking' && !isset($_GET['tab'])) ? 'nav-tab-active' : ''; ?>">Overview</a>
                <a href="?page=auto-internal-linking&tab=word-usage" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'word-usage') ? 'nav-tab-active' : ''; ?>">Word Usage</a>
                <a href="?page=auto-internal-linking&tab=link-diagram" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'link-diagram') ? 'nav-tab-active' : ''; ?>">Link Diagram</a>
            </h2>
            <?php
            $tab = isset($_GET['tab']) ? $_GET['tab'] : '';
            switch ($tab) {
                case 'word-usage':
                    $this->create_word_usage_page();
                    break;
                case 'link-diagram':
                    $this->create_link_diagram_page();
                    break;
                default:
                    $this->create_overview_page();
                    break;
            }
            ?>
        </div>
    <?php
    }

    public function create_overview_page()
    {
    ?>
        <p>Welcome to Auto Internal Linking. Use the tabs above to view word usage across your posts and the internal link diagram.</p>
    <?php
    }

    public function create_word_usage_page()
    {
    ?>
        <h3>Word Usage Analysis</h3>
        <button id="analyze-all-posts" class="button button-primary">Analyze All Posts</button>
        <div id="analysis-results">
            <p>Click the button above to start the analysis.</p>
        </div>
    <?php
    }

    public function create_link_diagram_page()
    {
    ?>
        <h3>Internal Link Diagram</h3>
        <div id="link-diagram"></div>
        <button id="generate-diagram" class="button button-primary">Generate Diagram</button>
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
        if ($hook == 'tools_page_auto-internal-linking') {
            wp_enqueue_script('mermaid', 'https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js', array(), '8.13.10', true);
            wp_enqueue_script('auto-internal-linking-admin', plugins_url('js/admin.js', __FILE__), array('jquery'), '1.0', true);
            wp_enqueue_script('auto-internal-linking-diagram', plugins_url('js/diagram.js', __FILE__), array('jquery', 'mermaid'), '1.0', true);
            wp_localize_script('auto-internal-linking-admin', 'ailAjax', array('ajaxurl' => admin_url('admin-ajax.php')));
        }
    }

    public function get_all_words()
    {
        global $wpdb;
        $words = $wpdb->get_col("
            SELECT DISTINCT LOWER(word) word FROM (
                SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(REPLACE(REPLACE(REPLACE(LOWER(post_content), ',' , ' '), '.', ' '), '!', ' '), ' ', n.n), ' ', -1) word
                FROM {$wpdb->posts} p
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

    private function find_pages_for_word($word)
    {
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            's' => $word,
        );

        $query = new WP_Query($args);
        return $query->posts;
    }

    public function add_internal_links($content)
    {
        if (!is_singular('post')) {
            return $content;
        }

        $words = $this->get_all_words();

        foreach ($words as $word) {
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

        $words = $this->get_all_words();
        $diagram = "graph LR\n";

        $word_nodes = array();
        $post_nodes = array();

        foreach ($words as $word) {
            $post_id = $this->find_post_for_word($word);
            if ($post_id) {
                $post_title = get_the_title($post_id);
                $word_node = 'word_' . md5($word);
                $post_node = 'post_' . $post_id;

                if (!in_array($word_node, $word_nodes)) {
                    $diagram .= "    {$word_node}[\"" . esc_html($word) . "\"]\n";
                    $word_nodes[] = $word_node;
                }

                if (!in_array($post_node, $post_nodes)) {
                    $diagram .= "    {$post_node}[\"" . esc_html($post_title) . "\"]\n";
                    $post_nodes[] = $post_node;
                }

                $diagram .= "    {$word_node} --> {$post_node}\n";
            }
        }

        wp_send_json_success($diagram);
    }

    public function analyze_all_posts()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        $words = $this->get_all_words();
        $word_usage = array();

        foreach ($words as $word) {
            $pages = $this->find_pages_for_word($word);
            if (!empty($pages)) {
                $word_usage[$word] = array_map(function ($page) {
                    return array(
                        'id' => $page->ID,
                        'title' => $page->post_title,
                        'url' => get_permalink($page->ID)
                    );
                }, $pages);
            }
        }

        wp_send_json_success($word_usage);
    }
}

// Instantiate the class
function run_auto_internal_linking()
{
    $plugin = new Auto_Internal_Linking();
}

// Hook to WordPress init action
add_action('init', 'run_auto_internal_linking');
