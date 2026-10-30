<?php

declare(strict_types=1);

function containsCurrentLeaveBalanceMutation(string $contents): bool
{
    $searchableContents = '';
    foreach (token_get_all($contents) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
            $searchableContents .= str_repeat("\n", substr_count($token[1], "\n"));

            continue;
        }

        $searchableContents .= is_array($token) ? $token[1] : $token;
    }

    return 1 === preg_match(
        '/(?:\+\+|--)\s*\$\w+(?:\s*->\s*\w+)*\s*->currentLeaveBalance\b|->currentLeaveBalance\s*(?:\+\+|--|(?:\?\?|\*\*|<<|>>|[+\-*\/.%&|^])?=(?!=|>))/',
        $searchableContents,
    );
}

function containsDedicatedBalanceWriterReference(string $contents): bool
{
    $searchableContents = '';
    foreach (token_get_all($contents) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
            continue;
        }

        $searchableContents .= is_array($token) ? $token[1] : $token;
    }

    return str_contains($searchableContents, 'UserBalanceWriter');
}

function containsBalanceColumnSqlWrite(string $contents): bool
{
    return 1 === preg_match(
        '/(?:UPDATE\s+[^;]*(?:\bSET\b[^;]*|)current_leave_balance\s*=|ON\s+DUPLICATE\s+KEY\s+UPDATE\s+[^;]*current_leave_balance\s*=|->set\(\s*[\'\"](?:\w+\.)?current(?:LeaveBalance|_leave_balance)[\'\"]|->update\(\s*[\'\"][^\'\"]+[\'\"]\s*,\s*\[[^\]]*[\'\"]current_leave_balance[\'\"]\s*=>|REPLACE\s+INTO\s+[^;]*current_leave_balance)/is',
        $contents,
    );
}

function containsAbsenceBalanceResetDayMutation(string $contents): bool
{
    $searchableContents = '';
    foreach (token_get_all($contents) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
            $searchableContents .= str_repeat("\n", substr_count($token[1], "\n"));

            continue;
        }

        $searchableContents .= is_array($token) ? $token[1] : $token;
    }

    return 1 === preg_match(
        '/->absenceBalanceResetDay\s*(?:\+\+|--|(?:\?\?|\*\*|<<|>>|[+\-*\/.%&|^])?=(?!=|>))/',
        $searchableContents,
    );
}

function containsAbsenceBalanceResetDaySqlWrite(string $contents): bool
{
    return 1 === preg_match(
        '/(?:UPDATE\s+[^;]*(?:\bSET\b[^;]*|)absence_balance_reset_day\s*=|ON\s+DUPLICATE\s+KEY\s+UPDATE\s+[^;]*absence_balance_reset_day\s*=|->set\(\s*[\'\"](?:\w+\.)?absence(?:BalanceResetDay|_balance_reset_day)[\'\"]|->update\(\s*[\'\"][^\'\"]+[\'\"]\s*,\s*\[[^\]]*[\'\"]absence_balance_reset_day[\'\"]\s*=>|REPLACE\s+INTO\s+[^;]*absence_balance_reset_day)/is',
        $contents,
    );
}

function containsNamedCurrentLeaveBalanceArgument(string $contents): bool
{
    $searchableContents = '';
    foreach (token_get_all($contents) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
            $searchableContents .= str_repeat("\n", substr_count($token[1], "\n"));

            continue;
        }

        $searchableContents .= is_array($token) ? $token[1] : $token;
    }

    return 1 === preg_match('/\bcurrentLeaveBalance\s*:(?!:)/', $searchableContents);
}

it('only allows the balance funnel and dedicated writer repository to assign currentLeaveBalance', function (): void {
    $allowlist = [
        srcDirectory('/Module/User/UseCase/Command/RecordBalanceChangeCommandHandler.php'),
        srcDirectory('/Infrastructure/Doctrine/Repository/UserBalanceWriterRepository.php'),
    ];

    $violations = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(srcDirectory(''), FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if ('php' !== $file->getExtension()) {
            continue;
        }

        if (in_array($file->getPathname(), $allowlist, true)) {
            continue;
        }

        if (containsCurrentLeaveBalanceMutation((string) file_get_contents($file->getPathname()))) {
            $violations[] = str_replace(srcDirectory(''), '', $file->getPathname());
        }
    }

    expect($violations)->toBe([]);
});

