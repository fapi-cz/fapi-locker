<?php
/*
Plugin Name: FAPI Locker
Plugin URI: http://www.fapi.cz
Description: Zamykání článků
Version: 2.1
Author: Effecto
Author URI: http://www.effecto.cz
License: 
*/

/* IMPORT verze 2.1, nefunkcni */

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

    add_settings_section("lockerTheme", "Nastavení vzhledu", "lockerOptionsSection", "locker");
    add_settings_field('lockerSettingsBorder', 'Ohraničení formuláře', 'lockerSettingsBorder', 'locker', 'lockerMain');
    add_settings_field('lockerSettingsButtonBackground', 'Barva pozadí tlačítka', 'lockerSettingsButtonBackground', 'locker', 'lockerMain');
    add_settings_field('lockerSettingsButtonColor', 'Barva písma tlačítka', 'lockerSettingsButtonColor', 'locker', 'lockerMain');

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
    delete_post_meta_by_key('lockerForm');
    delete_post_meta_by_key('lockerAllowedEmails');
}

function lockerOptionsSection()
{
    // section title
}

function lockerFieldsUsername()
{
    $options = get_option('lockerOptions');
    echo "<input id='lockerSettingsUsername' name='lockerOptions[username]' size='40' type='text' value='" . (isset($options["username"]) ? $options["username"] : "") . "' />";
}

function lockerFieldsPassword()
{
    $options = get_option('lockerOptions');
    echo "<input id='lockerSettingsPassword' name='lockerOptions[password]' size='40' type='password' value='" . (isset($options["password"]) ? $options["password"] : "") . "' />";
}


function lockerSettingsBorder()
{
    $options = get_option('lockerOptions');
    echo "<select name=\"lockerOptions[border]\"><option value=\"0\">Bez ohraničení</option><option value=\"1\" " . (isset($options["border"]) && $options["border"] == 1 ? "selected" : "") . ">S ohraničením</option></select>";
}


function lockerSettingsButtonBackground()
{
    $options = get_option('lockerOptions');
    echo "<input id='lockerOptionsButtonBackground' name='lockerOptions[buttonBackground]' size='40' type='text' value='" . (isset($options["buttonBackground"]) ? $options["buttonBackground"] : "") . "' />";
}

function lockerSettingsButtonColor()
{
    $options = get_option('lockerOptions');
    echo "<input id='lockerOptionsButtonColor' name='lockerOptions[buttonColor]' size='40' type='text' value='" . (isset($options["buttonColor"]) ? $options["buttonColor"] : "") . "' />";
}


function lockerOptions()
{
    echo "
<div class=\"wrap\">
	" . screen_icon() . "
	<h2>Locker: Nastavení FAPI údajů</h2>
	<form method=\"post\" action=\"options.php\"> 
		" . (checkCredentials() ? "" : "<br /><strong style=\"font-size:15px;color:red;\">Nepodařilo se připojit k FAPI. Zkontrolujte prosím své přihlašovací údaje.</strong>") . "
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
    wp_register_script('colorPicker', plugins_url('/libs/colorpicker/js/colpick.js', __FILE__), array('jquery'), '1.0', true);
    wp_enqueue_script('colorPicker');
    wp_register_style('colorPickerCSS', plugins_url('/libs/colorpicker/css/colpick.css', __FILE__), array(), '1.0');
    wp_enqueue_style('colorPickerCSS');
    wp_register_style('lockerBackendCSS', plugins_url('/css/backend.css', __FILE__), array(), '1.0');
    wp_enqueue_style('lockerBackendCSS');
}

add_action('admin_enqueue_scripts', 'lockerInit');


