<?php
/*
Plugin Name: FAPI Locker
Plugin URI: http://knowledge.firma20.cz/knowledgebase/fapi-fapi-locker/
Description: Zamykání článků s ověřením platby přes FAPI.
Version: 1.2
Author: Effecto + Jakub Koňas <jakub.konas@gmail.com>
Author URI: http://www.fapi.cz
License: 
*/

/*

CHANGE LOG
----------
1.2
    - Detailnejsi chybove zpravy: komunikace s FAPI, chybna konfigurace uzivatelem
    - Vyhledani textu na fakture je nove nezavisle na velikosti pismen
    - Opravena chyba v iteraci dokladu.
    - Pridana kontrola, zda doklad byl skutecne uhrazen.
    - Kontrola emailu nezavisle na velikosti pismen.
    - @AllowedEmail je mozne psat jednotlive po radcich, oddelovat je carkou, strednikem

1.1
    - Opravy ve validacnich rutinach.
    - Zprava o nepristupnem obsahu nove ve formatu (chybova zprava z validace)+@lockerNotBought+(email validacni FORM)+(nakupni FAPI FORM).
    - Chybova zprava z validace se korektne zobrazuje pri neuspene kontrole emailu. Rozlisuje pricinu. Ma CSS styl "fapi_locker_error" pro stylovani.
    - Pridana podpora pro debugging.
    - Vstupni email je ve vsech vystupech html-escapovani.
    - Pri chybe validace je posledni zadany email vlozen do INPUTu pro email (oprava preklepu ap.).
    - Hook pro skryvani prispevku je zarazen na konec fronty hooku. Napr. pri pouziti vizualniho editoru MioWebu (a jinych)
      tyto pregenerovali zpravu o zamceni.


CHYBOVE ZPRAVY
--------------
"Tento obsah prozatím nemáte zakoupený! (kód 100)"
    - Neurcita chyba. Pouzije se jako vychozi text, pokud selze overeni emailu a nepodarilo se zjistit presnou pricinu.
"V nastavení FAPI LOCKER chybí název položky faktury (kód 101)."
    - V konfiguraci lockeru u prispevku chybi text, ktery se ma vyhledat v polozce faktury.
"V nastavení FAPI LOCKER chybí jméno uživatele (kód 102)."
    - Chyba v konfiguraci. Neni zadany login do FAPI (Settings->Locker).
"V nastavení FAPI LOCKER chybí heslo (kód 103)."
    - Chyba v konfiguraci. Neni zadane heslo do FAPI (Settings->Locker).

"Ke službě FAPI se nelze připojit (kód 001). \n" . $e->getMessage()
    - Chyba komunikace s FAPI. Nedostupnost, chybne prihlasovaci udaje. V detailu pripojena konkretni zprava z FAPI.
"Služba FAPI není dostupná (kód 002). \nHTTP status code = ". $fapi->getCode()
    - HTTP pozadave na FAPI vratil jiny status kod nez 200. Patrne chyba v API ci zmena v API nekompatibilni s lockerem
"Pro e-mail \"".htmlspecialchars($email)."\" neregistrujeme žádnou platbu (kód 003 - neznámý email). Je emailová adresa správná?"
    - Pro email neni ve FAPI registrovany zadny klient. Tj. pro email neexistuje ani objednavka.
"Pro e-mail \"".htmlspecialchars($email)."\" neregistrujeme žádnou objednávku (kód 004 - žádné doklady). Je emailová adresa správná?"
    - Email je FAPI nalezen, ale neexistuje pro nej zadna faktura/doklad.
"Pro e-mail \"".htmlspecialchars($email)."\" neregistrujeme platbu pro tento chráněný obsah (kód 005)."
    - Pro email existují doklady, ale žádný uhrazený neobsahuje požadovaný text v položce faktury.



JAK TO FUNGUJE
--------------

Po instalaci pluginu si uzivatel u prispevku muze aktivovat FAPIlocker. V nastaveni zadava volby, ktere jsou ulozene
jako meta options u prispevku. Konkretne:
    - lockerAllowedEmails = carkou oddeleny seznam emailu, ktere jsou opravneny cist obsah
    - lockerInvoice = podtext polozky na fakture ve FAPI, ktera podminuje opravneni cist obsah
    - lockerActive = zda je locker pro dany prispevek aktivni
    - lockerNotBought = html text, ktery se vlozi jako informace o tom, ze cteni obsahu neni povoleno
    - lockerButton = prosty text, ktery se vypise na tlacitku pro koupi obsahu prostrednictvim FAPI formu
    - lockerJavascript = JavaScript prodejniho formulare ve FAPI pro nakup obsahu, soucasti formu by mela byt polozka obsahujici z parametru "lockerInvoice"

Pri pristupu k prispevku, u ktereho je @lockerActive=1, je kontrolovana cookie "postLocker[$PostId]". Jeji pritomnost a
spravna hodnota propusti puvodni obsah prispevku.
Pokud validace cookie neprojde, zkusi se validace oproti emailu emailu obdrzeneho v $_POST["lockerMail"] nebo $_GET["unlock"]
oproti FAPI. Najde-li se faktura obsahujici polozku s podtextem @lockerInvoice, ulozi opravnujici cookie na 3 mesice
a refreshne se stranka.
Pokud email v requestu neprisel, nahradi se obsah prispevku textem @lockerNotBought, dale je pridan FORM pro zpristupneni
obsahu zadanim opravnujiciho emailu (dostupny rozkliknutim odkazu) a nakonec se prida se tlacitko pro nakup
s textem @lockerButton, za kterym je schovany FAPI formular z @lockerJavaScript.

Pri pokusu o validaci emailu, ktera selze, vypise konktretni pricinu (nedostupnost FAPI, klienta ve FAPI, platby za polozku...).
Chybova zprava ma CSS tridu "fapi_locker_error" pro pripadnou stylizaci.


Ostatni:
    - Emaily z @lockerAlloweEmails se kontroluji na presnou shodu, tj. velikost pismen rozhoduje.
      (Validace pro FAPI je case-insensitive.)
    - Pri zapnuti WP_DEBUG ci WP_DEBUG_LOG se generuji zpravi s prefixem "FAPI locker" (/wp-content/debug.log).

TODO:
    - Usetrit kolecko s refreshem stranky a po uspecne validaci emailu rovnou propustit puvodni obsah. Cookie poslat asynchronne.
    - Generovat FAPI form asychronne, stejne se schovany za tlacitkem. U kratkych stranek prodluzuje zobrazeni stranky
      na dvojnasobek (cca 400ms).
    - Vytahnout text "Již mám obsah koupen, chci si ho prohlédnout" do options pluginu.
    - Vytahnout chybove hlasky do konstant, pripadne pro preklady.
    - Pridat zruseni overeni nebo checkbox pro "verejne pocitace" nastavujici kratsi platnost cookie.

 * */


