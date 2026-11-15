<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CheckTranslationsCommand extends Command
{
    protected $signature = 'stockora:check-translations';

    protected $description = 'Verify 100% EN/ID translation key parity and ensure all static __()/@lang() keys exist in both locales.';

    public function handle(): int
    {
        $enDir = lang_path('en');
        $idDir = lang_path('id');

        if (!is_dir($enDir) || !is_dir($idDir)) {
            $this->error('Missing lang/en or lang/id directory.');

            return self::FAILURE;
        }

        $enFiles = $this->phpBasenames($enDir);
        $idFiles = $this->phpBasenames($idDir);

        $missingInIdFiles = array_diff($enFiles, $idFiles);
        $missingInEnFiles = array_diff($idFiles, $enFiles);

        $errors = [];

        foreach ($missingInIdFiles as $file) {
            $errors[] = "Missing file lang/id/{$file}.php";
        }
        foreach ($missingInEnFiles as $file) {
            $errors[] = "Missing file lang/en/{$file}.php";
        }

        $allKeysByLocale = ['en' => [], 'id' => []];
        $commonFiles = array_intersect($enFiles, $idFiles);

        foreach ($commonFiles as $group) {
            $enFlat = Arr::dot(require "{$enDir}/{$group}.php");
            $idFlat = Arr::dot(require "{$idDir}/{$group}.php");

            foreach ($enFlat as $k => $v) {
                $full = "{$group}.{$k}";
                $allKeysByLocale['en'][$full] = $v;
                if (!array_key_exists($k, $idFlat)) {
                    $errors[] = "Key [{$full}] exists in EN but is missing in ID.";
                } elseif (is_string($v) && trim($v) === '') {
                    $errors[] = "Key [{$full}] has empty string in EN.";
                }
            }

            foreach ($idFlat as $k => $v) {
                $full = "{$group}.{$k}";
                $allKeysByLocale['id'][$full] = $v;
                if (!array_key_exists($k, $enFlat)) {
                    $errors[] = "Key [{$full}] exists in ID but is missing in EN.";
                } elseif (is_string($v) && trim($v) === '') {
                    $errors[] = "Key [{$full}] has empty string in ID.";
                }
            }
        }

        // Scan static literal __('group.key') / @lang('group.key') / trans('group.key') references
        $scanDirs = [resource_path('views'), app_path()];
        $knownGroups = array_unique(array_merge($enFiles, $idFiles));
        $checkedRefs = 0;

        foreach ($scanDirs as $dir) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($it as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $contents = file_get_contents($file->getPathname());
                if ($contents === false) {
                    continue;
                }

                if (preg_match_all("/(?:__|trans|@lang)\(\s*['\"]([a-z0-9_]+(?:\.[a-z0-9_-]+)+)['\"]\s*(?:,|\))/i", $contents, $matches)) {
                    foreach ($matches[1] as $ref) {
                        $group = explode('.', $ref)[0] ?? '';
                        if (!in_array($group, $knownGroups, true)) {
                            continue;
                        }
                        $checkedRefs++;
                        if (!array_key_exists($ref, $allKeysByLocale['en']) && !array_key_exists("{$ref}.0", $allKeysByLocale['en'])) {
                            $errors[] = "Referenced key [{$ref}] missing in EN ({$file->getFilename()}).";
                        }
                        if (!array_key_exists($ref, $allKeysByLocale['id']) && !array_key_exists("{$ref}.0", $allKeysByLocale['id'])) {
                            $errors[] = "Referenced key [{$ref}] missing in ID ({$file->getFilename()}).";
                        }
                    }
                }
            }
        }

        if ($errors !== []) {
            foreach ($errors as $err) {
                $this->error($err);
            }
            $this->error('Translation audit failed with ' . count($errors) . ' issue(s).');

            return self::FAILURE;
        }

        $totalEn = count($allKeysByLocale['en']);
        $totalId = count($allKeysByLocale['id']);
        $this->info("OK: 100% EN/ID translation parity verified ({$totalEn} EN keys, {$totalId} ID keys across " . count($commonFiles) . " files; {$checkedRefs} static key references valid).");

        return self::SUCCESS;
    }

    private function phpBasenames(string $dir): array
    {
        $names = [];
        foreach (glob($dir . '/*.php') ?: [] as $path) {
            $names[] = basename($path, '.php');
        }
        sort($names);

        return $names;
    }
}
