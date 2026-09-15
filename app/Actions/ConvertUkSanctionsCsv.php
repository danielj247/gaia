<?php

declare(strict_types=1);

namespace App\Actions;

use App\Graph\UkSanctionsFields;
use RuntimeException;
use Throwable;

final readonly class ConvertUkSanctionsCsv
{
    public function handle(string $csvPath, string $jsonlPath): void
    {
        if (! is_file($csvPath) || ! is_readable($csvPath)) {
            throw new RuntimeException('Unable to read the UK Sanctions List CSV.');
        }

        $handle = $this->open($csvPath, 'r', 'Unable to read the UK Sanctions List CSV.');

        try {
            $headers = $this->readHeaders($handle);
            $groups = [];
            $invalidCount = 0;

            while (($line = fgetcsv($handle)) !== false) {
                if ($line === [null]) {
                    continue;
                }

                $row = [];

                foreach ($headers as $index => $header) {
                    $row[$header] = $line[$index] ?? '';
                }

                $uniqueId = UkSanctionsFields::uniqueId($row);

                if ($uniqueId === null) {
                    $invalidCount++;

                    continue;
                }

                $groups[$uniqueId][] = $row;
            }
        } finally {
            fclose($handle);
        }

        if (is_dir($jsonlPath) || ! is_writable(dirname($jsonlPath))) {
            throw new RuntimeException('Unable to write the UK Sanctions List JSON lines.');
        }

        $out = $this->open($jsonlPath, 'w', 'Unable to write the UK Sanctions List JSON lines.');

        try {
            foreach ($groups as $uniqueId => $rows) {
                $record = UkSanctionsFields::mergeRows($rows);
                $record['id'] = 'uksl:'.$uniqueId;
                $record['list'] = 'uksl';

                fwrite($out, json_encode($record, JSON_THROW_ON_ERROR)."\n");
            }

            for ($i = 0; $i < $invalidCount; $i++) {
                fwrite($out, json_encode(['list' => 'uksl'], JSON_THROW_ON_ERROR)."\n");
            }
        } finally {
            fclose($out);
        }
    }

    /**
     * @return resource
     */
    private function open(string $path, string $mode, string $message)
    {
        try {
            $handle = fopen($path, $mode);
        } catch (Throwable) {
            $handle = false;
        }

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

            $joined = implode(',', $line);

            if (str_contains($joined, 'Unique ID') && str_contains($joined, 'Last Updated')) {
                $headers = [];

                foreach ($line as $header) {
                    $headers[] = mb_trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $header));
                }

                return $headers;
            }
        }

        throw new RuntimeException('UK Sanctions List CSV is missing the Unique ID header.');
    }
}
