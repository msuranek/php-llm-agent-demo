# PHP LLM Agent Demo

Samostatná ukázka řízeného LLM agenta v PHP bez aplikačního frameworku. Ukazuje, jak nad databází, API nebo aplikačními službami postavit vrstvu, která dostane cíl, zvolí vhodné nástroje a sestaví z nich vícekrokový pracovní postup.

Demo řeší konkrétní provozní problém: **kontrolu skladové zásoby a přípravu návrhu objednávky**. Stejný princip lze zapojit do e-shopu, ERP, CRM, skladového systému nebo interní administrace.

## Co agent udělá

Uživatel nemusí určovat jednotlivé technické kroky. Zadá pouze výsledek, kterého chce dosáhnout:

> Zkontroluj, zda je potřeba doplnit produkt USB-HUB-01, a pokud ano, vytvoř návrh objednávky.

Agent následně:

1. najde produkt a jeho aktuální skladovou zásobu,
2. načte pravidla pro doplňování,
3. deterministicky spočítá doporučené množství,
4. podle výsledku rozhodne, zda je potřeba další akce,
5. před zápisem znovu ověří SKU a přepočítá množství v PHP,
6. vytvoří návrh objednávky, pokud to uživatel výslovně požadoval, doplnění je stále potřeba a zápis je povolený.

```text
cíl uživatele
    ↓
agentní smyčka
    ↓
registr povolených nástrojů
    ├── skladová data
    ├── firemní pravidla
    ├── doménový výpočet
    └── řízená zápisová akce
```

Model objednávku nikdy sám neodešle. Vytvoří pouze lokální návrh pro lidskou kontrolu. Aplikace stále řídí data, výpočty, validaci, oprávnění i provedení změn.

## Proč je to agent, ne pevný workflow

Běžný program má pořadí kroků napsané předem. Tady aplikace poskytne modelu cíl a sadu povolených schopností. Model podle průběžných výsledků rozhoduje, který nástroj použít, zda potřebuje další data, jak reagovat na chybu a kdy je úkol hotový.

```text
cíl → nástroj → výsledek → další rozhodnutí → výsledek → odpověď
```

Jde záměrně o **řízeného agenta**, ne o neomezeně autonomní proces. PHP kód drží bezpečnostní hranice: dostupné nástroje, jejich schémata, oprávnění a maximální počet kol.

## Nástroje v ukázce

| Nástroj | Odpovědnost |
|---|---|
| `find_product` | Vyhledá produkt a vrátí stav skladu a průměrný denní prodej. |
| `get_restock_policy` | Vrátí dodací dobu a požadovanou bezpečnostní zásobu. |
| `calculate_restock` | V PHP vypočítá cílovou zásobu a doporučené množství. |
| `create_purchase_draft` | Znovu ověří produkt a výpočet a uloží návrh pro kontrolu; vyžaduje `--allow-write`. |

Pro demonstrační produkt `USB-HUB-01` jsou vstupy:

```text
aktuální zásoba:       8 ks
průměrný denní prodej: 3 ks
dodací doba:           5 dní
bezpečnostní zásoba:  10 ks
```

Doménový nástroj spočítá cílovou zásobu `25 ks` a doporučí objednat `17 ks`. Výpočet nedělá jazykový model, ale testovatelný PHP kód.

### Ověřený průchod agenta

Zkrácený trace skutečného běhu přes OpenAI API:

```text
find_product           → stock 8, average daily sales 3
get_restock_policy     → lead time 5 days, safety stock 10
calculate_restock      → target stock 25, recommended quantity 17
create_purchase_draft  → draft created for 17 units
final_answer           → no order was placed
```

Každý krok vznikl jako samostatné rozhodnutí modelu podle výsledku předchozího nástroje. Množství návrhu následně znovu ověřil a vypočítal PHP kód na zápisové hranici.

## Jak to zapojit do vlastního softwaru

Demonstrační JSON soubory nahraďte službami, které už vaše aplikace používá. Agentní smyčka přitom může zůstat stejná.

| V tomto demu | Ve skutečné aplikaci |
|---|---|
| `data/inventory.json` | produktové repository, skladová databáze nebo ERP API |
| `data/restock-policies.json` | konfigurace, pravidlová služba nebo nastavení dodavatele |
| `CalculateRestockTool` | existující doménová služba pro plánování zásob |
| `CreatePurchaseDraftTool` | nákupní modul, ERP příkaz nebo schvalovací workflow |
| `ToolRegistry` | whitelist operací dostupných agentovi |
| `--allow-write` | role uživatele, autorizace nebo explicitní schválení |

Typický nástroj je pouze tenký adaptér nad existující aplikační službou:

```php
final class FindProductTool implements Tool
{
    public function __construct(private InventoryRepository $products)
    {
    }

    public function execute(array $arguments): array
    {
        $matches = $this->products->search((string) $arguments['query']);

        return [
            'count' => count($matches),
            'products' => $matches,
        ];
    }

    // name(), definition() a requiresWritePermission()...
}
```

Stejnou architekturu lze použít i jinde:

- **E-shop:** načíst objednávku, ověřit podmínky vrácení a připravit refundaci.
- **CRM:** projít historii zákazníka, vyhodnotit prioritu a vytvořit úkol obchodníkovi.
- **Servis:** najít zařízení a servisní pravidla, vyhodnotit závadu a připravit tiket.
- **Interní systém:** spojit data z více modulů a vytvořit souhrnný report.

