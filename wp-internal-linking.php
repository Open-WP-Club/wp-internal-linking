<?php
/*
 * Plugin Name:             WordPress Internal Linking
 * Plugin URI:              https://github.com/Open-WP-Club/wp-internal-linking
 * Description:             A plugin to manage and analyze internal linking in WordPress
 * Version:                 0.0.3
 * Author:                  Gabriel Kanev
 * Author URI:              https://gkanev.com
 * License:                 GPL-2.0 License
 * Requires Plugins:        
 * Requires at least:       6.0
 * Requires PHP:            7.4
 * Tested up to:            6.6.1
 */

// Don't allow direct access to the plugin file
if (!defined('ABSPATH')) {
    exit;
}

class Internal_Linking
{
    private $options;
    private $blacklist;
    private $blacklist_per_page = 20;

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_pages'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('the_content', array($this, 'add_internal_links'));
        add_action('wp_ajax_generate_content_relationship_map', array($this, 'generate_content_relationship_map'));
        add_action('wp_ajax_delete_content_relationship_map', array($this, 'delete_content_relationship_map'));
        add_action('wp_ajax_analyze_all_content', array($this, 'analyze_all_content'));
        add_action('wp_ajax_analyze_internal_links', array($this, 'analyze_internal_links'));
        add_action('wp_ajax_delete_internal_link_analysis', array($this, 'delete_internal_link_analysis'));

