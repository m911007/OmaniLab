<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // الاختبارات مغلقة: أي طلب HTTP لم يُزيَّف صراحةً يفشل بدل أن يخرج للإنترنت الحقيقي.
        Http::preventStrayRequests();
    }

    /** يبدأ مصنع HTTP جديدًا (Http::fake يراكم الردود والأول يغلب) مع بقاء منع الطلبات الشاردة. */
    protected function freshHttp(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
    }
}
