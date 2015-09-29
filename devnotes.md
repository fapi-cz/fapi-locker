# Jak to funguje uvnitr

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


# Ostatni
- Emaily z @lockerAlloweEmails se kontroluji na presnou shodu, tj. velikost pismen rozhoduje.
  (Validace pro FAPI je case-insensitive.)
- Pri zapnuti WP_DEBUG ci WP_DEBUG_LOG se generuji zpravi s prefixem "FAPI locker" (/wp-content/debug.log).

# TODO
- Usetrit kolecko s refreshem stranky a po uspecne validaci emailu rovnou propustit puvodni obsah. Cookie poslat asynchronne.
- Generovat FAPI form asychronne, stejne se schovany za tlacitkem. U kratkych stranek prodluzuje zobrazeni stranky
  na dvojnasobek (cca 400ms).
- Vytahnout text "Již mám obsah koupen, chci si ho prohlédnout" do options pluginu.
- Vytahnout chybove hlasky do konstant, pripadne pro preklady.
- Pridat zruseni overeni nebo checkbox pro "verejne pocitace" nastavujici kratsi platnost cookie.
- Proverit funkcnost upgradu pluginu z predchozich verzi.
- Proverit moznost plugin odinstalovat.
