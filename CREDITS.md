# Credits and licensing status

Plogger Reloaded builds on the original Plogger authors and contributors, the PDO fork by **wuffleton**, and the `plogger_php7` / `plogger_php8` work by **xiphiasphotography**. The inherited README attributes the intermediate source to `alexzhuustc/plogger`. Full author and copyright provenance still needs review; retain all source headers.

The root `LICENSE` is the inherited GNU GPL version 3 text, identical in the adjacent php7/php8 forks according to AUDIT.md. Original `readme.html` instead states GPL2 and references absent `LICENSE.txt`. GPLv3 remains a project assumption, not a completed provenance audit or a new grant for upstream code. Phase 0 leaves LICENSE unchanged.

The verified baseline records these bundled notices:

| Component | Notice/status |
| --- | --- |
| Retired Exifer 1.7 / Zenphoto-derived updates | Removed in Phase 3; native PHP EXIF/IPTC replaces it; historical attribution retained |
| PHP-gettext / Danilo Segan | GPL version 2 or later; retained notices and bounded native-format/plural parsers |
| Retired PclZip 2.4 | Removed in Phase 3; native ZipArchive replaces it |
| phpThumb / James Heinrich | Explicit GPLv3 option in bundled license.txt; GPL texts retained; GIF Util/Yamasoft and Unsharp Mask/Torstein Hønsi notices retained |
| Lightbox | Creative Commons Attribution 2.5 header |
| Font Awesome 5 | Icons CC BY 4.0, fonts SIL OFL 1.1, code MIT |
| CAPTCHA | CC Attribution-Noncommercial-Share Alike 3.0 header; potential GPL incompatibility blocks redistribution pending resolution |
| Prototype, Bootstrap, fonts, icons, images and custom ZIP code | Complete attribution/license tracing pending |

This inventory preserves AUDIT.md findings; it is not legal clearance. Resolve original grants, CAPTCHA permission/replacement and component obligations before publishing a release. Replacing code does not remove obligations for retained assets.

See [Phase 3 component review](docs/libraries.md) for versions, licenses, security boundaries and verification limits. The old custom ZIP writer is also removed.

The 2026-10-22 release audit reconfirmed the root/fork LICENSE identity and the
specific gettext, phpThumb, Prototype, Lightbox and CAPTCHA notices. It did not
resolve the original grant or asset provenance; redistribution remains blocked.
See [RELEASE-BLOCKERS.md](RELEASE-BLOCKERS.md#rb-07-licenses-and-assets).

The [2026-10-23/24 provenance follow-up](docs/rb05-08-validation.md#redistribution-evidence-and-missing-inputs)
checks all 109 asset inventory hashes and records the exact missing grants.
CAPTCHA's embedded noncommercial notice and its Mana Mana/Jenny Barck font
remain blockers. Font Awesome's upstream component licenses were confirmed,
but exact asset/build attribution still requires reconciliation. No notice was
removed and no code or asset was relicensed.
