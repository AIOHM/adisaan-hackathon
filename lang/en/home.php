<?php

declare(strict_types=1);

/*
 * English home copy lives in config/adisaan_public.php (`home`), so this
 * file is empty on purpose: the controller only overlays a translation
 * when the locale is not English. Other locales mirror the config keys
 * they translate; anything missing falls back to the English config.
 */
return [];
