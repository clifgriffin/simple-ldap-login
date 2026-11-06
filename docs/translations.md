# Translations

The plugin uses the same translation tools and 24 locales as Reviewbird: WP-CLI i18n and `gpt-po` 1.2.3. All editions use the `simple-ldap-login` text domain and the shared `languages` folder. WordPress loads the bundled translations on `init`.

The locales are Arabic (`ar`), Czech (`cs_CZ`), Danish (`da_DK`), German (`de_DE`), English (`en_US`), Spanish (`es_ES`), Estonian (`et`), Finnish (`fi`), French (`fr_FR`), Hindi (`hi_IN`), Italian (`it_IT`), Japanese (`ja`), Korean (`ko_KR`), Norwegian Bokmål (`nb_NO`), Dutch (`nl_NL`), Polish (`pl_PL`), Brazilian Portuguese (`pt_BR`), Portuguese (`pt_PT`), Russian (`ru_RU`), Swedish (`sv_SE`), Thai (`th`), Turkish (`tr_TR`), Simplified Chinese (`zh_CN`), and Traditional Chinese (`zh_TW`).

Install WP-CLI with its i18n command, GNU gettext, and the Node version in `.nvmrc`. Then run:

```sh
npm ci
npm run make:pot
npm run make:po
npm run translate
npm run make:po
npm run make:mo
npm run make:php
npm run test:i18n
```

The translation command needs `OPENAI_API_KEY` in the environment. It sends plugin strings to OpenAI and has an API cost. Keep the key out of files and Git. To update only one language, use `npm run translate -- de_DE`. The tool keeps existing translations and fills empty entries. Run `make:po` again after translation to normalize catalog headers.

The initial non-English translations use `gpt-po`. The Pro account feature update adds 44 messages per locale, translated with Codex without an external API call. A native speaker should review them before release. Keep placeholders, product names, code identifiers, and LDAP examples unchanged. The project context is in `.gpt-po/context.txt`. The empty project dictionary overrides the tool's sample dictionary.

Commit the POT, PO, MO, and `.l10n.php` files. MO files support WordPress 6.5. Newer WordPress versions can use the PHP catalogs. The current JavaScript has no user-facing strings, so it does not need JSON catalogs. Use `npm run make:json` if translated JavaScript strings are added later.

`test:i18n` checks all catalog entries, format placeholders, and compiled MO files. The package tests check that every edition includes the language files. `npm run i18n:build` updates the template and compiles existing translations without API calls. If new strings are added, translate them before the release checks.

On the demo site, check both compiled formats with WordPress:

```sh
wp eval-file "$PWD/tests/i18n-runtime.php" --path="$HOME/Sites/simple-ldap-demo"
```

This check does not change the saved site or user language.

Generate catalogs from a workspace with the paid checkout at `pro/`. The public repo stores the shared catalogs for all editions. Public catalog checks do not need paid source.