function debug_fapi($message)
{
    error_log("FAPI locker -- " . $message);
}

add_action('admin_menu', 'lockerMenu');

function lockerMenu()
{

    add_options_page('Nastavení lockeru', 'Locker', 'manage_options', 'options.php', 'lockerOptions');
}

/* plugin options */

add_action('admin_init', 'register_pluginSettings');

function register_pluginSettings()
{
    register_setting('lockerOptions', 'lockerOptions');
    add_settings_section("lockerMain", "Nastavení údajů", "lockerOptionsSection", "locker");
    add_settings_field('lockerSettingsUsername', 'Přihlašovací jméno', 'lockerFieldsUsername', 'locker', 'lockerMain');
    add_settings_field('lockerSettingsPassword', 'Heslo', 'lockerFieldsPassword', 'locker', 'lockerMain');

}

register_deactivation_hook(__FILE__, 'lockerDeactivate');

function lockerDeactivate()
{
    //
}

register_uninstall_hook(__FILE__, 'lockerUninstall');

function lockerUninstall()
{
    if (!defined('WP_UNINSTALL_PLUGIN'))
        exit ();

    delete_option('lockerOptions');
    delete_post_meta_by_key('lockerActive');
    delete_post_meta_by_key('lockerNotBought');
    delete_post_meta_by_key('lockerButton');
    delete_post_meta_by_key('lockerInvoice');
    delete_post_meta_by_key('lockerAllowedEmails');
}

function lockerOptionsSection()
{
    // section title
}

function lockerFieldsUsername()
{
    $options = get_option('lockerOptions');
    echo "<input id='lockerSettingsUsername' name='lockerOptions[username]' size='40' type='text' value='{$options['username']}' />";
}

function lockerFieldsPassword()
{
    $options = get_option('lockerOptions');
    echo "<input id='lockerSettingsPassword' name='lockerOptions[password]' size='40' type='password' value='{$options['password']}' />";
}


