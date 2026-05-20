<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [

            /*
            |--------------------------------------------------------------------------
            | COMPANY
            |--------------------------------------------------------------------------
            */

            [
                'key'   => 'company_bio',
                'value' => 'We are building next-gen learning systems.',
            ],

            [
                'key'   => 'company_logo',
                'value' => 'public/uploads/settings/1779261822_logo.png',
            ],

            /*
            |--------------------------------------------------------------------------
            | MOBILE APPS
            |--------------------------------------------------------------------------
            */

            [
                'key'   => 'app_ios_store',
                'value' => 'https://apps.apple.com/app/id123456',
            ],

            [
                'key'   => 'app_ios_download',
                'value' => 'https://yourdomain.com/ios-app.apk',
            ],

            [
                'key'   => 'app_android_store',
                'value' => 'https://play.google.com/store/apps/details?id=com.app',
            ],

            [
                'key'   => 'app_android_download',
                'value' => 'https://yourdomain.com/app.apk',
            ],

            /*
            |--------------------------------------------------------------------------
            | CONTACT
            |--------------------------------------------------------------------------
            */

            [
                'key'   => 'contact_heading',
                'value' => 'Reach out to us',
            ],

            [
                'key'   => 'contact_text',
                'value' => 'Get your questions answered about learning with us.',
            ],

            [
                'key'   => 'contact_phone',
                'value' => '+91 9876543210',
            ],

            [
                'key'   => 'contact_email',
                'value' => 'support@company.com',
            ],

            /*
            |--------------------------------------------------------------------------
            | SOCIAL LINKS
            |--------------------------------------------------------------------------
            */

            [
                'key'   => 'social_facebook',
                'value' => 'https://facebook.com/company',
            ],

            [
                'key'   => 'social_linkedin',
                'value' => 'https://linkedin.com/company/company',
            ],

            [
                'key'   => 'social_instagram',
                'value' => 'https://instagram.com/company',
            ],

            [
                'key'   => 'social_twitter',
                'value' => 'https://x.com/company',
            ],

            /*
            |--------------------------------------------------------------------------
            | FOOTER
            |--------------------------------------------------------------------------
            */

            [
                'key'   => 'footer_text',
                'value' => '© 2025 Avante Medical LMS - v2.1.0  test',
            ],

            /*
            |--------------------------------------------------------------------------
            | ABOUT US
            |--------------------------------------------------------------------------
            */

            [
                'key'   => 'about_us',
                'value' => '<p><strong>About Us</strong></p><p><br></p><p><strong>Our platform is dedicated to providing high-quality digital learning experiences for students, professionals, and organizations.</strong></p><p><br></p><p><strong>Our Mission</strong></p><p><br></p><p><strong>We aim to make learning accessible, engaging, and effective through modern technology and structured educational content.</strong></p><p><br></p><p><strong>What We Offer</strong></p><p><br></p><p>• Interactive learning programs</p><p>• Progress tracking and certifications</p><p>• Expert-designed educational content</p><p>• Flexible online learning experience</p><p><br></p><p>Our Vision</p><p><br></p><p>We believe in empowering learners worldwide by providing innovative and user-friendly educational solutions.</p><p><br></p><p>Contact Information</p><p><br></p><p>For support or business inquiries, please contact our official support team.</p>',
            ],

            /*
            |--------------------------------------------------------------------------
            | PRIVACY POLICY
            |--------------------------------------------------------------------------
            */

            [
                'key'   => 'privacy_policy',
                'value' => '<p><strong>Privacy Policy</strong></p><p><br></p><p><strong>We value your privacy and are committed to protecting your personal information. This Privacy Policy explains how we collect, use, and safeguard your data when you use our platform.</strong></p><p><br></p><p><strong>Information We Collect</strong></p><p><br></p><p>• Name and contact information</p><p>• Email address and account details</p><p>• Learning progress and activity</p><p>• Device and browser information</p><p><br></p><p>How We Use Your Information</p><p><br></p><p>We use your information to provide learning services, improve platform performance, personalize user experience, and communicate important updates.</p><p><br></p><p>Data Protection</p><p><br></p><p>We implement security measures to protect your data from unauthorized access, misuse, or disclosure.</p><p><br></p><p>Third-Party Services</p><p><br></p><p>Some services may be provided through trusted third-party providers. We do not sell your personal information to any third party.</p><p><br></p><p>Contact Us</p><p><br></p><p>If you have any questions regarding this Privacy Policy, please contact our support team.</p>',
            ],

            /*
            |--------------------------------------------------------------------------
            | TERMS & CONDITIONS
            |--------------------------------------------------------------------------
            */

            [
                'key'   => 'terms_conditions',
                'value' => '<p><strong>Terms &amp; Conditions</strong></p><p><br></p><p><strong>By accessing and using this platform, you agree to comply with the following terms and conditions.</strong></p><p><br></p><p><strong>User Responsibilities</strong></p><p><br></p><p><strong>• Provide accurate registration information</strong></p><p><strong>• Maintain the confidentiality of your account</strong></p><p><strong>• Use the platform for lawful purposes only</strong></p><p><br></p><p><strong>Intellectual Property</strong></p><p><br></p><p>All content, materials, logos, and resources available on this platform are the property of the organization and protected by copyright laws.</p><p><br></p><p>Restrictions</p><p><br></p><p>• Do not misuse or attempt to hack the platform</p><p>• Do not copy or redistribute platform content without permission</p><p>• Do not upload harmful or illegal content</p><p><br></p><p>Termination</p><p><br></p><p>We reserve the right to suspend or terminate accounts that violate our policies or terms.</p><p><br></p><p>Changes to Terms</p><p><br></p><p>We may update these Terms &amp; Conditions from time to time. Continued use of the platform means you accept the updated terms.</p>',
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | INSERT / UPDATE SETTINGS
        |--------------------------------------------------------------------------
        */

        foreach ($settings as $setting) {

            Setting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | FIREBASE SERVICE ACCOUNT
        |--------------------------------------------------------------------------
        */

        Setting::updateOrCreate(
            ['key' => 'firebase_service_account'],
            [
                'value' => 'eyJpdiI6IkZvczRzMXR5cmg2WDM1SVhSY1FPUkE9PSIsInZhbHVlIjoiK3BValY1ZldUUTFwN2hhMXFIcWtKR05LRHhkQ0g1MExQQmJQU0JueG5iZ1p3dnl5ZTlNTFNmMWNGc2tORzhXdEJENWdXeUl5Y2cvME01ZUxFMjg3bjFQaC9lVWZUSFJZMXlQeXRBMjBLTGwxQlc4cU9PME9qT1NtSUYzNjVLaUtQSnc3NDBsanVOd0N0Z0RDZEF3UjVEamMyMi9ldUJuOU1ZUjh3S3RtU3MyeVRXejJPclJKQUttWDhuME02MGdvNThDUE5rYlAvY3k4Mkc1enNmZjVhYkgvWlN5bFY4cGxIZi9CekdZeGVqVnRxdWdGc2tFZkZ0WkhYQlVQbHhIWkk5OUVXY0cvZGtiQXdxWFkwTVlHTFJoTCtjYVNVVWRRUDVHaXRDclBLcnB0eEoxQmRJOEcxTzNYWUNBNGhpZGJaZERTenQ4R2UrZzk3VUZtTHJOZnZOR2dhd3BRWTViemZBU21zeHpSSktLYUs0VzlCblNTSUZYNGVUQXhPMktwUTYvMkV5MFpHY3kwNzRReWZGRFpFSXZxTEJBVGRNTEVacm5UZTJrZzNoZkdnbGJiQ09KOG1IZlJjMTdEU3RlNUhWc2pFcEN4ZitRK1ZNMWhDSG1YdVpHUTd3MGxSVUpCbjJtOXJzT2J2aUYrYi9HQ3pmWEpMa0ZPYURlN2hDQkF6SUl1R1A5aUEvTDdEM3pYMCtrV3dnRzJXVmI3UVdwcGIrZ2MzVy9TRldpQ1pTcHU5YkFhRWR2bUNrbjRYUmx6WU5EL21aaHBkM0RPd0V0elRsZXpUcGZMMHo4bk5vTGw4UytQM251clg2VkhhWUM5SHFSa1dFSE16K05Tb2J5RFY4ODcwemJlU2hEYUxqemlSdzY3WTRiQVpyWW1VZi9iRjB6R0syTkpHYUxpcTBseitRVURTSWNnRHdRSWFhaHlYbUNVVDl1c3grVVNIVXA3amY5YVJZVHM1dzJINHhISHVwbGQ4YTl6NFRIcEVjWmRYUWhUKzJicVRrUlFuRWF4Y3NBYkFSSDI4S1Nnblhnd0RqWHg0RG90S2ZLdHZOM1B6V2ViQVhvRlZEZDZxOFcwTnM3TzhJTjJkdXFRSHRFeGhUZDBmVTVPRFF3WDZDK0FzZll4YjVCWnhuWmNnbmR3clZWMFp1N0hHbUltTVpRSC9PYk1EOGNXRnozMnBCV2JrMDVsOUNZb25aUVFTWXlGdnJET01vTWR6YmdmODlRck1BdUN3RnhrLzl2c3VzRmtabjFZYzBiTXdWR3Z6SVRMRVJhT3g2NG9SQjgvNGJPY3c3Wk5GcTJVTVRtQWdOOUhrNGNkb2dVRnhwN01HMmxzaXdNQVh2emJ1UVBRSVJoczFVTStpVjJ4UlQ4UFgvWXVXQkFvV0FnYU1QZ3R3LzVSUHg5TUNWUllNLzdPejRtQW1keWJZa2pmaTZnY0xJbW5BZXh5S1NjNk1BVFdiRWluVUpSY3VCVHppTU9hcVppaHorajlyRm1ScDZ3MThtMGE4bnphTzBRWXUwdmtReVhXWFZBRTBsYy9aVHdrQWd2b3ZQalhKVy9pSzlWMEJsZTA2OUE0ck9Zd3lhOGM5eDlUTW1TQjNoeFpMS1IyZyswUkR2MVVrWVBHajZKZlF1OHJkQUdVbXFvVllJSW5PNUJtSUk2cGlwV2RSbHJlaGRobUR2RWhUN21obXE4bTlDOFR5bUtteG52bTB0SDRzZ1hrN2pvb08yaDJMRUMwdkg4aTh6L0pvRDZ5Yk9CRTFzUXBBWmVYZjhraU4xc1V1L1lUZEhjR0IxR052ZGpUbkJGM2plYk45dEVWYndYcGpNMkl2cVBoMXh2dXdoVE1DZDBrZ20vMFZlUjlKZjc4UGMxMDU3YlRXSUpvaWVmYzkrbFZDamF6Rk04TkVremc1WkhtaGJpVXliWWh0bytmUnkzblFQSWpnQTRxQlF0Zm5QNXliQVVGQ091ZDVkRmpOMVRlMjluNHg3WFAwdmxPTUkwQmMrbVJhUWI2NHRqbHdCaHBnSGZpNkkwenVnZy9neHUvNm92d284S0E0T1RmOVI3NG1ZR3FDZUxKaFp4MHkvVkQ0bUhLTlp6VWFTa1kwUm9vWFg4dFZPRTB4MU55SFJsUTJjeVNpZDBxNzhiMFVEb2E3ajlIZktxN3pjaEl3VHg0ckhVUFZPMmE1Sko5UHBUYzZtTUdsVlE2Z0t6aXVCdVQxUDlFK3BoQ0N6WC94aHBUUENhTFdRWnk0eThMdk1ZNTFIYjBEcHdzWno5UTczZDNia2ZUNU5uSi9PTW4yZWhKTzhaNzVjQ3dOc3VaTHVseFhPME5sU0h1YkdyMkhzVlgrTHRyWklRbnA4UTFtVHdWRi9QZmJtTHVuUWJCdGl3N2x2MkczTENtSnFoRlo2MkViVkllKzdValpsNjhYdjIzYzh2TnRwV3ZDaWtCUXQ1MWtYWitzaDRIVHFKSmFrTTJmTjRyZFl5TFArYkJYUkVUK2V5TDcxUzRxY2JPMnhYTzY3eVhKYy9ERFNyeXVrS2N1cVl6VGY4R2tOc0VFL0svRDFuOWx5bFh5S2gxbUc4Q1JnSDd3eWNsdzg0Sitkc0RCeHJQMzVMd0J6cnJEdy85Mjk1Z2Flb1FZZFhuNTFIMHRHc2xRdE1pNXdCRXpVVUtCcmx2MUMxZG50R3NWSzl4WDZpRGZ2eEllTzNSKzFuVzZ2QWRMZldnRmJJNFlTM2Jsdi84cnE2aEZlai9xWlppNE9kcnluOEFDR3oyRHpwZzZyd0JFcnZ1UnUzZmhJTnY5VlVvcHQxRjFZQWtBeEpDK3ZieWphSFhZcmE1bWxDYUhTYnRteXNicXV4OWdqaGphdVJCOHdmbDdhcGYyYzRJSmJzOTFFZlZpQjVKSzJtT3dkQ1FpaUNnekZyajNrK0g4dk5vUmpBUElDL3kzeXQ1S1J6eEhvdktKNHN0eGpFMnJLYkpxV3FCdnV2cnZxK3d4aDh0WkdSZVlBOTd0Nkh5YnVuaEs2ZEYwNWdkdkFqOHVFVWszV01JN0RzY0M5OUxnbGZobmo0cFJkYlorOGhuMTFlT2NJVTdJUDE2UnQyd2RxNzVHckJaZXNWMVBqTnVjMTBSWld0TWNkdEgwbmZHckRWWHZMZi80anFGcnlNNkF6cVcvMHY2VTRKaDZMWDFIUFlLbFBUdmJXc1RWSGIyNWpZb28wSzJ2UXd6Nmk5aFRkS0xGcVBnUldCZFNKenlBNVlTTlZ3TmNNWGVSdmdIMEdrK3pMZVRFZkVaeVM2VEpWVlNFY3cxcklEUm56QWpYdkY3Q1dsaVVsbXRPc21CdUM5dEtBUEp5NzZSbFB6OFlGTUhHRGxaQWtrNDVXMTEwbEpBYmpKMzBPaHA3VTRXUFN4Yjk2RXArV28xSGpMUmtWTjNVY2l1SnVjUUF5N3VwcnZIK1Z3bGt0ZmFTYnVFZnYxR2hibFNVZzd2djI3QWFhbHk4aTFZNjhpVnFGSTNFdGxtaVlFemFlTW1YZEVTalorOVN4SVdFak5OajZPK3lWK0hmZ0NGaUd5TVhtVVdEUmFUZlpkYS9FRllrZUlPZXY2QWxIS1dxNGExVlo3ZWt6MDhMS1gxUUhLM3gwWllTbHFmbVFQVVhtR1VsVWRWTFFCSFlWa1ZCNGdFT2RWZlQ3SEVKZ2E2czF6OTM5UUxYeTJhVVRSRGNLZnhZN3k3V0YwMVEyenh4SzhqbzlyeXo1Y3BkU3VLdnlFREZ5a29Tek1xYTFpZjh6bVl3djU0REVrcGhOcFJXMVpXM3FZYjg0cjVBdmJUVXZremhVQ3EwV2VIemJyeTBQMi9pRThqTUdNTTMyK0JQT2k0UkF6OUxtb3FGcXVBYjRYcitXZ3M0VDdMbUgvREViNmVCRDlaUW1McWVPLzRlY042STl1aGNIVlMza0pXc2NLR1NzK2tVeUg3L1ZqWXJGYS9xME5Bcm96cmZ3ZldKaEdtai9tR01aRDR2QjRoWWF2ODlZN3cwUUZyR0pNSTdJc1p1QXVMVDBocXRsMkd0bHdLOGtqZ3l5YzJsdS9xa3cwQ1JpNm5ZOERMU01kWU9zUnU3NkRDU25FVmhXOTRGV2lpYndnQ2h2UUNGR0s3cVRRK0UrQkJvaC9HeTg3ajlwYUpDbVRQcE1QRk14Y09taU9VeVdvNFJ6YXRtUXZHQUFyTmtKb0t6MFVTNVM3Y3lSblRSbkt1NDhicnNMSUdwRHZUMjZpdVJPbHprYkhJalc5Y2l2UHIiLCJtYWMiOiI5ZWJiOTdhNTU2N2I3ZmQyY2RlODExZjliYjg2YmNlYjFjNzgyN2ZjYjhiYjc5ZmFmYzhlZTI3ZTBiMDdhMTc4IiwidGFnIjoiIn0='
            ]
        );
    }
}
