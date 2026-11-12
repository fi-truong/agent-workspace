<?php

use App\Services\TabularResultValidator;

it('flags copied tabular fields that differ from a complete supplied source', function () {
    $source = "[UNTRUSTED FILE DATA: scores.txt | extraction complete | handoff complete content supplied | full source]\n"
        ."record\titem_a\titem_b\nA\t1\t0\nB\t0\t1\n[/UNTRUSTED DATA]";
    $output = "record\titem_a\titem_b\tgroup\nA\t1\t0\tx\nB\t1\t0\ty";

    expect(app(TabularResultValidator::class)->warnings($source, $output))->toHaveCount(1)
        ->and(app(TabularResultValidator::class)->warnings($source, $output)[0])->toContain('1 dòng')->toContain('dòng dữ liệu: 2');
});

it('does not invent discrepancies for unchanged numeric data or non-tabular output', function () {
    $source = "[UNTRUSTED FILE DATA: source.txt | extraction complete | handoff complete content supplied | full source]\n"
        ."record\titem_a\titem_b\nA\t1\t0\nB\t0\t1\n[/UNTRUSTED DATA]";

    expect(app(TabularResultValidator::class)->warnings($source, "record\titem_a\titem_b\nA\t1.0\t0\nB\t0\t1"))->toBe([])
        ->and(app(TabularResultValidator::class)->warnings($source, 'A general explanation without a table.'))->toBe([]);
});

it('does not select an arbitrary source when different source tables are equally plausible', function () {
    $source = "[UNTRUSTED FILE DATA: first.txt | extraction complete | handoff complete content supplied | full source]\n"
        ."record\ta\tb\nA\t1\t0\nB\t0\t1\n[/UNTRUSTED DATA]\n"
        ."[UNTRUSTED FILE DATA: second.txt | extraction complete | handoff complete content supplied | full source]\n"
        ."record\ta\tb\nA\t0\t0\nB\t0\t1\n[/UNTRUSTED DATA]";

    expect(app(TabularResultValidator::class)->warnings($source, "record\ta\tb\nA\t1\t0\nB\t0\t1"))->toBe([]);
});

it('checks reported category totals against the rows even without a comparable source table', function () {
    $table = "id\tvalue\tcategory\na\t1\tblue\nb\t2\tblue\nc\t3\tred";
    $validator = app(TabularResultValidator::class);

    expect($validator->warnings('', "blue=1, red=2\n\n".$table))->toHaveCount(1)
        ->and($validator->warnings('', "blue=1, red=2\n\n".$table)[0])->toContain('blue: nêu 1, đếm trong bảng 2')
        ->and($validator->warnings('', $table."\n\nblue: 1; red: 2"))->toHaveCount(1)
        ->and($validator->warnings('', "blue=2, red=1\n\n".$table))->toBe([]);
});

it('normalizes a mismatched count summary from one complete table when no other warning exists', function () {
    $validator = app(TabularResultValidator::class);
    $result = "id\tvalue\tnote\tgroup\nA\t1\tx\tG0\nB\t2\tx\tG1\nC\t3\tx\tG2\nD\t4\tx\tG3\n\nTotals: G0=2, G1=0, G2=1, G3=1";

    $normalized = $validator->normalizeSummaryCountsFromRows('', $result);

    expect($normalized)
        ->toContain('G0=1, G1=1, G2=1, G3=1')
        ->not->toContain('G0=2')
        ->and($validator->warnings('', (string) $normalized))->toBe([]);
});

it('does not normalize count summaries when another table warning is present', function () {
    $source = "[UNTRUSTED FILE DATA: source.txt | extraction complete | handoff complete content supplied | full source]\n"
        ."id\tvalue\tnote\nA\t1\tx\nB\t2\ty\n[/UNTRUSTED DATA]";
    $result = "id\tvalue\tnote\tgroup\nA\t0\tx\tG0\nB\t2\ty\tG1\n\nTotals: G0=0, G1=2";

    expect(app(TabularResultValidator::class)->normalizeSummaryCountsFromRows($source, $result))->toBeNull();
});

it('normalizes counts for the only categorized table while retaining an unrelated source table', function () {
    $sourceTable = "record\titem_a\titem_b\nA\t1\t0\nB\t0\t1\nC\t1\t1";
    $analysisTable = "record\tvalue\tcategory\nA\t1\tblue\nB\t2\tblue\nC\t3\tred";
    $result = $sourceTable."\n\nAnalysis\n".$analysisTable."\n\nblue=1; red=2";

    expect(app(TabularResultValidator::class)->normalizeSummaryCountsFromRows('', $result))
        ->toBe($sourceTable."\n\nAnalysis\n".$analysisTable."\n\nblue=2; red=1");
});

