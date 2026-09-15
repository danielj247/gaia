<?php

declare(strict_types=1);

namespace App\Actions;

use App\Graph\CompaniesHousePsc;
use RuntimeException;
use ZipArchive;

final readonly class ConvertCompaniesHousePsc
{
    public function handle(string $sourcePath, string $jsonlPath, ?int $limit = null): void
    {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new RuntimeException('Unable to read the Companies House PSC file.');
        }

        if (is_dir($jsonlPath) || ! is_writable(dirname($jsonlPath))) {
            throw new RuntimeException('Unable to write the Companies House PSC JSON lines.');
        }

        $zip = null;
        $handle = $this->openSource($sourcePath, $zip);

        try {
            $out = $this->open($jsonlPath, 'w', 'Unable to write the Companies House PSC JSON lines.');

            try {
                $written = 0;

                while (($line = fgets($handle)) !== false) {
                    $trimmed = mb_trim($line);

                    if ($trimmed === '') {
                        continue;
                    }

                    $decoded = json_decode($trimmed, true);

                    if (! is_array($decoded)) {
                        continue;
                    }

                    $record = [];

                    foreach ($decoded as $key => $value) {
                        if (is_string($key)) {
                            $record[$key] = $value;
                        }
                    }

                    $kind = CompaniesHousePsc::kind($record);

                    if (! CompaniesHousePsc::isGraphKind($kind)) {
                        continue;
                    }

                    $companyNumber = CompaniesHousePsc::companyNumber($record);
                    $sourceId = CompaniesHousePsc::sourceId($record);

                    if ($companyNumber === null || $sourceId === null) {
                        continue;
                    }

                    $payload = CompaniesHousePsc::payload($record);

                    fwrite($out, json_encode([
                        'id' => $sourceId,
                        'list' => 'ch_psc',
                        'kind' => $kind,
                        'company_number' => $companyNumber,
                        'data' => $payload,
                    ], JSON_THROW_ON_ERROR)."\n");
                    $written++;

                    if ($limit !== null && $written >= $limit) {
                        break;
                    }
                }
            } finally {
                fclose($out);
            }
        } finally {
            fclose($handle);

            if ($zip instanceof ZipArchive) {
                $zip->close();
            }
        }
    }

    /**
     * @return resource
     */
    private function openSource(string $path, ?ZipArchive &$zip)
    {
        if ($this->looksLikeZip($path)) {
            $zip = new ZipArchive;

            if ($zip->open($path) !== true) {
                throw new RuntimeException('Unable to read the Companies House PSC file.');
            }

            $name = $this->firstJsonName($zip);

            if ($name === null) {
                $zip->close();
                $zip = null;

                throw new RuntimeException('Companies House PSC zip does not contain a JSON file.');
            }

            $handle = $zip->getStream($name);

            if ($handle === false) {
                $zip->close();
                $zip = null;

                throw new RuntimeException('Unable to read the Companies House PSC file.');
            }

            return $handle;
        }

        return $this->open($path, 'r', 'Unable to read the Companies House PSC file.');
    }

    private function looksLikeZip(string $path): bool
    {
        if (str_ends_with(mb_strtolower($path), '.zip')) {
            return true;
        }

        $head = file_get_contents($path, false, null, 0, 4);

        return $head === "PK\x03\x04" || $head === "PK\x05\x06" || $head === "PK\x07\x08";
    }

    private function firstJsonName(ZipArchive $zip): ?string
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);
            $lower = mb_strtolower(mb_rtrim($name, '/'));

            if (str_ends_with($lower, '.json') || str_ends_with($lower, '.jsonl') || str_ends_with($lower, '.txt')) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return resource
     */
    private function open(string $path, string $mode, string $message)
    {
        $handle = @fopen($path, $mode);

        if ($handle === false) {
            throw new RuntimeException($message);
        }

        return $handle;
    }
}
