<?php
/*
Plugin Name: FAPI Locker
Plugin URI: https://fapi.cz/fapi-locker
Description: Zamykání článků a stránek s ověřováním uhrazené platby přes FAPI s možností přímého nákupu.
Version: 2.3
Author: Firma 2.0 - FAPI
Author URI: http://www.fapi.cz
License:
*/


/** Slug-name stranky nastaveni pro locker. */
define("LKR_SETTINGS_SLUG", "locker");
/** Nazev skupiny nastaveni lockeru. */
define("LKR_OPTION_GROUP", "lockerOptions");
/** Globalni promenna obsahujici chybovou zpravu, pokud neco selhalo. Zprava je jako prosty text urceny pro vypis uzivateli.
 * @global @var string $lockerErrorMessage  */
$lockerErrorMessage = "";
/**
 * @global @var bool $lockerFapiCredentialsValidated Je-li nastavena, pak obsahuje vysledek validace prihlaseni k FAPI.
 * Jedne se v podstate o cachovani vysledku, pokud je potreba validaci provest vicekrat v jedne iteraci, napr. pri vysledku
 * vyhledavani.
 */
unset($lockerFapiCredentialsValidated);

/**
 * Debugovaci zprava prefixovana "FAPI locker" pro snazsi ladeni. Vhodne pri logovani pres WP do souboru.
 * @param $message string Zprava, ktera se ma zalogovat
 */
function debug_fapi($message)
{
    if (isset($message) and ($message))
        error_log("FAPI locker -- " . $message);
}


/*
 * Hook pro pripojeni do administracniho menu --> Settings/Locker
 */
function hook_registerLockerMenu()
{
    add_options_page('Nastavení FAPI lockeru', 'FAPI Locker', 'manage_options', 'options.php', 'genLckrOptions');
}
add_action('admin_menu', 'hook_registerLockerMenu');

/*
 * Registrace pro nastaveni pluginu
 */
function hook_registerPluginSettings()
{
    register_setting(LKR_OPTION_GROUP, 'lockerOptions');

    //Locker main section
    add_settings_section('lockerMain', 'Nastavení údajů', 'lockerOptionsSection', LKR_SETTINGS_SLUG);
    add_settings_field('lockerSettingsUsername', 'Přihlašovací jméno', 'genLckrStgs_Username', LKR_SETTINGS_SLUG, 'lockerMain');
    add_settings_field('lockerSettingsPassword', 'Heslo', 'genLckrStgs_Password', LKR_SETTINGS_SLUG, 'lockerMain');

    //Locker theme section
    add_settings_section('lockerTheme', 'Nastavení vzhledu', 'lockerOptionsSection', LKR_SETTINGS_SLUG);
    add_settings_field('lockerSettingsBorder', 'Ohraničení formuláře', 'genLckrStgs_Border', LKR_SETTINGS_SLUG, 'lockerTheme');
    add_settings_field('lockerSettingsButtonBackground', 'Barva pozadí tlačítka', 'genLckrStgs_ButtonBackground', LKR_SETTINGS_SLUG, 'lockerTheme');
    add_settings_field('lockerSettingsButtonColor', 'Barva písma tlačítka', 'genLckrStgs_ButtonColor', LKR_SETTINGS_SLUG, 'lockerTheme');
}
add_action('admin_init', 'hook_registerPluginSettings');


/*
 * Hook pro deaktivaci pluginu
 */
function hook_lockerPluginDeactivate()
{
    //
}
register_deactivation_hook(__FILE__, 'hook_lockerPluginDeactivate');

/*
 * Hook pro odinstalaci pluginu
 */
function hook_lockerPluginUninstall()
{
    if (!defined('WP_UNINSTALL_PLUGIN'))
        exit ();

    delete_option('lockerOptions');
    delete_post_meta_by_key('lockerActive');
    delete_post_meta_by_key('lockerNotBought');
    delete_post_meta_by_key('lockerButton');
    delete_post_meta_by_key('lockerInvoice');
    delete_post_meta_by_key('lockerForm');
    delete_post_meta_by_key('lockerAllowedEmails');
}
register_uninstall_hook(__FILE__, 'hook_lockerPluginUninstall');

function lockerOptionsSection()
{
    // section title
}

function genLckrStgs_Username()
{
    $options = get_option('lockerOptions');
    echo "<input id='lockerSettingsUsername' name='lockerOptions[username]' size='40' type='text' value='"
        . (isset($options["username"]) ? $options["username"] : "")
        . "' />";
}

