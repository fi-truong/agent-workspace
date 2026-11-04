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
