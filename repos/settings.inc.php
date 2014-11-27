<?php

$repo1  = __DIR__ . '/../../mozillaorg/';
$repo2  = __DIR__ . '/../../l10n-misc/fx36start/';
$repo3  = __DIR__ . '/../../l10n-misc/surveys/';
$repo4  = __DIR__ . '/../../l10n-misc/marketing/';
$repo5  = __DIR__ . '/../../l10n-misc/firefoxhealthreport/';
$repo7  = __DIR__ . '/../../l10n-misc/snippets/';
$repo8  = __DIR__ . '/../../l10n-misc/add-ons/';
$repo9  = __DIR__ . '/../../l10n-misc/firefoxupdater/';
$repo10 = __DIR__ . '/../../l10n-misc/firefoxos-marketing/';
$repo11 = __DIR__ . '/../../l10n-misc/firefoxtiles/';
$repo12 = __DIR__ . '/../../l10n-misc/googleplay/';
$repo6  = ''; // let's ignore this one, marginal

// Path to local clone of Locamotion's repo
$locamotion_repo  = ''; // no need for stats

include __DIR__ . '/locales.inc.php';

if (isset($mozillaorg)) {
    $mozilla = array_diff($mozillaorg, ['en-GB', 'es']);
}

$mozillaorg = array_diff($mozilla, ['en-GB', 'es', 'lg', 'nn-NO', 'sw']);