function genLckrStgs_Password()
{
    $options = get_option('lockerOptions');
    echo "<input id='lockerSettingsPassword' name='lockerOptions[password]' size='40' type='password' value='"
        . (isset($options["password"]) ? $options["password"] : "")
        . "' />";
}


function genLckrStgs_Border()
{
    $options = get_option('lockerOptions');
    echo "<select name=\"lockerOptions[border]\">
        <option value=\"0\">Bez ohraničení</option>
        <option value=\"1\" "
            . (isset($options["border"]) && $options["border"] == 1 ? "selected" : "")
            . ">S ohraničením</option>
        </select>";
}


function genLckrStgs_ButtonBackground()
{
    $options = get_option('lockerOptions');
    echo "<input id='lockerOptionsButtonBackground' name='lockerOptions[buttonBackground]' size='40' type='text' value='"
        . (isset($options["buttonBackground"]) ? $options["buttonBackground"] : "")
        . "' />";
}

function genLckrStgs_ButtonColor()
{
    $options = get_option('lockerOptions');
    echo "<input id='lockerOptionsButtonColor' name='lockerOptions[buttonColor]' size='40' type='text' value='"
        . (isset($options["buttonColor"]) ? $options["buttonColor"] : "")
        . "' />";
}


/**
 * Vygeneruje stranku globalniho nastaveni FAPI lockeru obsahujici nastaveni pripojeni na FAPI.
 */
function genLckrOptions()
{
    echo "
<div class=\"wrap\">
	<h2>Locker: Nastavení FAPI údajů</h2>
	<form method=\"post\" action=\"options.php\">";
    if (!fapiCheckCredentials()) {
//        add_settings_error('locker', '199', 'Nelze se pripojit...');
        echo "<br /><strong style=\"font-size:15px;color:red;\">Nepodařilo se připojit k FAPI. Zkontrolujte prosím své přihlašovací údaje.</strong>";
    }
    settings_fields(LKR_OPTION_GROUP);
    do_settings_sections('locker');
    submit_button();
    echo "
	</form>
</div>
";
}

/**
 * Hook pro zaveseni skriptu lockeru.
 */
function hook_registerAdminScripts()
{
    wp_register_script('lockerBackend', plugins_url('/js/backend.js', __FILE__), array('jquery'), '1.0', true);
    wp_enqueue_script('lockerBackend');
    wp_register_script('colorPicker', plugins_url('/libs/colorpicker/js/colpick.js', __FILE__), array('jquery'), '1.0', true);
    wp_enqueue_script('colorPicker');
    wp_register_style('colorPickerCSS', plugins_url('/libs/colorpicker/css/colpick.css', __FILE__), array(), '1.0');
    wp_enqueue_style('colorPickerCSS');
    wp_register_style('lockerBackendCSS', plugins_url('/css/backend.css', __FILE__), array(), '1.0');
    wp_enqueue_style('lockerBackendCSS');
}
add_action('admin_enqueue_scripts', 'hook_registerAdminScripts');

/**
 * Prida editacni metaboxy pro stranky a pro prispevky.
 */
function lockerMetaBox()
{
    add_meta_box('lockerMeta', 'Locker', 'genLckrMetaBox', 'post', 'normal', 'high');
    add_meta_box('lockerMeta', 'Locker', 'genLckrMetaBox', 'page', 'normal', 'high');

}
add_action('add_meta_boxes', 'lockerMetaBox');

/**
 * Vygeneruje nastavovaci metabox lockeru pro stranku nebo prispevek
 */