function lockerMetaOutput()
{
    global $post;
    $values = get_post_custom($post->ID);

    require_once(plugin_dir_path(__FILE__) . "FAPIClient.php");

    if (checkCredentials()) {

        $fapiSettings = get_option("lockerOptions");
        $fapi = new FAPIClient($fapiSettings["username"], $fapiSettings["password"]);

        ?>

        <div id="lockerMeta">

            <?php
            wp_nonce_field('my_meta_box_nonce', 'meta_box_nonce');
            ?>

            <label for="active">Status lockeru</label>
            <select id="active" name="active">
                <option value="0">Neaktivní</option>
                <option value="1" <?php if (isset($values["lockerActive"][0]) && $values["lockerActive"][0] == 1) {
                    echo "selected";
                } ?>>Aktivní
                </option>
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
                <textarea name="allowedEmails"
                          id="allowedEmails"><?php echo "" . (isset($values["lockerAllowedEmails"][0]) ? $values["lockerAllowedEmails"][0] : ""); ?></textarea><br/>
                Při zadávání více emailů je oddělujte čárkou.

                <div class="clear"></div>
                <br/>
                <a href="<?php echo get_admin_url(); ?>/options-general.php?page=options.php" target="_blank">Nastavení
                    barvy a ohraničení boxu</a>
                <br/>
            </div>

        </div>
        <?php
    } else {
        echo "<br /><strong>Pro vypsání seznamu formulářů je nutné zadat vaše přihlašovací údaje do FAPI. Tuto akci provedete pouze jednou.</strong><br /><br />Přihlašovací údaje můžete zadat <a href=\"" . admin_url() . "options-general.php?page=options.php\">zde</a>.";
    }
}

add_action('save_post', 'lockerMetaSave');
function lockerMetaSave($post_id)
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['meta_box_nonce']) || !wp_verify_nonce($_POST['meta_box_nonce'], 'my_meta_box_nonce')) return;
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


function checkCredentials()
{

    $fapiSettings = get_option("lockerOptions");
    if (($fapiSettings["username"] == "") OR ($fapiSettings["password"] == "")) {
        return false;
    } else {
        require_once(plugin_dir_path(__FILE__) . "FAPIClient.php");
        try {
            $fapi = new FAPIClient($fapiSettings["username"], $fapiSettings["password"]);
            $fapi->checkConnection();
            return true;
        } catch (FAPIClient_UnauthorizedException $e) {
            return false;
        }

    }

}


function connectFapi($email, $invoice)
{
    $fapiSettings = get_option("lockerOptions");
    $fapiUsername = $fapiSettings["username"];
    $fapiPassword = $fapiSettings["password"];

    require_once("FAPIClient.php");

    $fapi = new FAPIClient($fapiUsername, $fapiPassword, 'http://api.fapi.cz');
    $client = $fapi->client->search(array('email' => $email));
    if (empty($client["clients"])) {
        return false;
    }

    $client = $client["clients"][0];
    if (!$client)
        return false;

    $invoices = $fapi->invoice->search(array('client' => $client['id']));
    if (!$invoices)
        return false;


    if (empty($invoice)) {
        $invoice = array();
    } else {

        foreach ($invoices["invoices"] as $inv) {
            foreach ($inv["items"] as $item) {
                if (in_array($item["name"], $invoice))
                    return true;
            }
        }
    }


    return false;
}


