#!/usr/bin/env php
<?php
if (php_sapi_name() != 'cli') {
    die('This command can only be used in CLI mode.');
}

date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

// Manage CTRL+C and other script interruptions
declare(ticks = 1); // how often to check for signals
// This function will process sent signals
function sig_handler($signo)
{
    global $git_mozorg;
    global $svn_misc;
    if ($signo == SIGTERM || $signo == SIGHUP || $signo == SIGINT) {
        if ($signo != 15) {
            print "Received signal $signo and will exit now!\n";
        } else {
            print "Operations finished\n";
        }
        // Cleanup
        chdir($svn_misc);
        exec('svn cleanup');
        exec('pkill php');
        exit;
    }
}

// These define the signal handling
pcntl_signal(SIGTERM, 'sig_handler');
pcntl_signal(SIGHUP,  'sig_handler');
pcntl_signal(SIGINT,  'sig_handler');

// Repositories to loop for date in
$app             = realpath(__DIR__);
$data_path       = $app   . '/logs/data.json';
$repos           = $app   . '/repos';
$git_mozorg      = $repos . '/mozillaorg/';
$svn_misc        = $repos . '/l10n-misc';
$git_langchecker = $repos . '/langchecker';
$git_stores      = $repos . '/appstores';

// Create our data structure
$update_repos = true;

if (! is_dir($git_mozorg)) {
    print "Checking our mozilla.org git repository\n";
    mkdir($repos . '/mozillaorg/');
    chdir($repos);
    exec('git clone https://github.com/mozilla-l10n/www.mozilla.org/ mozillaorg');
} elseif ($update_repos) {
    chdir($git_mozorg);
    print "Updating the mozilla.org git repository\n";
    exec('git checkout master');
    exec('git pull origin master');
}

if (! is_dir($svn_misc)) {
    chdir($repos);
    print "Checking our l10n-misc svn repository\n";
    exec('svn co https://svn.mozilla.org/projects/l10n-misc/trunk l10n-misc');
}

if (! is_dir($git_langchecker)) {
    chdir($repos);
    print "Cloning the Langchecker git repository\n";
    exec('git clone https://github.com/mozilla-l10n/langchecker');
} elseif ($update_repos) {
    chdir($git_langchecker);
    print "Updating the Langchecker git repository\n";
    exec('git checkout master');
    exec('git pull origin master');
}

if (! is_dir($git_stores)) {
    chdir($repos);
    print "Cloning the AppStores git repository\n";
    exec('git clone https://github.com/mozilla-l10n/appstores');
} elseif ($update_repos) {
    chdir($git_stores);
    print "Updating the AppStores git repository\n";
    exec('git checkout master');
    exec('git pull origin master');
}

if (! is_file($git_langchecker . '/composer.phar')) {
    chdir($git_langchecker);
    print "Installing composer.\n";
    exec('curl -sS https://getcomposer.org/installer | php', $output, $err);
} else {
    print "Self-updating composer.\n";
    exec("php $git_langchecker/composer.phar self-update");
}



// On 2015-10-14 we reorganized the langchecker app file structure
if (is_dir($git_langchecker .'/config/')) {
    copy($repos . '/settings.inc.php', $git_langchecker .'/config/settings.inc.php');
}

// On 2015-10-14 we reorganized the langchecker app file structure
if (is_dir($git_langchecker .'/app/config/')) {
    copy($repos . '/settings2015-10-13.inc.php', $git_langchecker .'/app/config/settings.inc.php');
}


if (! is_dir($app . '/logs/')) {
    mkdir($app . '/logs/');
}

if (! file_exists($data_path)) {
    file_put_contents($data_path, json_encode([]));
}