function genLckrMetaBox()
{
    global $post;
    $values = get_post_custom($post->ID);

    require_once(plugin_dir_path(__FILE__) . "FAPIClient.php");

    // Zkontroluj, zda je funkcni FAPI pripojeni. Bez nej nema nastavnei smysl.
    if (!fapiCheckCredentials()) {
        echo "<br /><strong class=\"fapi_locker_error lockerError\"> Připojení na FAPI není funkční. Nejprve je potřeba nastavit spojení na FAPI.</strong>"
            ."<br /><br />"
            ." Správné přihlašovací údaje zadejte v <a href=\"" . admin_url() . "options-general.php?page=options.php\">nastavení FAPI lockeru</a>."
        ;
        return;
    }


    $fapiSettings = get_option("lockerOptions");
    $fapi = new FAPIClient($fapiSettings["username"], $fapiSettings["password"]);

    ?>

    <div id="lockerMeta">
        <?php
        wp_nonce_field('lckr_metabox_nonce_action', 'lckr_metabox_n_name');
        ?>

        <label for="active">Stav lockeru</label>
        <select id="active" name="active">
            <option value="0">Neaktivní</option>
            <option value="1" <?php if (isset($values["lockerActive"][0]) && $values["lockerActive"][0] == 1) {
                echo "selected";
            } ?>>Aktivní</option>
        </select>

        <div class="clear"></div>

        <div id="activeLocker" <?php if (isset($values["lockerActive"][0]) && $values["lockerActive"][0] == 1) {
            echo "style=\"display:block;\"";
        } ?>>
            <label for="not_bought">Text v případě nezakoupení článku</label>
            <?php wp_editor('' . (!isset($values["lockerNotBought"][0]) ? "" : stripslashes($values["lockerNotBought"][0])) . '', 'not_bought'); ?>

            <div class="clear"></div>

            <label for="button">Text tlačítka pro zobrazení Prodejního formuláře</label>
            <input id="button" name="button"
                   value="<?php echo(!isset($values["lockerButton"][0]) ? "Zakoupit" : $values["lockerButton"][0]); ?>"/>

            <div class="clear"></div>

            <label for="invoice">Název položky na faktuře</label>
            <?php
            $itemTemplates = $fapi->itemTemplate->getAll();

            if (isset($_GET["action"]) == "edit") {
                $invoiceItems = unserialize($values["lockerInvoice"][0]);
            }

            echo "<select name=\"invoice[]\" id=\"invoice\">";
            foreach ($itemTemplates as $item) {
                if (isset($_GET["action"]) == "edit") {
                    echo "<option " . ($invoiceItems[0] == $item["name"] ? "selected" : "") . ">" . $item["name"] . "</option>";
                } else {
                    echo "<option " . ($values["lockerInvoice"][0] == $item["name"] ? "selected" : "") . ">" . $item["name"] . "</option>";
                }
            }
            echo "</select>";

            ?>
            <div id="invoiceSelects">
                <?php
                if (!empty($itemTemplates)) {

                    if (isset($_GET["action"]) == "edit") {

                        $n = 0;
                        foreach (unserialize($values["lockerInvoice"][0]) as $invoiceItem) {
                            $n++;

                            if ($n == 1) {
                                continue;
                            }

                            echo "<div><select name=\"invoice[]\">";
                            foreach ($itemTemplates as $item) {
                                echo "<option " . ($invoiceItem == $item["name"] ? "selected" : "") . ">" . $item["name"] . "</option>";
                            }
                            echo "</select>&nbsp;<a class=\"removeInvoice\" href=\"#\">X</a> <div class=\"clear\"></div></div>";

                        }
                    }

                }

                ?>

            </div>
            <a href="#" id="addInvoice">Přidat další položku</a>

            <div class="clear"></div>

            <label for="javascriptForm">Zvolte Prodejní formulář FAPI</label>
            <select name="javascriptForm" id="javascriptForm">
                <?php
                $forms = $fapi->form->getAll();
                if (!empty($forms)) {
                    foreach ($forms as $form) {
                        echo "<option value=\"" . $form["id"] . "\" " . ($values["lockerForm"][0] == $form["id"] ? "selected" : "") . ">" . $form["name"] . "</option>";
                    }
                }
                ?>
            </select>

            <div class="clear"></div>

            <label for="allowedEmails">Povolené e-maily</label>
            <textarea name="allowedEmails" id="allowedEmails"><?php echo ""
                    . (isset($values["lockerAllowedEmails"][0]) ? $values["lockerAllowedEmails"][0] : "");
                ?></textarea><br/>
            Při zadávání více emailů je oddělujte čárkou.

            <div class="clear"></div>
            <br/>
            <a href="<?php echo get_admin_url(); ?>/options-general.php?page=options.php" target="_blank">Globální nastavení
                barvy a ohraničení prodejního formuláře</a>
            <br/>
        </div>
    </div>
    <?php
}

/**
 * Ulozi nastaveni z metaboxu lockeru pro stranku nebo prispevek
 */
function saveLckrMetabox($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['lckr_metabox_n_name']) || !wp_verify_nonce($_POST['lckr_metabox_n_name'], 'lckr_metabox_nonce_action')) return;
    $allowed = array(
        'a' => array(
            'href' => array()
        )
    );

    if (isset($_POST['active']))
        update_post_meta($post_id, 'lockerActive', wp_kses($_POST['active'], $allowed));
    if (isset($_POST['not_bought']))
        update_post_meta($post_id, 'lockerNotBought', addslashes($_POST['not_bought']));
    if (isset($_POST['button']))
        update_post_meta($post_id, 'lockerButton', wp_kses($_POST['button'], $allowed));
    if (isset($_POST['invoice']))
        update_post_meta($post_id, 'lockerInvoice', $_POST['invoice']);
    if (isset($_POST['javascriptForm']))
        update_post_meta($post_id, 'lockerForm', $_POST['javascriptForm']);
    if (isset($_POST['allowedEmails']))
        update_post_meta($post_id, 'lockerAllowedEmails', wp_kses($_POST['allowedEmails'], $allowed));
}
add_action('save_post', 'saveLckrMetabox');

