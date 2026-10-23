<?php

/*
  Plugin Name: LIKE-Json
  Plugin URI: https://github.com/Felix-LIKE/like-json-updated
  Version: 1.6.0
  Description: Dient der Ausgabe von studentischen Arbeiten aus der LIKE Datenbank.
  Author: LIKE
  Author URI: 
  License: GPLv2 or later
  Text Domain: like-json
 */

/*
  This program is free software; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  as published by the Free Software Foundation; either version 2
  of the License, or (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

/*
  Verzeichnisschema:

  cms-basis
  |-- includes
      |-- cmb                   Framework für Metaboxen (Auf Basis von https://github.com/humanmade/Custom-Meta-Boxes)
          ..
      +-- meta-boxes.php        Definition der im Plugin einzubindenden Metaboxen
  |-- languages                 
  |   +-- cms-basis.pot         Vorlagedatei falls Übersetzungen in andere Sprachen nötig werden
  |   +-- cms-basis-en_US.po    Englische Übersetzungsdatei (kann mit poedit angepasst werden)
  |   +-- cms-basis-en_US.mo    Englische Übersetzungsdatei (wird beim Speichern in poedit aktualisiert)
  +-- README.md
  +-- cms-basis.php
 */

add_action('plugins_loaded', array('like_json', 'instance'));

register_activation_hook(__FILE__, array('LIKE_Json', 'activation'));
register_deactivation_hook(__FILE__, array('LIKE_Json', 'deactivation'));


class LIKE_Json {

    const option_name = 'like_json'; 
    const version_option_name = '_like_json_version';
    const textdomain = 'like-json';
    const version = '1.6.0';
    const php_version = '8.0';
    const wp_version = '6.0';
    protected static $options;
    protected $admin_settings_page;
    protected static $instance = null; 
	
	private $source = 'http://like35.like.e-technik.uni-erlangen.de/json/'; 

