#!/usr/bin/env php
<?php
if (php_sapi_name() != 'cli') {
    die('This command can only be used in CLI mode.');
}

date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

// Declare paths and time
$app               = realpath(__DIR__ . '/../');
$archive_data_path = $app . '/data/archive.json';
$live_data_path    = $app . '/data/live.json';
$today             = new DateTime('now');

if (! file_exists($live_data_path)) {
    $live_data = [];
} else {
    $live_data = json_decode(file_get_contents($live_data_path), true);
}

// Get today's data
$json_day = json_decode(file_get_contents('https://l10n.mozilla-community.org/langchecker/?action=count&json'), true);

if (is_array($json_day)) {
    ksort($json_day);
}

if (empty($json_day)) {
    print "Empty JSON source\n";
}

$live_data[$today->format('Y-m-d')] = $json_day;
file_put_contents($live_data_path, json_encode($live_data, JSON_PRETTY_PRINT));

$archive_data = json_decode(file_get_contents($archive_data_path), true);

$all_data = array_merge($archive_data, $live_data);

// Get locales list, this can vary when we add or drop a locale
$locales = [];
foreach ($all_data as $date => $series) {
    if (is_array($series)) {
        $locales = array_merge($locales, array_keys($series));
    }
}
$locales = array_unique($locales);
sort($locales);

// Generate data.csv file containing all data
$all = 'date,' . implode(',', $locales) . "\n";

foreach ($all_data as $date => $series) {
    $loop_time = new DateTime($date);

    $all .= $date . ',';
    foreach ($locales as $this_locale) {
        if (! is_array($series)) {
            continue;
        }
        if (array_key_exists($this_locale, $series)) {
            $all .= $series[$this_locale];
        } else {
            $all .= '0';
        }
        $all .= ($this_locale === end($locales)) ? "\n" : ',';
    }
}

file_put_contents($app . '/web/csv/data.csv', $all);

// Generate .csv file for each locale
foreach ($locales as $this_locale) {
    $csv = 'date,' . $this_locale . "\n";

    foreach ($all_data as $date => $series) {
        if (! is_array($series)) {
            continue;
        }

        if (array_key_exists($this_locale, $series)) {
            $csv .= $date . ',' . $series[$this_locale] . "\n";
        } else {
            $csv .= $date . ",0\n";
        }
    }

    file_put_contents($app . '/web/csv/' . $this_locale . '.csv', $csv);
}
