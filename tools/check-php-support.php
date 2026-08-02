<?php

/**
 * Asserts that every declaration of which PHP versions this project supports
 * agrees with every other one.
 *
 * The supported range is stated in five places. They drifted apart once already:
 * a composer.lock resolved on the maintainer's PHP left two of the four
 * CI-tested versions unable to install. This check makes that class of drift a
 * build failure instead of a surprise.
 *
 * Run with: composer check-php-support
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

/** @return string|null */
$matchOne = static function (string $pattern, string $subject, string $what) use (&$errors): ?string {
    if (preg_match($pattern, $subject, $m) !== 1) {
        $errors[] = "could not read $what — has its format changed?";

        return null;
    }

    return $m[1];
};

// --- 1. composer.json: require.php gives the supported floor -----------------
$composer = json_decode((string)file_get_contents("$root/composer.json"), true, 512, JSON_THROW_ON_ERROR);

$requirePhp = $composer['require']['php'] ?? '';
$floor = $matchOne('/^\^(\d+\.\d+)$/', $requirePhp, 'require.php');
if ($floor === null) {
    fwrite(STDERR, "require.php must look like \"^8.2\", got \"$requirePhp\"\n");
    exit(1);
}

// --- 2. composer.json: config.platform.php must sit on that floor ------------
$platform = $composer['config']['platform']['php'] ?? '';
if (!str_starts_with($platform, "$floor.")) {
    $errors[] = "config.platform.php is \"$platform\" but require.php floor is $floor — "
        . 'the lock would resolve against a different version than the package claims to support';
}

// --- 3. psalm.xml: analysis target must be the floor -------------------------
$psalmXml = (string)file_get_contents("$root/psalm.xml");
$psalmVersion = $matchOne('/phpVersion="([^"]+)"/', $psalmXml, 'psalm.xml phpVersion');
if ($psalmVersion !== null && $psalmVersion !== $floor) {
    $errors[] = "psalm.xml phpVersion is \"$psalmVersion\" but the supported floor is $floor";
}

// --- 4. CI matrix: must start at the floor ----------------------------------
$workflow = (string)file_get_contents("$root/.github/workflows/php.yml");
$matrixRaw = $matchOne("/php-versions:\s*\[([^\]]+)\]/", $workflow, 'the CI php-versions matrix');
$matrix = [];
if ($matrixRaw !== null) {
    preg_match_all('/(\d+\.\d+)/', $matrixRaw, $m);
    $matrix = $m[1];
    if ($matrix === []) {
        $errors[] = 'the CI php-versions matrix is empty';
    } elseif (min($matrix) !== $floor) {
        $errors[] = 'the CI matrix starts at ' . min($matrix) . " but the supported floor is $floor";
    }
}

// --- 5. README: must list exactly the CI matrix ------------------------------
$readmeMd = (string)file_get_contents("$root/README.md");
$readmeRaw = $matchOne('/^- PHP ([^—\n]+)/m', $readmeMd, "README's PHP requirement line");
if ($readmeRaw !== null && $matrix !== []) {
    preg_match_all('/(\d+\.\d+)/', $readmeRaw, $m);
    $readme = $m[1];
    if ($readme !== $matrix) {
        $errors[] = 'README lists PHP ' . implode(', ', $readme)
            . ' but CI tests ' . implode(', ', $matrix);
    }
}

// --- report -----------------------------------------------------------------
if ($errors !== []) {
    fwrite(STDERR, "PHP support declarations disagree:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  - $error\n");
    }
    fwrite(STDERR, "\nThese must all state the same thing: composer.json require.php,\n");
    fwrite(STDERR, "composer.json config.platform.php, psalm.xml phpVersion,\n");
    fwrite(STDERR, ".github/workflows/php.yml php-versions, and README.md Requirements.\n");
    exit(1);
}

printf(
    "PHP support is consistent: floor %s, platform %s, psalm %s, tested on %s\n",
    $floor,
    $platform,
    $psalmVersion,
    implode(', ', $matrix)
);
exit(0);
