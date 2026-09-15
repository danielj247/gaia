<?php

declare(strict_types=1);

namespace App\Actions;

use App\Graph\CompaniesHouseFields;
use RuntimeException;
use ZipArchive;

final readonly class ConvertCompaniesHouseCsv
{
    public function handle(string $sourcePath, string $jsonlPath, ?int $limit = null): void
    {
        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new RuntimeException('Unable to read the Companies House company data file.');
        }

        if (is_dir($jsonlPath) || ! is_writable(dirname($jsonlPath))) {
            throw new RuntimeException('Unable to write the Companies House company JSON lines.');
        }

        $zip = null;
        $handle = $this->openSource($sourcePath, $zip);

        try {
            $headers = $this->readHeaders($handle);
            $out = $this->open($jsonlPath, 'w', 'Unable to write the Companies House company JSON lines.');

            try {
                $written = 0;

                while (($line = fgetcsv($handle)) !== false) {
                    if ($line === [null]) {
                        continue;
                    }

                    $row = [];

                    foreach ($headers as $index => $header) {
                        $row[$header] = $line[$index] ?? '';
                    }

                    $companyNumber = CompaniesHouseFields::companyNumber($row);

                    if ($companyNumber === null) {
                        continue;
                    }

                    $row['id'] = $companyNumber;
                    $row['list'] = 'ch_companies';
                    $row['company_number'] = $companyNumber;

                    fwrite($out, json_encode($row, JSON_THROW_ON_ERROR)."\n");
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
                throw new RuntimeException('Unable to read the Companies House company data file.');
            }

            $csvName = $this->firstCsvName($zip);

            if ($csvName === null) {
                $zip->close();
                $zip = null;

                throw new RuntimeException('Companies House company zip does not contain a CSV file.');
            }

            $handle = $zip->getStream($csvName);

            if ($handle === false) {
                $zip->close();
                $zip = null;

                throw new RuntimeException('Unable to read the Companies House company data file.');
            }

            return $handle;
        }

        return $this->open($path, 'r', 'Unable to read the Companies House company data file.');
    }

    private function looksLikeZip(string $path): bool
    {
        if (str_ends_with(mb_strtolower($path), '.zip')) {
            return true;
        }

        $head = file_get_contents($path, false, null, 0, 4);

        return $head === "PK\x03\x04" || $head === "PK\x05\x06" || $head === "PK\x07\x08";
    }

    private function firstCsvName(ZipArchive $zip): ?string
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (str_ends_with(mb_strtolower(mb_rtrim($name, '/')), '.csv')) {
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

    /**
     * @param  resource  $handle
     * @return list<string>
     */
    private function readHeaders($handle): array
    {
        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null]) {
                continue;
            }

            $headers = [];

            foreach ($line as $header) {
                $headers[] = mb_trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $header));
            }

            $joined = implode(',', $headers);

            if (str_contains($joined, 'CompanyNumber') || str_contains($joined, 'Company Number')) {
                return $headers;
            }
        }

        throw new RuntimeException('Companies House company CSV is missing the CompanyNumber header.');
    }
}