    /*
     * Erstellt und gibt eine Instanz der Klasse zurück.
     * Es stellt sicher, dass von der Klasse genau ein Objekt existiert (Singleton Pattern).
     * @return object
     */
    public static function instance() {

        if (null == self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /*
     * Initialisiert das Plugin, indem die Lokalisierung, Hooks und Verwaltungsfunktionen festgesetzt werden.
     * @return void
     */
    private function __construct() {
        // Sprachdateien werden eingebunden.
        load_plugin_textdomain(self::textdomain, false, sprintf('%s/languages/', dirname(plugin_basename(__FILE__))));

        // Enthaltene Optionen.
        self::$options = self::get_options();
        
        // Aktualisierung des Plugins (ggf).
        self::update_version();
        
        /* -- START META-BOXES (Optional) -- */
        // Das CMB-Framework wird eingebunden und initialisiert.
        // (Auf Basis von https://github.com/WebDevStudios/Custom-Metaboxes-and-Fields-for-WordPress)
        //include_once(plugin_dir_path(__FILE__) . 'includes/cmb-meta-boxes.php');
        /* -- ENDE META-BOXES -- */

        /* -- START Optionsseite (Optional) -- */
        add_action('admin_menu', array($this, 'admin_settings_page'));
        add_action('admin_init', array($this, 'admin_settings'));
        /* -- ENDE Optionsseite -- */
        
        // Ab hier können weitere Hooks angelegt werden.
        add_shortcode('like', array($this, 'shortcode'));
		
	    include_once(plugin_dir_path(__FILE__) . 'templates/studentische-arbeiten-templates.php');
		
		include_once(plugin_dir_path(__FILE__) . 'includes/classes.php');

    }
    /*
	 *	Wird durch Eingabe eines Shortcuts aufgerufen.
	 *	Die einzelnen Dokumente liegen in separaten Dateien.  
	 *  Je nach Task wird die entsprechende URL zusammengebaut.
	 */
    public function get_content($task = '', $id='', $format='', $advisor='', $status='', $ressources='') { 
        $json_urls = array();
        switch ($task) {
            case 'masterarbeiten':
				$json_url = $this->source . "masterarbeiten"; 
				$task_object = new Studentische_Arbeiten($json_url, $id, $task, $format, $advisor, $status); 
				break;
            case 'bachelorarbeiten':
				$json_url = $this->source . "bachelorarbeiten"; 
                $task_object = new Studentische_Arbeiten($json_url, $id, $task, $format, $advisor, $status);
                break;
            case 'forschungspraktika':
				$json_url = $this->source . "forschungspraktika"; 
                $task_object = new Studentische_Arbeiten($json_url, $id, $task, $format, $advisor, $status);
                break;	
            case 'projektarbeiten':
				$json_url = $this->source . "projektarbeiten"; 
                $task_object = new Studentische_Arbeiten($json_url, $id, $task, $format, $advisor, $status);
                break;	
            case 'sonstiges':
				$json_url = $this->source . "sonstiges"; 
                $task_object = new Studentische_Arbeiten($json_url, $id, $task, $format, $advisor, $status);
                break;						
            case 'arbeiten-alle':
				$json_urls['bachelorarbeiten'] = $this->source . "bachelorarbeiten";
                $json_urls['masterarbeiten'] = $this->source . "masterarbeiten";
				$json_urls['forschungspraktika'] = $this->source . "forschungspraktika";
				$json_urls['projektarbeiten'] = $this->source . "projektarbeiten";
				$json_urls['sonstiges'] = $this->source . "sonstiges";
                $task_object = new Studentische_Arbeiten_Alle($json_urls, $format);
                break;
            default:
                return('Keine Übereinstimmung mit dem angegebenen Task gefunden.');
        }
		
		switch ($ressources) {
			case 'link': break;
			default: $ressources = 'base64';
		}
        
        $data = $task_object->get_data();  		
		return $task_object->create_html($data, $ressources);  // Gebe gefundene Daten aus 

    }
    
    public function shortcode($atts) {
        $default = array(
            'task' => '',
            'id' => '',
			'format' => '',
			'advisor' => '',
			'status' => '',
			'ressources' => 'base64'
        );
        $atts = shortcode_atts($default, $atts);
        
	    return $this->get_content($atts['task'], $atts['id'], $atts['format'], $atts['advisor'], $atts['status'], $atts['ressources']); 
    }

    /*
     * Wird durchgeführt wenn das Plugin aktiviert wird.
     * @return void
     */
    public static function activation() {
        // Überprüft die minimal erforderliche PHP- u. WP-Version.
        self::system_requirements();
        
        // Aktualisierung des Plugins (ggf).
        self::update_version();
        
        // Ab hier können die Funktionen/Methoden hinzugefügt werden, 
        // die bei der Aktivierung des Plugins aufgerufen werden müssen.
        // Bspw. wp_schedule_event, flush_rewrite_rules, etc.
    }

    /*
     * Wird durchgeführt wenn das Plugin deaktiviert wird.
     * @return void
     */
    public static function deactivation() {
        // Hier können die Funktionen/Methoden hinzugefügt werden, die
        // bei der Deaktivierung des Plugins aufgerufen werden müssen.
        // Bspw. wp_clear_scheduled_hook
    }

    /*
     * Überprüft die minimal erforderliche PHP- u. WP-Version.
     * @return void
     */
    private static function system_requirements() {
        $error = '';

        if (version_compare(PHP_VERSION, self::php_version, '<')) {
            $error = sprintf(__('Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', self::textdomain), PHP_VERSION, self::php_version);
        }

        if (version_compare($GLOBALS['wp_version'], self::wp_version, '<')) {
            $error = sprintf(__('Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', self::textdomain), $GLOBALS['wp_version'], self::wp_version);
        }

        // Wenn die Überprüfung fehlschlägt, dann wird das Plugin automatisch deaktiviert.
        if (!empty($error)) {
            deactivate_plugins(plugin_basename(__FILE__), false, true);
            wp_die($error);
        }
    }

    /*
     * Aktualisierung des Plugins
     * @return void
     */
    private static function update_version() {
        $version = get_option(self::version_option_name, '0');
        
        if (version_compare($version, self::version, '<')) {
            // Wird durchgeführt wenn das Plugin aktualisiert muss.
        }
        
        update_option(self::version_option_name, self::version);
    }
    
    /*
     * Standard Einstellungen werden definiert
     * @return array
     */
    private static function default_options() {
        $options = array(
            'like_json_field_1' => '',
            // Hier können weitere Felder ('key' => 'value') angelegt werden.
        );

        return $options;
    }

    /*
     * Gibt die Einstellungen zurück.
     * @return object
     */
    private static function get_options() {
        $defaults = self::default_options();

        $options = (array) get_option(self::option_name);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return (object) $options;
    }
        
    /*
     * Füge eine Optionsseite in das Menü "Einstellungen" hinzu.
     * @return void
     */
    public function admin_settings_page() {
        $this->admin_settings_page = add_options_page(__('LIKE-Json', self::textdomain), __('CMS-Basis', self::textdomain), 'manage_options', 'cms-basis', array($this, 'settings_page'));
        add_action('load-' . $this->admin_settings_page, array($this, 'admin_help_menu'));        
    }
    
    /*
     * Die Ausgabe der Optionsseite.
     * @return void
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h2><?php echo __('Einstellungen &rsaquo; CMS-Basis', self::textdomain); ?></h2>
            <form method="post" action="options.php">
                <?php
                settings_fields('like_json_options');
                do_settings_sections('like_json_options');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /*
     * Legt die Einstellungen der Optionsseite fest.
     * @return void
     */
    public function admin_settings() {
        register_setting('like_json_options', self::option_name, array($this, 'options_validate'));
        add_settings_section('like_json_section_1', false, '__return_false', 'like_json_options');
        add_settings_field('like_json_field_1', __('Feld 1', self::textdomain), array($this, 'like_json_field_1'), 'like_json_options', 'like_json_section_1');
    }

    /*
     * Validiert die Eingabe der Optionsseite.
     * @param array $input
     * @return array
     */
    public function options_validate($input) {
        $input['like_json_text'] = !empty($input['like_json_field_1']) ? $input['like_json_field_1'] : '';
        return $input;
    }

    /*
     * Erstes Feld der Optionsseite
     * @return void
     */
    public function like_json_field_1() {
        ?>
        <input type='text' name="<?php printf('%s[like_json_field_1]', self::option_name); ?>" value="<?php echo self::$options->like_json_field_1; ?>">
        <?php
    }

    /*
     * Erstellt die Kontexthilfe der Optionsseite.
     * @return void
     */
    public function admin_help_menu() {

        $content = array(
            '<p>' . __('Hier kommt der Inhalt der Kontexthilfe.', self::textdomain) . '</p>',
        );


        $help_tab = array(
            'id' => $this->admin_settings_page,
            'title' => __('Übersicht', self::textdomain),
            'content' => implode(PHP_EOL, $content),
        );

        $help_sidebar = sprintf('<p><strong>%1$s:</strong></p><p><a href="http://blogs.fau.de/webworking">RRZE-Webworking</a></p><p><a href="https://github.com/RRZE-Webteam">%2$s</a></p>', __('Für mehr Information', self::textdomain), __('RRZE-Webteam in Github', self::textdomain));

        $screen = get_current_screen();

        if ($screen->id != $this->admin_settings_page) {
            return;
        }

        $screen->add_help_tab($help_tab);

        $screen->set_help_sidebar($help_sidebar);
    }
    

}




