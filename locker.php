<?php
declare(strict_types=1);

/*
 * Plugin Name: FAPI Locker
 * Plugin URI: https://fapi.cz/fapi-locker
 * Description: Zamykání stránek a článků s možností zakoupení přístupu přes prodejní aplikaci FAPI.
 * Version: 3.0
 * Author: FAPI Business s.r.o.
 * Author URI: https://www.fapi.cz
 * Text Domain: fapi-locker
 * License:
*/

if (!defined('ABSPATH')) {
    exit;
}

class FAPI_Locker
{
    /** @var string */
    const NAMESPACE = 'FAPILocker';

    /** @var string */
    const SETTINGS_SLUG = 'fapi-locker';

    /** @var string */
    const LANG_DOMAIN = 'fapi-locker';

    /** @var string */
    const OPTIONS_NAME = self::NAMESPACE . 'Options';

    /** @var string */
    const COOKIE_NAME = self::NAMESPACE . 'Post';

    /** @var string */
    const SETTINGS_GROUP = self::NAMESPACE . 'Options';

    /** @var string */
    const METABOX_NONCE_ACTION = self::NAMESPACE . 'NonceAction';

    /** @var string */
    const META_ACTIVE = self::NAMESPACE . 'Active';

    /** @var string */
    const META_NOT_BOUGHT = self::NAMESPACE . 'NotBought';

    /** @var string */
    const META_BUTTON = self::NAMESPACE . 'Button';

    /** @var string */
    const META_UNLOCK_BUTTON = self::NAMESPACE . 'UnlockButton';

    /** @var string */
    const META_SHOW_BUTTON = self::NAMESPACE . 'ShowOrderForm';

    /** @var string */
    const META_INVOICE = self::NAMESPACE . 'Invoice';

    /** @var string */
    const META_FORM = self::NAMESPACE . 'Form';

    /** @var string */
    const META_ALLOWED_EMAILS = self::NAMESPACE . 'AllowedEmails';

    /** @var bool */
    private static $inited = false;

    /** @var array */
    private static $options = [];

    /** @var \Fapi\FapiClient\FapiClient */
    private static $client;

    /** @var string */
    private static $error_message = '';

    /** @var int */
    private static $error_code = 0;

    /** @var bool */
    private static $credentials_valid;


    public static function init()
    {
        if (!static::$inited) {
            static::$inited = true;

            static::$options = get_option(static::OPTIONS_NAME);

            if (!is_array(static::$options)) {
                static::$options = [];
            }

            static::register_hooks();
        }
    }


    private static function log(string $message, string $url = null)
    {
        if (empty($url)) {
            $url = $_SERVER['REQUEST_URI'];
        }

        $message = date('[Y-m-d H:i:s]') . ' ' . $message . ' @ URL: ' . $url . PHP_EOL;
        file_put_contents(__DIR__ . '/log/' . date('Y-m_') . 'log.log', $message, FILE_APPEND);
    }


    /**
     * @param $delimiters
     * @param $string
     *
     * @return array
     */
    private static function multiexplode(array $delimiters, string $string): array
    {
        foreach ($delimiters as &$delimiter) {
            $delimiter = preg_quote($delimiter, '/');
        }

        return preg_split('/(' . implode('|', $delimiters) . ')/', $string, -1, PREG_SPLIT_NO_EMPTY);
    }


    /**
     * @param string $message
     * @param int $err_code
     */
    private static function set_error_message(string $message, int $err_code = 0)
    {
        static::$error_message = $message;
        static::$error_code = $err_code;
    }


    /**
     * @return string
     */
    public static function get_error_message(): string
    {
        return static::$error_message;
    }


    /**
     * @return string
     */
    public static function get_error_message_as_html(): string
    {
        $message = static::get_error_message();

        if (empty($message)) {
            return '';
        }

        return "<div class='fapi_locker_error lockerError'>" . wpautop($message) . "</div>\n";
    }


    private static function get_current_post_id()
    {
        return url_to_postid(filter_input(INPUT_SERVER, 'REQUEST_URI'));
    }


    /**
     * @param int $post_id
     *
     * @return false|string
     */
    private static function get_post_url(int $post_id)
    {
        return get_permalink($post_id);
    }


    private static function get_post_meta(int $post_id)
    {
        $meta = get_post_custom($post_id);

        return array_combine(array_keys($meta), array_column($meta, '0'));
    }


    /**
     * @param string|null $key
     *
     * @return array|string|null
     */
    private static function get_cookie(int $key = null)
    {
        if (isset($_COOKIE[static::COOKIE_NAME])) {
            $cookie = $_COOKIE[static::COOKIE_NAME];
        } else {
            $cookie = null;
        }

        if (!is_array($cookie)) {
            return null;
        }

        if ($key === null) {
            return $cookie;
        }

        if (array_key_exists($key, $cookie) && is_string($cookie[$key])) {
            return $cookie[$key];
        }

        return null;
    }


