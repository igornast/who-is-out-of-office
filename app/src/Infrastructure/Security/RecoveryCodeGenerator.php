<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

class RecoveryCodeGenerator
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';
    private const CODE_COUNT = 8;
    private const HALF_LENGTH = 4;

    /**
     * @return string[]
     */
    public function generate(): array
    {
        $codes = [];

        while (count($codes) < self::CODE_COUNT) {
            $code = sprintf('%s-%s', $this->randomHalf(), $this->randomHalf());

            if (!in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    private function randomHalf(): string
    {
        $half = '';
        $maxIndex = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::HALF_LENGTH; ++$i) {
            $half .= self::ALPHABET[random_int(0, $maxIndex)];
        }

        return $half;
    }
}