it('normalizes consistent repeated summaries without changing any table row', function () {
    $table = "record\tvalue\tcategory\nA\t1\tblue\nB\t2\tblue\nC\t3\tred";
    $result = "blue=1; red=2\n\n".$table."\n\nblue=1; red=2";

    expect(app(TabularResultValidator::class)->normalizeSummaryCountsFromRows('', $result))
        ->toBe("blue=2; red=1\n\n".$table."\n\nblue=2; red=1");
});

it('does not normalize ambiguous category tables', function () {
    $first = "record\tvalue\tcategory\nA\t1\tblue\nB\t2\tblue\nC\t3\tred";
    $second = "record\tvalue\tcategory\nX\t1\tblue\nY\t2\tblue\nZ\t3\tred";

    expect(app(TabularResultValidator::class)->normalizeSummaryCountsFromRows('', $first."\n\n".$second."\n\nblue=1; red=2"))->toBeNull();
});

it('preserves summary-like values inside table cells while correcting prose counts', function () {
    $table = "record\tnote\tcategory\nA\tblue=1\tblue\nB\tother\tblue\nC\tother\tred";

    expect(app(TabularResultValidator::class)->normalizeSummaryCountsFromRows('', $table."\n\nblue=1; red=2"))
        ->toBe($table."\n\nblue=2; red=1");
});

it('accepts a complete corrected table only after checking its source and category totals again', function () {
    $source = "[UNTRUSTED FILE DATA: records.txt | extraction complete | handoff complete content supplied | full source]\n"
        ."record\titem_a\titem_b\nA\t1\tCHUA_CO\nB\t0\t1\nC\t1\t1\n[/UNTRUSTED DATA]";
    $original = "record\titem_a\titem_b\tgroup\nA\t1\t0\tred\nB\t1\t0\tblue\nC\t1\t1\tblue\n\nblue=1; red=2";
    $replacement = "record\titem_a\titem_b\tgroup\nC\t1\t1\tblue\nB\t0\t1\tblue\nA\t1\tCHUA_CO\tred\n\nblue=2; red=1";

    expect(app(TabularResultValidator::class)->replacementWarnings($source, $original, $replacement))->toBe([]);
});

it('rejects a table correction that drops evidence or retains inconsistencies', function (string $replacement) {
    $source = "[UNTRUSTED FILE DATA: records.txt | extraction complete | handoff complete content supplied | full source]\n"
        ."record\titem_a\titem_b\nA\t1\tCHUA_CO\nB\t0\t1\nC\t1\t1\n[/UNTRUSTED DATA]";
    $original = "record\titem_a\titem_b\tgroup\nA\t1\t0\tred\nB\t1\t0\tblue\nC\t1\t1\tblue\n\nblue=1; red=2";

    expect(app(TabularResultValidator::class)->replacementWarnings($source, $original, $replacement))->not->toBe([]);
})->with([
    'no table' => 'Everything has been corrected.',
    'missing row' => "record\titem_a\titem_b\tgroup\nA\t1\tCHUA_CO\tred\nB\t0\t1\tblue\n\nblue=1; red=1",
    'duplicate row' => "record\titem_a\titem_b\tgroup\nA\t1\tCHUA_CO\tred\nB\t0\t1\tblue\nB\t0\t1\tblue\n\nblue=2; red=1",
    'missing column' => "record\titem_a\tgroup\nA\t1\tred\nB\t0\tblue\nC\t1\tblue\n\nblue=2; red=1",
    'wrong source value' => "record\titem_a\titem_b\tgroup\nA\t1\t0\tred\nB\t0\t1\tblue\nC\t1\t1\tblue\n\nblue=2; red=1",
    'wrong summary' => "record\titem_a\titem_b\tgroup\nA\t1\tCHUA_CO\tred\nB\t0\t1\tblue\nC\t1\t1\tblue\n\nblue=1; red=2",
    'missing summary' => "record\titem_a\titem_b\tgroup\nA\t1\tCHUA_CO\tred\nB\t0\t1\tblue\nC\t1\t1\tblue",
    'extra row' => "record\titem_a\titem_b\tgroup\nA\t1\tCHUA_CO\tred\nB\t0\t1\tblue\nC\t1\t1\tblue\nD\t1\t1\tblue\n\nblue=3; red=1",
]);

it('accepts an explicitly reported zero category after a complete correction', function () {
    $original = "record\tvalue\tgroup\nA\t1\tred\nB\t2\tblue\n\nred=2; blue=0";
    $replacement = "record\tvalue\tgroup\nA\t1\tblue\nB\t2\tblue\n\nred=0; blue=2";

    expect(app(TabularResultValidator::class)->replacementWarnings('', $original, $replacement))->toBe([]);
});
