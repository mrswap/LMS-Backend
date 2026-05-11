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
                'key' => 'company_logo',
                'value' => 'uploads/settings/company-logo.png',
            ],

            [
                'key' => 'company_bio',
                'value' => 'Avante Sales Training App helps medical and sales professionals master product knowledge, assessments, certifications, and field learning through a modern LMS platform.',
            ],

            /*
            |--------------------------------------------------------------------------
            | MOBILE APPS
            |--------------------------------------------------------------------------
            */

            [
                'key' => 'app_ios_store',
                'value' => 'https://apps.apple.com/app/id123456',
            ],

            [
                'key' => 'app_ios_download',
                'value' => 'https://yourdomain.com/downloads/ios-app.apk',
            ],

            [
                'key' => 'app_android_store',
                'value' => 'https://play.google.com/store/apps/details?id=com.avante.salestraining',
            ],

            [
                'key' => 'app_android_download',
                'value' => 'https://yourdomain.com/downloads/android-app.apk',
            ],

            /*
            |--------------------------------------------------------------------------
            | CONTACT
            |--------------------------------------------------------------------------
            */

            [
                'key' => 'contact_heading',
                'value' => 'Reach Out To Us',
            ],

            [
                'key' => 'contact_text',
                'value' => 'Need help with training, assessments, certificates, or onboarding? Our support team is always ready to assist you.',
            ],

            [
                'key' => 'contact_phone',
                'value' => '+91 9876543210',
            ],

            [
                'key' => 'contact_email',
                'value' => 'support@avanteapp.com',
            ],

            /*
            |--------------------------------------------------------------------------
            | SOCIAL LINKS
            |--------------------------------------------------------------------------
            */

            [
                'key' => 'social_facebook',
                'value' => 'https://facebook.com/avanteapp',
            ],

            [
                'key' => 'social_linkedin',
                'value' => 'https://linkedin.com/company/avanteapp',
            ],

            [
                'key' => 'social_instagram',
                'value' => 'https://instagram.com/avanteapp',
            ],

            [
                'key' => 'social_twitter',
                'value' => 'https://x.com/avanteapp',
            ],

            /*
            |--------------------------------------------------------------------------
            | FOOTER
            |--------------------------------------------------------------------------
            */

            [
                'key' => 'footer_text',
                'value' => '© 2026 Avante Sales Training App. All rights reserved.',
            ],

            /*
            |--------------------------------------------------------------------------
            | ABOUT US
            |--------------------------------------------------------------------------
            */

            [
                'key' => 'about_us',
                'value' => '
                <h2>About Avante Sales Training</h2>

                <p>
                    Avante Sales Training App is a next-generation LMS platform
                    designed for healthcare, pharmaceutical, and medical sales teams.
                </p>

                <p>
                    Our platform enables structured learning through multilingual
                    modules, assessments, certifications, and real-time progress tracking.
                </p>

                <p>
                    We focus on delivering scalable digital learning experiences
                    that improve field readiness and product expertise.
                </p>
                ',
            ],

            /*
            |--------------------------------------------------------------------------
            | PRIVACY POLICY
            |--------------------------------------------------------------------------
            */

            [
                'key' => 'privacy_policy',
                'value' => '
                <h2>Privacy Policy</h2>

                <p>
                    We respect your privacy and are committed to protecting
                    your personal information.
                </p>

                <p>
                    User data is securely stored and only used for learning,
                    assessment, analytics, and certification purposes.
                </p>

                <p>
                    We do not sell or share personal information with
                    unauthorized third parties.
                </p>
                ',
            ],

            /*
            |--------------------------------------------------------------------------
            | TERMS & CONDITIONS
            |--------------------------------------------------------------------------
            */

            [
                'key' => 'terms_conditions',
                'value' => '
                <h2>Terms & Conditions</h2>

                <p>
                    By using the Avante Sales Training App, users agree to
                    comply with all training, certification, and assessment policies.
                </p>

                <p>
                    Misuse of content, unauthorized sharing, or fraudulent
                    assessment activity may result in account suspension.
                </p>

                <p>
                    All educational content remains the intellectual property
                    of Avante Sales Training App.
                </p>
                ',
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
        | FIREBASE SERVICE ACCOUNT (PRE-ENCRYPTED)
        |--------------------------------------------------------------------------
        */

        Setting::updateOrCreate(
            ['key' => 'firebase_service_account'],
            [
                'value' => 'eyJpdiI6IktBQjZKeGhWeTFINGViQUp6Y0p4dlE9PSIsInZhbHVlIjoiQTBNenh1cUNhMEZwWUdRZkh4KzVYbjJKS0E5WStyVG5YWk9lZGZvZjVMVkxkQnRkUEJ2QkxRMTN2ak1TNGtPa3pQUitaRTZyZ1dZdnh5T2dpRW9nUTZKbkU2bE4xbG5YejlaQ09xOXptcE1yTEtVREplTFJlR1d4R1UxYXZ0eTl5SCtWNHhRZ2duTUYxbUdkSGw1TFBia0o2cU9zZHFxVlVhTzZ3M2l2VkZ0djdnVGVXc3ZYaFdwbVVzVVJpQjR2emlYeWxKQUFxOHpMQTl6UXBGZVp3cERmZWJHMzA3RzJOd2h2Snl5SDN2dmNqRWYyK3N5NWtlNXA1M2x3aVFJelpyYU15Slk3NTJvd2hFQ1JlWCtqSW9sQWgwaGdLWSt4M2NsNkRtRmFlT0hhN1lDYVdHMEN6Ky9EdWN1cVZmVTgxS0J5RTVNZlV1RzhwRGFPOU1OMlV4dyt3KzJQbUxQcThqemhiK2FBdWxlZzVtbk9BaGxFT2dXNHdtem1GOTE3V0ZIMTNDOVZURUJkVlRzS0dydjhOVnJjTkNhaytZNDdpTnpsa3VNZkw0dm55SDE1YmhqU3hwUUxlTTQ5UlBxVGZ0NlhjN0lhS0ZLTVp3V2U0YkJPSU82QjlTNWk3bFMvcjJKdGVqajUzTVFMd2kxWURXejhxckdzd0prRkpodmVlTS9LbjF5T2xGd0pISmlTSnNtWXFUWUVGblpvSTVYa3FiZGREZFB2WFdZSDByeFJWci80NVg3UGpaWTY1Umg3ZjdGcjdyVS9aRXRwL2k5c3BhRDZEbW9vWlBldWVKRTY2UStRdks1KzRTMzBCN2RjN3BnVjk0eVk1a0pYdjZWWk9tc0pPMjI0RFdSbjlJcFpyalJXMCtzZTZkVjhVS2pONFd3Zm1HbVMvYnJyM3NGaHJISVFyMzhoalNMVm94MzhSQWdSbXNXTjZGcWIreVlRMzVmYmk2ODYzOE9reXR1Uld5bWNtYUFubko1eDhKNE16MzF0OUFYb0M0V29FQ0N0SDVBdmFFVjdQV0VVOUkyakx4NnpYcE41bzNWZm9CelE0L01vaEJJS0l3aitBYndidFhwUDBtQmxVay9PQTY1S082dG10V2FSS0I1Yk82anRyRm0wRTdhdUpSdXlmODV4L2JHSmxXTVFXMjh2eThmRTNDaDF1bFJiaXlBZUt6UjNjNUNHQ3ZSTWszQjd3b2IxZ1hvWXB4a2NiNVpQMmVYVXFzc2U0bElUUC94SVpEazh0VTkrblRvNkc4WnowTU83S1YyaDRSQmQ3UE9xOVFIYVg0Ri9CVVl0UUZ0ZHovSVY1c293WVNhQXBtOXZLa0FwZTVJWG1Sb3llcm1WTklYeENCYzN6T2N1SWlZOHY2SlBGOVJ2SDFBdiszVUpkRHMzMUUydjFlVkVNRjZMVGkvbVJFcXVSeGtQeW5YRXEvWUJmWXFhTVhpVUhUQi9Dc3V5enJiY01sRnY0ZGdnWDR6RWlYYjM5QWlVMStVaW1rSDcrcHdUanJoTmRYNlk4V2tZNEh5NW9NRkh0YVFySWlMSkxvUjlZMkxUNlE5ak00a3NpMWNWU1RPeS9kUGNvbFpNMzY5K01RVU4yMml2UXJiUEw1bmRFenNoZlB3dEtYZ1NuRnl3VXRHZkN4V1dVRENqTSt4eDdsaFl0Qlc5L2Z2aStmbHpEelduZlVja0Z1N1NadkdSdFF4em1BYTU4RGNIdW5Zb3VyRFpwbnovVTY3aGZvMjJMMHI5cnNMMlEyeUhXQ0FUWWlaNTcrSDFNVkEwM2pUTmVYQkFQejVuUW9FWDNab1VTRDhRQURoSHBJOUl0T0dHb2l6ZmZrUi9EQmpwRWNSZEhWa1BoUXllMTZzbVRaUUkyQkFUcmR6TkJld2pQM2FKUHFDaXdhWTgvTFNVVzB6Q3lZbzZMQjYzQVZMWmp3Vms3WkpoVW1UdEpsQUFwS0ZiVllJODhmY01MQmMrTmNYdFpyTTlGcm9DWndSS0NISU5kTG9zQ0NKdlM1OGNkL0cvWTcxelkvU08zenk1OUVMMUNxWERzWnNacW5FSE5EZGE2S2pVNXkwZ1k5c3U3aEczT3Y0Z1lsQ2RpYmN3a1JJWUs2QjRvaUxNMyswVGY1VWIwenlBNUxYWDFpVXlGSlJuMEoxK1pHZzhpcGVtOFNZZmtzUTg5MFQ3QWV2cjhCbFEvSmdUN21XdFk4Tk5HWkFSNU1EOWV5TTV5dUJncmMwUTAzUUhrbGpwdzZxVExDOWUxMDR4cjIreldLQzFZWVJzdHMxeUZkVFVXdG1pelJEZHdiYmlNNmJpcTYwcGhOOGdZYW93d1hsQjk1VnFlT2R6dXI2ZzJ2RVNEYklxVXYxdmVud0RGRFBXNmVlL3BTdDJFR3J1WWcwb1BVN0pHc1o5bjlteUpqZkhpSTFKVDhvQXZ6N2t5UDR2azl1NjhsUFBTTnBadlVvS0E2eHBpTURJWWhDZWZVNUNkR3J2WDV5VVl3RUp1UFA3MUJEU2FJekR3eUlpT2k4ZzFDY284Y3d2YXRheEVLTk9uQ3BScENyYitqeUkrSEo1YTJzMmZTZlZ0THlOdnBiZ1BMNHZieHQzTi9Gb3puZDRGN3Fza1ZnUXFZQWVwTHR4Tkl1QVEwWXIwc2FFdXFhYUd0UGtINVF5WjNpNUd4UTc2WGpCZ1VxS1d6WGRZaElLYzRoMm9DcWhHTGRmQ0srRXFKcUJ3c1pvQUJJSmJXUkVndHdYTENmQ0JBbkZiTm5hL0o4N2QzYzZ2dXFKVURrYkZqeGtWWWROTHpCWUVNY0plNHY2QXcyUFkrMGxJdURkMi93YmQwUFRNY2ltMmVYeHU4UFoxOTVRbzBWajZnNGRwNjJQUWZvcW84ODF0bzJsWEZMb2VCaEZXTDFYRHUxcVkxSlRBUVpUTzROc2w3QTFiUExNK20xU2pIQ2VYOVFCTWV3UERCRFJrKzRTcEtqcnFSK3lneWV4ZHRKTGczUE8zMm5sRzNsUHpXZjFwQlBUMHV2dFV2aXpDckZXekVLcUxJb0QxcU5TVlUwTE81Ym1vdjZBazlHL1FFSkNnOEV3dWNYY1hkZXNlTGNnb21OcG9YeUR2eExKcXlaRjI4ZVZ1ZEFsbmI2TnB6OUtYT3BDRUdxZWhQSDNaM1g2N3N1T2Qvdzl5dUc1SklNdmgva0JTNm1SRTFhU3RmZzY5QURpdGcwa2RjSHE1cDZMRHEwTHdxVC95R2grVFBBdWNWS1dFcU5nSExEbWNxNW8yVElsd0IxWnBlZkxRSTVoRjRLdnpSZzhRNms2SE1ZN1BJMGN1ZXQvRUFFQmxFMEU5Q1E5V0NoMEJMTE9MY3BodkVLdjIvT1BFcXp5RzNoZ1FEOVJxMENJUzd1UWxwREJ0aUJpa0xpZWtlN0U3WnZGc1dtdTFuQzZjNllRbHpPVXFyQlZtZVJXU1I1a3JlazEzR2d1UXplU0tpZEhySHBYVHJCZkh0K2tSbUwvL09KWW9Ecmp5Uy9nUlJaMG80L2Q5K0JWOTJ3MVhxczN3c2E4cEw2OTN1Ukc1cklHell6dGs3dndhc2tmK1AycHBvdFVkUTVtYXpiQTVuamdmT1c5Wm0yemoyNUpuYjRhZExpclNHajBFaHZqa1ZXekpVRmIxRVRWUWhQOGhqanpRYUtVaGZUdGh3cms4bURRNlF1TVJVSS9MSHQrR2Q0TkVZL0tsS0ViU1RmYndHZXd6T1BKRmx6S0hKdGtZVHNrbFIzdlBmb29WeFFwTkwvOWppV1RhL2c3UnZlekFrSWFuQ1pUYUVSV2tlNjFhSzlzK2FKYStuMHNLNDRuMml1ckp5Q3hZOHpwSXVwK3V1enZCSFBFQ21VZEtTTXBtbnJvcWoxM0p4NERMZzNPTng2YUFRZm4vYjdXU0RCeS9xSWl4MWpVUzIwcHRUd0Fhby9SOWNpWDVjT3ZKSXowcktXZmp6UjgzSUhwSDNKd2lHaEVWTDVxNkk2Zjg0OXVzVzJxMG5GTC9DMVpGQ3p5MG5BN1hiUjQ1OXk3NGUrUVhWTUtLbDVhVWhMYjZUcmdkLzVLZEVqbXNUT2o2eC9WdjRRK0dwWnNaYVJpMWsrWldGR0VrSE1RQ3BwSmY5T3A4ZFJqOGJKSHpkQXNJdHc1bGhWNEFGMUJiY0JIMW1LbDNjWE9XaEtVaVhmU241T3duSHdaQ2U4TXpic1FFUGo2anhQM2ZwNUgrcGc9IiwibWFjIjoiMWM3MzhlMzIzMTVhMDBkOGRlNDIzMjg0ZTZiNGNmODhiYmFhOWY5YWI3YWY4ODgwNThmMzY1NTJkYjE4MWExYiIsInRhZyI6IiJ9'
            ]
        );
    }
}
