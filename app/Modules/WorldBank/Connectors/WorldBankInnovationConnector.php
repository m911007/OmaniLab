<?php

namespace App\Modules\WorldBank\Connectors;

final class WorldBankInnovationConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.innovation';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: البحث والابتكار ومخرجات التعلم';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: research, innovation and learning outcomes';
    }

    protected function section(): string
    {
        return 'digital';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::level('IP.JRN.ARTC.SC', 'المقالات العلمية والتقنية', 'Scientific and technical journal articles', 'مقال'),
            WbIndicator::value('SP.POP.SCIE.RD.P6', 'الباحثون لكل مليون نسمة', 'Researchers in R&D per million people', 'باحث', 1e6),
            WbIndicator::value('IT.NET.SECR.P6', 'خوادم الإنترنت الآمنة لكل مليون نسمة', 'Secure Internet servers per million people', 'خادم', 1e8),
            WbIndicator::value('HD.HCI.LAYS', 'سنوات الدراسة المعدَّلة بالتعلم', 'Learning-adjusted years of school', 'سنة', 20),
            WbIndicator::value('LO.TIMSS.MAT8', 'نتيجة TIMSS في الرياضيات (الصف الثامن)', 'TIMSS mathematics score, grade 8', 'درجة', 1000),
            WbIndicator::level('CM.MKT.LDOM.NO', 'الشركات المحلية المدرجة', 'Listed domestic companies', 'شركة'),
            WbIndicator::share('SL.UEM.NEET.ZS', 'الشباب خارج التعليم والعمل والتدريب (البنك الدولي)', 'Youth not in education, employment or training', '%'),
        ];
    }
}
