<?php

test('ai defaults are configured for openrouter gemma', function (): void {
    expect(config('ai.default'))->toBe('openrouter');
    expect(config('ai.providers.openrouter.driver'))->toBe('openrouter');
    expect(config('ai.providers.openrouter.models.text.default'))
        ->toBe('google/gemma-4-31b-it');
    expect(config('ai.providers.openrouter.models.text.cheapest'))
        ->toBe('google/gemma-4-31b-it');
    expect(config('ai.providers.openrouter.models.text.smartest'))
        ->toBe('google/gemma-4-31b-it');
});
