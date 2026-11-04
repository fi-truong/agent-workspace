<?php

namespace App\Services;

/** Checks repeated source fields in tab-separated Agent output without interpreting grading rules. */
class TabularResultValidator
{
    /** @return list<string> */
    public function warnings(string $handoff, string $result): array
    {
        $matched = preg_match_all('/\[UNTRUSTED FILE DATA:[^\n]*\| extraction complete \| handoff complete content supplied \|[^\n]*\]\R(.*?)\R\[\/UNTRUSTED DATA\]/s', $handoff, $blocks);
        $sourceTables = [];
        if ($matched !== false) {
            foreach ($blocks[1] as $block) {
                $sourceTables = [...$sourceTables, ...$this->tables($block)];
            }
        }

        $warnings = [];
        foreach ($this->tables($result) as $output) {
            $differentTotals = [];
            foreach ($this->categorySummary($result, $output) as $label => $counts) {
                if ($counts['declared'] !== $counts['actual']) {
                    $differentTotals[] = $label.': nêu '.$counts['declared'].', đếm trong bảng '.$counts['actual'];
                }
            }
            if ($differentTotals !== []) {
                $warnings[] = 'Số lượng trong phần tóm tắt không khớp bảng ('.implode('; ', array_slice($differentTotals, 0, 10)).'). Hãy kiểm tra lại trước khi dùng kết quả.';
            }
            $best = [];
            $bestScore = 0;
            foreach ($sourceTables as $source) {
                if ($source['columns'][0] !== $output['columns'][0]) {
                    continue;
                }
                $columns = array_values(array_intersect($source['columns'], $output['columns']));
                $keys = array_intersect(array_keys($source['rows']), array_keys($output['rows']));
                if (count($columns) < 3 || count($keys) < 2) {
                    continue;
                }
                $score = count($columns) * count($keys);
                if ($score > $bestScore) {
                    $best = [[$source, $columns, $keys]];
                    $bestScore = $score;
                } elseif ($score === $bestScore) {
                    $best[] = [$source, $columns, $keys];
                }
            }

            // Ambiguous input tables may represent different versions. Do not choose one.
            if (count($best) !== 1) {
                continue;
            }
            [$source, $columns, $keys] = $best[0];
            $differentRows = [];
            $rowNumber = 0;
            foreach ($output['rows'] as $key => $outputRow) {
                $rowNumber++;
                if (! in_array($key, $keys, true)) {
                    continue;
                }
                foreach (array_slice($columns, 1) as $column) {
                    $original = $source['rows'][$key][$column] ?? '';
                    $returned = $outputRow[$column] ?? '';
                    if ($original !== $returned && ! (is_numeric($original) && is_numeric($returned) && (float) $original === (float) $returned)) {
                        $differentRows[] = $rowNumber;

                        break;
                    }
                }
            }
            if ($differentRows !== []) {
                $count = count($differentRows);
                $positions = implode(', ', array_slice($differentRows, 0, 10));
                $suffix = $count > 10 ? '…' : '';
                $warnings[] = "Đối chiếu tự động phát hiện {$count} dòng có trường được sao chép bị thiếu hoặc khác nguồn đã cung cấp (thứ tự dòng dữ liệu: {$positions}{$suffix}). Hãy kiểm tra nguồn và xác nhận các thay đổi có chủ ý trước khi dùng kết quả.";
            }
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @param  array{header: string, columns: list<string>, rows: array<string, array<string, string>>}  $table
     * @return array<string, array{declared: int, actual: int}>
     */
    private function categorySummary(string $result, array $table): array
    {
        $position = strpos($result, $table['header']);
        if ($position === false) {
            return [];
        }
        $before = substr($result, max(0, $position - 4000), min(4000, $position));
        $afterTable = substr($result, $position + strlen($table['header']));
        $lines = preg_split('/\R/u', ltrim($afterTable, "\r\n")) ?: [];
        while ($lines !== [] && str_contains($lines[0], "\t")) {
            array_shift($lines);
        }
        $after = substr(implode("\n", $lines), 0, 4000);
        foreach ([$before, $after] as $summary) {
            $matched = $this->matchCategoryTotals($summary, $table);
            if (count($matched) >= 2) {
                return $matched;
            }
        }

        return [];
    }

    /**
     * @param  array{header: string, columns: list<string>, rows: array<string, array<string, string>>}  $table
     * @return array<string, array{declared: int, actual: int}>
     */
    private function matchCategoryTotals(string $summary, array $table): array
    {
        preg_match_all('/(?<![\pL\pN_])([\pL][\pL\pN_-]{0,15})\s*(?:=|:)\s*(\d+)(?![\d\/.])/u', $summary, $matches, PREG_SET_ORDER);
        $declared = [];
        foreach ($matches as $match) {
            $declared[$match[1]][(int) $match[2]] = true;
        }
        $matched = [];
        foreach ($declared as $label => $counts) {
            if (count($counts) !== 1) {
                continue;
            }
            $actual = 0;
            foreach ($table['rows'] as $row) {
                if (in_array($label, $row, true)) {
                    $actual++;
                }
            }
            if ($actual > 0) {
                $matched[$label] = ['declared' => (int) array_key_first($counts), 'actual' => $actual];
            }
        }

        return $matched;
    }

    /** @return list<array{header: string, columns: list<string>, rows: array<string, array<string, string>>}> */
    private function tables(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $tables = [];
        for ($i = 0, $count = count($lines); $i < $count - 1; $i++) {
            $columns = array_map('trim', explode("\t", ltrim($lines[$i], "\xEF\xBB\xBF")));
            if (count($columns) < 3 || in_array('', $columns, true) || count(array_unique($columns)) !== count($columns)
                || ! str_contains($lines[$i + 1], "\t")) {
                continue;
            }
            $rows = [];
            $duplicate = false;
            for ($j = $i + 1; $j < $count && str_contains($lines[$j], "\t"); $j++) {
                $values = array_map('trim', explode("\t", $lines[$j]));
                $key = $values[0];
                if ($key === '' || isset($rows[$key])) {
                    $duplicate = true;
                    break;
                }
                $row = [];
                foreach ($columns as $index => $column) {
                    $row[$column] = $values[$index] ?? '';
                }
                $rows[$key] = $row;
            }
            if (! $duplicate && count($rows) >= 2) {
                $tables[] = ['header' => $lines[$i], 'columns' => $columns, 'rows' => $rows];
            }
            $i = max($i, $j - 1);
        }

        return $tables;
    }
}
