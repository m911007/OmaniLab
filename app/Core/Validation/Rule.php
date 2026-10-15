<?php

namespace App\Core\Validation;

interface Rule
{
    public function name(): string;

    /**
     * وصف المعاملات (يُحفظ في تعريف المؤشر للشفافية).
     *
     * @return array<string, mixed>
     */
    public function describe(): array;

    /** @return iterable<Issue> */
    public function check(ValidationContext $context): iterable;
}