function lockerOptions()
{
    echo "
<div class=\"wrap\">
	" . screen_icon() . "
	<h2>Locker: Nastavení FAPI údajů</h2>
	<form method=\"post\" action=\"options.php\"> 
	<p>Pomocí těchto údajů se plugin přihlásí do systému FAPI a získá potřebné údaje pro kontrolu zaplaceného obsahu</p>
	";
    settings_fields('lockerOptions');
    do_settings_sections('locker');
    submit_button();
    echo "
	</form>
</div>
";
}


/* // plugin options */

/* post meta */

add_action('add_meta_boxes', 'lockerMetaBox');

function lockerMetaBox()
{
    add_meta_box('lockerMeta', 'Locker', 'lockerMetaOutput', 'post', 'normal', 'high');
    add_meta_box('lockerMeta', 'Locker', 'lockerMetaOutput', 'page', 'normal', 'high');

}

function lockerInit()
{
    wp_register_script('lockerBackend', plugins_url('/js/backend.js', __FILE__), array('jquery'), '1.0', true);
    wp_enqueue_script('lockerBackend');

    wp_register_style('lockerBackendCSS', plugins_url('/css/backend.css', __FILE__), array(), '1.0');
    wp_enqueue_style('lockerBackendCSS');
}

add_action('admin_enqueue_scripts', 'lockerInit');


function lockerMetaOutput()
{
    global $post;
    $values = get_post_custom($post->ID);

    ?>

    <div id="lockerMeta">

        <?php
        wp_nonce_field('my_meta_box_nonce', 'meta_box_nonce');
        ?>

        <label for="active">Status lockeru</label>
        <select id="active" name="active">
            <option value="0">Neaktivní</option>
            <option value="1" <?php if ($values["lockerActive"][0] == 1) {
                echo "selected";
            } ?>>Aktivní
            </option>
        </select>

        <div class="clear"></div>

        <div id="activeLocker" <?php if ($values["lockerActive"][0] == 1) {
            echo "style=\"display:block;\"";
        } ?>>

            <label for="not_bought">Text v případě nezakoupení článku</label>
            <?php wp_editor(stripslashes($values["lockerNotBought"][0]), 'not_bought'); ?>

            <div class="clear"></div>

            <label for="button">Text tlačítka</label>
            <input id="button" name="button" value="<?php echo $values["lockerButton"][0]; ?>"/>

            <div class="clear"></div>

            <label for="invoice">Název položky na faktuře</label>
            <input id="invoice" name="invoice" value="<?php echo $values["lockerInvoice"][0]; ?>"/>

            <div class="clear"></div>

            <label for="javascript">JavaScript formuláře</label>
            <textarea name="javascript" id="javascript"><?php echo $values["lockerJavascript"][0]; ?></textarea>

            <div class="clear"></div>

            <label for="allowedEmails">Povolené e-maily</label>
            <textarea name="allowedEmails"
                      id="allowedEmails"><?php echo $values["lockerAllowedEmails"][0]; ?></textarea>

        </div>

    </div>
    <?php
}

add_action('save_post', 'lockerMetaSave');
function lockerMetaSave($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (!isset($_POST['meta_box_nonce']) || !wp_verify_nonce($_POST['meta_box_nonce'], 'my_meta_box_nonce')) return;

    if (!current_user_can('edit_post')) return;

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
        update_post_meta($post_id, 'lockerInvoice', wp_kses($_POST['invoice'], $allowed));
    if (isset($_POST['javascript']))
        update_post_meta($post_id, 'lockerJavascript', $_POST['javascript']);
    if (isset($_POST['allowedEmails']))
        update_post_meta($post_id, 'lockerAllowedEmails', wp_kses($_POST['allowedEmails'], $allowed));
}

/* // post meta */


/* post functions */

add_action('wp_enqueue_scripts', 'postLockerInit');

function postLockerInit()
{

    wp_enqueue_script("jquery");

    wp_register_script('lockerFrontend', plugins_url('/js/frontend.js', __FILE__), array('jquery'), '1.0', true);
    wp_enqueue_script('lockerFrontend');

    wp_register_style('lockerFrontendCSS', plugins_url('/css/frontend.css', __FILE__), array(), '1.0');
    wp_enqueue_style('lockerFrontendCSS');
}


/**
 * Provede kontrolu ve FAPI, zda v nektere fakture klienta s danym $email existuje polozka s textem $invoice.
 *
 * @param string $email Emailova adresa klienta, jehoz faktury se maji prohledat.
 * @param string $invoice Text polozky faktury. Muze se jednat i o cast textu polozky, vice polozek pak muze vest ke splneni funkce.
 * @return bool Pokud polozka existuje, vrati true. Pokud zadna vyhovujici nebyla nalezena, vrati false.
 */