/**
 * Registrovat frontend skripty.
 */
function hook_registerFrontendScripts()
{
    wp_enqueue_script("jquery");

    wp_register_script('lockerFrontend', plugins_url('/js/frontend.js', __FILE__), array('jquery'), '1.0', true);
    wp_enqueue_script('lockerFrontend');

    wp_register_style('lockerFrontendCSS', plugins_url('/css/frontend.css', __FILE__), array(), '1.0');
    wp_enqueue_style('lockerFrontendCSS');
}
add_action('wp_enqueue_scripts', 'hook_registerFrontendScripts');

/**
 -------------------------------------------------------------------------------------------------------------
 */

/**
 * Zkontroluje, zda jsou nastaveny prihlasovaci udaje a zda se lze s nimi uspesne spojit s FAPI.
 * Pri chybe naplni globalni promennou $lockerErrorMessage.
 *
 * @return bool Vrati true, pokud je spojeni funkci. Vrati false pri chybe a naplni $lockerErrorMessage.
 */
function fapiCheckCredentials()
{
    global $lockerErrorMessage;
    global $lockerFapiCredentialsValidated;

    debug_fapi("fapiCheckCredentials()");
    if (isset($lockerFapiCredentialsValidated)) {
        debug_fapi("fapiCheckCredentials(): cached = " . $lockerFapiCredentialsValidated);
        return $lockerFapiCredentialsValidated;
    }

    $fapiSettings = get_option("lockerOptions");
    if (($fapiSettings["username"] == "") OR ($fapiSettings["password"] == "")) {
        $lockerErrorMessage = "Ke službě FAPI se nelze připojit (kód 001). \nChybí přihlašovací údaje do FAPI.";
        debug_fapi("fapiCheckCredentials() = error no credential > ");
        $lockerFapiCredentialsValidated = false;
        return $lockerFapiCredentialsValidated;
    } else {
        require_once(plugin_dir_path(__FILE__) . "FAPIClient.php");
        try {
            $fapi = new FAPIClient($fapiSettings["username"], $fapiSettings["password"]);
            $fapi->checkConnection();
            if ($fapi->getCode() != 200) {
                $lockerErrorMessage = "Služba FAPI není dostupná (kód 002). \nHTTP status code = ". $fapi->getCode();
                debug_fapi("fapiCheckCredentials() = error connection > HTTP status code = ". $fapi->getCode());
                $lockerFapiCredentialsValidated = false;
                return $lockerFapiCredentialsValidated;
            }
            debug_fapi("fapiCheckCredentials() = OK");
            $lockerFapiCredentialsValidated = true;
            return $lockerFapiCredentialsValidated;
        } catch (FAPIClient_UnauthorizedException $e) {
            $lockerErrorMessage = "Ke službě FAPI se nelze připojit (kód 001). \nNeplatné přihlašovací údaje do FAPI.";
            debug_fapi("fapiCheckCredentials() = error unauthorized > " . $e->getMessage());
            $lockerFapiCredentialsValidated = false;
            return $lockerFapiCredentialsValidated;
        } catch (Exception $e) {
            $lockerErrorMessage = "Ke službě FAPI se nelze připojit (kód 001). \n" . $e->getMessage();
            debug_fapi("fapiCheckCredentials() = error general > " . $e->getMessage());
            $lockerFapiCredentialsValidated = false;
            return $lockerFapiCredentialsValidated;
        }
    }
}

/**
 * Provede kontrolu ve FAPI, zda v nektere fakture klienta s danym $email existuje polozka s textem $invoice.
 *
 * @param string $email Emailova adresa klienta, jehoz faktury se maji prohledat.
 * @param array $invoice Texty polozek faktury. Texty jsou porovnavany na cely text, case-insensitive.
 * @return bool Pokud polozka existuje, vrati true. Pokud zadna vyhovujici nebyla nalezena, vrati false.
 */
