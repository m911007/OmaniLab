<?php

namespace App\Modules\Gcc\Connectors;

/** السكان والعمل والتعليم والصحة. */
final class GccStatSocietyConnector extends GccStatConnector
{
    protected function datasetKey(): string
    {
        return 'gccstat.society';
    }

    protected function datasetNameAr(): string
    {
        return 'المجتمع في دول الخليج: السكان والعمل والتعليم والصحة';
    }

    protected function datasetNameEn(): string
    {
        return 'GCC society: population, labour, education, health';
    }

    protected function section(): string
    {
        return 'society';
    }

    protected function intervalMinutes(): int
    {
        return 3 * 24 * 60;
    }

    protected function flows(): array
    {
        return [
            GccFlow::level('MOBILE_DF_POP', 'POP', 'عدد السكان', 'Population', 'نسمة'),
            GccFlow::level('MOBILE_DF_POP_CITIZEN', 'POP_CITIZEN', 'عدد المواطنين', 'Citizen population', 'نسمة'),
            GccFlow::level('MOBILE_DF_POP_NON_CITIZEN', 'POP_NON_CITIZEN', 'عدد غير المواطنين', 'Non-citizen population', 'نسمة'),
            GccFlow::level('MOBILE_DF_LAB_EMPLOYEES', 'EMPLOYED', 'عدد العاملين', 'Number of employed', 'شخص'),
            GccFlow::level('MOBILE_DF_LAB_EMPLOYEES_CITIZEN', 'EMPLOYED_CITIZEN', 'عدد العاملين من المواطنين', 'Citizen employees', 'شخص'),
            GccFlow::percent('MOBILE_DF_LAB_PART_RATE', 'LABOUR_PARTICIPATION', 'معدل المشاركة في القوى العاملة', 'Labour force participation rate', 'A', 0, 100),
            GccFlow::percent('MOBILE_DF_SDG08_8_5_2', 'UNEMPLOYMENT', 'معدل البطالة', 'Unemployment rate', 'A', 0, 100),
            GccFlow::level('MOBILE_DF_EDU_STUDENT_EDU', 'STUDENTS_SCHOOL', 'طلبة التعليم المدرسي', 'School students', 'طالب'),
            GccFlow::level('MOBILE_DF_EDU_STUDENT_UNI', 'STUDENTS_UNI', 'طلبة التعليم العالي', 'Higher-education students', 'طالب'),
            GccFlow::level('MOBILE_DF_EDU_SCHOOL', 'SCHOOLS', 'عدد المدارس', 'Number of schools', 'مدرسة'),
            GccFlow::level('MOBILE_DF_HTH_HOSPITAL', 'HOSPITALS', 'عدد المستشفيات', 'Number of hospitals', 'مستشفى'),
            GccFlow::level('MOBILE_DF_HTH_PHYSICIANS', 'PHYSICIANS', 'عدد الأطباء', 'Number of physicians', 'طبيب'),
            GccFlow::level('MOBILE_DF_HTH_NURSES', 'NURSES', 'عدد الممرضين', 'Number of nurses', 'ممرض'),
        ];
    }
}
