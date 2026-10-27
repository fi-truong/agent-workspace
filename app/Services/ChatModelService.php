<?php

namespace App\Services;

class ChatModelService
{
    public const UNITS_PER_CREDIT = 1000;

    /** @return array<string, array{label: string, multiplier: int}> */
    public static function options(): array
    {
        $models = ['gpt-6-luna' => ['label' => 'GPT-6 Luna', 'multiplier' => 1]];
        if (config('openai.sol_enabled', true)) {
            $models['gpt-6-sol'] = ['label' => 'GPT-6 Sol', 'multiplier' => 20];
        }

        return $models;
    }

    public static function defaultModel(): string
    {
        $model = (string) config('openai.model', 'gpt-6-luna');

        return isset(self::options()[$model]) ? $model : 'gpt-6-luna';
    }

    public static function resolve(?string $model): string
    {
        return isset(self::options()[$model]) ? $model : self::defaultModel();
    }

    public static function multiplier(?string $model): int
    {
        // Historical Sol billing remains valid when its picker is disabled.
        return $model === 'gpt-6-sol' || preg_match('/^gpt-6-sol-\d{4}-\d{2}-\d{2}$/', $model ?? '') === 1 ? 20 : 1;
    }

    public static function credits(int $units): float
    {
        return $units / self::UNITS_PER_CREDIT;
    }

    public static function formatCredits(int $units): string
    {
        return rtrim(rtrim(number_format(self::credits($units), 3), '0'), '.');
    }
}