function fapiCheckInvoice($email, $invoice)
{
    global $lockerErrorMessage;

    debug_fapi("fapiCheckInvoice()");

    //Validovat pozadavek  //TODO test na prazdny seznam
    if (empty($invoice)) {
        $lockerErrorMessage = "V nastavení FAPI LOCKER chybí název položky faktury (kód 101).";
        return false;
    }

    $fapiSettings = get_option("lockerOptions");
    $fapiUsername = trim($fapiSettings["username"]);
    $fapiPassword = ($fapiSettings["password"]);

    //Validovat nastaveni lockeru
    if (empty($fapiUsername)) {
        $lockerErrorMessage = "V nastavení FAPI LOCKER chybí jméno uživatele pro komunikaci s FAPI (kód 102).";
        return false;
    }
    if (empty($fapiPassword)) {
        $lockerErrorMessage = "V nastavení FAPI LOCKER chybí heslo pro komunikaci s FAPI (kód 103).";
        return false;
    }

    //Vykonny kod

    require_once("FAPIClient.php");

    $fapi = new FAPIClient($fapiUsername, $fapiPassword, 'http://api.fapi.cz');
    try {
        $client = $fapi->client->search(array('email' => $email));
    } catch (Exception $e) {
        $lockerErrorMessage = "Ke službě FAPI se nelze připojit (kód 001). \n" . $e->getMessage();
        return false;
    }
    if ($fapi->getCode() != 200) {
        $lockerErrorMessage = "Služba FAPI není dostupná (kód 002). \nHTTP status code = ". $fapi->getCode();
        return false;
    }
    if (count($client["clients"]) == 0) {
        $lockerErrorMessage = "Pro e-mail \"".htmlspecialchars($email)."\" neevidujeme žádnou objednávku ani platbu (kód 003 - neznámý email). \nJe emailová adresa správná?";
        return false;
    }
    $client = $client["clients"][0];

    $invoices = $fapi->invoice->search(array('client' => $client['id']));
    if (!$invoices || empty($invoice) || (count($invoices) == 0))
    {
        $lockerErrorMessage = "Pro e-mail \"".htmlspecialchars($email)."\" neregistrujeme žádnou objednávku ani platbu (kód 004 - žádné doklady). \nJe emailová adresa správná?";
        return false;
    }

    foreach ($invoices["invoices"] as $inv) {
        if ($inv["paid"]) {  //zajimaji nas pouze uhrazene
            foreach ($inv["items"] as $item) {
                if (in_array(strtolower($item["name"]), array_map('strtolower', $invoice))) //pro jistotu porovnavat case-insensitive
                    return true;
            }
        }
    }

    //Faktury existuji, v zadne neni pozadovany text
    $lockerErrorMessage = "Pro e-mail \"".htmlspecialchars($email)."\" se nepodařilo nalézt platbu za tento obsah (kód 005).";
    return false;
}

/**
 * Rozdeli string podle vicero oddelovacu.
 *
 * Priklad: $exploded = multiexplode(array(",",".","|",":"),$text);
 *
 * @param $delimiters array of string Pole oddelovacu
 * @param $string string Rozdelovany text
 * @return array Vrati pole rozdelenych textu.
 */
function multiexplode ($delimiters,$string) {

    $ready = str_replace($delimiters, $delimiters[0], $string);
    $launch = explode($delimiters[0], $ready);
    return  $launch;
}

/**
 * Overi, zda $email ma opravneni lockeru k prispevku $postId.
 *
 * Nejprve se provadi kontrola dle vyjmenovanych emailu u prispevku (volba <i>lockerAllowedEmail</i>).
 *
 * Pri neuspechu se pokracuje kontrolou emailu ve FAPI, kdy je dohledavana pro email uhrazena polozka faktury.
 * Musi se jednat o takovou polozku, jejiz text obsahuje text uvedeny u prispevku ve volbe <i>lockerInvoce<i>.
 *
 * @param string $email Email uzivatele, ktery se pokousi pristoupit k obsahu.
 * @param int $postID Unikatni ID kontrolovaneho prispevku.
 * @return bool             Vrati true, pokud je email opravnen pristoupit k obsahu. Jinak vrati false.
 */