        $this->options = get_option('internal_linking_options');
        $this->blacklist = $this->get_blacklist();
        $this->add_ajax_handlers();
    }

    public function add_plugin_pages()
    {
        add_submenu_page(
            'tools.php',
            'Internal Linking',
            'Internal Linking',
            'manage_options',
            'internal-linking',
            array($this, 'create_main_page')
        );
    }

    public function create_main_page()
    {
?>
        <div class="wrap">
            <h1>Internal Linking</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=internal-linking" class="nav-tab <?php echo ($_GET['page'] === 'internal-linking' && !isset($_GET['tab'])) ? 'nav-tab-active' : ''; ?>">Overview</a>
                <a href="?page=internal-linking&tab=word-usage" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'word-usage') ? 'nav-tab-active' : ''; ?>">Word Usage</a>
                <a href="?page=internal-linking&tab=content-relationship-map" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'content-relationship-map') ? 'nav-tab-active' : ''; ?>">Content Relationship Map</a>
                <a href="?page=internal-linking&tab=internal-link-analysis" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'internal-link-analysis') ? 'nav-tab-active' : ''; ?>">Internal Link Analysis</a>
            </h2>
            <div class="internal-linking-content">
                <?php
                $tab = isset($_GET['tab']) ? $_GET['tab'] : '';
                switch ($tab) {
                    case 'word-usage':
                        $this->create_word_usage_page();
                        break;
                    case 'content-relationship-map':
                        $this->create_content_relationship_map_page();
                        break;
                    case 'internal-link-analysis':
                        $this->create_internal_link_analysis_page();
                        break;
                    default:
                        $this->create_overview_page();
                        break;
                }
                ?>
            </div>
        </div>
    <?php
    }

    public function create_overview_page()
    {
        // Get the stored analysis data
        $stored_analysis = get_option('internal_linking_analysis', array());

        // Calculate statistics
        $total_words = count($stored_analysis);
        $total_content_items = 0;
        $content_types = array();

        foreach ($stored_analysis as $word => $content_items) {
            $total_content_items += count($content_items);
            foreach ($content_items as $item) {
                if (!isset($content_types[$item['type']])) {
                    $content_types[$item['type']] = 0;
                }
                $content_types[$item['type']]++;
            }
        }

        // Get blacklist statistics
        $blacklist_count = count($this->blacklist);
    ?>
        <div class="internal-linking-section">
            <h3>Internal Linking Overview</h3>
            <p>Welcome to Internal Linking. Here are some statistics about your content:</p>
            <table class="internal-linking-table">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Total analyzed words</strong></td>
                        <td><?php echo $total_words; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total content items linked</strong></td>
                        <td><?php echo $total_content_items; ?></td>
                    </tr>
                    <?php foreach ($content_types as $type => $count): ?>
                        <tr>
                            <td><strong><?php echo ucfirst($type) . 's linked'; ?></strong></td>
                            <td><?php echo $count; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td><strong>Blacklisted words</strong></td>
                        <td><?php echo $blacklist_count; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="internal-linking-section">
            <h3>Manage Blacklist</h3>
            <div id="blacklist-manager">
                <button id="toggle-blacklist" class="button">Show/Hide Blacklist</button>
                <div id="blacklist-content" style="display: none;">
                    <input type="text" id="search-blacklist" placeholder="Search blacklist">
                    <div id="blacklist-words">
                        <?php $this->display_blacklist(); ?>
                    </div>
                    <div id="blacklist-pagination">
                        <?php $this->display_blacklist_pagination(); ?>
                    </div>
                </div>
                <div id="add-to-blacklist">
                    <input type="text" id="new-blacklist-word" placeholder="Add new word">
                    <button id="add-blacklist-word" class="button">Add to Blacklist</button>
                </div>
            </div>
        </div>
    <?php
    }

    public function create_word_usage_page()
    {
    ?>
        <div class="internal-linking-section">
            <h3>Word Usage Analysis</h3>
            <p>Analyze your content to see how words are used across your site.</p>
            <button id="analyze-all-content" class="button button-primary">Analyze All Content</button>
            <div id="analysis-results" style="margin-top: 20px;">
                <?php
                $stored_analysis = get_option('internal_linking_analysis');
                if ($stored_analysis) {
                    echo $this->generate_analysis_table($stored_analysis);
                } else {
                    echo '<p>No analysis data available. Click the button above to start the analysis.</p>';
                }
                ?>
            </div>
        </div>
    <?php
    }

    public function create_content_relationship_map_page()
    {
        $saved_diagram = get_option('internal_linking_diagram');
    ?>
        <div class="internal-linking-section">
            <h3>Content Relationship Map</h3>
            <p>Generate a visual representation of relationships between your content based on common keywords.</p>
            <div id="content-relationship-map" data-saved-diagram="<?php echo esc_attr($saved_diagram ? 'true' : 'false'); ?>"></div>
            <div class="button-group">
                <button id="generate-diagram" class="button button-primary">Generate Map</button>
                <button id="delete-diagram" class="button" <?php echo $saved_diagram ? '' : 'style="display:none;"'; ?>>Delete Map</button>
                <button id="download-diagram" class="button" <?php echo $saved_diagram ? '' : 'style="display:none;"'; ?>>Download as PNG</button>
                <button id="download-svg" class="button" <?php echo $saved_diagram ? '' : 'style="display:none;"'; ?>>Download as SVG</button>
            </div>
        </div>
    <?php
    }

    public function create_internal_link_analysis_page()
    {
    ?>
        <div class="internal-linking-section">
            <h3>Internal Link Analysis</h3>
            <p>Analyze existing internal links in your posts, pages, and other content types.</p>
            <div id="internal-link-analysis-results" data-saved-analysis="<?php echo esc_attr($saved_analysis ? 'true' : 'false'); ?>"></div>
            <div class="button-group">
                <button id="analyze-internal-links" class="button button-primary"><?php echo $saved_analysis ? 'Update Analysis' : 'Analyze Internal Links'; ?></button>
                <button id="delete-internal-link-analysis" class="button" <?php echo $saved_analysis ? '' : 'style="display:none;"'; ?>>Delete Analysis</button>
                <button id="download-internal-link-analysis" class="button" <?php echo $saved_analysis ? '' : 'style="display:none;"'; ?>>Download as PNG</button>
                <button id="download-internal-link-analysis-svg" class="button" <?php echo $saved_analysis ? '' : 'style="display:none;"'; ?>>Download as SVG</button>
            </div>
        </div>
<?php
    }

    public function page_init()
    {
        register_setting(
            'internal_linking_option_group',
            'internal_linking_options',
            array($this, 'sanitize')
        );
    }

    public function sanitize($input)
    {
        return $input;
    }

    public function enqueue_admin_scripts($hook)
    {
        if ($hook == 'tools_page_internal-linking') {
            wp_enqueue_style('internal-linking-admin-styles', plugins_url('css/style.css', __FILE__));
            wp_enqueue_script('mermaid', 'https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js', array(), '8.13.10', true);
            wp_enqueue_script('file-saver', 'https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.0/FileSaver.min.js', array(), '2.0.0', true);
            wp_enqueue_script('internal-linking-admin', plugins_url('js/admin.js', __FILE__), array('jquery'), '1.0', true);
            wp_enqueue_script('internal-linking-diagram', plugins_url('js/diagram.js', __FILE__), array('jquery', 'mermaid', 'file-saver'), '1.0', true);
            wp_localize_script('internal-linking-admin', 'ailAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'savedDiagram' => get_option('internal_linking_diagram', ''),
                'savedInternalLinkAnalysis' => get_option('internal_linking_analysis_diagram', '')
            ));
        }
    }

    private function get_blacklist()
    {
        $blacklist_file = plugin_dir_path(__FILE__) . 'blacklist.php';
        if (file_exists($blacklist_file)) {
            return include $blacklist_file;
        }
        return array(); // Return an empty array if the file doesn't exist
    }

    private function display_blacklist()
    {
        $page = isset($_GET['blacklist_page']) ? intval($_GET['blacklist_page']) : 1;
        $search = isset($_GET['blacklist_search']) ? sanitize_text_field($_GET['blacklist_search']) : '';

        $filtered_blacklist = array_filter($this->blacklist, function ($word) use ($search) {
            return empty($search) || stripos($word, $search) !== false;
        });

        $total_items = count($filtered_blacklist);
        $total_pages = ceil($total_items / $this->blacklist_per_page);
        $page = max(1, min($page, $total_pages));

        $start = ($page - 1) * $this->blacklist_per_page;
        $displayed_items = array_slice($filtered_blacklist, $start, $this->blacklist_per_page);

        echo '<ul class="blacklist-words">';
        foreach ($displayed_items as $word) {
            echo '<li>';
            echo esc_html($word);
            echo ' <button class="remove-blacklist-word button-link" data-word="' . esc_attr($word) . '">Remove</button>';
            echo '</li>';
        }
        echo '</ul>';
    }

    private function display_blacklist_pagination()
    {
        $page = isset($_GET['blacklist_page']) ? intval($_GET['blacklist_page']) : 1;
        $search = isset($_GET['blacklist_search']) ? sanitize_text_field($_GET['blacklist_search']) : '';

        $filtered_blacklist = array_filter($this->blacklist, function ($word) use ($search) {
            return empty($search) || stripos($word, $search) !== false;
        });

        $total_items = count($filtered_blacklist);
        $total_pages = ceil($total_items / $this->blacklist_per_page);

        if ($total_pages > 1) {
            echo '<div class="tablenav-pages">';
            echo paginate_links(array(
                'base' => add_query_arg('blacklist_page', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $total_pages,
                'current' => $page,
                'add_args' => array('blacklist_search' => $search)
            ));
            echo '</div>';
        }
    }

    public function get_all_words()
    {
        global $wpdb;
        $blacklist_pattern = implode('|', array_map('preg_quote', $this->blacklist));

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
                AND (post_type = 'post' OR post_type = 'page') AND post_status = 'publish'
            ) words
            WHERE LENGTH(word) > 3
            AND word NOT REGEXP '{$blacklist_pattern}'
            AND word REGEXP '^[a-zA-Z]+$'
            ORDER BY word
        ");

        return $words;
    }

    private function find_content_for_word($word)
    {
        $args = array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'posts_per_page' => -1,
            's' => $word,
        );

        $query = new WP_Query($args);
        $content = array();

        foreach ($query->posts as $post) {
            $content[] = array(
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'type' => $post->post_type
            );
        }

        // Add categories
        $categories = get_categories(array('hide_empty' => false, 'name__like' => $word));
        foreach ($categories as $category) {
            $content[] = array(
                'id' => $category->term_id,
                'title' => $category->name,
                'url' => get_category_link($category->term_id),
                'type' => 'category'
            );
        }

        // Add tags
        $tags = get_tags(array('hide_empty' => false, 'name__like' => $word));
        foreach ($tags as $tag) {
            $content[] = array(
                'id' => $tag->term_id,
                'title' => $tag->name,
                'url' => get_tag_link($tag->term_id),
                'type' => 'tag'
            );
        }

        return $content;
    }

    public function analyze_all_content()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        $words = $this->get_all_words();
        $word_usage = array();

        foreach ($words as $word) {
            $content = $this->find_content_for_word($word);
            if (!empty($content)) {
                $word_usage[$word] = $content;
            }
        }

        update_option('internal_linking_analysis', $word_usage);

        $table_html = $this->generate_analysis_table($word_usage);
        wp_send_json_success($table_html);
    }

    private function generate_analysis_table($word_usage)
    {
        $html = '<table class="internal-linking-table">';
        $html .= '<thead><tr><th>Word</th><th>Content</th></tr></thead><tbody>';

        foreach ($word_usage as $word => $content_items) {
            $html .= '<tr><td>' . esc_html($word) . '</td><td>';
            foreach ($content_items as $item) {
                $html .= '<a href="' . esc_url($item['url']) . '">' . esc_html($item['title']) . '</a> (' . esc_html($item['type']) . ')<br>';
            }
            $html .= '</td></tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    public function add_internal_links($content)
    {
        if (!is_singular(array('post', 'page'))) {
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
            'post_type' => array('post', 'page'),
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

    public function generate_content_relationship_map()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        $words = $this->get_all_words();
        $diagram = "graph TD\n";

        $word_nodes = array();
        $content_nodes = array();
        $connections = array();

        foreach ($words as $word) {
            $content_items = $this->find_content_for_word($word);
            if (!empty($content_items)) {
                $word_node = 'word_' . md5($word);
                if (!in_array($word_node, $word_nodes)) {
                    $escaped_word = $this->escape_mermaid_label($word);
                    if (!empty($escaped_word)) {
                        $diagram .= "    {$word_node}[\"{$escaped_word}\"]\n";
                        $word_nodes[] = $word_node;
                    }
                }

                foreach ($content_items as $item) {
                    $content_node = 'content_' . $item['type'] . '_' . $item['id'];
                    if (!in_array($content_node, $content_nodes)) {
                        $escaped_title = $this->escape_mermaid_label($item['title']);
                        if (!empty($escaped_title)) {
                            $diagram .= "    {$content_node}[\"{$escaped_title}\"]\n";
                            $content_nodes[] = $content_node;
                            $connections[] = "    {$content_node} --- {$word_node}\n";
                        }
                    }
                }
            }
        }

        $diagram .= implode("", array_unique($connections));

        // Save the generated diagram
        update_option('internal_linking_diagram', $diagram);

        wp_send_json_success($diagram);
    }

    public function delete_content_relationship_map()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        delete_option('internal_linking_diagram');
        wp_send_json_success();
    }

    public function analyze_internal_links()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        $args = array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'posts_per_page' => -1,
        );

        $query = new WP_Query($args);
        $link_data = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $post_title = get_the_title();
                $post_content = get_the_content();

                preg_match_all('/<a\s[^>]*href=([\'"])(.*?)\1[^>]*>(.*?)<\/a>/i', $post_content, $matches);

                foreach ($matches[2] as $index => $url) {
                    if (strpos($url, get_site_url()) !== false) {
                        $link_text = strip_tags($matches[3][$index]);
                        $target_post_id = url_to_postid($url);
                        if ($target_post_id) {
                            $link_data[] = array(
                                'source' => $post_id,
                                'source_title' => $post_title,
                                'target' => $target_post_id,
                                'target_title' => get_the_title($target_post_id),
                                'link_text' => $link_text
                            );
                        }
                    }
                }
            }
        }

        wp_reset_postdata();

        $diagram = $this->generate_internal_link_diagram($link_data);
        update_option('internal_linking_analysis_diagram', $diagram);
        wp_send_json_success($diagram);
    }

    public function delete_internal_link_analysis()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        delete_option('internal_linking_analysis_diagram');
        wp_send_json_success();
    }

    private function generate_internal_link_diagram($link_data)
    {
        $diagram = "graph TD\n";
        $nodes = array();
        $connections = array();

        foreach ($link_data as $link) {
            $source_node = 'post_' . $link['source'];
            $target_node = 'post_' . $link['target'];

            if (!in_array($source_node, $nodes)) {
                $escaped_source_title = $this->escape_mermaid_label($link['source_title']);
                $diagram .= "    {$source_node}[\"{$escaped_source_title}\"]\n";
                $nodes[] = $source_node;
            }

            if (!in_array($target_node, $nodes)) {
                $escaped_target_title = $this->escape_mermaid_label($link['target_title']);
                $diagram .= "    {$target_node}[\"{$escaped_target_title}\"]\n";
                $nodes[] = $target_node;
            }

            $escaped_link_text = $this->escape_mermaid_label($link['link_text']);
            $connections[] = "    {$source_node} -->|{$escaped_link_text}| {$target_node}\n";
        }

        $diagram .= implode("", array_unique($connections));

        return $diagram;
    }

    private function escape_mermaid_label($text)
    {
        // Remove HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Remove any characters that might interfere with Mermaid syntax
        $text = preg_replace('/[\\\\\'\"]/u', '', $text);
        // Limit length to prevent overly long labels
        $text = mb_substr($text, 0, 30);
        if (mb_strlen($text) >= 30) {
            $text .= '...';
        }
        // Ensure the label is not empty
        return !empty($text) ? $text : 'Unnamed';
    }

    public function add_ajax_handlers()
    {
        add_action('wp_ajax_add_blacklist_word', array($this, 'ajax_add_blacklist_word'));
        add_action('wp_ajax_remove_blacklist_word', array($this, 'ajax_remove_blacklist_word'));
        add_action('wp_ajax_search_blacklist', array($this, 'ajax_search_blacklist'));
    }

    public function ajax_add_blacklist_word()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        $word = sanitize_text_field($_POST['word']);
        if (!in_array($word, $this->blacklist)) {
            $this->blacklist[] = $word;
            $this->update_blacklist();
            wp_send_json_success();
        } else {
            wp_send_json_error('Word already in blacklist');
        }
    }

    public function ajax_remove_blacklist_word()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        $word = sanitize_text_field($_POST['word']);
        $key = array_search($word, $this->blacklist);
        if ($key !== false) {
            unset($this->blacklist[$key]);
            $this->update_blacklist();
            wp_send_json_success();
        } else {
            wp_send_json_error('Word not found in blacklist');
        }
    }

    public function ajax_search_blacklist()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;

        $filtered_blacklist = array_filter($this->blacklist, function ($word) use ($search) {
            return empty($search) || stripos($word, $search) !== false;
        });

        $total_items = count($filtered_blacklist);
        $total_pages = ceil($total_items / $this->blacklist_per_page);
        $page = max(1, min($page, $total_pages));

        $start = ($page - 1) * $this->blacklist_per_page;
        $displayed_items = array_slice($filtered_blacklist, $start, $this->blacklist_per_page);

        ob_start();
        echo '<ul class="blacklist-words">';
        foreach ($displayed_items as $word) {
            echo '<li>';
            echo esc_html($word);
            echo ' <button class="remove-blacklist-word button-link" data-word="' . esc_attr($word) . '">Remove</button>';
            echo '</li>';
        }
        echo '</ul>';
        $blacklist_html = ob_get_clean();

        ob_start();
        $this->display_blacklist_pagination();
        $pagination_html = ob_get_clean();

        wp_send_json_success(array(
            'blacklist_html' => $blacklist_html,
            'pagination_html' => $pagination_html
        ));
    }

    private function update_blacklist()
    {
        $blacklist_file = plugin_dir_path(__FILE__) . 'blacklist.php';
        $blacklist_content = "<?php\nreturn " . var_export(array_values($this->blacklist), true) . ";\n";
        file_put_contents($blacklist_file, $blacklist_content);
    }
}

// Instantiate the class
function run_internal_linking()
{
    $plugin = new Internal_Linking();
}

// Hook to WordPress init action
add_action('init', 'run_internal_linking');