// We can force the recalculation of a single date, ex: dig.php 2015-01-29
if (isset($argv[1])) {
    $date_override = true;
    $begin = new DateTime($argv[1]);
    $end   = new DateTime($argv[1] . ' + 1 day');
} else {
    $date_override = false;
    // Define our date interval
    // 2013-10-12 is when I added the json API to the countstring view in Langchecker
    // 2014-11-02 is when all web parts were included in the json, not just mozilla.org
    $begin = new DateTime('2014-11-02');

    // We define the end date as being yesterday because we don't want to process partial days
    $end = new DateTime();
    $end->add(DateInterval::createFromDateString('yesterday'));
}

$interval = DateInterval::createFromDateString('1 day');
$period = new DatePeriod($begin, $interval, $end);

$data = json_decode(file_get_contents($data_path), true);

chdir($git_langchecker);
print "Launching PHP dev server in the background inside the Langchecker instance.\n";
exec('php -S localhost:8082 > /dev/null 2>&1 &');

foreach ($period as $date) {
    $day = $date->format('Y-m-d');
    if (array_key_exists($day, $data) && ! $date_override) {
        continue;
    }

    print $day . "\n";

    // We switched reference locale from en-GB to en-US on 2015-01-15
    $vcs_day = ($day == '2015-01-15') ? $day . ' 12:00:00' : $day . ' 00:00:00';

    // Update repositories
    chdir($git_langchecker);
    exec("git checkout `git rev-list -n 1 --before=\"${vcs_day}\" master`");

    if (! is_dir($git_langchecker . '/vendor') && is_file($git_langchecker . '/composer.json')) {
        print "Installing composer dependencies.\n";
        exec("php {$git_langchecker}/composer.phar install");
    }

    if (! isset($composer_sig) && is_file($git_langchecker . '/composer.json')) {
        $composer_sig = sha1(file_get_contents($git_langchecker . '/composer.json'));
    }

    if (isset($composer_sig)) {
        if (sha1(file_get_contents($git_langchecker . '/composer.json')) != $composer_sig) {
            print "Updating composer dependencies.\n";
            exec("php {$git_langchecker}/composer.phar");
            $composer_sig = sha1(file_get_contents($git_langchecker . '/composer.json'));
        } else {
            // Updating autoloader
            exec("php {$git_langchecker}/composer.phar dump-autoload");
        }
    }

    chdir($git_mozorg);
    exec("git checkout `git rev-list -n 1 --before=\"${vcs_day}\" master`");
    chdir($svn_misc);
    exec('svn up -r{"' . $vcs_day . '"}');
    chdir($git_stores);
    exec("git checkout `git rev-list -n 1 --before=\"${vcs_day}\" master`");

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

$data = json_decode(file_get_contents($data_path), true);

// Get locales list, this can vary when we add or drop a locale
$locales = [];
foreach ($data as $date => $serie) {
    if (is_array($serie)) {
        $locales = array_merge($locales, array_keys($serie));
    }
}
$locales = array_unique($locales);
sort($locales);

$all = 'date,' . implode(',', $locales) . "\n";

foreach ($data as $date => $serie) {
    $loop_time = new DateTime($date);

    $all .= $date . ',';
    foreach ($locales as $this_locale) {
        if (! is_array($serie)) {
            continue;
        }
        if (array_key_exists($this_locale, $serie)) {
            $all .= $serie[$this_locale];
        } else {
            $all .= '0';
        }
        $all .= ($this_locale === end($locales)) ? "\n" : ',';
    }
}

file_put_contents($app .'/logs/data.csv', $all);

foreach ($locales as $this_locale) {
    $csv = 'date,' . $this_locale . "\n";

    foreach ($data as $date => $serie) {
        if (! is_array($serie)) {
            continue;
        }

        if (array_key_exists($this_locale, $serie)) {
            $csv .= $date . ',' . $serie[$this_locale] . "\n";
        } else {
            $csv .= $date . ",0\n";
        }
    }

    file_put_contents($app .'/logs/' . $this_locale . '.csv', $csv);
}

// Kill the php process we launched in the background
exec('pkill php');