function lockerCheckEmail($email, $postID)
{
    global $lockerErrorMessage;

    debug_fapi("lockerCheckEmail($email, $postID)...started");
    //Emaily validovat v lower case. Jako oddelovace akceptovat vice moznosti (carky, konce radek...)

    $email = strtolower($email);
    $getMails = multiexplode(array(",", ";", "\r\n", "\n"), strtolower(get_post_meta($postID, "lockerAllowedEmails", TRUE)));
    if (in_array($email, $getMails)) {
        debug_fapi("lockerCheckEmail($email, $postID) = allowed email");
        return true;
    } else {
        debug_fapi("lockerCheckEmail($email, $postID) = not in allowed emails, verifying in FAPI...");
        if (fapiCheckInvoice($email, get_post_meta($postID, "lockerInvoice", TRUE))) {
            debug_fapi("lockerCheckEmail($email, $postID) = FAPI validated");
            return true;
        } else {
            debug_fapi("lockerCheckEmail($email, $postID) = FAPI validation failed");
            debug_fapi("reason: $lockerErrorMessage");
            return false;
        }
    }
    debug_fapi("lockerCheckEmail($email, $postID) = FALSE unknown / unpredicted reason");
    return false;
}

/**
 * Sestavi URL pro aktualni stranku. Reflektuje protokol, vyhodi vychozi port, jedna-li se o standardni 80ku.
 *
 * @return string URL aktualni stranky
 */
function getCurrentPageURL()
{
    $pageURL = 'http';
    if ($_SERVER["HTTPS"] == "on") {
        $pageURL .= "s";
    }
    $pageURL .= "://";
    if ($_SERVER["SERVER_PORT"] != "80") {
        $pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
    } else {
        $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
    }
    return $pageURL;
}

/**
 * Ziska ID prispevku aktualni URL adresy.
 * @return int identifikator prispevku jako cislo
 */
function getCurrentPostId()
{
    global $wp_rewrite, $wp;

    //Inicializace globalu, ktere vyuziva funkce url_to_postid. Kez by byla jejich potreba nekde popsana! ;)
    if (!$wp_rewrite)
        $wp_rewrite = new WP_Rewrite();
    if (!$wp)
        $wp = new WP();

    //Preloz URL na PostId. Vyuzije se k tomu jak wp_rewrite, tak nastaveni permalinku a pak i par zajimavych chytristik.
    $result = url_to_postid($_SERVER["REQUEST_URI"]);
    if (isset($result) and $result) {
        debug_fapi("currentPostId (real) = ". $result);
        return $result;
    }

    global $wpdb;
    $getSlug = explode("/", $_SERVER['REQUEST_URI']);

    if (($getSlug[count($getSlug) - 1] != "") && (strpos($getSlug[count($getSlug) - 1], "?"))) {
        $slug = $getSlug[count($getSlug) - 1];
    } else {
        $slug = $getSlug[count($getSlug) - 2];
    }

    $sql = "
      SELECT
         ID
      FROM
         $wpdb->posts
      WHERE
        post_name = \"$slug\"
   ";

    debug_fapi("currentPostId (probably) = ". $result);
    return $wpdb->get_var($sql);
}

/**
 * Zkontroluje pritomnost overovaci cookie. Neni-li pritomna, zkusi provest autorizaci emailem prihlaseneho uzivatele.
 * @param $postID int Ciselne ID prispevku ci stranky.
 * @return bool Vrati true, pokud je pristup povolen. Jinak vrati false.
 */
function lockerCheckCookieOrUser($postID)
{
    if (isset($_COOKIE["postLocker"]) && isset($_COOKIE["postLocker"][$postID])
        && $_COOKIE["postLocker"][$postID] == md5(get_bloginfo("url") . $postID . get_the_title($postID))
    ) {
        //TODO verze 2.1 jeste validovala $_COOKIE["postLockerM"][$postID], coz zrychli zneplatneni cookie v pripade chybne autorizace (=pokud o neopravneny pristup), na druhe strane tim ukaze email overeneho uzivatele.
        debug_fapi("lockerCheckCookieOrUser($postID) = cookie OK");
        return true;
    } elseif (is_user_logged_in()) {
        global $current_user;
        get_currentuserinfo();
        debug_fapi("validating current logged in user");

        if (lockerCheckEmail($current_user->user_email, $postID)) {
            debug_fapi("lockerCheckCookieOrUser($postID) = current user OK");
            return true;
        }
    }

    debug_fapi("lockerCheckCookieOrUser($postID) = FALSE");
    return false;

}
/*{
    if ((isset($_COOKIE["postLocker"][$postID]) && ($_COOKIE["postLocker"][$postID] == md5(get_bloginfo("url") . $postID . get_the_title($postID))))) {
        if ((isset($_COOKIE["postLockerM"][$postID]) && (lockerCheckEmail($_COOKIE["postLockerM"][$postID], $postID)))) {
            return true;
        }
    } elseif (is_user_logged_in()) {
        global $current_user;
        get_currentuserinfo();

        if (lockerCheckEmail($current_user->user_email, $postID)) {
            return true;
        }
    }

    return false;

}*/

