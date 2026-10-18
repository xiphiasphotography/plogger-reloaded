# Theme compatibility and photograph actions

Existing theme-facing functions in `plog-includes/plog-functions.php` and
`plogger.php` keep their names, argument order/defaults and return conventions.
The inherited signature fixture in `tests/fixtures/theme-api.json` guards that
contract; static checks approximate missing functions and PHP syntax, while
HTTP fixtures exercise bundled theme rendering. They do not certify a private
theme or replace staging/browser checks. No legacy theme is converted to a new
CSS or JavaScript system by Phase 5b.

The additive `plogger_get_picture_actions(?array $picture = null, ?array $album =
null): string` function returns safe HTML for registered photograph actions.
Within the existing picture loop, after `plogger_load_picture()`, use:

```php
<?php echo plogger_get_picture_actions(); ?>
```

Place this beneath the photograph/description, outside an existing link or
paragraph. The bundled default, air, lucid and xip-bs templates already do so.
With no enabled providers, the return value is an empty string and no additional
database lookup occurs. Custom themes can omit the hook and continue working.
Explicit picture/album arrays avoid the optional current-album lookup.

Core validates HTTP(S) links, escapes URLs and text, and renders translated
provider labels with `rel="noopener noreferrer"` for new-window links. Trusted
providers return structured data rather than HTML. Providers should not parse
images or perform remote requests during rendering. The optional Oypo provider
uses persisted original dimensions and remains disabled until its missing
contract is supplied. See [extension registration and Oypo configuration](oypo.md).