Model tedy nenahrazuje doménovou logiku. Rozhoduje, **které povolené schopnosti použít a v jakém pořadí**. Načtení dat, výpočty, autorizace a změny stavu zůstávají v běžném aplikačním kódu.

## Spuštění v Dockeru

Docker varianta nevyžaduje lokální instalaci PHP.

```bash
cp .env.example .env
# Do .env vložte OPENAI_API_KEY a případně změňte OPENAI_MODEL.

docker compose build
```

Kontrola zásoby bez zápisu:

```bash
docker compose run --rm agent --trace \
  "Check whether SKU USB-HUB-01 needs restocking and explain the result."
```

Kontrola zásoby a vytvoření návrhu objednávky:

```bash
docker compose run --rm agent --trace --allow-write \
  "Check whether SKU USB-HUB-01 needs restocking and create a purchase draft if necessary."
```

Vytvořený JSON zůstane na hostiteli v `data/runtime`. Bez `--allow-write` aplikace zápis zamítne a agent musí vysvětlit, které oprávnění chybí.

Testy nevolají API a nepotřebují platný klíč:

```bash
docker compose run --rm test
```

Soubor `.env` se nekopíruje do image. Compose z něj pouze předá kontejneru proměnné `OPENAI_API_KEY` a `OPENAI_MODEL` při spuštění.

## Spuštění bez Dockeru

Požadavky:

- PHP 8.0 nebo novější,
- rozšíření `curl` a `json`,
- OpenAI API klíč,
- model dostupný vašemu OpenAI projektu a podporující function calling přes Responses API.

```bash
cp .env.example .env
# Do .env vložte OPENAI_API_KEY a případně změňte OPENAI_MODEL.

php bin/agent --trace \
  "Check whether SKU USB-HUB-01 needs restocking and explain the result."
```

Model lze změnit bez úpravy kódu:

```bash
OPENAI_MODEL=your-function-calling-model php bin/agent "Your goal"
# nebo
php bin/agent --model=your-function-calling-model "Your goal"
```

## Přidání vlastního nástroje

1. Implementujte `Demo\Tools\Tool`.
2. V `definition()` popište vstupy pomocí strict JSON Schema.
3. V `execute()` zavolejte vlastní repository, API klienta nebo doménovou službu.
4. Pokud nástroj mění data nebo externí stav, vraťte z `requiresWritePermission()` hodnotu `true`.
5. Zaregistrujte nástroj v `ToolRegistry` v `bin/agent`.

Model PHP funkci nikdy nespouští přímo. Pouze vrátí požadavek na tool call. Aplikace ověří whitelist a oprávnění, zavolá implementaci a výsledek vrátí modelu jako další pozorování.

## Co ukázka demonstruje

- obecnou agentní smyčku nad OpenAI Responses API,
- nativní function calling se striktními JSON schématy,
- několik kroků a nástrojů v rámci jednoho cíle,
- oddělení LLM, orchestrace a doménové logiky,
- deterministický a testovatelný byznysový výpočet,
- předávání výsledků i chyb nástrojů zpět agentovi,
- limit počtu kol proti nekonečné smyčce,
- whitelist nástrojů a oprávnění pro zápis,
- explicitní označení výstupů nástrojů jako nedůvěryhodných dat, ne instrukcí,
- trace skutečně provedených akcí,
- test agentní smyčky bez volání API.

Projekt nemá framework, databázi ani externí Composer závislosti, aby zůstal dobře viditelný samotný integrační princip.

## Struktura projektu

```text
bin/agent                              CLI vstup a registrace nástrojů
Dockerfile                             PHP CLI image
compose.yaml                           služby pro agenta a testy
src/Agent/Agent.php                    obecná agentní smyčka
src/OpenAI/ResponsesClient.php         malý HTTP klient pro Responses API
src/Inventory/InventoryRepository.php  přístup ke skladovým datům
src/Inventory/RestockPolicyRepository.php přístup k pravidlům doplňování
src/Inventory/RestockCalculator.php    sdílený doménový výpočet
src/Tools/Tool.php                     kontrakt nástroje
src/Tools/ToolRegistry.php             whitelist, spuštění a oprávnění
src/Tools/FindProductTool.php          čtení skladových dat
src/Tools/GetRestockPolicyTool.php     čtení pravidel doplňování
src/Tools/CalculateRestockTool.php     doménový výpočet
src/Tools/CreatePurchaseDraftTool.php  řízená zápisová akce
data/inventory.json                    demonstrační skladová data
data/restock-policies.json             demonstrační pravidla
data/runtime/                          vytvořené návrhy objednávek
tests/run.php                          testy bez API
```

## Testy

```bash
php tests/run.php
# nebo
composer test
```

## Bezpečnost a produkční použití

- API klíč patří pouze do `.env`, který je v `.gitignore`.
- `store: false` vypíná ukládání Response objektů na straně API pro tento příklad.
- Výstup nástrojů se považuje za nedůvěryhodná data, ne za instrukce.
- `CreatePurchaseDraftTool` zapisuje pouze do `data/runtime` a bez `--allow-write` se nespustí.
- Před zápisem znovu načte produkt a pravidla a množství přepočítá v doménovém PHP kódu.
- Zápisový nástroj vytváří návrh; žádnou objednávku neposílá.
- Demo neposkytuje shell ani obecný přístup k souborovému systému.

Před produkčním nasazením je vhodné doplnit autentizaci, autorizaci pro jednotlivé nástroje a objekty, auditní log, schvalování rizikových akcí, retry politiku, idempotenci zápisů, limity nákladů a evaluační scénáře odpovídající konkrétní doméně.