/**
 * Vrati URL na prispevek/stranku dle ID prispevku
 *
 * @param $postId int Ciselne ID prispevku.
 * @return string Absolutni URL. V pripade neexistence prispevku URL pro site.
 */
function getPostURL($postId) {
    global $wp_rewrite;
    if (!$wp_rewrite)
        $wp_rewrite = new WP_Rewrite();
    $result = get_permalink($postId);
    if (!$result)
        $result = get_site_url($postId);
    return $result;
}

// Zde se obsluhuje pozadavek na odemknuti obsahu dle emailove adresy.
// Pres POST prichazi bud jako "lockerMail", ktery vyplnil uzivatel ve zde vygenerovanem
// formulari. Alternativne muze prijit pres GET jako hodnota "unlock".

error_log("\n"); //pro citelnejsi vypisy v logu
debug_fapi(
    "REQUEST:"
    . " " .(isset($_POST["lockerMail"]) ? "POST(lockerMail)=" . $_POST["lockerMail"] : "POST(lockerMail)=null")
    . " ; " . (isset($_GET["unlock"]) ? "GET(unlock)=" . $_GET["unlock"] : "GET(unlock)=null")
);

unset($emailToValidate);
if (isset($_POST["lockerMail"])) {
    $emailToValidate = $_POST["lockerMail"];
} elseif (isset($_GET["unlock"])) {
    $emailToValidate = $_GET["unlock"];
}

if(isset($emailToValidate) && !empty($emailToValidate)) {
    debug_fapi("validating email \"$emailToValidate\"");

    $postID = getCurrentPostId();
    if (lockerCheckEmail(addslashes($_POST["lockerMail"]), $postID)) {
        // Pristup povolen. Uloz cookie a refreshni stranku.
        //TODO Nebo rovnou generuj povoleny obsah pro usetreni jednoho kolecka? Otazkou je, jak pak dostat cookie do prohlizece klienta - musel by se nejakym separe async callem.
        $lockerHash = md5(get_bloginfo("url") . $postID . get_the_title($postID));
        debug_fapi("setting cookie 'postLocker[$postID]'");
        setcookie("postLocker[" . $postID . "]", $lockerHash, time() + 60 * 60 * 24 * 90, "/");
//        setcookie("postLockerM[" . $postID . "]", $_POST["lockerMail"], time() + 60 * 60 * 24 * 90, "/"); //TODO toto je z 2.1 a je to bezpecnostni dira
        //adresa pro presmerovani
        $redirectUrl = getPostURL($postID);
        debug_fapi("redirecting to " . $redirectUrl);
        header("location:" . $redirectUrl);
        status_header(302);
        //Ukonci nasledne generovani stranky.
        debug_fapi("exit");
        exit;
    } else {
        //Pokud neni napr. z validace platby dostupna konkretnejsi zprava, pouzij vychozi.
        if (!$lockerErrorMessage)
            $lockerErrorMessage = "Tento obsah prozatím nemáte zakoupený (kód 100). \nPro přístup je nutné obsah odemknout pomocí oprávněného e-mailu nebo zakoupit.";
    }
}

/**
 * Vrati do HTML zformatovanou zpravu z globalni $lockerErrorMessage v DIVu nebo prazdny text. Ve zprave prevede znaky
 * noveho radku na BR.
 *
 * @return string Zprava v HTML nebo nic.
 */
function lockerGetErrorMessageAsHTML()
{
    global $lockerErrorMessage;

    if (isset($lockerErrorMessage) && !empty($lockerErrorMessage)) {
        return "<div class='fapi_locker_error lockerError'>" . wpautop($lockerErrorMessage) . "</div>\n";
    } else return "";
}

/**
 * Hook zaveseny na generovani obsahu. Pokud se nepodari overit, ze obsah ma byt dostupny, nahradi obsah za zpravu z lockeru
 * o zakazanem obsahu a nutnosti obsah odemknout emailem nebo zakoupit.
 * @param $content string Skutecny obsah stranky.
 * @return string Novy obsah stranky - puvodni ci info o zakazanem pristupu.
 */
