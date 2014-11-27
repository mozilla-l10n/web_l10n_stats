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
if (! is_dir($svn_mozorg)) {
    chdir($repos);
    print "Checking our mozilla.org svn repository\n";
    mkdir($repos . '/mozillaorg/');
    exec('svn co https://svn.mozilla.org/projects/mozilla.com/trunk/locales mozillaorg/locales');
}

if (! is_dir($svn_misc)) {
    chdir($repos);
    print "Checking our l10n-misc svn repository\n";
    exec('svn co https://svn.mozilla.org/projects/l10n-misc/trunk l10n-misc');
}

if (! is_dir($git)) {
    chdir($repos);
    print "Cloning the Langchecker git repository\n";
    exec('git clone https://github.com/mozilla-l10n/langchecker');
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
$begin    = new DateTime('2013-12-10');
$end       = new DateTime('2014-11-26');
$interval  = DateInterval::createFromDateString('1 week');
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
        print "install composer dependencies\n";
        exec("composer install > /dev/null 2>&1");
        $composer_sig = sha1(file_get_contents($git . '/composer.json'));
    }

    if (isset($composer_sig)) {
        if (sha1(file_get_contents($git . '/composer.json')) != $composer_sig) {
            print "Updating composer dependencies\n";
            exec("composer update > /dev/null 2>&1");
            $composer_sig = sha1(file_get_contents($git . '/composer.json'));
        } else {
            print "Updating autoloader\n";
            exec("composer dump-autoload > /dev/null 2>&1");
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

// Kill the php process we launched in the background
exec('pkill php');
