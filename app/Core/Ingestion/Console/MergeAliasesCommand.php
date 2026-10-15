<?php

namespace App\Core\Ingestion\Console;

use App\Core\Ingestion\AliasMerger;
use Illuminate\Console\Command;

class MergeAliasesCommand extends Command
{
    protected $signature = 'entities:merge-aliases';

    protected $description = 'دمج الكيانات ذات الرموز الشاذة في رموزها الموحَّدة (بدور المالك)';

    public function handle(AliasMerger $merger): int
    {
        $this->info('كيانات مدموجة: '.$merger->merge());

        return self::SUCCESS;
    }
}