function postLocker($content)
{
    global $post, $lockerErrorMessage, $emailToValidate;

    if ($GLOBALS['post']->ID == "") {
        $postID = $GLOBALS['post']->ID;
    } else {
        $postID = $post->ID;
    }

    if (get_post_meta($postID, "lockerActive", TRUE) == '1') {
        debug_fapi("postLocker($postID) = locker ACTIVE");

        if (lockerCheckCookieOrUser($postID)) {
            debug_fapi("postLocker($postID) = returning CONTENT");
            return $content;
        }
//        add_filter('the_excerpt', 'postLockerExcerpt');

        $lockerContent = "";

        // Pro generovani prodejniho formulare potrebujeme funkcni pristup na FAPI.
        if (!fapiCheckCredentials()) {
            debug_fapi("postLocker($postID) = returning LOCKER - FAPI not connected");
            $lockerContent .= lockerGetErrorMessageAsHTML();
            return $lockerContent;
        }

        $fapiSettings = get_option("lockerOptions");
        $fapi = new FAPIClient($fapiSettings["username"], $fapiSettings["password"]);
        $fapiForm = $fapi->form->get(get_post_meta($postID, "lockerForm", TRUE)); //TODO hodila by se tu kontrola, ze form existuje (nekdo ho mohl prejmenovat/smaznout...)

        // Predcachovat styl pro tlacitka.
        $lockerButtonStyleHtml =
            (isset($fapiSettings["buttonBackground"]) && !empty($fapiSettings["buttonBackground"]) || isset($fapiSettings["buttonColor"]) && !empty($fapiSettings["buttonColor"]))
                ? (
                "style=\""
                . (isset($fapiSettings["buttonBackground"]) && !empty($fapiSettings["buttonBackground"]) ? "background-color:#" . $fapiSettings["buttonBackground"] . ";" : "")
                . ""
                . (isset($fapiSettings["buttonColor"]) && !empty($fapiSettings["buttonColor"]) ? "color:#" . $fapiSettings["buttonColor"] . "" : "")
                . "\""
            ) : "";

        // Pokud je evidovana chyba z vyhodnoceni, vypis ji.
        $lockerContent .= lockerGetErrorMessageAsHTML();

        $lockerContent .= "<div id=\"lockerBox\" class=\"" . ($fapiSettings["border"] == 1 ? "border" : "") . "\"><div id=\"lockerBoxInner\">";

        // Locker funguje pouze pro stranky a prispevky. Pro stranky vyhledavani a obsahu pouzit zkracenou verzi.
        if (!(is_single() || is_page())) {
            $lockerContent .= stripslashes(get_post_meta($postID, "lockerNotBought", TRUE))
                . "\n<br />Akci můžete provést na <a href=\"". getPostURL($postID) ."\">stránce s obsahem</a>.";
        } else {
            $lockerContent .= stripslashes(wpautop(get_post_meta($postID, "lockerNotBought", TRUE)));
            // Generuj odemykaci FORM podle emailu.
            $lockerContent .= "<br />";
            $lockerContent .= "<div id=\"lockerCheckMail\">
 	 	<form action=\"\" method=\"post\">
 	 	<label for=\"lockerMail\">Zadejte e-mail</label>
 	 	<input type=\"text\" name=\"lockerMail\" id=\"lockerMail\" "
                . (isset($emailToValidate) ? "value=\"".htmlspecialchars($emailToValidate)."\"" : "")
                ." />
 	 	<input type=\"submit\" value=\"Odemknout\" " . $lockerButtonStyleHtml . " />
 	 	</form>
 	 	</div>";
            $lockerContent .= "<input type=\"button\" id=\"lockerCheckMailButton\" value=\"Mám koupeno, chci odemknout obsah\" ". $lockerButtonStyleHtml ." />";
            // Generuj prodejni form.
            $lockerContent .= "<br />";
            $lockerContent .= "<input type=\"button\" id=\"lockerBuyButton\" value=\"" . get_post_meta($postID, "lockerButton", TRUE) . "\" ". $lockerButtonStyleHtml ." />";
            $lockerContent .= "<div id=\"lockerForm\">" . $fapiForm["html_code"] . "</div>";
            $lockerContent .= "<a id=\"fapiLocker\" href=\"http://fapi.cz/fapi-locker/\" target=\"_blank\"><strong>FAPI</strong> Locker</a>";
        }
        $lockerContent .= "</div></div>";

        debug_fapi("postLocker($postID) = returning LOCKER");
        return $lockerContent;

    }
    debug_fapi("postLocker(ORIGINAL_CONTENT) = locker INACTIVE");
    return $content;
}
add_filter('the_content', 'postLocker', 100);


function postLockerExcerpt($content)
{
    global $post;

    if ($GLOBALS['post']->ID == "") {
        $postID = $GLOBALS['post']->ID;
    } else {
        $postID = $post->ID;
    }
    if (empty($post->post_excerpt)) {
        return $content;
    } else {
        return $post->post_excerpt . "<br /><br />";
    }

}
add_filter('get_the_excerpt', 'postLocker', 100);

?>