it('only allows the balance funnel to consume the dedicated balance writer', function (): void {
    $allowlist = [
        srcDirectory('/Module/User/Repository/UserBalanceWriterInterface.php'),
        srcDirectory('/Module/User/UseCase/Command/RecordBalanceChangeCommandHandler.php'),
        srcDirectory('/Module/User/UseCase/Command/OpenInitialLeavePeriodCommandHandler.php'),
        srcDirectory('/Module/User/UseCase/Command/ResetAbsenceBalanceCommandHandler.php'),
        srcDirectory('/Infrastructure/Doctrine/Repository/UserBalanceWriterRepository.php'),
    ];
    $violations = [];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(srcDirectory(''), FilesystemIterator::SKIP_DOTS)
    );

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if ('php' !== $file->getExtension() || in_array($file->getPathname(), $allowlist, true)) {
            continue;
        }

        if (containsDedicatedBalanceWriterReference((string) file_get_contents($file->getPathname()))) {
            $violations[] = str_replace(srcDirectory(''), '', $file->getPathname());
        }
    }

    expect($violations)->toBe([]);
});

it('detects both interface and concrete dedicated writer consumers', function (string $source): void {
    expect(containsDedicatedBalanceWriterReference(sprintf('<?php %s', $source)))->toBeTrue();
})->with([
    'use App\Module\User\Repository\UserBalanceWriterInterface;',
    'use App\Infrastructure\Doctrine\Repository\UserBalanceWriterRepository;',
]);

it('ignores dedicated writer names in comments and strings', function (string $source): void {
    expect(containsDedicatedBalanceWriterReference(sprintf('<?php %s', $source)))->toBeFalse();
})->with([
    '// UserBalanceWriterInterface',
    '"UserBalanceWriter";',
]);

it('detects every PHP assignment and increment form for the balance property', function (string $mutation): void {
    expect(containsCurrentLeaveBalanceMutation(sprintf('<?php %s', $mutation)))->toBeTrue();
})->with([
    '$dto->currentLeaveBalance = 1;',
    '$dto->currentLeaveBalance += 1;',
    '$dto->currentLeaveBalance -= 1;',
    '$dto->currentLeaveBalance *= 2;',
    '$dto->currentLeaveBalance /= 2;',
    '$dto->currentLeaveBalance %= 2;',
    '$dto->currentLeaveBalance **= 2;',
    '$dto->currentLeaveBalance .= "1";',
    '$dto->currentLeaveBalance &= 1;',
    '$dto->currentLeaveBalance |= 1;',
    '$dto->currentLeaveBalance ^= 1;',
    '$dto->currentLeaveBalance <<= 1;',
    '$dto->currentLeaveBalance >>= 1;',
    '$dto->currentLeaveBalance ??= 1;',
    '$dto->currentLeaveBalance++;',
    '$dto->currentLeaveBalance--;',
    '++$dto->currentLeaveBalance;',
    '--$this->dto->currentLeaveBalance;',
]);

it('ignores balance reads comparisons comments and string literals', function (string $source): void {
    expect(containsCurrentLeaveBalanceMutation(sprintf('<?php %s', $source)))->toBeFalse();
})->with([
    '$balance = $dto->currentLeaveBalance;',
    '$dto->currentLeaveBalance === 1;',
    '$dto->currentLeaveBalance == 1;',
    '$dto->currentLeaveBalance >= 1;',
    '// $dto->currentLeaveBalance = 1;',
    '"$dto->currentLeaveBalance = 1";',
]);

it('keeps current balance fields out of CRUD forms', function (): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(srcDirectory(''), FilesystemIterator::SKIP_DOTS));

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if ('php' !== $file->getExtension() || !str_ends_with($file->getFilename(), 'CrudController.php')) {
            continue;
        }

        preg_match_all(
            '/[A-Za-z_][A-Za-z0-9_\\\\]*::new\(\s*[\'\"]currentLeaveBalance[\'\"][\s,)]*[\s\S]*?;/',
            (string) file_get_contents($file->getPathname()),
            $matches,
        );

        foreach ($matches[0] as $field) {
            expect($field)->toMatch('/->(?:hideOnForm|onlyOnIndex|onlyOnDetail)\s*\(/');
        }
    }
});

