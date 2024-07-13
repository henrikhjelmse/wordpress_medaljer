<?php
/*
Plugin Name: Medaljer Plugin
Description: Ett plugin för att hantera och tilldela medaljer till användare.
Version: 1.0
Author: Ditt Namn
*/

// Aktiveringskrok för att skapa databastabellen
register_activation_hook(__FILE__, 'medaljer_plugin_install');

function medaljer_plugin_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'medaljer';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_name varchar(60) NOT NULL,
        medal varchar(20) NOT NULL,
        points smallint NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Lägg till en meny i adminpanelen
add_action('admin_menu', 'medaljer_plugin_menu');

function medaljer_plugin_menu() {
    add_menu_page('Medaljer Plugin', 'Medaljer', 'manage_options', 'medaljer-plugin', 'medaljer_plugin_page');
}

// Hantera pluginets huvudsida
function medaljer_plugin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'medaljer';

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] == 'add_medal' && check_admin_referer('medaljer_add_medal_action', 'medaljer_add_medal_nonce')) {
            $user_name = sanitize_text_field($_POST['user_name']);
            $medal = sanitize_text_field($_POST['medal']);
            $points = 0;

            switch ($medal) {
                case 'gold':
                    $points = 3;
                    break;
                case 'silver':
                    $points = 2;
                    break;
                case 'bronze':
                    $points = 1;
                    break;
                case 'participant':
                    $points = 2;
                    break;
            }

            $wpdb->insert($table_name, [
                'user_name' => $user_name,
                'medal' => $medal,
                'points' => $points
            ]);
        } elseif ($_POST['action'] == 'reset_medals' && check_admin_referer('medaljer_reset_medals_action', 'medaljer_reset_medals_nonce')) {
            $wpdb->query("TRUNCATE TABLE $table_name");
        }
    }

    $results = $wpdb->get_results($wpdb->prepare("SELECT user_name, SUM(points) as total_points FROM $table_name GROUP BY user_name ORDER BY total_points DESC"));

    echo '<h1>Medaljer</h1>';
    echo '<form method="POST">
        ' . wp_nonce_field('medaljer_add_medal_action', 'medaljer_add_medal_nonce', true, false) . '
        <label for="user_name">Användarnamn:</label>
        <input type="text" name="user_name" required>
        <label for="medal">Medalj:</label>
        <select name="medal" required>
            <option value="gold">🎖️Gold 3p</option>
            <option value="silver">🥈Silver 2p</option>
            <option value="bronze">🥉Bronze 1p</option>
            <option value="participant">🗽Participant 2p</option>
        </select>
        <input type="hidden" name="action" value="add_medal">
        <button type="submit">Ge ut medalj</button>
    </form>';

    echo '<form method="POST" style="margin-top: 20px;">
        ' . wp_nonce_field('medaljer_reset_medals_action', 'medaljer_reset_medals_nonce', true, false) . '
        <input type="hidden" name="action" value="reset_medals">
<div popover id="mydiv">
<input type="submit" value="Reset medaljer" onclick="return confirm(\'Är du säker på att du vill återställa alla medaljer?\')">
</div>


    </form>
<button popovertarget="mydiv">ta bort</button>
    ';

    if (!empty($results)) {
        echo do_shortcode('[lista_medaljer]');
    } else {
        echo '<p>Inga poäng ännu.</p>';
    }
}

// Shortcode för att lista alla användare och deras medaljer
add_shortcode('lista_medaljer', 'lista_medaljer_shortcode');

function lista_medaljer_shortcode() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'medaljer';

    $results = $wpdb->get_results($wpdb->prepare("SELECT user_name, medal, GROUP_CONCAT(medal ORDER BY medal SEPARATOR ' ') as medals, SUM(points) as total_points FROM $table_name GROUP BY user_name ORDER BY total_points DESC"));

    if (!empty($results)) {
        $output = '<table>
            <tr>
                <th>Användarnamn</th>
                <th>Medaljer</th>
                <th>Totala poäng</th>
            </tr>';
        foreach ($results as $row) {
            $output .= '<tr>
                <td>' . esc_html($row->user_name) . '</td>
                <td>' . esc_html($row->medals) . '</td>
                <td>' . esc_html($row->total_points) . '</td>
            </tr>';
        }
        $output .= '</table>';
    } else {
        $output = '<p>Inga medaljer ännu.</p>';
    }

    return $output;
}
?>