function connectFapi($email, $invoice)
{
    global $lockerErrorMessage;

    //Oriznout prazdne kraje.
    $invoice = trim($invoice);

    //Validovat pozadavek
    if (empty($invoice)) {
        $lockerErrorMessage = "V nastavení FAPI LOCKER chybí název položky faktury (kód 101).";
        return false;
    }

    $fapiSettings = get_option("lockerOptions");
    $fapiUsername = trim($fapiSettings["username"]);
    $fapiPassword = ($fapiSettings["password"]);

    //Validovat nastaveni lockeru
    if (empty($fapiUsername)) {
        $lockerErrorMessage = "V nastavení FAPI LOCKER chybí jméno uživatele (kód 102).";
        return false;
    }
    if (empty($fapiPassword)) {
        $lockerErrorMessage = "V nastavení FAPI LOCKER chybí heslo (kód 103).";
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
        $lockerErrorMessage = "Pro e-mail \"".htmlspecialchars($email)."\" neregistrujeme žádnou platbu (kód 003 - neznámý email). Je emailová adresa správná?";
        return false;
    }
    $client = $client["clients"][0];

    $invoices = $fapi->invoice->search(array('client' => $client['id']));
    if (!$invoices || (count($invoices) == 0))
    {
        $lockerErrorMessage = "Pro e-mail \"".htmlspecialchars($email)."\" neregistrujeme žádnou objednávku (kód 004 - žádné doklady). Je emailová adresa správná?";
        return false;
    }

    foreach ($invoices["invoices"] as $inv) {
        if ($inv["paid"]) {
            foreach ($inv["items"] as $item) {
                if (stristr($item["name"], $invoice))
                    return true;
            }
        }
    }

    //Faktury existuji, v zadne neni pozadovany text
    $lockerErrorMessage = "Pro e-mail \"".htmlspecialchars($email)."\" neregistrujeme platbu pro tento chráněný obsah (kód 005).";
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
 * Overi, zda $email opravneni k prispevku $postId.
 *
 * Nejprve se provadi kontrola dle vyjmenovanych emailu u prispevku (volba <i>lockerAllowedEmail</i>).
 *
 * Pri neuspechu se pokracuje kontrolou emailu ve FAPI, kdy je dohledavana pro email uhrazena polozka faktury.
 * Musi se jednat o takovou polozku, jejiz text obsahuje text uvedeny u prispevku ve volbe <i>lockerInvoce<i> jako poddtext.
 * Nemusi se jednat o plnou shodu.
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
        if (connectFapi($email, get_post_meta($postID, "lockerInvoice", TRUE))) {
            debug_fapi("lockerCheckEmail($email, $postID) = FAPI validated");
            return true;
        } else {
            debug_fapi("lockerCheckEmail($email, $postID) = FAPI validation failed");
            debug_fapi("reason: $lockerErrorMessage");
            return false;
        }
    }
    debug_fapi("lockerCheckEmail($email, $postID) = FALSE unknown unpredicted reason");
    return false;
}

function curPageURL()
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
 * @return int|null|string
 */
