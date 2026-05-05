# Agent Instructions pre Symfony 8 & PHP 8.5 Projekt

Tento projekt je postavený na Symfony 8.0 a beží na PHP 8.5. Pri generovaní, upravovaní a refaktorovaní kódu striktne dodržuj nasledujúce pravidlá.

## Dev Environment & PHP Štandardy
- **Moderné PHP:** Využívaj najnovšie funkcie PHP 8.5. Preferuj `readonly` triedy (najmä pre DTO a Eventy), constructor property promotion a `match` expressions.
- **Striktné typy:** Každý nový PHP súbor MUSÍ začínať deklaráciou `declare(strict_types=1);`.
- **Atribúty namiesto anotácií:** Pre routing, Doctrine mapovanie, validáciu a DI používaj výhradne natívne PHP atribúty (napr. `#[Route]`, `#[ORM\Entity]`). Zásadne nepoužívaj PHPDoc anotácie na logiku.
- **Dependency Injection:** Nikdy neťahaj služby priamo z kontajnera (`$container->get()`). Vždy používaj injektovanie cez konštruktor. Pre autowiring špecifických služieb používaj atribút `#[Autowire]`.
- **Konzola:** Vždy používaj `php bin/console`. Ak vytváraš nové entity, controllery alebo formuláre, preferuj generovanie cez `php bin/console make:...` pre udržanie štandardnej štruktúry.
- **Balíčky:** Na správu závislostí používaj `composer require <package>`. Pre vývojárske nástroje nezabudni pridať flag `--dev`.

## Testing Instructions (Testovanie a Statická analýza)
- **Architektúra testov:** Testy sú v zložke `tests/`. Dodržuj rozdelenie na `Unit`, `Integration` a `Application` (alebo `Functional`) testy.
- **Spúšťanie testov:** Na spustenie testov používaj `php bin/phpunit`. Pre zameranie sa na jeden test použi `php bin/phpunit --filter <TestName>`.
- **Povinné testovanie:** Ak meníš logiku v adresári `src/`, automaticky vytvor alebo aktualizuj príslušný test v adresári `tests/`, aj keď ťa o to používateľ výslovne nepožiadal.
- **Statická analýza:** Tento projekt používa striktný PHPStan. Po akejkoľvek zmene kódu spusti `vendor/bin/phpstan analyse`. Kód nesmie obsahovať žiadne chyby na maximálnom leveli.
- **Formátovanie kódu:** Pred dokončením tasku spusti `vendor/bin/php-cs-fixer fix` (alebo ECS, ak je v projekte nastavený), aby sa zachoval jednotný štýl kódu (PSR-12 / PER Coding Style). Celá CI pipeline musí po tvojom zásahu zostať zelená.

## PR a Commit Instructions
- **Názvoslovie commitov:** Používaj štandardný formát: `[Komponent] Popis zmeny` (napr. `[Messenger] Pridanie Respatch endpointu` alebo `[Security] Overovanie API tokenu`).
- **Verifikácia pred commitom:** Vždy si interne simuluj spustenie PHPStanu a PHPUnitu pred tým, než kód označíš za hotový. Kód s varovaniami o chýbajúcich typoch (`mixed`) nebude akceptovaný.
- **Zabezpečenie:** Zásadne neukladaj žiadne citlivé údaje (API kľúče, heslá, tokeny) do repozitára. Všetky secret kľúče odkazuj na `.env` premenné a do `.env` súboru pridávaj len ich prázdne štruktúry alebo dummy hodnoty.