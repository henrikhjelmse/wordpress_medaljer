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
    $table_name = $wpdb->prefix . 'medaljer'; // Prefix för att undvika kollisioner med andra tabeller i WordPress databasen
    $charset_collate = $wpdb->get_charset_collate();

    // SQL för att skapa tabellen med medaljer
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_name varchar(60) NOT NULL,
        medal varchar(20) NOT NULL,
        points smallint NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql); // Funktion för att skapa eller uppdatera databastabeller i WordPress
}

// Lägg till en meny i adminpanelen
add_action('admin_menu', 'medaljer_plugin_menu');

function medaljer_plugin_menu() {
    // Skapa en meny i adminpanelen för pluginet
    add_menu_page('Medaljer Plugin', 'Medaljer', 'manage_options', 'medaljer-plugin', 'medaljer_plugin_page');
}

// Hantera pluginets huvudsida
function medaljer_plugin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'medaljer';

    // Hantera POST-data för att lägga till medaljer eller återställa
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] == 'add_medal' && check_admin_referer('medaljer_add_medal_action', 'medaljer_add_medal_nonce')) {
            // Hantera tillägg av medaljer till användare
            $user_name = sanitize_text_field($_POST['user_name']);
            $medal = sanitize_text_field($_POST['medal']);
            $points = 0;

            // Tilldela poäng baserat på vilken typ av medalj som valts
            switch ($medal) {
                case 'gold':
                    $points = 3;
                    break;
                case 'silver':
                    $points = 2;
                    break;
                case 'bronze':
                case 'participant':
                    $points = 1;
                    break;
            }

            // Lägg till en rad i databastabellen för den valda medaljen
            $wpdb->insert($table_name, [
                'user_name' => $user_name,
                'medal' => $medal,
                'points' => $points
            ]);
        } elseif ($_POST['action'] == 'reset_medals' && check_admin_referer('medaljer_reset_medals_action', 'medaljer_reset_medals_nonce')) {
            // Återställ alla medaljer genom att tömma tabellen
            $wpdb->query("TRUNCATE TABLE $table_name");
        }
    }

    // Hämta resultat för att visa alla användare och deras totala poäng
    $results = $wpdb->get_results($wpdb->prepare("SELECT user_name, SUM(points) as total_points FROM $table_name GROUP BY user_name ORDER BY total_points DESC"));

    // Visa formulär för att lägga till medaljer och återställa medaljer
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
            <option value="participant">🗽Participant 1p</option>
        </select>
        <input type="hidden" name="action" value="add_medal">
        <button type="submit">Ge ut medalj</button>
    </form>';

    // Formulär för att återställa alla medaljer
    echo '<form method="POST" style="margin-top: 20px;">
        ' . wp_nonce_field('medaljer_reset_medals_action', 'medaljer_reset_medals_nonce', true, false) . '
        <input type="hidden" name="action" value="reset_medals">
        <input type="submit" value="Reset medaljer" onclick="return confirm(\'Är du säker på att du vill återställa alla medaljer?\')">
    </form>';

    // Visa en lista över alla användare och deras medaljer (genom att använda en shortcode)
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

    // Hämta resultat för alla användare och deras totala poäng och medaljer
    $results = $wpdb->get_results($wpdb->prepare("SELECT user_name, medal, GROUP_CONCAT(medal ORDER BY medal SEPARATOR ' ') as medals, SUM(points) as total_points FROM $table_name GROUP BY user_name ORDER BY total_points DESC"));
    
    /*
      SQL-förfrågan ovan väljer data från databastabellen $table_name (som är prefixad med WordPress prefix). Den använder följande funktioner och tekniker:
      
      - SELECT user_name, medal: Väljer användarnamn och typen av medalj för varje post i tabellen.
      - GROUP_CONCAT(medal ORDER BY medal SEPARATOR ' ') as medals: Används för att sammanfoga alla medaljer som tilldelats varje användare till en enda sträng. Medaljer är ordnade i stigande ordning (gold, silver, bronze osv.) och separeras med mellanslag.
      - SUM(points) as total_points: Beräknar totala poängen för varje användare genom att summera poängen för alla deras medaljer.
      - GROUP BY user_name: Grupperar resultatet efter användarnamn så att varje användare visas endast en gång.
      - ORDER BY total_points DESC: Sorterar resultaten i fallande ordning baserat på totala poängen för att visa de mest framgångsrika användarna först.
    
      Detta resulterar i en lista över användare med deras sammanfogade medaljer och totala poäng, vilket sedan visas på pluginets sida för att visa användare och deras prestationer.
    */
    // Skapa HTML-tabell för att visa användare och deras medaljer
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