function lockerCheckEmail($email, $postID)
{
    $getMails = explode(",", get_post_meta($postID, "lockerAllowedEmails", TRUE));

    if (in_array($email, $getMails)) {
        return true;
    } else {
        if (connectFapi($email, get_post_meta($postID, "lockerInvoice", TRUE))) {
            return true;
        }
    }

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

function get_the_post_id()
{

    if (isset($_GET["p"])) {
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

}

if (isset($_POST["lockerMail"])) {
    $postID = get_the_post_id();
    if (lockerCheckEmail(addslashes($_POST["lockerMail"]), $postID)) {
        $lockerHash = md5(get_bloginfo("url") . $postID . get_the_title($postID));
        setcookie("postLocker[" . $postID . "]", $lockerHash, time() + 60 * 60 * 24 * 90, "/");
        setcookie("postLockerM[" . $postID . "]", $_POST["lockerMail"], time() + 60 * 60 * 24 * 90, "/");
        $wp_rewrite = new WP_Rewrite();
        //header("location:".get_permalink($postID));
    } else {
        $wp_rewrite = new WP_Rewrite();
        //echo "<script type=\"text/javascript\">window.location.replace(\"".get_permalink($postID)."?err=1\");</script>";
    }
} elseif (isset($_GET["unlock"])) {
    $postID = get_the_post_id();
    if (lockerCheckEmail(addslashes($_GET["unlock"]), $postID)) {
        $lockerHash = md5(get_bloginfo("url") . $postID . get_the_title($postID));
        setcookie("postLocker[" . $postID . "]", $lockerHash, time() + 60 * 60 * 24 * 90, "/");
        $wp_rewrite = new WP_Rewrite();
        header("location:" . get_permalink($postID));
    } else {
        $wp_rewrite = new WP_Rewrite();
        echo "<script type=\"text/javascript\">window.location.replace(\"" . get_permalink($postID) . "?err=1\");</script>";
    }
}


function postLocker($content)
{

    global $post;

    if ($GLOBALS['post']->ID == "") {
        $postID = $GLOBALS['post']->ID;
    } else {
        $postID = $post->ID;
    }

    if (get_post_meta($postID, "lockerActive", TRUE) == '1') {

        if (lockerCheckCookie($postID)) {
            return $content;
        }


        if (checkCredentials()) {
            $fapiSettings = get_option("lockerOptions");
            $fapi = new FAPIClient($fapiSettings["username"], $fapiSettings["password"]);
            $form = $fapi->form->get(get_post_meta($postID, "lockerForm", TRUE));

            if (get_post_field("post_excerpt", $postID) != "") {
                $lockerContent = wpautop(get_post_field('post_excerpt', $postID));
            } else {
                $lockerContent = mb_substr(get_the_content(), 0, 300) . "...";
            }
            if (isset($_GET["err"])) {
                $lockerContent .= "<div class=\"lockerError\">Zadali jste neplatný email. Nebo vaše objednávka není zaplacena</div>";
            }
            $lockerContent .= "<div id=\"lockerBox\" class=\"" . ($fapiSettings["border"] == 1 ? "border" : "") . "\"><div id=\"lockerBoxInner\">";
            $lockerContent .= stripslashes(wpautop(get_post_meta($postID, "lockerNotBought", TRUE)));
            $lockerContent .= "<input type=\"button\" id=\"lockerBuyButton\" value=\"" . get_post_meta($postID, "lockerButton", TRUE) . "\" style=\"" . (isset($fapiSettings["buttonBackground"]) ? "background-color:#" . $fapiSettings["buttonBackground"] . ";" : "") . "" . (isset($fapiSettings["buttonColor"]) ? "color:#" . $fapiSettings["buttonColor"] . "" : "") . "\" />";
            $lockerContent .= "<div id=\"lockerForm\">" . $form["html_code"] . "</div>";
            $lockerContent .= "<div id=\"lockerCheckMail\">
 	 	<form action=\"\" method=\"post\">
 	 	<label for=\"lockerMail\">Zadejte e-mail</label>
 	 	<input type=\"text\" name=\"lockerMail\" id=\"lockerMail\" />
 	 	<input type=\"submit\" value=\"Otevřít obsah\" style=\"" . (isset($fapiSettings["buttonBackground"]) ? "background-color:#" . $fapiSettings["buttonBackground"] . ";" : "") . "" . (isset($fapiSettings["buttonColor"]) ? "color:#" . $fapiSettings["buttonColor"] . "" : "") . "\" />
 	 	</form>
 	 	</div>";
            $lockerContent .= "<br /><br /> <a href=\"#\" id=\"lockerCheckMailButton\">Již mám obsah koupen, chci si ho prohlédnout</a>";

            $lockerContent .= "<a id=\"fapiLocker\" href=\"http://fapi.cz/fapi-locker/\" target=\"_blank\"><strong>FAPI</strong> Locker</a></div></div>";
        } else {
            $lockerContent = "Nepodařilo se přihlásit do systému FAPI.";
        }

        return $lockerContent;

    }
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
    if (empty($post->post_excerpt)) {
        return "no excerpt";
    } else {
        return $post->post_excerpt . "<br /><br />";
    }

}


add_filter('get_the_excerpt', 'postLocker', 100);
add_filter('the_content', 'postLocker', 100);

/* // post functions */
?>