    /**
     * @param int $post_id
     *
     * @return string
     */
    private static function create_key(int $post_id): string
    {
        return rtrim(base64_encode(md5(md5((string)$post_id, true), true)), '=');
    }


    /**
     * @param int $post_id
     *
     * @return bool
     */
    private static function check_cookie_or_user(int $post_id): bool
    {
        $cookie = static::get_cookie($post_id);

        if (!empty($cookie)) {
            $mykey = static::create_key($post_id);
            static::log(__METHOD__ . ' COOK = ' . $cookie);

            if ($mykey === $cookie) {
                //TODO verze 2.1 jeste validovala $_COOKIE["postLockerM"][$postID], coz zrychli zneplatneni cookie v pripade chybne autorizace (=pokud o neopravneny pristup), na druhe strane tim ukaze email overeneho uzivatele.
                static::log(__METHOD__ . "($post_id) = cookie OK");
                return true;
            }
        } elseif (is_user_logged_in()) {
            static::log('validating current logged in user');

            $WP_User = wp_get_current_user();

            if (static::check_email($WP_User->user_email, $post_id)) {
                static::log(__METHOD__ . "($post_id) = current user OK");
                return true;
            }
        }

        static::log(__METHOD__ . "($post_id) = FALSE");
        return false;
    }


    private static function check_email($email, $post_id)
    {
        static::log(__METHOD__ . "($email, $post_id)...started");
        //Emaily validovat v lower case. Jako oddelovace akceptovat vice moznosti (carky, konce radek...)

        if ($email) {
            $email = strtolower($email);
        }

        if (get_post_meta($post_id, static::META_ALLOWED_EMAILS, true)) {
            $getMails = static::multiexplode([',', ';', "\r\n", "\n"], strtolower(get_post_meta($post_id, static::META_ALLOWED_EMAILS, true)));
        } else {
            $getMails = [];
        }

        if (self::get_options('globalEnableEmails')) {
            $getGlobalMails = static::multiexplode([',', ';', "\r\n", "\n"], strtolower(self::get_options('globalEnableEmails')));
        } else {
            $getGlobalMails = [];
        }

        if (in_array($email, $getMails, true)) {
            static::log(__METHOD__ . "($email, $post_id) = allowed email");
            return true;
        } elseif (in_array($email, $getGlobalMails, true)) {
            static::log(__METHOD__ . "($email, $post_id) = allowed global email");
            return true;
        } else {
            static::log(__METHOD__ . "($email, $post_id) = not in allowed emails, verifying in FAPI...");

            if (static::fapi_check_invoice($email, get_post_meta($post_id, static::META_INVOICE, true))) {
                static::log(__METHOD__ . "($email, $post_id) = FAPI validated");
                return true;
            } else {
                static::log(__METHOD__ . "($email, $post_id) = FAPI validation failed");
                static::log(__METHOD__ . "($email, $post_id) = reason: " . static::get_error_message());
                return false;
            }
        }

        static::log(__METHOD__ . "($email, $post_id) = FALSE unknown / unpredicted reason");
        return false;
    }


    /**
     * @param string $option
     *
     * @return mixed
     */
    private static function get_options(string $option = null)
    {
        if ($option === null) {
            return static::$options;
        }

        if (array_key_exists($option, static::$options)) {
            return static::$options[$option];
        }

        return null;
    }


    /**
     * @param array $new_options
     * @param bool $do_not_merge
     *
     * @return bool
     */
    private static function update_options(array $new_options = [], bool $do_not_merge = false): bool
    {
        if (!empty($new_options)) {
            static::$options = $new_options + ($do_not_merge ? [] : static::$options);
        }

        return \update_option(static::OPTIONS_NAME, static::$options);
    }


    /**
     * @param string $username
     * @param string $apikey
     *
     * @return bool
     */
    private function save_api_credentials(string $username, string $apikey): bool
    {
        return static::update_options([
            'username' => $username,
            'apikey' => $apikey,
        ]);
    }


    /**
     * @return string
     */
    private static function get_api_username(): string
    {
        $username = static::get_options('username');

        return is_string($username) ? $username : '';
    }


    /**
     * @return string
     */
    private static function get_api_apikey(): string
    {
        $apikey = static::get_options('apikey');

        return is_string($apikey) ? $apikey : '';
    }


    /**
     * @return bool
     */
    private static function is_api_credentials_filled(): bool
    {
        return true
            && !empty(static::get_api_username())
            && !empty(static::get_api_apikey());
    }


    /**
     * @return \Fapi\FapiClient\FapiClient
     */
    private static function get_fapi_client(): \Fapi\FapiClient\FapiClient
    {
        if (!(static::$client instanceof \Fapi\FapiClient\IFapiClient)) {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

            $factory = new \Fapi\FapiClient\FapiClientFactory();

            static::$client = $factory->createFapiClient(static::get_api_username(), static::get_api_apikey());
        }

        return static::$client;
    }


