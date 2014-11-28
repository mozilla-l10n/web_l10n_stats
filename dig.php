#!/usr/bin/env php
<?php
date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

// Manage CTRL+C and other script interruptions
declare(ticks = 1); // how often to check for signals
// This function will process sent signals
function sig_handler($signo){
    global $svn_mozorg;
    global $svn_misc;
    if ($signo == SIGTERM || $signo == SIGHUP || $signo == SIGINT) {
        if ($signo != 15) {
            print "Received signal $signo and will exit now!\n";
        } else {
            print "Operations finished\n";
        }
        // Cleanup
        chdir($svn_mozorg);
        exec('svn cleanup');
        chdir($svn_misc);
        exec('svn cleanup');
        exec('pkill php');
        exit();
    }
}

// These define the signal handling
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGHUP,  "sig_handler");
pcntl_signal(SIGINT, "sig_handler");

// Repositories to loop for date in
$app        = realpath(__DIR__);
$repos      = $app . '/repos';
$svn_mozorg = $repos . '/mozillaorg/locales';
$svn_misc   = $repos . '/l10n-misc';
$git        = $repos . '/langchecker';
$data_path  = $app . '/logs/data.json';

// Create our data structure
$update_repos = false;

if (! is_dir($svn_mozorg)) {
    chdir($repos);
    print "Checking our mozilla.org svn repository\n";
    mkdir($repos . '/mozillaorg/');
    exec('svn co https://svn.mozilla.org/projects/mozilla.com/trunk/locales mozillaorg/locales');
} elseif($update_repos) {
    chdir($svn_mozorg);
    exec('svn up');
}

if (! is_dir($svn_misc)) {
    chdir($repos);
    print "Checking our l10n-misc svn repository\n";
    exec('svn co https://svn.mozilla.org/projects/l10n-misc/trunk l10n-misc');
} elseif($update_repos) {
    chdir($svn_misc);
    exec('svn up');
}

if (! is_dir($git)) {
    chdir($repos);
    print "Cloning the Langchecker git repository\n";
    exec('git clone https://github.com/mozilla-l10n/langchecker');
} elseif($update_repos) {
    chdir($git);
    exec('git pull origin master');
}


if (! is_file($git . '/composer.phar')) {
    print "Installing composer.\n";
    exec("curl -sS https://getcomposer.org/installer | php");
}

if (is_dir($git) && ! file_exists($git .'/config/settings.inc.php')) {
    copy($repos . '/settings.inc.php', $git .'/config/settings.inc.php');
}

if (! is_dir($app . '/logs/')) {
    mkdir($app . '/logs/');
}

if (! file_exists($data_path)) {
    file_put_contents($data_path, json_encode([]));
}

// Define our date interval

// 2013-10-12 is when I added the json API to the countstring view in Langchecker
$begin    = new DateTime('2014-11-02');
$end       = new DateTime('2014-11-28');
$interval  = DateInterval::createFromDateString('1 day');
$period    = new DatePeriod($begin, $interval, $end);
$data = json_decode(file_get_contents($app .'/logs/data.json'), true);

chdir($git);
print "Launching PHP dev server in the background inside the Langchecker instance.\n";
exec('php -S localhost:8082 > /dev/null 2>&1 &');

foreach ($period as $date) {
    $day = $date->format('Y-m-d');
    if (array_key_exists($day, $data)) {
        continue;
    }

    print $day . "\n";

    // Update repositories
    chdir($git);
    exec("git checkout `git rev-list -n 1 --before=\"${day}\" master` --quiet");

    if (! is_dir($git . '/vendor') && is_file($git . '/composer.json')) {
        print "Installing composer dependencies.\n";
        exec("php composer.phar install > /dev/null 2>&1");
        $composer_sig = sha1(file_get_contents($git . '/composer.json'));
    }

    if (isset($composer_sig)) {
        if (sha1(file_get_contents($git . '/composer.json')) != $composer_sig) {
            print "Updating composer dependencies.\n";
            exec("php composer.phar > /dev/null 2>&1");
            $composer_sig = sha1(file_get_contents($git . '/composer.json'));
        } else {
            print "Updating autoloader.\n";
            exec("php composer.phar dump-autoload > /dev/null 2>&1");
        }
    }

    chdir($svn_mozorg);
    exec('svn up -r{' . $day . '}');
    chdir($svn_misc);
    exec('svn up -r{' . $day . '}');

    // Analyse data
    chdir($app);
    $json_day = json_decode(file_get_contents('http://localhost:8082/?action=count&json'), true);
    if (is_array($json_day)) {
        ksort($json_day);
    }
    if (empty($json_day)) {
        print "Empty json source\n";
    }
    $data[$day] = $json_day;
    ksort($data);
    file_put_contents($data_path, json_encode($data, JSON_PRETTY_PRINT));
}

$data = json_decode(file_get_contents($app .'/logs/data.json'), true);

// get locales list, this can vary when we add or drop a locale
$locales = [];
foreach($data as $date => $serie) {
    $locales = array_merge($locales, array_keys($serie));
}
$locales = array_unique($locales);
sort($locales);

$all = 'date,' . implode(',', $locales) . "\n";

foreach($data as $date => $serie) {
    $loop_time = new DateTime($date);
    if ($loop_time < $begin || $loop_time > $end) {
        continue;
    }
    $all .= $date . ',';
    foreach($locales as $this_locale) {
        if (array_key_exists($this_locale, $serie)) {
            $all .= $serie[$this_locale];
        } else {
            $all .= '0';
        }
        $all .= ($this_locale === end($locales)) ? "\n" : ',';
    }
}

file_put_contents($app .'/logs/data.csv', $all);

foreach($locales as $this_locale) {
    $csv = 'date,' . $this_locale . "\n";
    foreach($data as $date => $serie) {
        $loop_time = new DateTime($date);
        if ($loop_time < $begin || $loop_time > $end) {
            continue;
        }

        if (array_key_exists($this_locale, $serie)) {
            $csv .= $date . ',' . $serie[$this_locale] . "\n";
        } else {
            $all .= $date . ",0\n";
        }
    }
    file_put_contents($app .'/logs/' . $this_locale . '.csv', $csv);
}

// Kill the php process we launched in the background
exec('pkill php');