function get_the_post_id()
{

    if (isset($_GET["p"]) && $_GET["p"] != "") {
        return intval($_GET["p"]);
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


    return $wpdb->get_var($sql);
}

function lockerCheckCookie($postID)
{
    if (isset($_COOKIE["postLocker"]) && isset($_COOKIE["postLocker"][$postID])
        && $_COOKIE["postLocker"][$postID] == md5(get_bloginfo("url") . $postID . get_the_title($postID))
    ) {
        debug_fapi("lockerCheckCookie($postID) = OK");
        return true;
    } elseif (is_user_logged_in()) {
        global $current_user;
        get_currentuserinfo();
        debug_fapi("validating current logged in user");

        if (lockerCheckEmail($current_user->user_email, $postID)) {
            debug_fapi("lockerCheckCookie($postID) = OK");
            return true;
        }
    }

    debug_fapi("lockerCheckCookie($postID) = FALSE");
    return false;

}


// Zde se obsluhuje pozadavek na odemknuti obsahu dle emailove adresy.
// Pres POST prichazi bud jako "lockerMail", ktery vyplnil uzivatel ve zde vygenerovanem
// formulari. Alternativne muze prijit pres GET jako hodnota "unlock".

debug_fapi(
    "Checking request:"
    . " " .(isset($_POST["lockerMail"]) ? "POST(lockerMail)=" . $_POST["lockerMail"] : "POST(lockerMail)=null")
    . " ; " . (isset($_GET["unlock"]) ? "GET(unlock)=" . $_GET["unlock"] : "GET(unlock)=null")
);

unset($emailToValidate);
if (isset($_POST["lockerMail"])) {
    $emailToValidate = $_POST["lockerMail"];
} elseif (isset($_GET["unlock"])) {
    $emailToValidate = $_GET["unlock"];
}

if(isset($emailToValidate)) {
    debug_fapi("validating email \"$emailToValidate\"");

    $postID = get_the_post_id();
    if (lockerCheckEmail(addslashes($_POST["lockerMail"]), $postID)) {
        // Pristup povolen. Uloz cookie a refreshni stranku.
        //TODO Nebo rovnou generuj povoleny obsah pro usetreni jednoho kolecka? Otazkou je, jak pak dostat cookie do prohlizece klienta - musel by se nejakym separe async callem.
        $lockerHash = md5(get_bloginfo("url") . $postID . get_the_title($postID));
        debug_fapi("setting cookie 'postLocker[$postID]'");
        setcookie("postLocker[" . $postID . "]", $lockerHash, time() + 60 * 60 * 24 * 90, "/");
        //Inicializovat rewrite engine, pokud to neudelal mezitim nekdo jiny. Nutne pro volani get_permalink().
        if (!$wp_rewrite)
          $wp_rewrite = new WP_Rewrite();
        $redirectUrl = get_permalink($postID);
        debug_fapi("redirecting to " . $redirectUrl);
        header("location:" . $redirectUrl);
        status_header(302);
        //Ukonci nasledne generovani stranky.
        debug_fapi("exit");
        exit;
    } else {
        //Pokud neni napr. z validace platby dostupna konkretnejsi zprava, pouzij vychozi.
        if (!$lockerErrorMessage)
            $lockerErrorMessage = "Tento obsah prozatím nemáte zakoupený! (kód 100)";
    }
}

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

        if (lockerCheckCookie($postID)) {
            debug_fapi("postLocker($postID) = returning CONTENT");
            return $content;
        }

        add_filter('the_excerpt', 'postLockerExcerpt');


        $lockerContent = "";
        if (isset($lockerErrorMessage)) {
            $lockerContent .= "<strong class='fapi_locker_error'>" . $lockerErrorMessage . "</strong><br /><br />";
        }

        // Locker funguje pouze pro stranky a prispevky
        if (!(is_single() || is_page())) {
            $lockerContent .= stripslashes(get_post_meta($postID, "lockerNotBought", TRUE));
            //return $Content; //TODO Nebylo by vhodnejsi v takovem pripade puvodni obsah, nez ho slepe bloknout?
        } else {
            $lockerContent .= stripslashes(get_post_meta($postID, "lockerNotBought", TRUE));
            // Generuj odemykaci FORM podle emailu.
            $lockerContent .= "<div id=\"lockerCheckMail\">
 	 	<form action=\"\" method=\"post\">
 	 	<label for=\"lockerMail\">Zadejte e-mail</label>
 	 	<input type=\"text\" name=\"lockerMail\" id=\"lockerMail\" "
            . (isset($emailToValidate) ? "value=\"".htmlspecialchars($emailToValidate)."\"" : "")
            ." />
 	 	<input type=\"submit\" value=\"Odemknout obsah\" />
 	 	</form>
 	 	</div>";
            $lockerContent .= "<br /><br />";
            $lockerContent .= "<a href=\"#\" id=\"lockerCheckMailButton\">Již mám obsah koupen, chci si ho prohlédnout</a>";
            // Generuj prodejni form.
            $lockerContent .= "<br /><br />";
            $lockerContent .= "<input type=\"button\" id=\"lockerBuyButton\" value=\"" . get_post_meta($postID, "lockerButton", TRUE) . "\" />";
            $lockerContent .= "<div id=\"lockerForm\">" . get_post_meta($postID, "lockerJavascript", TRUE) . "</div>";
        }

        debug_fapi("postLocker($postID) = returning LOCKER");
        return $lockerContent;

    }
    debug_fapi("postLocker($content) = locker INACTIVE");
    return $content;
}


function postLockerExcerpt($content)
{
    global $post;

    if ($GLOBALS['post']->ID == "") {
        $postID = $GLOBALS['post']->ID;
    } else {
        $postID = $post->ID;
    }

    return stripslashes(get_post_meta($postID, "lockerNotBought", TRUE));

}

add_filter('the_content', 'postLocker', 100);

?>