    /**
     * @return bool
     */
    private static function validate_api_credentials()
    {
        static::log(__METHOD__ . '()');

        if (static::$credentials_valid !== null) {
            static::log(__METHOD__ . '(): cached = ' . static::$credentials_valid);
            return static::$credentials_valid;
        }

        if (!static::is_api_credentials_filled()) {
            static::set_error_message("Ke službě FAPI se nelze připojit.", 1);
            static::$credentials_valid = false;
            return static::$credentials_valid;
        } else {
            try {
                static::get_fapi_client()->checkConnection();

                static::log(__METHOD__ . '() = OK');
                static::$credentials_valid = true;
                return static::$credentials_valid;
            } catch (\Fapi\FapiClient\Rest\InvalidStatusCodeException $e) {
                static::set_error_message("Ke službě FAPI se nelze připojit.", 1);
                static::$credentials_valid = false;
                return static::$credentials_valid;
            } catch (Exception $e) {
                static::set_error_message("Ke službě FAPI se nelze připojit. \n", 1);
                static::$credentials_valid = false;
                return static::$credentials_valid;
            }
        }
    }


    private static function fapi_check_invoice($email, $invoice)
    {
        static::log(__METHOD__ . '()');

        //Validovat pozadavek  //TODO test na prazdny seznam
        if (empty($invoice)) {
            static::set_error_message('V nastavení FAPI Lockeru chybí název prodejní položky.', 101);
            return false;
        }

        //Validovat nastaveni lockeru
        if (empty(static::get_api_username())) {
            static::set_error_message('V nastavení FAPI Lockeru chybí jméno uživatele pro komunikaci s FAPI.', 102);
            return false;
        }

        if (empty(static::get_api_apikey())) {
            static::set_error_message('V nastavení FAPI Lockeru chybí heslo pro komunikaci s FAPI.', 103);
            return false;
        }

        if (!static::validate_api_credentials()) {
            return false;
        }

        //Vykonny kod
        $fapi = static::get_fapi_client();

        try {
            $clients = $fapi->getClients()->findAll(['email' => $email]);
        } catch (Exception $e) {
            static::set_error_message("Ke službě FAPI se nelze připojit (kód 001). \n", 1);
            return false;
        }

        if (count($clients) == 0) {
            static::set_error_message('Pro e-mailovou adresu "' . htmlspecialchars($email) . "\" není tento tento obsah přístupný.", 3);
            return false;
        }

        $invoices = [];

        try {
            foreach ($clients as $client) {
                $invoices = array_merge($fapi->getInvoices()->findAll(['client' => $client['id'], 'order' => 'created_on']), $invoices);
            }
        } catch (Exception $e) {
            static::set_error_message("Ke službě FAPI se nelze připojit (kód 001). \n", 1);
            return false;
        }


        if (!isset($invoices) || empty($invoices)) {
            static::set_error_message('Pro e-mailovou adresu "' . htmlspecialchars($email) . "\" není tento tento obsah přístupný.", 4);
            return false;
        }

        $allowed = [];

        $resolvedItems = [];

        foreach ($invoices as $inv) {
            if ($inv['paid']) {
                foreach ($inv['items'] as $item) {
                    if (in_array(strtolower($item['name']), array_map('strtolower', $invoice), true)) {

                        foreach ($invoices as $i) {
                            foreach ($i['items'] as $it) {
                                if ($it['name'] != $item['name']) {
                                    continue;
                                }

                                if (isset($i['parent']) && $i['parent'] == $inv['id'] && $i['type'] == 'credit_note' && $it['name'] == $item['name']) {

                                    $allowed[$item['name']] = false;
                                } elseif ($i['paid']) {

                                    $allowed[$item['name']] = true;
                                }
                            }
                        }
                        $resolvedItems[] = $item['name'];
                    }
                }
            }
        }


        if (in_array(true, $allowed)) {
            return true;
        }

        //Faktury existuji, v zadne neni pozadovany text
        static::set_error_message('Pro e-mailovou adresu "' . htmlspecialchars($email) . '" není tento tento obsah přístupný.', 5);
        return false;
    }


    /**
     * Ulozi nastaveni z metaboxu lockeru pro stranku nebo prispevek
     *
     * @param int $post_id
     */
    public static function save_metabox($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE === true) {
            return;
        }

        if (!isset($_POST['lckr_metabox_n_name']) || !wp_verify_nonce($_POST['lckr_metabox_n_name'], static::METABOX_NONCE_ACTION)) {
            return;
        }


        $allowed = [
            'a' => [
                'href' => [],
            ],
        ];

        update_post_meta($post_id, static::META_ACTIVE, (isset($_POST['active']) ? '1' : '0'));

