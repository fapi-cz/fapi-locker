# FAPI locker

FAPI locker je plugin pro Wordpress. Umoznuje ridit pristup ke strankam a prispevkum bloku na zaklade uhrazenych faktur ve [FAPI](https://fapi.cz).

Instalace se provadi standardnim postupem pro instalaci pluginu.

Po aktivaci pluginu je nejprve potreba nastavit komunikaci s FAPI v `Settings -> FAPI Locker`.
Nasledne je mozne u prispevku a stranek aktivovat FAPI Locker a nastavit jeho parametry pro konkretni obsah.

Oficialni posledni release a dokumentace jsou dostupne na https://fapi.cz/fapi-locker/.

# Changelog

### 2.2
- Plugin je plne funkcni oproti FAPI z 2015-09-28. Opraveny zname bugy tykajici se nefunkcnosti pluginu na nekterych webech. Celkove odladeni, refactoring.
- Doplneny kontroly chybnych uzivatelskych vstupu a chyb pri komunikaci s FAPI (chybejici prihlasovaci udaje, neplatne prihlasovaci udaje, nedostupnost FAPI, neocekavane odpovedi FAPI).
- Rozsireny chybove a napovedne hlasky, aby uzivatel ve frontendu vedel, co je spatne, pripadne co ma udelat. Pridany prokliky do prislusnych nastaveni, pokud maji smysl.
- Pridana ochrana prispevku a stranek pri vyhledavani/vylistovani. Je-li dany obsah chraneny, vypise se misto prispevku zjednodusena verze informace o zamceni s linkem na obsah, kde je mozne si obsah zpristupnit.
- Pridany funkce z verze 1.2:
    - podpora ladeni do souboru na serveru (`wp-content/debug.log`) z verze 1.2.
    - Opravena chyba v iteraci dokladu.
    - Pridana kontrola, zda doklad byl skutecne uhrazen.
    - Kontrola povolenych `AllowedEmail` emailu nezavisle na velikosti pismen.
    - Povolene emaily `AllowedEmail` je mozne psat jednotlive na radkz, oddelovat je carkou, strednikem a toto libovolne kombinovat.
- Upravy a zjednoduseni CSS stylovani.
- Upravena hlavicka pluginu.
- Pridany soubory s popisem pluginu.


### 2.1
- Tesnejsi integrace s FAPI. Polozky faktury a formular se vybira z predvyplneneho seznamu nacteneho primo z FAPI.
- V rade pripadu selhava funkcnost. Clanky jsou bud i pres zamceni pristupne nebo naopak je nelze odemknout.
- Podpora CSS stylovani.

### 1.2
- Detailnejsi chybove zpravy: komunikace s FAPI, chybna konfigurace uzivatelem
- Vyhledani textu na fakture je nove nezavisle na velikosti pismen
- Opravena chyba v iteraci dokladu.
- Pridana kontrola, zda doklad byl skutecne uhrazen.
- Kontrola emailu nezavisle na velikosti pismen.
- @AllowedEmail je mozne psat jednotlive po radcich, oddelovat je carkou, strednikem

### 1.1
- Opravy ve validacnich rutinach.
- Zprava o nepristupnem obsahu nove ve formatu (chybova zprava z validace)+@lockerNotBought+(email validacni FORM)+(nakupni FAPI FORM).
- Chybova zprava z validace se korektne zobrazuje pri neuspene kontrole emailu. Rozlisuje pricinu. Ma CSS styl "fapi_locker_error" pro stylovani.
- Pridana podpora pro debugging.
- Vstupni email je ve vsech vystupech html-escapovani.
- Pri chybe validace je posledni zadany email vlozen do INPUTu pro email (oprava preklepu ap.).
- Hook pro skryvani prispevku je zarazen na konec fronty hooku. Napr. pri pouziti vizualniho editoru MioWebu (a jinych) tyto pregenerovali zpravu o zamceni.

# Chybove zpravy

##### `Tento obsah prozatím nemáte zakoupený! (kód 100)`

Neurcita chyba. Pouzije se jako vychozi text, pokud selze overeni emailu a nepodarilo se zjistit presnou pricinu.

##### `V nastavení FAPI LOCKER chybí název položky faktury (kód 101)."`

V konfiguraci lockeru u prispevku chybi text, ktery se ma vyhledat v polozce faktury.

##### `V nastavení FAPI LOCKER chybí jméno uživatele (kód 102).`

Chyba v konfiguraci. Neni zadany login do FAPI (Settings->Locker).

##### `V nastavení FAPI LOCKER chybí heslo (kód 103).`

Chyba v konfiguraci. Neni zadane heslo do FAPI (Settings->Locker).

##### `Ke službě FAPI se nelze připojit (kód 001). \n" . $e->getMessage()`

Chyba komunikace s FAPI. Nedostupnost, chybne prihlasovaci udaje. V detailu pripojena konkretni zprava z FAPI.

##### `Služba FAPI není dostupná (kód 002). \nHTTP status code = ". $fapi->getCode()`

HTTP pozadave na FAPI vratil jiny status kod nez 200. Patrne chyba v API ci zmena v API nekompatibilni s lockerem

##### `Pro e-mail \"".htmlspecialchars($email)."\" neregistrujeme žádnou platbu (kód 003 - neznámý email). Je emailová adresa správná?"`

Pro email neni ve FAPI registrovany zadny klient. Tj. pro email neexistuje ani objednavka.

##### `Pro e-mail \"".htmlspecialchars($email)."\" neregistrujeme žádnou objednávku (kód 004 - žádné doklady). Je emailová adresa správná?"`

Email je FAPI nalezen, ale neexistuje pro nej zadna faktura/doklad.

##### `Pro e-mail \"".htmlspecialchars($email)."\" neregistrujeme platbu pro tento chráněný obsah (kód 005)."`

Pro email existují doklady, ale žádný uhrazený neobsahuje požadovaný text v položce faktury.