it('only allows the dedicated writer repository to write current balance columns in SQL', function (): void {
    $allowlist = [
        srcDirectory('/Infrastructure/Doctrine/Repository/UserBalanceWriterRepository.php'),
    ];
    $violations = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(srcDirectory(''), FilesystemIterator::SKIP_DOTS));

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if ('php' !== $file->getExtension() || in_array($file->getPathname(), $allowlist, true)) {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());
        if (containsBalanceColumnSqlWrite($contents)) {
            $violations[] = str_replace(srcDirectory(''), '', $file->getPathname());
        }
    }

    expect($violations)->toBe([]);
});

it('detects aliased and upsert balance column SQL writes', function (string $source): void {
    expect(containsBalanceColumnSqlWrite($source))->toBeTrue();
})->with([
    'UPDATE user u SET u.current_leave_balance = 1',
    'INSERT INTO user (...) VALUES (...) ON DUPLICATE KEY UPDATE current_leave_balance = VALUES(current_leave_balance)',
    '\$queryBuilder->set(\'u.currentLeaveBalance\', :balance)',
    "\$connection->update('user', ['current_leave_balance' => 1])",
    "\$queryBuilder->set('current_leave_balance', :balance)",
    'REPLACE INTO user (id, current_leave_balance) VALUES (1, 1)',
]);

it('only allows the user creation path to construct current balance named arguments', function (): void {
    $allowlist = [
        srcDirectory('/Module/Admin/Controller/UserCrudController.php'),
        srcDirectory('/Shared/DTO/UserDTO.php'),
    ];
    $violations = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(srcDirectory(''), FilesystemIterator::SKIP_DOTS));

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if ('php' !== $file->getExtension() || in_array($file->getPathname(), $allowlist, true)) {
            continue;
        }

        if (containsNamedCurrentLeaveBalanceArgument((string) file_get_contents($file->getPathname()))) {
            $violations[] = str_replace(srcDirectory(''), '', $file->getPathname());
        }
    }

    expect($violations)->toBe([]);
});

it('detects current balance named arguments while ignoring comments and strings', function (string $source, bool $expected): void {
    expect(containsNamedCurrentLeaveBalanceArgument(sprintf('<?php %s', $source)))->toBe($expected);
})->with([
    ['currentLeaveBalance : 1', true],
    ['// currentLeaveBalance : 1', false],
    ['"currentLeaveBalance: 1"', false],
]);

it('only allows the balance writer and legitimate user construction paths to mutate reset days', function (): void {
    $allowlist = [
        srcDirectory('/Infrastructure/Doctrine/Entity/User.php'),
        srcDirectory('/Infrastructure/Doctrine/Repository/UserBalanceWriterRepository.php'),
        srcDirectory('/Module/User/UseCase/Command/OpenInitialLeavePeriodCommandHandler.php'),
        srcDirectory('/Module/User/UseCase/Command/RecordBalanceChangeCommandHandler.php'),
        srcDirectory('/Module/User/UseCase/Command/ResetAbsenceBalanceCommandHandler.php'),
        srcDirectory('/Shared/DTO/UserDTO.php'),
    ];
    $violations = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(srcDirectory(''), FilesystemIterator::SKIP_DOTS));

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if ('php' !== $file->getExtension() || in_array($file->getPathname(), $allowlist, true)) {
            continue;
        }

        if (containsAbsenceBalanceResetDayMutation((string) file_get_contents($file->getPathname()))) {
            $violations[] = str_replace(srcDirectory(''), '', $file->getPathname());
        }
    }

    expect($violations)->toBe([]);
});

it('only allows the balance writer repository to write reset day columns in SQL', function (): void {
    $allowlist = [srcDirectory('/Infrastructure/Doctrine/Repository/UserBalanceWriterRepository.php')];
    $violations = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(srcDirectory(''), FilesystemIterator::SKIP_DOTS));

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if ('php' !== $file->getExtension() || in_array($file->getPathname(), $allowlist, true)) {
            continue;
        }

        if (containsAbsenceBalanceResetDaySqlWrite((string) file_get_contents($file->getPathname()))) {
            $violations[] = str_replace(srcDirectory(''), '', $file->getPathname());
        }
    }

    expect($violations)->toBe([]);
});

it('detects reset day mutation and SQL bypass forms', function (string $source, string $detector): void {
    expect($detector($source))->toBeTrue();
})->with([
    ['$dto->absenceBalanceResetDay = $day;', 'containsAbsenceBalanceResetDayMutation'],
    ["\$connection->update('user', ['absence_balance_reset_day' => :day])", 'containsAbsenceBalanceResetDaySqlWrite'],
]);