        if (isset($_POST['not_bought'])) {
            update_post_meta($post_id, static::META_NOT_BOUGHT, addslashes($_POST['not_bought']));
        }

        if (isset($_POST['button'])) {
            update_post_meta($post_id, static::META_BUTTON, wp_kses($_POST['button'], $allowed));
        }

        if (isset($_POST['unlockButton'])) {
            update_post_meta($post_id, static::META_UNLOCK_BUTTON, wp_kses($_POST['unlockButton'], $allowed));
        }

        if (isset($_POST['invoice'])) {
            update_post_meta($post_id, static::META_INVOICE, $_POST['invoice']);
        }

        if (isset($_POST['javascriptForm'])) {
            update_post_meta($post_id, static::META_FORM, $_POST['javascriptForm']);
        }

        if (isset($_POST['allowedEmails'])) {
            update_post_meta($post_id, static::META_ALLOWED_EMAILS, wp_kses($_POST['allowedEmails'], $allowed));
        }

        update_post_meta($post_id, static::META_SHOW_BUTTON, (isset($_POST['showOrderForm']) ? '1' : '0'));
    }


    public static function unlock_post()
    {
        static::log(
            'REQUEST:'
            . ' ' . (isset($_POST['lockerMail']) ? 'POST(lockerMail)=' . $_POST['lockerMail'] : 'POST(lockerMail)=null')
            . ' ; ' . (isset($_GET['unlock']) ? 'GET(unlock)=' . $_GET['unlock'] : 'GET(unlock)=null')
        );

        if (isset($_POST['lockerMail'])) {
            $emailToValidate = $_POST['lockerMail'];
        } elseif (isset($_GET['unlock'])) {
            $emailToValidate = $_GET['unlock'];
        }

        if (isset($emailToValidate) && !empty($emailToValidate)) {
            static::log('validating email "' . $emailToValidate . '"');

            $post_id = static::get_current_post_id();

            if (static::check_email(addslashes($_POST['lockerMail']), $post_id)) {
                // Pristup povolen. Uloz cookie a refreshni stranku.
                //TODO Nebo rovnou generuj povoleny obsah pro usetreni jednoho kolecka? Otazkou je, jak pak dostat cookie do prohlizece klienta - musel by se nejakym separe async callem.
                $lockerHash = static::create_key($post_id);

                static::log("setting cookie '" . static::COOKIE_NAME . "[$post_id]' = " . $lockerHash);
                setcookie(static::COOKIE_NAME . '[' . $post_id . ']', $lockerHash, strtotime('+90 days'), '/');

                //adresa pro presmerovani
                $redirectUrl = static::get_post_url($post_id);
                static::log('redirecting to ' . $redirectUrl);

                status_header(302);
                header('location:' . $redirectUrl);

                //Ukonci nasledne generovani stranky.
                static::log('exit');
                exit;
            } //Pokud neni napr. z validace platby dostupna konkretnejsi zprava, pouzij vychozi.
            elseif (empty(static::get_error_message())) {
                static::set_error_message("Tento obsah prozatím nemáte zakoupený. \nPro přístup je nutné obsah odemknout pomocí oprávněné e-mailové adresy nebo zakoupit.", 100);
            }
        }
    }


    private static function register_hooks()
    {
        add_action('plugins_loaded', [__CLASS__, 'hook_plugins_loaded']);

        add_action('admin_init', [__CLASS__, 'hook_admin_init']);
        add_action('admin_menu', [__CLASS__, 'hook_admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'hook_admin_scripts']);
        add_action('add_meta_boxes', [__CLASS__, 'hook_metabox']);

        add_action('wp_enqueue_scripts', [__CLASS__, 'hook_frontend_scripts']);

        add_action('save_post', [__CLASS__, 'save_metabox']);

        add_action('setup_theme', [__CLASS__, 'unlock_post']);

        add_filter('the_content', [__CLASS__, 'render_post_locker'], 101);
        add_filter('get_the_excerpt', [__CLASS__, 'render_post_locker'], 101);

        register_deactivation_hook(__FILE__, [__CLASS__, 'hook_deactivate']);
        register_uninstall_hook(__FILE__, [__CLASS__, 'hook_uninstall']);
    }


    public static function hook_plugins_loaded()
    {
        load_plugin_textdomain(static::LANG_DOMAIN, false, basename(dirname(__FILE__)) . '/languages/');
    }


    public static function hook_deactivate()
    {
    }


    public static function hook_uninstall()
    {
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            return;
        }

        delete_option(static::OPTIONS_NAME);

        delete_post_meta_by_key(static::META_ACTIVE);
        delete_post_meta_by_key(static::META_NOT_BOUGHT);
        delete_post_meta_by_key(static::META_BUTTON);
        delete_post_meta_by_key(static::META_INVOICE);
        delete_post_meta_by_key(static::META_FORM);
        delete_post_meta_by_key(static::META_ALLOWED_EMAILS);
    }


    public static function hook_admin_init()
    {
        register_setting(static::SETTINGS_GROUP, static::OPTIONS_NAME);

        //Locker main section
        add_settings_section(static::NAMESPACE . 'Main', __('Propojení s FAPI', static::LANG_DOMAIN), [__CLASS__, 'render_options_section'], static::SETTINGS_SLUG);
        add_settings_field(static::NAMESPACE . 'SettingsUsername', __('Přihlašovací jméno', static::LANG_DOMAIN), [__CLASS__, 'render_options_username'], static::SETTINGS_SLUG, static::NAMESPACE . 'Main');
        add_settings_field(static::NAMESPACE . 'SettingsAPIKey', __('API klíč', static::LANG_DOMAIN), [__CLASS__, 'render_options_apikey'], static::SETTINGS_SLUG, static::NAMESPACE . 'Main');

        //Locker theme section
        add_settings_section(static::NAMESPACE . 'Theme', __('Vzhled', static::LANG_DOMAIN), [__CLASS__, 'render_options_section'], static::SETTINGS_SLUG);
        add_settings_field(static::NAMESPACE . 'SettingsBorder', __('Ohraničení formuláře', static::LANG_DOMAIN), [__CLASS__, 'render_options_border'], static::SETTINGS_SLUG, static::NAMESPACE . 'Theme');
        add_settings_field(static::NAMESPACE . 'SettingsButtonBackground', __('Barva pozadí tlačítka', static::LANG_DOMAIN), [__CLASS__, 'render_options_button_background'], static::SETTINGS_SLUG, static::NAMESPACE . 'Theme');
        add_settings_field(static::NAMESPACE . 'SettingsButtonColor', __('Barva písma tlačítka', static::LANG_DOMAIN), [__CLASS__, 'render_options_button_color'], static::SETTINGS_SLUG, static::NAMESPACE . 'Theme');

        //Locker global section
        add_settings_section(static::NAMESPACE . 'Access', __('Přístup k obsahu', static::LANG_DOMAIN), [__CLASS__, 'render_options_section'], static::SETTINGS_SLUG);
        add_settings_field(static::NAMESPACE . 'SettingsGlobalEnableEmails', __('E-mailové adresy s univerzálním přístupem (pro všechny zamčené stránky)', static::LANG_DOMAIN), [__CLASS__, 'render_global_enable_emails'], static::SETTINGS_SLUG, static::NAMESPACE . 'Access');
    }


    public static function hook_admin_menu()
    {
        add_options_page(__('Nastavení FAPI lockeru', static::LANG_DOMAIN), __('FAPI Locker', static::LANG_DOMAIN), 'manage_options', static::SETTINGS_SLUG, [__CLASS__, 'render_options']);
    }


    public static function hook_admin_scripts()
    {
        wp_register_script(static::NAMESPACE . 'Backend', plugins_url('/assets/js/backend.js', __FILE__), ['jquery'], '1.0', true);
        wp_enqueue_script(static::NAMESPACE . 'Backend');
        wp_register_script(static::NAMESPACE . 'ColorPicker', plugins_url('/assets/libs/colorpicker/js/colpick.js', __FILE__), ['jquery'], '1.0', true);
        wp_enqueue_script(static::NAMESPACE . 'ColorPicker');
        wp_register_style(static::NAMESPACE . 'ColorPickerCSS', plugins_url('/assets/libs/colorpicker/css/colpick.css', __FILE__), [], '1.0');
        wp_enqueue_style(static::NAMESPACE . 'ColorPickerCSS');
        wp_register_style(static::NAMESPACE . 'BackendCSS', plugins_url('/assets/css/backend.css', __FILE__), [], '1.0');
        wp_enqueue_style(static::NAMESPACE . 'BackendCSS');
    }


    public static function hook_metabox()
    {
        add_meta_box(static::NAMESPACE . 'Meta', 'FAPI Locker', [__CLASS__, 'render_metabox'], 'post', 'normal', 'high');
        add_meta_box(static::NAMESPACE . 'Meta', 'FAPI Locker', [__CLASS__, 'render_metabox'], 'page', 'normal', 'high');
    }


    public static function hook_frontend_scripts()
    {
        wp_register_script(static::NAMESPACE . 'Frontend', plugins_url('/assets/js/frontend.js', __FILE__), ['jquery'], '1.0', true);
        wp_enqueue_script(static::NAMESPACE . 'Frontend');

        wp_register_style(static::NAMESPACE . 'FrontendCSS', plugins_url('/assets/css/frontend.css', __FILE__), [], '1.0');
        wp_enqueue_style(static::NAMESPACE . 'FrontendCSS');
    }


    public static function render_options_section()
    {
    }


    public static function render_options_username()
    {
        echo '<input id="lockerSettingsUsername" name="' . static::OPTIONS_NAME . '[username]" size="40" type="text" value="' . esc_html(static::get_api_username()) . '" />';
    }


    public static function render_options_apikey()
    {
        echo '<input id="lockerSettingsAPIKey" name="' . static::OPTIONS_NAME . '[apikey]" size="40" type="password" value="' . esc_html(static::get_api_apikey()) . '" />';

        if (!static::validate_api_credentials()) {
            echo '<br /><br /><strong style="font-size:15px;color:red;">' . __('Nepodařilo se připojit k FAPI. Zkontrolujte prosím své přihlašovací údaje.', static::LANG_DOMAIN) . '</strong>';
        } else {
            echo '<br /><br /><strong style="font-size:15px;color:green;">' . __('Připojeno k FAPI.', static::LANG_DOMAIN) . '</strong>';
        }
    }


    public static function render_options_border()
    {
        echo '
<select name="' . static::OPTIONS_NAME . '[border]">
<option value="0">' . __('Bez ohraničení', static::LANG_DOMAIN) . '</option>
<option value="1" ' . (static::get_options('border') == 1 ? 'selected' : '') . '>' . __('S ohraničením', static::LANG_DOMAIN) . '</option>
</select>';
    }


    public static function render_options_button_background()
    {
        echo '<input id="lockerOptionsButtonBackground" name="' . static::OPTIONS_NAME . '[buttonBackground]" size="40" type="text" value="' . esc_html(static::get_options('buttonBackground') ?: '') . '" />';
    }


    public static function render_options_button_color()
    {
        echo '<input id="lockerOptionsButtonColor" name="' . static::OPTIONS_NAME . '[buttonColor]" size="40" type="text" value="' . esc_html(static::get_options('buttonColor') ?: '') . '" />';
    }


    public static function render_global_enable_emails()
    {
        echo '<textarea id="lockerGlobalEnableEmails" name="' . static::OPTIONS_NAME . '[globalEnableEmails]" cols="40" rows="5">' . esc_html(static::get_options('globalEnableEmails') ?: '') . '</textarea><br/>Adresy oddělujte čárkou. ';
    }


    public static function render_options()
    {
        echo '
<div class="wrap">
<h2>' . __('Nastavení rozšíření FAPI Locker', static::LANG_DOMAIN) . '</h2>
<form method="post" action="options.php">';

        settings_fields(static::SETTINGS_GROUP);
        do_settings_sections(static::SETTINGS_SLUG);
        submit_button();

        echo '
</form>
</div>
';
    }


    public static function render_metabox()
    {
        global
        $post;
        $values = static::get_post_meta((int)$post->ID);

        // Zkontroluj, zda je funkcni FAPI pripojeni. Bez nej nema nastavnei smysl.
        if (!static::validate_api_credentials()) {
            echo '<br /><strong class="fapi_locker_error lockerError">' . __('Propojení s FAPI není funkční.', static::LANG_DOMAIN) . '</strong>'
                . '<br /><br />'
                . __('Správné přihlašovací údaje zadejte v ', static::LANG_DOMAIN) . '<a href="' . esc_html(admin_url() . 'options-general.php?page=' . static::SETTINGS_SLUG) . '">' . __('nastavení FAPI lockeru', static::LANG_DOMAIN) . '</a>.';

            return;
        }

        $fapi_client = static::get_fapi_client(); ?>

        <div id="lockerMeta">
            <?php
                wp_nonce_field(static::METABOX_NONCE_ACTION, 'lckr_metabox_n_name'); ?>

            <label for="active"
                   style="font-size: 14px;"><?php echo __('Zamknout stránku', static::LANG_DOMAIN); ?></label>
            <input type="checkbox" id="active" name="active"
                   value="1" <?php echo $values[static::META_ACTIVE] === '1' ? 'checked' : ''; ?>>
            <label for="active">Toggle</label>

            <div class="clear"></div>

            <div id="activeLocker" <?php echo (isset($values[static::META_ACTIVE]) && $values[static::META_ACTIVE] === '1') ? 'style="display:block;"' : ''; ?>>
                <label for="not_bought"
                       style="font-size: 14px;"><?php echo __('Text zobrazený před udělením přístupu', static::LANG_DOMAIN); ?></label>
                <?php wp_editor('' . (!isset($values[static::META_NOT_BOUGHT]) ? '' : stripslashes($values[static::META_NOT_BOUGHT])) . '', 'not_bought'); ?>

                <div class="clear"></div>

                <label for="invoice"
                       style="font-size: 14px;"><?php echo __('Produkt udělující přístup k obsahu', static::LANG_DOMAIN); ?></label>

                <?php
                    $itemTemplates = $fapi_client->getItemTemplates()->findAll();
                    $invoiceItems = (isset($values[static::META_INVOICE]) ? maybe_unserialize($values[static::META_INVOICE]) : []);

                    echo '<select name="invoice[]" id="invoice">';

                    foreach ($itemTemplates as $item) {
                        echo '<option ' . ($invoiceItems[0] == $item['name'] ? 'selected' : '') . '>' . $item['name'] . '</option>';
                    }

                    echo '</select>'; ?>
                <div id="invoiceSelects">
                    <?php
                        if (!empty($itemTemplates)) {
                            if (isset($_GET['action']) == 'edit') {
                                $n = 0;
                                foreach ($invoiceItems as $invoiceItem) {
                                    $n++;

                                    if ($n == 1) {
                                        continue;
                                    }

                                    echo '<div><select name="invoice[]">';
                                    foreach ($itemTemplates as $item) {
                                        echo '<option ' . ($invoiceItem == $item['name'] ? 'selected' : '') . '>' . $item['name'] . '</option>';
                                    }
                                    echo '</select>&nbsp;<a class="removeInvoice" href="#">X</a> <div class="clear"></div></div>';
                                }
                            }
                        } ?>

                </div>
                <a href="#" id="addInvoice"><?php echo __('Přidat další produkt', static::LANG_DOMAIN); ?></a>

                <div class="clear"></div>

                <label for="unlockbutton"
                       style="font-size: 14px;"><?php echo __('Text odkazu pro odemčení obsahu', static::LANG_DOMAIN); ?></label>

                <div class="clear"></div>

                <input id="button" name="unlockButton"
                       value="<?php echo !isset($values[static::META_UNLOCK_BUTTON]) ? 'Mám koupeno, chci odemknout obsah' : $values[static::META_UNLOCK_BUTTON]; ?>"/>

                <div class="clear"></div>

                <label for="allowedEmails"
                       style="font-size: 14px;"><?php echo __('E-mailové adresy s univerzálním přístupem (pro tuto stránku)', static::LANG_DOMAIN); ?></label>
                <textarea name="allowedEmails"
                          id="allowedEmails"><?php echo isset($values[static::META_ALLOWED_EMAILS]) ? $values[static::META_ALLOWED_EMAILS] : ''; ?></textarea><br/>
                <?php echo __('Adresy oddělujte čárkou.', static::LANG_DOMAIN); ?>

                <div class="clear"></div>

                <label for="showOrderForm"
                       style="font-size: 14px;"><?php echo __('Zobrazit tlačítko pro zakoupení přístupu', static::LANG_DOMAIN); ?></label>
                <input type="checkbox" id="showOrderForm" name="showOrderForm"
                       value="1" <?php echo $values[static::META_SHOW_BUTTON] === '0' ? '' : 'checked'; ?>>
                <label for="showOrderForm">Toggle</label>


                <div class="clear"></div>
                <div id="activeShowOrderForm">
                    <label for="button"
                           style="font-size: 14px;"><?php echo __('Text tlačítka pro zobrazení prodejního formuláře', static::LANG_DOMAIN); ?></label>

                    <div class="clear"></div>
                    <input id="button" name="button"
                           value="<?php echo !isset($values[static::META_BUTTON]) ? 'Zakoupit' : $values[static::META_BUTTON]; ?>"/>


                    <div class="clear"></div>

                    <label for="javascriptForm"
                           style="font-size: 14px;"><?php echo __('Prodejní formulář pro zakoupení přístupu', static::LANG_DOMAIN); ?></label>
                    <select name="javascriptForm" id="javascriptForm">
                        <?php
                            $forms = $fapi_client->forms->findAll();
                            if (!empty($forms)) {
                                foreach ($forms as $form) {
                                    echo '<option value="' . $form['id'] . '" ' . ((string)$values['FAPILockerForm'] == (string)$form['id'] ? 'selected' : '') . '>' . $form['name'] . '</option>';
                                }
                            } ?>
                    </select>

                    <div class="clear"></div>
                </div>
                <br/>
                <a href="<?php echo esc_attr(get_admin_url() . 'options-general.php?page=' . static::SETTINGS_SLUG); ?>"
                   target="_blank">
                    <?php echo __('Nastavení FAPI Lockeru', static::LANG_DOMAIN); ?>
                </a>
                <br/>
            </div>
        </div>
        <?php
    }


    public static function render_post_locker($content)
    {
        global $emailToValidate;

        $post_id = get_the_ID();

        if (get_post_meta($post_id, static::META_ACTIVE, true) == '1') {
            // Deaktivovat CACHE pro tuto stranku
            if (!defined('DONOTCACHEPAGE')) {
                define('DONOTCACHEPAGE', true);
            }

            static::log(__METHOD__ . "($post_id) = locker ACTIVE");

            if (static::check_cookie_or_user($post_id)) {
                static::log(__METHOD__ . "($post_id) = returning CONTENT");
                return $content;
            }

            $lockerContent = '';

            // Pro generovani prodejniho formulare potrebujeme funkcni pristup na FAPI.
            if (!static::validate_api_credentials()) {
                static::log(__METHOD__ . "($post_id) = returning LOCKER - FAPI not connected");
                $lockerContent .= static::get_error_message_as_html();
                return $lockerContent;
            }

            $fapiSettings = static::get_options();

            $fapi = static::get_fapi_client();

            $form_id = (int)(get_post_meta($post_id, static::META_FORM, true));

            $fapiForm = $fapi->getForms()->find($form_id); //TODO hodila by se tu kontrola, ze form existuje (nekdo ho mohl prejmenovat/smaznout...)

            // Pokud formular nebyl nalezen ...
            if ($fapiForm === null) {
                // ... informuj o tom zakaznika, ze formular byl odstranen
                return __('Článek není možné odemknout, jelikož autor odstranil FAPI formulář', static::LANG_DOMAIN);
            }

            // Predcachovat styl pro tlacitka.
            $lockerButtonStyleHtml =
                (isset($fapiSettings['buttonBackground']) && !empty($fapiSettings['buttonBackground']) || isset($fapiSettings['buttonColor']) && !empty($fapiSettings['buttonColor']))
                    ? (
                    'style="'
                    . (isset($fapiSettings['buttonBackground']) && !empty($fapiSettings['buttonBackground']) ? 'background-color:#' . $fapiSettings['buttonBackground'] . ';' : '')
                    . ''
                    . (isset($fapiSettings['buttonColor']) && !empty($fapiSettings['buttonColor']) ? 'color:#' . $fapiSettings['buttonColor'] . '' : '')
                    . '"'
                ) : '';

            // Pokud je evidovana chyba z vyhodnoceni, vypis ji.
            $lockerContent .= static::get_error_message_as_html();

            $lockerContent .= '<div id="lockerBox" class="' . ($fapiSettings['border'] == 1 ? 'border' : '') . '"><div id="lockerBoxInner">';

            // Locker funguje pouze pro stranky a prispevky. Pro stranky vyhledavani a obsahu pouzit zkracenou verzi.
            if (!(is_single() || is_page())) {
                $lockerContent .= stripslashes(get_post_meta($post_id, static::META_NOT_BOUGHT, true))
                    . "\n<br />" . __('Akci můžete provést na', static::LANG_DOMAIN) . ' <a href="' . static::get_post_url($post_id) . '">' . __('stránce s obsahem', static::LANG_DOMAIN) . '</a>.';
            } else {
                $values = static::get_post_meta($post_id);
                $lockerContent .= stripslashes(wpautop(get_post_meta($post_id, static::META_NOT_BOUGHT, true)));

                // Generuj prodejni form.
                if (get_post_meta($post_id, static::META_SHOW_BUTTON, true) == '1') {
                    $lockerContent .= '<input type="button" id="lockerBuyButton" value="' . get_post_meta($post_id, static::META_BUTTON, true) . '" ' . $lockerButtonStyleHtml . ' />';

                    $lockerContent .= '<br class="hide" />';
                }

                if (strlen(get_post_meta($post_id, static::META_UNLOCK_BUTTON, true)) > 0) {
                    $lockerContent .= '<a href="#!" id="lockerCheckMailButton">' . get_post_meta($post_id, static::META_UNLOCK_BUTTON, true) . '' . '</a>';

                    $lockerContent .= '<br class="hide" />';
                } else {
                    $lockerContent .= '<a href="#!" id="lockerCheckMailButton">Mám koupeno, chci odemknout obsah</a>';

                    $lockerContent .= '<br class="hide" />';
                }

                // Generuj odemykaci FORM podle emailu.
                $lockerContent .= '
<div id="lockerCheckMail">
    <form action="" method="post">
        <label for="lockerMail">' . __('Zadejte e-mailovou adresu, kterou jste použili v objednávce', static::LANG_DOMAIN) . '. </label>
        <input type="text" name="lockerMail" id="lockerMail" ' . (isset($emailToValidate) ? 'value="' . esc_attr($emailToValidate) . '"' : '') . ' />
        <input type="submit" value="Odemknout" ' . $lockerButtonStyleHtml . ' />
    </form>
</div>';
                $lockerContent .= '<br />';
                $lockerContent .= '<div id="lockerForm">' . $fapiForm['html_code'] . '</div>';
                $lockerContent .= '<a id="fapiLocker" href="https://fapi.cz/fapi-locker/" target="_blank"><strong>FAPI</strong> Locker</a>';
            }
            $lockerContent .= '</div></div>';

            static::log(__METHOD__ . "($post_id) = returning LOCKER");
            return $lockerContent;
        }
        static::log(__METHOD__ . '(ORIGINAL_CONTENT) = locker INACTIVE');
        return $content;
    }
}

FAPI_Locker::init();
