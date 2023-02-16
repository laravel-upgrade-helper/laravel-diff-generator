<?php

define('LARAVEL_FOLDER', 'laravel');
define('DIFF_FOLDER', 'diffs');
define('TAGS_FILE', 'tags.txt');

function generateDiffFile($v1, $v2)
{
    $file = sprintf('%s/%s...%s.diff', DIFF_FOLDER, $v1, $v2);

    if (!file_exists($file)) {
        exec(sprintf('git --git-dir %s/.git diff %s %s > %s', LARAVEL_FOLDER, $v1, $v2, $file));
    }
}

function versionFormat($version)
{
    return str_replace('v', '', $version);
}

if (!file_exists(DIFF_FOLDER)) {
    mkdir(DIFF_FOLDER);
}

if (!file_exists(LARAVEL_FOLDER)) {
    exec(sprintf('git clone https://github.com/laravel/laravel.git %s', LARAVEL_FOLDER));
}

$versionList = shell_exec(sprintf('git --git-dir %s/.git tag --sort=refname | cat', LARAVEL_FOLDER));
$versionList = explode(PHP_EOL, trim($versionList));

$excludedVersions = ['v3', 'v4', 'v5.0', 'v5.1', 'v5.2', 'v5.3', 'v5.4'];

$versionList = (array) array_filter(
    $versionList,
    function ($version) use ($excludedVersions) {
        foreach ($excludedVersions as $excludedVersion) {
            if (substr($version, 0, strlen($excludedVersion)) === $excludedVersion) {
                return false;
            }
        }

        return true;
    }
);

$versionList = array_values($versionList);

usort($versionList, function ($v1, $v2) {
    return version_compare(versionFormat($v1), versionFormat($v2));
});

file_put_contents(TAGS_FILE, implode(PHP_EOL, $versionList));

foreach ($versionList as $index => $baseVersion) {
    echo $baseVersion.PHP_EOL;

    $versionsToCreate = (array) array_filter(
        $versionList,
        function ($version) use ($baseVersion) {
            $versionToCheck = substr($baseVersion, 0, 2) === 'v5' ?
                substr($baseVersion, 0, 4) :
                substr($baseVersion, 0, 2);

            return (substr($version, 0, strlen($versionToCheck)) === $versionToCheck) &&
                (version_compare(versionFormat($baseVersion), versionFormat($version)) === -1);
        }
    );

    $nextItemIndex = array_search($versionsToCreate ? end($versionsToCreate) : $baseVersion, $versionList);
    if ($nextItemIndex !== false) {
        $nextItemIndex++;

        if (isset($versionList[$nextItemIndex])) {
            $versionsToCreate[] = $versionList[$nextItemIndex];
        }
    }

    foreach ($versionsToCreate as $versionToCreate) {
         generateDiffFile($baseVersion, $versionToCreate);
    }
}

echo 'DONE';
